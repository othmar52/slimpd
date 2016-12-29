<?php
namespace Slimpd\Modules\Systemcheck;
/* Copyright (C) 2016 othmar52 <othmar52@users.noreply.github.com>
 *                    smuth4 <smuth4@users.noreply.github.com>
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

/**
 * TODO: refacture with a new Check model
 */
trait EnvironmentChecks {
    protected function runEnvironmentChecks(&$check) {
        $this->runCronjobCheck($check);
        $this->runPDOCheck($check);
        $this->runLocaleCheck($check);
    }

    /**
     * checks last execution of update cronJob
     */
    protected function runCronjobCheck(&$check) {
        $check['envCron']['lastHeartBeat'] = \Slimpd\Modules\Importer\CliController::getHeartBeatTstamp();
        if(time() - $check['envCron']['lastHeartBeat'] < 100) {
            $check['envCron']['status'] = 'success';
        }
    }

    /**
     * checks that PDO is installed
     */
    protected function runPDOCheck(&$check) {
        if (extension_loaded('pdo')) {
            $check['envPDO']['status'] = 'success';
        }
    }

    /**
     * checks that the current locale can handle UTF-8 (by dint of the escapeshellarg function)
     */
    protected function runLocaleCheck(&$check) {
        if (escapeshellarg('/testdir/pathtest-u§s²e³l¼e¬sµsöäüß⁄x/testfile.mp3') === "'/testdir/pathtest-u§s²e³l¼e¬sµsöäüß⁄x/testfile.mp3'") {
            $check['envLocale']['status'] = 'success';
        }
    }
}
