<?php
namespace Slimpd\Models;

class Bitmap extends \Slimpd\Models\AbstractFilesystemItem
{
	protected $mimeType;
	protected $width;
	protected $height;
	protected $bghex;
	
	protected $albumId;
	protected $trackId;
	protected $rawTagDataId;
	protected $embedded;
	protected $embeddedName;
	protected $pictureType;
	protected $sorting;
	protected $importStatus;
	protected $error;
	
	public static $tableName = 'bitmap';
	
	public function dump($preConf, $app) {
		$imgDirecoryPrefix = ($this->getTrackId())
			? APP_ROOT
			: $app->config['mpd']['musicdir'];
			
		$phpThumb = self::getPhpThumb();	
		$phpThumb->setSourceFilename($imgDirecoryPrefix . $this->getRelPath());
		$phpThumb->setParameter('config_output_format', 'jpg');
		
		switch($preConf) {
			case 35:
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
			$newResponse = $app->response();
			$newResponse->body(
				new \GuzzleHttp\Stream\LazyOpenStream($phpThumb->cache_filename, 'r')
			);
			$newResponse->headers->set('Content-Type', 'image/jpeg');
			return $newResponse;
		} catch(\Exception $e) {
			$app->response->redirect($app->config['root'] . 'imagefallback-'.$preConf.'/broken');
			return;
		}
	}
	
	public function update() {
		if($this->getId() > 0) {
			// we already have an id ...
		} else {
			// check if we have a record with this path
			$searchParams = array(
				'relPath' => $this->getRelPath()
			);
			
			// multiple usage of same image files are possible...
			if($this->getAlbumId() > 0) {
				$searchParams['albumId'] = $this->getAlbumId();
			}			
			$bitmap2 = Bitmap::getInstanceByAttributes($searchParams);

			if($bitmap2 === NULL) {
				return $this->insert();
			}
			$this->setId($bitmap2->getId());
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
		
		if(!$this->getRelPath()) {
			// invalid instance 
			return FALSE;
		}
		$bitmapPath = APP_ROOT . $this->getRelPath(); 
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
	public function setMimeType($value) {
		$this->mimeType = $value;
	}
	public function setWidth($value) {
		$this->width = $value;
	}
	public function setHeight($value) {
		$this->height = $value;
	}
	public function setBghex($value) {
		$this->bghex = $value;
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
	public function setEmbeddedName($value) {
		$this->embeddedName = $value;
	}
	public function setPictureType($value) {
		$this->pictureType = $value;
	}
	public function setSorting($value) {
		$this->sorting = $value;
	}
	public function setImportStatus($value) {
		$this->importStatus = $value;
	}
	public function setError($value) {
		$this->error = $value;
	}
	
	
	// getter
	public function getMimeType() {
		return $this->mimeType;
	}
	public function getWidth() {
		return $this->width;
	}
	public function getHeight() {
		return $this->height;
	}
	public function getBghex() {
		return $this->bghex;
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
	public function getEmbeddedName() {
		return $this->embeddedName;
	}
	public function getPictureType() {
		return $this->pictureType;
	}
	public function getSorting() {
		return $this->sorting;
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
		
		$phpThumb->setParameter('config_cache_directory', APP_ROOT .'cache');
		$phpThumb->setParameter('config_temp_directory',  APP_ROOT .'cache');
		$phpThumb->setParameter('config_cache_prefix', 'phpThumb_cache');
		#$phpThumb->setParameter('config_cache_force_passthru', FALSE);
		#$phpThumb->setParameter('config_cache_maxage', NULL);
		#$phpThumb->setParameter('config_cache_maxsize', NULL);
		#$phpThumb->setParameter('config_cache_maxfile', NULL);
		$phpThumb->setParameter('config_cache_directory_depth', 3);
		$phpThumb->setParameter('config_file_create_mask', octdec(\Slim\Slim::getInstance()->config['config']['fileCreateMask']));
		$phpThumb->setParameter('config_dir_create_mask', octdec(\Slim\Slim::getInstance()->config['config']['dirCreateMask']));
		return $phpThumb;
	}
	
}
