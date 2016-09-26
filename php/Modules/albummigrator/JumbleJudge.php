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
  * based on those differences JumbeJudge decides if track-bunch should
  * be treated as an album or a bunch of loose tracks(jumble) 
  * 
  * JumbleJudge's pattern-discoverer also does some scoring for guessing track/album properties 
  */
class JumbleJudge {
	
	// those attributes are used for "handle all directory tracks as album"
	protected $fileNameCase;
	protected $filenameSchema;
	protected $titleSchema;
	protected $artistSchema;
	protected $albumSchema;
	protected $numberSchema;
	
	
	
	public $tests;
	public $testResults;
	
	
	public function collect(\Slimpd\Modules\albummigrator\TrackContext &$trackContext) {
		$test = new \Slimpd\Modules\albummigrator\SchemaTests\FilenameCase(basename($trackContext->getRelPath()));
		$test->run();
		$this->tests["FilenameCase"][] = $test;
		
		$test = new \Slimpd\Modules\albummigrator\SchemaTests\FilenameSchema1(basename($trackContext->getRelPath()));
		$test->run();
		$this->tests["FilenameSchema1"][] = $test;
		
		
		#$this->getFilenameSchema1() , $trackContext);
	}
	
	public function judge() {
		foreach($this->tests as $tests) {
			foreach($tests as $test) {
				var_dump($zesz->result);
			}
		}
	}
	

}

