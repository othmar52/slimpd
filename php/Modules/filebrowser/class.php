<?php
namespace Slimpd;

class filebrowser {
	
	public $directory;
	public $base;
	public $subDirectories = array(
		"total" => 0,
		"count" => 0,
		"dirs" => array()
	);
	public $files = array(
		"total" => 0,
		"count" => 0,
		"music" => array(),
		"playlist" => array(),
		"info" => array(),
		"image" => array(),
		"other" => array(),
	);
	public $breadcrumb = array();
	
	public $currentPage = 1;
	public $itemsPerPage = 20;
	public $filter = "";
	
	
	public function getDirectoryContent($path, $ignoreLimit = FALSE, $systemdir = FALSE) {
		$path = $this->checkDirectoryAccess($path, $systemdir);

		if($path === FALSE) {
			return;
		}
		$dir = $path["dir"];
		$base = $path["base"];

		$this->breadcrumb = self::fetchBreadcrumb($dir);
		$this->directory = $dir;

		$files = scandir($base . $dir);
		natcasesort($files);
		
		// determine which portion is requestet
		$minIndex = (($this->currentPage-1) * $this->itemsPerPage);
		$minIndex = ($minIndex === 0) ? 1 : $minIndex+1;
		$maxIndex = $minIndex +  $this->itemsPerPage -1;

		/* The 2 accounts for . and .. */
		if( count($files) < 3 ) {
			return;
		}

		// create helper array only once for performance reasons
		$extTypes = $this->getExtMapping();
		foreach( $files as $file ) {
			// skip "." and ".." and hidden files
			if(substr($file,0,1) === ".") {
				continue;
			}
			if( file_exists($base. $dir . $file) === FALSE) {
				continue;
			}

			// directories
			if(is_dir($base . $dir . $file) === TRUE) {
				$this->handleDirectory($dir.$file, $minIndex, $maxIndex, $ignoreLimit);
				continue;
			}

			// files
			$this->handleFile($dir.$file, $minIndex, $maxIndex, $ignoreLimit, $extTypes);
		}
		return;
	}

	private function handleFile($relPath, $minIndex, $maxIndex, $ignoreLimit, $extTypes) {
		$this->files["total"]++;
		if($this->filter === "dirs" && $ignoreLimit === FALSE) {
			return;
		}
		if($this->files["total"] < $minIndex && $ignoreLimit === FALSE) {
			return;
		}
		if($this->files["total"] > $maxIndex && $ignoreLimit === FALSE) {
			return;
		}
		$fileInstance = new \Slimpd\Models\File($relPath);
		$group = (isset($extTypes[$fileInstance->getExt()]) === TRUE)
			? $extTypes[$fileInstance->getExt()]
			: "other";
		$this->files[$group][] = $fileInstance;
		$this->files["count"]++;
	}
	private function handleDirectory($relPath, $minIndex, $maxIndex, $ignoreLimit) {
		$this->subDirectories["total"]++;
		if($this->filter === "files" && $ignoreLimit === FALSE) {
			return;
		}
		if($this->subDirectories["total"] < $minIndex && $ignoreLimit === FALSE) {
			return;
		}
		if($this->subDirectories["total"] > $maxIndex && $ignoreLimit === FALSE) {
			return;
		}
		$this->subDirectories["dirs"][] = new \Slimpd\Models\Directory($relPath);
		$this->subDirectories["count"]++;
	}

	private function getExtMapping() {
		$app = \Slim\Slim::getInstance();
		$extTypes = array();
		foreach($app->config["musicfiles"]["ext"] as $ext) {
			$extTypes[$ext] = "music";
		}
		foreach($app->config["playlists"]["ext"] as $ext) {
			$extTypes[$ext] = "playlist";
		}
		foreach($app->config["infofiles"]["ext"] as $ext) {
			$extTypes[$ext] = "info";
		}
		foreach($app->config["images"]["ext"] as $ext) {
			$extTypes[$ext] = "image";
		}
		return $extTypes;
	}

