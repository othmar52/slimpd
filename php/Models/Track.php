<?php
namespace Slimpd\Models;

class Track extends \Slimpd\Models\AbstractModel
{
	protected $id;
	protected $title;
	
	protected $artistId;
	protected $featuringId;
	protected $remixerId;
	protected $albumId;
	protected $genreId;
	protected $labelId;
	protected $catalogNr;
	
	protected $relativePath;
	protected $relativePathHash;
	protected $directoryPathHash;
	
	protected $fingerprint;
	protected $mimeType;
	protected $filesize;
	protected $filemtime;
	protected $miliseconds;
	protected $audioBitrate;
	// ...
	protected $audioBitsPerSample;
	protected $audioSampleRate;
	protected $audioChannels;
	protected $audioLossless;
	protected $audioCompressionRatio;
	protected $audioDataformat;
	protected $audioEncoder;
	protected $audioProfile;
	
	protected $videoDataformat;
	protected $videoCodec;
	protected $videoResolutionX;
	protected $videoResolutionY;
	protected $videoFramerate;
	// ...
	protected $disc;
	protected $number;
	protected $error;
	protected $transcoded;
	
	protected $importStatus;
	protected $lastScan;
	
	protected $comment;
	protected $year;
	protected $isMixed;
	
	protected $discogsId;
	protected $rolldabeatsId;
	protected $beatportId;
	protected $junoId;
	
	
	protected $dr;
	
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
			array('relativePathHash' => getFilePathHash($pathString))
		);
		if($instance !== NULL || $createDummy === FALSE) {
			return $instance;
		}
		// track is not imported in sliMpd database
		$track = new \Slimpd\Models\Track();
		$track->setRelativePath($pathString);
		$track->setRelativePathHash(getFilePathHash($pathString));
		return $track;
	}
		
	public function setFeaturedArtistsAndRemixers() {
		$artistBlacklist = \Slimpd\Models\Artist::getArtistBlacklist();

		$inputArtistString = $this->getArtistId();
		$inputTitleString = $this->getTitle();
		
		
		
		$regexFeat = "/([\ \(\[])featuring\ |([\ \(\[])ft(.?)\ |([\ \(\[])feat(.?)\ |([\ \(\[])ft\.|([\ \(\[])feat\./i";
		$regexArtist = "/,|&amp;|\ &\ |\ and\ |&|\ n\'\ |\ vs(.?)\ |\ versus\ |\ with\ |\ meets\ |\  w\/|\.and\.|\ aka\ |\ b2b\ |\//i";
		$regexRemix = "/(.*)\((.*)(\ remix|\ mix|\ rework|\ rmx|\ re-edit|\ re-lick|\ vip|\ remake)/i";
		$regexRemix2 = "/(.*)\((remix\ by\ |remixed\ by\ |remixedby\ )(.*)?\)/i";
		#$regexRemix = "/(.*)\(((.*)(\ remix|\ mix|\ rework|\ rmx))|(remixed\ by\ (.*))/i";
		
		$regularArtists = array();
		$featuredArtists = array();
		$remixerArtists = array();
		$titlePattern = '';
		
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
		
		
		/*
		 * for development/debuggung only
		 */
		/*
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

		$performTest = 0;
		if($performTest>0) {
			$inputArtistString = $tests[$performTest][0];
			$inputTitleString = $tests[$performTest][1];
			if(isset($tests[$performTest][2])) {
				$inputFileName = $tests[$performTest][2];
			}
			if(isset($tests[$performTest][3])) {
				$inputDirectoryName = $tests[$performTest][3];
			}
		}
		*/
		
		$artistString = $inputArtistString;
		$titleString = $inputTitleString;
		
		
		// in case we dont have artist nor title string take the filename as a basis
		if($artistString == "" && $titleString == "") {
			if($this->getRelativePath() !== "") {
				$titleString = preg_replace('/\\.[^.\\s]{3,4}$/', '', basename($this->getRelativePath()));
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
		$artistString = str_replace(array("[", "]"), array("(", ")"), $artistString);
		$titleString = str_replace(array("[", "]"), array("(", ")"), $titleString);
		
		// replace multiple whitespace with a single whitespace
		$artistString = preg_replace('!\s+!', ' ', $artistString);
		$titleString = preg_replace('!\s+!', ' ', $titleString);
		
		
		
		// assign all string-parts to category
		$groupFeat = "([\ \(])(featuring|ft(?:.?)|feat(?:.?))\ ";
		$groupFeat2 = "([\ \(\.])(feat\.|ft\.|f\.)"; // without trailing whitespace

		# TODO: verify that this unused variable $groupGlue can be deleted and remove this line
		#$groupGlue = "/&amp;|\ &\ |\ and\ |&|\ n\'\ |\ vs(.?)\ |\ versus\ |\ with\ |\ meets\ |\  w\/\ /i";

		if($artistString == "") {
			$regularArtists[] = "Unknown Artist";
		}
		
		// parse ARTIST string for featured artists REGEX 1
		if(preg_match("/(.*)" . $groupFeat . "([^\(]*)(.*)$/i", $artistString, $m)) {
			$sFeat = trim($m[4]);
			if(substr($sFeat, -1) == ')') {
				$sFeat = substr($sFeat,0,-1);
			}
			$artistString = str_replace(
				$m[2] .$m[3] . ' ' . $m[4],
				" ",
				$artistString
			);
			$featuredArtists = array_merge($featuredArtists, preg_split($regexArtist, $sFeat));
		}
		// parse ARTIST string for featured artists REGEX 2
		if(preg_match("/(.*)" . $groupFeat2 . "([^\(]*)(.*)$/i", $artistString, $m)) {
			$sFeat = trim($m[4]);
			if(substr($sFeat, -1) == ')') {
				$sFeat = substr($sFeat,0,-1);
			}
			$artistString = str_replace(
				$m[2] .$m[3] . $m[4],
				" ",
				$artistString
			);
			$featuredArtists = array_merge($featuredArtists, preg_split($regexArtist, $sFeat));
		}
		
		$regularArtists = array_merge($regularArtists, preg_split($regexArtist, $artistString));
		
		// parse TITLE string for featured artists REGEX 1
		if(preg_match("/(.*)" . $groupFeat . "([^\(]*)(.*)$/i", $titleString, $m)) {
			$sFeat = trim($m[4]);
			if(substr($sFeat, -1) == ')') {
				$sFeat = substr($sFeat,0,-1);
			}
			
			if(isset($artistBlacklist[strtolower($sFeat)]) === FALSE) {
				$titleString = str_replace(
					$m[2] .$m[3] . ' ' . $m[4],
					" ",
					$titleString
				);
				$featuredArtists = array_merge($featuredArtists, preg_split($regexArtist, $sFeat));
			}
		}
		
		// parse TITLE string for featured artists REGEX 2
		if(preg_match("/(.*)" . $groupFeat2 . "([^\(]*)(.*)$/i", $titleString, $m)) {
			#print_r($m); die();
			$sFeat = trim($m[4]);
			if(substr($sFeat, -1) == ')') {
				$sFeat = substr($sFeat,0,-1);
			}
			if(isset($artistBlacklist[strtolower($sFeat)]) === FALSE) {
				$titleString = str_replace(
					$m[2] .$m[3] . $m[4],
					" ",
					$titleString
				);
				$featuredArtists = array_merge($featuredArtists, preg_split($regexArtist, $sFeat));
			}
		}

		// parse title string for remixer regex 1
		if(preg_match($regexRemix, $titleString, $m)) {
			$remixerArtists = array_merge($remixerArtists, preg_split($regexArtist, $m[2]));
		}
		// parse title string for remixer regex 1
		if(preg_match($regexRemix2, $titleString, $m)) {
			#print_r($m); die();
			$remixerArtists = array_merge($remixerArtists, preg_split($regexArtist, $m[3]));
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
		if(preg_match("/(.*)\ \($/", $titlePattern, $m)) {
			$titlePattern = $m[1];
		}
		*/
		
		// clean up artist names
		// unfortunately there are artist names like "45 Thieves"
		$regularArtists = $this->removeLeadingNumbers($regularArtists);
		$featuredArtists = $this->removeLeadingNumbers($featuredArtists);
		$remixerArtists = $this->removeLeadingNumbers($remixerArtists);
		
		
		$this->setArtistId(join(",", Artist::getIdsByString(join(" & ", $regularArtists))));
		if(count($featuredArtists) > 0) { 
			$this->setFeaturingId(join(",", Artist::getIdsByString(join(" & ", $featuredArtists))));
		} else {
			# TODO: currently empty values are ignored in AbstractModel::update()
			# this is relevant for old exisiting database-items which already have an invalid featuringId-value 
			$this->setFeaturingId('');
		}
		
		if(count($remixerArtists) > 0) {
			$this->setRemixerId(join(",", Artist::getIdsByString(join(" & ", $remixerArtists))));
		} else {
			# TODO: currently empty values are ignored in AbstractModel::update()
			# this is relevant for old exisiting database-items which already have an invalid remixerId-value
			$this->setRemixerId('');
		}
		
		// replace multiple whitespace with a single whitespace
		$titlePattern = preg_replace('!\s+!', ' ', $titlePattern);
		// remove whitespace before bracket
		$titlePattern = str_replace(' )', ')', $titlePattern);
		
		
		$this->setTitle($titlePattern);
		return;
		
		echo "inputArtist: " . $inputArtistString . "\n";
		echo "inputTitle: " . $inputTitleString . "\n";
		echo "regular: " . print_r($regularArtists,1);
		echo "feat: " . print_r($featuredArtists,1);
		echo "remixer: " . print_r($remixerArtists,1);
		echo "titlePattern: " . $titlePattern . "\n";
		die();
		
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
				if(preg_match("/^([\d]{2})([\.\-\ ]{1,3})([^\d]{1})(.*)$/", $string, $m)) {
					if($m[1] < 21) {
						#print_r($m); die();
						$string = $m[3].$m[4];
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

	public static function ensureRecordIdExists($id) {
		$db = \Slim\Slim::getInstance()->db;
		if($db->query("SELECT id FROM " . self::$tableName . " WHERE id=" . (int)$id)->num_rows == $id) {
			return;
		}
		$db->query("INSERT INTO " . self::$tableName . " (id) VALUES (".(int)$id.")");
		return;
	}
	
	public function jsonSerialize() {
		return get_object_vars($this);
	}


	
	//setter
	public function setId($value) {
		$this->id = $value;
	}
	public function setArtistId($value) {
		$this->artistId = $value;
	}
	public function setFeaturingId($value) {
		$this->featuringId = $value;
	}
	public function setTitle($value) {
		$this->title = $value;
	}
	public function setRemixerId($value) {
		$this->remixerId = $value;
	}
	public function setRelativePath($value) {
		$this->relativePath = $value;
	}
	public function setRelativePathHash($value) {
		$this->relativePathHash = $value;
	}
	public function setDirectoryPathHash($value) {
		$this->directoryPathHash = $value;
	}
	
	public function setFingerprint($value) {
		$this->fingerprint = $value;
	}
	public function setMimeType($value) {
		$this->mimeType = $value;
	}
	public function setFilesize($value) {
		$this->filesize = $value;
	}
	public function setFilemtime($value) {
		$this->filemtime = $value;
	}
	public function setMiliseconds($value) {
		$this->miliseconds = $value;
	}
	public function setAudioBitrate($value) {
		$this->audioBitrate = $value;
	}
	// ...
	public function setAudioBitsPerSample($value) {
		$this->audioBitsPerSample = $value;
	}
	public function setAudioSampleRate($value) {
		$this->audioSampleRate = $value;
	}
	public function setAudioChannels($value) {
		$this->audioChannels = $value;
	}
	public function setAudioLossless($value) {
		$this->audioLossless = $value;
	}
	public function setAudioCompressionRatio($value) {
		$this->audioCompressionRatio = $value;
	}
	public function setAudioDataformat($value) {
		$this->audioDataformat = $value;
	}
	public function setAudioEncoder($value) {
		$this->audioEncoder = $value;
	}
	public function setAudioProfile($value) {
		$this->audioProfile = $value;
	}
	public function setVideoDataformat($value) {
		$this->videoDataformat = $value;
	}
	public function setVideoCodec($value) {
		$this->videoCodec = $value;
	}
	public function setVideoResolutionX($value) {
		$this->videoResolutionX = $value;
	}
	public function setVideoResolutionY($value) {
		$this->videoResolutionY = $value;
	}
	public function setVideoFramerate($value) {
		$this->videoFramerate = $value;
	}
	// ...
	
	public function setDisc($value) {
		$this->disc = $value;
	}
	public function setNumber($value) {
		$this->number = $value;
	}
	public function setError($value) {
		$this->error = $value;
	}
	public function setAlbumId($value) {
		$this->albumId = $value;
	}
	public function setLabelId($value) {
		$this->labelId = $value;
	}
	public function setCatalogNr($value) {
		$this->catalogNr = $value;
	}
	public function setTranscoded($value) {
		$this->transcoded = $value;
	}
	public function setImportStatus($value) {
		$this->importStatus = $value;
	}
	public function setLastScan($value) {
		$this->lastScan = $value;
	}
	public function setGenre($value) {
		$this->genre = $value;
	}
	public function setGenreId($value) {
		$this->genreId = $value;
	}
	public function setComment($value) {
		$this->comment = $value;
	}
	public function setYear($value) {
		$this->year = $value;
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
	public function getId() {
		return $this->id;
	}
	public function getArtistId() {
		return $this->artistId;
	}
	public function getFeaturingId() {
		return $this->featuringId;
	}
	public function getTitle() {
		return $this->title;
	}
	public function getRemixerId() {
		return $this->remixerId;
	}
	public function getRelativePath() {
		return $this->relativePath;
	}
	public function getRelativePathHash() {
		return $this->relativePathHash;
	}
	public function getDirectoryPathHash() {
		return $this->directoryPathHash;
	}
	
	public function getFingerprint() {
		return $this->fingerprint;
	}
	public function getMimeType() {
		return $this->mimeType;
	}
	public function getFilesize() {
		return $this->filesize;
	}
	public function getFilemtime() {
		return $this->filemtime;
	}
	public function getMiliseconds() {
		return $this->miliseconds;
	}
	public function getAudioBitrate() {
		return $this->audioBitrate;
	}
	// ...
	public function getAudioBitsPerSample() {
		return $this->audioBitsPerSample;
	}
	public function getAudioSampleRate() {
		return $this->audioSampleRate;
	}
	public function getAudioChannels() {
		return $this->audioChannels;
	}
	public function getAudioLossless() {
		return $this->audioLossless;
	}
	public function getAudioCompressionRatio() {
		return $this->audioCompressionRatio;
	}
	public function getAudioDataformat() {
		return $this->audioDataformat;
	}
	public function getAudioEncoder() {
		return $this->audioEncoder;
	}
	public function getAudioProfile() {
		return $this->audioProfile;
	}
	
	public function getVideoDataformat() {
		return $this->videoDataformat;
	}
	public function getVideoCodec() {
		return $this->videoCodec;
	}
	public function getVideoResolutionX() {
		return $this->videoResolutionX;
	}
	public function getVideoResolutionY() {
		return $this->videoResolutionY;
	}
	public function getVideoFramerate() {
		return $this->videoFramerate;
	}
	// ...
	public function getDisc() {
		return $this->disc;
	}
	public function getNumber() {
		return $this->number;
	}
	public function getError() {
		return $this->error;
	}
	public function getAlbumId() {
		return $this->albumId;
	}
	public function getLabelId() {
		return $this->labelId;
	}
	public function getCatalogNr() {
		return $this->catalogNr;
	}
	
	public function getTranscoded() {
		return $this->transcoded;
	}
	public function getImportStatus() {
		return $this->importStatus;
	}
	public function getLastScan() {
		return $this->lastScan;
	}
	public function getGenre() {
		return $this->genre;
	}
	public function getGenreId() {
		return $this->genreId;
	}
	public function getComment() {
		return $this->comment;
	}
	public function getYear() {
		return $this->year;
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
}
