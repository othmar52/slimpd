<?php
namespace Slimpd\Modules\database;
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
	protected $treshold = 100;

	public function que($instance) {
		$className = get_class($instance);
		$tableName = $className::$tableName;
		$this->instances[$tableName][] = $instance;
		$this->checkQueue($tableName);
	}
	
	private function checkQueue($tableName) {
		if(count($this->instances[$tableName]) >= $this->treshold) {
			$this->insertBatch($tableName);
		}
	}

	public function insertBatch($tableName) {
		$app = \Slim\Slim::getInstance();
		$query = "INSERT INTO " . $tableName . " ";
		$counter = 0;
		foreach($this->instances[$tableName] as $instance) {
			$mapped = $instance->mapInstancePropertiesToDatabaseKeys(FALSE);
			
			// TODO: remove track.relDirPath
			// for now use this ugly hack...
			if($tableName === "track") { unset($mapped["relDirPath"]); }

			if($counter === 0) {
				$query .= "(" . join(",", array_keys($mapped)) . ") VALUES ";
			}
			$counter++;
			$query .= "(";
			foreach($mapped as $value) {
				$query .= "\"" . $app->db->real_escape_string($value) . "\",";
			}
			$query = substr($query,0,-1) . "),";
		}
		$query = substr($query,0,-1) . ";";
		$app->db->query($query);
		$this->instances[$tableName] = array();
		#print_r($query); die;
		#print_r($this->instances[$tableName]); die;
	}
	
	public function finishAll() {
		foreach(array_keys($this->instances) as $tableName) {
			$this->insertBatch($tableName);
		}
	}
}
