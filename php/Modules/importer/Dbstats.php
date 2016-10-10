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
 * populates those database fields:
 * [artist|genre|label].trackCount
 * [artist|genre|label].albumCount
 * [artist|genre|label].yearRange
 * [artist|label].topGenreUids (TODO: not finished for all models)
 * [artist|genre].topLabelUids (TODO: not finished for all models)
 */
class Dbstats extends \Slimpd\Modules\importer\AbstractImporter {
	public function updateCounterCache() {
		$app = \Slim\Slim::getInstance();
		$this->jobPhase = 7;
		$this->beginJob(array(
			'currentItem' => "counting items to process for displaying progressbar ..."
		), __FUNCTION__);
		cliLog("clearing stats in databasetables", 2, "yellow");
		foreach(array('artist', 'genre', 'label') as $table) {
			// reset all counters
			$app->db->query("UPDATE " . $table . " SET trackCount=0, albumCount=0");
			// get total amount for displaying progress
			$query = "SELECT count(uid) AS itemsTotal FROM " . $table;
			$this->itemsTotal += $app->db->query($query)->fetch_assoc()['itemsTotal'];
		}
		
		// collect all genreUids, labelUids, artistUids, remixerUids, featuringUids, albumUid provided by tracks
		$tables = array(
			'Artist' => array(),
			'Genre' => array(),
			'Label' => array()
		);
		
		// "$all" is used for displaying current progress but not processed
		$all = array();
		$result = $app->db->query("SELECT uid,albumUid,artistUid,remixerUid,featuringUid,genreUid,labelUid,year FROM track");
		cliLog("collecting track data", 2, "yellow");
		while($trackRow = $result->fetch_assoc()) {
			$this->handleTrackRow($trackRow, $tables, $all);
			// first half is collecting data
			$this->itemsChecked = count($all)/2;
		}

		// we may have album artists|genres|labels that does not exist as track artists|genres|labels
		$result = $app->db->query("SELECT uid,artistUid,genreUid,labelUid FROM album");
		cliLog("collecting album data", 2, "yellow");
		while($albumRow = $result->fetch_assoc()) {
			$this->handleAlbumRow($albumRow, $tables, $all);
			// first half is collecting data
			$this->itemsChecked = count($all)/2;
		}
		cliLog("start processing collected data", 2, "yellow");
		$this->processCollectedData($tables, $app);
		unset($tables);
		unset($all);
		$this->deleteOrphans($app);
		$this->finishJob(array(), __FUNCTION__);
		return;
	}

	private function handleTrackRow($trackRow, &$tables, &$all) {
		$this->updateJob(array(
			'currentItem' => 'trackUid: ' . $trackRow['uid']
		));
		$itemUids = trimExplode(",", join(",", [$trackRow["artistUid"],$trackRow["remixerUid"],$trackRow["featuringUid"]]), TRUE);
		foreach($itemUids as $itemUid) {
			$tables['Artist'][$itemUid]['tracks'][ $trackRow['uid'] ] = NULL;
			$tables['Artist'][$itemUid]['albums'][ $trackRow['albumUid'] ] = NULL;
			// TODO: do this check seemsYeary in migrator phase (on insert) and remove it from here
			if(\Slimpd\RegexHelper::seemsYeary($trackRow['year']) === TRUE) {
				$tables['Artist'][$itemUid]['years'][ $trackRow['year'] ] = NULL;
			}
			// add label uids
			foreach(trimExplode(",",$trackRow['labelUid'], TRUE) as $labelUid) {
				if($labelUid == 10) { // Unknown Label
					continue;
				}
				$tables['Artist'][$itemUid]['labels'][] = $labelUid;
			}
			// add genre uids
			foreach(trimExplode(",",$trackRow['genreUid'], TRUE) as $genreUid) {
				if($genreUid == 10) { // Unknown Genre
					continue;
				}
				$tables['Artist'][$itemUid]['genres'][] = $genreUid;
			}
			$all['ar' . $itemUid] = NULL;
		}
		$itemUids = trimExplode(",", $trackRow['genreUid'], TRUE);
		foreach($itemUids as $itemUid) {
			$tables['Genre'][$itemUid]['tracks'][ $trackRow['uid'] ] = NULL;
			$tables['Genre'][$itemUid]['albums'][ $trackRow['albumUid'] ] = NULL;
			$all['ge' . $itemUid] = NULL;
		}
		$itemUids = trimExplode(",", $trackRow['labelUid'], TRUE);
		foreach($itemUids as $itemUid) {
			$tables['Label'][$itemUid]['tracks'][ $trackRow['uid'] ] = NULL;
			$tables['Label'][$itemUid]['albums'][ $trackRow['albumUid'] ] = NULL;
			$all['la' . $itemUid] = NULL;
		}
	}

