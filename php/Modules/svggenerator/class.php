<?php
namespace Slimpd;

class Svggenerator {
	protected $svgResolution = 300;
	protected $absolutePath;
	protected $fingerprint;
	protected $peakValuesFilePath;
	protected $peakFileResolution = 4000;
	protected $ext;
	protected $cmdTempwav = '';
	
	
	public function __construct($arg) {
		$config = \Slim\Slim::getInstance()->config['mpd'];
		$arg = join(DS, $arg);
		$track = NULL;
		if(is_numeric($arg) === TRUE) {
			$track = \Slimpd\Models\Track::getInstanceByAttributes(array('id' => (int)$arg));
		}
		if(is_numeric($arg) === FALSE) {
			$track = \Slimpd\Models\Track::getInstanceByAttributes(array('relativePathHash' => getFilePathHash($arg)));
		}
		if(is_object($track) === TRUE) {
			$this->absolutePath = $config['musicdir'] . $track->getRelativePath();
			$this->fingerprint = $track->getFingerprint();
			$this->ext = $track->getAudioDataFormat();
		}

		if($this->fingerprint === NULL) {
			if(ALTDIR && is_file($config['alternative_musicdir'].$arg) === TRUE) {
				$arg = $config['alternative_musicdir'].$arg;
			}
			if(is_file($config['musicdir'].$arg) === TRUE) {
				$arg = $config['musicdir'].$arg;
			}
			if(is_file($arg) === TRUE) {
				$this->absolutePath = $arg;
				$this->ext = strtolower(pathinfo($arg, PATHINFO_EXTENSION));
			}
		}
		
		// systemcheck testfiles are not within our music_dirs nsor in our database
		if($this->fingerprint === NULL) {
			if(strpos(realpath(DS.$arg), APP_ROOT . 'templates/partials/systemcheck/waveforms/testfiles/') === 0) {
				$this->absolutePath = realpath(DS.$arg);
				$this->ext = strtolower(pathinfo(DS.$arg, PATHINFO_EXTENSION));
			}
		}
		
		if(is_file($this->absolutePath) === FALSE) {
			// TODO: should we serve a default waveform svg?
			return NULL;
		}
		
		if(!preg_match("/^([a-f0-9]){32}$/", $this->fingerprint)) {
			// extract the fingerprint
			if($fingerprint = \Slimpd\Modules\Importer::extractAudioFingerprint($this->absolutePath)) {
				$this->fingerprint = $fingerprint;
			} else {
				# TODO: handle missing fingerprint
				return NULL;
				#die('invalid fingerprint: ' . $this->absolutePath);
			}
		}
		$this->setPeakFilePath();
		if(is_file($this->peakValuesFilePath) === FALSE) {
			session_write_close(); // do not block other requests during processing
			$tmpFileName = APP_ROOT . 'cache' . DS . $this->ext . '.' . $this->fingerprint . '.';
			if(is_file($tmpFileName.'mp3') === TRUE || is_file($tmpFileName.'wav') === TRUE) {
				# another request already triggered generateSvg
				$this->fireRetryHeaderAndExit();
			}
			$this->generatePeakFile();
		} 
	}

	public function fireRetryHeaderAndExit() {
		$newResponse = \Slim\Slim::getInstance()->response();
		$newResponse->headers->set('Retry-After', 5);
		# TODO: check why slim's setStatus does not work
		#$newResponse->setStatus(503);
		header('HTTP/1.1 503 Service Temporarily Unavailable');
		header('Status: 503 Service Temporarily Unavailable');
		return $newResponse;
	}
	
	public function findValues($byte1, $byte2) {
		$byte1 = hexdec(bin2hex($byte1));
		$byte2 = hexdec(bin2hex($byte2));
		return ($byte1 + ($byte2*256));
	}
	
	public function generateSvg($pixel=300) {
		if(is_file($this->peakValuesFilePath) === FALSE) {
			$app = \Slim\Slim::getInstance();
			$app->response->redirect($app->config['root'] . 'imagefallback-100/broken');
			return;
		}
		
		$peaks = file_get_contents($this->peakValuesFilePath);
		if($peaks === 'generating') {
			$this->fireRetryHeaderAndExit();
		}
		
		$values = array_map('trim', explode("\n", $peaks));
		
		$values = $this->limitArray($values, $pixel);
		$amount = count($values);
		$max = max($values);
		
		$strokeLine = 2;
		$strokeBorder = 1;
		$strokeCounter = 0;
		$avgPeak = 0;
		
		$renderValues = array();
		
		
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

			$stroke = array(
				'x' => number_format($i/($amount/100), 5, '.', ''),
				'y1' => number_format($diffPercent/2, 2, '.', ''),
				'y2' => number_format($diffPercent/2 + $percent, 2, '.', '')
			);
			if(\Slim\Slim::getInstance()->request->get('mode') === 'half') {
				$stroke["y1"] = number_format($diffPercent, 2, '.', '');
				$stroke["y2"] = 100;
			}
			$renderValues[] = $stroke;
		}
    
