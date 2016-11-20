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
	'Filebrowser' => [
		['/filebrowser[/]', 'index', 'filebrowser'],
		['/filebrowser/{itemParams:.*}', 'dircontent'],
		['/markup/widget-directory/[{itemParams:.*}]', 'widgetDirectory'],
		['/deliver/[{itemParams:.*}]', 'deliverAction']
	],
	'Bitmap' => [
		['/imagefallback-{imagesize}/{type}','fallback', 'imagefallback'],
		['/image-{imagesize}/album/{itemUid}', 'album', 'imagealbum'],
		['/image-{imagesize}/track/{itemUid}', 'track'],
		['/image-{imagesize}/id/{itemUid}', 'bitmap'],
		['/image-{imagesize}/path/[{itemParams:.*}]', 'path', 'imagepath'],
		['/image-{imagesize}/searchfor/[{itemParams:.*}]', 'searchfor']
	],
	'Album' => [
		['/album/{itemUid}', 'detailAction'],
		['/markup/albumtracks/{itemUid}', 'albumTracksAction'],
		['/markup/widget-album/{itemUid}', 'widgetAlbumAction'],
		['/albums/page/{currentPage}/sort/{sort}/{direction}', 'listAction'],
		['/maintainance/albumdebug/[{itemParams:.*}]', 'editAction'],
		['/maintainance/updatealbum/{itemUid}', 'updateAction', '', 'post'],
		['/album/remigrate/{itemUid}', 'remigrateAction']
	],
	'Systemcheck' => [
		['/systemcheck', 'runAction']
	],
	'Playlist' => [
		['/playlists', 'indexAction'],
		['/showplaylist/[{itemParams:.*}]', 'showAction'],
		['/markup/widget-playlist/[{itemParams:.*}]', 'widgetAction']
	],
	'Track' => [
		['/markup/mpdplayer', 'mpdplayerAction'],
		['/markup/localplayer', 'localplayerAction'],
		['/markup/widget-trackcontrol', 'widgetTrackcontrolAction'],
		#['/markup/widget-xwax', 'widgetXwaxAction'],
		['/markup/widget-deckselector', 'widgetDeckselectorAction'],
		['/markup/standalone-trackview', 'standaloneTrackviewAction'],
		['/maintainance/trackid3/[{itemParams:.*}]', 'dumpid3Action'],
		['/maintainance/trackdebug/[{itemParams:.*}]', 'editAction']
	],
	'Artist' => [
		['/artists/[{itemParams:.*}]', 'listAction'],
	],
	'Genre' => [
		['/genres/[{itemParams:.*}]', 'listAction'],
	],
	'Label' => [
		['/labels/[{itemParams:.*}]', 'listAction'],
	],
	'Tools' => [
		['/css/spotcolors.css', 'spotcolorsCssAction'],
		['/css/localplayer/{relPathHash}', 'localPlayerCssAction'],
		['/css/mpdplayer/{relPathHash}', 'mpdPlayerCssAction'],
		['/css/xwaxplayer/{relPathHash}', 'xwaxPlayerCssAction'],
		['/showplaintext/[{itemParams:.*}]', 'showplaintextAction'],
		['/tools/clean-rename-confirm/[{itemParams:.*}]', 'cleanRenameConfirmAction'],
		['/tools/clean-rename/[{itemParams:.*}]', 'cleanRenameAction'],
	],
	'WaveformGenerator' => [
		['/audiosvg/width/{width}/[{itemParams:.*}]', 'svgAction'],
		['/audiojson/resolution/{width}/[{itemParams:.*}]', 'jsonAction'],
	],
	'Mpd' => [
		['/mpdstatus[/]', 'mpdstatusAction'],
		['/mpdctrl/{cmd}', 'cmdAction'],
		['/mpdctrl/{cmd}/[{item:.*}]', 'cmdAction'],
		['/playlist/page/{pagenum}', 'playlistAction'],
	],
	'Search' => [
		['/{className}/{itemUid}/{show}s/page/{currentPage}/sort/{sort}/{direction}', 'listAction', 'search-list'],
		['/search{currentType}/page/{currentPage}/sort/{sort}/{direction}', 'searchAction', 'search'],
		['/autocomplete/{type}/', 'autocompleteAction', 'autocomplete'],
		['/directory/[{itemParams:.*}]', 'directoryAction'],
		['/alphasearch/', 'alphasearchAction'],
	],
	'Importer' => [
		['/importer[/]', 'indexAction'],
		['/importer/triggerUpdate', 'triggerUpdateAction']
	],
	'Xwax' => [
		['/xwaxstatus[/]', 'statusAction'],
		['/markup/xwaxplayer', 'xwaxplayerAction'],
		['/markup/widget-xwax', 'widgetAction'],
		['/xwax/load_track/{deckIndex}/[{itemParams:.*}]', 'cmdLoadTrackAction'],
		['/xwax/reconnect/{deckIndex}', 'cmdReconnectAction'],
		['/xwax/disconnect/{deckIndex}', 'cmdDisconnectAction'],
		['/xwax/recue/{deckIndex}', 'cmdRecueAction'],
		['/xwax/cycle_timecode/{deckIndex}', 'cmdCycleTimecodeAction'],
		['/xwax/launch', 'cmdLaunchAction'],
		['/xwax/exit', 'cmdExitAction'],
		['/djscreen', 'djscreenAction']
		
	],
];

foreach($ctrlRoutes as $ctrlName => $ctrlRoutes) {
	foreach($ctrlRoutes as $ctrlRoute) {
		$routeName = (isset($ctrlRoute[2]) === TRUE) ? $ctrlRoute[2] : '';
		$routeMethod = (isset($ctrlRoute[3]) === TRUE) ? $ctrlRoute[3] : 'get';
		$app->$routeMethod(
			$ctrlRoute[0],
			'Slimpd\Modules\\'. $ctrlName .'\Controller' . ':' . $ctrlRoute[1]
		)->setName($routeName);
	}
}
