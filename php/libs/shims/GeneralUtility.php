<?php

function getFilePathHash($inputString) {
	return str_pad(dechex(crc32($inputString)), 8, '0', STR_PAD_LEFT) . str_pad(strlen($inputString), 3, '0', STR_PAD_LEFT);
}

function sortHelper($a,$b){
    return strlen($b)-strlen($a);
}

function remU($input){
    return trim(preg_replace('!\s+!', ' ', str_replace("_", " ", $input)));
}


function cliLog($msg, $verbosity=1, $color="default") {
	// TODO: configure verbosity in config file
	$activeVerbosity = 1;
	if($verbosity > $activeVerbosity) {
		return;
	}
	
	// TODO: check colors (especially the color after linebreaks)
	$black 		= "33[0;30m";
	$darkgray 	= "33[1;30m";
	$blue 		= "33[0;34m";
	$lightblue 	= "33[1;34m";
	$green 		= "33[0;32m";
	$lightgreen = "33[1;32m";
	$cyan 		= "33[0;36m";
	$lightcyan 	= "33[1;36m";
	$red 		= "33[0;31m";
	$lightred 	= "33[1;31m";
	$purple 	= "33[0;35m";
	$lightpurple= "33[1;35m";
	$brown 		= "33[0;33m";
	$yellow 	= "33[1;33m";
	$lightgray 	= "33[0;37m";
	$white 		= "33[1;37m";
		
		
		
	$shellColorize = FALSE;
	$prefix = "";
	$suffix = "";
	if($shellColorize == TRUE) {
		switch($color) {
			case "green":  $prefix = "\033[32m"; $suffix = "\033[37m"; break;
			case "yellow": $prefix = "\033[33m"; $suffix = "\033[37m"; break;
			case "red":    $prefix = "\033[1;31m"; $suffix = "\033[37m"; break;
			case "cyan":   $prefix = "\033[36m"; $suffix = "\033[37m"; break;
			case "purple": $prefix = "\033[35m"; $suffix = "\033[37m"; break;
			default:       $prefix = "";         $suffix = "";         break;
		}
  	}
	
	echo $prefix . $msg . $suffix . "\n";
	ob_flush();
}

function debugLog($mixed){
	$debug = 0;
	
	if(!$debug) {
		return;
	}
	if(is_string($mixed) === TRUE) {
		echo $mixed . "\n";
	}
	if(is_array($mixed) === TRUE) {
		echo join("\n", $mixed) . "\n";
	}
}

if (!function_exists('array_replace_recursive')) {
    function array_replace_recursive($base, $replacements) {
        foreach (array_slice(func_get_args(), 1) as $replacements) {
            $bref_stack = array(&$base);
            $head_stack = array($replacements);

            do {
                end($bref_stack);

                $bref = &$bref_stack[key($bref_stack)];
                $head = array_pop($head_stack);

                unset($bref_stack[key($bref_stack)]);

                foreach (array_keys($head) as $key) {
                    if (isset($key, $bref) && is_array($bref[$key]) && is_array($head[$key])) {
                        $bref_stack[] = &$bref[$key];
                        $head_stack[] = $head[$key];
                    } else {
                        $bref[$key] = $head[$key];
                    }
                }
            } while(count($head_stack));
        }

        return $base;
    }

}


/**
 * converts exotic characters in similar [A-Za-z0-9]
 * and removes all other characters (also whitspaces and punctiations)
 * 
 * @param $string string	input string
 * @return string the converted string
 * 
 *  
 */
