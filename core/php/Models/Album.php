<?php
namespace Slimpd\Models;
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
class Album extends \Slimpd\Models\AbstractFilesystemItem {
    use \Slimpd\Traits\PropertyTitle;        // title
    use \Slimpd\Traits\PropertyAdded;        // added
    use \Slimpd\Traits\PropertyLastScan;     // lastScan
    use \Slimpd\Traits\PropGroupTypeIds;     // artistUid, labelUid, genreUid
    use \Slimpd\Traits\PropertyTrackCount;   // trackCount
    use \Slimpd\Traits\PropertyCatalogNr;    // catalogNr
    use \Slimpd\Traits\PropGroupExternalIds; // discogsId, rolldabeatsId, beatportId, junoId

    protected $year;
    protected $month;

    protected $discs;

    protected $albumDr;

    protected $isMixed;
    protected $isJumble;
    protected $isLive;


    public static $tableName = 'album'; // TODO remove this as its part of the repo
    public static $repoKey = 'albumRepo';

    //setter
    public function setYear($value) {
        $this->year = $value;
        return $this;
    }
    public function setMonth($value) {
        $this->month = $value;
        return $this;
    }
    public function setDiscs($value) {
        $this->discs = $value;
        return $this;
    }
    public function setAlbumDr($value) {
        $this->albumDr = $value;
        return $this;
    }

    public function setIsMixed($value) {
        $this->isMixed = $value;
        return $this;
    }
    public function setIsJumble($value) {
        $this->isJumble = $value;
        return $this;
    }
    public function setIsLive($value) {
        $this->isLive = $value;
        return $this;
    }


    // getter
    public function getYear() {
        return $this->year;
    }
    public function getMonth() {
        return $this->month;
    }
    public function getDiscs() {
        return $this->discs;
    }
    public function getAlbumDr() {
        return $this->albumDr;
    }

    public function getIsMixed() {
        return $this->isMixed;
    }
    public function getIsJumble() {
        return $this->isJumble;
    }
    public function getIsLive() {
        return $this->isLive;
    }
}
