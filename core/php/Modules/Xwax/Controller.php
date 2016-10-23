<?php
namespace Slimpd\Modules\Xwax;
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
	private $xwax;
	private $notifyJson = NULL;

	public function xwaxplayerAction(Request $request, Response $response, $args) {
		#die('TODO: upgrade to slimv3');
		$this->xwax = new \Slimpd\Modules\Xwax\Xwax($this->container);
		$this->validateBaseConfig();
		$this->validateClientCommand('get_status');
		$args['decknum'] = $request->getParam('deck');
		$this->validateDeckParam($args['decknum']);
		
		$args['item'] = $this->xwax->getCurrentlyPlayedTrack($args['decknum']);
		#var_dump($args['item']);die;
		
		if($args['item'] !== NULL) {
			#$itemRelPath = $args['item']->getRelPath();
			$args['renderitems'] = $this->getRenderItems($args['item']);
		}
		
		$this->view->render($response, 'modules/xwaxplayer.htm', $args);
		return $response;
		
		#if($app->request->get('type') == 'djscreen') {
		#	$markupSnippet = 'standalone-trackview';
		#	$templateFile = 'modules/standalone-trackview.htm';
		#}
	}

	public function statusAction(Request $request, Response $response, $args) {
		$this->xwax = new \Slimpd\Modules\Xwax\Xwax($this->container);
		$this->validateBaseConfig();
		$this->validateClientCommand('get_status');
		if($this->notifyJson !== NULL) {
			$newResponse = $response;
			return $newResponse->withJson($this->notifyJson);
		}
		if($request->getParam('force' === '1')) {
			$this->xwax->noCache = TRUE;
		}
		
		$deckStats = $this->xwax->fetchAllDeckStats();
		#var_dump($deckStats); die('sdgsdgdfhd');
		$newResponse = $response;
		if($this->xwax->notifyJson !== NULL) {
			return $newResponse->withJson($this->xwax->notifyJson);
		}
		return $newResponse->withJson($deckStats);
	}

	public function cmdLoadTrackAction(Request $request, Response $response, $args) {
		$this->xwax = new \Slimpd\Modules\Xwax\Xwax($this->container);
		$this->validateBaseConfig();
		$this->validateClientCommand('load_track');
		$this->validateDeckParam($args['deckIndex']);
		
		$filePath = $this->filesystemUtility->getFileRealPath($args['itemParams']);
		if($filePath === FALSE) {
			$this->notifyJson = notifyJson($this->ll->str('xwax.invalid.file'), 'danger');
		}
		// TODO: fetch artist and title from database
		$this->xwax->loadArgs = escapeshellarg($filePath) . ' artistname tracktitle';

		if($this->notifyJson !== NULL) {
			$newResponse = $response;
			return $newResponse->withJson($this->notifyJson);
		}
		$this->xwax->cmd();
		$newResponse = $response;
		return $newResponse->withJson($this->xwax->notifyJson);
		die(__FUNCTION__);
		$xwax = new \Slimpd\Modules\Xwax\Xwax($this->container);
		if($request->getParam('force' === '1')) {
			$xwax->noCache = TRUE;
		}
		$xwax->cmd($args['cmd'], $args['params']);
		$app->stop();


		$this->view->render($response, 'css/nowplaying.css', $args);
		return $response->withHeader('Content-Type', 'text/css');
	}
	
	private function validateClientCommand($cmd) {
		if(isset($this->conf['xwax']['cmd_'. $cmd]) === FALSE || trim($this->conf['xwax']['cmd_'. $cmd]) === '') {
			$this->notifyJson = notifyJson($this->ll->str('xwax.invalid.cmd'), 'danger');
			return;
		}
		$this->xwax->runCmd = $cmd;
	}

	private function validateDeckParam($deckIndex) {
		if(in_array($this->xwax->runCmd, ['launch', 'kill']) === TRUE) {
			// we do not need a param for lauch or exit
			return;
		}
		if($deckIndex < 1 || $deckIndex > $this->xwax->totalDecks) {
			$this->notifyJson = notifyJson($this->ll->str('xwax.missing.deckparam'), 'danger');
			return;
		}
		// url uses "1" as first deck but client needs "0" for first deck 
		$this->xwax->deckIndex = $deckIndex-1;
	}

	private function validateBaseConfig() {
		// check if xwax control is enabled by config
		if($this->conf['modules']['enable_xwax'] !== '1') {
			$this->notifyJson = notifyJson($this->ll->str('xwax.notenabled'), 'danger');
			return;
		}

		// check configuration for deck amount
		if($this->conf['xwax']['decks'] < 1) {
			$this->notifyJson = notifyJson($this->ll->str('xwax.deckconfig'), 'danger');
			return;
		}
		$this->xwax->totalDecks = $this->conf['xwax']['decks'];

		// check if clientpath is relative or absolute
		$this->xwax->clientPath = ($this->conf['xwax']['clientpath'][0] === '/')
			? $this->conf['xwax']['clientpath']
			: APP_ROOT . $this->conf['xwax']['clientpath'];

		if(is_file($this->xwax->clientPath) === FALSE) {
			$this->notifyJson = notifyJson($this->ll->str('xwax.invalid.clientpath'), 'danger');
			return;
		}
		if(trim($this->conf['xwax']['server']) === '') {
			$this->notifyJson = notifyJson($this->ll->str('xwax.invalid.serverconf'), 'danger');
			return;
		}
		
		$this->xwax->ipAddress = $this->conf['xwax']['server'];
		return;
	}

	public function widgetAction(Request $request, Response $response, $args) {
		$xwax = new \Slimpd\Modules\Xwax\Xwax($this->container);
		if($request->getParam('force' === '1')) {
			$xwax->noCache = TRUE;
		}
		$args['xwax']['deckstats'] = $xwax->fetchAllDeckStats();
		if($xwax->notifyJson !== NULL) {
			$newResponse = $response;
			return $newResponse->withJson($xwax->notifyJson);
		}
		foreach($args['xwax']['deckstats'] as $deckStat) {
			$itemsToRender[] = $deckStat['item'];
		}
		$vars['renderitems'] = $this->getRenderItems($itemsToRender);
		$this->view->render($response, 'modules/widget-xwax.htm', $args);
		return $response;
		die(__FUNCTION__);
		$xwax->cmd($cmd, $params, $app);
		$app->stop();


		$this->view->render($response, 'css/nowplaying.css', $args);
		return $response->withHeader('Content-Type', 'text/css');
	}
}
