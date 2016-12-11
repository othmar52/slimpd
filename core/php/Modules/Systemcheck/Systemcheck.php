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
class Systemcheck {
    use \Slimpd\Modules\Systemcheck\FilesystemChecks;
    use \Slimpd\Modules\Systemcheck\DatabaseChecks;
    use \Slimpd\Modules\Systemcheck\SphinxChecks;
    use \Slimpd\Modules\Systemcheck\AudioChecks;
    use \Slimpd\Modules\Systemcheck\DiscogsChecks;
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
            'dbConn'           => array('status' => 'warning', 'hide' => FALSE, 'skip' => FALSE),
            'dbPerms'          => array('status' => 'warning', 'hide' => FALSE, 'skip' => TRUE),
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
            'discogsAuth'        => array('status' => 'warning', 'hide' => FALSE, 'skip' => TRUE),

            'skipAudioTests'=> FALSE
        );

        $this->runConfigLocalCheck($check);
        $this->runMusicdirChecks($check);
        $this->runAppDirChecks($check);

        $check['dbConn']['status'] = 'success';
        $check['dbPerms']['skip'] = FALSE;
        // check if we can connect to database
        if($dbError === TRUE) {
            $check['dbConn']['status'] = 'danger';
            $check['dbPerms']['skip'] = TRUE;
            $check['dbSchema']['skip'] = TRUE;
            $check['dbContent']['skip'] = TRUE;
            $check['mpdConn']['skip'] = TRUE;
            $check['skipAudioTests'] = TRUE;
        }

        $this->runDatabasePermissionCheck($check);
        $this->runDatabaseSchemaCheck($check);
        $this->runDatabaseContentCheck($check);
        $this->runMpdChecks($check);
        $this->runSphinxChecks($check);
        $this->buildAudioCheckConf($check);
        $this->runAudioChecks($check);
        $this->runDiscogsChecks($check);
        return $check;
    }

    protected function runMpdChecks(&$check) {
        // check MPD connection
        if($check['mpdConn']['skip'] === FALSE) {
            $check['mpdConn']['status'] = ($this->container->mpd->cmd('status') === FALSE) ? 'danger' : 'success';
        }

        // check if we have a configured value for MPD-databasefile
        if(trim($this->conf['mpd']['dbfile']) === '') {
            $check['mpdDbfileconf']['status'] = 'danger';
            $check['mpdDbfile']['hide'] = TRUE;
        } else {
            $check['mpdDbfile']['skip'] = FALSE;
            $check['mpdDbfileconf']['hide'] = TRUE;
        }

        // check if MPD databasefile is readable
        if($check['mpdDbfile']['skip'] === TRUE) {
            return;
        }

        if(is_file($this->conf['mpd']['dbfile']) == FALSE || is_readable($this->conf['mpd']['dbfile']) === FALSE) {
            $check['mpdDbfile']['status'] = 'danger';
            return;
        }
        $check['mpdDbfile']['status'] = 'success';
    }
}
