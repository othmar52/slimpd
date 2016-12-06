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
class Editorial extends \Slimpd\Models\AbstractModel {
    protected $crdate;
    protected $tstamp;
    protected $itemType;
    protected $itemUid;
    protected $column;
    protected $value;

    use \Slimpd\Traits\PropGroupRelPath; // $relPath, $relPathHash
    use \Slimpd\Traits\PropertyFingerprint; // $fingerprint

    public static $tableName = "editorial";
    public static $repoKey = 'editorialRepo';

    //setter
    public function setCrdate($value) {
        $this->crdate = $value;
        return $this;
    }
    public function setTstamp($value) {
        $this->tstamp = $value;
        return $this;
    }
    public function setItemType($value) {
        $this->itemType = $value;
        return $this;
    }
    public function setItemUid($value) {
        $this->itemUid = $value;
        return $this;
    }
    public function setColumn($value) {
        $this->column = $value;
        return $this;
    }
    public function setValue($value) {
        $this->value = $value;
        return $this;
    }

    // getter
    public function getCrdate() {
        return $this->crdate;
    }
    public function getTstamp() {
        return $this->tstamp;
    }
    public function getItemType() {
        return $this->itemType;
    }
    public function getItemUid() {
        return $this->itemUid;
    }
    public function getColumn() {
        return $this->column;
    }
    public function getValue() {
        return $this->value;
    }
}
