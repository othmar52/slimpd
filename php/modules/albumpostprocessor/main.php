<?php
namespace Slimpd;

class AlbumPostProcessor {
	protected $albumHash;
	protected $album;
	protected $tracks;
	
	
	protected $handleAsAlbum;
	protected $handleAsAlbumScore;
	
	
	protected $albumTitles;
	protected $artists;
	protected $genres;
	protected $labels;
	
	protected $years;
	protected $bitrates;
	protected $audioFormats;
	protected $comments;
	
	
	protected $filenames;
	protected $titles;
	protected $numbers;
	
	
	protected $filenameCases;
	protected $filenameSchemes;
	protected $titleSchemes;
	protected $numberSchemes;
	
	
	public function __construct() {
		$this->reset();
	}
	
	public function run() {
		// first of all - try to guess if this dir should be
		// treated as an album or as a bunch of loose tracks
		$this->setHandleAsAlbum();
		return;
		print_r($this); die();
	}
	
	public function reset() {
		foreach ($this as &$value) {
		    $value = array();
		}
		$this->albumHash = '';
		$this->album = NULL;
		$this->handleAsAlbum = NULL;
		$this->handleAsAlbumScore = 0;
	}
	
	
	private function setHandleAsAlbum() {
		
		#TODO: case of filename
		
		// collect specific data 
		foreach($this->tracks as $t) {
			foreach(trimExplode(",", $t->getArtistId()) as $artistId) {
				$this->artists[] = $artistId;
			}
			foreach(trimExplode(",", $t->getGenreId()) as $genreId) {
				$this->genres[] = $genreId;
			}
			foreach(trimExplode(",", $t->getLabelId()) as $labelId) {
				$this->labels[] = $labelId;
			}
			$this->years[] = $t->getYear();
			$this->bitrates[] = intval($t->getAudioBitrate());
			$this->audioFormats[] = $t->getAudioDataformat();
			$this->comments[] = $t->getComment();
			
			$this->filenameCases[] = $this->getFilenameCase( basename($t->getRelativePath()) );
			$this->filenames[] = basename($t->getRelativePath());
			$this->titles[] = $t->getTitle();
			$this->numbers[] = $t->getNumber();
			
			$this->filenameSchemes[] = $this->getFilenameScheme( basename($t->getRelativePath()) );
			#$this->titleScheme[] = $t->getTitleScheme();
			$this->numberSchemes[] = $this->getNumberScheme($t->getNumber());
		}
		
		// check similarity of collected data
		$trackCount = count($this->tracks);
		
		// TODO: check if we should move this to top of method...
		if($trackCount == 1) {
			$this->handleAsAlbum = TRUE;
			return;
		}
		
		# define some weightening
		# TODO: testing, testing, testing - no idea if those values makes sense
		
		# TODO: check if we find gapless chronological tracknumbers (in attributes: number or artist or title or filename)
		# case yes -> generously add score
		$scoreTable = array(
			'artists' => 0.7,
			'labels' => 1,
			'years' => 1.5,
			'audioFormats' => 0.2,
			'comments' => 1,
			// TODO: titleScheme
			'filenameSchemes' => 3,
			'filenameCases' => 2,
			'numberSchemes' => 2
		);
		$decisionBoundry = 5;
		
		foreach(array_keys($scoreTable) as $property) {
			$bestMatch = uniqueArrayOrderedByRelevance($this->$property)[0];
			#cliLog("bestmatch" . $bestMatch , 1, 'purple'); #die();
			$propScore = $scoreTable[$property];
			foreach($this->$property as $i) {
				// does it make sense to exclude missing attributes from scoring?
				#if($i == '') {
				#	#continue;
				#}
				$this->handleAsAlbumScore += ($i == $bestMatch) ? $propScore : $propScore*-1;
			}
		}
		
		$this->handleAsAlbumScore /= $trackCount;
		$this->handleAsAlbum = ($this->handleAsAlbumScore>$decisionBoundry) ? TRUE : FALSE;
		cliLog("handleAsAlbumScore " . $this->handleAsAlbumScore , 1, 'purple'); #die();
		
		
		
		return;
		$this->tracks = NULL;print_r($this); ob_flush();print_r(uniqueArrayOrderedByRelevance($this->bitrates));die();
	}

