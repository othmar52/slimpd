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
/**
 * pretty global database operations
 * TODO: find a better name for this class
 */
class DatabaseStuff extends \Slimpd\Modules\Importer\AbstractImporter {

    public static function getInitialDatabaseQueries($ll) {
        $queries = array(
            "TRUNCATE artist;",
            "TRUNCATE genre;",
            "TRUNCATE track;",
            "TRUNCATE label;",
            "TRUNCATE album;",
            "TRUNCATE albumindex;",
            "TRUNCATE trackindex;",
            "ALTER TABLE `artist` AUTO_INCREMENT = 10;",
            "ALTER TABLE `genre` AUTO_INCREMENT = 10;",
            "ALTER TABLE `label` AUTO_INCREMENT = 10;",
            "ALTER TABLE `album` AUTO_INCREMENT = 10;",
            "ALTER TABLE `albumindex` AUTO_INCREMENT = 10;",
            "ALTER TABLE `track` AUTO_INCREMENT = 10;",
            "ALTER TABLE `trackindex` AUTO_INCREMENT = 10;",
        );
        foreach([
            'unknownartist' => 'artist',
            'variousartists' => 'artist',
            'unknowngenre' => 'genre'] as $llKey => $table) {
            $queries[] =
                "INSERT INTO `".$table."` ".
                "VALUES (
                    NULL,
                    '".$ll->str('importer.' . $llKey)."',
                    '',
                    '".az09($ll->str('importer.' . $llKey))."',
                    0,
                    0,
                    '',
                    '',
                    ''
                );";
        }
        $queries[] =
            "INSERT INTO `label` ".
            "VALUES (
                NULL,
                '".$ll->str('importer.unknownlabel')."',
                '".az09($ll->str('importer.unknownlabel'))."',
                0,
                0,
                '',
                '',
                ''
            );";
        return $queries;
    }


    public function buildDictionarySql() {
        \Slimpd\Modules\sphinx\Sphinx::defineSphinxConstants($this->conf['sphinx']);

        $input  = fopen ("php://stdin", "r");
        $output = fopen ("php://stdout", "w+");
        $usedKeywords = array();
        $sectionCounter = 0;
        fwrite ($output, "TRUNCATE suggest;\n");
        while ($line = fgets($input, 1024)) {
            list($keyword, $freq ) = explode(" ", trim($line));
            $keyword = trim($keyword);
            if (self::addKeywordToSql($keyword, $freq, $usedKeywords) === FALSE) {
                continue;
            }
            
            $trigrams = buildTrigrams($keyword);
            $usedKeywords[$keyword] = NULL;
            fwrite($output, (($sectionCounter === 0) ? "INSERT INTO suggest VALUES\n" : ",\n"));
            fwrite($output, "( 0, '".$keyword."', '".$trigrams.".', ".$freq.")");
            $sectionCounter++;
            if (($sectionCounter % 10000) == 0) {
                fwrite ($output, ";\n");
                $sectionCounter = 0;
            }
        }
        if ($sectionCounter > 0) {
            fwrite ( $output, ";" );
        }
        fwrite ( $output,  "\n");
    }

    private static function addKeywordToSql($keyword, $freq, $usedKeywords) {
        if($keyword === "") {
            return FALSE;
        }
        if($freq < FREQ_THRESHOLD) {
            return FALSE;
        }
        if(strlen($keyword) < 2) {
            return FALSE;
        }
        if(isset($usedKeywords[$keyword]) === TRUE ) {
            return FALSE;
        }
        if(strstr($keyword, "_") !== FALSE) {
            return FALSE;
        }
        if(strstr($keyword, "'") !== FALSE) {
            return FALSE;
        }
        return TRUE;
    }
    
}
