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
trait PropGroupAudio {
    protected $miliseconds;
    protected $audioBitrate;
    protected $audioBitsPerSample;
    protected $audioSampleRate;
    protected $audioChannels;
    protected $audioLossless;
    protected $audioComprRatio;
    protected $audioDataformat;
    protected $audioEncoder;
    protected $audioProfile;

    // getter
    public function getMiliseconds() {
        return $this->miliseconds;
    }
    public function getAudioBitrate() {
        return $this->audioBitrate;
    }
    public function getAudioBitsPerSample() {
        return $this->audioBitsPerSample;
    }
    public function getAudioSampleRate() {
        return $this->audioSampleRate;
    }
    public function getAudioChannels() {
        return $this->audioChannels;
    }
    public function getAudioLossless() {
        return $this->audioLossless;
    }
    public function getAudioComprRatio() {
        return $this->audioComprRatio;
    }
    public function getAudioDataformat() {
        return $this->audioDataformat;
    }
    public function getAudioEncoder() {
        return $this->audioEncoder;
    }
    public function getAudioProfile() {
        return $this->audioProfile;
    }

    // setter
    public function setMiliseconds($value) {
        $this->miliseconds = $value;
        return $this;
    }
    public function setAudioBitrate($value) {
        $this->audioBitrate = $value;
        return $this;
    }
    public function setAudioBitsPerSample($value) {
        $this->audioBitsPerSample = $value;
        return $this;
    }
    public function setAudioSampleRate($value) {
        $this->audioSampleRate = $value;
        return $this;
    }
    public function setAudioChannels($value) {
        $this->audioChannels = $value;
        return $this;
    }
    public function setAudioLossless($value) {
        $this->audioLossless = $value;
        return $this;
    }
    public function setAudioComprRatio($value) {
        $this->audioComprRatio = $value;
        return $this;
    }
    public function setAudioDataformat($value) {
        $this->audioDataformat = $value;
        return $this;
    }
    public function setAudioEncoder($value) {
        $this->audioEncoder = $value;
        return $this;
    }
    public function setAudioProfile($value) {
        $this->audioProfile = $value;
        return $this;
    }
}
