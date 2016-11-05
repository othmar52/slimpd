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

class TrackRecommendationsPostProcessor {

	public static function postProcess($setterName, $value, &$contextItem) {
		if(method_exists(__CLASS__, $setterName) === FALSE) {
			// we dont have a post processer for this property
			return;
		}
		self::$setterName($value, $contextItem);
	}

	public static function setArtist($value, &$contextItem) {
		// "A01. Master of Puppets"
		if(preg_match("/^" . RGX::MAY_BRACKET . RGX::VINYL . RGX::MAY_BRACKET . RGX::GLUE . RGX::ANYTHING . "$/i", $value, $matches)) {
			$contextItem->setRecommendationEntry("setTrackNumber", strtoupper($matches[1]), 1);
			$contextItem->setRecommendationEntry("setArtist", $matches[2], 1);
			$contextItem->setRecommendationEntry("setArtist", $value, -2);
		}
		// "1. Master of Puppets"
		if(preg_match("/^" . RGX::MAY_BRACKET . RGX::NUM . RGX::MAY_BRACKET . RGX::GLUE . RGX::ANYTHING . "$/i", $value, $matches)) {
			$contextItem->setRecommendationEntry("setTrackNumber", removeLeadingZeroes($matches[1]), 1);
			$contextItem->setRecommendationEntry("setArtist", $matches[2], 1);
			$contextItem->setRecommendationEntry("setArtist", $value, -2);
		}
	}

	public static function setTitle($value, &$contextItem) {
		if(preg_match("/^" . RGX::MAY_BRACKET . RGX::VINYL . RGX::MAY_BRACKET . RGX::GLUE . RGX::ANYTHING . "$/i", $value, $matches)) {
			$contextItem->setRecommendationEntry("setTrackNumber", strtoupper($matches[1]), 1);
			$contextItem->setRecommendationEntry("setTitle", $matches[2], 1);
			$contextItem->setRecommendationEntry("setTitle", $value, -2);
		}
		if(preg_match("/^" . RGX::MAY_BRACKET . RGX::NUM . RGX::MAY_BRACKET . RGX::GLUE . RGX::ANYTHING . "$/i", $value, $matches)) {
			$contextItem->setRecommendationEntry("setTrackNumber", removeLeadingZeroes($matches[1]), 1);
			$contextItem->setRecommendationEntry("setTitle", $matches[2], 1);
			$contextItem->setRecommendationEntry("setTitle", $value, -2);
		}
	}

	public static function setTrackNumber($value, &$contextItem) {
		if(isset($value[0]) && $value[0] === "0") {
			$contextItem->setRecommendationEntry("setTrackNumber", removeLeadingZeroes($value), 1);
			$contextItem->setRecommendationEntry("setTrackNumber", $value, -2);
		}
	}

	public static function setYear($value, &$contextItem) {
		$score = (RGX::seemsYeary($value) === TRUE) ? 1 : -1;
		$contextItem->setRecommendationEntry("setYear", $value, $score);
	}

	public static function setLabel($value, &$contextItem) {
		// "℗ 2010 Lench Mob Records"
		// "(p) 2009 Lotus Records"
		// "(p) & (c) 2005 Mute Records Ltd"
		// "(P)+(C) 1998 Elektrolux"
		if(preg_match("/^(?:℗|\(p\)|\(p\)[ &+]\(c\))" . RGX::YEAR . "\ " . RGX::ANYTHING ."$/i", $value, $matches)) {
			if(RGX::seemsYeary($matches[1]) === TRUE) {
				$contextItem->setRecommendationEntry("setYear", trim($matches[1]), 1);
				$contextItem->setRecommendationEntry("setLabel", trim($matches[2]), 1);
				$contextItem->setRecommendationEntry("setLabel", $value, -2);
				return;
			}
		}

		// "(c)Subtitles Music (UK)"
		if(preg_match("/^\(c\)" . RGX::ANYTHING ."$/i", $value, $matches)) {
			$contextItem->setRecommendationEntry("setLabel", trim($matches[1]), 1);
			$contextItem->setRecommendationEntry("setLabel", $value, -2);
			return;
		}

		// "a division of Universal Music GmbH"
		if(preg_match("/^a\ division\ of\ " . RGX::ANYTHING ."$/i", $value, $matches)) {
			$contextItem->setRecommendationEntry("setLabel", trim($matches[1]), 1);
			$contextItem->setRecommendationEntry("setLabel", $value, -2);
			return;
		}

		// "Jerona Fruits (JF006)"
		// "World Of Drum & Bass (WODNB003)"
		// "Viper Recordings | VPR051" // TODO: make sure glue gets removed
		// "Jazzman - JMANCD048" // TODO: make sure glue gets removed
		if(preg_match("/^" . RGX::ANYTHING . RGX::GLUE . RGX::CATNR . "$/", $value, $matches)) {
			$contextItem->setRecommendationEntry("setLabel", trim($matches[1]), 1);
			$contextItem->setRecommendationEntry("setCatalogNr", trim($matches[2]), 1);
			$contextItem->setRecommendationEntry("setLabel", $value, -2);
			return;
		}
	}

