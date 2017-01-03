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
    use \Slimpd\Traits\PropertyTitle;       // title
    use \Slimpd\Traits\PropertyLastScan;    // lastScan
    use \Slimpd\Traits\PropertyMimeType;    // mimeType
    use \Slimpd\Traits\PropertyFingerprint; // fingerprint
    use \Slimpd\Traits\PropertyCatalogNr;   // catalogNr
    use \Slimpd\Traits\PropGroupAudio;      // miliseconds, audioBitrate, audioBitsPerSample, audioSampleRate, audioChannels, audioLossless, audioComprRatio, audioDataformat, audioEncoder, audioProfile
    use \Slimpd\Traits\PropGroupVideo;      // videoDataformat, videoCodec, videoResolutionX, videoResolutionY, videoFramerate
    use \Slimpd\Traits\PropertyError;       // error
    protected $year;
    protected $comment;
    protected $trackNumber;

    // getter
    public function getYear() {
        return $this->year;
    }
    public function getComment() {
        return $this->comment;
    }
    public function getTrackNumber() {
        return $this->trackNumber;
    }


    // setter
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
}
