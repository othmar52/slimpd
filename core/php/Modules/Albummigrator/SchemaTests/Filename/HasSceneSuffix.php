<?php
namespace Slimpd\Modules\Albummigrator\SchemaTests\Filename;
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

/*
 * pattern: *-cms.mp3
 */
class HasSceneSuffix extends \Slimpd\Modules\Albummigrator\AbstractTests\AbstractTest {
    // TODO: it makes sense to weight a lot in case its the same but ignore in case its not the same suffix
    // for now dont weight at all for handleAsAlbum decision
    public $isAlbumWeight = 0.01;
    
    public function __construct($input, &$trackContext, &$albumContext, &$jumbleJudge) {
        parent::__construct($input, $trackContext, $albumContext, $jumbleJudge);
        $this->pattern = "/" . RGX::SCENE . RGX::EXT . "$/i";
        return $this;
    }
    
    public function run() {
        if(preg_match($this->pattern, $this->input, $matches)) {
            $this->matches = $matches;
            $this->result = $matches[1];
            return;
        }
        $this->result = '';
    }
}
