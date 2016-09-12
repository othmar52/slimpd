<?php
namespace Slimpd\Modules\database;
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
 * FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License
 * for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
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
