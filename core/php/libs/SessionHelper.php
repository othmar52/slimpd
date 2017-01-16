<?php
namespace Slimpd\libs;
/* Copyright (C) 2017 othmar52 <othmar52@users.noreply.github.com>
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
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License
 * for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

class SessionHelper extends \SlimSession\Helper
{
    /**
     * appends a value to an array session variable.
     *
     * @param string $key
     * @param mixed  $value
     */
    public function push($key, $value)
    {
        $array = (is_array($this->get($key)) === TRUE)
            ? array_unique($this->get($key))
            : array();
        $existingKey = (array_search($value, $array));
        if($existingKey !== FALSE) {
            // $value already exists in $key-array
            return;
        }
        $array[] = $value;
        $this->set($key, $array);
    }

    /**
     * removes a value of an array session variable.
     *
     * @param string $key
     * @param mixed  $value
     */
    public function drop($key, $value)
    {
        $array = (is_array($this->get($key)) === TRUE)
            ? array_unique($this->get($key))
            : array();
        $existingKey = (array_search($value, $array));
        if($existingKey === FALSE) {
            // $value does not exist in $key-array. no need to drop
            return;
        }
        unset($array[$existingKey]);
        $this->set($key, $array);
    }
}
