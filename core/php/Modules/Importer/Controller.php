<?php
namespace Slimpd\Modules\Importer;
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
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License
 * for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class Controller extends \Slimpd\BaseController {
    use \Slimpd\Traits\MethodRedirectToSignIn;
    public function indexAction(Request $request, Response $response, $args) {
        if($this->auth->hasPermissionFor('importer') === FALSE) {
            $this->flash->addMessage('error', 'Access denied');
            return $this->redirectToSignIn($response);
        }
        useArguments($request);
        $args['action'] = 'importer';
        $args['servertime'] = time();

        $query = "SELECT * FROM importer WHERE batchUid>0 ORDER BY batchUid DESC, jobPhase ASC LIMIT 200;";
        $result = $this->db->query($query);
        $showDetail = TRUE;
        $batchBegin = 0;
        while($record = $result->fetch_assoc() ) {
            $record['jobStatistics'] = unserialize($record['jobStatistics']);
            $batchUid = $record["batchUid"];
            if($record["jobPhase"] === "0") {
                $args['itemlist'][$batchUid] = $record;
                $args['itemlist'][$batchUid]["showDetails"] = $showDetail;
                $args['itemlist'][$batchUid]["lastUpdate"] = $record["jobLastUpdate"];
                $batchBegin = $record['jobStart'];
                $args['itemlist'][$batchUid]['status'] = ($record['jobEnd'] < 1)
                    ? ($showDetail === TRUE) ? 'running' : 'interrupted'
                    : 'finished';
                $showDetail = FALSE;
                continue;
            }
            $args['itemlist'][$batchUid]["interruptedAfter"] = $record["jobLastUpdate"] - $batchBegin;
            $args['itemlist'][$batchUid]["phases"][ $record["jobPhase"] ] = $record;
        }
        $args['lastHeartBeat'] = CliController::getHeartBeatTstamp();
        $this->view->render($response, 'surrounding.htm', $args);
        return $response;
    }
}
