<?php
namespace Slimpd\Modules\mpd;
use Slimpd\Models\Track;
use Slimpd\playlist;
class mpd
{
	public function getCurrentlyPlayedTrack() {
		$status 		= $this->mpd('status');
		$listpos		= isset($status['song']) ? $status['song'] : 0;
		$files			= $this->mpd('playlist');
		$listlength		= $status['playlistlength'];
		if($listlength < 1) {
			return NULL;
		}
		return \Slimpd\Models\Track::getInstanceByPath($files[$listpos], TRUE);
	}

	public function getCurrentPlaylist($pageNum = 1) {
		$playlist = array();
		$filePaths = $this->mpd('playlist');
		if($filePaths === FALSE) {
			return $playlist;
		}
		$itemsPerPage = \Slim\Slim::getInstance()->config['mpd-playlist']['max-items'];
		$minIndex = (($pageNum-1) * $itemsPerPage);
		$maxIndex = $minIndex +  $itemsPerPage;

		foreach($filePaths as $idx => $filePath) {
			if($idx < $minIndex || $idx >= $maxIndex) {
				continue;
			}
			$playlist[$idx] = \Slimpd\Models\Track::getInstanceByPath($filePath, TRUE);
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
		$itemsPerPage = \Slim\Slim::getInstance()->config['mpd-playlist']['max-items'];
		$currentPage = floor($listPos/$itemsPerPage)+1;
		return $currentPage;
	}

	private function getFirePlay($cmd) {
		$commandList = [
			"appendTrackAndPlay",
			"injectTrackAndPlay",
			"appendDirAndPlay",
			"injectDirAndPlay",
			"appendPlaylistAndPlay",
			"injectPlaylistAndPlay",
			"replaceTrack",
			"replaceDir",
			"replacePlaylist"
		];
		return in_array($cmd, $commandList);
	}

	private function getTargetPosition($cmd) {
		$status = $this->mpd('status');
		$appendPos = (isset($status['playlistlength'])) ? $status['playlistlength'] : 0;
		$injectPos = (isset($status['song'])) ? $status['song']+1 : 0;

		$commandList = [
			"replaceTrack" => 0,
			"replaceDir" => 0,
			"replacePlaylist" => 0,

			"softreplaceTrack" => 1,
			"softreplaceDir" => 1,
			"softreplacePlaylist" => 1,

			"appendTrack" => $appendPos,
			"appendTrackAndPlay" => $appendPos,
			"appendDir" => $appendPos,
			"appendDirAndPlay" => $appendPos,
			"appendPlaylist" => $appendPos,
			"appendPlaylistAndPlay" => $appendPos,

			"injectTrack" => $injectPos,
			"injectTrackAndPlay" => $injectPos,
			"injectDir" => $injectPos,
			"injectDirAndPlay" => $injectPos,
			"injectPlaylist" => $injectPos,
			"injectPlaylistAndPlay" => $injectPos,
		];
		if(array_key_exists($cmd, $commandList) === TRUE) {
			return $commandList[$cmd];
		}
		return FALSE;
	}

	private function getClearPlaylist($cmd) {
		$commandList = [
			"replaceTrack",
			"replaceDir",
			"replacePlaylist"
		];
		return in_array($cmd, $commandList);
	}

	private function getSoftClear($cmd) {
		$commandList = [
			"softreplaceTrack",
			"softreplaceDir",
			"softreplacePlaylist"
		];
		return in_array($cmd, $commandList);
	}

	/*
	private function getIsPlaylist($cmd) {
		$commandList = [
			"appendPlaylist",
			"appendPlaylistAndPlay",
			"injectPlaylist",
			"injectPlaylistAndPlay",
			"replacePlaylist",
			"softreplacePlaylist"
		];
		return in_array($cmd, $commandList);
	}
	*/

	private function getItemPath($item) {
		if(is_numeric($item) === TRUE) {
			$instance = \Slimpd\Models\Track::getInstanceByAttributes(array('id' => $item));
			if($instance === NULL) {
				return FALSE;
			}
			return $instance->getRelativePath();
		}
		if(is_string($item) === TRUE) {
			return $item;
		}
		if(is_array($item) === TRUE) {
			return join(DS, $item);
		}
		return FALSE;
	}

	private function getItemType($itemPath) {
		$musicDir = \Slim\Slim::getInstance()->config['mpd']['musicdir'];
		if(is_file($musicDir.$itemPath) === TRUE) {
			return 'file';
		}
		if(is_dir($musicDir.$itemPath) === TRUE) {
			return 'dir';
		}
		return FALSE;
	}

	public function cmd($cmd, $item = NULL) {
		// TODO: check access
		// @see: http://www.musicpd.org/doc/protocol/playback_commands.html

		// validate commands

		
		$firePlay = $this->getFirePlay($cmd);
		$targetPosition = $this->getTargetPosition($cmd);
		$itemPath = $this->getItemPath($item);
		$itemType = $this->getItemType($itemPath);
		//$isPlaylist = $this->getIsPlaylist($cmd);
		$clearPlaylist = $this->getClearPlaylist($cmd);
		$softclearPlaylist = $this->getSoftClear($cmd);


		$config = \Slim\Slim::getInstance()->config['mpd'];

		// don't clear playlist in case we have nothing to add
		if($clearPlaylist === TRUE) {
			if($itemType === FALSE) {
				notifyJson("ERROR: " . $itemPath . " not found", 'mpd');
				return;
			}
			$this->mpd('clear');
		}
		// don't softclear playlist in case we have nothing to add
		if($softclearPlaylist === TRUE) {
			if($itemType === FALSE) {
				notifyJson("ERROR: " . $itemPath . " not found", 'mpd');
				return;
			}
			$this->softclearPlaylist();
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
				$closestMpdItem = $this->findClosestExistingItem($itemPath);

				// special case when we try to play a single new file (without parent-dir) out of mpd root
				if($closestMpdItem === NULL && $config['disallow_full_database_update'] == '1') {
					# TODO: send warning to client?
					return FALSE;
				}

				\Slimpd\importer::queDirectoryUpdate($closestMpdItem);

				// trailing slash on directories does not work - lets remove it
				$this->mpd('update "' . str_replace("\"", "\\\"", rtrim($closestMpdItem, DS)) . '"');
				notifyJson("MPD: updating directory " . $closestMpdItem, 'mpd');
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
			default:
				notifyJson("sorry, not implemented yet", "mpd");
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

	public function softclearPlaylist() {
		$status = $this->mpd('status');
		$currentSongId = isset($status['songid']) ? $status['songid'] : 0;
		if($currentSongId < 1) {
			$this->mpd('clear');
			return;
		}

		// move current song to first position
		$this->mpd('moveid ' . $currentSongId . ' 0');
		$playlistLength = isset($status['playlistlength']) ? $status['playlistlength'] : 0;
		if($playlistLength > 1) {
			$this->mpd('delete 1:' . $playlistLength);
		}
	}

	public function appendPlaylist($playlist, $targetPosition = FALSE) {
		$counter = 0;
		foreach($playlist->getTracks() as $t) {
			if($t->getError() === 'notfound') {
				continue;
			}
			$trackPath = "\"" . str_replace("\"", "\\\"", $t->getRelativePath()) . "\""; 
			$cmd = "add " . $trackPath;
			if($targetPosition !== FALSE) {
				$cmd = "addid " . $trackPath . " " . ($targetPosition+$counter);
			}
			$this->mpd($cmd);
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
				$errorNo,
				$errorString,
				3
			);
			if($socket === FALSE) {
				$app->flashNow('error', $errorString . "(" . $errorNo . ")");
				return FALSE;
			}
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
		if (substr($line, 0, 3) === 'ACK') {
			fclose($socket);
			$app->flashNow('error', $app->ll->str('error.mpdgeneral', array($line)));
			return FALSE;
		}

		if (substr($line, 0, 6) !== 'OK MPD') {
			fclose($socket);
			$app->flashNow('error', $app->ll->str('error.mpdgeneral', array($line)));
			return FALSE;
		}

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
				return $array;
			}
			list($key, $value) = explode(': ', $line, 2);
			if ($command == 'playlist' || $command == 'playlistinfo') {
				$array[] = iconv('UTF-8', APP_DEFAULT_CHARSET, $value);
				continue;
			}
			// name: value
			$array[$key] = $value;
		}
		fclose($socket);
		$app->flashNow('error', $app->ll->str('error.mpdconnectionclosed', array($line)));
		return FALSE;
	}

}
