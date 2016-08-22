<?php
namespace Slimpd\Models;

class Rawtagdata extends \Slimpd\Models\AbstractFilesystemItem
{
	protected $artist;
	protected $title;
	protected $album;
	protected $genre;
	protected $comment;
	protected $year;
	protected $date;
	protected $publisher;
	protected $trackNumber;
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
	protected $textCatalogNumber;
	protected $textDiscogsReleaseId;
	protected $textUrlUser;
	protected $textSource;

	protected $fingerprint;
	protected $mimeType;
	protected $miliseconds;
	protected $dynamicRange;
	
	protected $audioBitrate;
	protected $audioBitrateMode;
	protected $audioBitsPerSample;
	protected $audioSampleRate;
	protected $audioChannels;
	protected $audioLossless;
	protected $audioComprRatio;
	protected $audioDataformat;
	protected $audioEncoder;
	protected $audioProfile;
	
	protected $videoDataformat;
	protected $videoCodec;
	protected $videoResolutionX;
	protected $videoResolutionY;
	protected $videoFramerate;
	
	protected $lastScan;
	protected $importStatus;
	protected $error;
	protected $added;

	public static $tableName = 'rawtagdata';


	//setter
	public function setArtist($value) {
		$this->artist = $value;
	}
	public function setTitle($value) {
		$this->title = $value;
	}
	public function setAlbum($value) {
		$this->album = $value;
	}
	public function setGenre($value) {
		$this->genre = $value;
	}
	public function setComment($value) {
		$this->comment = $value;
	}
	public function setYear($value) {
		$this->year = $value;
	}
	public function setDate($value) {
		$this->date = $value;
	}
	public function setPublisher($value) {
		$this->publisher = $value;
	}
	public function setTrackNumber($value) {
		$this->trackNumber = $value;
	}
	public function setTotalTracks($value) {
		$this->totalTracks = $value;
	}
	public function setAlbumArtist($value) {
		$this->albumArtist = $value;
	}
	public function setRemixer($value) {
		$this->remixer = $value;
	}
	public function setLanguage($value) {
		$this->language = $value;
	}
	public function setCountry($value) {
		$this->country = $value;
	}
	//...
	public function setDirectoryMtime($value) {
		$this->directoryMtime = $value;
	}
	// ...
	public function setInitialKey($value) {
		$this->initialKey = $value;
	}
	public function setTextBpm($value) {
		$this->textBpm = $value;
	}
	public function setTextBpmQuality($value) {
		$this->textBpmQuality = $value;
	}
	public function setTextPeakDb($value) {
		$this->textPeakDb = $value;
	}
	public function setTextPerceivedDb($value) {
		$this->textPerceivedDb = $value;
	}
	public function setTextRating($value) {
		$this->textRating = $value;
	}
	public function setTextCatalogNumber($value) {
		$this->textCatalogNumber = $value;
	}
	public function setTextDiscogsReleaseId($value) {
		$this->textDiscogsReleaseId = $value;
	}
	public function setTextUrlUser($value) {
		$this->textUrlUser = $value;
	}
	public function setTextSource($value) {
		$this->textSource = $value;
	}
	//...
	public function setFingerprint($value) {
		$this->fingerprint = $value;
	}
	public function setMimeType($value) {
		$this->mimeType = $value;
	}
	public function setMiliseconds($value) {
		$this->miliseconds = $value;
	}
	public function setDynamicRange($value) {
		$this->dynamicRange = $value;
	}
	// ...
	public function setAudioBitrate($value) {
		$this->audioBitrate = $value;
	}
	public function setAudioBitrateMode($value) {
		$this->audioBitrateMode = $value;
	}
	public function setAudioBitsPerSample($value) {
		$this->audioBitsPerSample = $value;
	}
	public function setAudioSampleRate($value) {
		$this->audioSampleRate = $value;
	}
	public function setAudioChannels($value) {
		$this->audioChannels = $value;
	}
	public function setAudioLossless($value) {
		$this->audioLossless = $value;
	}
	public function setAudioComprRatio($value) {
		$this->audioComprRatio = $value;
	}
	public function setAudioDataformat($value) {
		$this->audioDataformat = $value;
	}
	public function setAudioEncoder($value) {
		$this->audioEncoder = $value;
	}
	public function setAudioProfile($value) {
		$this->audioProfile = $value;
	}
	// ...
	public function setVideoDataformat($value) {
		$this->videoDataformat = $value;
	}
	public function setVideoCodec($value) {
		$this->videoCodec = $value;
	}
	public function setVideoResolutionX($value) {
		$this->videoResolutionX = $value;
	}
	public function setVideoResolutionY($value) {
		$this->videoResolutionY = $value;
	}
	public function setVideoFramerate($value) {
		$this->videoFramerate = $value;
	}
	// ...
	public function setLastScan($value) {
		$this->lastScan = $value;
	}
	public function setImportStatus($value) {
		$this->importStatus = $value;
	}
	public function setError($value) {
		$this->error = $value;
	}
	public function setAdded($value) {
		$this->added = $value;
	}
	
	

	
	
	
	// getter
	public function getArtist() {
		return $this->artist;
	}
	public function getTitle() {
		return $this->title;
	}
	public function getAlbum() {
		return $this->album;
	}
	public function getGenre() {
		return $this->genre;
	}
	public function getComment() {
		return $this->comment;
	}
	public function getYear() {
		return $this->year;
	}
	public function getDate() {
		return $this->date;
	}
	public function getPublisher() {
		return $this->publisher;
	}
	public function getTrackNumber() {
		return $this->trackNumber;
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
	public function getTextCatalogNumber() {
		return $this->textCatalogNumber;
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
	//...
	public function getFingerprint() {
		return $this->fingerprint;
	}
	public function getMimeType() {
		return $this->mimeType;
	}
	public function getMiliseconds() {
		return $this->miliseconds;
	}
	public function getDynamicRange() {
		return $this->dynamicRange;
	}
	// ...
	public function getAudioBitrate() {
		return $this->audioBitrate;
	}
	public function getAudioBitrateMode() {
		return $this->audioBitrateMode;
	}
	public function getAudioBitsPerSample() {
		return $this->audioBitsPerSample;
	}
	public function getAudioSampleRate() {
		return $this->audioSampleRate;
	}
	public function getAudioChannels() {
		return $this->audioChannels;
	}
	public function getAudioLossless() {
		return $this->audioLossless;
	}
	public function getAudioComprRatio() {
		return $this->audioComprRatio;
	}
	public function getAudioDataformat() {
		return $this->audioDataformat;
	}
	public function getAudioEncoder() {
		return $this->audioEncoder;
	}
	public function getAudioProfile() {
		return $this->audioProfile;
	}
	//...
	public function getVideoDataformat() {
		return $this->videoDataformat;
	}
	public function getVideoCodec() {
		return $this->videoCodec;
	}
	public function getVideoResolutionX() {
		return $this->videoResolutionX;
	}
	public function getVideoResolutionY() {
		return $this->videoResolutionY;
	}
	public function getVideoFramerate() {
		return $this->videoFramerate;
	}
	// ...
	public function getLastScan() {
		return $this->lastScan;
	}
	public function getImportStatus() {
		return $this->importStatus;
	}
	
	public function getError() {
		return $this->error;
	}

	public function getAdded() {
		return $this->added;
	}
}