	/**
	 * in case we have
	 * 	 most scored artist: "Franck Dona & Dan Marciano - Losing My Religion(Chris Kaeser Mix)"
	 *   most scored title : "Losing My Religion(Chris Kaeser Mix)"
	 * remove title from artist
	 *
	 * this test only makes sense AFTER ALL recommentations
	 */
	public static function removeSuffixedTitleFromArtist(&$contextItem) {
		cliLog(__FUNCTION__, 9, "purple");
		$artist = $contextItem->getMostScored("setArtist");
		cliLog("  most scored artist: " . $artist, 10);
		$title = $contextItem->getMostScored("setTitle");
		cliLog("  most scored title : " . $title, 10);
		if(stripos($artist, $title) === FALSE) {
			cliLog("  artist does not contain title", 9);
			return;
		}
		$shortenedArtist = trim(str_ireplace($title, "", $artist), " -");
		cliLog("  shortened artist  : " . $shortenedArtist, 9);
		$contextItem->recommend(["setArtist" => $shortenedArtist], 5);
	}

	/**
	 * in case we have
	 * 	 most scored artist: "Kenny Dope Feat. Screechy Dan"
	 *   most scored title : "Kenny Dope Feat. Screechy Dan - Boomin' In Ya Jeep"
	 * remove artist from title
	 *
	 * this test only makes sense AFTER ALL recommentations
	 */
	public static function removePrefixedArtistFromTitle(&$contextItem) {
		cliLog(__FUNCTION__, 9, "purple");
		$artist = $contextItem->getMostScored("setArtist");
		cliLog("  most scored artist: " . $artist, 10);
		$title = $contextItem->getMostScored("setTitle");
		cliLog("  most scored title : " . $title, 10);
		if(az09($artist) === az09($title)) {
			cliLog("  very similar. downvoting both", 10);
			$contextItem->recommend(["setArtist" => $artist], -2);
			$contextItem->recommend(["setTitle" => $title], -2);
			return;
		}
		if(stripos($title, $artist) !== 0) {
			cliLog("  artist is not prefixed in title", 9);
			return;
		}
		$shortenedTitle = ltrim(substr($title, strlen($artist)), " -");
		cliLog("  shortened title  : " . $shortenedTitle, 9);
		$contextItem->recommend(["setTitle" => $shortenedTitle], 5);
	}

	/**
	 * this function checks all artist recommendations for beeing "Various Artists", "v.a."
	 * to avoid this situation:
	 * 	 most scored artist: "Various Artists"
	 *   most scored title : "Tenor Saw - Ring The Alarm (Hip Hop Mix)"
	 */
	public static function downVoteVariousArtists(&$contextItem) {
		cliLog(__FUNCTION__, 9, "purple");
		if(array_key_exists("setArtist", $contextItem->recommendations) === FALSE) {
			cliLog("  no recommendations for setArtist. skipping...", 1);
			return;
		}
		foreach(array_keys($contextItem->recommendations["setArtist"]) as $artistRecommendation) {
			if(RGX::isVa($artistRecommendation) === FALSE) {
				cliLog("  no need to downvote: " . $artistRecommendation, 10);
				continue;
			}
			cliLog("  found setArtist recommendation for downvoting");
			$contextItem->recommend(["setArtist" => $artistRecommendation], -5);
		}
	}
}
