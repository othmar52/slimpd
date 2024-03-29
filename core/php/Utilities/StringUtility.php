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
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License
 * for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

function appendTrailingSlash($pathString) {
    return removeTrailingSlash($pathString) . DS;
}

function removeTrailingSlash($pathString) {
    return rtrim($pathString, DS);
}

function getFilePathHash($inputString) {
    return str_pad(dechex(crc32($inputString)), 8, "0", STR_PAD_LEFT) . str_pad(strlen($inputString), 3, "0", STR_PAD_LEFT);
}

function removeAppRootPrefix($inputString) {
    return str_replace(APP_ROOT, "", $inputString);
}

function isValidFingerprint($inputString) {
    return preg_match("/^([a-f0-9]){32}$/", $inputString);
}

function remU($input){
    return trim(preg_replace("!\s+!", " ", str_replace("_", " ", $input)));
}

/** 
 * TODO: fix case sensitivity of strings like
 * "Genaside Ii" should be: "...II"
 * "Never Trust The Drugweib (original Mix)" should be "...(Original..."
 * "Showdown (Undercover Agent Vip Mix)" should be "... VIP Mix)"
 */
function fixCaseSensitivity($input){
    $tmpGlue = "p³§%8=7 ";
    // add temporary whitespace after a few chars
    foreach(["(", ".", "-"] as $char) {
        $input = str_replace($char, $char . $tmpGlue , $input);
    }
    $input = ucwords(strtolower($input));
    // remove temporary whitespace again
    foreach(["(", ".", "-"] as $char) {
        $input = str_replace($char . $tmpGlue , $char, $input);
    }
    // lets assume standalone S belongs to previous phrase like "There S Nothing Better", "It S Party Time", "Heart Of Gold (Jaybee S Club Mix)"
    $input = str_replace(" S ", "'s ", $input);
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
        ["[", "]", "{", "}", "<", ">"],
        ["(", ")", "(", ")", "(", ")"],
        $input
    );
}


/**
 * replaces curly+square braces with normal braces
 */
function unifyQuotes($input) {
    return str_replace(
        ["`", "´", "‘", "’", "“", "”"],
        ["'", "'", "'", "'", '"', '"'],
        $input
    );
}

function unifyAll($input) {
    return trim(
        flattenWhitespace(
            unifyBraces(
                unifyQuotes(
                    unifyHyphens(
                        remU(
                            $input
                        )
                    )
                )
            )
        )
    );
}

/**
 * replaces hyphen variations with standard hyphen
 * Thanks to https://www.cs.tut.fi/~jkorpela/dashes.html
 */
function unifyHyphens($input) {
    return str_ireplace(
        [
            "~", //    U+007E    &#126;    tilde    the Ascii tilde, with multiple usage; “swung dash”
            "֊", //    U+058A    &#1418;    armenian hyphen    as soft hyphen, but different in shape
            "־", //    U+05BE    &#1470;    hebrew punctuation maqaf    word hyphen in Hebrew
            "‐", //    U+2010    &#8208;    hyphen    unambiguously a hyphen character, as in “left-to-right”; narrow width
            "‑", //    U+2011    &#8209;    non-breaking hyphen    as hyphen (U+2010), but not an allowed line break point
            "‒", //    U+2012    &#8210;    figure dash    as hyphen-minus, but has the same width as digits
            "–", //    U+2013    &#8211;    en dash    used e.g. to indicate a range of values
            "—", //    U+2014    &#8212;    em dash    used e.g. to make a break in the flow of a sentence
            "―", //    U+2015    &#8213;    horizontal bar    used to introduce quoted text in some typographic styles; “quotation dash”; often (e.g., in the representative glyph in the Unicode standard) longer than em dash
            "⁓", //    U+2053    &#8275;    swung dash    like a large tilde
            "⁻", //    U+207B    &#8315;    superscript minus    a compatibility character which is equivalent to minus sign U+2212 in superscript style
            "₋", //    U+208B    &#8331;    subscript minus    a compatibility character which is equivalent to minus sign U+2212 in subscript style
            "−", //    U+2212    &#8722;    minus sign    an arithmetic operator; the glyph may look the same as the glyph for a hyphen-minus, or may be longer ;
            "⸗", //    U+2E17    &#11799;    double oblique hyphen    used in ancient Near-Eastern linguistics; not in Fraktur, but the glyph of Ascii hyphen or hyphen is similar to this character in Fraktur fonts
            "⸺", //    U+2E3A    &#11834;    two-em dash    omission dash<(a>, 2 em units wide
            "⸻", //    U+2E3B    &#11835;    three-em dash    used in bibliographies, 3 em units wide
            "〜", //    U+301C    &#12316;    wave dash    a Chinese/Japanese/Korean character
            "〰", //    U+3030    &#12336;    wavy dash    a Chinese/Japanese/Korean character
            "゠", //    U+30A0    &#12448;    katakana-hiragana double hyphen    in Japasene kana writing
            "︱", //    U+FE31    &#65073;    presentation form for vertical em dash    vertical variant of em dash
            "︲", //    U+FE32    &#65074    presentation form for vertical en dash    vertical variant of en dash
            "﹘", //    U+FE58    &#65112;    small em dash    small variant of em dash
            "﹣", //    U+FE63    &#65123;    small hyphen-minus    small variant of Ascii hyphen
            "－", //    U+FF0D    &#65293;    fullwidth hyphen-minus    variant of Ascii hyphen for use with CJK characters
            "_eao_", //an incorrectly encoded hyphen
            " eao ", //an incorrectly encoded hyphen
        ],
        "-",
        $input
    );
}

