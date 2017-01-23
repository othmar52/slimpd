<?php
namespace Slimpd\Modules\Search;
/* Copyright (C) 2016 othmar52 <othmar52@users.noreply.github.com>
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
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License
 * for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class Controller extends \Slimpd\BaseController {

    // those values have to match sphinxindex:srcslimpdautocomplete
    protected $filterTypeMapping = [
        "artist" => 1,
        "album" => 2,
        "label" => 3,
        "track" => 4,
        "genre" => 5,
        "dirname" => 6,
    ];

    // TODO: carefully check which sorting is possible for each model (@see config/sphinx.example.conf:srcslimpdmain)
    //   compare with templates/partials/dropdown-search-sorting.htm
    //   compare with templates/partials/dropdown-typelist-sorting.htm
    protected $sortfields = array(
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
        if($this->auth->hasPermissionFor('media') === FALSE) {
            return $this->renderAccessDenied($response);
        }
        useArguments($request);

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
            $args["search"][$resultType] = [
                "total" => parseMetaForTotal($sphinxPdo->query("SHOW META")->fetchAll()),
                "time" => 0,
                "term" => $args['itemUid'],
                "matches" => []
            ];

            if($resultType !== $args['show']) {
                // we need only total count for non requested item but not the items itself
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
        if($redirectUri = $this->mayRedirect($args)) {
            return $response->withRedirect($redirectUri, 403);
        }
        $args["renderitems"] = $this->getRenderItems($args["item"], $args["itemlist"]);

        $this->view->render($response, 'surrounding.htm', $args);
        return $response;
    }

    protected function mayRedirect(&$args) {
        // redirect to tracks in case we have zero albums
        if($args['show'] === "album" && $args["search"]["album"]["total"] === "0" && $args["search"]["track"]["total"] > 0) {
            $args['show'] = 'track';
            return $this->router->pathFor('search-list', $args). getNoSurSuffix($this->view->getEnvironment()->getGlobals()['nosurrounding']);
        }
        // redirect to albums in case we have zero tracks
        if($args['show'] === "track" && $args["search"]["track"]["total"] === "0" && $args["search"]["album"]["total"] > 0) {
            $args['show'] = 'album';
            return $this->router->pathFor('search-list', $args). getNoSurSuffix($this->view->getEnvironment()->getGlobals()['nosurrounding']);
        }
        return FALSE;
    }

    /**
     * queries the sphinx index not for results but only for total count for specific item-type
     */
    protected function fetchSphinxResultAmount($sphinxPdo, $typeString, $term, &$args) {
        $typeIndex = ($typeString === 'all') ? 0 : $this->filterTypeMapping[$typeString];
        $args["timelog"][$typeString."-total"] = new \Slimpd\Modules\ExecutionTime\ExecutionTime();
        $args["timelog"][$typeString."-total"]->Start();
        $stmt = $sphinxPdo->prepare("
            SELECT itemuid,type FROM ". $this->conf["sphinx"]["mainindex"]."
            WHERE MATCH(:match)
            " . (($typeString !== "all") ? " AND type=:type " : "") . "
            GROUP BY itemuid,type
            LIMIT 1;
        ");
        $stmt->bindValue(":match", getSphinxMatchSyntax([$term], $args['useExactMatch']), \PDO::PARAM_STR);
        if(($typeString !== "all")) {
            $stmt->bindValue(":type", $typeIndex, \PDO::PARAM_INT);
        }

        $stmt->execute();
        $args["search"][$typeString]["total"] = parseMetaForTotal($sphinxPdo->query("SHOW META")->fetchAll());
        $args["search"][$typeString]["time"] = 0;
        $args["search"][$typeString]["term"] = $term;
        $args["search"][$typeString]["matches"] = [];
        $args["timelog"][$typeString."-total"]->End();
    }

    protected function querySphinxIndex($sphinxPdo, $typeString, $term, &$args) {
        $itemsPerPage = 20;
        $typeIndex = ($typeString === 'all') ? 0 : $this->filterTypeMapping[$typeString];
        $args["timelog"][$typeString] = new \Slimpd\Modules\ExecutionTime\ExecutionTime();
        $args["timelog"][$typeString]->Start();
        $sortfield = (in_array($args['sort'], $this->sortfields[$typeString]) === TRUE) ? $args['sort'] : "relevance";
        $args['direction'] = ($args['direction'] == "asc") ? "asc" : "desc";
        $args["search"]["activesorting"] = $sortfield . "-" . $args['direction'];
        $args["search"][$typeString]["term"] = $term;
        $args["search"][$typeString]["time"] = getMicrotimeFloat();

        $sortQuery = ($sortfield !== "relevance")?  " ORDER BY " . $sortfield . " " . $args['direction'] : "";

        $stmt = $sphinxPdo->prepare("
            SELECT id,type,itemuid,display FROM ". $this->conf["sphinx"]["mainindex"]."
            WHERE MATCH(:match)
            " . (($typeString !== "all") ? " AND type=:type " : "") . "
            GROUP BY itemuid,type
            ".$sortQuery."
            LIMIT :offset,:max
            OPTION
            ranker = wordcount,
                field_weights=(title=50, display=40, artist=30, allchunks=1),
                max_matches=1000000;"); // TODO: move max_matches to sliMpd conf
        $stmt->bindValue(":match", getSphinxMatchSyntax([$term], $args['useExactMatch']), \PDO::PARAM_STR);
        $stmt->bindValue(":offset", ($args['currentPage']-1)*$itemsPerPage , \PDO::PARAM_INT);
        $stmt->bindValue(":max", $itemsPerPage, \PDO::PARAM_INT);
        if($typeString !== "all") {
            $stmt->bindValue(":type", $typeIndex, \PDO::PARAM_INT);
        }

        $stmt->execute();
        $args["search"][$typeString]["total"] = parseMetaForTotal($sphinxPdo->query("SHOW META")->fetchAll());
        $rows = $stmt->fetchAll();

        $args["search"][$typeString]["time"] = number_format(getMicrotimeFloat() - $args["search"][$typeString]["time"],3);
        $args["timelog"][$typeString]->End();
        $args["action"] = "searchresult." . $args['currentType'];
        $args["searchcurrent"] = $args['currentType'];
        $args["statsstring"] = $this->container->ll->str( // "x results in x seconds";
            'searchstats.singlepage',
            [
                $args["search"][$args['currentType']]["total"],
                $args["search"][$args['currentType']]["time"]
            ]
        );
        $suggest = getPhaseSuggestion($term, $sphinxPdo);
        if($suggest !== FALSE) {
            $args['suggestions'] = explode(" ", $suggest);
        }
        if(count($rows) === 0) {
            return;
        }

        $args["paginator"] = new \JasonGrimes\Paginator(
            $args["search"][$typeString]["total"],
            $itemsPerPage,
            $args['currentPage'],
            $this->conf['config']['absRefPrefix'] . "search".$typeString."/page/(:num)/sort/".$sortfield."/".$args['direction']."?q=" . $term
        );
        $args["paginator"]->setMaxPagesToShow(paginatorPages($args['currentPage']));
        if($args["paginator"]->getNumPages() > 1){
            $args["statsstring"] = $this->container->ll->str( // "x - x of x results in x seconds"
                'searchstats.multipage',
                [
                    $args['currentPage']*$itemsPerPage+1-$itemsPerPage,
                    (($args['currentPage'] == $args["paginator"]->getNumPages())
                        ? $args["search"][$typeString]["total"]
                        : $args['currentPage']*$itemsPerPage),
                    $args["search"][$typeString]["total"],
                    $args["search"][$typeString]["time"]
                ]
            );
        }

        $filterTypeMappingF = array_flip($this->filterTypeMapping);
        foreach($rows as $row) {
            if($filterTypeMappingF[$row["type"]] === "dirname") {
                $tmp = $this->albumRepo->getInstanceByAttributes(array("uid" => $row["itemuid"]));
                if($tmp !== NULL) {
                    $obj = new \Slimpd\Models\Directory($tmp->getRelPath());
                    $obj->setBreadcrumb(\Slimpd\Modules\Filebrowser\Filebrowser::fetchBreadcrumb($obj->getRelPath()));
                }
            }
            if(in_array($filterTypeMappingF[$row["type"]], ["artist", "label", "album", "track", "genre"]) === TRUE) {
                $repoKey = $filterTypeMappingF[$row["type"]] . 'Repo';
                $obj = $this->$repoKey->getInstanceByAttributes(array("uid" => $row["itemuid"]));
            }
            if($obj === NULL) {
                // vanished item: we have it in sphinx index but not in MySQL database
                $obj = $this->trackRepo->getNewInstanceWithoutDbQueries($row["display"]);
                $obj->setError("notfound");
            }
            $args["itemlist"][] = $obj;
        }
        $args["renderitems"] = $this->getRenderItems($args["itemlist"]);
    }

    public function searchAction(Request $request, Response $response, $args) {
        if($this->auth->hasPermissionFor('media') === FALSE) {
            return $this->renderAccessDenied($response);
        }

        # TODO: evaluate if modifying searchterm makes sense
        // "Artist_-_Album_Name-(CAT001)-WEB-2015" does not match without this modification
        $originalTerm = $request->getParam("q");
        $term = cleanSearchterm($originalTerm);
        $args['useExactMatch'] = (preg_match("/^\".*\"$/", trim($originalTerm))) ? TRUE : FALSE;

        $sphinxPdo = \Slimpd\Modules\sphinx\Sphinx::getPdo($this->conf);
        $startTime = getMicrotimeFloat();
        $stopAfter = 2; // seconds  (for skipping not so relevant counting of other types)

        $args["itemlist"] = [];
        $args["timelog"] = [];
        $args["search"]["all"]["total"] = '?';

        $currentTypeString = $args['currentType'];

        // first query only the requested item type
        $this->querySphinxIndex($sphinxPdo, $currentTypeString, $term, $args);

        // in case we have enough time fetch totalCount for all remaining types
        foreach(array_keys($this->filterTypeMapping) as $filterTypeString) {
            if($currentTypeString === $filterTypeString) {
                // we already queried this type
                continue;
            }
            $args["search"][$filterTypeString]["total"] = '?';
            if((getMicrotimeFloat() - $startTime) > $stopAfter) {
                // we have reached the time limit - skip counting results for other item types
                continue;
            }
            $this->fetchSphinxResultAmount($sphinxPdo, $filterTypeString, $term, $args);
        }

        if($currentTypeString !== 'all' && (getMicrotimeFloat() - $startTime) < $stopAfter) {
            $this->fetchSphinxResultAmount($sphinxPdo, 'all', $term, $args);
        }
        $this->view->render($response, 'surrounding.htm', $args);
        return $response;
    }

    public function autocompleteAction(Request $request, Response $response, $args) {
        if($this->auth->hasPermissionFor('media') === FALSE) {
            return $this->renderAccessDenied($response);
        }
        useArguments($args);
        $term = $request->getParam("q");

        $originalTerm = ($request->getParam("qo")) ? $request->getParam("qo") : $term;

        # TODO: evaluate if modifying searchterm makes sense
        // "Artist_-_Album_Name-(CAT001)-WEB-2015" does not match without this modification

        $term = cleanSearchterm($term);
        $args['useExactMatch'] = (preg_match("/^\".*\"$/", trim($originalTerm))) ? TRUE : FALSE;
        $start = 0;
        $offset = 20;
        $result = [];

        $sphinxPdo = \Slimpd\Modules\sphinx\Sphinx::getPdo($this->conf);
        $stmt = $sphinxPdo->prepare("
            SELECT id,type,itemuid,display,trackCount,albumCount FROM ". $this->conf["sphinx"]["mainindex"]."
            WHERE MATCH(:match)
            " . (($args["type"] !== "all") ? " AND type=:type " : "") . "
            GROUP BY itemuid,type
            LIMIT " . $start . "," . $offset . "
            OPTION
            ranker = wordcount,
                field_weights=(title=50, display=40, artist=30, allchunks=1);");

        if(($args["type"] !== "all")) {
            $stmt->bindValue(":type", $this->filterTypeMapping[$args["type"]], \PDO::PARAM_INT);
        }
        $stmt->bindValue(":match", getSphinxMatchSyntax([$term,$originalTerm]), \PDO::PARAM_STR);

        // do some timelogging for debugging purposes
        $timLogData = [
            "-----------------------------------------------",
            "AUTOCOMPLETE timelogger " . getMicrotimeFloat(),
            " term: " . $term,
            " orignal term: " . $originalTerm,
            " matcher: " . getSphinxMatchSyntax([$term,$originalTerm])
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

        if(count($rows) === 0 && $request->getParam("suggested") != 1) {
            $suggest = getPhaseSuggestion($term, $sphinxPdo);
            $timLogData[] = " getPhaseSuggestion() " . (getMicrotimeFloat() - $timeLogBegin);
            if($suggest !== FALSE) {
                $uri = $this->router->pathFor(
                    'autocomplete',
                    ['type' => $args['type'] ]
                ). "?suggested=1&q=" . rawurlencode($suggest) . "&qo=" . rawurlencode($term);
                return $response->withRedirect($uri, 403);
            }
        } else {
            $filterTypeMapping = array_flip($this->filterTypeMapping);
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
        if($this->auth->hasPermissionFor('media') === FALSE) {
            return $this->renderAccessDenied($response);
        }

        // validate directory
        $directory = $this->directoryRepo->create($args['itemParams']);
        if($this->directoryRepo->validate($directory) === FALSE) {
            $this->container->flash->AddMessageNow("error", $this->container->ll->str("directory.notfound"));
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
        $total = parseMetaForTotal($sphinxPdo->query("SHOW META")->fetchAll());

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
        $args["breadcrumb"] = \Slimpd\Modules\Filebrowser\Filebrowser::fetchBreadcrumb($args['itemParams']);
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
        if($this->auth->hasPermissionFor('media') === FALSE) {
            return $this->renderAccessDenied($response);
        }
        useArguments($args);
        $type = $request->getParam("searchtype");
        $term = $request->getParam("searchterm");
        $uri = $this->conf['config']['absRefPrefix'] . $type."s/searchterm/".rawurlencode($term)."/page/1" . getNoSurSuffix($this->view->getEnvironment()->getGlobals()['nosurrounding']);
        return $response->withRedirect($uri, 403);
    }
}
