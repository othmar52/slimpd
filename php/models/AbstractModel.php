<?php
namespace Slimpd;


abstract class AbstractModel {
		
	public static $tableName;
	
	public static function getInstancesByAttributes(array $attributeArray, $singleInstance = FALSE, $itemsperPage = 200, $currentPage = 1, $orderBy = "") {
		$instances = array();
		if(is_array($attributeArray) === FALSE) {
			return $instances;
		}
		if(count($attributeArray) < 1) {
			return $instances;
		}
		
		$db = \Slim\Slim::getInstance()->db;
		$query = "SELECT * FROM ". self::getTableName() ." WHERE ";
		foreach($attributeArray as $key => $value) {
			$query .= $db->real_escape_string($key) . '="' . $db->real_escape_string($value) . '" AND ';
		}
		$query = substr($query, 0, -5); // remove suffixed ' AND '
		
		// important TODO: validate orderBy to avoid SQL injection
		// for now use an ugly whitelist
		switch($orderBy) {
			case 'number ASC':
				// as we have a string field(01, A1,...) we have to cast it by adding ' +0'
				$orderBy = ' ORDER BY number + 0 ASC ';
				break;
			case 'imageweight':
				$weightConf = trimExplode("\n", \Slim\Slim::getInstance()->config['images']['weightening'], TRUE);
				$orderBy = " ORDER BY FIELD(pictureType, '" . join("','", $weightConf) . "'), sorting ASC, filesize DESC ";
				break;
			default:
				$orderBy = "";
				break;
		}

		$query .= $orderBy;

		$query .= ' LIMIT ' . $itemsperPage * ($currentPage-1) . ','. $itemsperPage ; // TODO: handle limit and ordering stuff
		#echo $query; die();
		$result = $db->query($query);
		if($singleInstance === TRUE && $result->num_rows == 0) {
			return NULL;
		}
		$calledClass =get_called_class();
		while($record = $result->fetch_assoc()) {
			$instance = new $calledClass();
			$instance->mapArrayToInstance($record);
			if($singleInstance !== FALSE) {
				return $instance;
			}
			$instances[] = $instance;
		}
		return $instances;
	}


	public static function getInstancesByFindInSetAttributes(array $attributeArray, $itemsperPage = 50, $currentPage = 1, $sortBy=NULL, $sortDirection='desc') {
		$instances = array();
		if(is_array($attributeArray) === FALSE) {
			return $instances;
		}
		if(count($attributeArray) < 1) {
			return $instances;
		}
		
		$db = \Slim\Slim::getInstance()->db;
		$query = "SELECT * FROM ". self::getTableName() ." WHERE ";
		foreach($attributeArray as $key => $value) {
			$query .= ' FIND_IN_SET('. (int)$value .',' .$db->real_escape_string($key) . ') OR ';
		}
		$query = substr($query, 0, -5); // remove suffixed ') OR '
		$query .= ')'; // close bracket
		
		if($sortBy !== NULL) {
			$query .= ' ORDER BY ' . az09($sortBy) . ' ' . az09($sortDirection);
		}
		
		$query .= ' LIMIT ' . $itemsperPage * ($currentPage-1) . ','. $itemsperPage ; // TODO: handle limit and ordering stuff
		#echo $query; die();
		$result = $db->query($query);
		if($result->num_rows == 0) {
			return NULL;
		}
		$calledClass =get_called_class();
		while($record = $result->fetch_assoc()) {
			$instance = new $calledClass();
			$instance->mapArrayToInstance($record);
			$instances[] = $instance;
		}
		return $instances;
	}
	
	
	
	public static function getCountByFindInSetAttributes(array $attributeArray) {
		$instances = array();
		if(is_array($attributeArray) === FALSE) {
			return $instances;
		}
		if(count($attributeArray) < 1) {
			return $instances;
		}
		
		$db = \Slim\Slim::getInstance()->db;
		$query = "SELECT count(id) AS itemCountTotal FROM ". self::getTableName() ." WHERE ";
		foreach($attributeArray as $key => $value) {
			$query .= ' FIND_IN_SET('. (int)$value .',' .$db->real_escape_string($key) . ') OR ';
		}
		$query = substr($query, 0, -5); // remove suffixed ') OR '
		$query .= ')'; // close bracket
		return $db->query($query)->fetch_assoc()['itemCountTotal'];
	}
	
