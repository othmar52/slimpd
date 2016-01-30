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
    'url' => $config['absRefPrefix'] . 'playlist/page/current'
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

$config['playerMode'] = ($app->getCookie('playerMode') === 'mpd') ? 'mpd' : 'local';

$config['nosurrounding'] = ($app->request->get('nosurrounding') == 1) ? TRUE : FALSE;

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
		$config['renderitems'] = getRenderItems($config['albumlist'], $config['item'], $config['tracklist']);
		
	    $app->render('surrounding.twig', $config);
	});
		
}

$app->get('/library/album(/)', function() use ($app, $config){
	$config['action'] = 'library.album';
    $app->render('surrounding.twig', $config);
});


foreach(['/library/album', '/markup/albumtracks'] as $what) {
	$app->get($what .'/:albumId', function($albumId) use ($app, $config, $what){
		$config['action'] = ($what == '/library/album') ? 'album.detail' : 'albumtracks';
		$config['album'] = \Slimpd\Album::getInstanceByAttributes(array('id' => $albumId));
		$config['itemlist'] = \Slimpd\Track::getInstancesByAttributes(array('albumId' => $albumId));
		
		
		// get all relational items we need for rendering
		$config['renderitems'] = getRenderItems($config['album'], $config['itemlist']);
		$config['totalitems'] = \Slimpd\Album::getCountAll();
		$config['albumimages'] = \Slimpd\Bitmap::getInstancesByAttributes(
			array('albumId' => $albumId)
		);
		$app->render('surrounding.twig', $config);
	});	
}

$app->get('/library/year/:itemString', function($itemString) use ($app, $config){
	$config['action'] = 'library.year';
	
	$config['albumlist'] = \Slimpd\Album::getInstancesByAttributes(
		array('year' => $itemString)
	);
	
	// get all relational items we need for rendering
	$config['renderitems'] = getRenderItems($config['albumlist']);
    $app->render('surrounding.twig', $config);
});



$app->get('/mpdctrl(/:cmd(/:item))', function($cmd, $item='') use ($app, $config){
	$config['action'] = 'mpdctrl.' . $cmd;
	$mpd = new \Slimpd\modules\mpd\mpd();
	$mpd->cmd($cmd, $item);
	if($cmd !== 'playerStatus') {
		//$app->redirect('/playlist/page/current');
	}
});

$app->get('/mpdctrl/:cmd/:item+', function($cmd, $item='') use ($app, $config){
	$config['action'] = 'mpdctrl.' . $cmd;
	$mpd = new \Slimpd\modules\mpd\mpd();
	$mpd->cmd($cmd, $item);
});



