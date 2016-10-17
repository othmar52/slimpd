<?php
namespace Slimpd\Modules\Tools;
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
	public function spotcolorsCssAction(Request $request, Response $response, $args) {
		$this->view->render($response, 'css/spotcolors.css', $args);
		return $response->withHeader('Content-Type', 'text/css');
	}

	public function localPlayerCssAction(Request $request, Response $response, $args) {
		$args['color'] = $this->conf['colors'][ $this->conf['spotcolor']['local'] ]['1st'];
		$this->view->render($response, 'css/nowplaying.css', $args);
		return $response->withHeader('Content-Type', 'text/css');
	}

	public function mpdPlayerCssAction(Request $request, Response $response, $args) {
		$args['color'] = $this->conf['colors'][ $this->conf['spotcolor']['mpd'] ]['1st'];
		$this->view->render($response, 'css/nowplaying.css', $args);
		return $response->withHeader('Content-Type', 'text/css');
	}

	public function xwaxPlayerCssAction(Request $request, Response $response, $args) {
		$args['color'] = $this->conf['colors'][ $this->conf['spotcolor']['xwax'] ]['1st'];
		$args['deck'] = $request->getParam('deck');
		$this->view->render($response, 'css/nowplaying.css', $args);
		return $response->withHeader('Content-Type', 'text/css');
	}
}