	public static function getInstancesLikeAttributes(array $attributeArray, $itemsperPage = 50, $currentPage = 1) {
		$instances = array();
		if(is_array($attributeArray) === FALSE) {
			return $instances;
		}
		if(count($attributeArray) < 1) {
			return $instances;
		}
		
		$db = \Slim\Slim::getInstance()->db;
		$query = "SELECT * FROM ". self::getTableName() ." WHERE ";
		foreach($attributeArray as $key => $value) {
			$query .= $db->real_escape_string($key) . ' LIKE "%'. $db->real_escape_string($value) .'%" OR ';
		}
		$query = substr($query, 0, -6); // remove suffixed '%" OR '
		$query .= '%"'; // close bracket
		
		$query .= ' LIMIT ' . $itemsperPage * ($currentPage-1) . ','. $itemsperPage ; // TODO: handle limit and ordering stuff
		#echo $query; die();
		$result = $db->query($query);
		if($result->num_rows == 0) {
			return NULL;
		}
		$calledClass =get_called_class();
		while($record = $result->fetch_assoc()) {
			$instance = new $calledClass();
			$instance->mapArrayToInstance($record);
			$instances[] = $instance;
		}
		return $instances;
	}
	
	public static function getCountLikeAttributes(array $attributeArray, $itemsperPage = 50, $currentPage = 1) {
		$instances = array();
		if(is_array($attributeArray) === FALSE) {
			return $instances;
		}
		if(count($attributeArray) < 1) {
			return $instances;
		}
		
		$db = \Slim\Slim::getInstance()->db;
		$query = "SELECT count(id) AS itemCountTotal FROM ". self::getTableName() ." WHERE ";
		foreach($attributeArray as $key => $value) {
			$query .= $db->real_escape_string($key) . ' LIKE "%'. $db->real_escape_string($value) .'%" OR ';
		}
		$query = substr($query, 0, -6); // remove suffixed '%" OR '
		$query .= '%"'; // close bracket
		#echo $query; die();
		return $db->query($query)->fetch_assoc()['itemCountTotal'];
	}
	
	public static function getInstanceByAttributes(array $attributeArray, $orderBy = FALSE) {
		$instance = NULL;
		if(is_array($attributeArray) === FALSE) {
			return $instance;
		}
		if(count($attributeArray) < 1) {
			return $instance;
		}
		
		$db = \Slim\Slim::getInstance()->db;
		$query = "SELECT * FROM ". self::getTableName() ." WHERE ";
		foreach($attributeArray as $key => $value) {
			$query .= $db->real_escape_string($key) . '="' . $db->real_escape_string($value) . '" AND ';
		}
		$query = substr($query, 0, -5); // remove suffixed ' AND '
		
		if($orderBy !== FALSE && is_string($orderBy) === TRUE) {
			# TODO: whitelist to avoid sql injection
			$query .= ' ORDER BY ' . $orderBy . ' ';
		}
		$query .= ' LIMIT 1';
		$result = $db->query($query);
		if($result->num_rows == 0) {
			return $instance;
		}
		$calledClass =get_called_class();
		while($record = $result->fetch_assoc()) {
			$instance = new $calledClass();
			$instance->mapArrayToInstance($record);
			return $instance;
		}
		return $instance;
	}


	public static function getCountByAttributes(array $attributeArray) {
		$count = 0;
		if(is_array($attributeArray) === FALSE) {
			return $count;
		}
		if(count($attributeArray) < 1) {
			return $count;
		}
		
		$db = \Slim\Slim::getInstance()->db;
		$query = "SELECT count(*) AS itemCountTotal FROM ". self::getTableName() ." WHERE ";
		foreach($attributeArray as $key => $value) {
			$query .= $db->real_escape_string($key) . '="' . $db->real_escape_string($value) . '" AND ';
		}
		$query = substr($query, 0, -5); // remove suffixed ' AND '
		
		return $db->query($query)->fetch_assoc()['itemCountTotal'];
	}
	
