<?php
namespace Slimpd\Modules\localization;
class Localization
{
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
				} else {
					return vsprintf(self::$lang[$langkey . '.' . $itemkey], $vars);
				}
			}
		}
		return 'TRNSLT:' . $itemkey;
	}
}
