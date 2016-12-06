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

class AlbumContext extends \Slimpd\Models\Album {
    use \Slimpd\Modules\Albummigrator\MigratorContext; // config
    protected $confKey = "album-tag-mapping-";
    protected $jumbleJudge;
    #public $sceneSuffix;

    public function __construct($container) {
        $this->container = $container;
        $this->db = $container->db;
        $this->ll = $container->ll;
        $this->conf = $container->conf;
    }

    /*
    // TODO: currently this is not used - does it make sense to use it?
    public function getTagsFromTrack($rawTagArray, $config) {
        $this->rawTagRecord = $rawTagArray;
        $this->rawTagArray = unserialize($rawTagArray['tagData']);
        $this->config = $config;
        $this->configBasedSetters();
    }
    */

    /**
     * some rawTagData-fields are identical to album fields 
     */
    public function copyBaseProperties($rawTagRecord) {
        $this->setRelPath($rawTagRecord['relDirPath'])
            ->setRelPathHash($rawTagRecord['relDirPathHash'])
            ->setFilemtime($rawTagRecord['directoryMtime'])
            //->setAdded($rawTagRecord['added'])
            //->setLastScan($rawTagRecord['lastDirScan'])
            ;
    }

    public function collectAlbumStuff(&$albumMigrator, &$jumbleJudge) {
        $this->jumbleJudge = $jumbleJudge;
        $dirname = unifyAll(basename($this->getRelPath()));
        // TODO: use parent dir in case dirname is "cd1", "cd01", "Disc_1_(of_4)", ???

        // dirname as album name is better than nothing
        $this->recommend(["setTitle" => $dirname]);

        // guess attributes by directory name
        $this->runTest("SchemaTests\\Dirname\\ArtistTitleYear", $dirname)
            ->runTest("SchemaTests\\Dirname\\ArtistTitle", $dirname)
            ->runTest("SchemaTests\\Dirname\\ArtistYearTitle", $dirname)
            ->runTest("SchemaTests\\Dirname\\ArtistTitleSourceYearScene", $dirname)
            ->runTest("SchemaTests\\Dirname\\HasYear", $dirname)
            ->runTest("SchemaTests\\Dirname\\HasCatalogNr", $dirname)
            ->runTest("SchemaTests\\Dirname\\HasPrefixedCatalogNr", $dirname)
            ;

        $this->scoreLabelByLabelDirectory($albumMigrator);

        // do not search for common scene suffix in case there is only one track
        if($this->jumbleJudge->albumMigrator->getTrackCount() === 1) {
            return;
        }

        // check if we have a common scene suffix string
        $sceneSuffixes = [];
        foreach($jumbleJudge->tests["SchemaTests\Filename\HasSceneSuffix"] as $sceneSuffixTest) {
            $sceneSuffixes[] = $sceneSuffixTest->result;
        }
        #print_r($sceneSuffixes);
        if(count(array_unique($sceneSuffixes)) === 1) {
            // downvoting in case scene-suffix had been guessed as artist or title
            $this->jumbleJudge->albumMigrator->recommendationForAllTracks(
                [
                    'setArtist' => ucfirst($sceneSuffixes[0]),
                    'setTitle' => ucfirst($sceneSuffixes[0])
                ],
                -4
            );
        }
    }

    protected function runTest($className, $input) {
        $classPath = "\\Slimpd\\Modules\\Albummigrator\\" . $className;
        // for now there is no need for this instance within the tests
        // but abstraction requires any kind of variable... 
        $dummyTrackContext = NULL;
        $test = new $classPath($input, $dummyTrackContext, $this, $this->jumbleJudge);
        $test->run();
        $test->scoreMatches();
        return $this;
    }

