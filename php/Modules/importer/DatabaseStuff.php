<?php
namespace Slimpd\Modules\importer;

/**
 * pretty global database operations
 * TODO: find a better name for this class
 */
class DatabaseStuff extends \Slimpd\Modules\importer\AbstractImporter {

	/**
	 * fills databasefields trackCount & albumCount of tables: artist,genre,label
	 */
	public function updateCounterCache() {
		$app = \Slim\Slim::getInstance();
		$this->jobPhase = 7;
		$this->beginJob(array(
			'currentItem' => "counting items to process for displaying progressbar ..."
		), __FUNCTION__);
		foreach(array('artist', 'genre', 'label') as $table) {
			// reset all counters
			$app->db->query("UPDATE " . $table . " SET trackCount=0, albumCount=0");
			
			$query = "SELECT count(id) AS itemsTotal FROM " . $table;
			$this->itemsTotal += $app->db->query($query)->fetch_assoc()['itemsTotal'];
		}
		
		// collect all genreIds, labelIds, artistIds, remixerIds, featuringIds, albumId provided by tracks
		$tables = array(
			'Artist' => array(),
			'Genre' => array(),
			'Label' => array()
		);
		
		// to be able to display a progress status
		$all = array();
		$result = $app->db->query("SELECT id,albumId,artistId,remixerId,featuringId,genreId,labelId FROM track");
		
		while($record = $result->fetch_assoc()) {
			$all['al' . $record['albumId']] = NULL;
			$this->updateJob(array(
				'currentItem' => 'trackId: ' . $record['id']
			));
			
			$itemIds = trimExplode(",", join(",", [$record["artistId"],$record["remixerId"],$record["featuringId"]]), TRUE);
			foreach($itemIds as $itemId) {
				$tables['Artist'][$itemId]['tracks'][ $record['id'] ] = NULL;
				$tables['Artist'][$itemId]['albums'][ $record['albumId'] ] = NULL;
				$all['ar' . $itemId] = NULL;
			}
			$itemIds = trimExplode(",", $record['genreId'], TRUE);
			foreach($itemIds as $itemId) {
				$tables['Genre'][$itemId]['tracks'][ $record['id'] ] = NULL;
				$tables['Genre'][$itemId]['albums'][ $record['albumId'] ] = NULL;
				$all['ge' . $itemId] = NULL;
			}
			$itemIds = trimExplode(",", $record['labelId'], TRUE);
			foreach($itemIds as $itemId) {
				$tables['Label'][$itemId]['tracks'][ $record['id'] ] = NULL;
				$tables['Label'][$itemId]['albums'][ $record['albumId'] ] = NULL;
				$all['la' . $itemId] = NULL;
			}
		}
		
		foreach($tables as $className => $tableData) {
			cliLog("updating table:".$className." with trackCount and albumCount", 3);
			foreach($tableData as $itemId => $data) {
				
				$classPath = "\\Slimpd\\Models\\" . $className;
				$item = new $classPath();
				$item->setId($itemId);
				$item->setTrackCount( count($data['tracks']) );
				
				$msg = "updating ".$className.": " . $itemId . " with trackCount:" .  $item->getTrackCount();
				$item->setAlbumCount( count($data['albums']) );
				$msg .= ", albumCount:" .  $item->getAlbumCount();
				$item->update();
				$this->itemsProcessed++;
				$this->itemsChecked++;
				$this->updateJob(array(
					'currentItem' => $msg
				));
				cliLog($msg, 7);
			}
			
			// delete all items which does not have any trackCount or albumCount
			// but preserve default entries
			$query = "
				DELETE FROM " . strtolower($className) . "
				WHERE trackCount=0
				AND albumCount=0
				AND id>" . (($className === 'Artist') ? 11 : 10); // unknown artist, various artists,...
			cliLog("deleting ".$className."s  with trackCount=0 AND albumCount=0", 3);
			$app->db->query($query);
		}
		unset($tables);
		unset($all);
		$this->finishJob(array(), __FUNCTION__);
		return;
	}

	public static function getInitialDatabaseQueries($app = NULL) {
		if($app === NULL) {
			$app = \Slim\Slim::getInstance();
		}
		$queries = array(
			"TRUNCATE artist;",
			"TRUNCATE genre;",
			"TRUNCATE track;",
			"TRUNCATE label;",
			"TRUNCATE album;",
			"TRUNCATE albumindex;",
			"TRUNCATE trackindex;",
			"ALTER TABLE `artist` AUTO_INCREMENT = 10;",
			"ALTER TABLE `genre` AUTO_INCREMENT = 10;",
			"ALTER TABLE `label` AUTO_INCREMENT = 10;",
			"ALTER TABLE `album` AUTO_INCREMENT = 10;",
			"ALTER TABLE `albumindex` AUTO_INCREMENT = 10;",
			"ALTER TABLE `track` AUTO_INCREMENT = 10;",
			"ALTER TABLE `trackindex` AUTO_INCREMENT = 10;",
		);
		foreach([
			'unknownartist' => 'artist',
			'variousartists' => 'artist',
			'unknowngenre' => 'genre'] as $llKey => $table) {
			$queries[] =
				"INSERT INTO `".$table."` ".
				"VALUES (
					NULL,
					'".$app->ll->str('importer.' . $llKey)."',
					'',
					'".az09($app->ll->str('importer.' . $llKey))."',
					0,
					0
				);";
		}
		$queries[] =
			"INSERT INTO `label` ".
			"VALUES (
				NULL,
				'".$app->ll->str('importer.unknownlabel')."',
				'".az09($app->ll->str('importer.unknownlabel'))."',
				0,
				0
			);";
		return $queries;
	}


	public static function buildDictionarySql() {
		$app = \Slim\Slim::getInstance();
		\Slimpd\Modules\sphinx\Sphinx::defineSphinxConstants($app->config['sphinx']);

		$input  = fopen ("php://stdin", "r");
		$output = fopen ("php://stdout", "w+");
		$usedKeywords = array();
		$sectionCounter = 0;
		fwrite ($output, "TRUNCATE suggest;\n");
		while ($line = fgets($input, 1024)) {
			list($keyword, $freq ) = explode(" ", trim($line));
			$keyword = trim($keyword);
			if (self::addKeywordToSql($keyword, $freq, $usedKeywords) === FALSE) {
				continue;
			}
			
			$trigrams = buildTrigrams($keyword);
			$usedKeywords[$keyword] = NULL;
			fwrite($output, (($sectionCounter === 0) ? "INSERT INTO suggest VALUES\n" : ",\n"));
			fwrite($output, "( 0, '".$keyword."', '".$trigrams.".', ".$freq.")");
			$sectionCounter++;
			if (($sectionCounter % 10000) == 0) {
				fwrite ($output, ";\n");
				$sectionCounter = 0;
			}
		}
		if ($sectionCounter > 0) {
			fwrite ( $output, ";" );
		}
		fwrite ( $output,  "\n");
		$app->stop();
	}

	private static function addKeywordToSql($keyword, $freq, $usedKeywords) {
		if($keyword === "") {
			return FALSE;
		}
		if($freq < FREQ_THRESHOLD) {
			return FALSE;
		}
		if(strlen($keyword) < 2) {
			return FALSE;
		}
		if(isset($usedKeywords[$keyword]) === TRUE ) {
			return FALSE;
		}
		if(strstr($keyword, "_") !== FALSE) {
			return FALSE;
		}
		if(strstr($keyword, "'") !== FALSE) {
			return FALSE;
		}
		return TRUE;
	}
	
}
