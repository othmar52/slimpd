<?php
namespace Slimpd\Traits;
/* Copyright (C) 2017 othmar52 <othmar52@users.noreply.github.com>
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
trait PropGroupExternalIds {

    protected $discogsId;
    protected $rolldabeatsId;
    protected $beatportId;
    protected $junoId;

    // getter
    public function getDiscogsId() {
        return $this->discogsId;
    }
    public function getRolldabeatsId() {
        return $this->rolldabeatsId;
    }
    public function getBeatportId() {
        return $this->beatportId;
    }
    public function getJunoId() {
        return $this->junoId;
    }

    // setter
    public function setDiscogsId($value) {
        $this->discogsId = $value;
        return $this;
    }
    public function setRolldabeatsId($value) {
        $this->rolldabeatsId = $value;
        return $this;
    }
    public function setBeatportId($value) {
        $this->beatportId = $value;
        return $this;
    }
    public function setJunoId($value) {
        $this->junoId = $value;
        return $this;
    }
}
