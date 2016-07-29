<?php
namespace Slimpd\modules\imageweighter;
class Imageweighter
{
	public static $weightconf = array();
	
	public function getWeight($filename) {
		if (count(self::$weightconf) === 0) {
			$this->buildWeightConf();
		}
		echo "oleoleole";
	}
	
	private function buildWeightConf() {
		// read config stuff
		$reflectedClass = new \ReflectionClass('\Slimpd\modules\imageweighter\Imageweighter');
		$rawConf = parse_ini_file(APP_ROOT . "config/importer/tmp.ini", FALSE);
		
		$conf = array();
		
		foreach($rawConf as $rawKey => $valueChunk) {
			$filenames = trimExplode("\n", $valueChunk, TRUE);
			$cleanRawKey = str_replace('%s', '', $rawKey);
			echo "<pre>" . print_r($filenames, 1);
			if(strpos($rawKey, '%s') !== FALSE) {
				foreach(range(1,3) as $num) {
					foreach($filenames as $filename) {
						$add = (strpos($filename, '%s') !== FALSE)
							? sprintf($filename, strval($num))
							: $filename;
						$conf[$cleanRawKey][$add] = $add;
						
						$add = (strpos($filename, '%s') !== FALSE)
							? sprintf($filename, strval(str_pad($num, 2, '0', STR_PAD_LEFT)))
							: $filename;
						$conf[$cleanRawKey][$add] = $add;
						$add = (strpos($filename, '%s') !== FALSE)
							? sprintf($filename, strval(str_pad($num, 3, '0', STR_PAD_LEFT)))
							: $filename;
						$conf[$cleanRawKey][$add] = $add;
					}
				}
				#echo $rawKey; die();
			} else {
				
			}
			
		}
		echo "<pre>" . print_r($conf, 1); die();
		echo "<pre>" . print_r($rawConf, 1);
		$reflectedClass->setStaticPropertyValue('weightconf', parse_ini_file(APP_ROOT . "config/importer/image-weights.ini", FALSE));
	}
	
}
