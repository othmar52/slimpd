<?php
namespace Slimpd\Modules\Importer;
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

class Filescanner extends \Slimpd\Modules\Importer\AbstractImporter {
    public $extractedImages = 0;
    public function run() {
        # TODO: handle orphaned records
        # TODO: displaying itemsChecked / itemsProcessed is incorrect
        # TODO: which speed-calculation makes sense? itemsChecked/minutute or itemsProcessed/minute or both?
        $this->jobPhase = 3;
        $this->beginJob(array('msg' => 'collecting tracks to scan from mysql database' ), __FUNCTION__);

        $query = "
            SELECT COUNT(*) AS itemsTotal
            FROM rawtagdata WHERE lastScan=0";
        $this->itemsTotal = (int) $this->db->query($query)->fetch_assoc()['itemsTotal'];

        $query = "
            SELECT uid, relPath, relPathHash, relDirPathHash
            FROM rawtagdata WHERE lastScan=0";

        $result = $this->db->query($query);
        $this->extractedImages = 0;
        while($record = $result->fetch_assoc()) {
            $this->itemsChecked++;
            cliLog($record['uid'] . ' ' . $record['relPath'], 2);
            $this->updateJob(array(
                'msg' => 'processed ' . $this->itemsChecked . ' files',
                'currentItem' => $record['relPath'],
                'extractedImages' => $this->extractedImages
            ));
            $this->singleFile2Database($record);
        }

        $this->finishJob(array(
            'extractedImages' => $this->extractedImages
        ), __FUNCTION__);
        return;
    }

    public function singleFile2Database($record) {
        $rawTagData = new \Slimpd\Models\Rawtagdata();
        $rawTagData->setUid($record['uid'])
            ->setRelPath($record['relPath'])
            ->setLastScan(time())
            ->setImportStatus(2);

        // TODO: handle not found files
        if(is_file($this->conf['mpd']['musicdir'] . $record['relPath']) === FALSE) {
            $rawTagData->setError('invalid file');
            $this->container->rawtagdataRepo->update($rawTagData);
            $this->createTagBlobEntry($record['uid'], array());
            return;
        }

        $rawTagData->setFilesize( filesize($this->conf['mpd']['musicdir'] . $record['relPath']) );

        // skip very large files
        // TODO: how to handle this?
        if($rawTagData->getFilesize() > 1000000000) {
            cliLog("ERROR: cant handle filesize (". formatByteSize($rawTagData->getFilesize()) .") of ". $rawTagData->getRelpath(), 1, "red");
            $rawTagData->setError('invalid filesize ' . $rawTagData->getFilesize() . ' bytes');
            $this->container->rawtagdataRepo->update($rawTagData);
            $this->createTagBlobEntry($record['uid'], array());
            return;
        }
        $getID3 = new \getID3;
        $tagData = $getID3->analyze($this->conf['mpd']['musicdir'] . $record['relPath']);
        \getid3_lib::CopyTagsToComments($tagData);


        // Write tagdata-array into sparata database table, gzipcompressed, serialized
        $this->createTagBlobEntry($record['uid'], $tagData);

        // TODO: should we complete rawTagData with fingerprint on flac files?
        $this->container->rawtagdataRepo->update($rawTagData);

        if(!$this->conf['images']['read_embedded']) {
            return;
        }
        $this->extractEmbeddedBitmaps($tagData, $record);
    }

    // Write tagdata-array into sparata database table, gzipcompressed, serialized
    protected function createTagBlobEntry($uid, $tagData) {
        $this->container->rawtagblobRepo->ensureRecordUidExists($uid);
        $rawTagBlob = new \Slimpd\Models\Rawtagblob();
        $rawTagBlob->setUid($uid)
            ->setTagData(gzcompress(serialize($this->removeHugeTagData($tagData))));
        $this->container->rawtagblobRepo->update($rawTagBlob);
    }

    /**
     * remove big-tagData-stuff (images, traktor-waveforms) to keep database-size as small as possible
     * maybe some or all array paths does not not exist...
     * TODO: move array paths to config
     */
    protected function removeHugeTagData($hugeTagdata) {
        // drop large data by common array paths
        foreach([
            ['comments', 'picture'],
            ['id3v2', 'APIC'],
            ['id3v2', 'PIC'],
            ['id3v2', 'PRIV'],
            ['id3v2', 'picture'],
            ['id3v2', 'comments', 'picture'],
            ['flac', 'PICTURE'],
            ['ape', 'items', 'cover art (front)'],
            ['comments', 'text', 'COVERART_UUENCODED'],
            ['comments_html'],
            ['tags_html']
        ] as $arrayPath) {
            recursiveArrayCleaner($arrayPath, $hugeTagdata);
        }
        // drop the rest of large data under non-common array paths
        try { recursiveDropLargeData($hugeTagdata); } catch (\Exception $e) { }
        return $hugeTagdata;
    }

