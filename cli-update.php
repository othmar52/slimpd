#!/usr/bin/env php
<?php

/* TODO: currently there is no check of any update status
 * TODO: remove dead items after update
 * TODO: instead of heaving multiple update-cli.php scripts merge the together with a procedural logic
 * TODO: move memory_limit - value to config
 */
if(PHP_SAPI !== 'cli') {
	header('HTTP/1.0 403 Forbidden');
	echo "Sorry, execution is not allowed via http...";
	die();
}


ini_set('max_execution_time', 0);
ini_set('memory_limit', '4096M');
define('DS', DIRECTORY_SEPARATOR);
define('APP_ROOT', __DIR__ . DS);

chdir(dirname(__DIR__)); // set directory to root

require_once 'vendor' . DS . 'autoload.php';
require_once 'php' . DS . 'autoload.php';
require_once 'php' . DS . 'libs' . DS . 'shims' . DS . 'GeneralUtility.php';
require_once 'php' . DS . 'libs' . DS . 'shims' . DS . 'CompareImages.php';
require_once 'php' . DS . 'libs' . DS . 'shims' . DS . 'RegexHelper.php';
date_default_timezone_set('Europe/Vienna');


// convert all the command line arguments into a URL
$argv = $GLOBALS['argv'];
array_shift($GLOBALS['argv']);
$pathInfo = '/' . implode('/', $argv);


// Create our app instance
$app = new Slim\Slim([
    'debug' => false,  // Turn off Slim's own PrettyExceptions
]);

// Set up the environment so that Slim can route
$app->environment = Slim\Environment::mock([
    'PATH_INFO'   => $pathInfo
]);


// CLI-compatible not found error handler
$app->notFound(function () use ($app) {
    $url = $app->environment['PATH_INFO'];
    echo "Error: Cannot route to $url\n";
    $app->stop();
});

// Format errors for CLI
$app->error(function (\Exception $e) use ($app) {
    echo $e;
    $app->stop();
});


// LOAD MODULES
call_user_func(function() use ($app) {
    $path = APP_ROOT . 'php' . DS . 'modules' . DS;
    foreach (scandir($path) as $dir) {
        $dir = $path . $dir;
        $file = $dir . DS . 'main.php';
        if (is_dir($dir) && is_file($file) && is_readable($file)) {
            include $file;
        }
    }
});

// LOAD MODELS
call_user_func(function() use ($app) {
    $path = APP_ROOT . 'php' . DS . 'models' . DS;
    foreach (scandir($path) as $file) {
        $dir = $path . $file;
        if (is_file($path . $file) && is_readable($path . $file)) {
            include_once $path . $file;
        }
    }
});

$configLoader = $app->configLoaderINI;

$config = $configLoader->loadConfig('master.ini');
$app->config = $config;

$importer = new \Slimpd\importer();



// IMPORTANT TODO: avoid simultaneously executet updates

$app->get('/', function () use ($app, $importer) {
	
	// check if we have something to process
	if($importer->checkQue() === TRUE) {
		$importer->triggerImport();
	}
    echo "Hello, kitty\n"; exit;
});


$app->get('/debugmig', function () use ($app, $importer) {
	\Slimpd\importer::queDirectoryUpdate('mu/usr/lehrbub/');
	$importer->migrateRawtagdataTable();
});


$app->get('/standard', function () use ($app, $importer) {
	$importer->triggerImport();
});

$app->get('/database-cleaner', function () use ($app) {
	// phase 11: delete all bitmap-database-entries that does not exist in filesystem
	// TODO: limit this to embedded images only
	$importer->deleteOrphanedBitmapRecords();
	
	// TODO: delete orphaned artists + genres + labels
});

// run!
$app->run();
