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
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License
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
    protected $rawTagArray;    // unserialized array of field rawtagdata.tagData
    public $recommendations = array();

    protected function configBasedSetters() {
        foreach($this->config as $confSection => $rawTagPaths) {
            if(preg_match("/^" . $this->confKey . "(.*)$/", $confSection, $matches)) {
                $this->runSetters($matches[1], $rawTagPaths);
            }
        }
    }

    protected function runSetters($setterName, $rawTagPaths) {
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
            if($foundValue === FALSE || $foundValue === "0" || $foundValue === []) {
                // nothing to do in case we have no value
                continue;
            }

            // preserve priority by config
            // do not overwrite value in case a previous setter already populated the property
            $getterName = "g" . substr($setterName, 1);
            $propertyValue = $this->$getterName();
            if(is_array($propertyValue) === TRUE) {
                // getRemixArtists() can be an array or string
                $propertyValue = join(", ", $propertyValue);
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
                $this->$setterName($foundValue);
                continue;
            }
            cliLog("configBasedSetter (".$rawTagPath."): " . $setterName, 10, "purple");

            // but add to recommendations in any case (higher scoring for real tag attributes)
            $this->recommend([$setterName => $foundValue], 2);

            if (\Slimpd\Modules\Albummigrator\TrackContext::class !== get_called_class()) {
                continue;
            }

            if ($setterName === 'setAlbum') {
                cliLog("configBasedSetterAlbum: " . $setterName, 10, "purple");
                $this->albumContext->recommend(['setTitle' => $foundValue], 2, TRUE);
                continue;
            }
            if ($setterName === 'setDiscogsId' || $setterName === 'setLabel' || $setterName === 'setGenre' || $setterName === 'setYear') {
                cliLog("configBasedSetterAlbum: " . $setterName, 10, "purple");
                $this->albumContext->recommend([$setterName => $foundValue], 0.5, TRUE);
            }

        }
    }

    protected function extractTagString($mixed) {
        $out = '';
        if(is_string($mixed))    { $out = $mixed; }
        if(is_array($mixed))    { $out = join (", ", $mixed); }
        if(is_bool($mixed))        { $out = ($mixed === TRUE) ? "1" : "0"; }
        if(is_int($mixed))        { $out = $mixed; }
        if(is_float($mixed))    { $out = $mixed; }
        if(trim($out) === '')    { return FALSE; }
        return unifyAll(strip_tags($out));
    }

    public function recommend($properties, $score = 1, $disablePostProcess = FALSE) {
        cliLog("  " .get_called_class() . " recommendations", 10, "purple");
        foreach($properties as $setterName => $value) {
            if(trim($value) === "") {
                cliLog("  skipping empty value", 10, "darkgray");
                continue;
            }
            $this->setRecommendationEntry($setterName, $value, $score);
            if ($disablePostProcess === FALSE) {
                \Slimpd\Modules\Albummigrator\TrackRecommendationsPostProcessor::postProcess($setterName, $value, $this, $score);
            }
        }
        cliLog(" ", 10);
    }

    /**
     * checks if array key exists before scoring
     */
    public function setRecommendationEntry($setterName, $value, $score) {
        if(trim($value) === '') {
            cliLog("  empty value. skipping", 10, "darkgray");
            return;
        }
        $cleanValue = unifyAll($value);
        $cleanValue = str_ireplace(
            ["&amp;", "aaeao"],
            ["&"],
            $cleanValue
        );
        $cleanValue = str_replace(" & ", " And ", $cleanValue);
        $caseFunc = ($setterName === "setCatalogNr") ? "strtoupper" : "fixCaseSensitivity";
        $cleanValue = $caseFunc($cleanValue);
        if(isset($this->recommendations[$setterName][$cleanValue]) === FALSE) {
            $this->recommendations[$setterName][$cleanValue] = 0;
        }
        cliLog("    [".($score>0?"+":"") .$score. "]" . $setterName . ": " . $cleanValue, 10, "cyan");
        $this->recommendations[$setterName][$cleanValue] += $score;
    }


    public function getMostScored($setterName, $includeNegativeScore = TRUE) {
        // without recommendations return instance property
        if(array_key_exists($setterName, $this->recommendations) === FALSE) {
            $getterName = "g" . substr($setterName, 1);
            return $this->$getterName();
        }

        $highestScore = array_keys(
            $this->recommendations[$setterName],
            max($this->recommendations[$setterName])
        );

        // there is only one item with highscore
        if(count($highestScore) === 1) {
            $highestScoredValue = trim($highestScore[0]);
            if ($includeNegativeScore === TRUE) {
                return $highestScoredValue;
            }
            return $this->recommendations[$setterName][$highestScoredValue] >= 0
                ? $highestScoredValue
                : '';
        }

        // hard to decide what to take if we have same score for different values
        // lets take the first entry
        // TODO respect $includeNegativeScore argument in return value
        return $highestScore[0];
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
            $highscore = 0;
            foreach($rec as $value => $score) {
                $innerLoop++;
                $prefix = str_repeat(" ", $setterLength);
                if($innerLoop === 1) {
                    $highscore = $score;
                    $prefix = str_pad($setterName, $setterLength, " ", STR_PAD_RIGHT);
                }
                // also highlight more recommendations with identical highscore
                $color = ($score === $highscore) ? "" : "darkgray";
                $scoreString = str_pad($score, $scoreLength, " ", STR_PAD_LEFT);
                cliLog($prefix . "| " . $scoreString . " |  " . $value, 10, $color);
            }
            cliLog(" ", 10);
            cliLog(str_repeat("―", 90), 10, "purple");
        }
    }
}