	private static function getTableName() {
		$class = get_called_class();
      	return $class::$tableName;
	}
	
	public function mapArrayToInstance($array) {
		foreach($array as $dbfield => $value) {
			$setter = 'set'.ucfirst($dbfield);
			if(method_exists(get_called_class(), $setter)) {
				#echo $setter . $value ."<br>";
				$this->$setter($value);
			}
		}
	}
	
	
	/**
	 * @return array  - keys named like databasefields
	 * 
	 */
	public function mapInstancePropertiesToDatabaseKeys($ignoreEmpty = TRUE) {
		$return = array();
		$calledClass = get_called_class();
		#echo $calledClass;
		foreach(array_keys(get_class_vars($calledClass)) as $classVar) {
			$getter = 'get'.ucfirst($classVar);
			if($classVar === 'tableName') {
				continue;
			}
			if(method_exists($calledClass, $getter)) {
				$instancePropertyValue = $this->$getter();
				if($ignoreEmpty === TRUE && !$instancePropertyValue) {
					continue;
				}
				$return[$classVar] = $instancePropertyValue;
			}
		}
		return $return;
	}
	
	public function insert() {
		$app = \Slim\Slim::getInstance();

		// automatically add timestamp if possible
		$setter = 'setAdded';
		if(method_exists(get_called_class(), $setter)) {
			$getter = 'getAdded';
			if($this->$getter() < 1) {
				$this->$setter(time());
			}
		}
		$mapped = $this->mapInstancePropertiesToDatabaseKeys();
		$query = 'INSERT INTO '. self::getTableName().' (' . join(",", array_keys($mapped)) . ') VALUES (';
		foreach($mapped as $value) {
			$query .= "\"" . $app->db->real_escape_string($value) . "\",";
		}
		$query = substr($query,0,-1) . ");";
		$app->db->query($query);
		$this->setId($app->db->insert_id);
	}
	
	public function update() {
		if($this->getId() > 0) {
			// we already have an id ...
		} else {
			// check if we have a record with this path
			$classPath = get_called_class();
			$i2 = new $classPath;
			
			if(method_exists($classPath, 'getRelativePathHash') === TRUE) {
				$i2 = $classPath::getInstanceByAttributes(array('relativePathHash' => $this->getRelativePathHash()));
				if($i2 === NULL && $this->getRelativePathHash() !== '') {
					return $this->insert();
				}
			}
			
			
			if($i2 === NULL && method_exists($classPath, 'getRelativePath') === TRUE) {
				$i2 = $classPath::getInstanceByAttributes(array('relativePath' => $this->getRelativePath()));
			}
			
			if($i2 === NULL && method_exists($classPath, 'getAz09') === TRUE) {
				$i2 = $classPath::getInstanceByAttributes(array('az09' => $this->getAz09()));
			}
			if($i2 !== NULL && $i2->getId() > 0) {
				$this->setId($i2->getId());
			} else {
				return $this->insert();
			}
		}
			
		$app = \Slim\Slim::getInstance();
		
		$query = 'UPDATE '. self::getTableName() . ' SET ';
		foreach($this->mapInstancePropertiesToDatabaseKeys() as $dbfield => $value) {
			$query .= $dbfield . '="' . $app->db->real_escape_string($value) . '",';
		}
		$query = substr($query,0,-1) . ' WHERE id=' . (int)$this->getId() . ";";
		$app->db->query($query);
	}
	
