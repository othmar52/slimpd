<?php
namespace Slimpd\Traits;
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
 * FITNESS FOR A PARTICULAR PURPOSE.    See the GNU Affero General Public License
 * for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.    If not, see <http://www.gnu.org/licenses/>.
 */

trait MethodTypeListAction {

    protected function completeArgsForTypelist($className, &$args) {
        $repoKey = $className . 'Repo';
        $args['action'] = 'library.'. $className .'s';
        $currentPage = 1;
        $itemsPerPage = 100;
        $searchterm = FALSE;
        // TODO: implement orderBy support
        //$orderBy = FALSE;

        $urlSegments = trimExplode("/", $args['itemParams']); 
        foreach($urlSegments as $i => $urlSegment) {
            switch($urlSegment) {
                case 'page':
                    if(isset($urlSegments[$i+1]) === TRUE && is_numeric($urlSegments[$i+1]) === TRUE) {
                        $currentPage = (int) $urlSegments[$i+1];
                    }
                    break;
                case 'searchterm':
                    if(isset($urlSegments[$i+1]) === TRUE && strlen(trim($urlSegments[$i+1])) > 0) {
                        $searchterm = trim($urlSegments[$i+1]);
                    }
                    break;
                default:
                    break;
            }
        }

        if($searchterm !== FALSE) {
            $args['itemlist'] = $this->$repoKey->getInstancesLikeAttributes(
                array('az09' => preg_replace('/[^\da-z]/i', '%', $searchterm)),
                $itemsPerPage,
                $currentPage
            );
            $args['totalresults'] = $this->$repoKey->getCountLikeAttributes(
                array('az09' => preg_replace('/[^\da-z]/i', '%', $searchterm))
            );
            $urlPattern = $this->conf['config']['absRefPrefix'] .$className.'s/searchterm/'.$searchterm.'/page/(:num)';
        } else {
            $args['itemlist'] = $this->$repoKey->getAll($itemsPerPage, $currentPage);
            $args['totalresults'] = $this->$repoKey->getCountAll();
            $urlPattern = $this->conf['config']['absRefPrefix'] . $className.'s/page/(:num)';
        }
        $args['paginator'] = new \JasonGrimes\Paginator(
            $args['totalresults'],
            $itemsPerPage,
            $currentPage,
            $urlPattern
        );
        $args['searchterm'] = $searchterm;
        $args['paginator']->setMaxPagesToShow(paginatorPages($currentPage));
        $args['renderitems'] = $this->getRenderItems($args['itemlist']);
        return;
    }
}