$app->get('/playlist/page/:pagenum', function($pagenum) use ($app, $config){
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
	$config['currentplaylist'] = $mpd->getCurrentPlaylist($pagenum);
	$config['currentplaylistlength'] = $mpd->getCurrentPlaylistLength();
	
	
	// get all relational items we need for rendering
	$config['renderitems'] = getRenderItems($config['nowplaying_album'], $config['currentplaylist']);
	
	
	$config['paginator_params'] = new JasonGrimes\Paginator(
		$config['currentplaylistlength'],
		$app->config['mpd-playlist']['max-items'],
		(($pagenum === 'current') ? $mpd->getCurrentPlaylistCurrentPage() : $pagenum),
		'/playlist/page/(:num)'
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
	try {
		$config['mpd']['status']['duration'] = $mpd->cmd('currentsong')['Time'];
		$percent = $config['mpd']['status']['elapsed'] / ($config['mpd']['status']['duration']/100);
		$config['mpd']['status']['percent'] = ($percent >=0 && $percent <= 100) ? $percent : 0;
	} catch (\Exception $e) {
		// TODO: display smth like "no track loaded"
		$config['mpd']['status'] = array();
	}
	echo json_encode($config['mpd']['status']);
	$app->stop();
});

foreach(['mpdplayer', 'localplayer', 'widget-trackcontrol', 'widget-xwax'] as $markupSnippet ) {

	$app->get('/markup/'.$markupSnippet, function() use ($app, $config, $markupSnippet){
		
		// maybe we cant find item in mpd or mysql database because it has ben accessed via filebrowser
		$itemRelativePath = '';
		
		if($markupSnippet === 'mpdplayer') {
			$mpd = new \Slimpd\modules\mpd\mpd();
			$config['item'] = $mpd->getCurrentlyPlayedTrack();
			$itemRelativePath = $config['item']->getRelativePath();
		} else {
			if(is_numeric($app->request->get('item')) === TRUE) {
				$search = array('id' => (int)$app->request->get('item'));
			} else {
				$search = array('relativePathHash' => getFilePathHash($app->request->get('item')));
				$itemRelativePath = $app->request->get('item');
			}
			$config['item'] = \Slimpd\Track::getInstanceByAttributes($search);
		}
		
		
		if(is_null($config['item']) === FALSE && $config['item']->getId() > 0) {
			$config['renderitems'] = getRenderItems($config['item']);
			$itemRelativePath = $config['item']->getRelativePath();
		} else {
			// playing track has not been imported in slimpd database yet...
			// so we are not able to get any renderitems
			$item = new \Slimpd\Track();
			$item->setRelativePath($itemRelativePath);
			$item->setRelativePathHash(getFilePathHash($itemRelativePath));
			$config['item'] = $item;
		}
		
		// TODO: remove external liking as soon we have implemented a proper functionality
		$config['temp_likerurl'] = 'http://ixwax/filesystem/plusone?f=' .
			urlencode($config['mpd']['alternative_musicdir'] . $itemRelativePath);
		
		$app->render('modules/'.$markupSnippet.'.twig', $config);
		$app->stop();
	});
	
	$app->get('/css/'.$markupSnippet . '/:relativePathHash', function($relativePathHash) use ($app, $config, $markupSnippet){
		$config['relativePathHash'] = $relativePathHash;
		$app->response->headers->set('Content-Type', 'text/css');
		$app->render('css/'.$markupSnippet.'.twig', $config);
	});
}



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
	$config['renderitems'] = getRenderItems($config['item']);
	$config['totalitems'] = \Slimpd\Track::getCountAll();
	$app->render('surrounding.twig', $config);
});


$app->get('/maintainance/albumdebug/:itemParams+', function($itemParams) use ($app, $config){
	$config['action'] = 'maintainance.albumdebug';
	if(count($itemParams) === 1 && is_numeric($itemParams[0])) {
		$search = array('id' => (int)$itemParams[0]);
	}
	
	$config['album'] = \Slimpd\Album::getInstanceByAttributes($search);
	
	
	$tmp = \Slimpd\Track::getInstancesByAttributes(array('albumId' => $config['album']->getId()));
	$trackInstances = array();
	$rawTagDataInstances = array();
	foreach($tmp as $t) {
		$config['itemlist'][$t->getId()] = $t;
		$config['itemlistraw'][$t->getId()] = \Slimpd\Rawtagdata::getInstanceByAttributes(array('id' => (int)$t->getId()));
	}
	#echo "<pre>" . print_r(array_keys($trackInstances),1) . "</pre>";
	unset($tmp);
	
	
	$config['discogstracks'] = array();
	$config['matchmapping'] = array();
	
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
		$config['matchmapping'] = $discogsItem->guessTrackMatch($config['itemlistraw']);
		$config['discogstracks'] = $discogsItem->trackstrings;
		$config['discogsalbum'] = $discogsItem->albumAttributes;
		
		
	}
	
	$config['renderitems'] = getRenderItems($config['itemlist'], $config['album']);
	$config['totalitems'] = \Slimpd\Album::getCountAll();
	$app->render('surrounding.twig', $config);
});


// TODO: carefully check which sorting ist possible for each model (@see config/sphinx.example.conf:srcslimpdmain)
$sortfields = array(
	'all' => array('title', 'artist', 'year', 'added'),
	'track' => array('title', 'artist', 'year', 'added'),
	'album' => array('year', 'title', 'added', 'artist', 'trackCount'),
	'artist' => array('title', 'trackCount', 'albumCount'),
	'genre' => array('title', 'trackCount', 'albumCount'),
	'label' => array('title', 'trackCount', 'albumCount'),
);