    protected function extractEmbeddedBitmaps($tagData, $record) {
        if(isset($tagData['comments']['picture']) === FALSE) {
            return;
        }
        if(is_array($tagData['comments']['picture']) === FALSE) {
            return;
        }

        $bitmapController = new \Slimpd\Modules\Bitmap\Controller($this->container);
        $phpThumb = $bitmapController->getPhpThumb();
        $phpThumb->setParameter('config_cache_directory', APP_ROOT.'localdata/embedded');

        // loop through all embedded images
        foreach($tagData['comments']['picture'] as $bitmapIndex => $bitmapData) {    
            if(isset($bitmapData['image_mime']) === FALSE) {
                // skip unspecifyable datachunk
                continue;
            }
            if(isset($bitmapData['data']) === FALSE) {
                // skip missing datachunk
                continue;
            }

            $rawImageData = $bitmapData['data'];
            if(strlen($rawImageData) < 20) {
                // skip obviously invalid imagedata
                continue;
            }

            $phpThumb->resetObject();
            $phpThumb->setSourceData($rawImageData);
            $phpThumb->setParameter('config_cache_prefix', $record['relPathHash'].'_' . $bitmapIndex . '_');
            $phpThumb->SetCacheFilename();
            $phpThumb->GenerateThumbnail();
            if($phpThumb->source_height > 65500 || $phpThumb->source_width > 65500) {
                cliLog("ERROR extracting bitmap! Maximum supported image dimension is 65500 pixels", 1, "red");
                continue;
            }
            \phpthumb_functions::EnsureDirectoryExists(
                dirname($phpThumb->cache_filename),
                octdec($this->conf['config']['dirCreateMask'])
            );
            try {
                $phpThumb->RenderToFile($phpThumb->cache_filename);
            } catch(\Exception $e) {
                cliLog("ERROR extracting embedded Bitmap! " . $e->getMessage(), 1, "red");
                continue;
            }

            $this->extractedImages ++;

            if(is_file($phpThumb->cache_filename) === FALSE) {
                // there had been an error
                // TODO: how to handle this?
                continue;
            }

            // remove tempfiles of phpThumb
            $this->container->filesystemUtility->clearPhpThumbTempFiles($phpThumb);

            $relPath = removeAppRootPrefix($phpThumb->cache_filename);
            $relPathHash = getFilePathHash($relPath);

            $imageSize = GetImageSize($phpThumb->cache_filename);

            $bitmap = new \Slimpd\Models\Bitmap();
            $bitmap->setRelPath($relPath)
                ->setRelPathHash($relPathHash)
                ->setFilemtime(filemtime($phpThumb->cache_filename))
                ->setFilesize(filesize($phpThumb->cache_filename))
                ->setTrackUid($record['uid'])
                ->setRelDirPathHash($record['relDirPathHash'])
                ->setEmbedded(1)
                // setAlbumUid() will be applied later because at this time we havn't any albumUid's but tons of bitmap-record-dupes
                ->setFileName(
                    (isset($bitmapData['picturetype']) !== FALSE)
                        ? $bitmapData['picturetype'] . '.ext'
                        : 'Other.ext'
                )
                ->setPictureType($this->container->imageweighter->getType($bitmap->getFileName()))
                ->setSorting($this->container->imageweighter->getWeight($bitmap->getFileName()));

            if($imageSize === FALSE) {
                $bitmap->setError(1);
                $this->container->bitmapRepo->update($bitmap);
                continue;
            }

            $bitmap->setWidth($imageSize[0])
                ->setHeight($imageSize[1])
                ->setBghex(
                    self::getDominantColor($phpThumb->cache_filename, $imageSize[0], $imageSize[1])
                )
                ->setMimeType($imageSize['mime']);

            # TODO: can we call insert() immediatly instead of letting check the update() function itself?
            # this could save performance...
            $this->container->bitmapRepo->update($bitmap);
        }
    }

    // TODO: where to move pythonscript?
    // TODO: general wrapper for shell-executing stuff
    public function extractAudioFingerprint($absolutePath, $returnCommand = FALSE) {
        switch($this->container->filesystemUtility->getFileExt($absolutePath)) {
            case 'mp3':
                $cmd =  $this->conf['modules']['bin_python_2'] .
                    ' ' . APP_ROOT . "core/scripts/mp3md5_mod.py -3 " . escapeshellarg($absolutePath);
                break;
            case 'flac':
                $cmd =  $this->conf['modules']['bin_metaflac'] .
                    ' --show-md5sum ' . escapeshellarg($absolutePath);
                break;
            default:
                # TODO: can we get md5sum with php in a performant way?
                $cmd = $this->conf['modules']['bin_md5'] .' ' . escapeshellarg($absolutePath) . ' | awk \'{ print $1 }\'';
        }
        if($returnCommand === TRUE) {
            return $cmd;
        }
        #echo $cmd . "\n";
        $response = exec($cmd);
        if(preg_match("/^[0-9a-f]{32}$/", $response)) {
            return $response;
        }
        return FALSE;
    }

    public static function getDominantColor($absolutePath, $width, $height) {
        $quality = $width*$height/10;
        $quality = ($quality < 10) ? 10 : $quality;
        try {
            return rgb2hex(\ColorThief\ColorThief::getColor($absolutePath, $quality));
        } catch(\Exception $e) {
            return "#000000";
        }
    }
}
