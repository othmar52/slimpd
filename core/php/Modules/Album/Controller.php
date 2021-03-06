<?php
namespace Slimpd\Modules\Album;
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
    public function listAction(Request $request, Response $response, $args) {
        if($this->auth->hasPermissionFor('media') === FALSE) {
            return $this->renderAccessDenied($response);
        }
        useArguments($request);
        $args["action"] = "albums";
        $args["itemlist"] = [];
        $itemsPerPage = 18;

        $args['itemlist'] = $this->albumRepo->getAll($itemsPerPage, $args['currentPage'], $args['sort'] . " " . $args['direction']);
        $args["totalresults"] = $this->albumRepo->getCountAll();
        $args["activesorting"] = $args['sort'] . "-" . $args['direction'];

        $args["paginator"] = new \JasonGrimes\Paginator(
            $args["totalresults"],
            $itemsPerPage,
            $args['currentPage'],
            $this->conf['config']['absRefPrefix'] ."albums/page/(:num)/sort/".$args['sort']."/".$args['direction']
        );
        $args["paginator"]->setMaxPagesToShow(paginatorPages($args['currentPage']));
        $args['renderitems'] = $this->getRenderItems($args['itemlist']);
        $this->view->render($response, 'surrounding.htm', $args);
        return $response;
    }

    public function detailAction(Request $request, Response $response, $args) {
        if($this->auth->hasPermissionFor('media') === FALSE) {
            return $this->renderAccessDenied($response);
        }
        useArguments($request);
        $this->completeArgsForDetailView($args['itemUid'], $args);
        if($args['album'] === NULL) {
            $args['action'] = '404';
            $this->view->render($response, 'surrounding.htm', $args);
            return $response->withStatus(404);
        }
        $args['action'] = 'album.detail';
        $this->view->render($response, 'surrounding.htm', $args);
        return $response;
    }

    /**
     * downloadAction ALPHA
     * 
     * TODO: separate downloadDirectory from downloadAlbum
     * TODO: implement a global exec() wrapper
     * TODO: add a systemcheck to verify $this->conf['modules']['cmd_dirdownload'] is working as expected
     * TODO: add something like a que to avoid parallel execution of archive creation
     */
    public function downloadAction(Request $request, Response $response, $args) {
        if($this->auth->hasPermissionFor('download-directory') === FALSE) {
            return $this->renderAccessDenied($response);
        }
        useArguments($request);
        $this->completeArgsForDetailView($args['itemUid'], $args);
        if($args['album'] === NULL || $this->conf['modules']['enable_dirdownload'] !== '1') {
            $args['action'] = '404';
            $this->view->render($response, 'surrounding.htm', $args);
            return $response->withStatus(404);
        }
        $realPath = $this->container->filesystemUtility->getFileRealPath($args['album']->getRelPath());
        $maxSizeMB = $this->conf['modules']['max_archivsize'];
        $maxSizeBytes = $this->conf['modules']['max_archivsize']*1024*1024;

        // check total directory size before creating archive
        $filesystemReader = new \Slimpd\Modules\Importer\FilesystemReader($this->container);
        $realSizeBytes = $filesystemReader->getDirectorySizeInBytes($realPath, $maxSizeBytes);

        // do not continue if expected archive size is too big
        if($realSizeBytes > $maxSizeBytes) {
            $this->flash->AddMessageNow("error", $this->container->ll->str("error.dirarchivsize", [$maxSizeMB]));
            $this->view->render($response, 'surrounding.htm', $args);
            return $response;
        }

        $tmpFile = "localdata/cache/" . az09(basename($realPath), "_\-.", FALSE). ".zip";
        $cmd = str_replace(
            array(
                "%targetfile",
                "%directory"
            ),
            array(
                APP_ROOT . $tmpFile,
                escapeshellarg($realPath)
            ),
            $this->conf['modules']['cmd_dirdownload']
        );
        exec($cmd);

        // do not block other requests of this client
        session_write_close();
        set_time_limit(0);

        $newResponse = $response->withHeader('Content-Description', 'File Transfer')
           ->withHeader('Content-Type', 'application/octet-stream')
           ->withHeader('Content-Disposition', 'attachment;filename="'.basename(APP_ROOT . $tmpFile).'"')
           ->withHeader('Expires', '0')
           ->withHeader('Cache-Control', 'must-revalidate')
           ->withHeader('Pragma', 'public')
           ->withHeader('Content-Length', filesize(APP_ROOT . $tmpFile));

        $file = @fopen(APP_ROOT . $tmpFile,"rb");
        fseek($file, 0);
        while(!feof($file)) {
            $response->getBody()->write(@fread($file, 1024*8));
            if (connection_status()!=0) {
                @fclose($file);
                return $newResponse;
            }
        }
        @fclose($file);
        unlink(APP_ROOT . $tmpFile);
        return $newResponse;
    }

    public function albumTracksAction(Request $request, Response $response, $args) {
        if($this->auth->hasPermissionFor('media') === FALSE) {
            return $this->renderAccessDenied($response);
        }
        useArguments($request);
        $this->completeArgsForDetailView($args['itemUid'], $args);
        if($args['album'] === NULL) {
            $args['action'] = '404';
            $this->view->render($response, 'surrounding.htm', $args);
            return $response->withStatus(404);
        }
        $args['action'] = 'albumtracks';
        $this->view->render($response, 'surrounding.htm', $args);
        return $response;
    }

    public function widgetAlbumAction(Request $request, Response $response, $args) {
        if($this->auth->hasPermissionFor('media') === FALSE) {
            return $this->renderAccessDenied($response);
        }
        useArguments($request);
        $this->completeArgsForDetailView($args['itemUid'], $args);
        if($args['album'] === NULL) {
            $args['action'] = '404';
            $this->view->render($response, 'surrounding.htm', $args);
            return $response->withStatus(404);
        }
        $args['action'] = 'albumwidget';
        $this->view->render($response, 'modules/widget-album.htm', $args);
        return $response;
    }

    public function remigrateAction(Request $request, Response $response, $args) {
        $this->completeArgsForDetailView($args['itemUid'], $args);
        if($args['album'] === NULL) {
            $args['action'] = '404';
            $this->view->render($response, 'surrounding.htm', $args);
            return $response->withStatus(404);
        }
        // TODO: move to global exec() wrapper
        exec(APP_ROOT . 'slimpd remigratealbum ' . $args['album']->getUid(), $cliResponse);
        $args['migratordump'] = $cliResponse;
        // separate dump into track specific parts
        $args['trackdump'] = [];
        $section = "";
        $item = "";
        foreach($cliResponse as $line) {
            if(preg_match("/===\ (.*)\ (begin|end)\ for\ (?:.*)?(\ [a-f0-9]{11}|album)\ ===/", $line, $matches)) {
                $section = $matches[1];
                $item = trim($matches[3]);
                if($matches[2] === "end") {
                    $item = "";
                    $section = "";
                }
            }
            if($item === "" || $section === "") {
                continue;
            }
            $args['trackdump'][$item][$section][] = $line;
        }
        return $this->detailAction($request, $response, $args);
    }

    public function editAction(Request $request, Response $response, $args) {
        if($this->auth->hasPermissionFor('edit-library') === FALSE) {
            return $this->renderAccessDenied($response);
        }
        $this->completeArgsForDetailView($args['itemParams'], $args);
        if($args['album'] === NULL) {
            $args['action'] = '404';
            $this->view->render($response, 'surrounding.htm', $args);
            return $response->withStatus(404);
        }
        $args['action'] = 'maintainance.albumdebug';
        $args['activeTab'] = 'editall';
        $trackInstances = $args['itemlist'];
        $args['itemlist'] = [];
        foreach($trackInstances as $track) {
            $args['itemlist'][$track->getUid()] = $track;
        }
        $args['discogstracks'] = array();
        $args['matchmapping'] = array();
        $args['renderitems'] = $this->getRenderItems($args['itemlist'], $args['album']);
        $args['guessmapping'] = array_keys($args['itemlist']);


        $discogsId = $request->getParam('discogsid');
        if($discogsId > 0) {

            /* possible usecases:
             * we have same track amount on local side and discogs side
             *   each local track matches to one discogs track
             *   one ore more local track does not have a match on the discogs side
             *   two local tracks matches one discogs-track 
             * 
             * we have more tracks on the local side
             *   we have dupes on the local side
             *   we have tracks on the local side that dous not exist on the discogs side
             * 
             * we have more tracks on the discogs side
             *   all local tracks exists on the discogs side
             *   some local tracks does not have a track on the discogs side
             * 
             * 
             */

            // TODO: catch error in case we cant retrieve release informations (example discogsID : 777655 )
            $this->discogsitemRepo->retrieveAlbum($discogsId);
            $args['discogstracks'] = $this->discogsitemRepo->trackContexts;
            $args['discogsalbum'] = $this->discogsitemRepo->albumContext;
            $args['discogsimages'] = $this->discogsitemRepo->images;
            $args['activeTab'] = 'discogs';
            $args['discogsid'] = $discogsId;
            $trackMatcher = new \Slimpd\Modules\Discogs\TrackMatcher(
                $this->discogsitemRepo->trackContexts,
                $args['itemlist'],
                $args['renderitems']['artists']
            );
            $args['guessmapping'] = $trackMatcher->guessTrackMatch();
        }

        // fetch manually edited properties for highlighting input fields
        $args['editorials'] = array();
        foreach($args['itemlist'] as $trackInstance) {
            $editorials = $this->container->editorialRepo->getInstancesByAttributes([
                'relPathHash' => $trackInstance->getRelPathHash(),
                'itemType' => 'track'
            ]);
            foreach($editorials as $editorial) {
                $args['editorials'][$editorial->getRelPathHash()][$editorial->getColumn()] = $editorial->getValue();
            }
        }
        $this->view->render($response, 'surrounding.htm', $args);
        return $response;
    }

    protected function completeArgsForDetailView($albumUid, &$args) {
        $args['album'] = $this->container->albumRepo->getInstanceByAttributes(array('uid' => $albumUid));
        if($args['album'] === NULL) {
            return;
        }
        $args['itemlist'] = $this->container->trackRepo->getInstancesByAttributes(
            ['albumUid' => $albumUid], FALSE, 200, 1, 'trackNumber ASC'
        );
        $args['renderitems'] = $this->getRenderItems($args['album'], $args['itemlist']);
        $args['albumimages'] = [];
        $args['bookletimages'] = [];
        $bitmaps = $this->container->bitmapRepo->getInstancesByAttributes(
            ['albumUid' => $albumUid], FALSE, 200, 1, 'imageweight'
        );
        $foundFront = FALSE;
        foreach($bitmaps as $bitmap) {
            switch($bitmap->getPictureType()) {
                case 'front':
                    if($foundFront === TRUE && $this->conf['images']['hide_front_duplicates'] === '1') {
                        continue;
                    }
                    $args['albumimages'][] = $bitmap;
                    $foundFront = TRUE;
                    break;
                case 'booklet':
                    $args['bookletimages'][] = $bitmap;
                    break;
                default:
                    $args['albumimages'][] = $bitmap;
                    break;
            }
        }
        $args['breadcrumb'] = \Slimpd\Modules\Filebrowser\Filebrowser::fetchBreadcrumb($args['album']->getRelPath());
        return;
    }

    public function updateAction(Request $request, Response $response, $args) {
        if($this->auth->hasPermissionFor('edit-library') === FALSE) {
            return $this->renderAccessDenied($response);
        }
        $postParams = $request->getParsedBody();
        if($postParams === NULL) {
            $postParams = array();
        }
        // TODO: fetch editorials for album properties for highlighting input fields
        //$albumEditorials = array();
        if(array_key_exists('album', $postParams) === FALSE) {
            $postParams['album'] = array();
        }
        // NOTE: the form submissions is limited on changed input-fields on the client-side
        // so if we do have only album attributes we have to fetch all track-uids
        foreach($this->albumRepo->getTrackUidsForAlbumUid($args['itemUid']) as $trackUid){
            if(isset($postParams['track'][$trackUid]) === FALSE) {
                $postParams['track'][$trackUid] = array();
            }
        }
        if(isset($postParams['discogsid'])) {
            $this->discogsitemRepo->retrieveAlbum($postParams['discogsid']);
        }
        foreach($postParams['track'] as $trackUid => $properties) {
            $track = $this->container->trackRepo->getInstanceByAttributes(['uid' => $trackUid]);

            // set album properties for all tracks
            foreach($postParams['album'] as $setterName => $value) {
                $this->container->editorialRepo->insertTrackBasedInstance($track, $setterName, $value);
            }

            // set track properties
            foreach($properties as $setterName => $value) {
                ($setterName === 'setDiscogsTrackIndex')
                    ? $this->container->editorialRepo->marryTrack($track, $this->discogsitemRepo->trackContexts[$value])
                    : $this->container->editorialRepo->insertTrackBasedInstance($track, $setterName, $value);
            }
        }
        // TODO: highlight all input fields that already has an editorial value
        $newResponse = $response;
        return $newResponse->withJson(
            notifyJson("saved successful<br><strong>NOTE:</strong> you have to apply changes for taking effect", 'success')
        );
    }

}
