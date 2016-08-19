<?php
namespace Slimpd\Models;

class Albumindex extends \Slimpd\Models\AbstractModel
{
	protected $id;
	protected $artist;
	protected $title;
	protected $allchunks;
	
	public static $tableName = 'albumindex';
	
	
	public static function ensureRecordIdExists($id) {
		$db = \Slim\Slim::getInstance()->db;
		if($db->query("SELECT id FROM " . self::$tableName . " WHERE id=" . (int)$id)->num_rows == $id) {
			return;
		}
		$db->query("INSERT INTO " . self::$tableName . " (id) VALUES (".(int)$id.")");
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