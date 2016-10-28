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

class TrackRepo extends \Slimpd\Repositories\BaseRepository {
	public static $tableName = 'track';
	public static $classPath = '\Slimpd\Models\Track';
	
	
	/**
	 * in case tracks have been added via playlist containing absolute paths that
	 * does not begin with mpd-music dir try to fix the path...
	 */
	public function getInstanceByPath($pathString, $createDummy = FALSE) {
		$pathString = $this->container->filesystemUtility->trimAltMusicDirPrefix($pathString);
		$instance = $this->getInstanceByAttributes(
			array('relPathHash' => getFilePathHash($pathString))
		);
		if($instance !== NULL || $createDummy === FALSE) {
			return $instance;
		}
		// track is not imported in sliMpd database
		return $this->getNewInstanceWithoutDbQueries($pathString);
	}

	
	public function getNewInstanceWithoutDbQueries($pathString) {
		$track = new \Slimpd\Models\Track();
		$track->setRelPath($pathString);
		$track->setRelPathHash(getFilePathHash($pathString));
		$track->setAudioDataFormat($this->container->filesystemUtility->getFileExt($pathString));
		return $track;
	}

	public function fetchRenderItems(&$renderItems, $trackInstance) {
		if(isset($renderItems["itembreadcrumbs"][$trackInstance->getRelPathHash()]) === FALSE) {
			$renderItems["itembreadcrumbs"][$trackInstance->getRelPathHash()] = \Slimpd\Modules\Filebrowser\Filebrowser::fetchBreadcrumb($trackInstance->getRelPath());
		}
		
		$artistUidString = join(",", [$trackInstance->getArtistUid(), $trackInstance->getFeaturingUid(), $trackInstance->getRemixerUid()]);
		foreach(trimExplode(",", $artistUidString, TRUE) as $artistUid) {
			if(isset($renderItems["artists"][$artistUid]) === TRUE) {
				continue;
			}
			$renderItems["artists"][$artistUid] = $this->container->artistRepo->getInstanceByAttributes(["uid" => $artistUid]);
		}
		
		if(isset($renderItems["albums"][$trackInstance->getAlbumUid()]) === FALSE) {
			$renderItems["albums"][$trackInstance->getAlbumUid()] = $this->container->albumRepo->getInstanceByAttributes(["uid" => $trackInstance->getAlbumUid()]);
		}
		
		foreach(trimExplode(",", $trackInstance->getGenreUid(), TRUE) as $genreUid) {
			if(isset($renderItems["genres"][$genreUid]) === TRUE) {
				continue;
			}
			$renderItems["genres"][$genreUid] = $this->container->genreRepo->getInstanceByAttributes(["uid" => $genreUid]);
		}
		
		foreach(trimExplode(",", $trackInstance->getLabelUid(), TRUE) as $labelUid) {
			if(isset($renderItems["labels"][$labelUid]) === TRUE) {
				continue;
			}
			$renderItems["labels"][$labelUid] = $this->container->labelRepo->getInstanceByAttributes(["uid" => $labelUid]);
		}
		
		return;
	}
}
