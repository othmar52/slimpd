<?php
namespace Slimpd\Repositories;
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
class GenreRepo extends \Slimpd\Repositories\BaseRepository {
    public static $tableName = 'genre';
    public static $classPath = '\Slimpd\Models\Genre';

    public function fetchRenderItems(&$renderItems, $genreInstance) {
        $renderItems["genres"][$genreInstance->getUid()] = $genreInstance;
        foreach(trimExplode(",", $genreInstance->getTopArtistUids(), TRUE) as $artistUid) {
            if(isset($renderItems["artists"][$artistUid]) === TRUE) {
                continue;
            }
            $renderItems["artists"][$artistUid] = $this->container->artistRepo->getInstanceByAttributes(["uid" => $artistUid]);
        }
        foreach(trimExplode(",", $genreInstance->getTopLabelUids(), TRUE) as $labelUid) {
            if(isset($renderItems["labels"][$labelUid]) === TRUE) {
                continue;
            }
            $renderItems["labels"][$labelUid] = $this->container->labelRepo->getInstanceByAttributes(["uid" => $labelUid]);
        }
        return;
    }

    protected static function unifyItemnames($genres) {
        $return = array();
        foreach($genres as $az09 => $genreString) {
            $return[az09($genreString)] = $genreString;
            $return[$az09] = $genreString;
            $return[$az09 . az09($genreString)] = $genreString;
            if(is_numeric($az09) === FALSE) {
                $return[$az09. 's'] = $genreString;
                if(strlen($az09)>4) {
                    $return[substr($az09,1)] = $genreString;
                    $return[substr($az09,0,-1)] = $genreString;
                }
            }
            // TODO: read from config
            foreach(array('generale', 'general', 'classic', 'allgemein', 'original', 'other', 'engeneral') as $uselessAddon) {
                $return[$uselessAddon . $az09] = $genreString;
                $return[$az09 . $uselessAddon] = $genreString;
                $return[$uselessAddon . az09($genreString)] = $genreString;
                $return[az09($genreString) . $uselessAddon] = $genreString;
            }
        }
        return $return;
    }


    public function parseGenreStringAdvanced($itemString) {
        $finalGenres = array();
        $badChunk = FALSE;
        cliLog("----------GENRE-PARSER----------", 6);
        cliLog("INPUT: " . $itemString, 6);

        $this->parseGenreStringPhase1($itemString, $finalGenres, $badChunk);
        if($badChunk === FALSE) {
            cliLog("  FINAL RESULT: " . join(", ", $finalGenres), 6);
            return $finalGenres;
        }

        $badChunk = FALSE;
        $this->parseGenreStringPhase2($itemString, $finalGenres, $badChunk);
        if($badChunk === FALSE) {
            cliLog("  FINAL RESULT: " . join(", ", $finalGenres), 6);
            return $finalGenres;
        }

        $badChunk = FALSE;
        $this->parseGenreStringPhase3($itemString, $finalGenres, $badChunk);
        if($badChunk === FALSE) {
            cliLog("  FINAL RESULT: " . join(", ", $finalGenres), 6);
            return $finalGenres;
        }

        $badChunk = FALSE;
        $this->parseGenreStringPhase4($itemString, $finalGenres, $badChunk);
        if($badChunk === FALSE) {
            cliLog("  FINAL RESULT: " . join(", ", $finalGenres), 6);
            return $finalGenres;
        }

        $badChunk = FALSE;
        $this->parseGenreStringPhase5($itemString, $finalGenres, $badChunk);
        cliLog("  FINAL RESULT: " . join(", ", $finalGenres), 6);
        return $finalGenres;
    }

    public function parseGenreStringPhase1(&$itemString, &$finalGenres, &$badChunk) {
        cliLog(" Phase 1: check if we do have a string we can work with", 6);
        if(trim($itemString) === '') {
            $finalGenres['unknown'] = "Unknown";
            cliLog("  nothing to do with an emtpy string.", 7);
            return;
        }
        if(isHash($itemString) === TRUE) {
            // TODO: is there a chance to translate strings like HASH(0xa54fe70) to an useable string?
            // TODO: read from config: "importer.unknowngenre"
            $finalGenres['unknown'] = "Unknown";
            cliLog("  nothing to do with an useless hash string.", 7);
            return;
        }
        cliLog("  genre seems useable. continue...", 7);
        $badChunk = TRUE;
    }

    public function parseGenreStringPhase2(&$itemString, &$finalGenres, &$badChunk) {
        cliLog(" Phase 2: check if we do have a single cached genre", 6);
        $itemString = str_replace(array("(",")","[","]", "{","}", "<", ">"), " ", $itemString);
        $az09 = az09($itemString);
        $itemUid = $this->cacheRead(self::$classPath, $az09);
        if($itemUid !== FALSE) {
            $finalGenres[$az09] = $itemString;
            return;
        }
        cliLog("  continue...", 7);
        $badChunk = TRUE;
    }

