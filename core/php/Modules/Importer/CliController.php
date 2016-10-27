<?php
namespace Slimpd\Modules\Importer;
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
 * FITNESS FOR A PARTICULAR PURPOSE.	See the GNU Affero General Public License
 * for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.	If not, see <http://www.gnu.org/licenses/>.
 */
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class CliController extends \Slimpd\BaseController {
	public function indexAction(Request $request, Response $response, $args) {
		$xx = $this->conf; // TODO: how to trigger required session ver beeing set?
		renderCliHelp($this->ll);
		return $response;
	}

	public function remigrateAction(Request $request, Response $response, $args) {
		$xx = $this->conf; // TODO: how to trigger required session ver beeing set?
		$importer = new \Slimpd\Modules\Importer\Importer($this->container);
		$importer->triggerImport(TRUE);
		return $response;
	}

	public function updateAction(Request $request, Response $response, $args) {
		$xx = $this->conf; // TODO: how to trigger required session ver beeing set?
		$importer = new \Slimpd\Modules\Importer\Importer($this->container);
		$importer->triggerImport();
		return $response;
	}

	public function builddictsqlAction(Request $request, Response $response, $args) {
		$xx = $this->conf; // TODO: how to trigger required session ver beeing set?
		$importer = new \Slimpd\Modules\Importer\DatabaseStuff($this->container);
		$importer->buildDictionarySql();
		return $response;
	}

	public function updateDbSchemeAction(Request $request, Response $response, $args) {
		$xx = $this->conf; // TODO: how to trigger required session ver beeing set?
		$action = 'migrate';

		// TODO: manually performed db-changes does not get recognized here - find a solution!
		
		// check if we can query the revisions table
		$query = "SELECT * FROM db_revisions";
		$result = $this->db->query($query);
		if($result === FALSE) {
			// obviosly table(s) never have been created
			// let's force initial creation of all tables
			$action = 'init';
		}
	
		\Helper::setConfig( getDatabaseDiffConf($this->conf) );
		if (!\Helper::checkConfigEnough()) {
			\Output::error('mmp: please check configuration');
			return $response;
		}
	
		# after database-structure changes we have to
		# 1) uncomment next line
		# 2) run ./slimpd update-db-scheme
		# 3) recomment this line again
		# to make a new revision
		#$action = 'create';
	
		$controller = \Helper::getController($action, NULL);
		if ($controller !== false) {
			$controller->runStrategy();
		} else {
			\Output::error('mmp: unknown command "'.$cli_params['command']['name'].'"');
			\Helper::getController('help')->runStrategy();
			return $response;
		}
	
		if($action !== 'init') {
			return $response;
		}
	
		foreach(\Slimpd\Modules\Importer\DatabaseStuff::getInitialDatabaseQueries() as $query) {
			$this->db->query($query);
		}
		return $response;
	}

	public function databaseCleanerAction(Request $request, Response $response, $args) {
		$xx = $this->conf; // TODO: how to trigger required session ver beeing set?
		die('TODO: not implemented yet '. __FUNCTION__ );
		$importer = new \Slimpd\Modules\Importer\Importer($this->container);
		// phase 11: delete all bitmap-database-entries that does not exist in filesystem
		// TODO: limit this to embedded images only
		$importer->deleteOrphanedBitmapRecords();
		
		// TODO: delete orphaned artists + genres + labels
		return $response;
	}

	/**
	 * start from scratch by dropping and recreating database
	 */
	public function hardResetAction(Request $request, Response $response, $args) {
		if($this->getDatabaseDropConfirm() === FALSE) {
			return $response;
		}

		try {
			// we cant use $this->db for dropping and creating
			@$db = new \mysqli(
				$this->conf['database']['dbhost'],
				$this->conf['database']['dbusername'],
				$this->conf['database']['dbpassword']
			);
		} catch (\Exception $e) {
			cliLog($this->ll->str('database.connect'), 1, 'red');
			return $response;
		}
		if($db->connect_error) {
			cliLog($this->ll->str('database.connect'), 1, 'red');
			return $response;
		}
		cliLog("Dropping database");

		$result = $db->query("DROP DATABASE IF EXISTS " . $this->conf['database']['dbdatabase'].";");
		cliLog("Recreating database");
		$result = $db->query("CREATE DATABASE " . $this->conf['database']['dbdatabase'].";");
		$action = 'init';

		\Helper::setConfig( getDatabaseDiffConf($this->conf) );
		if (!\Helper::checkConfigEnough()) {
			cliLog("ERROR: invalid mmp configuration", 1, "red");
			return $response;
		}
		$controller = \Helper::getController($action, NULL);
		$controller->runStrategy();

		foreach(\Slimpd\Modules\Importer\DatabaseStuff::getInitialDatabaseQueries($this->ll) as $query) {
			$this->db->query($query);
		}

		// delete files created by sliMpd
		foreach(['cache', 'embedded', 'peakfiles'] as $sysDir) {
			$fileBrowser = new \Slimpd\Modules\filebrowser\filebrowser($this->container);
			$fileBrowser->getDirectoryContent('localdata' . DS . $sysDir, TRUE, TRUE);
			cliLog("Deleting files and directories inside ". 'localdata' . DS . $sysDir ."/");
			foreach(['music','playlist','info','image','other'] as $key) {
				foreach($fileBrowser->files[$key] as $file) {
					$this->filesystemUtility->rmfile(APP_ROOT . $file->getRelPath());
				}
			}
			foreach($fileBrowser->subDirectories['dirs'] as $dir) {
				$this->filesystemUtility->rrmdir(APP_ROOT . $dir->getRelPath());
			}
		}
		$importer = new \Slimpd\Modules\Importer\Importer($this->container);
		$importer->triggerImport();
	}

	private function getDatabaseDropConfirm() {
		$userInput = '';
		do {
			if ($userInput != "\n") {
				cliLog($this->ll->str("cli.dropdbconfirm", [$this->conf['database']['dbdatabase']]), 1 , "red");
			}
			$userInput = fread(STDIN, 1);
			if (strtolower($userInput) === 'y') {
				return TRUE;
			}
			if (strtolower($userInput) === 'n') {
				cliLog($this->ll->str("cli.dropdbconfirm.abort"));
				return FALSE;
			}
		} while (TRUE);
	}

	/**
	 * this function checks if any processing has been requested via sliMpd-GUI
	 */
	public function checkQueAction(Request $request, Response $response, $args) {
		$xx = $this->conf; // TODO: how to trigger required session ver beeing set?

		cliLog("checking database for importer triggers", 3);
		// check if we have something to process
		$query = "SELECT uid FROM importer
			WHERE batchUid=0 AND jobStart=0 AND jobStatistics='update';";

		$result = $this->db->query($query);
		$runUpdate = FALSE;
		while($record = $result->fetch_assoc()) {
			$runUpdate = TRUE;
		}
		if($runUpdate === FALSE) {
			cliLog("Nothing to do. exiting...", 1, "green");
			return $response;
		}

		cliLog("marking importer triggers as running", 3);
		// update database - so we can avoid parallel execution
		$query = "UPDATE importer
			SET jobStart=". getMicrotimeFloat()."
			WHERE batchUid=0 AND jobStatistics='update';";
		$this->db->query($query);

		// start update process
		$importer = new \Slimpd\Modules\Importer\Importer($this->container);
		$importer->triggerImport();

		// TODO: create runSphinxTriggerFile

		cliLog("deleting already processed importer triggers from database", 3);
		$query = "DELETE FROM importer
			WHERE batchUid=0 AND jobStatistics='update';";
		$this->db->query($query);
		return $response;
	}
}
