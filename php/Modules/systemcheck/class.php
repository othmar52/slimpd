<?php
namespace Slimpd;
/**
 * 
 * TODO: refacture with a new Check model
 */
class Systemcheck {
	protected $config;
	protected $checks;
	
	
	public function __construct() {
		$this->config = \Slim\Slim::getInstance()->config;
	}

	public function runChecks() {
		$check = array(
	
			// filesystem
			'fsMusicdirconf'=> array('status' => 'warning', 'hide' => FALSE, 'skip' => FALSE),
			'fsMusicdirslash'=> array('status' => 'warning','hide' => FALSE, 'skip' => TRUE),
			'fsMusicdir'    => array('status' => 'warning', 'hide' => FALSE, 'skip' => TRUE),
			'fsCache'		=> array('status' => 'warning', 'hide' => FALSE, 'skip' => FALSE),
			'fsEmbedded'	=> array('status' => 'warning', 'hide' => FALSE, 'skip' => FALSE),
			'fsPeakfiles'	=> array('status' => 'warning', 'hide' => FALSE, 'skip' => FALSE),
			// TODO: perform checks for optional configuration [mpd]alternative_musicdir
	
			// database
			'dbConn'		=> array('status' => 'warning', 'hide' => FALSE, 'skip' => FALSE),
			'dbPerms'		=> array('status' => 'warning', 'hide' => FALSE, 'skip' => TRUE),
			'dbSchema'		=> array('status' => 'warning', 'hide' => FALSE, 'skip' => TRUE),
			'dbContent'		=> array('status' => 'warning', 'hide' => FALSE, 'skip' => TRUE, 'tracks' => 0),
	
			// mpd
			'mpdConn'		=> array('status' => 'warning', 'hide' => FALSE, 'skip' => FALSE),
			'mpdDbfileconf'	=> array('status' => 'warning', 'hide' => FALSE, 'skip' => FALSE),
			'mpdDbfile'		=> array('status' => 'warning', 'hide' => FALSE, 'skip' => TRUE),
	
			// sphinx
			'sxConn'		=> array('status' => 'warning', 'hide' => FALSE, 'skip' => FALSE),
			'sxSchema'		=> array('status' => 'warning', 'hide' => FALSE, 'skip' => FALSE),
			'sxContent'		=> array('status' => 'warning', 'hide' => FALSE, 'skip' => FALSE),
			
		);

		$audioFormats = array(
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
		
		$check['audioFormats'] = array_keys($audioFormats);
		$check['audioFormatsUc'] = array_map('ucfirst', $check['audioFormats']);
		$check['skipAudioTests'] = FALSE;
		
		foreach($audioFormats as $format => $data) {
			$check['fp'.ucfirst($format)] = array('status' => 'warning', 'hide' => FALSE, 'skip' => FALSE,
				'filepath' => APP_ROOT . 'templates/partials/systemcheck/waveforms/testfiles/' . array_values($data)[0],
				'cmd' => '',
				'resultExpected' => array_keys($data)[0],
				'resultReal' => FALSE,
			);
			$check['wf'.ucfirst($format)] = array('status' => 'warning', 'hide' => FALSE, 'skip' => TRUE,
				'filepath' => APP_ROOT . 'templates/partials/systemcheck/waveforms/testfiles/' . array_values($data)[0],
				'cmd' => ''
			);
			
		}

		$app = \Slim\Slim::getInstance();

	
	
		// check if we have a configured value for MPD-musicdirectory
		if(trim($this->config['mpd']['musicdir']) === '') {
			$check['fsMusicdirconf']['status'] = 'danger';
			$check['fsMusicdirslash']['hide'] = TRUE;
			$check['fsMusicdir']['hide'] = TRUE;
			$check['fsMusicdirslash']['skip'] = TRUE;
			$check['fsMusicdir']['skip'] = TRUE;
		} else {
			$check['fsMusicdirconf']['hide'] = TRUE;
			$check['fsMusicdirslash']['skip'] = FALSE;
			$check['fsMusicdir']['skip'] = FALSE;
		}
	
		// check if we have a trailing slash on [mpd].musicdir
		if($check['fsMusicdirslash']['skip'] === FALSE) {
			if(substr($this->config['mpd']['musicdir'],-1) !== DS) {
				$check['fsMusicdirslash']['status'] = 'danger';
			} else {
				$check['fsMusicdirslash']['hide'] = TRUE;
			}
		}
	
		// check if we can access [mpd]-musicdir
		// TODO: check if there is any content inside
		// TODO: is it possible to read this from mpd API instead of configuring it manually?
		if($check['fsMusicdir']['skip'] === FALSE) {
			if(is_dir($this->config['mpd']['musicdir']) === FALSE || is_readable($this->config['mpd']['musicdir']) === FALSE) {
				$check['fsMusicdir']['status'] = 'danger';
			} else {
				$check['fsMusicdir']['status'] = 'success';
			}
		}
	
		// check filesystem access for writable directories
		foreach(['Cache', 'Embedded', 'Peakfiles'] as $dir) {
			if(is_dir(APP_ROOT . strtolower($dir)) === FALSE || is_writeable(APP_ROOT . strtolower($dir)) === FALSE) {
				$check['fs'. $dir]['status'] = 'danger';
				$check['skipAudioTests'] = TRUE;
			} else {
				$check['fs'. $dir]['status'] = 'success';
			}
		}
	
	
	
		// check if we can connect to database
		if($app->request->get('dberror') !== NULL) {
			$check['dbConn']['status'] = 'danger';
			$check['skipAudioTests'] = TRUE;
		} else {
			$check['dbConn']['status'] = 'success';
			$check['dbPerms']['skip'] = FALSE;
		}
	
		// check permissions for "create database" (needed for schema-comparison)
		if($check['dbPerms']['skip'] === FALSE) {
			$tmpDb = $this->config['database']['dbdatabase']."_prmchk";
			$result = $app->db->query("CREATE DATABASE ". $tmpDb .";");
			if (!$result) {#
				$check['dbPerms']['status'] = 'danger';
			} else {
				$app->db->query("DROP DATABASE ". $tmpDb .";");
				$check['dbPerms']['status'] = 'success';
				$check['dbSchema']['skip'] = FALSE;
			}
		}
	
		// check if db-schema is correct
		if($check['dbSchema']['skip'] === FALSE) {
			\Helper::setConfig( getDatabaseDiffConf($app) );
	        $tmpdb = \Helper::getTmpDbObject();
	        \Helper::loadTmpDb($tmpdb);
	        $diff = new \dbDiff(\Helper::getDbObject(), $tmpdb);
	        $difference = $diff->getDifference();
	        if(!count($difference['up']) && !count($difference['down'])) {
				$check['dbSchema']['status'] = 'success';
				$check['dbContent']['skip'] = FALSE;
			} else {
				$check['dbSchema']['status'] = 'danger';
				$check['skipAudioTests'] = TRUE;
			}
		}
	
		// check if we have useful records in our database
		if($check['dbContent']['skip'] === FALSE) {
			$check['dbContent']['tracks']  = \Slimpd\Models\Track::getCountAll();
			$check['dbContent']['albums']  = \Slimpd\Models\Album::getCountAll();
			$check['dbContent']['artists'] = \Slimpd\Models\Artist::getCountAll();
			$check['dbContent']['genres']  = \Slimpd\Models\Genre::getCountAll();
			$check['dbContent']['labels']  = \Slimpd\Models\Label::getCountAll();
			$check['dbContent']['status'] = ($check['dbContent']['tracks'] > 0)
				? 'success'
				: 'danger';
		}
	
	
		// check MPD connection
		$mpd = new \Slimpd\Modules\mpd\mpd();
		$check['mpdConn']['status'] = ($mpd->cmd('status') === FALSE) ? 'danger' : 'success';
	
		// check if we have a configured value for MPD-databasefile
		if(trim($this->config['mpd']['dbfile']) === '') {
			$check['mpdDbfileconf']['status'] = 'danger';
			$check['mpdDbfile']['hide'] = TRUE;
		} else {
			$check['mpdDbfile']['skip'] = FALSE;
			$check['mpdDbfileconf']['hide'] = TRUE;
		}
	
		// check if MPD databasefile is readable
		if($check['mpdDbfile']['skip'] === FALSE) {
			if(is_file($this->config['mpd']['dbfile']) == FALSE || is_readable($this->config['mpd']['dbfile']) === FALSE) {
				$check['mpdDbfile']['status'] = 'danger';
			} else {
				$check['mpdDbfile']['status'] = 'success';
			}
		}
	
	
		// check sphinx connection
		$check['sxConn']['status'] = 'success';
		try {
			$ln_sph = new \PDO('mysql:host='.$this->config['sphinx']['host'].';port=9306;charset=utf8;', '','');
		} catch (\Exception $e) {
			$check['sxConn']['status'] = 'danger';
			$check['sxSchema']['skip'] = TRUE;
			$check['sxContent']['skip'] = TRUE;
		}
		
		// check if we can query both sphinx indices
		if($check['sxSchema']['skip'] === FALSE) {
			$schemaError = FALSE;
			$contentError = FALSE;
			foreach(['main', 'suggest'] as $indexName) {
				$ln_sph = new \PDO('mysql:host='.$app->config['sphinx']['host'].';port=9306;charset=utf8;', '','');
				$stmt = $ln_sph->prepare(
					"SELECT ". $app->config['sphinx']['fields_'.$indexName]." FROM ". $app->config['sphinx'][$indexName . 'index']." LIMIT 1;"
				);
				$stmt->execute();
				if($stmt->errorInfo()[0] > 0) {
					$check['sxSchema']['status'] = 'danger';
					$check['sxSchema']['msg'] = $stmt->errorInfo()[2];
					$schemaError = TRUE;
					$check['sxContent']['skip'] = TRUE;
				} else {
					$check['sxSchema']['status'] = 'sucess';
					$check['sxContent']['skip'] = FALSE;
					$meta = $ln_sph->query("SHOW META")->fetchAll();
					foreach($meta as $m) {
						if($m['Variable_name'] === 'total_found') {
							if($m['Value'] < 1) {
								$contentError = TRUE;
							} else {
								$check['sxContent'][$indexName]['total'] = $m['Value'];
							}
						}
					}
				}
			}
			$check['sxSchema']['status'] = ($schemaError === TRUE) ? 'danger' : 'success';
			$check['sxContent']['status'] = ($contentError === TRUE) ? 'danger' : 'success';
			if($schemaError === TRUE) {
				$check['sxContent']['status'] = 'warning';
			}
		}
		
		

		if($check['skipAudioTests'] === TRUE) {
			return $check;
		}

		// check if can extract a fingerprint of music file
		foreach($check['audioFormats'] as $ext) {
			$checkFp = 'fp'.ucfirst($ext);
			$checkWf = 'wf'.ucfirst($ext);
			if($check[$checkFp]['skip'] === FALSE) {
				$check[$checkFp]['cmd'] = \Slimpd\Modules\Importer::extractAudioFingerprint($check[$checkFp]['filepath'], TRUE);
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
		
			if($check[$checkWf]['skip'] === FALSE) {
				$peakfile = APP_ROOT . "peakfiles/".$ext.DS. substr($check[$checkFp]['resultExpected'],0,3) . DS . $check[$checkFp]['resultExpected'];
				#echo $peakfile; die();
				$tmpMp3 = APP_ROOT . "cache/".$ext."." . $check[$checkFp]['resultExpected'] . '.mp3';
				$tmpWav = APP_ROOT . "cache/".$ext."." . $check[$checkFp]['resultExpected'] . '.wav';
		
				// make sure we retrieve nothing cached
				@unlink($peakfile);
				@unlink($tmpMp3);
				@unlink($tmpWav);
				
				$svgGenerator = new \Slimpd\Svggenerator([$check[$checkWf]['filepath']]);
				
				$check[$checkWf]['cmd'] = $svgGenerator->getCmdTempwav();

				exec($check[$checkWf]['cmd'], $response, $returnStatus);
				if($returnStatus === 0) {
					$check[$checkWf]['status'] = 'success';
				} else {
					$check[$checkWf]['status'] = 'danger';
				}
				
				if(is_file($tmpMp3) === FALSE) {
					$check[$checkWf]['status'] = 'danger';
				}
				if(is_file($tmpWav) === FALSE) {
					$check[$checkWf]['status'] = 'danger';
				}
				unset($response);
				unset($returnStatus);
			}
		}
		return $check;

	}
}