foreach(array_keys($sortfields) as $currentType) {
	$app->get(
		'/search'.$currentType.'/page/:currentPage/sort/:sort/:direction',
		function($currentPage, $sort, $direction) use ($app, $config, $currentType, $sortfields){
		foreach(['freq_threshold', 'suggest_dubug', 'length_threshold', 'levenshtein_threshold', 'top_count'] as $var) {
			define (strtoupper($var), intval($app->config['sphinx'][$var]) );
		}
		
		# TODO: evaluate if modifying searchterm makes sense
		// "Artist_-_Album_Name-(CAT001)-WEB-2015" does not match without this modification
		$term = str_replace(array("_", "-", "/"), " ", $app->request()->params('q'));
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
		);
		$config['itemlist'] = [];
		foreach(array_keys($sortfields) as $type) {
			$stmt = $ln_sph->prepare("
				SELECT itemid,type FROM ". $app->config['sphinx']['mainindex']."
				WHERE MATCH(:match)
				" . (($type !== 'all') ? ' AND type=:type ' : '') . "
				GROUP BY itemid,type
				LIMIT ".$maxCount."
				OPTION ranker=".$ranker.", max_matches=".$maxCount.";");
			$stmt->bindValue(':match', $term, PDO::PARAM_STR);
			if(($type !== 'all')) {
				$stmt->bindValue(':type', $filterTypeMapping[$type], PDO::PARAM_INT);
			}
			
			$stmt->execute();
			
			$config['search'][$type]['total'] = $stmt->rowCount();
			$config['search'][$type]['time'] = 0;
			$config['search'][$type]['term'] = $term;
			$config['search'][$type]['matches'] = [];
			
			if($type == $currentType) {
				$sortfield = (in_array($sort, $sortfields[$currentType]) === TRUE) ? $sort : 'relevance';
				$direction = ($direction == 'asc') ? 'asc' : 'desc';
				$config['search']['activesorting'] = $sortfield . '-' . $direction;
				
				$sortQuery = ($sortfield !== 'relevance')?  ' ORDER BY ' . $sortfield . ' ' . $direction : '';
				
				$config['search'][$type]['time'] = microtime(TRUE);
				
				$stmt = $ln_sph->prepare("
					SELECT id,type,itemid,display FROM ". $app->config['sphinx']['mainindex']."
					WHERE MATCH(:match)
					" . (($currentType !== 'all') ? ' AND type=:type ' : '') . "
					GROUP BY itemid,type
					".$sortQuery."
					LIMIT :offset,:max
					OPTION ranker=".$ranker.";");
				$stmt->bindValue(':match', $term, PDO::PARAM_STR);
				$stmt->bindValue(':offset', ($currentPage-1)*$itemsPerPage , PDO::PARAM_INT);
				$stmt->bindValue(':max', $itemsPerPage, PDO::PARAM_INT);
				if(($currentType !== 'all')) {
					$stmt->bindValue(':type', $filterTypeMapping[$currentType], PDO::PARAM_INT);
				}
				
				$urlPattern = '/search'.$type.'/page/(:num)/sort/'.$sortfield.'/'.$direction.'?q=' . $term;
				$config['paginator_params'] = new JasonGrimes\Paginator(
					$config['search'][$type]['total'],
					$itemsPerPage,
					$currentPage,
					$urlPattern
				);
				
				$stmt->execute();
				$rows = $stmt->fetchAll();
				#echo "<pre>" . print_r($stmt,1). print_r($rows,1); die();
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
							'search'.$currentType, [
								'type' => $currentType,
								'currentPage' => $currentPage,
								'sort' => $sort,
								'direction' => $direction
							])
							.'?nosuggestion=1&q='.$suggest . ($app->request()->params('nosurrounding') ? '&nosurrounding=1' : ''));
						$app->stop();
					}
					$result[] = [
						'label' => 'nothing found',
						'url' => '#',
						'type' => '',
						'img' => '/skin/default/img/icon-label.png' // TODO: add not-found-icon
					];
				} else {
					$config['search'][$type]['time'] = number_format(microtime(TRUE) - $config['search'][$type]['time'],3);
					$filterTypeMappingF = array_flip($filterTypeMapping);
					foreach($rows as $row) {
						switch($filterTypeMappingF[$row['type']]) {
							case 'artist':
								$obj = \Slimpd\Artist::getInstanceByAttributes(array('id' => $row['itemid']));
								break;
							case 'label':
								$obj = \Slimpd\Label::getInstanceByAttributes(array('id' => $row['itemid']));
								break;
							case 'album':
								$obj = \Slimpd\Album::getInstanceByAttributes(array('id' => $row['itemid']));
								break; 
							case 'track':
								$obj = \Slimpd\Track::getInstanceByAttributes(array('id' => $row['itemid']));
								break;
							case 'genre':
								$obj = \Slimpd\Genre::getInstanceByAttributes(array('id' => $row['itemid']));
								break;
						}
						$config['itemlist'][] = $obj;
					}
				}
			}
		}
		$config['action'] = 'searchresult.' . $currentType;
		$config['renderitems'] = getRenderItems($config['itemlist']);
		$app->render('surrounding.twig', $config);
			
	})->name('search'.$currentType);
}


