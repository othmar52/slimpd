<?php
namespace Slimpd\Modules\importer;

class Migrator extends \Slimpd\Modules\importer\AbstractImporter {
	private $dbTstampsTrack = array();
	private $dbTstampsAlbum = array();
	
	private $skipAlbum = TRUE;
	
	public $migratedAlbums = 0;
	
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
		
		$this->dbTstampsAlbum = self::getMigratedAlbumTimstamps();
		$this->dbTstampsTrack = self::getMigratedTrackTimstamps();
		$this->skipAlbum = TRUE;

		$this->migratedAlbums = 0;
		
		
		$query = "SELECT count(id) AS itemsTotal FROM rawtagdata";
		$this->itemsTotal = (int) $app->db->query($query)->fetch_assoc()['itemsTotal'];
		
		$query = "SELECT * FROM rawtagdata ORDER BY relDirPathHash ";
		$result = $app->db->query($query);
		while($record = $result->fetch_assoc()) {
			$this->itemsChecked++;
			if($this->itemsChecked === 1) {
				$this->newPrevAlb($record);
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
			if($this->itemsChecked === $this->itemsTotal && $this->itemsTotal > 1) {
				$this->mayMigrate();
			}
		}

		$this->finishJob(array(
			'msg' => 'migrated ' . $this->itemsChecked . ' files',
			'migratedAlbums' => $this->migratedAlbums
		), __FUNCTION__);
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
		$this->migratedAlbums++;
	}
	
	private function newPrevAlb($record) {
		$this->prevAlb = new \Slimpd\Modules\AlbumMigrator();
		$this->prevAlb->setRelDirPathHash($record['relDirPathHash']);
		$this->prevAlb->setRelDirPath($record['relDirPath']);
		$this->prevAlb->setDirectoryMtime($record['directoryMtime']);
	}
}
