<?php
namespace Slimpd\Modules\imageweighter;
class Imageweighter
{
	public static $types = array();
	public static $weights = array();
	
	
	public function getWeight($filename) {
		if (count(self::$weights) === 0) {
			$this->buildWeightConf();
		}
		$pathInfo = pathinfo($filename);
		$cleanFilename = az09($pathInfo['filename']);
		
		if(array_key_exists($cleanFilename, self::$weights) === TRUE) {
			return $this::$weights[$cleanFilename];
		}
		return 10000;
	}
	
	public function getType($filename) {
		if (count(self::$weights) === 0) {
			$this->buildWeightConf();
		}
		$pathInfo = pathinfo($filename);
		$cleanFilename = az09($pathInfo['filename']);
		
		if(array_key_exists($cleanFilename, self::$types) === TRUE) {
			return $this::$types[$cleanFilename];
		}
		return "other";
	}
	
	private function buildWeightConf() {
		// read config stuff
		$rawConf = parse_ini_file(APP_ROOT . "config/importer/image-weights.ini", TRUE);
		$conf = array();
		
		$keyRange = 50;
		
		foreach($rawConf['image-weights'] as $rawKey => $valueChunk) {
			$filenames = trimExplode("\n", $valueChunk, TRUE);
			#$cleanRawKey = str_replace('%s', '', $rawKey);
			if(strpos($rawKey, '%s') !== FALSE) {
				foreach(range(1,$keyRange) as $num) {
					$finalKey = sprintf($rawKey, strval($num));
					$conf[$finalKey] = $this->addPaddedNumberSuffixes($filenames, $num, $num);
				}
				continue;
			}
			$conf[$rawKey] = $this->addPaddedNumberSuffixes($filenames, 1, $keyRange);
		}
		$weightArray = [];
		$typeArray = [];
		foreach($conf as $picturetype => $filenameArray) {
			foreach($filenameArray as $filename) {
				
				if(array_key_exists($filename, $typeArray) === FALSE) {
					$typeArray[$filename] = $picturetype;
					$weightArray[] = $filename;
				}
			}
		}
		$weightArray = array_flip($weightArray);
		
		#echo "<pre>" . print_r($typeArray, 1);
		#echo "<pre>" . print_r($weightArray, 1);
		#echo "<pre>" . print_r($conf, 1); die();
		#echo "<pre>" . print_r($rawConf, 1);
		$reflectedClass = new \ReflectionClass('\Slimpd\Modules\imageweighter\Imageweighter');
		$reflectedClass->setStaticPropertyValue('types', $typeArray);
		$reflectedClass->setStaticPropertyValue('weights', $weightArray);
		
	}
	
	/**
	 * in case we have a placeholder - replace it with padded number [ cd%sfront => cd1front ]
	 * else - append the number [ cdfront => cdfront1 ]
	 * 
	 * @return array with filename variations
	 */
	private function addPaddedNumberSuffixes($input, $minNumber, $maxNumber) {
		$return = array();
		foreach(range($minNumber,$maxNumber) as $num) {
			foreach($input as $filename) {
				$value1 = $filename;
				$value2 = $filename . $num;
				$value3 = $filename . str_pad($num, 2, '0', STR_PAD_LEFT);
				$value4 = $filename . str_pad($num, 3, '0', STR_PAD_LEFT);
				if (strpos($filename, '%s') !== FALSE) {
					$value1 = sprintf($filename, strval($num));
					$value2 = sprintf($filename, strval(str_pad($num, 2, '0', STR_PAD_LEFT)));
					$value3 = sprintf($filename, strval(str_pad($num, 3, '0', STR_PAD_LEFT)));
					$value4 = sprintf($filename, strval(str_pad($num, 3, '0', STR_PAD_LEFT)));
				}
				$return[$value1] = $value1;
				$return[$value2] = $value2;
				$return[$value3] = $value3;
				$return[$value4] = $value4;
			}
		}
		return $return;
	}
}
