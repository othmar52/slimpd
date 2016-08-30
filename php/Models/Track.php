<?php
namespace Slimpd\Models;

class Track extends \Slimpd\Models\AbstractTrack
{
	protected $artistId;
	protected $featuringId;
	protected $remixerId;
	protected $albumId;
	protected $genreId;
	protected $labelId;

	protected $disc;
	protected $transcoded;
	protected $lastScan;
	protected $isMixed;
	protected $dr;

	protected $discogsId;
	protected $rolldabeatsId;
	protected $beatportId;
	protected $junoId;

	public static $tableName = 'track';

	
	/**
	 * in case tracks have been added via playlist containing absolute paths that does not mpd-music dir try to fix the path...
	 */
	public static function getInstanceByPath($pathString, $createDummy = FALSE) {
		$altMusicDir = \Slim\Slim::getInstance()->config['mpd']['alternative_musicdir'];
		if(strlen($altMusicDir) > 0) {
			if(stripos($pathString, $altMusicDir) === 0) {
				$pathString = substr($pathString, strlen($altMusicDir));
			}
		}
		$instance = self::getInstanceByAttributes(
			array('relPathHash' => getFilePathHash($pathString))
		);
		if($instance !== NULL || $createDummy === FALSE) {
			return $instance;
		}
		// track is not imported in sliMpd database
		$track = new \Slimpd\Models\Track();
		$track->setRelPath($pathString);
		$track->setRelPathHash(getFilePathHash($pathString));
		return $track;
	}

