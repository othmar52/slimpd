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
class Systemcheck {
    use \Slimpd\Modules\Systemcheck\FilesystemChecks;
    use \Slimpd\Modules\Systemcheck\DatabaseChecks;
    use \Slimpd\Modules\Systemcheck\SphinxChecks;
    use \Slimpd\Modules\Systemcheck\AudioChecks;
    use \Slimpd\Modules\Systemcheck\DiscogsChecks;
    use \Slimpd\Modules\Systemcheck\MpdChecks;
    use \Slimpd\Modules\Systemcheck\EnvironmentChecks;
    protected $config;
    protected $checks;
    protected $audioFormats;
    public $configLocalUrl;

    public function __construct($container, $request) {
        $this->container = $container;
        $this->conf = $container->conf;
        $this->ll = $container->ll;
        $this->request = $request;
    }

    public function runChecks($dbError) {
        $check = array(
            // filesystem
            'fsConfLocalExists'=> array('status' => 'danger', 'hide' => FALSE, 'skip' => FALSE),
            'fsConfLocalServe' => array('status' => 'danger', 'hide' => FALSE, 'skip' => FALSE),
            'fsMusicdirconf'   => array('status' => 'warning', 'hide' => FALSE, 'skip' => FALSE),
            'fsMusicdir'       => array('status' => 'warning', 'hide' => FALSE, 'skip' => TRUE),
            'fsCache'          => array('status' => 'warning', 'hide' => FALSE, 'skip' => FALSE),
            'fsEmbedded'       => array('status' => 'warning', 'hide' => FALSE, 'skip' => FALSE),
            'fsPeakfiles'      => array('status' => 'warning', 'hide' => FALSE, 'skip' => FALSE),
            // TODO: perform checks for optional configuration [mpd]alternative_musicdir

            // database
            'dbConn'           => array('status' => 'success', 'hide' => FALSE, 'skip' => FALSE),
            'dbPerms'          => array('status' => 'warning', 'hide' => FALSE, 'skip' => FALSE),
            'dbSchema'         => array('status' => 'warning', 'hide' => FALSE, 'skip' => TRUE),
            'dbContent'        => array('status' => 'warning', 'hide' => FALSE, 'skip' => TRUE, 'tracks' => 0),

            // mpd
            'mpdConn'          => array('status' => 'warning', 'hide' => FALSE, 'skip' => FALSE),
            'mpdDbfileconf'    => array('status' => 'warning', 'hide' => FALSE, 'skip' => FALSE),
            'mpdDbfile'        => array('status' => 'warning', 'hide' => FALSE, 'skip' => TRUE),

            // sphinx
            'sxConn'           => array('status' => 'warning', 'hide' => FALSE, 'skip' => FALSE),
            'sxSchema'         => array('status' => 'warning', 'hide' => FALSE, 'skip' => FALSE),
            'sxContent'        => array('status' => 'warning', 'hide' => FALSE, 'skip' => FALSE),

            // discogs
            'discogsConf'      => array('status' => 'warning', 'hide' => FALSE, 'skip' => FALSE),
            'discogsAuth'      => array('status' => 'warning', 'hide' => FALSE, 'skip' => TRUE),

            // environment
            'envCron'          => array('status' => 'danger', 'hide' => FALSE, 'skip' => FALSE),
            'envPDO'           => array('status' => 'danger', 'hide' => FALSE, 'skip' => FALSE),
            'envLocale'        => array('status' => 'danger', 'hide' => FALSE, 'skip' => FALSE),

            'skipAudioTests'=> FALSE
        );

        $this->runFilesystemChecks($check);
        $this->runDatabaseChecks($check, $dbError);
        $this->runMpdChecks($check);
        $this->runSphinxChecks($check);
        $this->runDiscogsChecks($check);
        $this->runAudioChecks($check);
        $this->runEnvironmentChecks($check);
        return $check;
    }
}
