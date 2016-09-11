<?php
namespace Slimpd\Models;
/* Copyright
 *
 */
class Album extends \Slimpd\Models\AbstractFilesystemItem {
	use \Slimpd\Traits\PropertyLastScan; // lastScan
	use \Slimpd\Traits\PropGroupTypeIds; // artistId, labelId, genreId

	
	protected $title;
	protected $year;
	protected $month;
	
	protected $catalogNr;

	protected $added;
	protected $discs;

	protected $importStatus;
	protected $albumDr;
	protected $trackCount;

	protected $isMixed;
	protected $isJumble;
	protected $isLive;

	protected $discogsId;
	protected $rolldabeatsId;
	protected $beatportId;
	protected $junoId;

	public static $tableName = 'album';

	public function getAlbumByRelPath($relPath) {
		$app = \Slim\Slim::getInstance();
		$query = "
			SELECT * 
			FROM album
			WHERE relPathHash=\"" . getFilePathHash($relPath) . "\"";
		$result = $app->db->query($query);
		$record = $result->fetch_assoc();
		if($record === NULL) {
			return NULL;
		}
		$this->mapArrayToInstance($record);
	}

	//setter
	public function setTitle($value) {
		$this->title = $value;
		return $this;
	}
	public function setYear($value) {
		$this->year = $value;
		return $this;
	}
	public function setMonth($value) {
		$this->month = $value;
		return $this;
	}
	public function setCatalogNr($value) {
		$this->catalogNr = $value;
		return $this;
	}
	public function setAdded($value) {
		$this->added = $value;
		return $this;
	}
	public function setDiscs($value) {
		$this->discs = $value;
		return $this;
	}
	public function setImportStatus($value) {
		$this->importStatus = $value;
	}
	public function setAlbumDr($value) {
		$this->albumDr = $value;
		return $this;
	}
	public function setTrackCount($value) {
		$this->trackCount = $value;
		return $this;
	}


	public function setIsMixed($value) {
		$this->isMixed = $value;
		return $this;
	}
	public function setIsJumble($value) {
		$this->isJumble = $value;
		return $this;
	}
	public function setIsLive($value) {
		$this->isLive = $value;
		return $this;
	}


	public function setDiscogsId($value) {
		$this->discogsId = $value;
		return $this;
	}
	public function setRolldabeatsId($value) {
		$this->rolldabeatsId = $value;
		return $this;
	}
	public function setBeatportId($value) {
		$this->beatportId = $value;
		return $this;
	}
	public function setJunoId($value) {
		$this->junoId = $value;
		return $this;
	}



	// getter

	public function getTitle() {
		return $this->title;
	}
	public function getYear() {
		return $this->year;
	}
	public function getMonth() {
		return $this->month;
	}
	public function getCatalogNr() {
		return $this->catalogNr;
	}
	public function getAdded() {
		return $this->added;
	}
	public function getDiscs() {
		return $this->discs;
	}
	public function getImportStatus() {
		return $this->importStatus;
	}
	public function getAlbumDr() {
		return $this->albumDr;
	}
	public function getTrackCount() {
		return $this->trackCount;
	}

	public function getIsMixed() {
		return $this->isMixed;
	}
	public function getIsJumble() {
		return $this->isJumble;
	}
	public function getIsLive() {
		return $this->isLive;
	}

	public function getDiscogsId() {
		return $this->discogsId;
	}
	public function getRolldabeatsId() {
		return $this->rolldabeatsId;
	}
	public function getBeatportId() {
		return $this->beatportId;
	}
	public function getJunoId() {
		return $this->junoId;
	}
}