    public function migrate($trackContextItems, $jumbleJudge, $useBatcher) {
        $album = new \Slimpd\Models\Album();
        $album->setRelPath($this->getRelPath())
            ->setRelPathHash($this->getRelPathHash())
            ->setFilemtime($this->getFilemtime())
            ->setAdded($this->getAdded())
            ->setIsJumble($jumbleJudge->handleAsAlbum)
            ->setTitle($this->getMostScored("setTitle"))
            ->setYear($this->getMostScored("setYear"))
            ->setCatalogNr($this->getMostScored("setCatalogNr"))
            ->setArtistUid(join(",", $this->container->artistRepo->getUidsByString($this->getMostScored("setArtist"))))
            ->setGenreUid(join(",", $this->container->genreRepo->getUidsByString($this->getMostScored("setGenre"))))
            ->setLabelUid(join(",", $this->container->labelRepo->getUidsByString($this->getMostScored("setLabel"))))
            /*->setLabelUid(
                join(",", $this->container->labelRepo->getUidsByString(
                    ($album->getIsJumble() === 1)
                        ? $this->getAllRecommendations("setLabel")            // all labels
                        : $this->getMostScored("setLabel")    // only 1 label
                ))
            )*/
            ->setTrackCount(count($trackContextItems));

        if($useBatcher === TRUE) {
            $this->container->batcher->que($album);
        } else {
            $this->container->albumRepo->ensureRecordUidExists($album->getUid());
            $this->container->albumRepo->update($album);
        }

        // at this time we have an album-artist-uid
        // copy from album-item to album-context-item for a later vice-versa check with artist-uids of track-context-items
        $this->setArtistUid($album->getArtistUid());

        $this->setUid($album->getUid())->updateAlbumIndex($useBatcher);
    }

    protected function updateAlbumIndex($useBatcher) {
        $indexChunks = $this->getRelPath() . " " .
            str_replace(
                array('/', '_', '-', '.'),
                ' ',
                $this->getRelPath()
            )
            . " " . join(" ", $this->getAllRecommendations("setArtist"))
            . " " . join(" ", $this->getAllRecommendations("setTitle"))
            . " " . join(" ", $this->getAllRecommendations("setYear"))
            . " " . join(" ", $this->getAllRecommendations("setGenre"))
            . " " . join(" ", $this->getAllRecommendations("setLabel"))
            . " " . join(" ", $this->getAllRecommendations("setCatalogNr"));

        // minimize index entry by removing duplicate phrases
        $indexChunks = join(" ", array_unique(trimExplode(" ", strtolower($indexChunks))));

        // make sure to use identical uids in table:trackindex and table:track
        $albumIndex = new \Slimpd\Models\Albumindex();
        $albumIndex->setUid($this->getUid())
            ->setArtist($this->getMostScored("setArtist"))
            ->setTitle($this->getMostScored("setTitle"))
            ->setAllchunks($indexChunks);

        if($useBatcher === TRUE) {
            $this->container->batcher->que($albumIndex);
            return;
        }
        $this->container->albumindexRepo->ensureRecordUidExists($this->getUid());
        $this->container->albumindexRepo->update($albumIndex);
    }

    protected function scoreLabelByLabelDirectory(&$albumMigrator) {
        cliLog("--- add LABEL based on directory ---", 8);
        cliLog("  album directory: " . $this->getRelPath(), 8);

        // check config
        if(isset($this->conf['label-parent-directories']) === FALSE) {
            cliLog("  aborting because no label directories configured",8);
            return;
        }

        foreach($this->conf['label-parent-directories'] as $labelDir) {
            $labelDir = appendTrailingSlash($labelDir);
            cliLog("  configured label dir: " . $labelDir, 10);
            if(stripos($this->getRelPath(), $labelDir) !== 0) {
                cliLog("  no match: " . $labelDir, 8);
                continue;
            }
            // use directory name as label name
            $newLabelString = basename(dirname($this->getRelPath()));

            // do some cleanup
            $newLabelString = ucwords(remU($newLabelString));
            cliLog("  match: " . $newLabelString, 8);

            $this->recommend(['setLabel'=> $newLabelString]);
            #var_dump($newLabelString);die;
            $albumMigrator->recommendationForAllTracks(
                ['setLabel'=> $newLabelString]
            );
            return;
        }
        return;
    }
}
