<?php
namespace Slimpd\Traits;
/* Copyright
 * 
 */
trait PropGroupAudio {
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

	// getter
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

	// setter
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
}
