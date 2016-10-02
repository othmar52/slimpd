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
	public $conf;
	protected $rawTagItems;
	protected $trackContextItems;
	protected $albumContextItem;
	protected $jumbleJudge;
	
	public function run() {
		// create albumContext
		$this->albumContextItem = new \Slimpd\Modules\albummigrator\AlbumContext();
		$this->jumbleJudge = new \Slimpd\Modules\albummigrator\JumbleJudge();
		
		// create TrackContext for each input item
		foreach($this->rawTagItems as $idx => $rawTagItem) {
			$this->trackContextItems[$idx] = new \Slimpd\Modules\albummigrator\TrackContext($rawTagItem, $idx, $this->conf);
			// do some characteristics analysis for each "track"
			$this->jumbleJudge->collect($this->trackContextItems[$idx]);
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
		
		#print_r($this->albumContextItem->recommendations);die;
		#print_r($this->trackContextItems[0]->recommendations);die;
		$this->albumContextItem->migrate($this->trackContextItems, $this->jumbleJudge);
		
		
		#print_r($this->jumbleJudge->testResults); die;
		
		foreach($this->trackContextItems as $trackContextItem) {
			$trackContextItem->setAlbumUid($this->albumContextItem->getUid());
			$trackContextItem->migrate();
		}
		#var_dump($this);
		#die('blaaaaa');
	}
	
	public static function parseConfig() {
		return parse_ini_file(APP_ROOT . "config/importer/tag-mapper.ini", TRUE);
	}
	
	public function addTrack(array $rawTagDataArray) {
		$this->rawTagItems[] = $rawTagDataArray;
		return $this;
	}
	
	private function runAttributeScoring() {
		foreach($this->trackContextItems as $trackContextItem) {
			$trackContextItem->initScorer($this->albumContextItem, $this->jumbleJudge);
			$trackContextItem->postProcessProperties();
		}
	}
	
	public function getMergedFromTracks($setterName) {
		
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
	
	protected $relPath;
	protected $relPathHash;
	protected $relDirPath;
	protected $relDirPathHash;
	protected $filesize;
	protected $filemtime = 0;
	protected $importStatus;

	public function getRelPath() {
		return $this->relPath;
	}
	public function getRelPathHash() {
		return $this->relPathHash;
	}
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
	
	public function setRelPath($value) {
		$this->relPath = $value;
		return $this;
	}
	public function setRelPathHash($value) {
		$this->relPathHash = $value;
		return $this;
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
