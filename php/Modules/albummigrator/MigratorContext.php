<?php
namespace Slimpd\Modules\albummigrator;
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
 * FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License
 * for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

trait MigratorContext {
	use \Slimpd\Traits\PropGroupTypes; // artist, genre, label
	protected $config;
	protected $zeroWhitelist;
	protected $rawTagRecord; // database record as array
	protected $rawTagArray;	// unserialized array of field rawtagdata.tagData
	public $recommendations = array();
	
	private function configBasedSetters() {
		foreach($this->config as $confSection => $rawTagPaths) {
			if(preg_match("/^" . $this->confKey . "(.*)$/", $confSection, $matches)) {
				$this->runSetters($matches[1], $rawTagPaths);
			}
		}
	}
	
	private function runSetters($setterName, $rawTagPaths) {
		if(method_exists($this, $setterName) === FALSE) {
			cliLog(" invalid config. setter " . $setterName . " does not exists", 10, "red");
			return;
		}

		foreach($rawTagPaths as $rawTagPath) {
			$foundValue = $this->extractTagString(
				recursiveArrayParser(
					trimExplode(".", $rawTagPath),
					$this->rawTagArray
				)
			);
			if($foundValue === FALSE || $foundValue === "0") {
				// nothing to do in case we have no value
				continue;
			}

			// preserve priority by config
			// do not overwrite value in case a previous setter already populated the property
			$getterName = "g" . substr($setterName, 1);
			if(strlen($this->$getterName()) === 0) {
				$this->$setterName($foundValue);
			}

			// but add to recommendations in any case
			$this->recommendations[$setterName][] = $foundValue;
		}
	}

	private function extractTagString($mixed) {
		$out = '';
		if(is_string($mixed))	{ $out = $mixed; }
		if(is_array($mixed))	{ $out = join (", ", $mixed); }
		if(is_bool($mixed))		{ $out = ($mixed === TRUE) ? "1" : "0"; }
		if(is_int($mixed))		{ $out = $mixed; }
		if(is_float($mixed))	{ $out = $mixed; }
		if(trim($out) === '')	{ return FALSE; }
		return trim(flattenWhitespace(remU(strip_tags($out))));
	}
	
	public function recommend($properties) {
		cliLog("  " .__CLASS__ . " recommendations", 10, "purple");
		foreach($properties as $setterName => $value) {
			$cleanValue = fixCaseSensitivity(trim(flattenWhitespace(remU($value))));
			$caseFunc = ($setterName === "setCatalogNr") ? "strtoupper" : "fixCaseSensitivity";
			$caseFunc($cleanValue);
			if($cleanValue === "") {
				continue;
			}
			cliLog("    " . $setterName . ": " . $cleanValue, 10, "cyan");
			$this->recommendations[$setterName][] = $cleanValue;
			\Slimpd\Modules\albummigrator\TrackRecommendationsPostProcessor::postProcess($setterName, $cleanValue, $this);
		}
		cliLog(" ", 10);
	}
	
	public function getMostScored($setterName) {
		// without recommendations return instance property
		if(array_key_exists($setterName, $this->recommendations) === FALSE) {
			$getterName = "g" . substr($setterName, 1);
			return $this->$getterName();
		}

		if(count($this->recommendations[$setterName]) === 1) {
			return $this->recommendations[$setterName][0];
		}
		$mostRelevant = uniqueArrayOrderedByRelevance($this->recommendations[$setterName]);
		return array_shift($mostRelevant);
	}
	
	public function getAllRecommendations($setterName) {
		// without recommendations return instance property
		if(array_key_exists($setterName, $this->recommendations) === FALSE) {
			$getterName = "g" . substr($setterName, 1);
			return [ $this->$getterName() ];
		}
		// without recommendations return 
		if(count($this->recommendations[$setterName]) === 1) {
			return [ $this->recommendations[$setterName][0] ];
		}
		return array_unique($this->recommendations[$setterName]);
	}
}

