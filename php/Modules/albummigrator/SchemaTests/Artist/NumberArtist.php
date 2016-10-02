<?php
namespace Slimpd\Modules\albummigrator\SchemaTests\Artist;
use Slimpd\RegexHelper as RGX;
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
 * Number and Artist will be extracted from inputs like
 * pattern: 01 Juno Reactor
 * pattern: 01. Juno Reactor
 * pattern: [01] Juno Reactor
 * pattern: [01] - Juno Reactor
 * pattern: [01]. Juno Reactor
 */
class NumberArtist extends \Slimpd\Modules\albummigrator\AbstractTests\AbstractTest {
	public $isAlbumWeight = 0.8;
	
	public function __construct($input) {
		$this->input = $input;
		$this->pattern = "/^" . RGX::MAY_BRACKET . RGX::NUM . RGX::MAY_BRACKET. RGX::GLUE . RGX::NO_MINUS . "$/i";
		return $this;
	}
	
	public function run() {
		if(preg_match($this->pattern, $this->input, $matches)) {
			$this->matches = $matches;
			$this->result = 'number-artist';
			return;
		}
		$this->result = 0;
	}
	
	public function scoreMatches(&$trackContext, &$albumContext, $jumbleJudge) {
		
		if(count($this->matches) === 0) {
			return;
		}
		$trackContext->recommend([
			'setTrackNumber' => $this->matches[1],
			'setArtist' => $this->matches[2]
		]);
		$albumContext->recommend([
			'setArtist' => $this->matches[2]
		]);
	}
}
