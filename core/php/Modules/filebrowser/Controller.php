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

	public function widgetDirectory(Request $request, Response $response, $args) {
		$fileBrowser = $this->filebrowser;
		$fileBrowser->getDirectoryContent($args['itemParams']);
		$args['directory'] = $fileBrowser->directory;
		$args['breadcrumb'] = $fileBrowser->breadcrumb;
		$args['subDirectories'] = $fileBrowser->subDirectories;
		$args['files'] = $fileBrowser->files;
		
		// try to fetch album entry for this directory
		$args['album'] = \Slimpd\Models\Album::getInstanceByAttributes(
			$this->db,
			array('relPathHash' => getFilePathHash($fileBrowser->directory))
		);
	
		$this->view->render($response, 'modules/widget-directory.htm', $args);
		return $response;
	}
	
	public function deliverAction(Request $request, Response $response, $args) {
		#var_dump($args['itemParams']); die;
		if(is_numeric($args['itemParams'])) {
			$track = $this->trackRepo->getInstanceByAttributes(array('uid' => (int)$args['itemParams']));
			$path = ($track === NULL) ? '' : $track->getRelPath();
		}
		$this->filesystemUtility->deliver($this->filesystemUtility->trimAltMusicDirPrefix($path));
		
		
		$fileBrowser = $this->filebrowser;
		$fileBrowser->getDirectoryContent($args['itemParams']);
		$args['directory'] = $fileBrowser->directory;
		$args['breadcrumb'] = $fileBrowser->breadcrumb;
		$args['subDirectories'] = $fileBrowser->subDirectories;
		$args['files'] = $fileBrowser->files;
		
		// try to fetch album entry for this directory
		$args['album'] = $this->albumRepo->getInstanceByAttributes(
			array('relPathHash' => $this->filesystemUtility->getFilePathHash($fileBrowser->directory))
		);
	
		$this->view->render($response, 'modules/widget-directory.htm', $args);
		return $response;
	}
}
