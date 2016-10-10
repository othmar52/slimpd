<?php
namespace Slimpd\Models;
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
class Artist extends \Slimpd\Models\AbstractModel {
	use \Slimpd\Traits\PropGroupCounters; // trackCount, albumCount
	use \Slimpd\Traits\PropertyTopGenre; // topGenreUids
	use \Slimpd\Traits\PropertyTopLabel; // topLabelUids
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
		if(isset($app->config["artist-blacklist"]["blacklist"]) === FALSE) {
			return $GLOBALS["artist-blacklist"];
		}
		foreach(trimExplode("\n", $app->config["artist-blacklist"]["blacklist"], TRUE) as $term) {
			$GLOBALS["artist-blacklist"][$term] = 1;
			$GLOBALS["artist-blacklist"][" " . $term] = 1;
		}
		#print_r($GLOBALS["artist-blacklist"]); die;
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
				->setArticle($artistArticle);

			// TODO: de we need the non-batcher version anymore?
			#$instance->insert();
			#$itemUid = $app->db->insert_id;
			$app->batcher->que($instance);
			$itemUid = $instance->getUid();

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
