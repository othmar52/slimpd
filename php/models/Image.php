<?php
namespace Slimpd;

class Image {
	
	function renderImageByAlbumId($album_id, $quality = 'hq') {
		
		$app = \Slim\Slim::getInstance();
		$query = "
			SELECT image FROM bitmap
			WHERE album_id='" . $app->db->real_escape_string($album_id) . "'
			LIMIT 1;
		";
		#echo $query;
		$result = $app->db->query($query);
		while($record = $result->fetch_assoc()) {
			$this->streamData($record['image'], 'image/jpeg', false, false, '"never_expire"');
		}
		die('error');
		// TODO: check code below 
		
		
		global $cfg, $db;
		require_once('getid3/getid3/getid3.php');
		/* $query  = mysql_query('SELECT image, image_front FROM bitmap WHERE image_id = "' . mysql_real_escape_string($image_id) . '" LIMIT 1');
		$bitmap = mysql_fetch_assoc($query) or imageError(); */
		
		if (!empty($track_id)) 
		$query  = mysql_query('SELECT bitmap.image, bitmap.image_front, track.relative_file, track.track_id, bitmap.image_id  FROM bitmap LEFT JOIN track on bitmap.album_id = track.album_id WHERE bitmap.image_id = "' . mysql_real_escape_string($image_id) . '" AND track.track_id = "' . mysql_real_escape_string($track_id) . '" LIMIT 1');
	
		else
		$query  = mysql_query('SELECT bitmap.image, bitmap.image_front, bitmap.image_id, track.relative_file  FROM bitmap LEFT JOIN track on bitmap.album_id = track.album_id WHERE bitmap.image_id = "' . mysql_real_escape_string($image_id) . '" LIMIT 1');
		
		$bitmap = mysql_fetch_assoc($query) or imageError();
		
		//get embedded picture for misc tracks
		if ((!empty($track_id)) && ((strpos(strtolower($bitmap['relative_file']), strtolower($cfg['misc_tracks_folder'])) !== false) || (strpos(strtolower($bitmap['relative_file']), strtolower($cfg['misc_tracks_misc_artists_folder'])) !== false))) {
			// Initialize getID3
			$getID3 = new getID3;
			//initial settings for getID3:
			include 'include/getID3init.inc.php';
			$path2file = $cfg['media_dir'] . $bitmap['relative_file'];
			$getID3->analyze($path2file);
			
			if (isset($getID3->info['error']) == false &&
				isset($getID3->info['comments']['picture'][0]['image_mime']) &&
				isset($getID3->info['comments']['picture'][0]['data']) &&
				($getID3->info['comments']['picture'][0]['image_mime'] == 'image/jpeg' || $getID3->info['comments']['picture'][0]['image_mime'] == 'image/png')) {
					$redImg = $getID3->info['comments']['picture'][0]['data'];
					header('Cache-Control: max-age=31536000');
					streamData($redImg, 'image/jpeg', false, false, '"never_expire"');	
			}
			else {
				/* $image = imagecreatefromjpeg(NJB_HOME_DIR . 'image/misc_image.jpg');
				header("Content-type: image/jpeg");
				imagejpeg($image);
				imagedestroy($image); */
				header('Cache-Control: max-age=31536000');
				streamData($bitmap['image'], 'image/jpeg', false, false, '"never_expire"');
			}
			
		}
		elseif ($bitmap['image_front'] == '') {
			header('Cache-Control: max-age=31536000');
			streamData($bitmap['image'], 'image/jpeg', false, false, '"never_expire"');
		}
		elseif ($quality == 'hq') {
			if (strpos($bitmap['image_front'],"misc_image.jpg") === false){ 
				$path2file = $cfg['media_dir'] . $bitmap['image_front'];
				if (is_file($path2file)) {
					$image = imagecreatefromjpeg($path2file);
					header("Content-type: image/jpeg");
					imagejpeg($image);
					imagedestroy($image);
				}
				elseif (strpos($bitmap['image_id'],"no_image") !== false) {
					$image = imagecreatefromjpeg(NJB_HOME_DIR . 'image/no_image.jpg');
					header("Content-type: image/jpeg");
					imagejpeg($image);
					imagedestroy($image);
				}
				else {
					//$image = imagecreatefromjpeg('image/no_image.jpg');
					header('Cache-Control: max-age=31536000');
					streamData($bitmap['image'], 'image/jpeg', false, false, '"never_expire"');	
				}
			}
			else {
				if (is_file(NJB_HOME_DIR . 'image/misc_image.jpg')) {
					$image = imagecreatefromjpeg(NJB_HOME_DIR . 'image/misc_image.jpg');
					header("Content-type: image/jpeg");
					imagejpeg($image);
					imagedestroy($image);
				}
				else imageError();
			}			
		}
		else {
			/* $nFile = str_replace('folder.jpg', 'th_folder.jpg',$bitmap['image_front']);
			if (file_exists($cfg['media_dir'] . $nFile)) {
				$image = imagecreatefromjpeg($cfg['media_dir'] . $nFile);
				header("Content-type: image/jpeg");
				imagejpeg($image);
				imagedestroy($image);		
			}
			else {
			 */
			header('Cache-Control: max-age=31536000');
			streamData($bitmap['image'], 'image/jpeg', false, false, '"never_expire"');	
			//}
		}
		
	}
	
	
	
	
	//  +------------------------------------------------------------------------+
	//  | Resample image                                                         |
	//  +------------------------------------------------------------------------+
	function resampleImage($image, $size = NJB_IMAGE_SIZE) {
		global $cfg, $db;
		authenticate('access_admin', true);
		
		if (substr($image, 0, 7) != 'http://' && substr($image, 0, 8) != 'https://')
			imageError();
		
		$extension = substr(strrchr($image, '.'), 1);
		$extension = strtolower($extension);
		
		if		($extension == 'jpg')	$src_image = @imageCreateFromJpeg($image) 	or imageError();
		elseif	($extension == 'jpeg')	$src_image = @imageCreateFromJpeg($image)	or imageError();
		elseif	($extension == 'png')	$src_image = @imageCreateFromPng($image)	or imageError();
		else {
			$imagesize = @getimagesize($image) or imageError();
			if ($imagesize[2] == IMAGETYPE_JPEG) {
				$src_image = @imageCreateFromJpeg($image) or imageError();
				$extension = 'jpg';
			}
			elseif ($imagesize[2] == IMAGETYPE_PNG) {
				$src_image = @imageCreateFromJpeg($image) or imageError();
				$extension = 'png';
			}
			else
				imageCreateFromPng('image/image_error.png');
			
		}
		
		if (($extension == 'jpg' || $extension == 'jpeg') && imageSX($src_image) == $size && imageSY($src_image) == $size) {
			$data = @file_get_contents($image) or imageError();
		}
		elseif (imageSY($src_image) / imageSX($src_image) <= 1) {
			// Crops from left and right to get a squire image.
			$sourceWidth		= imageSY($src_image);
			$sourceHeight		= imageSY($src_image);
			$sourceX			= round((imageSX($src_image) - imageSY($src_image)) / 2);
			$sourceY			= 0;
		}
		else {
			// Crops from top and bottom to get a squire image.
			$sourceWidth		= imageSX($src_image);
			$sourceHeight		= imageSX($src_image);
			$sourceX			= 0;
			$sourceY			= round((imageSY($src_image) - imageSX($src_image)) / 2);
		}
		if (isset($sourceWidth)) {
			$dst_image = ImageCreateTrueColor($size, $size);
			imageCopyResampled($dst_image, $src_image, 0, 0, $sourceX, $sourceY, $size, $size, $sourceWidth, $sourceHeight);
			ob_start();
			imageJpeg($dst_image, NULL, NJB_IMAGE_QUALITY);
			$data = ob_get_contents();
			ob_end_clean();
			imageDestroy($dst_image);
		}
		imageDestroy($src_image);
		
		header('Cache-Control: max-age=600');
		streamData($data, 'image/jpeg');
	}
	
	
	
	
	//  +------------------------------------------------------------------------+
	//  | Share image                                                            |
	//  +------------------------------------------------------------------------+
	function shareImage() {
		global $cfg, $db;
		
		if ($cfg['image_share_mode'] == 'played') {
			$query = mysql_query('SELECT image, artist, album, filesize, filemtime, album.album_id
				FROM counter, album, bitmap
				WHERE counter.flag <= 1
				AND counter.album_id = album.album_id
				AND counter.album_id = bitmap.album_id
				ORDER BY counter.time DESC
				LIMIT 1');
			$bitmap = mysql_fetch_assoc($query);
			$text	=  'Recently played:';
		}
		else {
			$query	= mysql_query('SELECT image, artist, album, filesize, filemtime, album.album_id
				FROM album, bitmap 
				WHERE album.album_id = bitmap.album_id 
				ORDER BY album_add_time DESC
				LIMIT 1');
			$bitmap = mysql_fetch_assoc($query);
			$text	=  'New album:';
			$cfg['image_share_mode'] = 'new';
		}
		
		$etag = '"' . md5($bitmap['album_id'] . $cfg['image_share_mode'] . $bitmap['filemtime'] . '-' . $bitmap['filesize'] . '-' . filemtime('image/share.png')) . '"';
		if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == $etag) {
			header('HTTP/1.1 304 Not Modified');
			header('ETag: ' . $etag);
			header('Cache-Control: max-age=5');
			exit();
		}
		
		// Background (253 x 52 pixel)
		$dst_image = imageCreateFromPng('image/share.png');
		
		// Image copy source NJB_IMAGE_SIZE x NJB_IMAGE_SIZE => 50x50
		$src_image = imageCreateFromString($bitmap['image']);
		imageCopyResampled($dst_image, $src_image, 1, 1, 0, 0, 50, 50, NJB_IMAGE_SIZE, NJB_IMAGE_SIZE);
		imageDestroy($src_image);
		
		// Text
		$font		= NJB_HOME_DIR . 'fonts/DejaVuSans.ttf';
		$font_color = imagecolorallocate($dst_image, 0, 0, 99);
		imagettftext($dst_image, 8, 0, 55, 13, $font_color, $font, $text);
		imagettftext($dst_image, 8, 0, 55, 30, $font_color, $font, $bitmap['artist']);
		imagettftext($dst_image, 8, 0, 55, 47, $font_color, $font, $bitmap['album']);
		
		// For to long text overwrite 4 pixels right margin
		$src_image = imageCreateFromPng('image/share.png');
		ImageCopy($dst_image, $src_image, 249, 0, 249, 0, 4, 52);
		imageDestroy($src_image);
		
		// Buffer data
		ob_start();
		ImagePng($dst_image);
		$data = ob_get_contents();
		ob_end_clean();
		
		imageDestroy($dst_image);
		
		header('Cache-Control: max-age=5');
		streamData($data, 'image/jpeg', false, false, $etag);
	}
	
	
	
	
	//  +------------------------------------------------------------------------+
	//  | Image error                                                            |
	//  +------------------------------------------------------------------------+
	function imageError() {
		$etag = '"image_error_' . dechex(filemtime('image/image_error.png')) . '"';
		//$etag = "never_expire";
		streamData(file_get_contents('image/image_error.png'), 'image/png', false, false, $etag);
		exit();
	}
	
	
	function streamData($data, $mime_type, $content_disposition = '', $filename = '', $etag = '') {
		ini_set('zlib.output_compression', 'off');
		ini_set('max_execution_time', 0);
		
		$filename	= str_replace('"', '\"', $filename); // Needed for double quoted content disposition
		$filesize	= strlen($data);
		$etag 		= ($etag == '') ? '"' . md5($data) . '"' : $etag;
		
		if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == $etag) {
			header('HTTP/1.1 304 Not Modified');
			header('ETag: ' . $etag);
			exit();
		}
		
		if (isset($_SERVER['HTTP_RANGE']) && isset($_SERVER['HTTP_IF_RANGE']) && $_SERVER['HTTP_IF_RANGE'] != $etag) {
			header('HTTP/1.1 412 Precondition Failed');
			exit();
		}
		
		if (isset($_SERVER['HTTP_RANGE']) && preg_match('#bytes=([0-9]*)-([0-9]*)#', $_SERVER['HTTP_RANGE'], $match)) {
			$range_start	= $match[1];
			$range_end   	= $match[2];
			
			if ($range_start >= 0 && $range_end == '') {
				$range_end		= $filesize - 1;
			}
			elseif ($range_start == '' && $range_end >= 0) {
				$range_start	= $filesize - $range_end;
				$range_end		= $filesize - 1;
			}
			
			if ($range_start == '' || $range_end == '' || $range_start < 0 || $range_start > $range_end || $range_end > $filesize - 1) {
		    	header('Status: 416 Requested Range Not Satisfiable');
		    	header('Content-Range: */' . $filesize);
				exit();
			}
			
			$length	= $range_end - $range_start + 1;
			
			header('HTTP/1.1 206 Partial Content');
			header('ETag: ' . $etag);
			header('Accept-Ranges: bytes');
			header('Content-Length: ' . $length);
			header('Content-Range: bytes ' . $range_start . '-' . $range_end . '/' . $filesize);
			header('Content-Type: ' . $mime_type);
			
			// Content-Disposition: attachment; filename="album.zip"
			// Content-Disposition: inline; filename="cover.pdf"
			if ($content_disposition != '' && $filename != '')
				header('Content-Disposition: ' . $content_disposition . '; filename="' . $filename . '"');
			
			echo substr($data, $range_start, $range_end);
		}
		else {
			header('ETag: ' . $etag);
			header('Accept-Ranges: bytes');
			header('Content-Length: ' . $filesize);
			header('Content-Type: ' . $mime_type);
			if ($content_disposition != '' && $filename != '')
				header('Content-Disposition: ' . $content_disposition . '; filename="' . $filename . '"');
			
			echo $data;
		}
	}
}
