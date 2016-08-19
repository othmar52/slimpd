<?php
$weightConf = trimExplode("\n", $app->config['images']['weightening'], TRUE);
$imageWeightOrderBy = "FIELD(pictureType, '" . join("','", $weightConf) . "'), filesize DESC";

#echo $imageWeightOrderBy; die();
// predefined album-image sizes
foreach (array(35, 50,100,300,1000) as $imagesize) {
	$app->get('/image-'.$imagesize.'/album/:itemId', function($itemId) use ($app, $vars, $imagesize, $imageWeightOrderBy){
		$image = \Slimpd\Models\Bitmap::getInstanceByAttributes(
			array('albumId' => $itemId), $imageWeightOrderBy
		);
		if($image === NULL) {
			$app->response->redirect($app->urlFor('imagefallback-'.$imagesize, ['type' => 'album']));
			return;
		}
		
		$image->dump($imagesize, $app);
	});
	
	$app->get('/image-'.$imagesize.'/track/:itemId', function($itemId) use ($app, $vars, $imagesize, $imageWeightOrderBy){
		$image = \Slimpd\Models\Bitmap::getInstanceByAttributes(
			array('trackId' => $itemId), $imageWeightOrderBy
		);
		if($image === NULL) {
			$track = \Slimpd\Models\Track::getInstanceByAttributes(
				array('id' => $itemId)
			);  
			$app->response->redirect($app->config['root'] . 'image-'.$imagesize.'/album/' . $track->getAlbumId());
			return;
		}
		$image->dump($imagesize, $app);
	});
	
	$app->get('/image-'.$imagesize.'/id/:itemId', function($itemId) use ($app, $vars, $imagesize, $imageWeightOrderBy){
		$image = \Slimpd\Models\Bitmap::getInstanceByAttributes(
			array('id' => $itemId), $imageWeightOrderBy
		);
		if($image === NULL) {
			$app->response->redirect($app->urlFor('imagefallback-'.$imagesize, ['type' => 'track']));
			return;
		}
		$image->dump($imagesize, $app);
	});
	
	$app->get('/image-'.$imagesize.'/path/:itemParams+', function($itemParams) use ($app, $vars, $imagesize){
		$image = new \Slimpd\Models\Bitmap();
		
		$image->setRelativePath(join(DS, $itemParams));
		$image->dump($imagesize, $app);
	})->name('imagepath-' .$imagesize);
	
	$app->get('/image-'.$imagesize.'/searchfor/:itemParams+', function($itemParams) use ($app, $vars, $imagesize){
		$importer = new Slimpd\importer();
		$images = $importer->getFilesystemImagesForMusicFile(join(DS, $itemParams));
		
		if(count($images) === 0) {
			$app->response->redirect($app->urlFor('imagefallback-'.$imagesize, ['type' => 'track']));
			return;
		}
		// pick a random image
		shuffle($images);
		$path = array_shift($images);
		
		$app->response->redirect($app->urlFor('imagepath-'.$imagesize, ['itemParams' => path2url($path)]));

	});
	
	$app->get('/imagefallback-'.$imagesize.'/:type', function($type) use ($app, $vars, $imagesize){
		$vars['imagesize'] = $imagesize;
		$vars['color'] = $vars['images']['noimage'][ $vars['playerMode'] ]['color'];
		$vars['backgroundcolor'] = $vars['images']['noimage'][ $vars['playerMode'] ]['backgroundcolor'];
		
		switch($type) {
			case 'artist': $template = 'svg/icon-artist.svg'; break;
			case 'noresults': $template = 'svg/icon-noresults.svg'; break;
			case 'broken': $template = 'svg/icon-broken-image.svg'; break;
			default: $template = 'svg/icon-album.svg';
		}
		$app->response->headers->set('Content-Type', 'image/svg+xml');
		
		header("Content-Type: image/svg+xml");
		$app->render($template, $vars);
	})->name('imagefallback-' .$imagesize);
	
	// missing track or album paramter caused by items that are not imported in slimpd yet
	# TODO: maybe use another fallback image for those items...
	$app->get('/image-'.$imagesize.'/album/', function() use ($app, $vars, $imagesize){
		$app->response->redirect($app->urlFor('imagefallback-'.$imagesize, ['type' => 'album']));
	});
	$app->get('/image-'.$imagesize.'/track/', function() use ($app, $vars, $imagesize){
		$app->response->redirect($app->urlFor('imagefallback-'.$imagesize, ['type' => 'track']));
	});
	
}