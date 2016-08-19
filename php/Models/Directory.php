<?php
namespace Slimpd\Models;
class Directory {
	
	public $fullpath;
	public $name;
	public $hash;
	public $breadcrumb;
	protected $exists = FALSE;

	
	
	public function __construct($directory)
	{

		try {
			$this->fullpath = $directory;
			$this->name = basename($directory);
			$this->hash = getFilePathHash($this->name);
		} catch (Exception $e) {
			
		}
		
	}
	
	public function validate() {
		$app = \Slim\Slim::getInstance();

		// avoid path disclosure outside allowed directories
		$base = $app->config['mpd']['musicdir'];
		$d = ($this->fullpath === $base) ? '' : $this->fullpath;
		$realpath = realpath(rtrim($base .$d, DS));
		if(stripos($realpath, $app->config['mpd']['musicdir']) !== 0
		&& stripos($realpath, $app->config['mpd']['alternative_musicdir']) !== 0 ) {
			return FALSE;
		}

		// check existence
		if(is_dir($realpath) === FALSE) {
			return FALSE;
		}
		$this->exists = TRUE;
		return TRUE;
	}

    public function __get($property) {
        if (property_exists($this, $property)) {
            return $this->$property;
        }
    }

    public function __set($property, $value) {
        if (property_exists($this, $property)) {
            $this->$property = $value;
        }
    }
}
