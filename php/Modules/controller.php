<?php


$app->get('/importer(/)', function() use ($app, $vars){
	$vars['action'] = 'importer';
	$vars['servertime'] = time();;
	
	$query = "SELECT * FROM importer ORDER BY batchId DESC, jobPhase ASC LIMIT 200;";
	$result = $app->db->query($query);
	$showDetail = TRUE;
	$running = TRUE;
	$batchBegin = 0;
	while($record = $result->fetch_assoc() ) {
		$record['jobStatistics'] = unserialize($record['jobStatistics']);
		$batchId = $record["batchId"];
		if($record["jobPhase"] === "0") {
			$vars['itemlist'][$batchId] = $record;
			$vars['itemlist'][$batchId]["showDetails"] = $showDetail;
			$vars['itemlist'][$batchId]["lastUpdate"] = $record["jobLastUpdate"];
			$batchBegin = $record['jobStart'];
			$vars['itemlist'][$batchId]['status'] = ($record['jobEnd'] < 1)
				? ($showDetail === TRUE) ? 'running' : 'interrupted'
				: 'finished';
			$showDetail = FALSE;
			continue;
		}
		$vars['itemlist'][$batchId]["interruptedAfter"] = $record["jobLastUpdate"] - $batchBegin;
		$vars['itemlist'][$batchId]["phases"][] = $record;
	}
	$app->render('surrounding.htm', $vars);
});

$app->get('/importer/triggerUpdate', function() use ($app, $vars){
	\Slimpd\importer::queStandardUpdate();
});

