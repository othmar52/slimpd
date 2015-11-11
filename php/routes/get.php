<?php

$config['current_url']  = rtrim($app->request->getResourceUri(), '/');
$config['absRefPrefix'] = '/';
$config['mainmenu']= array(
  array(
    'title' => $app->ll->str('menu.library'),
    'url' => $config['absRefPrefix'] . 'library'
  ),
  array(
    'title' => $app->ll->str('menu.playlist'),
    'url' => $config['absRefPrefix'] . 'playlist'
  ),
  array(
    'title' => $app->ll->str('menu.playlists'),
    'url' => $config['absRefPrefix'] . 'playlists'
  ),
  array(
    'title' => $app->ll->str('menu.filebrowser'),
    'url' => $config['absRefPrefix'] . 'filebrowser'
  ),
  #array(
  #  'title' => $app->ll->str('menu.genres'),
  #  'url' => $config['absRefPrefix'] . 'library/genres/page/1'
  #),
  #array(
  #  'title' => $app->ll->str('menu.artists'),
  #  'url' => $config['absRefPrefix'] . 'library/artists/page/1'
  #),
  #array(
  #  'title' => $app->ll->str('menu.labels'),
  #  'url' => $config['absRefPrefix'] . 'library/labels/page/1'
  #),
  
  
  #array(
  #  'title' => $app->ll->str('menu.favorites'),
  #  'url' => $config['absRefPrefix'] . 'favorites'
  #),
  array(
    'title' => $app->ll->str('menu.importer'),
    'url' => $config['absRefPrefix'] . 'importer'
  ),
);
$config['ll'] = $app->ll->getAllCommonTranslationItems('general');





$app->get('/', function() use ($app,$config){
	$config['action'] = "landing";
	// TODO: $app->auth->check('library');
    $app->render('surrounding.twig', $config);
});

$app->get('/library(/)', function() use ($app, $config){
	$config['action'] = "landing";
    $app->render('surrounding.twig', $config);
});


foreach(array('artist', 'label', 'genre') as $className) {
	
	// stringlist of artist|label|genre
	$app->get('/library/'.$className.'s/:itemParams+', function($itemParams) use ($app, $config, $className){
		$classPath = "\\Slimpd\\" . ucfirst($className);
		$config['action'] = 'library.'. $className .'s';
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
				default: break;
					
			}
		}

		if($searchterm !== FALSE) {
			$config['itemlist'] = $classPath::getInstancesLikeAttributes(
				array('az09' => str_replace('*', '%', $searchterm)),
				$itemsPerPage,
				$currentPage
			);
			$config['totalresults'] = $classPath::getCountLikeAttributes(
				array('az09' => str_replace('*', '%', $searchterm))
			);
			$urlPattern = '/library/'.$className.'s/searchterm/'.$searchterm.'/page/(:num)';
		} else {
			$config['itemlist'] = $classPath::getAll($itemsPerPage, $currentPage);
			$config['totalresults'] = $classPath::getCountAll();
			$urlPattern = '/library/'.$className.'s/page/(:num)';
		}
		$config['paginator_params'] = new JasonGrimes\Paginator(
			$config['totalresults'],
			$itemsPerPage,
			$currentPage,
			$urlPattern
		);
		#echo "<pre>" . print_r($config['paginator_params'], 1); die();
    	$app->render('surrounding.twig', $config);
	});
		
		
		
	// albumlist+tracklist of artist|label|genre
	$app->get('/library/'.$className.'/:itemParams+', function($itemParams) use ($app, $config, $className){
		
		$itemId = $itemParams[0];
		$currentPage = 1;
		if(isset($itemParams[1]) && $itemParams[1]=='page') {
			if(isset($itemParams[2]) && is_numeric($itemParams[2]) ) {
				$currentPage = $itemParams[2];
			}
		}
		#echo "<pre>" . print_r($itemParams); die();
		$itemsPerPage = 24;
		$classPath = "\\Slimpd\\" . ucfirst($className);
		$config['action'] = 'library.'.$className;
		$config['albumlist'] = \Slimpd\Album::getInstancesByFindInSetAttributes(
			array($className . 'Id' => $itemId),
			$itemsPerPage,
			$currentPage
		);
		$config['item'] = $classPath::getInstanceByAttributes(array('id' => $itemId));
		$config['totalresults'] = \Slimpd\Album::getCountByFindInSetAttributes(
			array($className.'Id' => $itemId)
		);
		$config['paginator_params'] = new JasonGrimes\Paginator(
			$config['totalresults'],
			$itemsPerPage,
			$currentPage,
			'/library/'.$className.'/'.$itemId.'/page/(:num)'
		);
		
		// tracklist
		$config['tracklist'] = \Slimpd\Track::getInstancesByFindInSetAttributes(
			array(
				'artistId' => $itemId,
				'remixerId' => $itemId,
				'featuringId' => $itemId
			),
			$itemsPerPage,
			$currentPage
		);
		#print_r($config['tracklist'] ); die();
		
		$config['renderitems'] = array(
			'genres' => \Slimpd\Genre::getInstancesForRendering($config['albumlist'], $config['item'], $config['tracklist']),
			'labels' => \Slimpd\Label::getInstancesForRendering($config['albumlist'], $config['item'], $config['tracklist']),
			'artists' => \Slimpd\Artist::getInstancesForRendering($config['albumlist'], $config['item'], $config['tracklist'])
		);
		
	    $app->render('surrounding.twig', $config);
	});
		
}

