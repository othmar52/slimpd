<?php
namespace Slimpd\Modules\Albummigrator\SchemaTests\Dirname;
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
 * FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License
 * for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * pattern: Wink_-_Higher_State_of_Consciousness_96_Remixes-CDM-1996-TT
 *          |  |   |                                      | | | |  | |scene
 *          |  |   |                                      | | | |  | └──
 *          |  |   |                                      | | | |year
 *          |  |   |                                      | | | └──
 *          |  |   |                                      | |source
 *          |  |   |                                      | └──
 *          |  |   |albumtitle
 *          |  |   └──
 *          |artist
 *          └──
 *
 * pattern: Jah_Mason-Most_Royal-CD-2004-iLL
 */
class ArtistTitleSourceYearScene extends \Slimpd\Modules\Albummigrator\AbstractTests\AbstractTest {
    
    public function __construct($input, &$trackContext, &$albumContext, &$jumbleJudge) {
        parent::__construct($input, $trackContext, $albumContext, $jumbleJudge);
        $this->pattern = "/^" . RGX::NO_MINUS . RGX::GLUE . RGX::NO_MINUS . RGX::GLUE .
            RGX::SOURCE . RGX::GLUE . 
            RGX::MAY_BRACKET . RGX::YEAR . RGX::MAY_BRACKET .
            RGX::SCENE . "$/";
        return $this;
    }
    
    public function run() {
        if(preg_match($this->pattern, $this->input, $matches)) {
            $this->matches = $matches;
            $this->result = 'artist-title-source-year-scene';
            return;
        }
        $this->result = 0;
    }
    
    public function scoreMatches() {
        cliLog(get_called_class(),10, "purple"); cliLog("  INPUT: " . $this->input, 10);
        if(count($this->matches) === 0) {
            cliLog("  no matches\n ", 10);
            return;
        }
        $this->albumContext->recommend([
            'setArtist' => $this->matches[1],
            'setTitle' => $this->matches[2],
            'setYear' => az09($this->matches[4])
        ]);
        $this->jumbleJudge->albumMigrator->recommendationForAllTracks(
            ['setArtist' => $this->matches[1] ],
            0.1
        );
    }
}
