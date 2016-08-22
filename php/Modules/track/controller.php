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
		$itemRelPath = '';
		
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
				$mpd = new \Slimpd\Modules\mpd\mpd();
				$vars['item'] = $mpd->getCurrentlyPlayedTrack();
				if($vars['item'] !== NULL) {
					$itemRelPath = $vars['item']->getRelPath();
				}
				break;
			case 'xwaxplayer':
				$xwax = new \Slimpd\Xwax();
				$vars['decknum'] = $app->request->get('deck');
				$vars['item'] = $xwax->getCurrentlyPlayedTrack($vars['decknum']);
				
				if($vars['item'] !== NULL) {
					$itemRelPath = $vars['item']->getRelPath();
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
					$search = array('relPathHash' => getFilePathHash($itemPath));
					$itemRelPath = $itemPath;
				}
				$vars['item'] = \Slimpd\Models\Track::getInstanceByAttributes($search);
				// no break
		}
		
		$itemsToRender[] = $vars['item'];
		$vars['renderitems'] = getRenderItems($itemsToRender);
		
		if(is_null($vars['item']) === FALSE && $vars['item']->getId() > 0) {
			$itemRelPath = $vars['item']->getRelPath();
		} else {
			// playing track has not been imported in slimpd database yet...
			// so we are not able to get any renderitems
			$item = new \Slimpd\Models\Track();
			$item->setRelPath($itemRelPath);
			$item->setRelPathHash(getFilePathHash($itemRelPath));
			$vars['item'] = $item;
		}
		
		// TODO: remove external liking as soon we have implemented a proper functionality
		$vars['temp_likerurl'] = 'http://ixwax/filesystem/plusone?f=' .
			urlencode($vars['mpd']['alternative_musicdir'] . $itemRelPath);
		
		$app->render($templateFile, $vars);
		$app->stop();
	});
	
	$app->get('/css/'.$markupSnippet . '/:relPathHash', function($relPathHash) use ($app, $vars, $markupSnippet){
		$vars['relPathHash'] = $relPathHash;
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
	$itemRelPath = '';
	$itemRelPathHash = '';
	$vars['item'] = (count($itemParams) === 1 && is_numeric($itemParams[0]))
		? \Slimpd\Models\Track::getInstanceByAttributes(['id' => (int)$itemParams[0]])
		: \Slimpd\Models\Track::getInstanceByPath(join(DS, $itemParams), TRUE);

	$vars['itemraw'] = \Slimpd\Models\Rawtagdata::getInstanceByAttributes(
		['relPathHash' => $vars['item']->getRelPathHash()]
	);
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
