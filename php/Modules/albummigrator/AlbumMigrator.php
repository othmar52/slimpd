<?php
namespace Slimpd\Modules\albummigrator;

class AlbumMigrator {
	public $conf;
	protected $rawTagItems;
	protected $trackContextItems;
	protected $albumContextItem;
	protected $jumbleJudge;
	
	public function run() {
		// create albumContext
		$this->albumContextItem = new \Slimpd\Modules\albummigrator\AlbumContext();
		$this->jumbleJudge = new \Slimpd\Modules\albummigrator\JumbleJudge();
		
		// create TrackContext for each input item
		foreach($this->rawTagItems as $idx => $rawTagItem) {
			$this->trackContextItems[$idx] = new \Slimpd\Modules\albummigrator\TrackContext($rawTagItem, $this->conf);
			$this->jumbleJudge->collect($this->trackContextItems[$idx]);
		}
		print_r($this->jumbleJudge);
		#if(\Slim\Slim::getInstance()->config["modules"]["enable_guessing"] == "1") {
		#	$this->init
		#}
		$this->albumContextItem->migrate();
		foreach($this->trackContextItems as $trackContextItems) {
			$trackContextItems->migrate();
		}
		#var_dump($this);
		#die('blaaaaa');
	}
	
	public static function parseConfig() {
		return parse_ini_file(APP_ROOT . "config/importer/tag-mapper.ini", TRUE);
	}
	
	public function addTrack(array $rawTagDataArray) {
		$this->rawTagItems[] = $rawTagDataArray;
		return $this;
	}
	
	
	
	
	// TODO: get this from Trait
	protected $directoryMtime;
	public function getDirectoryMtime() {
		return $this->directoryMtime;
	}
	public function setDirectoryMtime($value) {
		$this->directoryMtime = $value;
		return $this;
	}
	
	protected $relPath;
	protected $relPathHash;
	protected $relDirPath;
	protected $relDirPathHash;
	protected $filesize;
	protected $filemtime = 0;
	protected $importStatus;

	public function getRelPath() {
		return $this->relPath;
	}
	public function getRelPathHash() {
		return $this->relPathHash;
	}
	public function getRelDirPath() {
		return $this->relDirPath;
	}
	public function getRelDirPathHash() {
		return $this->relDirPathHash;
	}
	public function getFilesize() {
		return $this->filesize;
	}
	public function getFilemtime() {
		return $this->filemtime;
	}
	public function getImportStatus() {
		return $this->importStatus;
	}
	
	public function setRelPath($value) {
		$this->relPath = $value;
		return $this;
	}
	public function setRelPathHash($value) {
		$this->relPathHash = $value;
		return $this;
	}
	public function setRelDirPath($value) {
		$this->relDirPath = $value;
		return $this;
	}
	public function setRelDirPathHash($value) {
		$this->relDirPathHash = $value;
		return $this;
	}
	public function setFilesize($value) {
		$this->filesize = $value;
		return $this;
	}
	public function setFilemtime($value) {
		$this->filemtime = $value;
		return $this;
	}
	public function setImportStatus($value) {
		$this->importStatus = $value;
		return $this;
	}
}
