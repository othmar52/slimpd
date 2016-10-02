<?php
namespace Slimpd\Modules\albummigrator;
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
	
	// those attributes are used for "handle all directory tracks as album"
	/*
	protected $fileNameCase;
	protected $filenameSchema;
	protected $titleSchema;
	protected $artistSchema;
	protected $albumSchema;
	protected $numberSchema;
	*/
	
	private $isAlbumTreshold = 0.8;
	public $handleAsAlbum;
	public $tests;
	public $testResults;
	
	
	public function collect(\Slimpd\Modules\albummigrator\TrackContext &$trackContext) {
		$fileName = basename($trackContext->getRelPath());
		$test = new \Slimpd\Modules\albummigrator\CaseSensitivityTests\Filename($fileName);
		$test->run();
		$this->tests["FilenameCase"][] = $test;
		
		$test = new \Slimpd\Modules\albummigrator\SchemaTests\Filename\NumberArtistTitleExt($fileName);
		$test->run();
		$this->tests["FilenameNumberArtistTitleExt"][] = $test;
		
		$test = new \Slimpd\Modules\albummigrator\SchemaTests\Filename\VinylArtistTitleExt($fileName);
		$test->run();
		$this->tests["FilenameVinylArtistTitleExt"][] = $test;
		
		$test = new \Slimpd\Modules\albummigrator\SchemaTests\Filename\HasYear($fileName);
		$test->run();
		$this->tests["FilenameHasYear"][] = $test;

		$test = new \Slimpd\Modules\albummigrator\EqualTagTests\Artist($trackContext->getArtist());
		$test->run();
		$this->tests["EqualTagArtist"][] = $test;
		
		
		
		$test = new \Slimpd\Modules\albummigrator\EqualTagTests\Album($trackContext->getAlbum());
		$test->run();
		$this->tests["EqualTagAlbum"][] = $test;
		
		$test = new \Slimpd\Modules\albummigrator\EqualTagTests\Year($trackContext->getYear());
		$test->run();
		$this->tests["EqualTagYear"][] = $test;
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
		$this->handleAsAlbum = ($finalValue < $this->isAlbumTreshold) ? 1 : 0;
		//var_dump($this->handleAsAlbum); die;
	}
}
