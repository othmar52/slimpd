<?php
namespace Slimpd\Traits;
/* Copyright
 * 
 */
trait PropertyTopGenre {
	protected $topGenreUids;

	public function getTopGenreUids() {
		return $this->topGenreUids;
	}

	public function setTopGenreUids($value) {
		$this->topGenreUids = $value;
		return $this;
	}
}
