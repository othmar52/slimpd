<?php
namespace Slimpd\Repositories;
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
class BitmapRepo extends \Slimpd\Repositories\BaseRepository {
    public static $tableName = 'bitmap';
    public static $classPath = '\Slimpd\Models\Bitmap';


    public function searchUidBeforeInsert(&$instance) {
        if($instance->getUid() > 0) {
            // we already have an uid ...
            return;
        }

        // check if we have a record with this path
        // multiple usage of same image files is possible. so albumDirHash has to match
        $bitmap2 = $this->getInstanceByAttributes(array(
            'relPathHash' => $instance->getRelPathHash(),
            'relDirPathHash' => $instance->getRelDirPathHash(),
        ));

        if($bitmap2 !== NULL) {
            $instance->setUid($bitmap2->getUid());
        }
        return;
    }

    public function update(&$instance) {
        $this->searchUidBeforeInsert($instance);
        if($instance->getUid() < 1) {
            return $this->insert($instance);
        }

        $query = 'UPDATE '.self::$tableName .' SET ';
        foreach($instance->mapInstancePropertiesToDatabaseKeys() as $dbfield => $value) {
            $query .= $dbfield . '="' . $this->db->real_escape_string($value) . '",';
        }
        $query = substr($query,0,-1) . ' WHERE uid=' . (int)$instance->getUid() . ";";
        $this->db->query($query);
    }

    public function destroy($instance) {
        if($instance->getUid() < 1) {
            // invalid instance
            return FALSE;
        }

        if($instance->getEmbedded() < 1) {
            // currently it is only allowed to delete images extracted from musicfiles
            return FALSE;
        }

        if(!$instance->getRelPath()) {
            // invalid instance 
            return FALSE;
        }
        $this->container->filesystemUtility->rmfile(APP_ROOT . $instance->getRelPath());
        $query = 'DELETE FROM '.self::$tableName .' WHERE uid=' . (int)$instance->getUid() . ";";
        $this->db->query($query);
        return TRUE;
    }

    public function addAlbumUidToRelDirPathHash($relDirPathHash, $albumUid) {
        # blind adding albumUid - no matter if it a record is affected or not..
        # TODO: does it matter or not?
        $this->db->query(
            'UPDATE '.self::$tableName .
            ' SET albumUid='. (int)$albumUid .
            ' WHERE relDirPathHash="'. $relDirPathHash . '";'
        );
        return;
    }

}
