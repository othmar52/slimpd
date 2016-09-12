<?php
namespace Slimpd\Modules\configloader_ini;
/* Copyright (C) 2015-2016 othmar52 <othmar52@users.noreply.github.com>
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
class ConfigLoaderINI {

	private $configPath;
	private $absCacheFilePath; // absolute path for cached config

	/**
	 * create new config loader to parse .ini files in given directory
	 *
	 * @param $configPath - absolute path to the root folder of config files
	 * @return ConfigLoaderINI
	 */
	function __construct($configPath) {
		if (!function_exists('array_replace_recursive')) {
			throw new \Exception('Function array_replace_recursive not available! Please install a PHP Version >= 5.3 or define the function manually', 1);
		}

		if (!is_string($configPath)) {
			throw new \Exception('ConfigPath must be of Type String', 1);
		}

		$configPath = $this->prepareFilePath($configPath);
		if (!is_dir($configPath)) {
			throw new \Exception('ConfigPath no directory', 1);
		}
		$this->configPath = $configPath;
	}

	/**
	 * reads a single config file and parses the config
	 *
	 * @param $filename - relative path from the $configPath
	 * @return array()
	 */
	public function parseConfigFile($filename) {
		$filename = $this->prepareFilePath($this->configPath . $filename);

		if (!is_file($filename) || !is_readable($filename)) {
			return array();
		}

		if (!$tmpconfig = parse_ini_file($filename, TRUE)) {
			throw new \Exception('Can not load configuration from file: ' . $filename, 1);
		}
		return $this->parseRecursive($this->parseIniAdvanced($tmpconfig));
	}

	/**
	 * parses the master ini file. then parses every default-ini file and (if given) additional custom ini files an merges them
	 *
	 * @param $masterConfigFile - relative path to the master config file from the $configPath
	 * @param $additionalFiles - dictionary which config files to load from the master.ini file
	 */
	public function loadConfig($masterConfigFile, $additionalConfig = array(), $noCache = FALSE) {
		if (!is_string($masterConfigFile) || empty($masterConfigFile)) {
			throw new \Exception('Master Config file not given', 1);
		}

		$this->absCacheFilePath = APP_ROOT . "cache" . DS . "conf-". getFilePathHash($masterConfigFile). ".php";

		if($noCache === TRUE) {
			rmfile($this->absCacheFilePath);
		}

		if($cachedConfig = $this->getCachedConfig()) {
			return $cachedConfig;
		}

		//get masterconfig from given configfile
		$masterConfig = $this->parseConfigFile($masterConfigFile);

		if (empty($masterConfig)) {
			throw new INInotFoundException('Can not load configuration files from master config file', 1);
		}

		//return config
		$config = $this->buildConfig($masterConfig, $additionalConfig);

		// some post processing
		$config = $this->postProcess($config);

		// write to cache file
		$this->writeCacheFile($config);
		return $config;
	}

	private function prepareFilePath($path = '') {
		return str_replace('/', DIRECTORY_SEPARATOR, $path);
	}

	private function parseIniAdvanced($array) {
		$returnArray = array();
		if (is_array($array) === FALSE) {
			return $returnArray;
		}

		foreach (array_keys($array) as $key) {
			$exp = explode(':', $key);
			if (empty($exp[1]) === TRUE) {
				$returnArray[$key] = $array[$key];
				continue;
			}

			$tmpArray = array();
			foreach ($exp as $tk=>$tv) {
				$tmpArray[$tk] = trim($tv);
			}
			$tmpArray = array_reverse($tmpArray, true);
			foreach (array_keys($tmpArray) as $key2) {
				$newKey = $tmpArray[0];
				if (empty($returnArray[$newKey])) {
					$returnArray[$newKey] = array();
				}
				if (isset($returnArray[$tmpArray[1]])) {
					$returnArray[$newKey] = array_merge($returnArray[$newKey], $returnArray[$tmpArray[1]]);
				}
				if ($key2 === 0) {
					$returnArray[$newKey] = array_merge($returnArray[$newKey], $array[$key]);
				}
			}
			$returnArray[$key] = $array[$key];
		}
		return $returnArray;
	}

	private function parseRecursive($array) {
		$returnArray = array();
		if (is_array($array) === FALSE) {
			 return $returnArray;
		}

		foreach ($array as $key => $value) {
			if (is_array($value)) {
				$array[$key] = $this->parseRecursive($value);
			}
			$varName = explode('.', $key);
			if (empty($varName[1]) === TRUE) {
				$returnArray[$key] = $array[$key];
				continue;
			}

			$varName = array_reverse($varName, TRUE);
			if (isset($returnArray[$key])) {
				unset($returnArray[$key]);
			}
			if (!isset($returnArray[ $varName[0] ])) {
				$returnArray[ $varName[0] ] = array();
			}
			$first = TRUE;
			foreach ($varName as $v) {
				if ($first === TRUE) {
					$b = $array[$key];
					$first = FALSE;
				}
				$b = array($v=>$b);
			}
			$returnArray[ $varName[0] ] = array_replace_recursive($returnArray[ $varName[0] ], $b[ $varName[0] ]);
		}
		return $returnArray;
	}

	/**
	 * PARSE INI FILE AND CREATE GENERAL CONFIG/CONTENT
	 * read all given config files - and merge them
	 */
	private function buildConfig($masterConfig, $additionalConfig) {
		$config = array();

		// load default config
		foreach ($masterConfig['default'] as $defaultConfigFile) {
			$config = array_replace_recursive($config, $this->parseConfigFile($defaultConfigFile));
		}

		if(is_array($additionalConfig) === FALSE) {
			return $config;
		}

		// add additional config
		foreach($additionalConfig as $key => $value) {
			$lookup = $masterConfig;

			while (is_array($value)) {
				if (!isset($lookup[$key])) {
					continue 2;
				}
				$lookup = $lookup[$key];
				reset($value);
				$key = key($value);
				$value = $value[$key];
			}

			try {
				foreach($lookup[$key][$value] as $additionalConfigFile) {
					$config = array_replace_recursive($config, $this->parseConfigFile($additionalConfigFile));
				}
			} catch(\Exception $e) {
				continue;
			}
		}
		return $config;
	}

	/**
	 * override some config values based on other config values
	 */
	private function postProcess($config) {
		try {
			// append trailing slash if missing
			$config["mpd"]["musicdir"] = appendTrailingSlash($config["mpd"]["musicdir"]);
			if($config["mpd"]["alternative_musicdir"] !== "") {
				$config["mpd"]["alternative_musicdir"] = appendTrailingSlash($config["mpd"]["alternative_musicdir"]);
			}
		} catch(\Exception $e) { }
		try {
			if($config['destructiveness']['disable-all'] !== '1') {
				return $config;
			}
			// override destructiveness values based on specific config key
			foreach(array_keys($config['destructiveness']) as $key) {
				$config['destructiveness'][$key] = ($key === 'disable-all') ? '1' : '0';
			}
		} catch(\Exception $e) { }
		return $config;
	}

	private function getCachedConfig() {
		if(is_file($this->absCacheFilePath) === FALSE) {
			return FALSE;
		}
		$cachedConf = '';
		include($this->absCacheFilePath);
		return $cachedConf;
	}

	private function writeCacheFile($configArray) {
		$fileContent = "<?php \$cachedConf = " . var_export($configArray,1) . ";";
		file_put_contents($this->absCacheFilePath, $fileContent);
	}
}
