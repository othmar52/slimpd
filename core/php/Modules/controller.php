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

$app->get('/importer(/)', function() use ($app, $vars){
	$vars['action'] = 'importer';
	$vars['servertime'] = time();
	
	$query = "SELECT * FROM importer ORDER BY batchUid DESC, jobPhase ASC LIMIT 200;";
	$result = $app->db->query($query);
	$showDetail = TRUE;
	$running = TRUE;
	$batchBegin = 0;
	while($record = $result->fetch_assoc() ) {
		$record['jobStatistics'] = unserialize($record['jobStatistics']);
		$batchUid = $record["batchUid"];
		if($record["jobPhase"] === "0") {
			$vars['itemlist'][$batchUid] = $record;
			$vars['itemlist'][$batchUid]["showDetails"] = $showDetail;
			$vars['itemlist'][$batchUid]["lastUpdate"] = $record["jobLastUpdate"];
			$batchBegin = $record['jobStart'];
			$vars['itemlist'][$batchUid]['status'] = ($record['jobEnd'] < 1)
				? ($showDetail === TRUE) ? 'running' : 'interrupted'
				: 'finished';
			$showDetail = FALSE;
			continue;
		}
		$vars['itemlist'][$batchUid]["interruptedAfter"] = $record["jobLastUpdate"] - $batchBegin;
		$vars['itemlist'][$batchUid]["phases"][ $record["jobPhase"] ] = $record;
	}
	$app->render('surrounding.htm', $vars);
});

$app->get('/importer/triggerUpdate', function() use ($app, $vars){
	\Slimpd\Modules\Importer::queStandardUpdate();
});
