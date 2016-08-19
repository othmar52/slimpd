<?php

function getFilePathHash($inputString) {
	return str_pad(dechex(crc32($inputString)), 8, "0", STR_PAD_LEFT) . str_pad(strlen($inputString), 3, "0", STR_PAD_LEFT);
}

function sortHelper($string1,$string2){
	return strlen($string2) - strlen($string1);
}

function remU($input){
	return trim(preg_replace("!\s+!", " ", str_replace("_", " ", $input)));
}

function fixCaseSensitivity($input){
	if(strtolower($input) == $input) {
		return ucwords($input);
	}
	if(strtoupper($input) == $input && strlen($input)>3) {
		return ucwords(strtolower($input));
	}
	return $input;
}

function timeStringToSeconds($time) {
	$sec = 0;
	foreach (array_reverse(explode(":", $time)) as $key => $value) {
		$sec += pow(60, $key) * $value;
	}
	return $sec;
}

function cleanSearchterm($searchterm) {
	# TODO: use flattenWhitespace() in albummigrator on reading tag-information
	return flattenWhitespace(
		str_replace(["_", "-", "/", " ", "(", ")"], " ", $searchterm)
	);
}

function addStars($searchterm) {
	$str = "*" . str_replace(["_", "-", "/", " ", "(", ")"], "* *", $searchterm) . "*";
	// single letters like "o" in "typo o negative" must not get a star appended because the lack of results 
	if(preg_match("/\ ([A-Za-z]){1}\*/", $str, $matches)) {
		$str = str_replace(
			" ".$matches[1]."*",
			" ".$matches[1],
			$str
		);
	}
	return $str;
}

/**
 * Builds querystring to use for sphinx-queries
 * we have to make sure that /autocomplete and /search* gives us the same results
 * @param array $terms : array with searchphrases
 * @return string : query syntax which can be used in MATCH(:match)
 */
function getSphinxMatchSyntax(array $terms) {
	$groups = [];
	foreach($terms as $term) {
		$groups[] = "(' \"". addStars($term) . "\"')";
		$groups[] = "('\"". $term ."\"')";
		$groups[] = "('\"". str_replace(" ", "*", $term) ."\"')";
		$groups[] = "('\"". str_replace(" ", ",", $term) ."\"')";
		$groups[] = "('\"". str_replace(" ", " | ", $term) ."\"')";
		$groups[] = "('\"". str_replace(" ", "* | ", $term) ."*\"')";
	}
	return join("|\n", $groups);
}

/**
 * limit displayed pages in paginator in case we have enourmous numbers
 * @param int $currentPage : currentPage
 * @return int : pages to be displayed
 */
function paginatorPages($currentPage) {
	switch(strlen($currentPage)) {
		case "5": return 5;
		case "4": return 6;
		case "3": return 7;
		default:  return 10;
	}
}

function removeStars($searchterm) {
	return trim(str_replace("*", " ", $searchterm));
}

/**
 * replaces multiple whitespaces with a single whitespace
 */
function flattenWhitespace($input) {
	return preg_replace("!\s+!", " ", $input);
}

/**
 * @return string : empty string or get-parameter-string which is needed for Slim redirects 
 */
function getNoSurSuffix($prefixQuestionmark = TRUE) {
	return  (\Slim\Slim::getInstance()->request->get("nosurrounding") == 1)
		? (($prefixQuestionmark)? "?":"") . "nosurrounding=1"
		: "";
}

function notifyJson($message, $type="info") {
	$out = new stdClass();
	$out->notify = 1;
	$out->message = $message;
	$out->type = $type;
	deliverJson($out);
}

function deliverJson($data) {
	$newResponse = \Slim\Slim::getInstance()->response();
	$newResponse->body(json_encode($data));
	$newResponse->headers->set('Content-Type', 'application/json');
	return $newResponse;
}

/**
 * php's escapeshellarg() invalidates pathes with some specialchars
 *      escapeshellarg("/testdir/pathtest-u§s²e³l¼e¬sµsöäüß⁄x/testfile.mp3")
 *          results in "/testdir/pathtest-uselessx/testfile.mp3"
 * TODO: check if this is a security issue
 * @see: issue #4
 */
