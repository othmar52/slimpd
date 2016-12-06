<?php
namespace Slimpd\Modules\Filebrowser;
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

class Filebrowser {
    protected $container;
    public $directory;
    public $base;
    public $subDirectories = array(
        "total" => 0,
        "count" => 0,
        "dirs" => array()
    );
    public $files = array(
        "total" => 0,
        "count" => 0,
        "music" => array(),
        "playlist" => array(),
        "info" => array(),
        "image" => array(),
        "other" => array(),
    );
    public $breadcrumb = array();
    
    public $currentPage = 1;
    public $itemsPerPage = 20;
    public $filter = "";
    
    public function __construct($container) {
        $this->container = $container;
    }

    public function getDirectoryContent($path, $ignoreLimit = FALSE, $systemdir = FALSE) {
        $path = $this->checkDirectoryAccess($path, $systemdir);

        if($path === FALSE) {
            return;
        }
        $dir = $path["dir"];
        $base = $path["base"];

        $this->breadcrumb = self::fetchBreadcrumb($dir);
        $this->directory = $dir;

        $files = scandir($base . $dir);
        natcasesort($files);
        
        // determine which portion is requestet
        $minIndex = (($this->currentPage-1) * $this->itemsPerPage);
        $minIndex = ($minIndex === 0) ? 1 : $minIndex+1;
        $maxIndex = $minIndex +  $this->itemsPerPage -1;

        /* The 2 accounts for . and .. */
        if( count($files) < 3 ) {
            return;
        }

        // create helper array only once for performance reasons
        $extTypes = $this->getExtMapping();
        foreach( $files as $file ) {
            // skip "." and ".." and hidden files
            if(substr($file,0,1) === ".") {
                continue;
            }
            if( file_exists($base. $dir . $file) === FALSE) {
                continue;
            }

            // directories
            if(is_dir($base . $dir . $file) === TRUE) {
                $this->handleDirectory($dir.$file, $minIndex, $maxIndex, $ignoreLimit);
                continue;
            }

            // files
            $this->handleFile($dir.$file, $minIndex, $maxIndex, $ignoreLimit, $extTypes);
        }
        return;
    }

    protected function handleFile($relPath, $minIndex, $maxIndex, $ignoreLimit, $extTypes) {
        $this->files["total"]++;
        if($this->filter === "dirs" && $ignoreLimit === FALSE) {
            return;
        }
        if($this->files["total"] < $minIndex && $ignoreLimit === FALSE) {
            return;
        }
        if($this->files["total"] > $maxIndex && $ignoreLimit === FALSE) {
            return;
        }
        $fileInstance = new \Slimpd\Models\File($relPath);
        $group = (isset($extTypes[$fileInstance->getExt()]) === TRUE)
            ? $extTypes[$fileInstance->getExt()]
            : "other";
        $this->files[$group][] = $fileInstance;
        $this->files["count"]++;
    }
    protected function handleDirectory($relPath, $minIndex, $maxIndex, $ignoreLimit) {
        $this->subDirectories["total"]++;
        if($this->filter === "files" && $ignoreLimit === FALSE) {
            return;
        }
        if($this->subDirectories["total"] < $minIndex && $ignoreLimit === FALSE) {
            return;
        }
        if($this->subDirectories["total"] > $maxIndex && $ignoreLimit === FALSE) {
            return;
        }
        $this->subDirectories["dirs"][] = new \Slimpd\Models\Directory($relPath);
        $this->subDirectories["count"]++;
    }

    protected function getExtMapping() {
        $extTypes = array();
        foreach($this->container->conf["musicfiles"]["ext"] as $ext) {
            $extTypes[$ext] = "music";
        }
        foreach($this->container->conf["playlists"]["ext"] as $ext) {
            $extTypes[$ext] = "playlist";
        }
        foreach($this->container->conf["infofiles"]["ext"] as $ext) {
            $extTypes[$ext] = "info";
        }
        foreach($this->container->conf["images"]["ext"] as $ext) {
            $extTypes[$ext] = "image";
        }
        return $extTypes;
    }

