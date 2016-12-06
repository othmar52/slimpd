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
            'fsConfLocalServe'=> array('status' => 'danger', 'hide' => FALSE, 'skip' => FALSE),
            'fsMusicdirconf'=> array('status' => 'warning', 'hide' => FALSE, 'skip' => FALSE),
            'fsMusicdir'    => array('status' => 'warning', 'hide' => FALSE, 'skip' => TRUE),
            'fsCache'        => array('status' => 'warning', 'hide' => FALSE, 'skip' => FALSE),
            'fsEmbedded'    => array('status' => 'warning', 'hide' => FALSE, 'skip' => FALSE),
            'fsPeakfiles'    => array('status' => 'warning', 'hide' => FALSE, 'skip' => FALSE),
            // TODO: perform checks for optional configuration [mpd]alternative_musicdir

            // database
            'dbConn'        => array('status' => 'warning', 'hide' => FALSE, 'skip' => FALSE),
            'dbPerms'        => array('status' => 'warning', 'hide' => FALSE, 'skip' => TRUE),
            'dbSchema'        => array('status' => 'warning', 'hide' => FALSE, 'skip' => TRUE),
            'dbContent'        => array('status' => 'warning', 'hide' => FALSE, 'skip' => TRUE, 'tracks' => 0),

            // mpd
            'mpdConn'        => array('status' => 'warning', 'hide' => FALSE, 'skip' => FALSE),
            'mpdDbfileconf'    => array('status' => 'warning', 'hide' => FALSE, 'skip' => FALSE),
            'mpdDbfile'        => array('status' => 'warning', 'hide' => FALSE, 'skip' => TRUE),

            // sphinx
            'sxConn'        => array('status' => 'warning', 'hide' => FALSE, 'skip' => FALSE),
            'sxSchema'        => array('status' => 'warning', 'hide' => FALSE, 'skip' => FALSE),
            'sxContent'        => array('status' => 'warning', 'hide' => FALSE, 'skip' => FALSE),

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
        return $check;
    }

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

    protected function runDatabasePermissionCheck(&$check) {
        // check permissions for "create database" (needed for schema-comparison)
        if($check['dbPerms']['skip'] === TRUE) {
            return;
        }
        $tmpDb = $this->conf['database']['dbdatabase']."_prmchk";
        $result = $this->container->db->query("CREATE DATABASE ". $tmpDb .";");
        if (!$result) {#
            $check['dbPerms']['status'] = 'danger';
        } else {
            $this->container->db->query("DROP DATABASE ". $tmpDb .";");
            $check['dbPerms']['status'] = 'success';
            $check['dbSchema']['skip'] = FALSE;
        }
    }

    protected function runDatabaseSchemaCheck(&$check) {
        // check if db-schema is correct
        if($check['dbSchema']['skip'] === TRUE) {
            return;
        }
        \Helper::setConfig( getDatabaseDiffConf($this->conf) );
        $tmpdb = \Helper::getTmpDbObject();
        \Helper::loadTmpDb($tmpdb);
        $diff = new \dbDiff(\Helper::getDbObject(), $tmpdb);
        $difference = $diff->getDifference();
        if(!count($difference['up']) && !count($difference['down'])) {
            $check['dbSchema']['status'] = 'success';
            $check['dbContent']['skip'] = FALSE;
        } else {
            $check['dbSchema']['status'] = 'danger';
            $check['dbSchema']['queries'] = $difference['down'];
            $check['skipAudioTests'] = TRUE;
        }
    }

    protected function runDatabaseContentCheck(&$check) {
        // check if we have useful records in our database
        if($check['dbContent']['skip'] === TRUE) {
            return;
        }
        $check['dbContent']['tracks']  = $this->container->trackRepo->getCountAll();
        $check['dbContent']['albums']  = $this->container->albumRepo->getCountAll();
        $check['dbContent']['artists'] = $this->container->artistRepo->getCountAll();
        $check['dbContent']['genres']  = $this->container->genreRepo->getCountAll();
        $check['dbContent']['labels']  = $this->container->labelRepo->getCountAll();
        $check['dbContent']['status'] = ($check['dbContent']['tracks'] > 0)
            ? 'success'
            : 'danger';
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

    protected function runSphinxChecks(&$check) {

        // check sphinx connection
        $check['sxConn']['status'] = 'success';
        try {
            $sphinxPdo = \Slimpd\Modules\sphinx\Sphinx::getPdo($this->conf);
        } catch (\Exception $e) {
            $check['sxConn']['status'] = 'danger';
            $check['sxSchema']['skip'] = TRUE;
            $check['sxContent']['skip'] = TRUE;
            return;
        }
        // check if we can query both sphinx indices
        $schemaError = FALSE;
        $contentError = FALSE;
        foreach(['main', 'suggest'] as $indexName) {
            $sphinxPdo = \Slimpd\Modules\sphinx\Sphinx::getPdo($this->conf);
            $stmt = $sphinxPdo->prepare(
                "SELECT ". $this->conf['sphinx']['fields_'.$indexName]." FROM ". $this->conf['sphinx'][$indexName . 'index']." LIMIT 1;"
            );
            $stmt->execute();
            if($stmt->errorInfo()[0] > 0) {
                $check['sxSchema']['status'] = 'danger';
                $check['sxSchema']['msg'] = $stmt->errorInfo()[2];
                $schemaError = TRUE;
                $check['sxContent']['skip'] = TRUE;
                continue;
            }
            $check['sxSchema']['status'] = 'sucess';
            $check['sxContent']['skip'] = FALSE;
            $total = parseMetaForTotal($sphinxPdo->query("SHOW META")->fetchAll());
            if($total < 1) {
                $contentError = TRUE;
                continue;
            }
            $check['sxContent'][$indexName]['total'] = $total;
        }
        $check['sxSchema']['status'] = ($schemaError === TRUE) ? 'danger' : 'success';
        $check['sxContent']['status'] = ($contentError === TRUE) ? 'danger' : 'success';
        if($schemaError === TRUE) {
            $check['sxContent']['skip'] = TRUE;
            $check['sxContent']['status'] = 'warning';
        }
    }

    protected function buildAudioCheckConf(&$check) {
        $this->audioFormats = array(
            'mp3' => array(
                '811d1030efefb4bde7b5126e740ff34c' => 'testfile-online-convert.com.mp3'
            ),
            'flac' => array(
                'd84bd2fdeb119b724e3441af376d7159' => 'testfile-online-convert.com.flac'
            ),
            'wav' => array(
                'f719fd7c146c5f1f7a3808477c379ee9' => 'testfile.wav'
            ),
            'm4a' => array(
                'f3ecf7790e9394981c09915efc5668d0' => 'testfile-online-audio-converter.com.m4a'
            ),
            'aif' => array(
                '50ccced31bbeae8ca5dfe989d9a5e08d' => 'testfile-online-convert.com.aif'
            ),
            'aac' => array(
                '070aab812298dec6ac937080e6d3adae' => 'testfile-online-convert.com.aac'
            ),
            'ogg' => array(
                '5b97a2865f9d0c4f28b2c0894ac37502' => 'testfile-online-audio-converter.com.ogg'
            ),
            'wma' => array(
                '06a76631d599a699e93ea9462f7f0feb' => 'testfile-online-audio-converter.com.wma'
            ),
            'ac3' => array(
                'dc713d0a458118bf61ae2905c2b8e483' => 'testfile-converted-with-www.zamzar.com.ac3'
            )
        );

        $check['audioFormats'] = array_keys($this->audioFormats);
        $check['audioFormatsUc'] = array_map('ucfirst', $check['audioFormats']);

        foreach($this->audioFormats as $format => $data) {
            $check['fp'.ucfirst($format)] = array('status' => 'warning', 'hide' => FALSE, 'skip' => FALSE,
                'filepath' => 'core/templates/partials/systemcheck/waveforms/testfiles/' . array_values($data)[0],
                'cmd' => '',
                'resultExpected' => array_keys($data)[0],
                'resultReal' => FALSE,
            );
            $check['wf'.ucfirst($format)] = array('status' => 'warning', 'hide' => FALSE, 'skip' => TRUE,
                'filepath' => 'core/templates/partials/systemcheck/waveforms/testfiles/' . array_values($data)[0],
                'cmd' => ''
            );

        }
    }

    protected function runAudioChecks(&$check) {
        if($check['skipAudioTests'] === TRUE) {
            return;
        }
        $fileScanner = new \Slimpd\Modules\Importer\Filescanner($this->container);
        // check if can extract a fingerprint of music file
        foreach($check['audioFormats'] as $ext) {
            $checkFp = 'fp'.ucfirst($ext);
            $checkWf = 'wf'.ucfirst($ext);
            if($check[$checkFp]['skip'] === FALSE) {
                $check[$checkFp]['cmd'] = $fileScanner->extractAudioFingerprint(APP_ROOT . $check[$checkFp]['filepath'], TRUE);
                exec($check[$checkFp]['cmd'], $response);
                $check[$checkFp]['resultReal'] = trim(join("\n", $response));
                unset($response);
                if($check[$checkFp]['resultExpected'] === $check[$checkFp]['resultReal']) {
                    $check[$checkFp]['status'] = 'success';
                    if($check['fsPeakfiles']['status'] === 'success' && $check['fsCache']['status'] === 'success') {
                        $check[$checkWf]['skip'] = FALSE;
                    }
                } else {
                    $check[$checkFp]['status'] = 'danger';
                }
            }

            if($check[$checkWf]['skip'] === TRUE) {
                continue;
            }
            $peakfile = APP_ROOT . "localdata". DS . "peakfiles/".$ext.DS. substr($check[$checkFp]['resultExpected'],0,3) . DS . $check[$checkFp]['resultExpected'];
            $tmpMp3 = APP_ROOT . "localdata". DS . "cache/".$ext."." . $check[$checkFp]['resultExpected'] . '.mp3';
            $tmpWav = APP_ROOT . "localdata". DS . "cache/".$ext."." . $check[$checkFp]['resultExpected'] . '.wav';

            // make sure we retrieve nothing cached
            $this->container->filesystemUtility->rmfile([$peakfile, $tmpMp3, $tmpWav]);
            $waveformGenerator = new \Slimpd\Modules\WaveformGenerator\WaveformGenerator($this->container);
            $waveformGenerator
                ->setAbsolutePath(APP_ROOT . $check[$checkWf]['filepath'])
                ->setExt($this->container->filesystemUtility->getFileExt($check[$checkWf]['filepath']))
                ->setFingerprint($check[$checkFp]['resultReal']);
            $waveformGenerator->getPeaks();

            $check[$checkWf]['cmd'] = $waveformGenerator->getCmdTempwav();

            exec($check[$checkWf]['cmd'], $response, $returnStatus);
            $check[$checkWf]['status'] = ($returnStatus === 0) ? 'success' : 'danger';

            if(is_file($tmpMp3) === FALSE) {
                $check[$checkWf]['status'] = 'danger';
            }
            if(is_file($tmpWav) === FALSE) {
                $check[$checkWf]['status'] = 'danger';
            }
            $this->container->filesystemUtility->rmfile([$peakfile, $tmpMp3, $tmpWav]);
        }
    }
}