$app->get('/autocomplete/:type/:term', function($type, $term) use ($app, $config) {
	foreach(['freq_threshold', 'suggest_dubug', 'length_threshold', 'levenshtein_threshold', 'top_count'] as $var) {
		define (strtoupper($var), intval($app->config['sphinx'][$var]) );
	}
	
	# TODO: evaluate if modifying searchterm makes sense
	// "Artist_-_Album_Name-(CAT001)-WEB-2015" does not match without this modification
	$term = str_replace(array("_", "-", "/"), " ", $term);
	
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
	);
	
	$stmt = $ln_sph->prepare("
		SELECT id,type,itemid,display FROM ". $app->config['sphinx']['mainindex']."
		WHERE MATCH(:match)
		" . (($type !== 'all') ? ' AND type=:type ' : '') . "
		GROUP BY itemid,type
		LIMIT $start,$offset
		OPTION ranker=sph04");
	$stmt->bindValue(':match', $term, PDO::PARAM_STR);
	if(($type !== 'all')) {
		$stmt->bindValue(':type', $filterTypeMapping[$type], PDO::PARAM_INT);
	}
	$stmt->execute();
	$rows = $stmt->fetchAll();
	#echo "<pre>" . print_r($stmt,1) . print_r($rows,1); die();
	$meta = $ln_sph->query("SHOW META")->fetchAll();
	foreach($meta as $m) {
	    $meta_map[$m['Variable_name']] = $m['Value'];
	}
	
	
	if(count($rows) === 0) {
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
			$app->response->redirect($app->urlFor('autocomplete', array('type' => $type, 'term' => $suggest)));
			$app->stop();
		}
		$result[] = [
			'label' => 'nothing found',
			'url' => '#',
			'type' => '',
			'img' => '/skin/default/img/icon-label.png' // TODO: add not-found-icon
		];
	} else {
		$filterTypeMapping = array_flip($filterTypeMapping);
		$cl = new SphinxClient();
		foreach($rows as $row) {
			$excerped = $cl->BuildExcerpts([$row['display']], $app->config['sphinx']['mainindex'], $term);
			$filterType = $filterTypeMapping[$row['type']];
			$entry = [
				'label' => $excerped[0],
				'url' => (($filterType === 'track')
					? '/searchall/page/1/sort/relevance/desc?q=' . $row['display']
					: '/library/' . $filterType . '/' . $row['itemid']),
				'type' => $filterType,
				'typelabel' => $app->ll->str($filterType),
				'itemid' => $row['itemid']
			];
			switch($filterType) {
				case 'artist':
				case 'label':
				case 'genre':
					$entry['img'] = '/skin/default/img/icon-'. $filterType .'.png';
					break;
				case 'album':
				case 'track':
					$entry['img'] = '/image-50/'. $filterType .'/' . $row['itemid'];
					break;
			}
			$result[] = $entry;
		}
	}
	#echo "<pre>" . print_r($result,1); die();
	#echo "<pre>" . print_r($rows,1); die();
	echo json_encode($result); exit;
})->name('autocomplete');



$app->get('/deliver/:item+', function($item) use ($app, $config){
	$path = join(DS, $item);
	if(is_numeric($path)) {
		$track = \Slimpd\Track::getInstanceByAttributes(array('id' => (int)$path));
		$path = ($track === NULL) ? '' : $track->getRelativePath();
	}
	
	deliver($app->config['mpd']['alternative_musicdir'] . $path, $app);
	$app->stop();
});

$app->get('/xwax/:cmd/:params+', function($cmd, $params) use ($app, $config){
	
	$xwax = new \Slimpd\Xwax();
	$xwax->cmd($cmd, $params, $app);
	$app->stop();
});
