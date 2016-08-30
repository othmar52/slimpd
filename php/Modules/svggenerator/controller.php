<?php

$app->get('/audiosvg/width/:width/:itemParam+', function($width, $itemParam) use ($app, $vars){
	$svgGenerator = new \Slimpd\Svggenerator($itemParam);
	$svgGenerator->generateSvg($width);
});

$app->get('/audiojson/resolution/:width/:itemParam+', function($resolution, $itemParam) use ($app, $vars){
	$svgGenerator = new \Slimpd\Svggenerator($itemParam);
	$svgGenerator->generateJson($resolution);
});

