<?php
namespace Slimpd\Modules\Albummigrator\SchemaTests\TrackNumber;
use Slimpd\Utilities\RegexHelper as RGX;
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

class LeadingZero extends \Slimpd\Modules\Albummigrator\AbstractTests\AbstractTest {
	// TODO: this test does return misleading result in case we have more than 9 tracks ...09, 10, 11,...
	// for now keep isAlbumWeight low
	public $isAlbumWeight = 0.5;

	public function run() {
		if(removeLeadingZeroes($this->input) !== strval($this->input)) {
			$this->result = 'leadingzero';	// 01, 02, 001, 002
			$this->matches = intval($this->input);
			return;
		}
		$this->result = 0;
	}

	public function scoreMatches() {
		cliLog(get_called_class(),10, "purple"); cliLog("  INPUT: " . $this->input, 10);
		if(count($this->matches) === 0) {
			cliLog("  no matches\n ", 10);
			return;
		}

		$this->trackContext->recommend([
			'setTrackNumber' => $this->matches[0]
		]);
	}
}
