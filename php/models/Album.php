<?php
namespace Slimpd;

class Album extends \Slimpd\AbstractModel
{
	protected $id;
	protected $artistId;
	protected $title;
	protected $relativePath;
	protected $relativePathHash;
	protected $year;
	protected $month;
	protected $genreId;
	protected $labelId;
	protected $filemtime;
	protected $added;
	protected $discs;
	protected $importStatus;
	protected $lastScan;
	protected $albumDr;
	protected $trackCount;
	
	public static $tableName = 'album';
	
	
	public function getAlbumByRelativePath($relativePath) {
		
		$app = \Slim\Slim::getInstance();
		$query = "
			SELECT * 
			FROM album
			WHERE relativePathHash=\"" . getFilePathHash($relativePath) . "\"";
		$result = $app->db->query($query);
		$record = $result->fetch_assoc();
		if($record === NULL) {
			return NULL;
		}
		$this->mapArrayToInstance($record);
	}
	
	
	
	//setter
	public function setId($value) {
		$this->id = $value;
	}
	public function setArtistId($value) {
		$this->artistId = $value;
	}
	public function setTitle($value) {
		$this->title = $value;
	}
	public function setRelativePath($value) {
		$this->relativePath = $value;
	}
	public function setRelativePathHash($value) {
		$this->relativePathHash = $value;
	}
	public function setYear($value) {
		$this->year = $value;
	}
	public function setMonth($value) {
		$this->month = $value;
	}
	public function setGenreId($value) {
		$this->genreId = $value;
	}
	public function setLabelId($value) {
		$this->labelId = $value;
	}
	public function setFilemtime($value) {
		$this->filemtime = $value;
	}
	public function setAdded($value) {
		$this->added = $value;
	}
	public function setDiscs($value) {
		$this->discs = $value;
	}
	public function setImportStatus($value) {
		$this->importStatus = $value;
	}
	public function setLastScan($value) {
		$this->lastScan = $value;
	}
	public function setAlbumDr($value) {
		$this->albumDr = $value;
	}
	public function setTrackCount($value) {
		$this->trackCount = $value;
	}
	
	
	
	// getter
	public function getId() {
		return $this->id;
	}
	public function getArtistId() {
		return $this->artistId;
	}
	public function getTitle() {
		return $this->title;
	}
	public function getRelativePath() {
		return $this->relativePath;
	}
	public function getRelativePathHash() {
		return $this->relativePathHash;
	}
	public function getYear() {
		return $this->year;
	}
	public function getMonth() {
		return $this->month;
	}
	public function getGenreId() {
		return $this->genreId;
	}
	public function getLabelId() {
		return $this->labelId;
	}
	public function getFilemtime() {
		return $this->filemtime;
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
	public function getLastScan() {
		return $this->lastScan;
	}
	public function getAlbumDr() {
		return $this->albumDr;
	}
	public function getTrackCount() {
		return $this->trackCount;
	}
	
	
}
