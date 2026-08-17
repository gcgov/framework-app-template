<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// In the production / CI environment the mongodb extension is loaded and the
// real MongoDB\BSON classes are available. In the sandbox / local environment
// the extension may be missing, so provide runtime-functional shims.
if ( !extension_loaded( 'mongodb' ) ) {
	require __DIR__ . '/Shims/MongoDBShims.php';
}

// Seed a minimal unifiedConfig so framework code that reads config via the
// static accessors (config::getBasePath() etc.) doesn't try to read config.json from disk.
$envConfig = new \gcgov\framework\models\unifiedConfig();
$envConfig->basePath = 'api';
$prop = new \ReflectionProperty( \gcgov\framework\config::class, 'unifiedConfig' );
$prop->setValue( null, $envConfig );
