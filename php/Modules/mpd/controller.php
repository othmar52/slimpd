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
$app->get('/mpdctrl(/:cmd(/:item))', function($cmd, $item='') use ($app, $vars){
	$vars['action'] = 'mpdctrl.' . $cmd;
	$mpd = new \Slimpd\Modules\mpd\mpd();
	$mpd->cmd($cmd, $item);
});

$app->get('/mpdctrl/:cmd/:item+', function($cmd, $item='') use ($app, $vars){
	$vars['action'] = 'mpdctrl.' . $cmd;
	$mpd = new \Slimpd\Modules\mpd\mpd();
	$mpd->cmd($cmd, $item);
});

$app->get('/mpdstatus(/)', function() use ($app, $vars){
	$mpd = new \Slimpd\Modules\mpd\mpd();
	
	# TODO: mpd-version check, v 0.20 has 'duration' included in status()
	# @see: http://www.musicpd.org/doc/protocol/command_reference.html#status_commands
	
	$vars['mpd']['status'] = $mpd->cmd('status');
	if($vars['mpd']['status'] === FALSE) {
		$vars['mpd']['status'] = array(
			"volume" => "0",
			"repeat" => "0",
			"random" => "0",
			"single" => "0",
			"consume" => "0",
			"playlist" => "0",
			"playlistlength" => "0",
			"mixrampdb" => "0.000000",
			"state" => "pause",
			"song" => "0",
			"songid" => "0",
			"time" => "0",
			"elapsed" => "0",
			"bitrate" => "0",
			"audio" => "0",
			"nextsong" => "0",
			"nextsongid" => "0",
			"duration" => "0",
			"percent" => "0"
		);
	}
	try {
		$vars['mpd']['status']['duration'] = $mpd->cmd('currentsong')['Time'];
		$percent = $vars['mpd']['status']['elapsed'] / ($vars['mpd']['status']['duration']/100);
		$vars['mpd']['status']['percent'] = ($percent >=0 && $percent <= 100) ? $percent : 0;
	} catch (\Exception $e) {
		$vars['mpd']['status']['duration'] = "0";
		$vars['mpd']['status']['percent'] = "0";
	}
	deliverJson($vars['mpd']['status']);
	$app->stop();
});


$app->get('/playlist/page/:pagenum', function($pagenum) use ($app, $vars){
	$vars['action'] = 'playlist';
	$mpd = new \Slimpd\Modules\mpd\mpd();
	$vars['item'] = $mpd->getCurrentlyPlayedTrack();
	if($vars['item'] !== NULL) {
		$vars['nowplaying_album'] = \Slimpd\Models\Album::getInstanceByAttributes(
			array('uid' => $vars['item']->getAlbumUid())
		);
	} else {
		// TODO: how to handle mpd played tracks we cant find in database
		$vars['nowplaying_album'] = NULL;
	}
	
	switch($pagenum) {
		case 'current':
			$currentPage = $mpd->getCurrentPlaylistCurrentPage();
			break;
		case 'last':
			$currentPage = $mpd->getCurrentPlaylistTotalPages();
			break;
		default:
			$currentPage = (int)$pagenum;
			break;
	}

	$vars['currentplaylist'] = $mpd->getCurrentPlaylist($currentPage);
	$vars['currentplaylistlength'] = $mpd->getCurrentPlaylistLength();
	
	// get all relational items we need for rendering
	$vars['renderitems'] = getRenderItems($vars['item'], $vars['nowplaying_album'], $vars['currentplaylist']);
	$vars['paginator'] = new JasonGrimes\Paginator(
		$vars['currentplaylistlength'],
		$app->config['mpd-playlist']['max-items'],
		$currentPage,
		$app->config['root'] . 'playlist/page/(:num)'
	);
	$vars['paginator']->setMaxPagesToShow(paginatorPages($currentPage));
	$app->render('surrounding.htm', $vars);
});
