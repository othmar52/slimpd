<?php
namespace Slimpd\modules\mpd;
use Slimpd\Track;
class mpd
{
	public function getCurrentlyPlayedTrack() {
		$status 		= $this->mpd('status');
		$listpos		= isset($status['song']) ? $status['song'] : 0;
		$files			= $this->mpd('playlist');
		$listlength		= $status['playlistlength'];
		if($listlength > 0) { 
			$track = \Slimpd\Track::getInstanceByPath($files[$listpos]);
			return $track;
		}
		return NULL;
	}
	
	public function getCurrentPlaylist() {
		
		$files = $this->mpd('playlist');
		if($files === FALSE) {
			return array();
		}
		
		#print_r($files); die();
		// calculate the portion which should be rendered
		$status = $this->mpd('status');
		$listPos = isset($status['song']) ? $status['song'] : 0;
		$listLength = isset($status['playlistlength']) ? $status['playlistlength'] : 0;
		$minIndex = 0;
		$maxIndex = \Slim\Slim::getInstance()->config['mpd-playlist']['max-items'];
		
		
		if($listLength > $maxIndex) {
			if(($listLength - $maxIndex) < $listPos ) {
				$minIndex = $listLength - $maxIndex;
				$maxIndex = $listLength-1;
			} else {
				$minIndex = $listPos;
				$maxIndex = $listPos + $maxIndex-1;
			}
		}
		

		if(1 == 2) {
			echo "<pre>";
			echo "min: " . $minIndex . "\n";
			echo "max: " . $maxIndex . "\n";
			echo "pos: " . $listPos . "\n";
			echo "len: " . $listLength . "\n";
			die();
		}
		$playlist = array();
		foreach($files as $idx => $filepath) {
			if($idx < $minIndex || $idx > $maxIndex) {
				continue;
			}
			$playlist[$idx] = \Slimpd\Track::getInstanceByPath($filepath);
		}
		
		
		return $playlist;
	}
	
	public function cmd($cmd, $item = NULL) {
		// TODO: check access
		// @see: http://www.musicpd.org/doc/protocol/playback_commands.html
		
		// validate commans
		switch($cmd) {
			
			case 'seekPercent':
				
				$currentSong = $this->mpd('currentsong');
				$cmd = 'seek ' .$currentSong['Pos'] . ' ' . round($item * ($currentSong['Time']/100)) . '';
				$this->mpd($cmd);
			case 'status':
			case 'currentsong':
				return $this->mpd($cmd);
				
			case 'play':
			case 'pause':
			case 'stop':
			case 'previous':
			case 'next':
				$this->mpd($cmd);
				break;
			case 'toggleRepeat':
				$status = $this->mpd('status');
				$this->mpd('repeat ' . (int)($status['repeat'] xor 1));
				break;
			case 'toggleRandom':
				$status = $this->mpd('status');
				$this->mpd('random ' . (int)($status['random'] xor 1));
				break;
			case 'toggleConsume':
				$status = $this->mpd('status');
				$this->mpd('consume ' . (int)($status['consume'] xor 1));
				break;
			case 'playlistStatus':
				$this->playlistStatus();
				break;
				
			case 'addSelect':
				# TODO: general handling of position to add
				# TODO: general handling of playing immediately or simply appending to playlist
				
				$path = '';
				if(is_numeric($item) === TRUE) {
					$path = \Slimpd\Track::getInstanceByAttributes(array('id' => $item))->getRelativePath();
				}
				if (is_array($item) === TRUE) {
					$path = join(DS, $item);
				}
				
				if(is_file(\Slim\Slim::getInstance()->config['mpd']['musicdir'] . $path) === TRUE) {
					$this->mpd('addid "' . str_replace("\"", "\\\"", $path) . '" 0');
					$this->mpd('play 0');
				} else {
					// trailing slash on directories will not work - lets remove it
					if(substr($path,-1) === DS) {
						$path = substr($path,0,-1);
					}
					$this->mpd('add "' . str_replace("\"", "\\\"", $path) . '"');
				}
				
				
				break;
				
			case 'playIndex':
				$this->mpd('play ' . $item);
				break;
				
			case 'deleteIndex':
				$this->mpd('delete ' . $item);
				break;
				
			case 'clearPlaylist':
				$this->mpd('clear');
				break;
			
			case 'playSelect': //		playSelect();
			case 'addSelect': //		addSelect();
			case 'deleteIndexAjax'://	deleteIndexAjax();
			case 'deletePlayed'://		deletePlayed();
			case 'volumeImageMap'://	volumeImageMap();
			case 'toggleMute'://		toggleMute();
			case 'loopGain'://			loopGain();
			
			case 'playlistTrack'://	playlistTrack();
			
				die('sorry, not implemented yet');
				break;
			default:
				die('unsupported');
				break;
		}
	}

