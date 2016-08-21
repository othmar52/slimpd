<?php
namespace Slimpd\Modules\importer;

class FilesystemReader extends \Slimpd\Modules\importer\AbstractImporter {
	
	protected $artworkDirNames = array();
	protected $directoryImages = array();
	
	public function getFilesystemImagesForMusicFile($musicFilePath) {
		$directory = dirname($musicFilePath) . DS;
		$directoryHash = getFilePathHash($directory);

		$foundAlbumImages = array();

		$app = \Slim\Slim::getInstance();

		if($app->config['images']['look_current_directory']) {
			// make sure that a single directory will not be scanned twice
			// so check if have scanned the directory already
			$images = (array_key_exists($directoryHash, $this->directoryImages) === TRUE)
				? $this->directoryImages[ $directoryHash ]
				: $this->getDirectoryFiles($app->config['mpd']['musicdir'] . $directory);

			$this->directoryImages[ $directoryHash ] = $images;
			if(count($images) > 0) {
				$foundAlbumImages = array_merge($foundAlbumImages, $images);
			}
		}

		if($app->config['images']['look_cover_directory']) {
			$this->pluralizeartworkDirNames();
			// search for specific named subdirectories
			if(is_dir($app->config['mpd']['musicdir'] . $directory) === TRUE) {
				$handle=opendir($app->config['mpd']['musicdir'] . $directory);
				while ($dirname = readdir ($handle)) {
					if(is_dir($app->config['mpd']['musicdir'] . $directory . $dirname)) {
						if(in_array(az09($dirname), $this->artworkDirNames)) {
							$foundAlbumImages = array_merge(
								$foundAlbumImages,
								$this->getDirectoryFiles($app->config['mpd']['musicdir'] . $directory . $dirname)
							);
						}
					}
				}
				closedir($handle);
			}
		}

		if($app->config['images']['look_silbling_directory']) {
			$this->pluralizeartworkDirNames();
			$parentDir = $app->config['mpd']['musicdir'] . dirname($directory) . DS;
			// search for specific named subdirectories
			if(is_dir($parentDir) === TRUE) {
				$handle=opendir($parentDir);
				while ($dirname = readdir ($handle)) {
					if(is_dir($parentDir . $dirname)) {
						if(in_array(az09($dirname), $this->artworkDirNames)) {
							$foundAlbumImages = array_merge(
								$foundAlbumImages,
								$this->getDirectoryFiles($parentDir . $dirname)
							);
						}
					}
				}
				closedir($handle);
			}
		}

		if($app->config['images']['look_parent_directory'] && count($foundAlbumImages) === 0) {
			$parentDir = dirname($directory) . DS;
			$parentDirHash = getFilePathHash($parentDir);
			// check if have scanned the directory already
			$images = (array_key_exists($parentDirHash, $this->directoryImages) === TRUE)
				? $this->directoryImages[ $parentDirHash ]
				: $this->getDirectoryFiles($app->config['mpd']['musicdir'] . $parentDir);
			$this->directoryImages[ $parentDirHash ] = $images;
			if(count($images) > 0) {
				$foundAlbumImages = array_merge($foundAlbumImages, $images);
			}
		}

		$return = array();
		foreach($foundAlbumImages as $imagePath){
			$return[] = str_replace($app->config['mpd']['musicdir'], '', $imagePath);
		}
		return $return;
	}

	private function pluralizeartworkDirNames() {
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
	