    public function parseGenreStringPhase3(&$itemString, &$finalGenres, &$badChunk) {
        cliLog(" Phase 3: check if we do have multiple cached genres", 6);
        $classPath = self::$classPath;
        $tmpGlue = "tmpGlu3";
        $chunks = trimExplode($tmpGlue, str_ireplace($this->conf['genre-glue'], $tmpGlue, $itemString), TRUE);
        foreach($chunks as $chunk) {
            $az09 = az09($chunk);

            if(isset($this->importerCache[$classPath]["unified"][$az09])) {
                $finalGenres[$az09] = $this->importerCache[$classPath]["unified"][$az09];
                $itemString = str_ireplace($chunk, "", $itemString);
                cliLog("  FINAL-CHUNK: $chunk = ".$finalGenres[$az09], 7);
                continue;
            }
            // very fuzzy check if we have an url
            if(preg_match("/^(http|www)([a-z0-9\.\/\:]*)\.[a-z]{2,4}$/i", $chunk)) {
                $itemString = str_ireplace($chunk, "", $itemString);
                cliLog("  TRASHING url-chunk: $chunk", 7);
                continue;
            }
            // very fuzzy check if we have an url
            if(preg_match("/(myspace|blogspot).com$/i", $chunk)) {
                $itemString = str_ireplace($chunk, "", $itemString);
                cliLog("  TRASHING: trash url-chunk: $chunk", 7);
                continue;
            }
            cliLog("  BAD-CHUNK: $chunk - entering phase 4...", 7);
            $badChunk = TRUE;
        }

    }

    public function parseGenreStringPhase4(&$itemString, &$finalGenres, &$badChunk) {
        cliLog(" Phase 4: check remaining chunks", 6);
        $classPath = self::$classPath;
        $tmpGlue = "tmpGlu3";
        #print_r($this->conf['genre-replace-chunks']); die();
        // phase 4: tiny chunks
        # TODO: would camel-case splitting make sense?
        $splitBy = array_merge($this->conf['genre-glue'], array(" ", "-", ".", "_", ""));
        $badChunk = FALSE;
        $chunks = trimExplode($tmpGlue, str_ireplace($splitBy, $tmpGlue, $itemString), TRUE);
        foreach($chunks as $chunk) {
            $az09 = az09($chunk);
            if(isset($this->conf['genre-replace-chunks'][$az09])) {
                $itemString = str_ireplace($chunk, $this->conf['genre-replace-chunks'][$az09], $itemString);
                cliLog("  REPLACING $chunk with: ".$this->conf['genre-replace-chunks'][$az09], 7);
            }
            if(isset($this->conf['genre-remove-chunks'][$az09])) {
                $itemString = str_ireplace($chunk, "", $itemString);
                cliLog("  REMOVING: trash url-chunk: $chunk",7);
                continue;
            }
            if(isset($this->importerCache[$classPath]["unified"][$az09])) {
                $finalGenres[$az09] = $this->importerCache[$classPath]["unified"][$az09];
                $itemString = str_ireplace($chunk, "", $itemString);
                cliLog("  FINAL-CHUNK: $chunk = ".$finalGenres[$az09], 7);
                continue;
            }
            if(trim(az09($chunk)) !== '' && trim(az09($chunk)) !== 'and') {
                cliLog("  BAD-CHUNK: $chunk - entering phase 5...", 7);
                $badChunk = TRUE;
            }
        }
    }

    public function parseGenreStringPhase5(&$itemString, &$finalGenres, &$badChunk) {
        cliLog(" Phase 5: check remaining chunks after replacement and removal", 6);
        $classPath = self::$classPath;
        $tmpGlue = "tmpGlu3";
        $splitBy = array_merge($this->conf['genre-glue'], array(" ", "-", ".", "_", ""));
        $badChunk = FALSE;
        $chunks = trimExplode($tmpGlue, str_ireplace($splitBy, $tmpGlue, $itemString), TRUE);
        if(count($chunks) === 1) {
            $az09 = az09($chunks[0]);
            $finalGenres[$az09] = $chunks[0];
            cliLog("  only one chunk left. lets assume \"". $chunks[0] ."\" is a genre", 7);
            return $finalGenres; 
        }
        $joinedChunkRest = strtolower(join(".", $chunks));

        if(isset($this->importerCache[$classPath]["preserve"][$joinedChunkRest]) === TRUE) {
            $finalGenres[az09($joinedChunkRest)] = $this->importerCache[$classPath]["preserve"][$joinedChunkRest];
            cliLog("  found genre based on full preserved pattern: $joinedChunkRest = ".$this->importerCache[$classPath]["preserve"][$joinedChunkRest], 7);
            return $finalGenres;
        }

        cliLog("  REMAINING CHUNKS:" . $joinedChunkRest, 7);
        $foundPreservedMatch = FALSE;
        foreach($this->importerCache[$classPath]["preserve"] as $preserve => $genreString) {
            if(preg_match("/".str_replace(".", "\.", $preserve) . "/", $joinedChunkRest)) {
                $finalGenres[az09($preserve)] = $genreString;
                $foundPreservedMatch = TRUE;
                cliLog("  found genre based on partly preserved pattern: $preserve = ".$genreString, 7);
                $removeChunks = explode('.', $preserve);
                $az09Chunks = array_map('az09', $chunks);
                foreach($removeChunks as $removeChunk) {
                    if(array_search($removeChunk, $az09Chunks) !== FALSE) {
                        unset($chunks[array_search($removeChunk, $az09Chunks)]);
                    }
                }
            }
        }

        // TODO check
        // Coast Hip-Hop, Hardcore Hip-Hop, Gangsta

        // give up and create new genre for each chunk        
        foreach($chunks as $chunk) {
            $az09 = az09($chunk);
            $finalGenres[$az09] = $chunk;
            cliLog("  giving up and creating new genre: $az09 = ".$chunk, 7);
        }
    }

