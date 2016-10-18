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

// library routes
$ctrlName = 'Slimpd\Modules\Library\Controller';
$app->get('[/]', $ctrlName . ':indexAction');
$app->get('/library[/]', $ctrlName . ':indexAction');

// filebrowser routes
$ctrlName = 'Slimpd\Modules\filebrowser\Controller';
$app->get('/filebrowser', $ctrlName . ':index')->setName('filebrowser');
$app->get('/filebrowser/[{itemParams:.*}]', $ctrlName . ':dircontent');
$app->get('/markup/widget-directory/[{itemParams:.*}]', $ctrlName . ':widgetDirectory');
$app->get('/deliver/[{itemParams:.*}]', $ctrlName . ':deliverAction');

// image routes
$ctrlName = 'Slimpd\Modules\Bitmap\Controller';
$app->get('/imagefallback-{imagesize}/{type}', $ctrlName . ':fallback')->setName('imagefallback');
$app->get('/image-{imagesize}/album/{itemUid}', $ctrlName . ':album')->setName('imagealbum');
$app->get('/image-{imagesize}/track/{itemUid}', $ctrlName . ':track');
$app->get('/image-{imagesize}/id/{itemUid}', $ctrlName . ':bitmap');
$app->get('/image-{imagesize}/path/[{itemParams:.*}]', $ctrlName . ':path');

// album routes
$ctrlName = 'Slimpd\Modules\Album\Controller';
$app->get('/album/{itemUid}', $ctrlName . ':detailAction');
$app->get('/markup/albumtracks/{itemUid}', $ctrlName . ':albumTracksAction');
$app->get('/markup/widget-album/{itemUid}', $ctrlName . ':widgetAlbumAction');
$app->get('/albums/page/{currentPage}/sort/{sort}/{direction}', $ctrlName . ':listAction');

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
$ctrlName = 'Slimpd\Modules\importer\Controller';
$app->get('/importer[/]', $ctrlName . ':indexAction');
$app->get('/importer/triggerUpdate', $ctrlName . ':triggerUpdateAction');
