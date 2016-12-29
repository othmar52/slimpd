<?php
namespace Slimpd\Modules\Localization;
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

class Localization {
    protected $lang = array();
    protected $preferedLang;

    public function __construct($preferedLang) {
        $this->preferedLang = $preferedLang;
        // TODO: caching of parsed ll-config
        // TODO: check if file is readable
        // read config stuff
        $this->lang = parse_ini_file(APP_ROOT . "core/config/i18n.ini", FALSE);
    }

    public function str($itemkey, $vars = array()) {
        $checkLanguages = array(
            $this->preferedLang,
            'en' // fallback language
        );
        foreach($checkLanguages as $langkey) {
            if(isset($this->lang[$langkey . '.' . $itemkey])) {
                if(count($vars) === 0) {
                    return $this->lang[$langkey . '.' . $itemkey];
                }
                return vsprintf($this->lang[$langkey . '.' . $itemkey], $vars);
            }
        }
        return 'TRNSLT:' . $itemkey;
    }
}
