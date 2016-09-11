<?php
namespace Slimpd\Models;
/* Copyright
 *
 */
class File extends \Slimpd\Models\AbstractFilesystemItem {
	protected $exists = FALSE;
	protected $ext = '';
	public function __construct($relPath) {
		try {
			$this->relPath = $relPath;
			$this->title = basename($relPath);
			// TODO: move ext to string functions
			$this->ext = strtolower(preg_replace('/^.*\./', '', $relPath));
			$this->relPathHash = getFilePathHash($relPath);
		} catch (Exception $e) {}
	}

	public function validate() {
		$app = \Slim\Slim::getInstance();
		$realPath = getFileRealPath($this->getRelPath());
		if(isInAllowedPath($this->relPath) === FALSE || $realPath === FALSE) {
			return FALSE;
		}

		// check if it is really a file because getFileRealPath() also works for directories
		if(is_file($realPath) === FALSE) {
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
	}

	public function getBreadcrumb() {
		return $this->breadcrumb;
	}

	public function setBreadcrumb($value) {
		$this->breadcrumb = $value;
	}

	public function getExt() {
		return $this->ext;
	}

	public function setExt($value) {
		$this->ext = $value;
	}
}
