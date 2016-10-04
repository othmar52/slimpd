<?php
namespace Slimpd\Modules\albummigrator;
use \Slimpd\Models\Artist;
use \Slimpd\RegexHelper as RGX;
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

class TrackContext extends \Slimpd\Models\Track {
	use \Slimpd\Modules\albummigrator\MigratorContext; // config
	protected $confKey = "track-tag-mapping-";
	
	// those attributes holds string values (track holds relational Uids)
	protected $album;
	protected $remixArtists;
	protected $featuredArtists;
	
	public $idx;
	
	public $mostScored;
	protected $totalTracks;
	protected $audioBitrateMode;
	
	public function __construct($rawTagArray, $idx, $config) {
		$this->config = $config;
		$this->idx = $idx;
		$this->rawTagRecord = $rawTagArray;
		$this->rawTagArray = unserialize($rawTagArray['tagData']);
		$this->process();
	}
	
	private function process() {
		$this->copyBaseProperties();
		$this->configBasedSetters();
		$this->postProcessAudioProperties();
	}

	private function postProcessAudioProperties() {
		// convert decimal-seconds to miliseconds
		$this->setMiliseconds(round($this->getMiliseconds()*1000));

		// default value for audio-encoder
		if(!$this->getAudioEncoder()) {
			$this->setAudioEncoder('Unknown encoder');
		}

		// default value for audio-bits-per-sample
		if(!$this->getAudioBitsPerSample()) {
			$this->setAudioBitsPerSample(16);
		}

		// default value for audio-sample-rate
		if(!$this->getAudioSampleRate()) {
			$this->setAudioSampleRate(44100);
		}

		// default value for audio-channels
		if(!$this->getAudioChannels()) {
			$this->setAudioChannels(2);
		}

		if($this->getAudioLossless()) {
			$this->setAudioProfile('Lossless compression');
			if($this->getAudioComprRatio() === "1") {
				$this->setAudioProfile('Lossless');
			}
		}

		if(!$this->getAudioProfile()) {
			$this->setAudioProfile(
				$this->getAudioBitrateMode() . " " . round($this->getAudioBitrate()/ 1000, 1) . " kbps"
			);
		}

		// integer in database
		$this->setAudioBitrate(round($this->getAudioBitrate()));

		// override description of audiocodec
		// @see: https://github.com/othmar52/slimpd/issues/25
		// @see: https://github.com/JamesHeinrich/getID3/issues/48
		$audioCodec = $this->extractTagString(
			recursiveArrayParser(
				["audio", "codec"],
				$this->rawTagArray
			)
		);
		if($this->rawTagRecord['extension'] === 'm4a' && $audioCodec === "Apple Lossless Audio Codec") {
			$this->setMimeType('audio/aac');
			$this->setAudioDataformat('aac');
		}
	}

	/**
	 * most rawTagData-fields are identical to track fields 
	 */
	private function copyBaseProperties() {
		$this->setUid($this->rawTagRecord['uid'])
			->setRelPath($this->rawTagRecord['relPath'])
			->setRelPathHash($this->rawTagRecord['relPathHash'])
			->setRelDirPath($this->rawTagRecord['relDirPath'])
			->setRelDirPathHash($this->rawTagRecord['relDirPathHash'])
			#->setAdded($this->rawTagRecord['added'])
			->setFilesize($this->rawTagRecord['filesize'])
			->setFilemtime($this->rawTagRecord['filemtime'])
			->setLastScan($this->rawTagRecord['lastScan'])
			->setImportStatus($this->rawTagRecord['importStatus'])
			->setFingerprint($this->rawTagRecord['fingerprint'])
			->setError($this->rawTagRecord['error']);
	}
	
	public function initScorer(&$albumContext, $jumbleJudge) {
		foreach($jumbleJudge->tests as $tests) {
			$tests[$this->idx]->scoreMatches($this, $albumContext, $jumbleJudge);
		}
	}
	
	public function postProcessProperties() {
		$this->setArtist($this->getMostScored('setArtist'));
		$this->setTitle($this->getMostScored('setTitle'));
		$this->setAlbum($this->getMostScored('setAlbum'));
		$this->setGenre($this->getMostScored('setGenre'));
		$this->setLabel($this->getMostScored('setLabel'));
		$this->setYear($this->getMostScored('setYear'));
		$this->setTrackNumber($this->getMostScored('setTrackNumber'));
	}

	/**
	 * copy all attributes from TrackContext-instance to new Track->instance
	 */
	public function getTrackInstanceByContext() {
		$track = new \Slimpd\Models\Track();
		$self = new \ReflectionClass("\Slimpd\Models\Track");
		foreach($self->getMethods() as $method) {
			
			if(preg_match("/^set/", $method->name) === 0) {
				continue;
			}
			
			// TODO: remove this setter from Track at all
			if($method->name === "setRelDirPath") {
				continue;
			}
			$setter = $method->name;
			$getter = "g" . substr($setter, 1);
			if(method_exists($this, $getter) === FALSE) {
				continue;
			}
			$track->$setter($this->$getter());
		}
		return $track;
	}

