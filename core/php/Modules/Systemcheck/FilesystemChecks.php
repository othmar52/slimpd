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
trait FilesystemChecks {

    protected function runConfigLocalCheck(&$check) {
        // check if individual config file exists
        $relConfPath = "core/config/config_local.ini";
        if(is_file(APP_ROOT . $relConfPath) === FALSE) {
            return;
        }
        $check['fsConfLocalExists']['hide'] = TRUE;
        $check['fsConfLocalExists']['status'] = 'success';

        // check if individual config file is not served by webserver
        $httpClient = new \GuzzleHttp\Client();
        try {
            $response = $httpClient->get($this->configLocalUrl);
            $statusCode = $response->getStatusCode();
        } catch(\GuzzleHttp\Exception\ClientException $exception) {
            $statusCode = $exception->getResponse()->getStatusCode();
        } catch(\GuzzleHttp\Exception\RequestException $exception) {
            $check['fsConfLocalServe']['skip'] = TRUE;
            \Slim\Slim::getInstance()->flashNow('error', $exception->getMessage());
            return;
        }
        if($statusCode === 200) {
            $check['fsConfLocalServe']['status'] = 'danger';
            return;
        }
        $check['fsConfLocalServe']['status'] = 'success';
    }

    protected function runMusicdirChecks(&$check) {
        // check if we have a configured value for MPD-musicdirectory
        // DIRECTORY_SEPARATOR automatically gets appended to this config value. so DS means empty
        if(trim($this->conf['mpd']['musicdir']) === DS) {
            $check['fsMusicdirconf']['status'] = 'danger';
            $check['fsMusicdir']['hide'] = TRUE;
            $check['fsMusicdir']['skip'] = TRUE;
            return;
        }
        $check['fsMusicdirconf']['hide'] = TRUE;
        $check['fsMusicdir']['skip'] = FALSE;

        // check if we can access [mpd]-musicdir
        // TODO: check if there is any content inside
        // TODO: is it possible to read this from mpd API instead of configuring it manually?
        if(is_dir($this->conf['mpd']['musicdir']) === FALSE || is_readable($this->conf['mpd']['musicdir']) === FALSE) {
            $check['fsMusicdir']['status'] = 'danger';
            return;
        }
        $check['fsMusicdir']['status'] = 'success';
    }

    protected function runAppDirChecks(&$check) {
        // check filesystem access for writable directories
        foreach(['Cache', 'Embedded', 'Peakfiles'] as $dir) {
            if(is_dir(APP_ROOT . 'localdata' . DS . strtolower($dir)) === FALSE || is_writeable(APP_ROOT . 'localdata' . DS . strtolower($dir)) === FALSE) {
                $check['fs'. $dir]['status'] = 'danger';
                $check['skipAudioTests'] = TRUE;
            } else {
                $check['fs'. $dir]['status'] = 'success';
            }
        }
    }
}
