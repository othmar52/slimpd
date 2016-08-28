<?php


$app->get('/importer(/)', function() use ($app, $vars){
	$vars['action'] = 'importer';
	$vars['servertime'] = time();;
	
	$query = "SELECT * FROM importer ORDER BY batchId DESC, jobPhase ASC LIMIT 200;";
	$result = $app->db->query($query);
	$showDetail = TRUE;
	while($record = $result->fetch_assoc() ) {
		$record['jobStatistics'] = unserialize($record['jobStatistics']);
		$batchId = $record["batchId"];
		if($record["jobPhase"] === "0") {
			$vars['itemlist'][$batchId] = $record;
			$vars['itemlist'][$batchId]["showDetails"] = $showDetail;
			$showDetail = FALSE;
			continue;
		}
		$vars['itemlist'][$batchId]["phases"][] = $record;
	}
	$app->render('surrounding.htm', $vars);
});

$app->get('/importer/triggerUpdate', function() use ($app, $vars){
	\Slimpd\importer::queStandardUpdate();
});

