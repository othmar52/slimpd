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
class Bitmap extends \Slimpd\Models\AbstractFilesystemItem {
    use \Slimpd\Traits\PropertyMimeType;

    protected $width;
    protected $height;
    protected $bghex;
    
    protected $albumUid;
    protected $trackUid;
    protected $embedded;
    protected $fileName;
    protected $pictureType;
    protected $sorting;
    protected $hidden;
    protected $error;
    
    public static $tableName = 'bitmap';
    public static $repoKey = 'bitmapRepo';


    //setter
    public function setWidth($value) {
        $this->width = $value;
        return $this;
    }
    public function setHeight($value) {
        $this->height = $value;
        return $this;
    }
    public function setBghex($value) {
        $this->bghex = $value;
        return $this;
    }
    public function setAlbumUid($value) {
        $this->albumUid = $value;
        return $this;
    }
    public function setTrackUid($value) {
        $this->trackUid = $value;
        return $this;
    }
    public function setEmbedded($value) {
        $this->embedded = $value;
        return $this;
    }
    public function setFileName($value) {
        $this->fileName = $value;
        return $this;
    }
    public function setPictureType($value) {
        $this->pictureType = $value;
        return $this;
    }
    public function setSorting($value) {
        $this->sorting = $value;
        return $this;
    }
    public function setHidden($value) {
        $this->hidden = $value;
        return $this;
    }
    public function setError($value) {
        $this->error = $value;
        return $this;
    }

    // getter
    public function getWidth() {
        return $this->width;
    }
    public function getHeight() {
        return $this->height;
    }
    public function getBghex() {
        return $this->bghex;
    }
    public function getAlbumUid() {
        return $this->albumUid;
    }
    public function getTrackUid() {
        return $this->trackUid;
    }
    public function getEmbedded() {
        return $this->embedded;
    }
    public function getFileName() {
        return $this->fileName;
    }
    public function getPictureType() {
        return $this->pictureType;
    }
    public function getSorting() {
        return $this->sorting;
    }
    public function getHidden() {
        return $this->hidden;
    }
    public function getError() {
        return $this->error;
    }
}
