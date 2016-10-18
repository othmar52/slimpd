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
	
	
	public function hardResetAction(Request $request, Response $response, $args) {
		if($this->getDatabaseDropConfirm() === FALSE) {
			return $response;
		}
		// we cant use $app->db for dropping and creating
		$db = new \mysqli(
			$app->config['database']['dbhost'],
			$app->config['database']['dbusername'],
			$app->config['database']['dbpassword']
		);
		cliLog("Dropping database");
	
		$result = $db->query("DROP DATABASE IF EXISTS " . $app->config['database']['dbdatabase'].";");
		cliLog("Recreating database");
		$result = $db->query("CREATE DATABASE " . $app->config['database']['dbdatabase'].";");
		$action = 'init';
	
		Helper::setConfig( getDatabaseDiffConf($app) );
		if (!Helper::checkConfigEnough()) {
			cliLog("mmp: invalid configuration");
			$app->stop();
		}
		$controller = Helper::getController($action, NULL);
		$controller->runStrategy();
	
		foreach(\Slimpd\Modules\importer\DatabaseStuff::getInitialDatabaseQueries() as $query) {
			$app->db->query($query);
		}
	
		// delete files created by sliMpd
		foreach(['cache', 'embedded', 'peakfiles'] as $sysDir) {
			$fileBrowser = new \Slimpd\filebrowser();
			$fileBrowser->getDirectoryContent('localdata' . DS . $sysDir, TRUE, TRUE);
			cliLog("Deleting files and directories inside ". 'localdata' . DS . $sysDir ."/");
			foreach(['music','playlist','info','image','other'] as $key) {
				foreach($fileBrowser->files[$key] as $file) {
					rmfile(APP_ROOT . $file->getRelPath());
				}
			}
			foreach($fileBrowser->subDirectories['dirs'] as $dir) {
				rrmdir(APP_ROOT . $dir->getRelPath());
			}
		}
		$importer->triggerImport();
	}

	public function triggerUpdateAction(Request $request, Response $response, $args) {
		// FIXME: relly trigger update
		return $response;
		
		#\Slimpd\Modules\Importer::queStandardUpdate();
		#$this->view->render($response, 'surrounding.htm', $args);
		#return $response;
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
}
