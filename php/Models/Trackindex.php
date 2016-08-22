<?php
namespace Slimpd\Models;

class Trackindex extends \Slimpd\Models\AbstractModel
{
	protected $id;
	protected $artist;
	protected $title;
	protected $allchunks;
	
	public static $tableName = 'trackindex';

	public static function ensureRecordIdExists($itemId) {
		if(\Slim\Slim::getInstance()->db->query("SELECT id FROM " . self::$tableName . " WHERE id=" . (int)$itemId)->num_rows == $itemId) {
			return;
		}
		\Slim\Slim::getInstance()->db->query("INSERT INTO " . self::$tableName . " (id) VALUES (".(int)$itemId.")");
		return;
	}

	//setter
	public function setId($value) {
		$this->id = $value;
	}
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
	public function getId() {
		return $this->id;
	}
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