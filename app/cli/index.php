<?php
//include composer requirements
$projectRootDirectory = dirname(__FILE__).'/../../';
include_once($projectRootDirectory.'vendor/autoload.php');

$_SERVER[ 'REQUEST_METHOD' ] = 'CLI';
$_SERVER['REQUEST_URI'] = $argv[1];
$_SERVER[ 'REMOTE_ADDR' ] = '127.0.0.1';

$framework = new \gcgov\framework\framework( $projectRootDirectory );
echo $framework->runApp();