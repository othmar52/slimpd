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

	private static function setArtist($value, &$contextItem) {
		// "A01. Master of Puppets"
		if(preg_match("/^" . RGX::MAY_BRACKET . RGX::VINYL . RGX::MAY_BRACKET . RGX::GLUE . RGX::ANYTHING . "$/i", $value, $matches)) {
			$contextItem->setRecommendationEntry("setTrackNumber", strtoupper($matches[1]), 1);
			$contextItem->setRecommendationEntry("setArtist", $matches[2], 1);
		}
		// "1. Master of Puppets"
		if(preg_match("/^" . RGX::MAY_BRACKET . RGX::NUM . RGX::MAY_BRACKET . RGX::GLUE . RGX::ANYTHING . "$/i", $value, $matches)) {
			$contextItem->setRecommendationEntry("setTrackNumber", removeLeadingZeroes($matches[1]), 1);
			$contextItem->setRecommendationEntry("setArtist", $matches[2], 1);
		}
	}

	private static function setTitle($value, &$contextItem) {
		if(preg_match("/^" . RGX::MAY_BRACKET . RGX::VINYL . RGX::MAY_BRACKET . RGX::GLUE . RGX::ANYTHING . "$/i", $value, $matches)) {
			$contextItem->setRecommendationEntry("setTrackNumber", strtoupper($matches[1]), 1);
			$contextItem->setRecommendationEntry("setTitle", $matches[2], 1);
		}
		if(preg_match("/^" . RGX::MAY_BRACKET . RGX::NUM . RGX::MAY_BRACKET . RGX::GLUE . RGX::ANYTHING . "$/i", $value, $matches)) {
			$contextItem->setRecommendationEntry("setTrackNumber", removeLeadingZeroes($matches[1]), 1);
			$contextItem->setRecommendationEntry("setTitle", $matches[2], 1);
		}
	}

	private static function setTrackNumber($value, &$contextItem) {
		if(isset($value[0]) && $value[0] === "0") {
			$contextItem->setRecommendationEntry("setTrackNumber", removeLeadingZeroes($value), 1);
		}
	}

	private static function setLabel($value, &$contextItem) {
		// "℗ 2010 Lench Mob Records"
		// "(p) 2009 Lotus Records"
		// "(p) & (c) 2005 Mute Records Ltd"
		// "(P)+(C) 1998 Elektrolux"
		if(preg_match("/^(?:℗|\(p\)|\(p\)[ &+]\(c\))" . RGX::YEAR . "\ " . RGX::ANYTHING ."$/i", $value, $matches)) {
			$contextItem->setRecommendationEntry("setYear", trim($matches[1]), 1);
			$contextItem->setRecommendationEntry("setLabel", trim($matches[2]), 1);
			return;
		}

		// "(c)Subtitles Music (UK)"
		if(preg_match("/^\(c\)" . RGX::ANYTHING ."$/i", $value, $matches)) {
			$contextItem->setRecommendationEntry("setLabel", trim($matches[1]), 1);
			return;
		}

		// "a division of Universal Music GmbH"
		if(preg_match("/^a\ division\ of\ " . RGX::ANYTHING ."$/i", $value, $matches)) {
			$contextItem->setRecommendationEntry("setLabel", trim($matches[1]), 1);
			return;
		}

		// "Jerona Fruits (JF006)"
		// "World Of Drum & Bass (WODNB003)"
		// "Viper Recordings | VPR051" // TODO: make sure glue gets removed
		// "Jazzman - JMANCD048" // TODO: make sure glue gets removed
		if(preg_match("/^" . RGX::ANYTHING . RGX::GLUE . RGX::CATNR . "$/", $value, $matches)) {
			$contextItem->setRecommendationEntry("setLabel", trim($matches[1]), 1);
			$contextItem->setRecommendationEntry("setCatalogNr", trim($matches[2]), 1);
			return;
		}
	}
}
