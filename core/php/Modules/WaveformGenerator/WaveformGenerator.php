<?php
namespace Slimpd\Modules\WaveformGenerator;
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

class WaveformGenerator {
    protected $svgResolution = 300;
    protected $absolutePath;
    protected $fingerprint;
    protected $peakValuesFilePath;
    protected $peakFileResolution = 4000;
    protected $ext;
    protected $cmdTempwav = '';
    protected $conf;

    public function __construct($container) {
        $this->conf = $container->conf;
        $this->container = $container;
        return $this;
    }

    public function prepare(&$response) {
        $this->setPeakFilePath();
        if(is_file($this->peakValuesFilePath) === TRUE) {
            // peakfile already exists - no need to generate it again
            return $this;
        }

        $tmpFileName = APP_ROOT . 'localdata' . DS . 'cache' . DS . $this->ext . '.' . $this->fingerprint . '.';
        if(is_file($tmpFileName.'mp3') === TRUE || is_file($tmpFileName.'wav') === TRUE) {
            // check if another request already triggered the peakfile-extraction
            $this->fireRetryHeaderAndExit($response);
        }
        session_write_close(); // do not block other requests during processing
        $this->generatePeakFile();
    }

    public function fireRetryHeaderAndExit(&$response) {
        $newResponse = $response->withHeader('Retry-After', 5)->withStatus(503);
        return $newResponse;
    }

    public function findValues($byte1, $byte2) {
        $byte1 = hexdec(bin2hex($byte1));
        $byte2 = hexdec(bin2hex($byte2));
        return ($byte1 + ($byte2*256));
    }

    public function getSvgValues($pixel = 300, $half, &$response) {
        if(is_file($this->peakValuesFilePath) === FALSE) {
            $uri = $this->container->router->pathFor(
                'imagefallback',
                ['type' => 'broken', 'imagesize' => 100 ]
            );
            $newResponse = $response->withRedirect($uri, 303);
            return $newResponse;
        }

        $peaks = file_get_contents($this->peakValuesFilePath);
        if($peaks === 'generating') {
            $this->fireRetryHeaderAndExit($response);
        }

        $values = array_map('trim', explode("\n", $peaks));

        $values = $this->limitArray($values, $pixel);
        $values = $this->beautifyPeaks($values);
        $amount = count($values);
        $max = max($values);

        $strokeLine = 2;
        $strokeBorder = 1;
        $strokeCounter = 0;
        $avgPeak = 0;

        $renderValues = array();


        foreach($values as $idx => $value) {
            $strokeCounter++;
            $avgPeak += $value;
            if($strokeCounter < ($strokeBorder + $strokeLine)){
                continue;
            }
            $strokeCounter = 0;
            $avgPeak = $avgPeak/($strokeBorder + $strokeLine+1);

            $percent = $avgPeak/($max/100);
            $diffPercent = 100 - $percent;

            $stroke = array(
                'x' => number_format($idx/($amount/100), 5, '.', ''),
                'y1' => number_format($diffPercent/2, 2, '.', ''),
                'y2' => number_format($diffPercent/2 + $percent, 2, '.', '')
            );
            if($half === TRUE) {
                $stroke["y1"] = number_format($diffPercent, 2, '.', '');
                $stroke["y2"] = 100;
            }
            $renderValues[] = $stroke;
        }
        return $renderValues;
    }

    public function generateJson($resolution = 300, &$response) {
        if(is_file($this->peakValuesFilePath) === FALSE) {
            // TODO: deliver something like a broken-waveform-json :P 
            $uri = $this->container->router->pathFor(
                'imagefallback',
                ['type' => 'broken', 'imagesize' => 100 ]
            );
            $newResponse = $response->withRedirect($uri, 303);
            return $newResponse;
        }

        $peaks = file_get_contents($this->peakValuesFilePath);
        if($peaks === 'generating') {
            $now = new \DateTimeImmutable();
            if ($now->getTimestamp() - filemtime($this->peakValuesFilePath) > 100) {
                // obviously we have had a stuck process
                $this->container->filesystemUtility->rmfile($this->peakValuesFilePath);
            }
            $this->fireRetryHeaderAndExit($response);
            return NULL;
        }

        $values = explode("\n", $peaks);
        $values = array_map('trim', $values);
        $values = $this->limitArray($values, $resolution);
        $values = $this->beautifyPeaks($values);

        deliverJson($values, $response);
    }

