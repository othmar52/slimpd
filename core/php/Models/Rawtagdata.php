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

class Rawtagdata extends \Slimpd\Models\AbstractFilesystemItem {
	use \Slimpd\Traits\PropertyLastScan;
	use \Slimpd\Traits\PropertyFingerprint;

	protected $directoryMtime = 0;
	protected $added;
	protected $extension;
	protected $lastDirScan;
	protected $error;	// TODO: move to Trait

	public static $tableName = 'rawtagdata';
	public static $repoKey = 'rawtagdataRepo';


	//setter
	public function setDirectoryMtime($value) {
		$this->directoryMtime = $value;
		return $this;
	}
	public function setAdded($value) {
		$this->added = $value;
		return $this;
	}
	public function setExtension($value) {
		$this->extension = $value;
		return $this;
	}
	public function setLastDirScan($value) {
		$this->lastDirScan = $value;
		return $this;
	}
	public function setError($value) {
		$this->error = $value;
		return $this;
	}


	// getter
	public function getDirectoryMtime() {
		return $this->directoryMtime;
	}
	public function getAdded() {
		return $this->added;
	}
	public function getExtension() {
		return $this->extension;
	}
	public function getLastDirScan() {
		return $this->lastDirScan;
	}
	public function getError() {
		return $this->error;
	}
}
