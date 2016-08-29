<?php

function getFilePathHash($inputString) {
	return str_pad(dechex(crc32($inputString)), 8, "0", STR_PAD_LEFT) . str_pad(strlen($inputString), 3, "0", STR_PAD_LEFT);
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

/**
 * replaces multiple whitespaces with a single whitespace
 */
function flattenWhitespace($input) {
	return preg_replace("!\s+!", " ", $input);
}

/**
 * replaces curly+square braces with normal braces
 */
function unifyBraces($input) {
	return str_replace(
		["[", "]","{", "}"],
		["(", ")","(", ")"],
		$input
	);
}

/**
 * php's escapeshellarg() invalidates pathes with some specialchars
 *	  escapeshellarg("/testdir/pathtest-u§s²e³l¼e¬sµsöäüß⁄x/testfile.mp3")
 *		  results in "/testdir/pathtest-uselessx/testfile.mp3"
 * TODO: check if this is a security issue
 * @see: issue #4
 */
function escapeshellargDirty($input) {
	return "'" . str_replace("'", "'\"'\"'", $input) . "'";
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
 * @param   string	  Delimiter string to explode with
 * @param   string	  The string to explode
 * @param   boolean	 If set, all empty values will be removed in output
 * @param   integer	 If positive, the result will contain a maximum of
 *					   $limit elements, if negative, all components except
 *					   the last -$limit are returned, if zero (default),
 *					   the result is not limited at all. Attention though
 *					   that the use of this parameter can slow down this
 *					   function.
 * @return  array	   Exploded values
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

function path2url($mixed) {
	if(is_array($mixed) === TRUE) {
		$mixed = join("", $mixed);
	}
	// rawurlencode but preserve slashes
	return str_replace("%2F", "/", rawurlencode($mixed));
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

/**
 * depending on locale setting we may be a comma separator - lets force the dot
 */
function getMicrotimeFloat() {
	return str_replace(",", ".", microtime(TRUE));
}

/**
 * Thanks to "Glavić"
 * http://stackoverflow.com/questions/1416697/converting-timestamp-to-time-ago-in-php-e-g-1-day-ago-2-days-ago
 * TODO: translate
 */
function timeElapsedString($datetime, $full = false) {
	$now = new DateTime;
	$ago = new DateTime("@".floor($datetime));
	$diff = $now->diff($ago);

	$diff->w = floor($diff->d / 7);
	$diff->d -= $diff->w * 7;

	$string = array(
		'y' => 'year',
		'm' => 'month',
		'w' => 'week',
		'd' => 'day',
		'h' => 'hour',
		'i' => 'minute',
		's' => 'second',
	);
	foreach($string as $key => &$value) {
		if ($diff->$key) {
			$value = $diff->$key . ' ' . $value . ($diff->$key > 1 ? 's' : '');
			continue;
		}
		unset($string[$key]);
	}

	if (!$full) {
		$string = array_slice($string, 0, 1);
	}
	return $string
		? implode(', ', $string) . ' ago'
		: 'just now';
}

/**
 * Thanks to https://gist.github.com/Pushplaybang/5432844
 */
function rgb2hex($rgb) {
	return join(
		"",
		[
			"#",
			str_pad(dechex($rgb[0]), 2, "0", STR_PAD_LEFT),
			str_pad(dechex($rgb[1]), 2, "0", STR_PAD_LEFT),
			str_pad(dechex($rgb[2]), 2, "0", STR_PAD_LEFT)
		]
	);
}

function isHash($input) {
	if(preg_match("/^hash0x([a-f0-9]{7})$/", az09($input))) {
		return TRUE;
	}
	return FALSE;
}
