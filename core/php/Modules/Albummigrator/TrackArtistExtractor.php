<?php
namespace Slimpd\Modules\Albummigrator;
use \Slimpd\Utilities\RegexHelper as RGX;
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
 * This class extracts regular artist, featured artist and remix artists
 * further the track-title-pattern is created which preserves remix names
 */
trait TrackArtistExtractor {

	// input which will be processed
	protected $artistVanilla;
	protected $titleVanilla;

	// string representation which gets modified heavyly during processing
	// this will be written to table:trackIndex and visible in autocomplete-widget
	protected $artistString = '';
	protected $titleString = '';
	protected $titlePattern = '';

	// arrays with string values (track holds relational Uids in artistUid, feauringUid, remixerUid)
	protected $regularArtists = [];
	protected $featArtists = [];
	protected $remixArtists = [];

	// regex stuff which will be applied
	protected $regexArtist;
	protected $groupFeat1;
	protected $groupFeat2;
	protected $regexRemix1;
	protected $regexRemix2;

	// blacklists for stuff that should be ignored as remixer like "foo (Original Mix)"
	protected $artistBlacklist;
	protected $remixBlacklist;

	public function setRegularArtists($value) {
		$this->regularArtists = $value;
		return $this;
	}
	public function getRegularArtists() {
		return $this->regularArtists;
	}

	public function setFeaturedArtists($value) {
		$this->featArtists = $value;
		return $this;
	}
	public function getFeaturedArtists() {
		return $this->featArtists;
	}

	public function setRemixArtists($value) {
		$this->remixArtists = $value;
		return $this;
	}
	public function getRemixArtists() {
		return $this->remixArtists;
	}

	public function setArtistString($value) {
		$this->artistString = $value;
		return $this;
	}
	public function getArtistString() {
		return $this->artistString;
	}

	public function setTitleString($value) {
		$this->titleString = $value;
		return $this;
	}
	public function getTitleString() {
		return $this->titleString;
	}

	/**
	 * set class properties which are needed during parsing
	 */
	private function init() {
		$this->artistBlacklist = $this->container->artistRepo->getArtistBlacklist();
		$this->remixBlacklist = $GLOBALS["remixer-blacklist"] + $this->artistBlacklist;

		$this->artistVanilla = $this->getArtist();
		$this->titleVanilla = $this->getTitle();

		$this->artistString = $this->getArtist();
		$this->titleString = $this->getTitle();

		$this->regexArtist = "/".RGX::ARTIST_GLUE."/i";

		// assign all string-parts to category
		$this->groupFeat1 = "([\ \(])(featuring|ft(?:\.?)|feat(?:\.?)|w|w\.)\ ";
		$this->groupFeat2 = "([\ \(\.])(feat\.|ft\.|f\.|f\/)"; // without trailing whitespace

		# TODO: verify that this unused variable $groupGlue can be deleted and remove this line
		#$groupGlue = "/&amp;|\ &\ |\ and\ |&|\ n\'\ |\ vs(.?)\ |\ versus\ |\ with\ |\ meets\ |\  w\/\ /i";

		$this->regexRemix1 = "/" . RGX::REMIX1 . "/i";
		$this->regexRemix2 = "/" . RGX::REMIX2 . "/i";
	}

	private function parseStringForFeat($artistOrTitle, $regex) {
		cliLog("  INPUT: " . $this->$artistOrTitle, 10);
		if(preg_match("/(.*)" . $regex . "([^\(]*)(.*)$/i", $this->$artistOrTitle, $matches)) {
			$sFeat = trim($matches[4]);
			if(substr($sFeat, -1) == ')') {
				$sFeat = substr($sFeat,0,-1);
			}
			if(isset($this->artistBlacklist[strtolower($sFeat)]) === TRUE) {
				cliLog("  found ". $sFeat ." on blacklist. aborting..." , 10);
				return;
			}
			$this->$artistOrTitle = str_ireplace(
				$matches[2] .$matches[3] . ' ' . $matches[4],
				" ",
				$this->$artistOrTitle
			);
			$foundFeat = preg_split($this->regexArtist, $sFeat);
			$this->featArtists = array_merge($this->featArtists, $foundFeat);
			cliLog("  found featured artists:" , 10, "cyan");
			cliLog("  " . print_r($foundFeat,1) , 10);
			cliLog("  continue with ".$artistOrTitle.": " . $this->$artistOrTitle , 10);
			return;
		}
		cliLog("  no matches for featured artists " , 10, "darkgray");
	}

