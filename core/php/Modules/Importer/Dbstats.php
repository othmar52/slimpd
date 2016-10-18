<?php
namespace Slimpd\Modules\Importer;
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
class Dbstats extends \Slimpd\Modules\Importer\AbstractImporter {
	public function updateCounterCache() {
		$this->jobPhase = 7;
		$this->beginJob(array(
			'currentItem' => "counting items to process for displaying progressbar ..."
		), __FUNCTION__);
		cliLog("clearing stats in databasetables", 2, "yellow");
		foreach(array('artist', 'genre', 'label') as $table) {
			// reset all counters
			// TODO: reset topArtistUids, topGenreUids, topLabelUids
			$this->db->query("UPDATE " . $table . " SET trackCount=0, albumCount=0, yearRange='' ");
			// get total amount for displaying progress
			$query = "SELECT count(uid) AS itemsTotal FROM " . $table;
			$this->itemsTotal += $this->db->query($query)->fetch_assoc()['itemsTotal'];
		}
		
		// collect all genreUids, labelUids, artistUids, remixerUids, featuringUids, albumUid provided by tracks
		$tables = array(
			'Artist' => array(),
			'Genre' => array(),
			'Label' => array()
		);
		
		// "$all" is used for displaying current progress but not processed
		$all = array();
		$result = $this->db->query("SELECT uid,albumUid,artistUid,remixerUid,featuringUid,genreUid,labelUid,year FROM track");
		cliLog("collecting track data", 2, "yellow");
		while($trackRow = $result->fetch_assoc()) {
			$this->handleTrackRow($trackRow, $tables, $all);
			// first half is collecting data
			$this->itemsChecked = count($all)/2;
		}

		// we may have album artists|genres|labels that does not exist as track artists|genres|labels
		$result = $this->db->query("SELECT uid,artistUid,genreUid,labelUid FROM album");
		cliLog("collecting album data", 2, "yellow");
		while($albumRow = $result->fetch_assoc()) {
			$this->handleAlbumRow($albumRow, $tables, $all);
			// first half is collecting data
			$this->itemsChecked = count($all)/2;
		}
		cliLog("start processing collected data", 2, "yellow");
		$this->processCollectedData($tables);
		unset($tables);
		unset($all);
		$this->deleteOrphans();
		$this->finishJob(array(), __FUNCTION__);
		return;
	}

	private function handleTrackRow($trackRow, &$tables, &$all) {
		$this->updateJob(array(
			'currentItem' => 'trackUid: ' . $trackRow['uid']
		));
		$artistUids = trimExplode(",", join(",", [$trackRow["artistUid"],$trackRow["remixerUid"],$trackRow["featuringUid"]]), TRUE);
		$genreUids = trimExplode(",", $trackRow['genreUid'], TRUE);
		$labelUids = trimExplode(",", $trackRow['labelUid'], TRUE);
		foreach($artistUids as $itemUid) {
			$tables['Artist'][$itemUid]['tracks'][ $trackRow['uid'] ] = NULL;
			$tables['Artist'][$itemUid]['albums'][ $trackRow['albumUid'] ] = NULL;
			$tables['Artist'][$itemUid]['years'][ $trackRow['year'] ] = NULL;
			$this->appendTopRelations($tables, 'Artist', $itemUid, 'genres', $genreUids);
			$this->appendTopRelations($tables, 'Artist', $itemUid, 'labels', $labelUids);
			$all['ar' . $itemUid] = NULL;
		}
		
		foreach($genreUids as $itemUid) {
			$tables['Genre'][$itemUid]['tracks'][ $trackRow['uid'] ] = NULL;
			$tables['Genre'][$itemUid]['albums'][ $trackRow['albumUid'] ] = NULL;
			$tables['Genre'][$itemUid]['years'][ $trackRow['year'] ] = NULL;
			$this->appendTopRelations($tables, 'Genre', $itemUid, 'artists', $artistUids);
			$this->appendTopRelations($tables, 'Genre', $itemUid, 'labels', $labelUids);
			$all['ge' . $itemUid] = NULL;
		}
		
		foreach($labelUids as $itemUid) {
			$tables['Label'][$itemUid]['tracks'][ $trackRow['uid'] ] = NULL;
			$tables['Label'][$itemUid]['albums'][ $trackRow['albumUid'] ] = NULL;
			$tables['Label'][$itemUid]['years'][ $trackRow['year'] ] = NULL;
			$this->appendTopRelations($tables, 'Label', $itemUid, 'artists', $artistUids);
			$this->appendTopRelations($tables, 'Label', $itemUid, 'genres', $genreUids);
			
			$all['la' . $itemUid] = NULL;
		}
	}