    public function setPeakFilePath() {
        $this->peakValuesFilePath = APP_ROOT . 'localdata' . DS . 'peakfiles' .
            DS . $this->ext .
            DS . substr($this->fingerprint,0,3) .
            DS . $this->fingerprint;
    }

    protected function generatePeakFile() {

        \phpthumb_functions::EnsureDirectoryExists(
            dirname($this->peakValuesFilePath),
            octdec($this->conf['config']['dirCreateMask'])
        );
        file_put_contents($this->peakValuesFilePath, "generating");

        // extract peaks
        $peakValues = $this->getPeaks();
        if($peakValues === FALSE) {
            return FALSE;
        }

        // shorten values to configured limit
        $peakValues = $this->limitArray($peakValues, $this->peakFileResolution);

        file_put_contents($this->peakValuesFilePath, join("\n", $peakValues));
        chmod($this->peakValuesFilePath, octdec($this->conf['config']['fileCreateMask']));
        return;
    }

    public function getPeaks() {
        $tmpFileName = APP_ROOT . 'localdata' . DS . 'cache' . DS . $this->ext . '.' . $this->fingerprint;
        $inFile = escapeshellarg($this->absolutePath);
        $tmpWav = escapeshellarg($tmpFileName.'.wav');
        $tmpMp3 = escapeshellarg($tmpFileName.'.mp3');
        $binConf = $this->conf['modules'];

        switch($this->ext) {
            case 'flac':
                $this->cmdTempwav = sprintf(
                    "%s -d --stdout --totally-silent %s | %s -m m -S -f -b 16 --resample 8 --quiet - %s",
                    $binConf['bin_flac'],
                    $inFile,
                    $binConf['bin_lame'],
                    $tmpMp3
                );
                break;
            case 'm4a':
            case 'aac':
                $this->cmdTempwav = sprintf(
                    "%s -q -o %s %s && %s -m m -S -f -b 16 --resample 8 --quiet %s %s",
                    $binConf['bin_faad'],
                    $tmpWav,
                    $inFile,
                    $binConf['bin_lame'],
                    $tmpWav,
                    $tmpMp3
                );
                break;
            case 'ogg':
                $this->cmdTempwav = sprintf(
                    "%s -Q    %s -o    %s &&    %s -m m -S -f -b 16 --resample 8 --quiet %s %s",
                    $binConf['bin_oggdec'],
                    $inFile,
                    $tmpWav,
                    $binConf['bin_lame'],
                    $tmpWav,
                    $tmpMp3
                );
                break;
            case 'ac3':
                $this->cmdTempwav = sprintf(
                    "%s -really-quiet -channels 5 -af pan=2:'1:0':'0:1':'0.7:0':'0:0.7':'0.5:0.5' %s".
                    " -ao pcm:file=%s && %s -m m -S -f -b 16 --resample 8 --quiet %s %s",
                    $binConf['bin_mplayer'],
                    $inFile,
                    $tmpWav,
                    $binConf['bin_lame'],
                    $tmpWav,
                    $tmpMp3
                );
                break;
            case 'wma':
                $this->cmdTempwav = sprintf(
                    "%s -really-quiet %s -ao pcm:file=%s && %s -m m -S -f -b 16 --resample 8 --quiet %s %s",
                    $binConf['bin_mplayer'],
                    $inFile,
                    $tmpWav,
                    $binConf['bin_lame'],
                    $tmpWav,
                    $tmpMp3
                );
                break;
            default:
                $this->cmdTempwav = sprintf(
                    "%s %s -m m -S -f -b 16 --resample 8 --quiet %s",
                    $binConf['bin_lame'],
                    $inFile,
                    $tmpMp3
                );
                break;
        }

        $this->cmdTempwav .= sprintf(
            " && %s -S --quiet --decode %s %s",
            $binConf['bin_lame'],
            $tmpMp3,
            $tmpWav
        );

        exec($this->cmdTempwav);

        // delete temporary mp3 file
        $this->container->filesystemUtility->rmfile($tmpFileName . ".mp3");

        if(is_file($tmpFileName.'.wav') === FALSE) {
            return FALSE;
        }
        $values = $this->getWavPeaks($tmpFileName.'.wav');
        // delete temporary wav file
        $this->container->filesystemUtility->rmfile($tmpFileName . ".wav");
        return $values;
    }

