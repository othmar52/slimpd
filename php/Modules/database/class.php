<?php
namespace Slimpd\Modules\database;
try {
    $app->container->singleton('db', function($app) {
    	try {
    		mysqli_report(MYSQLI_REPORT_STRICT);
			$dbh = new \mysqli(
				$app->config['database']['dbhost'],
				$app->config['database']['dbusername'],
				$app->config['database']['dbpassword'],
				$app->config['database']['dbdatabase']
			);
		} catch (\Exception $e) {
			$app = \Slim\Slim::getInstance();
			if(PHP_SAPI === 'cli') {
				cliLog($app->ll->str('database.connect'), 1, 'red');
				$app->stop();
			}
			$app->flash('error', $app->ll->str('database.connect'));
			$app->redirect($app->config['root'] . 'systemcheck?dberror');
			$app->stop();
			return;
		}
		
		/*
		$dbh = new \PDO(
			"mysql:host=".$app->config['database']['dbhost'] .
			";dbname=".$app->config['database']['dbdatabase'],
			$app->config['database']['dbusername'],
			$app->config['database']['dbpassword']
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