	private function appendTopRelations(&$tables, $model, $modelUid, $relName, $relUids) {
		foreach($relUids as $relUid) {
			$tables[$model][$modelUid][$relName][] = $relUid;
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
	
	private function processCollectedData($tables) {
		foreach($tables as $className => $tableData) {
			cliLog("updating table:".$className." with trackCount and albumCount", 3);
			foreach($tableData as $itemUid => $data) {
				$classPath = "\\Slimpd\\Models\\" . $className;
				$item = new $classPath();
				$item->setUid($itemUid)
					->setTrackCount( count(@$data['tracks']) )
					->setAlbumCount( count(@$data['albums']) );

				$this->setTopArtistUids($item, $className, $data);
				$this->setTopGenreUids($item, $className, $data);
				$this->setTopLabelUids($item, $className, $data);
				$this->setYearRange($item, $data);
				
				$repoKey = $item::$repoKey;
				$this->container->$repoKey->update($item);
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

	private function setTopArtistUids(&$item, $className, $data) {
		if($className === "Artist" || isset($data["artists"]) === FALSE) {
			return;
		}
		$max = 2;
		$found = 0;
		$finalUids = "";
		$artistUids = uniqueArrayOrderedByRelevance($data['artists']);
		foreach($artistUids as $artistUid) {
			if($artistUid < 12) { // Unknown Artist=10, Various Artists=11
				continue;
			}
			$found++;
			$finalUids .= $artistUid . ",";
			if($found === $max) {
				break;
			}
		}
		
		$item->setTopArtistUids(trim($finalUids, ","));
	}

	private function setTopGenreUids(&$item, $className, $data) {
		if($className === "Genre" || isset($data["genres"]) === FALSE) {
			return;
		}
		$max = 2;
		$found = 0;
		$finalUids = "";
		$genreUids = uniqueArrayOrderedByRelevance($data['genres']);
		foreach($genreUids as $genreUid) {
			if($genreUid < 11) { // Unknown Genre = 10
				continue;
			}
			$found++;
			$finalUids .= $genreUid . ",";
			if($found === $max) {
				break;
			}
		}
		$item->setTopGenreUids(trim($finalUids, ","));
	}

	private function setTopLabelUids(&$item, $className, $data) {
		if($className === "Label" || isset($data["labels"]) === FALSE) {
			return;
		}
		$max = 2;
		$found = 0;
		$finalUids = "";
		$labelUids = uniqueArrayOrderedByRelevance($data['labels']);
		foreach($labelUids as $labelUid) {
			if($labelUid < 11) { // Unknown Label = 10
				continue;
			}
			$found++;
			$finalUids .= $labelUid . ",";
			if($found === $max) {
				break;
			}
		}
		$item->setTopLabelUids(trim($finalUids, ","));
	}

	private function setYearRange(&$item, $data) {
		if(isset($data['years']) === FALSE) {
			return;
		}
		$finalYears = [];
		foreach(array_keys($data['years']) as $year) {
			if(\Slimpd\Utilities\RegexHelper::seemsYeary($year) === FALSE) {
				continue;
			}
			$finalYears[] = $year;
		}
		
		if(count($finalYears) === 0) {
			return;
		}
		$min = min($finalYears);
		$max = max($finalYears);
		$yearString = ($min === $max) ? $min : $min . "-".$max;
		$item->setYearRange($yearString);
	}

	/**
	 * delete all items which does not have any trackCount or albumCount
	 * but preserve default entries
	 */
	private function deleteOrphans() {
		$tables = [
			// tablename => highestDefaultUid
			'artist' => 11,	// Unknown artist=10, various artists=11
			'label' => 10,  // Unknown label
			'genre' => 10, // Unknown genre
		];
		foreach($tables as $tableName => $defaultUid) {
			$query = "DELETE FROM " . $tableName . " WHERE trackCount=0 AND albumCount=0 AND uid>" . $defaultUid;
			cliLog("deleting ".$tableName."s  with trackCount=0 AND albumCount=0", 2, "yellow");
			$this->db->query($query);
		}
	}
}
