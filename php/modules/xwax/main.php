<?php
namespace Slimpd;

class Xwax {
	
	public function cmd($cmd, $params, $app) {
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
				// TODO: send error msg to frontend
				echo $app->ll->str('xwax.invalid.file'); die();
			}
			$loadArgs = ' ' . escapeshellarg($filePath) . ' '
							. escapeshellarg('dummyartist') . ' '
							. escapeshellarg('dummytitle');
		}
		
		$execCmd = 'timeout 2 ' . $xConf['clientpath'] . " " . $xConf['server'] . " "  . $cmd . " " . ($selectedDeck-1) . $loadArgs;
		
		exec($execCmd, $response);
		
		// TODO: send success message to frontend
		echo "success";
		$app->stop(); exit;
		echo "<pre>$execCmd"; die(); 
	}
}
