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

require_once 'vendor-dist' . DS . 'autoload.php';
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
$config = $app->configLoaderINI->loadConfig('master.ini');
switch($config['config']['langkey']) {
	case 'de':
		setlocale(LC_ALL, array('de_DE.UTF-8','de_DE@euro','de_DE','german'));
		break;
	default:
		// TODO: what is the correct locale-setting for en?
		// make sure this works correctly:
		//   var_dump(basename('musicfiles/testdirectory/Ænima-bla')); die();
		// for now force DE...
		// setlocale(LC_ALL, array('en_EN.UTF-8','en_EN','en_EN'))
		setlocale(LC_ALL, array('de_DE.UTF-8','de_DE@euro','de_DE','german'));
		break;
}

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
	$importer->checkQue();
	$importer->migrateRawtagdataTable();
});


$app->get('/standard', function () use ($app, $importer) {
	$importer->triggerImport();
});


$app->get('/builddictsql', function () use ($app, $importer) {
	$importer->buildDictionarySql();
});

$app->get('/database-cleaner', function () use ($app) {
	// phase 11: delete all bitmap-database-entries that does not exist in filesystem
	// TODO: limit this to embedded images only
	$importer->deleteOrphanedBitmapRecords();
	
	// TODO: delete orphaned artists + genres + labels
});


$app->get('/update-db-scheme', function () use ($app, $argv) {
	$action = 'migrate';

	// TODO: manually performed db-changes does not get recognized here - find a solution!

	// check if we can query the revisions table
	$query = "SELECT * FROM db_revisions";
	$result = $app->db->query($query);
	if($result === FALSE) {
		// obviosly table(s) never have been created
		// let's force initial creation of all tables
		$action = 'init';
	}

	Helper::setConfig( getDatabaseDiffConf($app) );
	if (!Helper::checkConfigEnough()) {
	    Output::error('mmp: please check configuration');
	    die(1);
	}

	# after database-structure changes we have to
	# 1) uncomment next line
	# 2) run ./cli-update.php update-db-scheme
	# 3) recomment this line again
	# to make a new revision
	#$action = 'create';

	$controller = Helper::getController($action, NULL);
	if ($controller !== false) {
	    $controller->runStrategy();
	} else {
	    Output::error('mmp: unknown command "'.$cli_params['command']['name'].'"');
	    Helper::getController('help')->runStrategy();
	    die(1);
	}

	if($action !== 'init') {
		exit;
	}

	// insert some defaults
	// TODO: read from localization-file
	$queries = array(
		"INSERT INTO `artist` VALUES ('1', 'Unknown Artist', '', 'unknownartist', 0,0);",
		"INSERT INTO `artist` VALUES ('2', 'Various Artists', '', 'variousartists', 0,0);",
		"INSERT INTO `genre` VALUES ('1', 'Unknown', '0', 'unknown',0,0);",
		"INSERT INTO `label` VALUES ('1', 'Unknown Label', 'unknownlabel',0,0);"
	);
	foreach($queries as $query) {
		$app->db->query($query);
	}
});


// run!
$app->run();
