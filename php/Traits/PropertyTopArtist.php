<?php
namespace Slimpd\Traits;
/* Copyright
 * 
 */
trait PropertyTopArtist {
	protected $topArtistUids;

	public function getTopArtistUids() {
		return $this->topArtistUids;
	}

	public function setTopArtistUids($value) {
		$this->topArtistUids = $value;
		return $this;
	}
}
