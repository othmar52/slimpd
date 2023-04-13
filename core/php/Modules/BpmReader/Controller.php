<?php
namespace Slimpd\Modules\BpmReader;
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

    public function getBpmAction(Request $request, Response $response, $args) {
        useArguments($request, $response, $args);
        
        $bpmReader = new \Slimpd\Modules\BpmReader\BpmReader($this->container);
        $valid = $this->validateInput($args['itemParams'], $bpmReader);
        if(($valid) === FALSE) {
            throw new \Exception("Unable to handle invalid file path", 1481873390);
        }
        $bpmReader->prepare($response);
        return $bpmReader->getBpmDetectValue();
        deliverJson($bpmReader->getBpmDetectValue(), $response);
    }

    protected function validateInput($input, &$BpmReader) {
        $track = $this->trackRepo->getInstanceByPath($input, TRUE);

        if($this->filesystemUtility->isInAllowedPath($track->getRelPath()) === FALSE
        && $this->filesystemUtility->isSystemCheckSample($track->getRelPath()) === FALSE) {
            throw new \Exception("File is not within allowed paths", 1481873364);
        }

        $absolutePath = ($this->filesystemUtility->isSystemCheckSample($track->getRelPath()) === TRUE)
            ? APP_ROOT . $track->getRelPath()
            : $this->filesystemUtility->getFileRealPath($track->getRelPath());


        // we need to tweak min/max if we have drum and bass to avoid invalid tempo detection
        $isHighTempo = $track->isHighTempo($this->container->genreRepo);
        $BpmReader->setMin($isHighTempo === TRUE ? 150 : 70);
        $BpmReader->setMax($isHighTempo === TRUE ? 200 : 156);
        $BpmReader->setAbsolutePath($absolutePath);
        $BpmReader->setExt($track->getAudioDataformat());
        $BpmReader->setFingerprint($track->getFingerprint());
        if(isValidFingerprint($BpmReader->getFingerprint()) === 1) {
            return TRUE;
        }

        $fileScanner = new \Slimpd\Modules\Importer\Filescanner($this->container);
        $fingerprint = $fileScanner->extractAudioFingerprint($BpmReader->getAbsolutePath());
        if(isValidFingerprint($fingerprint) === 1) {
            $BpmReader->setFingerprint($fingerprint);
            return TRUE;
        }
        return FALSE;
    }
}
