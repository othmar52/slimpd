<?php
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

// DIC configuration

$container = $app->getContainer();

// -----------------------------------------------------------------------------
// Service providers
// -----------------------------------------------------------------------------

// Flash messages
$container['flash'] = function () {
	return new Slim\Flash\Messages;
};

// Config loader
$container['conf'] = function ($cont) {
	// set cache parameter
	$noCache = false;
	if(isset($_REQUEST['noCache']) === true) {
		$noCache = true;
	}

	// force clearCache when we access route:systemcheck
	if($cont->request->getUri()->getPath() === "systemcheck") {
		$noCache = true;
	}

	$configLoader = new \Slimpd\Modules\configloader_ini\ConfigLoaderINI(APP_ROOT . 'core/config/');
	$config = $configLoader->loadConfig('master.ini', NULL, $noCache);
	\Slimpd\Modules\Localization\Localization::setLocaleByLangKey($config['config']['langkey']);

	if(PHP_SAPI === 'cli') {
		$_SESSION['cliVerbosity'] = $config['config']['cli-verbosity'];
	}
	return $config;
};


// Twig
$container['view'] = function ($cont) {
	if(PHP_SAPI === 'cli') {
		return new stdClass();
	}
	$conf = $cont->get('conf');
	$view = new Slim\Views\Twig(
		'core/templates',
		[
			#'cache' => 'localdata/cache',
			'debug' => true,
			#'auto_reload' => true,
		]
	);

	// Add extensions
	$view->addExtension(new Twig_Extension_Debug());
	$view->addExtension(new \Slim\Views\TwigExtension(
		$cont->get('router'),
		$cont->get('request')->getUri())
	);
	$view->addExtension(new \Slimpd\libs\twig\SlimpdTwigExtension\SlimpdTwigExtension($cont));

	// TODO: is this the right place for adding global template variables?
	$globalTwigVars = [
		'playerMode' => (($cont->cookie->get('playerMode') === 'mpd') ? 'mpd' : 'local'),
		'nosurrounding' => isset($_REQUEST['nosurrounding']),
		'root' => $conf['config']['absRefPrefix'],
		'fileroot' => $conf['config']['absFilePrefix'],
		'config' => $conf,
		'flash' => $cont['flash']
	];
	foreach($globalTwigVars as $varName => $value) {
		$view->getEnvironment()->addGlobal($varName, $value);
	}
	return $view;
};

$container['db'] = function ($cont) { 
	try {
		mysqli_report(MYSQLI_REPORT_STRICT);
		$settings = $cont->get('conf')['database'];
		$dbh = new \mysqli(
			$settings['dbhost'],
			$settings['dbusername'],
			$settings['dbpassword'],
			$settings['dbdatabase']
		);
	} catch (\Exception $e) {
		if(PHP_SAPI === 'cli') {
			cliLog($cont->ll->str('database.connect'), 1, 'red');
			exit;
		}
		$cont->flash->AddMessage('error', $cont->ll->str('database.connect'));
		$uri = $cont->conf['config']['absRefPrefix'] . 'systemcheck?dberror';
		// TODO: are we able to set the header in the reponse object?
		// workaround without response object:
		header('Location: ' . $uri);
		return;
	}
	return $dbh;
};

// Batcher
$container['batcher'] = function ($cont) {
	return new \Slimpd\Modules\Database\Batcher($cont);
};


// Filebrowser
$container['filebrowser'] = function ($cont) {
	return new \Slimpd\Modules\filebrowser\filebrowser($cont);
};


// Imageweighter
$container['imageweighter'] = function () {
	return new \Slimpd\Modules\imageweighter\Imageweighter();
};


// Localization
$container['ll'] = function () {
	return new \Slimpd\Modules\Localization\Localization();
};

// Cookies
$container['cookie'] = function($cont){
	$request = $cont->get('request');
	return new \Slim\Http\Cookies($request->getCookieParams());
};




// -----------------------------------------------------------------------------
// Service factories
// -----------------------------------------------------------------------------

// monolog
$container['logger'] = function ($c) {
	$logger = new Monolog\Logger('Slimpd');
	$logger->pushProcessor(new Monolog\Processor\UidProcessor());
	$logger->pushHandler(new Monolog\Handler\StreamHandler('localdata/cache/mono.log', Monolog\Logger::DEBUG));
	return $logger;
};


