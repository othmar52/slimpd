<?php
namespace Slimpd\Models;
/* Copyright
 *
 */
class Artist extends \Slimpd\Models\AbstractModel {
	use \Slimpd\Traits\PropGroupCounters; // trackCount, albumCount
	protected $title;
	protected $article;
	protected $az09;

	public static $tableName = "artist";

	protected static function unifyItemnames($items) {
		$return = array();
		foreach($items as $az09 => $itemString) {
			$return[$az09] = $itemString;
		}
		return $return;
	}

	public static function getArtistBlacklist() {
		$app = \Slim\Slim::getInstance();
		// get unified artist-blacklist
		if(isset($GLOBALS["artist-blacklist"]) === TRUE) {
			return $GLOBALS["artist-blacklist"];
		}
		$GLOBALS["artist-blacklist"] = array();
		if(isset($app->config["artist-blacklist"]) === FALSE) {
			return $GLOBALS["artist-blacklist"];
		}
		foreach($app->config["artist-blacklist"] as $term) {
			$GLOBALS["artist-blacklist"][$term] = 1;
			$GLOBALS["artist-blacklist"][" " . $term] = 1;
		}
		return $GLOBALS["artist-blacklist"];
	}

	public static function getUidsByString($itemString) {
		if(trim($itemString) === "") {
			return array("10"); // Unknown
		}

		$app = \Slim\Slim::getInstance();
		$classPath = get_called_class();
		$class = strtolower($classPath);
		if(preg_match("/\\\([^\\\]*)$/", $classPath, $matches)) {
			$class = strtolower($matches[1]);
		}

		self::cacheUnifier($app, $classPath);

		$itemUids = array();
		$tmpGlue = "tmpGlu3";
		foreach(trimExplode($tmpGlue, str_ireplace($app->config[$class . "-glue"], $tmpGlue, $itemString), TRUE) as $itemPart) {
			$az09 = az09($itemPart);

			if($az09 === "" || isHash($az09) === TRUE) {
				// TODO: is there a chance to translate strings like HASH(0xa54fe70) to an useable string?
				$itemUids[10] = 10; // Unknown Genre
				continue;
			}

			$artistArticle = "";
			// TODO: read articles from config
			foreach(array("The", "Die", ) as $matchArticle) {
				// search for prefixed article
				if(preg_match("/^".$matchArticle." (.*)$/i", $itemPart, $matches)) {
					$artistArticle = $matchArticle." ";
					$itemPart = $matches[1];
					$az09 = az09($itemPart);
					#var_dump($itemString); die("prefixed-article");
				}
				// search for suffixed article
				if(preg_match("/^(.*)([\ ,]+)".$matchArticle."/i", $itemPart, $matches)) {
					$artistArticle = $matchArticle." ";
					$itemPart = remU($matches[1]);
					$az09 = az09($itemPart);
					#var_dump($matches); die("suffixed-article");
				}
			}

			// unify items based on config
			if(isset($app->importerCache[$classPath]["unified"][$az09]) === TRUE) {
				$itemPart = $app->importerCache[$classPath]["unified"][$az09];
				$az09 = az09($itemPart);
			}

			// check if we alread have an id
			// permformance improvement ~8%
			$itemUid = self::cacheRead($app, $classPath, $az09);
			if($itemUid !== FALSE) {
				$itemUids[$itemUid] = $itemUid;
				continue;
			}

			$query = "SELECT uid FROM " . self::$tableName ." WHERE az09=\"" . $az09 . "\" LIMIT 1;";
			$result = $app->db->query($query);
			$record = $result->fetch_assoc();
			if($record) {
				$itemUid = $record["uid"];
				$itemUids[$record["uid"]] = $record["uid"];
				self::cacheWrite($app, $classPath, $az09, $record["uid"]);
				continue;
			}
			
			$instance = new $classPath();
			$instance->setTitle(ucwords(strtolower($itemPart)))
				->setAz09($az09)
				->setArticle($artistArticle)
				->insert();
			$itemUid = $app->db->insert_id;
			
			$itemUids[$itemUid] = $itemUid;
			self::cacheWrite($app, $classPath, $az09, $itemUid);
		}
		return $itemUids;

	}


	//setter
	public function setTitle($value) {
		$this->title = $value;
		return $this;
	}
	public function setArticle($value) {
		$this->article = $value;
		return $this;
	}
	public function setAz09($value) {
		$this->az09 = $value;
		return $this;
	}

	// getter
	public function getTitle() {
		return $this->title;
	}
	public function getArticle() {
		return $this->article;
	}
	public function getAz09() {
		return $this->az09;
	}
}
