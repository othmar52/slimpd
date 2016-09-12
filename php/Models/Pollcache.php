<?php
namespace Slimpd\Models;
/* Copyright
 *
 */
class Pollcache extends \Slimpd\Models\AbstractModel {
	protected $microtstamp;
	protected $type;
	protected $deckindex;
	protected $success;
	protected $ipAddress;
	protected $port;
	protected $response;
	
	public static $tableName = 'pollcache';
	
	public function setMicrotstamp($value) {
		$this->microtstamp = $value;
		return $this;
	}
	
	public function getMicrotstamp() {
		return $this->microtstamp;
	}
	
	
	public function setType($value) {
		$this->type = $value;
		return $this;
	}
	
	public function getType() {
		return $this->type;
	}
	
	
	public function setDeckindex($value) {
		$this->deckindex = $value;
		return $this;
	}
	
	public function getDeckindex() {
		return $this->deckindex;
	}
	
	
	public function setSuccess($value) {
		$this->success = $value;
		return $this;
	}
	
	public function getSuccess() {
		return $this->success;
	}
	
	
	public function setIpAddress($value) {
		$this->ipAddress = $value;
		return $this;
	}
	
	public function getIpAddress() {
		return $this->ipAddress;
	}


	public function setPort($value) {
		$this->port = $value;
		return $this;
	}
	
	public function getPort() {
		return $this->port;
	}
	
	
	public function setResponse($value) {
		$this->response = $value;
		return $this;
	}
	
	public function getResponse() {
		return $this->response;
	}
	
}
