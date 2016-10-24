<?php
namespace Slimpd\Modules\Mpd;
/* Copyright (C) 2015-2016 othmar52 <othmar52@users.noreply.github.com>
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
 * FITNESS FOR A PARTICULAR PURPOSE.	See the GNU Affero General Public License
 * for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.	If not, see <http://www.gnu.org/licenses/>.
 */
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class Controller extends \Slimpd\BaseController {
	public function cmdAction(Request $request, Response $response, $args) {
		$args['action'] = 'mpdctrl.' . $args['cmd'];
		if(isset($args['item']) === FALSE) {
			$args['item'] = '';
		}
		$this->mpd->cmd($args['cmd'], $args['item']);
		if($this->mpd->notifyJson !== NULL) {
			$newResponse = $response;
			return $newResponse->withJson($this->mpd->notifyJson);
		}
		return $response;
	}

	public function playlistAction(Request $request, Response $response, $args) {
		$args['action'] = 'playlist';
		$args['item'] = $this->mpd->getCurrentlyPlayedTrack();
		$args['nowplaying_album'] = NULL;
		if($args['item'] !== NULL) {
			$args['nowplaying_album'] = $this->albumRepo->getInstanceByAttributes(
				array('uid' => $args['item']->getAlbumUid())
			);
		}
		
		switch($args['pagenum']) {
			case 'current':
				$currentPage = $this->mpd->getCurrentPlaylistCurrentPage();
				break;
			case 'last':
				$currentPage = $this->mpd->getCurrentPlaylistTotalPages();
				break;
			default:
				$currentPage = (int)$args['pagenum'];
				break;
		}
	
		$args['currentplaylist'] = $this->mpd->getCurrentPlaylist($currentPage);
		$args['currentplaylistlength'] = $this->mpd->getCurrentPlaylistLength();
		
		// get all relational items we need for rendering
		$args['renderitems'] = $this->getRenderItems($args['item'], $args['nowplaying_album'], $args['currentplaylist']);
		$args['paginator'] = new \JasonGrimes\Paginator(
			$args['currentplaylistlength'],
			$this->conf['mpd-playlist']['max-items'],
			$currentPage,
			$this->conf['config']['absRefPrefix'] . 'playlist/page/(:num)'
		);
		$args['paginator']->setMaxPagesToShow(paginatorPages($currentPage));
		$this->view->render($response, 'surrounding.htm', $args);
		return $response;
	}

	public function mpdstatusAction(Request $request, Response $response, $args) {
		# TODO: mpd-version check, v 0.20 has 'duration' included in status()
		# @see: http://www.musicpd.org/doc/protocol/command_reference.html#status_commands
		$args['mpd']['status'] = $this->mpd->cmd('status');
		if($args['mpd']['status'] === FALSE) {
			$args['mpd']['status'] = array(
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
			$currentSong = $this->mpd->cmd('currentsong');
			if(isset($currentSong['Time']) === FALSE || $currentSong['Time'] < 1) {
				return $response->withJson($args['mpd']['status'], 201);
			}
			if(isset($args['mpd']['status']['elapsed']) === FALSE) {
				return $response->withJson($args['mpd']['status'], 201);
			}
			$args['mpd']['status']['duration'] = $currentSong['Time'];
			$percent = $args['mpd']['status']['elapsed'] / ($args['mpd']['status']['duration']/100);
			$args['mpd']['status']['percent'] = ($percent >=0 && $percent <= 100) ? $percent : 0;
		} catch (\Exception $e) {
			$args['mpd']['status']['duration'] = "0";
			$args['mpd']['status']['percent'] = "0";
		}
		return $response->withJson($args['mpd']['status'], 201);
	}
}
