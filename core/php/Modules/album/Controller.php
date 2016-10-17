<?php
namespace Slimpd\Modules\album;
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



	public function listAction(Request $request, Response $response, $args) {
		
		#var_dump($this->albumRepository);die;
		
		$args["action"] = "albums";
		$args["itemlist"] = [];
		$itemsPerPage = 18;
	
		$args['itemlist'] = $this->albumRepo->getAll($itemsPerPage, $args['currentPage'], $args['sort'] . " " . $args['direction']);
		#die(__FUNCTION__);
		$args["totalresults"] = \Slimpd\Models\Album::getCountAll($this->db);
		$args["activesorting"] = $args['sort'] . "-" . $args['direction'];
	
		$args["paginator"] = new \JasonGrimes\Paginator(
			$args["totalresults"],
			$itemsPerPage,
			$args['currentPage'],
			$this->conf['config']['absRefPrefix'] ."albums/page/(:num)/sort/".$args['sort']."/".$args['direction']
		);
		$args["paginator"]->setMaxPagesToShow(paginatorPages($args['currentPage']));
		$args['renderitems'] = $this->getRenderItems($args['itemlist']);
		$this->view->render($response, 'surrounding.htm', $args);
		return $response;
	}

	public function detailAction(Request $request, Response $response, $args) {
		$this->completeArgsForDetailView($args['itemUid'], $args);
		if($args['album'] === NULL) {
			die('TODO: redirect to 404');
			$app->notFound();
			return;
		}
		$args['action'] = 'album.detail';
		$this->view->render($response, 'surrounding.htm', $args);
		return $response;
	}
	
	public function albumTracksAction(Request $request, Response $response, $args) {
		$this->completeArgsForDetailView($args['itemUid'], $args);
		if($args['album'] === NULL) {
			die('TODO: redirect to 404');
			$app->notFound();
			return;
		}
		$args['action'] = 'albumtracks';
		$this->view->render($response, 'surrounding.htm', $args);
		return $response;
	}
	
	public function widgetAlbumAction(Request $request, Response $response, $args) {
		$this->completeArgsForDetailView($args['itemUid'], $args);
		if($args['album'] === NULL) {
			die('TODO: redirect to 404');
			$app->notFound();
			return;
		}
		$args['action'] = 'albumwidget';
		$this->view->render($response, 'modules/widget-album.htm', $args);
		return $response;
	}

	private function completeArgsForDetailView($albumUid, &$args) {
		$args['album'] = $this->container->albumRepo->getInstanceByAttributes(array('uid' => $albumUid));
		if($args['album'] === NULL) {
			return;
		}
		$args['itemlist'] = $this->container->trackRepo->getInstancesByAttributes(
			['albumUid' => $albumUid], FALSE, 200, 1, 'trackNumber ASC'
		);
		$args['renderitems'] = $this->getRenderItems($args['album'], $args['itemlist']);
		$args['albumimages'] = [];
		$args['bookletimages'] = [];
		$bitmaps = $this->container->bitmapRepo->getInstancesByAttributes(
			['albumUid' => $albumUid], FALSE, 200, 1, 'imageweight'
		);
		$foundFront = FALSE;
		foreach($bitmaps as $bitmap) {
			switch($bitmap->getPictureType()) {
				case 'front':
					if($foundFront === TRUE && $app->config['images']['hide_front_duplicates'] === '1') {
						continue;
					}
					$args['albumimages'][] = $bitmap;
					$foundFront = TRUE;
					break;
				case 'booklet':
					$args['bookletimages'][] = $bitmap;
					break;
				default:
					$args['albumimages'][] = $bitmap;
					break;
			}
		}
		
		$args['breadcrumb'] = \Slimpd\Modules\filebrowser\filebrowser::fetchBreadcrumb($args['album']->getRelPath());
		return;
	}
}
