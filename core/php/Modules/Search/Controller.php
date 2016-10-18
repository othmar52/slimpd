<?php
namespace Slimpd\Modules\Search;
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
 * FITNESS FOR A PARTICULAR PURPOSE.	See the GNU Affero General Public License
 * for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.	If not, see <http://www.gnu.org/licenses/>.
 */
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class Controller extends \Slimpd\BaseController {

	// TODO: carefully check which sorting is possible for each model (@see config/sphinx.example.conf:srcslimpdmain)
	//   compare with templates/partials/dropdown-search-sorting.htm
	//   compare with templates/partials/dropdown-typelist-sorting.htm
	private $sortfields = array(
		"artist" => array("title", "trackCount", "albumCount"),
		"genre" => array("title", "trackCount", "albumCount"),
		"label" => array("title", "trackCount", "albumCount"),
		"all" => array("title", "artist", "year", "added"),
		"track" => array("title", "artist", "year", "added"),
		"album" => array("year", "title", "added", "artist", "trackCount"),
		"dirname" => array("title", "added", "trackCount")
	);

	// albumlist+tracklist of artist|genre|label
	public function listAction(Request $request, Response $response, $args) {
		#echo "<pre>" . print_r($args,1); die();
		
		$args["action"] = $args['className']."." . $args['show']."s";
		$args["itemtype"] = $args['className'];
		$args["listcurrent"] = $args['show'];
		$args["itemlist"] = [];
		
		$repoKey = $args['className'] . 'Repo';
		
		// TODO: check where %20 on multiple artist-uids come from
		$args['itemUid'] = str_replace("%20", ",", $args['itemUid']);
		
		$term = str_replace(",", " ", $args['itemUid']);
		$args["item"] = $this->$repoKey->getInstanceByAttributes(array("uid" => $args['itemUid']));
		
		$args["itemUids"] = $args['itemUid'];
		$itemsPerPage = 20;
		$maxCount = 1000;

		$sphinxPdo = \Slimpd\Modules\sphinx\Sphinx::getPdo($this->conf);
		
		foreach(["album","track"] as $resultType) {
			
			// get total results for all types (albums + tracks)
			$sphinxTypeIndex = ($resultType === "album") ? 2 : 4;
			$stmt = $sphinxPdo->prepare("
				SELECT id FROM ". $this->conf["sphinx"]["mainindex"]."
				WHERE MATCH('@".$args['className']."Uids \"". $term ."\"')
				AND type=:type
				GROUP BY itemuid,type
				LIMIT 1;
			");
			$stmt->bindValue(":type", $sphinxTypeIndex, \PDO::PARAM_INT);
			$stmt->execute();
			$meta = $sphinxPdo->query("SHOW META")->fetchAll();
			$args["search"][$resultType]["total"] = 0;
			foreach($meta as $m) {
				if($m["Variable_name"] === "total_found") {
					$args["search"][$resultType]["total"] = $m["Value"];
				}
			}
			$args["search"][$resultType]["time"] = 0;
			$args["search"][$resultType]["term"] = $args['itemUid'];
			$args["search"][$resultType]["matches"] = [];
			
			if($resultType !== $args['show']) {
				// we only need total count for non requested item
				continue;
			}

			$sortQuery = ($args['sort'] !== "relevance")?  " ORDER BY " . $args['sort'] . " " . $args['direction'] : "";
			$args["search"]["activesorting"] = $args['sort'] . "-" . $args['direction'];
			
			$stmt = $sphinxPdo->prepare("
				SELECT id,type,itemuid,artistUids,display
				FROM ". $this->conf["sphinx"]["mainindex"]."
				WHERE MATCH('@".$args['className']."Uids \"". $term ."\"')
				AND type=:type
				GROUP BY itemuid,type
					".$sortQuery."
					LIMIT :offset,:max
				OPTION ranker=proximity, max_matches=".$args["search"][$resultType]["total"].";
			");
			$stmt->bindValue(":offset", ($args['currentPage']-1)*$itemsPerPage , \PDO::PARAM_INT);
			$stmt->bindValue(":max", $itemsPerPage, \PDO::PARAM_INT);
			$stmt->bindValue(":type", $sphinxTypeIndex, \PDO::PARAM_INT);
			
			$args["search"][$resultType]["time"] = getMicrotimeFloat();
			
			$stmt->execute();
			$rows = $stmt->fetchAll();
			
			foreach($rows as $row) {
				switch($row["type"]) {
					case "2":
						$obj = $this->albumRepo->getInstanceByAttributes(array("uid" => $row["itemuid"]));
						break; 
					case "4":
						$obj = $this->trackRepo->getInstanceByAttributes(array("uid" => $row["itemuid"]));
						break;
				}
				$args["itemlist"][] = $obj;
			}
			
			$args["search"][$resultType]["time"] = number_format(getMicrotimeFloat() - $args["search"][$resultType]["time"],3);
			
			$args["paginator"] = new \JasonGrimes\Paginator(
				$args["search"][$resultType]["total"],
				$itemsPerPage,
				$args['currentPage'],
				$this->conf['config']['absRefPrefix'] .$args['className']."/".$args['itemUid']."/".$args['show']."s/page/(:num)/sort/".$args['sort']."/".$args['direction']
			);
			$args["paginator"]->setMaxPagesToShow(paginatorPages($args['currentPage']));
			
		}
		// redirect to tracks in case we have zero albums
		if($args['show'] === "album" && $args["search"]["album"]["total"] === "0" && $args["search"]["track"]["total"] > 0) {
			$args['show'] = 'track';
			$uri =$this->router->pathFor('search-list', $args). getNoSurSuffix($this->view->getEnvironment()->getGlobals()['nosurrounding']);
			return $response->withRedirect($uri, 403);
		}
		// redirect to albums in case we have zero tracks
		if($args['show'] === "track" && $args["search"]["track"]["total"] === "0" && $args["search"]["album"]["total"] > 0) {
			$args['show'] = 'album';
			$uri =$this->router->pathFor('search-list', $args). getNoSurSuffix($this->view->getEnvironment()->getGlobals()['nosurrounding']);
			return $response->withRedirect($uri, 403);
		}
		$args["renderitems"] = $this->getRenderItems($args["item"], $args["itemlist"]);
			
		$this->view->render($response, 'surrounding.htm', $args);
		return $response;
	}

	public function searchAction(Request $request, Response $response, $args) {
		
		# TODO: evaluate if modifying searchterm makes sense
		// "Artist_-_Album_Name-(CAT001)-WEB-2015" does not match without this modification
		$term = cleanSearchterm($request->getParam("q"));
		
		
		# TODO: read a blacklist of searchterms from configfile
		// searching "mp3" can be bad for our snappy gui
		// at least we have to skip the "total results" query for each type
		// for now - redirect immediately
		if(strtolower(trim($term)) === "mp3" || strtolower(trim($term)) === "mu") {
			$this->container->flash->AddMessage("error", "OH SNAP! searchterm <strong>". $term ."</strong> is currently blacklisted...");
			$this->view->render($response, 'surrounding.htm', $args);
			return $response;
		}
		
		$ranker = "sph04";
		$start = 0;
		$itemsPerPage = 20;
		$maxCount = 1000;
		$result = [];

		$sphinxPdo = \Slimpd\Modules\sphinx\Sphinx::getPdo($this->conf);
		
		// those values have to match sphinxindex:srcslimpdmain:type
		$filterTypeMapping = array(
			"artist" => 1,
			"album" => 2,
			"label" => 3,
			"track" => 4,
			"genre" => 5,
			"dirname" => 6,
		);
		$args["itemlist"] = [];
		$args["timelog"] = [];

		foreach(array_keys($this->sortfields) as $type) {
			$args["timelog"][$type."-total"] = new \Slimpd\Modules\ExecutionTime\ExecutionTime();
			$args["timelog"][$type."-total"]->Start();
			// get result count for each resulttype 
			$stmt = $sphinxPdo->prepare("
				SELECT itemuid,type FROM ". $this->conf["sphinx"]["mainindex"]."
				WHERE MATCH(:match)
				" . (($type !== "all") ? " AND type=:type " : "") . "
				GROUP BY itemuid,type
				LIMIT 1;
			");
			$stmt->bindValue(":match", getSphinxMatchSyntax([$term]), \PDO::PARAM_STR);
			if(($type !== "all")) {
				$stmt->bindValue(":type", $filterTypeMapping[$type], \PDO::PARAM_INT);
			}
			
			$stmt->execute();
			$meta = $sphinxPdo->query("SHOW META")->fetchAll();
			$args["search"][$type]["total"] = 0;
			foreach($meta as $m) {
				if($m["Variable_name"] === "total_found") {
					$args["search"][$type]["total"] = $m["Value"];
				}
			}
			$args["search"][$type]["time"] = 0;
			$args["search"][$type]["term"] = $term;
			$args["search"][$type]["matches"] = [];
			
			$args["timelog"][$type."-total"]->End();

			// get results only for requestet result-type
			if($type == $args['currentType']) {
				$args["timelog"][$type] = new \Slimpd\Modules\ExecutionTime\ExecutionTime();
				$args["timelog"][$type]->Start();
				$sortfield = (in_array($args['sort'], $this->sortfields[$args['currentType']]) === TRUE) ? $args['sort'] : "relevance";
				$args['direction'] = ($args['direction'] == "asc") ? "asc" : "desc";
				$args["search"]["activesorting"] = $sortfield . "-" . $args['direction'];
				
				$sortQuery = ($sortfield !== "relevance")?  " ORDER BY " . $sortfield . " " . $args['direction'] : "";
				
				$args["search"][$type]["time"] = getMicrotimeFloat();
				
				$stmt = $sphinxPdo->prepare("
					SELECT id,type,itemuid,display FROM ". $this->conf["sphinx"]["mainindex"]."
					WHERE MATCH(:match)
					" . (($args['currentType'] !== "all") ? " AND type=:type " : "") . "
					GROUP BY itemuid,type
					".$sortQuery."
					LIMIT :offset,:max
					OPTION ranker=".$ranker.",max_matches=".$args["search"][$type]["total"].";");
				$stmt->bindValue(":match", getSphinxMatchSyntax([$term]), \PDO::PARAM_STR);
				$stmt->bindValue(":offset", ($args['currentPage']-1)*$itemsPerPage , \PDO::PARAM_INT);
				$stmt->bindValue(":max", $itemsPerPage, \PDO::PARAM_INT);
				if(($args['currentType'] !== "all")) {
					$stmt->bindValue(":type", $filterTypeMapping[$args['currentType']], \PDO::PARAM_INT);
				}
				
				$urlPattern = $this->conf['config']['absRefPrefix'] . "search".$type."/page/(:num)/sort/".$sortfield."/".$args['direction']."?q=" . $term;
				$args["paginator"] = new \JasonGrimes\Paginator(
					$args["search"][$type]["total"],
					$itemsPerPage,
					$args['currentPage'],
					$urlPattern
				);
				$args["paginator"]->setMaxPagesToShow(paginatorPages($args['currentPage']));
				
				$stmt->execute();
				$rows = $stmt->fetchAll();
				$meta = $sphinxPdo->query("SHOW META")->fetchAll();
				foreach($meta as $m) {
					$meta_map[$m["Variable_name"]] = $m["Value"];
				}
				
				if(count($rows) === 0 && !$request->getParam("nosuggestion")) {
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
					$suggest = MakePhaseSuggestion($words, $term, $sphinxPdo);
					if($suggest !== FALSE) {
						$uri = $this->router->pathFor(
							'search',
							[
								"type" => $args['currentType'],
								"currentPage" => $args['currentPage'],
								"sort" => $args['sort'],
								"direction" => $args['direction']
							]
						) . "?nosuggestion=1&q=".$suggest . "&" . getNoSurSuffix($this->view->getEnvironment()->getGlobals()['nosurrounding'], FALSE);
						return $response->withRedirect($uri, 403);
					}
					$result[] = [
						"label" => "nothing found",
						"url" => "#",
						"type" => "",
						"img" => "/core/skin/default/img/icon-label.png" // TODO: add not-found-icon TODO: respect and use root/fileroot variables 
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
								$repoKey = $filterTypeMappingF[$row["type"]] . 'Repo';
								$obj = $this->$repoKey->getInstanceByAttributes(array("uid" => $row["itemuid"]));
								break;
							case "dirname":
								$tmp = $this->albumRepo->getInstanceByAttributes(array("uid" => $row["itemuid"]));
								if($tmp !== NULL) {
									$obj = new \Slimpd\Models\Directory($tmp->getRelPath());
									$obj->setBreadcrumb(\Slimpd\Modules\filebrowser\filebrowser::fetchBreadcrumb($obj->getRelPath()));
								}
								break;
						}
						if($obj === NULL) {
							// vanished item: we have it in sphinx index but not in MySQL database
							$obj = $this->trackRepo->getNewInstanceWithoutDbQueries($row["display"]);
							$obj->setError("notfound");
						}
						$args["itemlist"][] = $obj;
					}
					
				}
				$args["search"][$type]["time"] = number_format(getMicrotimeFloat() - $args["search"][$type]["time"],3);
				$args["timelog"][$type]->End();
				
			}

		}

		$args["action"] = "searchresult." . $args['currentType'];
		$args["searchcurrent"] = $args['currentType'];
		#echo "<pre>" . print_r($args["itemlist"],1); ob_flush();
		$args["renderitems"] = $this->getRenderItems($args["itemlist"]);
		#die;
		$args["statsstring"] = $this->container->ll->str( // "x results in x seconds";
			'searchstats.singlepage',
			[
				$args["search"][$args['currentType']]["total"],
				$args["search"][$args['currentType']]["time"]
			]
		);
		if($args["paginator"]->getNumPages() > 1){
			#$args["statsstring"] = ;
			$args["statsstring"] = $this->container->ll->str( // "x - x of x results in x seconds"
				'searchstats.multipage',
				[
					$args['currentPage']*$itemsPerPage+1-$itemsPerPage,
					(($args['currentPage'] == $args["paginator"]->getNumPages())
						? $args["search"][$args['currentType']]["total"]
						: $args['currentPage']*$itemsPerPage),
					$args["search"][$args['currentType']]["total"],
					$args["search"][$args['currentType']]["time"]
				]
			);
		}
		$this->view->render($response, 'surrounding.htm', $args);
		return $response;
	}

	public function autocompleteAction(Request $request, Response $response, $args) {
		$term = $request->getParam("q");
		
		$originalTerm = ($request->getParam("qo")) ? $request->getParam("qo") : $term;
		
		# TODO: evaluate if modifying searchterm makes sense
		// "Artist_-_Album_Name-(CAT001)-WEB-2015" does not match without this modification
		
		$term = cleanSearchterm($term);
		$start =0;
		$offset =20;
		$current = 1;
		$result = [];
		
		$sphinxPdo = \Slimpd\Modules\sphinx\Sphinx::getPdo($this->conf);
		
		// those values have to match sphinxindex:srcslimpdautocomplete
		$filterTypeMapping = array(
			"artist" => 1,
			"album" => 2,
			"label" => 3,
			"track" => 4,
			"genre" => 5,
			"dirname" => 6,
		);
		
		$stmt = $sphinxPdo->prepare("
			SELECT id,type,itemuid,display,trackCount,albumCount FROM ". $this->conf["sphinx"]["mainindex"]."
			WHERE MATCH(:match)
			" . (($args["type"] !== "all") ? " AND type=:type " : "") . "
			GROUP BY itemuid,type
			LIMIT $start,$offset;");
		
		if(($args["type"] !== "all")) {
			$stmt->bindValue(":type", $filterTypeMapping[$args["type"]], \PDO::PARAM_INT);
		}
		$stmt->bindValue(":match", getSphinxMatchSyntax([$term,$originalTerm]), \PDO::PARAM_STR);
	
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
	
		$timeLogBegin = getMicrotimeFloat();
		$stmt->execute();
		$timLogData[] = " execute() " . (getMicrotimeFloat() - $timeLogBegin);
		$rows = $stmt->fetchAll();
		$timLogData[] = " fetchAll() " . (getMicrotimeFloat() - $timeLogBegin);
		$meta = $sphinxPdo->query("SHOW META")->fetchAll();
		$timLogData[] = " metaFetch() " . (getMicrotimeFloat() - $timeLogBegin);
		foreach($meta as $m) {
		    $meta_map[$m["Variable_name"]] = $m["Value"];
		}
		if(count($rows) === 0 && $request->getParam("suggested") != 1) {
			$words = array();
			foreach($meta_map as $key => $value) {
				if(preg_match("/keyword\[([\d]*)\]/", $key, $matches)) {
					$words[ $matches[1] ]["keyword"] = $value;
				}
				if(preg_match("/docs\[([\d]*)\]/", $key, $matches)) {
					$words[ $matches[1] ]["docs"] = $value;
				}
			}
			$suggest = MakePhaseSuggestion($words, $term, $sphinxPdo);
			$timLogData[] = " MakePhaseSuggestion() " . (getMicrotimeFloat() - $timeLogBegin);
			if($suggest !== FALSE) {
				$uri = $this->router->pathFor(
					'autocomplete',
					['type' => $args['type'] ]
				). "?suggested=1&q=" . rawurlencode($suggest) . "&qo=" . rawurlencode($term);
				return $response->withRedirect($uri, 403);
			}
		} else {
			$filterTypeMapping = array_flip($filterTypeMapping);
			$sphinxClient = new \SphinxClient();
			foreach($rows as $row) {
				$excerped = $sphinxClient->BuildExcerpts([$row["display"]], $this->conf["sphinx"]["mainindex"], $term);
				$filterType = $filterTypeMapping[$row["type"]];
				
				switch($filterType) {
					case "track":
						$url = "searchall/page/1/sort/relevance/desc?q=" . $row["display"];
						break;
					case "album":
					case "dirname":
						$url = "album/" . $row["itemuid"];
						break;
					default:
						$url = $filterType . "/" . $row["itemuid"] . "/tracks/page/1/sort/added/desc";
						break;
				}
	
				$entry = [
					"label" => $excerped[0],
					"url" => $this->conf['config']['absRefPrefix'] . $url,
					"type" => $filterType,
					"typelabel" => $this->container->ll->str($filterType),
					"itemuid" => $row["itemuid"],
					"trackcount" => $row["trackcount"],
					"albumcount" => $row["albumcount"]
				];
				switch($filterType) {
					case "artist":
					case "genre":
						$entry["img"] = $this->conf['config']['absRefPrefix'] . "imagefallback-50/" . $filterType;
						break;
					case "label":
					case "dirname":
						$entry["img"] = $this->conf['config']['absFilePrefix'] . "core/skin/default/img/icon-". $filterType .".png";
						break;
					case "album":
					case "track":
						$entry["img"] = $this->conf['config']['absRefPrefix'] . "image-50/". $filterType ."/" . $row["itemuid"];
						break;
				}
				$result[] = $entry;
			}
			$timLogData[] = " BuildExcerptsAndJson() " . (getMicrotimeFloat() - $timeLogBegin);
		}
		if(count($result) === 0) {
			$result[] = [
				"label" => $this->container->ll->str("autocomplete." . $args["type"] . ".noresults", [$originalTerm]),
				"url" => "#",
				"type" => "",
				"img" => $this->conf['config']['absRefPrefix'] . "imagefallback-50/noresults"
			];
		}
		$timLogData[] = " json_encode() " . (getMicrotimeFloat() - $timeLogBegin);
	
		// TODO: read usage of file-logging from config
		#fileLog($timLogData);
		return deliverJson($result, $response);
	}

	public function directoryAction(Request $request, Response $response, $args) {
	
		// validate directory
		$directory = $this->directoryRepo->create($args['itemParams']);
		if($this->directoryRepo->validate($directory) === FALSE) {
			$this->container->flash->AddMessage("error", $this->container->ll->str("directory.notfound"));
			$this->view->render($response, 'surrounding.htm', $args);
			return $response;
		}
	
		// get total items of directory from sphinx
		$sphinxPdo = \Slimpd\Modules\sphinx\Sphinx::getPdo($this->conf);
		
		$stmt = $sphinxPdo->prepare("
			SELECT id
			FROM ". $this->conf["sphinx"]["mainindex"]."
			WHERE MATCH(:match)
			AND type=:type
			LIMIT 1;
		");
		$stmt->bindValue(":match", "'@allchunks \"". $directory->getRelPath() . "\"'", \PDO::PARAM_STR);
		$stmt->bindValue(":type", 4, \PDO::PARAM_INT);
		$stmt->execute();
		$meta = $sphinxPdo->query("SHOW META")->fetchAll();
		$total = 0;
		foreach($meta as $m) {
			if($m["Variable_name"] === "total_found") {
				$total = $m["Value"];
			}
		}
	
		// get requestet portion of track-uids from sphinx
		$itemsPerPage = 20;
		$args['currentPage'] = intval($request->getParam("page"));
		$args['currentPage'] = ($args['currentPage'] === 0) ? 1 : $args['currentPage'];
		$sphinxPdo = \Slimpd\Modules\sphinx\Sphinx::getPdo($this->conf);
		$stmt = $sphinxPdo->prepare("
			SELECT itemuid
			FROM ". $this->conf["sphinx"]["mainindex"]."
			WHERE MATCH('@allchunks \"". $directory->getRelPath(). "\"')
			AND type=:type
			ORDER BY sort1 ASC
			LIMIT :offset,:max
			OPTION max_matches=".$total.";
		");
		$stmt->bindValue(":type", 4, \PDO::PARAM_INT);
		$stmt->bindValue(":offset", ($args['currentPage']-1)*$itemsPerPage , \PDO::PARAM_INT);
		$stmt->bindValue(":max", $itemsPerPage, \PDO::PARAM_INT);
		$stmt->execute();
		$rows = $stmt->fetchAll();
		$args["itemlist"] = [];
		foreach($rows as $row) {
			$args["itemlist"][] = $this->trackRepo->getInstanceByAttributes(array("uid" => $row["itemuid"]));
		}

		// get additional stuff we need for rendering the view
		$args["action"] = "directorytracks";
		$args["renderitems"] = $this->getRenderItems($args["itemlist"]);
		$args["breadcrumb"] = \Slimpd\Modules\filebrowser\filebrowser::fetchBreadcrumb($args['itemParams']);
		$args["paginator"] = new \JasonGrimes\Paginator(
			$total,
			$itemsPerPage,
			$args['currentPage'],
			$this->conf['config']['absRefPrefix'] . "directory/".$args['itemParams'] . "?page=(:num)"
		);
		$args["paginator"]->setMaxPagesToShow(paginatorPages($args['currentPage']));
		$this->view->render($response, 'surrounding.htm', $args);
		return $response;
	}

	public function alphasearchAction(Request $request, Response $response, $args) {
		$type = $request->getParam("searchtype");
		$term = $request->getParam("searchterm");
		$uri = $this->conf['config']['absRefPrefix'] . $type."s/searchterm/".rawurlencode($term)."/page/1" . getNoSurSuffix($this->view->getEnvironment()->getGlobals()['nosurrounding']);
		return $response->withRedirect($uri, 403);
	}
}
