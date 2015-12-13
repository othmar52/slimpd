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
		
		$searchInAttributes = array(
			'artistId' => $itemId,
			'remixerId' => $itemId,
			'featuringId' => $itemId
		);
	
		if($className == 'label') { $searchInAttributes = array('labelId' => $itemId); }
		if($className == 'genre') { $searchInAttributes = array('genreId' => $itemId); }
		
		// tracklist
		$config['tracklist'] = \Slimpd\Track::getInstancesByFindInSetAttributes(
			$searchInAttributes,
			$itemsPerPage,
			$currentPage
		);
		#print_r($config['tracklist'] ); die();
		
		$config['renderitems'] = array(
			'genres' => \Slimpd\Genre::getInstancesForRendering($config['albumlist'], $config['item'], $config['tracklist']),
			'labels' => \Slimpd\Label::getInstancesForRendering($config['albumlist'], $config['item'], $config['tracklist']),
			'artists' => \Slimpd\Artist::getInstancesForRendering($config['albumlist'], $config['item'], $config['tracklist']),
			'albums' => \Slimpd\Album::getInstancesForRendering($config['item'], $config['tracklist'])
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
	$config['totalitems'] = \Slimpd\Album::getCountAll();
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
	
	
	if(is_null($config['item']) === FALSE && $config['item']->getId() > 0) {
		$config['renderitems'] = array(
			'genres' => \Slimpd\Genre::getInstancesForRendering($config['item']),
			'labels' => \Slimpd\Label::getInstancesForRendering($config['item']),
			'artists' => \Slimpd\Artist::getInstancesForRendering($config['item']),
			'albums' => \Slimpd\Album::getInstancesForRendering($config['item'])
		);
	} else {
		// playing track has not been imported in slimpd database yet...
		// so we are not able to get any renderitems
	}
	
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
	
	// missing track or album paramter caused by items that are not imported in slimpd yet
	# TODO: maybe use another fallback image for those items...
	$app->get('/image-'.$imagesize.'/album/', function() use ($app, $config, $imagesize){
		$image = \Slimpd\Bitmap::getFallbackImage();
		$image->dump($imagesize);
	});
	$app->get('/image-'.$imagesize.'/track/', function() use ($app, $config, $imagesize){
		$image = \Slimpd\Bitmap::getFallbackImage();
		$image->dump($imagesize);
	});
	
}

$app->get('/importer(/)', function() use ($app, $config){
	$config['action'] = 'importer';
	$config['servertime'] = time();;
	
	$query = "SELECT * FROM importer ORDER BY jobStart DESC,id DESC LIMIT 30;";
	$result = $app->db->query($query);
	while($record = $result->fetch_assoc() ) {
		$record['jobStatistics'] = unserialize($record['jobStatistics']);
		$config['itemlist'][] = $record;
	}
	$app->render('surrounding.twig', $config);
});

$app->get('/importer/triggerUpdate', function() use ($app, $config){
	\Slimpd\importer::queStandardUpdate();
});


$app->get('/audiosvg/width/:width/:itemParam+', function($width, $itemParam) use ($app, $config){
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
	} else {
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
	$config['totalitems'] = \Slimpd\Track::getCountAll();
	$app->render('surrounding.twig', $config);
});


$app->get('/maintainance/albumdebug/:itemParams+', function($itemParams) use ($app, $config){
	$config['action'] = 'maintainance.albumdebug';
	if(count($itemParams) === 1 && is_numeric($itemParams[0])) {
		$search = array('id' => (int)$itemParams[0]);
	}
	
	$config['album'] = \Slimpd\Album::getInstanceByAttributes($search);
	
	$trackInstances =  $config['itemlist'] = \Slimpd\Track::getInstancesByAttributes(array('albumId' => $config['album']->getId()));
	
	foreach($trackInstances as $t) {
		$config['itemlist'][$t->getId()] = $t;
		$config['itemlistraw'][$t->getId()] = \Slimpd\Rawtagdata::getInstanceByAttributes(array('id' =>$t->getId()));
	} 
	 
	
	#echo "<pre>" . print_r($config['album'],1); die();
	$config['renderitems'] = array(
		'genres' => \Slimpd\Genre::getInstancesForRendering($config['itemlist'], $config['album']),
		'labels' => \Slimpd\Label::getInstancesForRendering($config['itemlist'], $config['album']),
		'artists' => \Slimpd\Artist::getInstancesForRendering($config['itemlist'], $config['album']),
		'albums' => \Slimpd\Album::getInstancesForRendering($config['itemlist'], $config['album'])
	);
	$config['totalitems'] = \Slimpd\Album::getCountAll();
	$app->render('surrounding.twig', $config);
});


$sortfields = array(
	'all' => array('title', 'artist', 'year', 'added'),
	'track' => array('title', 'artist', 'year', 'added'),
	'album' => array('year', 'title', 'added', 'artist', 'trackCount'),
	'artist' => array('title', 'trackCount', 'albumCount'),
	'genre' => array('title', 'trackCount', 'albumCount'),
	'label' => array('title', 'trackCount', 'albumCount'),
);


