<?php

$app->get('/', function() use ($app, $vars){
	$vars['action'] = "landing";
	// TODO: $app->auth->check('library');
    $app->render('surrounding.htm', $vars);
});

$app->get('/library(/)', function() use ($app, $vars){
	$vars['action'] = "landing";
    $app->render('surrounding.htm', $vars);
});

$app->get('/djscreen', function() use ($app, $vars){
	$vars['action'] = "djscreen";
    $app->render('djscreen.htm', $vars);
});

foreach(array('artist', 'label', 'genre') as $className) {
	// stringlist of artist|label|genre
	$app->get('/'.$className.'s/:itemParams+', function($itemParams) use ($app, $vars, $className){
		$classPath = "\\Slimpd\\" . ucfirst($className);
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
				array('az09' => str_replace('*', '%', $searchterm)),
				$itemsPerPage,
				$currentPage
			);
			$vars['totalresults'] = $classPath::getCountLikeAttributes(
				array('az09' => str_replace('*', '%', $searchterm))
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
		$vars['paginator']->setMaxPagesToShow(paginatorPages($currentPage));
		$app->render('surrounding.htm', $vars);
	});
}


$app->get('/library/album(/)', function() use ($app, $vars){
	$vars['action'] = 'library.album';
    $app->render('surrounding.htm', $vars);
});


foreach(['/album', '/markup/albumtracks'] as $what) {
	$app->get($what .'/:albumId', function($albumId) use ($app, $vars, $what){
		$vars['action'] = ($what == '/album') ? 'album.detail' : 'albumtracks';
		$vars['album'] = \Slimpd\Album::getInstanceByAttributes(array('id' => $albumId));
		$vars['itemlist'] = \Slimpd\Track::getInstancesByAttributes(array('albumId' => $albumId));
		$vars['renderitems'] = getRenderItems($vars['album'], $vars['itemlist']);
		$vars['albumimages'] = \Slimpd\Bitmap::getInstancesByAttributes(
			array('albumId' => $albumId)
		);
		
		$vars['breadcrumb'] = \Slimpd\filebrowser::fetchBreadcrumb($vars['album']->getRelativePath());
	
		$app->render('surrounding.htm', $vars);
	});
}

$app->get('/library/year/:itemString', function($itemString) use ($app, $vars){
	$vars['action'] = 'library.year';
	
	$vars['albumlist'] = \Slimpd\Album::getInstancesByAttributes(
		array('year' => $itemString)
	);
	
	// get all relational items we need for rendering
	$vars['renderitems'] = getRenderItems($vars['albumlist']);
    $app->render('surrounding.htm', $vars);
});



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
	$vars['renderitems'] = getRenderItems($vars['nowplaying_album'], $vars['currentplaylist']);
	$vars['paginator'] = new JasonGrimes\Paginator(
		$vars['currentplaylistlength'],
		$app->config['mpd-playlist']['max-items'],
		$currentPage,
		$app->config['root'] . 'playlist/page/(:num)'
	);
	$vars['paginator']->setMaxPagesToShow(paginatorPages($currentPage));
    $app->render('surrounding.htm', $vars);
});


$app->get('/favorites(/)', function() use ($app, $vars){
	$vars['action'] = 'favorites';
    $app->render('surrounding.htm', $vars);
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
	echo json_encode($vars['mpd']['status']);
	$app->stop();
});

$app->get('/xwaxstatus(/)', function() use ($app, $vars){
	$xwax = new \Slimpd\Xwax();
	$deckStats = $xwax->fetchAllDeckStats();
	echo json_encode($deckStats);
	$app->stop();
});

