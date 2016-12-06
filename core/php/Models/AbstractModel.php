<?php
namespace Slimpd\Models;
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
abstract class AbstractModel {
    public static $tableName;
    public static $repoKey;
    protected $uid;


    protected static function getTableName() {
        $class = get_called_class();
        return $class::$tableName;
    }
    
    public static function getRepoKey() {
        $class = get_called_class();
        return $class::$repoKey;
    }
    
    public function mapArrayToInstance($array) {
        foreach($array as $dbField => $value) {
            $setter = 'set'.ucfirst($dbField);
            if(method_exists(get_called_class(), $setter)) {
                #echo $setter . $value ."<br>";
                $this->$setter($value);
            }
        }
    }

    /**
     * @return array  - keys named like databasefields
     * 
     */
    public function mapInstancePropertiesToDatabaseKeys($ignoreEmpty = TRUE) {
        $return = array();
        $calledClass = get_called_class();
        #echo $calledClass; die();
        foreach(array_keys(get_class_vars($calledClass)) as $classVar) {
            $getter = 'get'.ucfirst($classVar);
            if(in_array($classVar, ['tableName', 'repoKey']) === TRUE) {
                continue;
            }
            if(method_exists($calledClass, $getter)) {
                $instancePropertyValue = $this->$getter();
                if($ignoreEmpty === TRUE && !$instancePropertyValue) {
                    continue;
                }
                $return[$classVar] = $instancePropertyValue;
            }
        }
        return $return;
    }



    public function getUid() {
        return $this->uid;
    }
    public function setUid($value) {
        $this->uid = $value;
        return $this;
    }
}