	public function delete() {
		if($this->getId() > 0) {
			// we already have an id ...
		} else {
			// check if we have a record with this path
			$classPath = get_called_class();
			$i2 = new $classPath;
			if(method_exists($classPath, 'getRelativePath') === TRUE) {
				$i2 = $classPath::getInstanceByAttributes(array('relativePath' => $this->getRelativePath()));
			}
			
			if(method_exists($classPath, 'getAz09') === TRUE) {
				$i2 = $classPath::getInstanceByAttributes(array('az09' => $this->getAz09()));
			}
			if($i2 !== NULL && $i2->getId() > 0) {
				$this->setId($i2->getId());
			} else {
				// no idea which database item should be deleted...
				return FALSE;
			}
		}
		\Slim\Slim::getInstance()->db->query(
			'DELETE FROM '. self::getTableName() . ' WHERE id=' . (int)$this->getId()
		);
	}

	public static function getIdsByString($itemString) {
		$idForUnknown = 10;
		if(trim($itemString) === '') {
			return array($idForUnknown); // Unknown
		}
		
		$app = \Slim\Slim::getInstance();
		$classPath = get_called_class();
		if(preg_match("/\\\([^\\\]*)$/", $classPath, $m)) {
			$class = strtolower($m[1]);
		} else {
			$class = strtolower($classPath);
		}
		if(isset($GLOBALS['unified' . $class . 's']) === FALSE) {
			if(method_exists($classPath, 'unifyItemnames')) {
				if(isset($app->config[$class .'s'])) {
					$GLOBALS['unified' . $class . 's'] = $classPath::unifyItemnames($app->config[$class .'s']);
				} else {
					$GLOBALS['unified' . $class . 's'] = array();
				}
			} else {
				$GLOBALS['unified' . $class . 's'] = array();
			}
		}
		
		if(isset($GLOBALS[$class . 'Cache']) === FALSE) {
			$GLOBALS[$class . 'Cache'] = array();
		}
		
		$itemIds = array();
		$tmpGlue = "tmpGlu3";
		foreach(trimExplode($tmpGlue, str_ireplace($app->config[$class . '-glue'], $tmpGlue, $itemString), TRUE) as $itemPart) {
			$az09 = az09($itemPart);
			
			if($az09 === '' || preg_match("/^hash0x([a-f0-9]{7})$/", $az09)) {
				// TODO: is there a chance to translate strings like HASH(0xa54fe70) to an useable string?
				$itemIds[$idForUnknown] = $idForUnknown;
			} else {
				
				
				// unify items based on config
				if (array_key_exists($az09, $GLOBALS['unified' . $class . 's']) === TRUE) {
					$itemPart = $GLOBALS['unified' . $class . 's'][$az09];
					$az09 = az09($itemPart);
				}
				
				// check if we alread have an id
				// permformance improvement ~8%
				if(isset($GLOBALS[$class . 'Cache'][$az09]) === TRUE) {
					$itemIds[$GLOBALS[$class . 'Cache'][$az09]] = $GLOBALS[$class . 'Cache'][$az09];
					continue;
				}
				
				$query = "SELECT id FROM ". self::getTableName() ." WHERE az09=\"" . $az09 . "\" LIMIT 1;";
				$result = $app->db->query($query);
				$record = $result->fetch_assoc();
				if($record) {
					$itemId = $record['id'];
				} else {
					$g = new $classPath();
					$g->setTitle($itemPart);
					$g->setAz09($az09);
					$g->insert();
					$itemId = $app->db->insert_id;
				}
				$itemIds[$itemId] = $itemId;
				$GLOBALS[$class .'Cache'][$az09] = $itemId;
			}
		}
		return $itemIds;
		
	}
	
	
	public static function getInstancesForRendering($args) {
		$itemIds = '';
		$return = array();
		$classPath = get_called_class();
		if(preg_match("/\\\([^\\\]*)$/", $classPath, $m)) {
			$getter = 'get' . $m[1] . 'Id';
		} else {
			$getter = 'get' . $classPath . 'Id';
		}
		
		for($i=0; $i < func_num_args();$i++) {
			$argument = func_get_arg($i);
			if(is_array($argument) === TRUE) {
				foreach($argument as $item) {
					if(is_object($item) === TRUE) {
						if(method_exists($item, $getter) === TRUE) {
							$itemIds .= $item->$getter() . ',';
						}
						#$itemIds .= $item->$getter() . ',';
						if(method_exists($item, 'getRemixerId') === TRUE) {
							$itemIds .= $item->getRemixerId() . ',';
						}
						if(method_exists($item, 'getFeaturingId') === TRUE) {
							$itemIds .= $item->getFeaturingId() . ',';
						}
					}
				}
			}
			if(is_object($argument) === TRUE) {
				#$itemIds .= $argument->$getter() . ',';
				if(method_exists($argument, $getter) === TRUE) {
					$itemIds .= $argument->$getter() . ',';
				}
				if(method_exists($argument, 'getRemixerId') === TRUE) {
					$itemIds .= $argument->getRemixerId() . ',';
				}
				if(method_exists($argument, 'getFeaturingId') === TRUE) {
					$itemIds .= $argument->getFeaturingId() . ',';
				}
			}
		}
	
		$itemIds = array_unique(explode(",", substr($itemIds, 0, -1)));
		foreach($itemIds as $itemId) {
			$return[$itemId] = $classPath::getInstanceByAttributes(array('id' => $itemId));
		}
		return $return;
	}