	private function checkDirectoryAccess($path, $systemdir) {
		$app = \Slim\Slim::getInstance();
		if($app->config["mpd"]["musicdir"] === "") {
			$app->flashNow("error", $app->ll->str("error.mpd.conf.musicdir"));
			return FALSE;
		}

		// append trailing slash if missing
		$path = rtrim($path, DS) . DS;

		$base = $app->config["mpd"]["musicdir"];
		$path = ($path === $base) ? "" : $path;
		$realpath = realpath($base.$path) . DS;
		$return = ["base" => $base, "dir" => $path];

		// avoid path disclosure outside relevant directories
		if(!$realpath) {
			$app->flashNow("error", $app->ll->str("filebrowser.realpathempty", [$base.$path]));
			return FALSE;
		}

		if($systemdir === TRUE && in_array($path, ["cache/", "embedded/", "peakfiles/"]) === TRUE) {
			$return['base'] = APP_ROOT;
			$realpath = realpath(APP_ROOT.$path) . DS;
		}

		if(is_dir($realpath) === FALSE){ //} || $this->checkAccess($path, $baseDirs) === FALSE) {
			$app->flashNow("error", $app->ll->str("filebrowser.invaliddir", [$realpath]));
			return FALSE;
		}

		// check filesystem permission
		if(is_readable($realpath) === FALSE) {
			$app->flashNow("error", $app->ll->str("filebrowser.dirpermission", [$path]));
			return FALSE;
		}

		if($app->config["filebrowser"]["restrict-to-musicdir"] !== "1" || $systemdir === TRUE) {
			return $return;
		}

		// and again we do the same musicdir/alternative_musicdir check...
		// TODO: move this to a proper place
		if(stripos($realpath, $app->config["mpd"]["musicdir"]) !== 0
		&& stripos($realpath, $app->config["mpd"]["alternative_musicdir"]) !== 0 ) {
			$app->flashNow("error", $app->ll->str("filebrowser.outsiderealpath", [$realpath, $app->config["mpd"]["musicdir"]]));
			return FALSE;
		}

		return $return;
	}

	/**
	 * get content of the next silblings directory
	 * @param string $path: directorypath
	 * @return object
	 */
	public function getNextDirectoryContent($path) {
		$app = \Slim\Slim::getInstance();
		
		// make sure we have directory separator as last char
		$path .= (substr($path,-1) !== DS) ? DS : "";
		
		// fetch content of the parent directory
		$parentDirectory = new \Slimpd\filebrowser();
		$parentDirectory->getDirectoryContent(dirname($path), TRUE);
		if($parentDirectory->directory === "./") {
			$parentDirectory = new \Slimpd\filebrowser();
			$parentDirectory->getDirectoryContent($app->config["mpd"]["musicdir"], TRUE);
		}
		
		
		// iterate over parentdirectories until we find the inputdirectory +1
		$found = FALSE;
		
		foreach($parentDirectory->subDirectories["dirs"] as $subDir) {
			if($found === TRUE) {
				return $this->getDirectoryContent($subDir->getRelPath());
			}
			if($subDir->getRelPath()."/" === $path) {
				$found = TRUE;
			}
		}
		$app->flashNow("error", $app->ll->str("filebrowser.nonextdir"));
		return $this->getDirectoryContent($path);
	}

	/**
	 * get content of the previous silblings directory
	 * @param string $path: directorypath
	 * @return object
	 */
	 public function getPreviousDirectoryContent($path) {
		$app = \Slim\Slim::getInstance();
		$path .= (substr($path,-1) !== DS) ? DS : "";
		$parentDirectory = new \Slimpd\filebrowser();
		$parentDirectory->getDirectoryContent(dirname($path), TRUE);
		if($parentDirectory->directory === "./") {
			$parentDirectory = new \Slimpd\filebrowser();
			$parentDirectory->getDirectoryContent($app->config["mpd"]["musicdir"], TRUE);
		}
		
		$prev = 0;
		
		foreach($parentDirectory->subDirectories["dirs"] as $subDir) {
			if($subDir->getRelPath()."/" === $path) {
				if($prev === 0) {
					$app->flashNow("error", $app->ll->str("filebrowser.noprevdir"));
					return $this->getDirectoryContent($path);
				}
				return $this->getDirectoryContent($prev);
			}
			$prev = $subDir->getRelPath();
		}
		$app->flashNow("error", $app->ll->str("filebrowser.noprevdir"));
		return $this->getDirectoryContent($path);
	}

	public static function fetchBreadcrumb($relPath) {
		$bread = trimExplode(DS, $relPath, TRUE);
		$breadgrow = "";
		$items = array();
		foreach($bread as $part) {
			$breadgrow .= DS . $part;
			$items[] = new \Slimpd\Models\Directory($breadgrow);
		}
		return $items;
	}
}
