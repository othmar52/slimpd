<?php
namespace Slimpd\Models;
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
abstract class AbstractFilesystemItem extends \Slimpd\Models\AbstractModel {

	protected $relPath;
	protected $relPathHash;
	protected $relDirPath;
	protected $relDirPathHash;
	protected $filesize;
	protected $filemtime = 0;
	protected $importStatus;

	public function getRelPath() {
		return $this->relPath;
	}
	public function getRelPathHash() {
		return $this->relPathHash;
	}
	public function getRelDirPath() {
		return $this->relDirPath;
	}
	public function getRelDirPathHash() {
		return $this->relDirPathHash;
	}
	public function getFilesize() {
		return $this->filesize;
	}
	public function getFilemtime() {
		return $this->filemtime;
	}
	public function getImportStatus() {
		return $this->importStatus;
	}
	
	public function setRelPath($value) {
		$this->relPath = $value;
		return $this;
	}
	public function setRelPathHash($value) {
		$this->relPathHash = $value;
		return $this;
	}
	public function setRelDirPath($value) {
		$this->relDirPath = $value;
		return $this;
	}
	public function setRelDirPathHash($value) {
		$this->relDirPathHash = $value;
		return $this;
	}
	public function setFilesize($value) {
		$this->filesize = $value;
		return $this;
	}
	public function setFilemtime($value) {
		$this->filemtime = $value;
		return $this;
	}
	public function setImportStatus($value) {
		$this->importStatus = $value;
		return $this;
	}
}
