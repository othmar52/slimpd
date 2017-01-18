<?php
namespace Slimpd\Controllers\User;
/* Copyright (C) 2017 othmar52 <othmar52@users.noreply.github.com>
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

use Slimpd\Controllers\Controller;
use Slimpd\Models\User;
use Respect\Validation\Validator as v;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class UserController extends Controller
{
    use \Slimpd\Traits\MethodRedirectToSignIn;

    public function listAction(Request $request, Response $response)
    {
        useArguments($request);
        if($this->auth->hasPermissionFor('users.list') === FALSE) {
            $this->flash->addMessage('warning', 'Access denied');
            return $this->redirectToSignIn($response);
        }
        return $this->view->render(
            $response,
            'user/list.htm',
            ['users' => User::all()]
        );
    }

    public function editAction(Request $request, Response $response)
    {
        if($this->auth->hasPermissionFor('users.edit') === FALSE) {
            $this->flash->addMessage('warning', 'Access denied');
            return $this->redirectToSignIn($response);
        }
        $user = User::find($request->getParam('uid'));
        if(!$user) {
            $this->flash->addMessage('warning', 'Invalid user');
            return $response->withRedirect(
                $this->router->pathFor('users.list') .
                getNoSurSuffix($this->view->getEnvironment()->getGlobals()['nosurrounding'])
            );
        }
        $user->username = $request->getParam('username');
        $user->role = $request->getParam('role');
        $user->update();
        $this->flash->addMessage('success', 'User had been updated');
        return $response->withRedirect(
            $this->router->pathFor('users.list') .
            getNoSurSuffix($this->view->getEnvironment()->getGlobals()['nosurrounding'])
        );
    }
}