	private function parseStringForRemixer($input, $regex, $matchIndex) {
		cliLog("  INPUT: " . $input, 10);
		if(preg_match($regex, $input, $matches)) {
			$foundRemixers = preg_split($this->regexArtist, $matches[$matchIndex]);
			foreach($foundRemixers as $foundRemixer) {
				if(preg_match("/^(.*)'s(?:\ )?/", $foundRemixer, $matches2)) {
					$foundRemixer = $matches2[1];
				}
				cliLog("  found remix artists: " . $foundRemixer, 10, "cyan");
				$this->remixArtists[] = $foundRemixer;
			}

			#if(isset($this->artistBlacklist[strtolower($sFeat)]) === TRUE) {
			#	cliLog("  found ". $sFeat ." on blacklist. aborting..." , 10);
			#	return;
			#}

			return;
		}
		cliLog("  no matches for remix artists " , 10, "darkgray");
	}

	// TODO: pretty sure getzFeaturedArtist() from id3 tags is currently not used at all
	public function setFeaturedArtistsAndRemixers() {
		cliLog("=== artistparsing begin for " . basename($this->getRelPath()) . " " . $this->getRelPathHash() . " ===", 10, "yellow");
		$this->init();

		if($this->artistString == "") {
			$this->regularArtists[] = "Unknown Artist";
		}

		cliLog("parse ARTIST string for featured artists REGEX 1", 10, "purple");
		$this->parseStringForFeat("artistString", $this->groupFeat1);
		cliLog(" ", 10);

		cliLog("parse ARTIST string for featured artists REGEX 2", 10, "purple");
		$this->parseStringForFeat("artistString", $this->groupFeat2);
		cliLog(" ", 10);

		$this->regularArtists = array_merge($this->regularArtists, preg_split($this->regexArtist, $this->artistString));

		cliLog("parse TITLE string for featured artists REGEX 1", 10, "purple");
		$this->parseStringForFeat("titleString", $this->groupFeat1);
		cliLog(" ", 10);

		cliLog("parse TITLE string for featured artists REGEX 2", 10, "purple");
		$this->parseStringForFeat("titleString", $this->groupFeat2);
		cliLog(" ", 10);

		cliLog("parse TITLE string for remix artists REGEX 1", 10, "purple");
		$this->parseStringForRemixer($this->titleString, $this->regexRemix1, 2);
		cliLog(" ", 10);

		cliLog("parse TITLE string for remix artists REGEX 2", 10, "purple");
		$this->parseStringForRemixer($this->titleString, $this->regexRemix2, 3);
		cliLog(" ", 10);

		$this->removeCommonStringsFromArtists();

		$this->regularArtists = array_unique(array_filter(array_map('trim', $this->regularArtists)));
		$this->featArtists = array_unique(array_map('trim', $this->featArtists));
		$this->remixArtists = array_unique(array_map('trim', $this->remixArtists));

		cliLog("drop duplicates of regularArtists/featArtists", 10, "purple");
		$this->removeRegularArtistsBasedOnFeaturedArtists();

		// to avoid incomplete substitution caused by partly artistname-matches sort array by length DESC
		$sortedRxArtists = $this->remixArtists;
		usort($sortedRxArtists,'sortHelper');
		$this->titlePattern = str_ireplace($sortedRxArtists, "%s", $this->titleString);

		// make sure we have a whitespace on strings like "Bla (%sVIP Mix)"
		$this->titlePattern = flattenWhitespace(str_replace("%s", "%s ", $this->titlePattern));

		// but remove whitespace in a special case
		$this->titlePattern = str_replace("%s 's", "%s's", $this->titlePattern);

		if(substr_count($this->titlePattern, "%s") !== count($this->remixArtists)) {
			// oh no - we have a problem
			// reset extracted remixers
			cliLog("ERROR: amount of remix artists dows not match placeholders. resetting remixers", 10, "red");
			cliLog("  title pattern: " . $this->titlePattern, 10);
			cliLog("  remixers: " . print_r($this->remixArtists,1), 10);
			cliLog("  resetting remixers...", 10);
			$this->titlePattern = $this->titleString;
			$this->remixArtists = array();
		}
		$this->mayForceVariousArtists();

		$this->finish();
		return $this;
	}

