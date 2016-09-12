<?php
namespace Slimpd\Traits;
/* Copyright
 * 
 */
trait PropGroupTypeIds {
	protected $artistUid;
	protected $genreUid;
	protected $labelUid;

	// getter
	public function getArtistUid() {
		return $this->artistUid;
	}
	public function getGenreUid() {
		return $this->genreUid;
	}
	public function getLabelUid() {
		return $this->labelUid;
	}

	// setter
	public function setArtistUid($value) {
		$this->artistUid = $value;
		return $this;
	}
	public function setGenreUid($value) {
		$this->genreUid = $value;
		return $this;
	}
	public function setLabelUid($value) {
		$this->labelUid = $value;
		return $this;
	}
}
