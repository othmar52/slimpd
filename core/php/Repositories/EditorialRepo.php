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

class EditorialRepo extends \Slimpd\Repositories\BaseRepository {
	public static $tableName = 'editorial';
	public static $classPath = '\Slimpd\Models\Editorial';

	protected function searchExistingUid(&$instance) {
		if($instance->getUid() > 0) {
			return;
		}
		$dummyInstance = NULL;
		// check if we have a record with this path and property
		$dummyInstance = $this->getInstanceByAttributes([
			'itemType' => $instance->getItemType(),
			'relPathHash' => $instance->getRelPathHash(),
			'column' => $instance->getColumn(),
		]);
		if($dummyInstance === NULL) {
			return;
		}
		$instance->setUid($dummyInstance->getUid());
	}

	public function insertTrackBasedInstance(\Slimpd\Models\Track $track, $setterName, $value) {
		$editorial = new \Slimpd\Models\Editorial();
		$editorial->setItemUid($track->getUid())
			->setItemType('track')
			->setRelPath($track->getRelPath())
			->setRelPathHash($track->getRelPathHash())
			->setFingerprint($track->getFingerprint())
			->setCrdate(time())
			->setTstamp(time())
			->setColumn($setterName)
			->setValue($value);

		$this->update($editorial);
	}
}
