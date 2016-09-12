<?php
namespace Slimpd\Traits;
/* Copyright
 * 
 */
trait PropGroupCounters {
	protected $trackCount;
	protected $albumCount;

	// getter
	public function getTrackCount() {
		return $this->trackCount;
	}
	public function getAlbumCount() {
		return $this->albumCount;
	}

	// setter
	public function setTrackCount($value) {
		$this->trackCount = $value;
		return $this;
	}
	public function setAlbumCount($value) {
		$this->albumCount = $value;
		return $this;
	}
}
