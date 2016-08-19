<?php
namespace Slimpd\Models;
class File {
	
	public $fullpath;
	public $ext;
	public $name;
	public $relativePathHash;

	
	
	public function __construct($fileidentifier)
	{
		try {
			$this->fullpath = $fileidentifier;
			$this->name = baseName($fileidentifier);
			$this->ext = strtolower(preg_replace('/^.*\./', '', $this->name));
			$this->relativePathHash = getFilePathHash($fileidentifier);
		} catch (Exception $e) {
			
		}
		
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
