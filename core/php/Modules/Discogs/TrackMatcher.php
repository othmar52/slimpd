<?php
namespace Slimpd\Modules\Discogs;
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

/**
 * This class compares local album tracks with discogs album tracks and guesses
 * which discogstrack fits best to a local track
 * 
 * in the match result we must not have a local track that matches multiple discogs tracks and vice versa
 * 
 */
class TrackMatcher {
    protected $discogsTracks = array();
    protected $localTracks = array();
    protected $localArtists = array();

    protected $discogsStrings = array();
    protected $localStrings = array();

    protected $localMatchScores = array(/*localUid => array (discogsIdx => score) */);

    /* we need to iterate from highest score to lowest score! those vars are little helpers */
    protected $highestScore = 0;
    protected $highestLocal = 0;
    protected $highestDiscogs = 0;
    protected $recursionPanic = 0;

    protected $matches = array(/* localUid => discogsIdx */);

    /**
     * @param array $discogsTracks: instances of \Slimpd\Modules\Albummigrator\DiscogsTrackContext
     * @param array $localTracks: instances of \Slimpd\Models\Track
     * @param array $localArtists: instances of \Slimpd\Models\Artist
     */
    public function __construct($discogsTracks, $trackInstances, $localArtists) {
        $this->discogsTracks = $discogsTracks;
        $this->localTracks = $trackInstances;
        $this->localArtists = $localArtists;
    }

    public function guessTrackMatch() {
        $this->runScoring();
        while($this->recursionPanic < 10000) {
            $this->recursionPanic++;
            $this->setMostScoredPair();
            $this->matches[$this->highestLocal] = $this->highestDiscogs;
            $this->dropMatches();
            if(count($this->localMatchScores) === 0) {
                $this->recursionPanic = 10000;
            }
        }
        $this->matches = array_flip($this->matches);
        ksort($this->matches);
        $this->addPlaceholders();
        return($this->matches);
    }

    protected function addPlaceholders() {
        $longerLength = (count($this->localTracks) > count($this->discogsTracks))
            ? count($this->localTracks)
            : count($this->discogsTracks);
        $remainigLocals = array_keys($this->localTracks);
        $tmp = array();
        foreach(range(0, $longerLength-1) as $idx) {
            if(isset($this->matches[$idx]) === TRUE) {
                $tmp[$idx] = $this->matches[$idx];
                unset($remainigLocals[ array_search($this->matches[$idx], $remainigLocals)]);
                continue;
            }
            // in case we have less discogs-items we have to fill guesmatch with remaining local tracks
            if(count($remainigLocals) > 0) {
                $tmp[$idx] = array_shift($remainigLocals);
                continue;
            }
            // otherwise add a placeholder
            $tmp[$idx] = "placeholder";
        }
        $this->matches = $tmp;
    }

    protected function sortMatches() {
        $this->matches = array_flip($this->matches);
        ksort($this->matches);
    }

    protected function dropMatches() {
        unset($this->localMatchScores[$this->highestLocal]);
        foreach(array_keys($this->localMatchScores) as $localUid) {
            unset($this->localMatchScores[$localUid][$this->highestDiscogs]);
        }
    }

    protected function setMostScoredPair() {
        $this->highestScore = 0;
        foreach($this->localMatchScores as $localUid => $scorePairs) {
            foreach($scorePairs as $discogsIdx => $score) {
                if($score < $this->highestScore) {
                    continue;
                }
                $this->highestScore = $score;
                $this->highestLocal = $localUid;
                $this->highestDiscogs = $discogsIdx;
            }
        }
    }

    /**
     * TODO: read sorting of tracks that already had been married from table:editorial
     *       special usecase: multiple local tracks had been manually married to a single discogs track
     */
    protected function runScoring() {
        // convert to compareable strings
        $this->trackInstancesToStringArray();
        $this->discogsInstancesToStringArray();
        
        // compare all string chunks and collect scores for similarity
        foreach($this->localStrings as $localUid => $localStrings) {
            foreach($this->discogsStrings as $discogsIdx => $discogsStrings) {
                if(isset($this->localMatchScores[$localUid][$discogsIdx]) === FALSE) {
                    $this->localMatchScores[$localUid][$discogsIdx] = 0;
                }
                
                foreach($discogsStrings as $discogsString) {
                    foreach($localStrings as $localString) {
                        $score = $this->getMatchStringScore($discogsString, $localString);
                        #$score = 10 + rand(0,2);
                        $this->localMatchScores[$localUid][$discogsIdx] += $score;
                    }
                }

                /*
                // TODO: duration based scoring
                // in case we have a discogs duration do some additional scoring based on durations
                if($discogsTrack->getMiliseconds() < 1) {
                    continue;
                }
                $extSeconds = $discogsTrack->getMiliseconds()*1000;
                $higher = $extSeconds;
                $lower =  $track->getMiliseconds();
                if($track->getMiliseconds() > $extSeconds) {
                    $higher = $track->getMiliseconds();
                    $lower =  $extSeconds;
                }
                $this->localMatchScores[$localUid][$discogsIdx] += floor($lower/($higher/100));
                */
            }
        }

        // sort scoring with highest first (not needed for functionality but comfortable for debugging)
        $this->localMatchScores = $this->sortScoringByValue($this->localMatchScores);
    }

    protected function sortScoringByValue($inputArray) {
        $return = array();
        foreach($inputArray as $idx => $scorePairs) {
            arsort($scorePairs);
            $return[$idx] = $scorePairs;
        }
        return $return;
    }

    /**
     * converts instances of \Slimpd\Models\Track into array of strings for
     * later comparison
     * @return void
     */
    protected function trackInstancesToStringArray() {
        foreach($this->localTracks as $track) {
            $artistSting = "";
            foreach(trimExplode(",", $track->getArtistUid()) as $artistUid){
                $artistSting .= $this->localArtists[$artistUid]->getTitle() . " ";
            }
            $this->localStrings[$track->getUid()] = [
                trim($artistSting),
                $track->getTitle(),
                $track->getTrackNumber(),
                remU(basename($track->getRelPath()))
            ];
        }
    }

    /**
     * converts instances of \Slimpd\Modules\Albummigrator\DiscogsTrackContext
     * into array of strings for later comparison
     * @return void
     */
    protected function discogsInstancesToStringArray() {
        foreach($this->discogsTracks as $discogsIdx => $discogsTrack) {
            $this->discogsStrings[$discogsIdx] = [
                $discogsTrack->getArtistString(),
                $discogsTrack->getTitleString(),
                $discogsTrack->getTrackNumber()
            ];
        }
    }

    /**
     * compares similarity of 2 strings
     * @param string $string1 
     * @param string $string2
     * @return int|float similarity of input strings
     */
    protected function getMatchStringScore($string1, $string2) {
        if(strtolower(trim($string1)) == strtolower(trim($string2))) {
            return 100;
        }
        return similar_text($string1, $string2);
    }
}
