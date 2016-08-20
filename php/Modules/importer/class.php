<?php
namespace Slimpd\Modules;
use Slimpd\Models\Track;
use Slimpd\Models\Trackindex;
use Slimpd\Models\Artist;
use Slimpd\Models\Album;
use Slimpd\Models\Albumindex;
use Slimpd\Models\Label;
use Slimpd\Models\Genre;
use Slimpd\Models\Rawtagdata;
use Slimpd\Models\Bitmap;

class Importer extends \Slimpd\Modules\importer\AbstractImporter {
	protected $commonArtworkDirectoryNames = array();
	protected $directoryImages = array();
	
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
		$fileScanner = new \Slimpd\Modules\importer\Filescanner();
		$fileScanner->run();
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
				$bitmap = new Bitmap();
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


	public function migrateRawtagdataTable($resetMigrationPhase = FALSE) {
		$migrator = new \Slimpd\Modules\importer\Migrator();
		$migrator->run($resetMigrationPhase);
	}
	
	public function processMpdDatabasefile() {
		$this->jobPhase = 1;
		$app = \Slim\Slim::getInstance();
		$this->beginJob(array(
			'msg' => $app->ll->str('importer.processing.mpdfile')
		), __FUNCTION__);
		
		$mpdParser = new \Slimpd\Modules\importer\MpdDatabaseParser($app->config['mpd']['dbfile']);
		if($mpdParser->error === TRUE) {
			$msg = $app->ll->str('error.mpd.dbfile', array($app->config['mpd']['dbfile']));
			cliLog($msg, 1, 'red', TRUE);
			$this->finishJob(array('msg' => $msg));
			$app->stop();
		}

		$this->updateJob(array(
			'msg' => $app->ll->str('importer.collecting.mysqlitems')
		));
		
	
		$dbFilePath = $app->config['mpd']['dbfile'];
		$this->updateJob(array(
			'msg' => $app->ll->str('importer.testdbfile')
        ));
		
		if($mpdParser->gzipped === TRUE) {
			$this->updateJob(array(
				'msg' => $app->ll->str('importer.gunzipdbfile')
            ));
			$mpdParser->decompressDbFile();
		}
		
		$mpdParser->readMysqlTstamps();
		$mpdParser->parse($this);
		
		
		

		// delete dead items in table:rawtagdata & table:track & table:trackindex
		if(count($mpdParser->fileOrphans) > 0) {
			Rawtagdata::deleteRecordsByIds($mpdParser->fileOrphans);
			Track::deleteRecordsByIds($mpdParser->fileOrphans);
			Trackindex::deleteRecordsByIds($mpdParser->fileOrphans);

			// TODO: last check if those 3 tables has identical totalCount()
			// reason: basis is only rawtagdata and not all 3 tables
		}

		// delete dead items in table:album & table:albumindex
		if(count($mpdParser->dirOrphans) > 0) {
			print_r($mpdParser->dirOrphans);
			Album::deleteRecordsByIds($mpdParser->dirOrphans);
			Albumindex::deleteRecordsByIds($mpdParser->dirOrphans);
		}


		cliLog("dircount: " . $mpdParser->dirCount);
		cliLog("songs: " . $mpdParser->itemsChecked);
		//cliLog("playlists: " . count($playlists));
		
		# TODO: flag&handle dead items in mysql-database
		//cliLog("dead dirs: " . count($deadMysqlDirectories));
		cliLog("dead songs: " . count($mpdParser->fileOrphans));
		#print_r($deadMysqlFiles);
		
		$this->itemCountTotal = $mpdParser->itemsChecked;
		$this->finishJob(array(
			'msg' => 'processed ' . $mpdParser->itemsChecked . ' files',
			'directorycount' => $mpdParser->dirCount,
			'deletedRecords' => count($mpdParser->fileOrphans),
			'unmodified_files' => $mpdParser->itemsUnchanged
		), __FUNCTION__);
		
		// destroy parser with large array properties
		unset($mpdParser);
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
				$bitmap = new Bitmap();
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
				$album = new Album();
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
				$album = new Album();
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
				
				$classPath = "\\Slimpd\\Models\\" . $className;
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
						$databaseLabel = Label::getInstanceByAttributes(array('id' => $existingLabelId));
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
						$foundMatchingDatabaseLabelId = join(",", Label::getIdsByString($newLabelString));
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
				$i = new Rawtagdata();
				$i->setId($record['id']);
				$i->setFingerprint($fp);
				$i->update();
				
				$i = new Track();
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
		$mpd = new \Slimpd\Modules\mpd\mpd();
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
