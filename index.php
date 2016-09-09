<?php
namespace Slimpd;
/* Copyright (C) 2015-2016 engine <engine@gas-werk.org>
 *
 * This file is part of sliMpd - a php based mpd web client
 *
 * This program is free software: you can redistribute it and/or modify it
 * under the terms of the GNU Affero General Public License as published by the
 * Free Software Foundation, either version 3 of the License, or (at your
 * option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License
 * for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

$debug = isset($_REQUEST['debug']) ? true : false;
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
require_once APP_ROOT . 'php' . DS . 'libs' . DS . 'shims' . DS . 'StringUtility.php';
require_once APP_ROOT . 'php' . DS . 'libs' . DS . 'shims' . DS . 'FilesystemUtility.php';
require_once APP_ROOT . 'php' . DS . 'libs' . DS . 'shims' . DS . 'CompareImages.php';
require_once APP_ROOT . 'php' . DS . 'libs' . DS . 'twig'  . DS . 'SlimpdTwigExtension.php';

$view = $app->view();
$view->parserExtensions = array(new \Twig_Extension_Debug());
$view->parserOptions = array('debug' => $debug);

$twig = $app->view->getInstance();
$twig->addExtension(new \Slimpd_Twig_Extension());


// LOAD MODULES
call_user_func(function() use ($app) {
	$path = APP_ROOT . 'php' . DS . 'Modules' . DS;
	foreach (scandir($path) as $dir) {
		// suppress warning with "@" and avoid tons of is_file()-checks
		@include_once($path . $dir . DS . 'class.php');
	}
});



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

if($config['mpd']['alternative_musicdir'] === '') {
	define('ALTDIR', FALSE);
} else {
	define('ALTDIR', TRUE);
}


$config['current_url']  = rtrim($app->request->getResourceUri(), '/');
# TODO: its not possible to use 2 browsertabs in different playermodes simultaneously!?
$config['playerMode'] = ($app->getCookie('playerMode') === 'mpd') ? 'mpd' : 'local';
$config['nosurrounding'] = ($app->request->get('nosurrounding') == 1) ? TRUE : FALSE;
$config['root'] = $config['config']['absRefPrefix'];
$config['fileroot'] = $config['config']['absFilePrefix'];
$app->config = $config;
$vars = $config;

$app->view->getEnvironment()->addGlobal('flash', @$_SESSION['slim.flash']);

$app->error(function(\Exception $e) use ($app, $vars){
	$vars['action'] = 'error';
	$vars['errormessage'] = $e->getMessage();
	$vars['tracestring'] = str_replace(array('#', "\n"), array('<div>#', '</div>'), htmlspecialchars($e->getTraceAsString()));
	$vars['url'] = $app->request->getResourceUri();
	$vars['file'] = $e->getFile();
	$vars['line'] = $e->getLine();
	$app->render('appless.htm', $vars);
});

// LOAD CONTROLLERS
call_user_func(function() use ($app, $vars) {
	$path = APP_ROOT . 'php' . DS . 'Modules' . DS;
	foreach (scandir($path) as $dir) {
		// suppress warning with "@" and avoid tons of is_file()-checks
		@include_once($path . $dir . DS . 'controller.php');
	}
});

// DEFINE GET/POST routes (also check for .gitignored local-routes)
foreach(array('get', 'post') as $method) {
	foreach(array('', '_local') as $local) {
		if(file_exists(APP_ROOT . 'php' . DS . 'routes' . DS . $method . $local . '.php')) {
			include_once APP_ROOT . 'php' . DS . 'routes' . DS . $method . $local . '.php';
		}
	}
}

// use 404 not found as a search in case we don't have a slash in uri
$app->notFound(function() use ($app, $vars){
	$uri = ltrim(rawurldecode($app->request->getResourceUri()),'/');
	// check if we do have a slash in uri
	if(stripos($uri, '/') !== FALSE) {
		$vars['action'] = '404';
		$app->render('surrounding.htm', $vars);
	} else {
		// trigger a search
		$app->response->redirect($app->config['root'] . 'searchall/page/1/sort/relevance/desc?q='.rawurlencode($uri) . getNoSurSuffix(), 301);
	}
});

$app->run();
