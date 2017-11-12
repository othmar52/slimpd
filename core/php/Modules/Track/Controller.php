<?php
namespace Slimpd\Modules\track;
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
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class Controller extends \Slimpd\BaseController {
    
    protected $stemDir = "";
    protected $stemChunk = 0;
    protected $fileScanner = "";
    protected $TODO_RemoveMe = [];

    public function widgetTrackcontrolAction(Request $request, Response $response, $args) {
        useArguments($request, $response, $args);
        $itemParam = $request->getParam('item');
        $this->completeArgsForDetailView($itemParam, $args);
        $this->view->render($response, 'modules/widget-trackcontrol.htm', $args);
        return $response;
    }

    public function localplayerAction(Request $request, Response $response, $args) {
        useArguments($request, $response, $args);
        $itemParam = $request->getParam('item');
        $this->completeArgsForDetailView($itemParam, $args);
        $args['player'] = 'local';
        $this->view->render($response, 'partials/player/permaplayer.htm', $args);
        return $response;
    }

    public function widgetDeckselectorAction(Request $request, Response $response, $args) {
        useArguments($request, $response, $args);
        $itemParam = $request->getParam('item');
        $this->completeArgsForDetailView($itemParam, $args);
        $this->view->render($response, 'modules/widget-deckselector.htm', $args);
        return $response;
    }

    public function mpdplayerAction(Request $request, Response $response, $args) {
        useArguments($request, $response, $args);
        $item = $this->mpd->getCurrentlyPlayedTrack();
        $itemRelPath = ($item !== NULL) ? $item->getRelPath() : 0;
        $this->completeArgsForDetailView($itemRelPath, $args);
        $args['player'] = 'mpd';
        $this->view->render($response, 'partials/player/permaplayer.htm', $args);
        return $response;
    }

    public function dumpid3Action(Request $request, Response $response, $args) {
        useArguments($request, $response, $args);
        $args['action'] = 'trackid3';
        $getID3 = new \getID3;
        $tagData = $getID3->analyze($this->conf['mpd']['musicdir'] . $args['itemParams']);
        \getid3_lib::CopyTagsToComments($tagData);
        \getid3_lib::ksort_recursive($tagData);
        $args['dumpvar'] = $tagData;
        $args['getid3version'] = $getID3->version();
        $this->view->render($response, 'appless.htm', $args);
        return $response;
    }

    public function editAction(Request $request, Response $response, $args) {
        useArguments($request, $response, $args);
        $this->completeArgsForDetailView($args['itemParams'], $args);

        $args['action'] = 'maintainance.trackdebug';

        // nothing to do with non imported tracks
        if($args['item']->getUid() < 1) {
            $this->view->render($response, 'surrounding.htm', $args);
            return $response;
        }

        $query = "SELECT * FROM rawtagdata WHERE uid=".$args['item']->getUid()." LIMIT 1;";
        $result = $this->db->query($query);
        while($record = $result->fetch_assoc()) {
            $args['itemraw'] = new \Slimpd\Modules\Albummigrator\TrackContext(
                $record,
                0,
                \Slimpd\Modules\Albummigrator\AlbumMigrator::parseConfig(),
                $this->container
            );
        }
        $album = $this->albumRepo->getInstanceByAttributes(['uid' => $args['item']->getAlbumUid()]);
        $args['renderitems'] = $this->getRenderItems($args['item'], $album);
        $this->view->render($response, 'surrounding.htm', $args);
        return $response;
    }

    protected function completeArgsForDetailView($itemParam, &$args) {
        $args['item'] = NULL;
        if(is_numeric($itemParam) === TRUE) {
            $search = array('uid' => (int)$itemParam);
            $args['item'] = $this->trackRepo->getInstanceByAttributes($search);
        }
        if($args['item'] === NULL) {
            $itemPath = $this->filesystemUtility->trimAltMusicDirPrefix($itemParam, $this->conf);
            $search = array('relPathHash' => getFilePathHash($itemPath));
            $itemRelPath = $itemPath;
            $args['item'] = $this->trackRepo->getInstanceByAttributes($search);
        }

        if($args['item'] === NULL) {
            // track has not been imported in slimpd database yet...
            $args['item'] = $this->trackRepo->getNewInstanceWithoutDbQueries($itemRelPath);
        }

        $args['renderitems'] = $this->getRenderItems($args['item']);

        // TODO: remove external liking as soon we have implemented a proper functionality
        $args['temp_likerurl'] = 'http://ixwax/filesystem/plusone?f=' .
            urlencode($this->conf['mpd']['alternative_musicdir'] . $args['item']->getRelPath());

        return;
    }

    /**
     * experimental feature
     * TODO heavy cleanup in case the idea is working
     * ffprobe
     * ffmpeg
     * multiple subdomains
     *
     * 
     * TODO: sort single stem tracks based on activity(avarage gain) when creating stem?
     * TODO: add title for each stem track
     *
     * TODO: generate waveform for each destemmed file during destemming
     * TODO: volume meter for each track
     * TODO: store changed volume levels for each track when mute/solo/nextChunk
     * TODO: make multiple solo possible
     *
     *
     *
     *
     * 
     */
    public function stemplayerAction(Request $request, Response $response, $args) {
        useArguments($request, $response, $args);
        
        $args['action'] = 'stem';
        $this->completeArgsForDetailView($args['itemParams'], $args);
        $args['player'] = 'stem';
        $fingerPrint = $args['item']->getFingerprint();
        
        #$stemTrack = $this->stemUtility->getStemInstanceByPath($args['itemParams']);
        #echo "<pre>STEMTRACK:" . print_r($stemTrack, 1) . "</pre>";die;
        #echo "<pre>STEMTRACK:" . print_r($args, 1) . "</pre>";die;
        
        if($fingerPrint === NULL) {
            $this->fileScanner = new \Slimpd\Modules\Importer\Filescanner($this->container);
            $fingerPrint = $this->fileScanner->extractAudioFingerprint(
                $this->conf['mpd']['musicdir'] . $args['item']->getRelPath()
            );
        }
        if($args['item']->getAudioDataFormat() === NULL) {
            $args['item']->setAudioDataFormat(
                $this->container->filesystemUtility->getFileExt(
                    $args['item']->getRelPath()
                )
            );
        }
        #echo "<pre>" . print_r($args, 1) . "</pre>";exit;
        $this->stemDir = 'localdata' . DS . 'stems' .
            DS . $args['item']->getAudioDataFormat() .
            DS . $fingerPrint;

        #echo "<pre>" . print_r($this->stemDir, 1) . "</pre>";exit;
        // obviously destemming is already executed or currently running
        if(is_dir(APP_ROOT . $this->stemDir) === TRUE) {
            $this->getAvailableStemTracks($args);
            $this->view->render($response, 'surrounding.htm', $args);
            return $response;
        }
        
        // we have to initialize the destemming process
        // because browser is not able to access multiple streams within a file
        echo "<pre>" . print_r($this->conf['mpd']['musicdir'] .$args['item']->getRelPath(), 1) . "</pre>";
        echo "<pre>" . print_r($fingerPrint, 1) . "</pre>";
        echo "<pre>TODO: add to destemming que</pre>";
        $this->view->render($response, 'surrounding.htm', $args);
        return $response;

    }

    /**
     * TODO remove hardcoded paths
     * this is only an experimental tryout
     */
    public function getAvailableStemTracks(&$args) {
        $this->fileBrowserStemRoot = new \Slimpd\Modules\Filebrowser\Filebrowser($this->container);
        $this->fileBrowser = new \Slimpd\Modules\Filebrowser\Filebrowser($this->container);
        
        $this->fileBrowserStemRoot->getDirectoryContent($this->stemDir);
        
        $this->stemChunk = 0;
        $requestedChunk = $this->request->getParam('stemChunk');
        if($requestedChunk !== NULL) {
            $requestedChunk--;
            if(array_key_exists($requestedChunk, $this->fileBrowserStemRoot->subDirectories["dirs"]) === TRUE) {
                $this->stemChunk = (int)$requestedChunk;
            }
        }

        #echo "<pre>" . print_r($args) . "</pre>"; exit;
        #echo "<pre>" . print_r($this->fileBrowserStemRoot->subDirectories["total"], 1) . "</pre>"; exit;
        
        
        $args['paginator'] = new \JasonGrimes\Paginator(
            $this->fileBrowserStemRoot->subDirectories["total"],
            1, // $itemsPerPage
            $this->stemChunk + 1,
            $this->conf['config']['absRefPrefix'] . 'stemplayer/'.$args["itemParams"] .'?stemChunk=(:num)'
        );
        $args['paginator']->setMaxPagesToShow(10);
        

        
        
        $this->fileBrowser->getDirectoryContent($this->stemDir."/" . $this->stemChunk);
        
        $args['stems'] = [];
        
        $status = "ERROR: obviously sliMpd was not able to destem requested file";

        foreach($this->fileBrowser->files["other"] as $file) {
            #var_dump($file);exit;
            if($file->title !== "status") {
                continue;
            }
            $status = trim(file_get_contents(APP_ROOT . $file->getRelPath()));
        }
        $args['status'] = $status;
        if($status !== "finished") {
            return;
        }

        $this->temp_prepareMeta($this->fileBrowser->files["music"][0]->getRelPath());
        foreach($this->fileBrowser->files["music"] as $idx => $file) {
            $tempTrack = $this->trackRepo->getNewInstanceWithoutDbQueries(
                $file->getRelPath()
            );
            $tempTrack->setTitle($this->temp_getMeta($idx, "title"));
            # TODO: remove misuse of audio-encoder property
            $tempTrack->setAudioEncoder($this->temp_getMeta($idx, "volume"));
            $args['stems'][] = $tempTrack;
        }
    }

    protected function temp_getMeta($idx, $propName) {
        $idx++;
        if($propName === "title") {
            if(preg_match("/stream".$idx."_title\:\ (.*)/", $this->TODO_RemoveMe["rawdata"], $matches)) {
                return $matches[1];
            }
            return "untitled";
        }
        if($propName !== "volume") {
            return "";
        }
        $peak = 0;
        if(preg_match("/stream".$idx.$this->TODO_RemoveMe["search_for"] . "\:\ (.*)/", $this->TODO_RemoveMe["rawdata"], $matches)) {
            $peak = str_replace(",", ".", floatval(str_replace("+", "",$matches[1])));
        }
        if($peak === 0) {
            return $this->TODO_RemoveMe["set_vol_default"];
        }

        // find out percent
        $foundMax = $this->TODO_RemoveMe["found_peak_max"];
        $foundMin = $this->TODO_RemoveMe["found_peak_min"];
        
        $targetMax = $this->TODO_RemoveMe["set_vol_max"];
        $targetMin = $this->TODO_RemoveMe["set_vol_min"];
        
        $currentPeak = $peak;
        
        $onePercent = ($foundMin*-1) - ($foundMax*-1);
        $targetPercent = 1 - ((($currentPeak*-1) - ($foundMax*-1)) / $onePercent) * ($targetMax - $targetMin);

        return str_replace(",", ".", floatval($targetPercent));

    }
    /**
     * 
     */
    protected function temp_prepareMeta($filePath) {
        $this->TODO_RemoveMe = [
            "rawdata" => "",
            "search_for" => "_mean_volume",
            "found_peak_max" => -100,
            "found_peak_min" => 1,
            "set_vol_max" => 1,
            "set_vol_min" => 0.2,
            "set_vol_default" => 0.8
        ];
        $getID3 = new \getID3;
        $tagData = $getID3->analyze(APP_ROOT . $filePath);
        \getid3_lib::CopyTagsToComments($tagData);
        if(@isset($tagData["tags"]["id3v2"]["text"]["description"])) {
            $this->TODO_RemoveMe["rawdata"] = str_replace(" =", ":", $tagData["tags"]["id3v2"]["text"]["description"]);
        }
        
        if(preg_match_all("/stream\d".$this->TODO_RemoveMe["search_for"] . "\:\ (.*)/", $this->TODO_RemoveMe["rawdata"], $matches)) {
            #echo "<pre>";print_r($matches); exit;
            foreach($matches[1] as $match) {
                $val = str_replace(",", ".", floatval(str_replace("+", "",$match)));
                if($val > $this->TODO_RemoveMe["found_peak_max"]) {
                    $this->TODO_RemoveMe["found_peak_max"] = $val;
                }
                if($val < $this->TODO_RemoveMe["found_peak_min"]) {
                    $this->TODO_RemoveMe["found_peak_min"] = $val;
                }
            }
        }
        
        #echo "<pre>";print_r($this->TODO_RemoveMe);exit;
    }

    protected function convertDBtoPercent($dbValue) {
        $dbArray = [];
        
    }
}
