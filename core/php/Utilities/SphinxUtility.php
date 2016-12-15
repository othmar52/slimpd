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
 * FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License
 * for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Helper functions for sphinx search engine
 */
function cleanSearchterm($searchterm) {
    # TODO: use flattenWhitespace() in albummigrator on reading tag-information
    return trim(flattenWhitespace(
        str_replace(["_", "-", "/", " ", "(", ")", "\"", "'"], " ", $searchterm)
    ));
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


function getPhaseSuggestion($term, &$sphinxPdo) {
    $meta = $sphinxPdo->query("SHOW META")->fetchAll();
    $metaMap = array();
    foreach($meta as $metaVar) {
        $metaMap[$metaVar["Variable_name"]] = $metaVar["Value"];
    }
    $words = array();
    foreach($metaMap as $key => $value) {
        if(preg_match("/keyword\[([\d]*)\]/", $key, $matches)) {
            $words[ $matches[1] ]["keyword"] = $value;
        }
        if(preg_match("/docs\[([\d]*)\]/", $key, $matches)) {
            $words[ $matches[1] ]["docs"] = $value;
        }
    }
    return MakePhaseSuggestion($words, $term, $sphinxPdo, TRUE);
}

/**
 * Builds querystring to use for sphinx-queries
 * we have to make sure that /autocomplete and /search* gives us the same results
 * @param array $terms : array with searchphrases
 * @return string : query syntax which can be used in MATCH(:match)
 */
function getSphinxMatchSyntax(array $terms, $useExactMatch = FALSE) {
    $groups = [];
    foreach($terms as $term) {

        #$groups[] = "('\"". $term ."\"')";
        ##$groups[] = "('\"". str_replace(" ", "*", $term) ."\"')";
        ##$groups[] = "('\"". str_replace(" ", ",", $term) ."\"')";
        ##$groups[] = "('\"". str_replace(" ", " | ", $term) ."\"')";

        $groups[] = "('@* ". join(" ", $terms) ."')";
        $groups[] = "(' \"". addStars($term) . "\"')";
        if($useExactMatch === FALSE) {
            $groups[] = "('". str_replace(" ", " | ", $term) ."')";
        }
    }
    $groups = array_unique(array_map('simplifySphinxQuery', $groups));
    #echo "<pre>" . print_r($groups,1);die;
    return join("|\n", $groups);
}

function simplifySphinxQuery($input) {
    // replace multiple asterisks with single asterisk
    $output = preg_replace('/\*+/', '*', strtolower($input));
    return str_replace('* *', '*', $output);
}

function removeStars($searchterm) {
    return trim(str_replace("*", " ", $searchterm));
}

/// build a list of trigrams for a given keywords
function buildTrigrams ($keyword) {
    $pattern = "__" . $keyword . "__";
    $trigrams = "";
    for ($charIndex=0; $charIndex<strlen($pattern)-2; $charIndex++) {
        $trigrams .= substr($pattern, $charIndex, 3 ) . " ";
    }
    return $trigrams;
}

function MakeSuggestion($keyword, $sphinxPDO, $force = FALSE) {
    $query = '"' . buildTrigrams($keyword) .'"/1';
    $len = strlen($keyword);

    $delta = LENGTH_THRESHOLD;
    $stmt = $sphinxPDO->prepare("
        SELECT *, weight() as w, w+:delta-ABS(len-:len) as myrank
        FROM slimpdsuggest
        WHERE MATCH(:match) AND len BETWEEN :lowlen AND :highlen
        ". (($force === TRUE) ? "AND keyword != :keyword" : "" )."
        AND keyword != :keyword
        ORDER BY myrank DESC, freq DESC
        LIMIT 0,:topcount OPTION ranker=wordcount
    ");

    $stmt->bindValue(":match", $query, PDO::PARAM_STR);
    $stmt->bindValue(":len", $len, PDO::PARAM_INT);
    $stmt->bindValue(":delta", $delta, PDO::PARAM_INT);
    $stmt->bindValue(":lowlen", $len - $delta, PDO::PARAM_INT);
    $stmt->bindValue(":highlen", $len + $delta, PDO::PARAM_INT);
    if($force === TRUE) {
        $stmt->bindValue(":keyword", $keyword, PDO::PARAM_STR);
    }
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

function MakePhaseSuggestion($words, $query, $sphinxPDO, $force = FALSE) {
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
    if($force === TRUE) {
        $mismatches = trimExplode(" ", $query);
    }
    foreach ($words as $key => $word) {
        if ($word["docs"] == 0 | $word["docs"] < $docsCount) {
            $mismatches[] = $word["keyword"];
        }
    }

    if(count($mismatches) < 1) {
        return FALSE;
    }
    foreach ($mismatches as $mismatch) {
        $result = MakeSuggestion($mismatch, $sphinxPDO, $force);
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
