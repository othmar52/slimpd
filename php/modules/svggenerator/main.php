<?php
namespace Slimpd;

class Svggenerator {
	protected $svgResolution = 300;
	protected $absolutePath;
	protected $fingerprint;
	protected $peakValuesFilePath;
	protected $peakFileResolution = 4000;
	protected $ext;
	
	
	public function __construct($arg) {
		if(is_string($arg) && is_file($arg) === TRUE) {
			$this->absolutePath = $arg;
			$this->ext = pathinfo($arg, PATHINFO_EXTENSION);
		}
		if(is_numeric($arg) === TRUE) {
			$t = \Slimpd\Track::getInstanceByAttributes(array('id' => $arg));
			if(is_object($t) === TRUE) {
				$this->absolutePath = \Slim\Slim::getInstance()->config['mpd']['musicdir'] . $t->getRelativePath();
				$this->fingerprint = $t->getFingerprint();
				$this->ext = $t->getAudioDataFormat();
			}
		}
		
		if(is_file($this->absolutePath) === FALSE) {
			// TODO: should we serve a default waveform svg?
			return NULL;
		}
		
		if(!preg_match("/^([a-f0-9]){32}$/", $this->fingerprint)) {
			// extract the fingerprint
			if($fp = \Slimpd\Importer::extractAudioFingerprint($this->absolutePath)) {
				$this->fingerprint = $fp;
			} else {
				# TODO: handle missing fingerprint 
				die('invalid fingerprint: ' . $this->absolutePath);
			}
		}
		$this->setPeakFilePath();
		if(is_file($this->peakValuesFilePath) === FALSE) {
			session_write_close(); // do not block other requests during processing
			
			
			$tmpFileName = APP_ROOT . 'cache' . DS . $this->ext . '.' . $this->fingerprint . '.';
			if(is_file($tmpFileName.'mp3') === TRUE || is_file($tmpFileName.'wav') === TRUE) {
				# make sure same file isnt processed twice simultaneously by different client-requests...
				# TODO: send a message to client for requesting waveform again after a few seconds?
				# or sleep here until tmp files had been deleted?
				# or redirect to same route with increasing counter until a maximum is reached
				return NULL;
			}
			$this->generatePeakFile();
		} 
	}
	
	public function findValues($byte1, $byte2) {
		$byte1 = hexdec(bin2hex($byte1));
		$byte2 = hexdec(bin2hex($byte2));
		return ($byte1 + ($byte2*256));
	}
	
	public function generateSvg($pixel=300) {
		if(is_file($this->peakValuesFilePath) === FALSE) {
			die($peakFilePath . ' does not exist');	
		}
		$svg  = "<?xml version=\"1.0\"?>\n";
	    $svg .= "<?xml-stylesheet href=\"/skin/default/waveform-1.css\" type=\"text/css\"?>\n";
	    $svg .= "<!DOCTYPE svg PUBLIC \"-//W3C//DTD SVG 1.1//EN\" \"http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd\">\n";
	    $svg .= "<svg width=\"100%\" height=\"100%\" version=\"1.1\" xmlns=\"http://www.w3.org/2000/svg\" xmlns:xlink=\"http://www.w3.org/1999/xlink\">\n";
		$svg .= "<svg y=\"0%\" width=\"100%\" height=\"100%\">";
		
		
		$values = explode("\n", file_get_contents($this->peakValuesFilePath));
		$values = array_map('trim', $values);
		
		$values = $this->limitArray($values, $pixel);
		$amount = count($values);
		$max = max($values);
		
		$strokeLine = 2;
		$strokeBorder = 1;
		$strokeCounter = 0;
		$avgPeak = 0;
		
		foreach($values as $i => $v) {
			$strokeCounter++;
			$avgPeak += $v;
			if($strokeCounter>=($strokeBorder + $strokeLine)){
				$strokeCounter = 0;
				$avgPeak = $avgPeak/($strokeBorder + $strokeLine+1);
			} else {
				continue;
			}
			$percent = $avgPeak/($max/100);
			// increase difference between low peak and high peak
			$percent = $percent*0.01*$percent;
			
			$diffPercent = 100 - $percent;
			$x = $i/($amount/100);
			$y1 = number_format($diffPercent/2, 2);
			$y2 = number_format($diffPercent/2 + $percent, 2);
			
			$svg .= "<line class=\"l\" x1=\"{$x}%\" y1=\"{$y1}%\" x2=\"{$x}%\" y2=\"{$y2}%\" />";
			$svg .= "<line class=\"b\" x1=\"{$x}%\" y1=\"{$y1}%\" x2=\"{$x}%\" y2=\"{$y2}%\" />"; 
		}
		$svg .= "</svg>\n";
		$svg .= "\n</svg>";
    
		header("Content-Type: image/svg+xml");
		print $svg;
		exit;

	}
	
