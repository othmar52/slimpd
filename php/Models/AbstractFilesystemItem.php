<?php
namespace Slimpd\Models;

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
	}
	public function setRelPathHash($value) {
		$this->relPathHash = $value;
	}
	public function setRelDirPath($value) {
		$this->relDirPath = $value;
	}
	public function setRelDirPathHash($value) {
		$this->relDirPathHash = $value;
	}
	public function setFilesize($value) {
		$this->filesize = $value;
	}
	public function setFilemtime($value) {
		$this->filemtime = $value;
	}
}

