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


$app->get('/djscreen', function() use ($app, $vars){
	$vars['action'] = "djscreen";
	$app->render('djscreen.htm', $vars);
});

foreach(array('artist', 'label', 'genre') as $className) {
	// stringlist of artist|label|genre
	$app->get('/'.$className.'s/:itemParams+', function($itemParams) use ($app, $vars, $className){
		$classPath = "\\Slimpd\\Models\\" . ucfirst($className);
		$vars['action'] = 'library.'. $className .'s';
		$currentPage = 1;
		$itemsPerPage = 100;
		$searchterm = FALSE;
		$orderBy = FALSE;

		foreach($itemParams as $i => $urlSegment) {
			switch($urlSegment) {
				case 'page':
					if(isset($itemParams[$i+1]) === TRUE && is_numeric($itemParams[$i+1]) === TRUE) {
						$currentPage = (int) $itemParams[$i+1];
					}
					break;
				case 'searchterm':
					if(isset($itemParams[$i+1]) === TRUE && strlen(trim($itemParams[$i+1])) > 0) {
						$searchterm = trim($itemParams[$i+1]);
					}
					break;
				default:
					break;
			}
		}

		if($searchterm !== FALSE) {
			$vars['itemlist'] = $classPath::getInstancesLikeAttributes(
				array('az09' => preg_replace('/[^\da-z]/i', '%', $searchterm)),
				$itemsPerPage,
				$currentPage
			);
			$vars['totalresults'] = $classPath::getCountLikeAttributes(
				array('az09' => preg_replace('/[^\da-z]/i', '%', $searchterm))
			);
			$urlPattern = $app->config['root'] .$className.'s/searchterm/'.$searchterm.'/page/(:num)';
		} else {
			$vars['itemlist'] = $classPath::getAll($itemsPerPage, $currentPage);
			$vars['totalresults'] = $classPath::getCountAll();
			$urlPattern = $app->config['root'] . $className.'s/page/(:num)';
		}
		$vars['paginator'] = new JasonGrimes\Paginator(
			$vars['totalresults'],
			$itemsPerPage,
			$currentPage,
			$urlPattern
		);
		$vars['searchterm'] = $searchterm;
		$vars['paginator']->setMaxPagesToShow(paginatorPages($currentPage));
		if($className === 'album') {
			$vars['renderitems'] = getRenderItems($vars['itemlist']);
		} else {
			$vars['renderitems'] = convertInstancesArrayToRenderItems($vars['itemlist']);
		}
		$app->render('surrounding.htm', $vars);
	});
}

$app->get('/library/year/:itemString', function($itemString) use ($app, $vars){
	$vars['action'] = 'library.year';

	$vars['albumlist'] = \Slimpd\Models\Album::getInstancesByAttributes(
		array('year' => $itemString)
	);

	// get all relational items we need for rendering
	$vars['renderitems'] = getRenderItems($vars['albumlist']);
	$app->render('surrounding.htm', $vars);
});

$app->get('/css/spotcolors.css', function() use ($app, $vars){
	$app->response->headers->set('Content-Type', 'text/css');
	$app->render('css/spotcolors.css', $vars);
});

$app->get('/showplaintext/:itemParams+', function($itemParams) use ($app, $vars){
	$vars['action'] = "showplaintext";
	$relPath = join(DS, $itemParams);
	$validPath = getFileRealPath($relPath);
	if($validPath === FALSE) {
		$app->flashNow('error', 'invalid path ' . $relPath);
	} else {
		$vars['plaintext'] = nfostring2html(file_get_contents($validPath));
	}
	$vars['filepath'] = $relPath;
	$app->render('modules/widget-plaintext.htm', $vars);
});




$app->get('/tools/clean-rename-confirm/:itemParams+', function($itemParams) use ($app, $vars){
	if($vars['destructiveness']['clean-rename'] !== '1') {
		$app->notFound();
		return;
	}

	$fileBrowser = new \Slimpd\filebrowser();
	$fileBrowser->getDirectoryContent(join(DS, $itemParams));
	$vars['directory'] = $fileBrowser;
	$vars['action'] = 'clean-rename-confirm';
	$app->render('modules/widget-cleanrename.htm', $vars);
});


$app->get('/tools/clean-rename/:itemParams+', function($itemParams) use ($app, $vars){
	if($vars['destructiveness']['clean-rename'] !== '1') {
		$app->notFound();
		return;
	}

	$fileBrowser = new \Slimpd\filebrowser();
	$fileBrowser->getDirectoryContent(join(DS, $itemParams));

	// do not block other requests of this client
	session_write_close();

	// IMPORTANT TODO: move this to an exec-wrapper
	$cmd = APP_ROOT . 'core/vendor-dist/othmar52/clean-rename/clean-rename '
		. escapeshellarg($app->config['mpd']['musicdir']. $fileBrowser->directory);
	exec($cmd, $result);

	$vars['result'] = join("\n", $result);
	$vars['directory'] = $fileBrowser;
	$vars['cmd'] = $cmd;
	$vars['action'] = 'clean-rename';

	$app->render('modules/widget-cleanrename.htm', $vars);
});
