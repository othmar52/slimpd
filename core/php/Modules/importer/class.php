<?php
namespace Slimpd\Modules;
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

	// waiting until mpd has finished his internal database-update
	protected $waitingLoop = 0;
	protected $maxWaitingTime = 60; // seconds

	protected $directoryHashes = array(/* dirhash -> albumUid */);
	protected $updatedAlbums = array(/* uid -> NULL */); 

	# TODO: unset all big arrays at the end of each method

	public function triggerImport($remigrate = FALSE) {
		// create a wrapper entry for all import phases
		$this->jobPhase = 0;
		$this->beginJob(array('msg' => 'starting sliMpd import/update process'), __FUNCTION__);
		$this->batchUid = $this->jobUid;
		$this->batchBegin = $this->jobBegin;
		if($remigrate === FALSE) {
				// phase 1: check if mpd database update is running and simply wait if required
				$this->waitForMpd();

				// phase 2: parse mpd database and insert/update table:rawtagdata
				$this->processMpdDatabasefile();

				// phase 3: scan id3 tags and insert into table:rawtagdata of all new or modified files
				$this->scanMusicFileTags();
		}

		// phase 4: migrate table rawtagdata to table track,album,artist,genre,label
		$this->migrateRawtagdataTable($remigrate);

		if($remigrate === FALSE) {
				// phase 5: delete dupes of extracted embedded images
				$this->destroyExtractedImageDupes();

				// phase 6: get images
				// TODO: extend directory scan with additional relevant files
				$this->searchImagesInFilesystem();
		}

		// phase 7:
		$this->updateCounterCache();

		// phase 8: add fingerprint to rawtagdata+track table
		$this->extractAllMp3FingerPrints();

		// update the wrapper entry for all import phases
		$this->finishBatch();
	}

	public function finishBatch() {
		$this->jobPhase = 0;
		$this->jobUid = $this->batchUid;
		$this->jobBegin = $this->batchBegin;
		$this->itemsChecked = Track::getCountAll();
		$this->itemsProcessed = $this->itemsChecked;
		$this->itemsTotal = $this->itemsChecked;
		$this->finishJob(array('msg' => 'finished sliMpd import/update process'), __FUNCTION__);
	}

	public function scanMusicFileTags() {
		$fileScanner = new \Slimpd\Modules\importer\Filescanner();
		$fileScanner->run();
	}

	public function updateCounterCache() {
		$dbStats = new \Slimpd\Modules\importer\Dbstats();
		$dbStats->updateCounterCache();
	}

	// TODO: performance tweaking by processing it vice versa:
	// read all images in embedded-directory and check if a db-record exists
	// skip the check for non-embedded/non-extracted images at all and delete db-record in case delivering fails
	// for now: skip this import phase ...

	// TODO: is it really necessary to execute this step on each import? maybe some manually triggered mainainance steps would make sense

	public function deleteOrphanedBitmapRecords() {

		# TODO: remove this line after refactoring
		return;

		$this->jobPhase = 8;
		$this->beginJob(array('msg' => 'collecting records to check from table:bitmap'), __FUNCTION__);

		$app = \Slim\Slim::getInstance();

		$query = "SELECT count(uid) AS itemsTotal FROM bitmap";
		$this->itemsTotal = (int) $app->db->query($query)->fetch_assoc()['itemsTotal'];

		$deletedRecords = 0;
		$query = "SELECT uid, relPath, embedded FROM bitmap;";
		$result = $app->db->query($query);
		while($record = $result->fetch_assoc()) {
			$this->itemsChecked++;
			$prefix = ($record['embedded'] == '1')
				? APP_ROOT . 'localdata' . DS . 'embedded'
				: $app->config['mpd']['musicdir'];
			if(is_file($prefix . $record['relPath']) === TRUE) {
				cliLog('keeping database-entry for ' . $record['relPath'], 3);
			} else {
				cliLog('deleting database-entry for ' . $record['relPath'], 3);
				$bitmap = new Bitmap();
				$bitmap->setUid($record['uid']);
				$bitmap->delete(); 
				$deletedRecords++;
			}
			$this->itemsProcessed++;
			$this->updateJob(array(
				'currentItem' => $record['relPath'],
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

		$this->jobPhase = 6;
		$this->beginJob(array('msg' => 'collecting directories to scan from table:albums'), __FUNCTION__);

		
		$app = \Slim\Slim::getInstance();

		$query = "SELECT count(DISTINCT relDirPathHash) AS itemsTotal FROM rawtagdata WHERE lastDirScan <= directoryMtime;";
		$this->itemsTotal = (int) $app->db->query($query)->fetch_assoc()['itemsTotal'];

		$query = "SELECT uid, relDirPath, relDirPathHash, directoryMtime FROM rawtagdata WHERE lastDirScan <= directoryMtime GROUP BY relDirPathHash;";
		$result = $app->db->query($query);
		$insertedImages = 0;

		$filesystemReader = new \Slimpd\Modules\importer\FilesystemReader();

		while($record = $result->fetch_assoc()) {
			$this->itemsChecked++;
			cliLog($record['uid'] . ' ' . $record['relDirPath'], 2);
			$this->updateJob(array(
				'msg' => 'processed ' . $this->itemsChecked . ' files',
				'currentItem' => $record['relDirPath'],
				'insertedImages' => $insertedImages
			));
			
			$query = "UPDATE rawtagdata SET lastDirScan = ".time()." WHERE relDirPathHash='". $record['relDirPathHash'] ."';";
			$app->db->query($query);

			#$album = new RawTagData();
			#$album->setUid($record['uid'])
			#	->setLastDirScan(time())
			#	// TODO: should we update import status here?
			#	/*->setImportStatus(2)*/;

			$foundAlbumImages = $filesystemReader->getFilesystemImagesForMusicFile($record['relDirPath'].'filename-not-relevant.mp3');
			if(count($foundAlbumImages) === 0) {
				continue;
			}
			
			// get albumUid
			$query = "SELECT uid FROM album WHERE relPathHash = '".$record['relDirPathHash']."';";
			$albumUid = (int) $app->db->query($query)->fetch_assoc()['uid'];

			foreach($foundAlbumImages as $relPath) {
				$imagePath = $app->config['mpd']['musicdir'] . $relPath;
				$relPathHash = getFilePathHash($relPath);
				$imageSize = GetImageSize($imagePath);

				$bitmap = new Bitmap();
				$bitmap->setRelPath($relPath)
					->setRelPathHash($relPathHash)
					->setRelDirPathHash($record['relDirPathHash'])
					->setFileName(basename($relPath))
					->setFilemtime(filemtime($imagePath))
					->setFilesize(filesize($imagePath))
					->setAlbumUid($albumUid);

				if($imageSize === FALSE) {
					$bitmap->setError(1);
					$bitmap->update();
					cliLog("ERROR getting image size from " . $relPath, 2, 'red');
					continue;
				}
				$bitmap->setWidth($imageSize[0])
					->setHeight($imageSize[1])
					->setBghex(
						\Slimpd\Modules\importer\Filescanner::getDominantColor($imagePath, $imageSize[0], $imageSize[1])
					)
					->setMimeType($imageSize['mime'])
					->setPictureType($app->imageweighter->getType($bitmap->getRelPath()))
					->setSorting($app->imageweighter->getWeight($bitmap->getRelPath()))
					->update();
				$insertedImages++;
			}
			#$album->update();
		}
		$this->finishJob(array(
			'msg' => 'processed ' . $this->itemsChecked . ' directories',
			'insertedImages' => $insertedImages
		), __FUNCTION__);
		return;
	}



	/**
	 * decrease timestamps - so all tracks will get remigrated on next standard-update-run
	 * TODO: consider to remove because maybe tons of files gets remigrated
	 */
	public function modifyDirectoryTimestamps($relDirPath) {
		$database = \Slim\Slim::getInstance()->db;
		$query = "
			UPDATE album
			SET
				lastScan = lastScan-1,
				filemtime = filemtime-1
			WHERE
				relPath LIKE '". $database->real_escape_string($relDirPath)."%'";
		$database->query($query);
		return;
	}

	/**
	 * 
	 */
	public function checkQue() {

		// check if we really have something to process
		$runImporter = FALSE;
		$query = "SELECT uid, relPath FROM importer
			WHERE jobPhase=0 AND jobEnd=0";

		$result = \Slim\Slim::getInstance()->db->query($query);
		$directories = array();
		while($record = $result->fetch_assoc()) {
			$runImporter = TRUE;
			if(strlen($record['relPath']) > 0) {
				$directories[ $record['relPath'] ] = $record['relPath'];
			}
			$this->jobUid = $record['uid'];
			$this->finishJob(array(), __FUNCTION__);
		}
		// process unified directories
		foreach($directories as $dir) {
			$this->modifyDirectoryTimestamps($dir);
		}
		$this->jobUid = 0;
		return $runImporter;
	}

	/**
	 * queDirectoryUpdate() inserts a database record which will be processed by ./slimpd (cli-tool)
	 */
	public static function queDirectoryUpdate($relPath) {
		$app = \Slim\Slim::getInstance();
		if(is_dir($app->config['mpd']['musicdir'] .$relPath ) === FALSE) {
			// no need to process invalid directory
			return;
		}
		cliLog('adding dir to que: ' . $relPath, 5);
		$data = array(
			'relPath' => $relPath
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
		$this->jobPhase = 2;
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
		$app->batcher->finishAll();

		// delete dead items in table:rawtagdata & table:track & table:trackindex
		if(count($mpdParser->fileOrphans) > 0) {
			Rawtagdata::deleteRecordsByUids($mpdParser->fileOrphans);
			Track::deleteRecordsByUids($mpdParser->fileOrphans);
			Trackindex::deleteRecordsByUids($mpdParser->fileOrphans);

			// TODO: last check if those 3 tables has identical totalCount()
			// reason: basis is only rawtagdata and not all 3 tables
		}

		// delete dead items in table:album & table:albumindex
		if(count($mpdParser->dirOrphans) > 0) {
			print_r($mpdParser->dirOrphans);
			Album::deleteRecordsByUids($mpdParser->dirOrphans);
			Albumindex::deleteRecordsByUids($mpdParser->dirOrphans);
		}

		cliLog("dircount: " . $mpdParser->dirCount);
		cliLog("songs: " . $mpdParser->itemsChecked);
		//cliLog("playlists: " . count($playlists));

		# TODO: flag&handle dead items in mysql-database
		//cliLog("dead dirs: " . count($deadMysqlDirectories));
		cliLog("dead songs: " . count($mpdParser->fileOrphans));
		#print_r($deadMysqlFiles);

		$this->itemsTotal = $mpdParser->itemsChecked;
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
		$this->jobPhase = 5;
		$this->beginJob(array(
			'msg' => "searching extracted image-dupes in database ..."
		), __FUNCTION__);
		$app = \Slim\Slim::getInstance();

		$query = "SELECT count(uid) AS itemsTotal FROM  bitmap WHERE error=0 AND trackUid > 0";
		$this->itemsTotal = (int) $app->db->query($query)->fetch_assoc()['itemsTotal'];
		$query = "
			SELECT
				uid,
				trackUid,
				embedded,
				CONCAT(relDirPathHash, '.', width, '.', height, '.', filesize) as dupes,
				relPath,
				filesize
			FROM  bitmap
			WHERE error=0 AND embedded=1
			ORDER BY albumUid;";
		$result = $app->db->query($query);

		$previousKey = '';

		$deletedFilesize = 0;

		#$msgKeep = $app->ll->str('importer.image.keep');
		#$msgDestroy = $app->ll->str('importer.image.destroy');
		$msgProcessing = $app->ll->str('importer.image.dupecheck.processing');
		while ($record = $result->fetch_assoc()) {
			$this->updateJob(array(
				'msg' => $msgProcessing,
				'currentItem' => $record['relPath']
			));
			$this->itemsChecked++;
			if($this->itemsChecked === 1) {
				$previousKey = $record['dupes'];
				cliLog($app->ll->str('importer.image.keep', array($record['relPath'])), 3);
				continue;
			}
			if($record['dupes'] === $previousKey) {
				$msg = $app->ll->str('importer.image.destroy', array($record['relPath']));
				$bitmap = new Bitmap();
				$bitmap->setUid($record['uid'])
					->setTrackUid($record['trackUid'])
					->setEmbedded($record['embedded'])
					->setRelPath($record['relPath'])
					->destroy();

				$this->itemsProcessed++;
				$deletedFilesize += $record['filesize'];
			} else {
				$msg = $app->ll->str('importer.image.keep', array($record['relPath']));
			}
			cliLog($msg, 3);
			$previousKey = $record['dupes'];
		}

		$msg = $app->ll->str('importer.destroyimages.result', array($this->itemsProcessed, formatByteSize($deletedFilesize)));
		cliLog($msg);

		$this->finishJob(array(
			'msg' => $msg,
			'deletedFileSize' => formatByteSize($deletedFilesize)
		), __FUNCTION__);
		return;
	}

	public function extractAllMp3FingerPrints() {
		$app = \Slim\Slim::getInstance();
		$this->jobPhase = 8;

		
		if($app->config['modules']['enable_fingerprints'] !== '1') {
			return;
		}
		// reset phase 
		// UPDATE rawtagdata SET fingerprint="" WHERE audioDataFormat="mp3"

		
		$this->beginJob(array(
			'currentItem' => "fetching mp3 files with missing fingerprint attribute"
		), __FUNCTION__);

		$query = "
			SELECT count(uid) AS itemsTotal
			FROM rawtagdata
			WHERE extension='mp3' AND fingerprint='';";
		$this->itemsTotal = (int) $app->db->query($query)->fetch_assoc()['itemsTotal'];

		
		$query = "
			SELECT uid, relPath
			FROM rawtagdata
			WHERE extension='mp3' AND fingerprint='';";

		$result = $app->db->query($query);

		while($record = $result->fetch_assoc()) {
			$this->itemsChecked++;

			$this->updateJob(array(
				'currentItem' => 'albumUid: ' . $record['relPath']
			));

			$this->itemsProcessed++;

			$fullPath = $app->config['mpd']['musicdir'] . $record['relPath'];
			if(is_file($fullPath) == FALSE || is_readable($fullPath) === FALSE) {
				cliLog("ERROR: fileaccess " . $record['relPath'], 1, 'red');
				continue;
			}

			$fingerPrint = \Slimpd\Modules\importer\Filescanner::extractAudioFingerprint($fullPath);
			if($fingerPrint === FALSE) {
				cliLog("ERROR: regex fingerprint result " . $record['relPath'], 1, 'red');
				continue;
			}

			// complete rawtagdata record
			$rawTagData = new Rawtagdata();
			$rawTagData->setUid($record['uid'])
				->setFingerprint($fingerPrint)
				->update();

			// complete track record
			$track = new Track();
			$track->setUid($record['uid'])
				->setFingerprint($fingerPrint)
				->update();

			// complete trackindex record
			$track = new Trackindex();
			$trackIndex = \Slimpd\Models\Trackindex::getInstanceByAttributes([ 'uid' => $record['uid'] ]);
			$trackIndex->setAllchunks($trackIndex->getAllchunks() . " " . $fingerPrint)->update();

			cliLog("fingerprint: " . $fingerPrint . " for " . $record['relPath'],3);
		}
		$this->finishJob(array(), __FUNCTION__);
		return;
	}

	public function waitForMpd() {
		$this->jobPhase = 1;
		$recursionInterval = 3; // seconds
		if($this->waitingLoop === 0) {
			$this->waitingLoop = time();
			// fake total items with total seconds
			$this->itemsTotal = (int)$this->maxWaitingTime;
			$this->beginJob(array(), __FUNCTION__);
		}
		$mpd = new \Slimpd\Modules\mpd\mpd();
		$status = $mpd->cmd('status');
		if(isset($status['updating_db'])) {
			if(time() - $this->waitingLoop > $this->maxWaitingTime) {
				cliLog('max waiting time ('.$this->maxWaitingTime .' sec) for mpd reached. exiting now...', 1, 'red', TRUE);
				$this->finishJob(NULL, __FUNCTION__);
				$this->finishBatch();
				\Slim\Slim::getInstance()->stop();
			}
			$this->itemsProcessed = time()-$this->waitingLoop;
			$this->itemsChecked = time()-$this->waitingLoop;
			$this->updateJob(array(), __FUNCTION__);
			//cliLog('waiting '. (time()-$this->waitingLoop - $this->maxWaitingTime)*-1 .' sec. until mpd\'s internal database-update has finished');
			sleep($recursionInterval);
			// recursion
			return $this->waitForMpd();
		}
		if($this->waitingLoop > 0) {
			$this->itemsProcessed = $this->itemsTotal;
			cliLog('mpd seems to be ready. continuing...', 1, 'green');
			$this->finishJob(NULL, __FUNCTION__);
		}
		return;
	}

}
