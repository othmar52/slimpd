<?php
/* Copyright (C) 2016 othmar52 <othmar52@users.noreply.github.com>
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
$app->get('/playlists', function() use ($app, $vars){
	$vars['action'] = "playlists";
	$app->flash('error', 'playlists not implemented yet - fallback to filebrowser/playlists');
	$app->response->redirect($this->conf['root'] . 'filebrowser/playlists' . getNoSurSuffix(), 301);
});


$app->get('/showplaylist/:itemParams+', function($itemParams) use ($app, $vars){
	$vars['action'] = "showplaylist";
	$playlist = new \Slimpd\Models\PlaylistFilesystem(join(DS, $itemParams));

	if($playlist->getErrorPath() === TRUE) {
		$app->render('surrounding.htm', $vars);
		return;
	}
	
	$itemsPerPage = $this->conf['mpd-playlist']['max-items'];
	
	$totalItems = $playlist->getLength();
	
	if($app->request->get('page') === 'last') {
		$currentPage = ceil($totalItems/$itemsPerPage);
	} else {
		$currentPage = $app->request->get('page');
	}
	$currentPage = ($currentPage) ? $currentPage : 1;
	$minIndex = (($currentPage-1) * $itemsPerPage);
	$maxIndex = $minIndex +  $itemsPerPage;

	$playlist->fetchTrackRange($minIndex, $maxIndex);

	$vars['itemlist'] = $playlist->getTracks();
	$vars['renderitems'] = getRenderItems($vars['itemlist']);
	$vars['playlist'] = $playlist;
	$vars['paginator'] = new JasonGrimes\Paginator(
		$totalItems,
		$itemsPerPage,
		$currentPage,
		$this->conf['root'] . 'showplaylist/'.$playlist->getRelPath() .'?page=(:num)'
	);
	$vars['paginator']->setMaxPagesToShow(paginatorPages($currentPage));
	$app->render('surrounding.htm', $vars);
});

$app->get('/markup/widget-playlist/:itemParams+', function($itemParams) use ($app, $vars){
	$vars['action'] = 'widget-playlist';
	$vars['playlist'] = new \Slimpd\Models\PlaylistFilesystem(join(DS, $itemParams));
	$vars['playlist']->fetchTrackRange(0, 5);
	$vars['playlisttracks'] = $vars['playlist']->getTracks();
	$vars['renderitems'] = getRenderItems($vars['playlist']->getTracks());
	$vars['breadcrumb'] =  \Slimpd\filebrowser::fetchBreadcrumb(join(DS, $itemParams));
	$app->render('modules/widget-playlist.htm', $vars);
});
