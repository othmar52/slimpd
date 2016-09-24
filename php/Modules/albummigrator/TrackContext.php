<?php
namespace Slimpd\Modules\albummigrator;

class TrackContext extends \Slimpd\Models\Track {
	use \Slimpd\Modules\albummigrator\MigratorContext; // config
	protected $confKey = "track-tag-mapping-";
	
	// those attributes holds string values (track holds relational Uids)
	protected $album;
	protected $artist;
	protected $genre;
	protected $label;
	protected $remixArtists;
	protected $featuredArtists;
	
	// those attributes are used for "handle all directory tracks as album"
	protected $fileNameCase;
	protected $filenameSchema;
	protected $titleSchema;
	protected $artistSchema;
	protected $albumSchema;
	protected $numberSchema;
	protected $recommendations;
	protected $totalTracks;
	
	public function __construct($rawTagArray, $config) {
		$this->config = $config;
		$this->rawTagRecord = $rawTagArray;
		$this->process();
	}
	
	private function process() {
		$this->copyBaseProperties();
		$this->configBasedSetters();
		print_r($this); #die();
	}

	/**
	 * most rawTagData-fields are identical to track fields 
	 */
	private function copyBaseProperties() {
		$this->setUid($this->rawTagRecord['uid'])
			->setRelPath($this->rawTagRecord['relPath'])
			->setRelPathHash($this->rawTagRecord['relPathHash'])
			->setRelDirPath($this->rawTagRecord['relDirPath'])
			->setRelDirPathHash($this->rawTagRecord['relDirPathHash'])
			#->setAdded($this->rawTagRecord['added'])
			->setFilesize($this->rawTagRecord['filesize'])
			->setFilemtime($this->rawTagRecord['filemtime'])
			->setLastScan($this->rawTagRecord['lastScan'])
			->setImportStatus($this->rawTagRecord['importStatus'])
			->setFingerprint($this->rawTagRecord['fingerprint'])
			->setError($this->rawTagRecord['error']);
	}
	

	public function setArtist($value) {
		$this->artist = $value;
		return $this;
	}
	public function getArtist() {
		return $this->artist;
	}
	public function setAlbum($value) {
		$this->album = $value;
		return $this;
	}
	public function getAlbum() {
		return $this->album;
	}
	public function setGenre($value) {
		$this->genre = $value;
		return $this;
	}
	public function getGenre() {
		return $this->genre;
	}
	public function setLabel($value) {
		$this->label = $value;
		return $this;
	}
	public function getLabel() {
		return $this->label;
	}
	public function setRemixArtists($value) {
		$this->remixArtists = $value;
		return $this;
	}
	public function getRemixArtists() {
		return $this->remixArtists;
	}
	public function setFeaturedArtists($value) {
		$this->featuredArtists = $value;
		return $this;
	}
	public function getFeaturedArtists() {
		return $this->featuredArtists;
	}
	public function setTotalTracks($value) {
		$this->totalTracks = $value;
		return $this;
	}
	public function getTotalTracks() {
		return $this->totalTracks;
	}
}

