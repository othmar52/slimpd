<?php
namespace Slimpd;
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
class ExecutionTime {
	private $startTime;
	private $endTime;

	public function Start(){
		$this->startTime = getrusage();
	}

	public function End(){
		$this->endTime = getrusage();
	}

	private function runTime($ru, $rus, $index) {
		return ($ru["ru_$index.tv_sec"]*1000 + intval($ru["ru_$index.tv_usec"]/1000))
	-  ($rus["ru_$index.tv_sec"]*1000 + intval($rus["ru_$index.tv_usec"]/1000));
	}

	public function __toString(){
		return "This process used " . $this->runTime($this->endTime, $this->startTime, "utime") .
		" ms for its computations\nIt spent " . $this->runTime($this->endTime, $this->startTime, "stime") .
		" ms in system calls\n";
	}
}