	private function mayForceVariousArtists() {
		// force Various Artists on Mixes
		cliLog(__FUNCTION__, 10, "purple");
		if(az09($this->artistString) === az09($this->titleString) && $this->getMiliseconds() > 1800000) {
			cliLog("  forcing Various Artists", 10, "cyan");
			$this->regularArtists = ["Various Artists"];
			return;
		}
		cliLog("  not forcing Various Artists", 10, "darkgray");
	}

	private function finish() {
		$this->fetchArtistUids();

		// replace multiple whitespace with a single whitespace
		$this->titlePattern = flattenWhitespace($this->titlePattern);
		// remove whitespace before bracket
		$this->titlePattern = trim(str_replace(' )', ')', $this->titlePattern));

		// close possible open brackets
		$this->titlePattern .= (substr_count($this->titlePattern, "(") > substr_count($this->titlePattern, ")")) ? ")" : "";

		$this->setTitle($this->titlePattern);

		$this->artistString = join(" & ", $this->regularArtists);
		if(count($this->featArtists) > 0) {
			$this->artistString .= " (ft. " . join(" & ", $this->featArtists) . ")";
		}
		cliLog("=== artistparsing end for " . basename($this->getRelPath()) . " " . $this->getRelPathHash() . " ===", 10, "yellow");
		$this->dumpParserResults();
	}

	/**
	 * in case we have the same artist in regular and featured drop the featured artist
	 * but only if we have a regular artist left after dropping
	 */
	private function removeRegularArtistsBasedOnFeaturedArtists() {
		$dupes =  array_intersect($this->regularArtists, $this->featArtists);
		if(count($dupes) === 0) {
			cliLog("  no dupes found.", 10, "darkgray");
			return;
		}
		$dropFrom = (count($this->regularArtists) > count($dupes)) ? 'regularArtists' : 'featArtists';
		foreach($dupes as $artistString) {
			if(($key = array_search($artistString, $this->$dropFrom)) !== false) {
				cliLog("  removing " . $artistString . " from " . $dropFrom, 10);
				unset($this->$dropFrom[$key]);
			}
		}
	}
	private function removeCommonStringsFromArtists() {
		cliLog(__FUNCTION__, 10, "purple");
		// clean up extracted remixer-names with common strings
		$tmp = array();
		foreach($this->remixArtists as $remixerArtist) {
			$correction = FALSE;
			$remixerArtist = trim($remixerArtist, "()- ");
			foreach(array_keys($this->remixBlacklist) as $chunk) {
				if(preg_match("/(.*)" . $chunk . "$/i", $remixerArtist, $matches)) {
					$tmp[] = str_ireplace($chunk, "", $remixerArtist);
					cliLog("  removing chunk " . $chunk . " from remix-artist", 10, "cyan");
					$correction = TRUE;
					break;
				}
			}
			if($correction === FALSE) {
				$tmp[] = $remixerArtist;
			}
		}
		$this->remixArtists = $tmp;

		// clean up extracted featuring-names with common strings
		$tmp = array();
		foreach($this->featArtists as $featuredArtist) {
			$featuredArtist = trim($featuredArtist, "()- ");
			if(isset($this->artistBlacklist[ strtolower($featuredArtist)] ) === TRUE) {
				continue;
			}
			// TODO: pretty sure we have to append stripped phrases to tracktitle, right?
			$tmp[] = str_ireplace($this->artistBlacklist, "", $featuredArtist);
		}
		$this->featArtists = $tmp;

		// sometimes extracted featured artist has a remixer included
		// example "Danny Byrd - Tonight (feat. Netsky - Cutline Remix)"
		$tmp = array();
		foreach($this->featArtists as $featuredArtist) {
			// compareString does not have braces "Netsky - Cutline Remix"
			// to use existing remixer regex we have to add braces
			$compareString = str_replace(" - ", " (", $featuredArtist) . ")";

			if(preg_match("/^". RGX::REMIX1 . "\)$/i" , $compareString, $matches)) {
				$tmp[] = trim($matches[1]);
				if(array_key_exists(strtolower(trim($matches[2])), $this->remixBlacklist) === FALSE) {
					$this->remixArtists[] = trim($matches[2]);
				}
				// append it to title string
				// TODO: make sure titlestring does not end up in "Tracktitle (Artist 1 Remix) (Artist 2 Remix)"
				$this->titleString .= " (". trim($matches[2]) .$matches[3] . ")";
				continue;
			}
			// clean feat-artists like "Zarif - Instrumental"
			foreach(array_keys($this->remixBlacklist) as $blacklistItem) {
				if(preg_match("/^" . RGX::ANYTHING . "\ (". $blacklistItem .")$/i", $featuredArtist, $matches)) {
					$featuredArtist = trim($matches[1], " -");
					$this->titleString .= " (". trim($matches[2]) . ")";
					break;
				}
			}
			$tmp[] = $featuredArtist;
		}
		$this->featArtists = $tmp;
		cliLog(" ", 10);
	}

