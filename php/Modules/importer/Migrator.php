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
		foreach(\Slimpd\Modules\Importer::getInitialDatabaseQueries() as $query) {
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
	public function run($resetMigrationPhase = FALSE) {
		
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
		
		$this->dbTstampsAlbum = self::getMigratedAlbumTimstamps();
		$this->dbTstampsTrack = self::getMigratedTrackTimstamps();
		$this->skipAlbum = TRUE;

		$this->migratedAlbums = 0;
		
		
		$query = "SELECT count(id) AS itemCountTotal FROM rawtagdata";
		$this->itemCountTotal = (int) $app->db->query($query)->fetch_assoc()['itemCountTotal'];
		
		$query = "SELECT * FROM rawtagdata ORDER BY relativeDirectoryPathHash ";
		$result = $app->db->query($query);
		while($record = $result->fetch_assoc()) {
			$this->itemCountChecked++;
			if($this->itemCountChecked === 1) {
				$this->newPrevAlb($record);
			}
			
			// directory had changed within the loop
			if($record['relativeDirectoryPathHash'] !== $this->prevAlb->getRelativeDirectoryPathHash() ) {
				$this->mayMigrate();
				cliLog('resetting previousAlbum', 10);
				$this->newPrevAlb($record);
				cliLog('  adding hash of dir '. $record['relativeDirectoryPath'] .' to previousAlbum', 10);
				$this->skipAlbum = TRUE;
			}
			
			$this->updateJob(array(
				'currentItem' => $record['relativePath'],
				'migratedAlbums' => $this->migratedAlbums
			));
			$this->checkTrackSkip($record);
			$this->prevAlb->addTrack($record);
			cliLog('adding track to previousAlbum: ' . $record['relativePath'], 10);
			
			cliLog("#" . $this->itemCountChecked . " " . $record['relativePath'],2);
			
			// dont forget to check the last directory
			if($this->itemCountChecked === $this->itemCountTotal && $this->itemCountTotal > 1) {
				$this->mayMigrate();
			}
		}

		$this->finishJob(array(
			'msg' => 'migrated ' . $this->itemCountChecked . ' files',
			'migratedAlbums' => $this->migratedAlbums
		), __FUNCTION__);
	}

	private function checkAlbumSkip() {	
		// decide if we have to process album or if we can skip it
		if(isset($this->dbTstampsAlbum[ $this->prevAlb->getRelativeDirectoryPathHash() ]) === FALSE) {
			cliLog('album does NOT exist in migrated data. migrating: ' . $this->prevAlb->getRelativeDirectoryPath(), 5);
			$this->skipAlbum = FALSE;
			return;
		}
		if($this->dbTstampsAlbum[ $this->prevAlb->getRelativeDirectoryPathHash() ] < $this->prevAlb->getDirectoryMtime()) {
			cliLog('dir-timestamp raw is more recent than migrated. migrating: ' . $this->prevAlb->getRelativeDirectoryPath(), 5);
			cliLog('dir-timestamps mig: '. $this->dbTstampsAlbum[ $this->prevAlb->getRelativeDirectoryPathHash() ] .' raw: ' . $this->prevAlb->getDirectoryMtime(), 10, 'yellow');
			$this->skipAlbum = FALSE;
			return;
		}
	}
	
	private function checkTrackSkip($record) {
				// decide if we have to process album based on single-track-change or if we can skip it
		if(isset($this->dbTstampsTrack[ $record['relativePathHash'] ]) === FALSE) {
			cliLog('track does NOT exist in migrated data. migrating: ' . $record['relativePath'], 5);
			$this->skipAlbum = FALSE;
			return;
		} 
		if($this->dbTstampsTrack[ $record['relativePathHash'] ] < $record['filemtime']) {
			cliLog('track-imestamp raw is more recent than migrated. migrating: ' . $record['relativePath'], 5);
			$this->skipAlbum = FALSE;
		}
	}
	
	private function mayMigrate() {
		$this->checkAlbumSkip();
		if($this->skipAlbum === TRUE) {
			cliLog('skipping migration for: ' . $this->prevAlb->getRelativeDirectoryPath(), 5);
			return;
		}
		$this->prevAlb->run();
		$this->migratedAlbums++;
	}
	
	private function newPrevAlb($record) {
		$this->prevAlb = new \Slimpd\Modules\AlbumMigrator();
		$this->prevAlb->setRelativeDirectoryPathHash($record['relativeDirectoryPathHash']);
		$this->prevAlb->setRelativeDirectoryPath($record['relativeDirectoryPath']);
		$this->prevAlb->setDirectoryMtime($record['directoryMtime']);
	}
}
