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
foreach(['/album', '/markup/albumtracks', '/markup/widget-album'] as $what) {
	$app->get($what .'/:albumUid', function($albumUid) use ($app, $vars, $what){
		$vars['action'] = ($what == '/album') ? 'album.detail' : 'albumtracks';
		$vars['album'] = \Slimpd\Models\Album::getInstanceByAttributes(array('uid' => $albumUid));
		if($vars['album'] === NULL) {
			$app->notFound();
			return;
		}
		$vars['itemlist'] = \Slimpd\Models\Track::getInstancesByAttributes(
			['albumUid' => $albumUid], FALSE, 200, 1, 'trackNumber ASC'
		);
		$vars['renderitems'] = getRenderItems($vars['album'], $vars['itemlist']);
		$vars['albumimages'] = [];
		$vars['bookletimages'] = [];
		$bitmaps = \Slimpd\Models\Bitmap::getInstancesByAttributes(
			['albumUid' => $albumUid], FALSE, 200, 1, 'imageweight'
		);
		$foundFront = FALSE;
		foreach($bitmaps as $bitmap) {
			switch($bitmap->getPictureType()) {
				case 'front':
					if($foundFront === TRUE && $app->config['images']['hide_front_duplicates'] === '1') {
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
		
		$vars['breadcrumb'] = \Slimpd\filebrowser::fetchBreadcrumb($vars['album']->getRelPath());
		
		if($what === '/markup/widget-album') {
			$app->render('modules/widget-album.htm', $vars);
			return;
		}
	
		$app->render('surrounding.htm', $vars);
	});
}

// stringlist of artist|label|genre
$app->get("/albums/page/:currentPage/sort/:sort/:direction", function($currentPage, $sort, $direction) use ($app, $vars) {
	$vars["action"] = "albums";
	$vars["itemlist"] = [];
	$itemsPerPage = 18;

	$vars['itemlist'] = \Slimpd\Models\Album::getAll($itemsPerPage, $currentPage, $sort . " " . $direction);
	$vars["totalresults"] = \Slimpd\Models\Album::getCountAll();
	$vars["activesorting"] = $sort . "-" . $direction;

	$vars["paginator"] = new JasonGrimes\Paginator(
		$vars["totalresults"],
		$itemsPerPage,
		$currentPage,
		$app->config["root"] ."albums/page/(:num)/sort/".$sort."/".$direction
	);
	$vars["paginator"]->setMaxPagesToShow(paginatorPages($currentPage));
	$vars['renderitems'] = getRenderItems($vars['itemlist']);
	$app->render('surrounding.htm', $vars);
});

$app->get('/maintainance/albumdebug/:itemParams+', function($itemParams) use ($app, $vars){
	$vars['action'] = 'maintainance.albumdebug';
	$search = array();
	$vars['album'] = \Slimpd\Models\Album::getInstanceByAttributes(
		['uid' => (int)$itemParams[0] ]
	);
	// invalid album id
	if($vars['album'] === NULL) {
		$app->notFound();
	}

	$tmp = \Slimpd\Models\Track::getInstancesByAttributes(array('albumUid' => $vars['album']->getUid()));
	$trackInstances = array();
	$rawTagDataInstances = array();
	foreach($tmp as $t) {
		$vars['itemlist'][$t->getUid()] = $t;
		$vars['itemlistraw'][$t->getUid()] = \Slimpd\Models\Rawtagdata::getInstanceByAttributes(array('uid' => (int)$t->getUid()));
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
		
		$discogsItem = new \Slimpd\Models\Discogsitem($discogsId);
		$vars['matchmapping'] = $discogsItem->guessTrackMatch($vars['itemlistraw']);
		$vars['discogstracks'] = $discogsItem->trackstrings;
		$vars['discogsalbum'] = $discogsItem->albumAttributes;
	}
	
	$vars['renderitems'] = getRenderItems($vars['itemlist'], $vars['album']);
	$app->render('surrounding.htm', $vars);
});
