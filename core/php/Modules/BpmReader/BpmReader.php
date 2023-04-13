<?php
namespace Slimpd\Modules\BpmReader;
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
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License
 * for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

class BpmReader {
    protected $svgResolution = 300;
    protected $absolutePath;
    protected $fingerprint;
    protected $bpmDetectFilePath;
    protected $peakFileResolution = 4000;
    protected $ext;
    protected $min;
    protected $max;
    protected $cmdTempwav = '';
    protected $conf;

    public function __construct($container) {
        $this->conf = $container->conf;
        $this->container = $container;
        return $this;
    }

    public function prepare(&$response) {
        $this->setBpmDetectPath();
        if(is_file($this->bpmDetectFilePath) === TRUE) {
            // bpm detect file already exists - no need to generate it again
            return $this;
        }

        $tmpFileName = APP_ROOT . 'localdata' . DS . 'cache' . DS . $this->ext . '.' . $this->fingerprint . '.';
        if(is_file($tmpFileName.'mp3') === TRUE || is_file($tmpFileName.'wav') === TRUE) {
            // check if another request already triggered the peakfile-extraction
            $this->fireRetryHeaderAndExit($response);
        }
        session_write_close(); // do not block other requests during processing
        $this->generateBpmDetectFile();
    }

    public function fireRetryHeaderAndExit(&$response) {
        $newResponse = $response->withHeader('Retry-After', 5)->withStatus(503);
        return $newResponse;
    }

    public function setBpmDetectPath() {
        $this->bpmDetectFilePath = APP_ROOT . 'localdata' . DS . 'bpmdetect' .
            DS . $this->ext .
            DS . substr($this->fingerprint,0,3) .
            DS . $this->fingerprint .
            '-' . $this->min .
            '-' . $this->max;
    }

    public function getBpmDetectValue() {
        return file_get_contents($this->bpmDetectFilePath);
    }

    protected function generateBpmDetectFile() {

        \phpthumb_functions::EnsureDirectoryExists(
            dirname($this->bpmDetectFilePath),
            octdec($this->conf['config']['dirCreateMask'])
        );
        file_put_contents($this->bpmDetectFilePath, "generating");

        $bpmValue = $this->runBpmDetectCommand();
        if($bpmValue === FALSE) {
            return FALSE;
        }
        file_put_contents($this->bpmDetectFilePath, $bpmValue);
        chmod($this->bpmDetectFilePath, octdec($this->conf['config']['fileCreateMask']));
        return;
    }

    public function runBpmDetectCommand() {
        $tmpFileName = APP_ROOT . 'localdata' . DS . 'cache' . DS . $this->ext . '.' . $this->fingerprint;
        $inFile = escapeshellarg($this->absolutePath);
        $tmpWav = escapeshellarg($tmpFileName.'.wav');
        $tmpMp3 = escapeshellarg($tmpFileName.'.mp3');
        $tmpFlac = escapeshellarg($tmpFileName.'.flac');
        $binConf = $this->conf['modules'];
        $useFile = NULL;

        // some file formats are not supported by bpm detect
        // we have to convert it to mp3 first
        switch($this->ext) {
            case 'mp3':
            case 'flac':
            case 'ogg':
            case 'oga':
            case 'vorbis':
                $useFile = $inFile;
                break;
            case 'ac3':
                $cmdTempMp3 = sprintf(
                    "%s -really-quiet -channels 5 -af pan=2:'1:0':'0:1':'0.7:0':'0:0.7':'0.5:0.5' %s".
                    " -ao pcm:file=%s && %s -m m -S -f -b 128 --resample 8 --quiet %s %s",
                    $binConf['bin_mplayer'],
                    $inFile,
                    $tmpWav,
                    $binConf['bin_lame'],
                    $tmpWav,
                    $tmpMp3
                );
                exec($cmdTempMp3);
                $useFile = $tmpMp3;
                break;
            case 'wma':
                $cmdTempMp3 = sprintf(
                    "%s -really-quiet %s -ao pcm:file=%s && %s -m m -S -f -b 128 --resample 8 --quiet %s %s",
                    $binConf['bin_mplayer'],
                    $inFile,
                    $tmpWav,
                    $binConf['bin_lame'],
                    $tmpWav,
                    $tmpMp3
                );
                exec($cmdTempMp3);
                #echo $cmdTempMp3; exit;
                $useFile = $tmpMp3;
                break;
            case 'wav':
            case 'aiff':
            case 'aif':
            case 'mp4':
            case 'm4a':
            case 'aac':
                $cmdTempFlac = sprintf(
                    "%s -y -hide_banner -v quiet -stats -i %s %s",
                    $binConf['bin_ffmpeg'],
                    $inFile,
                    $tmpFlac
                );
                #echo $cmdTempFlac; exit;
                exec($cmdTempFlac);
                $useFile = $tmpFlac;
                break;
            case 'mp4XXXX':
            case 'm4aXXXX':
            case 'aacXXXX':
                $cmdTempMp3 = sprintf(
                    "%s -q -o %s %s && %s -m m -S -f -b 16 --resample 8 --quiet %s %s",
                    $binConf['bin_faad'],
                    $tmpWav,
                    $inFile,
                    $binConf['bin_lame'],
                    $tmpWav,
                    $tmpMp3
                );
                exec($cmdTempMp3);
                $useFile = $tmpMp3;
                break;
            default:
                echo "TODO - check file conversion for bpm detect " .
                $this->ext . "\n" . $this->absolutePath;
                return '0';
        }

        $this->cmdTempwav = sprintf(
            "%s -m %d -x %d %s 2>&1",
            $binConf['bin_bpmdetect'],
            $this->min,
            $this->max,
            $useFile
        );
        exec($this->cmdTempwav, $output);
        $result = '0';
        foreach($output as $line) {
            if (preg_match('/^.*\:\ ([0-9\.]+){1,8}\ BPM$/i', trim($line), $matches)) {
                $result = $matches[1];
            }
        }

        // delete possble temporary mp3 + wav file
        $this->container->filesystemUtility->rmfile($tmpFileName . ".mp3");
        $this->container->filesystemUtility->rmfile($tmpFileName . ".wav");
        $this->container->filesystemUtility->rmfile($tmpFileName . ".flac");
        return $result;
    }

    public function getCmdTempwav() {
        return $this->cmdTempwav;
    }

    public function setAbsolutePath($value) {
        $this->absolutePath = $value;
        return $this;
    }
    public function getAbsolutePath() {
        return $this->absolutePath;
    }

    public function setFingerprint($value) {
        $this->fingerprint = $value;
        return $this;
    }

    public function getFingerprint() {
        return $this->fingerprint;
    }

    public function setExt($value) {
        $this->ext = $value;
        return $this;
    }

    public function setMin($value) {
        $this->min = $value;
        return $this;
    }

    public function setMax($value) {
        $this->max = $value;
        return $this;
    }
}
