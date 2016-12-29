<?php
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
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License
 * for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

function useArguments(){
    // do nothing - codacity's review does not like unused arguments which makes totaly sense.
    // but slim-framwork passes (Request, Response, Args) to each controller function 
    // calling this function makes it possible for codacity to find really unused arguments
    return;
}

function sortHelper($string1, $string2){
    return strlen($string2) - strlen($string1);
}

function isFutureTimestamp($inputTstamp) {
    return ($inputTstamp > time()) ? TRUE : FALSE;
}

function recursiveDropLargeData(&$inputArray, $strlenTreshold = 1000) {
    if(is_array($inputArray) === FALSE) {
        return;
    }
    foreach($inputArray as $key => &$value) {
        if(is_string($value) === TRUE && strlen($value) > $strlenTreshold) {
            cliLog("removing large data: " . $key, 10 , "red");
            unset($inputArray[$key]);
        }
        if(is_array($value) === TRUE) {
            recursiveDropLargeData($value, $strlenTreshold);
        }
    }
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


/**
 * @return string : empty string or get-parameter-string which is needed for Slim redirects
 */
function getNoSurSuffix($append, $prefixQuestionmark = TRUE) {
    return ($append === TRUE)
        ? (($prefixQuestionmark)? "?":"") . "nosurrounding=1"
        : "";
}

function notifyJson($message, $type = "info") {
    $out = new stdClass();
    $out->notify = 1;
    $out->message = $message;
    $out->type = $type;
    return $out;
}

function deliverJson($data, $response) {
    $newResponse = $response->withHeader('Content-Type', 'application/json');
    $newResponse->getBody()->write(json_encode($data));
    return $newResponse;
}

/**
 * reads values in multidimensional array
 * example ["keylevel1", "level2"] returns $inputArray["keylevel1"]["level2"]
 * @param array $pathChunks : target path for multidimensional array
 * @param array $inputArray : multidimensional array
 * @return mixed : found value or FALSE in case array path does not exist
 */
function recursiveArrayParser($pathChunks, $inputArray) {
    return recursiveArrayHandler($pathChunks, $inputArray, 'read');
}

/**
 * deletes values in multidimensional array
 * example ["keylevel1", "level2"] deletes $inputArray["keylevel1"]["level2"] in case it exists
 * @param array $pathChunks : target path for multidimensional array
 * @param array $inputArray : multidimensional array
 * @return mixed : found value or FALSE in case array path does not exist
 */
function recursiveArrayCleaner($pathChunks, &$inputArray) {
    return recursiveArrayHandler($pathChunks, $inputArray, 'unset');
}

/**
 * walks inside multidimensional array
 * example ["keylevel1", "level2"] walks to $inputArray["keylevel1"]["level2"] in case it exists
 * @param array $pathChunks : target path for multidimensional array
 * @param array $inputArray : multidimensional array
 * @param string $action : 'read|unset'
 * @return mixed : found value or FALSE in case array path does not exist
 */
function recursiveArrayHandler($pathChunks, &$inputArray, $action) {
    $currentChunk = array_shift($pathChunks);
    if(isset($inputArray[$currentChunk]) === FALSE) {
        return FALSE;
    }
    if(count($pathChunks) === 0) {
        // reached requested level
        if($action === 'read') {
            return $inputArray[$currentChunk];
        }
        unset($inputArray[$currentChunk]);
        return TRUE;
    }
    if(is_array($inputArray[$currentChunk]) === FALSE) {
        return FALSE;
    }
    // recursion 
    return recursiveArrayHandler($pathChunks, $inputArray[$currentChunk], $action);
}

function cliLog($msg, $verbosity = 1, $color = "default", $fatal = FALSE) {
    if(PHP_SAPI !== "cli") {
        return;
    }
    if($verbosity > @$_SESSION['cliVerbosity'] && $fatal === FALSE) {
        return;
    }

    // TODO: read from config
    $shellColorize = TRUE;
    // TODO: check colors (especially the color and boldness after linebreaks)
    #$black         = "33[0;30m";
    #$darkgray     = "33[1;30m";
    #$blue         = "33[0;34m";
    #$lightblue     = "33[1;34m";
    #$green         = "33[0;32m";
    #$lightgreen = "33[1;32m";
    #$cyan         = "33[0;36m";
    #$lightcyan     = "33[1;36m";
    #$red         = "33[0;31m";
    #$lightred     = "33[1;31m";
    #$lightpurple= "33[1;35m";
    #$brown         = "33[0;33m";
    #$yellow     = "33[1;33m";
    #$lightgray     = "33[0;37m";
    #$white         = "33[1;37m";

    $colors = [
        "green"        => ["\033[32m",  "\033[37m"],
        "yellow"       => ["\033[1;33m","\033[0m" ],
        "red"          => ["\033[1;31m","\033[0m" ],
        "cyan"         => ["\033[36m",  "\033[37m"],
        "purple"       => ["\033[35m",  "\033[37m"],
        "blue"         => ["\033[34m",  "\033[37m"],
        "darkgray"     => ["\033[1;30m","\033[0m"],
        "lightblue"    => ["\033[1;34m","\033[0m" ]
    ];

    if($shellColorize !== TRUE || isset($colors[$color]) === FALSE) {
        $colors[$color] = ["", ""];
    }
    echo $colors[$color][0] . $msg . $colors[$color][1] . "\n";
    ob_flush();
}

function cli2html($input) {
    $replace = [
        " " => "&nbsp;",
        "\033[35m" => '<span style="color:#db00e5">',    // purple
        "\033[36m" => '<span style="color:#00e5e5">',    // cyan
        "\033[1;33m" => '<span style="color:#effe4b">',    // yellow
        "\033[1;30m" => '<span style="color:#777">',    // darkgray
        "\033[1;31m" => '<span style="color:#fd3c3c">',    // red
        "\033[37m" => '</span>',
        "\033[0m" => '</span>'
    ];
    return str_replace(array_keys($replace), $replace, $input);
}

function fileLog($mixed) {
    $filename = APP_ROOT . "localdata/cache/log-" . date("Y-M-d") . ".log";
    if(is_string($mixed) === TRUE) {
        $data = $mixed . "\n";
    }
    if(is_array($mixed) === TRUE) {
        $data = print_r($mixed,1) . "\n";
    }
    file_put_contents($filename, $data, FILE_APPEND);
}

function getDatabaseDiffConf($conf) {
    return array(
        "host"         => $conf["database"]["dbhost"],
        "user"         => $conf["database"]["dbusername"],
        "password"     => $conf["database"]["dbpassword"],
        "db"           => $conf["database"]["dbdatabase"],
        "savedir"      => APP_ROOT . "core/config/dbscheme",
        "verbose"      => "On",
        "versiontable" => "db_revisions",
        "aliastable"   => "db_alias",
        "aliasprefix"  => "slimpd_v",
        "forceyes"     => TRUE
    );
}

function uniqueArrayOrderedByRelevance(array $input) {
    if(is_array($input) === FALSE) {
        return [];
    }
    $acv = array_count_values($input);
    arsort($acv); 
    $return = array_keys($acv);
    if(count($return) === count($input)) {
        // preserve ordering in case array does not have duplicate entries
        return $input;
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
        $item->fetchRenderItems($return);
    }
    return $return;
}

function renderCliHelp($ll) {
    cliLog($ll->str("cli.copyright.line1"));
    cliLog("");
    cliLog(" " . $ll->str("cli.copyright.line2"));
    cliLog(" " . $ll->str("cli.copyright.line3"));
    cliLog(" " . $ll->str("cli.copyright.line4"));
    cliLog(" " . $ll->str("cli.copyright.line5"));
    cliLog("");
    cliLog($ll->str("cli.usage"), 1, "yellow");
    cliLog("  ./slimpd [ARGUMENT]");
    cliLog("ARGUMENTS", 1, "yellow");
    cliLog("  check-que", 1, "cyan");
    cliLog("    " . $ll->str("cli.args.check-que"));
    cliLog("  update", 1, "cyan");
    cliLog("    " . $ll->str("cli.args.update"));
    cliLog("  remigrate", 1, "cyan");
    cliLog("    " . $ll->str("cli.args.remigrate.line1"));
    cliLog("    " . $ll->str("cli.args.remigrate.line2"));
    cliLog("  hard-reset", 1, "cyan");
    cliLog("    " . $ll->str("cli.args.hard-reset.line1"));
    cliLog("    " . $ll->str("cli.args.hard-reset.line2"));
    cliLog("    " . $ll->str("cli.args.hard-reset.warning"), 1, "yellow");
    cliLog("");
    cliLog("  ..................................");
    cliLog("  https://github.com/othmar52/slimpd");
    cliLog("");
}