foreach([
		'mpdplayer',
		'localplayer',
		'xwaxplayer',
		'widget-trackcontrol',
		'widget-xwax',
		'widget-deckselector',
		'standalone-trackview'
	] as $markupSnippet ) {

	$app->get('/markup/'.$markupSnippet, function() use ($app, $vars, $markupSnippet){
		
		// maybe we cant find item in mpd or mysql database because it has been accessed via filebrowser
		$itemRelativePath = '';
		
		$templateFile = 'modules/'.$markupSnippet.'.htm';
		$vars['action'] = $markupSnippet;
		if($markupSnippet === 'mpdplayer') {
			$vars['player'] = 'mpd';
			$templateFile = 'partials/player/permaplayer.htm';
		}
		if($markupSnippet === 'localplayer') {
			$vars['player'] = 'local';
			$templateFile = 'partials/player/permaplayer.htm';
		}
		
		$itemsToRender = array();
		
		switch($markupSnippet) {
			case 'mpdplayer':
				$mpd = new \Slimpd\modules\mpd\mpd();
				$vars['item'] = $mpd->getCurrentlyPlayedTrack();
				if($vars['item'] !== NULL) {
					$itemRelativePath = $vars['item']->getRelativePath();
				}
				break;
			case 'xwaxplayer':
				$xwax = new \Slimpd\Xwax();
				$vars['decknum'] = $app->request->get('deck');
				$vars['item'] = $xwax->getCurrentlyPlayedTrack($vars['decknum']);
				
				if($vars['item'] !== NULL) {
					$itemRelativePath = $vars['item']->getRelativePath();
				}
				if($app->request->get('type') == 'djscreen') {
					$markupSnippet = 'standalone-trackview';
					$templateFile = 'modules/standalone-trackview.htm';
				}

				break;
			case 'widget-xwax':
			case 'widget-deckselector':
				$xwax = new \Slimpd\Xwax();
				$vars['xwax']['deckstats'] = $xwax->fetchAllDeckStats();
				foreach($vars['xwax']['deckstats'] as $deckStat) {
					$itemsToRender[] = $deckStat['item'];
				} 
				// no break
			default:
				if(is_numeric($app->request->get('item')) === TRUE) {
					$search = array('id' => (int)$app->request->get('item'));
				} else {
					// TODO: pretty sure we have the pathcheck musicdir/alternative_musicdir somewhere else! find & use it...
					$itemPath = $app->request->get('item');
					if(ALTDIR && strpos($itemPath, $app->config['mpd']['alternative_musicdir']) === 0) {
						$itemPath = substr($itemPath, strlen($app->config['mpd']['alternative_musicdir']));
					}
					$search = array('relativePathHash' => getFilePathHash($itemPath));
					$itemRelativePath = $itemPath;
				}
				$vars['item'] = \Slimpd\Track::getInstanceByAttributes($search);
				// no break
		}
		
		$itemsToRender[] = $vars['item'];
		$vars['renderitems'] = getRenderItems($itemsToRender);
		
		if(is_null($vars['item']) === FALSE && $vars['item']->getId() > 0) {
			$itemRelativePath = $vars['item']->getRelativePath();
		} else {
			// playing track has not been imported in slimpd database yet...
			// so we are not able to get any renderitems
			$item = new \Slimpd\Track();
			$item->setRelativePath($itemRelativePath);
			$item->setRelativePathHash(getFilePathHash($itemRelativePath));
			$vars['item'] = $item;
		}
		
		// TODO: remove external liking as soon we have implemented a proper functionality
		$vars['temp_likerurl'] = 'http://ixwax/filesystem/plusone?f=' .
			urlencode($vars['mpd']['alternative_musicdir'] . $itemRelativePath);
		
		$app->render($templateFile, $vars);
		$app->stop();
	});
	
	$app->get('/css/'.$markupSnippet . '/:relativePathHash', function($relativePathHash) use ($app, $vars, $markupSnippet){
		$vars['relativePathHash'] = $relativePathHash;
		$vars['deck'] = $app->request->get('deck');
		if($markupSnippet === 'localplayer') {
			$vars['color'] = $vars['colors'][ $vars['spotcolor']['local'] ]['1st'];
			$markupSnippet = 'nowplaying';
		}
		if($markupSnippet === 'mpdplayer') {
			$vars['color'] = $vars['colors'][ $vars['spotcolor']['mpd'] ]['1st'];
			$markupSnippet = 'nowplaying';
		}
		if($markupSnippet === 'xwaxplayer') {
			$vars['color'] = $vars['colors'][ $vars['spotcolor']['xwax'] ]['1st'];
			$markupSnippet = 'nowplaying';
		}
		$app->response->headers->set('Content-Type', 'text/css');
		$app->render('css/'.$markupSnippet.'.css', $vars);
	});
}

$app->get('/css/spotcolors.css', function() use ($app, $vars){
	$app->response->headers->set('Content-Type', 'text/css');
	$app->render('css/spotcolors.css', $vars);
});

// predefined album-image sizes
foreach (array(35, 50,100,300,1000) as $imagesize) {
	$app->get('/image-'.$imagesize.'/album/:itemId', function($itemId) use ($app, $vars, $imagesize){
		$image = \Slimpd\Bitmap::getInstanceByAttributes(
			array('albumId' => $itemId), 'filesize DESC'
		);
		if($image === NULL) {
			$app->response->redirect($app->urlFor('imagefallback-'.$imagesize, ['type' => 'album']));
			return;
		}
		
		$image->dump($imagesize);
		exit();
	});
	
	$app->get('/image-'.$imagesize.'/track/:itemId', function($itemId) use ($app, $vars, $imagesize){
		$image = \Slimpd\Bitmap::getInstanceByAttributes(
			array('trackId' => $itemId), 'filesize DESC'
		);
		if($image === NULL) {
			$track = \Slimpd\Track::getInstanceByAttributes(
				array('id' => $itemId), 'filesize DESC'
			);  
			$app->response->redirect($app->config['root'] . 'image-'.$imagesize.'/album/' . $track->getAlbumId());
			return;
		}
		$image->dump($imagesize);
		exit();
	});
	
	$app->get('/image-'.$imagesize.'/id/:itemId', function($itemId) use ($app, $vars, $imagesize){
		$image = \Slimpd\Bitmap::getInstanceByAttributes(
			array('id' => $itemId), 'filesize DESC'
		);
		if($image === NULL) {
			$app->response->redirect($app->urlFor('imagefallback-'.$imagesize, ['type' => 'track']));
			return;
		}
		$image->dump($imagesize);
	});
	
	$app->get('/image-'.$imagesize.'/path/:itemParams+', function($itemParams) use ($app, $vars, $imagesize){
		$image = new \Slimpd\Bitmap();
		
		$image->setRelativePath(join(DS, $itemParams));
		$image->dump($imagesize);
	})->name('imagepath-' .$imagesize);
	
	$app->get('/image-'.$imagesize.'/searchfor/:itemParams+', function($itemParams) use ($app, $vars, $imagesize){
		$importer = new Slimpd\importer();
		$images = $importer->getFilesystemImagesForMusicFile(join(DS, $itemParams));
		
		if(count($images) === 0) {
			$app->response->redirect($app->urlFor('imagefallback-'.$imagesize, ['type' => 'track']));
			return;
		}
		// pick a random image
		shuffle($images);
		$path = array_shift($images);
		
		$app->response->redirect($app->urlFor('imagepath-'.$imagesize, ['itemParams' => path2url($path)]));

	});
	
	$app->get('/imagefallback-'.$imagesize.'/:type', function($type) use ($app, $vars, $imagesize){
		$vars['imagesize'] = $imagesize;
		$vars['color'] = $vars['images']['noimage'][ $vars['playerMode'] ]['color'];
		$vars['backgroundcolor'] = $vars['images']['noimage'][ $vars['playerMode'] ]['backgroundcolor'];
		
		switch($type) {
			case 'artist': $template = 'svg/icon-artist.svg'; break;
			case 'noresults': $template = 'svg/icon-noresults.svg'; break;
			default: $template = 'svg/icon-album.svg';
		}
		$app->response->headers->set('Content-Type', 'image/svg+xml');
		
		header("Content-Type: image/svg+xml");
		$app->render($template, $vars);
	})->name('imagefallback-' .$imagesize);
	
	// missing track or album paramter caused by items that are not imported in slimpd yet
	# TODO: maybe use another fallback image for those items...
	$app->get('/image-'.$imagesize.'/album/', function() use ($app, $vars, $imagesize){
		$app->response->redirect($app->urlFor('imagefallback-'.$imagesize, ['type' => 'album']));
	});
	$app->get('/image-'.$imagesize.'/track/', function() use ($app, $vars, $imagesize){
		$app->response->redirect($app->urlFor('imagefallback-'.$imagesize, ['type' => 'track']));
	});
	
}