function escapeshellargDirty($input) {
	return "'" . str_replace("'", "'\"'\"'", $input) . "'";
}

function cliLog($msg, $verbosity=1, $color="default", $fatal = FALSE) {
	if($verbosity > \Slim\Slim::getInstance()->config["config"]["cli-verbosity"] && $fatal === FALSE) {
		return;
	}
	
	if(PHP_SAPI !== "cli") {
		return;
	}
	

	// TODO: read from config
	$shellColorize = TRUE;

	if($shellColorize !== TRUE) {
		echo $msg ."\n";
		ob_flush();
		return;
	}

	// TODO: check colors (especially the color and boldness after linebreaks)
	#$black 		= "33[0;30m";
	#$darkgray 	= "33[1;30m";
	#$blue 		= "33[0;34m";
	#$lightblue 	= "33[1;34m";
	#$green 		= "33[0;32m";
	#$lightgreen = "33[1;32m";
	#$cyan 		= "33[0;36m";
	#$lightcyan 	= "33[1;36m";
	#$red 		= "33[0;31m";
	#$lightred 	= "33[1;31m";
	#$purple 	= "33[0;35m";
	#$lightpurple= "33[1;35m";
	#$brown 		= "33[0;33m";
	#$yellow 	= "33[1;33m";
	#$lightgray 	= "33[0;37m";
	#$white 		= "33[1;37m";
	switch($color) {
		case "green":  $prefix = "\033[32m"; $suffix = "\033[37m"; break;
		case "yellow": $prefix = "\033[33m"; $suffix = "\033[37m"; break;
		case "red":    $prefix = "\033[1;31m"; $suffix = "\033[37m"; break;
		case "cyan":   $prefix = "\033[36m"; $suffix = "\033[37m"; break;
		case "purple": $prefix = "\033[35m"; $suffix = "\033[37m"; break;
		default:       $prefix = "";         $suffix = "";         break;
	}
	echo $prefix . $msg . $suffix . "\n";
	ob_flush();
}

function fileLog($mixed) {
	$filename = APP_ROOT . "cache/log-" . date("Y-M-d") . ".log";
	if(is_string($mixed) === TRUE) {
		$data = $mixed . "\n";
	}
	if(is_array($mixed) === TRUE) {
		$data = print_r($mixed,1) . "\n";
	}
	file_put_contents($filename, $data, FILE_APPEND);
}


/**
 * converts exotic characters in similar [A-Za-z0-9]
 * and removes all other characters (also whitspaces and punctiations)
 * 
 * @param $string string	input string
 * @return string the converted string
 */
function az09($string, $preserve = "", $strToLower = TRUE) {
	$charGroup = array();
	$charGroup[] = array("a","à","á","â","ã","ä","å","ª");
	$charGroup[] = array("A","À","Á","Â","Ã","Ä","Å");
	$charGroup[] = array("e","é","ë","ê","è");
	$charGroup[] = array("E","È","É","Ê","Ë","€");
	$charGroup[] = array("i","ì","í","î","ï");
	$charGroup[] = array("I","Ì","Í","Î","Ï","¡");
	$charGroup[] = array("o","ò","ó","ô","õ","ö","ø");
	$charGroup[] = array("O","Ò","Ó","Ô","Õ","Ö");
	$charGroup[] = array("u","ù","ú","û","ü");
	$charGroup[] = array("U","Ù","Ú","Û","Ü");
	$charGroup[] = array("n","ñ");
	$charGroup[] = array("y","ÿ","ý");
	$charGroup[] = array("Y","Ý","Ÿ");
	$charGroup[] = array("x","×");
	$charGroup[] = array("ae","æ");
	$charGroup[] = array("AE","Æ");
	$charGroup[] = array("c","ç","¢","©");
	$charGroup[] = array("C","Ç");
	$charGroup[] = array("D","Ð");
	$charGroup[] = array("s","ß","š");
	$charGroup[] = array("S","$","§","Š");
	$charGroup[] = array("tm","™");
	$charGroup[] = array("r","®");
	#$charGroup[] = array("(","{", "[", "<");
	#$charGroup[] = array(")","}", "]", ">");
	$charGroup[] = array("0","Ø");
	$charGroup[] = array("2","²");
	$charGroup[] = array("3","³");
	$charGroup[] = array("and","&");
	for($cgIndex=0; $cgIndex<count($charGroup); $cgIndex++){
		for($charIndex=1; $charIndex<count($charGroup[$cgIndex]); $charIndex++){
			if(strpos($preserve, $charGroup[$cgIndex][$charIndex]) !== FALSE) {
				continue;
			}
			$string = str_replace(
				$charGroup[$cgIndex][$charIndex],
				$charGroup[$cgIndex][0],
				$string
			);
		}
	}
	unset($charGroup);
	$string = preg_replace("/[^a-zA-Z0-9". $preserve ."]/", "", $string);
	$string = ($strToLower === TRUE) ? strtolower($string) : $string;
	return($string);
}