	public function setPeakFilePath() {
		$this->peakValuesFilePath = APP_ROOT . 'peakfiles' .
			DS . $this->ext .
			DS . substr($this->fingerprint,0,3) .
			DS . $this->fingerprint;
	}
	
		
	private function generatePeakFile() {
		// extract peaks
		$peakValues = $this->getPeaks();
		
		// shorten values to configured limit
		$peakValues = $this->limitArray($peakValues, $this->peakFileResolution);
		
		
		
		\phpthumb_functions::EnsureDirectoryExists(dirname($this->peakValuesFilePath));
		file_put_contents($this->peakValuesFilePath, join("\n", $peakValues));
		return;
	}
	
	private function getPeaks() {
		$tmpFileName = APP_ROOT . 'cache' . DS . $this->ext . '.' . $this->fingerprint;
		
		$cmd = "lame " . escapeshellarg($this->absolutePath) . " -m m -S -f -b 16 --resample 8 ". escapeshellarg($tmpFileName.'.mp3') .
			" && lame -S --decode ". escapeshellarg($tmpFileName.'.mp3') . " ". escapeshellarg($tmpFileName.'.wav');
		exec($cmd);
		
		
		if(is_file($tmpFileName.'.mp3') === TRUE) {
			unlink($tmpFileName . ".mp3");
		}
		
		
		if(is_file($tmpFileName.'.wav') === FALSE) {
			die('error 8======D');
		}
		$values = $this->getWavPeaks($tmpFileName.'.wav');
		// delete temporary files
    	
		unlink($tmpFileName . ".wav");
		return $values;
	}
	
	
	private function getWavPeaks($temp_wav)
	{
		ini_set ('memory_limit', '1024M'); // extracted wav-data is very large (500000 entries)
		/**
       * Below as posted by "zvoneM" on
       * http://forums.devshed.com/php-development-5/reading-16-bit-wav-file-318740.html
       * as findValues() defined above
       * Translated from Croation to English - July 11, 2011
       */
      $data = array();
      $handle = fopen ($temp_wav, "r");
      //dohvacanje zaglavlja wav datoteke
      $heading[] = fread ($handle, 4);
      $heading[] = bin2hex(fread ($handle, 4));
      $heading[] = fread ($handle, 4);
      $heading[] = fread ($handle, 4);
      $heading[] = bin2hex(fread ($handle, 4));
      $heading[] = bin2hex(fread ($handle, 2));
      $heading[] = bin2hex(fread ($handle, 2));
      $heading[] = bin2hex(fread ($handle, 4));
      $heading[] = bin2hex(fread ($handle, 4));
      $heading[] = bin2hex(fread ($handle, 2));
      $heading[] = bin2hex(fread ($handle, 2));
      $heading[] = fread ($handle, 4);
      $heading[] = bin2hex(fread ($handle, 4));
      
      //bitrate wav datoteke
      $peek = hexdec(substr($heading[10], 0, 2));
      $byte = $peek / 8;
      
      //provjera da li se radi o mono ili stereo wavu
      $channel = hexdec(substr($heading[6], 0, 2));
      
      if($channel == 2){
        $omjer = 40;
      }
      else{
        $omjer = 80;
      }

      while(!feof($handle)){
        $bytes = array();
        //get number of bytes depending on bitrate
        for ($i = 0; $i < $byte; $i++){
          $bytes[$i] = fgetc($handle);
        }

        switch($byte){
        	
          //get value for 8-bit wav
          case 1:
              $data[] = $this->findValues($bytes[0], $bytes[1]);
              break;
			  
          //get value for 16-bit wav
          case 2:
            $temp = (ord($bytes[1]) & 128) ? 0 : 128;
            $temp = chr((ord($bytes[1]) & 127) + $temp);
            $data[]= floor($this->findValues($bytes[0], $temp) / 256);
            break;
        }

        //skip bytes for memory optimization
        fread ($handle, $omjer);
      }
      
      // close and cleanup
      fclose ($handle);
	  #return $data;
	  
	  return $data;
	  
	}

    
	private function limitArray($input, $max = 22000)
	{
		#echo ini_get('memory_limit'); die();
		// 512MB is not enough for files > 4hours (XXX entries)
		# TODO: add a note in documentation
		ini_set ('memory_limit', '1024M'); // extracted wav-data is very large (500000 entries)
		$c = count($input);
		if($c < $max) {
			return $input;
		}
		$f = (floor($c / $max)) + 1;
		
		$output = array();
		$prev = 0;
		$current = 0;
		
		for($i = 0; $i < $c; $i++) {
			$current++;
			$prev = ($input[$i] > $prev) ? $input[$i] : $prev;
			if($current == $f) {
				$output[] = $prev;
				$current = 0;
				$prev = 0;
			}
			unset($input[$i]);
		}
		return $output;
	}
	
}