$app->get('/importer(/)', function() use ($app, $vars){
	$vars['action'] = 'importer';
	$vars['servertime'] = time();;
	
	$query = "SELECT * FROM importer ORDER BY jobStart DESC,id DESC LIMIT 30;";
	$result = $app->db->query($query);
	while($record = $result->fetch_assoc() ) {
		$record['jobStatistics'] = unserialize($record['jobStatistics']);
		$vars['itemlist'][] = $record;
	}
	$app->render('surrounding.htm', $vars);
});

$app->get('/importer/triggerUpdate', function() use ($app, $vars){
	\Slimpd\importer::queStandardUpdate();
});

$app->get('/audiosvg/width/:width/:itemParam+', function($width, $itemParam) use ($app, $vars){
	$svgGenerator = new \Slimpd\Svggenerator($itemParam);
	$svgGenerator->generateSvg($width);
});

$app->get('/audiojson/resolution/:width/:itemParam+', function($resolution, $itemParam) use ($app, $vars){
	$svgGenerator = new \Slimpd\Svggenerator($itemParam);
	$svgGenerator->generateJson($resolution);
});

$app->get('/filebrowser', function() use ($app, $vars){
	$vars['action'] = 'filebrowser';
	$fileBrowser = new \Slimpd\filebrowser();
	$fileBrowser->getDirectoryContent($vars['mpd']['musicdir']);
	$vars['breadcrumb'] = $fileBrowser->breadcrumb;
	$vars['subDirectories'] = $fileBrowser->subDirectories;
	$vars['files'] = $fileBrowser->files;
	$vars['hotlinks'] = array();
	foreach($vars['filebrowser-hotlinks'] as $path){
		$vars['hotlinks'][] =  \Slimpd\filebrowser::fetchBreadcrumb($path);
	}
	
	$app->render('surrounding.htm', $vars);
})->name('filebrowser');

$app->get('/filebrowser/:itemParams+', function($itemParams) use ($app, $vars){
	$vars['action'] = 'filebrowser';
	
	$fileBrowser = new \Slimpd\filebrowser();
	$fileBrowser->itemsPerPage = $app->config['filebrowser']['max-items'];
	$fileBrowser->currentPage = intval($app->request->get('page'));
	$fileBrowser->currentPage = ($fileBrowser->currentPage === 0) ? 1 : $fileBrowser->currentPage;
	switch($app->request->get('filter')) {
		case 'dirs':
			$fileBrowser->filter = 'dirs';
			break;
		case 'files':
			$fileBrowser->filter = 'files';
			break;
		default :
			break;
	}
	
	switch($app->request->get('neighbour')) {
		case 'next':
			$fileBrowser->getNextDirectoryContent(join(DS, $itemParams));
			break;
		case 'prev':
			$fileBrowser->getPreviousDirectoryContent(join(DS, $itemParams));
			break;
		case 'up':
			$fileBrowser->getDirectoryContent(dirname(join(DS, $itemParams)));
			if($fileBrowser->directory === './') {
				$app->response->redirect($app->urlFor('filebrowser') . getNoSurSuffix());
				return;
			}
			break;
		default:
			$fileBrowser->getDirectoryContent(join(DS, $itemParams));
			break;
	}

	$vars['directory'] = $fileBrowser->directory;
	$vars['breadcrumb'] = $fileBrowser->breadcrumb;
	$vars['subDirectories'] = $fileBrowser->subDirectories;
	$vars['files'] = $fileBrowser->files;
	$vars['filter'] = $fileBrowser->filter;
	
	switch($fileBrowser->filter) {
		case 'dirs':
			$totalFilteredItems = $fileBrowser->subDirectories['total'];
			$vars['showDirFilterBadge'] = FALSE;
			$vars['showFileFilterBadge'] = FALSE;
			break;
		case 'files':
			$totalFilteredItems = $fileBrowser->files['total'];
			$vars['showDirFilterBadge'] = FALSE;
			$vars['showFileFilterBadge'] = FALSE;
			break;
		default :
			$totalFilteredItems = 0;
			$vars['showDirFilterBadge'] = ($fileBrowser->subDirectories['count'] < $fileBrowser->subDirectories['total'])
				? TRUE
				: FALSE;
			
			$vars['showFileFilterBadge'] = ($fileBrowser->files['count'] < $fileBrowser->files['total'])
				? TRUE
				: FALSE;
			break;
	}
	
	$vars['paginator'] = new JasonGrimes\Paginator(
		$totalFilteredItems,
		$fileBrowser->itemsPerPage,
		$fileBrowser->currentPage,
		$app->config['root'] . 'filebrowser/'.$fileBrowser->directory . '?filter=' . $fileBrowser->filter . '&page=(:num)'
	);
	$vars['paginator']->setMaxPagesToShow(paginatorPages($fileBrowser->currentPage));
	$app->render('surrounding.htm', $vars);
});


$app->get('/markup/widget-directory/:itemParams+', function($itemParams) use ($app, $vars){
	$vars['action'] = 'filebrowser';
	$fileBrowser = new \Slimpd\filebrowser();

	$fileBrowser->getDirectoryContent(join(DS, $itemParams));
	$vars['directory'] = $fileBrowser->directory;
	$vars['breadcrumb'] = $fileBrowser->breadcrumb;
	$vars['subDirectories'] = $fileBrowser->subDirectories;
	$vars['files'] = $fileBrowser->files;
	
	/// try to fetch album entry for this directory
	$vars['album'] = \Slimpd\Album::getInstanceByAttributes(
		array('relativePathHash' => getFilePathHash($fileBrowser->directory))
	);
	$app->render('modules/widget-directory.htm', $vars);
});


