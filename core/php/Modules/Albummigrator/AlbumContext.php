<?php
namespace Slimpd\Modules\Albummigrator;
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
	use \Slimpd\Modules\Albummigrator\MigratorContext; // config
	protected $confKey = "album-tag-mapping-";
	protected $jumbleJudge;
	
	public function __construct($container) {
		$this->container = $container;
		$this->db = $container->db;
		$this->ll = $container->ll;
		$this->conf = $container->conf;
	}

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
		$this->jumbleJudge = $jumbleJudge;
		$dirname = unifyHyphens(unifyBraces(basename($this->getRelPath())));
		// TODO: use parent dir in case dirname is "cd1", "cd01", ???

		// dirname as album name is better than nothing
		$this->recommend(["setTitle" => $dirname]);

		// guess attributes by directory name
		$this->runTest("SchemaTests\\Dirname\\ArtistTitleYear", $dirname)
			->runTest("SchemaTests\\Dirname\\ArtistTitle", $dirname)
			->runTest("SchemaTests\\Dirname\\ArtistYearTitle", $dirname)
			->runTest("SchemaTests\\Dirname\\ArtistTitleSourceYearScene", $dirname)
			->runTest("SchemaTests\\Dirname\\HasYear", $dirname)
			->runTest("SchemaTests\\Dirname\\HasCatalogNr", $dirname)
			->runTest("SchemaTests\\Dirname\\HasPrefixedCatalogNr", $dirname)
			;

		$this->scoreLabelByLabelDirectory($albumMigrator);
	}

	private function runTest($className, $input) {
		$classPath = "\\Slimpd\\Modules\\Albummigrator\\" . $className;
		// for now there is no need for this instance within the tests
		// but abstraction requires any kind of variable... 
		$dummyTrackContext = NULL;
		$test = new $classPath($input, $dummyTrackContext, $this, $this->jumbleJudge);
		$test->run();
		$test->scoreMatches();
		return $this;
	}

	public function migrate($trackContextItems, $jumbleJudge, $useBatcher) {
		$album = new \Slimpd\Models\Album();
		#var_dump($this->getMostScored("setArtist")); die;

		$album->setRelPath($this->getRelPath())
			->setRelPathHash($this->getRelPathHash())
			->setFilemtime($this->getFilemtime())
			->setAdded($this->getAdded())
			->setIsJumble($jumbleJudge->handleAsAlbum)
			->setTitle($this->getMostScored("setTitle"))
			->setYear($this->getMostScored("setYear"))
			->setCatalogNr($this->getMostScored("setCatalogNr"))
			->setArtistUid(join(",", $this->container->artistRepo->getUidsByString($this->getMostScored("setArtist"))))
			->setGenreUid(join(",", $this->container->genreRepo->getUidsByString($this->getMostScored("setGenre"))))
			->setLabelUid(join(",", $this->container->labelRepo->getUidsByString($this->getMostScored("setLabel"))))
			/*
			->setLabelUid(
				join(",", \Slimpd\Models\Label::getUidsByString(
					($album->getIsJumble() === 1)
						? $mergedFromTracks['label']			// all labels
						: $this->mostScored['album']['label']	// only 1 label
				))
			)*/
			->setTrackCount(count($trackContextItems));

		// TODO: extend batcher to handle non-inserted uid's
		// for now do not use batcher for album records
		
		if($useBatcher === TRUE) {
			$this->container->batcher->que($album);
		} else {
			$this->container->albumRepo->ensureRecordUidExists($album->getUid());
			$this->container->albumRepo->update($album);
		}
		$this->setUid($album->getUid())->updateAlbumIndex($useBatcher);
	}

	private function updateAlbumIndex($useBatcher) {
		$indexChunks = $this->getRelPath() . " " .
			str_replace(
				array('/', '_', '-', '.'),
				' ',
				$this->getRelPath()
			)
			. " " . join(" ", $this->getAllRecommendations("setArtist"))
			. " " . join(" ", $this->getAllRecommendations("setTitle"))
			. " " . join(" ", $this->getAllRecommendations("setYear"))
			. " " . join(" ", $this->getAllRecommendations("setGenre"))
			. " " . join(" ", $this->getAllRecommendations("setLabel"))
			. " " . join(" ", $this->getAllRecommendations("setCatalogNr"));

		// make sure to use identical uids in table:trackindex and table:track
		$albumIndex = new \Slimpd\Models\Albumindex();
		$albumIndex->setUid($this->getUid())
			->setArtist($this->getMostScored("setArtist"))
			->setTitle($this->getMostScored("setTitle"))
			->setAllchunks($indexChunks);

		if($useBatcher === TRUE) {
			$this->container->batcher->que($albumIndex);
			return;
		}
		$this->container->albumindexRepo->ensureRecordUidExists($this->getUid());
		$this->container->albumindexRepo->update($albumIndex);
	}

	private function scoreLabelByLabelDirectory(&$albumMigrator) {
		cliLog("--- add LABEL based on directory ---", 8);
		cliLog("  album directory: " . $this->getRelPath(), 8);

		// check config
		if(isset($this->conf['label-parent-directories']) === FALSE) {
			cliLog("  aborting because no label directories configured",8);
			return;
		}

		foreach($this->conf['label-parent-directories'] as $labelDir) {
			$labelDir = appendTrailingSlash($labelDir);
			cliLog("  configured label dir: " . $labelDir, 10);
			if(stripos($this->getRelPath(), $labelDir) !== 0) {
				cliLog("  no match: " . $labelDir, 8);
				continue;
			}
			// use directory name as label name
			$newLabelString = basename(dirname($this->getRelPath()));

			// do some cleanup
			$newLabelString = ucwords(remU($newLabelString));
			cliLog("  match: " . $newLabelString, 8);

			$this->recommend(['setLabel'=> $newLabelString]);
			#var_dump($newLabelString);die;
			$albumMigrator->recommendationForAllTracks(
				['setLabel'=> $newLabelString]
			);
			return;
		}
		return;
	}
}
