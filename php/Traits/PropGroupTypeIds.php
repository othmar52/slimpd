<?php
namespace Slimpd\Traits;
/* Copyright
 * 
 */
trait PropGroupTypeIds {
	protected $artistId;
	protected $genreId;
	protected $labelId;

	// getter
	public function getArtistId() {
		return $this->artistId;
	}
	public function getGenreId() {
		return $this->genreId;
	}
	public function getLabelId() {
		return $this->labelId;
	}

	// setter
	public function setArtistId($value) {
		$this->artistId = $value;
		return $this;
	}
	public function setGenreId($value) {
		$this->genreId = $value;
		return $this;
	}
	public function setLabelId($value) {
		$this->labelId = $value;
		return $this;
	}
}
