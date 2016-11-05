<?php
namespace Slimpd\Modules\Importer;
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
class Migrator extends \Slimpd\Modules\Importer\AbstractImporter {
	private $dbTstampsTrack = array();
	private $dbTstampsAlbum = array();

	private $skipAlbum = TRUE;

	private $batchSize = 1500000; // process only 1.5 million tracks per batch

	public $migratedAlbums = 0;

	/* start with empty database? - then we can maximise speed by using the "Batcher" */
	public $useBatcher = FALSE;

	// only for development purposes
	public function tempResetMigrationPhase() {
		cliLog('truncating all tables with migrated data', 1, 'red', TRUE);
		foreach(\Slimpd\Modules\Importer\DatabaseStuff::getInitialDatabaseQueries($this->ll) as $query) {
			$this->db->query($query);
		}
		$this->useBatcher = TRUE;
	}

	/** 
	 * @return array 'directoryHash' => 'most-recent-timestamp' 
	 */
	public function getMigratedAlbumTimstamps() {
		return $this->getMigratedTimstamps('album');
	}

	public function getMigratedTrackTimstamps() {
		return $this->getMigratedTimstamps('track');
	}

	public function getMigratedTimstamps($tablename) {
		$timestampsMysql = array();

		$query = "SELECT relPathHash,filemtime FROM " . az09($tablename);
		$result = $this->db->query($query);
		while($record = $result->fetch_assoc()) {
			$timestampsMysql[ $record['relPathHash'] ] = $record['filemtime'];
		}
		return $timestampsMysql;
	}

	/**
	 * TODO: how to deal with album-orphans?
	 * TODO: reset album:lastScan for each migrated album 
	 */
	public function run($resetMigrationPhase = FALSE) {

		# only for development
		# TODO: make this step optional controllable via gui
		if($resetMigrationPhase === TRUE) {
			$this->tempResetMigrationPhase();
		}

		$this->jobPhase = 4;
		$this->beginJob(array(
			'msg' => "migrateRawtagdataTable"
		), __FUNCTION__);
		$this->migratorConfig = \Slimpd\Modules\Albummigrator\AlbumMigrator::parseConfig();
		$this->dbTstampsAlbum = self::getMigratedAlbumTimstamps();
		$this->dbTstampsTrack = self::getMigratedTrackTimstamps();
		$this->skipAlbum = TRUE;

		$this->migratedAlbums = 0;

		$query = "SELECT count(uid) AS itemsTotal FROM rawtagdata";
		$this->itemsTotal = (int) $this->db->query($query)->fetch_assoc()['itemsTotal'];
		$this->checkBatch();
		$this->container->batcher->finishAll();

		$this->finishJob(array(
			'msg' => 'migrated ' . $this->itemsChecked . ' files',
			'migratedAlbums' => $this->migratedAlbums
		), __FUNCTION__);
	}

	/**
	 * limiting SELECT query to $this->batchSize to avoid retrieving millions of records within a single query
	 * so this method calls itself recursively
	 *
	 */
	private function checkBatch() {
		$query = "SELECT * FROM rawtagdata ORDER BY relDirPathHash LIMIT " . $this->itemsChecked . "," . $this->batchSize;
		$result = $this->db->query($query);
		$counter = 0;
		while($record = $result->fetch_assoc()) {
			$this->itemsChecked++;
			$counter++;

			if($counter % $this->batchSize === 0) {
				cliLog(" shortening itemsChecked by " . $this->prevAlb->getTrackCount(), 10, "cyan");
				$this->itemsChecked -= ($this->prevAlb->getTrackCount()+1);
				cliLog(" batch complete...",9 , "cyan");
				// recursion
				return $this->checkBatch();
			}

			if($counter === 1) {
				$this->newPrevAlb($record);
				$this->skipAlbum = TRUE;
			}

			// directory had changed within the loop
			if($record['relDirPathHash'] !== $this->prevAlb->getRelDirPathHash() ) {
				$this->mayMigrate();
				cliLog('resetting previousAlbum', 10);
				$this->newPrevAlb($record);
				cliLog('  adding hash of dir '. $record['relDirPath'] .' to previousAlbum', 10);
				$this->skipAlbum = TRUE;
			}

			$this->updateJob(array(
				'currentItem' => $record['relPath'],
				'migratedAlbums' => $this->migratedAlbums
			));
			$this->checkTrackSkip($record);
			$this->prevAlb->addTrack($record);
			cliLog('adding track to previousAlbum: ' . $record['relPath'], 10);

			cliLog("#" . $this->itemsChecked . " " . $record['relPath'],2);

			// dont forget to check the last directory
			if($this->itemsChecked === $this->itemsTotal) {
				$this->mayMigrate();
			}
		}
	}

