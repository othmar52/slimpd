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
	protected $directoryImages = array();
	protected $jobId;					// mysql record id
	protected $jobPhase;				// numeric index
	protected $jobBegin;				// tstamp
	protected $jobStatusInterval = 5; 	// seconds
	protected $lastJobStatusUpdate = 0; // timestamp
	
	// counters needed for calculating estimated time and speed [Tracks/minute]
	protected $itemCountChecked = 0;	
	protected $itemCountProcessed = 0;
	protected $itemCountTotal = 0;
	
	// waiting until mpd has finished his internal database-update
	protected $waitingLoop = 0;
	protected $maxWaitingTime = 60; // seconds
	
	protected $directoryHashes = array(/* dirhash -> albumId */);
	protected $updatedAlbums = array(/* id -> NULL */); 
	
	# TODO: unset all big arrays at the end of each method
	
	public function triggerImport($remigrate = FALSE) {
		
		if($remigrate === FALSE) {
			// phase 2: check if mpd database update is running and simply wait if required
			$this->waitForMpd();
			
			// phase 3: parse mpd database and insert/update table:rawtagdata
			$this->processMpdDatabasefile();
			
			// phase 4: scan id3 tags and insert into table:rawtagdata of all new or modified files
			$this->scanMusicFileTags();
		}
		
		// phase 5: migrate table rawtagdata to table track,album,artist,genre,label
		$this->migrateRawtagdataTable($remigrate);
		
		if($remigrate === FALSE) {
			// phase 6: delete dupes of extracted embedded images
			$this->destroyExtractedImageDupes();
			
			
			// phase 7: get images
			// TODO: extend directory scan with additional relevant files
			$this->searchImagesInFilesystem();
		}
		// phase 6: makes sure each album record gets all genreIds which appears on albumTracks
		#$importer->fixAlbumGenres();
		
		// phase 7: check configured label-directories and update table:track:labelId
		# TODO: move this funtionality to album-migrator
		$this->setDefaultLabels();
		
		// phase 8: makes sure each album record gets all labelIds which appears on albumTracks
		# TODO: move this funtionality to album-migrator
		$this->fixAlbumLabels();
		
		// phase 9:
		$this->updateCounterCache();
		
		// phase 10
		$this->extractAllMp3FingerPrints();
		
		
		// phase X: add trackcount to albumRecords
		
		// phase X: add trackcount & albumcount to genre records
		
		// phase X: add fingerprint to rawtagdata+track table
		
		// phase 9
			
		
	}
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
		
		// get timestamps of all images from mysql database
		$imageTimestampsMysql = array();
		
			////////////////////////////////////////////////////////////////
			// TEMP reset database status for testing purposes
			#$query = "UPDATE rawtagdata SET importStatus=1, lastScan=0;";
			#$app->db->query($query);
			#$query = "DELETE FROM bitmap WHERE trackId > 0;";
			#$app->db->query($query);
			////////////////////////////////////////////////////////////////
		
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
			$t->setRelativePath($record['relativePath']);
			$t->setLastScan(time());
			$t->setImportStatus(2);
			
			// TODO: handle not found files
			if(is_file($app->config['mpd']['musicdir'] . $record['relativePath']) === FALSE) {
				$t->setError('invalid file');
				$t->update();
				continue;
			}
			$t->setFilesize( filesize($app->config['mpd']['musicdir'] . $record['relativePath']) );
			
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
				$phpThumb->setParameter('config_cache_prefix', $record['relativePathHash'].'_' . $bitmapIndex . '_');
				$phpThumb->SetCacheFilename();
				$phpThumb->GenerateThumbnail();
				\phpthumb_functions::EnsureDirectoryExists(
					dirname($phpThumb->cache_filename),
					octdec($app->config['config']['dirCreateMask'])
				);
				$phpThumb->RenderToFile($phpThumb->cache_filename);
				
				$extractedImages ++;
				
				if(is_file($phpThumb->cache_filename) === FALSE) {
					// there had been an error
					// TODO: how to handle this?
					continue;
				}
				
				// remove tempfiles of phpThumb
				clearPhpThumbTempFiles($phpThumb);
				
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
				$bitmap->setMimeType($imageSize['mime']);

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

		// override description of audiocodec
		// @see: https://github.com/othmar52/slimpd/issues/25
		// @see: https://github.com/JamesHeinrich/getID3/issues/48
		$ext = strtolower(preg_replace('/^.*\./', '', $t->getRelativePath())); 
		if($ext !== 'm4a') {
			return;
		}
		if(@$data['audio']['codec'] === 'Apple Lossless Audio Codec') {
			$t->setMimeType('audio/aac');
			$t->setAudioDataformat('aac');
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
	
	// TODO: is it really necessary to execute this step on each import? maybe some manually triggered mainainance steps would make sense

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
			
			$album = new Album();
			$album->setId($record['id']);
			$album->setLastScan(time());
			$album->setImportStatus(2);
			
			$foundAlbumImages = $this->getFilesystemImagesForMusicFile($record['relativePath'].'filename-not-relevant.mp3');

			foreach($foundAlbumImages as $relativePath) {
				$imagePath = $app->config['mpd']['musicdir'] . $relativePath;
				$relativePathHash = getFilePathHash($relativePath);
				$imageSize = GetImageSize($imagePath);

				$bitmap = new Bitmap();
				$bitmap->setRelativePath($relativePath);
				$bitmap->setRelativePathHash($relativePathHash);
				$bitmap->setFilemtime(filemtime($imagePath));
				$bitmap->setFilesize(filesize($imagePath));
				$bitmap->setAlbumId($record['id']);
				
				if($imageSize === FALSE) {
					$bitmap->setError(1);
					$bitmap->update();
					cliLog("ERROR getting image size from " . $relativePath, 2, 'red');
					continue;
				}
				$bitmap->setWidth($imageSize[0]);
				$bitmap->setHeight($imageSize[1]);
				$bitmap->setMimeType($imageSize['mime']);

				$bitmap->setPictureType($app->imageweighter->getType($bitmap->getRelativePath()));
				$bitmap->setSorting($app->imageweighter->getWeight($bitmap->getRelativePath()));
				$bitmap->update();
				$insertedImages++;
			}
			$album->update();
		}
		$this->finishJob(array(
			'msg' => 'processed ' . $this->itemCountChecked . ' directories',
			'insertedImages' => $insertedImages
		), __FUNCTION__);
		$this->directoryImages = array();
		return;
	}

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
				: getDirectoryFiles($app->config['mpd']['musicdir'] . $directory);

			$this->directoryImages[ $directoryHash ] = $images;
			if(count($images) > 0) {
				$foundAlbumImages = array_merge($foundAlbumImages, $images);
			}
		}

		if($app->config['images']['look_cover_directory']) {
			$this->pluralizeCommonArtworkDirectoryNames();
			// search for specific named subdirectories
			if(is_dir($app->config['mpd']['musicdir'] . $directory) === TRUE) {
				$handle=opendir($app->config['mpd']['musicdir'] . $directory);
				while ($dirname = readdir ($handle)) {
					if(is_dir($app->config['mpd']['musicdir'] . $directory . $dirname)) {
						if(in_array(az09($dirname), $this->commonArtworkDirectoryNames)) {
							$foundAlbumImages = array_merge(
								$foundAlbumImages,
								getDirectoryFiles($app->config['mpd']['musicdir'] . $directory . $dirname)
							);
						}
					}
				}
				closedir($handle);
			}
		}

		if($app->config['images']['look_silbling_directory']) {
			$this->pluralizeCommonArtworkDirectoryNames();
			$parentDir = $app->config['mpd']['musicdir'] . dirname($directory) . DS;
			// search for specific named subdirectories
			if(is_dir($parentDir) === TRUE) {
				$handle=opendir($parentDir);
				while ($dirname = readdir ($handle)) {
					if(is_dir($parentDir . $dirname)) {
						if(in_array(az09($dirname), $this->commonArtworkDirectoryNames)) {
							$foundAlbumImages = array_merge(
								$foundAlbumImages,
								getDirectoryFiles($parentDir . $dirname)
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
				: getDirectoryFiles($app->config['mpd']['musicdir'] . $parentDir);
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

	private function pluralizeCommonArtworkDirectoryNames() {
		if(count($this->commonArtworkDirectoryNames)>0) {
			// we already have pluralized those strings
			return;
		}

		$app = \Slim\Slim::getInstance();
		if($app->config['images']['look_cover_directory'] != TRUE && $app->config['images']['look_silbling_directory'] != TRUE) {
			// disabled by config
			return;
		}

		foreach($app->config['images']['common_artwork_dir_names'] as $dirname) {
			$this->commonArtworkDirectoryNames[] = az09($dirname);
			$this->commonArtworkDirectoryNames[] = az09($dirname) . 's';
		}
	}

	/**
	 * decrease timestamps - so all tracks will get remigrated on next standard-update-run
	 * TODO: consider to remove because maybe tons of files gets remigrated
	 */
	public function modifyDirectoryTimestamps($relativeDirectoryPath) {
		$database = \Slim\Slim::getInstance()->db;
		$query = "
			UPDATE album
			SET
				lastScan = lastScan-1,
				filemtime = filemtime-1
			WHERE
				relativePath LIKE '". $database->real_escape_string($relativeDirectoryPath)."%'";
		$database->query($query);
		return;
	}
	
	/**
	 * 
	 */
	public function checkQue() {
		
		// check if we really have something to process
		$runImporter = FALSE;
		$query = "SELECT id, relativePath FROM importer
			WHERE jobPhase=0 AND jobEnd=0";
			
		$result = \Slim\Slim::getInstance()->db->query($query);
		$directories = array();
		while($record = $result->fetch_assoc()) {
			$runImporter = TRUE;
			if(strlen($record['relativePath']) > 0) {
				$directories[ $record['relativePath'] ] = $record['relativePath'];
			}
			$this->jobId = $record['id'];
			$this->finishJob(array(), __FUNCTION__);
		}
		// process unified directories
		foreach($directories as $dir) {
			$this->modifyDirectoryTimestamps($dir);
		}
		$this->jobId = 0;
		return $runImporter;
	}

	/**
	 * queDirectoryUpdate() inserts a database record which will be processed by ./slimpd (cli-tool)
	 */
	public static function queDirectoryUpdate($relativePath) {
		$app = \Slim\Slim::getInstance();
		if(is_dir($app->config['mpd']['musicdir'] .$relativePath ) === FALSE) {
			// no need to process invalid directory
			return;
		}
		cliLog('adding dir to que: ' . $relativePath, 5);
		$data = array(
			'relativePath' => $relativePath
		);
		$importer = new self();
		$importer->jobPhase = 0;
		$importer->beginJob($data, __FUNCTION__);
		return;
	}

	/**
	 * queStandardUpdate() inserts a database record which will be processed by ./slimpd (cli-tool)
	 */
	public static function queStandardUpdate() {
		$data = array(
			'standardUpdate' => 1
		);
		$importer = new self();
		$importer->jobPhase = 0;
		$importer->beginJob($data, __FUNCTION__);
		return;
	}

	private function beginJob($data = array(), $function = '') {
		cliLog("STARTING import phase " . $this->jobPhase . " " . $function . '()', 1, "cyan");
		$app = \Slim\Slim::getInstance();
		$this->jobBegin = microtime(TRUE);
		$this->itemCountChecked = 0;
		$this->itemCountProcessed = 0;
		
		$relativePath = (isset($data['relativePath']) === TRUE)
			? $app->db->real_escape_string($data['relativePath'])
			: '';
		//$this->itemCountTotal = 0;
		$query = "INSERT INTO importer
			(jobPhase, jobStart, jobLastUpdate, jobStatistics, relativePath)
			VALUES (
				".(int)$this->jobPhase.",
				". $this->jobBegin.",
				". $this->jobBegin. ",
				'" .serialize($data)."',
				'". $relativePath ."')";
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
		$this->itemCountChecked = 0;
		$this->itemCountProcessed = 0;
		$this->itemCountTotal = 0;
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
		cliLog('truncating all tables with migrated data', 1, 'red', TRUE);
		foreach(self::getInitialDatabaseQueries() as $query) {
			\Slim\Slim::getInstance()->db->query($query);
		}
	}
	
	
	/** 
	 * @return array 'directoryHash' => 'most-recent-timestamp' 
	 */
	public static function getMigratedAlbumTimstamps() {
		return self::getMigratedTimstamps('album');
	}

	public static function getMigratedTrackTimstamps() {
		return self::getMigratedTimstamps('track');
	}
	
	public static function getMigratedTimstamps($tablename) {
		$timestampsMysql = array();
		
		$query = "SELECT relativePathHash,filemtime FROM " . az09($tablename);
		$result = \Slim\Slim::getInstance()->db->query($query);
		while($record = $result->fetch_assoc()) {
			$timestampsMysql[ $record['relativePathHash'] ] = $record['filemtime'];
		}
		return $timestampsMysql;
	}

	/**
	 * TODO: how to deal with album-orphans?
	 * TODO: reset album:lastScan for each migrated album 
	 */
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
	
				$previousAlbum->setRelativeDirectoryPathHash($record['relativeDirectoryPathHash']);
				$previousAlbum->setRelativeDirectoryPath($record['relativeDirectoryPath']);
				$previousAlbum->setDirectoryMtime($record['directoryMtime']);
			}
			
			if($record['relativeDirectoryPathHash'] !== $previousAlbum->getRelativeDirectoryPathHash() ) {
				
				// decide if we have to process album or if we can skip it
				if(isset($migratedAlbumTimstamps[ $previousAlbum->getRelativeDirectoryPathHash() ]) === FALSE) {
					cliLog('album does NOT exist in migrated data. migrating: ' . $previousAlbum->getRelativeDirectoryPath(), 5);
					$triggerAlbumMigration = TRUE;
				} else {
					if($migratedAlbumTimstamps[ $previousAlbum->getRelativeDirectoryPathHash() ] < $previousAlbum->getDirectoryMtime()) {
						cliLog('dir-timestamp raw is more recent than migrated. migrating: ' . $previousAlbum->getRelativeDirectoryPath(), 5);
						cliLog('dir-timestamps mig1: '. $migratedAlbumTimstamps[ $previousAlbum->getRelativeDirectoryPathHash() ] .' raw: ' . $previousAlbum->getDirectoryMtime(), 10, 'yellow');
						$triggerAlbumMigration = TRUE;
					}
				}
				
				if($triggerAlbumMigration === TRUE) {
					$previousAlbum->run();
					$migratedAlbums++;
				} else {
					cliLog('skipping migration for: ' . $previousAlbum->getRelativeDirectoryPath(), 5);
				}
				unset($previousAlbum);
				cliLog('resetting previousAlbum', 10);
				$previousAlbum = new \Slimpd\AlbumMigrator();
				$previousAlbum->setRelativeDirectoryPathHash($record['relativeDirectoryPathHash']);
				$previousAlbum->setRelativeDirectoryPath($record['relativeDirectoryPath']);
				$previousAlbum->setDirectoryMtime($record['directoryMtime']);
				
				cliLog('  adding hash of dir '. $record['relativeDirectoryPath'] .' to previousAlbum', 10);
				$triggerAlbumMigration = FALSE;
			}
			
			$this->updateJob(array(
				'currentItem' => $record['relativePath'],
				'migratedAlbums' => $migratedAlbums
			));
			
			
			
			
			// decide if we have to process album based on single-track-change or if we can skip it
			if(isset($migratedTrackTimstamps[ $record['relativePathHash'] ]) === FALSE) {
				cliLog('track does NOT exist in migrated data. migrating: ' . $record['relativePath'], 5);
				$triggerAlbumMigration = TRUE;
			} else {
				if($migratedTrackTimstamps[ $record['relativePathHash'] ] < $record['filemtime']) {
					cliLog('track-imestamp raw is more recent than migrated. migrating: ' . $record['relativePath'], 5);
					$triggerAlbumMigration = TRUE;
				}
			}
				
			
			$previousAlbum->addTrack($record);
			cliLog('adding track to previousAlbum: ' . $record['relativePath'], 10);
			
			cliLog("#" . $this->itemCountChecked . " " . $record['relativePath'],2);
			
			// dont forget to check the last one
			if($this->itemCountChecked === $this->itemCountTotal && $this->itemCountTotal > 1) {
				// decide if we have to process album or if we can skip it
				if(isset($migratedAlbumTimstamps[ $previousAlbum->getRelativeDirectoryPathHash() ]) === FALSE) {
					cliLog('album does NOT exist in migrated data. migrating: ' . $previousAlbum->getRelativeDirectoryPath(), 5);
					$triggerAlbumMigration = TRUE;
				} else {
					if($migratedAlbumTimstamps[ $previousAlbum->getRelativeDirectoryPathHash() ] < $previousAlbum->getDirectoryMtime()) {
						cliLog('dir-timestamp raw is more recent than migrated. migrating: ' . $previousAlbum->getRelativeDirectoryPath(), 5);
						cliLog('dir-timestamps mig: '. $migratedAlbumTimstamps[ $previousAlbum->getRelativeDirectoryPathHash() ] .' raw: ' . $previousAlbum->getDirectoryMtime(), 10, 'yellow');
						$triggerAlbumMigration = TRUE;
					}
				}
				
				if($triggerAlbumMigration === TRUE) {
					$previousAlbum->run();
					$migratedAlbums++;
				} else {
					cliLog('skipping migration for: ' . $previousAlbum->getRelativeDirectoryPath(), 5);
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
		
		// get all existing album-ids to determine orphans
		$deadMysqlAlbums = array();
		$query = "SELECT id, relativePathHash FROM album;";
		$result = $app->db->query($query);
		while($record = $result->fetch_assoc()) {
			$deadMysqlAlbums[ $record['relativePathHash'] ] = $record['id'];
		}
		
		
		$dbFilePath = $app->config['mpd']['dbfile'];
		$this->updateJob(array(
                        'msg' => $app->ll->str('importer.testdbfile')
                ));
		
		// check if we have a plaintext or gzipped mpd-databasefile
		$isBinary = testBinary($dbFilePath);
		
		if($isBinary === TRUE) {
			$this->updateJob(array(
	                        'msg' => $app->ll->str('importer.gunzipdbfile')
	                ));
			// decompress databasefile
			$bufferSize = 4096; // read 4kb at a time (raising this value may increase performance)
			$outFileName = APP_ROOT . 'cache/mpd-database-plaintext';
			
			// Open our files (in binary mode)
			$inFile = gzopen($app->config['mpd']['dbfile'], 'rb');
			$outFile = fopen($outFileName, 'wb');
			
			// Keep repeating until the end of the input file
			while(!gzeof($inFile)) {
			// Read buffer-size bytes
			// Both fwrite and gzread and binary-safe
			  fwrite($outFile, gzread($inFile, $bufferSize));
			}  
			// Files are done, close files
			fclose($outFile);
			gzclose($inFile);
			$dbFilePath = $outFileName;
		}
		
		
		$dbfile = explode("\n", file_get_contents($dbFilePath));
		$currentDirectory = "";
		$currentSong = "";
		$currentPlaylist = "";
		$currentSection = "";
		
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
							
							if(array_key_exists($directoryHash, $deadMysqlAlbums)) {
								unset($deadMysqlAlbums[$directoryHash]);
							}
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
			if(isset($attr[1]) === TRUE && in_array($attr[0], ['Time','Artist','Title','Track','Album','Genre','Date'])) {
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

		// delete dead items in table:rawtagdata & table:track & table:trackindex
		if(count($deadMysqlFiles) > 0) {
			\Slimpd\Rawtagdata::deleteRecordsByIds($deadMysqlFiles);
			\Slimpd\Track::deleteRecordsByIds($deadMysqlFiles);
			\Slimpd\Trackindex::deleteRecordsByIds($deadMysqlFiles);

			// TODO: last check if those 3 tables has identical totalCount()
			// reason: basis is only rawtagdata and not all 3 tables
		}

		// delete dead items in table:album & table:albumindex
		if(count($deadMysqlAlbums) > 0) {
			print_r($deadMysqlAlbums);
			\Slimpd\Album::deleteRecordsByIds($deadMysqlAlbums);
			\Slimpd\Albumindex::deleteRecordsByIds($deadMysqlAlbums);
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
		
		$previousKey = '';

		$deletedFilesize = 0;
		
		#$msgKeep = $app->ll->str('importer.image.keep');
		#$msgDestroy = $app->ll->str('importer.image.destroy');
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
				$orderedByRelevance = uniqueArrayOrderedByRelevance(
					trimExplode(",", $labelIdsFromTracks, TRUE)
				);

				// remove "Unknown Label" in case we have another label-id
				// TODO: find out why this case is possible and remove this "cleanup" code
				if(count($orderedByRelevance) > 1 && in_array(10, $orderedByRelevance)) {
					unset($orderedByRelevance[array_search(10, $orderedByRelevance)]);
				}

				$album->setLabelId(join(",", $orderedByRelevance));
				cliLog($app->ll->str('importer.fixlabel.msg', array($album->getId(), $album->getLabelId())), 7);
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

	/**
	 * fills databasefields trackCount & albumCount of tables: artist,genre,label
	 */
	public function updateCounterCache() {
		$app = \Slim\Slim::getInstance();
		$this->jobPhase = 9;
		$this->beginJob(array(
			'currentItem' => "fetching all track-labels for inserting into table:album ..."
		), __FUNCTION__);
		foreach(array('artist', 'genre', 'label') as $table) {
			// reset all counters
			$query = "UPDATE " . $table . " SET trackCount=0, albumCount=0";
			$app->db->query($query);
			
			$query = "SELECT count(id) AS itemCountTotal FROM " . $table;
			$this->itemCountTotal += $app->db->query($query)->fetch_assoc()['itemCountTotal'];
		}
		
		// collect all genreIds, labelIds, artistIds, remixerIds, featuringIds provided by tracks
		$tables = array(
			'Artist' => array(),
			'Genre' => array(),
			'Label' => array()
		);
		
		// to be able to display a progress status
		$all = array();
		
		$query = "SELECT id,albumId,artistId,remixerId,featuringId,genreId,labelId FROM track";
		$result = $app->db->query($query);
		
		
		while($record = $result->fetch_assoc()) {
			$all['al' . $record['albumId']] = NULL;
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
			$msg = "updating table:".$className." with trackCount and albumCount";
			cliLog($msg, 3);
			foreach($tableData as $itemId => $data) {
				
				$classPath = "\\Slimpd\\" . $className;
				$item = new $classPath();
				$item->setId($itemId);
				$item->setTrackCount( count($data['tracks']) );
				
				$msg = "updating ".$className.": " . $itemId . " with trackCount:" .  $item->getTrackCount();
				$item->setAlbumCount( count($data['albums']) );
				$msg .= ", albumCount:" .  $item->getAlbumCount();
				$item->update();
				$this->itemCountProcessed++;
				$this->itemCountChecked = count($all);
				$this->updateJob(array(
					'currentItem' => $msg
				));
				
				cliLog($msg, 7);
			}
			
			// delete all items which does not have any trackCount or albumCount
			// but preserve default entries
			$query = "
				DELETE FROM " . strtolower($className) . "
				WHERE trackCount=0
				AND albumCount=0
				AND id>" . (($className === 'Artist') ? 11 : 10); // unknown artist, various artists,...
			$msg = "deleting ".$className."s  with trackCount=0 AND albumCount=0";
			cliLog($msg, 3);
			$app->db->query($query);
		}
		unset($tables);
		unset($all);
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
				
				if($t['labelId'] == '' || $t['labelId'] == '10') { // unknown label
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
					
					// remove "Unknown Label (id=10)"
					if(($key = array_search('10', $existingLabelIdsInDir)) !== false) {
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
	public static function extractAudioFingerprint($absolutePath, $returnCommand = FALSE) {
		$ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
		switch($ext) {
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

	public function waitForMpd() {
		$this->jobPhase = 1;
		$recursionInterval = 3; // seconds
		$mpd = new \Slimpd\modules\mpd\mpd();
		$status = $mpd->cmd('status');
		if(isset($status['updating_db'])) {
			if($this->waitingLoop === 0) {
				$this->waitingLoop = time();
				// fake total items with total seconds
				$this->itemCountTotal = (int)$this->maxWaitingTime;
				$this->beginJob(array(), __FUNCTION__);
			}
			if(time() - $this->waitingLoop > $this->maxWaitingTime) {
				cliLog('max waiting time ('.$this->maxWaitingTime .' sec) for mpd reached. exiting now...', 1, 'red', TRUE);
				$this->finishJob(NULL, __FUNCTION__);
				\Slim\Slim::getInstance()->stop();
			}
			$this->itemCountProcessed = time()-$this->waitingLoop;
			$this->itemCountChecked = time()-$this->waitingLoop;
			$this->updateJob(array(), __FUNCTION__);
			//cliLog('waiting '. (time()-$this->waitingLoop - $this->maxWaitingTime)*-1 .' sec. until mpd\'s internal database-update has finished');
			sleep($recursionInterval);
			// recursion
			return $this->waitForMpd();
		}
		if($this->waitingLoop > 0) {
			$this->itemCountProcessed = $this->itemCountTotal;
			cliLog('mpd seems to be ready. continuing...', 1, 'green');
			$this->finishJob(NULL, __FUNCTION__);
		}
		return;
	}

	public static function getInitialDatabaseQueries($app = NULL) {
		if($app === NULL) {
			$app = \Slim\Slim::getInstance();
		}
		$queries = array(
			"TRUNCATE artist;",
			"TRUNCATE genre;",
			"TRUNCATE track;",
			"TRUNCATE label;",
			"TRUNCATE album;",
			"TRUNCATE albumindex;",
			"TRUNCATE trackindex;",
			"ALTER TABLE `artist` AUTO_INCREMENT = 10;",
			"ALTER TABLE `genre` AUTO_INCREMENT = 10;",
			"ALTER TABLE `label` AUTO_INCREMENT = 10;",
			"ALTER TABLE `album` AUTO_INCREMENT = 10;",
			"ALTER TABLE `albumindex` AUTO_INCREMENT = 10;",
			"ALTER TABLE `track` AUTO_INCREMENT = 10;",
			"ALTER TABLE `trackindex` AUTO_INCREMENT = 10;",
		);
		foreach([
			'unknownartist' => 'artist',
			'variousartists' => 'artist',
			'unknowngenre' => 'genre'] as $llKey => $table) {
			$queries[] =
				"INSERT INTO `".$table."` ".
				"VALUES (
					NULL,
					'".$app->ll->str('importer.' . $llKey)."',
					'',
					'".az09($app->ll->str('importer.' . $llKey))."',
					0,
					0
				);";
		}
		$queries[] =
			"INSERT INTO `label` ".
			"VALUES (
				NULL,
				'".$app->ll->str('importer.unknownlabel')."',
				'".az09($app->ll->str('importer.unknownlabel'))."',
				0,
				0
			);";
		return $queries;
	}


	public function buildDictionarySql() {
		$app = \Slim\Slim::getInstance();
		foreach(['freq_threshold', 'suggest_debug', 'length_threshold', 'levenshtein_threshold', 'top_count'] as $var) {
			define (strtoupper($var), intval($app->config['sphinx'][$var]) );
		}
		
		$in  = fopen ("php://stdin", "r");
		$out = fopen ("php://stdout", "w+");
		
		$used_keywords = array();
		fwrite ( $out, "TRUNCATE suggest;\n");
		$n = 0;
		$m = 0;
		while ( $line = fgets( $in, 1024 ) ) {
			list ( $keyword, $freq ) = explode ( " ", trim ( $line ) );
			
			$keyword = trim($keyword);
			if (
				strlen($keyword) < 2
				|| $keyword === ''
				|| $freq<FREQ_THRESHOLD
				|| strstr ( $keyword, "_" )!==FALSE
				|| strstr ( $keyword, "'" )!==FALSE
				|| array_key_exists($keyword,$used_keywords) === TRUE ) {
					continue;
				}
			
			$trigrams = buildTrigrams ( $keyword );
			$used_keywords[$keyword] = NULL;
			fwrite ( $out, (( !$m ) ? "INSERT INTO suggest VALUES\n" : ",\n"));
			
			$n++;
			fwrite ( $out, "( 0, '$keyword', '$trigrams', $freq )" );
			$m++;
			if ( ( $m % 10000 )==0 ) {
				fwrite ( $out,  ";\n");
				$m = 0;
			}
		}
	
		if ( $m ) {
			fwrite ( $out, ";" );
		}
		fwrite ( $out,  "\n");
		$app->stop();
	}
}
