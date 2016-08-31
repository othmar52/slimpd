<?php
namespace Slimpd\Models;

class Genre extends \Slimpd\Models\AbstractModel
{
	protected $title;
	protected $parent;
	protected $az09;
	protected $trackCount;
	protected $albumCount;
	
	public static $tableName = 'genre';
	
	protected static function unifyItemnames($genres) {
		$return = array();
		foreach($genres as $az09 => $genreString) {
			$return[az09($genreString)] = $genreString;
			$return[$az09] = $genreString;
			$return[$az09 . az09($genreString)] = $genreString;
			if(is_numeric($az09) === FALSE) {
				$return[$az09. 's'] = $genreString;
				if(strlen($az09)>4) {
					$return[substr($az09,1)] = $genreString;
					$return[substr($az09,0,-1)] = $genreString;
				}
			}
			// TODO: read from config
			foreach(array('generale', 'general', 'classic', 'allgemein', 'original', 'other', 'engeneral') as $uselessAddon) {
				$return[$uselessAddon . $az09] = $genreString;
				$return[$az09 . $uselessAddon] = $genreString;
				$return[$uselessAddon . az09($genreString)] = $genreString;
				$return[az09($genreString) . $uselessAddon] = $genreString;
			}
		}
		return $return;
	}

	//setter
	public function setTitle($value) {
		$this->title = $value;
	}
	public function setParent($value) {
		$this->parent = $value;
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
	public function getTitle() {
		return $this->title;
	}
	public function getParent() {
		return $this->parent;
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

	public static function parseGenreStringAdvanced($itemString) {
		$app = \Slim\Slim::getInstance();
		$finalGenres = array();
		$badChunk = FALSE;
		cliLog("----------GENRE-PARSER----------", 6);
		cliLog("INPUT: " . $itemString, 6);

		self::parseGenreStringPhase1($itemString, $finalGenres, $badChunk);
		if($badChunk === FALSE) {
			cliLog("  FINAL RESULT: " . join(", ", $finalGenres), 6);
			return $finalGenres;
		}

		$badChunk = FALSE;
		self::parseGenreStringPhase2($app, $itemString, $finalGenres, $badChunk);
		if($badChunk === FALSE) {
			cliLog("  FINAL RESULT: " . join(", ", $finalGenres), 6);
			return $finalGenres;
		}

		$badChunk = FALSE;
		self::parseGenreStringPhase3($app, $itemString, $finalGenres, $badChunk);
		if($badChunk === FALSE) {
			cliLog("  FINAL RESULT: " . join(", ", $finalGenres), 6);
			return $finalGenres;
		}

		$badChunk = FALSE;
		self::parseGenreStringPhase4($app, $itemString, $finalGenres, $badChunk);
		if($badChunk === FALSE) {
			cliLog("  FINAL RESULT: " . join(", ", $finalGenres), 6);
			return $finalGenres;
		}

		$badChunk = FALSE;
		self::parseGenreStringPhase5($app, $itemString, $finalGenres, $badChunk);
		cliLog("  FINAL RESULT: " . join(", ", $finalGenres), 6);
		return $finalGenres;
	}

	public static function parseGenreStringPhase1(&$itemString, &$finalGenres, &$badChunk) {
		cliLog(" Phase 1: check if we do have a string we can work with", 6);
		if(trim($itemString) === '') {
			$finalGenres['unknown'] = "Unknown";
			cliLog("  nothing to do with an emtpy string.", 7);
			return;
		}
		if(isHash($itemString) === TRUE) {
			// TODO: is there a chance to translate strings like HASH(0xa54fe70) to an useable string?
			// TODO: read from config: "importer.unknowngenre"
			$finalGenres['unknown'] = "Unknown";
			cliLog("  nothing to do with an useless hash string.", 7);
			return;
		}
		cliLog("  genre seems useable. continue...", 7);
		$badChunk = TRUE;
	}

	public static function parseGenreStringPhase2($app, &$itemString, &$finalGenres, &$badChunk) {
		cliLog(" Phase 2: check if we do have a single cached genre", 6);
		$itemString = str_replace(array("(",")","[","]", "{","}", "<", ">"), " ", $itemString);
		$az09 = az09($itemString);
		$itemId = self::cacheRead($app, get_called_class(), $az09);
		if($itemId !== FALSE) {
			$finalGenres[$az09] = $itemString;
			return;
		}
		cliLog("  continue...", 7);
		$badChunk = TRUE;
	}

	public static function parseGenreStringPhase3($app, &$itemString, &$finalGenres, &$badChunk) {
		cliLog(" Phase 3: check if we do have multiple cached genres", 6);
		$classPath = get_called_class();
		$tmpGlue = "tmpGlu3";
		$chunks = trimExplode($tmpGlue, str_ireplace($app->config['genre-glue'], $tmpGlue, $itemString), TRUE);
		foreach($chunks as $chunk) {
			$az09 = az09($chunk);
			
			if(isset($app->importerCache[$classPath]["unified"][$az09])) {
				$finalGenres[$az09] = $app->importerCache[$classPath]["unified"][$az09];
				$itemString = str_ireplace($chunk, "", $itemString);
				cliLog("  FINAL-CHUNK: $chunk = ".$finalGenres[$az09], 7);
				continue;
			}
			// very fuzzy check if we have an url
			if(preg_match("/^(http|www)([a-z0-9\.\/\:]*)\.[a-z]{2,4}$/i", $chunk)) {
				$itemString = str_ireplace($chunk, "", $itemString);
				cliLog("  TRASHING url-chunk: $chunk", 7);
				continue;
			}
			// very fuzzy check if we have an url
			if(preg_match("/(myspace|blogspot).com$/i", $chunk)) {
				$itemString = str_ireplace($chunk, "", $itemString);
				cliLog("  TRASHING: trash url-chunk: $chunk", 7);
				continue;
			}
			cliLog("  BAD-CHUNK: $chunk - entering phase 4...", 7);
			$badChunk = TRUE;
		}
		
	}

	public static function parseGenreStringPhase4($app, &$itemString, &$finalGenres, &$badChunk) {
		cliLog(" Phase 4: check remaining chunks", 6);
		$classPath = get_called_class();
		$tmpGlue = "tmpGlu3";
		#print_r($app->config['genre-replace-chunks']); die();
		// phase 4: tiny chunks
		# TODO: would camel-case splitting make sense?
		$splitBy = array_merge($app->config['genre-glue'], array(" ", "-", ".", "_", ""));
		$badChunk = FALSE;
		$chunks = trimExplode($tmpGlue, str_ireplace($splitBy, $tmpGlue, $itemString), TRUE);
		foreach($chunks as $chunk) {
			$az09 = az09($chunk);
			if(isset($app->config['genre-replace-chunks'][$az09])) {
				$itemString = str_ireplace($chunk, $app->config['genre-replace-chunks'][$az09], $itemString);
				cliLog("  REPLACING $chunk with: ".$app->config['genre-replace-chunks'][$az09], 7);
			}
			if(isset($app->config['genre-remove-chunks'][$az09])) {
				$itemString = str_ireplace($chunk, "", $itemString);
				cliLog("  REMOVING: trash url-chunk: $chunk",7);
				continue;
			}
			if(isset($app->importerCache[$classPath]["unified"][$az09])) {
				$finalGenres[$az09] = $app->importerCache[$classPath]["unified"][$az09];
				$itemString = str_ireplace($chunk, "", $itemString);
				cliLog("  FINAL-CHUNK: $chunk = ".$finalGenres[$az09], 7);
				continue;
			}
			if(trim(az09($chunk)) !== '' && trim(az09($chunk)) !== 'and') {
				cliLog("  BAD-CHUNK: $chunk - entering phase 5...", 7);
				$badChunk = TRUE;
			}
		}
	}

	public static function parseGenreStringPhase5($app, &$itemString, &$finalGenres, &$badChunk) {
		cliLog(" Phase 5: check remaining chunks after replacement and removal", 6);
		$classPath = get_called_class();
		$tmpGlue = "tmpGlu3";
		$splitBy = array_merge($app->config['genre-glue'], array(" ", "-", ".", "_", ""));
		$badChunk = FALSE;
		$chunks = trimExplode($tmpGlue, str_ireplace($splitBy, $tmpGlue, $itemString), TRUE);
		if(count($chunks) === 1) {
			$az09 = az09($chunks[0]);
			$finalGenres[$az09] = $chunks[0];
			cliLog("  only one chunk left. lets assume \"". $chunks[0] ."\" is a genre", 7);
			return $finalGenres; 
		}
		$joinedChunkRest = strtolower(join(".", $chunks));
		
		if(isset($app->importerCache[$classPath]["preserve"][$joinedChunkRest]) === TRUE) {
			$finalGenres[az09($joinedChunkRest)] = $app->importerCache[$classPath]["preserve"][$joinedChunkRest];
			cliLog("  found genre based on full preserved pattern: $joinedChunkRest = ".$app->importerCache[$classPath]["preserve"][$joinedChunkRest], 7);
			return $finalGenres;
		}

		cliLog("  REMAINING CHUNKS:" . $joinedChunkRest, 7);
		$foundPreservedMatch = FALSE;
		foreach($app->importerCache[$classPath]["preserve"] as $preserve => $genreString) {
			if(preg_match("/".str_replace(".", "\.", $preserve) . "/", $joinedChunkRest)) {
				$finalGenres[az09($preserve)] = $genreString;
				$foundPreservedMatch = TRUE;
				cliLog("  found genre based on partly preserved pattern: $preserve = ".$genreString, 7);
				$removeChunks = explode('.', $preserve);
				$az09Chunks = array_map('az09', $chunks);
				foreach($removeChunks as $removeChunk) {
					if(array_search($removeChunk, $az09Chunks) !== FALSE) {
						unset($chunks[array_search($removeChunk, $az09Chunks)]);
					}
				}
			}
		}

		// TODO check
		// Coast Hip-Hop, Hardcore Hip-Hop, Gangsta

		// give up and create new genre for each chunk		
		foreach($chunks as $chunk) {
			$az09 = az09($chunk);
			$finalGenres[$az09] = $chunk;
			cliLog("  giving up and creating new genre: $az09 = ".$chunk, 7);
		}
	}

	// check for all uppercase or all lowercase and do apply corrections
	public static function cleanUpGenreStringArray($input) {
		$output = array();
		
		if(count($input) == 0) {
			return array("unknown" => "Unknown");
		}
		
		// "Unknown" can be dropped in case we have an additional genre-entry 
		if(count($input) > 1 && $idx = array_search("Unknown", $input)) {
			unset($input[$idx]);
		}
		
		foreach($input as $item) {
			if(strlen($item) < 2) {
				continue;
			}
			$output[az09($item)] = fixCaseSensitivity($item);
		}
		/*
		// hotfix for bug in parseGenreStringAdvanced()
		// not sure if this bug still exists!?
		# TODO: fix parseGenreStringAdvanced() and remove these lines
		if(isset($output['drum']) === TRUE && isset($output['bass']) === TRUE) {
			unset($output['drum']);
			unset($output['bass']);
			$output['drumandbass'] = "Drum & Bass";
		}
		if(isset($output['deep']) === TRUE && isset($output['house']) === TRUE) {
			unset($output['deep']);
			$output['deephouse'] = "Deep House";
		}
		*/
		
		return $output;
	}
	
	public static function getIdsByString($itemString) {
		$app = \Slim\Slim::getInstance();
		self::cacheUnifier($app, get_called_class());
		self::buildPreserveCache($app, get_called_class());
		
		$genreStringArray = [];
		$tmpGlue = "tmpGlu3";
		foreach(trimExplode($tmpGlue, str_ireplace($app->config['genre-glue'], $tmpGlue, $itemString), TRUE) as $itemPart) {
			// activate parser
			$genreStringArray = array_merge($genreStringArray, self::parseGenreStringAdvanced($itemPart));
		}

		// string beautyfying & 1 workaround for a parser bug
		$genreStringArray = self::cleanUpGenreStringArray($genreStringArray);

		
		#echo "input: $itemString\nresul: " . join(' || ', $genreStringArray) . "\n-------------------\n";
		#ob_flush();

		
		$itemIds = array();
		foreach($genreStringArray as $az09 => $genreString) {

			// check if we alread have an id
			// permformance improvement ~8%
			$itemId = self::cacheRead($app, get_called_class(), $az09);
			if($itemId !== FALSE) {
				$itemIds[$itemId] = $itemId;
				continue;
			}
			
			$query = "SELECT id FROM genre WHERE az09=\"" . $az09 . "\" LIMIT 1;";
			$result = $app->db->query($query);
			$record = $result->fetch_assoc();
			if($record) {
				$itemId = $record["id"];
				$itemIds[$record["id"]] = $record["id"];
				self::cacheWrite($app, get_called_class(), $az09, $record["id"]);
				continue;
			}

			$instance = new \Slimpd\Models\Genre();
			$instance->setTitle($genreString);
			$instance->setAz09($az09);
			$instance->insert();
			$itemId = $app->db->insert_id;

			$itemIds[$itemId] = $itemId;
			self::cacheWrite($app, get_called_class(), $az09, $itemId);
		}
		
		return $itemIds;

	}

	public static function buildPreserveCache($app, $classPath) {
		if(isset($app->importerCache[$classPath]["preserve"]) === TRUE) {
			return;
		}
		if(isset($app->importerCache) === FALSE) {
			$app->importerCache = array();
		}
		// we can only modify a copy and assign it back afterward (Indirect modification of overloaded property)
		$tmpArray = $app->importerCache;
		$tmpArray[$classPath]["preserve"] = array();
		
		// build a special whitelist
		$recursiveIterator = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($app->config['genre-preserve-junks']));
		foreach ($recursiveIterator as $leafValue) {
			$keys = array();
			foreach (range(0, $recursiveIterator->getDepth()) as $depth) {
				$keys[] = $recursiveIterator->getSubIterator($depth)->key();
			}
			$tmpArray[$classPath]["preserve"][ join('.', $keys) ] = $leafValue;
		}

		$app->importerCache = $tmpArray;
	}
}
