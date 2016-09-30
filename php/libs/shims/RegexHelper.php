<?php
namespace Slimpd;
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

	const NUM    = "([\d]{1,3})";
	const VINYL  = "([A-M\d]{1,3})"; // a1, AA2,
	const GLUE   = "(?:[ .\-_\|]{1,4})"; // "_-_", ". ", "-",
	const GLUE_NO_WHITESPACE   = "(?:[.\-_]{1,4})"; // "_-_", ". ", "-",
	const EXT    = "\.([a-z\d]{2,4})";
	const SCENE  = "-([^\s\-]+)";
	const YEAR  = "((?:[1920]{2})(?:[0-9]{2}))";
	#const year  = "([0-9]{4})";
	const CATNR  = "((?:([\(\[]{1})?((?:[A-Z]{2,14})(?:[0-9]{1,})(?:[A-Z]{2,7})?)(?:([\)\]]{1})?)))";
	const SOURCE  = "((?:([\(\[]{1})?([vinylVINYLwebWEBCDcd]{3,5})(?:([\)\]]{1})?)))";
	const NO_MINUS= "([^-]+)";
	const ANYTHING= "(.*)";
	const MAY_BRACKET = "(?:([\(\)\[\]]{0,1}))?";
	
	const VARIOUS = "(va|v\.a\.|various|various\ artists|various\ artist)";
	
	const ARTIST_GLUE = ",|&amp;|\ &\ |\ and\ |&|\ n\'\ |\ vs(.?)\ |\ versus\ |\ with\ |\ meets\ |\  w\/|\.and\.|\ aka\ |\ b2b\ |\/";
	const REMIX1 = "(.*)\((.*)(\ remix|\ mix|\ rework|\ rmx|\ re-edit|\ re-lick|\ vip|\ remake)";
	const REMIX2 = "(.*)\((remix\ by\ |remixed\ by\ |remixedby\ )(.*)?\)";
	
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
}
