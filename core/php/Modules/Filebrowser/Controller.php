<?php
namespace Slimpd\Modules\Filebrowser;
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

    public function index(Request $request, Response $response, $args) {
        if($this->auth->hasPermissionFor('filebrowser') === FALSE) {
            return $this->renderAccessDenied($response);
        }
        $args['itemParams'] = $this->conf['mpd']['musicdir'];
        $args['hotlinks'] = array();
        #$args['hideQuicknav'] = 1;
        foreach(trimExplode("\n", $this->conf['filebrowser']['hotlinks'], TRUE) as $path){
            $args['hotlinks'][] =  \Slimpd\Modules\Filebrowser\Filebrowser::fetchBreadcrumb($path);
        }
        return $this->dircontent($request, $response, $args);
    }

    public function dircontent(Request $request, Response $response, $args) {
        if($this->auth->hasPermissionFor('filebrowser') === FALSE) {
            return $this->renderAccessDenied($response);
        }
        $args['action'] = 'filebrowser';
        $fileBrowser = $this->filebrowser;
        $fileBrowser->itemsPerPage = $this->conf['filebrowser']['max-items'];
        $fileBrowser->currentPage = intval($request->getParam('page'));
        $fileBrowser->currentPage = ($fileBrowser->currentPage === 0) ? 1 : $fileBrowser->currentPage;
        switch($request->getParam('filter')) {
            case 'dirs':
                $fileBrowser->filter = 'dirs';
                break;
            case 'files':
                $fileBrowser->filter = 'files';
                break;
            default :
                break;
        }

        switch($request->getParam('neighbour')) {
            case 'next':
                $fileBrowser->getNextDirectoryContent($args['itemParams']);
                break;
            case 'prev':
                $fileBrowser->getPreviousDirectoryContent($args['itemParams']);
                break;
            case 'up':
                $parentPath = dirname($args['itemParams']);
                if($parentPath === '.') {
                    $uri = $request->getUri()->withPath(
                        $this->router->pathFor('filebrowser')
                    )->getPath() . getNoSurSuffix($this->view->getEnvironment()->getGlobals()['nosurrounding']);
                    return $response->withRedirect($uri, 403);
                }
                $fileBrowser->getDirectoryContent($parentPath);
                break;
            default:
                $fileBrowser->getDirectoryContent($args['itemParams']);
                break;
        }

        $args['directory'] = $fileBrowser->directory;
        $args['breadcrumb'] = $fileBrowser->breadcrumb;
        $args['subDirectories'] = $fileBrowser->subDirectories;
        $args['files'] = $fileBrowser->files;
        $args['filter'] = $fileBrowser->filter;

        switch($fileBrowser->filter) {
            case 'dirs':
                $totalFilteredItems = $fileBrowser->subDirectories['total'];
                $args['showDirFilterBadge'] = FALSE;
                $args['showFileFilterBadge'] = FALSE;
                break;
            case 'files':
                $totalFilteredItems = $fileBrowser->files['total'];
                $args['showDirFilterBadge'] = FALSE;
                $args['showFileFilterBadge'] = FALSE;
                break;
            default :
                $totalFilteredItems = 0;
                $args['showDirFilterBadge'] = ($fileBrowser->subDirectories['count'] < $fileBrowser->subDirectories['total'])
                    ? TRUE
                    : FALSE;

                $args['showFileFilterBadge'] = ($fileBrowser->files['count'] < $fileBrowser->files['total'])
                    ? TRUE
                    : FALSE;
                break;
        }

        $args['paginator'] = new \JasonGrimes\Paginator(
            $totalFilteredItems,
            $fileBrowser->itemsPerPage,
            $fileBrowser->currentPage,
            $this->conf['config']['absRefPrefix'] . 'filebrowser/'.$fileBrowser->directory . '?filter=' . $fileBrowser->filter . '&page=(:num)'
        );
        $args['paginator']->setMaxPagesToShow(paginatorPages($fileBrowser->currentPage));
        $this->view->render($response, 'surrounding.htm', $args);
        return $response;
    }

    public function widgetDirectory(Request $request, Response $response, $args) {
        useArguments($request);
        if($this->auth->hasPermissionFor('filebrowser') === FALSE) {
            return $this->renderAccessDenied($response);
        }
        $fileBrowser = $this->filebrowser;
        $fileBrowser->getDirectoryContent($args['itemParams']);
        $args['directory'] = $fileBrowser->directory;
        $args['breadcrumb'] = $fileBrowser->breadcrumb;
        $args['subDirectories'] = $fileBrowser->subDirectories;
        $args['files'] = $fileBrowser->files;

        // try to fetch album entry for this directory
        $args['album'] = $this->albumRepo->getInstanceByAttributes(
            array('relPathHash' => getFilePathHash($fileBrowser->directory))
        );

        $this->view->render($response, 'modules/widget-directory.htm', $args);
        return $response;
    }

    public function deliverAction(Request $request, Response $response, $args) {
        $path = $args['itemParams'];
        if(is_numeric($path)) {
            $track = $this->trackRepo->getInstanceByAttributes(array('uid' => (int)$args['itemParams']));
            $path = ($track === NULL) ? '' : $track->getRelPath();
        }
        $newResponse = $response;

        // IMPORTANT TODO: check if a proper check is necessary
        if($this->filesystemUtility->isInAllowedPath($path) === FALSE) {
            return $this->deliveryError($newResponse, 404);
        }
        return $this->deliver(
            $request,
            $response,
            $this->filesystemUtility->trimAltMusicDirPrefix($path)
        );
    }


    /**
     * IMPORTANT TODO: check why performance on huge files is so bad (seeking-performance in large mixes is pretty poor compared to serving the mp3-mix directly)
     */
    protected function deliver($request, $response, $file) {

        /**
         * Copyright 2012 Armand Niculescu - media-division.com
         * Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
         * 1. Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
         * 2. Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
         * THIS SOFTWARE IS PROVIDED BY THE FREEBSD PROJECT "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE FREEBSD PROJECT OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
         */
     
     
        //- turn off compression on the server
        if(function_exists("apache_setenv")) {
            @apache_setenv("no-gzip", 1);
        }
        @ini_set("zlib.output_compression", "Off");

        // convert to absolute filepath

        // sanitize the file request, keep just the name and extension
        $filePath  = $this->filesystemUtility->getFileRealPath($file);
        $pathParts = pathinfo($filePath);
        $fileName  = $pathParts["basename"];
        $file = @fopen($filePath,"rb");
        if(!$file) {
            return $this->deliveryError($response, 500);
        }
        $fileSize = filesize($filePath);
        $seekStart = 0;
        $seekEnd = $fileSize - 1;

        // set the headers, prevent caching
        $response = $response->withHeader("Content-Length", $fileSize)
            ->withHeader("Content-Type", $this->filesystemUtility->getMimeType($fileName))
            ->withHeader("Accept-Ranges", "bytes")
            ->withHeader("Pragma", "public")
            ->withHeader("Expires", "-1")
            ->withHeader("Cache-Control", "public, must-revalidate, post-check=0, pre-check=0")
            ->withHeader("Content-Disposition", "attachment; filename=\"".str_replace('"', "_",$fileName)."\"");

        //check if http_range is sent by browser (or download manager)
        $this->checkPartialContentDelivery($request, $response, $seekStart, $seekEnd, $fileSize);

        // allow a file to be streamed instead of sent as an attachment
        // set appropriate headers for attachment or streamed file
        if($request->getParam("stream") === "1") {
            $response = $response->withHeader("Content-Disposition", "inline");
        }

        // do not block other requests of this client
        session_write_close();
        set_time_limit(0);
        fseek($file, $seekStart);
        while(!feof($file)) {
            $response->getBody()->write(@fread($file, 1024*8));
            if (connection_status()!=0) {
                @fclose($file);
                return $response;
            }
        }
        @fclose($file);
        return $response;
    }

    protected function checkPartialContentDelivery($request, &$response, &$seekStart, &$seekEnd, $fileSize) {

        $requestedRange = $request->getServerParam("HTTP_RANGE");
        if($requestedRange === NULL) {
            return;
        }

        $rangeParams = trimExplode("=", $requestedRange, TRUE, 2);
        if($rangeParams[0] !== "bytes") {
            return $this->deliveryError($response, 416);
        }
        //multiple ranges could be specified at the same time, but for simplicity only serve the first range
        $multipleRanges = trimExplode(",", $rangeParams[1], TRUE);

        //figure out download piece from range (if set)
        //set start and end based on range (if set)
        $seekRange = trimExplode("-", $multipleRanges[0], TRUE);
        $seekStart = max(abs(intval($seekRange[0])),0);
        if(isset($seekRange[1]) === TRUE) {
            $seekEnd = min(abs(intval($seekRange[1])),($fileSize - 1));
        }

        //Only send partial content header if downloading a piece of the file (IE workaround)
        if ($seekStart > 0 || $seekEnd < ($fileSize - 1)) {
            $response = $response->withStatus(206) // Partial Content
                ->withHeader("Content-Range", "bytes ".$seekStart."-".$seekEnd."/".$fileSize)
                ->withHeader("Content-Length", ($seekEnd - $seekStart + 1));
        }
    }


    public function deliveryError($response, $code = 401, $msg = null) {
        $msgs = array(
            400 => "Bad Request",
            401 => "Unauthorized",
            402 => "Payment Required",
            403 => "Forbidden",
            404 => "Not Found",
            416 => "Requested Range Not Satisfiable",
            500 => "Internal Server Error"
        );
        if(!$msg) {
            // TODO: catch possible invalid array key error
            $msg = $msgs[$code];
        }

        // TODO: sliMpd branding of all error pages
        $response->getBody()->write(
            sprintf("<html><head><title>%s %s</title></head><body><h1>%s</h1></body></html>", $code, $msg, $msg)
        );
        return $response->withStatus($code);
    }
}
