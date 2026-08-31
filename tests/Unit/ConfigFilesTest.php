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
		'MONGO_URI'                 => 'mongodb://mongodb:27017/?replicaSet=rs0',
		'APP_JWT_KEY_PATH'          => '/var/www/app/srv/jwtCertificates/',
	];

	/**
	 * The variables whose correct value differs between the host gf CLI and the php
	 * container, so docker-compose.yml pins them and .env carries the host's value.
	 *
	 * @var array<string, string>
	 */
	private const array CONTAINER_PINNED = [
		'APP_JWT_KEY_PATH' => '/var/www/app/srv/jwtCertificates/',
		'MONGO_URI'        => 'mongodb://mongodb:27017/?replicaSet=rs0',
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
		$this->assertSame( 'mongodb://mongodb:27017/?replicaSet=rs0', $config->mongoDatabases[ 0 ]->uri );
		$this->assertSame( 'app', $config->mongoDatabases[ 0 ]->database );
		$this->assertSame( 'Application', $config->app->title, 'the placeholder `gf init --title` overwrites' );
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

		// settings held only useSession, which the framework removed; cronMonitor was
		// registered as a service but never given a url, so it did nothing. Both would now
		// be present-and-blank blocks, which is what this test exists to prevent.
		foreach( [ 'microsoft', 'payjunction', 'sqlDatabases', 'environments', 'settings', 'cronMonitor' ] as $absent ) {
			$this->assertArrayNotHasKey( $absent, $raw );
		}
		foreach( [ 'serverName', 'cookieUrl', 'phpPath', 'baseUrl' ] as $removed ) {
			$this->assertArrayNotHasKey( $removed, $raw );
		}
	}


	/**
	 * Which Framework Services this application runs, asserted against the real committed
	 * config.json — this is the only place that fact is now recorded.
	 */
	public function testEnabledServicesAreAuthUserCrudAndDocumentation(): void {
		$services = configLoader::load( self::ROOT )->services;

		$this->assertNotNull( $services->auth );
		$this->assertSame( 'oauth', $services->auth->provider );
		$this->assertTrue( $services->auth->isOauth() );
		$this->assertNotNull( $services->userCrud );
		$this->assertNotNull( $services->documentation );
	}


	/** Selecting a provider selects its block and only its block. */
	public function testOnlyTheSelectedAuthProviderIsConfigured(): void {
		$auth = configLoader::load( self::ROOT )->services->auth;

		$this->assertNotNull( $auth->oauth, 'the selected provider hydrates to its defaults when omitted' );
		$this->assertNull( $auth->msFront );
	}


	/**
	 * Shipped blank, these would be configuration that looks deliberate and is not. They
	 * take their defaults; README documents them for an application that wants to change them.
	 */
	public function testNewUserProvisioningTakesItsFailClosedDefaults(): void {
		$auth = configLoader::load( self::ROOT )->services->auth;

		$this->assertTrue( $auth->blockNewUsers, 'only users already in the database may sign in' );
		$this->assertSame( [], $auth->defaultNewUserRoles );
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


	/**
	 * MongoDB must run as a replica set, and nothing else in the stack says so out loud.
	 *
	 * Every write the framework makes opens a transaction, which MongoDB offers only on a
	 * replica set or a sharded cluster. A standalone mongod serves every read and fails
	 * every write — so dropping this flag leaves a stack that starts, answers /health, lists
	 * widgets, and cannot save one. That is a regression a smoke test does not catch, which
	 * is what earns it a test.
	 */
	public function testTheLocalStackRunsMongoAsAReplicaSet(): void {
		$mongo = $this->composeService( 'mongodb' );

		$this->assertContains( '--replSet', $mongo[ 'command' ] ?? [], 'a standalone mongod cannot serve any write this framework makes' );
		$this->assertSame( 'service_healthy', $this->composeService( 'php' )[ 'depends_on' ][ 'mongodb' ][ 'condition' ] ?? null, 'the healthcheck is what initiates the set, so php must wait for it rather than for the process' );
	}


	/**
	 * The variables one .env cannot get right for both sides are pinned in the compose file.
	 *
	 * .env is read by the host gf CLI and by the container. MONGO_URI must name localhost on
	 * one and the compose service on the other; APP_JWT_KEY_PATH must name a host-relative
	 * and a container-absolute path. Pinning them under `environment:` (which beats
	 * `env_file:`) is what lets one file serve both — and losing either pin is silent, since
	 * the host's value resolves perfectly well inside the container and simply points nowhere.
	 */
	public function testTheContainerPinsTheVariablesThatDifferBySide(): void {
		$environment = $this->composeService( 'php' )[ 'environment' ] ?? [];

		foreach( self::CONTAINER_PINNED as $name => $value ) {
			$this->assertSame( $value, $environment[ $name ] ?? null, $name . ' must be pinned in docker-compose.yml: .env cannot hold a value correct on both the host and the container' );
		}
	}


	/** Every pinned variable is one config.json actually reads. */
	public function testEveryPinnedVariableIsReferencedByConfig(): void {
		$references = configLoader::references( self::ROOT );

		foreach( array_keys( self::CONTAINER_PINNED ) as $name ) {
			$this->assertArrayHasKey( $name, $references );
		}
	}


	/**
	 * .env.example must declare every variable docker compose interpolates.
	 *
	 * Compose reads `.env` and only `.env`, so `cp .env.example .env` is the whole supply
	 * chain for these. A ${VAR} the example file does not declare silently falls back to its
	 * default — no warning, no error — which is how a published port or a CORS origin ends up
	 * ignored.
	 */
	public function testEnvExampleDeclaresEveryVariableComposeInterpolates(): void {
		$compose = (string)file_get_contents( self::ROOT . '/docker-compose.yml' );
		$example = (string)file_get_contents( self::ROOT . '/.env.example' );

		preg_match_all( '/\$\{([A-Z0-9_]+)(?::-[^}]*)?}/', $compose, $matches );
		$interpolated = array_unique( $matches[ 1 ] );
		$this->assertNotEmpty( $interpolated );

		foreach( $interpolated as $name ) {
			$this->assertMatchesRegularExpression( '/^' . preg_quote( $name, '/' ) . '=/m', $example, $name . ' is interpolated by docker-compose.yml but .env.example does not declare it' );
		}
	}


	/**
	 * Read one service out of the committed compose file.
	 *
	 * @return array<string, mixed>
	 */
	private function composeService( string $name ): array {
		/** @var array<string, mixed> $parsed */
		$parsed = \Symfony\Component\Yaml\Yaml::parseFile( self::ROOT . '/docker-compose.yml' );
		$this->assertArrayHasKey( $name, $parsed[ 'services' ] );

		return $parsed[ 'services' ][ $name ];
	}

}