	// TODO: move the whole guessing stuff to album migrator
	public function setFeaturedArtistsAndRemixers() {
		$artistBlacklist = \Slimpd\Models\Artist::getArtistBlacklist();

		$artistStringVanilla = $this->getArtistId();
		$titleStringVanilla = $this->getTitle();
		
		$regexArtist = "/,|&amp;|\ &\ |\ and\ |&|\ n\'\ |\ vs(.?)\ |\ versus\ |\ with\ |\ meets\ |\  w\/|\.and\.|\ aka\ |\ b2b\ |\//i";
		$regexRemix = "/(.*)\((.*)(\ remix|\ mix|\ rework|\ rmx|\ re-edit|\ re-lick|\ vip|\ remake)/i";
		$regexRemix2 = "/(.*)\((remix\ by\ |remixed\ by\ |remixedby\ )(.*)?\)/i";
		#$regexRemix = "/(.*)\(((.*)(\ remix|\ mix|\ rework|\ rmx))|(remixed\ by\ (.*))/i";
		
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
		$this->setArtistId(join(",", Artist::getIdsByString(join(" & ", $regularArtists))));

		$this->setFeaturingId('');
		$featuredArtists = $this->removeLeadingNumbers($featuredArtists);
		if(count($featuredArtists) > 0) { 
			$this->setFeaturingId(join(",", Artist::getIdsByString(join(" & ", $featuredArtists))));
		}

		$this->setRemixerId('');
		$remixerArtists = $this->removeLeadingNumbers($remixerArtists);
		if(count($remixerArtists) > 0) {
			$this->setRemixerId(join(",", Artist::getIdsByString(join(" & ", $remixerArtists))));
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
		return;
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
	



	# TODO: extract catNr from labelString
	public function setLabelAndCatalogueNr() {
		
	}
	
	public function jsonSerialize() {
		return get_object_vars($this);
	}


	
	//setter
	public function setArtistId($value) {
		$this->artistId = $value;
	}
	public function setFeaturingId($value) {
		$this->featuringId = $value;
	}
	public function setRemixerId($value) {
		$this->remixerId = $value;
	}
	

	// ...
	
	public function setDisc($value) {
		$this->disc = $value;
	}
	public function setAlbumId($value) {
		$this->albumId = $value;
	}
	public function setLabelId($value) {
		$this->labelId = $value;
	}
	public function setTranscoded($value) {
		$this->transcoded = $value;
	}
	public function setLastScan($value) {
		$this->lastScan = $value;
	}
	public function setGenreId($value) {
		$this->genreId = $value;
	}
	
	
	public function setIsMixed($value) {
		$this->isMixed = $value;
	}
	
	public function setDiscogsId($value) {
		$this->discogsId = $value;
	}
	public function setRolldabeatsId($value) {
		$this->rolldabeatsId = $value;
	}
	public function setBeatportId($value) {
		$this->beatportId = $value;
	}
	public function setJunoId($value) {
		$this->junoId = $value;
	}
	
	public function setDr($value) {
		$this->dr = $value;
	}
	
	
	
	
	// getter
	public function getArtistId() {
		return $this->artistId;
	}
	public function getFeaturingId() {
		return $this->featuringId;
	}
	public function getRemixerId() {
		return $this->remixerId;
	}

	// ...
	public function getDisc() {
		return $this->disc;
	}
	public function getAlbumId() {
		return $this->albumId;
	}
	public function getLabelId() {
		return $this->labelId;
	}
	
	public function getTranscoded() {
		return $this->transcoded;
	}
	public function getLastScan() {
		return $this->lastScan;
	}
	public function getGenreId() {
		return $this->genreId;
	}

	
	public function getIsMixed() {
		return $this->isMixed;
	}
	
	public function getDiscogsId() {
		return $this->discogsId;
	}
	public function getRolldabeatsId() {
		return $this->rolldabeatsId;
	}
	public function getBeatportId() {
		return $this->beatportId;
	}
	public function getJunoId() {
		return $this->junoId;
	}
	
	public function getDr() {
		return $this->dr;
	}
	
	
	
	/* Testdata for development/debuggung only
	
	#$artistString = "Ed Rush & Optical (Featuring Tali";
	#$artistString = "Swell Session & Berti Feat. Yukimi Nagano AND Adolf)";
	#$artistString = "Ed Rush & Optical ft Tali";
	
	#$titleString = "The Allenko Brotherhood Ensemble Tony Allen Vs Son Of Scientists (Featuring Eska) - The Drum";
	#$titleString = "Don't Die Just Yet (The Holiday Girl-Arab Strap Remix)";
	#$titleString = "Something About Love (Original Mix)";
	#$titleString = "Channel 7 (Sutekh 233 Channels Remix)";
	#$titleString = "Levels (Cazzette`s NYC Mode Radio Mix)";
	#$titleString = "fory one ways (david j & dj k bass bomber remix)";
	#$titleString = "Music For Bagpipes (V & Z Remix)";
	#$titleString = "Ballerino _Original Mix";
	#$titleString = "to ardent feat. nancy sinatra (horse meat disco remix)";
	#$titleString = "Crushed Ice (Remix)";
	#$titleString = "Hells Bells (Gino's & Snake Remix)";
	#$titleString = "Hot Love (DJ Koze 12 Inch Mix)";
	#$titleString = "Skeleton keys (Omni Trio remix";
	#$titleString = "A Perfect Lie (Theme Song)(Gabriel & Dresden Remix)";
	#$titleString = "Princess Of The Night (Meyerdierks Digital Exclusiv Remix)";
	#$titleString = "Hello Piano (DJ Chus And Sharp & Smooth Remix)";
	#$titleString = "De-Phazz feat. Pat Appleton / Mambo Craze [Extended Version]";
	#$titleString = "Toller titel (ft ftartist a) (RemixerA & RemixerB Remix)";
	#$titleString = "Toller titel (RemixerA & RemixerB Remix) (ft ftartist a)";
	#$titleString = "Been a Long Time (Axwell Unreleased Remix)";
	#$titleString = "Feel 2009 (Deepside Deejays Rework)";
	#$titleString = "Jeff And Jane Hudson - Los Alamos";
	
	#TODO: $titleString = "Movement (Remixed By Lapsed & Nonnon";
	#TODO: $artistString = "Lionel Richie With Little Big Town";
	#TODO: $artistString = "B-Tight Und Sido";
	#TODO: $artistString = "James Morrison (feat. Jessie J)" // check why featArt had not been extracted
	#TODO: $titleString = "Moving Higher (Muffler VIP Mix)"
	#TODO: $titleString = "Time To Remember - MAZTEK Remix"
	
	# common errors in extracted artists
	# Rob Mello No Ears Vocal
	# Robin Dubstep
	# Rob Hayes Club
	# Robert Owens Full Version
	# Robert Owens - Marcus Intalex remix
	# Robert Rivera Instrumental
	# Robert Owens Radio Edit
	# Rocky Nti - Chords Remix
	# Roc Slanga Album Versio
	# Rosanna Rocci with Michael Morgan
	# Bob Marley with Mc Lyte
	# AK1200 with MC Navigator
	# Marcus Intalex w/ MC Skibadee
	# Mixed By Krafty Kuts
	# Mixed by London Elektricity
	# VA Mixed By Juju
	# Va - Mixed By DJ Friction
	# Written By Patrick Neate
	# Charles Webster Main Vocal
	# Various
	# 01 - Bryan Gee
	# 03 - DJ Hazard
	# Andy C presents Ram Raiders
	# London Elektricity/Landslide
	# Brockie B2B Mampi Swift
	# Matty Bwoy (Sta remix)
	# Va - Womc - Disc 2
	# LTJ Bukem Presents
	# V.A.(Mixed By Nu:Tone f.SP MC)
	# 6Blocc_VS._R.A.W.
	# Goldie (VIP Mix)
	# Top Buzz Present Jungle Techno
	# VA (mixed by The Green Man)
	# VA (mixed by Tomkin
	# VA - Knowledge presents
	# VA - Subliminal Volume 1
	# Special Extended
	# Original 12-Inch Disco
	# Actually Extended
	# El Bosso meets the Skadiolas
	# Desmond Dekker/The Specials
	# Flora Paar - Yapacc rework - bonus track version
	# feat Flora Paar - Yapacc
	# Helena Majdeaniec i Czerwono Czarni
	# Maciej Kossowski i Czerwono Czarni
	# DJ Sinner - Original DJ
	# Victor Simonelli Presents Solution
	# Full Continous DJ
	# Robag Wruhme Mit Wighnomy Brothers Und Delhia
	# Sound W/ Paul St. Hilaire
	# (Thomas Krome Remix)
	# (Holy Ghost Remix)
	# D-Region Full Vocal

	
	#######################################################
	# special remixer Strings - currently ignored at all
	# TODO: try to extract relevant data
	#######################################################
	# Prins Thomas, Lindstrom
	# WWW.TRANCEDL.COM
	# James Zabiela/Guy J
	# With - D. Mark
	# Featuring - Kate's Propellers
	# Vocals - Mike Donovan
	# Recorded By [Field Recording] - David Franzke
	# Guitar - D. Meteo *
	# Remix - Burnt Friedman *
	# Double Bass - Akira Ando Saxophone - Elliott Levin
	# Piano - Kiwi Menrath
	# Featuring - MC Soom-T
	# Featuring - MC Soom-T Remix - Dabrye
	# Featuring - Lady Dragon, Sach
	# Electric Piano - Kiwi Menrath
	# Co-producer - Thomas Fehlmann
	# Producer - Steb Sly Vocals - Shawna Sulek
	# Edited By - Dixon
	# Producer [Additional] - Oren Gerlitz, Robot Koch Producer [Additional], Lyrics By [Additional] - Sasha Perera Remix - Jahcoozi
	# Bass [2nd] - Matt Saunderson
	# Producer [Additional], Remix - Glimmers, The
	# Remix, Producer [Additional] - Âme
	# Keyboards [Psyche Keys] - Daves W.
	# Co-producer - Usual Suspects, The (2)
	# Fancy Guitar Solo - Tobi Neumann
	# 4DJsonline.com
	# [WWW.HOUSESLBEAT.COM]
	# Backing Vocals - Clara Hill, Nadir Mansouri Mixed By - Axel Reinemer
	# Edited By - Dixon, Phonique Remix - DJ Spinna
	# Bass - Magic Number Clarinet [Bass], Flugelhorn, Flute, Trumpet, Arranged By [Horns] - Pete Wraight Remix - Atjazz Saxophone [Soprano] - Dave O'Higgins
	# Remix - Slapped Eyeballers, The Vocals - Kink Artifishul
	# Vocals [Featuring], Written By [Lyrics] - Rich Medina
	# RemixedBy: PTH Projects
	# AdditionalProductionBy: Simon Green
	# Simple Jack Remix, Amine Edge & DANCE Edit
	# http://toque-musicall.blogspot.com
	# Vocals [Featuring] - Laura Darlington
	# Mohammed Yousuf (Arranged By); Salim Akhtar (Bass); Salim Jaffery (Drums); Arif Bharoocha (Guitar); Mohammed Ali Rashid (Organ)
	# Eric Fernandes (Bass); Ahsan Sajjad (Drums, Lead Vocals); Norman Braganza (Lead Guitar); Fasahat Hussein Syed (Sitar, Keyboards, Tabla)
	# RemixedBy: Parov Stelar & Raul Irie/AdditionalProductionBy: Parov Stelar & Raul Irie
	# RemixedBy: Roland Schwarz & Parov Stelar
	# Engineer [Additional], Mixed By [Additional] - Tobi Neumann Vocals - Dinky
	# Vocals, Guitar - Stan Eknatz
	
	
	
	#$artistString = "Ed Rush & Optical (Featuring Tali";
	#$artistString = "Swell Session & Berti Feat. Yukimi Nagano AND Adolf)";
	#$artistString = "Ed Rush & Optical ft Tali";
	
	#$titleString = "The Allenko Brotherhood Ensemble Tony Allen Vs Son Of Scientists (Featuring Eska) - The Drum";
	#$titleString = "Don't Die Just Yet (The Holiday Girl-Arab Strap Remix)";
	#$titleString = "Something About Love (Original Mix)";
	#$titleString = "Channel 7 (Sutekh 233 Channels Remix)";
	#$titleString = "Levels (Cazzette`s NYC Mode Radio Mix)";
	#$titleString = "fory one ways (david j & dj k bass bomber remix)";
	#$titleString = "Music For Bagpipes (V & Z Remix)";
	#$titleString = "Ballerino _Original Mix";
	#$titleString = "to ardent feat. nancy sinatra (horse meat disco remix)";
	#$titleString = "Crushed Ice (Remix)";
	#$titleString = "Hells Bells (Gino's & Snake Remix)";
	#$titleString = "Hot Love (DJ Koze 12 Inch Mix)";
	#$titleString = "Skeleton keys (Omni Trio remix";
	#$titleString = "A Perfect Lie (Theme Song)(Gabriel & Dresden Remix)";
	#$titleString = "Princess Of The Night (Meyerdierks Digital Exclusiv Remix)";
	#$titleString = "Hello Piano (DJ Chus And Sharp & Smooth Remix)";
	#$titleString = "De-Phazz feat. Pat Appleton / Mambo Craze [Extended Version]";
	#$titleString = "Toller titel (ft ftartist a) (RemixerA & RemixerB Remix)";
	#$titleString = "Toller titel (RemixerA & RemixerB Remix) (ft ftartist a)";
	#$titleString = "Been a Long Time (Axwell Unreleased Remix)";
	#$titleString = "Feel 2009 (Deepside Deejays Rework)";
	#$titleString = "Jeff And Jane Hudson - Los Alamos";
	
	#TODO: $titleString = "Movement (Remixed By Lapsed & Nonnon";
	#TODO: $artistString = "Lionel Richie With Little Big Town";
	#TODO: $artistString = "B-Tight Und Sido";
	#TODO: $artistString = "James Morrison (feat. Jessie J)" // check why featArt had not been extracted
	#TODO: $titleString = "Moving Higher (Muffler VIP Mix)"
	#TODO: $titleString = "Time To Remember - MAZTEK Remix"
						
		
	 */
	private function getTestData($index) {
		$tests = array(
			array("placeholder index 0"),
			array("Stel", "Your Parents Are Here (Ian F. Remix)", "Stel_-_Your_Parents_Are_Here-(JLYLTD015)-WEB-2009-WiTF"),
			array("Shotgun Club", "Fake Fake", "08_shotgun_club-fake_fake.mp3", "Shotgun_Club-Love_Under_The_Gun-2011-FWYH"),
			array("Friction", "Long Gone Memory (Ft. Arlissa) (Ulterior Motive Remix)"),
			array("Friction", "Long Gone Memory Ft Arlissa & Gandalf Stein (Extended Mix)"),
			array("Friction", "Long Gone Memory with Arlissa & Gandalf Stein (Extended Mix"),
			array("Friction", "Long Gone Memory (Ulterior Motive Remix) [feat. Arlissa]"),
			array("Friction", "Long Gone Memory [feat. Arlissa] (Ulterior Motive Remix)"),
			array("Friction", "fory one ways (david j & dj k bass bomber remix)"),
			array("Ed Rush & Optical (Featuring Tali", "Hells Bells (Gino's & Snake Remix)"),
			array("James Morrison (feat. Jessie J)", "Hot Love (DJ Koze 12 Inch Mix)"),
			array("Swell Session & Berti Feat. Yukimi Nagano AND Adolf)", "Feel 2009 (Deepside Deejays Rework)"),
			array("DJ Hidden", "The Devil's Instant (DJ Hidden's Other Side Remix)"),
			array("DJ Hidden", "Movement (Remixed By Lapsed & Nonnon"),
			array("The Prototypes", "Rage Within (ft.Takura) (Instrumental Mix)"),
			array("Bebel Gilberto", "So Nice (DJ Marky Radio Edit)"),
			array("High Contrast", "Basement Track (High Contrasts Upstairs Downstairs Remix)"),
			array("Shy FX & T-Power", "Don't Wanna Know (Feat Di & Skibadee Dillinja Remix)"), // not solved yet
			array("L'Aventure Imaginaire", "L'Aventure Imaginaire - Athen"),  // not solved yet
			array("", "", "Apache_Indian-Chudi_jo_Khanki_Hath_o_Meri.mp3", "Apache_Indian"),  // not solved yet
			array("My mega artistname ft. Hansbert", "Time To Remember - MAZTEK Remix"),  // not solved yet
			array("", "", "05-Woody_Allen-Martha_(Aka_Mazie).mp3", "Woody_Allen_and_His_New_Orleans_Jazz_Band-Wild_Man_Blues_(1998_Film)"),
			array("Cutty Ranks", "Eye For An Eye f Wayne Marshal"), // should " f " be treated as "ft."?
			array("Henrik Schwarz / Ame / Dixon", "where we at (version 3)"),
			array("Henrik Schwarz /wDixon", "where we at (version 3)"),
			array("Jo-S", "Snicker - Original Mix"),
			array("SECT, The", "Tyrant"), // not solved yet ( "The" will be extracted as a separate artist-string)
			array("Machine Code", "Milk Plus (vs Broken Note)"), // not solved yet
			array("Torqux", "Blazin' (feat. Lady Leshurr) (Extended Club Mix)"),
			array("", "", "01_El_Todopoderoso.mp3", "FANIA_MASTERWORKS-HECTOR_LAVOE____LA_VOZ____(USA2009)___(320k)"),
			array("fela kuti", "03. gentleman - edit version", "03._gentleman-edit_version.mp3", "cd_1_(128k)"),
			array("Various", "John Beltran featuring Sol Set / Aztec Girl", "03-John_Beltran_featuring_Sol_Set___Aztec_Girl.mp3", "earthcd004--va-earth_vol_4-2000"),
			array("T Power","The Mutant Remix - Rollers Instinct (DJ Trace remix)"),
			array("The Vision","Back In The Days (ft.Buggsy & Shadz & Scarz & Double KHK-SP)"),
			array("Crystal Clear", "No Sell Out (Xample & Sol Remi", "a_crystal_clear-no_sell_out_(xample_and_sol_remix).mp3", "iap001--industry_artists-crystal_clear_vs_xample_and_sol-iap001-vinyl-2005-obc"),
			array("Infuze", "Black Out (with TADT)"), // not solved yet
			
		);
		
		if(isset($tests[$index]) === TRUE) {
			return $tests[$index];
		}
		return FALSE;
	}
}
