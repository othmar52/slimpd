<?php
namespace Slimpd;

class filebrowser {
	
	public $directory;
	public $base;
	public $subDirectories = array();
	public $files = array(
		'count' => 0,
		'music' => array(),
		'playlist' => array(),
		'info' => array(),
		'image' => array(),
		'other' => array(),
	);
	public $breadcrumb = array();
	
	public function getDirectoryContent($d) {
		$app = \Slim\Slim::getInstance();
		
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
		$d .= (substr($d,-1) !== DS) ? DS : '';
		
		$base = $app->config['mpd']['musicdir'];
		
		$d = ($d === $base) ? '' : $d;
		
		if(is_dir($base .$d) === FALSE){ //} || $this->checkAccess($d, $baseDirs) === FALSE) {
			\Slim\Slim::getInstance()->notFound();
		}
		
		
		// avoid path disclosure outside relevant directories
		$realpath = realpath($base.$d) . DS;
		if(stripos($realpath, $app->config['mpd']['musicdir']) !== 0
		&& stripos($realpath, $app->config['mpd']['alternative_musicdir']) !== 0 ) {
			var_dump($realpath); die();
			\Slim\Slim::getInstance()->notFound();
		}

		$this->directory = $d;
		
		$this->fetchBreadcrumb($d);
	
		//if($this->checkAccess($d) === FALSE) {
		//	die('sorry, you are not allowed to view this directory 8==========D');
		//}
		
		$files = scandir($base . $d);
		natcasesort($files);
		
		if( count($files) > 2 ) { /* The 2 accounts for . and .. */
			foreach( $files as $file ) {
				if( file_exists($base. $d . $file) && $file != '.' && $file != '..' && substr($file,0,1) !== '.' ) {
					if(is_dir($base . $d . $file) === TRUE) {
						$this->subDirectories[] = new _Directory($d . $file);
					} else {
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
		$parentDirectory->getDirectoryContent(dirname($d));
		
		
		// iterate over parentdirectories until we find the inputdirectory +1
		$found = FALSE;
		
		foreach($parentDirectory->subDirectories as $subDir) {
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
		$parentDirectory->getDirectoryContent(dirname($d));
		
		$prev = 0;
		
		foreach($parentDirectory->subDirectories as $subDir) {
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

	public function fetchBreadcrumb($relativePath) {
		$bread = trimExplode(DS, $relativePath, TRUE);
		$breadgrow = "";
		foreach($bread as $part) {
			$breadgrow .= DS . $part;
			$this->breadcrumb[] = new _Directory($breadgrow);
		}
	}
}
