<?php
namespace Slimpd\Modules\importer;
/* Copyright (C) 2016 othmar52 <othmar52@users.noreply.github.com>
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
use Slimpd\Models\Track;
use Slimpd\Models\Trackindex;
use Slimpd\Models\Artist;
use Slimpd\Models\Album;
use Slimpd\Models\Albumindex;
use Slimpd\Models\Label;
use Slimpd\Models\Genre;
use Slimpd\Models\Rawtagdata;
use Slimpd\Models\Bitmap;

class Filescanner extends \Slimpd\Modules\importer\AbstractImporter {
	public $extractedImages = 0;
	public function run() {
		# TODO: handle orphaned records
		# TODO: displaying itemsChecked / itemsProcessed is incorrect
		# TODO: which speed-calculation makes sense? itemsChecked/minutute or itemsProcessed/minute or both?
		$this->jobPhase = 3;
		$this->beginJob(array('msg' => 'collecting tracks to scan from mysql database' ), __FUNCTION__);
		
		$app = \Slim\Slim::getInstance();
		
		$phpThumb = Bitmap::getPhpThumb();
		$phpThumb->setParameter('config_cache_directory', APP_ROOT.'embedded');
		
		$getID3 = new \getID3;
		
		// get timestamps of all images from mysql database
		//$imageTimestampsMysql = array();
		
			////////////////////////////////////////////////////////////////
			// TEMP reset database status for testing purposes
			#$query = "UPDATE rawtagdata SET importStatus=1, lastScan=0;";
			#$app->db->query($query);
			#$query = "DELETE FROM bitmap WHERE trackUid > 0;";
			#$app->db->query($query);
			////////////////////////////////////////////////////////////////
		
		$query = "
			SELECT COUNT(*) AS itemsTotal
			FROM rawtagdata WHERE lastScan=0";
		$this->itemsTotal = (int) $app->db->query($query)->fetch_assoc()['itemsTotal'];
			
			
		$query = "
			SELECT uid, relPath, relPathHash, relDirPathHash
			FROM rawtagdata WHERE lastScan=0";

		$result = $app->db->query($query);
		$this->extractedImages = 0;
		while($record = $result->fetch_assoc()) {
			$this->itemsChecked++;
			cliLog($record['uid'] . ' ' . $record['relPath'], 2);
			$this->updateJob(array(
				'msg' => 'processed ' . $this->itemsChecked . ' files',
				'currentItem' => $record['relPath'],
				'extractedImages' => $this->extractedImages
			));
			$rawTagData = new Rawtagdata();
			$rawTagData->setUid($record['uid'])
				->setRelPath($record['relPath'])
				->setLastScan(time())
				->setImportStatus(2);
			
			// TODO: handle not found files
			if(is_file($app->config['mpd']['musicdir'] . $record['relPath']) === FALSE) {
				$rawTagData->setError('invalid file');
				$rawTagData->update();
				continue;
			}

			$rawTagData->setFilesize( filesize($app->config['mpd']['musicdir'] . $record['relPath']) );
			
			// skip very large files
			// TODO: how to handle this?
			if($rawTagData->getFilesize() > 1000000000) {
				$rawTagData->setError('invalid filesize ' . $rawTagData->getFilesize() . ' bytes')
					->update();
				continue;
			}
			
			$tagData = $getID3->analyze($app->config['mpd']['musicdir'] . $record['relPath']);
			\getid3_lib::CopyTagsToComments($tagData);
			try {
				$dataCopy = $tagData;
				// TODO: move big-tagData-stuff that should be removed to config
				unset($dataCopy['comments']['picture']);
				unset($dataCopy['id3v2']['APIC']);
				// TODO: should we complete rawTagData with fingerprint on flac files?
				$rawTagData->setTagData(serialize($dataCopy));
			} catch (\Exception $e) { }
			$rawTagData->update();

			if(!$app->config['images']['read_embedded']) {
				continue;
			}
			$this->extractEmbeddedBitmaps($tagData, $phpThumb, $record);
			
		}

		$this->finishJob(array(
			'extractedImages' => $this->extractedImages
		), __FUNCTION__);
		return;
	}

	private function extractEmbeddedBitmaps($tagData, &$phpThumb, $record) {
		if(isset($tagData['comments']['picture']) === FALSE) {
			return;
		}
		if(is_array($tagData['comments']['picture']) === FALSE) {
			return;
		}
		$app = \Slim\Slim::getInstance();
		// loop through all embedded images
		foreach($tagData['comments']['picture'] as $bitmapIndex => $bitmapData) {	
			if(isset($bitmapData['image_mime']) === FALSE) {
				// skip unspecifyable datachunk
				continue;
			}
			if(isset($bitmapData['data']) === FALSE) {
				// skip missing datachunk
				continue;
			}
		
			$rawImageData = $bitmapData['data'];
			if(strlen($rawImageData) < 20) {
				// skip obviously invalid imagedata
				continue;
			}

			// TODO: find a file where we can reproduce this error
			// for now deactivate the size check
			//if(strlen($rawImageData) > 40000) {
			//	// skip huge imagedata
			//	// got errormessage "Maximum supported image dimension is 65500 pixels" from ???
			//	continue;
			//}
			
			# TODO: delete tmp files of php thumb (cache/pThumb*) - shouldn't phpThumb handle that itself?

			$phpThumb->resetObject();
			$phpThumb->setSourceData($rawImageData);
			$phpThumb->setParameter('config_cache_prefix', $record['relPathHash'].'_' . $bitmapIndex . '_');
			$phpThumb->SetCacheFilename();
			$phpThumb->GenerateThumbnail();
			\phpthumb_functions::EnsureDirectoryExists(
				dirname($phpThumb->cache_filename),
				octdec($app->config['config']['dirCreateMask'])
			);
			$phpThumb->RenderToFile($phpThumb->cache_filename);
			
			$this->extractedImages ++;
			
			if(is_file($phpThumb->cache_filename) === FALSE) {
				// there had been an error
				// TODO: how to handle this?
				continue;
			}
			
			// remove tempfiles of phpThumb
			clearPhpThumbTempFiles($phpThumb);
			
			$relPath = removeAppRootPrefix($phpThumb->cache_filename);
			$relPathHash = getFilePathHash($relPath);
			
			$imageSize = GetImageSize($phpThumb->cache_filename);
			
			$bitmap = new Bitmap();
			$bitmap->setRelPath($relPath)
				->setRelPathHash($relPathHash)
				->setFilemtime(filemtime($phpThumb->cache_filename))
				->setFilesize(filesize($phpThumb->cache_filename))
				->setTrackUid($record['uid'])
				->setRelDirPathHash($record['relDirPathHash'])
				->setEmbedded(1)
				// setAlbumUid() will be applied later because at this time we havn't any albumUid's but tons of bitmap-record-dupes
				->setFileName(
					(isset($bitmapData['picturetype']) !== FALSE)
						? $bitmapData['picturetype'] . '.ext'
						: 'Other.ext'
				)
				->setPictureType($app->imageweighter->getType($bitmap->getFileName()))
				->setSorting($app->imageweighter->getWeight($bitmap->getFileName()));

			if($imageSize === FALSE) {
				$bitmap->setError(1)->update();
				continue;
			}

			$bitmap->setWidth($imageSize[0])
				->setHeight($imageSize[1])
				->setBghex(
					self::getDominantColor($phpThumb->cache_filename, $imageSize[0], $imageSize[1])
				)
				->setMimeType($imageSize['mime'])
				# TODO: can we call insert() immediatly instead of letting check the update() function itself?
				# this could save performance...
				->update();
		}
	}

	// TODO: where to move pythonscript?
	// TODO: general wrapper for shell-executing stuff
	public static function extractAudioFingerprint($absolutePath, $returnCommand = FALSE) {
		switch(getFileExt($absolutePath)) {
			case 'mp3':
				$cmd =  \Slim\Slim::getInstance()->config['modules']['bin_python_2'] .
					' ' . APP_ROOT . "scripts/mp3md5_mod.py -3 " . escapeshellargDirty($absolutePath);
				break;
			case 'flac':
				$cmd =  \Slim\Slim::getInstance()->config['modules']['bin_metaflac'] .
					' --show-md5sum ' . escapeshellargDirty($absolutePath);
				break;
			default:
				# TODO: can we get md5sum with php in a performant way?
				$cmd = \Slim\Slim::getInstance()->config['modules']['bin_md5'] .' ' . escapeshellargDirty($absolutePath) . ' | awk \'{ print $1 }\'';
		}
		if($returnCommand === TRUE) {
			return $cmd;
		}
		#echo $cmd . "\n";
		$response = exec($cmd);
		if(preg_match("/^[0-9a-f]{32}$/", $response)) {
			return $response;
		}
		return FALSE;
	}

	public static function getDominantColor($absolutePath, $width, $height) {
		$quality = $width*$height/10;
		$quality = ($quality < 10) ? 10 : $quality;
		try {
			return rgb2hex(\ColorThief\ColorThief::getColor($absolutePath, $quality));
		} catch(\Exception $e) {
			return "#000000";
		}
	}
}
