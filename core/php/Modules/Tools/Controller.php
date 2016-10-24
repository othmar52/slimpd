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

	public function showplaintextAction(Request $request, Response $response, $args) {
		$args['action'] = "showplaintext";
		$relPath = $args['itemParams'];
		$validPath = $this->container->filesystemUtility->getFileRealPath($relPath);
		if($validPath === FALSE) {
			$this->container->flash->AddMessage('error', 'invalid path ' . $relPath);
		} else {
			$args['plaintext'] = nfostring2html(file_get_contents($validPath));
		}
		$args['filepath'] = $relPath;
		
		$this->view->render($response, 'modules/widget-plaintext.htm', $args);
		return $response->withHeader('Content-Type', 'text/css');
	}

	public function cleanRenameConfirmAction(Request $request, Response $response, $args) {
		if($this->conf['destructiveness']['clean-rename'] !== '1') {
			$this->view->render($response, 'modules/widget-cleanrename.htm', $args);
			return $response;
		}
		$fileBrowser = new \Slimpd\Modules\filebrowser\filebrowser($this->container);
		$fileBrowser->getDirectoryContent($args['itemParams']);
		$args['directory'] = $fileBrowser;
		$args['action'] = 'clean-rename-confirm';
		$this->view->render($response, 'modules/widget-cleanrename.htm', $args);
		return $response;
	}

	public function cleanRenameAction(Request $request, Response $response, $args) {
		if($this->conf['destructiveness']['clean-rename'] !== '1') {
			$this->view->render($response, 'modules/widget-cleanrename.htm', $args);
			return $response;
		}

		$fileBrowser = new \Slimpd\Modules\filebrowser\filebrowser($this->container);
		$fileBrowser->getDirectoryContent($args['itemParams']);

		// do not block other requests of this client
		session_write_close();

		// IMPORTANT TODO: move this to an exec-wrapper
		$cmd = APP_ROOT . 'core/vendor-dist/othmar52/clean-rename/clean-rename '
			. escapeshellarg($this->conf['mpd']['musicdir']. $fileBrowser->directory);
		exec($cmd, $result);

		$args['result'] = join("\n", $result);
		$args['directory'] = $fileBrowser;
		$args['cmd'] = $cmd;
		$args['action'] = 'clean-rename';
		$this->view->render($response, 'modules/widget-cleanrename.htm', $args);
		return $response;
	}
}
