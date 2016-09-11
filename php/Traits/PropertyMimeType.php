<?php
namespace Slimpd\Traits;
/* Copyright
 * 
 */
trait PropertyMimeType {
	protected $mimeType;

	public function getMimeType() {
		return $this->mimeType;
	}

	public function setMimeType($value) {
		$this->mimeType = $value;
		return $this;
	}
}
