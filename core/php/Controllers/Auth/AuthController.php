<?php
namespace Slimpd\Controllers\Auth;
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



class AuthController extends Controller
{
    public function getSignIn(Request $request, Response $response)
    {
        return $this->view->render($response, 'auth/signin.htm');
    }

    public function postSignIn(Request $request, Response $response)
    {
        $auth = $this->auth->attempt(
            $request->getParam('username'),
            $request->getParam('password')
        );
        if($auth === FALSE) {
            return $response->withRedirect($this->router->pathFor('auth.signin'));
        }
        return $response->withRedirect($this->router->pathFor('home'));
    }

    public function getSignUp(Request $request, Response $response)
    {
        return $this->view->render($response, 'auth/signup.htm');
    }

    public function postSignUp(Request $request, Response $response)
    {
        $validation = $this->validator->validate(
            $request,
            [
                'email' => v::noWhitespace()->notEmpty(),
                'username' => v::noWhitespace()->notEmpty()->alpha()->usernameAvailable(),
                'password' => v::noWhitespace()->notEmpty()
            ]
        );
        if($validation->failed()) {
            return $response->withRedirect($this->container->router->pathFor('auth.signup'));
        }
        $user = User::create([
            'email' => $request->getParam('email'),
            'username' => $request->getParam('username'),
            'password' => password_hash($request->getParam('password'), \PASSWORD_DEFAULT),
            'role' => 'member'
        ]);
        return $response->withRedirect($this->container->router->pathFor('home'));
    }
}
