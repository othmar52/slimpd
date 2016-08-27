<?php


$app->get('/playlists', function() use ($app, $vars){
	$vars['action'] = "playlists";
	$app->flash('error', 'playlists not implemented yet - fallback to filebrowser/playlists');
	$app->response->redirect($app->config['root'] . 'filebrowser/playlists' . getNoSurSuffix(), 301);
});


$app->get('/showplaylist/:itemParams+', function($itemParams) use ($app, $vars){
	$vars['action'] = "showplaylist";
	$playlist = new \Slimpd\Models\PlaylistFilesystem(join(DS, $itemParams));

	if($playlist->getErrorPath() === TRUE) {
		$app->render('surrounding.htm', $vars);
		return;
	}
	
	$itemsPerPage = \Slim\Slim::getInstance()->config['mpd-playlist']['max-items'];
	
	$totalItems = $playlist->getLength();
	
	if($app->request->get('page') === 'last') {
		$currentPage = ceil($totalItems/$itemsPerPage);
	} else {
		$currentPage = $app->request->get('page');
	}
	$currentPage = ($currentPage) ? $currentPage : 1;
	$minIndex = (($currentPage-1) * $itemsPerPage);
	$maxIndex = $minIndex +  $itemsPerPage;

	$playlist->fetchTrackRange($minIndex, $maxIndex);

	$vars['itemlist'] = $playlist->getTracks();
	$vars['renderitems'] = getRenderItems($vars['itemlist']);
	$vars['playlist'] = $playlist;
	$vars['paginator'] = new JasonGrimes\Paginator(
		$totalItems,
		$itemsPerPage,
		$currentPage,
		$app->config['root'] . 'showplaylist/'.$playlist->getRelPath() .'?page=(:num)'
	);
	$vars['paginator']->setMaxPagesToShow(paginatorPages($currentPage));
	$app->render('surrounding.htm', $vars);
});

$app->get('/markup/widget-playlist/:itemParams+', function($itemParams) use ($app, $vars){
	$vars['action'] = 'widget-playlist';
	$vars['playlist'] = new \Slimpd\Models\PlaylistFilesystem(join(DS, $itemParams));
	$vars['playlist']->fetchTrackRange(0, 5);
	$vars['playlisttracks'] = $vars['playlist']->getTracks();
	$vars['renderitems'] = getRenderItems($vars['playlist']->getTracks());
	$vars['breadcrumb'] =  \Slimpd\filebrowser::fetchBreadcrumb(join(DS, $itemParams));
	$app->render('modules/widget-playlist.htm', $vars);
});
