<?php


$importer = new \Slimpd\importer();



// IMPORTANT TODO: avoid simultaneously executet updates

$app->get('/', function () use ($app, $importer) {
	
	// check if we have something to process
	if($importer->checkQue() === TRUE) {
		$importer->triggerImport();
	}
    echo "Hello, kitty\n"; exit;
});


$app->get('/debugmig', function () use ($app, $importer) {
	$importer->checkQue();
	$importer->migrateRawtagdataTable();
});


$app->get('/standard', function () use ($app, $importer) {
	$importer->triggerImport();
});

$app->get('/remigrate', function () use ($app, $importer) {
	$importer->triggerImport(TRUE);
});


$app->get('/builddictsql', function () use ($app, $importer) {
	$importer->buildDictionarySql();
});

$app->get('/database-cleaner', function () use ($app) {
	// phase 11: delete all bitmap-database-entries that does not exist in filesystem
	// TODO: limit this to embedded images only
	$importer->deleteOrphanedBitmapRecords();
	
	// TODO: delete orphaned artists + genres + labels
});


$app->get('/update-db-scheme', function () use ($app, $argv) {
	$action = 'migrate';

	// TODO: manually performed db-changes does not get recognized here - find a solution!

	// check if we can query the revisions table
	$query = "SELECT * FROM db_revisions";
	$result = $app->db->query($query);
	if($result === FALSE) {
		// obviosly table(s) never have been created
		// let's force initial creation of all tables
		$action = 'init';
	}

	Helper::setConfig( getDatabaseDiffConf($app) );
	if (!Helper::checkConfigEnough()) {
	    Output::error('mmp: please check configuration');
	    die(1);
	}

	# after database-structure changes we have to
	# 1) uncomment next line
	# 2) run ./cli-update.php update-db-scheme
	# 3) recomment this line again
	# to make a new revision
	#$action = 'create';

	$controller = Helper::getController($action, NULL);
	if ($controller !== false) {
	    $controller->runStrategy();
	} else {
	    Output::error('mmp: unknown command "'.$cli_params['command']['name'].'"');
	    Helper::getController('help')->runStrategy();
	    die(1);
	}

	if($action !== 'init') {
		exit;
	}

	foreach(\Slimpd\Importer::getInitialDatabaseQueries() as $query) {
		$app->db->query($query);
	}
});
