<?php
namespace Slimpd\Modules\Mpd;
/* Copyright (C) 2015-2016 othmar52 <othmar52@users.noreply.github.com>
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
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License
 * for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

class Mpd {
    public $notifyJson = NULL;

    protected $hasConnectionError = FALSE;

    public function __construct(\Slim\Container $container) {
        $this->container = $container;
        $this->conf = $container->conf;
        $this->trackRepo = $container->trackRepo;
    }
    public function getCurrentlyPlayedTrack() {
        $status         = $this->mpd('status');
        $listpos        = isset($status['song']) ? $status['song'] : 0;
        $files            = $this->mpd('playlist');
        $listlength        = $status['playlistlength'];
        if($listlength < 1) {
            return NULL;
        }
        return $this->trackRepo->getInstanceByPath($files[$listpos], TRUE);
    }

    public function getPriorityForTrack($relativePath) {
        $entry = $this->mpd('playlistfind "File" "'. str_replace('"', '\"', $relativePath).'"');
        if(array_key_exists('Prio', $entry) === FALSE) {
            return 0;
        }
        #echo "<pre>";print_r($entry);die;
        return $entry['Prio'];
    }

    /**
     * values of priorities are between 0 (no prio) and 255 (max prio)
     * so when playAsNext is requested we have to make sure it will get a lower prio than other prirized tracks.
     * this function fetches the highest existing prio decremented by 1
     */
    public function getNextGlobalPriority() {
        $nextPrio = 255;
        $allTracksInfo = $this->mpd('playlistinfo');
        foreach($allTracksInfo as $trackInfo) {
            if(array_key_exists('Prio', $trackInfo) === FALSE) {
                continue;
            }
            if($trackInfo["Prio"] > 0 && $trackInfo["Prio"] < $nextPrio) {
                $nextPrio = $trackInfo["Prio"]-1;
            }
        }
        return ($nextPrio === 0) ? 1 : $nextPrio;
    }

    public function getCurrentPlaylist($pageNum = 1) {
        $playlist = array();
        $filePaths = $this->mpd('playlist');
        if($filePaths === FALSE) {
            return $playlist;
        }
        $itemsPerPage = $this->conf['mpd-playlist']['max-items'];
        $minIndex = (($pageNum-1) * $itemsPerPage);
        $maxIndex = $minIndex +  $itemsPerPage;

        foreach($filePaths as $idx => $filePath) {
            if($idx < $minIndex || $idx >= $maxIndex) {
                continue;
            }
            $playlist[$idx] = $this->trackRepo->getInstanceByPath($filePath, TRUE);
            $playlist[$idx]->prio = $this->getPriorityForTrack($filePath);
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
        $itemsPerPage = $this->conf['mpd-playlist']['max-items'];
        $totalPages = floor($listLength/$itemsPerPage)+1;
        return $totalPages;
    }

    public function getCurrentPlaylistCurrentPage() {
        $status = $this->mpd('status');
        $listPos = isset($status['song']) ? $status['song'] : 0;
        $itemsPerPage = $this->conf['mpd-playlist']['max-items'];
        $currentPage = floor($listPos/$itemsPerPage)+1;
        return $currentPage;
    }

    protected function getFirePlay($cmd) {
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

    protected function getSetPriority($cmd) {
        $commandList = [
            "injectTrack",
            "injectDir",
            "injectPlaylist"
        ];
        return in_array($cmd, $commandList);
    }

    protected function getTargetPosition($cmd) {
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

    protected function getClearPlaylist($cmd) {
        $commandList = [
            "replaceTrack",
            "replaceDir",
            "replacePlaylist"
        ];
        return in_array($cmd, $commandList);
    }

    protected function getSoftClear($cmd) {
        $commandList = [
            "softreplaceTrack",
            "softreplaceDir",
            "softreplacePlaylist"
        ];
        return in_array($cmd, $commandList);
    }

    /*
    protected function getIsPlaylist($cmd) {
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

    protected function getItemPath($item) {
        if(is_numeric($item) === TRUE) {
            $instance = $this->trackRepo->getInstanceByAttributes(array('uid' => $item));
            if($instance === NULL) {
                return FALSE;
            }
            return $instance->getRelPath();
        }
        if(is_string($item) === TRUE) {
            return $item;
        }
        if(is_array($item) === TRUE) {
            return join(DS, $item);
        }
        return FALSE;
    }

    protected function getItemType($itemPath) {
        $musicDir = $this->conf['mpd']['musicdir'];
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
        $setPriority = $this->getSetPriority($cmd);
        $targetPosition = $this->getTargetPosition($cmd);
        $itemPath = $this->getItemPath($item);
        $itemType = $this->getItemType($itemPath);
        //$isPlaylist = $this->getIsPlaylist($cmd);
        $clearPlaylist = $this->getClearPlaylist($cmd);
        $softclearPlaylist = $this->getSoftClear($cmd);


        $config = $this->conf['mpd'];

        // don't clear playlist in case we have nothing to add
        if($clearPlaylist === TRUE) {
            if($itemType === FALSE) {
                $this->notifyJson = notifyJson("ERROR: " . $itemPath . " not found", 'mpd');
                return;
            }
            $this->mpd('clear');
        }
        // don't softclear playlist in case we have nothing to add
        if($softclearPlaylist === TRUE) {
            if($itemType === FALSE) {
                $this->notifyJson = notifyJson("ERROR: " . $itemPath . " not found", 'mpd');
                return;
            }
            $this->softclearPlaylist();
        }

        switch($cmd) {
            case 'injectTrack':
            case 'injectTrackAndPlay':
                if($itemType !== 'file') {
                    $this->notifyJson = notifyJson("ERROR: invalid file", 'mpd');
                    return;
                }
                $this->mpd('addid "' . str_replace("\"", "\\\"", $itemPath) . '" ' . $targetPosition);
                if($firePlay === TRUE) {
                    $this->mpd('play ' . intval($targetPosition));
                }
                if($setPriority === TRUE) {
                    $this->mpd('prio ' . $this->getNextGlobalPriority() . " " . intval($targetPosition));
                }
                $this->notifyJson = notifyJson("MPD: added " . $itemPath . " to playlist", 'mpd');
                return;
            case 'injectDir':
            case 'injectDirAndPlay':
                // this is not supported by mpd so we have to add each track manually
                // TODO: how to fetch possibly millions of tracks recursively?
                $this->notifyJson = notifyJson("ERROR: injecting dirs is not supported yet. please append it to playlist", 'mpd');
                return;
                if($itemType !== 'dir') {
                    $this->notifyJson = notifyJson("ERROR: invalid dir " . $itemPath, 'mpd');
                    return;
                }
                break;
            case 'injectPlaylist':
            case 'injectPlaylistAndPlay':
                $playlist = new \Slimpd\Models\PlaylistFilesystem($itemPath);
                $playlist->fetchTrackRange(0,1000, TRUE);
                $counter = $this->appendPlaylist($playlist, $targetPosition, $this->getNextGlobalPriority());
                if($firePlay === TRUE) {
                    $this->mpd('play ' . intval($targetPosition));
                }
                $this->notifyJson = notifyJson("MPD: added " . $playlist->getRelPath() . " (". $counter ." tracks) to playlist", 'mpd');
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
                if(removeTrailingSlash($itemPath) !== $closest) {
                    $this->mpd('update "' . str_replace("\"", "\\\"", $closest) . '"');
                    $this->notifyJson = notifyJson(
                        "OH Snap!<br>
                        " . $itemPath . " does not exist in MPD-database.<br>
                        updating " . $closest,
                        'mpd'
                    );
                    return;
                }

                // trailing slash on directories does not work - lets remove it
                $this->mpd('add "' . str_replace("\"", "\\\"", removeTrailingSlash($itemPath) ) . '"');
                if($firePlay === TRUE) {
                    $this->mpd('play ' . intval($targetPosition));
                }

                $this->notifyJson = notifyJson("MPD: added " . $itemPath . " to playlist", 'mpd');
                return;

            case 'appendPlaylist':
            case 'appendPlaylistAndPlay':
            case 'replacePlaylist':
            case 'replacePlaylistAndPlay':
            case 'softreplacePlaylist':
                $playlist = new \Slimpd\Models\PlaylistFilesystem($this->container);
                $playlist->load($itemPath);

                $playlist->fetchTrackRange(0,1000, TRUE);
                $counter = $this->appendPlaylist($playlist);
                if($firePlay === TRUE) {
                    $this->mpd('play ' . intval($targetPosition));
                }
                $this->notifyJson = notifyJson("MPD: added " . $playlist->getRelPath() . " (". $counter ." tracks) to playlist", 'mpd');
                return;

            case 'update':

                // now we have to find the nearest parent directory that already exists in mpd-database
                $closestMpdItem = ($itemPath === "") ? "" : $this->findClosestExistingItem($itemPath);

                if($closestMpdItem === "" && $config['disallow_full_database_update'] == '1') {
                    $this->notifyJson = notifyJson("full db update is disabled by config", 'error');
                    return;
                }

                $mpdUpdateArg = "";
                $mpdNotifyMsg = "MPD: running full database update";

                if($closestMpdItem !== "") {
                    // trailing slash on directories does not work - lets remove it
                    $mpdUpdateArg = ' "' . str_replace("\"", "\\\"", removeTrailingSlash($closestMpdItem)) . '"';
                    $mpdNotifyMsg = "MPD: updating directory " . $closestMpdItem;
                }

                // trigger MPD's internal update process
                $this->mpd('update' . $mpdUpdateArg);

                // insert database record which will be processed on next CLI run for sliMpd database update
                $importer = new \Slimpd\Modules\Importer\Importer($this->container);
                $importer->queUpdate();

                $this->notifyJson = notifyJson($mpdNotifyMsg, 'mpd');
                return;
            case 'seekPercent':
                $currentSong = $this->mpd('currentsong');
                $targetSecond = round($item * ($currentSong['Time']/100));
                $cmd = 'seek ' . $currentSong['Pos'] . ' ' . $targetSecond;
                // TODO: check mpd version >= 0.18
                // @see: https://bugs.musicpd.org/view.php?id=4073
                #$cmd = 'seekcur ' . $targetSecond;
                return $this->mpd($cmd);
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
                $this->notifyJson = notifyJson("MPD: cleared playlist", 'mpd');
                return;

            case 'softclearPlaylist':
                $this->softclearPlaylist();
                $this->notifyJson = notifyJson("MPD: cleared playlist", 'mpd');
                return;

            case 'removeDupes':
                // TODO: remove requirement of having mpc installed
                $cmd = APP_ROOT . 'core/vendor-dist/ajjahn/puppet-mpd/files/mpd-remove-duplicates.sh';
                exec($cmd);
                // TODO: count removed dupes and display result
                $this->notifyJson = notifyJson("MPD: removed dupes in current playlist", 'mpd');
                return;

            case 'playSelect': //        playSelect();
            case 'deleteIndexAjax'://    deleteIndexAjax();
            case 'deletePlayed'://        deletePlayed();
            case 'volumeImageMap'://    volumeImageMap();
            case 'toggleMute'://        toggleMute();
            case 'loopGain'://            loopGain();

            case 'playlistTrack'://    playlistTrack();
            default:
                $this->notifyJson = notifyJson("sorry, not implemented yet", "mpd");
                return;
        }
    }

    /*
     * function findClosestExistingDirectory
     * play() file, that does not exist in mpd database does not work
     * so we have to update the mpd db
     * update() with a path as argument whichs parent does not exist in mpd db will also not work
     * with this function we search for the closest directory that exists in mpd-db
     */
    protected function findClosestExistingItem($item) {
        if($this->mpd('lsinfo "' . str_replace("\"", "\\\"", $item) . '"') !== FALSE) {
            return $item;
        }
        if(is_file($this->conf['mpd']['musicdir'] .$item ) === TRUE) {
            $item = dirname($item);
        }

        $item = explode(DS, removeTrailingSlash($item));

        // single files (without a directory) added in mpd-root-directories requires a full mpd-database update :/
        if(count($item) === 1 && is_file($this->conf['mpd']['musicdir'] . $item[0])) {
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

    public function appendPlaylist($playlist, $targetPosition = FALSE, $priority = FALSE) {
        $counter = 0;
        foreach($playlist->getTracks() as $t) {
            if($t->getError() === 'notfound') {
                continue;
            }
            $trackPath = "\"" . str_replace("\"", "\\\"", $t->getRelPath()) . "\""; 
            $cmdAdd = "add " . $trackPath;
            $cmdPrio = FALSE;
            if($targetPosition !== FALSE) {
                $cmdAdd = "addid " . $trackPath . " " . ($targetPosition+$counter);
                if($priority > 0) {
                    $cmdPrio = 'prio ' . $priority . " " . ($targetPosition+$counter);
                }
            }
            $this->mpd($cmdAdd);
            if($cmdPrio !== FALSE) {
                $this->mpd($cmdPrio);
            }
            $counter ++;
        }
        return $counter;
    }



    //  +------------------------------------------------------------------------+
    //  | Music Player Daemon                                                    |
    //  +------------------------------------------------------------------------+
    public function mpd($command) {
        $socket = $this->getMpdSocket();
        if($socket === FALSE) {
            return FALSE;
        }

        try {
            fwrite($socket, $command . "\n");
        } catch (\Exception $e) {
            $this->container->flash->AddMessageNow('error', $this->container->ll->str('error.mpdwrite'));
            return FALSE;
        }

        $line = trim(fgets($socket, 1024));
        if (substr($line, 0, 3) === 'ACK') {
            fclose($socket);
            $this->container->flash->AddMessageNow('error', $this->container->ll->str('error.mpdgeneral', array($line)));
            return FALSE;
        }

        if (substr($line, 0, 6) !== 'OK MPD') {
            fclose($socket);
            $this->container->flash->AddMessageNow('error', $this->container->ll->str('error.mpdgeneral', array($line)));
            return FALSE;
        }

        $array = array();
        $idx = -1;
        while (!feof($socket)) {
            $line = trim(@fgets($socket, 1024));
            if (substr($line, 0, 3) == 'ACK') {
                fclose($socket);
                $this->container->flash->AddMessageNow('error', $this->container->ll->str('error.mpdgeneral', array($line)));
                return FALSE;
            }
            if (substr($line, 0, 2) == 'OK') {
                fclose($socket);
                return $array;
            }
            $keyValuePair = explode(': ', $line, 2);
            if(count($keyValuePair) !== 2) {
                continue;
            }
            if($command == 'playlistinfo') {
                if($keyValuePair[0] === 'file') {
                    $idx++;
                }
                $array[$idx][$keyValuePair[0]] = iconv('UTF-8', APP_DEFAULT_CHARSET, $keyValuePair[1]);
                continue;
            }
            $array[str_replace(":file", "", $keyValuePair[0])] = iconv('UTF-8', APP_DEFAULT_CHARSET, $keyValuePair[1]);
        }
        fclose($socket);
        $this->container->flash->AddMessageNow('error', $this->container->ll->str('error.mpdconnectionclosed', array($line)));
        return FALSE;
    }

    private function getMpdSocket() {
        $socket = @fsockopen(
            $this->conf['mpd']['host'],
            $this->conf['mpd']['port'],
            $errorNo,
            $errorString,
            3
        );
        if($socket === FALSE) {
            // avoid multiple errormessages generated by multiple mpd calls within one request
            if($this->hasConnectionError === FALSE) {
                useArguments($errorNo, $errorString);
                $this->container->flash->AddMessage('error', $this->container->ll->str('error.mpdconnect'));
                $this->hasConnectionError = TRUE;
            }
            return FALSE;
        }
        return $socket;
    }
}
