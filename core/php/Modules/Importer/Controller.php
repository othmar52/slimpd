<?php
namespace Slimpd\Modules\Importer;
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
	public function indexAction(Request $request, Response $response, $args) {
		$args['action'] = 'importer';
		$args['servertime'] = time();
		
		$query = "SELECT * FROM importer ORDER BY batchUid DESC, jobPhase ASC LIMIT 200;";
		$result = $this->db->query($query);
		$showDetail = TRUE;
		$running = TRUE;
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
		$this->view->render($response, 'surrounding.htm', $args);
		return $response;
	}
}
