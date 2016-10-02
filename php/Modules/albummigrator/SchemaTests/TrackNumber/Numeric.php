<?php
namespace Slimpd\Modules\albummigrator\SchemaTests\TrackNumber;
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

class Numeric extends \Slimpd\Modules\albummigrator\AbstractTests\AbstractTest {
	public $isAlbumWeight = 0.9;

	public function run() {
		if(is_numeric($this->input)) {
			$this->result = 'numeric';	// 1, 01, 22
			$this->matches = intval($this->input);
			return;
		}
		$this->result = 0;
	}

	public function scoreMatches() {
		if(count($this->matches) === 0) {
			return;
		}
		$this->trackContext->recommend([
			'setTrackNumber' => $this->matches[0]
		]);
	}
}
