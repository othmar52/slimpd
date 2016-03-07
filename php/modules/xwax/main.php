<?php
namespace Slimpd;

class Xwax {
	
	public function cmd($cmd, $params, $app, $returnResponse = FALSE) {
		if($app->config['modules']['enable_xwax'] !== '1') {
			// TODO: send error msg to frontend
			echo $app->ll->str('xwax.notenabled'); die();
		}
		$xConf = $app->config['xwax'];
		
		if($xConf['decks'] < 1) {
			// TODO: send error msg to frontend
			echo $app->ll->str('xwax.deckconfig'); die();
		}
		
		if(count($params) === 0) {
			// TODO: send error msg to frontend
			echo $app->ll->str('xwax.missing.deckparam'); die();
		}
		
		
		$totalDecks = $xConf['decks'];
		$selectedDeck = $params[0];
		
		if(is_numeric($selectedDeck) === FALSE || $selectedDeck < 1 || $selectedDeck > $totalDecks) {
			// TODO: send error msg to frontend
			echo $app->ll->str('xwax.invalid.deckparam'); die();
		}
		
		if(isset($xConf['cmd_'. $cmd]) === FALSE) {
			// TODO: send error msg to frontend
			echo $app->ll->str('xwax.invalid.cmd'); die();
		}
		
		$loadArgs = '';
		
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
		
		$execCmd = 'timeout 2 ' . $xConf['clientpath'] . " " . $xConf['server'] . " "  . $cmd . " " . ($selectedDeck-1) . $loadArgs;
		
		exec($execCmd, $response);
		
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

	public function getCurrentlyPlayedTrack($deckIndex) {
		$return = array();
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
		return $out;
	}
}
