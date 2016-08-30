<?php
class Slimpd_Twig_Extension extends Twig_Extension implements Twig_ExtensionInterface
{
	/**
	 * Name of this extension.
	 *
	 * @return string
	 */
	public function getName()
	{
		return 'Slimpd';
	}

	public function getFunctions()
	{
		return array(
			new Twig_SimpleFunction('getRandomInstance', array($this, 'getRandomInstance'))
		);
	}

	public function getRandomInstance($type)
	{
		try {
			$classPath = '\\Slimpd\\Models\\' . $type;
			if(class_exists($classPath) === FALSE) {
				return NULL;
			}
			return $classPath::getRandomInstance();
		} catch(\Exception $e) {
			\Slim\Slim::getInstance()->response->redirect(\Slim\Slim::getInstance()->config['root'] . 'systemcheck?dberror');
		}
	}

	public function getFilters()
	{
		return array(
			new \Twig_SimpleFilter('formatMiliseconds', function ($miliseconds) {
				return gmdate(($miliseconds > 3600000) ? "G:i:s" : "i:s", ($miliseconds/1000));
			}),

			new \Twig_SimpleFilter('path2url', function ($mixed) {
				// rawurlencode but preserve slashes
				return path2url($mixed);
			}),

			new \Twig_SimpleFilter('formatSeconds', function ($seconds) {
				$format = "G:i:s";
				$suffix = "h";
				if($seconds < 3600) {
					$format = "i:s";
					$suffix = "min";
				}
				if($seconds < 60) {
					$format = "s";
					$suffix = "sec";
				}
				if($seconds < 1) {
					return(round($seconds*1000)) . ' ms';
				}
				// remove leading zero
				return ltrim(gmdate($format, $seconds) . ' ' . $suffix, 0);
			}),

			new \Twig_SimpleFilter('timeElapsedString', function ($seconds) {
				return timeElapsedString($seconds);
			}),

			new \Twig_SimpleFilter('shorty', function ($number) {
				if($number < 990) {
					return $number;
				}
				if($number < 990000) {
					return number_format($number/1000,0) . " K";
				}
				return number_format($number/1000000,1) . " M";
			}),

			new \Twig_SimpleFilter('fingerprintshorty', function ($mixed, $length=2, $separator='...') {
				if(is_object($mixed) === TRUE) {
					if(method_exists($mixed, 'getFingerprint') === TRUE) {
						$fingerPrint = $mixed->getFingerprint();
						if(strlen($fingerPrint) === 32) {
							return substr($fingerPrint, 0, $length) . $separator . substr($fingerPrint, $length*-1);
						}
					}
				}
				return "";
			}),

			new \Twig_SimpleFilter('formatBytes', function ($bytes, $precision = 2) {
				$units = array('B', 'KB', 'MB', 'GB', 'TB');
				$bytes = max($bytes, 0);
				$pow = floor(($bytes ? log($bytes) : 0) / log(1024));
				$pow = min($pow, count($units) - 1);
				$bytes /= pow(1024, $pow);
				return round($bytes, $precision) . ' ' . $units[$pow];
			}),

			new \Twig_SimpleFilter('ll', function ($hans = array(), $vars = array()) {
				return \Slim\Slim::getInstance()->ll->str($hans, $vars);
			}),

			new \Twig_SimpleFilter('getType', function ($var) {
				return gettype($var);
			}),

			new \Twig_SimpleFilter("dumpImagestream", function ($input, $mimeType) {
				$imageinfo = array();
				if ($imagechunkcheck = \getid3_lib::GetDataImageSize($input, $imageinfo)) {
					$attributes = [
						"src='data:" .$mimeType.";base64,".base64_encode($input)."'",
						"width='". $imagechunkcheck[0] ."'",
						"height='". $imagechunkcheck[1] ."'"
					];
					return "<img " . join(" " , $attributes) . ">";
				}
				return "<i>invalid image data</i></td></tr>";
			})
		);
	}

	public function getTests()
	{
		return array(
			new \Twig_SimpleTest('instanceofAlbum', function ($item) {
				return $item instanceof \Slimpd\Models\Album;
			}),

			new \Twig_SimpleTest('instanceofTrack', function ($item) {
				return $item instanceof \Slimpd\Models\Track;
			}),

			new \Twig_SimpleTest('instanceofLabel', function ($item) {
				return $item instanceof \Slimpd\Models\Label;
			}),

			new \Twig_SimpleTest('instanceofGenre', function ($item) {
				return $item instanceof \Slimpd\Models\Genre;
			}),

			new \Twig_SimpleTest('instanceofArtist', function ($item) {
				return $item instanceof \Slimpd\Models\Artist;
			}),

			new \Twig_SimpleTest('instanceofDirectory', function ($item) {
				return $item instanceof \Slimpd\Models\Directory;
			}),

			new \Twig_SimpleTest('typeString', function ($value) {
				return is_string($value);
			}),

			new \Twig_SimpleTest('typeArray', function ($value) {
				return is_array($value);
			}),

			new \Twig_SimpleTest('typeBoolean', function ($value) {
				return is_bool($value);
			}),

			new \Twig_SimpleTest('typeDouble', function ($value) {
				return is_double($value);
			}),

			new \Twig_SimpleTest('typeFloat', function ($value) {
				return is_float($value);
			}),

			new \Twig_SimpleTest('typeInteger', function ($value) {
				return is_int($value);
			}),

			new \Twig_SimpleTest('typeNull', function ($value) {
				return is_null($value);
			}),

			new \Twig_SimpleTest('typeObject', function ($value) {
				return is_object($value);
			})
		);
	}
}
