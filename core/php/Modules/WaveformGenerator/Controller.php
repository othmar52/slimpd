<?php
namespace Slimpd\Modules\WaveformGenerator;
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
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License
 * for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class Controller extends \Slimpd\BaseController {

    public function svgAction(Request $request, Response $response, $args) {
        useArguments($request, $response, $args);
        $WaveformGenerator = new \Slimpd\Modules\WaveformGenerator\WaveformGenerator($this->container);
        $valid = $this->validateInput($args['itemParams'], $WaveformGenerator);
        if(($valid) === FALSE) {
            throw new \Exception("Unable to handle invalid file path", 1481873390);
        }
        $WaveformGenerator->prepare($response);
        $half = ($request->getParam('mode') === "half");
        $args['peakvalues'] = $WaveformGenerator->getSvgValues($args['width'], $half, $response);
        if(is_object($args['peakvalues']) === TRUE) {
            // $response got returned so something went wrong...
            return $args['peakvalues'];
        }


        $args['color'] =  $this->conf['colors']['defaultwaveform'];
        $colorFor = $request->getParam('colorFor');
        if(in_array($colorFor, ['mpd', 'local', 'xwax']) === TRUE) {
            $args['color'] =  $this->conf['colors'][ $this->conf['spotcolor'][$colorFor] ]['1st'];
        }
        $this->view->render($response, 'svg/waveform.svg', $args);
        $newResponse = $response->withHeader('Content-Type', 'image/svg+xml');
        return $newResponse;
    }

    public function jsonAction(Request $request, Response $response, $args) {
        useArguments($request, $response, $args);
        $WaveformGenerator = new \Slimpd\Modules\WaveformGenerator\WaveformGenerator($this->container);
        $valid = $this->validateInput($args['itemParams'], $WaveformGenerator);
        if(($valid) === FALSE) {
            return "{}"; // dirty fix for radio streams
            throw new \Exception("Unable to handle invalid file path", 1481873246);
        }
        $WaveformGenerator->prepare($response);
        return $WaveformGenerator->generateJson($args['width'], $response);
    }

    protected function validateInput($input, &$WaveformGenerator) {
        $track = $this->trackRepo->getInstanceByPath($input, TRUE);

        if($this->filesystemUtility->isInAllowedPath($track->getRelPath()) === FALSE
        && $this->filesystemUtility->isSystemCheckSample($track->getRelPath()) === FALSE) {
            throw new \Exception("File is not within allowed paths", 1481873364);
        }

        $absolutePath = ($this->filesystemUtility->isSystemCheckSample($track->getRelPath()) === TRUE)
            ? APP_ROOT . $track->getRelPath()
            : $this->filesystemUtility->getFileRealPath($track->getRelPath());

        $WaveformGenerator->setAbsolutePath($absolutePath);
        $WaveformGenerator->setExt($track->getAudioDataformat());
        $WaveformGenerator->setFingerprint($track->getFingerprint());
        if(isValidFingerprint($WaveformGenerator->getFingerprint()) === 1) {
            return TRUE;
        }

        $fileScanner = new \Slimpd\Modules\Importer\Filescanner($this->container);
        $fingerprint = $fileScanner->extractAudioFingerprint($WaveformGenerator->getAbsolutePath());
        if(isValidFingerprint($fingerprint) === 1) {
            $WaveformGenerator->setFingerprint($fingerprint);
            return TRUE;
        }
        return FALSE;
    }
}
