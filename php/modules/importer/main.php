<?php
namespace Slimpd;
use Slimpd\track;
use Slimpd\artist;
use Slimpd\album;
use Slimpd\label;
use Slimpd\genre;
use Slimpd\rawtagdata;

class Importer {
	protected $commonArtworkDirectoryNames = array();
	protected $jobId;					// mysql record id
	protected $jobPhase;				// numeric index
	protected $jobBegin;				// tstamp
	protected $jobStatusInterval = 5; 	// seconds
	protected $lastJobStatusUpdate = 0; // timestamp
	
	// counters needed for calculating estimated time and spped [Tracks/minute]
	protected $itemCountChecked = 0;	
	protected $itemCountProcessed = 0;
	protected $itemCountTotal = 0;
	
	protected $directoryHashes = array(/* dirhash -> albumId */);
	protected $updatedAlbums = array(/* id -> NULL */); 
	
	# TODO: unset all big arrays at the end of each method
	
	
	public function scanMusicFileTags() {
		# TODO: handle orphaned records
		# TODO: displaying itemsChecked / itemsProcessed is incorrect
		# TODO: which speed-calculation makes sense? itemsChecked/minutute or itemsProcessed/minute or both?
		 
		$this->jobPhase = 2;
		$this->beginJob(array('msg' => 'collecting tracks to scan from mysql database' ), __FUNCTION__);
		
		$app = \Slim\Slim::getInstance();
		
		$phpThumb = Bitmap::getPhpThumb();
		$phpThumb->setParameter('config_cache_directory', APP_ROOT.'embedded');
		
		$getID3 = new \getID3;
		// make sure that a single directory will not be scanned twice
		$scannedDirectories = array();
		
		// get timestamps of all images from mysql database
		$imageTimestampsMysql = array();
		
			////////////////////////////////////////////////////////////////
			// TEMP reset database status for testing purposes
			#$query = "UPDATE rawtagdata SET importStatus=1, lastScan=0;";
			#$app->db->query($query);
			#$query = "DELETE FROM bitmap WHERE trackId > 0;";
			#$app->db->query($query);
			////////////////////////////////////////////////////////////////
				
		
		if($app->config['images']['look_cover_directory'] == TRUE) {
			$this->pluralizeCommonArtworkDirectoryNames(
				$app->config['images']['common_artwork_dir_names']
			);
		}
		
		$query = "
			SELECT COUNT(*) AS itemCountTotal
			FROM rawtagdata WHERE lastScan < filemtime";
		$this->itemCountTotal = (int) $app->db->query($query)->fetch_assoc()['itemCountTotal'];
			
			
		$query = "
			SELECT id,
				relativePath, relativePathHash, filemtime,
				relativeDirectoryPath, relativeDirectoryPathHash, directoryMtime 
			FROM rawtagdata 
			WHERE lastScan < filemtime";// LIMIT 200000,1000;";
			
		$result = $app->db->query($query);
		$extractedImages = 0;
		while($record = $result->fetch_assoc()) {
			$this->itemCountChecked++;
			cliLog($record['id'] . ' ' . $record['relativePath'], 2);
			$this->updateJob(array(
				'msg' => 'processed ' . $this->itemCountChecked . ' files',
				'currentItem' => $record['relativePath'],
				'extractedImages' => $extractedImages
			));
			$t = new Rawtagdata();
			$t->setId($record['id']);
			$t->setLastScan(time());
			$t->setImportStatus(2);
			
			// TODO: handle not found files
			if(is_file($app->config['mpd']['musicdir'] . $record['relativePath']) === TRUE) {
				$t->setFilesize( filesize($app->config['mpd']['musicdir'] . $record['relativePath']) );
			} else {
				$t->setError('invalid file');
				$t->update();
				continue;
			}
			
			// skip very large files
			// TODO: how to handle this?
			if($t->getFilesize() > 1000000000) {
				$t->setError('invalid filesize ' . $t->getFilesize() . ' bytes');
				$t->update();
				continue;
			}
			
			$tagData = $getID3->analyze($app->config['mpd']['musicdir'] . $record['relativePath']);
			\getid3_lib::CopyTagsToComments($tagData);
			$this->mapTagsToRawtagdataInstance($t, $tagData);
			
			$t->update();

			if(!$app->config['images']['read_embedded']) {
				continue;
			}
			if(isset($tagData['comments']['picture']) === FALSE) {
				continue;
			}
			if(is_array($tagData['comments']['picture']) === FALSE) {
				continue;
			}
			
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
				if(strlen($rawImageData) > 40000) {
					// skip huge imagedata
					// got errormessage "Maximum supported image dimension is 65500 pixels" from ???
					continue;
				}
				
				# TODO: delete tmp files of php thumb - shouldn't phpThumb handle that itself?
				$phpThumb->resetObject();
				$phpThumb->setSourceData($rawImageData);
				$phpThumb->setParameter('config_cache_prefix', $record['relativePathHash'].'_' . $bitmapIndex . '_');
				$phpThumb->SetCacheFilename();
				$phpThumb->GenerateThumbnail();
				\phpthumb_functions::EnsureDirectoryExists(dirname($phpThumb->cache_filename));
				$phpThumb->RenderToFile($phpThumb->cache_filename);
				
				$extractedImages ++;
				
				if(is_file($phpThumb->cache_filename) === FALSE) {
					// there had been an error
					// TODO: how to handle this?
					continue;
				}
				
				# TODO: general handling of permissions of created directories and files
				chmod($phpThumb->cache_filename, 0777);
				
				$relativePath = str_replace(APP_ROOT, '', $phpThumb->cache_filename);
				$relativePathHash = getFilePathHash($relativePath);
				
				$imageSize = GetImageSize($phpThumb->cache_filename);
				
				$bitmap = new Bitmap();
				$bitmap->setRelativePath($relativePath);
				$bitmap->setRelativePathHash($relativePathHash);
				$bitmap->setFilemtime(filemtime($phpThumb->cache_filename));
				$bitmap->setFilesize(filesize($phpThumb->cache_filename));
				$bitmap->setRawTagDataId($record['id']); # TODO: is there any more need for both ID's?
				$bitmap->setTrackId($record['id']);		 # TODO: is there any more need for both ID's?
				$bitmap->setEmbedded(1);
				// setAlbumId() will be applied later because at this time we havn't any albumId's but tons of bitmap-record-dupes
				
				if($imageSize !== FALSE) {
					$bitmap->setWidth($imageSize[0]);
					$bitmap->setHeight($imageSize[1]);
					$bitmap->setMimeType($imageSize['mime']);
				} else {
					$bitmap->setError(1);
				}
				# TODO: can we call insert() immediatly instead of letting check the update() function itself?
				# this could save performance...
				$bitmap->update();
			}
		}

