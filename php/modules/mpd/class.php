<?php
namespace Slimpd\modules\mpd;
use Slimpd\Track;
use Slimpd\playlist;
class mpd
{
	public function getCurrentlyPlayedTrack() {
		$status 		= $this->mpd('status');
		$listpos		= isset($status['song']) ? $status['song'] : 0;
		$files			= $this->mpd('playlist');
		$listlength		= $status['playlistlength'];
		if($listlength > 0) { 
			$track = \Slimpd\Track::getInstanceByPath($files[$listpos]);
			// obviously the played track is not imported in slimpd-database...
			// TODO: trigger whole update procedure for this single track
			// for now we simply create a dummy instance
			if($track === NULL) {
				$track = new \Slimpd\Track();
				$track->setRelativePath($files[$listpos]);
				$track->setRelativePathHash(getFilePathHash($files[$listpos]));
			}
			return $track;
		}
		return NULL;
	}
	
	public function getCurrentPlaylist($pageNum = 1) {
		
		#print_r($files); die();
		// calculate the portion which should be rendered
		$status = $this->mpd('status');
		$listPos = isset($status['song']) ? $status['song'] : 0;
		$listLength = isset($status['playlistlength']) ? $status['playlistlength'] : 0;
		
		$itemsPerPage = \Slim\Slim::getInstance()->config['mpd-playlist']['max-items'];

		$minIndex = (($pageNum-1) * $itemsPerPage);
		$maxIndex = $minIndex +  $itemsPerPage;

		$files = $this->mpd('playlist');
		if($files === FALSE) {
			return array();
		}
		$playlist = array();
		foreach($files as $idx => $filepath) {
			if($idx < $minIndex || $idx >= $maxIndex) {
				continue;
			}
			$track = \Slimpd\Track::getInstanceByPath($filepath);

			if($track === NULL) {
				$track = new \Slimpd\Track();
				$track->setRelativePath($filepath);
				$track->setRelativePathHash(getFilePathHash($filepath));
			}
			$playlist[$idx] = $track;
		}
		return $playlist;
	}
	
	public function getCurrentPlaylistLength() {
		$status = $this->mpd('status');
		return (isset($status['playlistlength'])) ? $status['playlistlength'] : 0;
	}
	
	public function getCurrentPlaylistTotalPages() {
		$status = $this->mpd('status');
		$listLength = isset($status['playlistlength']) ? $status['playlistlength'] : 0;
		$itemsPerPage = \Slim\Slim::getInstance()->config['mpd-playlist']['max-items'];
		$totalPages = floor($listLength/$itemsPerPage)+1;
		return $totalPages;
	}
	
	public function getCurrentPlaylistCurrentPage() {
		$status = $this->mpd('status');
		$listPos = isset($status['song']) ? $status['song'] : 0;
		$listLength = isset($status['playlistlength']) ? $status['playlistlength'] : 0;
		$itemsPerPage = \Slim\Slim::getInstance()->config['mpd-playlist']['max-items'];
		$totalPages = $this->getCurrentPlaylistTotalPages();
		
		$currentPage = floor($listPos/$itemsPerPage)+1;
		return $currentPage;
	}
	
