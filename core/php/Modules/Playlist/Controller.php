<?php
namespace Slimpd\Modules\Playlist;
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
    public function indexAction(Request $request, Response $response, $args) {
        useArguments($request, $response, $args);
        $args['action'] = "playlists";
        $this->view->render($response, 'surrounding.htm', $args);
        return $response;
    }

    public function showAction(Request $request, Response $response, $args) {
        useArguments($request, $response, $args);
        $args['action'] = "showplaylist";
        $playlist = new \Slimpd\Models\PlaylistFilesystem($this->container);
        $playlist->load($args['itemParams']);

        if($playlist->getErrorPath() === TRUE) {
            $this->view->render($response, 'surrounding.htm', $args);
            return $response;
        }

        $itemsPerPage = $this->conf['mpd-playlist']['max-items'];
        $totalItems = $playlist->getLength();
        $currentPage = ($request->getParam('page') === 'last')
            ? ceil($totalItems/$itemsPerPage)
            : (($request->getParam('page')) ? $request->getParam('page') : 1);

        $minIndex = (($currentPage-1) * $itemsPerPage);
        $maxIndex = $minIndex +  $itemsPerPage;
        $playlist->fetchTrackRange($minIndex, $maxIndex);

        $args['itemlist'] = $playlist->getTracks();
        $args['renderitems'] = $this->getRenderItems($args['itemlist']);
        $args['playlist'] = $playlist;
        $args['paginator'] = new \JasonGrimes\Paginator(
            $totalItems,
            $itemsPerPage,
            $currentPage,
            $this->conf['config']['absRefPrefix'] . 'showplaylist/'.$playlist->getRelPath() .'?page=(:num)'
        );
        $args['paginator']->setMaxPagesToShow(paginatorPages($currentPage));
        $this->view->render($response, 'surrounding.htm', $args);
        return $response;
    }

    public function widgetAction(Request $request, Response $response, $args) {
        useArguments($request, $response, $args);
        $args['action'] = 'widget-playlist';
        $args['playlist'] = new \Slimpd\Models\PlaylistFilesystem($this->container);
        $args['playlist']->load($args['itemParams']);
        $args['playlist']->fetchTrackRange(0, 5);
        $args['playlisttracks'] = $args['playlist']->getTracks();
        $args['renderitems'] = $this->getRenderItems($args['playlist']->getTracks());
        $args['breadcrumb'] =  \Slimpd\Modules\Filebrowser\Filebrowser::fetchBreadcrumb($args['itemParams']);
        $this->view->render($response, 'modules/widget-playlist.htm', $args);
        return $response;
    }
}
