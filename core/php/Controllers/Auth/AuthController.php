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
    use \Slimpd\Traits\MethodRedirectToSignIn;

    public function getSignOut(Request $request, Response $response)
    {
        $this->auth->logout();
        return $response->withRedirect(
            $this->router->pathFor('home') .
            getNoSurSuffix($this->view->getEnvironment()->getGlobals()['nosurrounding'])
        );
    }

    /**
     * login without a password in case user.quickswitch == 1
     * TODO: limit this functionality to LAN access
     */
    public function quickSignInAction(Request $request, Response $response, $args)
    {
        $auth = $this->auth->attemptQuickLogin($args["itemUid"]);
        if($auth === FALSE) {
            $this->flash->AddMessage("error", "Could not log you in.");
            return $this->redirectToSignIn($response);
        }
        return $response->withRedirect(
            $this->router->pathFor('home') .
            getNoSurSuffix($this->view->getEnvironment()->getGlobals()['nosurrounding'])
        );
    }

    public function getSignIn(Request $request, Response $response)
    {
        $users = User::where("quickswitch", "1")->getModels();
        return $this->view->render($response, 'auth/signin.htm', ['users' => $users]);
    }

    public function postSignIn(Request $request, Response $response)
    {
        $auth = $this->auth->attempt(
            $request->getParam('username'),
            $request->getParam('password')
        );
        if($auth === FALSE) {
            $this->flash->AddMessage("error", "Could not log you in.");
            return $this->redirectToSignIn($response);
        }
        return $response->withRedirect(
            $this->router->pathFor('home') .
            getNoSurSuffix($this->view->getEnvironment()->getGlobals()['nosurrounding'])
        );
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
                'username' => v::noWhitespace()->notEmpty()->alpha()->usernameAvailable(),
                'email' => v::noWhitespace(),
                'password' => v::noWhitespace()
            ]
        );
        if($validation->failed()) {
            return $response->withRedirect(
                $this->container->router->pathFor('auth.signup') .
                getNoSurSuffix($this->view->getEnvironment()->getGlobals()['nosurrounding'])
            );
        }
        $user = User::create([
            'email' => $request->getParam('email'),
            'username' => $request->getParam('username'),
            'password' => password_hash($request->getParam('password'), \PASSWORD_DEFAULT),
            'quickswitch' => (($request->getParam('quickswitch') === "1") ? 1 : NULL),
            'role' => 'member'
        ]);
        return $response->withRedirect(
            $this->container->router->pathFor('home') .
            getNoSurSuffix($this->view->getEnvironment()->getGlobals()['nosurrounding']));
    }
}
