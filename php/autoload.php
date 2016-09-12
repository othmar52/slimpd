<?php
namespace Slimpd;
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
function classAutoLoader($class) {
	$path = explode("\\", $class);
	if (isset($path[0]) === false) {
		return false;
	}
	$filename = array_slice($path, -1, 1);
	if ($path[0] === "Slimpd") {
		$path = array_slice($path, 1, -1);
	}

	$classFile = __DIR__
				. DS
				. implode(DS, $path)
				. DS
				. $filename[0]
				. ".php";
	if (is_file($classFile) === true && class_exists($class) === false) {
		require_once $classFile;
	}
}
spl_autoload_register("\Slimpd\classAutoLoader");
