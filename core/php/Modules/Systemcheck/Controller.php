<?php
namespace Slimpd\Modules\Systemcheck;
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
	use \Slimpd\Traits\MethodTypeListAction;
	public function runAction(Request $request, Response $response, $args) {
		$dbError = ($request->getParam('dberror') !== NULL) ? TRUE : FALSE;

		$systemCheck = new \Slimpd\Modules\Systemcheck\Systemcheck($this->container, $request);		
		$systemCheck->configLocalUrl = $request->getUri()->getScheme()
			. "://" . $request->getUri()->getHost()
			. $this->conf['config']['absFilePrefix']
			. 'core/config/config_local.ini';

		$args['configLocalUrl'] = $systemCheck->configLocalUrl;
		$args['sys'] = $systemCheck->runChecks($dbError);
		$args['appRoot'] = APP_ROOT;
		$args['action'] = 'systemcheck';
		$this->view->render($response, 'appless.htm', $args);
		return $response;
	}
}
