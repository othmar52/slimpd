<?php
namespace Slimpd\Modules\localization;
/* Copyright
 *
 */
class Localization {
	public static $lang = array();
	
	public function __construct() {
		// TODO: caching of parsed ll-config
		// TODO: check if file is readable
		// read config stuff
		$reflectedClass = new \ReflectionClass('\Slimpd\Modules\localization\Localization');
		$reflectedClass->setStaticPropertyValue('lang', parse_ini_file(APP_ROOT . "config/i18n.ini", FALSE));
	}
	
	public static function str($itemkey, $vars = array()) {
		$checkLanguages = array(
			\Slim\Slim::getInstance()->config['config']['langkey'],
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
