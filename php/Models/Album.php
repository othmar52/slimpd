<?php
namespace Slimpd\Models;
/* Copyright (C) 2015-2016 othmar52 <othmar52@users.noreply.github.com>
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
class Album extends \Slimpd\Models\AbstractFilesystemItem {
	use \Slimpd\Traits\PropertyLastScan; // lastScan
	use \Slimpd\Traits\PropGroupTypeIds; // artistUid, labelUid, genreUid

	
	protected $title;
	protected $year;
	protected $month;
	
	protected $catalogNr;

	protected $added;
	protected $discs;

	protected $albumDr;
	protected $trackCount;

	protected $isMixed;
	protected $isJumble;
	protected $isLive;

	protected $discogsId;
	protected $rolldabeatsId;
	protected $beatportId;
	protected $junoId;

	public static $tableName = 'album';

	public function getAlbumByRelPath($relPath) {
		$app = \Slim\Slim::getInstance();
		$query = "
			SELECT * 
			FROM album
			WHERE relPathHash=\"" . getFilePathHash($relPath) . "\"";
		$result = $app->db->query($query);
		$record = $result->fetch_assoc();
		if($record === NULL) {
			return NULL;
		}
		$this->mapArrayToInstance($record);
	}

	//setter
	public function setTitle($value) {
		$this->title = $value;
		return $this;
	}
	public function setYear($value) {
		$this->year = $value;
		return $this;
	}
	public function setMonth($value) {
		$this->month = $value;
		return $this;
	}
	public function setCatalogNr($value) {
		$this->catalogNr = $value;
		return $this;
	}
	public function setAdded($value) {
		$this->added = $value;
		return $this;
	}
	public function setDiscs($value) {
		$this->discs = $value;
		return $this;
	}
	public function setAlbumDr($value) {
		$this->albumDr = $value;
		return $this;
	}
	public function setTrackCount($value) {
		$this->trackCount = $value;
		return $this;
	}


	public function setIsMixed($value) {
		$this->isMixed = $value;
		return $this;
	}
	public function setIsJumble($value) {
		$this->isJumble = $value;
		return $this;
	}
	public function setIsLive($value) {
		$this->isLive = $value;
		return $this;
	}


	public function setDiscogsId($value) {
		$this->discogsId = $value;
		return $this;
	}
	public function setRolldabeatsId($value) {
		$this->rolldabeatsId = $value;
		return $this;
	}
	public function setBeatportId($value) {
		$this->beatportId = $value;
		return $this;
	}
	public function setJunoId($value) {
		$this->junoId = $value;
		return $this;
	}



	// getter

	public function getTitle() {
		return $this->title;
	}
	public function getYear() {
		return $this->year;
	}
	public function getMonth() {
		return $this->month;
	}
	public function getCatalogNr() {
		return $this->catalogNr;
	}
	public function getAdded() {
		return $this->added;
	}
	public function getDiscs() {
		return $this->discs;
	}
	public function getAlbumDr() {
		return $this->albumDr;
	}
	public function getTrackCount() {
		return $this->trackCount;
	}

	public function getIsMixed() {
		return $this->isMixed;
	}
	public function getIsJumble() {
		return $this->isJumble;
	}
	public function getIsLive() {
		return $this->isLive;
	}

	public function getDiscogsId() {
		return $this->discogsId;
	}
	public function getRolldabeatsId() {
		return $this->rolldabeatsId;
	}
	public function getBeatportId() {
		return $this->beatportId;
	}
	public function getJunoId() {
		return $this->junoId;
	}
}
