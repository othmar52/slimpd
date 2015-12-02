<?php
namespace Slimpd;

class Trackindex extends AbstractModel
{
	protected $id;
	protected $data;
	
	public static $tableName = 'trackindex';
	
	
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
	public function setData($value) {
		$this->data = $value;
	}


	// getter
	public function getId() {
		return $this->id;
	}
	public function getData() {
		return $this->data;
	}	
}