    protected function getWavPeaks($temp_wav) {
        ini_set ('memory_limit', '1024M'); // extracted wav-data is very large (500000 entries)
        /**
         * Below as posted by "zvoneM" on
         * http://forums.devshed.com/php-development-5/reading-16-bit-wav-file-318740.html
         * as findValues() defined above
         * Translated from Croation to English - July 11, 2011
         */
        $data = array();
        $handle = fopen ($temp_wav, "r");
        //dohvacanje zaglavlja wav datoteke
        $heading[] = fread ($handle, 4);
        $heading[] = bin2hex(fread ($handle, 4));
        $heading[] = fread ($handle, 4);
        $heading[] = fread ($handle, 4);
        $heading[] = bin2hex(fread ($handle, 4));
        $heading[] = bin2hex(fread ($handle, 2));
        $heading[] = bin2hex(fread ($handle, 2));
        $heading[] = bin2hex(fread ($handle, 4));
        $heading[] = bin2hex(fread ($handle, 4));
        $heading[] = bin2hex(fread ($handle, 2));
        $heading[] = bin2hex(fread ($handle, 2));
        $heading[] = fread ($handle, 4);
        $heading[] = bin2hex(fread ($handle, 4));

        //bitrate wav datoteke
        $peek = hexdec(substr($heading[10], 0, 2));
        $byte = $peek / 8;

        //provjera da li se radi o mono ili stereo wavu
        $channel = hexdec(substr($heading[6], 0, 2));

        $ratio = ($channel == 2) ? 40 : 80;

        while(!feof($handle)) {
            $bytes = array();
            //get number of bytes depending on bitrate
            for ($i = 0; $i < $byte; $i++) {
                $bytes[$i] = fgetc($handle);
            }
            if($byte === 1) {
                $this->getValue8BitWav($data, $bytes);
            }
            if($byte === 2) {
                $this->getValue16BitWav($data, $bytes);
            }
            //skip bytes for memory optimization
            fread ($handle, $ratio);
        }

        // close and cleanup
        fclose ($handle);
        return $data;
    }

    protected function getValue8BitWav(&$data, $bytes) {
        $value = $this->findValues($bytes[0], $bytes[1]) - 128;
        $data[]= ($value < 0) ? 0 : $value;
    }

    protected function getValue16BitWav(&$data, $bytes) {
        $temp = (ord($bytes[1]) & 128) ? 0 : 128;
        $temp = chr((ord($bytes[1]) & 127) + $temp);
        $value = floor($this->findValues($bytes[0], $temp) / 256) - 128;
        $data[]= ($value < 0) ? 0 : $value;
    }

    protected function limitArray($input, $max = 22000) {
        #echo "<pre>" . print_r($input, 1); die();
        #echo ini_get('memory_limit'); die();
        // 512MB is not enough for files > 4hours (XXX entries)
        # TODO: add a note in documentation
        ini_set ('memory_limit', '1024M'); // extracted wav-data is very large (500000 entries)
        $count = count($input);
        if($count < $max) {
            return $input;
        }
        $floor = (floor($count / $max)) + 1;

        $output = array();
        $prev = 0;
        $current = 0;

        for($idx = 0; $idx < $count; $idx++) {
            $current++;
            $prev = ($input[$idx] > $prev) ? $input[$idx] : $prev;
            if($current == $floor) {
                $output[] = $prev;
                $current = 0;
                $prev = 0;
            }
            unset($input[$idx]);
        }
        return $output;
    }

    protected function beautifyPeaks($input) {
        $beauty = array();
        $avg = array_sum($input)/count($input);
        $maxPeak = max($input);

        // results of visual testing with dozens of random files
        // maxPeak:128 ->    best multiplicator -> 1.4
        // maxPeak:82    ->    best multiplicator -> 2.3

        // that gives us those guiding values
        // maxPeak: 128 -> 1.4
        // maxPeak: 1     -> 4

        // now try to find the best multiplicator for ($maxPeak/$avg) by playing around...
        $multiMax = 3.3;
        $multiMin = 1.4;
        $multi100th = ($multiMax-$multiMin)/100;

        $rangeMax = 128;
        $range100th = $rangeMax/100;
        $invertedRangePercent = 100 - $maxPeak / $range100th;
        $multiplicator = $multiMin + $invertedRangePercent * $multi100th;
        foreach($input as $value) {
            if($value < 1) {
                $beauty[] = $value;
                continue;
            }
            $beauty[] = floor($value * ($maxPeak/$avg) * $multiplicator);
        }
        return $beauty;
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
}