function az09($string, $preserve = '', $strToLower = TRUE) {
    $c = array();
    $c[] = array('a','à','á','â','ã','ä','å','ª');
    $c[] = array('A','À','Á','Â','Ã','Ä','Å');
    $c[] = array('e','é','ë','ê','è');
    $c[] = array('E','È','É','Ê','Ë','€');
    $c[] = array('i','ì','í','î','ï');
    $c[] = array('I','Ì','Í','Î','Ï','¡');
    $c[] = array('o','ò','ó','ô','õ','ö','ø');
    $c[] = array('O','Ò','Ó','Ô','Õ','Ö');
    $c[] = array('u','ù','ú','û','ü');
    $c[] = array('U','Ù','Ú','Û','Ü');
    $c[] = array('n','ñ');
    $c[] = array('y','ÿ','ý');
    $c[] = array('Y','Ý','Ÿ');
    $c[] = array('x','×');
    $c[] = array('ae','æ');
    $c[] = array('AE','Æ');
    $c[] = array('c','ç','¢','©');
    $c[] = array('C','Ç');
    $c[] = array('D','Ð');
    $c[] = array('s','ß','š');
    $c[] = array('S','$','§','Š');
    $c[] = array('tm','™');
    $c[] = array('r','®');
    #$c[] = array('(','{', '[', '<');
    #$c[] = array(')','}', ']', '>');
    $c[] = array('0','Ø');
    $c[] = array('2','²');
    $c[] = array('3','³');
    $c[] = array('and','&');
    for($ca=0; $ca<count($c); $ca++){
            for($e=1; $e<count($c[$ca]); $e++){
            	if(strpos($preserve, $c[$ca][$e]) !== FALSE) {
            		continue;
            	}
                $string = str_replace(($c[$ca][$e]),$c[$ca][0], $string);
            }
    }
    unset($c);
	$string = preg_replace("/[^a-zA-Z0-9". $preserve ."]/", "", $string);


	
    $string = ($strToLower === TRUE) ? strtolower($string) : $string;

    //echo  $string . "\n"; die();
    return($string);
}


/**
 * Explodes a string and trims all values for whitespace in the ends.
 * If $onlyNonEmptyValues is set, then all blank ('') values are removed.
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
    $result = array_map('trim', $explodedValues);

    if ($removeEmptyValues) {
        $temp = array();
        foreach ($result as $value) {
            if ($value !== '') {
                $temp[] = $value;
            }
        }
        $result = $temp;
      }

      if ($limit != 0) {
          if ($limit < 0) {
              $result = array_slice($result, 0, $limit);
          } elseif (count($result) > $limit) {
              $lastElements = array_slice($result, $limit - 1);
              $result = array_slice($result, 0, $limit - 1);
              $result[] = implode($delim, $lastElements);
          }
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
function getDirectoryFiles($dir, $ext='images', $addFilePath = TRUE, $checkMimeTypeIfPossible = TRUE) {
	$foundFiles = array();
	if( is_dir($dir) == FALSE) {
	  return $foundFiles;
	}
	
	$app = \Slim\Slim::getInstance();
	$validExtensions = array(strtolower($ext));
	if(array_key_exists($ext, $app->config['mimetypes'])) {
		if(is_array($app->config['mimetypes'][$ext]) === TRUE) {
			$validExtensions = array_keys($app->config['mimetypes'][$ext]);
		}
		if(is_string($app->config['mimetypes'][$ext]) === TRUE) {
			$checkMimeTypeIfPossible = FALSE;
		}
	}
	// make sure we have a trailing slash
	$dir .= (substr($dir, -1) !== DS) ? DS : ''; 
	
	$finfo = finfo_open(FILEINFO_MIME_TYPE);
	$handle=opendir ($dir);
	while ($file = readdir ($handle)) {
		$foundExt = strtolower(preg_replace('/^.*\./', '', $file));
		if(is_dir($dir . $file) === TRUE) {
			continue;
		}
		if(in_array($foundExt, $validExtensions) === FALSE) {
			continue;
		}
		if($checkMimeTypeIfPossible == TRUE && array_key_exists($ext, $app->config['mimetypes'])) {
			if(finfo_file($finfo, $dir.$file) !== $app->config['mimetypes'][$ext][$foundExt]) {
				continue;
			}
		}
		$foundFiles[] = (($addFilePath == TRUE)? $dir : '') . $file;
		
	}

	finfo_close($finfo);
	closedir($handle);
	return $foundFiles;
}


function formatByteSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    }
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }
    if ($bytes > 1) {
        return $bytes . ' bytes';
    }
    if ($bytes == 1) {
        return $bytes . ' byte';
    }
    return '0 bytes';
}



function uniqueArrayOrderedByRelevance(array $input) {
	$acv=array_count_values($input);
	arsort($acv); 
	$result=array_keys($acv);
	return $result;	
}
