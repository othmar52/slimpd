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
 * FITNESS FOR A PARTICULAR PURPOSE.    See the GNU Affero General Public License
 * for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.    If not, see <http://www.gnu.org/licenses/>.
 */
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class Controller extends \Slimpd\BaseController {
    protected $xwax;
    protected $notifyJson = NULL;

    public function xwaxplayerAction(Request $request, Response $response, $args) {
        $this->xwax = new \Slimpd\Modules\Xwax\Xwax($this->container);
        $this->validateBaseConfig();
        $this->validateClientCommand('get_status');
        $args['decknum'] = $request->getParam('deck');
        $this->validateDeckParam($args['decknum']);
        
        $args['item'] = $this->xwax->getCurrentlyPlayedTrack($args['decknum']);
        if($args['item'] !== NULL) {
            $args['renderitems'] = $this->getRenderItems($args['item']);
        }
        $templateFile = ($request->getParam('type') === 'djscreen')
            ? 'modules/standalone-trackview.htm'
            : 'modules/xwaxplayer.htm';
        $this->view->render($response, $templateFile , $args);
        return $response;
    }

    public function statusAction(Request $request, Response $response, $args) {
        useArguments($args);
        $this->xwax = new \Slimpd\Modules\Xwax\Xwax($this->container);
        $this->validateBaseConfig();
        $this->validateClientCommand('get_status');
        if($this->notifyJson !== NULL) {
            $newResponse = $response;
            return $newResponse->withJson($this->notifyJson);
        }
        if($request->getParam('force' === '1')) {
            // do not use database-cached poll result
            $this->xwax->noCache = TRUE;
        }

        $deckStats = $this->xwax->fetchAllDeckStats();
        $newResponse = $response;
        if($this->xwax->notifyJson !== NULL) {
            return $newResponse->withJson($this->xwax->notifyJson);
        }
        return $newResponse->withJson($deckStats);
    }

    public function cmdLoadTrackAction(Request $request, Response $response, $args) {
        useArguments($request);
        $this->xwax = new \Slimpd\Modules\Xwax\Xwax($this->container);
        $this->validateBaseConfig();
        $this->validateClientCommand('load_track');
        $this->validateDeckParam($args['deckIndex']);
        $newResponse = $response;
        $filePath = $this->filesystemUtility->getFileRealPath($args['itemParams']);
        if($filePath === FALSE) {
            return $newResponse->withJson(notifyJson($this->ll->str('xwax.invalid.file'), 'danger'));
        }
        // TODO: fetch artist and title from database
        $this->xwax->loadArgs = escapeshellargDirty($filePath) . ' artistname tracktitle';
        $this->xwax->cmd();
        return $newResponse->withJson($this->xwax->notifyJson);
    }

    public function cmdReconnectAction(Request $request, Response $response, $args) {
        useArguments($request, $response, $args);
        return $this->runSingleDeckCommand($request, $response, $args, 'reconnect');
    }

    public function cmdDisconnectAction(Request $request, Response $response, $args) {
        return $this->runSingleDeckCommand($request, $response, $args, 'disconnect');
    }

    public function cmdRecueAction(Request $request, Response $response, $args) {
        return $this->runSingleDeckCommand($request, $response, $args, 'recue');
    }

    public function cmdCycleTimecodeAction(Request $request, Response $response, $args) {
        return $this->runSingleDeckCommand($request, $response, $args, 'cycle_timecode');
    }

    public function runSingleDeckCommand(Request $request, Response $response, $args, $cmd) {
        useArguments($request);
        $this->xwax = new \Slimpd\Modules\Xwax\Xwax($this->container);
        $this->validateBaseConfig();
        $this->validateClientCommand($cmd);
        $this->validateDeckParam($args['deckIndex']);
        $newResponse = $response;
        if($this->notifyJson !== NULL) {
            return $newResponse->withJson($this->notifyJson);
        }
        $this->xwax->cmd();
        return $newResponse->withJson($this->xwax->notifyJson);
    }

    public function cmdLaunchAction(Request $request, Response $response, $args) {
        useArguments($request, $args);
        $this->xwax = new \Slimpd\Modules\Xwax\Xwax($this->container);
        $this->validateBaseConfig();
        $this->validateClientCommand('launch');
        $newResponse = $response;
        if($this->notifyJson !== NULL) {
            return $newResponse->withJson($this->notifyJson);
        }
        $this->xwax->cmd();
        return $newResponse->withJson($this->xwax->notifyJson);
    }

    public function cmdExitAction(Request $request, Response $response, $args) {
        useArguments($request, $args);
        $this->xwax = new \Slimpd\Modules\Xwax\Xwax($this->container);
        $this->validateBaseConfig();
        $this->validateClientCommand('exit');
        $newResponse = $response;
        if($this->notifyJson !== NULL) {
            return $newResponse->withJson($this->notifyJson);
        }
        $this->xwax->cmd();
        return $newResponse->withJson($this->xwax->notifyJson);
    }

    protected function validateClientCommand($cmd) {
        // check if we can fetch the command mapping from config
        if(isset($this->conf['xwax']['cmd_'. $cmd]) === FALSE || trim($this->conf['xwax']['cmd_'. $cmd]) === '') {
            $this->notifyJson = notifyJson($this->ll->str('xwax.invalid.cmd'), 'danger');
            return;
        }
        $this->xwax->runCmd = $cmd;
    }

    protected function validateDeckParam($deckIndex) {
        if(in_array($this->xwax->runCmd, ['launch', 'exit']) === TRUE) {
            // we do not need a param for lauch or exit
            return;
        }
        // all other commands needs a deck parameter (first deck is "0")
        if($deckIndex >= $this->xwax->totalDecks) {
            $this->notifyJson = notifyJson($this->ll->str('xwax.missing.deckparam'), 'danger');
            return;
        }
        $this->xwax->deckIndex = $deckIndex;
    }

    protected function validateBaseConfig() {
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

        // check if the client-file exists
        if(is_file($this->xwax->clientPath) === FALSE) {
            $this->notifyJson = notifyJson($this->ll->str('xwax.invalid.clientpath'), 'danger');
            return;
        }

        // check if we have a configured IP or server-name
        if(trim($this->conf['xwax']['server']) === '') {
            $this->notifyJson = notifyJson($this->ll->str('xwax.invalid.serverconf'), 'danger');
            return;
        }
        $this->xwax->ipAddress = $this->conf['xwax']['server'];
        return;
    }

    public function widgetAction(Request $request, Response $response, $args) {
        useArguments($request);
        $this->xwax = new \Slimpd\Modules\Xwax\Xwax($this->container);
        $this->validateBaseConfig();
        $this->validateClientCommand('get_status');
        $newResponse = $response;
        if($this->notifyJson !== NULL) {
            return $newResponse->withJson($this->notifyJson);
        }
        $args['xwax']['deckstats'] = $this->xwax->fetchAllDeckStats();
        if($this->xwax->notifyJson !== NULL) {
            return $newResponse->withJson($this->xwax->notifyJson);
        }
        foreach($args['xwax']['deckstats'] as $deckStat) {
            $itemsToRender[] = $deckStat['item'];
        }
        $args['renderitems'] = $this->getRenderItems($itemsToRender);
        $this->view->render($response, 'modules/widget-xwax.htm', $args);
        return $response;
    }
    public function djscreenAction(Request $request, Response $response, $args) {
        useArguments($request);
        $args['action'] = "djscreen";
        $this->view->render($response, 'djscreen.htm', $args);
        return $response;
    }
}
