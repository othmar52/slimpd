<?php

$app->get('/systemcheck', function() use ($app, $vars){
	$systemCheck = new \Slimpd\Systemcheck();
	$vars['sys'] = $systemCheck->runChecks();
	$vars['appRoot'] = APP_ROOT;
	$vars['action'] = 'systemcheck';
	$app->render('appless.htm', $vars);
});