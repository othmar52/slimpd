<?php
namespace Slimpd\Traits;
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
trait PropGroupVideo {
    protected $videoDataformat;
    protected $videoCodec;
    protected $videoResolutionX;
    protected $videoResolutionY;
    protected $videoFramerate;

    // getter
    public function getVideoDataformat() {
        return $this->videoDataformat;
    }
    public function getVideoCodec() {
        return $this->videoCodec;
    }
    public function getVideoResolutionX() {
        return $this->videoResolutionX;
    }
    public function getVideoResolutionY() {
        return $this->videoResolutionY;
    }
    public function getVideoFramerate() {
        return $this->videoFramerate;
    }

    // setter
    public function setVideoDataformat($value) {
        $this->videoDataformat = $value;
        return $this;
    }
    public function setVideoCodec($value) {
        $this->videoCodec = $value;
        return $this;
    }
    public function setVideoResolutionX($value) {
        $this->videoResolutionX = $value;
        return $this;
    }
    public function setVideoResolutionY($value) {
        $this->videoResolutionY = $value;
        return $this;
    }
    public function setVideoFramerate($value) {
        $this->videoFramerate = $value;
        return $this;
    }
}
