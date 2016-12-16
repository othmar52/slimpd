<?php
namespace Slimpd\Modules\Systemcheck;
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

/**
 * TODO: refacture with a new Check model
 */
trait MpdChecks {

    /**
     * call all existing mpd checks
     */
    protected function runMpdChecks(&$check) {
        $this->checkMpdConnection($check);
        $this->checkMpdDbFileConfig($check);
        $this->checkMpdDbFileAccess($check);
    }

    /**
     * try to connect to mpd
     */
    protected function checkMpdConnection(&$check) {
        if($check['mpdConn']['skip'] === TRUE) {
            return;
        }
        $check['mpdConn']['status'] = ($this->container->mpd->cmd('status') === FALSE)
            ? 'danger'
            : 'success';
    }

    /**
     * check if we have a configuration value for MPD databasefile
     */
    protected function checkMpdDbFileConfig(&$check) {
        if(trim($this->conf['mpd']['dbfile']) === '') {
            $check['mpdDbfileconf']['status'] = 'danger';
            $check['mpdDbfile']['hide'] = TRUE;
            return;
        }
        $check['mpdDbfile']['skip'] = FALSE;
        $check['mpdDbfileconf']['hide'] = TRUE;
    }

    /**
     * check if MPD databasefile is readable
     */
    protected function checkMpdDbFileAccess(&$check) {
        if($check['mpdDbfile']['skip'] === TRUE) {
            return;
        }

        if(is_file($this->conf['mpd']['dbfile']) == FALSE) {
            $check['mpdDbfile']['status'] = 'danger';
            return;
        }

        if(is_readable($this->conf['mpd']['dbfile']) === FALSE) {
            $check['mpdDbfile']['status'] = 'danger';
            return;
        }
        $check['mpdDbfile']['status'] = 'success';
    }
}
