<?php
namespace Slimpd\Models;

abstract class AbstractTrack extends \Slimpd\Models\AbstractFilesystemItem {

	protected $title;
	protected $year;
	protected $comment;
	protected $trackNumber;
	protected $catalogNr;

	protected $fingerprint;
	protected $mimeType;
	protected $miliseconds;
	
	protected $audioBitrate;
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
	
	protected $importStatus;
	protected $error;
	
	// getter
	public function getTitle() {
		return $this->title;
	}
	public function getYear() {
		return $this->year;
	}
	public function getComment() {
		return $this->comment;
	}
	public function getTrackNumber() {
		return $this->trackNumber;
	}
	public function getCatalogNr() {
		return $this->catalogNr;
	}

	public function getFingerprint() {
		return $this->fingerprint;
	}
	public function getMimeType() {
		return $this->mimeType;
	}
	public function getMiliseconds() {
		return $this->miliseconds;
	}
	
	public function getAudioBitrate() {
		return $this->audioBitrate;
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

	public function getImportStatus() {
		return $this->importStatus;
	}
	public function getError() {
		return $this->error;
	}
	
	// setter
	public function setTitle($value) {
		$this->title = $value;
	}
	public function setYear($value) {
		$this->year = $value;
	}
	public function setComment($value) {
		$this->comment = $value;
	}
	public function setTrackNumber($value) {
		$this->trackNumber = $value;
	}
	public function setCatalogNr($value) {
		$this->catalogNr = $value;
	}

	public function setFingerprint($value) {
		$this->fingerprint = $value;
	}
	public function setMimeType($value) {
		$this->mimeType = $value;
	}
	public function setMiliseconds($value) {
		$this->miliseconds = $value;
	}
	
	public function setAudioBitrate($value) {
		$this->audioBitrate = $value;
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
	
	public function setImportStatus($value) {
		$this->importStatus = $value;
	}
	public function setError($value) {
		$this->error = $value;
	}
}