	private function playlistStatus() {
		$playlist	= $this->mpd('playlist');
		$status 	= $this->mpd('status');
		
		$data = array();
		$data['hash']			= md5(implode('<seperation>', $playlist));
		$data['listpos']		= isset($status['song']) ? (int) $status['song'] : 0;
		$data['volume']			= (int) $status['volume'];
		$data['repeat']			= (int) $status['repeat'];
		$data['shuffle']		= (int) $status['random'];
		
		$data['isplaying'] = 0;
		if ($status['state'] == 'stop')		$data['isplaying'] = 0;
		if ($status['state'] == 'play')		$data['isplaying'] = 1;
		if ($status['state'] == 'pause')	$data['isplaying'] = 3;
		
		$data['miliseconds'] = ($status['state'] == 'stop') ? 0 : (int) round($status['elapsed'] * 1000);
		
		$data['gain'] = -1;
		
		$mpdVersion = '0.15.0';
		if (version_compare($mpdVersion, '0.16.0', '>=')) {
			$gain = $this->mpd('replay_gain_status');
			$data['gain'] = (string) $gain['replay_gain_mode'];
		}
		
		// TODO: get mute volume from database
		//if ($data['volume'] == 0) {
		//	$query	= mysql_query('SELECT mute_volume FROM player WHERE player_id = ' . (int) $cfg['player_id']);
		//	$temp	= mysql_fetch_assoc($query);
		//	$data['volume'] = -$temp['mute_volume'];
		//}
		header('Content-Type: application/json');
		echo json_encode($data);
		exit();
		
	}
		
		
		
	//  +------------------------------------------------------------------------+
	//  | Music Player Daemon                                                    |
	//  +------------------------------------------------------------------------+
	private function mpd($command) {
		$app = \Slim\Slim::getInstance();
		try {
			$socket = fsockopen(
				$app->config['mpd']['host'],
				$app->config['mpd']['port'],
				$error_no,
				$error_string,
				3
			);
		} catch (\Exception $e) {
			$app->flashNow('error', $app->ll->str('error.mpdconnect'));
			return FALSE;
		}
		
		try {
			fwrite($socket, $command . "\n");
		} catch (\Exception $e) {
			$app->flashNow('error', $app->ll->str('error.mpdwrite'));
			return FALSE;
		}
		
		
		
		$line = trim(fgets($socket, 1024)); 
		if (substr($line, 0, 3) == 'ACK') {
			fclose($socket);
			$app->flashNow('error', $app->ll->str('error.mpdgeneral', array($line)));
			return FALSE;
		}
		
		if (substr($line, 0, 6) !== 'OK MPD') {
			fclose($socket);
			$app->flashNow('error', $app->ll->str('error.mpdgeneral', array($line)));
			return FALSE;
		}
		
		$mpdVersion = (preg_match('#([0-9]+\.[0-9]+\.[0-9]+)$#', $line, $matches))
			? $matches[1]
			: '0.5.0';
		
		$array = array();
		while (!feof($socket)) {
			$line = trim(@fgets($socket, 1024));
			if (substr($line, 0, 3) == 'ACK') {
				fclose($socket);
				$app->flashNow('error', $app->ll->str('error.mpdgeneral', array($line)));
				return FALSE;
			}
			if (substr($line, 0, 2) == 'OK') {
				fclose($socket);
				if ($command == 'status' && isset($array['time']) && version_compare($mpdVersion, '0.16.0', '<')) {
					list($seconds, $dummy) = explode(':', $array['time'], 2);
					$array['elapsed'] = $seconds;
				}
				return $array;
			}
			if ($command == 'playlist' && version_compare($mpdVersion, '0.16.0', '<')) {
				// 0:directory/filename.extension
				list($key, $value) = explode(':', $line, 2);
				$array[] = iconv('UTF-8', APP_DEFAULT_CHARSET, $value);
			} elseif ($command == 'playlist' || $command == 'playlistinfo') {
				// 0:file: directory/filename.extension
				list($key, $value) = explode(': ', $line, 2);
				$array[] = iconv('UTF-8', APP_DEFAULT_CHARSET, $value);
			} else {
				// name: value
				list($key, $value) = explode(': ', $line, 2);
				$array[$key] = $value;	
			}
		}    
		fclose($socket);
		$app->flashNow('error', $app->ll->str('error.mpdconnectionclosed', array($line)));
		return FALSE;
	}
	
}
