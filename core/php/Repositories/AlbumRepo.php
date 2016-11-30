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
class AlbumRepo extends \Slimpd\Repositories\BaseRepository {
    public static $tableName = 'album';
    public static $classPath = '\Slimpd\Models\Album';
    
    #public function __construct($container) {
    #    parent::__construct($container);
    #}
    
    public function getAlbumByRelPath($relPath) {
        $query = "
            SELECT * 
            FROM album
            WHERE relPathHash=\"" . getFilePathHash($relPath) . "\"";
        $result = $this->db->query($query);
        $record = $result->fetch_assoc();
        if($record === NULL) {
            return NULL;
        }
        $this->mapArrayToInstance($record);
    }

    public function fetchRenderItems(&$renderItems, $albumInstance) {
        $renderItems["albums"][$albumInstance->getUid()] = $albumInstance;
        if(isset($renderItems["itembreadcrumbs"][$albumInstance->getRelPathHash()]) === FALSE) {
            $renderItems["itembreadcrumbs"][$albumInstance->getRelPathHash()] = \Slimpd\Modules\Filebrowser\Filebrowser::fetchBreadcrumb($albumInstance->getRelPath());
        }

        foreach(trimExplode(",", $albumInstance->getArtistUid(), TRUE) as $artistUid) {
            if(isset($renderItems["artists"][$artistUid]) === TRUE) {
                continue;
            }
            $renderItems["artists"][$artistUid] = $this->container->artistRepo->getInstanceByAttributes(["uid" => $artistUid]);
        }
        
        foreach(trimExplode(",", $albumInstance->getGenreUid(), TRUE) as $genreUid) {
            if(isset($renderItems["genres"][$genreUid]) === TRUE) {
                continue;
            }
            $renderItems["genres"][$genreUid] = $this->container->genreRepo->getInstanceByAttributes(["uid" => $genreUid]);
        }
        
        foreach(trimExplode(",", $albumInstance->getLabelUid(), TRUE) as $labelUid) {
            if(isset($renderItems["labels"][$labelUid]) === TRUE) {
                continue;
            }
            $renderItems["labels"][$labelUid] = $this->container->labelRepo->getInstanceByAttributes(["uid" => $labelUid]);
        }
        
        return;
    }

    public function getTrackUidsForAlbumUid($albumUid) {
        $return = array();
        $query = "
            SELECT uid
            FROM track
            WHERE albumUid=" . intval($albumUid);
        $result = $this->db->query($query);
        while($record = $result->fetch_assoc()) {
            $return[] = $record['uid'];
        }
        return $return;
    }
}
