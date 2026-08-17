<?php

declare(strict_types=1);

namespace app\tests\Unit;

use gcgov\framework\models\unifiedConfig;
use gcgov\framework\services\environment\dotEnvLoader;
use gcgov\framework\services\environment\envVarResolver;
use PHPUnit\Framework\TestCase;

/**
 * Pins the completeness contract between the committed root config.json and the example
 * env files: every hard %env(VAR)% reference must have a key in .env.example (dev) and
 * in prod.env.example (variant overlay reads). A failure here means a clean
 * `cp .env.example .env` checkout — or the Docker image built from it — would 500.
 */
final class ConfigFilesTest extends TestCase {

	private const string ROOT = __DIR__ . '/../..';


	/** @return array<string, string> */
	private function exampleOverlay( string $relativePath ): array {
		return dotEnvLoader::parseFile( self::ROOT . '/' . $relativePath );
	}


	private function resolveConfig( array $overlay ): unifiedConfig {
		$resolved = envVarResolver::resolveJson(
			(string)file_get_contents( self::ROOT . '/config.json' ),
			'config.json',
			$overlay
		);

		return unifiedConfig::jsonDeserialize( $resolved );
	}


	public function testConfigJsonResolvesWithDevExampleEnv(): void {
		$config = $this->resolveConfig( $this->exampleOverlay( '.env.example' ) );

		$this->assertSame( 'local', $config->type );
		$this->assertSame( 'mongodb://mongodb:27017', $config->mongoDatabases[ 0 ]->uri );
		$this->assertSame( 'app', $config->mongoDatabases[ 0 ]->database );
		// merged app.json sections hydrate from the same file
		$this->assertSame( '{app_title}', $config->app->title );
		$this->assertSame( '', $config->email->SMTPUsername );
		$this->assertFalse( $config->settings->useSession );
	}


	public function testConfigJsonResolvesWithProdExampleOverlay(): void {
		$config = $this->resolveConfig( $this->exampleOverlay( 'prod.env.example' ) );

		$this->assertSame( 'prod', $config->type, 'prod.env.example must set APP_TYPE=prod — the db:restore guard depends on it' );
		$this->assertSame( '/api', $config->getBasePath() );
	}

}
