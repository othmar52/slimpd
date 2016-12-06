<?php
namespace Slimpd\Repositories;
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
 * FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License
 * for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

class DiscogsitemRepo extends \Slimpd\Repositories\BaseRepository {
    public static $tableName = 'discogsapicache';
    public static $classPath = '\Slimpd\Models\Discogsitem';
    public $trackContexts = array();
    public $albumContext;

    public function retrieveAlbum($releaseId = FALSE) {
        if($releaseId === FALSE) {
            return $this;
        }
        if(is_numeric($releaseId) === FALSE || $releaseId < 1) {
            $this->container->flash->AddMessage('error', $this->container->ll->str('error.discogsid'));
            return;
        }
        $instance = new \Slimpd\Models\Discogsitem();
        $instance->setType('release');
        $instance->setExtid((int)$releaseId);

        $this->fetch($instance);
        $this->convertApiResponseToContextItems($instance);

        return $instance;
    }

    public function convertApiResponseToContextItems(&$instance) {
        // create DiscogsAlbumContext based on api response
        $this->albumContext = new \Slimpd\Modules\Albummigrator\DiscogsAlbumContext($instance, $this->container);
        //echo "<pre>" . print_r($data = $instance->getResponse(TRUE));die;
        // create DiscogsTrackContext instances for each provided track
        $counter = 0;
        foreach($instance->getResponse(TRUE)['tracklist'] as $idx => $trackData) {
            if($trackData['type_'] !== 'track') {
                // skip stuff like type:heading @see: discogs-id: 1008775
                continue;
            }
            $this->trackContexts[$counter] = new \Slimpd\Modules\Albummigrator\DiscogsTrackContext($instance, $counter, $this->container);
            $counter++;
        }
    }

    public function fetch(&$instance) {
        if($instance->getExtid() < 1 || !$instance->getType()) {
            return FALSE;
        }
        $item = $this->getInstanceByAttributes(
            ['extid' => $instance->getExtid(), 'type' => $instance->getType()]
        );
        if($item !== NULL) {
            $instance->setResponse($item->getResponse());
            return;    
        }
        $client = \Discogs\ClientFactory::factory([
            'defaults' => [
                'headers' => ['User-Agent' => $this->conf['discogsapi']['useragent']],
            ]
        ]);

        $getter = 'get' . ucfirst($instance->getType());
        $response = $client->$getter(['id' => $instance->getExtid()]);

        $instance->setTstamp(time());
        $instance->setResponse(serialize($response));

        $this->insert($instance);
    }

    public function guessTrackMatch(&$instance, $rawTagDataInstances) {
        $matchScore = array();
        $data = $instance->getResponse(TRUE);

        // TODO:
        // fetch rawtablob and create trackContext items
        return;

        foreach($rawTagDataInstances as $rawItem) {
            $localStrings = [
                $rawItem->getArtist(),
                $rawItem->getTitle(),
                $rawItem->getTrackNumber(),
                basename($rawItem->getRelPath())
            ];
            $counter = 0;
            foreach($data['tracklist'] as $t) {
                $counter++;
                $extIndex = $instance->getExtid() . '-' . $counter;

                $extArtistString = '';
                foreach(((isset($t['artists']) === TRUE) ? $t['artists'] : $data['artists']) as $a) {
                    $extArtistString .= $a['name'] . ' ';
                }

                if(isset($matchScore[$rawItem->getUid()][$extIndex]) === FALSE) {
                    $matchScore[$rawItem->getUid()][$extIndex] = 0;
                }
                $discogsStrings = [
                    $extArtistString,
                    $t['title'],
                    $t['position']
                ];

                foreach($discogsStrings as $discogsString) {
                    foreach($localStrings as $localString) {
                        $matchScore[$rawItem->getUid()][$extIndex] += $this->getMatchStringScore($discogsString, $localString);
                    }
                }

                // in case we have a discogs duration compare durations
                if(strlen($t['duration']) > 0) {
                    $extSeconds = timeStringToSeconds($t['duration']);
                    $higher = $extSeconds;
                    $lower =  $rawItem->getMiliseconds();
                    if($rawItem->getMiliseconds() > $extSeconds) {
                        $higher = $rawItem->getMiliseconds();
                        $lower =  $extSeconds;
                    }
                    $matchScore[$rawItem->getUid()][$extIndex] += floor($lower/($higher/100));
                }
            }
        }
        $return = array();
        foreach($matchScore as $rawIndex => $scorePairs) {
            arsort($scorePairs);
            $return[$rawIndex] = $scorePairs;
        }
        return $return;
        #echo "<pre>" . print_r($return,1); die();
        #echo "<pre>" . print_r($rawItem,1); die();
    }

    protected function getMatchStringScore($string1, $string2) {
        if(strtolower(trim($string1)) == strtolower(trim($string2))) {
            return 100;
        }
        return similar_text($string1, $string2);
    }

    public function fetchRenderItems(&$renderItems, $discogsitemInstance) {
        useArguments($renderItems, $directoryInstance);
        // nothing to fetch for this model...
        return;
    }
}
