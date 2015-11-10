<?php

$app->post('/alphasearch/', function() use ($app, $config){
	$type = $app->request()->post('searchtype');
	$term = $app->request()->post('searchterm');
	$app->response->redirect('/library/'.$type.'s/searchterm/'.rawurlencode($term).'/page/1');
});
