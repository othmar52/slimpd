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
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License
 * for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

// CLI Routes
$ctrlRoutes = [
    ['[/]', 'indexAction'],
    ['/hard-reset', 'hardResetAction'],
    ['/hard-reset/--force', 'hardResetForceAction'],
    ['/remigrate', 'remigrateAction'],
    ['/remigrate/--force', 'remigrateForceAction'],
    ['/remigratealbum/{albumUid}', 'remigratealbumAction'],
    ['/remigratedirectory/[{directory:.*}]', 'remigrateDirectoryAction'],
    ['/bpmdetect', 'bpmdetectAction'],
    ['/bpmdetect/--force', 'bpmdetectForceAction'],
    ['/update', 'updateAction'],
    ['/update/--force', 'updateForceAction'],
    ['/builddictsql', 'builddictsqlAction'],
    ['/update-db-scheme', 'updateDbSchemeAction'],
    ['/database-cleaner', 'databaseCleanerAction'],
    ['/check-que', 'checkQueAction'],
];

foreach($ctrlRoutes as $ctrlRoute) {
    $routeName = (isset($ctrlRoute[2]) === TRUE) ? $ctrlRoute[2] : '';
    $app->get(
        $ctrlRoute[0],
        'Slimpd\Modules\Importer\CliController' . ':' . $ctrlRoute[1]
    )->setName($routeName);
}