    // check for all uppercase or all lowercase and do apply corrections
    public static function cleanUpGenreStringArray($input) {
        $output = array();

        if(count($input) == 0) {
            return array("unknown" => "Unknown");
        }

        // "Unknown" can be dropped in case we have an additional genre-entry 
        if(count($input) > 1 && $idx = array_search("Unknown", $input)) {
            unset($input[$idx]);
        }

        foreach($input as $item) {
            if(strlen($item) < 2) {
                continue;
            }
            $output[az09($item)] = fixCaseSensitivity($item);
        }
        /*
        // hotfix for bug in parseGenreStringAdvanced()
        // not sure if this bug still exists!?
        # TODO: fix parseGenreStringAdvanced() and remove these lines
        if(isset($output['drum']) === TRUE && isset($output['bass']) === TRUE) {
            unset($output['drum']);
            unset($output['bass']);
            $output['drumandbass'] = "Drum & Bass";
        }
        if(isset($output['deep']) === TRUE && isset($output['house']) === TRUE) {
            unset($output['deep']);
            $output['deephouse'] = "Deep House";
        }
        */

        return $output;
    }

    public function getUidsByString($itemString) {
        $this->cacheUnifier(self::$classPath);
        $this->buildPreserveCache(self::$classPath);

        $genreStringArray = [];
        $tmpGlue = "tmpGlu3";
        foreach(trimExplode($tmpGlue, str_ireplace($this->conf['genre-glue'], $tmpGlue, $itemString), TRUE) as $itemPart) {
            // activate parser
            $genreStringArray = array_merge($genreStringArray, $this->parseGenreStringAdvanced($itemPart));
        }

        // string beautyfying & 1 workaround for a parser bug
        $genreStringArray = self::cleanUpGenreStringArray($genreStringArray);


        #echo "input: $itemString\nresul: " . join(' || ', $genreStringArray) . "\n-------------------\n";
        #ob_flush();


        $itemUids = array();
        foreach($genreStringArray as $az09 => $genreString) {

            // check if we alread have an id
            // permformance improvement ~8%
            $itemUid = $this->cacheRead(self::$classPath, $az09);
            if($itemUid !== FALSE) {
                $itemUids[$itemUid] = $itemUid;
                continue;
            }

            $query = "SELECT uid FROM genre WHERE az09=\"" . $az09 . "\" LIMIT 1;";
            $result = $this->db->query($query);
            $record = $result->fetch_assoc();
            if($record) {
                $itemUid = $record["uid"];
                $itemUids[$record["uid"]] = $record["uid"];
                $this->cacheWrite(self::$classPath, $az09, $record["uid"]);
                continue;
            }

            $instance = new \Slimpd\Models\Genre();
            $instance->setTitle($genreString)->setAz09($az09);

            // TODO: de we need the non-batcher version anymore?
            #$instance->insert();
            #$itemUid = $this->db->insert_id;
            $this->container->batcher->que($instance);
            $itemUid = $instance->getUid();

            $itemUids[$itemUid] = $itemUid;
            $this->cacheWrite(self::$classPath, $az09, $itemUid);
        }

        return $itemUids;

    }

    public function buildPreserveCache($classPath) {
        if(isset($this->importerCache[$classPath]["preserve"]) === TRUE) {
            return;
        }
        if(isset($this->importerCache) === FALSE) {
            $this->importerCache = array();
        }
        // we can only modify a copy and assign it back afterward (Indirect modification of overloaded property)
        $tmpArray = $this->importerCache;
        $tmpArray[$classPath]["preserve"] = array();

        // build a special whitelist
        $recursiveIterator = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($this->conf['genre-preserve-junks']));
        foreach ($recursiveIterator as $leafValue) {
            $keys = array();
            foreach (range(0, $recursiveIterator->getDepth()) as $depth) {
                $keys[] = $recursiveIterator->getSubIterator($depth)->key();
            }
            $tmpArray[$classPath]["preserve"][ join('.', $keys) ] = $leafValue;
        }

        $this->importerCache = $tmpArray;
    }
}