foreach(array_keys($sortfields) as $currenttype) {	
	// very basic partly functional search...
	$app->get(
		'/search'. $currenttype .'/page/:num/sort/:sort/:direction',
		function($num, $sort, $direction)
		use ($app, $config, $currenttype, $sortfields
	) {
		
		
		$searchterm = $app->request()->params('q');
		
		
		
		$cl = new \SphinxClient();
		$cl->SetServer(
			$app->config['sphinx']['host'], 
			(int)$app->config['sphinx']['port']
		);
		
		$sortfield = (in_array($sort, $sortfields[$currenttype]) === TRUE) ? $sort : 'relevance';
		
		#echo $sortfield; die(); exit;
		$matches = array();
		foreach(array_keys($sortfields) as $type) {
			$itemsPerPage = 1;
			$currentPage = 1;
			
			if($sortfield !== 'relevance' && $type == $currenttype) {
				$cl->SetSortMode((($direction == 'asc') ? SPH_SORT_ATTR_ASC : SPH_SORT_ATTR_DESC), $sortfield);
			} else {
				$cl->SetSortMode(SPH_SORT_RELEVANCE);
			}
			switch($type) {
				case 'all' :
					$indexname = $app->config['sphinx']['trackindex'];
					$query = '+' . $searchterm;
					break;
				case 'track' :
					$indexname = $app->config['sphinx']['trackindex'];
					$query = '@title +' . $searchterm;
					break;
				case 'album' :
					$indexname = $app->config['sphinx']['albumindex'];
					$query = '+' . $searchterm;
					
					
					break;
				case 'artist' :
					$indexname = $app->config['sphinx']['artistindex'];
					$query = '+' . $searchterm;
					break;
				case 'genre' :
					$indexname = $app->config['sphinx']['genreindex'];
					$query = '+' . $searchterm;
					break;
				case 'label' :
					$indexname = $app->config['sphinx']['labelindex'];
					$query = '+' . $searchterm;
					break;
			}
			if($type == $currenttype) {
				$itemsPerPage = 50;
				$currentPage = $num;
			}
		
			
		
			# SPHINX MATCH MODES
			#$cl->SetMatchMode( SPH_MATCH_ALL );       //	Match all query words (default mode).
			$cl->SetMatchMode( SPH_MATCH_ANY );       //	Match any of query words.
			#$cl->SetMatchMode( SPH_MATCH_PHRASE );    //	Match query as a phrase, requiring perfect match.
			#$cl->SetMatchMode( SPH_MATCH_BOOLEAN );   //	Match query as a boolean expression.
			$cl->SetMatchMode( SPH_MATCH_EXTENDED );  //	Match query as an expression in Sphinx internal query language.
			#$cl->SetMatchMode( SPH_MATCH_FULLSCAN );  //	Enables fullscan.
			#$cl->SetMatchMode( SPH_MATCH_EXTENDED2 ); //	The same as SPH_MATCH_EXTENDED plus ranking and quorum searching support.
			
			$cl->SetLimits(
				($currentPage-1)*$itemsPerPage,
				$itemsPerPage,
				1000000
			);
			
				
			$result = $cl->Query( $query, $indexname);
			#echo "<pre>" . print_r($result,1); #die();
			$config['search'][$type]['total'] = $result['total'];
			$config['search'][$type]['time'] = $result['time'];
			$config['search'][$type]['term'] = $searchterm;
			
			if($type == $currenttype) {
				$itemsPerPage = 50;
				$currentPage = $num;

				$urlPattern = '/search'.$type.'/page/(:num)/sort/'.$sortfield.'/'.$direction.'?q=' . $searchterm;
				$config['paginator_params'] = new JasonGrimes\Paginator(
					$config['search'][$type]['total'],
					$itemsPerPage,
					$currentPage,
					$urlPattern
				);
				
				if(isset($result['matches']) === FALSE) {
					$result['matches'] = array();
				}
				switch($currenttype) {
					case 'all':
					case 'track':
						
						$config['tracklist'] = array();
						foreach($result['matches'] as $id => $foo) {
							$config['tracklist'][] = \Slimpd\Track::getInstanceByAttributes(array('id' => $id));
						}
						// get all relational items we need for rendering
						$config['renderitems'] = array(
							'genres' => \Slimpd\Genre::getInstancesForRendering($config['tracklist']),
							'labels' => \Slimpd\Label::getInstancesForRendering($config['tracklist']),
							'artists' => \Slimpd\Artist::getInstancesForRendering($config['tracklist']),
							'albums' => \Slimpd\Album::getInstancesForRendering($config['tracklist'])
						);
						break;
					case 'album':
						$config['itemlist'] = array();
						foreach($result['matches'] as $id => $foo) {
							$config['itemlist'][] = \Slimpd\Album::getInstanceByAttributes(array('id' => $id));
						}
						$config['renderitems'] = array(
							'genres' => \Slimpd\Genre::getInstancesForRendering($config['itemlist']),
							'labels' => \Slimpd\Label::getInstancesForRendering($config['itemlist']),
							'artists' => \Slimpd\Artist::getInstancesForRendering($config['itemlist']),
							'albums' => \Slimpd\Album::getInstancesForRendering($config['itemlist'])
						);
						break;
						
					case 'artist':
						$config['itemlist'] = array();
						foreach($result['matches'] as $id => $foo) {
							$config['itemlist'][] = \Slimpd\Artist::getInstanceByAttributes(array('id' => $id));
						}
						break;
						
					case 'genre':
						$config['itemlist'] = array();
						foreach($result['matches'] as $id => $foo) {
							$config['itemlist'][] = \Slimpd\Genre::getInstanceByAttributes(array('id' => $id));
						}
						break;
						
					case 'label':
						$config['itemlist'] = array();
						foreach($result['matches'] as $id => $foo) {
							$config['itemlist'][] = \Slimpd\Label::getInstanceByAttributes(array('id' => $id));
						}
						break;
				}
			}
			
			
			
		}
		
		#echo "<pre>" . print_r($result,1); die();
		
		$config['action'] = 'searchresult.' . $currenttype;
		#if(isset($result['matches']) === FALSE) {
		#	$app->render('surrounding.twig', $config);
		#	return;
		#}
		
		
		

		
		
		
		
	
		$app->render('surrounding.twig', $config);
	});
}

