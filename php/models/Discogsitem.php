<?php
namespace Slimpd;

class Discogsitem extends AbstractModel
{
	protected $id;
	protected $tstamp;
	protected $type;
	protected $extid;
	protected $response;
	public $trackstrings;
	
	public static $tableName = 'discogsapicache';
	
	public function __construct($releaseId = FALSE) {
		if($releaseId === FALSE) {
			return $this;
		}
		if(is_numeric($releaseId) === FALSE || $releaseId < 1) {
			\Slim\Slim::getInstance()->flashNow('error', \Slim\Slim::getInstance()->ll->str('error.discogsid'));
			return;
		}
		$this->setType('release');
		$this->setExtid((int)$releaseId);
		
		$this->fetch();
		
		#var_dump($this);
		#die('nutte');
		$this->extractTracknames();
		
		return $this;
	}
	
	public function extractTracknames() {
		$data = $this->getResponse(TRUE);
		foreach($data['tracklist'] as $t) {
			$trackindex = $this->getExtid() . '-' . $t['position']; 
			$trackstring = $t['position'] . '. ';
			$trackArtists = (isset($t['artists']) === TRUE) ? $t['artists'] : $data['artists'];
			foreach($trackArtists as $a) {
				$trackstring .= $a['name'];
			}
			
			$trackstring .= ' - ' . $t['title'];#
			if(strlen($t['duration']) > 0) {
				$trackstring .= ' [' . $t['duration'] . ']';
			}
			$this->trackstrings[$trackindex] = $trackstring;
		}
	}
	
	public function fetch() {
		if($this->getExtid() < 1 || !$this->getType()) {
			return FALSE;
		}
		
		$item = $this->getInstanceByAttributes(
			['extid' => $this->getExtid(), 'type' => $this->getType()]
		);
		if($item !== NULL) {
			$this->setResponse($item->getResponse());
			return;	
		}
		$app = \Slim\Slim::getInstance();
		$client = \Discogs\ClientFactory::factory([
		    'defaults' => [
		        'headers' => ['User-Agent' => $app->config['discogsapi']['useragent']],
		    ]
		]);
		
		$getter = 'get' . ucfirst($this->getType());
		$response = $client->$getter(['id' => $this->getExtid()]);
		
		$this->setTstamp(time());
		$this->setResponse(serialize($response));
		
		$this->insert();
	}
	
	//setter
	public function setId($value) {
		$this->id = $value;
	}
	public function setTstamp($value) {
		$this->tstamp = $value;
	}
	public function setType($value) {
		$this->type = $value;
	}
	public function setExtid($value) {
		$this->extid = $value;
	}
	public function setResponse($value) {
		$this->response = $value;
	}
	
	
	// getter
	public function getId() {
		return $this->id;
	}
	public function getTstamp() {
		return $this->tstamp;
	}
	public function getType() {
		return $this->type;
	}
	public function getExtid() {
		return $this->extid;
	}
	public function getResponse($unserialize = FALSE) {
		return ($unserialize === TRUE && is_string($this->response) === TRUE) ? unserialize($this->response) : $this->response;
	}
	
}
