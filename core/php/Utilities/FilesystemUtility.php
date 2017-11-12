<?php
namespace Slimpd\Utilities;
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

class FilesystemUtility {
    protected $container;
    protected $conf;

    public function __construct($container) {
        $this->container = $container;
        $this->conf = $container->conf;
    }
    // TODO rename method because it also makes sense to trim regular music-dir-prefix
    public function trimAltMusicDirPrefix($pathString) {
        if(stripos($pathString, $this->conf['mpd']['musicdir']) === 0) {
            return substr($pathString, strlen($this->conf['mpd']['musicdir']));
        }
        $altMusicDir = $this->conf['mpd']['alternative_musicdir'];
        if(trim($altMusicDir) === "") {
            return $pathString;
        }
        if(stripos($pathString, $altMusicDir) === 0) {
            return substr($pathString, strlen($altMusicDir));
        }
        return $pathString;
    }

    function getFileExt($filePath, $toLower = TRUE) {
        //$ext = preg_replace('/^.*\./', '', $filePath);
        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
        return ($toLower === TRUE) ? strtolower($ext) : $ext;
    }

    function getFileRealPath($pathString) {
        $mpdConf = $this->conf['mpd'];
        foreach(["alternative_musicdir", "musicdir"] as $confName) {
            if(file_exists($mpdConf[$confName] . $pathString) === TRUE) {
                return realpath($mpdConf[$confName] . $pathString);
            }
        }
        if(file_exists(APP_ROOT . $pathString) === TRUE) {
           return realpath(APP_ROOT . $pathString);
        }
        return FALSE;
    }

    /**
     * checks if file path or directory path is within allowed direcories
     */
    function isInAllowedPath($itemPath) {
        
        if($this->conf['filebrowser']['restrict-to-musicdir'] === "0") {
            return TRUE;
        }
        $mpdConf = $this->conf['mpd'];
        $realPath = $this->getFileRealPath($itemPath);
        foreach(["alternative_musicdir", "musicdir"] as $confName) {
            if(stripos($realPath, $mpdConf[$confName]) === 0) {
                return TRUE;
            }
            if($realPath . DS === $mpdConf[$confName]) {
                return TRUE;
            }
        }
        return FALSE;
    }

    /**
     * checks if file path or directory path is within a special directory
     * these are tiny sample audio files shipped with sliMpd
     */
    function isSystemCheckSample($itemPath) {
        $search = APP_ROOT . 'core/templates/partials/systemcheck/waveforms/testfiles/';
        if(strpos(realpath($itemPath), $search) === 0) {
            return TRUE;
        }
        return FALSE;
    }

    /**
     * checks if file path or directory path is within our application directory
     */
    function isInAppDirectory($itemPath) {
        $realPath = realpath($itemPath);
        if($realPath === FALSE) {
            return FALSE;
        }
        foreach(["cache", "embedded", "peakfiles", "stems"] as $appDir) {
            if(strpos($realPath, APP_ROOT . 'localdata' . DS . $appDir) === 0) {
                return TRUE;
            }
        }
        return FALSE;
    }

    function getMimeType($filename) {
        $mimeExtensionMapping = parse_ini_file(APP_ROOT . "core/config/mimetypes.ini", TRUE);

        //Get Extension
        $ext = $this->getFileExt($filename);
        if(empty($ext)) {
            return "application/octet-stream";
        }
        if(isset($mimeExtensionMapping[$ext])) {
            return $mimeExtensionMapping[$ext];
        }
        return "x-extension/" . $ext;
    }

    /**
     * recursive delete directory
     */
    function rrmdir($dir) {
        if (is_dir($dir) === FALSE || $this->isInAppDirectory($dir) === FALSE) {
            return;
        }
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object === "." || $object === "..") {
                continue;
            }
            $continueWith = (is_dir($dir."/".$object)) ? "rrmdir" : "unlink";
            $this->$continueWith($dir."/".$object);
        }
        rmdir($dir);
    }


    function unlink($filePath) {
        unlink($filePath);
    }

    /**
     * performs check if ifle is within application directory and deletes the file
     */
    function rmfile($mixed) {
        if(is_string($mixed) === TRUE && $this->isInAppDirectory($mixed)) {
            @unlink($mixed);
            return;
        }
        if(is_array($mixed) === FALSE) {
            return;
        }
        foreach($mixed as $itemPath) {
            if(is_string($itemPath) === TRUE && $this->isInAppDirectory($itemPath)) {
                @unlink($itemPath);
            }
        }
    }

    function clearPhpThumbTempFiles($phpThumb) {
        foreach($phpThumb->tempFilesToDelete as $delete) {
            cliLog("deleting tmpFile " . $delete, 10);
            $this->rmfile($delete);
        }
    }

    /*
     * TODO: only take a small chunk of the file instead of reading the whole possibly huge file
     */
    function testBinary($filePath) {
        // return mime type ala mimetype extension
        $finfo = finfo_open(FILEINFO_MIME);

        //check to see if the mime-type starts with "text"
        return (substr(finfo_file($finfo, $filePath), 0, 4) == "text") ? FALSE : TRUE;
    }

    /**
     * checks if the string is parseable as XML
     */
    function isValidXml ($xmlstring) {
        libxml_use_internal_errors( true );
        $doc = new \DOMDocument('1.0', 'utf-8');
        $doc->loadXML($xmlstring);
        $errors = libxml_get_errors();
        return empty($errors);
    }

    /**
     * clears all files within localdata generated by sliMpd
     */
    public function deleteSlimpdGeneratedFiles() {
        foreach(['cache', 'embedded', 'peakfiles'] as $sysDir) {
            $fileBrowser = new \Slimpd\Modules\Filebrowser\Filebrowser($this->container);
            $fileBrowser->getDirectoryContent('localdata' . DS . $sysDir, TRUE, TRUE);
            cliLog("Deleting files and directories inside ". 'localdata' . DS . $sysDir ."/");
            foreach(['music','playlist','info','image','other'] as $key) {
                foreach($fileBrowser->files[$key] as $file) {
                    $this->rmfile(APP_ROOT . $file->getRelPath());
                }
            }
            foreach($fileBrowser->subDirectories['dirs'] as $dir) {
                $this->rrmdir(APP_ROOT . $dir->getRelPath());
            }
        }
    }
}