/**
 * removes leading zeroes from input like "01", "001"
 */
function removeLeadingZeroes($input) {
    return ltrim($input, "0");
}

/**
 * replaces exotic characters with similar [A-Za-z0-9] and removes all
 * other characters (also whitespaces and punctuations) of a string
 * 
 * @param $string string    input string
 * @return string the converted string
 */
function az09($string, $preserve = "", $strToLower = TRUE) {
    $charGroup = array();
    $charGroup[] = array("a","à","á","â","ã","ä","å","ª","а");
    $charGroup[] = array("A","À","Á","Â","Ã","Ä","Å","А");
    $charGroup[] = array("b","Б","б");
    $charGroup[] = array("c","ç","¢","©");
    $charGroup[] = array("C","Ç");
    $charGroup[] = array("d","д");
    $charGroup[] = array("D","Ð","Д");
    $charGroup[] = array("e","é","ë","ê","è","е","э");
    $charGroup[] = array("E","È","É","Ê","Ë","€","Е","Э");
    $charGroup[] = array("f","ф");
    $charGroup[] = array("F","Ф");
    $charGroup[] = array("g","г");
    $charGroup[] = array("G","Г");
    $charGroup[] = array("h","х");
    $charGroup[] = array("H","Х");
    $charGroup[] = array("i","ì","í","î","ï","и","ы");
    $charGroup[] = array("I","Ì","Í","Î","Ï","¡","И","Ы");
    $charGroup[] = array("k","к");
    $charGroup[] = array("K","К");
    $charGroup[] = array("l","л");
    $charGroup[] = array("L","Л");
    $charGroup[] = array("m","м");
    $charGroup[] = array("M","М");
    $charGroup[] = array("n","ñ","н");
    $charGroup[] = array("N","Н");
    $charGroup[] = array("o","ò","ó","ô","õ","ö","ø","о");
    $charGroup[] = array("O","Ò","Ó","Ô","Õ","Ö","О");
    $charGroup[] = array("p","п");
    $charGroup[] = array("P","П");
    $charGroup[] = array("r","®","р");
    $charGroup[] = array("R","Р");
    $charGroup[] = array("s","ß","š","с");
    $charGroup[] = array("S","$","§","Š","С");
    $charGroup[] = array("t","т");
    $charGroup[] = array("T","т");
    $charGroup[] = array("u","ù","ú","û","ü","у");
    $charGroup[] = array("U","Ù","Ú","Û","Ü","У");
    $charGroup[] = array("v","в");
    $charGroup[] = array("V","В");
    $charGroup[] = array("W","Ь");
    $charGroup[] = array("w","ь");
    $charGroup[] = array("x","×");
    $charGroup[] = array("y","ÿ","ý","й","ъ");
    $charGroup[] = array("Y","Ý","Ÿ","Й","Ъ");
    $charGroup[] = array("z","з");
    $charGroup[] = array("Z","З");
    $charGroup[] = array("ae","æ");
    $charGroup[] = array("AE","Æ");
    $charGroup[] = array("tm","™");
    #$charGroup[] = array("(","{", "[", "<");
    #$charGroup[] = array(")","}", "]", ">");
    $charGroup[] = array("0","Ø");
    $charGroup[] = array("2","²");
    $charGroup[] = array("3","³");
    $charGroup[] = array("and","&");
    $charGroup[] = array("zh","Ж","ж");
    $charGroup[] = array("ts","Ц","ц");
    $charGroup[] = array("ch","Ч","ч");
    $charGroup[] = array("sh","Ш","ш","Щ","щ");
    $charGroup[] = array("yu","Ю","ю");
    $charGroup[] = array("ya","Я","я");
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
    // replace double linebreaks with single linebreak
    $str = str_replace("<br />\r<br />", "<br />", $str);
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
