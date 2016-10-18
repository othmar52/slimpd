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
 * FITNESS FOR A PARTICULAR PURPOSE.	See the GNU Affero General Public License
 * for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.	If not, see <http://www.gnu.org/licenses/>.
 */

// Routes

$ctrlRoutes = [
	'Library' => [
		['[/]', 'indexAction'],
		['/library[/]', 'indexAction']
	],
	'filebrowser' => [
		['/filebrowser', 'index', 'filebrowser'],
		['/filebrowser/[{itemParams:.*}]', 'dircontent'],
		['/markup/widget-directory/[{itemParams:.*}]', 'widgetDirectory'],
		['/deliver/[{itemParams:.*}]', 'deliverAction']
	],
	'Bitmap' => [
		['/imagefallback-{imagesize}/{type}','fallback', 'imagefallback'],
		['/image-{imagesize}/album/{itemUid}', 'album', 'imagealbum'],
		['/image-{imagesize}/track/{itemUid}', 'track'],
		['/image-{imagesize}/id/{itemUid}', 'bitmap'],
		['/image-{imagesize}/path/[{itemParams:.*}]', 'path']
	],
	'Album' => [
		['/album/{itemUid}', 'detailAction'],
		['/markup/albumtracks/{itemUid}', 'albumTracksAction'],
		['/markup/widget-album/{itemUid}', 'widgetAlbumAction'],
		['/albums/page/{currentPage}/sort/{sort}/{direction}', 'listAction'],
	]
];

foreach($ctrlRoutes as $ctrlName => $ctrlRoutes) {
	foreach($ctrlRoutes as $ctrlRoute) {
		$routeName = (isset($ctrlRoute[2]) === TRUE) ? $ctrlRoute[2] : '';
		$app->get(
			$ctrlRoute[0],
			'Slimpd\Modules\\'. $ctrlName .'\Controller' . ':' . $ctrlRoute[1]
		)->setName($routeName);
	}
}
// TODO: move to config array above

// track routes
$ctrlName = 'Slimpd\Modules\Track\Controller';
$app->get('/markup/mpdplayer', $ctrlName . ':mpdplayerAction');
$app->get('/markup/localplayer', $ctrlName . ':localplayerAction');
$app->get('/markup/xwaxplayer', $ctrlName . ':xwaxplayerAction');
$app->get('/markup/widget-trackcontrol', $ctrlName . ':widgetTrackcontrolAction');
$app->get('/markup/widget-xwax', $ctrlName . ':widgetXwaxAction');
$app->get('/markup/widget-deckselector', $ctrlName . ':widgetDeckselectorAction');
$app->get('/markup/standalone-trackview', $ctrlName . ':standaloneTrackviewAction');

// artist routes
$ctrlName = 'Slimpd\Modules\Artist\Controller';
$app->get('/artists/[{itemParams:.*}]', $ctrlName . ':listAction');

// genre routes
$ctrlName = 'Slimpd\Modules\Genre\Controller';
$app->get('/genres/[{itemParams:.*}]', $ctrlName . ':listAction');

// label routes
$ctrlName = 'Slimpd\Modules\Label\Controller';
$app->get('/labels/[{itemParams:.*}]', $ctrlName . ':listAction');

// tools routes
$ctrlName = 'Slimpd\Modules\Tools\Controller';
$app->get('/css/spotcolors.css', $ctrlName . ':spotcolorsCssAction');
$app->get('/css/localplayer/{relPathHash}', $ctrlName . ':localPlayerCssAction');
$app->get('/css/mpdplayer/{relPathHash}', $ctrlName . ':mpdPlayerCssAction');
$app->get('/css/xwaxplayer/{relPathHash}', $ctrlName . ':xwaxPlayerCssAction');


// waveformgenerator routes
$ctrlName = 'Slimpd\Modules\WaveformGenerator\Controller';
$app->get('/audiosvg/width/{width}/[{itemParams:.*}]', $ctrlName . ':svgAction');
$app->get('/audiojson/resolution/{width}/[{itemParams:.*}]', $ctrlName . ':jsonAction');


// mpd
$ctrlName = 'Slimpd\Modules\Mpd\Controller';
$app->get('/mpdstatus[/]', $ctrlName . ':mpdstatusAction');
$app->get('/mpdctrl/{cmd}', $ctrlName . ':cmdAction');
$app->get('/mpdctrl/{cmd}/[{item:.*}]', $ctrlName . ':cmdAction');
$app->get('/playlist/page/{pagenum}', $ctrlName . ':playlistAction');

// search
$ctrlName = 'Slimpd\Modules\Search\Controller';
$app->get('/{className}/{itemUid}/{show}s/page/{currentPage}/sort/{sort}/{direction}', $ctrlName . ':listAction')->setName('search-list');
$app->get('/search{currentType}/page/{currentPage}/sort/{sort}/{direction}', $ctrlName . ':searchAction')->setName('search');
$app->get('/autocomplete/{type}/', $ctrlName . ':autocompleteAction')->setName('autocomplete');
$app->get('/directory/[{itemParams:.*}]', $ctrlName . ':directoryAction');
$app->get('/alphasearch/', $ctrlName . ':alphasearchAction');


// importer
$ctrlName = 'Slimpd\Modules\Importer\Controller';
$app->get('/importer[/]', $ctrlName . ':indexAction');
$app->get('/importer/triggerUpdate', $ctrlName . ':triggerUpdateAction');
