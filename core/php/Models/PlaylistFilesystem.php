<?php
namespace Slimpd\Models;
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
class PlaylistFilesystem extends \Slimpd\Models\AbstractFilesystemItem {
	protected $errorPath = TRUE; // TODO: consider to remove and use $error instead
	protected $title;
	protected $ext;
	protected $length;
	protected $itemPaths = [];	// pathstrings
	protected $tracks = [];		// track-instances
	private $fetchedLength = FALSE;

	public function __construct($relPath) {
		$app = \Slim\Slim::getInstance();
		if(isInAllowedPath($relPath) === TRUE) {
			$this->setRelPath($relPath);
			$this->setErrorPath(FALSE);
		}

		if($this->getErrorPath() === TRUE) {
			$app->flashNow('error', 'playlist file ' . $relPath . ' does not exist');
			return $this;
		}
		$this->setTitle(basename($this->getRelPath()));
		$this->setExt(getFileExt($this->getRelPath()));
	}

	public function fetchTrackRange($minIndex, $maxIndex, $pathOnly = FALSE) {
		$app = \Slim\Slim::getInstance();
		$raw = file_get_contents(getFileRealPath($this->relPath));
		switch($this->getExt()) {
			case 'm3u':
			case 'pls':
			case 'txt':
				$this->parsePlaintext($raw, $minIndex, $maxIndex);
				break;
			case 'nml':
				$this->parseNml($raw, $minIndex, $maxIndex);
				break;
			default :
				$app->flashNow('error', 'playlist extension ' . $this->getExt() . ' is not supported');
				return;
		}
		$this->fetchedLength === TRUE;

		if($pathOnly === FALSE) {
			$this->tracks = self::pathStringsToTrackInstancesArray($this->itemPaths);
			return;
		}
		$this->tracks = self::pathStringsToTrackInstancesArray($this->itemPaths, TRUE);
	}

	public static function pathStringsToTrackInstancesArray($pathStringArray, $noDatabaseQueries = FALSE) {
		$return = array();
		foreach($pathStringArray as $itemPath) {
			// increase performance by avoiding any database queries when adding tenthousands of tracks to mpd-playlist
			$track = ($noDatabaseQueries === FALSE)
				? \Slimpd\Models\Track::getInstanceByPath($itemPath, TRUE)
				: \Slimpd\Models\Track::getNewInstanceWithoutDbQueries(trimAltMusicDirPrefix($itemPath));

			if(getFileRealPath($track->getRelPath()) === FALSE) {
				$track->setError('notfound');
			}
			$return[] = $track;
		}
		return $return;
	}

	private function parsePlaintext($rawFileContent, $minIndex, $maxIndex) {
		// windows generated playlists are not supported yet
		$playlistContent = str_replace("\\", "/", $rawFileContent);
		$playlistContent = trimExplode("\n", $playlistContent, TRUE);
		$this->setLength(count($playlistContent));
		foreach($playlistContent as $idx => $itemPath) {
			if($idx < $minIndex || $idx >= $maxIndex) {
				continue;
			}
			$this->itemPaths[] = $itemPath;
		}
	}

	private function parseNml($rawFileContent, $minIndex, $maxIndex) {
		if(isValidXml($rawFileContent) === FALSE) {
			\Slim\Slim::getInstance()->flashNow('error', 'invalid XML ' . $this->getTitle());
			return;
		}
		$playlistContent = new \SimpleXMLElement($rawFileContent);
		$trackEntries = $playlistContent->xpath("//PLAYLIST/ENTRY/LOCATION");
		$this->setLength(count($trackEntries));
		foreach($trackEntries as $idx => $trackEntry) {
			if($idx < $minIndex || $idx >= $maxIndex) {
				continue;
			}
			$this->itemPaths[] = $trackEntry->attributes()->DIR->__toString() . $trackEntry->attributes()->FILE->__toString();
		}
	}

	public function setErrorPath($errorPath) {
		$this->errorPath = $errorPath;
		return $this;
	}

	public function getErrorPath() {
		return $this->errorPath;
	}

	public function setTitle($value) {
		$this->title = $value;
		return $this;
	}

	public function getTitle() {
		return $this->title;
	}

	public function setExt($ext) {
		$this->ext = $ext;
		return $this;
	}

	public function getExt() {
		return $this->ext;
	}

	public function setLength($length) {
		$this->length = $length;
		return $this;
	}

	public function getLength() {
		if($this->fetchedLength === FALSE) {
			// we have to process to get the total length
			$this->fetchTrackRange(0,1, TRUE);
			$this->tracks = [];
			$this->itemPaths = [];
		}
		return $this->length;
	}

	public function appendTrack(\Slimpd\Models\Track $track) {
		$this->tracks[] = $track;
		return $this;
	}

	public function getTracks() {
		return $this->tracks;
	}
}