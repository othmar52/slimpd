<?php
namespace Slimpd\Models;
/* Copyright
 *
 */
class Directory extends \Slimpd\Models\AbstractFilesystemItem {
	protected $exists = FALSE;
	protected $breadcrumb;
	public function __construct($dirPath) {
		try {
			$this->relPath = $dirPath;
			$this->title = basename($dirPath);
			$this->relPathHash = getFilePathHash($this->relPath);
		} catch (Exception $e) {}
	}

	public function validate() {
		$realPath = getFileRealPath($this->getRelPath());
		if(isInAllowedPath($this->getRelPath()) === FALSE || $realPath === FALSE) {
			return FALSE;
		}

		// check if it is really a directory because getFileRealPath() also works for files
		if(is_dir($realPath) === FALSE) {
			return FALSE;
		}
		$this->setExists(TRUE);
		return TRUE;
	}

	public function getExists() {
		return $this->exists;
	}

	public function setExists($value) {
		$this->exists = $value;
		return $this;
	}

	public function getBreadcrumb() {
		return $this->breadcrumb;
	}

	public function setBreadcrumb($value) {
		$this->breadcrumb = $value;
		return $this;
	}
}
