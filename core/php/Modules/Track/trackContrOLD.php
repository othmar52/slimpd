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
foreach([
		'xwaxplayer',
		'widget-xwax',
		'widget-deckselector',
		'standalone-trackview'
	] as $markupSnippet ) {

	$app->get('/markup/'.$markupSnippet, function() use ($app, $vars, $markupSnippet){
		
		// maybe we cant find item in mpd or mysql database because it has been accessed via filebrowser
		$itemRelPath = '';
		
		$templateFile = 'modules/'.$markupSnippet.'.htm';
		$vars['action'] = $markupSnippet;

		$itemsToRender = array();
		
		switch($markupSnippet) {

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
					$search = array('uid' => (int)$app->request->get('item'));
					$vars['item'] = \Slimpd\Models\Track::getInstanceByAttributes($search);
					break;
				}
				$itemPath = trimAltMusicDirPrefix($app->request->get('item'));
				$search = array('relPathHash' => getFilePathHash($itemPath));
				$itemRelPath = $itemPath;
				$vars['item'] = \Slimpd\Models\Track::getInstanceByAttributes($search);
				// no break
		}
		
		$itemsToRender[] = $vars['item'];
		$vars['renderitems'] = getRenderItems($itemsToRender);
		
		if(is_null($vars['item']) === FALSE && $vars['item']->getUid() > 0) {
			$itemRelPath = $vars['item']->getRelPath();
		} else {
			// playing track has not been imported in slimpd database yet...
			// so we are not able to get any renderitems
			$vars['item'] = \Slimpd\Models\Track::getNewInstanceWithoutDbQueries($itemRelPath);
		}
		
		// TODO: remove external liking as soon we have implemented a proper functionality
		$vars['temp_likerurl'] = 'http://ixwax/filesystem/plusone?f=' .
			urlencode($vars['mpd']['alternative_musicdir'] . $itemRelPath);
		
		$app->render($templateFile, $vars);
		$app->stop();
	});
	
}

