<?php
namespace Slimpd;

class Xwax {
	
	protected $ip;
	protected $port = 0;
	protected $deckIndex;
	protected $type = 'xwax';
	protected $pollcache = NULL;
	
	public function cmd($cmd, $params, $app, $returnResponse = FALSE) {
		if($app->config['modules']['enable_xwax'] !== '1') {
			notifyJson($app->ll->str('xwax.notenabled'), 'danger');
		}
		$xConf = $app->config['xwax'];
		
		if($xConf['decks'] < 1) {
			notifyJson($app->ll->str('xwax.deckconfig'), 'danger');
		}
		
		if(count($params) === 0) {
			notifyJson($app->ll->str('xwax.missing.deckparam'), 'danger');
		}
		
		$totalDecks = $xConf['decks'];
		$selectedDeck = $params[0];
		
		if(is_numeric($selectedDeck) === FALSE || $selectedDeck < 1 || $selectedDeck > $totalDecks) {
			notifyJson($app->ll->str('xwax.invalid.deckparam'), 'danger');
		}
		
		if(isset($xConf['cmd_'. $cmd]) === FALSE) {
			notifyJson($app->ll->str('xwax.invalid.cmd'), 'danger');
		}
		
		$loadArgs = '';
		$this->ip = $xConf['server'];
		$this->deckIndex = $selectedDeck-1;
		
		if($cmd == "load_track") {
			array_shift($params);
			// TODO: try to fetch artist and title from database
			$filePath = realpath($app->config['mpd']['alternative_musicdir'] . join(DS, $params));
			if(is_file($filePath) === FALSE) {
				notifyJson($app->ll->str('xwax.invalid.file'), 'danger');
			}
			$loadArgs = ' ' . escapeshellarg($filePath) . ' '
							. escapeshellarg('dummyartist') . ' '
							. escapeshellarg('dummytitle');
		}
		
		$xConf['clientpath'] = ($xConf['clientpath'][0] === '/')
			? $xConf['clientpath']
			: APP_ROOT . $xConf['clientpath'];
			
		if(is_file($xConf['clientpath']) === FALSE) {
			notifyJson($app->ll->str('xwax.invalid.clientpath'), 'danger');
		}
		
		$useCache = FALSE;
		
		if($cmd === "get_status") {
			$this->onBeforeGetStatus();
			if($this->pollcache !== NULL) {
				$interval = 2;
				if(microtime(TRUE) - $this->pollcache->getMicrotstamp() < $interval) {
					$useCache = TRUE;
				}
			}
			if($app->request->get('force' === '1')) {
				$useCache = FALSE;
			}
		}
		
		if($useCache === FALSE) {
			$execCmd = 'timeout 1 ' . $xConf['clientpath'] . " " . $this->ip . " "  . $cmd . " " . $this->deckIndex . $loadArgs;
			exec($execCmd, $response);
			
			if($cmd === "get_status") {
				$this->onAfterGetStatus($response);
			}
			
		} else {
			$response = unserialize($this->pollcache->getResponse());
		}
		
		if(isset($response[0]) && $response[0] === "OK") {
			if($returnResponse === FALSE) {
				notifyJson($app->ll->str('xwax.cmd.success'), 'success');
			} else {
				array_shift($response);
				return $response;
			}
		} else {
			notifyJson($app->ll->str('xwax.cmd.error'), 'danger');
		}
	}

	/*
	 * check if we have a cached pollresult to avoid xwax-client-penetration caused by multiple web-clients
	 **/
	private function onBeforeGetStatus() {
		$this->pollcache = \Slimpd\pollcache::getInstanceByAttributes(
			array(
				'type' => $this->type,
				'deckindex' => $this->deckIndex,
				'ip' => $this->ip,
				'port' => $this->port
			)
		);
	}
	
	private function onAfterGetStatus($response) {
		if($this->pollcache === NULL) {
			$this->pollcache = new \Slimpd\pollcache();
			$this->pollcache->setType($this->type);
			$this->pollcache->setDeckindex($this->deckIndex);
			$this->pollcache->setIp($this->ip);
			$this->pollcache->setPort($this->port);
		}
		$this->pollcache->setResponse(serialize($response));
		$this->pollcache->setMicrotstamp(microtime(TRUE));
		$this->pollcache->update();
	}

	public function getCurrentlyPlayedTrack($deckIndex) {
		$app = \Slim\Slim::getInstance();
		$xConf = $app->config['xwax'];
		$deckStatus = self::clientResponseToArray($this->cmd('get_status', array($deckIndex+1), $app, TRUE));
		$deckItem = ($deckStatus['path'] !== NULL)
			 ? \Slimpd\playlist\playlist::pathStringsToTrackInstancesArray([$deckStatus['path']])[0]
			 : NULL;
		return $deckItem;
	}

	public function fetchAllDeckStats() {
		$return = array();
		$app = \Slim\Slim::getInstance();
		$xConf = $app->config['xwax'];
		for($i=0; $i<$xConf['decks']; $i++) {
			$deckStatus = self::clientResponseToArray($this->cmd('get_status', array($i+1), $app, TRUE));
			$deckStatus['item'] = ($deckStatus['path'] !== NULL)
				? \Slimpd\playlist\playlist::pathStringsToTrackInstancesArray([$deckStatus['path']])[0]->jsonSerialize()
				: NULL;
			$return[] = $deckStatus;
		}
		return $return;
	}
	

	public static function clientResponseToArray($responseArray) {
		$out = array();
		foreach($responseArray as $line) {
			$params = trimExplode(":", $line, TRUE, 2);
			$out[$params[0]] = (isset($params[1]) === FALSE) ? NULL : $params[1];
		}
		
		$out['percent'] = ($out['length'] > 0 && $out['position'] > 0)
			? $out['position'] /($out['length']/100)
			: 0;
			
		$out['state'] = ($out['player_sync_pitch'] != 1) ? 'play' : 'pause';
		return $out;
	}
}
