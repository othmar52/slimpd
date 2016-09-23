<?php
namespace Slimpd\Models;
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
 * FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License
 * for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
class Bitmap extends \Slimpd\Models\AbstractFilesystemItem {
	use \Slimpd\Traits\PropertyMimeType;

	protected $width;
	protected $height;
	protected $bghex;
	
	protected $albumUid;
	protected $trackUid;
	protected $rawTagDataUid;
	protected $embedded;
	protected $fileName;
	protected $pictureType;
	protected $sorting;
	protected $hidden;
	protected $error;
	
	public static $tableName = 'bitmap';
	
	public function dump($preConf, $app) {
		$imgDirecoryPrefix = ($this->getTrackUid())
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
		if($this->getUid() > 0) {
			// we already have an uid ...
		} else {
			// check if we have a record with this path
			$searchParams = array(
				'relPath' => $this->getRelPath()
			);
			
			// multiple usage of same image files are possible...
			if($this->getAlbumUid() > 0) {
				$searchParams['albumUid'] = $this->getAlbumUid();
			}			
			$bitmap2 = Bitmap::getInstanceByAttributes($searchParams);

			if($bitmap2 === NULL) {
				return $this->insert();
			}
			$this->setUid($bitmap2->getUid());
		}
			
		$app = \Slim\Slim::getInstance();
		
		$query = 'UPDATE '.self::$tableName .' SET ';
		foreach($this->mapInstancePropertiesToDatabaseKeys() as $dbfield => $value) {
			$query .= $dbfield . '="' . $app->db->real_escape_string($value) . '",';
		}
		$query = substr($query,0,-1) . ' WHERE uid=' . (int)$this->getUid() . ";";
		$app->db->query($query);
	}
	
	public function destroy() {
		if($this->getUid() < 1) {
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
		rmfile(APP_ROOT . $this->getRelPath());
		$query = 'DELETE FROM '.self::$tableName .' WHERE uid=' . (int)$this->getUid() . ";";
		\Slim\Slim::getInstance()->db->query($query);
		return TRUE;
	}
	
	public static function addAlbumUidToTrackUid($trackUid, $albumUid) {
		# blind adding albumUid - no matter if it a record is affected or not..
		# TODO: does it matter or not?
		\Slim\Slim::getInstance()->db->query(
			'UPDATE '.self::$tableName .
			' SET albumUid='. (int)$albumUid .
			' WHERE trackUid='. (int)$trackUid
		);
		return;
	}


	//setter
	public function setWidth($value) {
		$this->width = $value;
		return $this;
	}
	public function setHeight($value) {
		$this->height = $value;
		return $this;
	}
	public function setBghex($value) {
		$this->bghex = $value;
		return $this;
	}
	public function setAlbumUid($value) {
		$this->albumUid = $value;
		return $this;
	}
	public function setTrackUid($value) {
		$this->trackUid = $value;
		return $this;
	}
	public function setRawTagDataUid($value) {
		$this->rawTagDataUid = $value;
		return $this;
	}
	public function setEmbedded($value) {
		$this->embedded = $value;
		return $this;
	}
	public function setFileName($value) {
		$this->fileName = $value;
		return $this;
	}
	public function setPictureType($value) {
		$this->pictureType = $value;
		return $this;
	}
	public function setSorting($value) {
		$this->sorting = $value;
		return $this;
	}
	public function setHidden($value) {
		$this->hidden = $value;
		return $this;
	}
	public function setError($value) {
		$this->error = $value;
		return $this;
	}

	// getter
	public function getWidth() {
		return $this->width;
	}
	public function getHeight() {
		return $this->height;
	}
	public function getBghex() {
		return $this->bghex;
	}
	public function getAlbumUid() {
		return $this->albumUid;
	}
	public function getTrackUid() {
		return $this->trackUid;
	}
	public function getRawTagDataUid() {
		return $this->rawTagDataUid;
	}
	public function getEmbedded() {
		return $this->embedded;
	}
	public function getFileName() {
		return $this->fileName;
	}
	public function getPictureType() {
		return $this->pictureType;
	}
	public function getSorting() {
		return $this->sorting;
	}
	public function getHidden() {
		return $this->hidden;
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
