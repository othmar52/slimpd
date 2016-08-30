<?php
namespace Slimpd\Modules\sphinx;

class Sphinx {
	public static function getPdo() {
		$sphinxConf = \Slim\Slim::getInstance()->config["sphinx"];
		self::defineSphinxConstants($sphinxConf);
		return new \PDO(
			"mysql:host=".$sphinxConf["host"].";port=". $sphinxConf["port"] .";charset=utf8;", "",""
		);
	}

	public static function defineSphinxConstants($sphinxConf) {
		foreach(["freq_threshold", "suggest_debug", "length_threshold", "levenshtein_threshold", "top_count"] as $var) {
			$constName = strtoupper($var);
			$constValue = intval($sphinxConf[$var]);
			if (defined($constName) === TRUE) {
				continue;
			}
			define ($constName, $constValue);
		}
	}
}
