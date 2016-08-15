<?php

$app->get('/mpdctrl(/:cmd(/:item))', function($cmd, $item='') use ($app, $vars){
	$vars['action'] = 'mpdctrl.' . $cmd;
	$mpd = new \Slimpd\modules\mpd\mpd();
	$mpd->cmd($cmd, $item);
});

$app->get('/mpdctrl/:cmd/:item+', function($cmd, $item='') use ($app, $vars){
	$vars['action'] = 'mpdctrl.' . $cmd;
	$mpd = new \Slimpd\modules\mpd\mpd();
	$mpd->cmd($cmd, $item);
});

$app->get('/mpdstatus(/)', function() use ($app, $vars){
	$mpd = new \Slimpd\modules\mpd\mpd();
	
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
	$mpd = new \Slimpd\modules\mpd\mpd();
	$vars['item'] = $mpd->getCurrentlyPlayedTrack();
	if($vars['item'] !== NULL) {
		$vars['nowplaying_album'] = \Slimpd\Album::getInstanceByAttributes(
			array('id' => $vars['item']->getAlbumId())
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
