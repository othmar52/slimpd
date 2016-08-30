<?php
namespace Slimpd;

class RegexHelper {
	
	public $dStart  = "/^";
	public $dEnd    = "$/";
	public $dEndInsens= "$/i";
		
		
	public $num    = "([\d]{1,3})";
	public $vinyl  = "([A-M\d]{1,3})"; // a1, AA2,
	public $glue   = "(?:[ .\-_\|]{1,4})"; // "_-_", ". ", "-",
	public $glueNoWhitespace   = "(?:[.\-_]{1,4})"; // "_-_", ". ", "-",
	public $ext    = "\.([a-z\d]{2,4})";
	public $scene  = "-([^\s\-]+)";
	public $year  = "((?:[1920]{2})(?:[0-9]{2}))";
	#public $year  = "([0-9]{4})";
	public $catNr  = "((?:([\(\[]{1})?((?:[A-Z]{2,14})(?:[0-9]{1,})(?:[A-Z]{2,7})?)(?:([\)\]]{1})?)))";
	public $source  = "((?:([\(\[]{1})?([vinylVINYLwebWEBCDcd]{3,5})(?:([\)\]]{1})?)))";
	public $noMinus= "([^-]+)";
	public $anything= "(.*)";
	
	
	
	
	
	public $mayBracket = "(?:([\(\)\[\]]{0,1}))?"; 
	
	public $various = "(va|v\.a\.|various|various\ artists|various\ artist)";
	
	
	public function seemsYeary($input) {
		return ($input > 1900 && $input < date("Y")+1 )? TRUE : FALSE;
	}
	
	public function seemsCatalogy($input) {
		$input = preg_replace('/[^A-Z0-9]/', "", strtoupper($input));
		if(preg_match("/". $this->catNr."/", $input)) {
			return TRUE;
		}
		return FALSE;
	}
	
	public function seemsTitly($input) {
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
	
	public function seemsArtistly($input) {
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