	public function migrate() {
		# setFeaturedArtistsAndRemixers() is processing:
			# $t->setArtistUid();
			# $t->setFeaturingUid();
			# $t->setRemixerUid();
		$this->setFeaturedArtistsAndRemixers()
			->setLabelUid( join(",", \Slimpd\Models\Label::getUidsByString($this->getLabel())))
			->setGenreUid( join(",", \Slimpd\Models\Genre::getUidsByString($this->getGenre())));
			
		$track = $this->getTrackInstanceByContext();
		

		\Slimpd\Models\Track::ensureRecordUidExists($track->getUid());
		$track->update();
		
		// add the whole bunch of valid and indvalid attributes to trackindex table
		$this->updateTrackIndex();
	}
	
	private function updateTrackIndex() {
		$indexChunks = $this->getRelPath() . " " .
			str_replace(
				array('/', '_', '-', '.'),
				' ',
				$this->getRelPath()
			)
			. " " . join(" ", $this->getAllRecommendations("setArtist"))
			. " " . join(" ", $this->getAllRecommendations("setTitle"))
			. " " . join(" ", $this->getAllRecommendations("setAlbum"))
			. " " . join(" ", $this->getAllRecommendations("setYear"))
			. " " . join(" ", $this->getAllRecommendations("setGenre"))
			. " " . join(" ", $this->getAllRecommendations("setLabel"))
			. " " . join(" ", $this->getAllRecommendations("setFingerprint"))
			. " " . join(" ", $this->getAllRecommendations("setCatalogNr"));
		// TODO: add all recomendations and other missing attributes

		// make sure to use identical uids in table:trackindex and table:track
		\Slimpd\Models\Trackindex::ensureRecordUidExists($this->getUid());
		$trackIndex = new \Slimpd\Models\Trackindex();
		$trackIndex->setUid($this->getUid())
			->setArtist($this->getArtist())
			->setTitle($this->getTitle()) // TODO: we need vanilla title
			->setAllchunks($indexChunks)
			->update();
	}

	public function setAlbum($value) {
		$this->album = $value;
		return $this;
	}
	public function getAlbum() {
		return $this->album;
	}
	public function setRemixArtists($value) {
		$this->remixArtists = $value;
		return $this;
	}
	public function getRemixArtists() {
		return $this->remixArtists;
	}
	public function setFeaturedArtists($value) {
		$this->featuredArtists = $value;
		return $this;
	}
	public function getFeaturedArtists() {
		return $this->featuredArtists;
	}
	public function setTotalTracks($value) {
		$this->totalTracks = $value;
		return $this;
	}
	public function getTotalTracks() {
		return $this->totalTracks;
	}
	public function setAudioBitrateMode($value) {
		$this->audioBitrateMode = $value;
		return $this;
	}
	public function getAudioBitrateMode() {
		return $this->audioBitrateMode;
	}
	
	
	

