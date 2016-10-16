<?php
namespace Slimpd\Modules\images;
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
	private $imageSizes = array(35, 50,100,300,1000);
	public function fallback(Request $request, Response $response, $args) {
		// T
		if(in_array($args['imagesize'], $this->imageSizes) === FALSE) {
			die('invalid size');
		}
		$playerMode = $this->view->getEnvironment()->getGlobals()['playerMode'];

		$args['color'] = $this->conf['images']['noimage'][$playerMode]['color'];
		$args['backgroundcolor'] = $this->conf['images']['noimage'][$playerMode]['backgroundcolor'];

		switch($args['type']) {
			case 'artist':    $template = 'svg/icon-artist.svg'; break;
			case 'genre':     $template = 'svg/icon-genre.svg'; break;
			case 'noresults': $template = 'svg/icon-noresults.svg'; break;
			case 'broken':    $template = 'svg/icon-broken-image.svg'; break;
			default:          $template = 'svg/icon-album.svg';
		}

		$this->view->render($response, $template, $args);
		return $response->withHeader('Content-Type', 'image/svg+xml');
	}
}
