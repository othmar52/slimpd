<?php
namespace Slimpd\Modules\importer;

class FilesystemReader extends \Slimpd\Modules\importer\AbstractImporter {
	
	protected $artworkDirNames = array();
	protected $dirImgCache = array();

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
			$relativePath = substr($imgPath, strlen(\Slim\Slim::getInstance()->config['mpd']['musicdir']));
			// write found files to cache array
			$this->dirImgCache[$dirHash][$relativePath] = $relativePath;
			// add to result set
			$this->foundImgPaths[$relativePath] = $relativePath;
		}
	}

	public function getFilesystemImagesForMusicFile($musicFilePath) {
		// reset result
		$this->foundImgPaths = [];
		
		// makes sure we have pluralized common directory names
		$this->pluralizeArtworkDirNames();
		
		$directory = dirname($musicFilePath) . DS;
		$directoryHash = getFilePathHash($directory);

		$app = \Slim\Slim::getInstance();

		if($app->config['images']['look_current_directory']) {
			$this->getCachedOrScan($directory);
		}

		if($app->config['images']['look_cover_directory']) {
			
			// search for specific named subdirectories
			if(is_dir($app->config['mpd']['musicdir'] . $directory) === TRUE) {
				$handle=opendir($app->config['mpd']['musicdir'] . $directory);
				while ($dirname = readdir ($handle)) {
					if(is_dir($app->config['mpd']['musicdir'] . $directory . $dirname)) {
						if(in_array(az09($dirname), $this->artworkDirNames)) {
							$this->getCachedOrScan($directory . $dirname);
						}
					}
				}
				closedir($handle);
			}
		}

		if($app->config['images']['look_silbling_directory']) {
			$parentDir = $app->config['mpd']['musicdir'] . dirname($directory) . DS;
			// search for specific named silbling directories
			if(is_dir($parentDir) === TRUE) {
				$handle=opendir($parentDir);
				while ($dirname = readdir ($handle)) {
					if(is_dir($parentDir . $dirname)) {
						if(in_array(az09($dirname), $this->artworkDirNames)) {
							$this->getCachedOrScan($parentDir . $dirname);
						}
					}
				}
				closedir($handle);
			}
		}

		if($app->config['images']['look_parent_directory'] && count($this->foundImgPaths) === 0) {
			$parentDir = dirname($directory) . DS;
			$this->getCachedOrScan($parentDir);
		}
		return $this->foundImgPaths;
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
		if( is_dir($dir) == FALSE) {
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
		$handle=opendir ($dir);
		while ($file = readdir ($handle)) {
			$foundExt = strtolower(preg_replace("/^.*\./", "", $file));
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
	