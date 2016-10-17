<?php
namespace Slimpd\Modules\Bitmap;
/* Copyright (C) 2015-2016 othmar52 <othmar52@users.noreply.github.com>
 *
 * This file is part of sliMpd - a php based mpd web client
 *
 * This program is free software: you can redistribute it and/or modify it
 * under the terms of the GNU Affero General Public License as published by the
 * Free Software Foundation, either version 3 of the License, or (at your
 * option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.	See the GNU Affero General Public License
 * for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.	If not, see <http://www.gnu.org/licenses/>.
 */
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class Controller extends \Slimpd\BaseController {
	private $imageSizes = array(35, 50,100,300,1000);
	private $weightOrderBy;
	
	public function __construct($container) {
		$this->container = $container;
		#var_dump($this->__get('conf')['images']['weightening']); die;
		$weightConf = trimExplode("\n", $this->__get('conf')['images']['weightening'], TRUE);
		$this->weightOrderBy = "FIELD(pictureType, '" . join("','", $weightConf) . "'), sorting ASC, filesize DESC";
	}

	public function album(Request $request, Response $response, $args) {
		#$app->get('/image-'.$imagesize.'/album/:itemUid', function($itemUid) use ($app, $vars, $imagesize, $imageWeightOrderBy){
		$bitmap = $this->bitmapRepo->getInstanceByAttributes(
			[ 'albumUid' => $args['itemUid'] ],
			$this->weightOrderBy
		);
		if($bitmap !== NULL) {
			return $this->dump($bitmap, $args['imagesize'], $response);
		}
		$uri = $request->getUri()->withPath(
			$this->router->pathFor(
				'imagefallback',
				['type' => 'album', 'imagesize' => $args['imagesize'] ]
			)
		);
		return $response->withRedirect($uri, 403);
	}

	public function track(Request $request, Response $response, $args) {
		#$app->get('/image-'.$imagesize.'/album/:itemUid', function($itemUid) use ($app, $vars, $imagesize, $imageWeightOrderBy){
		$bitmap = $this->bitmapRepo->getInstanceByAttributes(
			[ 'trackUid' => $args['itemUid'] ],
			$this->weightOrderBy
		);
		if($bitmap !== NULL) {
			return $this->dump($bitmap, $args['imagesize'], $response);
		}
		$track = $this->trackRepo->getInstanceByAttributes(['uid' => $args['itemUid'] ]);
		$uri = $request->getUri()->withPath(
			$this->router->pathFor(
				'imagealbum',
				['imagesize' => $args['imagesize'], 'itemUid' => $track->getAlbumUid() ]
			)
		);
		return $response->withRedirect($uri, 403);
	}
	
	public function bitmap(Request $request, Response $response, $args) {
		#$app->get('/image-'.$imagesize.'/album/:itemUid', function($itemUid) use ($app, $vars, $imagesize, $imageWeightOrderBy){
		$bitmap = $this->bitmapRepo->getInstanceByAttributes(
			[ 'uid' => $args['itemUid'] ],
			$this->weightOrderBy
		);
		if($bitmap !== NULL) {
			return $this->dump($bitmap, $args['imagesize'], $response);
		}
		$track = $this->trackRepo->getInstanceByAttributes(['uid' => $args['itemUid'] ]);
		$uri = $request->getUri()->withPath(
			$this->router->pathFor(
				'imagealbum',
				['imagesize' => $args['imagesize'], 'itemUid' => $track->getAlbumUid() ]
			)
		);
		return $response->withRedirect($uri, 403);
	}

	public function path(Request $request, Response $response, $args) {
		$bitmap = new \Slimpd\Models\Bitmap();
		$bitmap->setRelPath($args['itemParams']);
		return $this->dump($bitmap, $args['imagesize'], $response);
	}

	public function fallback(Request $request, Response $response, $args) {
		if(in_array($args['imagesize'], $this->imageSizes) === FALSE) {
			$notFoundHandler = $this->__get('notFoundHandler');
			return $notFoundHandler($request->withAttribute('message', 'not found'), $response);
		}
		$playerMode = $this->view->getEnvironment()->getGlobals()['playerMode'];

		$args['color'] = $this->conf['images']['noimage'][$playerMode]['color'];
		$args['backgroundcolor'] = $this->conf['images']['noimage'][$playerMode]['backgroundcolor'];

		switch($args['type']) {
			case 'artist':    $template = 'svg/icon-artist.svg'; break;
			case 'genre':     $template = 'svg/icon-genre.svg'; break;
			case 'noresults': $template = 'svg/icon-noresults.svg'; break;
			case 'broken':    $template = 'svg/icon-broken-image.svg'; break;
			default:          $template = 'svg/icon-album.svg';
		}

		$this->view->render($response, $template, $args);
		return $response->withHeader('Content-Type', 'image/svg+xml');
	}

	public function dump(\Slimpd\Models\Bitmap $bitmap, $imageSize, &$response) {
		$imgDirecoryPrefix = ($bitmap->getTrackUid())
			? APP_ROOT
			: $this->conf['mpd']['musicdir'];
			
		$phpThumb = $this->getPhpThumb();	
		$phpThumb->setSourceFilename($imgDirecoryPrefix . $bitmap->getRelPath());
		$phpThumb->setParameter('config_output_format', 'jpg');
		
		switch($imageSize) {
			case 35:
			case 50:
			case 100:
			case 300:
			case 1000:
				$phpThumb->setParameter('w', $imageSize);
				break;
			default:
				$phpThumb->setParameter('w', 300);
		}
		$phpThumb->SetCacheFilename();
		
		try {
			// check if we already have a cached image
			if(is_file($phpThumb->cache_filename) === FALSE || is_readable($phpThumb->cache_filename) === FALSE) {
				$phpThumb->GenerateThumbnail();
				\phpthumb_functions::EnsureDirectoryExists(
					dirname($phpThumb->cache_filename),
					octdec($this->conf['config']['dirCreateMask'])
				);
				$phpThumb->RenderToFile($phpThumb->cache_filename);
				if(is_file($phpThumb->cache_filename) === FALSE) {
					$uri = $this->router->pathFor(
						'imagefallback',
						['imagesize' => $imageSize, 'type' => 'album']
					);
					return $response->withRedirect($uri, 403);
				}
			}
			return $response->write(
				new \GuzzleHttp\Stream\LazyOpenStream($phpThumb->cache_filename, 'r')
			)->withHeader('Content-Type', 'image/jpeg');
		} catch(\Exception $e) {
			$uri = $this->router->pathFor(
				'imagefallback',
				['imagesize' => $imageSize, 'type' => 'broken']
			);
			return $response->withRedirect($uri, 403);
		}
	}

	# TODO: read phpThumbSettings from config
	public function getPhpThumb() {
		$phpThumb = new \phpThumb();
		#$phpThumb->resetObject();
		$phpThumb->setParameter('config_disable_debug', FALSE);
		$phpThumb->setParameter('config_document_root', APP_ROOT);
		
		#$phpThumb->setParameter('config_high_security_enabled', TRUE);
		
		$phpThumb->setParameter('config_imagemagick_path', '/usr/bin/convert');
		$phpThumb->setParameter('config_allow_src_above_docroot', true);
		
		$phpThumb->setParameter('config_cache_directory', APP_ROOT .'localdata/cache');
		$phpThumb->setParameter('config_temp_directory',  APP_ROOT .'localdata/cache');
		$phpThumb->setParameter('config_cache_prefix', 'phpThumb_cache');
		#$phpThumb->setParameter('config_cache_force_passthru', FALSE);
		#$phpThumb->setParameter('config_cache_maxage', NULL);
		#$phpThumb->setParameter('config_cache_maxsize', NULL);
		#$phpThumb->setParameter('config_cache_maxfile', NULL);
		$phpThumb->setParameter('config_cache_directory_depth', 3);
		$phpThumb->setParameter('config_file_create_mask', octdec($this->conf['config']['fileCreateMask']));
		$phpThumb->setParameter('config_dir_create_mask', octdec($this->conf['config']['dirCreateMask']));
		return $phpThumb;
	}
}
