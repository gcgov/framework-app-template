<?php
//include composer requirements
include_once('../vendor/autoload.php');

$projectRootDirectory = dirname(__FILE__).'/../';
$framework = new \gcgov\framework\framework( $projectRootDirectory  );
echo $framework->runApp();