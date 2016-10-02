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

class AlbumContext extends \Slimpd\Models\Album {
	use \Slimpd\Modules\albummigrator\MigratorContext; // config
	protected $confKey = "album-tag-mapping-";
	protected $artist;
	protected $label;
	public $recommendations;

	public function getTagsFromTrack($rawTagArray, $config) {
		$this->rawTagRecord = $rawTagArray;
		$this->rawTagArray = unserialize($rawTagArray['tagData']);
		$this->config = $config;
		$this->configBasedSetters();
	}
	
	/**
	 * some rawTagData-fields are identical to album fields 
	 */
	public function copyBaseProperties($rawTagRecord) {
		$this->setRelPath($rawTagRecord['relDirPath'])
			->setRelPathHash($rawTagRecord['relDirPathHash'])
			->setFilemtime($rawTagRecord['directoryMtime'])
			//->setAdded($rawTagRecord['added'])
			//->setLastScan($rawTagRecord['lastDirScan'])
			;
	}
	
	public function collectAlbumStuff(&$albumMigrator, &$jumbleJudge) {
		// guess attributes by directory name
		$dirname = basename($this->getRelPath());
		$test = new \Slimpd\Modules\albummigrator\SchemaTests\Dirname\ArtistTitleYear($dirname);
		$test->run();
		$test->scoreMatches($dirname, $this, $jumbleJudge);
		
		$dirname = basename($this->getRelPath());
		$test = new \Slimpd\Modules\albummigrator\SchemaTests\Dirname\ArtistTitle($dirname);
		$test->run();
		$test->scoreMatches($dirname, $this, $jumbleJudge);
	}

	public function migrate($trackContextItems, $jumbleJudge) {
		$album = new \Slimpd\Models\Album();
		#var_dump($this->getMostScored("setArtist")); die;

		$album->setRelPath($this->getRelPath())
			->setRelPathHash($this->getRelPathHash())
			->setFilemtime($this->getFilemtime())
			->setIsJumble($jumbleJudge->handleAsAlbum)
			->setTitle($this->getMostScored("setTitle"))
			->setYear($this->getMostScored("setYear"))
			->setCatalogNr($this->getMostScored("setCatalogNr"))
			->setArtistUid(join(",", \Slimpd\Models\Artist::getUidsByString($this->getMostScored("setArtist"))))
			->setGenreUid(join(",", \Slimpd\Models\Genre::getUidsByString($this->getMostScored("setGenre"))))
			->setLabelUid(join(",", \Slimpd\Models\Label::getUidsByString($this->getMostScored("setLabel"))))
			/*->setCatalogNr($this->mostScored['album']['catalogNr'])
			->setAdded($this->mostRecentAdded)
			->setYear($this->mostScored['album']['year'])
			->setLabelUid(
				join(",", \Slimpd\Models\Label::getUidsByString(
					($album->getIsJumble() === 1)
						? $mergedFromTracks['label']			// all labels
						: $this->mostScored['album']['label']	// only 1 label
				))
			)*/
			->setTrackCount(count($trackContextItems))
			->update();

		$this->setUid($album->getUid());
	}


	// TODO: move to trait
	public function setArtist($value) {
		$this->artist = $value;
		return $this;
	}
	public function getArtist() {
		return $this->artist;
	}

	public function setLabel($value) {
		$this->label = $value;
		return $this;
	}
	public function getLabel() {
		return $this->label;
	}
}
