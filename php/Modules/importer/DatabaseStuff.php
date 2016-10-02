<?php
namespace Slimpd\Modules\importer;
/* Copyright (C) 2016 othmar52 <othmar52@users.noreply.github.com>
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
			
			$query = "SELECT count(uid) AS itemsTotal FROM " . $table;
			$this->itemsTotal += $app->db->query($query)->fetch_assoc()['itemsTotal'];
		}
		
		// collect all genreUids, labelUids, artistUids, remixerUids, featuringUids, albumUid provided by tracks
		$tables = array(
			'Artist' => array(),
			'Genre' => array(),
			'Label' => array()
		);
		
		// to be able to display a progress status
		$all = array();
		$result = $app->db->query("SELECT uid,albumUid,artistUid,remixerUid,featuringUid,genreUid,labelUid FROM track");
		
		while($record = $result->fetch_assoc()) {
			$all['al' . $record['albumUid']] = NULL;
			$this->updateJob(array(
				'currentItem' => 'trackUid: ' . $record['uid']
			));
			
			$itemUids = trimExplode(",", join(",", [$record["artistUid"],$record["remixerUid"],$record["featuringUid"]]), TRUE);
			foreach($itemUids as $itemUid) {
				$tables['Artist'][$itemUid]['tracks'][ $record['uid'] ] = NULL;
				$tables['Artist'][$itemUid]['albums'][ $record['albumUid'] ] = NULL;
				$all['ar' . $itemUid] = NULL;
			}
			$itemUids = trimExplode(",", $record['genreUid'], TRUE);
			foreach($itemUids as $itemUid) {
				$tables['Genre'][$itemUid]['tracks'][ $record['uid'] ] = NULL;
				$tables['Genre'][$itemUid]['albums'][ $record['albumUid'] ] = NULL;
				$all['ge' . $itemUid] = NULL;
			}
			$itemUids = trimExplode(",", $record['labelUid'], TRUE);
			foreach($itemUids as $itemUid) {
				$tables['Label'][$itemUid]['tracks'][ $record['uid'] ] = NULL;
				$tables['Label'][$itemUid]['albums'][ $record['albumUid'] ] = NULL;
				$all['la' . $itemUid] = NULL;
			}
		}

		// we may have album artists that does not exist as track artists
		$result = $app->db->query("SELECT uid,artistUid,genreUid,labelUid FROM album");
		while($record = $result->fetch_assoc()) {
			$all['al' . $record['uid']] = NULL;
			$this->updateJob(array(
				'currentItem' => 'albumUid: ' . $record['uid']
			));
			$itemUids = trimExplode(",", $record["artistUid"], TRUE);
			foreach($itemUids as $itemUid) {
				$tables['Artist'][$itemUid]['albums'][ $record['uid'] ] = NULL;
				$all['ar' . $itemUid] = NULL;
			}
			$itemUids = trimExplode(",", $record['genreUid'], TRUE);
			foreach($itemUids as $itemUid) {
				$tables['Genre'][$itemUid]['albums'][ $record['uid'] ] = NULL;
				$all['ge' . $itemUid] = NULL;
			}
			$itemUids = trimExplode(",", $record['labelUid'], TRUE);
			foreach($itemUids as $itemUid) {
				$tables['Label'][$itemUid]['albums'][ $record['uid'] ] = NULL;
				$all['la' . $itemUid] = NULL;
			}
		}

		foreach($tables as $className => $tableData) {
			cliLog("updating table:".$className." with trackCount and albumCount", 3);
			foreach($tableData as $itemUid => $data) {
				
				$classPath = "\\Slimpd\\Models\\" . $className;
				$item = new $classPath();
				$item->setUid($itemUid)
					->setTrackCount( count(@$data['tracks']) )
					->setAlbumCount( count(@$data['albums']) )
					->update();
				$this->itemsProcessed++;
				$this->itemsChecked++;
				$msg = "updating ".$className.": " . $itemUid .
					" with trackCount:" .  $item->getTrackCount() .
					", albumCount:" .  $item->getAlbumCount();
				$this->updateJob(array(
					"currentItem" => $msg
				));
				cliLog($msg, 7);
			}
			
			// delete all items which does not have any trackCount or albumCount
			// but preserve default entries
			$query = "
				DELETE FROM " . strtolower($className) . "
				WHERE trackCount=0
				AND albumCount=0
				AND uid>" . (($className === 'Artist') ? 11 : 10); // unknown artist, various artists,...
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
					0,
					'',
					''
				);";
		}
		$queries[] =
			"INSERT INTO `label` ".
			"VALUES (
				NULL,
				'".$app->ll->str('importer.unknownlabel')."',
				'".az09($app->ll->str('importer.unknownlabel'))."',
				0,
				0,
				'',
				''
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