$app->get('/playlists', function() use ($app, $vars){
	$vars['action'] = "playlists";
	$app->flash('error', 'playlists not implemented yet - fallback to filebrowser/playlists');
	$app->response->redirect($app->config['root'] . 'filebrowser/playlists' . getNoSurSuffix(), 301);
});


$app->get('/showplaylist/:itemParams+', function($itemParams) use ($app, $vars){
	$vars['action'] = "showplaylist";
	$playlist = new \Slimpd\playlist\playlist(join(DS, $itemParams));

	if($playlist->getErrorPath() === TRUE) {
		$app->render('surrounding.htm', $vars);
		return;
	}
	
	$itemsPerPage = \Slim\Slim::getInstance()->config['mpd-playlist']['max-items'];
	
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
		$app->config['root'] . 'showplaylist/'.$playlist->getRelativePath() .'?page=(:num)'
	);
	$vars['paginator']->setMaxPagesToShow(paginatorPages($currentPage));
    $app->render('surrounding.htm', $vars);
});

$app->get('/markup/widget-playlist/:itemParams+', function($itemParams) use ($app, $vars){
	$vars['action'] = 'widget-playlist';
	$vars['playlist'] = new \Slimpd\playlist\playlist(join(DS, $itemParams));
	$vars['playlist']->fetchTrackRange(0, 5);
	$vars['playlisttracks'] = $vars['playlist']->getTracks();
	$vars['renderitems'] = getRenderItems($vars['playlist']->getTracks());
	$vars['breadcrumb'] =  \Slimpd\filebrowser::fetchBreadcrumb(join(DS, $itemParams));
	$app->render('modules/widget-playlist.htm', $vars);
});


$app->get('/showplaintext/:itemParams+', function($itemParams) use ($app, $vars){
	$vars['action'] = "showplaintext";
	$relativePath = join(DS, $itemParams);
	$validPath = '';
	foreach([$app->config['mpd']['musicdir'], $app->config['mpd']['alternative_musicdir']] as $path) {
		if(is_file($path . $relativePath) === TRUE) {
			$validPath = realpath($path . $relativePath);
			if(strpos($validPath, $path) !== 0) {
				$validPath = '';
			}
		}
	}
	if($validPath === '') {
		$app->flashNow('error', 'invalid path ' . $relativePath);
	} else {
		$vars['plaintext'] = nfostring2html(file_get_contents($validPath));
	}
	$vars['filepath'] = $relativePath;
	$app->render('modules/widget-plaintext.htm', $vars);
});



$app->get('/maintainance/trackdebug/:itemParams+', function($itemParams) use ($app, $vars){
	$vars['action'] = 'maintainance.trackdebug';
	$itemRelativePath = '';
	$itemRelativePathHash = '';
	if(count($itemParams) === 1 && is_numeric($itemParams[0])) {
		$search = array('id' => (int)$itemParams[0]);
	} else {
		$itemRelativePath = join(DS, $itemParams);
		$itemRelativePathHash = getFilePathHash($itemRelativePath);
		$search = array('relativePathHash' => $itemRelativePathHash);
	}
	$vars['item'] = \Slimpd\Track::getInstanceByAttributes($search);
	if($vars['item'] === NULL) {
		$item = new \Slimpd\Track();
		$item->setRelativePath($itemRelativePath);
		$item->setRelativePathHash($itemRelativePathHash);
		$vars['item'] = $item;
	}
	$vars['itemraw'] = \Slimpd\Rawtagdata::getInstanceByAttributes($search);
	$vars['renderitems'] = getRenderItems($vars['item']);
	$app->render('surrounding.htm', $vars);
});


$app->get('/maintainance/albumdebug/:itemParams+', function($itemParams) use ($app, $vars){
	$vars['action'] = 'maintainance.albumdebug';
	if(count($itemParams) === 1 && is_numeric($itemParams[0])) {
		$search = array('id' => (int)$itemParams[0]);
	}
	
	$vars['album'] = \Slimpd\Album::getInstanceByAttributes($search);

	$tmp = \Slimpd\Track::getInstancesByAttributes(array('albumId' => $vars['album']->getId()));
	$trackInstances = array();
	$rawTagDataInstances = array();
	foreach($tmp as $t) {
		$vars['itemlist'][$t->getId()] = $t;
		$vars['itemlistraw'][$t->getId()] = \Slimpd\Rawtagdata::getInstanceByAttributes(array('id' => (int)$t->getId()));
	}
	#echo "<pre>" . print_r(array_keys($trackInstances),1) . "</pre>";
	unset($tmp);
	
	$vars['discogstracks'] = array();
	$vars['matchmapping'] = array();
	
	$discogsId = $app->request->get('discogsid');
	if($discogsId !== NULL) {
		
		/* possible usecases:
		 * we have same track amount on local side and discogs side
		 *   each local track matches to one discogs track
		 *   one ore more local track does not have a match on the discogs side
		 *   two local tracks matches one discogs-track 
		 * 
		 * we have more tracks on the local side
		 *   we have dupes on the local side
		 *   we have tracks on the local side that dous not exist on the discogs side
		 * 
		 * we have more tracks on the discogs side
		 *   all local tracks exists on the discogs side
		 *   some local tracks does not have a track on the discogs side
		 * 
		 * 
		 */
		
		$discogsItem = new \Slimpd\Discogsitem($discogsId);
		$vars['matchmapping'] = $discogsItem->guessTrackMatch($vars['itemlistraw']);
		$vars['discogstracks'] = $discogsItem->trackstrings;
		$vars['discogsalbum'] = $discogsItem->albumAttributes;
	}
	
	$vars['renderitems'] = getRenderItems($vars['itemlist'], $vars['album']);
	$app->render('surrounding.htm', $vars);
});


