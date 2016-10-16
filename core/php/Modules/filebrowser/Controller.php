<?php
namespace Slimpd\Modules\filebrowser;
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


	public function index(Request $request, Response $response, $args) {
		#var_dump($this->conf);die;
		
		$args['action'] = 'filebrowser';
		$fileBrowser = $this->filebrowser;
		$fileBrowser->getDirectoryContent($this->__get('conf')['mpd']['musicdir']);
		$args['breadcrumb'] = $fileBrowser->breadcrumb;
		$args['subDirectories'] = $fileBrowser->subDirectories;
		$args['files'] = $fileBrowser->files;
		$args['hotlinks'] = array();
		$args['hideQuicknav'] = 1;
		foreach(trimExplode("\n", $this->conf['filebrowser']['hotlinks'], TRUE) as $path){
			$args['hotlinks'][] =  \Slimpd\filebrowser::fetchBreadcrumb($path);
		}
		$this->view->render($response, 'surrounding.htm', $args);
		return $response;
	}
	
	public function dircontent(Request $request, Response $response, $args) {
		$args['action'] = 'filebrowser';
	
		$fileBrowser = $this->filebrowser;
		#var_dump($this->filebrowser); die;
		$fileBrowser->itemsPerPage = $this->conf['filebrowser']['max-items'];
		$fileBrowser->currentPage = intval($request->getParam('page'));
		$fileBrowser->currentPage = ($fileBrowser->currentPage === 0) ? 1 : $fileBrowser->currentPage;
		switch($request->getParam('filter')) {
			case 'dirs':
				$fileBrowser->filter = 'dirs';
				break;
			case 'files':
				$fileBrowser->filter = 'files';
				break;
			default :
				break;
		}
		
		switch($request->getParam('neighbour')) {
			case 'next':
				$fileBrowser->getNextDirectoryContent($args['itemParams']);
				break;
			case 'prev':
				$fileBrowser->getPreviousDirectoryContent($args['itemParams']);
				break;
			case 'up':
				$parentPath = dirname($args['itemParams']);
				if($parentPath === '.') {
					$uri = $request->getUri()->withPath(
						$this->router->pathFor('filebrowser')
					)->getPath() . getNoSurSuffix($this->view->getEnvironment()->getGlobals()['nosurrounding']);
					return $response->withRedirect($uri, 403);
				}
				$fileBrowser->getDirectoryContent($parentPath);
				break;
			default:
				$fileBrowser->getDirectoryContent($args['itemParams']);
				break;
		}
	
		$args['directory'] = $fileBrowser->directory;
		$args['breadcrumb'] = $fileBrowser->breadcrumb;
		$args['subDirectories'] = $fileBrowser->subDirectories;
		$args['files'] = $fileBrowser->files;
		$args['filter'] = $fileBrowser->filter;
		
		switch($fileBrowser->filter) {
			case 'dirs':
				$totalFilteredItems = $fileBrowser->subDirectories['total'];
				$args['showDirFilterBadge'] = FALSE;
				$args['showFileFilterBadge'] = FALSE;
				break;
			case 'files':
				$totalFilteredItems = $fileBrowser->files['total'];
				$args['showDirFilterBadge'] = FALSE;
				$args['showFileFilterBadge'] = FALSE;
				break;
			default :
				$totalFilteredItems = 0;
				$args['showDirFilterBadge'] = ($fileBrowser->subDirectories['count'] < $fileBrowser->subDirectories['total'])
					? TRUE
					: FALSE;
				
				$args['showFileFilterBadge'] = ($fileBrowser->files['count'] < $fileBrowser->files['total'])
					? TRUE
					: FALSE;
				break;
		}
		
		$args['paginator'] = new \JasonGrimes\Paginator(
			$totalFilteredItems,
			$fileBrowser->itemsPerPage,
			$fileBrowser->currentPage,
			$this->conf['config']['absRefPrefix'] . 'filebrowser/'.$fileBrowser->directory . '?filter=' . $fileBrowser->filter . '&page=(:num)'
		);
		$args['paginator']->setMaxPagesToShow(paginatorPages($fileBrowser->currentPage));
		$this->view->render($response, 'surrounding.htm', $args);
		return $response;
		
	
	}

	public function track(Request $request, Response $response, $args) {
		#$app->get('/image-'.$imagesize.'/album/:itemUid', function($itemUid) use ($app, $vars, $imagesize, $imageWeightOrderBy){
		$bitmap = \Slimpd\Models\Bitmap::getInstanceByAttributes(
			$this->__get('db'),
			[ 'trackUid' => $args['itemUid'] ],
			$this->weightOrderBy
		);
		if($bitmap !== NULL) {
			return $this->dump($bitmap, $args['imagesize'], $response);
		}
		$track = \Slimpd\Models\Track::getInstanceByAttributes($this->__get('db'), ['uid' => $args['itemUid'] ]);
		$uri = $request->getUri()->withPath(
			$this->router->pathFor(
				'imagealbum',
				['imagesize' => $args['imagesize'], 'itemUid' => $track->getAlbumUid() ]
			)
		);
		return $response->withRedirect($uri, 403);
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
			: $this->__get('conf')['mpd']['musicdir'];
			
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
					octdec($app->config['config']['dirCreateMask'])
				);
				$phpThumb->RenderToFile($phpThumb->cache_filename);
				if(is_file($phpThumb->cache_filename) === FALSE) {
					// something went wrong
					$app->response->redirect($app->urlFor('imagefallback-'.$preConf, ['type' => 'album']));
					return;
				}
			}
			return $response->write(
				new \GuzzleHttp\Stream\LazyOpenStream($phpThumb->cache_filename, 'r')
			)->withHeader('Content-Type', 'image/jpeg');
		} catch(\Exception $e) {
			$app->response->redirect($app->config['root'] . 'imagefallback-'.$preConf.'/broken');
			return;
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
		$phpThumb->setParameter('config_file_create_mask', octdec($this->__get('conf')['config']['fileCreateMask']));
		$phpThumb->setParameter('config_dir_create_mask', octdec($this->__get('conf')['config']['dirCreateMask']));
		return $phpThumb;
	}
}
