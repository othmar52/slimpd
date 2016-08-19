<?php
namespace Slimpd;

class Genre extends AbstractModel
{
	protected $id;
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
	public function setId($value) {
		$this->id = $value;
	}
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
	public function getId() {
		return $this->id;
	}
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
		$classPath = get_called_class();
		$finalGenres = array();
		if(trim($itemString) === '') {
			$finalGenres['unknown'] = "Unknown";
			cliLog("GenreParser Phase 0: nothing to do with an emtpy string. result: " . join(", ", $finalGenres), 6);
			return $finalGenres;
		}

		if(preg_match("/^hash0x([a-f0-9]{7})$/", az09($itemString))) {
			// TODO: is there a chance to translate strings like HASH(0xa54fe70) to an useable string?
			// TODO: read from config: "importer.unknowngenre"
			$finalGenres['unknown'] = "Unknown";
			cliLog("GenreParser Phase 0: nothing to do with an useleass hash string. result: " . join(", ", $finalGenres), 6);
			return $finalGenres;
		}

		$originalItemString = $itemString; // TODO: lets see if we need it anymore...
		$itemString = str_replace(array("(",")","[","]", "{","}", "<", ">"), " ", $itemString);
		// phase 1: check if we already have a common genre
		cliLog("----------", 6);
		cliLog("GenreParser  1: with genreString: $originalItemString", 6);
		$az09 = az09($itemString);
		
		
		// check if we alread have an id
		// permformance improvement ~8%
		$itemId = self::cacheRead($app, get_called_class(), $az09);
		if($itemId !== FALSE) {
			$finalGenres[$az09] = $itemString;
			cliLog("GenreParser exiting in phase 1 with result: " . join(", ", $finalGenres), 6);
			return $finalGenres;
			
		}
		
		// phase 2: check if we have multiple common genres
		
		$tmpGlue = "tmpGlu3";
		$badChunk = FALSE;
		cliLog("GenreParser Phase 2", 6);
		$chunks = trimExplode($tmpGlue, str_ireplace($app->config['genre-glue'], $tmpGlue, $itemString), TRUE);
		foreach($chunks as $chunk) {
			$az09 = az09($chunk);
			
			if(isset($app->importerCache[$classPath]["unified"][$az09])) {
				$finalGenres[$az09] = $app->importerCache[$classPath]["unified"][$az09];
				$itemString = str_ireplace($chunk, "", $itemString);
				cliLog("GenreParser FINAL-CHUNK: $chunk = ".$finalGenres[$az09], 7);
				continue;
			}
			// very fuzzy check if we have an url
			if(preg_match("/^(http|www)([a-z0-9\.\/\:]*)\.[a-z]{2,4}$/i", $chunk)) {
				$itemString = str_ireplace($chunk, "", $itemString);
				cliLog("GenreParser TRASHING url-chunk: $chunk", 7);
				continue;
			}
			// very fuzzy check if we have an url
			if(preg_match("/(myspace|blogspot).com$/i", $chunk)) {
				$itemString = str_ireplace($chunk, "", $itemString);
				cliLog("GenreParser TRASHING: trash url-chunk: $chunk", 7);
				continue;
			}
			cliLog("GenreParser BAD-CHUNK: $chunk - entering phase 3...", 7);
			$badChunk = TRUE;
		}
		
		if($badChunk === FALSE) {
			cliLog("GenreParser exiting in phase 2 with result: " . join(", ", $finalGenres), 6);
			return $finalGenres;
		}
		#print_r($app->config['genre-replace-chunks']); die();
		// phase 3: tiny chunks
		# TODO: would camel-case splitting make sense?
		cliLog("GenreParser Phase 3", 6);
		$splitBy = array_merge($app->config['genre-glue'], array(" ", "-", ".", "_", ""));
		$badChunk = FALSE;
		$chunks = trimExplode($tmpGlue, str_ireplace($splitBy, $tmpGlue, $itemString), TRUE);
		foreach($chunks as $chunk) {
			$az09 = az09($chunk);
			if(isset($app->config['genre-replace-chunks'][$az09])) {
				$itemString = str_ireplace($chunk, $app->config['genre-replace-chunks'][$az09], $itemString);
				cliLog("GenreParser REPLACING $chunk with: ".$app->config['genre-replace-chunks'][$az09], 7);
			}
			if(isset($app->config['genre-remove-chunks'][$az09])) {
				$itemString = str_ireplace($chunk, "", $itemString);
				cliLog("GenreParser REMOVING: trash url-chunk: $chunk",7);
				continue;
			}
			if(isset($app->importerCache[$classPath]["unified"][$az09])) {
				$finalGenres[$az09] = $app->importerCache[$classPath]["unified"][$az09];
				$itemString = str_ireplace($chunk, "", $itemString);
				cliLog("GenreParser FINAL-CHUNK: $chunk = ".$finalGenres[$az09], 7);
				continue;
			}
			if(trim(az09($chunk)) !== '' && trim(az09($chunk)) !== 'and') {
				cliLog("GenreParser BAD-CHUNK: $chunk - entering phase 4...", 7);
				$badChunk = TRUE;
			}
		}

		if($badChunk === FALSE) {
			cliLog("GenreParser exiting in phase 3 with result: " . join(", ", $finalGenres), 6);
			return $finalGenres;
		}

		// phase 4: remaining tiny chunks
		cliLog("GenreParser Phase 4", 6);
		$splitBy = array_merge($app->config['genre-glue'], array(" ", "-", ".", "_", ""));
		$badChunk = FALSE;
		$chunks = trimExplode($tmpGlue, str_ireplace($splitBy, $tmpGlue, $itemString), TRUE);
		if(count($chunks) === 1) {
			$az09 = az09($chunks[0]);
			$finalGenres[$az09] = $chunks[0];
			cliLog("exiting phase 4 with result: " . join(", ", $finalGenres), 6);
			return $finalGenres; 
		}
		$joinedChunkRest = strtolower(join(".", $chunks));
		
		if(isset($app->importerCache[$classPath]["preserved"][$joinedChunkRest]) === TRUE) {
			$finalGenres[az09($joinedChunkRest)] = $app->importerCache[$classPath]["preserved"][$joinedChunkRest];
			cliLog("found genre based on full preserved pattern: $joinedChunkRest = ".$app->importerCache[$classPath]["preserved"][$joinedChunkRest], 7);
			cliLog("exiting in phase 4 with result: " . join(", ", $finalGenres), 6);
			return $finalGenres;
		}

		cliLog("REMAINING CHUNKWURST:" . $joinedChunkRest, 7);
		$foundPreservedMatch = FALSE;
		foreach($app->importerCache[$classPath]["preserved"] as $preserve => $genreString) {
			if(preg_match("/".str_replace(".", "\.", $preserve) . "/", $joinedChunkRest)) {
				$finalGenres[az09($preserve)] = $genreString;
				$foundPreservedMatch = TRUE;
				cliLog("found genre based on partly preserved pattern: $preserve = ".$genreString, 7);
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
			cliLog("GenreParser giving up and creating new genre: $az09 = ".$chunk, 7);
		}
		cliLog("GenreParser exiting phase 4 with result: " . join(", ", $finalGenres), 6);
		return $finalGenres;
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

			$instance = new \Slimpd\Genre();
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