/**
 * Explodes a string and trims all values for whitespace in the ends.
 * If $onlyNonEmptyValues is set, then all blank ("") values are removed.
 * Usage: 256
 *
 * @param   string      Delimiter string to explode with
 * @param   string      The string to explode
 * @param   boolean     If set, all empty values will be removed in output
 * @param   integer     If positive, the result will contain a maximum of
 *                       $limit elements, if negative, all components except
 *                       the last -$limit are returned, if zero (default),
 *                       the result is not limited at all. Attention though
 *                       that the use of this parameter can slow down this
 *                       function.
 * @return  array       Exploded values
 */
function trimExplode($delim, $string, $removeEmptyValues = FALSE, $limit = 0) {
	$explodedValues = explode($delim, $string);
	$result = array_map("trim", $explodedValues);

	if ($removeEmptyValues) {
		$temp = array();
		foreach ($result as $value) {
			if ($value !== "") {
				$temp[] = $value;
			}
		}
		$result = $temp;
	}

	if($limit === 0) {
		return $result;
	}
	if ($limit < 0) {
		return array_slice($result, 0, $limit);
	}
	if (count($result) > $limit) {
		$lastElements = array_slice($result, $limit - 1);
		$result = array_slice($result, 0, $limit - 1);
		$result[] = implode($delim, $lastElements);
	}
	return $result;
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
function getDirectoryFiles($dir, $ext="images", $addFilePath = TRUE, $checkMimeType = TRUE) {
	$foundFiles = array();
	if( is_dir($dir) == FALSE) {
	  return $foundFiles;
	}
	
	$app = \Slim\Slim::getInstance();
	$validExtensions = array(strtolower($ext));
	if(array_key_exists($ext, $app->config["mimetypes"])) {
		if(is_array($app->config["mimetypes"][$ext]) === TRUE) {
			$validExtensions = array_keys($app->config["mimetypes"][$ext]);
		}
		if(is_string($app->config["mimetypes"][$ext]) === TRUE) {
			$checkMimeType = FALSE;
		}
	}
	// make sure we have a trailing slash
	$dir = rtrim($dir, DS) . DS;
	
	$finfo = finfo_open(FILEINFO_MIME_TYPE);
	$handle=opendir ($dir);
	while ($file = readdir ($handle)) {
		$foundExt = strtolower(preg_replace("/^.*\./", "", $file));
		if(is_dir($dir . $file) === TRUE) {
			continue;
		}
		if(in_array($foundExt, $validExtensions) === FALSE) {
			continue;
		}
		if($checkMimeType == TRUE && array_key_exists($ext, $app->config["mimetypes"])) {
			if(finfo_file($finfo, $dir.$file) !== $app->config["mimetypes"][$ext][$foundExt]) {
				continue;
			}
		}
		$foundFiles[] = (($addFilePath == TRUE)? $dir : "") . $file;
	}

	finfo_close($finfo);
	closedir($handle);
	return $foundFiles;
}

function path2url($mixed) {
	if(is_array($mixed) === TRUE) {
		$mixed = join("", $mixed);
	}
	// rawurlencode but preserve slashes
	return str_replace("%2F", "/", rawurlencode($mixed));
}

function getDatabaseDiffConf($app) {
	return array(
		"host" => $app->config["database"]["dbhost"],
		"user" => $app->config["database"]["dbusername"],
		"password" => $app->config["database"]["dbpassword"],
		"db" => $app->config["database"]["dbdatabase"],
		"savedir" => APP_ROOT . "config/dbscheme",
		"verbose" => "On",
		"versiontable" => "db_revisions",
		"aliastable" => "db_alias",
		"aliasprefix" => "slimpd_v"
	);
}

function formatByteSize($bytes) {
	if ($bytes >= 1073741824) {
		return number_format($bytes / 1073741824, 2) . " GB";
	}
	if ($bytes >= 1048576) {
		return number_format($bytes / 1048576, 2) . " MB";
	}
	if ($bytes >= 1024) {
		return number_format($bytes / 1024, 2) . " KB";
	}
	if ($bytes > 1) {
		return $bytes . " bytes";
	}
	if ($bytes == 1) {
		return $bytes . " byte";
	}
	return "0 bytes";
}

function uniqueArrayOrderedByRelevance(array $input) {
	$acv = array_count_values($input);
	arsort($acv); 
	return array_keys($acv);	
}

/// build a list of trigrams for a given keywords
function buildTrigrams ( $keyword ) {
	$pattern = "__" . $keyword . "__";
	$trigrams = "";
	for ($charIndex=0; $charIndex<strlen($pattern)-2; $charIndex++) {
		$trigrams .= substr($pattern, $charIndex, 3 ) . " ";
	}
	return $trigrams;
}

function MakeSuggestion($keyword, $sphinxPDO) {
	$trigrams = buildTrigrams($keyword);
	$query = "\"$trigrams\"/1";
	$len = strlen($keyword);

	$delta = LENGTH_THRESHOLD;
	$stmt = $sphinxPDO->prepare("
		SELECT *, weight() as w, w+:delta-ABS(len-:len) as myrank
		FROM slimpdsuggest
		WHERE MATCH(:match) AND len BETWEEN :lowlen AND :highlen
		ORDER BY myrank DESC, freq DESC
		LIMIT 0,:topcount OPTION ranker=wordcount
	");

	$stmt->bindValue(":match", $query, PDO::PARAM_STR);
	$stmt->bindValue(":len", $len, PDO::PARAM_INT);
	$stmt->bindValue(":delta", $delta, PDO::PARAM_INT);
	$stmt->bindValue(":lowlen", $len - $delta, PDO::PARAM_INT);
	$stmt->bindValue(":highlen", $len + $delta, PDO::PARAM_INT);
	$stmt->bindValue(":topcount",TOP_COUNT, PDO::PARAM_INT);
	$stmt->execute();


	if (!$rows = $stmt->fetchAll()) {
		return false;
	}

	// further restrict trigram matches with a sane Levenshtein distance limit
	foreach ($rows as $match) {
		$suggested = $match["keyword"];
		if (levenshtein($keyword, $suggested) <= LEVENSHTEIN_THRESHOLD) {
			return $suggested;
		}
	}
	return $keyword;
}

function MakePhaseSuggestion($words, $query, $sphinxPDO) {
	$suggested = array();
	$llimf = 0;
	$idx = 0;
	foreach ($words as $key => $word) {
		if ($word["docs"] != 0) {
			$llimf +=$word["docs"];
		}
		$idx++;
	}
	if($idx === 0) {
		return FALSE;
	}
	$llimf = $llimf / ($idx * $idx);
	$mis = [];
	foreach ($words  as $key => $word) {
		if ($word["docs"] == 0 | $word["docs"] < $llimf) {
			$mis[] = $word["keyword"];
		}
	}
	if(count($mis) < 1) {
		return FALSE;
	}
	foreach ($mis as $m) {
		$re = MakeSuggestion($m, $sphinxPDO);
		if ($re && $m !== $re) {
			$suggested[$m] = $re;
		}
	}
	if(count($words) ==1 && empty($suggested)) {
		return FALSE;
	}
	$phrase = explode(" ", $query);
	foreach ($phrase as $k => $word) {
		if (isset($suggested[strtolower($word)])) {
			$phrase[$k] = $suggested[strtolower($word)];
		}
	}
	return join(" ", $phrase);
}


function addRenderItem($instance, &$return) {
	$class = get_class($instance);
	if($class === "Slimpd\Models\Artist") {
		$return["artists"][$instance->getId()] = $instance;
		return;
	}
	if($class === "Slimpd\Models\Label") {
		$return["labels"][$instance->getId()] = $instance;
		return;
	}
	if($class === "Slimpd\Models\Genre") {
		$return["genres"][$instance->getId()] = $instance;
		return;
	}
	if($class === "Slimpd\Models\Track") {
		if(isset($return["itembreadcrumbs"][$instance->getRelativePathHash()]) === FALSE) {
			$return["itembreadcrumbs"][$instance->getRelativePathHash()] = \Slimpd\filebrowser::fetchBreadcrumb($instance->getRelativePath());
		}
		return;
	}
	if($class === "Slimpd\Models\Album") {
		$return["albums"][$instance->getId()] = $instance;
		if(isset($return["itembreadcrumbs"][$instance->getRelativePathHash()]) === FALSE) {
			$return["itembreadcrumbs"][$instance->getRelativePathHash()] = \Slimpd\filebrowser::fetchBreadcrumb($instance->getRelativePath());
		}
		return;
	}
}

function getRenderItems() {
	$args = func_get_args();
	$return = array(
		"genres" => call_user_func_array(array("\\Slimpd\\Models\\Genre","getInstancesForRendering"), $args),
		"labels" => call_user_func_array(array("\\Slimpd\\Models\\Label","getInstancesForRendering"), $args),
		"artists" => call_user_func_array(array("\\Slimpd\\Models\\Artist","getInstancesForRendering"), $args),
		"albums" => call_user_func_array(array("\\Slimpd\\Models\\Album","getInstancesForRendering"), $args),
		"itembreadcrumbs" => [],
	);
	
	foreach($args as $argument) {
		if(is_object($argument) === TRUE) {
			addRenderItem($argument, $return);
		}
		if(is_array($argument) === TRUE) {
			foreach($argument as $item) {
				if(is_object($item) === FALSE) {
					continue;
				}
				addRenderItem($item, $return);
			}
		}
	}
	return $return;
}

function convertInstancesArrayToRenderItems($input) {
	$return = [
		"genres" => [],
		"labels" => [],
		"artists" => [],
		"albums" => [],
		"itembreadcrumbs" => [],
	];
	
	foreach($input as $item) {
		if(is_object($item) === FALSE) {
			continue;
		}
		addRenderItem($item, $return);
	}
	return $return;
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

	// sanitize the file request, keep just the name and extension
	$filePath  = realpath($file);
	$pathParts = pathinfo($filePath);
	$fileName  = $pathParts["basename"];
	#$fileExt   = $pathParts["extension"];

	if (is_file($filePath) === FALSE) {
		deliveryError(404);
	}

	// IMPORTANT TODO: proper check if file access is allowed
	if(stripos($filePath, $app->config["mpd"]["musicdir"]) !== 0 && stripos($filePath, $app->config["mpd"]["alternative_musicdir"]) !== 0) {
		deliveryError(401);
	}

	$file = @fopen($filePath,"rb");
	if (!$file) {
		deliveryError(500);
	}

	$fileSize  = filesize($filePath);

	//check if http_range is sent by browser (or download manager)
	$range = "";

	if(isset($app->environment["HTTP_RANGE"])) {
		@list($size_unit, $range_orig) = @explode("=", $app->environment["HTTP_RANGE"], 2);
		if ($size_unit == "bytes") {
			//multiple ranges could be specified at the same time, but for simplicity only serve the first range
			//http://tools.ietf.org/id/draft-ietf-http-range-retrieval-00.txt
			@list($range, $extra_ranges) = @explode(",", $range_orig, 2);
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


function deliveryError( $code = 401, $msg = null ) {
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
		$msg = $msgs[$code];
	}

	$app = \Slim\Slim::getInstance();
	$newResponse = $app->response();
	$newResponse->body(
		sprintf("<html><head><title>%s %s</title></head><body><h1>%s</h1></body></html>", $code, $msg, $msg)
	);
	$newResponse->status($code);
	header(sprintf("HTTP/1.0 %s %s",$code,$msg));
	$app->stop();
}

function getMimeType ($filename) {
	$mimeExtensionMapping = parse_ini_file(APP_ROOT . "config/mimetypes.ini", TRUE);

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

function nfostring2html($inputstring) {
	$convTable = array(
		/* 0*/ 0x0000, 0x263a, 0x263b, 0x2665, 0x2666,
		/* 5*/ 0x2663, 0x2660, 0x2022, 0x25d8, 0x0000,
		/* 10*/ 0x0000, 0x2642, 0x2640, 0x0000, 0x266b,
		/* 15*/ 0x263c, 0x25ba, 0x25c4, 0x2195, 0x203c,
		/* 20*/ 0x00b6, 0x00a7, 0x25ac, 0x21a8, 0x2191,
		/* 25*/ 0x2193, 0x2192, 0x2190, 0x221f, 0x2194,
		/* 30*/ 0x25b2, 0x25bc, 0x0000, 0x0000, 0x0022,
		/* 35*/ 0x0000, 0x0000, 0x0000, 0x0000, 0x0027,
		/* 40*/ 0x0000, 0x0000, 0x0000, 0x0000, 0x0000,
		/* 45*/ 0x0000, 0x0000, 0x0000, 0x0000, 0x0000,
		/* 50*/ 0x0000, 0x0000, 0x0000, 0x0000, 0x0000,
		/* 55*/ 0x0000, 0x0000, 0x0000, 0x0000, 0x0000,
		/* 60*/ 0x003c, 0x0000, 0x0000, 0x0000, 0x0000,
		/* 65*/ 0x0000, 0x0000, 0x0000, 0x0000, 0x0000,
		/* 70*/ 0x0000, 0x0000, 0x0000, 0x0000, 0x0000,
		/* 75*/ 0x0000, 0x0000, 0x0000, 0x0000, 0x0000,
		/* 80*/ 0x0000, 0x0000, 0x0000, 0x0000, 0x0000,
		/* 85*/ 0x0000, 0x0000, 0x0000, 0x0000, 0x0000,
		/* 90*/ 0x0000, 0x0000, 0x0000, 0x0000, 0x0000,
		/* 95*/ 0x0000, 0x0000, 0x0000, 0x0000, 0x0000,
		/*100*/ 0x0000, 0x0000, 0x0000, 0x0000, 0x0000,
		/*105*/ 0x0000, 0x0000, 0x0000, 0x0000, 0x0000,
		/*110*/ 0x0000, 0x0000, 0x0000, 0x0000, 0x0000,
		/*115*/ 0x0000, 0x0000, 0x0000, 0x0000, 0x0000,
		/*120*/ 0x0000, 0x0000, 0x0000, 0x0000, 0x0000,
		/*125*/ 0x0000, 0x0000, 0x2302, 0x00c7, 0x00fc,
		/*130*/ 0x00e9, 0x00e2, 0x00e4, 0x00e0, 0x00e5,
		/*135*/ 0x00e7, 0x00ea, 0x00eb, 0x00e8, 0x00ef,
		/*140*/ 0x00ee, 0x00ec, 0x00c4, 0x00c5, 0x00c9,
		/*145*/ 0x00e6, 0x00c6, 0x00f4, 0x00f6, 0x00f2,
		/*150*/ 0x00fb, 0x00f9, 0x00ff, 0x00d6, 0x00dc,
		/*155*/ 0x00a2, 0x00a3, 0x00a5, 0x20a7, 0x0192,
		/*160*/ 0x00e1, 0x00ed, 0x00f3, 0x00fa, 0x00f1,
		/*165*/ 0x00d1, 0x00aa, 0x00ba, 0x00bf, 0x2310,
		/*170*/ 0x00ac, 0x00bd, 0x00bc, 0x00a1, 0x00ab,
		/*175*/ 0x00bb, 0x2591, 0x2592, 0x2593, 0x2502,
		/*180*/ 0x2524, 0x2561, 0x2562, 0x2556, 0x2555,
		/*185*/ 0x2563, 0x2551, 0x2557, 0x255d, 0x255c,
		/*190*/ 0x255b, 0x2510, 0x2514, 0x2534, 0x252c,
		/*195*/ 0x251c, 0x2500, 0x253c, 0x255e, 0x255f,
		/*200*/ 0x255a, 0x2554, 0x2569, 0x2566, 0x2560,
		/*205*/ 0x2550, 0x256c, 0x2567, 0x2568, 0x2564,
		/*210*/ 0x2565, 0x2559, 0x2558, 0x2552, 0x2553,
		/*215*/ 0x256b, 0x256a, 0x2518, 0x250c, 0x2588,
		/*220*/ 0x2584, 0x258c, 0x2590, 0x2580, 0x03b1,
		/*225*/ 0x03b2, 0x0393, 0x03c0, 0x03a3, 0x03c3,
		/*230*/ 0x03bc, 0x03c4, 0x03a6, 0x03b8, 0x2126,
		/*235*/ 0x03b4, 0x221e, 0x00f8, 0x03b5, 0x2229,
		/*240*/ 0x2261, 0x00b1, 0x2265, 0x2264, 0x2320,
		/*245*/ 0x2321, 0x00f7, 0x2248, 0x00b0, 0x00b7,
		/*250*/ 0x02d9, 0x221a, 0x207f, 0x00b2, 0x25a0,
		/*255*/ 0x00a0
	);

	$str = str_replace("&", "&", $inputstring);
	for ($idx = 0; $idx < 256; $idx++) {
		if ($convTable[$idx] != 0) {
			$str = str_replace(chr($idx), "&#".$convTable[$idx].";", $str);
		}
	}
	$str = str_replace(" ", "&nbsp;", $str);
	$str = nl2br($str);
	return $str;
	
}

function rrmdir($dir) {
	if (is_dir($dir)) {
		$objects = scandir($dir);
		foreach ($objects as $object) {
			if ($object != "." && $object != "..") {
				if (is_dir($dir."/".$object)) {
					rrmdir($dir."/".$object);
				} else {
					unlink($dir."/".$object);
				}
			}
		}
		rmdir($dir);
	}
}


function renderCliHelp() {
	$app = \Slim\Slim::getInstance();
	cliLog($app->ll->str("cli.usage"), 1, "yellow");
	cliLog("  ./slimpd [ARGUMENT]");
	cliLog("ARGUMENTS", 1, "yellow");
	cliLog("  hard-reset", 1, "cyan");
	cliLog("    " . $app->ll->str("cli.args.hard-reset.line1"));
	cliLog("    " . $app->ll->str("cli.args.hard-reset.line2"));
	cliLog("    " . $app->ll->str("cli.args.hard-reset.warning"), 1, "yellow");
	cliLog("  update", 1, "cyan");
	cliLog("    " . $app->ll->str("cli.args.update"));
	cliLog("  remigrate", 1, "cyan");
	cliLog("    " . $app->ll->str("cli.args.remigrate.line1"));
	cliLog("    " . $app->ll->str("cli.args.remigrate.line2"));
	cliLog("");
	cliLog("  ..................................");
	cliLog("  https://github.com/othmar52/slimpd");
	cliLog("");
}


function clearPhpThumbTempFiles($phpThumb) {
	foreach($phpThumb->tempFilesToDelete as $delete) {
		if(strpos($delete, realpath(APP_ROOT . "cache/")) !== 0) {
			continue;
		}
		if(is_file($delete) === TRUE) {
			cliLog("deleting tmpFile " . $delete, 10);
			unlink($delete);
		}
	}
}