// TODO: carefully check which sorting is possible for each model (@see config/sphinx.example.conf:srcslimpdmain)
//   compare with templates/partials/dropdown-search-sorting.htm
//   compare with templates/partials/dropdown-typelist-sorting.htm
$sortfields1 = array(
	'artist' => array('title', 'trackCount', 'albumCount'),
	'genre' => array('title', 'trackCount', 'albumCount'),
	'label' => array('title', 'trackCount', 'albumCount')
);

$sortfields2 = array(
	'all' => array('title', 'artist', 'year', 'added'),
	'track' => array('title', 'artist', 'year', 'added'),
	'album' => array('year', 'title', 'added', 'artist', 'trackCount'),
	'dirname' => array('title', 'added', 'trackCount'),
);

foreach(array_keys($sortfields1) as $className) {
	foreach(['album','track'] as $show) {
		
		// albumlist+tracklist of artist|genre|label
		$app->get(
		'/'.$className.'/:idemId/'.$show.'s/page/:currentPage/sort/:sort/:direction',
		function($itemId, $currentPage, $sort, $direction) use ($app, $vars, $className, $show, $sortfields1) {
			$vars['action'] = $className.'.' . $show.'s';
			$vars['itemtype'] = $className;
			$vars['listcurrent'] = $show;
			$vars['itemlist'] = [];
			
			$classPath = "\\Slimpd\\" . ucfirst($className);
			
			// TODO: check where %20 on multiple artist-ids come from
			$itemId = str_replace('%20', ',', $itemId);
			
			$term = str_replace(",", " ", $itemId);
			$vars['item'] = $classPath::getInstanceByAttributes(array('id' => $itemId));
			
			$vars['itemids'] = $itemId;
			$itemsPerPage = 20;
			$maxCount = 1000;
			
			// TODO: move sphinx constants to somewhere else
			foreach(['freq_threshold', 'suggest_debug', 'length_threshold', 'levenshtein_threshold', 'top_count'] as $var) {
				define (strtoupper($var), intval($app->config['sphinx'][$var]) );
			}
			$ln_sph = new \PDO('mysql:host='.$app->config['sphinx']['host'].';port=9306;charset=utf8;', '','');
			
			foreach(['album','track'] as $resultType) {
				
				// get total results for all types (albums + tracks)
				$sphinxTypeIndex = ($resultType === 'album') ? 2 : 4;
				$stmt = $ln_sph->prepare("
					SELECT id FROM ". $app->config['sphinx']['mainindex']."
					WHERE MATCH('@".$className."Ids \"". $term ."\"')
					AND type=:type
					GROUP BY itemid,type
					LIMIT 1;
				");
				$stmt->bindValue(':type', $sphinxTypeIndex, PDO::PARAM_INT);
				$stmt->execute();
				$meta = $ln_sph->query("SHOW META")->fetchAll();
				$vars['search'][$resultType]['total'] = 0;
				foreach($meta as $m) {
					if($m['Variable_name'] === 'total_found') {
						$vars['search'][$resultType]['total'] = $m['Value'];
					}
				}
				$vars['search'][$resultType]['time'] = 0;
				$vars['search'][$resultType]['term'] = $itemId;
				$vars['search'][$resultType]['matches'] = [];
				
				if($resultType === $show) {
	
					$sortQuery = ($sort !== 'relevance')?  ' ORDER BY ' . $sort . ' ' . $direction : '';
					$vars['search']['activesorting'] = $sort . '-' . $direction;
					
					$stmt = $ln_sph->prepare("
						SELECT id,type,itemid,artistIds,display
						FROM ". $app->config['sphinx']['mainindex']."
						WHERE MATCH('@".$className."Ids \"". $term ."\"')
						AND type=:type
						GROUP BY itemid,type
							".$sortQuery."
							LIMIT :offset,:max
						OPTION ranker=proximity, max_matches=".$vars['search'][$resultType]['total'].";
					");
					$stmt->bindValue(':offset', ($currentPage-1)*$itemsPerPage , PDO::PARAM_INT);
					$stmt->bindValue(':max', $itemsPerPage, PDO::PARAM_INT);
					$stmt->bindValue(':type', $sphinxTypeIndex, PDO::PARAM_INT);
					
					$vars['search'][$resultType]['time'] = microtime(TRUE);
					
					$stmt->execute();
					$rows = $stmt->fetchAll();
					
					foreach($rows as $row) {
						switch($row['type']) {
							case '2':
								$obj = \Slimpd\Album::getInstanceByAttributes(array('id' => $row['itemid']));
								break; 
							case '4':
								$obj = \Slimpd\Track::getInstanceByAttributes(array('id' => $row['itemid']));
								break;
						}
						$vars['itemlist'][] = $obj;
					}
					
					$vars['search'][$resultType]['time'] = number_format(microtime(TRUE) - $vars['search'][$resultType]['time'],3);
					
					$vars['paginator'] = new JasonGrimes\Paginator(
						$vars['search'][$resultType]['total'],
						$itemsPerPage,
						$currentPage,
						$app->config['root'] .$className.'/'.$itemId.'/'.$show.'s/page/(:num)/sort/'.$sort.'/'.$direction
					);
					$vars['paginator']->setMaxPagesToShow(paginatorPages($currentPage));
				}
			}
			$vars['renderitems'] = getRenderItems($vars['itemlist']);
		    $app->render('surrounding.htm', $vars);
		});
		
	}
}


$app->get('/alphasearch/', function() use ($app, $vars){
	$type = $app->request()->get('searchtype');
	$term = $app->request()->get('searchterm');
	$app->response->redirect($app->config['root'] . $type.'s/searchterm/'.rawurlencode($term).'/page/1' . getNoSurSuffix());
});

$sortfields = array_merge($sortfields1, $sortfields2);
foreach(array_keys($sortfields) as $currentType) {
	$app->get(
		'/search'.$currentType.'/page/:currentPage/sort/:sort/:direction',
		function($currentPage, $sort, $direction) use ($app, $vars, $currentType, $sortfields){
		foreach(['freq_threshold', 'suggest_debug', 'length_threshold', 'levenshtein_threshold', 'top_count'] as $var) {
			define (strtoupper($var), intval($app->config['sphinx'][$var]) );
		}
		
		# TODO: evaluate if modifying searchterm makes sense
		// "Artist_-_Album_Name-(CAT001)-WEB-2015" does not match without this modification
		$term = cleanSearchterm($app->request()->params('q'));
		
		
		# TODO: read a blacklist of searchterms from configfile
		// searching 'mp3' can be bad for our snappy gui
		// at least we have to skip the "total results" query for each type
		// for now - redirect immediately
		if(strtolower(trim($term)) === 'mp3' || strtolower(trim($term)) === 'mu') {
			$app->flashNow('error', 'OH SNAP! searchterm <strong>'. $term .'</strong> is currently blacklisted...');
			$app->render('surrounding.htm', $vars);
			return;
		}
		
		$ranker = 'sph04';
		$start = 0;
		$itemsPerPage = 20;
		$maxCount = 1000;
		$result = [];
		
		$ln_sph = new \PDO('mysql:host='.$app->config['sphinx']['host'].';port=9306;charset=utf8;', '','');
		
		// those values have to match sphinxindex:srcslimpdmain:type
		$filterTypeMapping = array(
			'artist' => 1,
			'album' => 2,
			'label' => 3,
			'track' => 4,
			'genre' => 5,
			'dirname' => 6,
		);
		$vars['itemlist'] = [];
		$vars['timelog'] = [];

		foreach(array_keys($sortfields) as $type) {
			$vars['timelog'][$type.'-total'] = new \Slimpd\ExecutionTime();
			$vars['timelog'][$type.'-total']->Start();
			// get result count for each resulttype 
			$stmt = $ln_sph->prepare("
				SELECT itemid,type FROM ". $app->config['sphinx']['mainindex']."
				WHERE MATCH(:match)
				" . (($type !== 'all') ? ' AND type=:type ' : '') . "
				GROUP BY itemid,type
				LIMIT 1;
			");
			$stmt->bindValue(
				':match', "
				(' \"". addStars($term) . "\"') |
				('\"". $term ."\"') |
				('\"". str_replace(' ', '*', $term) ."\"')
				",
				PDO::PARAM_STR
			);
			if(($type !== 'all')) {
				$stmt->bindValue(':type', $filterTypeMapping[$type], PDO::PARAM_INT);
			}
			
			$stmt->execute();
			$meta = $ln_sph->query("SHOW META")->fetchAll();
			$vars['search'][$type]['total'] = 0;
			foreach($meta as $m) {
				if($m['Variable_name'] === 'total_found') {
					$vars['search'][$type]['total'] = $m['Value'];
				}
			}
			$vars['search'][$type]['time'] = 0;
			$vars['search'][$type]['term'] = $term;
			$vars['search'][$type]['matches'] = [];
			
			$vars['timelog'][$type.'-total']->End();

			// get results only for requestet result-type
			if($type == $currentType) {
				$vars['timelog'][$type] = new \Slimpd\ExecutionTime();
				$vars['timelog'][$type]->Start();
				$sortfield = (in_array($sort, $sortfields[$currentType]) === TRUE) ? $sort : 'relevance';
				$direction = ($direction == 'asc') ? 'asc' : 'desc';
				$vars['search']['activesorting'] = $sortfield . '-' . $direction;
				
				$sortQuery = ($sortfield !== 'relevance')?  ' ORDER BY ' . $sortfield . ' ' . $direction : '';
				
				$vars['search'][$type]['time'] = microtime(TRUE);
				
				$stmt = $ln_sph->prepare("
					SELECT id,type,itemid,display FROM ". $app->config['sphinx']['mainindex']."
					WHERE MATCH(:match)
					" . (($currentType !== 'all') ? ' AND type=:type ' : '') . "
					GROUP BY itemid,type
					".$sortQuery."
					LIMIT :offset,:max
					OPTION ranker=".$ranker.",max_matches=".$vars['search'][$type]['total'].";");
				$stmt->bindValue(
					':match', "
					(' \"". addStars($term) . "\"') |
					('\"". $term ."\"') |
					('\"". str_replace(' ', '*', $term) ."\"')
					",
					PDO::PARAM_STR
				);
				$stmt->bindValue(':offset', ($currentPage-1)*$itemsPerPage , PDO::PARAM_INT);
				$stmt->bindValue(':max', $itemsPerPage, PDO::PARAM_INT);
				if(($currentType !== 'all')) {
					$stmt->bindValue(':type', $filterTypeMapping[$currentType], PDO::PARAM_INT);
				}
				
				$urlPattern = $app->config['root'] . 'search'.$type.'/page/(:num)/sort/'.$sortfield.'/'.$direction.'?q=' . $term;
				$vars['paginator'] = new JasonGrimes\Paginator(
					$vars['search'][$type]['total'],
					$itemsPerPage,
					$currentPage,
					$urlPattern
				);
				$vars['paginator']->setMaxPagesToShow(paginatorPages($currentPage));
				
				$stmt->execute();
				$rows = $stmt->fetchAll();
				$meta = $ln_sph->query("SHOW META")->fetchAll();
				foreach($meta as $m) {
				    $meta_map[$m['Variable_name']] = $m['Value'];
				}
				
				if(count($rows) === 0 && !$app->request()->params('nosuggestion')) {
					$words = array();
					foreach($meta_map as $k=>$v) {
						if(preg_match('/keyword\[\d+]/', $k)) {
							preg_match('/\d+/', $k,$key);
							$key = $key[0];
							$words[$key]['keyword'] = $v;
						}
						if(preg_match('/docs\[\d+]/', $k)) {
							preg_match('/\d+/', $k,$key);
							$key = $key[0];
							$words[$key]['docs'] = $v;
						}
					}
					$suggest = MakePhaseSuggestion($words, $term, $ln_sph);
					if($suggest !== FALSE) {
						$app->response->redirect($app->urlFor(
							'search'.$currentType,
							[
								'type' => $currentType,
								'currentPage' => $currentPage,
								'sort' => $sort,
								'direction' => $direction
							]
						) . '?nosuggestion=1&q='.$suggest . '&' . getNoSurSuffix(FALSE));
						$app->stop();
					}
					$result[] = [
						'label' => 'nothing found',
						'url' => '#',
						'type' => '',
						'img' => '/skin/default/img/icon-label.png' // TODO: add not-found-icon
					];
				} else {
					$filterTypeMappingF = array_flip($filterTypeMapping);
					foreach($rows as $row) {
						switch($filterTypeMappingF[$row['type']]) {
							case 'artist':
							case 'label':
							case 'album':
							case 'track':
							case 'genre':
								$classPath = "\\Slimpd\\" . ucfirst($filterTypeMappingF[$row['type']]);
								$obj = $classPath::getInstanceByAttributes(array('id' => $row['itemid']));
								break;
							case 'dirname':
								$tmp = \Slimpd\Album::getInstanceByAttributes(array('id' => $row['itemid']));
								$obj = new \Slimpd\_Directory($tmp->getRelativePath());
								$obj->breadcrumb = \Slimpd\filebrowser::fetchBreadcrumb($obj->fullpath);
								break;
						}
						$vars['itemlist'][] = $obj;
					}
				}
				$vars['search'][$type]['time'] = number_format(microtime(TRUE) - $vars['search'][$type]['time'],3);
				$vars['timelog'][$type]->End();
			}
		}
		$vars['action'] = 'searchresult.' . $currentType;
		$vars['searchcurrent'] = $currentType;
		$vars['renderitems'] = getRenderItems($vars['itemlist']);
		$app->render('surrounding.htm', $vars);
			
	})->name('search'.$currentType);
}


$app->get('/autocomplete/:type/', function($type) use ($app, $vars) {
	foreach(['freq_threshold', 'suggest_debug', 'length_threshold', 'levenshtein_threshold', 'top_count'] as $var) {
		define (strtoupper($var), intval($app->config['sphinx'][$var]) );
	}
	$term = $app->request->get('q');
	
	$originalTerm = ($app->request->get('qo')) ? $app->request->get('qo') : $term;
	
	# TODO: evaluate if modifying searchterm makes sense
	// "Artist_-_Album_Name-(CAT001)-WEB-2015" does not match without this modification
	
	$term = cleanSearchterm($term);
	$start =0;
	$offset =20;
	$current = 1;
	$result = [];
	
	$ln_sph = new \PDO('mysql:host='.$app->config['sphinx']['host'].';port=9306;charset=utf8;', '','');
	
	// those values have to match sphinxindex:srcslimpdautocomplete
	$filterTypeMapping = array(
		'artist' => 1,
		'album' => 2,
		'label' => 3,
		'track' => 4,
		'genre' => 5,
		'dirname' => 6,
	);
	
	$stmt = $ln_sph->prepare("
		SELECT id,type,itemid,display,trackCount,albumCount FROM ". $app->config['sphinx']['mainindex']."
		WHERE MATCH(:match)
		" . (($type !== 'all') ? ' AND type=:type ' : '') . "
		GROUP BY itemid,type
		LIMIT $start,$offset
		OPTION ranker=sph04");
	
	if(($type !== 'all')) {
		$stmt->bindValue(':type', $filterTypeMapping[$type], PDO::PARAM_INT);
	}
	$stmt->bindValue(
		':match', "
		(' \"". addStars($originalTerm) . "\"') |
		(' \"". addStars($term) . "\"') |
		('\"". $originalTerm ."\"') |
		('\"". $term ."\"') |
		('\"". str_replace(' ', '*', $originalTerm) ."\"')
		",
		PDO::PARAM_STR
	);
	$stmt->execute();
	$rows = $stmt->fetchAll();
	$meta = $ln_sph->query("SHOW META")->fetchAll();
	foreach($meta as $m) {
	    $meta_map[$m['Variable_name']] = $m['Value'];
	}
	if(count($rows) === 0 && $app->request->get('suggested') != 1) {
		$words = array();
		foreach($meta_map as $k=>$v) {
			if(preg_match('/keyword\[\d+]/', $k)) {
				preg_match('/\d+/', $k,$key);
				$key = $key[0];
				$words[$key]['keyword'] = $v;
			}
			if(preg_match('/docs\[\d+]/', $k)) {
				preg_match('/\d+/', $k,$key);
				$key = $key[0];
				$words[$key]['docs'] = $v;
			}
		}
		$suggest = MakePhaseSuggestion($words, $term, $ln_sph);
		if($suggest !== FALSE) {
			$app->response->redirect(
				$app->urlFor(
					'autocomplete',
					array(
						'type' => $type
					)
				) . '?suggested=1&q=' . rawurlencode($suggest) . '&qo=' . rawurlencode($term)
			);
			$app->stop();
		}
	} else {
		$filterTypeMapping = array_flip($filterTypeMapping);
		$cl = new SphinxClient();
		foreach($rows as $row) {
			$excerped = $cl->BuildExcerpts([$row['display']], $app->config['sphinx']['mainindex'], $term);
			$filterType = $filterTypeMapping[$row['type']];
			
			switch($filterType) {
				case 'track':
					$url = 'searchall/page/1/sort/relevance/desc?q=' . $row['display'];
					break;
				case 'album':
				case 'dirname':
					$url = 'album/' . $row['itemid'];
					break;
				default:
					$url = $filterType . '/' . $row['itemid'] . '/tracks/page/1/sort/added/desc';
					break;
			}

			$entry = [
				'label' => $excerped[0],
				'url' => $app->config['root'] . $url,
				'type' => $filterType,
				'typelabel' => $app->ll->str($filterType),
				'itemid' => $row['itemid'],
				'trackcount' => $row['trackcount'],
				'albumcount' => $row['albumcount']
			];
			switch($filterType) {
				case 'artist':
					$entry['img'] = $app->config['root'] . 'imagefallback-50/artist';
					break;
				case 'label':
				case 'genre':
				case 'dirname':
					$entry['img'] = $app->config['root'] . 'skin/default/img/icon-'. $filterType .'.png';
					break;
				case 'album':
				case 'track':
					$entry['img'] = $app->config['root'] . 'image-50/'. $filterType .'/' . $row['itemid'];
					break;
			}
			$result[] = $entry;
		}
	}
	if(count($result) === 0) {
		$result[] = [
			'label' => $app->ll->str('autocomplete.' . $type . '.noresults', [$originalTerm]),
			'url' => '#',
			'type' => '',
			'img' => $app->config['root'] . 'imagefallback-50/noresults'
		];
	}
	echo json_encode($result); exit;
})->name('autocomplete');


$app->get('/directory/:itemParams+', function($itemParams) use ($app, $vars){

	// validate directory
	$directory = new \Slimpd\_Directory(join(DS, $itemParams));
	if($directory->validate() === FALSE) {
		$app->flashNow('error', $app->ll->str('directory.notfound'));
		$app->render('surrounding.htm', $vars);
		return;
	}

	// get total items of directory from sphinx
	$ln_sph = new \PDO('mysql:host='.$app->config['sphinx']['host'].';port=9306;charset=utf8;', '','');
	$stmt = $ln_sph->prepare("
		SELECT id
		FROM ". $app->config['sphinx']['mainindex']."
		WHERE MATCH(:match)
		AND type=:type
		LIMIT 1;
	");
	$stmt->bindValue(':match', "'@allchunks \"". join(DS, $itemParams) . DS . "\"'", PDO::PARAM_STR);
	$stmt->bindValue(':type', 4, PDO::PARAM_INT);
	$stmt->execute();
	$meta = $ln_sph->query("SHOW META")->fetchAll();
	$total = 0;
	foreach($meta as $m) {
		if($m['Variable_name'] === 'total_found') {
			$total = $m['Value'];
		}
	}

	// get requestet portion of track-ids from sphinx
	$itemsPerPage = 20;
	$currentPage = intval($app->request->get('page'));
	$currentPage = ($currentPage === 0) ? 1 : $currentPage;
	$ln_sph = new \PDO('mysql:host='.$app->config['sphinx']['host'].';port=9306;charset=utf8;', '','');
	$stmt = $ln_sph->prepare("
		SELECT itemid
		FROM ". $app->config['sphinx']['mainindex']."
		WHERE MATCH('@allchunks \"". $directory->fullpath. "\"')
		AND type=:type
		ORDER BY allchunks ASC
		LIMIT :offset,:max
		OPTION max_matches=".$total.";
	");
	$stmt->bindValue(':type', 4, PDO::PARAM_INT);
	$stmt->bindValue(':offset', ($currentPage-1)*$itemsPerPage , PDO::PARAM_INT);
	$stmt->bindValue(':max', $itemsPerPage, PDO::PARAM_INT);
	$stmt->execute();
	$rows = $stmt->fetchAll();
	$vars['itemlist'] = [];
	foreach($rows as $row) {
		$vars['itemlist'][] = \Slimpd\Track::getInstanceByAttributes(array('id' => $row['itemid']));
	}

	// get additional stuff we need for rendering the view
	$vars['action'] = 'directorytracks';
	$vars['renderitems'] = getRenderItems($vars['itemlist']);
	$vars['breadcrumb'] = \Slimpd\filebrowser::fetchBreadcrumb(join(DS, $itemParams));
	$vars['paginator'] = new JasonGrimes\Paginator(
		$total,
		$itemsPerPage,
		$currentPage,
		$app->config['root'] . 'directory/'.join(DS, $itemParams) . '?page=(:num)'
	);
	$vars['paginator']->setMaxPagesToShow(paginatorPages($currentPage));
	$app->render('surrounding.htm', $vars);
});


$app->get('/deliver/:item+', function($item) use ($app, $vars){
	$path = join(DS, $item);
	if(is_numeric($path)) {
		$track = \Slimpd\Track::getInstanceByAttributes(array('id' => (int)$path));
		$path = ($track === NULL) ? '' : $track->getRelativePath();
	}
	if(is_file($app->config['mpd']['musicdir'] . $path) === TRUE) {
		deliver($app->config['mpd']['musicdir'] . $path, $app);
		$app->stop();
	}
	
	if(is_file($app->config['mpd']['alternative_musicdir'] . $path) === TRUE) {
		deliver($app->config['mpd']['alternative_musicdir'] . $path, $app);
		$app->stop();
	}
	echo "Ivalid file: " . $path;
	$app->stop();
});

$app->get('/xwax/:cmd/:params+', function($cmd, $params) use ($app, $vars){
	$xwax = new \Slimpd\Xwax();
	$xwax->cmd($cmd, $params, $app);
	$app->stop();
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
	$cmd = APP_ROOT . 'vendor-dist/othmar52/clean-rename/clean-rename '
		. escapeshellarg($app->config['mpd']['musicdir']. $fileBrowser->directory);
	exec($cmd, $result);
	
	$vars['result'] = join("\n", $result);
	$vars['directory'] = $fileBrowser;
	$vars['cmd'] = $cmd;
	$vars['action'] = 'clean-rename';
	
	$app->render('modules/widget-cleanrename.htm', $vars);
});


$app->get('/systemcheck', function() use ($app, $vars){
	$systemCheck = new \Slimpd\Systemcheck();
	$vars['sys'] = $systemCheck->runChecks();
	$vars['appRoot'] = APP_ROOT;
	$app->render('systemcheck.htm', $vars);
});