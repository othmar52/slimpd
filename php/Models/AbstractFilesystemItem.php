<?php
namespace Slimpd\Models;
/* Copyright
 *
 */
abstract class AbstractFilesystemItem extends \Slimpd\Models\AbstractModel {

	protected $relPath;
	protected $relPathHash;
	protected $relDirPath;
	protected $relDirPathHash;
	protected $filesize;
	protected $filemtime = 0;

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
}
