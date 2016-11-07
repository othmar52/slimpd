<?php
namespace Slimpd\Modules\Albummigrator;
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
			if(
				$setterName === "setMimeType" ||
				$setterName === "setMiliseconds" ||
				$setterName === "setFingerprint" ||
				$setterName === "setAudioDataformat" ||
				$setterName === "setAudioComprRatio" ||
				$setterName === "setAudioBitrate" ||
				$setterName === "setAudioBitrateMode" ||
				$setterName === "setAudioEncoder" ||
				$setterName === "setAudioBitsPerSample" ||
				$setterName === "setAudioLossless" ||
				$setterName === "setAudioSamplerate"
			) {
				continue;
			}
			cliLog("configBasedSetter: " . $setterName, 10, "purple");

			// but add to recommendations in any case (higher scoring for real tag attributes)
			$this->recommend([$setterName => $foundValue], 2);
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
		return trim(flattenWhitespace(unifyHyphens(unifyBraces(remU(strip_tags($out))))));
	}
	
	public function recommend($properties, $score = 1) {
		cliLog("  " .get_called_class() . " recommendations", 10, "purple");
		foreach($properties as $setterName => $value) {
			$cleanValue = fixCaseSensitivity(trim(flattenWhitespace(remU($value))));
			$cleanValue = str_ireplace(
				["&amp;", "aaeao"],
				["&"],
				$cleanValue
			);
			$caseFunc = ($setterName === "setCatalogNr") ? "strtoupper" : "fixCaseSensitivity";
			$cleanValue = $caseFunc($cleanValue);
			if($cleanValue === "") {
				continue;
			}
			cliLog("    [".($score>0?"+":"") .$score. "]" . $setterName . ": " . $cleanValue, 10, "cyan");

			$this->setRecommendationEntry($setterName, $cleanValue, $score);
			\Slimpd\Modules\Albummigrator\TrackRecommendationsPostProcessor::postProcess($setterName, $cleanValue, $this);
		}
		cliLog(" ", 10);
	}
	
	/**
	 * checks if array key exists before scoring
	 */
	public function setRecommendationEntry($setterName, $value, $score) {
		if(isset($this->recommendations[$setterName][$value]) === FALSE) {
			$this->recommendations[$setterName][$value] = 0;
		}
		$this->recommendations[$setterName][$value] += $score;
	}

	
	public function getMostScored($setterName) {
		// without recommendations return instance property
		if(array_key_exists($setterName, $this->recommendations) === FALSE) {
			$getterName = "g" . substr($setterName, 1);
			return $this->$getterName();
		}

		$highestScore = array_keys($this->recommendations[$setterName], max($this->recommendations[$setterName]));

		// there is only one item with highscore
		if(count($highestScore) === 1) {
			return $highestScore[0];
		}

		// hard to decide what to take if we have same score for different values
		// lets take the longest string :)
		$lengths = array_map('strlen', $highestScore);
		$maxLength = max($lengths);
		$index = array_search($maxLength, $lengths);
		return $highestScore[$index];
	}
	
	public function getAllRecommendations($setterName) {
		// without recommendations return instance property
		if(array_key_exists($setterName, $this->recommendations) === FALSE) {
			$getterName = "g" . substr($setterName, 1);
			return [ $this->$getterName() ];
		}
		return array_unique(array_keys($this->recommendations[$setterName]));
	}

	public function dumpSortedRecommendations() {
		$setterLength = 14;
		$scoreLength = 5;
		cliLog(str_repeat("―", 90), 10, "purple");
		cliLog("property      | score |  value", 10);
		cliLog(str_repeat("―", 90), 10, "purple");
		foreach($this->recommendations as $setterName => $rec) {
			arsort($rec, SORT_NUMERIC);
			$innerLoop = 0;
			foreach($rec as $value => $score) {
				$innerLoop++;
				$prefix = str_repeat(" ", $setterLength);
				$color = "darkgray";
				if($innerLoop === 1) {
					$prefix = str_pad($setterName, $setterLength, " ", STR_PAD_RIGHT);
					$color = "";
				}
				$scoreString = str_pad($score, $scoreLength, " ", STR_PAD_LEFT);
				cliLog($prefix . "| " . $scoreString . " |  " . $value, 10, $color);
			}
			cliLog(" ", 10);
			cliLog(str_repeat("―", 90), 10, "purple");
		}
	}
}