    protected function checkDirectoryAccess($requestedPath, $systemdir) {
        
        if($this->container->conf["mpd"]["musicdir"] === "") {
            $this->container->flash->AddMessage("error", $this->container->ll->str("error.mpd.conf.musicdir"));
            return FALSE;
        }

        $path = appendTrailingSlash($requestedPath);
        $realpath = $this->container->filesystemUtility->getFileRealPath($path) . DS;

        if($realpath === DS && $this->container->conf["mpd"]["musicdir"] !== $requestedPath) {
            $this->container->flash->AddMessage("error", $this->container->ll->str("filebrowser.invaliddir", [$requestedPath]));
            return FALSE;
        }

        $base = $this->container->conf["mpd"]["musicdir"];
        $path = ($requestedPath === $base) ? "" : $path;
        $return = ["base" => $base, "dir" => $path];
        

        // avoid path disclosure outside relevant directories
        if($realpath === FALSE && $systemdir === FALSE) {
            $this->container->flash->AddMessage("error", $this->container->ll->str("filebrowser.realpathempty", [$realpath]));
            return FALSE;
        }

        if($systemdir === TRUE && in_array($path, ["localdata/cache/", "localdata/embedded/", "localdata/peakfiles/"]) === TRUE) {
            $return['base'] = APP_ROOT;
            $realpath = realpath(APP_ROOT . $path) . DS;
        }
        
        if($this->container->filesystemUtility->isInAllowedPath($path) === FALSE && $systemdir === FALSE) {
            // TODO: remove this error message "outsiderealpath"! invaliddir should be enough
            // $this->container->flash->AddMessage("error", $this->container->ll->str("filebrowser.outsiderealpath", [$realpath, $this->conf["mpd"]["musicdir"]]));
            $this->container->flash->AddMessage("error", $this->container->ll->str("filebrowser.invaliddir", [$requestedPath]));
            return FALSE;
        }

        // check filesystem permission
        if(is_readable($realpath) === FALSE) {
            $this->container->flash->AddMessage("error", $this->container->ll->str("filebrowser.dirpermission", [$path]));
            return FALSE;
        }

        // TODO: remove possibility for non music dir at all
        //if($this->conf["filebrowser"]["restrict-to-musicdir"] !== "1" || $systemdir === TRUE) {
        //    return $return;
        //}

        return $return;
    }
    
    protected function getParentDirSelf($path) {
        $parentPath = dirname($path);
        $isSysDir = FALSE;
        if($parentPath === ".") {
            $parentPath = $this->container->conf["mpd"]["musicdir"];
            $isSysDir = TRUE;
        }
        // fetch content of the parent directory
        $parentDirectory = new self($this->container);
        $parentDirectory->getDirectoryContent($parentPath, TRUE, $isSysDir);
        return $parentDirectory;
    }

    /**
     * get content of the next silblings directory
     * @param string $path: directorypath
     * @return object
     */
    public function getNextDirectoryContent($path) {        
        // make sure we have directory separator as last char
        $path = appendTrailingSlash($path);
        $parentDirectory = $this->getParentDirSelf($path);

        // iterate over parentdirectories until we find the inputdirectory +1
        $found = FALSE;
        foreach($parentDirectory->subDirectories["dirs"] as $subDir) {
            
            if($found === TRUE) {
                return $this->getDirectoryContent($subDir->getRelPath());
            }
            if($subDir->getRelPath()."/" === $path) {
                $found = TRUE;
            }
        }
        // TODO: force message getting displayed immediately (currrently we need another request))
        $this->container->flash->AddMessage("error", $this->container->ll->str("filebrowser.nonextdir"));
        return $this->getDirectoryContent($path);
    }

    /**
     * get content of the previous silblings directory
     * @param string $path: directorypath
     * @return object
     */
     public function getPreviousDirectoryContent($path) {
        // make sure we have directory separator as last char
        $path = appendTrailingSlash($path);
        $parentDirectory = $this->getParentDirSelf($path);

        $prev = 0;
        
        foreach($parentDirectory->subDirectories["dirs"] as $subDir) {
            if($subDir->getRelPath()."/" === $path) {
                if($prev === 0) {
                    // TODO: force message getting displayed immediately (currrently we need another request))
                    $this->container->flash->AddMessage("error", $this->container->ll->str("filebrowser.noprevdir"));
                    return $this->getDirectoryContent($path);
                }
                return $this->getDirectoryContent($prev);
            }
            $prev = $subDir->getRelPath();
        }
        // TODO: force message getting displayed immediately (currrently we need another request))
        $this->container->flash->AddMessage("error", $this->container->ll->str("filebrowser.noprevdir"));
        return $this->getDirectoryContent($path);
    }

    public static function fetchBreadcrumb($relPath) {
        $bread = trimExplode(DS, $relPath, TRUE);
        $breadgrow = "";
        $items = array();
        foreach($bread as $part) {
            $breadgrow .= DS . $part;
            $items[] = new \Slimpd\Models\Directory($breadgrow);
        }
        return $items;
    }
}