$container['errorHandler'] = function ($cont) {
	return function ($request, $response, $exception) use ($cont) {
		$vars['action'] = 'error';
		$vars['errormessage'] = $exception->getMessage();
		$vars['tracestring'] = removeAppRootPrefix(str_replace(array('#', "\n"), array('<div>#', '</div>'), htmlspecialchars($exception->getTraceAsString())));
		$vars['url'] = $request->getRequestTarget();
		$vars['file'] = removeAppRootPrefix($exception->getFile());
		$vars['line'] = $exception->getLine();

		// delete cached config
		$configLoader = new \Slimpd\Modules\configloader_ini\ConfigLoaderINI(APP_ROOT . 'core/config/');
		$config = $configLoader->loadConfig('master.ini', NULL, 1);

		$cont->view->render($response, 'appless.htm', $vars);
		return $response->withStatus(500);
	};
};


$container['notFoundHandler'] = function ($cont) {
	if(PHP_SAPI === 'cli') {
		return function ($request, $response) use ($cont) {
			$_SESSION['cliVerbosity'] = $cont->conf['config']['cli-verbosity'];
			$url = $cont->environment['REQUEST_URI'];
			cliLog($cont->ll->str('cli.arg.invalid', [ltrim($url, '/')]), 1, "red");
			cliLog('');
			renderCliHelp($cont->ll);
			return $response->withStatus(404);
		};
	}

	return function ($request, $response) use ($cont) {
		// use 404 not found as a search in case we don't have a slash in requestet uri
		$uri = rawurldecode($request->getUri()->getPath());
		// check if we do have a slash in uri
		if(stripos($uri, '/') !== FALSE) {
			$vars['action'] = '404';
			return $cont['view']->render($response, 'surrounding.htm', $vars)->withStatus(404);
		}
		// trigger a search
		$searchUri = $cont->conf['config']['absRefPrefix'] . 'searchall/page/1/sort/relevance/desc?q='.rawurlencode($uri);
		$newResponse = $response->withRedirect($searchUri, 301);
		return $newResponse;
	};
};


$container['albumRepo'] = function($cont) {
	return new \Slimpd\Repositories\AlbumRepo($cont);
};
$container['artistRepo'] = function($cont) {
	return new \Slimpd\Repositories\ArtistRepo($cont);
};
$container['genreRepo'] = function($cont) {
	return new \Slimpd\Repositories\GenreRepo($cont);
};
$container['labelRepo'] = function($cont) {
	return new \Slimpd\Repositories\LabelRepo($cont);
};

$container['trackRepo'] = function($cont) {
	return new \Slimpd\Repositories\TrackRepo($cont);
};
$container['bitmapRepo'] = function($cont) {
	return new \Slimpd\Repositories\BitmapRepo($cont);
};
$container['directoryRepo'] = function($cont) {
	return new \Slimpd\Repositories\DirectoryRepo($cont);
};

$container['rawtagblobRepo'] = function($cont) {
	return new \Slimpd\Repositories\RawtagblobRepo($cont);
};

$container['rawtagdataRepo'] = function($cont) {
	return new \Slimpd\Repositories\RawtagdataRepo($cont);
};

$container['albumindexRepo'] = function($cont) {
	return new \Slimpd\Repositories\AlbumindexRepo($cont);
};
$container['trackindexRepo'] = function($cont) {
	return new \Slimpd\Repositories\TrackindexRepo($cont);
};
$container['discogsitemRepo'] = function($cont) {
	return new \Slimpd\Repositories\DiscogsitemRepo($cont);
};
$container['pollcacheRepo'] = function($cont) {
	return new \Slimpd\Repositories\PollcacheRepo($cont);
};
$container['filesystemUtility'] = function($cont) {
	return new \Slimpd\Utilities\FilesystemUtility($cont);
};

$container['mpd'] = function($cont) {
	return new \Slimpd\Modules\Mpd\Mpd($cont);
};

// -----------------------------------------------------------------------------
// Action factories
// -----------------------------------------------------------------------------
