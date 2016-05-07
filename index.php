<?php
namespace Slimpd;


$debug = isset($_REQUEST['debug']) ? true : false;
//$debug = true;
if($debug){
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

define('DS', DIRECTORY_SEPARATOR);
define('APP_ROOT', __DIR__ . DS);
define('APP_DEFAULT_CHARSET', 'UTF-8');
require_once APP_ROOT . 'vendor-dist' . DS . 'autoload.php';
require_once APP_ROOT . 'php' . DS . 'autoload.php';
date_default_timezone_set('Europe/Vienna');


session_start();

$app = new \Slim\Slim(array(
    'debug' => $debug,
    'view' => new \Slim\Views\Twig(),
    'templates.path' => 'templates'
));

require_once APP_ROOT . 'php' . DS . 'libs' . DS . 'shims' . DS . 'GeneralUtility.php';
require_once APP_ROOT . 'php' . DS . 'libs' . DS . 'shims' . DS . 'CompareImages.php';

$view = $app->view();
$view->parserExtensions = array(new \Twig_Extension_Debug());
$view->parserOptions = array('debug' => $debug);

#####################################################################################
# TODO: where is the right place for twig-filters and twig-tests?
# TODO: does it make sense to generate all href's in a twig-filter instead of having hardcoded routes in twig templates/partials?

$twig = $app->view->getInstance();
$filter = new \Twig_SimpleFilter('formatMiliseconds', function ($miliseconds) {
	return gmdate(($miliseconds > 3600000) ? "G:i:s" : "i:s", ($miliseconds/1000));
});
$twig->addFilter($filter);

$filter = new \Twig_SimpleFilter('path2url', function ($mixed) {
	if(is_array($mixed) === TRUE) {
		$mixed = join("", $mixed);
	}
	// rawurlencode but preserve slashes
	return str_replace('%2F', '/', rawurlencode($mixed));
});
$twig->addFilter($filter);

$filter = new \Twig_SimpleFilter('formatSeconds', function ($seconds) {
	$format = "G:i:s";
	$suffix = "h";
	if($seconds < 3600) {
		$format = "i:s";
		$suffix = "min";
	}
	if($seconds < 60) {
		$format = "s";
		$suffix = "sec";
	}
	if($seconds < 1) {
		return(round($seconds*1000)) . ' ms';
	}
	// remove leading zero
	return ltrim(gmdate($format, $seconds) . ' ' . $suffix, 0);
});
$twig->addFilter($filter);

$filter = new \Twig_SimpleFilter('shorty', function ($number) {
	if($number < 990) {
		return $number;
	}
	if($number < 990000) {
		return number_format($number/1000,0) . " K";
	}
	return number_format($number/1000000,1) . " M";
});
$twig->addFilter($filter);

$filter = new \Twig_SimpleFilter('formatBytes', function ($bytes, $precision = 2) {
	$units = array('B', 'KB', 'MB', 'GB', 'TB'); 
    $bytes = max($bytes, 0); 
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
    $pow = min($pow, count($units) - 1);
	$bytes /= pow(1024, $pow);
	return round($bytes, $precision) . ' ' . $units[$pow];
});
$twig->addFilter($filter);

$filter = new \Twig_SimpleFilter('shortenChecksum', function ($hash, $precision = 3) {
	return substr($hash,0,$precision) . '...' . substr($hash,$precision*-1);
});
$twig->addFilter($filter);

$filter = new \Twig_SimpleFilter('ll', function ($hans = array(), $vars = array()) use($app) {
	return $app->ll->str($hans, $vars);
});
$twig->addFilter($filter);


$test = new \Twig_SimpleTest('instanceofAlbum', function ($item) {
	return $item instanceof \Slimpd\Album;
});
$twig->addTest($test);

$test = new \Twig_SimpleTest('instanceofTrack', function ($item) {
	return $item instanceof \Slimpd\Track;
});
$twig->addTest($test);

$test = new \Twig_SimpleTest('instanceofLabel', function ($item) {
	return $item instanceof \Slimpd\Label;
});
$twig->addTest($test);

$test = new \Twig_SimpleTest('instanceofGenre', function ($item) {
	return $item instanceof \Slimpd\Genre;
});
$twig->addTest($test);

$test = new \Twig_SimpleTest('instanceofArtist', function ($item) {
	return $item instanceof \Slimpd\Artist;
});
$twig->addTest($test);

$test = new \Twig_SimpleTest('instanceofDirectory', function ($item) {
	return $item instanceof \Slimpd\_Directory;
});
$twig->addTest($test);


#####################################################################################

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

$app->view->getEnvironment()->addGlobal('flash', @$_SESSION['slim.flash']);


define('NJB_DEFAULT_CHARSET', 'utf8');

$app->error(function(\Exception $e) use ($app, $config){
    $app->render('error.htm', $config);
});
$app->notFound(function() use ($app, $config){
	$config['action'] = '404';
    $app->render('surrounding.htm', $config);
});

// DEFINE GET/POST routes (also check for .gitignored local-routes)
foreach(array('get', 'post') as $method) {
	foreach(array('', '_local') as $local) {
		if(file_exists(APP_ROOT . 'php' . DS . 'routes' . DS . $method . $local . '.php')) {
			include_once APP_ROOT . 'php' . DS . 'routes' . DS . $method . $local . '.php';
		}
	}
}
$app->run();