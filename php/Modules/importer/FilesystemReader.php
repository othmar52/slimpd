<?php
namespace Slimpd\Modules\importer;

class FilesystemReader extends \Slimpd\Modules\importer\AbstractImporter {
	
	// a whitelist with common directory names like "cover", "artwork" 
	protected $artworkDirNames = array();

	// a list with real existing directories which maches whitelist entries 
	protected $artworkDirCache = array();
	
	// a list with filepaths of already scanned directories
	protected $dirImgCache = array();

	// result
	public $foundImgPaths = array();
	
	private function getCachedOrScan($dirPath) {
		$dirHash = getFilePathHash($dirPath);
		// make sure that a single directory will not be scanned twice
		// so check if we have scanned the directory already
		if(array_key_exists($dirHash, $this->dirImgCache) === TRUE) {
			// add cached image paths to result set
			foreach($this->dirImgCache[$dirHash] as $imgPath) {
				$this->foundImgPaths[$imgPath] = $imgPath;
			}
			return;
		}

		// read from filesystem
		$scanned = $this->getDirectoryFiles(
			\Slim\Slim::getInstance()->config['mpd']['musicdir'] . $dirPath
		);

		// create cache entry cache array
		$this->dirImgCache[$dirHash] = [];


		foreach($scanned as $imgPath) {
			// remove prefixed music directory
			$relPath = substr($imgPath, strlen(\Slim\Slim::getInstance()->config['mpd']['musicdir']));
			// write found files to cache array
			$this->dirImgCache[$dirHash][$relPath] = $relPath;
			// add to result set
			$this->foundImgPaths[$relPath] = $relPath;
		}
	}

	public function getFilesystemImagesForMusicFile($musicFilePath) {
		// reset result
		$this->foundImgPaths = [];
		
		// makes sure we have pluralized common directory names
		$this->pluralizeArtworkDirNames();
		
		$directory = dirname($musicFilePath) . DS;

		$app = \Slim\Slim::getInstance();

		if($app->config['images']['look_current_directory']) {
			$this->getCachedOrScan($directory);
		}

		if($app->config['images']['look_cover_directory']) {
			// search for specific named subdirectories
			foreach($this->lookupSpecialDirNames($directory) as $specialDir) {
				$this->getCachedOrScan($directory . $specialDir);
			}
		}

		if($app->config['images']['look_silbling_directory']) {
			$parentDir = dirname($directory) . DS;
			// search for specific named silbling directories
			foreach($this->lookupSpecialDirNames($parentDir) as $specialDir) {
				$this->getCachedOrScan($parentDir . $specialDir);
			}
		}

		if($app->config['images']['look_parent_directory'] && count($this->foundImgPaths) === 0) {
			$parentDir = dirname($directory) . DS;
			$this->getCachedOrScan($parentDir);
		}
		return $this->foundImgPaths;
	}

	private function lookupSpecialDirNames($parentPath) {
		$app = \Slim\Slim::getInstance();
		if(is_dir($app->config['mpd']['musicdir'] . $parentPath) === FALSE) {
			return;
		}
		$dirHash = getFilePathHash($parentPath);
		// make sure that a single directory will not be scanned twice
		// so check if we have scanned the directory already for special names directories
		if(array_key_exists($dirHash, $this->artworkDirCache) === TRUE) {
			return $this->artworkDirCache[$dirHash];
		}

		// create new cache entry
		$this->artworkDirCache[$dirHash] = [];
		
		// scan filesystem
		$handle = opendir($app->config['mpd']['musicdir'] . $parentPath);
		while($dirname = readdir ($handle)) {
			// skip files
			if(is_dir($app->config['mpd']['musicdir'] . $parentPath . $dirname) === FALSE) {
				continue;
			}
			// check if directory name matches configured values 
			if(in_array(az09($dirname), $this->artworkDirNames) === FALSE) {
				continue;
			}
			
			// add matches to cache result set
			$this->artworkDirCache[$dirHash] = [$dirname];
		}
		closedir($handle);
		return $this->artworkDirCache[$dirHash];
	} 

	private function pluralizeArtworkDirNames() {
		if(count($this->artworkDirNames)>0) {
			// we already have pluralized those strings
			return;
		}
		$app = \Slim\Slim::getInstance();
		foreach($app->config['images']['common_artwork_dir_names'] as $dirname) {
			$this->artworkDirNames[] = az09($dirname);
			$this->artworkDirNames[] = az09($dirname) . 's';
		}
	}
	
	
	/**
	 * getDirectoryFiles() read all files of given directory without recursion
	 * @param $dir (string): Directory to search
	 * @param $ext (string): fileextension or name of configured fileextension group
	 * @param $addFilePath (boolean): prefix every matching file with input-dir in output array-entries
	 * @param $checkMimeType (boolean): perform a mimetype check and skip file if mimetype dous not match configuration
	 * 
	 * @return (array) : filename-strings
	 */
	public function getDirectoryFiles($dir, $ext="images", $addFilePath = TRUE, $checkMimeType = TRUE) {
		$foundFiles = array();
		if(is_dir($dir) == FALSE) {
		  return $foundFiles;
		}
		
		$app = \Slim\Slim::getInstance();
		$validExtensions = array(strtolower($ext));
		if(array_key_exists($ext, $app->config["mimetypes"])) {
			if(is_array($app->config["mimetypes"][$ext]) === TRUE) {
				$validExtensions = array_keys($app->config["mimetypes"][$ext]);
			}
			if(is_string($app->config["mimetypes"][$ext]) === TRUE) {
				$checkMimeType = FALSE;
			}
		}
		// make sure we have a trailing slash
		$dir = rtrim($dir, DS) . DS;
		
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$handle = opendir ($dir);
		while ($file = readdir ($handle)) {
			$foundExt = getFileExt($file);
			if(is_dir($dir . $file) === TRUE) {
				continue;
			}
			if(in_array($foundExt, $validExtensions) === FALSE) {
				continue;
			}
			if($checkMimeType == TRUE && array_key_exists($ext, $app->config["mimetypes"])) {
				if(finfo_file($finfo, $dir.$file) !== $app->config["mimetypes"][$ext][$foundExt]) {
					continue;
				}
			}
			$foundFiles[] = (($addFilePath == TRUE)? $dir : "") . $file;
		}
	
		finfo_close($finfo);
		closedir($handle);
		return $foundFiles;
	}
}
	