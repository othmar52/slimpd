<?php
namespace Slimpd\Modules\Albummigrator\AbstractTests;
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

abstract class AbstractTest {
    public $isAlbumWeight = 1;
    public $matches = array();
    public $pattern;
    public $result;
    public $input;
    public $trackContext;
    public $albumContext;
    public $jumbleJudge;

    public function __construct($input, &$trackContext, &$albumContext, &$jumbleJudge) {
        $this->input = $input;
        $this->trackContext = $trackContext;
        $this->albumContext = $albumContext;
        $this->jumbleJudge = $jumbleJudge;
        return $this;
    }

    public function run() { }

    public function scoreMatches() { }
}
