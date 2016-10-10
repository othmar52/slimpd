<?php
namespace Slimpd\Modules\albummigrator;
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

class AlbumMigrator {

	use \Slimpd\Traits\PropGroupRelPath; // relPath, relPathHash
	public $conf;
	protected $rawTagItems;
	protected $trackContextItems;
	protected $albumContextItem;
	protected $jumbleJudge;
	protected $mostRecentAdded;

	public function run() {
		// create albumContext
		$this->albumContextItem = new \Slimpd\Modules\albummigrator\AlbumContext();
		$this->jumbleJudge = new \Slimpd\Modules\albummigrator\JumbleJudge($this->albumContextItem, $this);

		// create TrackContext for each input item
		foreach($this->rawTagItems as $idx => $rawTagItem) {
			$this->trackContextItems[$idx] = new \Slimpd\Modules\albummigrator\TrackContext($rawTagItem, $idx, $this->conf);
			// do some characteristics analysis for each "track"
			$this->jumbleJudge->collect($this->trackContextItems[$idx], $this->albumContextItem);
			$this->handleTrackFilemtime($rawTagItem["added"]);
		}
		// decide if bunch should be treated as album or as loose tracks
		$this->jumbleJudge->judge();
		
		if(\Slim\Slim::getInstance()->config["modules"]["enable_guessing"] == "1") {
			// do some voting for each attribute
			$this->runAttributeScoring();
		}
		
		// 
		// direcory path is the same for all tracks. copy from first rawTagItem
		$this->albumContextItem->copyBaseProperties($this->rawTagItems[0]);
		$this->albumContextItem->collectAlbumStuff($this, $this->jumbleJudge);
		
		$this->postProcessTrackProperties();
		
		#if($this->getRelDirPath() === "502_recordings/502003--Teeth-Shawty-(502003)-WEB-2011/") {
		#if($this->getRelDirPath() === "slimpd2/Q4_2015/francois_cousineau-l_initiation_(1970)/") {
		#if($this->getRelDirPath() === "1980-Rote_Lichter-Macht_Mich_Glucklich_Wie_Nie/") {
			#print_r($this->albumContextItem->recommendations);die;
			#print_r($this->trackContextItems[0]->recommendations);die;
		#}
		$this->albumContextItem->setAdded($this->mostRecentAdded)->migrate($this->trackContextItems, $this->jumbleJudge);
		
		
		#print_r($this->jumbleJudge->testResults); die;
		
		foreach($this->trackContextItems as $trackContextItem) {
			$trackContextItem->setAlbumUid($this->albumContextItem->getUid());
			$trackContextItem->migrate();
		}
		
		// complete embedded bitmaps with albumUid
		// to make sure extracted images will be referenced to an album
		\Slimpd\Models\Bitmap::addAlbumUidToRelDirPathHash($this->getRelDirPathHash(), $this->albumContextItem->getUid());
		
		
		#var_dump($this);
		#die('blaaaaa');
	}

	public function handleTrackFilemtime($trackFilemtime) {
		$this->mostRecentAdded = ($trackFilemtime > $this->mostRecentAdded)
			? $trackFilemtime
			: $this->mostRecentAdded;
	}

	public static function parseConfig() {
		return parse_ini_file(APP_ROOT . "config/importer/tag-mapper.ini", TRUE);
	}

	public function addTrack(array $rawTagDataArray) {
		$this->rawTagItems[] = $rawTagDataArray;
		return $this;
	}

	public function getTrackCount() {
		return count($this->rawTagItems);
	}

	private function runAttributeScoring() {
		foreach($this->trackContextItems as $trackContextItem) {
			$trackContextItem->initScorer($this->albumContextItem, $this->jumbleJudge);
			#$trackContextItem->postProcessProperties();
		}
	}


	private function postProcessTrackProperties() {
		foreach($this->trackContextItems as $trackContextItem) {
			#$trackContextItem->initScorer($this->albumContextItem, $this->jumbleJudge);
			$trackContextItem->postProcessProperties();
		}
	}

	public function recommendationForAllTracks(array $recommendations) {
		#print_r($recommendations); die;
		foreach($this->trackContextItems as $trackContextItem) {
			$trackContextItem->recommend($recommendations);
		}
	}

	// TODO: get this from Trait
	protected $directoryMtime;
	public function getDirectoryMtime() {
		return $this->directoryMtime;
	}
	public function setDirectoryMtime($value) {
		$this->directoryMtime = $value;
		return $this;
	}
	

	protected $relDirPath;
	protected $relDirPathHash;
	protected $filesize;
	protected $filemtime = 0;

	// todo: do we really need AlbumMigrator->importStatus property???
	protected $importStatus;


	public function getRelDirPath() {
		return $this->relDirPath;
	}
	public function getRelDirPathHash() {
		return $this->relDirPathHash;
	}
	public function getFilesize() {
		return $this->filesize;
	}
	public function getFilemtime() {
		return $this->filemtime;
	}
	public function getImportStatus() {
		return $this->importStatus;
	}


	public function setRelDirPath($value) {
		$this->relDirPath = $value;
		return $this;
	}
	public function setRelDirPathHash($value) {
		$this->relDirPathHash = $value;
		return $this;
	}
	public function setFilesize($value) {
		$this->filesize = $value;
		return $this;
	}
	public function setFilemtime($value) {
		$this->filemtime = $value;
		return $this;
	}
	public function setImportStatus($value) {
		$this->importStatus = $value;
		return $this;
	}
}
