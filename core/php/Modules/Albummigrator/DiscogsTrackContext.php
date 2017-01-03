<?php
namespace Slimpd\Modules\Albummigrator;
use \Slimpd\Utilities\RegexHelper as RGX;
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
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License
 * for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

class DiscogsTrackContext extends \Slimpd\Models\Track {
    use \Slimpd\Modules\Albummigrator\TrackArtistExtractor; // regularArtists, featArtists, remixArtists, artistString, titleString

    protected $album;

    public $idx;

    public function __construct(\Slimpd\Models\DiscogsItem $discogsItem, $idx, $container) {
        $this->idx = $idx;        
        $this->container = $container;
        $this->db = $container->db;
        $this->ll = $container->ll;
        $this->setPropertiesByApiResponse($discogsItem->getResponse(TRUE));
    }

    protected function setPropertiesByApiResponse($apiResponse) {
        #echo "<pre>";print_r($apiResponse);die;
        $counter = -1;
        foreach($apiResponse['tracklist'] as $trackData) {
            if($trackData['type_'] !== 'track') {
                // skip stuff like type:heading @see: discogs-id: 1008775
                continue;
            }
            $counter++;
            if($counter !== $this->idx) {
                continue;
            }
            $this->setTrackNumber($trackData['position']);
            $this->setFeaturedArtistsByApiResponse($trackData);
            $this->setArtistsByApiResponse($trackData);
            $this->setTitleString($trackData['title']);
            if(strlen($trackData['duration']) > 0) {
                $this->setMiliseconds(timeStringToSeconds($trackData['duration'])*1000);
            }
        }
    }

    protected function setArtistsByApiResponse($trackData) {
        // use track artists or album artist?
        $trackArtists = (isset($trackData['artists']) === TRUE) ? $trackData['artists'] : $apiResponse['artists'];
        foreach($trackArtists as $artist) {#
            if(array_key_exists($artist['id'], $this->featArtists) === TRUE) {
                // skip already provided featured artists
                continue;
            }
            $this->regularArtists[] = $artist['name'];
        }
        // TODO: move this to class TrackArtistExtractor
        $this->artistString = join(" & ", $this->regularArtists);
        if(count($this->featArtists) > 0) {
            $this->artistString .= " (ft. " . join(" & ", $this->featArtists) . ")";
        }
    }

    protected function setFeaturedArtistsByApiResponse($trackData) {
        if(isset($trackData['extraartists']) === FALSE) {
            return;
        }
        foreach($trackData['extraartists'] as $artist) {
            switch($artist['role']) {
                case 'Featuring':
                    $this->featArtists[ $artist['id'] ] = $artist['name'];
                    break;
                default:
                    // TODO: lets see what other possibilities discogs-API-response is returning...
                    break;
            }
        }
    }

    public function setIdx($value) {
        $this->idx = $value;
        return $this;
    }
    public function getIdx() {
        return $this->idx;
    }
    public function setAlbum($value) {
        $this->album = $value;
        return $this;
    }
    public function getAlbum() {
        return $this->album;
    }
}
