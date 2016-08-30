<?php
namespace Slimpd\Modules\importer;

class MpdDatabaseParser {
	protected $dbFile;
	public $error = FALSE;
	public $gzipped = FALSE;
	protected $rawTagItem = FALSE;

	// needed for traversing
	private $level = -1;
	private $openDirs = array();
	private $currentSection = "";
	private $currentSong = "";
	private $currentDir = "";
	private $currentPlaylist = "";

	// needed for comparisons
	public $fileTstamps = array();
	public $dirTstamps = array();
	public $fileOrphans = array();
	public $dirOrphans = array();

	// some statistics
	public $fileCount = 0;
	public $dirCount = 0;
	public $itemsUnchanged = 0;
	public $itemsTotal = 0;
	public $itemsChecked = 0;
	public $itemsProcessed = 0;

	public function __construct($dbFilePath) {
		$this->dbFile = $dbFilePath;

		// check if mpd_db_file exists
		if(is_file($this->dbFile) === TRUE || is_readable($this->dbFile) === TRUE) {
			// check if we have a plaintext or gzipped mpd-databasefile
			$this->gzipped = testBinary($this->dbFile);
			return $this;
		}
		$this->error = TRUE;
		$this->rawTagItem = new \Slimpd\Models\Rawtagdata();
		return $this;
	}

	public function decompressDbFile() {
		// decompress databasefile
		$bufferSize = 4096; // read 4kb at a time (raising this value may increase performance)
		$outFileName = APP_ROOT . "cache/mpd-database-plaintext";

		// Open our files (in binary mode)
		$inFile = gzopen($this->dbFile, "rb");
		$outFile = fopen($outFileName, "wb");

		// Keep repeating until the end of the input file
		while(!gzeof($inFile)) {
			// Read buffer-size bytes
			// Both fwrite and gzread and binary-safe
			fwrite($outFile, gzread($inFile, $bufferSize));
		}
		// Files are done, close files
		fclose($outFile);
		gzclose($inFile);

		$this->dbFile = $outFileName;
	}

	public function readMysqlTstamps() {
		$app = \Slim\Slim::getInstance();
		// get timestamps of all tracks and directories from mysql database
		// get all existing track-ids to determine orphans		
		$query = "SELECT id, relPathHash, relDirPathHash, filemtime, directoryMtime FROM rawtagdata;";
		$result = $app->db->query($query);
		while($record = $result->fetch_assoc()) {
			$this->itemsTotal++;
			$this->fileOrphans[ $record["relPathHash"] ] = $record["id"];
			$this->fileTstamps[ $record["relPathHash"] ] = $record["filemtime"];

			// get the oldest directory timestamp stored in rawtagdata
			if(isset($this->dirTstamps[ $record["relDirPathHash"] ]) === FALSE) {
				$this->dirTstamps[ $record["relDirPathHash"] ] = 9999999999;
			}
			if($record["directoryMtime"] < $this->dirTstamps[ $record["relDirPathHash"] ]) {
				$this->dirTstamps[ $record["relDirPathHash"] ] = $record["directoryMtime"]; 
			}
		}

		// get all existing album-ids to determine orphans
		$this->dirOrphans = array();
		$query = "SELECT id, relPathHash FROM album;";
		$result = $app->db->query($query);
		while($record = $result->fetch_assoc()) {
			$this->dirOrphans[ $record["relPathHash"] ] = $record["id"];
		}
	}

	public function parse(&$importer) {
		$this->rawTagItem = new \Slimpd\Models\Rawtagdata();

		foreach(explode("\n", file_get_contents($this->dbFile)) as $line) {
			if(trim($line) === "") {
				continue; // skip empty lines
			}

			$attr = explode (": ", $line, 2);
			array_map("trim", $attr);
			if(count($attr === 1)) {
				$this->handleStructuralLine($attr[0]);
				$importer->setItemsTotal($this->itemsTotal);
				$importer->setItemsChecked($this->itemsChecked);
				$importer->setItemsProcessed($this->itemsProcessed);
				$importer->updateJob(array(
					"msg" => "processed " . $this->itemsChecked . " files",
					"currentfile" => $this->currentDir . DS . $this->currentSong,
					"deadfiles" => count($this->fileOrphans),
					"unmodified_files" => $this->itemsUnchanged
				));
			}

			if(isset($attr[1]) === TRUE && in_array($attr[0], ["Time","Artist","Title","Track","Album","Genre","Date"])) {
				// believe it or not - some people store html in their tags
				// TODO: keep it but strip tags in migration phase
				$attr[1] = preg_replace("!\s+!", " ", (trim(strip_tags($attr[1]))));
			}

			switch($attr[0]) {
				case "directory":
					$this->currentSection = "directory";
					break;
				case "begin":
					$this->level++;
					$this->openDirs = explode(DS, $attr[1]);
					$this->currentSection = "directory";
					$this->currentDir = $attr[1];
					break;
				case "song_begin":
					$this->currentSection = "song";
					$this->currentSong = $attr[1];
					break;
				case "playlist_begin":
					$this->currentSection = "playlist";
					$this->currentPlaylist = $attr[1];
					break;
				case "end":
					$this->level--;
					//$dirs[$this->currentDir] = TRUE;
					$this->dirCount++;
					array_pop($this->openDirs);
					$this->currentDir = join(DS, $this->openDirs);
					$this->currentSection = "";
					break;

				case "mtime" :
					$setter = ($this->currentSection == "directory")
						? "setDirectoryMtime"
						: "setFilemtime";
					$this->rawTagItem->$setter($attr[1]);
					break;
				case "Time"  : $this->rawTagItem->setMiliseconds($attr[1]*1000); break;
				case "Artist": $this->rawTagItem->setArtist($attr[1]); break;
				case "Title" : $this->rawTagItem->setTitle($attr[1]); break;
				case "Track" : $this->rawTagItem->setTrackNumber($attr[1]); break;
				case "Album" : $this->rawTagItem->setAlbum($attr[1]); break;
				case "Genre" : $this->rawTagItem->setGenre($attr[1]); break;
				case "Date"  : $this->rawTagItem->setYear($attr[1]); break;
			}
		}
	}

