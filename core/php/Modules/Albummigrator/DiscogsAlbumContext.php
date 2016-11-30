<?php
namespace Slimpd\Modules\Albummigrator;
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

class DiscogsAlbumContext extends \Slimpd\Models\Album {
    protected $artistString;
    protected $labelString;
    public function __construct(\Slimpd\Models\DiscogsItem $discogsItem, $container) {
        $this->container = $container;
        $this->db = $container->db;
        $this->ll = $container->ll;
        $this->conf = $container->conf;
        $this->setPropertiesByApiResponse($discogsItem->getResponse(TRUE));
    }

    public function setPropertiesByApiResponse($apiResponse) {
        foreach($apiResponse['artists'] as $artist) {
            $this->artistString .= $artist['name'] . ",";
        }
        
        $apiResponse['styles'] = (isset($apiResponse['styles']) === TRUE) ? $apiResponse['styles'] : array();
        #$this->albumAttributes['artist'] = substr($this->albumAttributes['artist'],0,-1);
        $this->setTitle(isset($apiResponse['title']) ? $apiResponse['title'] : "");
        #$this->albumAttributes['genre'] = join(",", array_merge($apiResponse['genres'], $apiResponse['styles']));
        $this->setYear(isset($apiResponse['released']) ? $apiResponse['released'] : "");
        
        // only take the first label/CatNo - no matter how many are provided by discogs
        if(isset($apiResponse['labels'][0]) === TRUE) {
            $this->setLabelString($apiResponse['labels'][0]['name']);
            $this->setCatalogNr($apiResponse['labels'][0]['catno']);
        }
    }
    
    public function setArtistString($value) {
        $this->artistString = $value;
        return $this;
    }
    public function getArtistString() {
        return $this->artistString;
    }
    
    public function setLabelString($value) {
        $this->labelString = $value;
        return $this;
    }
    public function getLabelString() {
        return $this->labelString;
    }
}
