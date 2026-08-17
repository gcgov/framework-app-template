<?php

declare(strict_types=1);

namespace app\tests\Unit;

use gcgov\framework\models\appConfig;
use gcgov\framework\models\environmentConfig;
use gcgov\framework\services\environment\dotEnvLoader;
use gcgov\framework\services\environment\envVarResolver;
use PHPUnit\Framework\TestCase;

/**
 * Pins the completeness contract between the committed config files and the example
 * env files: every hard %env(VAR)% reference in app/config/environment.json and
 * app/config/app.json must have a key in .env.example (dev) and in
 * app/config/prod.env.example (variant overlay reads). A failure here means a clean
 * `cp .env.example .env` checkout — or the Docker image built from it — would 500.
 */
final class ConfigFilesTest extends TestCase {

	private const string ROOT = __DIR__ . '/../..';


	/** @return array<string, string> */
	private function exampleOverlay( string $relativePath ): array {
		return dotEnvLoader::parseFile( self::ROOT . '/' . $relativePath );
	}


	public function testEnvironmentJsonResolvesWithDevExampleEnv(): void {
		$resolved = envVarResolver::resolveJson(
			(string)file_get_contents( self::ROOT . '/app/config/environment.json' ),
			'app/config/environment.json',
			$this->exampleOverlay( '.env.example' )
		);

		$environmentConfig = environmentConfig::jsonDeserialize( $resolved );
		$this->assertSame( 'local', $environmentConfig->type );
		$this->assertSame( 'mongodb://mongodb:27017', $environmentConfig->mongoDatabases[ 0 ]->uri );
		$this->assertSame( 'app', $environmentConfig->mongoDatabases[ 0 ]->database );
	}


	public function testEnvironmentJsonResolvesWithProdExampleOverlay(): void {
		$resolved = envVarResolver::resolveJson(
			(string)file_get_contents( self::ROOT . '/app/config/environment.json' ),
			'app/config/environment.json',
			$this->exampleOverlay( 'app/config/prod.env.example' )
		);

		$environmentConfig = environmentConfig::jsonDeserialize( $resolved );
		$this->assertSame( 'prod', $environmentConfig->type, 'prod.env.example must set APP_TYPE=prod — the db:restore guard depends on it' );
		$this->assertSame( '/api', $environmentConfig->getBasePath() );
	}


	public function testAppJsonResolvesWithDevExampleEnv(): void {
		$resolved = envVarResolver::resolveJson(
			(string)file_get_contents( self::ROOT . '/app/config/app.json' ),
			'app/config/app.json',
			$this->exampleOverlay( '.env.example' )
		);

		$appConfig = appConfig::jsonDeserialize( $resolved );
		$this->assertNotNull( $appConfig->email );
	}

}
