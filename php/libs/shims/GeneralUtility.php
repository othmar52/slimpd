<?php



function sortHelper($string1,$string2){
	return strlen($string2) - strlen($string1);
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


function parseMetaForTotal($metaArray) {
	$return = "0";
	foreach($metaArray as $metaData) {
		if($metaData["Variable_name"] !== "total_found") {
			continue;
		}
		return $metaData["Value"];
	}
	return $return;
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
 * @return string : empty string or get-parameter-string which is needed for Slim redirects 
 */
function getNoSurSuffix($prefixQuestionmark = TRUE) {
	return (\Slim\Slim::getInstance()->request->get("nosurrounding") == 1)
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

function getDatabaseDiffConf($app) {
	return array(
		"host"         => $app->config["database"]["dbhost"],
		"user"         => $app->config["database"]["dbusername"],
		"password"     => $app->config["database"]["dbpassword"],
		"db"           => $app->config["database"]["dbdatabase"],
		"savedir"      => APP_ROOT . "config/dbscheme",
		"verbose"      => "On",
		"versiontable" => "db_revisions",
		"aliastable"   => "db_alias",
		"aliasprefix"  => "slimpd_v"
	);
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
	$docsCount = 0;
	$idx = 0;
	foreach ($words as $key => $word) {
		if ($word["docs"] != 0) {
			$docsCount +=$word["docs"];
		}
		$idx++;
	}
	if($idx === 0) {
		return FALSE;
	}
	$docsCount = $docsCount / ($idx * $idx);
	$mismatches = [];
	foreach ($words as $key => $word) {
		if ($word["docs"] == 0 | $word["docs"] < $docsCount) {
			$mismatches[] = $word["keyword"];
		}
	}
	if(count($mismatches) < 1) {
		return FALSE;
	}
	foreach ($mismatches as $mismatch) {
		$result = MakeSuggestion($mismatch, $sphinxPDO);
		if ($result && $mismatch !== $result) {
			$suggested[$mismatch] = $result;
		}
	}
	if(count($words) ==1 && empty($suggested)) {
		return FALSE;
	}
	$phrase = explode(" ", $query);
	foreach ($phrase as $key => $word) {
		if (isset($suggested[strtolower($word)])) {
			$phrase[$key] = $suggested[strtolower($word)];
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
		if(isset($return["itembreadcrumbs"][$instance->getRelPathHash()]) === FALSE) {
			$return["itembreadcrumbs"][$instance->getRelPathHash()] = \Slimpd\filebrowser::fetchBreadcrumb($instance->getRelPath());
		}
		return;
	}
	if($class === "Slimpd\Models\Album") {
		$return["albums"][$instance->getId()] = $instance;
		if(isset($return["itembreadcrumbs"][$instance->getRelPathHash()]) === FALSE) {
			$return["itembreadcrumbs"][$instance->getRelPathHash()] = \Slimpd\filebrowser::fetchBreadcrumb($instance->getRelPath());
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