$app->get('/library/album(/)', function() use ($app, $config){
	$config['action'] = 'library.album';
    $app->render('surrounding.twig', $config);
});



$app->get('/library/album/:albumId', function($albumId) use ($app, $config){
	$config['action'] = 'album.detail';
	$config['album'] = \Slimpd\Album::getInstanceByAttributes(array('id' => $albumId));
	$config['itemlist'] = \Slimpd\Track::getInstancesByAttributes(array('albumId' => $albumId));
	
	
	// get all relational items we need for rendering
	$config['renderitems'] = array(
		'genres' => \Slimpd\Genre::getInstancesForRendering($config['album'], $config['itemlist']),
		'labels' => \Slimpd\Label::getInstancesForRendering($config['album'], $config['itemlist']),
		'artists' => \Slimpd\Artist::getInstancesForRendering($config['album'], $config['itemlist'])
	);
		
	$config['albumimages'] = \Slimpd\Bitmap::getInstancesByAttributes(
		array('albumId' => $albumId)
	);
	$app->render('surrounding.twig', $config);
});	


$app->get('/library/year/:itemString', function($itemString) use ($app, $config){
	$config['action'] = 'library.year';
	
	$config['albumlist'] = \Slimpd\Album::getInstancesByAttributes(
		array('year' => $itemString)
	);
	
	// get all relational items we need for rendering
	$config['renderitems'] = array(
		'genres' => \Slimpd\Genre::getInstancesForRendering($config['albumlist']),
		'labels' => \Slimpd\Label::getInstancesForRendering($config['albumlist']),
		'artists' => \Slimpd\Artist::getInstancesForRendering($config['albumlist'])
	);
	
	
    $app->render('surrounding.twig', $config);
});



$app->get('/mpdctrl(/:cmd(/:item))', function($cmd, $item='') use ($app, $config){
	$config['action'] = 'mpdctrl.' . $cmd;
	$mpd = new \Slimpd\modules\mpd\mpd();
	$mpd->cmd($cmd, $item);
	if($cmd !== 'playerStatus') {
		//$app->redirect('/playlist');
	}
});

$app->get('/mpdctrl/:cmd/:item+', function($cmd, $item='') use ($app, $config){
	$config['action'] = 'mpdctrl.' . $cmd;
	$mpd = new \Slimpd\modules\mpd\mpd();
	$mpd->cmd($cmd, $item);
});



$app->get('/playlist(/)', function() use ($app, $config){
	$config['action'] = 'playlist';
	$mpd = new \Slimpd\modules\mpd\mpd();
	$config['nowplaying'] = $mpd->getCurrentlyPlayedTrack();
	if($config['nowplaying'] !== NULL) {
		$config['nowplaying_album'] = \Slimpd\Album::getInstanceByAttributes(
			array('id' => $config['nowplaying']->getAlbumId())
		);
	} else {
		// TODO: how to handle mpd played tracks we cant find in database
		$config['nowplaying_album'] = NULL;
	}
	$config['currentplaylist'] = $mpd->getCurrentPlaylist();
	
	// get all relational items we need for rendering
	$config['renderitems'] = array(
		'genres' => \Slimpd\Genre::getInstancesForRendering($config['nowplaying_album'], $config['currentplaylist']),
		'labels' => \Slimpd\Label::getInstancesForRendering($config['nowplaying_album'], $config['currentplaylist']),
		'artists' => \Slimpd\Artist::getInstancesForRendering($config['nowplaying_album'], $config['currentplaylist']),
		'albums' => \Slimpd\Album::getInstancesForRendering($config['nowplaying_album'], $config['currentplaylist'])
	);
		
    $app->render('surrounding.twig', $config);
});


$app->get('/favorites(/)', function() use ($app, $config){
	$config['action'] = 'favorites';
    $app->render('surrounding.twig', $config);
});



$app->get('/mpdstatus(/)', function() use ($app, $config){
	$mpd = new \Slimpd\modules\mpd\mpd();
	
	# TODO: mpd-version check, v 0.20 has 'duration' included in status()
	# @see: http://www.musicpd.org/doc/protocol/command_reference.html#status_commands
	
	$config['mpd']['status'] = $mpd->cmd('status');
	$config['mpd']['status']['duration'] = $mpd->cmd('currentsong')['Time'];
	$config['mpd']['status']['percent'] = $config['mpd']['status']['elapsed'] / ($config['mpd']['status']['duration']/100); 
	echo json_encode($config['mpd']['status']);
	$app->stop();
});

