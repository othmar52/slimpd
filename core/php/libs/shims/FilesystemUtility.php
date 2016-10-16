<?php
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
function getFileExt($filePath, $toLower = TRUE) {
	//$ext = preg_replace('/^.*\./', '', $filePath);
	$ext = pathinfo($filePath, PATHINFO_EXTENSION);
	return ($toLower === TRUE) ? strtolower($ext) : $ext;
}

function getFileRealPath($pathString, $conf) {
	$mpdConf = $conf['mpd'];
	foreach(["alternative_musicdir", "musicdir"] as $confName) {
		if(file_exists($mpdConf[$confName] . $pathString) === TRUE) {
			return realpath($mpdConf[$confName] . $pathString);
		}
	}
	return FALSE;
}

/**
 * checks if file path or directory path is within allowed direcories
 */
function isInAllowedPath($itemPath, $conf) {
	if($conf['filebrowser']['restrict-to-musicdir'] === "0") {
		return TRUE;
	}
	$mpdConf = $conf['mpd'];
	$realPath = getFileRealPath($itemPath, $conf);
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
 * checks if file path or directory path is within our application directory
 */
function isInAppDirectory($itemPath) {
	$realPath = realpath($itemPath);
	if($realPath === FALSE) {
		return FALSE;
	}
	foreach(["cache", "embedded", "peakfiles"] as $appDir) {
		if(strpos($realPath, APP_ROOT . 'localdata' . DS . $appDir) === 0) {
			return TRUE;
		}
	}
	return FALSE;
}

function getMimeType ($filename) {
	$mimeExtensionMapping = parse_ini_file(APP_ROOT . "core/config/mimetypes.ini", TRUE);

	//Get Extension
	$ext = strtolower(substr($filename,strrpos($filename, ".") + 1));
	if(empty($ext)) {
		return "application/octet-stream";
	}
	if(isset($mimeExtensionMapping[$ext])) {
		return $mimeExtensionMapping[$ext];
	}
	return "x-extension/" . $ext;
}


/**
 * temporary helper func to migrate tagdata from filesystem to new db table `rawtagblob`
 * TODO: remove this helper func
 * @see: php/routes/cli.php:tagdatatodb()
 */
function getTagDataFileName($relPathHash) {
	$dirDepth = 3;
	$out = ["localdata", "tagdata"];
	for ($i = 0; $i < $dirDepth; $i++) {
		$out[] = substr($relPathHash, 0, $i + 1);
	}
	return APP_ROOT . join(DS, $out); 
}

/**
 * recursive delete directory
 */
function rrmdir($dir) {
	if (is_dir($dir) === FALSE || isInAppDirectory($dir) === FALSE) {
		return;
	}
	$objects = scandir($dir);
	foreach ($objects as $object) {
		if ($object === "." || $object === "..") {
			continue;
		}
		$continueWith = (is_dir($dir."/".$object)) ? "rrmdir" : "unlink";
		$continueWith($dir."/".$object);
	}
	rmdir($dir);
}

/**
 * performs check if ifle is within application directory and deletes the file
 */
function rmfile($mixed) {
	if(is_string($mixed) === TRUE && isInAppDirectory($mixed)) {
		@unlink($mixed);
		return;
	}
	if(is_array($mixed) === FALSE) {
		return;
	}
	foreach($mixed as $itemPath) {
		if(is_string($itemPath) === TRUE && isInAppDirectory($itemPath)) {
			@unlink($itemPath);
		}
	}
}


function clearPhpThumbTempFiles($phpThumb) {
	foreach($phpThumb->tempFilesToDelete as $delete) {
		cliLog("deleting tmpFile " . $delete, 10);
		rmfile($delete);
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
 * IMPORTANT TODO: check why performance on huge files is so bad (seeking-performance in large mixes is pretty poor compared to serving the mp3-mix directly)
 */
function deliver($file, $app) {

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
	$filePath  = getFileRealPath($file);
	$pathParts = pathinfo($filePath);
	$fileName  = $pathParts["basename"];
	#$fileExt   = $pathParts["extension"];

	if (is_file($filePath) === FALSE) {
		deliveryError(404);
	}

	// IMPORTANT TODO: check if a proper check is necessary
	if(isInAllowedPath($file) === FALSE) {
		deliveryError(401);
	}

	$file = @fopen($filePath,"rb");
	if (!$file) {
		deliveryError(500);
	}

	$fileSize = filesize($filePath);

	//check if http_range is sent by browser (or download manager)
	$range = "";

	if(isset($app->environment["HTTP_RANGE"])) {
		@list($size_unit, $range_orig) = @explode("=", $app->environment["HTTP_RANGE"], 2);
		if ($size_unit == "bytes") {
			//multiple ranges could be specified at the same time, but for simplicity only serve the first range
			//http://tools.ietf.org/id/draft-ietf-http-range-retrieval-00.txt
			$tmp = trimExplode(",", $range_orig);
			$range = $tmp[0];
		} else {
			deliveryError(416);
		}
	}

	//figure out download piece from range (if set)
	@list($seekStart, $seekEnd) = @explode("-", $range, 2);

	//set start and end based on range (if set), else set defaults
	//also check for invalid ranges.
	$seekEnd   = (empty($seekEnd)) ? ($fileSize - 1) : min(abs(intval($seekEnd)),($fileSize - 1));
	$seekStart = (empty($seekStart) || $seekEnd < abs(intval($seekStart))) ? 0 : max(abs(intval($seekStart)),0);

	//Only send partial content header if downloading a piece of the file (IE workaround)
	if ($seekStart > 0 || $seekEnd < ($fileSize - 1)) {
		header("HTTP/1.1 206 Partial Content");
		header("Content-Range: bytes ".$seekStart."-".$seekEnd."/".$fileSize);
		header("Content-Length: ".($seekEnd - $seekStart + 1));
	} else {
		header("Content-Length: $fileSize");
	}

	// set the headers, prevent caching
	header("Pragma: public");
	header("Expires: -1");
	header("Cache-Control: public, must-revalidate, post-check=0, pre-check=0");

	// allow a file to be streamed instead of sent as an attachment
	// set appropriate headers for attachment or streamed file
	header("Content-Disposition: " . (
		($app->request->get("stream") === "1")
			? "attachment; filename=\"".str_replace('"', "_",$fileName)."\""
			: "inline;"
		)
	);

	header("Content-Type: " . getMimeType($fileName));
	header("Accept-Ranges: bytes");

	// do not block other requests of this client
	session_write_close();
	set_time_limit(0);
	fseek($file, $seekStart);
	while(!feof($file)) {
		print(@fread($file, 1024*8));
		ob_flush();
		flush();
		if (connection_status()!=0) {
			@fclose($file);
			$app->stop();
		}
	}
 
	@fclose($file);
	$app->stop();
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
