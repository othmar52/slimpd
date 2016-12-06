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
 * FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License
 * for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
class Discogsitem extends \Slimpd\Models\AbstractModel {
    protected $tstamp;
    protected $type;
    protected $extid;
    protected $response;
    public $trackstrings;
    public $albumAttributes;

    public static $tableName = 'discogsapicache';
    public static $repoKey = 'discogsitemRepo';

    //setter
    public function setTstamp($value) {
        $this->tstamp = $value;
        return $this;
    }
    public function setType($value) {
        $this->type = $value;
        return $this;
    }
    public function setExtid($value) {
        $this->extid = $value;
        return $this;
    }
    public function setResponse($value) {
        $this->response = $value;
        return $this;
    }

    // getter
    public function getTstamp() {
        return $this->tstamp;
    }
    public function getType() {
        return $this->type;
    }
    public function getExtid() {
        return $this->extid;
    }
    public function getResponse($unserialize = FALSE) {
        return ($unserialize === TRUE && is_string($this->response) === TRUE) ? unserialize($this->response) : $this->response;
    }
}
