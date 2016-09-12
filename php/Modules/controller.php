<?php
/* Copyright
 *
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
