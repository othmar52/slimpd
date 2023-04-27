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

class AlbumMigrator {

    use \Slimpd\Traits\PropGroupRelPath; // relPath, relPathHash
    public $conf;
    public $migratorConf;
    protected $rawTagItems;
    protected $trackContextItems;
    protected $albumContextItem;
    protected $jumbleJudge;
    protected $mostRecentAdded;
    public $useBatcher = FALSE;

    public function __construct($container) {
        $this->container = $container;
        $this->db = $container->db;
        $this->ll = $container->ll;
        $this->conf = $container->conf;
    }

    public function run() {
        // create albumContext
        $this->albumContextItem = new \Slimpd\Modules\Albummigrator\AlbumContext($this->container);
        $this->jumbleJudge = new \Slimpd\Modules\Albummigrator\JumbleJudge($this->albumContextItem, $this);

        // create TrackContext for each input item
        foreach($this->rawTagItems as $idx => $rawTagItem) {
            cliLog("=== collecting begin for " . basename($rawTagItem['relPath']) . " " . $rawTagItem['relPathHash'] . " ===", 10, "yellow");
            $this->trackContextItems[$idx] = new \Slimpd\Modules\Albummigrator\TrackContext(
                $rawTagItem, $idx, $this->migratorConf, $this->container, $this->albumContextItem
            );
            // do some characteristics analysis for each "track"
            $this->jumbleJudge->collect($this->trackContextItems[$idx], $this->albumContextItem);
            cliLog("=== collecting end for " . basename($rawTagItem['relPath']) . " " . $rawTagItem['relPathHash'] . " ===", 10, "yellow");
            $this->handleTrackFilemtime($rawTagItem["added"]);
        }

        // make sure manually edited track+album-properties gets applied
        $this->fetchEditorials();

        // decide if bunch should be treated as album or as loose tracks
        $this->jumbleJudge->judge();

        if($this->conf["modules"]["enable_guessing"] == "1") {
            // do some voting for each attribute
            $this->runAttributeScoring();
        }

        // 
        // direcory path is the same for all tracks. copy from first rawTagItem
        $this->albumContextItem->copyBaseProperties($this->rawTagItems[0]);
        cliLog("=== collecting begin for album ===", 10, "yellow");
        $this->albumContextItem->collectAlbumStuff($this, $this->jumbleJudge);
        cliLog("=== collecting end for album ===", 10, "yellow");

        $this->postProcessTrackProperties();

        cliLog("=== postprocessing begin for album ===", 10, "yellow");
        \Slimpd\Modules\Albummigrator\TrackRecommendationsPostProcessor::removePrefixedArtistFromTitle($this->albumContextItem);
        cliLog("=== postprocessing end for album ===", 10, "yellow");

        cliLog("=== recommendations begin for album ===", 10, "yellow");
        $this->albumContextItem->dumpSortedRecommendations();
        cliLog("=== recommendations end for album ===", 10, "yellow");

        $this->albumContextItem->setAdded($this->mostRecentAdded)->migrate($this->trackContextItems, $this->jumbleJudge, $this->useBatcher);

        #print_r($this->jumbleJudge->testResults); die;

        foreach($this->trackContextItems as $trackContextItem) {
            $trackContextItem->setAlbumUid($this->albumContextItem->getUid());
            cliLog("=== recommendations begin for " . basename($trackContextItem->getRelPath()) . " " . $trackContextItem->getRelPathHash() . " ===", 10, "yellow");
            $trackContextItem->dumpSortedRecommendations();
            cliLog("=== recommendations end for " . basename($trackContextItem->getRelPath()) . " " . $trackContextItem->getRelPathHash() . " ===", 10, "yellow");
            $trackContextItem->migrate($this->useBatcher);
            // add the whole bunch of valid and invalid attributes to trackindex table
            $trackContextItem->updateTrackIndex($this->useBatcher);
        }

        cliLog("=== postprocessing begin for album ===", 10, "yellow");
        // at this point we have the final artist uids of tracks
        // lets set the album artist
        $this->albumArtistViceVersaCorrection();
        cliLog("=== postprocessing end for album ===", 10, "yellow");
        // complete embedded bitmaps with albumUid
        // to make sure extracted images will be referenced to an album
        $this->container->bitmapRepo->addAlbumUidToRelDirPathHash($this->getRelDirPathHash(), $this->albumContextItem->getUid());
    }

