<?php
namespace Slimpd\Models;

class PlaylistFilesystem extends \Slimpd\Models\AbstractFilesystemItem {
	protected $errorPath = TRUE; // TODO: consider to remove and use $error instead
	protected $title;
	protected $ext;
	protected $length;
	protected $itemPaths = [];	// pathstrings
	protected $tracks = [];		// track-instances
	private $fetchedLength = FALSE;

	public function __construct($relPath) {
		$app = \Slim\Slim::getInstance();
		foreach([$app->config['mpd']['musicdir'], $app->config['mpd']['alternative_musicdir']] as $path) {
			if(is_file($path . $relPath) === TRUE) {
				$this->setRelPath($relPath);
				$this->setErrorPath(FALSE);
			}
		}

		if($this->getErrorPath() === TRUE) {
			$app->flashNow('error', 'playlist file ' . $relPath . ' does not exist');
			return $this;
		}
		$this->setTitle(basename($this->getRelPath()));
		$this->setExt(getFileExt($this->getRelPath()));
	}

	public function fetchTrackRange($minIndex, $maxIndex, $pathOnly = FALSE) {
		$app = \Slim\Slim::getInstance();
		$raw = file_get_contents($app->config['mpd']['musicdir'] . $this->relPath);
		switch($this->getExt()) {
			case 'm3u':
			case 'pls':
			case 'txt':
				$this->parsePlaintext($raw, $minIndex, $maxIndex);
				break;
			case 'nml':
				$this->parseNml($raw, $minIndex, $maxIndex);
				break;
			default :
				$app->flashNow('error', 'playlist extension ' . $this->getExt() . ' is not supported');
				return;
		}
		$this->fetchedLength === TRUE;

		if($pathOnly === FALSE) {
			$this->tracks = self::pathStringsToTrackInstancesArray($this->itemPaths);
			return;
		}
		$this->tracks = self::pathStringsToTrackInstancesArray($this->itemPaths, TRUE);
	}

	public static function pathStringsToTrackInstancesArray($pathStringArray, $noDatabaseQueries = FALSE) {
		$return = array();
		foreach($pathStringArray as $itemPath) {
			$track = ($noDatabaseQueries === FALSE)
				? \Slimpd\Models\Track::getInstanceByPath($itemPath)
				: NULL;

			// increase performance by avoiding any database queries when adding tenthousands of tracks to mpd-playlist
			if($track === NULL) {
				$track = new \Slimpd\Models\Track();
				// TODO: pretty sure we have the pathcheck musicdir/alternative_musicdir somewhere else! find & use it...
				if(ALTDIR && strpos($itemPath, \Slim\Slim::getInstance()->config['mpd']['alternative_musicdir']) === 0) {
					$itemPath = substr($itemPath, strlen(\Slim\Slim::getInstance()->config['mpd']['alternative_musicdir']));
				}
				$track->setRelPath($itemPath);
				$track->setRelPathHash(getFilePathHash($itemPath));
				$track->setAudioDataformat(getFileExt($track->getRelPath()));
			}

			if(is_file(\Slim\Slim::getInstance()->config['mpd']['musicdir'] . $track->getRelPath()) === FALSE) {
				$track->setError('notfound');
			}
			$return[] = $track;
		}
		return $return;
	}

	private function parsePlaintext($rawFileContent, $minIndex, $maxIndex) {
		// windows generated playlists are not supported yet
		$playlistContent = str_replace("\\", "/", $rawFileContent);
		$playlistContent = trimExplode("\n", $playlistContent, TRUE);
		$this->setLength(count($playlistContent));
		foreach($playlistContent as $idx => $itemPath) {
			if($idx < $minIndex || $idx >= $maxIndex) {
				continue;
			}
			$this->itemPaths[] = $itemPath;
		}
	}

	private function parseNml($rawFileContent, $minIndex, $maxIndex) {
		if(isValidXml($rawFileContent) === FALSE) {
			\Slim\Slim::getInstance()->flashNow('error', 'invalid XML ' . $this->getTitle());
			return;
		}
		$playlistContent = new \SimpleXMLElement($rawFileContent);
		$trackEntries = $playlistContent->xpath("//PLAYLIST/ENTRY/LOCATION");
		$this->setLength(count($trackEntries));
		foreach($trackEntries as $idx => $trackEntry) {
			if($idx < $minIndex || $idx >= $maxIndex) {
				continue;
			}
			$this->itemPaths[] = $trackEntry->attributes()->DIR->__toString() . $trackEntry->attributes()->FILE->__toString();
		}
	}

	public function setErrorPath($errorPath) {
		$this->errorPath = $errorPath;
	}

	public function getErrorPath() {
		return $this->errorPath;
	}

	public function setTitle($value) {
		$this->title = $value;
	}

	public function getTitle() {
		return $this->title;
	}

	public function setExt($ext) {
		$this->ext = $ext;
	}

	public function getExt() {
		return $this->ext;
	}

	public function setLength($length) {
		$this->length = $length;
	}

	public function getLength() {
		if($this->fetchedLength === FALSE) {
			// we have to process to get the total length
			$this->fetchTrackRange(0,1, TRUE);
			$this->tracks = [];
			$this->itemPaths = [];
		}
		return $this->length;
	}

	public function appendTrack(\Slimpd\Models\Track $track) {
		$this->tracks[] = $track;
	}

	public function getTracks() {
		return $this->tracks;
	}
}
