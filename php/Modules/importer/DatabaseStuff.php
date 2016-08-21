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
		$this->jobPhase = 9;
		$this->beginJob(array(
			'currentItem' => "fetching all track-labels for inserting into table:album ..."
		), __FUNCTION__);
		foreach(array('artist', 'genre', 'label') as $table) {
			// reset all counters
			$query = "UPDATE " . $table . " SET trackCount=0, albumCount=0";
			$app->db->query($query);
			
			$query = "SELECT count(id) AS itemCountTotal FROM " . $table;
			$this->itemCountTotal += $app->db->query($query)->fetch_assoc()['itemCountTotal'];
		}
		
		// collect all genreIds, labelIds, artistIds, remixerIds, featuringIds provided by tracks
		$tables = array(
			'Artist' => array(),
			'Genre' => array(),
			'Label' => array()
		);
		
		// to be able to display a progress status
		$all = array();
		
		$query = "SELECT id,albumId,artistId,remixerId,featuringId,genreId,labelId FROM track";
		$result = $app->db->query($query);
		
		while($record = $result->fetch_assoc()) {
			$all['al' . $record['albumId']] = NULL;
			$this->itemCountChecked = count($all);
			
			$this->updateJob(array(
				'currentItem' => 'trackId: ' . $record['id']
			));
			
			foreach(array('artistId', 'remixerId', 'featuringId') as $itemIds) {
				$itemIds = trimExplode(",", $record[$itemIds], TRUE);
				foreach($itemIds as $itemId) {
					$tables['Artist'][$itemId]['tracks'][ $record['id'] ] = NULL;
					$tables['Artist'][$itemId]['albums'][ $record['albumId'] ] = NULL;
					$all['ar' . $itemId] = NULL;
				}
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
			$msg = "updating table:".$className." with trackCount and albumCount";
			cliLog($msg, 3);
			foreach($tableData as $itemId => $data) {
				
				$classPath = "\\Slimpd\\Models\\" . $className;
				$item = new $classPath();
				$item->setId($itemId);
				$item->setTrackCount( count($data['tracks']) );
				
				$msg = "updating ".$className.": " . $itemId . " with trackCount:" .  $item->getTrackCount();
				$item->setAlbumCount( count($data['albums']) );
				$msg .= ", albumCount:" .  $item->getAlbumCount();
				$item->update();
				$this->itemCountProcessed++;
				$this->itemCountChecked = count($all);
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
			$msg = "deleting ".$className."s  with trackCount=0 AND albumCount=0";
			cliLog($msg, 3);
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
		foreach(['freq_threshold', 'suggest_debug', 'length_threshold', 'levenshtein_threshold', 'top_count'] as $var) {
			define (strtoupper($var), intval($app->config['sphinx'][$var]) );
		}
		
		$in  = fopen ("php://stdin", "r");
		$out = fopen ("php://stdout", "w+");
		
		$used_keywords = array();
		fwrite ( $out, "TRUNCATE suggest;\n");
		$n = 0;
		$m = 0;
		while ( $line = fgets( $in, 1024 ) ) {
			list ( $keyword, $freq ) = explode ( " ", trim ( $line ) );
			
			$keyword = trim($keyword);
			if (
				strlen($keyword) < 2
				|| $keyword === ''
				|| $freq<FREQ_THRESHOLD
				|| strstr ( $keyword, "_" )!==FALSE
				|| strstr ( $keyword, "'" )!==FALSE
				|| array_key_exists($keyword,$used_keywords) === TRUE ) {
					continue;
				}
			
			$trigrams = buildTrigrams ( $keyword );
			$used_keywords[$keyword] = NULL;
			fwrite ( $out, (( !$m ) ? "INSERT INTO suggest VALUES\n" : ",\n"));
			
			$n++;
			fwrite ( $out, "( 0, '$keyword', '$trigrams', $freq )" );
			$m++;
			if ( ( $m % 10000 )==0 ) {
				fwrite ( $out,  ";\n");
				$m = 0;
			}
		}
	
		if ( $m ) {
			fwrite ( $out, ";" );
		}
		fwrite ( $out,  "\n");
		$app->stop();
	}	
	
}
