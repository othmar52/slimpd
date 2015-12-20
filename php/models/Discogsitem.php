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
		$counter = 0;
		foreach($data['tracklist'] as $t) {
			$counter++;
			$trackindex = $this->getExtid() . '-' . $counter; 
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

	public function guessTrackMatch($rawTagDataInstances) {
		$matchScore = array();
		$data = $this->getResponse(TRUE);
		
		foreach($rawTagDataInstances as $rawId => $rawItem) {
			
			
			$filename = basename($rawItem->getRelativePath());
			
			$counter = 0;
			foreach($data['tracklist'] as $t) {
				$counter++;
				$extIndex = $this->getExtid() . '-' . $counter;
				
				$extArtists = (isset($t['artists']) === TRUE) ? $t['artists'] : $data['artists'];
				$extArtistString = '';
				foreach($extArtists as $a) {
					$extArtistString .= $a['name'];
				}
				
				
				
				
				if(isset($matchScore[$rawItem->getId()][$extIndex]) === FALSE) {
					$matchScore[$rawItem->getId()][$extIndex] = 0;
				}
				
				// search discogsArtist in supposable rawItem fields
				$matchScore[$rawItem->getId()][$extIndex] += $this->getMatchStringScore($rawItem->getArtist(), $extArtistString);
				$matchScore[$rawItem->getId()][$extIndex] += $this->getMatchStringScore($rawItem->getArtist(), $filename);
				$matchScore[$rawItem->getId()][$extIndex] += $this->getMatchStringScore($rawItem->getArtist(), $t['title']);
				
				
				// search discogsTitle in supposable rawItem fields
				$matchScore[$rawItem->getId()][$extIndex] += $this->getMatchStringScore($rawItem->getTitle(), $extArtistString);
				$matchScore[$rawItem->getId()][$extIndex] += $this->getMatchStringScore($rawItem->getTitle(), $filename);
				$matchScore[$rawItem->getId()][$extIndex] += $this->getMatchStringScore($rawItem->getTitle(), $t['title']);
				
				// search discogsNumber in supposable rawItem fields
				$matchScore[$rawItem->getId()][$extIndex] += $this->getMatchStringScore($rawItem->getTrackNumber(), $extArtistString);
				$matchScore[$rawItem->getId()][$extIndex] += $this->getMatchStringScore($rawItem->getTrackNumber(), $filename);
				$matchScore[$rawItem->getId()][$extIndex] += $this->getMatchStringScore($rawItem->getTrackNumber(), $t['title']);
				$matchScore[$rawItem->getId()][$extIndex] += $this->getMatchStringScore($rawItem->getTrackNumber(), $t['position']);
				
				// in case we have a discogs duration compare durations
				if(strlen($t['duration']) > 0) {
					$extSeconds = timeStringToSeconds($t['duration']);
					if($rawItem->getMiliseconds() > $extSeconds) {
						$higher = $rawItem->getMiliseconds();
						$lower =  $extSeconds;
					} else {
						$higher = $extSeconds;
						$lower =  $rawItem->getMiliseconds();
					}
					$matchScore[$rawItem->getId()][$extIndex] += floor($lower/($higher/100));
				}
			}
		}
		$return = array();
		foreach($matchScore as $rawIndex => $scorePairs) {
			arsort($scorePairs);
			$return[$rawIndex] = $scorePairs;
		}
		return $return;
		


		echo "<pre>" . print_r($return,1); die();
		echo "<pre>" . print_r($rawItem,1); die();
		
	}
	
	private function getMatchStringScore($string1, $string2) {
		if(strtolower($string1) == strtolower($string2)) {
			return 100;
		}
		return similar_text($string1, $string2);
		
		echo "<pre>".$string1 . "\n" . $string2;die();
		
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