	public function cmd($cmd, $item = NULL) {
		// TODO: check access
		// @see: http://www.musicpd.org/doc/protocol/playback_commands.html
		
		// validate commands
		
		
		$firePlay = FALSE;
		$targetPosition = FALSE;
		$itemPath = FALSE;
		$itemType = FALSE;
		$isPlaylist = FALSE;
		$clearPlaylist = FALSE;
		$softclearPlaylist = FALSE;
		
		switch($cmd) {
			case 'appendTrackAndPlay':
			case 'injectTrackAndPlay':
			case 'appendDirAndPlay':
			case 'injectDirAndPlay':
			case 'appendPlaylistAndPlay':
			case 'injectPlaylistAndPlay':
				$firePlay = TRUE;
				break;
		}
		
		switch($cmd) {
			case 'replaceTrack':
			case 'replaceDir':
			case 'replacePlaylist':
				$firePlay = TRUE;
				$clearPlaylist = TRUE;
				$targetPosition = 0;
				break;
		}
				
		switch($cmd) {
			case 'softreplaceTrack':
			case 'softreplaceDir':
			case 'softreplacePlaylist':
				$softclearPlaylist = TRUE;
				$targetPosition = 1;
				break;
		}
		
		switch($cmd) {
			case 'appendTrack':
			case 'appendTrackAndPlay':
			case 'appendDir':
			case 'appendDirAndPlay':
			case 'appendPlaylist':
			case 'appendPlaylistAndPlay':
				$status = $this->mpd('status');
				$targetPosition = isset($status['playlistlength']) ? $status['playlistlength'] : 0;
				break;
			case 'injectTrack':
			case 'injectTrackAndPlay':
			case 'injectDir':
			case 'injectDirAndPlay':
			case 'injectPlaylist':
			case 'injectPlaylistAndPlay':
				$status = $this->mpd('status');
				$targetPosition = isset($status['song']) ? $status['song']+1 : 0;
				break;
		}
		
		switch($cmd) {
			case 'appendPlaylist':
			case 'appendPlaylistAndPlay':
			case 'injectPlaylist':
			case 'injectPlaylistAndPlay':
			case 'replacePlaylist':
			case 'softreplacePlaylist':
				$isPlaylist = TRUE;
				break;
		}
		
		switch($cmd) {
			case 'update':
				
			case 'appendTrack':
			case 'appendTrackAndPlay':
			case 'injectTrack':
			case 'injectTrackAndPlay':
			case 'replaceTrack':
			case 'softreplaceTrack':
			
			case 'appendDir':
			case 'appendDirAndPlay':
			case 'injectDir':
			case 'injectDirAndPlay':
			case 'replaceDir':
			case 'softreplaceDir':
			
			case 'appendPlaylist':
			case 'appendPlaylistAndPlay':
			case 'injectPlaylist':
			case 'injectPlaylistAndPlay':
			case 'replacePlaylist':
			case 'softreplacePlaylist':
				$config = \Slim\Slim::getInstance()->config['mpd'];
				if(is_string($item) === TRUE) {
					$itemPath = $item;
				}
				if(is_numeric($item) === TRUE) {
					$itemPath = \Slimpd\Track::getInstanceByAttributes(array('id' => $item))->getRelativePath();
				}
				if(is_array($item) === TRUE) {
					$itemPath = join(DS, $item);
				}
				if(is_file($config['musicdir'].$itemPath)===TRUE && $itemPath !== FALSE) {
					$itemType = 'file';
				}
				if(is_dir($config['musicdir'].$itemPath)===TRUE && $itemPath !== FALSE) {
					$itemType = 'dir';
				}

				break;
		}

		// don't clear playlist in case we have nothing to add
		if($clearPlaylist === TRUE) {
			if($itemType === FALSE) {
				notifyJson("ERROR: " . $itemPath . " not found", 'mpd');
				return;
			} else {
				$this->mpd('clear');
			}
		}
		// don't softclear playlist in case we have nothing to add
		if($softclearPlaylist === TRUE) {
			if($itemType === FALSE) {
				notifyJson("ERROR: " . $itemPath . " not found", 'mpd');
				return;
			} else {
				$this->softclearPlaylist();
			}
		}


		switch($cmd) {
			case 'injectTrack':
			case 'injectTrackAndPlay':
				if($itemType !== 'file') {
					notifyJson("ERROR: invalid file", 'mpd');
					return;
				}
				$this->mpd('addid "' . str_replace("\"", "\\\"", $itemPath) . '" ' . $targetPosition);
				if($firePlay === TRUE) {
					$this->mpd('play ' . intval($targetPosition));
				}
				notifyJson("MPD: added " . $itemPath . " to playlist", 'mpd');
				return;
			case 'injectDir':
			case 'injectDirAndPlay':
				// this is not supported by mpd so we have to add each track manually
				// TODO: how to fetch possibly millions of tracks recursively?
				notifyJson("ERROR: injecting dirs is not supported yet. please append it to playlist", 'mpd');
				return;
				if($itemType !== 'dir') {
					notifyJson("ERROR: invalid dir " . $itemPath, 'mpd');
					return;
				}
				break;
			case 'injectPlaylist':
			case 'injectPlaylistAndPlay':
				$playlist = new \Slimpd\playlist\playlist($itemPath);
				$playlist->fetchTrackRange(0,1000, TRUE);
				$counter = $this->appendPlaylist($playlist, $targetPosition);
				if($firePlay === TRUE) {
					$this->mpd('play ' . intval($targetPosition));
				}
				notifyJson("MPD: added " . $playlist->getRelativePath() . " (". $counter ." tracks) to playlist", 'mpd');
				return;
			case 'appendTrack':
			case 'appendTrackAndPlay':
			case 'replaceTrack':
			case 'replaceTrackAndPlay':
			case 'softreplaceTrack':
			
			case 'appendDir':
			case 'appendDirAndPlay':
			case 'replaceDir':
			case 'replaceDirAndPlay':
			case 'softreplaceDir':
				// check if item exists in MPD database
				$closest = $this->findClosestExistingItem($itemPath);
				if(rtrim($itemPath, DS) !== $closest) {
					$this->mpd('update "' . str_replace("\"", "\\\"", $closest) . '"');
					notifyJson(
						"OH Snap!<br>
						" . $itemPath . " does not exist in MPD-database.<br>
						updating " . $closest,
						'mpd'
					);
					return;
				}

				// trailing slash on directories does not work - lets remove it
				$this->mpd('add "' . str_replace("\"", "\\\"", rtrim($itemPath, DS) ) . '"');
				if($firePlay === TRUE) {
					$this->mpd('play ' . intval($targetPosition));
				}

				notifyJson("MPD: added " . $itemPath . " to playlist", 'mpd');
				return;
				
			case 'appendPlaylist':
			case 'appendPlaylistAndPlay':
			case 'replacePlaylist':
			case 'replacePlaylistAndPlay':
			case 'softreplacePlaylist':
				$playlist = new \Slimpd\playlist\playlist($itemPath);

				$playlist->fetchTrackRange(0,1000, TRUE);
				$counter = $this->appendPlaylist($playlist);
				if($firePlay === TRUE) {
					$this->mpd('play ' . intval($targetPosition));
				}
				notifyJson("MPD: added " . $playlist->getRelativePath() . " (". $counter ." tracks) to playlist", 'mpd');
				break;
				
				
			case 'update':
				
				# TODO: move 'disallow_full_database_update' from config.ini to user-previleges
				if($itemPath === FALSE && $config['disallow_full_database_update'] == '0') {
					return $this->mpd($cmd);
				}
				
				if($itemType === FALSE) {
					// error - invalid $item
					return FALSE;
				}
				
				// now we have to find the nearest parent directory that already exists in mpd-database
				$closestExistingItemInMpdDatabase = $this->findClosestExistingItem($itemPath);
				
				// special case when we try to play a single new file (without parent-dir) out of mpd root
				if($closestExistingItemInMpdDatabase === NULL && $config['disallow_full_database_update'] == '1') {
					# TODO: send warning to client?
					return FALSE;
				}
				
				\Slimpd\importer::queDirectoryUpdate($closestExistingItemInMpdDatabase);
				
				// trailing slash on directories does not work - lets remove it
				$this->mpd('update "' . str_replace("\"", "\\\"", rtrim($closestExistingItemInMpdDatabase, DS)) . '"');
				notifyJson("MPD: updating directory " . $closestExistingItemInMpdDatabase, 'mpd');
				return;
			case 'seekPercent':
				$currentSong = $this->mpd('currentsong');
				$cmd = 'seek ' .$currentSong['Pos'] . ' ' . round($item * ($currentSong['Time']/100)) . '';
				$this->mpd($cmd);
			case 'status':
			case 'stats':
			case 'currentsong':
				return $this->mpd($cmd);
				
			case 'play':
			case 'pause':
			case 'stop':
			case 'previous':
			case 'next':
			case 'playlistid':
			case 'playlistinfo':
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
				
			case 'playIndex':
				$this->mpd('play ' . $item);
				break;
				
			case 'deleteIndex':
				$this->mpd('delete ' . $item);
				break;
				
			case 'clearPlaylist':
				$this->mpd('clear');
				notifyJson("MPD: cleared playlist", 'mpd');
				break;
				
			case 'softclearPlaylist':
				$this->softclearPlaylist();
				notifyJson("MPD: cleared playlist", 'mpd');
				break;
				
			case 'removeDupes':
				// TODO: remove requirement of having mpc installed
				$cmd = APP_ROOT . 'vendor-dist/ajjahn/puppet-mpd/files/mpd-remove-duplicates.sh';
				exec($cmd);
				// TODO: count removed dupes and display result
				notifyJson("MPD: removed dupes in current playlist", 'mpd');
				break;
			
			case 'playSelect': //		playSelect();
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

	/*
	 * function findClosestExistingDirectory
	 * play() file, that does not exist in mpd database does not work
	 * so we have to update the mpd db
	 * update() with a path as argument whichs parent does not exist in mpd db will also not work
	 * with this function we search for the closest directory that exists in mpd-db
	 */
	private function findClosestExistingItem($item) {
		if($this->mpd('lsinfo "' . str_replace("\"", "\\\"", $item) . '"') !== FALSE) {
			return $item;
		}
		if(is_file(\Slim\Slim::getInstance()->config['mpd']['musicdir'] .$item ) === TRUE) {
			$item = dirname($item);
		}
		
		$item = explode(DS, rtrim($item, DS));
		
		// single files (without a directory) added in mpd-root-directories requires a full mpd-database update :/
		if(count($item) === 1 && is_file(\Slim\Slim::getInstance()->config['mpd']['musicdir'] . $item[0])) {
			return NULL;
		}
		
		$itemCopy = $item;
		for($i=count($item); $i>=0; $i--) {
			if($this->mpd('lsinfo "' . str_replace("\"", "\\\"", join(DS, $itemCopy)) . '"') !== FALSE) {
				// we found the closest existing directory
				return join(DS, $itemCopy);
			}
			
			// shorten path by one level in every loop
			array_pop($itemCopy);
		}
		return NULL;
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
		deliverJson($data);
		
	}

	public function softclearPlaylist() {
		$status 		= $this->mpd('status');
		$songId		= isset($status['songid']) ? $status['songid'] : 0;
		if($songId > 0) {
			// move current song to first position
			$this->mpd('moveid ' . $songId . ' 0');
			
			$playlistLength		= isset($status['playlistlength']) ? $status['playlistlength'] : 0;
			if($playlistLength > 1) {
				$this->mpd('delete 1:' . $playlistLength);
			}
		} else {
			$this->mpd('clear');
		}
		
	}
	
	public function appendPlaylist($playlist, $targetPosition = FALSE) {
		$counter = 0;
		foreach($playlist->getTracks() as $t) {
			if($t->getError() === 'notfound') {
				continue;
			}
			if($targetPosition === FALSE) {
				$this->mpd('add "' . str_replace("\"", "\\\"", $t->getRelativePath()) . '"');
			} else {
				$this->mpd('addid "' . str_replace("\"", "\\\"", $t->getRelativePath()) . '" ' . ($targetPosition+$counter));
			}
			$counter ++;
		}
		return $counter;
	}
		
		
		
	//  +------------------------------------------------------------------------+
	//  | Music Player Daemon                                                    |
	//  +------------------------------------------------------------------------+
	public function mpd($command) {
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
