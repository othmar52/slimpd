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
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License
 * for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
abstract class AbstractTrack extends \Slimpd\Models\AbstractFilesystemItem {
    use \Slimpd\Traits\PropertyLastScan;    // $lastScan
    use \Slimpd\Traits\PropertyMimeType;    // $mimeType
    use \Slimpd\Traits\PropertyFingerprint; // $fingerprint
    use \Slimpd\Traits\PropGroupAudio;        
    use \Slimpd\Traits\PropGroupVideo;

    protected $title;
    protected $year;
    protected $comment;
    protected $trackNumber;
    protected $catalogNr;

    protected $error;

    // getter
    public function getTitle() {
        return $this->title;
    }
    public function getYear() {
        return $this->year;
    }
    public function getComment() {
        return $this->comment;
    }
    public function getTrackNumber() {
        return $this->trackNumber;
    }
    public function getCatalogNr() {
        return $this->catalogNr;
    }

    public function getError() {
        return $this->error;
    }

    // setter
    public function setTitle($value) {
        $this->title = $value;
        return $this;
    }
    public function setYear($value) {
        $this->year = $value;
        return $this;
    }
    public function setComment($value) {
        $this->comment = $value;
        return $this;
    }
    public function setTrackNumber($value) {
        $this->trackNumber = $value;
        return $this;
    }
    public function setCatalogNr($value) {
        $this->catalogNr = $value;
        return $this;
    }

    public function setError($value) {
        $this->error = $value;
        return $this;
    }
}
