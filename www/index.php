<?php
//include composer requirements
include_once(__DIR__ . '/../vendor/autoload.php');

$framework = new \gcgov\framework\framework();
echo $framework->runApp();
