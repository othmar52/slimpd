<?php
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

$importer = new \Slimpd\Modules\Importer();



// IMPORTANT TODO: avoid simultaneously executet updates

$app->get('/', function () use ($app, $importer) {
	renderCliHelp();
	// check if we have something to process
	//if($importer->checkQue() === TRUE) {
	//	$importer->triggerImport();
	//}

});


$app->get('/debugmig', function () use ($app, $importer) {
	$importer->checkQue();
	$importer->migrateRawtagdataTable();
});


$app->get('/update', function () use ($app, $importer) {
	$importer->triggerImport();
});

$app->get('/remigrate', function () use ($app, $importer) {
	$importer->triggerImport(TRUE);
});


$app->get('/builddictsql', function () use ($app) {
	\Slimpd\Modules\importer\DatabaseStuff::buildDictionarySql();
});

$app->get('/database-cleaner', function () use ($app) {
	// phase 11: delete all bitmap-database-entries that does not exist in filesystem
	// TODO: limit this to embedded images only
	$importer->deleteOrphanedBitmapRecords();
	
	// TODO: delete orphaned artists + genres + labels
});


$app->get('/update-db-scheme', function () use ($app, $argv) {
	$action = 'migrate';

	// TODO: manually performed db-changes does not get recognized here - find a solution!

	// check if we can query the revisions table
	$query = "SELECT * FROM db_revisions";
	$result = $app->db->query($query);
	if($result === FALSE) {
		// obviosly table(s) never have been created
		// let's force initial creation of all tables
		$action = 'init';
	}

	Helper::setConfig( getDatabaseDiffConf($app) );
	if (!Helper::checkConfigEnough()) {
	    Output::error('mmp: please check configuration');
	    die(1);
	}

	# after database-structure changes we have to
	# 1) uncomment next line
	# 2) run ./slimpd update-db-scheme
	# 3) recomment this line again
	# to make a new revision
	#$action = 'create';

	$controller = Helper::getController($action, NULL);
	if ($controller !== false) {
	    $controller->runStrategy();
	} else {
	    Output::error('mmp: unknown command "'.$cli_params['command']['name'].'"');
	    Helper::getController('help')->runStrategy();
	    die(1);
	}

	if($action !== 'init') {
		exit;
	}

	foreach(\Slimpd\Modules\importer\DatabaseStuff::getInitialDatabaseQueries() as $query) {
		$app->db->query($query);
	}
});


/**
 * start from scratch by dropping and recreating database
 */
$app->get('/hard-reset', function () use ($app, $argv, $importer) {
	getDatabaseDropConfirm();
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
		$fileBrowser->getDirectoryContent($sysDir, TRUE, TRUE);
		cliLog("Deleting files and directories inside ". $sysDir ."/");
		foreach(['music','playlist','info','image','other'] as $key) {
			foreach($fileBrowser->files[$key] as $file) {
				// just to make sure we do not delete unwanted stuff :)
				$delete = realpath(APP_ROOT . $file->getRelPath());
				if(strpos($delete, APP_ROOT.$sysDir.DS) === FALSE) {
					continue;
				}
				unlink($delete);
			}
		}
		foreach($fileBrowser->subDirectories['dirs'] as $dir) {
			// just to make sure we do not delete unwanted stuff :)
			$delete = realpath(APP_ROOT . $dir->getRelPath());
			if(strpos($delete, APP_ROOT.$sysDir.DS) === FALSE) {
				continue;
			}
			rrmdir($delete);
		}
	}
	$importer->triggerImport();
});
