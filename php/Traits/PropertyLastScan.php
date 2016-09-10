<?php
namespace Slimpd\Traits;
/* Copyright
 * 
 */
trait PropertyLastScan {
	protected $lastScan;

	public function getLastScan() {
		return $this->lastScan;
	}

	public function setLastScan($value) {
		$this->lastScan = $value;
	}
}
