<?php
namespace Slimpd\Models;
/* Copyright
 *
 */
abstract class AbstractTrack extends \Slimpd\Models\AbstractFilesystemItem {
	use \Slimpd\Traits\PropertyLastScan;
	use \Slimpd\Traits\PropertyMimeType;
	use \Slimpd\Traits\PropGroupAudio;
	use \Slimpd\Traits\PropGroupVideo;

	protected $title;
	protected $year;
	protected $comment;
	protected $trackNumber;
	protected $catalogNr;

	protected $fingerprint;

	protected $importStatus;
	protected $error;
	
	// getter
	public function getTitle() {
		return $this->title;
	}
	public function getYear() {
		return $this->year;
	}
	public function getComment() {
		return $this->comment;
	}
	public function getTrackNumber() {
		return $this->trackNumber;
	}
	public function getCatalogNr() {
		return $this->catalogNr;
	}

	public function getFingerprint() {
		return $this->fingerprint;
	}

	public function getImportStatus() {
		return $this->importStatus;
	}
	public function getError() {
		return $this->error;
	}
	
	// setter
	public function setTitle($value) {
		$this->title = $value;
	}
	public function setYear($value) {
		$this->year = $value;
	}
	public function setComment($value) {
		$this->comment = $value;
	}
	public function setTrackNumber($value) {
		$this->trackNumber = $value;
	}
	public function setCatalogNr($value) {
		$this->catalogNr = $value;
	}

	public function setFingerprint($value) {
		$this->fingerprint = $value;
	}

	public function setImportStatus($value) {
		$this->importStatus = $value;
	}
	public function setError($value) {
		$this->error = $value;
	}
}