$app->get('/markup/mpdplayer', function() use ($app, $config){
	$mpd = new \Slimpd\modules\mpd\mpd();
	$config['item'] = $mpd->getCurrentlyPlayedTrack();
	$config['renderitems'] = array(
		'genres' => \Slimpd\Genre::getInstancesForRendering($config['item']),
		'labels' => \Slimpd\Label::getInstancesForRendering($config['item']),
		'artists' => \Slimpd\Artist::getInstancesForRendering($config['item']),
		'albums' => \Slimpd\Album::getInstancesForRendering($config['item'])
	);
	// TODO: remove external liking as soon we have implemented a proper functionality
	$config['temp_likerurl'] = 'http://ixwax/filesystem/plusone?f=' .
	urlencode($config['mpd']['alternative_musicdir'] .
	$config['item']->getRelativePath()); 
	$app->render('modules/mpdplayer.twig', $config);
	$app->stop();
});




// predefined album-image sizes
foreach (array(50,100,300,1000) as $imagesize) {
	$app->get('/image-'.$imagesize.'/album/:itemId', function($itemId) use ($app, $config, $imagesize){
		$image = \Slimpd\Bitmap::getInstanceByAttributes(
			array('albumId' => $itemId), 'filesize DESC'
		);
		$image = ($image === NULL) ? \Slimpd\Bitmap::getFallbackImage() : $image;
		$image->dump($imagesize);
		exit();
	});
	
	$app->get('/image-'.$imagesize.'/track/:itemId', function($itemId) use ($app, $config, $imagesize){
		$image = \Slimpd\Bitmap::getInstanceByAttributes(
			array('trackId' => $itemId), 'filesize DESC'
		);
		if($image === NULL) {
			$track = \Slimpd\Track::getInstanceByAttributes(
				array('id' => $itemId), 'filesize DESC'
			);  
			$app->response->redirect('/image-'.$imagesize.'/album/' . $track->getAlbumId());
			return;
		}
		$image->dump($imagesize);
		exit();
	});
	
	$app->get('/image-'.$imagesize.'/id/:itemId', function($itemId) use ($app, $config, $imagesize){
		$image = \Slimpd\Bitmap::getInstanceByAttributes(
			array('id' => $itemId), 'filesize DESC'
		);
		$image = ($image === NULL) ? \Slimpd\Bitmap::getFallbackImage() : $image;
		$image->dump($imagesize);
	});
	
}

$app->get('/importer(/)', function() use ($app, $config){
	$config['action'] = 'importer';
	$config['servertime'] = time();;
	
	$query = "SELECT * FROM importer ORDER BY jobStart DESC LIMIT 10;";
	$result = $app->db->query($query);
	while($record = $result->fetch_assoc() ) {
		$record['jobStatistics'] = unserialize($record['jobStatistics']);
		$config['itemlist'][] = $record;
	}
	$app->render('surrounding.twig', $config);
});



$app->get('/audiosvg/width/:width/:itemParam', function($width, $itemParam) use ($app, $config){
	$svgGenerator = new \Slimpd\Svggenerator($itemParam);
	$svgGenerator->generateSvg($width);
});



$app->get('/filebrowser', function() use ($app, $config){
	$config['action'] = 'filebrowser';
	$fileBrowser = new \Slimpd\filebrowser();
	$fileBrowser->getDirectoryContent($config['mpd']['musicdir']);
	$config['breadcrumb'] = $fileBrowser->breadcrumb;
	$config['subDirectories'] = $fileBrowser->subDirectories;
	$config['files'] = $fileBrowser->files;
	$config['hotlinks'] = $config['filebrowser-hotlinks'];
	
	$app->render('surrounding.twig', $config);
});



$app->get('/filebrowser/:itemParams+', function($itemParams) use ($app, $config){
	$config['action'] = 'filebrowser';
	$fileBrowser = new \Slimpd\filebrowser();
	$fileBrowser->getDirectoryContent(join(DS, $itemParams));
	$config['directory'] = $fileBrowser->directory;
	$config['breadcrumb'] = $fileBrowser->breadcrumb;
	$config['subDirectories'] = $fileBrowser->subDirectories;
	$config['files'] = $fileBrowser->files; 
	$app->render('surrounding.twig', $config);
});


$app->get('/maintainance/trackdebug/:itemParams+', function($itemParams) use ($app, $config){
	$config['action'] = 'maintainance.trackdebug';
	if(count($itemParams) === 1 && is_numeric($itemParams[0])) {
		$search = array('id' => (int)$itemParams[0]);
	}
	if(count($itemParams)>1) {
		$search = array('relativePathHash' => getFilePathHash(join(DS, $itemParams)));
	}
	$config['item'] = \Slimpd\Track::getInstanceByAttributes($search);
	$config['itemraw'] = \Slimpd\Rawtagdata::getInstanceByAttributes($search);
	$config['renderitems'] = array(
		'genres' => \Slimpd\Genre::getInstancesForRendering($config['item']),
		'labels' => \Slimpd\Label::getInstancesForRendering($config['item']),
		'artists' => \Slimpd\Artist::getInstancesForRendering($config['item']),
		'albums' => \Slimpd\Album::getInstancesForRendering($config['item'])
	);
	$app->render('surrounding.twig', $config);
});



