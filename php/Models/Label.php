<?php
namespace Slimpd\Models;
/* Copyright
 *
 */
class Label extends \Slimpd\Models\AbstractModel {
	use \Slimpd\Traits\PropGroupCounters; // trackCount, albumCount
	protected $title;
	protected $az09;

	public static $tableName = 'label';

	//setter
	public function setTitle($value) {
		$this->title = $value;
		return $this;
	}
	public function setAz09($value) {
		$this->az09 = $value;
		return $this;
	}


	// getter
	public function getTitle() {
		return $this->title;
	}
	public function getAz09() {
		return $this->az09;
	}
}