    /**
     * maybe manually edited properties exists for this tracks or album
     */
    protected function fetchEditorials() {
        foreach($this->trackContextItems as $trackContext) {
            cliLog("=== collecting begin for " . basename($trackContext->getRelPath()) . " " . $trackContext->getRelPathHash() . " ===", 10, "yellow");
            cliLog(__FUNCTION__, 10, 'purple');
            $editorials = $this->container->editorialRepo->getInstancesByAttributes([
                'relPathHash' => $trackContext->getRelPathHash(),
                'itemType' => 'track'
            ]);
            foreach($editorials as $editorial) {
                $trackContext->setRecommendationEntry(
                    $editorial->getColumn(),
                    $editorial->getValue(),
                    100
                );
            }
            cliLog(' ', 10);
            cliLog("=== collecting end for " . basename($trackContext->getRelPath()) . " " . $trackContext->getRelPathHash() . " ===", 10, "yellow");
            cliLog("=== collecting begin for album ===", 10, "yellow");
            foreach($editorials as $editorial) {
                if(in_array($editorial->getColumn(), ['setAlbum', 'setYear', 'setLabel', 'setCatalogNr', 'setDiscogsId', 'setGenre', 'setArtist', 'setAlbumArtist']) === FALSE) {
                    continue;
                }
                $score = 100;
                switch ($editorial->getColumn()) {
                    case 'setAlbum': $setter = 'setTitle'; break;
                    #case 'setAlbumArtist': $setter = 'setArtist'; $score = 200; break;
                    default: $setter = $editorial->getColumn(); break;
                }
                $this->albumContextItem->setRecommendationEntry(
                    $setter,
                    $editorial->getValue(),
                    $score
                );
            }
            cliLog("=== collecting end for album ===", 10, "yellow");
        }
    }
    /**
     * TODO: how to handle albums/compilations where album artist does not appear on any track?
     * example compilation: "Sly & Robbie - LateNightTales"
     * or https://www.discogs.com/de/release/2902692-Wolf-Lamb-vs-Soul-Clap-DJ-KiCKS-Exclusives-EP1
     */
    protected function albumArtistViceVersaCorrection() {
        cliLog(__FUNCTION__, 10, "purple");

        // check if we have a single albumArtist
        // this will disable fetching artists from tracks
        // this will avoid "Various Artists" as well...
        if (array_key_exists('setAlbumArtist', $this->albumContextItem->recommendations)) {
            if (count($this->albumContextItem->recommendations['setAlbumArtist']) === 1) {
                $artistString = array_key_first($this->albumContextItem->recommendations['setAlbumArtist']);
                cliLog('forcing single AlbumArtist: ' . $artistString, 10, "yellow");
                $this->injectAlbumArtistUid(
                    $this->container->artistRepo->getUidsByString($artistString)
                );
                return;
            }
        }

        // collect all final artist-uids of each album-track
        $trackArtistUids = "";
        foreach($this->trackContextItems as $trackContextItem) {
            $trackArtistUids .= join(
                ",",
                [
                    $trackContextItem->getArtistUid(),
                    // TODO: does it make sense to added featured artists and remixer artists to album-artist?
                    // case yes: make sure album-artist won't get transformed into "Various Artists"
                    #$trackContextItem->getFeaturingUid(),
                    #$trackContextItem->getRemixerUid()
                ]
            ) . ",";
        }

        // append already extracted album-artist-uids
        #$trackArtistUids .= $this->albumContextItem->getArtistUid();

        // sort by most appearances
        $trackArtistUids = uniqueArrayOrderedByRelevance(trimExplode(",", $trackArtistUids, TRUE));

        // in case "Various Artists" is already included - remove it
        $vaKey = array_search('11', $trackArtistUids);
        if($vaKey !== FALSE) {
            unset($trackArtistUids[$vaKey]);
        }

        // in case we have more than 4 artists use "Various Artists" as album-artist
        $vaTreshold = 4;
        if(count($trackArtistUids) <= $vaTreshold) {
            cliLog('  found <= ' . $vaTreshold . ' artists within all tracks', 10);
            cliLog('  final album artists uids: ' . join(",", $trackArtistUids), 10);
            $this->injectAlbumArtistUid($trackArtistUids);
            return;
        }
        cliLog('  found > ' . $vaTreshold . ' artists within all tracks', 10);
        cliLog('  final album artists: Various Artists', 10);
        // set "various Artists"
        $this->injectAlbumArtistUid([11]);

    }

