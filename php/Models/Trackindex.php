<?php
namespace Slimpd\Models;

class Trackindex extends \Slimpd\Models\AbstractModel
{
	protected $artist;
	protected $title;
	protected $allchunks;
	
	public static $tableName = 'trackindex';

	//setter
	public function setArtist($value) {
		$this->artist = $value;
	}
	public function setTitle($value) {
		$this->title = $value;
	}
	public function setAllchunks($value) {
		$this->allchunks = $value;
	}


	// getter
	public function getArtist() {
		return $this->artist;
	}
	public function getTitle() {
		return $this->title;
	}
	public function getAllchunks() {
		return $this->allchunks;
	}
}