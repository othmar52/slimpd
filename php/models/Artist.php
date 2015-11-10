<?php
namespace Slimpd;

class Artist extends AbstractModel
{
	protected $id;
	protected $title;
	protected $az09;
	protected $trackCount;
	protected $albumCount;
	
	public static $tableName = 'artist';
	
	
	protected static function unifyItemnames($items) {
		$return = array();
		foreach($items as $az09 => $itemString) {
			$return[$az09] = $itemString;
		}
		return $return;
	}
	


	//setter
	public function setId($value) {
		$this->id = $value;
	}
	public function setTitle($value) {
		$this->title = $value;
	}
	public function setAz09($value) {
		$this->az09 = $value;
	}
	public function setTrackCount($value) {
		$this->trackCount = $value;
	}
	public function setAlbumCount($value) {
		$this->albumCount = $value;
	}
	
	
	// getter
	public function getId() {
		return $this->id;
	}
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
