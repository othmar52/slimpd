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
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License
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


    public static function getQueriesForRemigrateBefore() {
        return [
            "DROP INDEX `artistUid` ON `track`;",
            "DROP INDEX `featuringUid` ON `track`;",
            "DROP INDEX `title` ON `track`;",
            "DROP INDEX `remixerUid` ON `track`;",
            "DROP INDEX `fingerprint` ON `track`;",
            "DROP INDEX `audioDataformat` ON `track`;",
            "DROP INDEX `videoDataformat` ON `track`;",
            "DROP INDEX `albumUid` ON `track`;",
            "DROP INDEX `genreUid` ON `track`;",
            "DROP INDEX `labelUid` ON `track`;",
            "DROP INDEX `bpm` ON `track`;",
            "DROP INDEX `error` ON `track`;",
            "DROP INDEX `transcoded` ON `track`;",
            "DROP INDEX `relPath` ON `track`;",
            "DROP INDEX `relPath` ON `track`;",
            "DROP INDEX `allchunks` ON `trackindex`;"
        ];
    }

    public static function getQueriesForRemigrateAfter() {
        return [
            "ALTER TABLE `track` ADD INDEX `artistUid` (`artistUid`);",
            "ALTER TABLE `track` ADD INDEX `featuringUid` (`featuringUid`);",
            "ALTER TABLE `track` ADD INDEX `title` (`title`);",
            "ALTER TABLE `track` ADD INDEX `remixerUid` (`remixerUid`);",
            "ALTER TABLE `track` ADD INDEX `fingerprint` (`fingerprint`);",
            "ALTER TABLE `track` ADD INDEX `audioDataformat` (`audioDataformat`);",
            "ALTER TABLE `track` ADD INDEX `videoDataformat` (`videoDataformat`);",
            "ALTER TABLE `track` ADD INDEX `albumUid` (`albumUid`);",
            "ALTER TABLE `track` ADD INDEX `genreUid` (`genreUid`);",
            "ALTER TABLE `track` ADD INDEX `labelUid` (`labelUid`);",
            "ALTER TABLE `track` ADD INDEX `bpm` (`bpm`);",
            "ALTER TABLE `track` ADD INDEX `error` (`error`);",
            "ALTER TABLE `track` ADD INDEX `transcoded` (`transcoded`);",
            "ALTER TABLE `track` ADD FULLTEXT `relPath` (`relPath`);",
            "ALTER TABLE `trackindex` ADD FULLTEXT `allchunks` (`allchunks`);"
        ];
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

    protected static function addKeywordToSql($keyword, $freq, $usedKeywords) {
        if($keyword === "") {
            return FALSE;
        }
        if($freq < FREQ_THRESHOLD) {
            return FALSE;
        }
        if(strlen($keyword) < 2) {
            return FALSE;
        }
        if(is_numeric($keyword) === TRUE) {
            return FALSE;
        }
        if(preg_match('/^[a-f0-9]{32}$/', $keyword)) {
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
