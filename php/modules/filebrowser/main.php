<?php
namespace Slimpd;

class filebrowser {
	
	public $directory;
	public $base;
	public $subDirectories = array();
	public $files = array();
	public $breadcrumb = array();
	
	public function getDirectoryContent($d) {
		$app = \Slim\Slim::getInstance();
		
		// append trailing slash if missing
		$d .= (substr($d,-1) !== DS) ? DS : '';
		
		$base = $app->config['mpd']['musicdir'];
		
		$d = ($d === $base) ? '' : $d;
		
		if(is_dir($base .$d) === FALSE){ //} || $this->checkAccess($d, $baseDirs) === FALSE) {
			\Slim\Slim::getInstance()->notFound();
		}
		
		
		// avoid path disclosure outside relevant directories
		$realpath = realpath($base.$d) . DS;
		if(stripos($realpath, $app->config['mpd']['musicdir']) !== 0
		&& stripos($realpath, $app->config['mpd']['alternative_musicdir']) !== 0 ) {
			var_dump($realpath); die();
			\Slim\Slim::getInstance()->notFound();
		}

		$this->directory = $d;
		$bread = trimExplode(DS, $d, TRUE);
		
		$breadgrow = "";
		foreach($bread as $part) {
			$breadgrow .= DS . $part;
			$this->breadcrumb[] = new _Directory($breadgrow);
		}
	
		//if($this->checkAccess($d) === FALSE) {
		//	die('sorry, you are not allowed to view this directory 8==========D');
		//}
		
		$files = scandir($base . $d);
		natcasesort($files);
		
		if( count($files) > 2 ) { /* The 2 accounts for . and .. */
			foreach( $files as $file ) {
				if( file_exists($base. $d . $file) && $file != '.' && $file != '..' && substr($file,0,1) !== '.' ) {
					if(is_dir($base . $d . $file) === TRUE) {
						$this->subDirectories[] = new _Directory($d . $file);
					} else {
						$this->files[] = new File($d . $file);
					}
				}
			}
		}
		#echo "<pre>" . print_r($this,1); die();
		#echo json_encode($data);
		return ;
	}
}