		$app = \Slim\Slim::getInstance();
		switch( $app->request->get('colorFor') ) {
			case 'mpd':
			case 'local':
			case 'xwax':
				$color = $app->config['colors'][ $app->config['spotcolor'][$app->request->get('colorFor')] ]['1st'];
				break;
			default:
				$color = $app->config['colors']['defaultwaveform'];
				break;
		}
		$newResponse = $app->response();
		$newResponse->headers->set('Content-Type', 'image/svg+xml');
		$app->render(
			'svg/waveform.svg',
			array(
				'peakvalues' => $renderValues,
				'color' => $color
			)
		);
		return $newResponse;
	}

	public function generateJson($resolution=300) {
		if(is_file($this->peakValuesFilePath) === FALSE) {
			$app = \Slim\Slim::getInstance();
			$app->response->redirect($app->config['root'] . 'imagefallback-100/broken');
			return;
		}
		
		$peaks = file_get_contents($this->peakValuesFilePath);
		if($peaks === 'generating') {
			$this->fireRetryHeaderAndExit();
			return NULL;
		}
		
		$values = explode("\n", $peaks);
		$values = array_map('trim', $values);

		deliverJson($this->limitArray($values, $resolution));
	}
	
	public function setPeakFilePath() {
		$this->peakValuesFilePath = APP_ROOT . 'peakfiles' .
			DS . $this->ext .
			DS . substr($this->fingerprint,0,3) .
			DS . $this->fingerprint;
	}
	
		
	private function generatePeakFile() {
		
		\phpthumb_functions::EnsureDirectoryExists(
			dirname($this->peakValuesFilePath),
			octdec(\Slim\Slim::getInstance()->config['config']['dirCreateMask'])
		);
		file_put_contents($this->peakValuesFilePath, "generating");
		
		// extract peaks
		$peakValues = $this->getPeaks();
		if($peakValues === FALSE) {
			return FALSE;
		}
		
		// shorten values to configured limit
		$peakValues = $this->limitArray($peakValues, $this->peakFileResolution);
		
		file_put_contents($this->peakValuesFilePath, join("\n", $peakValues));
		chmod($this->peakValuesFilePath, octdec(\Slim\Slim::getInstance()->config['config']['fileCreateMask']));
		return;
	}
	
	private function getPeaks() {
		$tmpFileName = APP_ROOT . 'cache' . DS . $this->ext . '.' . $this->fingerprint;
		$inFile = escapeshellarg($this->absolutePath);
		$tmpWav = escapeshellarg($tmpFileName.'.wav');
		$tmpMp3 = escapeshellarg($tmpFileName.'.mp3');
		$binConf = \Slim\Slim::getInstance()->config['modules'];

		switch($this->ext) {
			case 'flac':
				$this->cmdTempwav = sprintf(
					"%s -d --stdout %s | %s -m m -S -f -b 16 --resample 8 - %s",
					$binConf['bin_flac'],
					$inFile,
					$binConf['bin_lame'],
					$tmpMp3
				);
				break;
			case 'm4a':
			case 'aac':
				$this->cmdTempwav = sprintf(
					"%s -q -o %s %s && %s -m m -S -f -b 16 --resample 8 %s %s",
					$binConf['bin_faad'],
					$tmpWav,
					$inFile,
					$binConf['bin_lame'],
					$tmpWav,
					$tmpMp3
				);
				break;
			case 'ogg':
				$this->cmdTempwav = sprintf(
					"%s -Q  %s -o  %s &&  %s -m m -S -f -b 16 --resample 8  %s  %s",
					$binConf['bin_oggdec'],
					$inFile,
					$tmpWav,
					$binConf['bin_lame'],
					$tmpWav,
					$tmpMp3
				);
				break;
			case 'ac3':
				$this->cmdTempwav = sprintf(
					"%s -really-quiet -channels 5 -af pan=2:'1:0':'0:1':'0.7:0':'0:0.7':'0.5:0.5' %s".
					" -ao pcm:file=%s && %s -m m -S -f -b 16 --resample 8 %s %s",
					$binConf['bin_mplayer'],
					$inFile,
					$tmpWav,
					$binConf['bin_lame'],
					$tmpWav,
					$tmpMp3
				);
				break;
			case 'wma':
				$this->cmdTempwav = sprintf(
					"%s -really-quiet %s -ao pcm:file=%s && %s -m m -S -f -b 16 --resample 8 %s %s",
					$binConf['bin_mplayer'],
					$inFile,
					$tmpWav,
					$binConf['bin_lame'],
					$tmpWav,
					$tmpMp3
				);
				break;
			default:
				$this->cmdTempwav = sprintf(
					"%s %s -m m -S -f -b 16 --resample 8 %s",
					$binConf['bin_lame'],
					$inFile,
					$tmpMp3
				);
				break;
		}

		$this->cmdTempwav .= sprintf(
			" && %s -S --decode %s %s",
			$binConf['bin_lame'],
			$tmpMp3,
			$tmpWav
		);


		exec($this->cmdTempwav);
		
		
		if(is_file($tmpFileName.'.mp3') === TRUE) {
			unlink($tmpFileName . ".mp3");
		}
		
		
		if(is_file($tmpFileName.'.wav') === FALSE) {
			return FALSE;
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
	public function getCmdTempwav() {
		return $this->cmdTempwav;
	}
}
