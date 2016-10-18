<?php
namespace Slimpd\Modules\importer;
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
class MpdDatabaseParser {
	protected $dbFile;
	public $error = FALSE;
	public $gzipped = FALSE;
	protected $rawTagItem = FALSE;
	
	private $useBatcher = FALSE;

	// needed for traversing
	private $level = -1;
	private $openDirs = array();
	private $currentSection = "";
	private $currentSong = "";
	private $currentDir = "";
	private $currentPlaylist = "";

	// timestamp attributes (filemtime, directorymtime, added)
	private $currentDirTime = 0;
	private $useNowAsAdded = TRUE;

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
		// very first import may use filesystem timestamp as attribute:added instead of time()
		if(\Slimpd\Models\Rawtagdata::getCountAll() < 1
		&& \Slim\Slim::getInstance()->config["importer"]["use-filemtime-on-initial-import"] === "1") {
			$this->useNowAsAdded = FALSE;
		}

		$this->dbFile = $dbFilePath;
		
		// batcher is only used on the very first import because we can be sure that no update of existing record is required
		if(\Slimpd\Models\Rawtagdata::getCountAll() < 1) {
			$this->useBatcher = TRUE;
		}

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
		$outFileName = APP_ROOT . "localdata/cache/mpd-database-plaintext";

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
		// get all existing track-uids to determine orphans		
		$query = "SELECT uid, relPathHash, relDirPathHash, filemtime, directoryMtime FROM rawtagdata;";
		$result = $app->db->query($query);
		while($record = $result->fetch_assoc()) {
			$this->itemsTotal++;
			$this->fileOrphans[ $record["relPathHash"] ] = $record["uid"];
			$this->fileTstamps[ $record["relPathHash"] ] = $record["filemtime"];

			// get the oldest directory timestamp stored in rawtagdata
			if(isset($this->dirTstamps[ $record["relDirPathHash"] ]) === FALSE) {
				$this->dirTstamps[ $record["relDirPathHash"] ] = 9999999999;
			}
			if($record["directoryMtime"] < $this->dirTstamps[ $record["relDirPathHash"] ]) {
				$this->dirTstamps[ $record["relDirPathHash"] ] = $record["directoryMtime"]; 
			}
		}

		// get all existing album-uids to determine orphans
		$this->dirOrphans = array();
		$query = "SELECT uid, relPathHash FROM album;";
		$result = $app->db->query($query);
		while($record = $result->fetch_assoc()) {
			$this->dirOrphans[ $record["relPathHash"] ] = $record["uid"];
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
				$importer->setItemsTotal($this->itemsTotal)
					->setItemsChecked($this->itemsChecked)
					->setItemsProcessed($this->itemsProcessed)
					->updateJob([
						"msg" => "processed " . $this->itemsChecked . " files",
						"currentfile" => $this->currentDir . DS . $this->currentSong,
						"deadfiles" => count($this->fileOrphans),
						"unmodified_files" => $this->itemsUnchanged
				]);
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
					$setter = ($this->currentSection === "directory")
						? "setDirectoryMtime"
						: "setFilemtime";
					$this->rawTagItem->$setter($attr[1]);
					if($this->currentSection === "directory") {
						$this->currentDirTime = $this->rawTagItem->getDirectoryMtime();
					}
					break;
				default:
					break;
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

		$this->rawTagItem->setDirectoryMtime($this->currentDirTime);

		// single music files directly in mpd-musicdir-root must not get a leading slash
		$this->rawTagItem->setRelDirPath(($this->currentDir === "") ? "" : $this->currentDir . DS)
			->setRelDirPathHash(getFilePathHash($this->rawTagItem->getRelDirPath()));

		// further we have to read directory-modified-time manually because there is no info
		// about mpd-root-directory in mpd-database-file
		if($this->currentDir === "") {
			$this->rawTagItem->setDirectoryMtime(filemtime(\Slim\Slim::getInstance()->config["mpd"]["musicdir"]));
		}

		$this->rawTagItem->setRelPath($this->rawTagItem->getRelDirPath() . $this->currentSong)
			->setRelPathHash(getFilePathHash($this->rawTagItem->getRelPath()));

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
			$this->rawTagItem->setUid($this->fileOrphans[$this->rawTagItem->getRelPathHash()]);
			// file is alive - remove it from dead items
			unset($this->fileOrphans[$this->rawTagItem->getRelPathHash()]);
		}

		$this->rawTagItem->setAdded(time());
		if($this->useNowAsAdded === FALSE) {
			$this->rawTagItem->setAdded($this->rawTagItem->getFilemtime());
			// in case we have an invalid filesystem timestamp (future) override it
			if(isFutureTimestamp($this->rawTagItem->getFilemtime()) === TRUE) {
				// TODO: does it make sense to store those items for echoing on finish?
				cliLog("WARNING : mtime is in future. consider to touch " . $this->rawTagItem->getRelPath(), 1, "red");
				$this->rawTagItem->setAdded(time());
			}
		}
		$this->rawTagItem->setLastScan(0)
			->setImportStatus(1)
			->setExtension(getFileExt($this->rawTagItem->getRelPath()));
			
		if($this->useBatcher === TRUE) {
			\Slim\Slim::getInstance()->batcher->que($this->rawTagItem);
		} else {
			$this->rawTagItem->update();
		}
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
