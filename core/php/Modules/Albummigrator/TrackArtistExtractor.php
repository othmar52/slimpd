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

	// those attributes holds string values (track holds relational Uids)
	protected $regularArtists;
	protected $featArtists;
	protected $remixArtists;

	protected $fullArtistString; // this will be written to table:trackIndex and visible in autocomplete-widget
	protected $fullTitleString;  // this will be written to table:trackIndex and visible in autocomplete-widget

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

	public function setFullArtistString($value) {
		$this->fullArtistString = $value;
		return $this;
	}
	public function getFullArtistString() {
		return $this->fullArtistString;
	}

	public function setFullTitleString($value) {
		$this->fullTitleString = $value;
		return $this;
	}
	public function getFullTitleString() {
		return $this->fullTitleString;
	}

	// TODO: refacture!!!
	// TODO: pretty sure getzFeaturedArtist() from id3 tags is currently not used at all
	public function setFeaturedArtistsAndRemixers() {
		$artistBlacklist = $this->container->artistRepo->getArtistBlacklist();

		$this->artistVanilla = $this->getArtist();
		$this->titleVanilla = $this->getTitle();
		
		$regexArtist = "/".RGX::ARTIST_GLUE."/i";
		$regexRemix = "/" . RGX::REMIX1 . "/i";
		$regexRemix2 = "/" . RGX::REMIX2 . "/i";
		
		$this->regularArtists = array();
		$this->featArtists = array();
		$this->remixArtists = array();
		$titlePattern = '';

		$artistString = $this->artistVanilla;
		$titleString = $this->titleVanilla;

		// assign all string-parts to category
		$groupFeat = "([\ \(])(featuring|ft(?:.?)|feat(?:.?)|w(?:.?))\ ";
		$groupFeat2 = "([\ \(\.])(feat\.|ft\.|f\.)"; // without trailing whitespace

		# TODO: verify that this unused variable $groupGlue can be deleted and remove this line
		#$groupGlue = "/&amp;|\ &\ |\ and\ |&|\ n\'\ |\ vs(.?)\ |\ versus\ |\ with\ |\ meets\ |\  w\/\ /i";

		if($artistString == "") {
			$this->regularArtists[] = "Unknown Artist";
		}
		
		// parse ARTIST string for featured artists REGEX 1
		if(preg_match("/(.*)" . $groupFeat . "([^\(]*)(.*)$/i", $artistString, $matches)) {
			$sFeat = trim($matches[4]);
			if(substr($sFeat, -1) == ')') {
				$sFeat = substr($sFeat,0,-1);
			}
			$artistString = str_replace(
				$matches[2] .$matches[3] . ' ' . $matches[4],
				" ",
				$artistString
			);
			$this->featArtists = array_merge($this->featArtists, preg_split($regexArtist, $sFeat));
		}
		// parse ARTIST string for featured artists REGEX 2
		if(preg_match("/(.*)" . $groupFeat2 . "([^\(]*)(.*)$/i", $artistString, $matches)) {
			$sFeat = trim($matches[4]);
			if(substr($sFeat, -1) == ')') {
				$sFeat = substr($sFeat,0,-1);
			}
			$artistString = str_replace(
				$matches[2] .$matches[3] . $matches[4],
				" ",
				$artistString
			);
			$this->featArtists = array_merge($this->featArtists, preg_split($regexArtist, $sFeat));
		}
		
		$this->regularArtists = array_merge($this->regularArtists, preg_split($regexArtist, $artistString));
		
		// parse TITLE string for featured artists REGEX 1
		if(preg_match("/(.*)" . $groupFeat . "([^\(]*)(.*)$/i", $titleString, $matches)) {
			$sFeat = trim($matches[4]);
			if(substr($sFeat, -1) == ')') {
				$sFeat = substr($sFeat,0,-1);
			}
			
			if(isset($artistBlacklist[strtolower($sFeat)]) === FALSE) {
				$titleString = str_replace(
					$matches[2] .$matches[3] . ' ' . $matches[4],
					" ",
					$titleString
				);
				$this->featArtists = array_merge($this->featArtists, preg_split($regexArtist, $sFeat));
			}
		}
		
		// parse TITLE string for featured artists REGEX 2
		if(preg_match("/(.*)" . $groupFeat2 . "([^\(]*)(.*)$/i", $titleString, $matches)) {
			#print_r($matches); die();
			$sFeat = trim($matches[4]);
			if(substr($sFeat, -1) == ')') {
				$sFeat = substr($sFeat,0,-1);
			}
			if(isset($artistBlacklist[strtolower($sFeat)]) === FALSE) {
				$titleString = str_replace(
					$matches[2] .$matches[3] . $matches[4],
					" ",
					$titleString
				);
				$this->featArtists = array_merge($this->featArtists, preg_split($regexArtist, $sFeat));
			}
		}

		// parse title string for remixer regex 1
		if(preg_match($regexRemix, $titleString, $matches)) {
			$this->remixArtists = array_merge($this->remixArtists, preg_split($regexArtist, $matches[2]));
		}
		// parse title string for remixer regex 1
		if(preg_match($regexRemix2, $titleString, $matches)) {
			#print_r($matches); die();
			$this->remixArtists = array_merge($this->remixArtists, preg_split($regexArtist, $matches[3]));
		}
		
		// clean up extracted remixer-names with common strings
		$tmp = array();
		$remixerBlacklist = array_merge($GLOBALS["remixer-blacklist"], $artistBlacklist);
		foreach($this->remixArtists as $remixerArtist) {
			$correction = FALSE;
			foreach(array_keys($remixerBlacklist) as $chunk) {
				if(preg_match("/(.*)" . $chunk . "$/i", $remixerArtist, $matches)) {
					$tmp[] = str_ireplace($chunk, "", $remixerArtist);
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
			if(isset($artistBlacklist[ strtolower($featuredArtist)] ) === TRUE) {
				continue;
			}
			// TODO: pretty sure we have to append stripped phrases to tracktitle, right?
			$tmp[] = str_ireplace($artistBlacklist, "", $featuredArtist);
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
				if(array_key_exists(strtolower(trim($matches[2])), $remixerBlacklist) === FALSE) {
					$this->remixArtists[] = trim($matches[2]);
				}
				// append it to title string
				// TODO: make sure titlestring does not end up in "Tracktitle (Artist 1 Remix) (Artist 2 Remix)"
				$titleString .= " (". trim($matches[2]) .$matches[3] . ")";
				continue;
			}
			// clean feat-artists like "Zarif - Instrumental"
			foreach(array_keys($remixerBlacklist) as $blacklistItem) {
				if(preg_match("/^" . RGX::ANYTHING . "\ (". $blacklistItem .")$/i", $featuredArtist, $matches)) {
					$featuredArtist = trim($matches[1], " -");
					$titleString .= " (". trim($matches[2]) . ")";
					break;
				}
			}
			$tmp[] = $featuredArtist;
		}
		$this->featArtists = $tmp;
		
		$this->regularArtists = array_unique(array_filter($this->regularArtists));
		$this->featArtists = array_unique($this->featArtists);
		$this->remixArtists = array_unique($this->remixArtists);
		
		// to avoid incomplete substitution caused by partly artistname-matches sort array by length DESC
		$allArtists = array_merge($this->regularArtists, $this->featArtists, $this->remixArtists);
		usort($allArtists,'sortHelper');
		$titlePattern = str_ireplace($allArtists, "%s", $titleString);
		
		// make sure we have a whitespace on strings like "Bla (%sVIP Mix)"
		$titlePattern = flattenWhitespace(str_replace("%s", "%s ", $titlePattern));
		

		// remove possible brackets from featuredArtists
		#$tmp = array();
		#foreach($this->featArtists as $featuredArtist) {
		#	$tmp[] = str_replace(array("(", ")"), "", $featuredArtist);
		#}
		#$this->featArtists = $tmp;
		
		if(substr_count($titlePattern, "%s") !== count($this->remixArtists)) {
			// oh no - we have a problem
			// reset extracted remixers
			$titlePattern = $titleString;
			$this->remixArtists = array();
		}
		
		/* TODO: do we need this?
		// remove " (" from titlepattern in case that are the last 2 chars
		if(preg_match("/(.*)\ \($/", $titlePattern, $matches)) {
			$titlePattern = $matches[1];
		}
		*/
		
		// clean up artist names
		// unfortunately there are artist names like "45 Thieves"
		$this->regularArtists = $this->removeLeadingNumbers($this->regularArtists);
		$this->setArtistUid(join(",", $this->container->artistRepo->getUidsByString(join(" & ", $this->regularArtists))));

		$this->setFeaturingUid('');
		$this->featArtists = $this->removeLeadingNumbers($this->featArtists);
		if(count($this->featArtists) > 0) {
			$this->setFeaturingUid(join(",", $this->container->artistRepo->getUidsByString(join(" & ", $this->featArtists))));
		}

		$this->setRemixerUid('');
		$this->remixArtists = $this->removeLeadingNumbers($this->remixArtists);
		if(count($this->remixArtists) > 0) {
			$this->setRemixerUid(join(",", $this->container->artistRepo->getUidsByString(join(" & ", $this->remixArtists))));
		}
		
		// replace multiple whitespace with a single whitespace
		$titlePattern = flattenWhitespace($titlePattern);
		// remove whitespace before bracket
		$titlePattern = str_replace(' )', ')', $titlePattern);
		$this->setTitle($titlePattern);
		#$performTest = 1;
		if($performTest > 0) {
			cliLog("----------ARTIST-PARSER---------", 1, "purple");
			cliLog(" inputArtist: " . $this->artistVanilla);
			cliLog(" inputTitle: " . $this->titleVanilla);
			cliLog(" regular: " . print_r($this->regularArtists,1));
			cliLog(" feat: " . print_r($this->featArtists,1));
			cliLog(" remixer: " . print_r($this->remixArtists,1));
			cliLog(" titlePattern: " . $titlePattern);
			cliLog(" titleString: " . $titleString);
		}
		$fullArtistString = join(" & ", $this->regularArtists);
		if(count($this->featArtists) > 0) {
			$fullArtistString .= " (ft. " . join(" & ", $this->featArtists) . ")";
		}
		$this->setFullArtistString($fullArtistString);
		$this->setFullTitleString($titleString);
		return $this;
	}
	
	// fix artists names like
	// 01.Dread Bass
	// 01 - Cookie Monsters
	public function removeLeadingNumbers($inputArray) {
		$out = array();
		#TODO: move to customizeable config
		$whitelist = array(
			"45 thieves",
			#"60 minute man"
		);
		foreach($inputArray as $string) {
			if(in_array(strtolower($string), $whitelist) === FALSE) {
				if(preg_match("/^([\d]{2})([\.\-\ ]{1,3})([^\d]{1})(.*)$/", $string, $matches)) {
					if($matches[1] < 21) {
						#print_r($matches); die();
						$string = $matches[3].$matches[4];
					}
				}
			}
			$out[] = $string;
		}
		return $out;
	}

}
