<?php
namespace Slimpd\Models;

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
		$app = \Slim\Slim::getInstance();

		// avoid path disclosure outside allowed directories
		$base = $app->config['mpd']['musicdir'];
		// special handling for root directory
		$relPath = ($this->relPath === $base) ? '' : $this->relPath;
		$realpath = realpath(rtrim($base .$relPath, DS));
		if(stripos($realpath, $app->config['mpd']['musicdir']) !== 0
		&& stripos($realpath, $app->config['mpd']['alternative_musicdir']) !== 0 ) {
			return FALSE;
		}

		// check existence
		if(is_dir($realpath) === FALSE) {
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
}
