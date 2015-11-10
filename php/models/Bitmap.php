<?php
namespace Slimpd;

class Bitmap extends AbstractModel
{
	protected $id;
	protected $relativePath;
	protected $relativePathHash;
	protected $filemtime;
	protected $filesize;
	protected $mimeType;
	protected $width;
	protected $height;
	
	protected $albumId;
	protected $trackId;
	protected $rawTagDataId;
	protected $embedded;
	protected $importStatus;
	protected $error;
	
	public static $tableName = 'bitmap';
	
	public function dump($preConf) {
		$imgDirecoryPrefix = ($this->getTrackId())
			? APP_ROOT
			: \Slim\Slim::getInstance()->config['mpd']['musicdir'];
			
		$phpThumb = self::getPhpThumb();	
		$phpThumb->setSourceFilename($imgDirecoryPrefix . $this->getRelativePath());
		$phpThumb->setParameter('config_output_format', 'jpg');
		
		switch($preConf) {
			case 50:
			case 100:
			case 300:
			case 1000:
				$phpThumb->setParameter('w', $preConf);
				break;
			default:
				$phpThumb->setParameter('w', 300);
		}
		$phpThumb->SetCacheFilename();
		
		
		// check if we already have a cached image
		if(is_file($phpThumb->cache_filename) === FALSE || is_readable($phpThumb->cache_filename) === FALSE) {		
			$phpThumb->GenerateThumbnail();
			\phpthumb_functions::EnsureDirectoryExists(dirname($phpThumb->cache_filename));
			$phpThumb->RenderToFile($phpThumb->cache_filename);
			if(is_file($phpThumb->cache_filename) === TRUE) {
				chmod($phpThumb->cache_filename, 0777);
			} else {
				// something went wrong
				// TODO: how to handle this?
				// TODO: make sure we have no infinite loop...
				return self::getFallbackImage()->dump($preConf);
			}
		}
		#\Slim\Slim::getInstance()->response()->headers->set('Content-Type', 'image/jpeg');
		header('Content-Type: image/jpeg');
		readfile($phpThumb->cache_filename);
		exit();	
	}
	
	public static function getFallbackImage() {
		$bitmap = new Bitmap();
		$bitmap->setRelativePath(\Slim\Slim::getInstance()->config['images']['fallback_image']);
		$bitmap->setTrackId('xxx');
		return $bitmap;
	}
	
	
	public function update() {
		if($this->getId() > 0) {
			// we already have an id ...
		} else {
			// check if we have a record with this path
			$searchParams = array(
				'relativePath' => $this->getRelativePath()
			);
			
			// multiple usage of same image files are possible...
			if($this->getAlbumId() > 0) {
				$searchParams['albumId'] = $this->getAlbumId(); 
			}			
			$b2 = Bitmap::getInstanceByAttributes($searchParams);

			if($b2 !== NULL) {
				$this->setId($b2->getId());
			} else {
				return $this->insert();
			}
		}
			
		$app = \Slim\Slim::getInstance();
		
		$query = 'UPDATE '.self::$tableName .' SET ';
		foreach($this->mapInstancePropertiesToDatabaseKeys() as $dbfield => $value) {
			$query .= $dbfield . '="' . $app->db->real_escape_string($value) . '",';
		}
		$query = substr($query,0,-1) . ' WHERE id=' . (int)$this->getId() . ";";
		$app->db->query($query);
	}
	
	public function destroy() {
		if($this->getId() < 1) {
			// invalid instance
			return FALSE;
		}
		
		if($this->getEmbedded() < 1) {
			// currently it is only allowed to delete images extracted from musicfiles
			return FALSE;
		}
		
		if(!$this->getRelativePath()) {
			// invalid instance 
			return FALSE;
		}
		$bitmapPath = APP_ROOT . $this->getRelativePath(); 
		if(is_file($bitmapPath) === TRUE && is_writeable($bitmapPath) === TRUE) {
			unlink($bitmapPath);
		}
		
		$query = 'DELETE FROM '.self::$tableName .' WHERE id=' . (int)$this->getId() . ";";
		\Slim\Slim::getInstance()->db->query($query);
		return TRUE;
	}
	
	public static function addAlbumIdToTrackId($trackId, $albumId) {
		# blind adding albumId - no matter if it a record is affected or not..
		# TODO: does it matter or not?
		\Slim\Slim::getInstance()->db->query(
			'UPDATE '.self::$tableName .
			' SET albumId='. (int)$albumId .
			' WHERE trackId='. (int)$trackId
		);
		return;
	}


		
	//setter
	public function setId($value) {
		$this->id = $value;
	}
	public function setRelativePath($value) {
		$this->relativePath = $value;
	}
	public function setRelativePathHash($value) {
		$this->relativePathHash = $value;
	}
	public function setFilemtime($value) {
		$this->filemtime = $value;
	}
	public function setFilesize($value) {
		$this->filesize = $value;
	}
	public function setMimeType($value) {
		$this->mimeType = $value;
	}
	public function setWidth($value) {
		$this->width = $value;
	}
	public function setHeight($value) {
		$this->height = $value;
	}
	public function setAlbumId($value) {
		$this->albumId = $value;
	}
	public function setTrackId($value) {
		$this->trackId = $value;
	}
	public function setRawTagDataId($value) {
		$this->rawTagDataId = $value;
	}
	public function setEmbedded($value) {
		$this->embedded = $value;
	}
	public function setImportStatus($value) {
		$this->importStatus = $value;
	}
	public function setError($value) {
		$this->error = $value;
	}
	
	
	// getter
	public function getId() {
		return $this->id;
	}
	public function getRelativePath() {
		return $this->relativePath;
	}
	public function getRelativePathHash() {
		return $this->relativePathHash;
	}
	public function getFilemtime() {
		return $this->filemtime;
	}
	public function getFilesize() {
		return $this->filesize;
	}
	public function getMimeType() {
		return $this->mimeType;
	}
	public function getWidth() {
		return $this->width;
	}
	public function getHeight() {
		return $this->height;
	}
	public function getAlbumId() {
		return $this->albumId;
	}
	public function getTrackId() {
		return $this->trackId;
	}
	public function getRawTagDataId() {
		return $this->rawTagDataId;
	}
	public function getEmbedded() {
		return $this->embedded;
	}
	public function getImportStatus() {
		return $this->importStatus;
	}
	public function getError() {
		return $this->error;
	}
	
	# TODO: read phpThumbSettings from config
	public static function getPhpThumb() {
		$phpThumb = new \phpThumb();
		#$phpThumb->resetObject();
		$phpThumb->setParameter('config_disable_debug', FALSE);
		$phpThumb->setParameter('config_document_root', APP_ROOT);
		
		#$phpThumb->setParameter('config_high_security_enabled', TRUE);
		
		$phpThumb->setParameter('config_imagemagick_path', '/usr/bin/convert');
		$phpThumb->setParameter('config_allow_src_above_docroot', true);
		
		$phpThumb->setParameter('config_cache_directory', APP_ROOT. '..'. DS .'cache');
		$phpThumb->setParameter('config_temp_directory',  APP_ROOT. '..'. DS .'cache');
		$phpThumb->setParameter('config_cache_prefix', 'phpThumb_cache');
		#$phpThumb->setParameter('config_cache_force_passthru', FALSE);
		#$phpThumb->setParameter('config_cache_maxage', NULL);
		#$phpThumb->setParameter('config_cache_maxsize', NULL);
		#$phpThumb->setParameter('config_cache_maxfile', NULL);
		$phpThumb->setParameter('config_cache_directory_depth', 3);
		return $phpThumb;
	}
	
}
