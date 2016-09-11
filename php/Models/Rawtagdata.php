<?php
namespace Slimpd\Models;
/* Copyright
 *
 */
class Rawtagdata extends \Slimpd\Models\AbstractTrack {
	protected $artist;
	protected $album;
	protected $genre;
	
	protected $date;
	protected $publisher;
	protected $totalTracks;
	protected $albumArtist;
	protected $remixer;
	protected $language;
	protected $country;

	protected $directoryMtime = 0;
	
	protected $initialKey;
	protected $textBpm;
	protected $textBpmQuality;
	protected $textPeakDb;
	protected $textPerceivedDb;
	protected $textRating;
	protected $textDiscogsReleaseId;
	protected $textUrlUser;
	protected $textSource;

	protected $dynamicRange;
	
	protected $audioBitrateMode;
	
	protected $added;

	public static $tableName = 'rawtagdata';


	//setter
	public function setArtist($value) {
		$this->artist = $value;
		return $this;
	}
	public function setAlbum($value) {
		$this->album = $value;
		return $this;
	}
	public function setGenre($value) {
		$this->genre = $value;
		return $this;
	}
	public function setDate($value) {
		$this->date = $value;
		return $this;
	}
	public function setPublisher($value) {
		$this->publisher = $value;
		return $this;
	}
	public function setTotalTracks($value) {
		$this->totalTracks = $value;
		return $this;
	}
	public function setAlbumArtist($value) {
		$this->albumArtist = $value;
		return $this;
	}
	public function setRemixer($value) {
		$this->remixer = $value;
		return $this;
	}
	public function setLanguage($value) {
		$this->language = $value;
		return $this;
	}
	public function setCountry($value) {
		$this->country = $value;
		return $this;
	}

	public function setDirectoryMtime($value) {
		$this->directoryMtime = $value;
		return $this;
	}

	public function setInitialKey($value) {
		$this->initialKey = $value;
		return $this;
	}
	public function setTextBpm($value) {
		$this->textBpm = $value;
		return $this;
	}
	public function setTextBpmQuality($value) {
		$this->textBpmQuality = $value;
		return $this;
	}
	public function setTextPeakDb($value) {
		$this->textPeakDb = $value;
		return $this;
	}
	public function setTextPerceivedDb($value) {
		$this->textPerceivedDb = $value;
		return $this;
	}
	public function setTextRating($value) {
		$this->textRating = $value;
		return $this;
	}
	public function setTextDiscogsReleaseId($value) {
		$this->textDiscogsReleaseId = $value;
		return $this;
	}
	public function setTextUrlUser($value) {
		$this->textUrlUser = $value;
		return $this;
	}
	public function setTextSource($value) {
		$this->textSource = $value;
		return $this;
	}
	//...

	public function setDynamicRange($value) {
		$this->dynamicRange = $value;
	}
	// ...

	public function setAudioBitrateMode($value) {
		$this->audioBitrateMode = $value;
		return $this;
	}

	public function setAdded($value) {
		$this->added = $value;
		return $this;
	}
	
	

	
	
	
	// getter
	public function getArtist() {
		return $this->artist;
	}
	public function getAlbum() {
		return $this->album;
	}
	public function getGenre() {
		return $this->genre;
	}
	public function getDate() {
		return $this->date;
	}
	public function getPublisher() {
		return $this->publisher;
	}
	public function getTotalTracks() {
		return $this->totalTracks;
	}
	public function getAlbumArtist() {
		return $this->albumArtist;
	}
	public function getRemixer() {
		return $this->remixer;
	}
	public function getLanguage() {
		return $this->language;
	}
	public function getCountry() {
		return $this->country;
	}
	// ...
	public function getDirectoryMtime() {
		return $this->directoryMtime;
	}
	// ...
	public function getInitialKey() {
		return $this->initialKey;
	}
	public function getTextBpm() {
		return $this->textBpm;
	}
	public function getTextBpmQuality() {
		return $this->textBpmQuality;
	}
	public function getTextPeakDb() {
		return $this->textPeakDb;
	}
	public function getTextPerceivedDb() {
		return $this->textPerceivedDb;
	}
	public function getTextRating() {
		return $this->textRating;
	}
	public function getTextDiscogsReleaseId() {
		return $this->textDiscogsReleaseId;
	}
	public function getTextUrlUser() {
		return $this->textUrlUser;
	}
	public function getTextSource() {
		return $this->textSource;
	}

	public function getDynamicRange() {
		return $this->dynamicRange;
	}

	public function getAudioBitrateMode() {
		return $this->audioBitrateMode;
	}

	public function getAdded() {
		return $this->added;
	}
}
