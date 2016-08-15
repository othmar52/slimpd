<?php


// TODO: carefully check which sorting is possible for each model (@see config/sphinx.example.conf:srcslimpdmain)
//   compare with templates/partials/dropdown-search-sorting.htm
//   compare with templates/partials/dropdown-typelist-sorting.htm
$sortfields1 = array(
	"artist" => array("title", "trackCount", "albumCount"),
	"genre" => array("title", "trackCount", "albumCount"),
	"label" => array("title", "trackCount", "albumCount")
);

$sortfields2 = array(
	"all" => array("title", "artist", "year", "added"),
	"track" => array("title", "artist", "year", "added"),
	"album" => array("year", "title", "added", "artist", "trackCount"),
	"dirname" => array("title", "added", "trackCount"),
);

foreach(array_keys($sortfields1) as $className) {
	foreach(["album","track"] as $show) {
		
		// albumlist+tracklist of artist|genre|label
		$app->get(
		"/".$className."/:idemId/".$show."s/page/:currentPage/sort/:sort/:direction",
		function($itemId, $currentPage, $sort, $direction) use ($app, $vars, $className, $show, $sortfields1) {
			$vars["action"] = $className."." . $show."s";
			$vars["itemtype"] = $className;
			$vars["listcurrent"] = $show;
			$vars["itemlist"] = [];
			
			$classPath = "\\Slimpd\\" . ucfirst($className);
			
			// TODO: check where %20 on multiple artist-ids come from
			$itemId = str_replace("%20", ",", $itemId);
			
			$term = str_replace(",", " ", $itemId);
			$vars["item"] = $classPath::getInstanceByAttributes(array("id" => $itemId));
			
			$vars["itemids"] = $itemId;
			$itemsPerPage = 20;
			$maxCount = 1000;
			
			// TODO: move sphinx constants to somewhere else
			foreach(["freq_threshold", "suggest_debug", "length_threshold", "levenshtein_threshold", "top_count"] as $var) {
				define (strtoupper($var), intval($app->config["sphinx"][$var]) );
			}
			$ln_sph = new \PDO("mysql:host=".$app->config["sphinx"]["host"].";port=9306;charset=utf8;", "","");
			
			foreach(["album","track"] as $resultType) {
				
				// get total results for all types (albums + tracks)
				$sphinxTypeIndex = ($resultType === "album") ? 2 : 4;
				$stmt = $ln_sph->prepare("
					SELECT id FROM ". $app->config["sphinx"]["mainindex"]."
					WHERE MATCH('@".$className."Ids \"". $term ."\"')
					AND type=:type
					GROUP BY itemid,type
					LIMIT 1;
				");
				$stmt->bindValue(":type", $sphinxTypeIndex, PDO::PARAM_INT);
				$stmt->execute();
				$meta = $ln_sph->query("SHOW META")->fetchAll();
				$vars["search"][$resultType]["total"] = 0;
				foreach($meta as $m) {
					if($m["Variable_name"] === "total_found") {
						$vars["search"][$resultType]["total"] = $m["Value"];
					}
				}
				$vars["search"][$resultType]["time"] = 0;
				$vars["search"][$resultType]["term"] = $itemId;
				$vars["search"][$resultType]["matches"] = [];
				
				if($resultType === $show) {
	
					$sortQuery = ($sort !== "relevance")?  " ORDER BY " . $sort . " " . $direction : "";
					$vars["search"]["activesorting"] = $sort . "-" . $direction;
					
					$stmt = $ln_sph->prepare("
						SELECT id,type,itemid,artistIds,display
						FROM ". $app->config["sphinx"]["mainindex"]."
						WHERE MATCH('@".$className."Ids \"". $term ."\"')
						AND type=:type
						GROUP BY itemid,type
							".$sortQuery."
							LIMIT :offset,:max
						OPTION ranker=proximity, max_matches=".$vars["search"][$resultType]["total"].";
					");
					$stmt->bindValue(":offset", ($currentPage-1)*$itemsPerPage , PDO::PARAM_INT);
					$stmt->bindValue(":max", $itemsPerPage, PDO::PARAM_INT);
					$stmt->bindValue(":type", $sphinxTypeIndex, PDO::PARAM_INT);
					
					$vars["search"][$resultType]["time"] = microtime(TRUE);
					
					$stmt->execute();
					$rows = $stmt->fetchAll();
					
					foreach($rows as $row) {
						switch($row["type"]) {
							case "2":
								$obj = \Slimpd\Album::getInstanceByAttributes(array("id" => $row["itemid"]));
								break; 
							case "4":
								$obj = \Slimpd\Track::getInstanceByAttributes(array("id" => $row["itemid"]));
								break;
						}
						$vars["itemlist"][] = $obj;
					}
					
					$vars["search"][$resultType]["time"] = number_format(microtime(TRUE) - $vars["search"][$resultType]["time"],3);
					
					$vars["paginator"] = new JasonGrimes\Paginator(
						$vars["search"][$resultType]["total"],
						$itemsPerPage,
						$currentPage,
						$app->config["root"] .$className."/".$itemId."/".$show."s/page/(:num)/sort/".$sort."/".$direction
					);
					$vars["paginator"]->setMaxPagesToShow(paginatorPages($currentPage));
				}
			}
			$vars["renderitems"] = getRenderItems($vars["itemlist"]);
		    $app->render("surrounding.htm", $vars);
		});
		
	}
}


$app->get("/alphasearch/", function() use ($app, $vars){
	$type = $app->request()->get("searchtype");
	$term = $app->request()->get("searchterm");
	$app->response->redirect($app->config["root"] . $type."s/searchterm/".rawurlencode($term)."/page/1" . getNoSurSuffix());
});

$sortfields = array_merge($sortfields1, $sortfields2);
foreach(array_keys($sortfields) as $currentType) {
	$app->get(
		"/search".$currentType."/page/:currentPage/sort/:sort/:direction",
		function($currentPage, $sort, $direction) use ($app, $vars, $currentType, $sortfields){
		foreach(["freq_threshold", "suggest_debug", "length_threshold", "levenshtein_threshold", "top_count"] as $var) {
			define (strtoupper($var), intval($app->config["sphinx"][$var]) );
		}
		
		# TODO: evaluate if modifying searchterm makes sense
		// "Artist_-_Album_Name-(CAT001)-WEB-2015" does not match without this modification
		$term = cleanSearchterm($app->request()->params("q"));
		
		
		# TODO: read a blacklist of searchterms from configfile
		// searching "mp3" can be bad for our snappy gui
		// at least we have to skip the "total results" query for each type
		// for now - redirect immediately
		if(strtolower(trim($term)) === "mp3" || strtolower(trim($term)) === "mu") {
			$app->flashNow("error", "OH SNAP! searchterm <strong>". $term ."</strong> is currently blacklisted...");
			$app->render("surrounding.htm", $vars);
			return;
		}
		
		$ranker = "sph04";
		$start = 0;
		$itemsPerPage = 20;
		$maxCount = 1000;
		$result = [];
		
		$ln_sph = new \PDO("mysql:host=".$app->config["sphinx"]["host"].";port=9306;charset=utf8;", "","");
		
		// those values have to match sphinxindex:srcslimpdmain:type
		$filterTypeMapping = array(
			"artist" => 1,
			"album" => 2,
			"label" => 3,
			"track" => 4,
			"genre" => 5,
			"dirname" => 6,
		);
		$vars["itemlist"] = [];
		$vars["timelog"] = [];

		foreach(array_keys($sortfields) as $type) {
			$vars["timelog"][$type."-total"] = new \Slimpd\ExecutionTime();
			$vars["timelog"][$type."-total"]->Start();
			// get result count for each resulttype 
			$stmt = $ln_sph->prepare("
				SELECT itemid,type FROM ". $app->config["sphinx"]["mainindex"]."
				WHERE MATCH(:match)
				" . (($type !== "all") ? " AND type=:type " : "") . "
				GROUP BY itemid,type
				LIMIT 1;
			");
			$stmt->bindValue(":match", getSphinxMatchSyntax([$term]), PDO::PARAM_STR);
			if(($type !== "all")) {
				$stmt->bindValue(":type", $filterTypeMapping[$type], PDO::PARAM_INT);
			}
			
			$stmt->execute();
			$meta = $ln_sph->query("SHOW META")->fetchAll();
			$vars["search"][$type]["total"] = 0;
			foreach($meta as $m) {
				if($m["Variable_name"] === "total_found") {
					$vars["search"][$type]["total"] = $m["Value"];
				}
			}
			$vars["search"][$type]["time"] = 0;
			$vars["search"][$type]["term"] = $term;
			$vars["search"][$type]["matches"] = [];
			
			$vars["timelog"][$type."-total"]->End();

			// get results only for requestet result-type
			if($type == $currentType) {
				$vars["timelog"][$type] = new \Slimpd\ExecutionTime();
				$vars["timelog"][$type]->Start();
				$sortfield = (in_array($sort, $sortfields[$currentType]) === TRUE) ? $sort : "relevance";
				$direction = ($direction == "asc") ? "asc" : "desc";
				$vars["search"]["activesorting"] = $sortfield . "-" . $direction;
				
				$sortQuery = ($sortfield !== "relevance")?  " ORDER BY " . $sortfield . " " . $direction : "";
				
				$vars["search"][$type]["time"] = microtime(TRUE);
				
				$stmt = $ln_sph->prepare("
					SELECT id,type,itemid,display FROM ". $app->config["sphinx"]["mainindex"]."
					WHERE MATCH(:match)
					" . (($currentType !== "all") ? " AND type=:type " : "") . "
					GROUP BY itemid,type
					".$sortQuery."
					LIMIT :offset,:max
					OPTION ranker=".$ranker.",max_matches=".$vars["search"][$type]["total"].";");
				$stmt->bindValue(":match", getSphinxMatchSyntax([$term]), PDO::PARAM_STR);
				$stmt->bindValue(":offset", ($currentPage-1)*$itemsPerPage , PDO::PARAM_INT);
				$stmt->bindValue(":max", $itemsPerPage, PDO::PARAM_INT);
				if(($currentType !== "all")) {
					$stmt->bindValue(":type", $filterTypeMapping[$currentType], PDO::PARAM_INT);
				}
				
				$urlPattern = $app->config["root"] . "search".$type."/page/(:num)/sort/".$sortfield."/".$direction."?q=" . $term;
				$vars["paginator"] = new JasonGrimes\Paginator(
					$vars["search"][$type]["total"],
					$itemsPerPage,
					$currentPage,
					$urlPattern
				);
				$vars["paginator"]->setMaxPagesToShow(paginatorPages($currentPage));
				
				$stmt->execute();
				$rows = $stmt->fetchAll();
				$meta = $ln_sph->query("SHOW META")->fetchAll();
				foreach($meta as $m) {
				    $meta_map[$m["Variable_name"]] = $m["Value"];
				}
				
				if(count($rows) === 0 && !$app->request()->params("nosuggestion")) {
					$words = array();
					foreach($meta_map as $k=>$v) {
						if(preg_match("/keyword\[\d+]/", $k)) {
							preg_match("/\d+/", $k,$key);
							$key = $key[0];
							$words[$key]["keyword"] = $v;
						}
						if(preg_match("/docs\[\d+]/", $k)) {
							preg_match("/\d+/", $k,$key);
							$key = $key[0];
							$words[$key]["docs"] = $v;
						}
					}
					$suggest = MakePhaseSuggestion($words, $term, $ln_sph);
					if($suggest !== FALSE) {
						$app->response->redirect($app->urlFor(
							"search".$currentType,
							[
								"type" => $currentType,
								"currentPage" => $currentPage,
								"sort" => $sort,
								"direction" => $direction
							]
						) . "?nosuggestion=1&q=".$suggest . "&" . getNoSurSuffix(FALSE));
						$app->stop();
					}
					$result[] = [
						"label" => "nothing found",
						"url" => "#",
						"type" => "",
						"img" => "/skin/default/img/icon-label.png" // TODO: add not-found-icon
					];
				} else {
					$filterTypeMappingF = array_flip($filterTypeMapping);
					foreach($rows as $row) {
						switch($filterTypeMappingF[$row["type"]]) {
							case "artist":
							case "label":
							case "album":
							case "track":
							case "genre":
								$classPath = "\\Slimpd\\" . ucfirst($filterTypeMappingF[$row["type"]]);
								$obj = $classPath::getInstanceByAttributes(array("id" => $row["itemid"]));
								break;
							case "dirname":
								$tmp = \Slimpd\Album::getInstanceByAttributes(array("id" => $row["itemid"]));
								$obj = new \Slimpd\_Directory($tmp->getRelativePath());
								$obj->breadcrumb = \Slimpd\filebrowser::fetchBreadcrumb($obj->fullpath);
								break;
						}
						$vars["itemlist"][] = $obj;
					}
				}
				$vars["search"][$type]["time"] = number_format(microtime(TRUE) - $vars["search"][$type]["time"],3);
				$vars["timelog"][$type]->End();
			}
		}
		$vars["action"] = "searchresult." . $currentType;
		$vars["searchcurrent"] = $currentType;
		$vars["renderitems"] = getRenderItems($vars["itemlist"]);
		$app->render("surrounding.htm", $vars);
			
	})->name("search".$currentType);
}


$app->get("/autocomplete/:type/", function($type) use ($app, $vars) {
	foreach(["freq_threshold", "suggest_debug", "length_threshold", "levenshtein_threshold", "top_count"] as $var) {
		define (strtoupper($var), intval($app->config["sphinx"][$var]) );
	}
	$term = $app->request->get("q");
	
	$originalTerm = ($app->request->get("qo")) ? $app->request->get("qo") : $term;
	
	# TODO: evaluate if modifying searchterm makes sense
	// "Artist_-_Album_Name-(CAT001)-WEB-2015" does not match without this modification
	
	$term = cleanSearchterm($term);
	$start =0;
	$offset =20;
	$current = 1;
	$result = [];
	
	$ln_sph = new \PDO("mysql:host=".$app->config["sphinx"]["host"].";port=9306;charset=utf8;", "","");
	
	// those values have to match sphinxindex:srcslimpdautocomplete
	$filterTypeMapping = array(
		"artist" => 1,
		"album" => 2,
		"label" => 3,
		"track" => 4,
		"genre" => 5,
		"dirname" => 6,
	);
	
	$stmt = $ln_sph->prepare("
		SELECT id,type,itemid,display,trackCount,albumCount FROM ". $app->config["sphinx"]["mainindex"]."
		WHERE MATCH(:match)
		" . (($type !== "all") ? " AND type=:type " : "") . "
		GROUP BY itemid,type
		LIMIT $start,$offset;");
	
	if(($type !== "all")) {
		$stmt->bindValue(":type", $filterTypeMapping[$type], PDO::PARAM_INT);
	}
	$stmt->bindValue(":match", getSphinxMatchSyntax([$term,$originalTerm]), PDO::PARAM_STR);

	// do some timelogging for debugging purposes
	$timLogData = [
		"-----------------------------------------------",
		"AUTOCOMPLETE timelogger",
		" term: " . $term,
		" orignal term: " . $originalTerm
	];

	// in case an autocomplete ajax call gets aborted make sure to stop query against our index
	// TODO: how can we test if this works?
	ignore_user_abort(FALSE);
	ob_implicit_flush();

	$timeLogBegin = microtime(TRUE);
	$stmt->execute();
	$timLogData[] = " execute() " . (microtime(TRUE) - $timeLogBegin);
	$rows = $stmt->fetchAll();
	$timLogData[] = " fetchAll() " . (microtime(TRUE) - $timeLogBegin);
	$meta = $ln_sph->query("SHOW META")->fetchAll();
	$timLogData[] = " metaFetch() " . (microtime(TRUE) - $timeLogBegin);
	foreach($meta as $m) {
	    $meta_map[$m["Variable_name"]] = $m["Value"];
	}
	if(count($rows) === 0 && $app->request->get("suggested") != 1) {
		$words = array();
		foreach($meta_map as $k=>$v) {
			if(preg_match("/keyword\[\d+]/", $k)) {
				preg_match("/\d+/", $k,$key);
				$key = $key[0];
				$words[$key]["keyword"] = $v;
			}
			if(preg_match("/docs\[\d+]/", $k)) {
				preg_match("/\d+/", $k,$key);
				$key = $key[0];
				$words[$key]["docs"] = $v;
			}
		}
		$suggest = MakePhaseSuggestion($words, $term, $ln_sph);
		$timLogData[] = " MakePhaseSuggestion() " . (microtime(TRUE) - $timeLogBegin);
		if($suggest !== FALSE) {
			$app->response->redirect(
				$app->urlFor(
					"autocomplete",
					array(
						"type" => $type
					)
				) . "?suggested=1&q=" . rawurlencode($suggest) . "&qo=" . rawurlencode($term)
			);
			$app->stop();
		}
	} else {
		$filterTypeMapping = array_flip($filterTypeMapping);
		$cl = new SphinxClient();
		foreach($rows as $row) {
			$excerped = $cl->BuildExcerpts([$row["display"]], $app->config["sphinx"]["mainindex"], $term);
			$filterType = $filterTypeMapping[$row["type"]];
			
			switch($filterType) {
				case "track":
					$url = "searchall/page/1/sort/relevance/desc?q=" . $row["display"];
					break;
				case "album":
				case "dirname":
					$url = "album/" . $row["itemid"];
					break;
				default:
					$url = $filterType . "/" . $row["itemid"] . "/tracks/page/1/sort/added/desc";
					break;
			}

			$entry = [
				"label" => $excerped[0],
				"url" => $app->config["root"] . $url,
				"type" => $filterType,
				"typelabel" => $app->ll->str($filterType),
				"itemid" => $row["itemid"],
				"trackcount" => $row["trackcount"],
				"albumcount" => $row["albumcount"]
			];
			switch($filterType) {
				case "artist":
					$entry["img"] = $app->config["root"] . "imagefallback-50/artist";
					break;
				case "label":
				case "genre":
				case "dirname":
					$entry["img"] = $app->config["fileroot"] . "skin/default/img/icon-". $filterType .".png";
					break;
				case "album":
				case "track":
					$entry["img"] = $app->config["root"] . "image-50/". $filterType ."/" . $row["itemid"];
					break;
			}
			$result[] = $entry;
		}
		$timLogData[] = " BuildExcerptsAndJson() " . (microtime(TRUE) - $timeLogBegin);
	}
	if(count($result) === 0) {
		$result[] = [
			"label" => $app->ll->str("autocomplete." . $type . ".noresults", [$originalTerm]),
			"url" => "#",
			"type" => "",
			"img" => $app->config["root"] . "imagefallback-50/noresults"
		];
	}
	$timLogData[] = " json_encode() " . (microtime(TRUE) - $timeLogBegin);

	// TODO: read usage of file-logging from config
	#fileLog($timLogData);
	deliverJson($result);
	$app->stop();
})->name("autocomplete");



$app->get("/directory/:itemParams+", function($itemParams) use ($app, $vars){

	// validate directory
	$directory = new \Slimpd\_Directory(join(DS, $itemParams));
	if($directory->validate() === FALSE) {
		$app->flashNow("error", $app->ll->str("directory.notfound"));
		$app->render("surrounding.htm", $vars);
		return;
	}

	// get total items of directory from sphinx
	$ln_sph = new \PDO("mysql:host=".$app->config["sphinx"]["host"].";port=9306;charset=utf8;", "","");
	$stmt = $ln_sph->prepare("
		SELECT id
		FROM ". $app->config["sphinx"]["mainindex"]."
		WHERE MATCH(:match)
		AND type=:type
		LIMIT 1;
	");
	$stmt->bindValue(":match", "'@allchunks \"". join(DS, $itemParams) . DS . "\"'", PDO::PARAM_STR);
	$stmt->bindValue(":type", 4, PDO::PARAM_INT);
	$stmt->execute();
	$meta = $ln_sph->query("SHOW META")->fetchAll();
	$total = 0;
	foreach($meta as $m) {
		if($m["Variable_name"] === "total_found") {
			$total = $m["Value"];
		}
	}

	// get requestet portion of track-ids from sphinx
	$itemsPerPage = 20;
	$currentPage = intval($app->request->get("page"));
	$currentPage = ($currentPage === 0) ? 1 : $currentPage;
	$ln_sph = new \PDO("mysql:host=".$app->config["sphinx"]["host"].";port=9306;charset=utf8;", "","");
	$stmt = $ln_sph->prepare("
		SELECT itemid
		FROM ". $app->config["sphinx"]["mainindex"]."
		WHERE MATCH('@allchunks \"". $directory->fullpath. "\"')
		AND type=:type
		ORDER BY sort1 ASC
		LIMIT :offset,:max
		OPTION max_matches=".$total.";
	");
	$stmt->bindValue(":type", 4, PDO::PARAM_INT);
	$stmt->bindValue(":offset", ($currentPage-1)*$itemsPerPage , PDO::PARAM_INT);
	$stmt->bindValue(":max", $itemsPerPage, PDO::PARAM_INT);
	$stmt->execute();
	$rows = $stmt->fetchAll();
	$vars["itemlist"] = [];
	foreach($rows as $row) {
		$vars["itemlist"][] = \Slimpd\Track::getInstanceByAttributes(array("id" => $row["itemid"]));
	}

	// get additional stuff we need for rendering the view
	$vars["action"] = "directorytracks";
	$vars["renderitems"] = getRenderItems($vars["itemlist"]);
	$vars["breadcrumb"] = \Slimpd\filebrowser::fetchBreadcrumb(join(DS, $itemParams));
	$vars["paginator"] = new JasonGrimes\Paginator(
		$total,
		$itemsPerPage,
		$currentPage,
		$app->config["root"] . "directory/".join(DS, $itemParams) . "?page=(:num)"
	);
	$vars["paginator"]->setMaxPagesToShow(paginatorPages($currentPage));
	$app->render("surrounding.htm", $vars);
});