	public static function getAll($itemsperPage = 500, $currentPage = 1, $orderBy = "") {
		$instances = array();
		if($itemsperPage > 500) {
			$itemsperPage = 500;
		}
		$currentPage = ($currentPage < 1) ? 1 : $currentPage;
		
		$db = \Slim\Slim::getInstance()->db;
		$query = "SELECT * FROM ". self::getTableName();
		// TODO: validate orderBy. for now use a quick and dirty whitelist
		switch($orderBy) {
			case "added desc":
				$orderBy = " ORDER BY added desc ";
				break;
			default:
				$orderBy = " ORDER BY title ASC ";
				break;
		}
		$query .= $orderBy; // TODO: handle ordering
		#$query .= ' ORDER BY trackCount DESC '; // TODO: handle ordering
		$query .= ' LIMIT ' . $itemsperPage * ($currentPage-1) . ','. $itemsperPage ; // TODO: handle limit
		#echo $query; die();
		$result = $db->query($query);

		$calledClass =get_called_class();
		while($record = $result->fetch_assoc()) {
			$instance = new $calledClass();
			$instance->mapArrayToInstance($record);
			$instances[] = $instance;
			
		}
		return $instances;
	}
	
	public static function getCountAll() {
		$db = \Slim\Slim::getInstance()->db;
		$query = "SELECT count(id) AS itemCountTotal FROM ". self::getTableName();
		$result = $db->query($query);
		if($result === FALSE) {
			throw new \Exception("Error getCountAll() - please check if table \"".self::getTableName()."\" exists", 1);
			return 0;
		}
		return $result->fetch_assoc()['itemCountTotal'];
	}
	
	public static function getRandomInstance() {
		$db = \Slim\Slim::getInstance()->db;

		// ORDER BY RAND is the killer on huge tables
		// lets try a different approach
		$higestId = $db->query("SELECT id FROM ". self::getTableName() ." ORDER BY id DESC LIMIT 0, 1")->fetch_assoc()['id'];

		$maxAttempts = 1000;
		$counter = 0;
		try {
			while (TRUE) {
				$try = $db->query(
					"SELECT id FROM ". self::getTableName() ." WHERE id = " . mt_rand(1, $higestId)
				)->fetch_assoc()['id'];
				if($try !== NULL) {
					return self::getInstanceByAttributes( ['id' => $try] );
				}
				if($counter > $maxAttempts) {
					throw new \Exception("OOPZ! couldn't fetch random instance of " . self::getTableName(), 1);
				}
				$counter++;
			}
		} catch(\Exception $e) {
			return FALSE;
		}
	}

	public static function deleteRecordsByIds(array $idArray) {
		if(count($idArray) === 0) {
			return;
		}
		$query = "DELETE FROM " . self::getTableName() . " WHERE id IN (" . join(',', $idArray) . ");";
		\Slim\Slim::getInstance()->db->query($query);
	}

}
