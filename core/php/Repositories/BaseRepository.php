<?php
namespace Slimpd\Repositories;
/* Copyright (C) 2015-2016 othmar52 <othmar52@users.noreply.github.com>
 *
 * This file is part of sliMpd - a php based mpd web client
 *
 * This program is free software: you can redistribute it and/or modify it
 * under the terms of the GNU Affero General Public License as published by the
 * Free Software Foundation, either version 3 of the License, or (at your
 * option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License
 * for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
class BaseRepository {
	public static $tableName;
	public static $classPath;
	protected $container;
	protected $db;
	
	public function __construct(\Slim\Container $container) {
		$this->container = $container;
		#echo "<pre>" . print_r($container,1); echo "xdgdhdh";#die;
		$this->db = $container->db;
		$this->conf = $container->conf;
	}

	public function getInstancesByAttributes(array $attributeArray, $singleInstance = FALSE, $itemsperPage = 200, $currentPage = 1, $orderBy = "") {
		$instances = array();
		if(is_array($attributeArray) === FALSE) {
			return $instances;
		}
		if(count($attributeArray) < 1) {
			return $instances;
		}

		$query = "SELECT * FROM ". self::getTableName() ." WHERE ";
		foreach($attributeArray as $key => $value) {
			$query .= $this->db->real_escape_string($key) . '="' . $this->db->real_escape_string($value) . '" AND ';
		}
		$query = substr($query, 0, -5); // remove suffixed ' AND '

		// important TODO: validate orderBy to avoid SQL injection
		// for now use an ugly whitelist
		switch($orderBy) {
			case 'trackNumber ASC':
				// as we have a string field(01, A1,...) we have to cast it by adding ' +0'
				$orderBy = ' ORDER BY trackNumber + 0 ASC ';
				break;
			case 'imageweight':
				$weightConf = trimExplode("\n", $this->container->conf['images']['weightening'], TRUE);
				$orderBy = " ORDER BY FIELD(pictureType, '" . join("','", $weightConf) . "'), sorting ASC, filesize DESC ";
				break;
			default:
				$orderBy = "";
				break;
		}

		$query .= $orderBy;

		$query .= ' LIMIT ' . $itemsperPage * ($currentPage-1) . ','. $itemsperPage ; // TODO: handle limit and ordering stuff
		#echo $query; die();
		$result = $this->db->query($query);
		if($singleInstance === TRUE && $result->num_rows == 0) {
			return NULL;
		}
		$calledClass = self::getClassPath();
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

	public function getInstancesByFindInSetAttributes(array $attributeArray, $itemsperPage = 50, $currentPage = 1, $sortBy=NULL, $sortDirection='desc') {
		$instances = array();
		if(is_array($attributeArray) === FALSE) {
			return $instances;
		}
		if(count($attributeArray) < 1) {
			return $instances;
		}

		$query = "SELECT * FROM ". self::getTableName() ." WHERE ";
		foreach($attributeArray as $key => $value) {
			$query .= ' FIND_IN_SET('. (int)$value .',' .$this->db->real_escape_string($key) . ') OR ';
		}
		$query = substr($query, 0, -5); // remove suffixed ') OR '
		$query .= ')'; // close bracket

		if($sortBy !== NULL) {
			$query .= ' ORDER BY ' . az09($sortBy) . ' ' . az09($sortDirection);
		}

		$query .= ' LIMIT ' . $itemsperPage * ($currentPage-1) . ','. $itemsperPage ; // TODO: handle limit and ordering stuff
		#echo $query; die();
		$result = $this->db->query($query);
		if($result->num_rows == 0) {
			return NULL;
		}
		$calledClass = $this->getClassPath();
		while($record = $result->fetch_assoc()) {
			$instance = new $calledClass();
			$instance->mapArrayToInstance($record);
			$instances[] = $instance;
		}
		return $instances;
	}

	public function getCountByFindInSetAttributes(array $attributeArray) {
		$instances = array();
		if(is_array($attributeArray) === FALSE) {
			return $instances;
		}
		if(count($attributeArray) < 1) {
			return $instances;
		}

		$query = "SELECT count(uid) AS itemsTotal FROM ". self::getTableName() ." WHERE ";
		foreach($attributeArray as $key => $value) {
			$query .= ' FIND_IN_SET('. (int)$value .',' .$this->db->real_escape_string($key) . ') OR ';
		}
		$query = substr($query, 0, -5); // remove suffixed ') OR '
		$query .= ')'; // close bracket
		return $this->db->query($query)->fetch_assoc()['itemsTotal'];
	}

	public function getInstancesLikeAttributes(array $attributeArray, $itemsperPage = 50, $currentPage = 1) {
		$instances = array();
		if(is_array($attributeArray) === FALSE) {
			return $instances;
		}
		if(count($attributeArray) < 1) {
			return $instances;
		}

		$query = "SELECT * FROM ". self::getTableName() ." WHERE ";
		foreach($attributeArray as $key => $value) {
			$query .= $this->db->real_escape_string($key) . ' LIKE "%'. $this->db->real_escape_string($value) .'%" OR ';
		}
		$query = substr($query, 0, -6); // remove suffixed '%" OR '
		$query .= '%"'; // close bracket

		$query .= ' LIMIT ' . $itemsperPage * ($currentPage-1) . ','. $itemsperPage ; // TODO: handle limit and ordering stuff
		#echo $query; die();
		$result = $this->db->query($query);
		if($result->num_rows == 0) {
			return NULL;
		}
		$calledClass = $this->getClassPath();
		while($record = $result->fetch_assoc()) {
			$instance = new $calledClass();
			$instance->mapArrayToInstance($record);
			$instances[] = $instance;
		}
		return $instances;
	}

	public function getCountLikeAttributes(array $attributeArray) {
		$instances = array();
		if(is_array($attributeArray) === FALSE) {
			return $instances;
		}
		if(count($attributeArray) < 1) {
			return $instances;
		}

		$query = "SELECT count(uid) AS itemsTotal FROM ". self::getTableName() ." WHERE ";
		foreach($attributeArray as $key => $value) {
			$query .= $this->db->real_escape_string($key) . ' LIKE "%'. $this->db->real_escape_string($value) .'%" OR ';
		}
		$query = substr($query, 0, -6); // remove suffixed '%" OR '
		$query .= '%"'; // close bracket
		return $this->db->query($query)->fetch_assoc()['itemsTotal'];
	}

	public function getInstanceByAttributes(array $attributeArray, $orderBy = FALSE) {
		$instance = NULL;
		if(is_array($attributeArray) === FALSE) {
			return $instance;
		}
		if(count($attributeArray) < 1) {
			return $instance;
		}
		
		#$database = $this->db;
		$query = "SELECT * FROM ". self::getTableName() ." WHERE ";
		foreach($attributeArray as $key => $value) {
			$query .= $this->db->real_escape_string($key) . '="' . $this->db->real_escape_string($value) . '" AND ';
		}
		$query = substr($query, 0, -5); // remove suffixed ' AND '
		
		if($orderBy !== FALSE && is_string($orderBy) === TRUE) {
			# TODO: whitelist to avoid sql injection
			$query .= ' ORDER BY ' . $orderBy . ' ';
		}
		$query .= ' LIMIT 1';
		#echo $query; die;
		$result = $this->db->query($query);
		if($result === FALSE) {
			#return NULL;
		}
		if($result->num_rows == 0) {
			return $instance;
		}
		$calledClass = $this->getClassPath();
		while($record = $result->fetch_assoc()) {
			$instance = new $calledClass();
			$instance->mapArrayToInstance($record);
			return $instance;
		}
		return $instance;
	}

	public function getCountByAttributes(array $attributeArray) {
		$count = 0;
		if(is_array($attributeArray) === FALSE) {
			return $count;
		}
		if(count($attributeArray) < 1) {
			return $count;
		}

		$query = "SELECT count(*) AS itemsTotal FROM ". self::getTableName() ." WHERE ";
		foreach($attributeArray as $key => $value) {
			$query .= $this->db->real_escape_string($key) . '="' . $this->db->real_escape_string($value) . '" AND ';
		}
		$query = substr($query, 0, -5); // remove suffixed ' AND '
		return $this->db->query($query)->fetch_assoc()['itemsTotal'];
	}

	private static function getTableName() {
		$class = get_called_class();
		return $class::$tableName;
	}
	
	private static function getClassPath() {
		$class = get_called_class();
		return $class::$classPath;
	}
	
	public function mapArrayToInstance($array) {
		foreach($array as $dbField => $value) {
			$setter = 'set'.ucfirst($dbField);
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

	public function insert(&$instance) {

		// automatically add timestamp if possible
		$setter = 'setAdded';
		if(method_exists($instance, $setter)) {
			$getter = 'getAdded';
			if($instance->$getter() < 1) {
				$instance->$setter(time());
			}
		}
		$mapped = $instance->mapInstancePropertiesToDatabaseKeys();
		$query = 'INSERT INTO '. self::getTableName().' (' . join(",", array_keys($mapped)) . ') VALUES (';
		foreach($mapped as $value) {
			$query .= "\"" . $this->db->real_escape_string($value) . "\",";
		}
		$query = substr($query,0,-1) . ");";
		$this->db->query($query);
		$instance->setUid($this->db->insert_id);
	}

	public function update(&$instance) {
		$this->searchExistingUid($instance);
		// we can't update. lets insert new record
		if($instance->getUid() < 1) {
			return $this->insert($instance);
		}
		$query = 'UPDATE '. self::getTableName() . ' SET ';
		foreach($instance->mapInstancePropertiesToDatabaseKeys() as $dbField => $value) {
			$query .= $dbField . '="' . $this->db->real_escape_string($value) . '",';
		}
		$query = substr($query,0,-1) . ' WHERE uid=' . (int)$instance->getUid() . ";";
		$this->db->query($query);
	}

	private function searchExistingUid(&$instance) {
		if($instance->getUid() > 0) {
			return;
		}
		$dummyInstance = NULL;
		// check if we have a record with this path
		if(method_exists($instance, 'getRelPathHash') === TRUE) {
			$dummyInstance = $this->getInstanceByAttributes(array('relPathHash' => $instance->getRelPathHash()));
			if($dummyInstance === NULL && $instance->getRelPathHash() !== '') {
				return;
			}
		}
		if($dummyInstance === NULL && method_exists($instance, 'getRelPath') === TRUE) {
			$dummyInstance = $this->getInstanceByAttributes(array('relPath' => $instance->getRelPath()));
		}
		if($dummyInstance === NULL && method_exists($instance, 'getAz09') === TRUE) {
			$dummyInstance = $this->getInstanceByAttributes(array('az09' => $instance->getAz09()));
		}
		if($dummyInstance === NULL || $instance->getUid() < 1) {
			return;
		}
		$this->setUid($dummyInstance->getUid());
	}

	public function delete() {
		if($this->getUid() > 0) {
			// we already have an uid ...
		} else {
			// check if we have a record with this path
			$classPath = get_called_class();
			$repoKey = $this->getClassPath();
			$instance = new $classPath;
			if(method_exists($classPath, 'getRelPath') === TRUE) {
				$instance = $this->$repoKey->getInstanceByAttributes(array('relPath' => $this->getRelPath()));
			}
			
			if(method_exists($classPath, 'getAz09') === TRUE) {
				$instance = $this->$repoKey->getInstanceByAttributes(array('az09' => $this->getAz09()));
			}
			if($instance !== NULL && $instance->getUid() > 0) {
				$this->setUid($instance->getUid());
			} else {
				// no idea which database item should be deleted...
				return FALSE;
			}
		}
		$this->db->query(
			'DELETE FROM '. self::getTableName() . ' WHERE uid=' . (int)$this->getUid()
		);
	}

	public function getUidsByString($itemString) {
		$uidForUnknown = 10;
		if(trim($itemString) === '') {
			return array($uidForUnknown => $uidForUnknown); // Unknown
		}
		
		$tmp = get_called_class();
		$classPath = $tmp::$classPath;
		
		$class = strtolower($classPath);
		if(preg_match("/\\\([^\\\]*)$/", $classPath, $matches)) {
			$class = strtolower($matches[1]);
		}

		$this->cacheUnifier($classPath);

		$itemUids = array();
		$tmpGlue = "tmpGlu3";
		foreach(trimExplode($tmpGlue, str_ireplace($this->conf[$class . '-glue'], $tmpGlue, $itemString), TRUE) as $itemPart) {
			$az09 = az09($itemPart);
			
			if($az09 === '' || isHash($az09) === TRUE) {
				// TODO: is there a chance to translate strings like HASH(0xa54fe70) to an useable string?
				$itemUids[$uidForUnknown] = $uidForUnknown;
				continue;
			}

			// unify items based on config
			if(isset($this->importerCache[$classPath]["unified"][$az09]) === TRUE) {
				$itemPart = $this->importerCache[$classPath]["unified"][$az09];
				$az09 = az09($itemPart);
			}
			
			// check if we alread have an id
			// permformance improvement ~8%
			$itemUid = $this->cacheRead($classPath, $az09);
			if($itemUid !== FALSE) {
				$itemUids[$itemUid] = $itemUid;
				continue;
			}

			$query = "SELECT uid FROM ". self::getTableName() ." WHERE az09=\"" . $az09 . "\" LIMIT 1;";
			$result = $this->db->query($query);
			$record = $result->fetch_assoc();

			if($record) {
				$itemUid = $record["uid"];
				$itemUids[$record["uid"]] = $record["uid"];
				$this->cacheWrite($classPath, $az09, $record["uid"]);
				continue;
			}

			$instance = new $classPath();
			$instance->setTitle($itemPart)->setAz09($az09);

			// TODO: de we need the non-batcher version anymore?
			#$instance->insert();
			#$itemUid = $this->db->insert_id;
			$this->container->batcher->que($instance);
			$itemUid = $instance->getUid();

			$itemUids[$itemUid] = $itemUid;
			$this->cacheWrite($classPath, $az09, $itemUid);
		}
		return $itemUids;
	}

	public function cacheRead($classPath, $az09) {
		$this->cacheUnifier($classPath);
		if(isset($this->importerCache[$classPath]["cache"][$az09]) === TRUE) {
			return $this->importerCache[$classPath]["cache"][$az09];
		}
		return FALSE;
	}

	public function cacheWrite($classPath, $az09, $itemUid) {
		$this->cacheUnifier($classPath);
		// we can only modify a copy and assign it back afterward (Indirect modification of overloaded property)
		$tmpArray = $this->importerCache;
		$tmpArray[$classPath]["cache"][$az09] = $itemUid;
		// delete cache as soon as we reach 1000 items
		if(count($tmpArray[$classPath]["cache"]) > 5000) {
			$tmpArray[$classPath]["cache"] = array($az09 => $itemUid);
			cliLog("clearing cache for " . $classPath, 5, "yellow");
			// make sure all instances gets written to database
			$tmpInstance = new $classPath;
			$this->container->batcher->insertBatch($tmpInstance::$tableName);
		}
		$this->importerCache = $tmpArray;
	}

	public function cacheUnifier($classPath) {
		if(isset($this->importerCache[$classPath]) === TRUE) {
			return;
		}
		if(isset($this->importerCache) === FALSE) {
			$this->importerCache = array();
		}
		// we can only modify a copy and assign it back afterward (Indirect modification of overloaded property)
		$tmpArray = $this->importerCache;
		$tmpArray[$classPath] = array(
			"unified" => array(),
			"cache" => array()
		);
		$this->importerCache = $tmpArray;
		if(method_exists($classPath, "unifyItemnames") === FALSE) {
			return;
		}

		$class = (preg_match("/\\\([^\\\]*)$/", $classPath, $matches))
			? strtolower($matches[1])
			: strtolower($classPath);

		if(isset($this->conf[$class ."s"]) === FALSE) {
			return;
		}
		$tmpArray[$classPath]["unified"] = $classPath::unifyItemnames($this->conf[$class ."s"]);
		$this->importerCache = $tmpArray;
	}

	public function getAll($itemsperPage = 500, $currentPage = 1, $orderBy = "") {
		$instances = array();
		if($itemsperPage > 500) {
			$itemsperPage = 500;
		}
		$currentPage = ($currentPage < 1) ? 1 : $currentPage;
		
		$query = "SELECT * FROM ". self::getTableName();
		// IMPORTANT TODO: validate orderBy to avoid sql injection
		switch($orderBy) {
			case "":
				$orderBy = " ORDER BY title ASC ";
				break;
			default:
				$orderBy = " ORDER BY " . $orderBy . " ";
				break;
		}
		$query .= $orderBy; // TODO: handle ordering
		#$query .= ' ORDER BY trackCount DESC '; // TODO: handle ordering
		$query .= ' LIMIT ' . $itemsperPage * ($currentPage-1) . ','. $itemsperPage ; // TODO: handle limit
		#echo $query; die();
		$result = $this->db->query($query);
		
		$classPath = self::getClassPath();

		while($record = $result->fetch_assoc()) {
			$instance = new $classPath();
			$instance->mapArrayToInstance($record);
			$instances[] = $instance;
		}
		return $instances;
	}

	public function getCountAll() {
		$query = "SELECT count(uid) AS itemsTotal FROM ". self::getTableName();
		$result = $this->db->query($query);
		if($result === FALSE) {
			throw new \Exception("Error getCountAll() - please check if table \"".self::getTableName()."\" exists", 1);
			return 0;
		}
		return $result->fetch_assoc()['itemsTotal'];
	}
	
	public function getRandomInstance() {
		#$database = $this->db;

		// ORDER BY RAND is the killer on huge tables
		// lets try a different approach
		$highestUid = $this->db->query("SELECT uid FROM ". self::getTableName() ." ORDER BY uid DESC LIMIT 0, 1")->fetch_assoc()['uid'];

		$maxAttempts = 1000;
		$counter = 0;
		try {
			while (TRUE) {
				$try = $this->db->query(
					"SELECT uid FROM ". self::getTableName() ." WHERE uid = " . mt_rand(1, $highestUid)
				)->fetch_assoc()['uid'];
				if($try !== NULL) {
					return $this->getInstanceByAttributes(['uid' => $try] );
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

	public static function deleteRecordsByUids(array $uidArray) {
		if(count($uidArray) === 0) {
			return;
		}
		$query = "DELETE FROM " . self::getTableName() . " WHERE uid IN (" . join(',', $uidArray) . ");";
		$this->db->query($query);
	}

	public function ensureRecordUidExists($itemUid) {
		if($this->db->query("SELECT uid FROM " . self::getTableName() . " WHERE uid=" . (int)$itemUid)->num_rows == $itemUid) {
			return;
		}
		$this->db->query("INSERT INTO " . self::getTableName() . " (uid) VALUES (".(int)$itemUid.")");
		return;
	}
}

