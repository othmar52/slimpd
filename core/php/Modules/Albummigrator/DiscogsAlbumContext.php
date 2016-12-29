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
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License
 * for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

class DiscogsAlbumContext extends \Slimpd\Models\Album {
    protected $artistString;
    protected $genreString;
    protected $genreList;
    protected $labelString;
    public function __construct(\Slimpd\Models\DiscogsItem $discogsItem, $container) {
        $this->container = $container;
        $this->db = $container->db;
        $this->ll = $container->ll;
        $this->conf = $container->conf;
        $this->setPropertiesByApiResponse($discogsItem->getResponse(TRUE));
    }

    public function setPropertiesByApiResponse($apiResponse) {
        $artists = [];
        foreach($apiResponse['artists'] as $artist) {
            $artists[] = $artist['name'];
        }
        $this->setArtistString(join(",", $artists));

        $apiResponse['styles'] = (isset($apiResponse['styles']) === TRUE) ? $apiResponse['styles'] : array();
        foreach(array_merge($apiResponse['genres'], $apiResponse['styles']) as $genre) {
            $this->appendGenreString($genre);
        }
        $this->setGenreString(join(", ", $this->getGenreList()));
        $this->setTitle(isset($apiResponse['title']) ? $apiResponse['title'] : "");
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

    public function setGenreString($value) {
        $this->genreString = $value;
        return $this;
    }
    public function getGenreString() {
        return $this->genreString;
    }

    public function setGenreList($value) {
        $this->genreList = $value;
        return $this;
    }
    public function getGenreList() {
        return $this->genreList;
    }
    public function appendGenreString($value) {
        return $this->genreList[] = $value;
    }

    public function setLabelString($value) {
        $this->labelString = $value;
        return $this;
    }
    public function getLabelString() {
        return $this->labelString;
    }
}
