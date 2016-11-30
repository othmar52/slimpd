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
 * FITNESS FOR A PARTICULAR PURPOSE.    See the GNU Affero General Public License
 * for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.    If not, see <http://www.gnu.org/licenses/>.
 */
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class CliController extends \Slimpd\BaseController {
    private $force = FALSE; // ignore parallel execution (or orphaned .lock file caused by crashed script-run)

    private $interval = 5; //seconds for recursion of check-que
    private $maxTime = 59;    // seconds - fits for cronjob executed every minute
    private $startTime = 0;

    public function indexAction(Request $request, Response $response, $args) {
        useArguments($request, $response, $args);
        $xx = $this->conf; // TODO: how to trigger required session variable beeing set?
        renderCliHelp($this->ll);
        return $response;
    }

    public function remigrateForceAction(Request $request, Response $response, $args) {
        useArguments($request, $response, $args);
        self::deleteLockFile();
        return $this->remigrateAction($request, $response, $args);
    }

    public function remigrateAction(Request $request, Response $response, $args) {
        useArguments($request, $response, $args);
        $xx = $this->conf; // TODO: how to trigger required session variable beeing set?
        if($this->abortOnLockfile($this->ll) === TRUE) {
            return $response;
        }
        self::touchLockFile();
        $importer = new \Slimpd\Modules\Importer\Importer($this->container);
        $importer->triggerImport(TRUE);
        self::deleteLockFile();
        return $response;
    }

    public function remigratealbumAction(Request $request, Response $response, $args) {
        useArguments($request, $response, $args);
        $xx = $this->conf; // TODO: how to trigger required session variable beeing set?
        if($this->abortOnLockfile($this->ll) === TRUE) {
            return $response;
        }
        self::touchLockFile();
        $migrator = new \Slimpd\Modules\Importer\Migrator($this->container);
        $args['migrator'] = $migrator->migrateSingleAlbum($args['albumUid']);
        self::deleteLockFile();
        return $response;
    }

    public function updateForceAction(Request $request, Response $response, $args) {
        useArguments($request, $response, $args);
        self::deleteLockFile();
        return $this->updateAction($request, $response, $args);
    }

    public function updateAction(Request $request, Response $response, $args) {
        useArguments($request, $response, $args);
        $xx = $this->conf; // TODO: how to trigger required session variable beeing set?
        if($this->abortOnLockfile($this->ll) === TRUE) {
            return $response;
        }
        self::touchLockFile();
        $importer = new \Slimpd\Modules\Importer\Importer($this->container);
        $importer->triggerImport();
        self::deleteLockFile();
        return $response;
    }

    public function builddictsqlAction(Request $request, Response $response, $args) {
        useArguments($request, $response, $args);
        $xx = $this->conf; // TODO: how to trigger required session variable beeing set?
        $importer = new \Slimpd\Modules\Importer\DatabaseStuff($this->container);
        $importer->buildDictionarySql();
        return $response;
    }

    public function updateDbSchemeAction(Request $request, Response $response, $args) {
        useArguments($request, $response, $args);
        $xx = $this->conf; // TODO: how to trigger required session variable beeing set?
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
        useArguments($request, $response, $args);
        $xx = $this->conf; // TODO: how to trigger required session variable beeing set?
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
        useArguments($request, $response, $args);
        if($this->abortOnLockfile($this->ll) === TRUE) {
            return $response;
        }
        self::touchLockFile();
        if($this->getDatabaseDropConfirm() === FALSE) {
            self::deleteLockFile();
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
            self::deleteLockFile();
            return $response;
        }
        if($db->connect_error) {
            cliLog($this->ll->str('database.connect'), 1, 'red');
            self::deleteLockFile();
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
            self::deleteLockFile();
            return $response;
        }
        $controller = \Helper::getController($action, NULL);
        $controller->runStrategy();

        foreach(\Slimpd\Modules\Importer\DatabaseStuff::getInitialDatabaseQueries($this->ll) as $query) {
            $this->db->query($query);
        }

        // delete files created by sliMpd
        foreach(['cache', 'embedded', 'peakfiles'] as $sysDir) {
            $fileBrowser = new \Slimpd\Modules\Filebrowser\Filebrowser($this->container);
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
        self::deleteLockFile();
        return $response;
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
     * TODO: add support for "remigrate" trigger
     */
    public function checkQueAction(Request $request, Response $response, $args) {
        useArguments($request, $response, $args);
        $xx = $this->conf; // TODO: how to trigger required session variable beeing set?
        self::heartBeat();
        if($this->startTime === 0) {
            $this->startTime = time();
        }
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
            $cliMsg = "Nothing to do. ";

            // check for recursion
            if((time() - $this->startTime) >= $this->maxTime) {
                $cliMsg .= "exiting...";
                cliLog($cliMsg, 1, "green");
                return $response;
            }
            $cliMsg .= "waiting " . $this->interval . " seconds...";
            cliLog($cliMsg, 1, "green");
            sleep($this->interval);
            return $this->checkQueAction($request, $response, $args);
        }

        // avoid parallel execution
        if($this->abortOnLockfile($this->ll) === TRUE) {
            return $response;
        }
        self::touchLockFile();

        cliLog("marking importer triggers as running", 3);
        // update database - so we can avoid parallel execution
        $query = "UPDATE importer
            SET jobStart=". getMicrotimeFloat()."
            WHERE batchUid=0 AND jobStatistics='update';";
        $this->db->query($query);

        // start update process
        $importer = new \Slimpd\Modules\Importer\Importer($this->container);
        $importer->triggerImport();

        // we have reached the maximum waiting time for MPD's update process without starting sliMpd's update process 
        if($importer->keepGuiTrigger === TRUE) {
            cliLog("marking importer triggers as NOT running again", 3);
            $query = "UPDATE importer
                SET jobStart=0
                WHERE batchUid=0 AND jobStatistics='update';";
            $this->db->query($query);
            return $response;
        }

        cliLog("deleting already processed importer triggers from database", 3);
        $query = "DELETE FROM importer
            WHERE batchUid=0 AND jobStatistics='update';";
        $this->db->query($query);

        // TODO: create runSphinxTriggerFile which should be processed by scripts/sphinxrotate.sh

        self::deleteLockFile();
        return $this->checkQueAction($request, $response, $args);
        return $response;
    }

    private function abortOnLockfile($ll) {
        if(file_exists(APP_ROOT . "localdata/importer.lock") === TRUE) {
            $age = filemtime(APP_ROOT . "localdata/importer.lock");
            cliLog($ll->str("cli.parallel.execution.line1"), 1, "red", TRUE);
            cliLog("  " . $ll->str("cli.parallel.execution.line2",[timeElapsedString($age)]), 1, "yellow");
            cliLog("  " . $ll->str("cli.parallel.execution.line3"), 1, "yellow");
            return TRUE;
        }
        return FALSE;
    }

    public static function touchLockFile() {
        touch(APP_ROOT . "localdata/importer.lock");
        @chmod(APP_ROOT . "localdata/importer.lock", 0777);
    }

    public static function deleteLockFile() {
        cliLog("deleting .lock file", 3);
        @unlink(APP_ROOT . "localdata/importer.lock");
    }

    public static function heartBeat() {
        touch(APP_ROOT . "localdata/importer.heartbeat");
        @chmod(APP_ROOT . "localdata/importer.heartbeat", 0777);
    }

    public static function getHeartBeatTstamp() {
        if(file_exists(APP_ROOT . "localdata/importer.heartbeat") === TRUE) {
            return filemtime(APP_ROOT . "localdata/importer.heartbeat");
        }
        return FALSE;
    }
}
