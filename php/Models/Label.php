<?php
namespace Slimpd\Models;
/* Copyright
 *
 */
class Label extends \Slimpd\Models\AbstractModel {
	protected $title;
	protected $az09;
	protected $trackCount;
	protected $albumCount;

	public static $tableName = 'label';

	//setter
	public function setTitle($value) {
		$this->title = $value;
		return $this;
	}
	public function setAz09($value) {
		$this->az09 = $value;
		return $this;
	}
	public function setTrackCount($value) {
		$this->trackCount = $value;
		return $this;
	}
	public function setAlbumCount($value) {
		$this->albumCount = $value;
		return $this;
	}


	// getter
	public function getTitle() {
		return $this->title;
	}
	public function getAz09() {
		return $this->az09;
	}
	public function getTrackCount() {
		return $this->trackCount;
	}
	public function getAlbumCount() {
		return $this->albumCount;
	}
}
