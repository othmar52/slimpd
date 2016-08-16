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
				$this->subDirectories["total"]++;
				if($this->filter === "files" && $ignoreLimit === FALSE) {
					continue;
				}
				if($this->subDirectories["total"] < $minIndex && $ignoreLimit === FALSE) {
					continue;
				}
				if($this->subDirectories["total"] > $maxIndex && $ignoreLimit === FALSE) {
					continue;
				}
				$this->subDirectories["dirs"][] = new _Directory($dir . $file);
				$this->subDirectories["count"]++;
				continue;
			}

			// files
			$this->files["total"]++;
			if($this->filter === "dirs" && $ignoreLimit === FALSE) {
				continue;
			}
			if($this->files["total"] < $minIndex && $ignoreLimit === FALSE) {
				continue;
			}
			if($this->files["total"] > $maxIndex && $ignoreLimit === FALSE) {
				continue;
			}
			$f = new File($dir . $file);
			$group = (isset($extTypes[$f->ext]) === TRUE)
				? $extTypes[$f->ext]
				: "other";
			$this->files[$group][] = new File($dir . $file);
			$this->files["count"]++;
		}
		return;
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

		if($systemdir === TRUE) {
			$base = APP_ROOT;
			switch($path) {
				case "cache/":
				case "embedded/":
				case "peakfiles/":
					break;
				default:
					$app->flashNow("error", $app->ll->str("filebrowser.invaliddir", [$base .$path]));
					return FALSE;
			}
			return [
				"base" => APP_ROOT,
				"dir" => $path
			];
		}

		$base = $app->config["mpd"]["musicdir"];
		$path = ($path === $base) ? "" : $path;

		if(is_dir($base .$path) === FALSE){ //} || $this->checkAccess($path, $baseDirs) === FALSE) {
			$app->flashNow("error", $app->ll->str("filebrowser.invaliddir", [$base .$path]));
			return FALSE;
		}

		// check filesystem permission
		if(is_readable($base . $path) === FALSE) {
			$app->flashNow("error", $app->ll->str("filebrowser.dirpermission", [$path]));
			return FALSE;
		}

		if($app->config["filebrowser"]["restrict-to-musicdir"] !== "1") {
			return [
				"base" => $base,
				"dir" => $path
			];
		}

		// avoid path disclosure outside relevant directories
		$realpath = realpath($base.$path) . DS;

		if(!$realpath) {
			$app->flashNow("error", $app->ll->str("filebrowser.realpathempty", [$base.$path]));
			return FALSE;
		}

		// and again we do the same musicdir/alternative_musicdir check...
		// TODO: move this to a proper place
		if(stripos($realpath, $app->config["mpd"]["musicdir"]) !== 0
		&& stripos($realpath, $app->config["mpd"]["alternative_musicdir"]) !== 0 ) {
			$app->flashNow("error", $app->ll->str("filebrowser.outsiderealpath", [$base .$path, $app->config["mpd"]["musicdir"]]));
			return FALSE;
		}

		return [
			"base" => $base,
			"dir" => $path
		];
	}

	/**
	 * get content of the next silblings directory
	 * @param string $d: directorypath
	 * @return object
	 */
	public function getNextDirectoryContent($d) {
		$app = \Slim\Slim::getInstance();
		
		// make sure we have directory separator as last char
		$d .= (substr($d,-1) !== DS) ? DS : "";
		
		// fetch content of the parent directory
		$parentDirectory = new \Slimpd\filebrowser();
		$parentDirectory->getDirectoryContent(dirname($d), TRUE);
		if($parentDirectory->directory === "./") {
			$parentDirectory = new \Slimpd\filebrowser();
			$parentDirectory->getDirectoryContent($app->config["mpd"]["musicdir"], TRUE);
		}
		
		
		// iterate over parentdirectories until we find the inputdirectory +1
		$found = FALSE;
		
		foreach($parentDirectory->subDirectories["dirs"] as $subDir) {
			if($found === TRUE) {
				return $this->getDirectoryContent($subDir->fullpath);
			}
			if($subDir->fullpath."/" == $d) {
				$found = TRUE;
			}
		}
		$app->flashNow("error", $app->ll->str("filebrowser.nonextdir"));
		return $this->getDirectoryContent($d);
	}

	/**
	 * get content of the previous silblings directory
	 * @param string $d: directorypath
	 * @return object
	 */
	 public function getPreviousDirectoryContent($d) {
		$app = \Slim\Slim::getInstance();
		$d .= (substr($d,-1) !== DS) ? DS : "";
		$parentDirectory = new \Slimpd\filebrowser();
		$parentDirectory->getDirectoryContent(dirname($d), TRUE);
		if($parentDirectory->directory === "./") {
			$parentDirectory = new \Slimpd\filebrowser();
			$parentDirectory->getDirectoryContent($app->config["mpd"]["musicdir"], TRUE);
		}
		
		$prev = 0;
		
		foreach($parentDirectory->subDirectories["dirs"] as $subDir) {
			if($subDir->fullpath."/" === $d) {
				if($prev === 0) {
					$app->flashNow("error", $app->ll->str("filebrowser.noprevdir"));
					return $this->getDirectoryContent($d);
				}
				return $this->getDirectoryContent($prev);
			}
			$prev = $subDir->fullpath;
		}
		$app->flashNow("error", $app->ll->str("filebrowser.noprevdir"));
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
