<?php
namespace Slimpd\Modules\importer;
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
			#$query = "DELETE FROM bitmap WHERE trackId > 0;";
			#$app->db->query($query);
			////////////////////////////////////////////////////////////////
		
		$query = "
			SELECT COUNT(*) AS itemsTotal
			FROM rawtagdata WHERE lastScan < filemtime";
		$this->itemsTotal = (int) $app->db->query($query)->fetch_assoc()['itemsTotal'];
			
			
		$query = "
			SELECT id,
				relPath, relPathHash, filemtime,
				relDirPath, relDirPathHash, directoryMtime
			FROM rawtagdata
			WHERE lastScan < filemtime";// LIMIT 200000,1000;";

		$result = $app->db->query($query);
		$this->extractedImages = 0;
		while($record = $result->fetch_assoc()) {
			$this->itemsChecked++;
			cliLog($record['id'] . ' ' . $record['relPath'], 2);
			$this->updateJob(array(
				'msg' => 'processed ' . $this->itemsChecked . ' files',
				'currentItem' => $record['relPath'],
				'extractedImages' => $this->extractedImages
			));
			$rawTagData = new Rawtagdata();
			$rawTagData->setId($record['id']);
			$rawTagData->setRelPath($record['relPath']);
			$rawTagData->setLastScan(time());
			$rawTagData->setImportStatus(2);
			
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
				$rawTagData->setError('invalid filesize ' . $rawTagData->getFilesize() . ' bytes');
				$rawTagData->update();
				continue;
			}
			
			$tagData = $getID3->analyze($app->config['mpd']['musicdir'] . $record['relPath']);
			\getid3_lib::CopyTagsToComments($tagData);
			$this->mapTagsToRawtagdataInstance($rawTagData, $tagData);
			
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
			
			$relPath = str_replace(APP_ROOT, '', $phpThumb->cache_filename);
			$relPathHash = getFilePathHash($relPath);
			
			$imageSize = GetImageSize($phpThumb->cache_filename);
			
			$bitmap = new Bitmap();
			$bitmap->setRelPath($relPath);
			$bitmap->setRelPathHash($relPathHash);
			$bitmap->setFilemtime(filemtime($phpThumb->cache_filename));
			$bitmap->setFilesize(filesize($phpThumb->cache_filename));
			$bitmap->setRawTagDataId($record['id']); # TODO: is there any more need for both ID's?
			$bitmap->setTrackId($record['id']);		 # TODO: is there any more need for both ID's?
			$bitmap->setEmbedded(1);
			// setAlbumId() will be applied later because at this time we havn't any albumId's but tons of bitmap-record-dupes
			
			$bitmap->setEmbeddedName(
				(isset($bitmapData['picturetype']) !== FALSE)
					? $bitmapData['picturetype'] . '.ext'
					: 'Other.ext'
			);
			
			$bitmap->setPictureType($app->imageweighter->getType($bitmap->getEmbeddedName()));
			$bitmap->setSorting($app->imageweighter->getWeight($bitmap->getEmbeddedName()));

			if($imageSize === FALSE) {
				$bitmap->setError(1);
				$bitmap->update();
				continue;
			}

			$bitmap->setWidth($imageSize[0]);
			$bitmap->setHeight($imageSize[1]);
			$bitmap->setBghex(
				self::getDominantColor($phpThumb->cache_filename, $imageSize[0], $imageSize[1])
			);
			$bitmap->setMimeType($imageSize['mime']);

			# TODO: can we call insert() immediatly instead of letting check the update() function itself?
			# this could save performance...
			$bitmap->update();
		}
	}

	// TODO: instead of setting initial values on instance define default values in mysql-fields
	// TODO: move this to models/Rawtagdata.php
	private function mapTagsToRawtagdataInstance(&$rawTagData, $data) {
		
		$baseTags = array(
			'mime_type' => 'setMimeType',
			'playtime_seconds' => 'setMiliseconds',
			'md5_data_source' => 'setFingerprint'
		);
		
		$commonTags = array(
			'album' => 'setAlbum',
			'artist' => 'setArtist',
			'genre' => 'setGenre',
			'publisher' => 'setPublisher',
			'remixer' => 'setRemixer',
			'remixed by' => 'setRemixer',
			'title' => 'setTitle',
			'track_number' => 'setTrackNumber',
			'track number' => 'setTrackNumber',
			'track' => 'setTrackNumber',
			'year' => 'setYear',
			'comment' => 'setComment',
			'catalog' => 'setCatalogNr',
			'discogs_release_id' => 'setTextDiscogsReleaseId',
			'discogs-id' => 'setTextDiscogsReleaseId',
			'country' => 'setCountry',
			'dynamic range' => 'setDynamicRange',
			'album artist' => 'setAlbumArtist',
			'date' => 'setDate',
			'totaltracks' => 'setTotalTracks',
			'total tracks' => 'setTotalTracks',
			'url_user' => 'setTextUrlUser',
			'source' => 'setTextSource',
			'initial_key' => 'setInitialKey'
		);
		
		$commentsTags = array(
			'comment' => 'setComment',
			'dynamic range' => 'setDynamicRange',
			'album artist' => 'setAlbumArtist',
			'date' => 'setDate',
			'totaltracks' => 'setTotalTracks',
		);
		
		$textTags = array(
			'CATALOG' => 'setCatalogNr',
			'Catalog #' => 'setCatalogNr',
			'Source' => 'setTextSource',
			'COUNTRY' => 'setCountry',
			'DISCOGS_RELEASE_ID' => 'setTextDiscogsReleaseId',
			'Discogs-id' => 'setCatalogNr',
			'DYNAMIC RANGE' => 'setDynamicRange',
			'TraktorPeakDB' => 'setTextPeakDb',
			'TraktorPerceivedDB' => 'setTextPerceivedDb',
			'TraktorRating' => 'setTextRating',
			'fBPM' => 'setTextBpm',
			'fBPMQuality' => 'setTextBpmQuality',
			'url_user' => 'setTextUrlUser',
			
		);
		
		$audio = array(
			'dataformat' => 'setAudioDataformat',
			'encoder' => 'setAudioEncoder',
			'lossless' => 'setAudioLossless',
			'compression_ratio' => 'setAudioComprRatio',
			'bitrate' => 'setAudioBitrate',
			'bitrate_mode' => 'setAudioBitrateMode',
			'bits_per_sample' => 'setAudioBitsPerSample',
			'sample_rate' => 'setAudioSamplerate',
		);
		
		$video = array(
			'dataformat' => 'setVideoDataformat',
			'codec' => 'setVideoCodec',
			'resolution_x' => 'setVideoResolutionX',
			'resolution_y' => 'setVideoResolutionY',
			'frame_rate' => 'setVideoFramerate',
		);
		
		
		if(isset($data['error'])) {
			$rawTagData->setError($rawTagData->getError() . "\n" . join("\n", $data['error']));
		}
		
		// commentsTags
		foreach($commentsTags as $tagName => $setter) {
			if(isset($data['comments'][$tagName]) === FALSE) {
				continue;
			}
			$tagValue = $this->extractTagString($data['comments'][$tagName]);
			if($tagValue !== FALSE) {
				$rawTagData->$setter($tagValue);
			}
		}
		
		// baseTags
		foreach($baseTags as $tagName => $setter) {
			if(isset($data[$tagName]) === FALSE) {
				continue;
			}
			$tagValue = $this->extractTagString($data[$tagName]);
			if($tagValue !== FALSE) {
				$rawTagData->$setter($tagValue);
			}
		}
		
		// audio
		foreach($audio as $tagName => $setter) {
			if(isset($data['audio'][$tagName]) === FALSE) {
				continue;
			}
			$tagValue = $this->extractTagString($data['audio'][$tagName]);
			if($tagValue !== FALSE) {
				$rawTagData->$setter($tagValue);
			}
		}
		if (isset($data['mpc']['header']['profile'])) {
			$tagValue = $this->extractTagString($data['mpc']['header']['profile']);
			if($tagValue !== FALSE) {
				$rawTagData->setAudioProfile($tagValue);
			}
		}
		if (isset($data['aac']['header']['profile_text'])) {
			$tagValue = $this->extractTagString($data['aac']['header']['profile_text']);
			if($tagValue !== FALSE) {
				$rawTagData->setAudioProfile($tagValue);
			}
		}

		// video
		foreach($video as $tagName => $setter) {
			if(isset($data['video'][$tagName]) === FALSE) {
				continue;
			}
			$tagValue = $this->extractTagString($data['video'][$tagName]);
			if($tagValue !== FALSE) {
				$rawTagData->$setter($tagValue);
			}
		}

		foreach(array('id3v1', 'id3v2', 'ape', 'vorbiscomment') as $tagGroup) {
			if(isset($data['tags'][$tagGroup]) === FALSE) {
				continue;
			}
			foreach($commonTags as $tagName => $setter) {
				if(isset($data['tags'][$tagGroup][$tagName]) === FALSE) {
					continue;
				}
				$tagValue = $this->extractTagString($data['tags'][$tagGroup][$tagName]);
				if($tagValue !== FALSE) {
					$rawTagData->$setter($tagValue);
				}
			}
			if(isset($data['tags'][$tagGroup]['text']) === FALSE) {
				continue;
			}
			foreach($textTags as $tagName => $setter) {
				if(isset($data['tags'][$tagGroup]['text'][$tagName]) === FALSE) {
					continue;
				}
				$tagValue = $this->extractTagString($data['tags'][$tagGroup]['text'][$tagName]);
				if($tagValue !== FALSE) {
					$rawTagData->$setter($tagValue);
				}
			}
		}

		// override description of audiocodec
		// @see: https://github.com/othmar52/slimpd/issues/25
		// @see: https://github.com/JamesHeinrich/getID3/issues/48
		if(getFileExt($rawTagData->getRelPath()) !== 'm4a') {
			return;
		}
		if(@$data['audio']['codec'] === 'Apple Lossless Audio Codec') {
			$rawTagData->setMimeType('audio/aac');
			$rawTagData->setAudioDataformat('aac');
		}
	}

	private function extractTagString($mixed) {
		$out = '';
		if(is_string($mixed))	{ $out = trim($mixed); }
		if(is_array($mixed))	{ $out = join (", ", $mixed); }
		if(is_bool($mixed))		{ $out = ($mixed === TRUE) ? '1' : '0'; }
		if(is_int($mixed))		{ $out = $mixed; }
		if(is_float($mixed))	{ $out = $mixed; }
		if(trim($out) === '')	{ return FALSE; }
		return trim($out);
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