    /**
     * update property on already persisted album or batcher-queued album-instance
     */
    protected function injectAlbumArtistUid($artistUids) {
        $uidString = join(",", $artistUids);
        if($this->albumContextItem->getArtistUid() === $uidString) {
            // album-artist-uid already is identical to all collected track-artist-uids
            return;
        }

        if($this->useBatcher === FALSE) {
            $album = new \Slimpd\Models\Album();
            $album->setUid($this->albumContextItem->getUid())->setArtistUid($uidString);
            $this->container->albumRepo->update($album);
            return;
        }
        // batcher has not inserted album-instance in it's que
        $this->container->batcher->modifyQueuedInstanceProperty(
            'album',
            $this->albumContextItem->getUid(),
            'setArtistUid',
            $uidString
        );
    }

    public function handleTrackFilemtime($trackFilemtime) {
        $this->mostRecentAdded = ($trackFilemtime > $this->mostRecentAdded)
            ? $trackFilemtime
            : $this->mostRecentAdded;
    }

    public static function parseConfig() {
        return parse_ini_file(APP_ROOT . "core/config/importer/tag-mapper.ini", TRUE);
    }

    public function addTrack(array $rawTagDataArray) {
        $this->rawTagItems[] = $rawTagDataArray;
        return $this;
    }

    public function getTrackCount() {
        return count($this->rawTagItems);
    }

    protected function runAttributeScoring() {
        foreach($this->trackContextItems as $trackContextItem) {
            cliLog("=== scoring begin for " . basename($trackContextItem->getRelPath()) . " " . $trackContextItem->getRelPathHash() . " ===", 10, "yellow");
            $trackContextItem->initScorer($this->albumContextItem, $this->jumbleJudge);
            cliLog("=== scoring end for " . basename($trackContextItem->getRelPath()) . " " . $trackContextItem->getRelPathHash() . " ===", 10, "yellow");
            #$trackContextItem->postProcessProperties();
        }
    }


    protected function postProcessTrackProperties() {
        foreach($this->trackContextItems as $trackContextItem) {
            cliLog("=== postprocessing begin for " . basename($trackContextItem->getRelPath()) . " " . $trackContextItem->getRelPathHash() . " ===", 10, "yellow");
            #$trackContextItem->initScorer($this->albumContextItem, $this->jumbleJudge);
            $trackContextItem->postProcessProperties($this->albumContextItem);
            cliLog("=== postprocessing end for " . basename($trackContextItem->getRelPath()) . " " . $trackContextItem->getRelPathHash() . " ===", 10, "yellow");
        }
    }

    public function recommendationForAllTracks(array $recommendations, $score = 1) {
        #print_r($recommendations); die;
        foreach($this->trackContextItems as $trackContextItem) {
            cliLog("=== scoring begin for " . basename($trackContextItem->getRelPath()) . " " . $trackContextItem->getRelPathHash() . " ===", 10, "yellow");
            cliLog(__FUNCTION__, 10, "purple");
            $trackContextItem->recommend($recommendations, $score);
            cliLog("=== scoring end for " . basename($trackContextItem->getRelPath()) . " " . $trackContextItem->getRelPathHash() . " ===", 10, "yellow");
        }
    }

    // TODO: get this from Trait
    protected $directoryMtime;
    public function getDirectoryMtime() {
        return $this->directoryMtime;
    }
    public function setDirectoryMtime($value) {
        $this->directoryMtime = $value;
        return $this;
    }


    protected $relDirPath;
    protected $relDirPathHash;
    protected $filesize;
    protected $filemtime = 0;

    // todo: do we really need AlbumMigrator->importStatus property???
    protected $importStatus;


    public function getRelDirPath() {
        return $this->relDirPath;
    }
    public function getRelDirPathHash() {
        return $this->relDirPathHash;
    }
    public function getFilesize() {
        return $this->filesize;
    }
    public function getFilemtime() {
        return $this->filemtime;
    }
    public function getImportStatus() {
        return $this->importStatus;
    }


    public function setRelDirPath($value) {
        $this->relDirPath = $value;
        return $this;
    }
    public function setRelDirPathHash($value) {
        $this->relDirPathHash = $value;
        return $this;
    }
    public function setFilesize($value) {
        $this->filesize = $value;
        return $this;
    }
    public function setFilemtime($value) {
        $this->filemtime = $value;
        return $this;
    }
    public function setImportStatus($value) {
        $this->importStatus = $value;
        return $this;
    }
}
