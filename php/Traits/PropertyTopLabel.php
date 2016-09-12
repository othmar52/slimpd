<?php
namespace Slimpd\Traits;
/* Copyright
 * 
 */
trait PropertyTopLabel {
	protected $topLabelUids;

	public function getTopLabelUids() {
		return $this->topLabelUids;
	}

	public function setTopLabelUids($value) {
		$this->topLabelUids = $value;
		return $this;
	}
}