	private function fetchArtistUids() {
		$this->setArtistUid(join(",", $this->container->artistRepo->getUidsByString(join(" & ", $this->regularArtists))));

		$this->setFeaturingUid('');
		if(count($this->featArtists) > 0) {
			$this->setFeaturingUid(join(",", $this->container->artistRepo->getUidsByString(join(" & ", $this->featArtists))));
		}

		$this->setRemixerUid('');
		if(count($this->remixArtists) > 0) {
			$this->setRemixerUid(join(",", $this->container->artistRepo->getUidsByString(join(" & ", $this->remixArtists))));
		}
	}

	private function dumpParserResults() {
		cliLog("=== artistresult begin for " . basename($this->getRelPath()) . " " . $this->getRelPathHash() . " ===", 10, "yellow");
		cliLog(str_repeat("―", 90), 10, "purple");
		cliLog("ARTIST", 10, "cyan");
		cliLog("  INPUT      | " . $this->artistVanilla, 10);
		cliLog("  RESULT     | " . $this->artistString, 10);
		cliLog(str_repeat("―", 90), 10, "purple");
		cliLog("TITLE", 10, "cyan");
		cliLog("  INPUT      | " . $this->titleVanilla, 10);
		cliLog("  RESULT     | " . $this->titleString, 10);

		if(count($this->remixArtists) > 0){
			cliLog("  PATTERN    | " . $this->titlePattern, 10);
		}
		cliLog(str_repeat("―", 90), 10, "purple");
		if(count($this->remixArtists) > 0){
			cliLog("REMIXERS", 10, "cyan");
			foreach ($this->remixArtists as $artist) {
				cliLog("  " .$artist, 10);
			}
			cliLog(" ", 10);
		}
		cliLog("ARTISTS", 10, "cyan");
		foreach ($this->regularArtists as $artist) {
			cliLog("  " .$artist, 10);
		}
		cliLog(" ", 10);
		if(count($this->featArtists) > 0){
			cliLog("FEAT. ARTISTS", 10, "cyan");
			foreach ($this->featArtists as $artist) {
				cliLog("  " .$artist, 10);
			}
			cliLog(" ", 10);
		}
		cliLog(str_repeat("―", 90), 10, "purple");
		cliLog("=== artistresult end for " . basename($this->getRelPath()) . " " . $this->getRelPathHash() . " ===", 10, "yellow");
	}
}
