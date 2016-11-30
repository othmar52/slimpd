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
    public $ipAddress;
    protected $port = 0;
    protected $type = 'xwax';
    protected $pollcache = NULL;

    public $clientPath;
    public $totalDecks;
    public $runCmd;
    public $deckIndex;
    public $loadArgs = '';
    public $notifyJson = NULL;
    public $noCache = FALSE;

    public function __construct($container) {
        $this->ll = $container->ll;
        $this->conf = $container->conf;
        $this->pollcacheRepo = $container->pollcacheRepo;
        $this->trackRepo = $container->trackRepo;
    }

    public function cmd() {
        $useCache = FALSE;

        if($this->runCmd === "get_status") {
            $this->onBeforeGetStatus();
            if($this->pollcache !== NULL) {
                $interval = 2;
                if(getMicrotimeFloat() - $this->pollcache->getMicrotstamp() < $interval) {
                    $useCache = TRUE;
                }
            }
            if($this->noCache === TRUE) {
                $useCache = FALSE;
            }
        }

        if($useCache === FALSE) {
            $execCmd = 'timeout 1 ' . $this->clientPath . " " . $this->ipAddress . " "  . $this->runCmd . " " . $this->deckIndex . " " .$this->loadArgs;
            #var_dump($execCmd); die;
            exec($execCmd, $response);

            if($this->runCmd === "get_status") {
                $this->onAfterGetStatus($response);
            }

        } else {
            $response = unserialize($this->pollcache->getResponse());
        }
        #var_dump($response);die;
        if(isset($response[0]) && $response[0] === "OK") {
            if($this->runCmd !== "get_status") {
                $this->notifyJson = notifyJson($this->ll->str('xwax.cmd.success'), 'success');
                return;
            }
            array_shift($response);
            return $response;
        }
        $this->notifyJson = notifyJson($this->ll->str('xwax.cmd.error'), 'danger');
        return;
    }

    /*
     * check if we have a cached pollresult to avoid xwax-client-penetration caused by multiple web-clients
     **/
    private function onBeforeGetStatus() {
        $this->pollcache = $this->pollcacheRepo->getInstanceByAttributes(
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
        $this->pollcache->setResponse(serialize($response))->setMicrotstamp(getMicrotimeFloat());
        $this->pollcacheRepo->update($this->pollcache);
    }

    public function getCurrentlyPlayedTrack() {
        $deckStatus = self::clientResponseToArray($this->cmd());
        $deckItem = (isset($deckStatus['path']) === TRUE && $deckStatus['path'] !== NULL)
             ? $this->trackRepo->getInstanceByPath($deckStatus['path'], TRUE)
             : NULL;
        return $deckItem;
    }

    public function fetchAllDeckStats() {
        $return = array();
        for($i=0; $i<$this->totalDecks; $i++) {
            // dont try other decks in case first deck fails
            // TODO: as soon as xwax-client supports returning ALL deckstats within a single call, remove this
            $this->deckIndex = $i;
            $response = $this->cmd();
            if(count($response) === 0) {
                return NULL;
            }
            $deckStatus = self::clientResponseToArray($response);
            $deckStatus['item'] = ($deckStatus['path'] !== NULL)
                ? $this->trackRepo->getInstanceByPath($deckStatus['path'], TRUE)->jsonSerialize()
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
                $out[$params[0]] = @$params[1];
            } catch(\Exception $e) {
                $out[$params[0]] = NULL;
            }
        }
        $out['percent'] = 0;
        try {
            if($out['length'] > 0) {
                $out['percent'] = $out['position'] /($out['length']/100);
            }
        } catch(\Exception $e) {
            $out['percent'] = 0;
        }
        $out['state'] = ($out['player_sync_pitch'] != 1) ? 'play' : 'pause';
        return $out;
    }
}
