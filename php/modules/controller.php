<?php


$app->get('/importer(/)', function() use ($app, $vars){
	$vars['action'] = 'importer';
	$vars['servertime'] = time();;
	
	$query = "SELECT * FROM importer ORDER BY jobStart DESC,id DESC LIMIT 30;";
	$result = $app->db->query($query);
	while($record = $result->fetch_assoc() ) {
		$record['jobStatistics'] = unserialize($record['jobStatistics']);
		$vars['itemlist'][] = $record;
	}
	$app->render('surrounding.htm', $vars);
});

$app->get('/importer/triggerUpdate', function() use ($app, $vars){
	\Slimpd\importer::queStandardUpdate();
});

