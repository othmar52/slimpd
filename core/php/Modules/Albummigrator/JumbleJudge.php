<?php
namespace Slimpd\Modules\Albummigrator;
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

/*
 * JumbleJudge collects a lot of characteristics for each "album-track"
 * based on those differences JumbleJudge decides if track-bunch should
 * be treated as an album or a bunch of loose tracks(jumble) 
 */

class JumbleJudge {

	private $isAlbumTreshold = 0.7;
	public $handleAsAlbum;
	public $tests;
	public $testResults;
	private $trackContext;
	private $albumContext;
	public $albumMigrator;

	public function __construct(\Slimpd\Modules\Albummigrator\AlbumContext &$albumContextItem, &$albumMigrator) {
		$this->albumContext = $albumContextItem;
		$this->albumMigrator = $albumMigrator;
	}

	public function collect(\Slimpd\Modules\Albummigrator\TrackContext &$trackContext) {
		$this->trackContext = $trackContext;
		$fileName = unifyHyphens(unifyBraces(basename($trackContext->getRelPath())));
		$this->runTest("SchemaTests\\Filename\\HasYear", $fileName)
			->runTest("CaseSensitivityTests\\Filename", $fileName)
			->runTest("SchemaTests\\Filename\\NumberArtistTitleExt", $fileName)
			->runTest("SchemaTests\\Filename\\NumberTitleArtistExt", $fileName)
			->runTest("SchemaTests\\Filename\\NumberArtistTitleSceneExt", $fileName)
			->runTest("SchemaTests\\Filename\\VinylArtistTitleExt", $fileName)
			->runTest("SchemaTests\\Filename\\VinylArtistTitleSceneExt", $fileName)
			->runTest("SchemaTests\\Filename\\ArtistTitleExt", $fileName)
			->runTest("SchemaTests\\Filename\\NumberTitleExt", $fileName)
			->runTest("SchemaTests\\Filename\\BeginsWithVinyl", $fileName)
			->runTest("SchemaTests\\Filename\\BeginsWithNumber", $fileName)
			->runTest("SchemaTests\\Filename\\HasSceneSuffix", $fileName)
			->runTest("SchemaTests\\Filename\\ArtistAlbumYearNumberTitleExt", $fileName)
			->runTest("EqualTagTests\\Artist", $trackContext->getArtist())
			->runTest("EqualTagTests\\Genre", $trackContext->getGenre())
			->runTest("EqualTagTests\\Album", $trackContext->getAlbum())
			->runTest("EqualTagTests\\Year", $trackContext->getYear())
			->runTest("EqualTagTests\\Label", $trackContext->getLabel())
			->runTest("SchemaTests\\Artist\\NumberArtist", $trackContext->getArtist())
			->runTest("SchemaTests\\Artist\\VinylArtist", $trackContext->getArtist())
			->runTest("SchemaTests\\Artist\\NumberArtistTitle", $trackContext->getArtist())
			->runTest("SchemaTests\\Artist\\VinylArtistTitle", $trackContext->getArtist())
			->runTest("SchemaTests\\Artist\\HasSuffixedUrl", $trackContext->getArtist())
			->runTest("SchemaTests\\Title\\ArtistTitle", $trackContext->getTitle())
			->runTest("SchemaTests\\TrackNumber\\Numeric", $trackContext->getTrackNumber())
			->runTest("SchemaTests\\TrackNumber\\Vinyl", $trackContext->getTrackNumber())
			->runTest("SchemaTests\\TrackNumber\\LeadingZero", $trackContext->getTrackNumber())
			->runTest("SchemaTests\\TrackNumber\\CombinedWithTotal", $trackContext->getTrackNumber())
			->runTest("SchemaTests\\Album\\HasCatalogNr", $trackContext->getAlbum());
	}

	private function runTest($className, $input) {
		$classPath = "\\Slimpd\\Modules\\Albummigrator\\" . $className;
		$test = new $classPath($input, $this->trackContext, $this->albumContext, $this);
		$test->run();
		$this->tests[$className][] = $test;
		return $this;
	}

	public function judge() {
		// get decisions for each single test
		foreach($this->tests as $testname => $tests) {
			$result = array();
			foreach($tests as $test) {
				$result[] = $test->result;
			}

			// get the most occuring testresult
			$relevant = uniqueArrayOrderedByRelevance($result);
			$mostSimilarity = array_shift($relevant);
			
			// count items which has this most occuring result
			$counter = 0;
			foreach($tests as $test) {
				$result[] = $test->result;
				if($test->result === $mostSimilarity) {
					$counter++;
				}
			}
			// store result as number in property
			$this->testResults[$testname] = ($counter*$tests[0]->isAlbumWeight)/count($tests);
		}
		// use each single test result to get the final decision;
		$finalValue = array_sum($this->testResults)/count($this->testResults);
		#var_dump($finalValue); die;
		$this->handleAsAlbum = ($finalValue < $this->isAlbumTreshold) ? 1 : 0;
		//var_dump($this->handleAsAlbum); die;
	}
}
