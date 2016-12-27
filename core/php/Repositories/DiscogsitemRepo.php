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
    public $images = array();

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
        foreach($instance->getResponse(TRUE)['tracklist'] as $trackData) {
            if($trackData['type_'] !== 'track') {
                // skip stuff like type:heading @see: discogs-release-id: 1008775
                continue;
            }
            $this->trackContexts[$counter] = new \Slimpd\Modules\Albummigrator\DiscogsTrackContext($instance, $counter, $this->container);
            $counter++;
        }

        // add images array
        $rawDiscogsData = $instance->getResponse(TRUE);
        if(array_key_exists('images', $rawDiscogsData) === TRUE) {
            $this->images = $rawDiscogsData['images'];
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
        $client = $this->getDiscogsClient();

        $getter = 'get' . ucfirst($instance->getType());
        $response = $client->$getter(['id' => $instance->getExtid()]);

        $instance->setTstamp(time());
        $instance->setResponse(serialize($response));

        $this->insert($instance);
    }

    protected function getDiscogsClient() {
        $client = \Discogs\ClientFactory::factory([
            'defaults' => [
                'headers' => ['User-Agent' => $this->conf['discogsapi']['useragent']],
                'auth' => 'oauth'
            ]
        ]);
        $client = \Discogs\ClientFactory::factory([]);
        $this->mayAttachOauth($client);
        return $client;
    }

    public function mayAttachOauth(&$client) {
        $oauthConf = array(
            'consumer_key' => $this->conf['discogsapi']['consumer_key'],
            'consumer_secret' => $this->conf['discogsapi']['consumer_secret']
        );
        $item = $this->getInstanceByAttributes(['type' => 'oauth_response_token']);
        if($item === NULL) {
            return;
        }
        $oauthConf['token'] = $item->getResponse();
        $item = $this->getInstanceByAttributes(['type' => 'oauth_response_token_secret']);
        if($item === NULL) {
            return;
        }
        $oauthConf['token_secret'] = $item->getResponse();
        $client->getHttpClient()->getEmitter()->attach(
            new \GuzzleHttp\Subscriber\Oauth\Oauth1($oauthConf)
        );
    }

    public function fetchRenderItems(&$renderItems, $discogsitemInstance) {
        useArguments($renderItems, $discogsitemInstance);
        // nothing to fetch for this model...
        return;
    }
}
