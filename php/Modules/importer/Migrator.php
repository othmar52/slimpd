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
class Migrator extends \Slimpd\Modules\importer\AbstractImporter {
	private $dbTstampsTrack = array();
	private $dbTstampsAlbum = array();
	
	private $skipAlbum = TRUE;
	
	private $migratorConfig = array();
	
	public $migratedAlbums = 0;
	
	private $albumMigrator;
	
	// only for development purposes
	public function tempResetMigrationPhase() {
		cliLog('truncating all tables with migrated data', 1, 'red', TRUE);
		foreach(\Slimpd\Modules\importer\DatabaseStuff::getInitialDatabaseQueries() as $query) {
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
		
		$query = "SELECT relPathHash,filemtime FROM " . az09($tablename);
		$result = \Slim\Slim::getInstance()->db->query($query);
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
		$app = \Slim\Slim::getInstance();
		
		$this->migratorConfig = \Slimpd\Modules\albummigrator\AlbumMigrator::parseConfig();
		$this->dbTstampsAlbum = self::getMigratedAlbumTimstamps();
		$this->dbTstampsTrack = self::getMigratedTrackTimstamps();
		
		$query = "SELECT count(uid) AS itemsTotal FROM rawtagdata";
		$this->itemsTotal = (int) $app->db->query($query)->fetch_assoc()['itemsTotal'];

		$this->migratedAlbums = 0;
		
		// all dirHashes of rawtagdata - no matter if already migrated or not
		$dirHashes = array();
		$query = "SELECT DISTINCT(relDirPathHash) AS dirHash FROM rawtagdata;";
		$result = $app->db->query($query);
		while($record = $result->fetch_assoc()) {
			$this->updateJob(array(
				'currentItem' => "dirHash:" . $record['dirHash'],
				'migratedAlbums' => $this->migratedAlbums
			));
			$this->checkDirMigration($record["dirHash"]);
			$dirHashes[] = $record["dirHash"];
		}

		$this->finishJob(array(
			'msg' => 'migrated ' . $this->itemsChecked . ' files',
			'migratedAlbums' => $this->migratedAlbums
		), __FUNCTION__);
	}

	// fetches records of rawtagdata
	// comperes modified-times and decides if the migration should be triggered
	private function checkDirMigration($dirHash) {
		$app = \Slim\Slim::getInstance();
		$this->skipAlbum = TRUE;
		$query = "SELECT * FROM rawtagdata WHERE relDirPathHash=\"" . $dirHash . "\";";
		$result = $app->db->query($query);
		$this->albumMigrator = new \Slimpd\Modules\albummigrator\AlbumMigrator();
		
		$trackCounter = 0;
		while($record = $result->fetch_assoc()) {
			$trackCounter++;
			$this->itemsChecked++;
			if($trackCounter === 1) {
				$this->albumMigrator->conf = $this->migratorConfig;
				$this->albumMigrator->setRelDirPathHash($record['relDirPathHash']);
				$this->albumMigrator->setRelDirPath($record['relDirPath']);
				$this->albumMigrator->setDirectoryMtime($record['directoryMtime']);
			}
			$this->updateJob(array(
				'currentItem' => $record['relPath'],
				'migratedAlbums' => $this->migratedAlbums
			));
			$this->checkTrackSkip($record);
			$this->albumMigrator->addTrack($record);
			cliLog('adding track to previousAlbum: ' . $record['relPath'], 10);
			
			cliLog("#" . $this->itemsChecked . " " . $record['relPath'],2);
		}
		$this->mayMigrate();
	}


	private function checkAlbumSkip() {	
		// decide if we have to process album or if we can skip it
		if(isset($this->dbTstampsAlbum[ $this->albumMigrator->getRelDirPathHash() ]) === FALSE) {
			cliLog('album does NOT exist in migrated data. migrating: ' . $this->albumMigrator->getRelDirPath(), 5);
			$this->skipAlbum = FALSE;
			return;
		}
		if($this->dbTstampsAlbum[ $this->albumMigrator->getRelDirPathHash() ] < $this->albumMigrator->getDirectoryMtime()) {
			cliLog('dir-timestamp raw is more recent than migrated. migrating: ' . $this->albumMigrator->getRelDirPath(), 5);
			cliLog('dir-timestamps mig: '. $this->dbTstampsAlbum[ $this->albumMigrator->getRelDirPathHash() ] .' raw: ' . $this->albumMigrator->getDirectoryMtime(), 10, 'yellow');
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
			cliLog('skipping migration for: ' . $this->albumMigrator->getRelDirPath(), 5);
			return;
		}
		$this->albumMigrator->run();
		$this->itemsProcessed += $this->albumMigrator->getTrackCount();
		$this->migratedAlbums++;
	}
}
