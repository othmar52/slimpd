<?php
namespace Slimpd;
class _Directory {
	
	public $fullpath;
	public $name;

	
	
	public function __construct($directory)
	{

		try {
			$this->fullpath = $directory;
			$this->name = basename($directory);
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
