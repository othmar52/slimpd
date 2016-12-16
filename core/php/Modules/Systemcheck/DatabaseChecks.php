<?php
namespace Slimpd\Modules\Systemcheck;
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

/**
 * TODO: refacture with a new Check model
 */
trait DatabaseChecks {

    /**
     * call all existing database checks
     */
    protected function runDatabaseChecks(&$check, $dbError) {
        // check if we can connect to database
        if($dbError === TRUE) {
            $check['dbConn']['status'] = 'danger';
            $check['dbPerms']['skip'] = TRUE;
            $check['dbSchema']['skip'] = TRUE;
            $check['dbContent']['skip'] = TRUE;
            $check['mpdConn']['skip'] = TRUE;
            $check['skipAudioTests'] = TRUE;
        }

        $this->runDatabasePermissionCheck($check);
        $this->runDatabaseSchemaCheck($check);
        $this->runDatabaseContentCheck($check);
    }

    /**
     * check permissions for "create database" (needed for schema-comparison)
     */
    protected function runDatabasePermissionCheck(&$check) {
        if($check['dbPerms']['skip'] === TRUE) {
            return;
        }
        $tmpDb = $this->conf['database']['dbdatabase']."_prmchk";
        $result = $this->container->db->query("CREATE DATABASE ". $tmpDb .";");
        if (!$result) {#
            $check['dbPerms']['status'] = 'danger';
            return;
        }
        $this->container->db->query("DROP DATABASE ". $tmpDb .";");
        $check['dbPerms']['status'] = 'success';
        $check['dbSchema']['skip'] = FALSE;
    }

    /**
     * check if db-schema is correct
     */
    protected function runDatabaseSchemaCheck(&$check) {
        if($check['dbSchema']['skip'] === TRUE) {
            return;
        }
        \Helper::setConfig( getDatabaseDiffConf($this->conf) );
        $tmpdb = \Helper::getTmpDbObject();
        \Helper::loadTmpDb($tmpdb);
        $diff = new \dbDiff(\Helper::getDbObject(), $tmpdb);
        $difference = $diff->getDifference();
        if(!count($difference['up']) && !count($difference['down'])) {
            $check['dbSchema']['status'] = 'success';
            $check['dbContent']['skip'] = FALSE;
            return;
        }
        $check['dbSchema']['status'] = 'danger';
        $check['dbSchema']['queries'] = $difference['down'];
        $check['skipAudioTests'] = TRUE;
    }

    protected function runDatabaseContentCheck(&$check) {
        // check if we have useful records in our database
        if($check['dbContent']['skip'] === TRUE) {
            return;
        }
        $check['dbContent']['tracks']  = $this->container->trackRepo->getCountAll();
        $check['dbContent']['albums']  = $this->container->albumRepo->getCountAll();
        $check['dbContent']['artists'] = $this->container->artistRepo->getCountAll();
        $check['dbContent']['genres']  = $this->container->genreRepo->getCountAll();
        $check['dbContent']['labels']  = $this->container->labelRepo->getCountAll();
        $check['dbContent']['status'] = ($check['dbContent']['tracks'] > 0)
            ? 'success'
            : 'danger';
    }
}
