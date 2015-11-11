<?php
namespace Slimpd\modules\database;
try {
    $app->container->singleton('db', function($app) {
    	try {
    		mysqli_report(MYSQLI_REPORT_STRICT);
			$dbh = new \mysqli(
				$app->config['database']['host'],
				$app->config['database']['username'],
				$app->config['database']['password'],
				$app->config['database']['database']
			);
		} catch (\Exception $e) {
			$app = \Slim\Slim::getInstance();
			if(PHP_SAPI === 'cli') {
				cliLog($app->ll->str('database.connect'), 1, 'red');
				$app->stop();
			}
			$app->flash('error', $app->ll->str('database.connect'));
			$app->redirect('/');
		}
		
		/*
		$dbh = new \PDO(
			"mysql:host=".$app->config['database']['host'] .
			";dbname=".$app->config['database']['database'],
			$app->config['database']['username'],
			$app->config['database']['password']
		);  
		$dbh->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		*/
		return $dbh;
    });
} catch(\Exception $e) {
    if ($debug) {
        echo '<pre><br><br>' . $e->getMessage() . '<br><br></pre>';
    }
	#$app->flashNow('error', $app->ll->str('database.connect'));
};
