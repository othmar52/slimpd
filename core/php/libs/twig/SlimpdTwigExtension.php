<?php
namespace Slimpd\libs\twig\SlimpdTwigExtension;
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
class SlimpdTwigExtension extends \Twig_Extension {
    /**
     * @var \Slim\Interfaces\RouterInterface
     */
    private $container;
    
    public function __construct($container)
    {
        $this->container = $container;
	}

	/**
	 * Name of this extension.
	 *
	 * @return string
	 */
	public function getName() {
		return 'Slimpd';
	}

	public function getFunctions() {
		return array(
			new \Twig_SimpleFunction('getRandomInstance', array($this, 'getRandomInstance'))
		);
	}

	public function getRandomInstance($type) {
		try {
			$classPath = '\\Slimpd\\Models\\' . $type;
			if(class_exists($classPath) === FALSE) {
				return NULL;
			}
			$repoKey = strtolower($type) . 'Repo';
			return $this->container->$repoKey->getRandomInstance();
		} catch(\Exception $e) {
			// FIXME: how to access the response object?
			\Slim\Slim::getInstance()->response->redirect(\Slim\Slim::getInstance()->config['root'] . 'systemcheck?dberror');
		}
	}

	public function getFilters() {
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
				return removeLeadingZeroes(gmdate($format, $seconds) . ' ' . $suffix);
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
				return $this->container->ll->str($hans, $vars);
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

	public function getTests() {
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
