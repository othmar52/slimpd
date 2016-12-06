<?php
namespace Slimpd\Modules\Importer;
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
class FilesystemReader extends \Slimpd\Modules\Importer\AbstractImporter {

    // a whitelist with common directory names like "cover", "artwork" 
    protected $artworkDirNames = array();

    // a list with real existing directories which maches whitelist entries 
    protected $artworkDirCache = array();

    // a list with filepaths of already scanned directories
    protected $dirImgCache = array();

    // result
    public $foundImgPaths = array();

    protected function getCachedOrScan($dirPath) {
        $dirHash = getFilePathHash($dirPath);
        // make sure that a single directory will not be scanned twice
        // so check if we have scanned the directory already
        if(array_key_exists($dirHash, $this->dirImgCache) === TRUE) {
            // add cached image paths to result set
            foreach($this->dirImgCache[$dirHash] as $imgPath) {
                $this->foundImgPaths[$imgPath] = $imgPath;
            }
            return;
        }

        // read from filesystem
        $scanned = $this->getDirectoryFiles(
            $this->conf['mpd']['musicdir'] . $dirPath
        );

        // create cache entry cache array
        $this->dirImgCache[$dirHash] = [];


        foreach($scanned as $imgPath) {
            // remove prefixed music directory
            $relPath = substr($imgPath, strlen($this->conf['mpd']['musicdir']));
            // write found files to cache array
            $this->dirImgCache[$dirHash][$relPath] = $relPath;
            // add to result set
            $this->foundImgPaths[$relPath] = $relPath;
        }
    }

    public function getFilesystemImagesForMusicFile($musicFilePath) {
        // reset result
        $this->foundImgPaths = [];

        // makes sure we have pluralized common directory names
        $this->pluralizeArtworkDirNames();

        $directory = dirname($musicFilePath) . DS;

        if($this->conf['images']['look_current_directory']) {
            $this->getCachedOrScan($directory);
        }

        if($this->conf['images']['look_cover_directory']) {
            // search for specific named subdirectories
            foreach($this->lookupSpecialDirNames($directory) as $specialDir) {
                $this->getCachedOrScan($directory . $specialDir);
            }
        }

        if($this->conf['images']['look_silbling_directory']) {
            $parentDir = dirname($directory) . DS;
            // search for specific named silbling directories
            foreach($this->lookupSpecialDirNames($parentDir) as $specialDir) {
                $this->getCachedOrScan($parentDir . $specialDir);
            }
        }

        if($this->conf['images']['look_parent_directory'] && count($this->foundImgPaths) === 0) {
            $parentDir = dirname($directory) . DS;
            $this->getCachedOrScan($parentDir);
        }
        return $this->foundImgPaths;
    }

    protected function lookupSpecialDirNames($parentPath) {
        if(is_dir($this->conf['mpd']['musicdir'] . $parentPath) === FALSE) {
            return;
        }
        $dirHash = getFilePathHash($parentPath);
        // make sure that a single directory will not be scanned twice
        // so check if we have scanned the directory already for special names directories
        if(array_key_exists($dirHash, $this->artworkDirCache) === TRUE) {
            return $this->artworkDirCache[$dirHash];
        }

        // create new cache entry
        $this->artworkDirCache[$dirHash] = [];

        // scan filesystem
        $handle = opendir($this->conf['mpd']['musicdir'] . $parentPath);
        while($dirname = readdir ($handle)) {
            // skip files
            if(is_dir($this->conf['mpd']['musicdir'] . $parentPath . $dirname) === FALSE) {
                continue;
            }
            // check if directory name matches configured values 
            if(in_array(az09($dirname), $this->artworkDirNames) === FALSE) {
                continue;
            }

            // add matches to cache result set
            $this->artworkDirCache[$dirHash] = [$dirname];
        }
        closedir($handle);
        return $this->artworkDirCache[$dirHash];
    } 

    protected function pluralizeArtworkDirNames() {
        if(count($this->artworkDirNames)>0) {
            // we already have pluralized those strings
            return;
        }
        foreach($this->conf['images']['common_artwork_dir_names'] as $dirname) {
            $this->artworkDirNames[] = az09($dirname);
            $this->artworkDirNames[] = az09($dirname) . 's';
        }
    }


    /**
     * getDirectoryFiles() read all files of given directory without recursion
     * @param $dir (string): Directory to search
     * @param $ext (string): fileextension or name of configured fileextension group
     * @param $addFilePath (boolean): prefix every matching file with input-dir in output array-entries
     * @param $checkMimeType (boolean): perform a mimetype check and skip file if mimetype dous not match configuration
     * 
     * @return (array) : filename-strings
     */
    public function getDirectoryFiles($dir, $ext = "images", $addFilePath = TRUE, $checkMimeType = TRUE) {
        $foundFiles = array();
        if(is_dir($dir) == FALSE) {
            return $foundFiles;
        }

        $validExtensions = array(strtolower($ext));
        if(array_key_exists($ext, $this->conf["mimetypes"])) {
            if(is_array($this->conf["mimetypes"][$ext]) === TRUE) {
                $validExtensions = array_keys($this->conf["mimetypes"][$ext]);
            }
            if(is_string($this->conf["mimetypes"][$ext]) === TRUE) {
                $checkMimeType = FALSE;
            }
        }

        $dir = appendTrailingSlash($dir);
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $handle = opendir ($dir);
        while ($file = readdir ($handle)) {
            $foundExt = $this->container->filesystemUtility->getFileExt($file);
            if(is_dir($dir . $file) === TRUE) {
                continue;
            }
            if(in_array($foundExt, $validExtensions) === FALSE) {
                continue;
            }
            if($checkMimeType == TRUE && array_key_exists($ext, $this->conf["mimetypes"])) {
                if(finfo_file($finfo, $dir.$file) !== $this->conf["mimetypes"][$ext][$foundExt]) {
                    continue;
                }
            }
            $foundFiles[] = (($addFilePath == TRUE)? $dir : "") . $file;
        }

        finfo_close($finfo);
        closedir($handle);
        return $foundFiles;
    }
}
