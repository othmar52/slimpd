<?php
namespace Slimpd\Modules\Albummigrator;
use \Slimpd\Models\Artist;
use \Slimpd\Utilities\RegexHelper as RGX;
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

class TrackContext extends \Slimpd\Models\Track {
    use \Slimpd\Modules\Albummigrator\MigratorContext; // config
    use \Slimpd\Modules\Albummigrator\TrackArtistExtractor; // regularArtists, featuredArtists, remixArtists, artistString, titleString
    protected $confKey = "track-tag-mapping-";
    
    // those attributes holds string values (track holds relational Uids)
    protected $album;
    
    public $idx;
    
    public $mostScored;
    protected $totalTracks;
    protected $audioBitrateMode;
    
    public function __construct($rawTagArray, $idx, $config, $container) {
        $this->config = $config;
        $this->idx = $idx;
        $this->rawTagRecord = $rawTagArray;
        
        
        $this->container = $container;
        $this->db = $container->db;
        $this->ll = $container->ll;

        $rawTagBlob = $this->container->rawtagblobRepo->getInstanceByAttributes([ "uid" => $rawTagArray['uid'] ]);
        if($rawTagBlob === NULL) {
            // for some reason we are not able to fetch the required rawTagBlob from database.
            // so we have to scan the file and fetch the database entry again
            $fileScanner = new \Slimpd\Modules\Importer\Filescanner($container);
            $fileScanner->singleFile2Database($rawTagArray);
            $rawTagBlob = $this->container->rawtagblobRepo->getInstanceByAttributes([ "uid" => $rawTagArray['uid'] ]);
        }
        $data = gzuncompress($rawTagBlob->getTagData());
        
        $data = unserialize($data);
        $this->rawTagArray = $data;
        $this->process();
    }
    
    protected function process() {
        $this->copyBaseProperties();
        $this->configBasedSetters();
        $this->postProcessAudioProperties();
    }

    protected function postProcessAudioProperties() {
        // convert decimal-seconds to miliseconds
        $this->setMiliseconds(round($this->getMiliseconds()*1000));

        // default value for audio-encoder
        if(!$this->getAudioEncoder()) {
            $this->setAudioEncoder('Unknown encoder');
        }

        // default value for audio-bits-per-sample
        if(!$this->getAudioBitsPerSample()) {
            $this->setAudioBitsPerSample(16);
        }

        // default value for audio-sample-rate
        if(!$this->getAudioSampleRate()) {
            $this->setAudioSampleRate(44100);
        }

        // default value for audio-channels
        if(!$this->getAudioChannels()) {
            $this->setAudioChannels(2);
        }

        if($this->getAudioLossless()) {
            $this->setAudioProfile('Lossless compression');
            if($this->getAudioComprRatio() === "1") {
                $this->setAudioProfile('Lossless');
            }
        }

        if(!$this->getAudioProfile()) {
            $this->setAudioProfile(
                $this->getAudioBitrateMode() . " " . round($this->getAudioBitrate()/ 1000, 1) . " kbps"
            );
        }

        // integer in database
        $this->setAudioBitrate(round($this->getAudioBitrate()));

        // override description of audiocodec
        // @see: https://github.com/othmar52/slimpd/issues/25
        // @see: https://github.com/JamesHeinrich/getID3/issues/48
        $audioCodec = $this->extractTagString(
            recursiveArrayParser(
                ["audio", "codec"],
                $this->rawTagArray
            )
        );
        if($this->rawTagRecord['extension'] === 'm4a' && $audioCodec === "Apple Lossless Audio Codec") {
            $this->setMimeType('audio/aac');
            $this->setAudioDataformat('aac');
        }
    }

    /**
     * most rawTagData-fields are identical to track fields 
     */
    protected function copyBaseProperties() {
        $this->setUid($this->rawTagRecord['uid'])
            ->setRelPath($this->rawTagRecord['relPath'])
            ->setRelPathHash($this->rawTagRecord['relPathHash'])
            ->setRelDirPath($this->rawTagRecord['relDirPath'])
            ->setRelDirPathHash($this->rawTagRecord['relDirPathHash'])
            #->setAdded($this->rawTagRecord['added'])
            ->setFilesize($this->rawTagRecord['filesize'])
            ->setFilemtime($this->rawTagRecord['filemtime'])
            ->setLastScan($this->rawTagRecord['lastScan'])
            ->setImportStatus($this->rawTagRecord['importStatus'])
            ->setFingerprint($this->rawTagRecord['fingerprint'])
            ->setError($this->rawTagRecord['error']);
    }

    public function initScorer(&$albumContext, $jumbleJudge) {
        foreach($jumbleJudge->tests as $tests) {
            $tests[$this->idx]->scoreMatches($this, $albumContext, $jumbleJudge);
        }
    }
    
