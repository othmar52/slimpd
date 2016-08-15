<?php
namespace Slimpd;

class Artist extends AbstractModel
{
	protected $id;
	protected $title;
	protected $article;
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
	
	
	public static function getArtistBlacklist() {
		$app = \Slim\Slim::getInstance();
		// get unified common-genres
		if(isset($GLOBALS['artist-blacklist']) === TRUE) {
			return $GLOBALS['artist-blacklist'];
		}
		$GLOBALS['artist-blacklist'] = array();	
		if(isset($app->config['artist-blacklist']) === FALSE) {
			return $GLOBALS['artist-blacklist'];
		}
		foreach($app->config['artist-blacklist'] as $term) {
			$GLOBALS['artist-blacklist'][$term] = 1;
			$GLOBALS['artist-blacklist'][' ' . $term] = 1;
		}
		return $GLOBALS['artist-blacklist'];
	}
	
	
	public static function getIdsByString($itemString) {
		if(trim($itemString) === '') {
			return array("10"); // Unknown
		}
		
		$app = \Slim\Slim::getInstance();
		$classPath = get_called_class();
		if(preg_match("/\\\([^\\\]*)$/", $classPath, $m)) {
			$class = strtolower($m[1]);
		} else {
			$class = strtolower($classPath);
		}
		if(isset($GLOBALS['unified' . $class . 's']) === FALSE) {
			$GLOBALS['unified' . $class . 's'] = array();
			if(method_exists($classPath, 'unifyItemnames')) {
				if(isset($app->config[$class .'s'])) {
					$GLOBALS['unified' . $class . 's'] = $classPath::unifyItemnames($app->config[$class .'s']);
				}
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
				$itemIds[1] = 10; // Unknown Genre
			} else {
				$artistArticle = '';
				foreach(array('The', 'Die', ) as $matchArticle) {
					// search for prefixed article
					if(preg_match("/^".$matchArticle." (.*)$/i", $itemPart, $m)) {
						$artistArticle = $matchArticle.' ';
						$itemPart = $m[1];
						$az09 = az09($itemPart);
						#var_dump($itemString); die('prefixed-article');
					}
					// search for suffixed article
					if(preg_match("/^(.*)([\ ,]+)".$matchArticle."/i", $itemPart, $m)) {
						$artistArticle = $matchArticle.' ';
						$itemPart = remU($m[1]);
						$az09 = az09($itemPart);
						#var_dump($m); die('suffixed-article');
					}
				}
				
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
				
				$query = "SELECT id FROM artist WHERE az09=\"" . $az09 . "\" LIMIT 1;";
				$result = $app->db->query($query);
				$record = $result->fetch_assoc();
				if($record) {
					$itemId = $record['id'];
				} else {
					$g = new $classPath();
					$g->setTitle(ucwords(strtolower($itemPart)));
					$g->setAz09($az09);
					$g->setArticle($artistArticle);
					$g->insert();
					$itemId = $app->db->insert_id;
				}
				$itemIds[$itemId] = $itemId;
				$GLOBALS[$class .'Cache'][$az09] = $itemId;
			}
		}
		return $itemIds;
		
	}


	


	//setter
	public function setId($value) {
		$this->id = $value;
	}
	public function setTitle($value) {
		$this->title = $value;
	}
	public function setArticle($value) {
		$this->article = $value;
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
	public function getArticle() {
		return $this->article;
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
