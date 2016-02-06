<?php

$app->get('/alphasearch/', function() use ($app, $config){
	$type = $app->request()->get('searchtype');
	$term = $app->request()->get('searchterm');
	$nosurParam = ($config['nosurrounding'] === TRUE)
		? '?nosurrounding=1'
		: '';
	$app->response->redirect('/'.$type.'s/searchterm/'.rawurlencode($term).'/page/1' . $nosurParam);
});
