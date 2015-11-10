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
		$finalGenres = array();
		if(trim($itemString) === '') {
			$finalGenres['unknown'] = "Unknown";
			debugLog("Phase 0: nothing to do with an emty string. result: " . join(", ", $finalGenres));
			return $finalGenres;
		}
		
		
		if(preg_match("/^hash0x([a-f0-9]{7})$/", az09($itemString))) {
			// TODO: is there a chance to translate strings like HASH(0xa54fe70) to an useable string?
			$finalGenres['unknown'] = "Unknown";
			debugLog("Phase 0: nothing to do with an useleass hassh string. result: " . join(", ", $finalGenres));
			return $finalGenres;
		}
		
		
		$originalItemString = $itemString; // TODO: lets see if we need it anymore...
		$itemString = str_replace(array("(",")","[","]", "{","}", "<", ">"), " ", $itemString);
		// phase 1: check if we already have a common genre
		debugLog("----------");
		debugLog("Phase 1: with genreString: $originalItemString");
		$az09 = az09($itemString);
		if(isset($GLOBALS['unifiedCommonGenres'][$az09])) {
			$finalGenres[] = $GLOBALS['unifiedCommonGenres'][$az09];
			debugLog("exiting in phase 1 with result: " . join(", ", $finalGenres));
			return $finalGenres;
		}
		
		
		// phase 2: check if we have multiple common genres
		
		$tmpGlue = "tmpGlu3";
		$badChunk = FALSE;
		debugLog("Phase 2");
		$chunks = trimExplode($tmpGlue, str_ireplace($app->config['genre-glue'], $tmpGlue, $itemString), TRUE);
		foreach($chunks as $chunk) {
			$az09 = az09($chunk);
			if(isset($GLOBALS['unifiedCommonGenres'][$az09])) {
				$finalGenres[$az09] = $GLOBALS['unifiedCommonGenres'][$az09];
				$itemString = str_ireplace($chunk, "", $itemString);
				debugLog("FINAL-CHUNK: $chunk = ".$finalGenres[$az09]); 
			} else {
				// very fuzzy check if we have an url
				if(preg_match("/^(http|www)([a-z0-9\.\/\:]*)\.[a-z]{2,4}$/i", $chunk)) {
					$itemString = str_ireplace($chunk, "", $itemString);
					debugLog("TRASHING url-chunk: $chunk");
					continue;
				}
				// very fuzzy check if we have an url
				if(preg_match("/(myspace|blogspot).com$/i", $chunk)) {
					$itemString = str_ireplace($chunk, "", $itemString);
					debugLog("TRASHING: trash url-chunk: $chunk");
					continue;
				}
				debugLog("BAD-CHUNK: $chunk - entering phase 3...");
				$badChunk = TRUE;
			}
		}
		
		if($badChunk === FALSE) {
			debugLog("exiting in phase 2 with result: " . join(", ", $finalGenres));
			return $finalGenres;
		}
		#print_r($app->config['genre-replace-chunks']); die();
		// phase 3: tiny chunks
		# TODO: would camel-case splitting make sense?
		debugLog("Phase 3");
		$splitBy = array_merge($app->config['genre-glue'], array(" ", "-", ".", "_", ""));
		$badChunk = FALSE;
		$chunks = trimExplode($tmpGlue, str_ireplace($splitBy, $tmpGlue, $itemString), TRUE);
		foreach($chunks as $chunk) {
			$az09 = az09($chunk);
			if(isset($app->config['genre-replace-chunks'][$az09])) {
				$itemString = str_ireplace($chunk, $app->config['genre-replace-chunks'][$az09], $itemString);
				debugLog("REPLACING $chunk with: ".$app->config['genre-replace-chunks'][$az09]);
			}
			if(isset($app->config['genre-remove-chunks'][$az09])) {
				$itemString = str_ireplace($chunk, "", $itemString);
				debugLog("REMOVING: trash url-chunk: $chunk");
				continue;
			}
			if(isset($GLOBALS['unifiedCommonGenres'][$az09])) {
				$finalGenres[$az09] = $GLOBALS['unifiedCommonGenres'][$az09];
				$itemString = str_ireplace($chunk, "", $itemString);
				debugLog("FINAL-CHUNK: $chunk = ".$finalGenres[$az09]); 
			} else {
				if(trim(az09($chunk)) !== '' && trim(az09($chunk)) !== 'and') {
					debugLog("BAD-CHUNK: $chunk - entering phase 4...");
					$badChunk = TRUE;
				}
			}
		}
		
		
		if($badChunk === FALSE) {
			debugLog("exiting in phase 3 with result: " . join(", ", $finalGenres));
			return $finalGenres;
		}
		
		
		// phase 4: remaining tiny chunks
		debugLog("Phase 4");
		$splitBy = array_merge($app->config['genre-glue'], array(" ", "-", ".", "_", ""));
		$badChunk = FALSE;
		$chunks = trimExplode($tmpGlue, str_ireplace($splitBy, $tmpGlue, $itemString), TRUE);
		if(count($chunks) === 1) {
			$az09 = az09($chunks[0]);
			$finalGenres[$az09] = $chunks[0];
			debugLog("exiting phase 4 with result: " . join(", ", $finalGenres));
			return $finalGenres; 
		}
		$joinedRemainingChunks = strtolower(join(".", $chunks));
		
		if(isset($GLOBALS['preserveGenreChunks'][$joinedRemainingChunks]) === TRUE) {
			$finalGenres[az09($joinedRemainingChunks)] = $GLOBALS['preserveGenreChunks'][$joinedRemainingChunks];
			debugLog("found genre based on full preserved pattern: $joinedRemainingChunks = ".$GLOBALS['preserveGenreChunks'][$joinedRemainingChunks]);
			debugLog("exiting in phase 4 with result: " . join(", ", $finalGenres));
			return $finalGenres;
		}
		
		
		debugLog("REMAINING CHUNKWURST:" . $joinedRemainingChunks);
		$foundPreservedMatch = FALSE;
		foreach($GLOBALS['preserveGenreChunks'] as $preserve => $genreString) {
			if(preg_match("/".str_replace(".", "\.", $preserve) . "/", $joinedRemainingChunks, $m)) {
				$finalGenres[az09($preserve)] = $genreString;
				$foundPreservedMatch = TRUE;
				debugLog("found genre based on partly preserved pattern: $preserve = ".$genreString);
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
			debugLog("giving up and creating new genre: $az09 = ".$chunk);
		}
		debugLog("exiting phase 4 with result: " . join(", ", $finalGenres));
		return $finalGenres;
	}

	// check for all uppercase or all lowercase and do apply corrections
	public static function cleanUpGenreStringArray($input) {
		$output = array();
		
		if(count($input) == 0) {
			return array("unknown" => "Unknown");
		}
		
		// "Unknown" is not necessary in case we have an additiopnal genre-entry 
		if(count($input) > 1 && $idx = array_search("Unknown", $input)) {
			unset($input[$idx]);
		}
		
		foreach($input as $item) {
			if(strlen($item) < 2) {
				continue;
			}
			$az09 = az09($item);
			$output[$az09] = $item;
			if(strtolower($item) == $item) {
				$output[$az09] = ucwords($item);
			}
			if(strtoupper($item) == $item && strlen($item)>3) {
				$output[$az09] = ucwords(strtolower($item));
			}
		}
		// hotfix for bug in parseGenreStringAdvanced()
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
		
		
		return $output;
	}

	
	public static function getIdsByString($itemString) {
		$app = \Slim\Slim::getInstance();
		// get unified common-genres
		if(isset($GLOBALS['unifiedCommonGenres']) === FALSE) {
			if(isset($app->config['common-genres'])) {
				$GLOBALS['unifiedCommonGenres'] = self::unifyItemnames($app->config['common-genres']);
			} else {
				$GLOBALS['unifiedCommonGenres'] = array();
			}
		}
		
		// build a special whitelist
		if(isset($GLOBALS['preserveGenreChunks']) === FALSE) {
			$recursiveIterator = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($app->config['genre-preserve-junks']));
			$GLOBALS['preserveGenreChunks'] = array();
			foreach ($recursiveIterator as $leafValue) {
			    $keys = array();
			    foreach (range(0, $recursiveIterator->getDepth()) as $depth) {
			        $keys[] = $recursiveIterator->getSubIterator($depth)->key();
			    }
			    $GLOBALS['preserveGenreChunks'][ join('.', $keys) ] = $leafValue;
			}
		}
		
		// make sure we have a cache to avoid tons of queries
		if(isset($GLOBALS['genreCache']) === FALSE) {
			$GLOBALS['genreCache'] = array();
		}
		
		
		// activate parser
		$genreStringArray = self::parseGenreStringAdvanced($itemString);
		
		// string beuatyfiing & 1 workaround for a parser bug
		$genreStringArray = self::cleanUpGenreStringArray($genreStringArray);

		
		#echo "input: $itemString\nresul: " . join(' || ', $genreStringArray) . "\n-------------------\n";
		#ob_flush();

		
		$itemIds = array();
		foreach($genreStringArray as $az09 => $genreString) {

			// check if we alread have an id
			// permformance improvement ~8%
			if(isset($GLOBALS['genreCache'][$az09]) === TRUE) {
				$itemIds[$GLOBALS['genreCache'][$az09]] = $GLOBALS['genreCache'][$az09];
				continue;
			}
			
			$query = "SELECT id FROM genre WHERE az09=\"" . $az09 . "\" LIMIT 1;";
			$result = $app->db->query($query);
			$record = $result->fetch_assoc();
			if($record) {
				$itemId = $record['id'];
			} else {
				$g = new \Slimpd\Genre();
				$g->setTitle($genreString);
				$g->setAz09($az09);
				$g->insert();
				$itemId = $app->db->insert_id;
			}
			$itemIds[$itemId] = $itemId;
			$GLOBALS['genreCache'][$az09] = $itemId;
		}
		
		return $itemIds;

	}
}
