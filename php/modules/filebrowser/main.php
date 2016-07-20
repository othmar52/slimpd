<?php
namespace Slimpd;

class filebrowser {
	
	public $directory;
	public $base;
	public $subDirectories = array(
		'total' => 0,
		'count' => 0,
		'dirs' => array()
	);
	public $files = array(
		'total' => 0,
		'count' => 0,
		'music' => array(),
		'playlist' => array(),
		'info' => array(),
		'image' => array(),
		'other' => array(),
	);
	public $breadcrumb = array();
	
	public $currentPage = 1;
	public $itemsPerPage = 20;
	public $filter = '';
	
	
	public function getDirectoryContent($d, $ignoreLimit = FALSE) {
		$app = \Slim\Slim::getInstance();
		if($app->config['mpd']['musicdir'] === '') {
			$app->flashNow('error', $app->ll->str('error.mpd.conf.musicdir'));
			return;
		}
		
		// create helper array only once for performance reasons
		$extTypes = array();
		foreach($app->config['musicfiles']['ext'] as $ext) {
			$extTypes[$ext] = 'music';
		}
		foreach($app->config['playlists']['ext'] as $ext) {
			$extTypes[$ext] = 'playlist';
		}
		foreach($app->config['infofiles']['ext'] as $ext) {
			$extTypes[$ext] = 'info';
		}
		foreach($app->config['images']['ext'] as $ext) {
			$extTypes[$ext] = 'image';
		}


		// append trailing slash if missing
		$d = rtrim($d, DS) . DS;
		
		$base = $app->config['mpd']['musicdir'];
		
		$d = ($d === $base) ? '' : $d;
		
		if(is_dir($base .$d) === FALSE){ //} || $this->checkAccess($d, $baseDirs) === FALSE) {
			$app->flashNow('error', $app->ll->str('filebrowser.invaliddir.', [$base .$d]));
			return;
		}

		if($app->config['filebrowser']['restrict-to-musicdir'] == '1') {
			// avoid path disclosure outside relevant directories
			$realpath = realpath($base.$d) . DS;
			
			if(!$realpath) {
				$app->flashNow('error', $app->ll->str('filebrowser.realpathempty', [$base.$d]));
				return;
			}
			
			if(stripos($realpath, $app->config['mpd']['musicdir']) !== 0
			&& stripos($realpath, $app->config['mpd']['alternative_musicdir']) !== 0 ) {
				$app->flashNow('error', $app->ll->str('filebrowser.outsiderealpath', [$base .$d, $app->config['mpd']['musicdir']]));
				return;
			}
		}

		$this->directory = $d;
		
		$this->breadcrumb = self::fetchBreadcrumb($d);
	
		//if($this->checkAccess($d) === FALSE) {
		//	die('sorry, you are not allowed to view this directory 8==========D');
		//}
		
		// check filesystem permissions
		if(is_readable($base . $d) === FALSE) {
			$app->flashNow('error', $app->ll->str('filebrowser.dirpermission', [$d]));
			return;
		}

		$files = scandir($base . $d);
		natcasesort($files);
		
		$minIndex = (($this->currentPage-1) * $this->itemsPerPage);
		$minIndex = ($minIndex === 0) ? 1 : $minIndex+1;
		$maxIndex = $minIndex +  $this->itemsPerPage -1;
		#echo $minIndex . "<br>" . $maxIndex; die();
		if( count($files) > 2 ) { /* The 2 accounts for . and .. */
			foreach( $files as $file ) {
				if( file_exists($base. $d . $file) && $file != '.' && $file != '..' && substr($file,0,1) !== '.' ) {
					if(is_dir($base . $d . $file) === TRUE) {
						$this->subDirectories['total']++;
						if($this->filter === 'files' && $ignoreLimit === FALSE) {
							continue;
						}
						if($this->subDirectories['total'] < $minIndex && $ignoreLimit === FALSE) {
							continue;
						}
						if($this->subDirectories['total'] > $maxIndex && $ignoreLimit === FALSE) {
							continue;
						}
						$this->subDirectories['dirs'][] = new _Directory($d . $file);
						$this->subDirectories['count']++;
					} else {
						$this->files['total']++;
						if($this->filter === 'dirs' && $ignoreLimit === FALSE) {
							continue;
						}
						if($this->files['total'] < $minIndex && $ignoreLimit === FALSE) {
							continue;
						}
						if($this->files['total'] > $maxIndex && $ignoreLimit === FALSE) {
							continue;
						}
						$f = new File($d . $file);
						$group = (isset($extTypes[$f->ext]) === TRUE)
							? $extTypes[$f->ext]
							: 'other';
						$this->files[$group][] = new File($d . $file);
						$this->files['count']++;
					}
				}
			}
		}
		return ;
	}

	/**
	 * get content of the next silblings directory
	 * @param string $d: directorypath
	 * @return object
	 */
	public function getNextDirectoryContent($d) {
		$app = \Slim\Slim::getInstance();
		
		// make sure we have directory separator as last char
		$d .= (substr($d,-1) !== DS) ? DS : '';
		
		// fetch content of the parent directory
		$parentDirectory = new \Slimpd\filebrowser();
		$parentDirectory->getDirectoryContent(dirname($d), TRUE);
		if($parentDirectory->directory === './') {
			$parentDirectory = new \Slimpd\filebrowser();
			$parentDirectory->getDirectoryContent($app->config['mpd']['musicdir'], TRUE);
		}
		
		
		// iterate over parentdirectories until we find the inputdirectory +1
		$found = FALSE;
		
		foreach($parentDirectory->subDirectories['dirs'] as $subDir) {
			if($found === TRUE) {
				return $this->getDirectoryContent($subDir->fullpath);
			}
			if($subDir->fullpath.'/' == $d) {
				$found = TRUE;
			}
		}
		$app->flashNow('error', $app->ll->str('filebrowser.nonextdir'));
		return $this->getDirectoryContent($d);
	}

	/**
	 * get content of the previous silblings directory
	 * @param string $d: directorypath
	 * @return object
	 */
	 public function getPreviousDirectoryContent($d) {
		$app = \Slim\Slim::getInstance();
		$d .= (substr($d,-1) !== DS) ? DS : '';
		$parentDirectory = new \Slimpd\filebrowser();
		$parentDirectory->getDirectoryContent(dirname($d), TRUE);
		if($parentDirectory->directory === './') {
			$parentDirectory = new \Slimpd\filebrowser();
			$parentDirectory->getDirectoryContent($app->config['mpd']['musicdir'], TRUE);
		}
		
		$prev = 0;
		
		foreach($parentDirectory->subDirectories['dirs'] as $subDir) {
			if($subDir->fullpath.'/' === $d) {
				if($prev === 0) {
					$app->flashNow('error', $app->ll->str('filebrowser.noprevdir'));
					return $this->getDirectoryContent($d);
				}
				return $this->getDirectoryContent($prev);
			}
			$prev = $subDir->fullpath;
		}
		$app->flashNow('error', $app->ll->str('filebrowser.noprevdir'));
		return $this->getDirectoryContent($d);
	}

	public static function fetchBreadcrumb($relativePath) {
		$bread = trimExplode(DS, $relativePath, TRUE);
		$breadgrow = "";
		$items = array();
		foreach($bread as $part) {
			$breadgrow .= DS . $part;
			$items[] = new _Directory($breadgrow);
		}
		return $items;
	}
}