    public function postProcessProperties() {
        \Slimpd\Modules\Albummigrator\TrackRecommendationsPostProcessor::downVoteVariousArtists($this);
        \Slimpd\Modules\Albummigrator\TrackRecommendationsPostProcessor::downVoteNumericArtists($this);
        \Slimpd\Modules\Albummigrator\TrackRecommendationsPostProcessor::downVoteGenericTrackTitles($this);
        \Slimpd\Modules\Albummigrator\TrackRecommendationsPostProcessor::downVoteUnknownArtists($this);
        \Slimpd\Modules\Albummigrator\TrackRecommendationsPostProcessor::removePrefixedArtistFromTitle($this);
        \Slimpd\Modules\Albummigrator\TrackRecommendationsPostProcessor::removeSuffixedTitleFromArtist($this);
        $this->setArtist($this->getMostScored('setArtist'));
        $this->setTitle($this->getMostScored('setTitle'));
        $this->setAlbum($this->getMostScored('setAlbum'));
        $this->setGenre($this->getMostScored('setGenre'));
        $this->setLabel($this->getMostScored('setLabel'));
        $this->setYear($this->getMostScored('setYear'));
        $this->setTrackNumber($this->getMostScored('setTrackNumber'));
    }

    /**
     * copy all attributes from TrackContext-instance to new Track->instance
     */
    public function getTrackInstanceByContext() {
        $track = new \Slimpd\Models\Track();
        $self = new \ReflectionClass("\Slimpd\Models\Track");
        foreach($self->getMethods() as $method) {
            if(preg_match("/^set/", $method->name) === 0) {
                continue;
            }

            // TODO: remove this setter from Track at all
            if($method->name === "setRelDirPath") {
                continue;
            }
            $setter = $method->name;
            $getter = "g" . substr($setter, 1);
            if(method_exists($this, $getter) === FALSE) {
                continue;
            }
            $track->$setter($this->$getter());
        }
        return $track;
    }

    public function migrate($useBatcher) {
        #print_r($this->recommendations);die;
        # setFeaturedArtistsAndRemixers() is processing:
            # $t->setArtistUid();
            # $t->setFeaturingUid();
            # $t->setRemixerUid();
        $this->setFeaturedArtistsAndRemixers()
            ->setLabelUid( join(",", $this->container->labelRepo->getUidsByString($this->getLabel())))
            ->setGenreUid( join(",", $this->container->genreRepo->getUidsByString($this->getGenre())));
            
        $track = $this->getTrackInstanceByContext();

        // set all artist uids of track-context that we can do a vice-versa check for album-artists on album-context
        $this->setArtistUid($track->getArtistUid());
        $this->setRemixerUid($track->getRemixerUid());
        $this->setFeaturingUid($track->getFeaturingUid());

        if($useBatcher === TRUE) {
            $this->container->batcher->que($track);
            return;
        }

        $this->container->trackRepo->ensureRecordUidExists($track->getUid());
        $this->container->trackRepo->update($track);
    }
    
    public function updateTrackIndex($useBatcher) {
        $indexChunks = $this->getRelPath() . " " .
            str_replace(
                array('/', '_', '-', '.'),
                ' ',
                $this->getRelPath()
            )
            . " " . join(" ", $this->getAllRecommendations("setArtist"))
            . " " . join(" ", $this->getAllRecommendations("setTitle"))
            . " " . join(" ", $this->getAllRecommendations("setAlbum"))
            . " " . join(" ", $this->getAllRecommendations("setYear"))
            . " " . join(" ", $this->getAllRecommendations("setGenre"))
            . " " . join(" ", $this->getAllRecommendations("setLabel"))
            . " " . join(" ", $this->getAllRecommendations("setFingerprint"))
            . " " . join(" ", $this->getAllRecommendations("setCatalogNr"));
        // TODO: add all recomendations and other missing attributes
        // TODO: add az09 version of each phrase to track index
        // TODO: here is the right place for adding dotted words as single word to trackindex
        //   example: track-title "c.l.a.u.d.i.a" should get word "claudia" in index as well

        // minimize index entry by removing duplicate phrases/words
        $indexChunks = join(" ", array_unique(trimExplode(" ", strtolower($indexChunks))));

        // make sure to use identical uids in table:trackindex and table:track
        $trackIndex = new \Slimpd\Models\Trackindex();
        $trackIndex->setUid($this->getUid())
            ->setArtist($this->getArtistString())
            ->setTitle($this->getTitleString())
            ->setAllchunks($indexChunks);

        if($useBatcher === TRUE) {
            $this->container->batcher->que($trackIndex);
            return;
        }
        $this->container->trackindexRepo->ensureRecordUidExists($this->getUid());
        $this->container->trackindexRepo->update($trackIndex);
    }

    public function setAlbum($value) {
        $this->album = $value;
        return $this;
    }
    public function getAlbum() {
        return $this->album;
    }
    public function setTotalTracks($value) {
        $this->totalTracks = $value;
        return $this;
    }
    public function getTotalTracks() {
        return $this->totalTracks;
    }
    public function setAudioBitrateMode($value) {
        $this->audioBitrateMode = $value;
        return $this;
    }
    public function getAudioBitrateMode() {
        return $this->audioBitrateMode;
    }
}