	// TODO: refacture!!!
	// TODO: pretty sure getzFeaturedArtist() from id3 tags is currently not used at all
	public function setFeaturedArtistsAndRemixers() {
		$artistBlacklist = \Slimpd\Models\Artist::getArtistBlacklist();

		$artistStringVanilla = $this->getArtist();
		$titleStringVanilla = $this->getTitle();
		
		$regexArtist = "/".RGX::ARTIST_GLUE."/i";
		$regexRemix = "/" . RGX::REMIX1 . "/i";
		$regexRemix2 = "/" . RGX::REMIX2 . "/i";
		
		$regularArtists = array();
		$featuredArtists = array();
		$remixerArtists = array();
		$titlePattern = '';
		
		$performTest = 0;
		if($performTest>0) {
			$testData = $this->getTestData($performTest);
			$artistStringVanilla = $testData[0];
			$titleStringVanilla = $testData[1];
		}

		$artistString = $artistStringVanilla;
		$titleString = $titleStringVanilla;
		
		
		// in case we dont have artist nor title string take the filename as a basis
		if($artistString == "" && $titleString == "") {
			if($this->getRelPath() !== "") {
				$titleString = preg_replace('/\\.[^.\\s]{3,4}$/', '', basename($this->getRelPath()));
				$titleString = str_replace("_", " ", $titleString);
			}
		}

		// in case artist string is missing try to get it from title string
		if($artistString == "" && $titleString != "") {
			$tmp = trimExplode(" - ", $titleString, TRUE, 2);
			if(count($tmp) == 2) {
				$artistString = $tmp[0];
				$titleString = $tmp[1];
			}
		}

		// in case title string is missing try to get it from artist string
		if($artistString != "" && $titleString == "") {
			$tmp = trimExplode(" - ", $artistString, TRUE, 2);
			if(count($tmp) == 2) {
				$artistString = $tmp[0];
				$titleString = $tmp[1];
			}
		}

		$artistString = flattenWhitespace(unifyBraces($artistString));
		$titleString = flattenWhitespace(unifyBraces($titleString));

		// assign all string-parts to category
		$groupFeat = "([\ \(])(featuring|ft(?:.?)|feat(?:.?))\ ";
		$groupFeat2 = "([\ \(\.])(feat\.|ft\.|f\.)"; // without trailing whitespace

		# TODO: verify that this unused variable $groupGlue can be deleted and remove this line
		#$groupGlue = "/&amp;|\ &\ |\ and\ |&|\ n\'\ |\ vs(.?)\ |\ versus\ |\ with\ |\ meets\ |\  w\/\ /i";

		if($artistString == "") {
			$regularArtists[] = "Unknown Artist";
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
			$featuredArtists = array_merge($featuredArtists, preg_split($regexArtist, $sFeat));
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
			$featuredArtists = array_merge($featuredArtists, preg_split($regexArtist, $sFeat));
		}
		
		$regularArtists = array_merge($regularArtists, preg_split($regexArtist, $artistString));
		
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
				$featuredArtists = array_merge($featuredArtists, preg_split($regexArtist, $sFeat));
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
				$featuredArtists = array_merge($featuredArtists, preg_split($regexArtist, $sFeat));
			}
		}

		// parse title string for remixer regex 1
		if(preg_match($regexRemix, $titleString, $matches)) {
			$remixerArtists = array_merge($remixerArtists, preg_split($regexArtist, $matches[2]));
		}
		// parse title string for remixer regex 1
		if(preg_match($regexRemix2, $titleString, $matches)) {
			#print_r($matches); die();
			$remixerArtists = array_merge($remixerArtists, preg_split($regexArtist, $matches[3]));
		}
		
		// clean up extracted remixer-names with common strings
		$tmp = array();
		foreach($remixerArtists as $remixerArtist) {
			if(isset($artistBlacklist[ strtolower($remixerArtist)]) === TRUE) {
				continue;
			}
			$tmp[] = str_ireplace($artistBlacklist, "", $remixerArtist);
		}
		$remixerArtists = $tmp;
		
		
		// clean up extracted featuring-names with common strings
		$tmp = array();
		foreach($featuredArtists as $featuredArtist) {
			if(isset($artistBlacklist[ strtolower($featuredArtist)] ) === TRUE) {
				continue;
			}
			$tmp[] = str_ireplace($artistBlacklist, "", $featuredArtist);
		}
		$featuredArtists = $tmp;
		
		
		$regularArtists = array_unique(array_filter($regularArtists));
		$featuredArtists = array_unique($featuredArtists);
		$remixerArtists = array_unique($remixerArtists);
		
		// to avoid incomplete substitution caused by partly artistname-matches sort array by length DESC
		$allArtists = array_merge($regularArtists, $featuredArtists, $remixerArtists);
		usort($allArtists,'sortHelper');
		$titlePattern = str_ireplace($allArtists, "%s", $titleString);
		

		// remove possible brackets from featuredArtists
		#$tmp = array();
		#foreach($featuredArtists as $featuredArtist) {
		#	$tmp[] = str_replace(array("(", ")"), "", $featuredArtist);
		#}
		#$featuredArtists = $tmp;
		
		if(substr_count($titlePattern, "%s") !== count($remixerArtists)) {
			// oh no - we have a problem
			// reset extracted remixers
			$titlePattern = $titleString;
			$remixerArtists = array();
		}
		
		/* TODO: do we need this?
		// remove " (" from titlepattern in case that are the last 2 chars
		if(preg_match("/(.*)\ \($/", $titlePattern, $matches)) {
			$titlePattern = $matches[1];
		}
		*/
		
		// clean up artist names
		// unfortunately there are artist names like "45 Thieves"
		$regularArtists = $this->removeLeadingNumbers($regularArtists);
		$this->setArtistUid(join(",", Artist::getUidsByString(join(" & ", $regularArtists))));

		$this->setFeaturingUid('');
		$featuredArtists = $this->removeLeadingNumbers($featuredArtists);
		if(count($featuredArtists) > 0) { 
			$this->setFeaturingUid(join(",", Artist::getUidsByString(join(" & ", $featuredArtists))));
		}

		$this->setRemixerUid('');
		$remixerArtists = $this->removeLeadingNumbers($remixerArtists);
		if(count($remixerArtists) > 0) {
			$this->setRemixerUid(join(",", Artist::getUidsByString(join(" & ", $remixerArtists))));
		}
		
		// replace multiple whitespace with a single whitespace
		$titlePattern = preg_replace('!\s+!', ' ', $titlePattern);
		// remove whitespace before bracket
		$titlePattern = str_replace(' )', ')', $titlePattern);
		$this->setTitle($titlePattern);
		
		if($performTest > 0) {
			cliLog("----------ARTIST-PARSER---------", 1, "purple");
			cliLog(" inputArtist: " . $artistStringVanilla);
			cliLog(" inputTitle: " . $titleStringVanilla);
			cliLog(" regular: " . print_r($regularArtists,1));
			cliLog(" feat: " . print_r($featuredArtists,1));
			cliLog(" remixer: " . print_r($remixerArtists,1));
			cliLog(" titlePattern: " . $titlePattern);
			\Slim\Slim::getInstance()->stop();
		}
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
