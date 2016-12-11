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
trait AudioChecks {
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
