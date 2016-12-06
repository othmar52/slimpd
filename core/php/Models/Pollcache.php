<?php
namespace Slimpd\Models;
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
class Pollcache extends \Slimpd\Models\AbstractModel {
    protected $microtstamp;
    protected $type;
    protected $deckindex;
    protected $success;
    protected $ipAddress;
    protected $port;
    protected $response;

    public static $tableName = 'pollcache';

    public function setMicrotstamp($value) {
        $this->microtstamp = $value;
        return $this;
    }

    public function getMicrotstamp() {
        return $this->microtstamp;
    }


    public function setType($value) {
        $this->type = $value;
        return $this;
    }

    public function getType() {
        return $this->type;
    }


    public function setDeckindex($value) {
        $this->deckindex = $value;
        return $this;
    }

    public function getDeckindex() {
        return $this->deckindex;
    }


    public function setSuccess($value) {
        $this->success = $value;
        return $this;
    }

    public function getSuccess() {
        return $this->success;
    }


    public function setIpAddress($value) {
        $this->ipAddress = $value;
        return $this;
    }

    public function getIpAddress() {
        return $this->ipAddress;
    }


    public function setPort($value) {
        $this->port = $value;
        return $this;
    }

    public function getPort() {
        return $this->port;
    }


    public function setResponse($value) {
        $this->response = $value;
        return $this;
    }

    public function getResponse() {
        return $this->response;
    }

}
