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
			// TODO: how to check if indexed schema is correct?
			//'sxSchema'		=> array('status' => 'warning', 'hide' => FALSE, 'skip' => FALSE),
			
			// fingerprints and waveforms
			'fpMp3'		=> array('status' => 'warning', 'hide' => FALSE, 'skip' => FALSE,
				'filepath' => APP_ROOT . 'templates/partials/systemcheck/waveforms/testfiles/testfile.mp3',
				'cmd' => '',
				'resultExpected' => '3b8ad4119fa46a6b56c51aa35d78c15d',
				'resultReal' => FALSE,
			),
			'wfMp3'		=> array('status' => 'warning', 'hide' => FALSE, 'skip' => TRUE,
				'filepath' => APP_ROOT . 'templates/partials/systemcheck/waveforms/testfiles/testfile.mp3',
				'cmd' => '',
				'resultExpected' => '',
				'resultReal' => FALSE,
			),
		);
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
			} else {
				$check['fs'. $dir]['status'] = 'success';
			}
		}
	
	
	
		// check if we can connect to database
		if($app->request->get('dberror') !== NULL) {
			$check['dbConn']['status'] = 'danger';
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
			$db = \Helper::getDbObject();
	        $tmpdb = \Helper::getTmpDbObject();
	        \Helper::loadTmpDb($tmpdb);
	        $diff = new \dbDiff($db, $tmpdb);
	        $difference = $diff->getDifference();
	        if(!count($difference['up']) && !count($difference['down'])) {
				$check['dbSchema']['status'] = 'success';
				$check['dbContent']['skip'] = FALSE;
			} else {
				$check['dbSchema']['status'] = 'danger';
			}
		}
	
		// check if we have useful records in our database
		if($check['dbContent']['skip'] === FALSE) {
			$check['dbContent']['tracks']  = \Slimpd\Track::getCountAll();
			$check['dbContent']['albums']  = \Slimpd\Album::getCountAll();
			$check['dbContent']['artists'] = \Slimpd\Artist::getCountAll();
			$check['dbContent']['genres']  = \Slimpd\Genre::getCountAll();
			$check['dbContent']['labels']  = \Slimpd\Label::getCountAll();
			$check['dbContent']['status'] = ($check['dbContent']['tracks'] > 0)
				? 'success'
				: 'danger';
		}
	
	
		// check MPD connection
		$mpd = new \Slimpd\modules\mpd\mpd();
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
		}
		
		// check if can extract a fingerprint of mp3 file
		if($check['fpMp3']['skip'] === FALSE) {
			$check['fpMp3']['cmd'] = \Slimpd\Importer::extractAudioFingerprint($check['fpMp3']['filepath'], TRUE);
			exec($check['fpMp3']['cmd'], $response);
			$check['fpMp3']['resultReal'] = trim(join("\n", $response));
			unset($response);
			if($check['fpMp3']['resultExpected'] === $check['fpMp3']['resultReal']) {
				$check['fpMp3']['status'] = 'success';
				if($check['fsPeakfiles']['status'] === 'success') {
					$check['wfMp3']['skip'] = FALSE;
				}
			} else {
				$check['fpMp3']['status'] = 'danger';
			}
		}
	
		if($check['wfMp3']['skip'] === FALSE) {
	
			// make sure we retrieve nothing cached
			@unlink(APP_ROOT . 'peakfiles/mp3/3b8/3b8ad4119fa46a6b56c51aa35d78c15d');
			@unlink(APP_ROOT . "cache/mp3." . $check['fpMp3']['resultExpected'] . '.mp3');
			@unlink(APP_ROOT . "cache/mp3." . $check['fpMp3']['resultExpected'] . '.wav');
			
			$svgGenerator = new \Slimpd\Svggenerator([$check['wfMp3']['filepath']]);
			$check['wfMp3']['cmd'] = $svgGenerator->getCmdTempwav();
			exec($check['wfMp3']['cmd'], $response, $returnStatus);
			$check['wfMp3']['resultReal'] = trim(join("\n", $response)); 
			if($returnStatus === 0) {
				$check['wfMp3']['status'] = 'success';
			} else {
				$check['wfMp3']['status'] = 'danger';
			}
			
			if(is_file(APP_ROOT . "cache/mp3." . $check['fpMp3']['resultExpected'] . '.mp3') === FALSE) {
				$check['wfMp3']['status'] = 'danger';
			}
			if(is_file(APP_ROOT . "cache/mp3." . $check['fpMp3']['resultExpected'] . '.wav') === FALSE) {
				$check['wfMp3']['status'] = 'danger';
			}
		}
		return $check;

	}
}
