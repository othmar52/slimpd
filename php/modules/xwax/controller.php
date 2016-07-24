<?php

$app->get('/xwax/:cmd/:params+', function($cmd, $params) use ($app, $vars){
	$xwax = new \Slimpd\Xwax();
	$xwax->cmd($cmd, $params, $app);
	$app->stop();
});

$app->get('/xwaxstatus(/)', function() use ($app, $vars){
	$xwax = new \Slimpd\Xwax();
	$deckStats = $xwax->fetchAllDeckStats();
	echo json_encode($deckStats);
	$app->stop();
});
