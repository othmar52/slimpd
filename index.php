<?php
/* Copyright (C) 2015-2016 othmar52 <othmar52@users.noreply.github.com>
 *                         smuth4 <smuth4@users.noreply.github.com>
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
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License
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

// register autoloader
require_once APP_ROOT . 'core' . DS . 'vendor-dist' . DS . 'autoload.php';

// include some additional files
foreach([
    'Utilities' . DS . 'GeneralUtility.php',
    'Utilities' . DS . 'StringUtility.php',
    'Utilities' . DS . 'SphinxUtility.php',
    'libs' . DS . 'twig' . DS . 'SlimpdTwigExtension.php'] as $filePath) {
    require_once APP_ROOT . 'core' . DS . 'php' . DS . $filePath;
}

// Timezone needs to be set before session_start, the locale is also set here for convience
$configLoader = new \Slimpd\Modules\configloader_ini\ConfigLoaderINI(APP_ROOT . 'core/config/');
$config = $configLoader->loadConfig('master.ini');
if (isset($config['config']['timezone'])) {
    date_default_timezone_set($config['config']['timezone']);
}
if (isset($config['config']['locale'])) {
    setlocale(LC_ALL, array($config['config']['locale']));
}

session_start();

// Create app
$app = new \Slim\App();

// Set up dependencies
require APP_ROOT . 'core/php/dependencies.php';

// Register routes
require APP_ROOT . 'core/php/routes.php';

$app->run();
