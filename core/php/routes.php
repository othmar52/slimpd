<?php
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

// Routes


#foreach (array(35, 50,100,300,1000) as $imagesize) {
#$app->get('/', 'Slimpd\Modules\images\Controller:index');#->setName('homepage');

$app->get('/filebrowser', 'Slimpd\Modules\filebrowser\Controller:index')->setName('filebrowser');
$app->get('/filebrowser/[{itemParams:.*}]', 'Slimpd\Modules\filebrowser\Controller:dircontent');
$app->get('/markup/widget-directory/[{itemParams:.*}]', 'Slimpd\Modules\filebrowser\Controller:widgetDirectory');

$app->get('/imagefallback-{imagesize}/{type}', 'Slimpd\Modules\images\Controller:fallback')->setName('imagefallback');
$app->get('/image-{imagesize}/album/{itemUid}', 'Slimpd\Modules\images\Controller:album')->setName('imagealbum');
$app->get('/image-{imagesize}/track/{itemUid}', 'Slimpd\Modules\images\Controller:track');#->name('imagefallback-' .$imagesize);


$app->get("/albums/page/{currentPage}/sort/{sort}/{direction}", 'Slimpd\Modules\album\Controller:listAction');

#}