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

class DirectoryRepo extends \Slimpd\Repositories\BaseRepository {
	public static $tableName = 'noDatabaseTable';
	public static $classPath = '\Slimpd\Models\Directory';
	
	public function create($dirPath) {
		#$classPath = self::getClassPath();
		return new \Slimpd\Models\Directory($dirPath);
	}

	public function validate(\Slimpd\Models\Directory $dirInstance) {
		$realPath = $this->container->filesystemUtility->getFileRealPath($dirInstance->getRelPath());
		if($this->container->filesystemUtility->isInAllowedPath($dirInstance->getRelPath()) === FALSE || $realPath === FALSE) {
			return FALSE;
		}

		// check if it is really a directory because getFileRealPath() also works for files
		if(is_dir($realPath) === FALSE) {
			return FALSE;
		}
		$dirInstance->setExists(TRUE);
		return TRUE;
	}
	public function fetchRenderItems(&$renderItems, $directoryInstance) {
		return;
	}
}
