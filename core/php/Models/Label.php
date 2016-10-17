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
class Label extends \Slimpd\Models\AbstractModel {
	use \Slimpd\Traits\PropGroupCounters; // trackCount, albumCount
	use \Slimpd\Traits\PropertyTopArtist; // topArtistUids
	use \Slimpd\Traits\PropertyTopGenre; // topGenreUids
	use \Slimpd\Traits\PropertyYearRange; // yearRange

	protected $title;
	protected $az09;

	public static $tableName = 'label';
	public static $repoKey = 'labelRepo';

	//setter
	public function setTitle($value) {
		$this->title = $value;
		return $this;
	}
	public function setAz09($value) {
		$this->az09 = $value;
		return $this;
	}


	// getter
	public function getTitle() {
		return $this->title;
	}
	public function getAz09() {
		return $this->az09;
	}
}
