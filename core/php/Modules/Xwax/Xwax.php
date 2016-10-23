<?php
namespace Slimpd\Modules\Xwax;
/* Copyright (C) 2016 othmar52 <othmar52@users.noreply.github.com>
 *
 * This file is part of sliMpd - a php based mpd web client
 *
 * This program is free software: you can redistribute it and/or modify it
 * under the terms of the GNU Affero General Public License as published by the
 * Free Software Foundation, either version 3 of the License, or (at your
 * option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License
 * for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
class Xwax {
	protected $ipAddress;
	protected $port = 0;
	protected $deckIndex;
	protected $type = 'xwax';
	protected $pollcache = NULL;

	public function __construct($container) {
		$this->ll = $container->ll;
		$this->conf = $container->conf;
	}

	public function cmd($cmd, $params, $returnResponse = FALSE) {
		if($this->conf['modules']['enable_xwax'] !== '1') {
			notifyJson($this->ll->str('xwax.notenabled'), 'danger');
		}
		$xConf = $this->conf['xwax'];

		if($xConf['decks'] < 1) {
			notifyJson($this->ll->str('xwax.deckconfig'), 'danger');
		}

		if(count($params) === 0) {
			notifyJson($this->ll->str('xwax.missing.deckparam'), 'danger');
		}

		$totalDecks = $xConf['decks'];
		$selectedDeck = $params[0];

		if(is_numeric($selectedDeck) === FALSE || $selectedDeck < 1 || $selectedDeck > $totalDecks) {
			notifyJson($this->ll->str('xwax.invalid.deckparam'), 'danger');
		}

		if(isset($xConf['cmd_'. $cmd]) === FALSE) {
			notifyJson($this->ll->str('xwax.invalid.cmd'), 'danger');
		}

		$loadArgs = '';
		$this->ipAddress = $xConf['server'];
		$this->deckIndex = $selectedDeck-1;

		if($cmd == "load_track") {
			array_shift($params);
			// TODO: try to fetch artist and title from database
			$filePath = getFileRealPath(join(DS, $params));
			if($filePath === FALSE) {
				notifyJson($this->ll->str('xwax.invalid.file'), 'danger');
			}
			$loadArgs = ' ' . escapeshellarg($filePath) . ' '
							. escapeshellarg('dummyartist') . ' '
							. escapeshellarg('dummytitle');
		}

		$xConf['clientpath'] = ($xConf['clientpath'][0] === '/')
			? $xConf['clientpath']
			: APP_ROOT . $xConf['clientpath'];

		if(is_file($xConf['clientpath']) === FALSE) {
			notifyJson($this->ll->str('xwax.invalid.clientpath'), 'danger');
		}

		$useCache = FALSE;

		if($cmd === "get_status") {
			$this->onBeforeGetStatus();
			if($this->pollcache !== NULL) {
				$interval = 2;
				if(getMicrotimeFloat() - $this->pollcache->getMicrotstamp() < $interval) {
					$useCache = TRUE;
				}
			}
			if($app->request->get('force' === '1')) {
				$useCache = FALSE;
			}
		}

		if($useCache === FALSE) {
			$execCmd = 'timeout 1 ' . $xConf['clientpath'] . " " . $this->ipAddress . " "  . $cmd . " " . $this->deckIndex . $loadArgs;
			exec($execCmd, $response);

			if($cmd === "get_status") {
				$this->onAfterGetStatus($response);
			}

		} else {
			$response = unserialize($this->pollcache->getResponse());
		}

		if(isset($response[0]) && $response[0] === "OK") {
			if($returnResponse === FALSE) {
				notifyJson($this->ll->str('xwax.cmd.success'), 'success');
			} else {
				array_shift($response);
				return $response;
			}
		} else {
			notifyJson($this->ll->str('xwax.cmd.error'), 'danger');
		}
	}

	/*
	 * check if we have a cached pollresult to avoid xwax-client-penetration caused by multiple web-clients
	 **/
	private function onBeforeGetStatus() {
		$this->pollcache = \Slimpd\Models\Pollcache::getInstanceByAttributes(
			array(
				'type' => $this->type,
				'deckindex' => $this->deckIndex,
				'ipAddress' => $this->ipAddress,
				'port' => $this->port
			)
		);
	}

	private function onAfterGetStatus($response) {
		if($this->pollcache === NULL) {
			$this->pollcache = new \Slimpd\Models\Pollcache();
			$this->pollcache->setType($this->type)
				->setDeckindex($this->deckIndex)
				->setIpAddress($this->ipAddress)
				->setPort($this->port);
		}
		$this->pollcache->setResponse(serialize($response))
			->setMicrotstamp(getMicrotimeFloat())
			->update();
	}

	public function getCurrentlyPlayedTrack($deckIndex) {
		
		#$xConf = $this->conf['xwax'];
		$deckStatus = self::clientResponseToArray($this->cmd('get_status', array($deckIndex+1), TRUE));
		$deckItem = ($deckStatus['path'] !== NULL)
			 ? \Slimpd\Models\PlaylistFilesystem::pathStringsToTrackInstancesArray([$deckStatus['path']])[0]
			 : NULL;
		return $deckItem;
	}

	public function fetchAllDeckStats() {
		$return = array();
		$xConf = $this->conf['xwax'];
		for($i=0; $i<$xConf['decks']; $i++) {
			// dont try other decks in case first deck fails
			$response = $this->cmd('get_status', array($i+1), TRUE);
			if(count($response) === 0) {
				return NULL;
			}
			$deckStatus = self::clientResponseToArray($response);
			$deckStatus['item'] = ($deckStatus['path'] !== NULL)
				? \Slimpd\Models\PlaylistFilesystem::pathStringsToTrackInstancesArray([$deckStatus['path']])[0]->jsonSerialize()
				: NULL;
			$return[] = $deckStatus;
		}
		return $return;
	}


	public static function clientResponseToArray($responseArray) {
		$out = array();
		if(is_array($responseArray) === FALSE) {
			return $out;
		}
		foreach($responseArray as $line) {
			$params = trimExplode(":", $line, TRUE, 2);
			try {
				$out[$params[0]] = $params[1];
			} catch(\Exception $e) {
				$out[$params[0]] = NULL;
			}
		}
		try {
			$out['percent'] = $out['position'] /($out['length']/100);
		} catch(\Exception $e) {
			$out['percent'] = 0;
		}
		$out['state'] = ($out['player_sync_pitch'] != 1) ? 'play' : 'pause';
		return $out;
	}
}