		$this->finishJob(array(
			'extractedImages' => $extractedImages
		), __FUNCTION__);
		return;
	}


	// TODO: instead of setting initial values on instance define default values in mysql-fields
	// TODO: move this to models/Rawtagdata.php
	private function mapTagsToRawtagdataInstance(&$t, $data) {
		
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
			'catalog' => 'setTextCatalogNumber',
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
			'CATALOG' => 'setTextCatalogNumber',
			'Catalog #' => 'setTextCatalogNumber',
			'Source' => 'setTextSource',
			'COUNTRY' => 'setCountry',
			'DISCOGS_RELEASE_ID' => 'setTextDiscogsReleaseId',
			'Discogs-id' => 'setTextCatalogNumber',
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
			'compression_ratio' => 'setAudioCompressionRatio',
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
			$t->setError($t->getError() . "\n" . join("\n", $data['error']));
		}
		
		// commentsTags
		foreach($commentsTags as $tagName => $setter) {
			if(isset($data['comments'][$tagName]) === TRUE) {
				$tagValue = $this->extractTagString($data['comments'][$tagName]);
				if($tagValue !== FALSE) {
					$t->$setter($tagValue);
				}
			}
		}
		
		// baseTags
		foreach($baseTags as $tagName => $setter) {
			if(isset($data[$tagName]) === TRUE) {
				$tagValue = $this->extractTagString($data[$tagName]);
				if($tagValue !== FALSE) {
					$t->$setter($tagValue);
				}
			}
		}
		
		// audio
		foreach($audio as $tagName => $setter) {
			if(isset($data['audio'][$tagName]) === TRUE) {
				$tagValue = $this->extractTagString($data['audio'][$tagName]);
				if($tagValue !== FALSE) {
					$t->$setter($tagValue);
				}
			}
		}
		if (isset($data['mpc']['header']['profile'])) {
			$tagValue = $this->extractTagString($data['mpc']['header']['profile']);
			if($tagValue !== FALSE) {
				$t->setAudioProfile($tagValue);
			}
		}
		if (isset($data['aac']['header']['profile_text'])) {
			$tagValue = $this->extractTagString($data['aac']['header']['profile_text']);
			if($tagValue !== FALSE) {
				$t->setAudioProfile($tagValue);
			}
		}

		// video
		foreach($video as $tagName => $setter) {
			if(isset($data['video'][$tagName]) === TRUE) {
				$tagValue = $this->extractTagString($data['video'][$tagName]);
				if($tagValue !== FALSE) {
					$t->$setter($tagValue);
				}
			}
		}

		foreach(array('id3v1', 'id3v2', 'ape', 'vorbiscomment') as $tagGroup) {
			if(isset($data['tags'][$tagGroup]) === FALSE) {
				continue;
			}
			foreach($commonTags as $tagName => $setter) {
				if(isset($data['tags'][$tagGroup][$tagName]) === TRUE) {
					$tagValue = $this->extractTagString($data['tags'][$tagGroup][$tagName]);
					if($tagValue !== FALSE) {
						$t->$setter($tagValue);
					}
				}
			}
			if(isset($data['tags'][$tagGroup]['text']) === FALSE) {
				continue;
			}
			foreach($textTags as $tagName => $setter) {
				if(isset($data['tags'][$tagGroup]['text'][$tagName]) === TRUE) {
					$tagValue = $this->extractTagString($data['tags'][$tagGroup]['text'][$tagName]);
					if($tagValue !== FALSE) {
						$t->$setter($tagValue);
					}
				}
			}
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
	
	// TODO: performance tweaking by processing it vice versa:
	// read all images in embedded-directory and check if a db-record exists
	// skip the check for non-embedded/non-extracted images at all and delete db-record in case delivering fails
	// for now: skip this import phase ...

	public function deleteOrphanedBitmapRecords() {
		
		# TODO: remove this line after refactoring
		return;
		
		$this->jobPhase = 11;
		$this->beginJob(array('msg' => 'collecting records to check from table:bitmap'), __FUNCTION__);
		
		$app = \Slim\Slim::getInstance();
		
		$query = "SELECT count(id) AS itemCountTotal FROM bitmap";
		$this->itemCountTotal = (int) $app->db->query($query)->fetch_assoc()['itemCountTotal'];
		
		$deletedRecords = 0;
		$query = "SELECT id, relativePath, embedded FROM bitmap;";
		$result = $app->db->query($query);
		while($record = $result->fetch_assoc()) {
			$this->itemCountChecked++;
			$prefix = ($record['embedded'] == '1')
				? APP_ROOT.'embedded'
				: $app->config['mpd']['musicdir'];
			if(is_file($prefix . $record['relativePath']) === TRUE) {
				cliLog('keeping database-entry for ' . $record['relativePath'], 3);
			} else {
				cliLog('deleting database-entry for ' . $record['relativePath'], 3);
				$bitmap = new \Slimpd\Bitmap();
				$bitmap->setId($record['id']);
				$bitmap->delete(); 
				$deletedRecords++;
			}
			$this->itemCountProcessed++;
			$this->updateJob(array(
				'currentItem' => $record['relativePath'],
				'deletedRecords' => $deletedRecords
			));
		}
		$this->finishJob(array(
			'deletedRecords' => $deletedRecords
		), __FUNCTION__);
	}
	
	
	
	# TODO: it makes sense to search for other files (like discogs-links) during
	# this scan process to avoid multiple scan-processes of the same directory
	 
	public function searchImagesInFilesystem() {
		
		# TODO: in case an image gets replaced with same filename, the database record should get updated 
		
		$this->jobPhase = 5;
		$this->beginJob(array('msg' => 'collecting directories to scan from table:albums'), __FUNCTION__);
		
		
		$app = \Slim\Slim::getInstance();
		
		$phpThumb = Bitmap::getPhpThumb();
		
		// make sure that a single directory will not be scanned twice
		$scannedDirectories = array();
		
		if($app->config['images']['look_cover_directory'] == TRUE) {
			$this->pluralizeCommonArtworkDirectoryNames(
				$app->config['images']['common_artwork_dir_names']
			);
		}
		
		$query = "SELECT count(id) AS itemCountTotal FROM album WHERE lastScan <= filemtime;";
		$this->itemCountTotal = (int) $app->db->query($query)->fetch_assoc()['itemCountTotal'];
		
		$query = "SELECT id, relativePath, relativePathHash, filemtime FROM album WHERE lastScan <= filemtime;";
		$result = $app->db->query($query);
		$insertedImages = 0;
		
		while($record = $result->fetch_assoc()) {
			$this->itemCountChecked++;
			cliLog($record['id'] . ' ' . $record['relativePath'], 2);
			$this->updateJob(array(
				'msg' => 'processed ' . $this->itemCountChecked . ' files',
				'currentItem' => $record['relativePath'],
				'insertedImages' => $insertedImages
			));
			
			$a = new Album();
			$a->setId($record['id']);
			$a->setLastScan(time());
			$a->setImportStatus(2);
			
			$foundAlbumImages = array();
			
			if($app->config['images']['look_current_directory']) {
				// check if have scanned the directory already
				$images = (array_key_exists($record['relativePathHash'], $scannedDirectories) === TRUE)
					? $scannedDirectories[ $record['relativePathHash'] ]
					: getDirectoryFiles($app->config['mpd']['musicdir'] . $record['relativePath']);
				
				$scannedDirectories[ $record['relativePathHash'] ] = $images;
				if(count($images) > 0) {
					$foundAlbumImages = array_merge($foundAlbumImages, $images);
				}
			}
			
			
			if($app->config['images']['look_cover_directory']) {
				// search for specific named subdirectories
				if(is_dir($app->config['mpd']['musicdir'] . $record['relativePath']) === TRUE) {
					$handle=opendir($app->config['mpd']['musicdir'] . $record['relativePath']);
					while ($dirname = readdir ($handle)) {
						if(is_dir($app->config['mpd']['musicdir'] . $record['relativePath'] . $dirname)) {
							if(in_array(az09($dirname), $this->commonArtworkDirectoryNames)) {
								$foundAlbumImages = array_merge(
									$foundAlbumImages,
									getDirectoryFiles($app->config['mpd']['musicdir'] . $record['relativePath'] . $dirname)
								);
							}
						}
					}
					closedir($handle);
				}
			}

			if($app->config['images']['look_parent_directory'] && count($foundAlbumImages) === 0) {				
				$parentDir = dirname($record['relativePath']) . DS;
				$parentDirHash = getFilePathHash($parentDir);
				// check if have scanned the directory already
				$images = (array_key_exists($parentDirHash, $scannedDirectories) === TRUE)
					? $scannedDirectories[ $parentDirHash ]
					: getDirectoryFiles($app->config['mpd']['musicdir'] . $parentDir);
				$scannedDirectories[ $parentDirHash ] = $images;
				if(count($images) > 0) {
					$foundAlbumImages = array_merge($foundAlbumImages, $images);
				}
			}

			foreach($foundAlbumImages as $imagePath) {
				$relativePath = str_replace($app->config['mpd']['musicdir'], '', $imagePath);
				$relativePathHash = getFilePathHash($relativePath);
				$imageSize = GetImageSize($app->config['mpd']['musicdir']. $relativePath);

				$bitmap = new Bitmap();
				$bitmap->setRelativePath($relativePath);
				$bitmap->setRelativePathHash($relativePathHash);
				$bitmap->setFilemtime(filemtime($imagePath));
				$bitmap->setFilesize(filesize($imagePath));
				$bitmap->setAlbumId($record['id']);
				
				if($imageSize !== FALSE) {
					$bitmap->setWidth($imageSize[0]);
					$bitmap->setHeight($imageSize[1]);
					$bitmap->setMimeType($imageSize['mime']);
				} else {
					$bitmap->setError(1);
				}
				$bitmap->update();
				$insertedImages++;
			}
			$a->update();
		}
		$this->finishJob(array(
			'msg' => 'processed ' . $this->itemCountChecked . ' directories',
			'insertedImages' => $insertedImages
		), __FUNCTION__);
		unset($scannedDirectories);
		return;
	}

	private function pluralizeCommonArtworkDirectoryNames($dirnames) {
		foreach($dirnames as $dirname) {
			$this->commonArtworkDirectoryNames[] = az09($dirname);
			$this->commonArtworkDirectoryNames[] = az09($dirname) . 's';
		}
	}
	
	private function beginJob($data = array(), $function = '') {
		cliLog("STARTING import phase " . $this->jobPhase . " " . $function . '()', 1, "cyan");
		$app = \Slim\Slim::getInstance();
		$this->jobBegin = microtime(TRUE);
		$this->itemCountChecked = 0;
		$this->itemCountProcessed = 0;
		$this->itemCountTotal = 0;
		$query = "INSERT INTO importer
			(jobPhase, jobStart, jobLastUpdate, jobStatistics)
			VALUES (".(int)$this->jobPhase.", ". $this->jobBegin.", ". $this->jobBegin. ",'" .serialize($data)."')";
		$app->db->query($query);
		$this->jobId = $app->db->insert_id;
		$this->lastJobStatusUpdate = $this->jobBegin;
	}
	
	private function updateJob($data = array()) {
		$microtime = microtime(TRUE);
		if($microtime - $this->lastJobStatusUpdate < $this->jobStatusInterval) {
			return;
		}
		
		$data['progressPercent'] = 0;
		$data['microTimestamp'] = $microtime;
		$this->calculateSpeed($data);
		
		$query = "UPDATE importer
			SET jobStatistics='" .serialize($data)."',
			jobLastUpdate=".$microtime."
			WHERE id=" . $this->jobId;
		\Slim\Slim::getInstance()->db->query($query);
		cliLog('progress:' . $data['progressPercent'] . '%', 1);
		$this->lastJobStatusUpdate = $microtime;
		return;
	}
	
	private function finishJob($data = array(), $function = '') {
		cliLog("FINISHED import phase " . $this->jobPhase . " " . $function . '()', 1, "cyan");
		$microtime = microtime(TRUE);
		$data['progressPercent'] = 100;
		$data['microTimestamp'] = $microtime;
		$this->calculateSpeed($data);
		
		$query = "UPDATE importer
			SET jobEnd=".$microtime.",
			jobLastUpdate=".$microtime.",
			jobStatistics='" .serialize($data)."' WHERE id=" . $this->jobId;
		
		\Slim\Slim::getInstance()->db->query($query);
		$this->jobId = 0;
		$this->lastJobStatusUpdate = $microtime;
		return;
	}
	
	private function calculateSpeed(&$data) {
		$data['itemCountChecked'] = $this->itemCountChecked;
		$data['itemCountProcessed'] = $this->itemCountProcessed;
		$data['itemCountTotal'] = $this->itemCountTotal;
		
		// this spped will be relevant for javascript animated progressbar
		$data['speedPercentPerSecond'] = 0;
		
		
		$data['runtimeSeconds'] = $data['microTimestamp'] - $this->jobBegin;
		if($this->itemCountChecked > 0 && $this->itemCountTotal > 0) {
			$seconds = microtime(TRUE) - $this->jobBegin;
			
			$itemsPerMinute = $this->itemCountChecked/$seconds*60;
			$data['speedItemsPerMinute'] = floor($itemsPerMinute);
			$data['speedItemsPerHour'] = floor($itemsPerMinute*60);
			$data['speedPercentPerSecond'] = ($itemsPerMinute/60)/($this->itemCountTotal/100);
			
			$minutesRemaining = ($this->itemCountTotal - $this->itemCountChecked) / $itemsPerMinute;
			if($data['progressPercent'] === 0) {
				$data['progressPercent'] = floor($this->itemCountChecked / ($this->itemCountTotal/100));
				// make sure we don not display 100% in case it is not finished
				$data['progressPercent'] = ($data['progressPercent']>99) ? 99 : $data['progressPercent'];
				
				$data['estimatedRemainingSeconds'] = round($minutesRemaining*60);
				$data['estimatedTotalRuntime'] = round($this->itemCountTotal/$itemsPerMinute*60);
			} else {
				$data['estimatedRemainingSeconds'] = 0;
				$data['estimatedTotalRuntime'] = $data['runtimeSeconds'];
			}
			

		}
	}
	
	// only for development purposes
	public function tempResetMigrationPhase() {
		$db = \Slim\Slim::getInstance()->db;
		cliLog('truncating alle tables with migrated data', 1, 'red');
		$queries = array(
			"TRUNCATE artist;",
			"TRUNCATE genre;",
			"TRUNCATE track;",
			"TRUNCATE label;",
			"TRUNCATE album;",
			"INSERT INTO `artist` VALUES ('1', 'Unknown Artist', '', 'unknownartist', 0, 0);",
			"INSERT INTO `artist` VALUES ('2', 'Various Artists', '', 'variousartists', 0, 0);",
			"INSERT INTO `genre` VALUES ('1', 'Unknown', '0', 'unknown', 0, 0);",
			"INSERT INTO `label` VALUES ('1', 'Unknown Label', 'unknownlabel', 0, 0);"
		);
		foreach($queries as $query) {
			$db->query($query);
		}
	}
	
	
	/** 
	 * @return array 'directoryHash' => 'most-recent-timestamp' 
	 */
	public static function getMigratedAlbumTimstamps() {
		$db = \Slim\Slim::getInstance()->db;
		$timestampsMysql = array();
		
		$query = "SELECT relativePathHash,filemtime FROM album";
		$result = $db->query($query);
		while($record = $result->fetch_assoc()) {
			$timestampsMysql[ $record['relativePathHash'] ] = $record['filemtime'];
		}
		
		return $timestampsMysql;
	}

	public static function getMigratedTrackTimstamps() {
		$db = \Slim\Slim::getInstance()->db;
		$timestampsMysql = array();
		
		$query = "SELECT relativePathHash,filemtime FROM track";
		$result = $db->query($query);
		while($record = $result->fetch_assoc()) {
			$timestampsMysql[ $record['relativePathHash'] ] = $record['filemtime'];
			
		}
		return $timestampsMysql;
	}

	public function migrateRawtagdataTable($resetMigrationPhase = FALSE) {
		
		# only for development
		# TODO: make this step optional controllable via gui
		if($resetMigrationPhase === TRUE) {
			$this->tempResetMigrationPhase();
		}
		
		$this->jobPhase = 3;
		$this->beginJob(array(
			'msg' => "migrateRawtagdataTable"
		), __FUNCTION__);
		$app = \Slim\Slim::getInstance();
		
		$migratedAlbumTimstamps = self::getMigratedAlbumTimstamps();
		$migratedTrackTimstamps = self::getMigratedTrackTimstamps();
		$triggerAlbumMigration = FALSE;

		$migratedAlbums = 0;
		$previousAlbum = new \Slimpd\AlbumMigrator();
		
		$query = "SELECT count(id) AS itemCountTotal FROM rawtagdata";
		$this->itemCountTotal = (int) $app->db->query($query)->fetch_assoc()['itemCountTotal'];
		
		$query = "SELECT * FROM rawtagdata ORDER BY relativeDirectoryPathHash ";
		$result = $app->db->query($query);
		while($record = $result->fetch_assoc()) {
			$this->itemCountChecked++;
			if($this->itemCountChecked === 1) {
				$previousAlbum->setDirectoryHash($record['relativeDirectoryPathHash']);
			}
			
			if($record['relativeDirectoryPathHash'] !== $previousAlbum->getDirectoryHash() ) {
				
				// decide if we have to process album or if we can skip it
				if(isset($migratedAlbumTimstamps[ $record['relativeDirectoryPathHash'] ]) === FALSE) {
					cliLog('album does NOT exist in migrated data. migrating: ' . $record['relativeDirectoryPath'], 5);
					$triggerAlbumMigration = TRUE;
				} else {
					if($migratedAlbumTimstamps[ $record['relativeDirectoryPathHash'] ] < $record['directoryMtime']) {
						cliLog('dir-timestamp raw is more recent than migrated. migrating: ' . $record['relativeDirectoryPath'], 5);
						$triggerAlbumMigration = TRUE;
					}
				}
				
				if($triggerAlbumMigration === TRUE) {
					$previousAlbum->run();
					$migratedAlbums++;
				} else {
					cliLog('skipping migration for: ' . $record['relativeDirectoryPath'], 5);
				}
				unset($previousAlbum);
				$previousAlbum = new \Slimpd\AlbumMigrator();
				$previousAlbum->setDirectoryHash($record['relativeDirectoryPathHash']);
				$triggerAlbumMigration = FALSE;
			}
			
			$this->updateJob(array(
				'currentItem' => $record['relativePath'],
				'migratedAlbums' => $migratedAlbums
			));
			
			
			
			
			// decide if we have to process album based on single-track-change or if we can skip it
			if(isset($migratedTrackTimstamps[ $record['relativePathHash'] ]) === FALSE) {
				cliLog('track does NOT exist in migrated data. migrating: ' . $record['relativeDirectoryPath'], 5);
				$triggerAlbumMigration = TRUE;
			} else {
				if($migratedTrackTimstamps[ $record['relativePathHash'] ] < $record['filemtime']) {
					cliLog('track-imestamp raw is more recent than migrated. migrating: ' . $record['relativeDirectoryPath'], 5);
					$triggerAlbumMigration = TRUE;
				}
			}
				
			
			$previousAlbum->addTrack($record);
			
			cliLog("#" . $this->itemCountChecked . " " . $record['relativePath'],2);
			
			// dont forget to check the last one
			if($this->itemCountChecked === $this->itemCountTotal && $this->itemCountTotal > 1) {
				// decide if we have to process album or if we can skip it
				if(isset($migratedAlbumTimstamps[ $record['relativeDirectoryPathHash'] ]) === FALSE) {
					cliLog('album does NOT exist in migrated data. migrating: ' . $record['relativeDirectoryPath'], 5);
					$triggerAlbumMigration = TRUE;
				} else {
					if($migratedAlbumTimstamps[ $record['relativeDirectoryPathHash'] ] < $record['directoryMtime']) {
						cliLog('dir-timestamp raw is more recent than migrated. migrating: ' . $record['relativeDirectoryPath'], 5);
						$triggerAlbumMigration = TRUE;
					}
				}
				
				if($triggerAlbumMigration === TRUE) {
					$previousAlbum->run();
					$migratedAlbums++;
				} else {
					cliLog('skipping migration for: ' . $record['relativeDirectoryPath'], 5);
				}
				unset($previousAlbum);
			}
		}

		$this->finishJob(array(
			'msg' => 'migrated ' . $this->itemCountChecked . ' files',
			'migratedAlbums' => $migratedAlbums
		), __FUNCTION__);
	}


	
	
	public function processMpdDatabasefile() {
		$this->jobPhase = 1;
		$app = \Slim\Slim::getInstance();
		$this->beginJob(array(
			'msg' => $app->ll->str('importer.processing.mpdfile')
		), __FUNCTION__);
		
		// check if mpd_db_file exists
		if(is_file($app->config['mpd']['dbfile']) == FALSE || is_readable($app->config['mpd']['dbfile']) === FALSE) {
			$msg = $app->ll->str('error.mpd.dbfile', array($app->config['mpd']['dbfile']));
			cliLog($msg, 1, 'red', TRUE);
			$this->finishJob(array(
				'msg' => $msg
			));
			$app->stop();
		}
		
		# TODO: check if mpd-database file is plaintext or gzipped or sqlite
		# TODO: processing mpd-sqlite db or gzipped db
		
		$this->updateJob(array(
			'msg' => $app->ll->str('importer.collecting.mysqlitems')
		));
		
		
		// get timestamps of all tracks and directories from mysql database
		$fileTimestampsMysql = array();
		$directoryTimestampsMysql = array();
		
		// get all existing track-ids to determine orphans
		$deadMysqlFiles = array();
		
		$query = "SELECT id, relativePathHash, relativeDirectoryPathHash, filemtime, directoryMtime FROM rawtagdata;";
		$result = $app->db->query($query);
		while($record = $result->fetch_assoc()) {
			$deadMysqlFiles[ $record['relativePathHash'] ] = $record['id'];
			$fileTimestampsMysql[ $record['relativePathHash'] ] = $record['filemtime'];
			
			
			// get the oldest directory timestamp stored in rawtagdata
			if(isset($directoryTimestampsMysql[ $record['relativeDirectoryPathHash'] ]) === FALSE) {
				$directoryTimestampsMysql[ $record['relativeDirectoryPathHash'] ] = 9999999999;
			}
			if($record['directoryMtime'] < $directoryTimestampsMysql[ $record['relativeDirectoryPathHash'] ]) {
				$directoryTimestampsMysql[ $record['relativeDirectoryPathHash'] ] = $record['directoryMtime']; 
			}
		}
		#print_r($directoryTimestampsMysql); die();
		
		$dbfile = explode("\n", file_get_contents($app->config['mpd']['dbfile']));
		$currentDirectory = "";
		$currentSong = "";
		$currentPlaylist = "";
		$currentSection = "";
		$dirs = array();
		
		//$songs = array();
		//$playlists = array();
		
		$dircount = 0;
		
		$unmodifiedFiles = 0;
		
		$level = -1;
		$opendirs = array();
		
		// set initial attributes
		$mtime = 0;
		$time = 0;
		$artist = '';
		$title = '';
		$track = '';
		$album = '';
		$date = '';
		$genre = '';
		$mtimeDirectory = 0;
		
		foreach($dbfile as $line) {
			if(trim($line) === "") {
				continue; // skip empty lines
			}
			
			$attr = explode (": ", $line, 2);
			array_map('trim', $attr);
			if(count($attr === 1)) {
				switch($attr[0]) {
					case 'info_begin': break;
					case 'info_end': break;
					case 'playlist_end':
						// TODO: what to do with playlists fetched by mpd-database???
						//$playlists[] = $currentDirectory . DS . $currentPlaylist;
						$currentPlaylist = "";
						$currentSection = "";
						break;
					case 'song_end':
						$this->itemCountChecked++;
						
						// single music files directly in mpd-musicdir-root must not get a leading slash
						$dirRelativePath = ($currentDirectory === '') ? '' : $currentDirectory . DS;
						$directoryHash = getFilePathHash($dirRelativePath);
						
						// further we have to read directory-modified-time manually because there is no info
						// about mpd-root-directory in mpd-database-file
						$mtimeDirectory = ($currentDirectory === '')
							? filemtime($app->config['mpd']['musicdir'])
							:  $mtimeDirectory;
						
						$trackRelativePath =  $dirRelativePath . $currentSong;
						$trackHash = getFilePathHash($trackRelativePath);
						
						
						
						$this->updateJob(array(
							'msg' => 'processed ' . $this->itemCountChecked . ' files',
							'currentfile' => $currentDirectory . DS . $currentSong,
							'deadfiles' => count($deadMysqlFiles),
							'unmodified_files' => $unmodifiedFiles
						));
						
						$insertOrUpdateRawtagData = FALSE;
						// compare timestamps of mysql-database-entry(rawtagdata) and mpddatabase
						if(isset($fileTimestampsMysql[$trackHash]) === FALSE) {
							cliLog('mpd-file does not exist in rawtagdata: ' . $trackRelativePath, 5);
							$insertOrUpdateRawtagData = TRUE;
						} else {
							if($mtime > $fileTimestampsMysql[$trackHash]) {
								cliLog('mpd-file timestamp is newer: ' . $trackRelativePath, 5);
								$insertOrUpdateRawtagData = TRUE;
							}
						}
						
						if(isset($directoryTimestampsMysql[$directoryHash]) === FALSE) {
							cliLog('mpd-directory does not exist in rawtagdata: ' . $dirRelativePath, 5);
							$insertOrUpdateRawtagData = TRUE;
						} else {
							if($mtimeDirectory > $directoryTimestampsMysql[$directoryHash]) {
								cliLog('mpd-directory timestamp is newer: ' . $trackRelativePath, 5);
								$insertOrUpdateRawtagData = TRUE;
							}
						}
						
						if($insertOrUpdateRawtagData === FALSE) {
							// track has not been modified - no need for updating
							unset($fileTimestampsMysql[$trackHash]);
							unset($deadMysqlFiles[$trackHash]);
							$unmodifiedFiles++;
						} else {
							
							$t = new Rawtagdata();
							if(isset($deadMysqlFiles[$trackHash])) {
								$t->setId($deadMysqlFiles[$trackHash]);
								// file is alive - remove it from dead items
								unset($deadMysqlFiles[$trackHash]);
							}

							$t->setArtist($artist);
							$t->setTitle($title);
							$t->setAlbum($album);
							$t->setGenre($genre);
							$t->setYear($date);
							$t->setTrackNumber($track);
								
							$t->setRelativePath($trackRelativePath);
							$t->setRelativePathHash($trackHash);
							$t->setRelativeDirectoryPath($dirRelativePath);
							$t->setRelativeDirectoryPathHash($directoryHash);
							
							$t->setDirectoryMtime($mtimeDirectory);
							
							$t->setFilemtime($mtime);
							$t->setMiliseconds($time*1000);
							
							$t->setlastScan(0);
							
							$t->setImportStatus(1);
							$t->update();
							
							unset($t);
							
							$this->itemCountProcessed++;
						}

						cliLog("#" . $this->itemCountChecked . " " . $currentDirectory . DS . $currentSong, 2);
						
						//$songs[] = $currentDirectory . DS . $currentSong;
						$currentSong = "";
						$currentSection = "";
						
						// reset song attributes
						$mtime = 0;
						$time = 0;
						$artist = '';
						$title = '';
						$track = '';
						$album = '';
						$date = '';
						$genre = '';
		
						break;
					default: break;
				}
			}
			if(isset($attr[1]) === TRUE) {
				// believe it or not - some people store html in their tags
				$attr[1] = preg_replace('!\s+!', ' ', (trim(strip_tags($attr[1]))));
			}
			switch($attr[0]) {
				case 'directory':
					$currentSection = "directory";
					break;
				case 'begin':
					$level++;
					$opendirs = explode(DS, $attr[1]);
					$currentSection = "directory";
					$currentDirectory = $attr[1];
					break;
				case 'song_begin':
					$currentSection = "song";
					$currentSong = $attr[1];
					break;
				case 'playlist_begin':
					$currentSection = "playlist";
					$currentPlaylist = $attr[1];
					break;
				case 'end':
					$level--;
					//$dirs[$currentDirectory] = TRUE;
					$dircount++;
					array_pop($opendirs);
					$currentDirectory = join(DS, $opendirs);
					$currentSection = "";
					
					break;
					
				case 'mtime' :
					if($currentSection == "directory") {
						$mtimeDirectory = $attr[1];
					} else {
						$mtime = $attr[1];
					}
					break;
				case 'Time'  : $time   = $attr[1]; break;
				case 'Artist': $artist = $attr[1]; break;
				case 'Title' : $title  = $attr[1]; break;
				case 'Track' : $track  = $attr[1]; break;
				case 'Album' : $album  = $attr[1]; break;
				case 'Genre' : $genre  = $attr[1]; break;
				case 'Date'  : $date   = $attr[1]; break;
			}
		}

		// delete dead items in table:rawtagdata & table:track
		if(count($deadMysqlFiles) > 0) {
			\Slimpd\Rawtagdata::deleteRecordsByIds($deadMysqlFiles);
			\Slimpd\Track::deleteRecordsByIds($deadMysqlFiles);
		}

		


		cliLog("dircount: " . $dircount);
		cliLog("songs: " . $this->itemCountChecked);
		//cliLog("playlists: " . count($playlists));
		
		# TODO: flag&handle dead items in mysql-database
		//cliLog("dead dirs: " . count($deadMysqlDirectories));
		cliLog("dead songs: " . count($deadMysqlFiles));
		#print_r($deadMysqlFiles);
		
		$this->itemCountTotal = $this->itemCountChecked;
		$this->finishJob(array(
			'msg' => 'processed ' . $this->itemCountChecked . ' files',
			'directorycount' => $dircount,
			'deletedRecords' => count($deadMysqlFiles),
			'unmodified_files' => $unmodifiedFiles
		), __FUNCTION__);
		
		// destroy large arrays
		unset($deadMysqlFiles);
		unset($fileTimestampsMysql);
		unset($directoryTimestampsMysql);
		
		return;
	}

	public function destroyExtractedImageDupes() {
		$this->jobPhase = 4;
		$this->beginJob(array(
			'msg' => "searching extracted image-dupes in database ..."
		), __FUNCTION__);
		$app = \Slim\Slim::getInstance();
		
		$query = "SELECT count(id) AS itemCountTotal FROM  bitmap WHERE error=0 AND trackId > 0";
		$this->itemCountTotal = (int) $app->db->query($query)->fetch_assoc()['itemCountTotal'];
		$query = "
			SELECT
				id,
				trackId,
				embedded,
				CONCAT(albumId, '.', width, '.', height, '.', filesize) as dupes,
				relativePath,
				filesize
			FROM  bitmap
			WHERE error=0 AND embedded=1
			ORDER BY albumId;";
		$result = $app->db->query($query);
		
		$images = array();
		$previousKey = '';

		$deletedFilesize = 0;
		
		$msgKeep = $app->ll->str('importer.image.keep');
		$msgDestroy = $app->ll->str('importer.image.destroy');
		$msgProcessing = $app->ll->str('importer.image.dupecheck.processing');
		while ($record = $result->fetch_assoc()) {
			$this->updateJob(array(
				'msg' => $msgProcessing,
				'currentItem' => $record['relativePath']
			));
			$this->itemCountChecked++;
			if($this->itemCountChecked === 1) {
				$previousKey = $record['dupes'];
				cliLog($app->ll->str('importer.image.keep', array($record['relativePath'])), 3);
				continue;
			}
			if($record['dupes'] === $previousKey) {
				$msg = $app->ll->str('importer.image.destroy', array($record['relativePath']));
				$bitmap = new \Slimpd\Bitmap();
				$bitmap->setId($record['id']);
				$bitmap->setTrackId($record['trackId']);
				$bitmap->setEmbedded($record['embedded']);
				$bitmap->setRelativePath($record['relativePath']);
				$bitmap->destroy();
				
				$this->itemCountProcessed++;
				$deletedFilesize += $record['filesize'];
			} else {
				$msg = $app->ll->str('importer.image.keep', array($record['relativePath']));
			}
			cliLog($msg, 3);
			$previousKey = $record['dupes'];
		}
		
		$msg = $app->ll->str('importer.destroyimages.result', array($this->itemCountProcessed, formatByteSize($deletedFilesize)));
		cliLog($msg);
		
		$this->finishJob(array(
			'msg' => $msg,
			'deletedFileSize' => formatByteSize($deletedFilesize)
		), __FUNCTION__);
		return;
	}

	public function fixAlbumGenres($forceAllAlbums = FALSE) {
		$app = \Slim\Slim::getInstance();
		
		// reset
		// UPDATE album SET importStatus=2
		
		
		$this->jobPhase = 6;
		$this->beginJob(array(
			'currentItem' => "fetching all track-genres for inserting into table:album ..."
		), __FUNCTION__);
		
		$query = "SELECT count(id) AS itemCountTotal FROM album" .(($forceAllAlbums === FALSE) ? " WHERE album.importStatus<3 " : "");
		$this->itemCountTotal = (int) $app->db->query($query)->fetch_assoc()['itemCountTotal'];
		
		// collect all genreIds provided by tracks
		$genreIdsFromTracks = '';
		$query = "
			SELECT
				track.albumId,
				track.genreId AS genreId
			FROM track
			LEFT JOIN album ON track.albumId=album.id
			".(($forceAllAlbums === FALSE) ? " WHERE album.importStatus<3 " : "")."
			ORDER BY track.albumId;
		";
		$result = $app->db->query($query);
		
		$counter = 0;
		$previousAlbumId = 0;
		
		while($record = $result->fetch_assoc()) {
			$counter++;
			
			$this->updateJob(array(
				'updatedAlbums' => $this->itemCountProcessed,
				'currentItem' => 'albumId: ' . $record['albumId']
			));
			
			
			if($counter === 1) {
				$genreIdsFromTracks = '';
				$previousKey = $record['albumId'];
			}
			if($record['albumId'] == $previousKey) {
				$genreIdsFromTracks .= $record['genreId'] . ',';
			} else {
				$album = new \Slimpd\Album();
				$album->setId($previousKey);
				$album->setImportStatus(3);
				
				// extract unique genreIds ordered by relevance
				$album->setGenreId(
					join(
						",",
						uniqueArrayOrderedByRelevance(
							trimExplode(",", $genreIdsFromTracks, TRUE)
						)
					)
				);
				cliLog($app->ll->str('importer.fixgenre.msg', array($album->getId(), $album->getGenreId())), 3);
				$album->update();
				$this->itemCountChecked++;
				$this->itemCountProcessed++;
				unset($album);
				$genreIdsFromTracks = '';
			}
			$previousKey = $record['albumId'];
		}
		$this->finishJob(array(
			'updatedAlbums' => $this->itemCountProcessed,
		), __FUNCTION__);
		return;
	}

	public function fixAlbumLabels($forceAllAlbums = FALSE) {
		$app = \Slim\Slim::getInstance();
		$this->jobPhase = 8;
		
		// reset phase 7
		// UPDATE album SET importStatus=3
		
		
		$this->beginJob(array(
			'currentItem' => "fetching all track-labels for inserting into table:album ..."
		), __FUNCTION__);
		
		$query = "SELECT count(id) AS itemCountTotal FROM album" .(($forceAllAlbums === FALSE) ? " WHERE album.importStatus<4 " : "");
		$this->itemCountTotal = (int) $app->db->query($query)->fetch_assoc()['itemCountTotal'];
		
		// collect all genreIds provided by tracks
		$labelIdsFromTracks = '';
		$query = "
			SELECT
				track.albumId,
				track.labelId AS labelId
			FROM track
			LEFT JOIN album ON track.albumId=album.id
			".(($forceAllAlbums === TRUE) ? " WHERE album.importStatus<4 " : "")."
			ORDER BY track.albumId;
		";
		$result = $app->db->query($query);
		
		$counter = 0;
		$previousAlbumId = 0;
		
		while($record = $result->fetch_assoc()) {
			$counter++;
			
			$this->updateJob(array(
				'updatedAlbums' => $this->itemCountProcessed,
				'currentItem' => 'albumId: ' . $record['albumId']
			));
			
			if($counter === 1) {
				$labelIdsFromTracks = '';
				$previousKey = $record['albumId'];
			}
			if($record['albumId'] == $previousKey) {
				$labelIdsFromTracks .= $record['labelId'] . ',';
			} else {
				$album = new \Slimpd\Album();
				$album->setId($previousKey);
				$album->setImportStatus(4);
				
				// extract unique labelIds ordered by relevance
				$album->setLabelId(
					join(
						",",
						uniqueArrayOrderedByRelevance(
							trimExplode(",", $labelIdsFromTracks, TRUE)
						)
					)
				);
				cliLog($app->ll->str('importer.fixlabel.msg', array($album->getId(), $album->getLabelId())), 3);
				$album->update();
				$this->itemCountChecked++;
				unset($album);
				$labelIdsFromTracks = '';
			}
			$previousKey = $record['albumId'];
		}
		$this->finishJob(array(
			'updatedAlbums' => $this->itemCountProcessed
		), __FUNCTION__);
		return;
	}

	public function updateCounterCache() {
		$app = \Slim\Slim::getInstance();
		$this->jobPhase = 9;
		$this->beginJob(array(
			'currentItem' => "fetching all track-labels for inserting into table:album ..."
		), __FUNCTION__);
		foreach(array('album', 'artist', 'genre', 'label') as $table) {
			$query = "SELECT count(id) AS itemCountTotal FROM " . $table;
			$this->itemCountTotal += $app->db->query($query)->fetch_assoc()['itemCountTotal'];
		}
		
		// collect all genreIds, labelIds, artistIds, remixerIds, featuringIds provided by tracks
		$tables = array(
			'Album' => array(),
			'Artist' => array(),
			'Genre' => array(),
			'Label' => array()
		);
		$genres = array();
		$labels = array();
		$artists = array();
		
		
		// to be able to display a progress status
		$all = array();
		
		$query = "SELECT id,albumId,artistId,remixerId,featuringId,genreId,labelId FROM track";
		$result = $app->db->query($query);
		
		
		while($record = $result->fetch_assoc()) {
			$all['al' . $record['albumId']] = NULL;
			
			$tables['Album'][ $record['albumId'] ]['tracks'][ $record['id'] ] = NULL;
			
			$this->itemCountChecked = count($all);
			
			$this->updateJob(array(
				'currentItem' => 'trackId: ' . $record['id']
			));
			
			foreach(array('artistId', 'remixerId', 'featuringId') as $itemIds) {
				$itemIds = trimExplode(",", $record[$itemIds], TRUE);
				foreach($itemIds as $itemId) {
					$tables['Artist'][$itemId]['tracks'][ $record['id'] ] = NULL;
					$tables['Artist'][$itemId]['albums'][ $record['albumId'] ] = NULL;
					$all['ar' . $itemId] = NULL;
				}
			}
			$itemIds = trimExplode(",", $record['genreId'], TRUE);
			foreach($itemIds as $itemId) {
				$tables['Genre'][$itemId]['tracks'][ $record['id'] ] = NULL;
				$tables['Genre'][$itemId]['albums'][ $record['albumId'] ] = NULL;
				$all['ge' . $itemId] = NULL;
			}
			
			$itemIds = trimExplode(",", $record['labelId'], TRUE);
			foreach($itemIds as $itemId) {
				$tables['Label'][$itemId]['tracks'][ $record['id'] ] = NULL;
				$tables['Label'][$itemId]['albums'][ $record['albumId'] ] = NULL;
				$all['la' . $itemId] = NULL;
			}
			
		}

		
		foreach($tables as $className => $tableData) {
			foreach($tableData as $itemId => $data) {
				
				$classPath = "\\Slimpd\\" . $className;
				$item = new $classPath();
				$item->setId($itemId);
				$item->setTrackCount( count($data['tracks']) );
				
				$msg = "updating ".$className.": " . $itemId . " with trackCount:" .  $item->getTrackCount();
				if($className !== 'Album') {
					$item->setAlbumCount( count($data['albums']) );
					$msg .= ", albumCount:" .  $item->getAlbumCount();
				}
				$item->update();
				$this->itemCountProcessed++;
				$this->itemCountChecked = count($all);
				$this->updateJob(array(
					'currentItem' => $msg
				));
				
				cliLog($msg, 3);
			}
		}
		unset($tables);
		$this->finishJob(array(), __FUNCTION__);
		return;
	}


	public function setDefaultLabels($forceAllAlbums = FALSE) {
		$app = \Slim\Slim::getInstance();
		$this->jobPhase = 7;
		
		// check config
		if(isset($app->config['label-parent-directories']) === FALSE) {
			cliLog('aborting setDefaultLabels() no label directories configured',2);
			return;
		}
		// validate config
		$foundValidDirectories = FALSE;
		foreach($app->config['label-parent-directories'] as $dir) {
			if(is_dir($app->config['mpd']['musicdir'] . $dir) === FALSE) {
				cliLog('WARNING: label-parent-directory ' . $dir . ' does not exist', 2, 'yellow',2);
			} else {
				$foundValidDirectories = TRUE;
			}
		}
		
		if($foundValidDirectories === FALSE) {
			cliLog('aborting setDefaultLabels() no valid label directories configured', 2, 'red');
			return;
		}

		
		$this->beginJob(array(
			'currentItem' => "fetching all track-labels for inserting into table:album ..."
		), __FUNCTION__);
		
		foreach($app->config['label-parent-directories'] as $labelDir) {
			if(substr($labelDir, -1) !== DS) { $labelDir .= DS; } // append trailingSlash
			$query = "SELECT count(id) as itemCountTotal FROM track WHERE relativePath LIKE \"". $labelDir ."%\" ";
			$this->itemCountTotal += $app->db->query($query)->fetch_assoc()['itemCountTotal'];
			$msg = "found " . $this->itemCountTotal . " to check"; 
			cliLog($msg, 2);
		}
		
		$updatedTracks = 0;
		
		foreach($app->config['label-parent-directories'] as $labelDir) {
			if(substr($labelDir, -1) !== DS) { $labelDir .= DS; } // append trailingSlash
			
			$query = "SELECT id, labelId, relativePath FROM track WHERE relativePath LIKE \"". $labelDir ."%\"";
			$result = $app->db->query($query);
			$counter = 0;
			$previousLabelString = "";
			$existingLabelIdsInDir= array();
			
			$updateTrackIds = array();
			
			while($t = $result->fetch_assoc()) {
				$labelDirname = explode( DS, substr($t['relativePath'], strlen($labelDir)), 2);
				$labelDirname = $labelDirname[0];
				
				$counter++;
				$this->itemCountChecked++;
				
				if($t['labelId'] == '' || $t['labelId'] == '1') {
					$updateTrackIds[] = $t['id'];
					$updatedTracks++;
				}
				
				if($counter === 1) {
					$previousLabelString = $labelDirname;
					$existingLabelIdsInDir = array_merge($existingLabelIdsInDir, trimExplode(",", $t['labelId'], TRUE));
					continue;
				}
				
				if($labelDirname != $previousLabelString) {
					// extract the most used label-id
					$existingLabelIdsInDir = (uniqueArrayOrderedByRelevance($existingLabelIdsInDir));
					
					$foundMatchingDatabaseLabelId = FALSE;
					
					// remove "Unknown Label (id=1)"
					if(($key = array_search('1', $existingLabelIdsInDir)) !== false) {
					    unset($existingLabelIdsInDir[$key]);
					}
					
					if(count($existingLabelIdsInDir) > 0) {
						$existingLabelId = array_shift($existingLabelIdsInDir);
						$databaseLabel = \Slimpd\Label::getInstanceByAttributes(array('id' => $existingLabelId));
						if(az09($previousLabelString) == $databaseLabel->getAz09()) {
							$foundMatchingDatabaseLabelId = $databaseLabel->getId();
							// everything is fine - we already have a label id
							#$msg = "GOOD: directory: " . $previousLabelString . " matches database-label:" . $databaseLabel->getTitle();
							#echo $msg . "\n";
						} else {
							// try to replace common substitutions
							$teststring = str_replace(
								array("_", " and ", " point " ),
								array(" ", " & ", " . " ),
								$previousLabelString
							);
							if(az09($teststring) == $databaseLabel->getAz09()) {
								$foundMatchingDatabaseLabelId = $databaseLabel->getId();
								#$msg = "GOOD: directory: " . $previousLabelString . " matches database-label:" . $databaseLabel->getTitle();
							} else {
								#$msg = "BAD: directory: " . $previousLabelString . " does NOT match database-label:" . $databaseLabel->getTitle();
								#echo $msg . "\n";
								#print_r($existingLabelIdsInDir);
								
							}
						}
					}

					if($foundMatchingDatabaseLabelId === FALSE) {
						$newLabelString = ucwords(str_replace("_", " ", $previousLabelString));
						$msg = "generating labelstring from dirname: " . $newLabelString;
						$foundMatchingDatabaseLabelId = join(",", \Slimpd\Label::getIdsByString($newLabelString));
					} else {
						$msg = "updating with ID: " . $foundMatchingDatabaseLabelId;
					}
					cliLog($msg,3);
					
					// update all tracks with labelId's
					if(count($updateTrackIds) > 0) {
						$query = "UPDATE track SET labelId=\"" . $foundMatchingDatabaseLabelId . "\"
							WHERE id IN (".join(",", $updateTrackIds).")";
						$app->db->query($query);
					}
					
					$this->updateJob(array(
						'updatedTracks' => $updatedTracks,
						'currentItem' => 'directory: ' . $t['relativePath'] . ' ' . $msg
					));
					
					$previousLabelString = $labelDirname;
					$existingLabelIdsInDir = trimExplode(",", $t['labelId'], TRUE);
					$updateTrackIds = array();
				}
				$this->itemCountProcessed++;
			}
		}
		
		$this->finishJob(array(
			'updatedTracks' => $updatedTracks
		), __FUNCTION__);
		return;
	}

	// TODO: where to move pythonscript?
	// TODO: general wrapper for shell-executing stuff
	public static function extractAudioFingerprint($absolutePath) {
		
		$ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
		switch($ext) {
			case 'mp3':
				$cmd =  \Slim\Slim::getInstance()->config['modules']['bin_python_2'] .
					' ' . APP_ROOT . "mp3md5_mod.py -3 " . escapeshellarg($absolutePath);
				break;
			case 'flac':
				die('# TODO: try to read flac fingerprint from tags via getId3-lib');
				return FALSE;
				break;
			default:
				# TODO: can we get md5sum with php in a performant way?
				$cmd = '/usr/bin/md5sum -b ' . escapeshellarg($absolutePath) . ' | awk \'{ print $1 }\'';
		}
		#echo $cmd . "\n";
		$response = exec($cmd);
		if(preg_match("/^[0-9a-f]{32}$/", $response)) {
			#echo $response . "\n"; die();
			return $response;
		}
		return FALSE;
	} 



	
	public function extractAllMp3FingerPrints($forceAllAlbums = FALSE) {
		$app = \Slim\Slim::getInstance();
		$this->jobPhase = 10;
		
		
		if($app->config['modules']['enable_fingerprints'] !== '1') {
			return;
		}
		// reset phase 
		// UPDATE rawtagdata SET fingerprint="" WHERE audioDataFormat="mp3"
		
		
		$this->beginJob(array(
			'currentItem' => "fetching mp3 files with missing fingerprint attribute"
		), __FUNCTION__);
		
		$query = "
			SELECT count(id) AS itemCountTotal
			FROM rawtagdata
			WHERE audioDataFormat='mp3' AND fingerprint=''";
		$this->itemCountTotal = (int) $app->db->query($query)->fetch_assoc()['itemCountTotal'];
		
		
		$query = "
			SELECT id, relativePath
			FROM rawtagdata
			WHERE audioDataFormat='mp3' AND fingerprint=''";
			
		$result = $app->db->query($query);

		while($record = $result->fetch_assoc()) {
			$this->itemCountChecked++;
			
			$this->updateJob(array(
				'currentItem' => 'albumId: ' . $record['relativePath']
			));
			
			$this->itemCountProcessed++;
			
			$fullPath = $app->config['mpd']['musicdir'] . $record['relativePath'];
			if(is_file($fullPath) == FALSE || is_readable($fullPath) === FALSE) {
				cliLog("ERROR: fileaccess " . $record['relativePath'], 1, 'red');
				continue;
			}
			
			if($fp = self::extractAudioFingerprint($fullPath)) {
				$i = new \Slimpd\Rawtagdata();
				$i->setId($record['id']);
				$i->setFingerprint($fp);
				$i->update();
				
				$i = new \Slimpd\Track();
				$i->setId($record['id']);
				$i->setFingerprint($fp);
				$i->update();
				
				cliLog("fingerprint: " . $fp . " for " . $record['relativePath'],3);
			} else {
				cliLog("ERROR: regex fingerprint result " . $record['relativePath'], 1, 'red');
				continue;
			}
		}
		$this->finishJob(array(), __FUNCTION__);
		return;
	}




	public function extractInfosFromAlbumFiles() {
		
		# TODO:  tons of guessing out of messy common shemes like:
		
		// Chemical_Brothers_-_Augumented_-1998__MTM/17_No_Name.mp3
		// Sasha_Donatello_Knox_Kastis_Torrau_Arnas_D_O_Smoke_Cone_(Lnoe013)/Sasha_Donatello_Knox_Kastis_Torrau_Arnas_D-Smoke_Cone_(UNER_Live_Club_Mix).mp3
		// Maceo_Plex-Galactic_Cinema_(DJ-Kicks)/Maceo_Plex-Mind_On_Fire_(Original_Mix).mp3
		// 01. Artist A -Tracktitle.ext
		// Lauschgift/07-Tokio-Paris.mp3
		// adiemus_2-cantata_mundi_2002/112_chorale_vi_and_cantus_-_song_of_aeolus.mp3
		// enigma-the_screen_behind_the_mirror_2000/103_enigma-gravity_of_love.mp3
		// Kenny-KENNY01-Kenny-200X/A1-Sulfurex-Point_Break_(Mutant_Metallique_Remix).wav
		// Dezakore-DEZAK001-Various_Artists-Dezak-YYYY/A2-MSD-Swinger.wav
		// Moloko/Extract_from_CD_4-Track_4.mp3
		// techno_MIXES/DJ_Dave_Clarke_and_Umek-Live___Convex_Prag.mp3
		// red_hot_chili_peppers-blood_sugar_sex_magik-NEU/red_hot_chili_peppers-suck_my_kiss.mp3
		// mixed/Die_fantastischen_Vier-Das_Kind_vor_dem_Euch_alle_warnten.mp3
		// nine_inch_nails-pretty_hate_machine/Nine_Inch_Nails-Pretty_Hate_Machine-05-Something_I_Can.mp3
		// beatles_yellow_submarine/Track-14.mp3
		// kaipron/Aria6_(Aria_Giovanni).asf
		
		// fat_boy_slim/05-grace-i_want_to_live_oakenfold_and_osborne_mix.mp3
		
		// Exciter/Depeche_Mode-13-Goodnight_Lovers.mp3
		
		// Only_ElectroHouse_Vol.16/Da_Silva-Wiretrip_(Edson_Pride_Darknite_Mix).mp3
		
		// Freshlub_Music_Releases_Of_Electrohouse_16.12.2008/X-Aro_Project-Interference_(Original_Mix).mp3
		
		// /Robert_Plant_(1984.02.08)_Open_Arms_(Midas_Touch)_(SBD_320)/Disc_2_(of_2)/3-Slow_Dancer.mp3
		
	}



















































}
