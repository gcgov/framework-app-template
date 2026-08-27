<?php

declare(strict_types=1);

namespace app\tests\Unit;

use gcgov\framework\models\unifiedConfig;
use gcgov\framework\services\environment\configLoader;
use gcgov\framework\services\environment\environmentException;
use PHPUnit\Framework\TestCase;

/**
 * Pins the contract of the committed config.json.
 *
 * Every %env() reference is required, so the failure mode this guards against is a clean
 * checkout — or the image built from it — refusing to boot because a variable nobody
 * documented is missing. The manifest is derived from config.json itself, so this test
 * asserts the derivation rather than a hand-written list.
 */
final class ConfigFilesTest extends TestCase {

	private const string ROOT = __DIR__ . '/../..';

	/** The values a developer supplies. Deliberately written out, so adding a required reference fails here first. */
	private const array DEV_ENVIRONMENT = [
		'APP_TYPE'                  => 'local',
		'APP_ROOT_URL'              => 'http://localhost:8080',
		'APP_BASE_PATH'             => '/',
		'APP_REDIRECT_AFTER_LOGIN'  => 'http://localhost:5173/auth/sign-in',
		'APP_REDIRECT_AFTER_LOGOUT' => 'http://localhost:5173/auth/sign-out',
		'MONGO_DATABASE'            => 'app',
		'MONGO_URI'                 => 'mongodb://mongodb:27017',
	];

	/** @var array<string, string> */
	private array $envSnapshot = [];


	protected function setUp(): void {
		$this->envSnapshot = $_ENV;
		foreach( self::DEV_ENVIRONMENT as $name => $value ) {
			$this->setEnv( $name, $value );
		}
	}


	protected function tearDown(): void {
		foreach( array_keys( $_ENV ) as $key ) {
			if( !array_key_exists( $key, $this->envSnapshot ) ) {
				putenv( $key );
				unset( $_SERVER[ $key ] );
			}
		}
		$_ENV = $this->envSnapshot;
	}


	private function setEnv( string $name, string $value ): void {
		$_ENV[ $name ] = $value;
		putenv( $name . '=' . $value );
	}


	private function clearEnv( string $name ): void {
		unset( $_ENV[ $name ], $_SERVER[ $name ] );
		putenv( $name );
	}


	public function testConfigResolvesWithTheDocumentedDeveloperEnvironment(): void {
		$config = configLoader::load( self::ROOT );

		$this->assertInstanceOf( unifiedConfig::class, $config );
		$this->assertSame( 'local', $config->type );
		$this->assertSame( 'mongodb://mongodb:27017', $config->mongoDatabases[ 0 ]->uri );
		$this->assertSame( 'app', $config->mongoDatabases[ 0 ]->database );
		$this->assertSame( 'Application', $config->app->title, 'the placeholder `gf init --title` overwrites' );
		$this->assertFalse( $config->settings->useSession );
	}


	/**
	 * The manifest and the config file cannot drift, because the manifest IS the config
	 * file. If this list changes, `gf env --init` changes with it automatically.
	 */
	public function testConfigReferencesExactlyTheDocumentedVariables(): void {
		$references = configLoader::references( self::ROOT );

		// Compared as a set: the order is config.json's key order, which is a formatting
		// choice, not a contract.
		$referenced = array_keys( $references );
		$documented = array_keys( self::DEV_ENVIRONMENT );
		sort( $referenced );
		sort( $documented );
		$this->assertSame( $documented, $referenced );
		$this->assertTrue( $references[ 'MONGO_URI' ], 'the connection string is a secret' );
		$this->assertFalse( $references[ 'APP_TYPE' ] );
	}


	/** Each reference is required: dropping any one of them must stop the application. */
	public function testEveryReferenceIsRequired(): void {
		foreach( array_keys( self::DEV_ENVIRONMENT ) as $name ) {
			$this->clearEnv( $name );

			try {
				configLoader::load( self::ROOT );
				$this->fail( 'config.json resolved without ' . $name . ' — every reference is supposed to be required' );
			}
			catch( environmentException $e ) {
				$this->assertStringContainsString( $name, $e->getMessage() );
			}
			finally {
				$this->setEnv( $name, self::DEV_ENVIRONMENT[ $name ] );
			}
		}
	}


	/**
	 * The production path: the connection string arrives as a provisioned file rather than
	 * an environment variable, and the same committed config.json reads it.
	 */
	public function testMongoUriCanBeSuppliedAsAProvisionedSecretFile(): void {
		$secretFile = tempnam( sys_get_temp_dir(), 'mongo' );
		$this->assertIsString( $secretFile );
		file_put_contents( $secretFile, "mongodb+srv://user:pass@cluster/\n" );

		$this->clearEnv( 'MONGO_URI' );
		$this->setEnv( 'MONGO_URI_FILE', $secretFile );

		try {
			$config = configLoader::load( self::ROOT );
			$this->assertSame( 'mongodb+srv://user:pass@cluster/', $config->mongoDatabases[ 0 ]->uri );
		}
		finally {
			$this->clearEnv( 'MONGO_URI_FILE' );
			unlink( $secretFile );
		}
	}


	/** Logging to files would be per-replica and destroyed by every deploy. */
	public function testLoggingGoesToStderr(): void {
		$this->assertSame( 'stderr', configLoader::load( self::ROOT )->logging->destination );
	}


	/** No optional integration blocks: an application adds what it actually uses. */
	public function testTemplateShipsNoUnusedIntegrationBlocks(): void {
		$raw = json_decode( (string)file_get_contents( self::ROOT . '/config.json' ), true );
		$this->assertIsArray( $raw );

		foreach( [ 'microsoft', 'payjunction', 'sqlDatabases', 'environments' ] as $absent ) {
			$this->assertArrayNotHasKey( $absent, $raw );
		}
		foreach( [ 'serverName', 'cookieUrl', 'phpPath', 'baseUrl' ] as $removed ) {
			$this->assertArrayNotHasKey( $removed, $raw );
		}
	}


	/** A missing section must hydrate to its defaults rather than fataling. */
	public function testAbsentSectionsHydrateToDefaults(): void {
		$config = configLoader::load( self::ROOT );

		$this->assertSame( '', $config->microsoft->clientId );
		$this->assertSame( '', $config->payjunction->username );
		$this->assertSame( [], $config->sqlDatabases );
	}


	/** Issuer and audience are not configured separately — they derive from the app's own urls. */
	public function testJwtIssuerAndAudienceDeriveFromTheApplicationUrls(): void {
		$config = configLoader::load( self::ROOT );

		$this->assertSame( 'http://localhost:8080', $config->getTokenIssuedBy() );
		$this->assertSame( '/', $config->getTokenPermittedFor() );
	}

}