	private function checkAlbumSkip() {	
		// decide if we have to process album or if we can skip it
		if(isset($this->dbTstampsAlbum[ $this->prevAlb->getRelDirPathHash() ]) === FALSE) {
			cliLog('album does NOT exist in migrated data. migrating: ' . $this->prevAlb->getRelDirPath(), 5);
			$this->skipAlbum = FALSE;
			return;
		}
		if($this->dbTstampsAlbum[ $this->prevAlb->getRelDirPathHash() ] < $this->prevAlb->getDirectoryMtime()) {
			cliLog('dir-timestamp raw is more recent than migrated. migrating: ' . $this->prevAlb->getRelDirPath(), 5);
			cliLog('dir-timestamps mig: '. $this->dbTstampsAlbum[ $this->prevAlb->getRelDirPathHash() ] .' raw: ' . $this->prevAlb->getDirectoryMtime(), 10, 'yellow');
			$this->skipAlbum = FALSE;
			return;
		}
	}

	private function checkTrackSkip($record) {
				// decide if we have to process album based on single-track-change or if we can skip it
		if(isset($this->dbTstampsTrack[ $record['relPathHash'] ]) === FALSE) {
			cliLog('track does NOT exist in migrated data. migrating: ' . $record['relPath'], 5);
			$this->skipAlbum = FALSE;
			return;
		} 
		if($this->dbTstampsTrack[ $record['relPathHash'] ] < $record['filemtime']) {
			cliLog('track-imestamp raw is more recent than migrated. migrating: ' . $record['relPath'], 5);
			$this->skipAlbum = FALSE;
		}
	}

	private function mayMigrate() {
		$this->checkAlbumSkip();
		if($this->skipAlbum === TRUE) {
			cliLog('skipping migration for: ' . $this->prevAlb->getRelDirPath(), 5);
			return;
		}
		$this->prevAlb->run();
		$this->itemsProcessed += $this->prevAlb->getTrackCount();
		$this->migratedAlbums++;
	}

	private function newPrevAlb($record) {
		$this->prevAlb = new \Slimpd\Modules\Albummigrator\AlbumMigrator($this->container);
		$this->prevAlb->migratorConf = $this->migratorConfig;
		$this->prevAlb->useBatcher = $this->useBatcher;
		$this->prevAlb->setRelDirPathHash($record['relDirPathHash']);
		$this->prevAlb->setRelDirPath($record['relDirPath']);
		$this->prevAlb->setDirectoryMtime($record['directoryMtime']);
	}

	public function migrateSingleAlbum($albumUid) {
		$album = $this->container->albumRepo->getInstanceByAttributes(['uid' => $albumUid]);
		if($album === NULL) {
			return FALSE;
		}
		$this->migratorConfig = \Slimpd\Modules\Albummigrator\AlbumMigrator::parseConfig();

		// make sure to capture all available information
		$_SESSION['cliVerbosity'] = '10';

		$query = "SELECT * FROM rawtagdata WHERE relDirPathHash='". $album->getRelPathHash()."';";
		$result = $this->db->query($query);
		$counter = 0;
		while($record = $result->fetch_assoc()) {
			if($counter === 0) {
				$this->newPrevAlb($record);
			}
			$counter++;
			$this->prevAlb->addTrack($record);
		}
		$this->prevAlb->useBatcher = FALSE;
		$this->prevAlb->run();
		$this->container->batcher->finishAll();
		return $this->prevAlb;
	}
}
