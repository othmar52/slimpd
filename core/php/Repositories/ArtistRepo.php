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
class ArtistRepo extends \Slimpd\Repositories\BaseRepository {
	public static $tableName = 'artist';
	public static $classPath = '\Slimpd\Models\Artist';
	

	public function fetchRenderItems(&$renderItems, $artistInstance) {
		$renderItems["artists"][$artistInstance->getUid()] = $artistInstance;
		foreach(trimExplode(",", $artistInstance->getTopLabelUids(), TRUE) as $labelUid) {
			if(isset($renderItems["labels"][$labelUid]) === TRUE) {
				continue;
			}
			$renderItems["labels"][$labelUid] = $this->container->labelRepo->getInstanceByAttributes(["uid" => $labelUid]);
		}
		foreach(trimExplode(",", $artistInstance->getTopGenreUids(), TRUE) as $genreUid) {
			if(isset($renderItems["genres"][$genreUid]) === TRUE) {
				continue;
			}
			$renderItems["genres"][$genreUid] = $this->container->genreRepo->getInstanceByAttributes(["uid" => $genreUid]);
		}
		return;
	}
}