	private function getFilenameScheme($value) {
		#$value = "B2-Aaron_Dilloway-Untitled-sour.mp3";
		
		if($value == '') {
			return 'missing';
		}
		
		// regex groups
		$gNum    = "([\d]{1,3})";
		$gVinyl  = "([A-M\d]{1,3})"; // a1, AA2,
		$gGlue   = "([ .\-_]{1,4})"; // "_-_", ". ", "-",
		$gExt    = "\.([a-z\d]{2,4})";
		$gScene  = "-([^\s\-]+)";
		$gNoMinus= "([^-]+)";
		
		
		// regex delims
		$dStart  = "/^";
		$dEnd    = "$/i";
		
		$iHateRegex = array(
			// 01-Aaron_Dilloway-Untitled.mp3
			'classic' => $dStart.$gNum.$gGlue.$gNoMinus."-".$gNoMinus.$gExt.$dEnd,
			// A1-Aaron_Dilloway-Untitled.mp3
			'classic-vinyl' => $dStart.$gVinyl.$gGlue.$gNoMinus."-".$gNoMinus.$gExt.$dEnd,
			// 112-Aaron_Dilloway-Untitled.mp3
			'classicscene' => $dStart.$gNum.$gGlue.$gNoMinus."-".$gNoMinus.$gScene.$gExt.$dEnd,
			// B2-Aaron_Dilloway-Untitled-sour.mp3
			'classicscene-vinyl' => $dStart.$gVinyl.$gGlue.$gNoMinus."-".$gNoMinus.$gScene.$gExt.$dEnd,
			// 05-Voodoo_Man.mp3
			'noartist' => $dStart.$gNum.$gGlue.$gNoMinus.$gExt.$dEnd,
			// B2-Voodoo_Man_(Last_Break_Mix).mp3
			'noartist-vinyl' => $dStart.$gVinyl.$gGlue.$gNoMinus.$gExt.$dEnd,
			
			// Aaron_Dilloway_-_Voodoo_Man_(Last_Break_Mix).mp3
			'nonumber' => $dStart.$gNoMinus."-".$gNoMinus.$gExt.$dEnd,
			
			// Voodoo_Man.mp3
			'nonumber-noartist' => $dStart.$gNoMinus.$gExt.$dEnd,
		);
		foreach($iHateRegex as $result => $pattern) {
			#cliLog($pattern);
			if(preg_match($pattern, $value, $m)) {
				#print_r($m); die();
				cliLog(__FUNCTION__ ." ".$result .": " . $value ,3 , 'green');
				return $result; // 01-Aaron_Dilloway-Untitled.mp3
			}
		}
		$result = "nomatch";
		cliLog(__FUNCTION__ ." ".$result .": " . $value ,3 , 'red');
		return $result;
	}

	private function getFilenameCase($value) {
		// exclude the file-extension
		$value = preg_replace('/\\.[^.\\s]{3,4}$/', '', $value);
		
		if(strtolower($value) === $value) { return 'lower'; }
		if(strtoupper($value) === $value) { return 'upper'; }
		return 'mixed';
	}
	
	private function getNumberScheme($value) {
		$value = str_replace(array(" ", ".", ","), "", $value);
		if($value == '') {
			return 'missing';
		}
		if(intval($value) == strval($value) && is_numeric($value) === TRUE) {
			return 'simple'; // 1, 2, 3
		}
		
		if(ltrim($value,'0') != strval($value)) {
			return 'leadingzero';	// 01, 02
		}
		if(preg_match("/^(\d*)\/(\d*)$/", $value)) {
			return 'slashsplit'; // 01/12 , 2/12
		}
		if(preg_match("/^([a-zA-Z]{1,2})(?:[\/-]{1})(\d*)$/", $value)) {
			return 'vinyl';	// AA1, B2, C34, A-1, A/4
		}
		cliLog(__FUNCTION__ ."(" . $value . ") unknown",3 , 'red');
		return 'unknown';
	}
	
	// setter
	public function setAlbumHash($value) {
		$this->albumHash = $value;
	}
	
	public function setAlbum(\Slimpd\Album $instance) {
		$this->album = $instance;
	}
	
	public function addTrack(\Slimpd\Track $instance) {
		$this->tracks[] = $instance;
	}
	
	// getter
	public function getAlbumHash() {
		return $this->albumHash;
	}
}
