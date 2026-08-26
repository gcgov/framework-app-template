<?php

declare(strict_types=1);

namespace app\tests\Unit;

use gcgov\framework\models\config\variantEnvironment;
use gcgov\framework\models\unifiedConfig;
use gcgov\framework\services\environment\configLoader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Dotenv\Dotenv;

/**
 * Pins the completeness contract of the committed root config.json against the committed
 * .env.example: after `cp .env.example .env` the active config must fully resolve (every hard
 * %env(VAR)% is covered), and the CLI-only `environments.prod` entry must resolve once the
 * (commented) PROD_* variables are supplied. A failure here means a clean checkout — or the
 * Docker image built from it — would 500.
 */
final class ConfigFilesTest extends TestCase {

	private const string ROOT = __DIR__ . '/../..';

	/** @var array<string, string> */
	private array $envSnapshot = [];


	protected function setUp(): void {
		$this->envSnapshot = $_ENV;
		// Mirror `cp .env.example .env`: seed the environment from the committed example
		// (uncommented entries only — commented PROD_* lines are intentionally absent).
		foreach( ( new Dotenv() )->parse( (string)file_get_contents( self::ROOT . '/.env.example' ) ) as $name => $value ) {
			$this->setEnv( $name, $value );
		}
	}


	protected function tearDown(): void {
		foreach( array_keys( $_ENV ) as $key ) {
			if( !array_key_exists( $key, $this->envSnapshot ) ) {
				putenv( $key );
			}
		}
		$_ENV = $this->envSnapshot;
	}


	private function setEnv( string $name, string $value ): void {
		$_ENV[ $name ] = $value;
		putenv( $name . '=' . $value );
	}


	public function testActiveConfigResolvesWithDotEnvExample(): void {
		$config = configLoader::load( self::ROOT );

		$this->assertInstanceOf( unifiedConfig::class, $config );
		$this->assertSame( 'local', $config->type );
		$this->assertSame( 'mongodb://mongodb:27017', $config->mongoDatabases[ 0 ]->uri );
		$this->assertSame( 'app', $config->mongoDatabases[ 0 ]->database );
		// merged app-side sections hydrate from the same file
		$this->assertSame( '{app_title}', $config->app->title );
		$this->assertSame( '', $config->email->SMTPUsername );
		$this->assertFalse( $config->settings->useSession );
	}


	public function testActiveConfigResolvesWithoutProdVariables(): void {
		// The environments section is CLI-only; the active config resolves even though
		// PROD_MONGO_URI / PROD_MONGO_DATABASE are absent from .env.example (commented out).
		$this->assertArrayNotHasKey( 'PROD_MONGO_URI', $_ENV );
		$config = configLoader::load( self::ROOT );
		$this->assertSame( 'local', $config->type );
	}


	public function testProdEnvironmentEntryResolvesWithProdVariables(): void {
		$this->setEnv( 'PROD_MONGO_URI', 'mongodb+srv://user:pass@prod-cluster/' );
		$this->setEnv( 'PROD_MONGO_DATABASE', 'app' );

		$prod = configLoader::loadVariantEnvironment( self::ROOT, 'prod' );

		$this->assertInstanceOf( variantEnvironment::class, $prod );
		$this->assertSame( 'prod', $prod->type, 'the prod entry type must be the literal "prod" — the db:restore guard depends on it' );
		$this->assertSame( 'mongodb+srv://user:pass@prod-cluster/', $prod->mongoDatabases[ 0 ]->uri );
	}

}
