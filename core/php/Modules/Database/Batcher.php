<?php
namespace Slimpd\Modules\Database;
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

/**
 * for better performance this class collects records to insert and executes a single INSERT query for thousands of records
 * so we can avoid that MySQL has to rebalance index tree that often
 * 
 * @see http://stackoverflow.com/questions/18857834/mysql-myisam-insert-slowness
 * @see http://databobjr.blogspot.com/2010/10/mysql-speepup-insert-into-myisam-table.html
 */
class Batcher {
    protected $nextUid = array();
    protected $instances = array();
    protected $treshold = 1000;
    
    public function __construct($container) {
        $this->db = $container->db;
    }

    public function que(&$instance) {
        $className = get_class($instance);
        $tableName = $className::$tableName;
        $this->mayAddUid($instance, $tableName);
        $this->instances[$tableName][$instance->getUid()] = $instance;
        $this->checkQueue($tableName);
        return $instance;
    }

    public function modifyQueuedInstanceProperty($tableName, $uid, $setterName, $value) {
        if(isset($this->instances[$tableName][$uid]) === FALSE) {
            cliLog('BATCHER: failed to modify instance property', 1, "red");
            return;
        }
        $this->instances[$tableName][$uid]->$setterName($value);
        return;
    }

    private function mayAddUid(&$instance, $tableName) {
        // instance already has an uid
        if($instance->getUid() > 0) {
            return;
        }

        // check if we already know what the next uid will be
        if(isset($this->nextUid[$tableName]) === TRUE) {
            $instance->setUid($this->nextUid[$tableName]);
            $this->nextUid[$tableName]++;
            return;
        }

        // find out highest id for $instance
        $this->nextUid[$tableName] = $this->db->query("SELECT uid FROM ". $tableName ." ORDER BY uid DESC LIMIT 0, 1")->fetch_assoc()['uid'];
        if($this->nextUid[$tableName] === NULL) {
            // empty tables have an AUTO_INCREMENT of 10
            $this->nextUid[$tableName] = 9;
        }
        $this->nextUid[$tableName]++;

        // recursion - now the id should be there
        $this->mayAddUid($instance, $tableName);
    }
    
    private function checkQueue($tableName) {
        if(count($this->instances[$tableName]) >= $this->treshold) {
            $this->insertBatch($tableName);
        }
    }

    public function insertBatch($tableName) {
        if(isset($this->instances[$tableName]) === FALSE) {
            return;
        }
        if(count($this->instances[$tableName]) === 0) {
            return;
        }
        $query = "INSERT INTO " . $tableName . " ";
        $counter = 0;
        foreach($this->instances[$tableName] as $instance) {
            $mapped = $instance->mapInstancePropertiesToDatabaseKeys(FALSE);
            
            // TODO: remove track.relDirPath (caused by abstraction ???)
            // TODO: remove album.relDirPath (caused by abstraction ???)
            // for now use this ugly hack...
            if($tableName === "track") { unset($mapped["relDirPath"]); }
            if($tableName === "album") {
                unset($mapped["relDirPath"]);
                unset($mapped["relDirPathHash"]);
                unset($mapped["filesize"]);
            }

            if($counter === 0) {
                $query .= "(" . join(",", array_keys($mapped)) . ") VALUES ";
            }
            $counter++;
            $query .= "(";
            foreach($mapped as $value) {
                $query .= "\"" . $this->db->real_escape_string($value) . "\",";
            }
            $query = substr($query,0,-1) . "),";
        }
        $query = substr($query,0,-1) . ";";
        cliLog("batch-insert " . $tableName . "s (" . $counter . " records)" , 2, "lightblue");
        #echo $query; die;
        $this->db->query($query);
        $this->instances[$tableName] = array();
    }
    
    public function finishAll() {
        foreach(array_keys($this->instances) as $tableName) {
            $this->insertBatch($tableName);
        }
    }
}
