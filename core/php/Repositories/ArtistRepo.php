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
class ArtistRepo extends \Slimpd\Repositories\BaseRepository {
    public static $tableName = 'artist';
    public static $classPath = '\Slimpd\Models\Artist';
    protected $artistBlacklist = array();
    protected $remixerBlacklist = array();


    public function fetchRenderItems(&$renderItems, $artistInstance) {
        $renderItems["artists"][$artistInstance->getUid()] = $artistInstance;
        foreach(trimExplode(",", $artistInstance->getTopLabelUids(), TRUE) as $labelUid) {
            if(isset($renderItems["labels"][$labelUid]) === TRUE) {
                continue;
            }
            $renderItems["labels"][$labelUid] = $this->container->labelRepo->getInstanceByAttributes(["uid" => $labelUid]);
        }
        foreach(trimExplode(",", $artistInstance->getTopGenreUids(), TRUE) as $genreUid) {
            if(isset($renderItems["genres"][$genreUid]) === TRUE) {
                continue;
            }
            $renderItems["genres"][$genreUid] = $this->container->genreRepo->getInstanceByAttributes(["uid" => $genreUid]);
        }
        return;
    }

    protected static function unifyItemnames($items) {
        $return = array();
        foreach($items as $az09 => $itemString) {
            $return[$az09] = $itemString;
        }
        return $return;
    }

    public function getArtistBlacklist() {
        // get unified artist-blacklist
        if(count($this->artistBlacklist) > 0) {
            return $this->artistBlacklist;
        }
        $this->artistBlacklist = array();
        if(isset($this->conf["artist-blacklist"]["blacklist"]) === FALSE) {
            return $this->artistBlacklist;
        }
        foreach(trimExplode("\n", $this->conf["artist-blacklist"]["blacklist"], TRUE) as $term) {

            // TODO: make configurable if 's should be removed
            // good example: "I Need Your Lovin (Popmuschi’s Radar Radio Cut)"
            // bad example:  "Jungle Brother (Stereo MC's Mix)"
            // maybe we need another blacklist's whitelist for this special case :)
            $this->remixerBlacklist["'s " . $term] = 1;

            $this->artistBlacklist[$term] = 1;
            $this->artistBlacklist[" " . $term] = 1;
        }
        #print_r($this->artistBlacklist); die;
        return $this->artistBlacklist;
    }

    public function getRemixerBlacklist() {
        // get unified artist-blacklist
        if(count($this->remixerBlacklist) > 0) {
            return $this->remixerBlacklist;
        }
        $this->getArtistBlacklist();
        return $this->remixerBlacklist;
    }

    public function getUidsByString($itemString) {
        if(trim($itemString) === "") {
            return array("10"); // Unknown
        }

        $classPath = self::$classPath;
        #$class = strtolower($classPath);
        #if(preg_match("/\\\([^\\\]*)$/", $classPath, $matches)) {
        #    $class = strtolower($matches[1]);
        #}
        $class = self::$tableName;

        $this->cacheUnifier($classPath);

        $itemUids = array();
        $tmpGlue = "tmpGlu3";
        foreach(trimExplode($tmpGlue, str_ireplace($this->conf[$class . "-glue"], $tmpGlue, $itemString), TRUE) as $itemPart) {
            $az09 = az09($itemPart);

            if($az09 === "" || isHash($az09) === TRUE) {
                // TODO: is there a chance to translate strings like HASH(0xa54fe70) to an useable string?
                $itemUids[10] = 10; // Unknown Genre
                continue;
            }

            $artistArticle = "";
            // TODO: read articles from config
            foreach(array("The", "Die", ) as $matchArticle) {
                // search for prefixed article
                if(preg_match("/^".$matchArticle." (.*)$/i", $itemPart, $matches)) {
                    $artistArticle = $matchArticle." ";
                    $itemPart = $matches[1];
                    $az09 = az09($itemPart);
                    #var_dump($itemString); die("prefixed-article");
                }
                // search for suffixed article
                if(preg_match("/^(.*)([\ ,]+)".$matchArticle."$/i", $itemPart, $matches)) {
                    $artistArticle = $matchArticle." ";
                    $itemPart = remU($matches[1]);
                    $az09 = az09($itemPart);
                    #var_dump($matches); die("suffixed-article");
                }
            }

            // unify items based on config
            if(isset($this->importerCache[$classPath]["unified"][$az09]) === TRUE) {
                $itemPart = $this->importerCache[$classPath]["unified"][$az09];
                $az09 = az09($itemPart);
            }

            // check if we alread have an id
            // permformance improvement ~8%
            $itemUid = $this->cacheRead($classPath, $az09);
            if($itemUid !== FALSE) {
                $itemUids[$itemUid] = $itemUid;
                continue;
            }

            $query = "SELECT uid FROM " . self::$tableName ." WHERE az09=\"" . $az09 . "\" LIMIT 1;";
            $result = $this->db->query($query);
            $record = $result->fetch_assoc();
            if($record) {
                $itemUid = $record["uid"];
                $itemUids[$record["uid"]] = $record["uid"];
                $this->cacheWrite($classPath, $az09, $record["uid"]);
                continue;
            }

            $instance = new $classPath();
            $instance->setTitle(ucwords(strtolower($itemPart)))
                ->setAz09($az09)
                ->setArticle($artistArticle);

            // TODO: de we need the non-batcher version anymore?
            #$instance->insert();
            #$itemUid = $this->db->insert_id;
            $this->container->batcher->que($instance);
            $itemUid = $instance->getUid();

            $itemUids[$itemUid] = $itemUid;
            $this->cacheWrite($classPath, $az09, $itemUid);
        }
        return $itemUids;
    }
}
