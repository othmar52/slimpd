<?php

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
/* Copyright (C) 2015-2016 othmar52 <othmar52@users.noreply.github.com>
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
require_once APP_ROOT . 'core' . DS . 'vendor-dist' . DS . 'autoload.php';
#require_once APP_ROOT . 'core' . DS . 'php' . DS . 'autoload.php';


require_once APP_ROOT . 'core' . DS . 'php' . DS . 'libs' . DS . 'shims' . DS . 'GeneralUtility.php';
require_once APP_ROOT . 'core' . DS . 'php' . DS . 'libs' . DS . 'shims' . DS . 'StringUtility.php';
require_once APP_ROOT . 'core' . DS . 'php' . DS . 'libs' . DS . 'shims' . DS . 'FilesystemUtility.php';
require_once APP_ROOT . 'core' . DS . 'php' . DS . 'libs' . DS . 'shims' . DS . 'CompareImages.php';
require_once APP_ROOT . 'core' . DS . 'php' . DS . 'libs' . DS . 'twig'  . DS . 'SlimpdTwigExtension.php';



date_default_timezone_set('Europe/Vienna');


session_start();

// Create app
$app = new \Slim\App();

// Set up dependencies
require APP_ROOT . 'core/php/dependencies.php';



// LOAD MODULES
call_user_func(function() use ($app) {
	$path = APP_ROOT . 'core' . DS . 'php' . DS . 'Modules' . DS;
	foreach (scandir($path) as $dir) {
		// suppress warning with "@" and avoid tons of is_file()-checks
		#include_once($path . $dir . DS . 'class.php');
	}
});


$container = $app->getContainer();


// TODO: where to set variables for all twig templates?
/*
$config['current_url']  = rtrim($container->request->getRequestTarget(), '/');
# TODO: its not possible to use 2 browsertabs in different playermodes simultaneously!?
$config['playerMode'] = ($container->cookie->get('playerMode') === 'mpd') ? 'mpd' : 'local';
$config['nosurrounding'] = isset($_REQUEST['nosurrounding']);
$config['root'] = $config['config']['absRefPrefix'];
$config['fileroot'] = $config['config']['absFilePrefix'];
$app->config = $config;
$vars = $config;
*/
#$app->view->getEnvironment()->addGlobal('flash', @$_SESSION['slim.flash']);


// LOAD CONTROLLERS
#call_user_func(function() use ($app, $vars) {
#	$path = APP_ROOT . 'core' . DS . 'php' . DS . 'Modules' . DS;
#	foreach (scandir($path) as $dir) {
#		// suppress warning with "@" and avoid tons of is_file()-checks
#		#@include_once($path . $dir . DS . 'controller.php');
#	}
#});

// DEFINE GET/POST routes (also check for .gitignored local-routes)
foreach(array('get', 'post') as $method) {
	foreach(array('', '_local') as $local) {
		if(file_exists(APP_ROOT . 'core' . DS . 'php' . DS . 'routes' . DS . $method . $local . '.php')) {
			#include_once APP_ROOT . 'core' . DS . 'php' . DS . 'routes' . DS . $method . $local . '.php';
		}
	}
}

$app->get('/hello/{name}', function (Request $request, Response $response) {
	die('sgsgsgd');
    $name = $request->getAttribute('name');
    $response->getBody()->write("Hello, $name");

    return $response;
});



$app->run();
