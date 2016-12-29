<?php
namespace Slimpd\Modules\Systemcheck;
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

/**
 * TODO: refacture with a new Check model
 */
trait SphinxChecks {
    protected function runSphinxChecks(&$check) {

        // check sphinx connection
        $check['sxConn']['status'] = 'success';
        try {
            $sphinxPdo = \Slimpd\Modules\sphinx\Sphinx::getPdo($this->conf);
        } catch (\Exception $e) {
            $check['sxConn']['status'] = 'danger';
            $check['sxSchema']['skip'] = TRUE;
            $check['sxContent']['skip'] = TRUE;
            return;
        }
        // check if we can query both sphinx indices
        $schemaError = FALSE;
        $contentError = FALSE;
        foreach(['main', 'suggest'] as $indexName) {
            $sphinxPdo = \Slimpd\Modules\sphinx\Sphinx::getPdo($this->conf);
            $stmt = $sphinxPdo->prepare(
                "SELECT ". $this->conf['sphinx']['fields_'.$indexName]." FROM ". $this->conf['sphinx'][$indexName . 'index']." LIMIT 1;"
            );
            $stmt->execute();
            if($stmt->errorInfo()[0] > 0) {
                $check['sxSchema']['status'] = 'danger';
                $check['sxSchema']['msg'] = $stmt->errorInfo()[2];
                $schemaError = TRUE;
                $check['sxContent']['skip'] = TRUE;
                continue;
            }
            $check['sxSchema']['status'] = 'sucess';
            $check['sxContent']['skip'] = FALSE;
            $total = parseMetaForTotal($sphinxPdo->query("SHOW META")->fetchAll());
            if($total < 1) {
                $contentError = TRUE;
                continue;
            }
            $check['sxContent'][$indexName]['total'] = $total;
        }
        $check['sxSchema']['status'] = ($schemaError === TRUE) ? 'danger' : 'success';
        $check['sxContent']['status'] = ($contentError === TRUE) ? 'danger' : 'success';
        if($schemaError === TRUE) {
            $check['sxContent']['skip'] = TRUE;
            $check['sxContent']['status'] = 'warning';
        }
    }
}