	private function handleStructuralLine($line) {
		if(in_array($line, ["playlist_end", "song_end"]) === FALSE) {
			return;
		}
		if($line === "playlist_end") {
			// TODO: what to do with playlists fetched by mpd-database???
			//$playlists[] = $this->currentDir . DS . $this->currentPlaylist;
			$this->currentPlaylist = "";
			$this->currentSection = "";
			return;
		}

		// process"song_end"
		$this->itemsChecked++;

		// single music files directly in mpd-musicdir-root must not get a leading slash
		$this->rawTagItem->setRelDirPath(($this->currentDir === "") ? "" : $this->currentDir . DS);
		$this->rawTagItem->setRelDirPathHash(getFilePathHash($this->rawTagItem->getRelDirPath()));

		// further we have to read directory-modified-time manually because there is no info
		// about mpd-root-directory in mpd-database-file
		if($this->currentDir === "") {
			$this->rawTagItem->setDirectoryMtime(filemtime(\Slim\Slim::getInstance()->config["mpd"]["musicdir"]));
		}

		$this->rawTagItem->setRelPath($this->rawTagItem->getRelDirPath() . $this->currentSong);
		$this->rawTagItem->setRelPathHash(getFilePathHash($this->rawTagItem->getRelPath()));

		cliLog("#" . $this->itemsChecked . " " . $this->currentDir . DS . $this->currentSong, 2);
		$this->currentSong = "";
		$this->currentSection = "";
		if($this->updateOrInsert() === FALSE) {
			// track has not been modified - no need for updating
			unset($this->fileTstamps[$this->rawTagItem->getRelPathHash()]);
			unset($this->fileOrphans[$this->rawTagItem->getRelPathHash()]);
			$this->itemsUnchanged++;

			if(array_key_exists($this->rawTagItem->getRelDirPathHash(), $this->dirOrphans)) {
				unset($this->dirOrphans[$this->rawTagItem->getRelDirPathHash()]);
			}
			// reset song attributes
			$this->rawTagItem = new \Slimpd\Models\Rawtagdata();
			return;
		}

		if(isset($this->fileOrphans[$this->rawTagItem->getRelPathHash()])) {
			$this->rawTagItem->setId($this->fileOrphans[$this->rawTagItem->getRelPathHash()]);
			// file is alive - remove it from dead items
			unset($this->fileOrphans[$this->rawTagItem->getRelPathHash()]);
		}

		$this->rawTagItem->setlastScan(0);
		$this->rawTagItem->setImportStatus(1);
		$this->rawTagItem->update();
		$this->itemsProcessed++;
		// reset song attributes
		$this->rawTagItem = new \Slimpd\Models\Rawtagdata();
	}

	/**
	 * compare timestamps of mysql-database-entry(rawtagdata) and mpddatabase
	 */
	private function updateOrInsert() {
		if(isset($this->fileTstamps[$this->rawTagItem->getRelPathHash()]) === FALSE) {
			cliLog("mpd-file does not exist in rawtagdata: " . $this->rawTagItem->getRelPath(), 5);
			return TRUE;
		}
		if($this->rawTagItem->getFilemtime() > $this->fileTstamps[$this->rawTagItem->getRelPathHash()]) {
			cliLog("mpd-file timestamp is newer: " . $this->rawTagItem->getRelPath(), 5);
			return TRUE;
		}
		if(isset($this->dirTstamps[$this->rawTagItem->getRelDirPathHash()]) === FALSE) {
			cliLog("mpd-directory does not exist in rawtagdata: " . $this->rawTagItem->getRelDirPath(), 5);
			return TRUE;
		}
		if($this->rawTagItem->getDirectoryMtime() > $this->dirTstamps[$this->rawTagItem->getRelDirPathHash()]) {
			cliLog("mpd-directory timestamp is newer: " . $this->rawTagItem->getRelPath(), 5);
			return TRUE;
		}
		return FALSE;
	}
}
