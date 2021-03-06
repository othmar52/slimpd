<?php
namespace Slimpd\Modules\Albummigrator\AbstractTests;
use Slimpd\Utilities\RegexHelper as RGX;
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
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License
 * for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/*
 * Number and Artist or Title will be extracted from inputs like
 * pattern: 01 Juno Reactor
 * pattern: 01. Juno Reactor
 * pattern: [01] Juno Reactor
 * pattern: [01] - Juno Reactor
 * pattern: [01]. Juno Reactor
 */

class NumberGlueNoMinus extends \Slimpd\Modules\Albummigrator\AbstractTests\AbstractTest {
    public $isAlbumWeight = 0.8;

    public function __construct($input, &$trackContext, &$albumContext, &$jumbleJudge) {
        parent::__construct($input, $trackContext, $albumContext, $jumbleJudge);
        $this->pattern = "/^" . RGX::MAY_BRACKET . RGX::NUM . RGX::MAY_BRACKET. RGX::GLUE . RGX::NO_MINUS . "$/i";
        return $this;
    }

    public function run() {
        if(preg_match($this->pattern, $this->input, $matches)) {
            $this->matches = $matches;
            $this->result = 'number-anything';
            return;
        }
        $this->result = 0;
    }
}
