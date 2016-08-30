<?php
namespace Slimpd\Models;

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

		// avoid path disclosure outside allowed directories
		$base = $app->config['mpd']['musicdir'];
		$realpath = realpath($base .$this->getRelPath());
		// TODO: callable check with alternative_musicdir stuff
		if(stripos($realpath, $app->config['mpd']['musicdir']) !== 0
		&& stripos($realpath, $app->config['mpd']['alternative_musicdir']) !== 0 ) {
			return FALSE;
		}

		// check existence
		if(is_file($realpath) === FALSE) {
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
