<?php


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


$app->get('/maintainance/trackid3/:itemParams+', function($itemParams) use ($app, $vars){
	$vars['action'] = 'trackid3';
	
	$getID3 = new \getID3;
	$tagData = $getID3->analyze($app->config['mpd']['musicdir'] . join(DS, $itemParams));
	\getid3_lib::CopyTagsToComments($tagData);
	\getid3_lib::ksort_recursive($tagData);

	$vars['dumpvar'] = $tagData;
	$vars['getid3version'] = $getID3->version();
	$app->render('appless.htm', $vars);
});