	private function handleAlbumRow($albumRow, &$tables, &$all) {
		#$all['al' . $albumRow['uid']] = NULL;
		$this->updateJob(array(
			'currentItem' => 'albumUid: ' . $albumRow['uid']
		));
		$itemUids = trimExplode(",", $albumRow["artistUid"], TRUE);
		foreach($itemUids as $itemUid) {
			$tables['Artist'][$itemUid]['albums'][ $albumRow['uid'] ] = NULL;
			$all['ar' . $itemUid] = NULL;
		}
		$itemUids = trimExplode(",", $albumRow['genreUid'], TRUE);
		foreach($itemUids as $itemUid) {
			$tables['Genre'][$itemUid]['albums'][ $albumRow['uid'] ] = NULL;
			$all['ge' . $itemUid] = NULL;
		}
		$itemUids = trimExplode(",", $albumRow['labelUid'], TRUE);
		foreach($itemUids as $itemUid) {
			$tables['Label'][$itemUid]['albums'][ $albumRow['uid'] ] = NULL;
			$all['la' . $itemUid] = NULL;
		}
	}
	
	private function processCollectedData($tables, $app) {
		foreach($tables as $className => $tableData) {
			cliLog("updating table:".$className." with trackCount and albumCount", 3);
			foreach($tableData as $itemUid => $data) {
				$classPath = "\\Slimpd\\Models\\" . $className;
				$item = new $classPath();
				$item->setUid($itemUid)
					->setTrackCount( count(@$data['tracks']) )
					->setAlbumCount( count(@$data['albums']) );

				$this->setTopLabelUids($item, $className, $data);
				$this->setTopGenreUids($item, $className, $data);
				$this->setYearRange($item, $data);

				$item->update();
				$this->itemsProcessed++;
				// 2nd half is proccessing collected data
				$this->itemsChecked += 0.5;
				$msg = "updating ".$className.": " . $itemUid .
					" with trackCount:" .  $item->getTrackCount() .
					", albumCount:" .  $item->getAlbumCount();
				$this->updateJob(array(
					"currentItem" => $msg
				));
				cliLog($msg, 7);
			}
		}
	}

	private function setTopGenreUids(&$item, $className, $data) {
		if($className === "Genre" || isset($data["genres"]) === FALSE) {
			return;
		}
		$genreUids = uniqueArrayOrderedByRelevance($data['genres']);
		$item->setTopLabelUids(trim(array_shift($genreUids) . "," . array_shift($genreUids), ","));
	}

	private function setTopLabelUids(&$item, $className, $data) {
		if($className === "Label" || isset($data["labels"]) === FALSE) {
			return;
		}
		$labelUids = uniqueArrayOrderedByRelevance($data['labels']);
		$item->setTopLabelUids(trim(array_shift($labelUids) . "," . array_shift($labelUids), ","));
	}

	private function setYearRange(&$item, $data) {
		if(isset($data['years']) === FALSE) {
			return;
		}
		$min = min(array_keys($data['years']));
		$max = max(array_keys($data['years']));
		$yearString = ($min === $max) ? $min : $min . "-".$max;
		$item->setYearRange($yearString);
	}

	/**
	 * delete all items which does not have any trackCount or albumCount
	 * but preserve default entries
	 */
	private function deleteOrphans($app) {
		$tables = [
			// tablename => highestDefaultUid
			'artist' => 11,	// Unknown artist=10, various artists=11
			'label' => 10,  // Unknown label
			'genre' => 10, // Unknown genre
		];
		foreach($tables as $tableName => $defaultUid) {
			$query = "DELETE FROM " . $tableName . " WHERE trackCount=0 AND albumCount=0 AND uid>" . $defaultUid;
			cliLog("deleting ".$tableName."s  with trackCount=0 AND albumCount=0", 2, "yellow");
			$app->db->query($query);
		}
	}
}
