<?php

foreach(['/album', '/markup/albumtracks', '/markup/widget-album'] as $what) {
	$app->get($what .'/:albumId', function($albumId) use ($app, $vars, $what){
		$vars['action'] = ($what == '/album') ? 'album.detail' : 'albumtracks';
		$vars['album'] = \Slimpd\Models\Album::getInstanceByAttributes(array('id' => $albumId));
		if($vars['album'] === NULL) {
			$app->notFound();
			return;
		}
		$vars['itemlist'] = \Slimpd\Models\Track::getInstancesByAttributes(
			['albumId' => $albumId], FALSE, 200, 1, 'number ASC'
		);
		$vars['renderitems'] = getRenderItems($vars['album'], $vars['itemlist']);
		$vars['albumimages'] = [];
		$vars['bookletimages'] = [];
		$bitmaps = \Slimpd\Models\Bitmap::getInstancesByAttributes(
			['albumId' => $albumId], FALSE, 200, 1, 'imageweight'
		);
		$foundFront = FALSE;
		foreach($bitmaps as $bitmap) {
			switch($bitmap->getPictureType()) {
				case 'front':
					if($foundFront === TRUE && $app->config['images']['hide_front_duplicates'] == '1') {
						continue;
					}
					$vars['albumimages'][] = $bitmap;
					$foundFront = TRUE;
					break;
				case 'booklet':
					$vars['bookletimages'][] = $bitmap;
					break;
				default:
					$vars['albumimages'][] = $bitmap;
					break;
			}
		}
		
		$vars['breadcrumb'] = \Slimpd\filebrowser::fetchBreadcrumb($vars['album']->getRelativePath());
		
		if($what === '/markup/widget-album') {
			$app->render('modules/widget-album.htm', $vars);
			return;
		}
	
		$app->render('surrounding.htm', $vars);
	});
}



$app->get('/maintainance/albumdebug/:itemParams+', function($itemParams) use ($app, $vars){
	$vars['action'] = 'maintainance.albumdebug';
	if(count($itemParams) === 1 && is_numeric($itemParams[0])) {
		$search = array('id' => (int)$itemParams[0]);
	}
	
	$vars['album'] = \Slimpd\Models\Album::getInstanceByAttributes($search);

	$tmp = \Slimpd\Models\Track::getInstancesByAttributes(array('albumId' => $vars['album']->getId()));
	$trackInstances = array();
	$rawTagDataInstances = array();
	foreach($tmp as $t) {
		$vars['itemlist'][$t->getId()] = $t;
		$vars['itemlistraw'][$t->getId()] = \Slimpd\Models\Rawtagdata::getInstanceByAttributes(array('id' => (int)$t->getId()));
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
