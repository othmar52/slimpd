<?php
namespace Slimpd\Models;

class Pollcache extends \Slimpd\Models\AbstractModel
{
	protected $microtstamp;
	protected $type;
	protected $deckindex;
	protected $success;
	protected $ip;
	protected $port;
	protected $response;
	
	public static $tableName = 'pollcache';
	
	public function setMicrotstamp($value) {
		$this->microtstamp = $value;
	}
	
	public function getMicrotstamp() {
		return $this->microtstamp;
	}
	
	
	public function setType($value) {
		$this->type = $value;
	}
	
	public function getType() {
		return $this->type;
	}
	
	
	public function setDeckindex($value) {
		$this->deckindex = $value;
	}
	
	public function getDeckindex() {
		return $this->deckindex;
	}
	
	
	public function setSuccess($value) {
		$this->success = $value;
	}
	
	public function getSuccess() {
		return $this->success;
	}
	
	
	public function setIp($value) {
		$this->ip = $value;
	}
	
	public function getIp() {
		return $this->ip;
	}
	
	
	public function setPort($value) {
		$this->port = $value;
	}
	
	public function getPort() {
		return $this->port;
	}
	
	
	public function setResponse($value) {
		$this->response = $value;
	}
	
	public function getResponse() {
		return $this->response;
	}
	
}
