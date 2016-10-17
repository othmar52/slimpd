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
	public static $lang = array();
	
	public function __construct() {
		// TODO: caching of parsed ll-config
		// TODO: check if file is readable
		// read config stuff
		$reflectedClass = new \ReflectionClass('\Slimpd\Modules\Localization\Localization');
		$reflectedClass->setStaticPropertyValue('lang', parse_ini_file(APP_ROOT . "core/config/i18n.ini", FALSE));
	}
	
	public static function str($itemkey, $vars = array()) {
		$checkLanguages = array(
			// TODO: how to access configured language in slim3?
			'de',#\Slim\Slim::getInstance()->config['config']['langkey'],
			'en' // fallback language
		);
		foreach($checkLanguages as $langkey) {
			if(isset(self::$lang[$langkey . '.' . $itemkey])) {
				if(count($vars) === 0) {
					return self::$lang[$langkey . '.' . $itemkey];
				}
				return vsprintf(self::$lang[$langkey . '.' . $itemkey], $vars);
			}
		}
		return 'TRNSLT:' . $itemkey;
	}

	public static function setLocaleByLangKey($languageKey) {
		switch($languageKey) {
			case 'de':
				setlocale(LC_ALL, array('de_DE.UTF-8','de_DE@euro','de_DE','german'));
				break;
			default:
				// TODO: what is the correct locale-setting for en?
				// make sure this works correctly:
				//   var_dump(basename('musicfiles/testdirectory/Ænima-bla')); die();
				// for now force DE...
				// setlocale(LC_ALL, array('en_EN.UTF-8','en_EN','en_EN'))
				setlocale(LC_ALL, array('de_DE.UTF-8','de_DE@euro','de_DE','german'));
				break;
		}
	}
}
