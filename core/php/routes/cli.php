<?php
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

$importer = new \Slimpd\Modules\Importer();



/**
 * temporary script to migrate tagdata from filesystem to new db table `rawtagblob`
 * TODO: remove this route
 * @see: FilesystemUtility.php:getTagDataFileName()
 */
$app->get('/tagdatatodb', function () use ($app, $argv, $importer) {
	$query = "SELECT uid,relPathHash FROM rawtagdata";
	$result = $this->db->query($query);
	while($record = $result->fetch_assoc()) {
		cliLog($record['uid']);
		$tagFilePath = getTagDataFileName($record['relPathHash']);
		\Slimpd\Models\Rawtagblob::ensureRecordUidExists($record['uid']);
		$rawTagBlob = new \Slimpd\Models\Rawtagblob();
		$rawTagBlob->setUid($record['uid'])
			->setTagData(file_get_contents($tagFilePath . DS . $record['relPathHash']))
			->update();
	}
});

/**
 * temporary script to migrate tagdata from filesystem to new db table `rawtagblob` AND compression
 * TODO: remove this route
 * @see: FilesystemUtility.php:getTagDataFileName()
 */
$app->get('/tagdatatodbcompressed', function () use ($app, $argv, $importer) {
	$query = "SELECT uid,relPathHash FROM rawtagdata";
	$result = $this->db->query($query);
	while($record = $result->fetch_assoc()) {
		cliLog($record['uid']);
		$tagFilePath = getTagDataFileName($record['relPathHash']);
		\Slimpd\Models\Rawtagblob::ensureRecordUidExists($record['uid']);
		$rawTagBlob = new \Slimpd\Models\Rawtagblob();
		$rawTagBlob->setUid($record['uid'])
			->setTagData(gzcompress(file_get_contents($tagFilePath . DS . $record['relPathHash'])))
			->update();
	}
});
