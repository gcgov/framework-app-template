<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// In the production / CI environment the mongodb extension is loaded and the
// real MongoDB\BSON classes are available. In the sandbox / local environment
// the extension may be missing, so provide runtime-functional shims.
if ( !extension_loaded( 'mongodb' ) ) {
	require __DIR__ . '/Shims/MongoDBShims.php';
}

// Seed a minimal environmentConfig so framework code that accesses it via
// config::getEnvironmentConfig() doesn't try to read a JSON file from disk.
$envConfig = new \gcgov\framework\models\environmentConfig();
$envConfig->basePath = 'api';
$prop = new \ReflectionProperty( \gcgov\framework\config::class, 'environmentConfig' );
$prop->setValue( null, $envConfig );
