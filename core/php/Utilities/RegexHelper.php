<?php
namespace Slimpd\Utilities;
/* Copyright (C) 2015-2016 othmar52 <othmar52@users.noreply.github.com>
 *
 * This file is part of sliMpd - a php based mpd web client
 *
 * This program is free software: you can redistribute it and/or modify it
 * under the terms of the GNU Affero General public static License as published by the
 * Free Software Foundation, either version 3 of the License, or (at your
 * option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Affero General public static License
 * for more details.
 *
 * You should have received a copy of the GNU Affero General public static License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

class RegexHelper {

    const NUM    = "(\d{1,3})";

    #TODO: "AB-" should not match at all but currently matches with "B-"
    const VINYL  = "([A-Ma-m]{1}\2?(?:\d{1,2})?)"; // a1, AA2, but not AB1, N4, 01A
    const VINYL_STRICT = "((?:[A-Ma-m]){1}(?:\d){1,2})"; // a1, B2,C03, but not A, AA1, C001
    const GLUE   = "[ .\-_\|]{1,4}"; // "_-_", ". ", "-",
    const GLUE_NO_WHITESPACE   = "(?:[\\.\-_]{1,4})"; // "_-_", ". ", "-",
    const EXT    = "\.([a-zA-Z\d]{2,4})";
    const SCENE  = "-([^\s\-]+)";
    const YEAR   = "(?<!\d)(?:\D)?([12]{1}[0-9]{3})(?:\D)?";
    const CATNR  = "(?:[\(\[]{1})?([A-Za-z]{2,14}[0-9]{1,}(?:[A-Za-z]{2,7})?)(?:[\)\]]{1})?";
    const SOURCE  = "((?:[\(\[]{1})?[vinylVINYLwebWEBCDMScdms]{2,5}(?:[\)\]]{1})?)"; // (cd, cdm, cds, vinyl, web)
    const NO_MINUS= "([^-]+)";
    const ANYTHING= "(.*)";
    const MAY_BRACKET = "(?:[\(\)\[\]]{0,1})?";
    const URL = "((?:http|https)\:\/\/(?:[^\s]+))";

    const VARIOUS = "(va|v\.a\.|various|various\ artists|various\ artist)";

    const ARTIST_GLUE = ",|&amp;|\ &\ |\ and\ |&|\ n\'\ |\ vs(.?)\ |\ versus\ |\ with\ |\ meets\ |\  w\/|\.and\.|\ aka\ |\ b2b\ |\/";
    const REMIX1 = "(.*)(?:\(|\ -\ )(.*)(\ vip\ mix|\ remix|\ mix|\ rework|\ rmx|\ re-edit|\ re-lick|\ re-set|\ vip|\ remake|\ instrumental|\ radio\ edit|\ edit)";
    const REMIX2 = "(.*)(remix\ by\ |rmx\ by\ |remixed\ by\ |remixedby\ |mixed\ by\ |compiled\ by\ |arranged\ by\ )(.*)\)?";

    public static function seemsYeary($input) {
        return ($input > 1900 && $input < date("Y")+1 )? TRUE : FALSE;
    }

    public static function seemsCatalogy($input) {
        $input = preg_replace('/[^A-Z0-9]/', "", strtoupper($input));
        if(preg_match("/". $this->catNr."/", $input)) {
            return TRUE;
        }
        return FALSE;
    }

    public static function seemsTitly($input) {
        $blacklist = array(
            'various',
            'artist',
            'artists',
            'originalmix'
        );
        if(isset($blacklist[az09($input)]) === TRUE) {
            return FALSE;
        }
        return TRUE;
    }

    public static function seemsArtistly($input) {
        $blacklist = array(
            'various',
            'artist',
            'artists',
            'originalmix',
            'unknownartist',
            'unbekannterinterpret'
        );
        if(isset($blacklist[az09($input)]) === TRUE) {
            return FALSE;
        }
        return TRUE;
    }

    public static function isVa($input) {
        // TODO: convert to regex where those strings also get recognized
        // Various Artisis
        // Varioust Artist
        // Various (unknown) Artists
        // Varios Artistas
        // Various Artistes
        // Artistes Varies
        $compare = array(
            'various' => NULL,
            'variousartist' => NULL,
            'variousartisis' => NULL,
            'variousartists' => NULL,
            'varios' => NULL,
            'variosartist' => NULL,
            'variosartists' => NULL,
            'va' => NULL
        );
        return array_key_exists(az09($input), $compare);
    }

    public static function isUnknownArtist($input) {
        // TODO: convert to regex where those strings also get recognized
        $compare = array(
            'unknownartist' => NULL,
            'unknownartists' => NULL,
            'unbekannterkuenstler' => NULL,
            'unbekannterkunstler' => NULL,
            'unbekannterknstler' => NULL,
            'inbekannterinterpret' => NULL
        );
        return array_key_exists(az09($input), $compare);
    }

    /**
     * TODO: remove this condition as soon as RGX::VINYL is capable of this
     * @see: https://regex101.com/r/a1HBxr/3
     */
    public static function seemsVinyly($input) {
        $blacklist = array(
            'dj',
            'mc',
            'i'
        );
        if(isset($blacklist[az09($input)]) === TRUE) {
            return FALSE;
        }
        return TRUE;
    }
}
