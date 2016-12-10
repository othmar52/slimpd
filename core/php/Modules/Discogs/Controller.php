<?php
namespace Slimpd\Modules\Discogs;
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
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class Controller extends \Slimpd\BaseController {
    public function indexAction(Request $request, Response $response, $args) {
        useArguments($request);
        $args["action"] = "discogs.index";

        $args["authorized"] = ($this->checkApiAccess() === TRUE) ? 1 : 0;
        $this->view->render($response, 'surrounding.htm', $args);
        return $response;
    }

    public function checkApiAccess() {
        try {
            $token = $this->discogsitemRepo->getInstanceByAttributes(['type' => 'oauth_response_token']);
            $tokenSecret = $this->discogsitemRepo->getInstanceByAttributes(['type' => 'oauth_response_token_secret']);
            if($token === NULL || $tokenSecret === NULL) {
                return FALSE;
            }

            $headers = $this->getApiCallHeaders();
            $headers['headers']['Authorization'] .=
                 ', oauth_token="' . $token->getResponse() .'"'
                .', oauth_signature="'. $this->conf['discogsapi']['consumer_secret'] . '&'. $tokenSecret->getResponse() .'"';

            $client = new \GuzzleHttp\Client();
            $authRequest = $client->createRequest('GET', $this->conf['discogsapi']['identity_url'], $headers);
            if($client->send($authRequest)->getStatusCode() === 200) {
                return TRUE;
            }
        } catch(\Exception $e) {

        }
        return FALSE;
    }

    protected function persistToken(Request $request, Response $response) {
        useArguments($request);
        $reqToken = $this->discogsitemRepo->getInstanceByAttributes(['type' => 'oauth_request_token']);
        $reqTokenSecret = $this->discogsitemRepo->getInstanceByAttributes(['type' => 'oauth_request_token_secret']);

        // send a POST request to the discogs access token url
        $headers = $this->getApiCallHeaders();
        $headers['headers']['Authorization'] .=
             ', oauth_token="' . $reqToken->getResponse() .'"'
            .', oauth_signature="'. $this->conf['discogsapi']['consumer_secret'] . '&'. $reqTokenSecret->getResponse() .'"'
            .', oauth_verifier="' . $request->getQueryParam('oauth_verifier') . '"';

        $client = new \GuzzleHttp\Client();
        $authRequest = $client->createRequest('POST', $this->conf['discogsapi']['access_token_url'], $headers);
        $authResponse = $client->send($authRequest);
        $queryString = \GuzzleHttp\Query::fromString($authResponse->getBody()->getContents());

        // store retrieved tokens in database
        $this->persistOauthProperty('oauth_response_token', $queryString->get('oauth_token'));
        $this->persistOauthProperty('oauth_response_token_secret', $queryString->get('oauth_token_secret'));

        // delete other persisted oauth stuff which is not needed anymore
        $this->discogsitemRepo->delete($reqToken);
        $this->discogsitemRepo->delete($reqTokenSecret);

        // redirect to indexAction
        return $response->withRedirect($this->router->pathFor('discogs'), 301);
    }

    protected function persistOauthProperty($type, $value) {
        $item = $this->discogsitemRepo->getInstanceByAttributes(['type' => $type]);
        if($item === NULL) {
            $item = new \Slimpd\Models\Discogsitem();
            $item->setType($type);
        }
        $item->setResponse($value);
        $item->setTstamp(time());
        $this->discogsitemRepo->update($item);
    }

    /**
     * oauth
     * @see https://www.discogs.com/developers/#page:authentication
     * @see https://holtstrom.com/michael/blog/post/518/Discogs-OAuth-Authorization-Headers.html
     */
    public function verifyAction(Request $request, Response $response, $args) {
        useArguments($args);

        // unable to authenticate with missing configuration
        if($this->conf['discogsapi']['consumer_key'] === '' || $this->conf['discogsapi']['consumer_secret'] === '') {
            throw new \Exception('missing configuration for discogsapi! TODO: error handling', 1481301534);
        }

        // we already have a callback from discogs
        if($request->getQueryParam('oauth_verifier') !== NULL && $request->getQueryParam('oauth_token') !== NULL) {
            return $this->persistToken($request, $response);
        }

        // build callback uri for later persisting token
        $callBackUrl = $request->getUri()->getScheme() . '://' . $request->getUri()->getHost()
            . $this->conf['config']['absRefPrefix'] . 'discogs/verify';

        $headers = $this->getApiCallHeaders();
        $headers['headers']['Authorization'] .=
             ', oauth_signature="'. $this->conf['discogsapi']['consumer_secret'] . '&"'
            .', oauth_callback="' . $callBackUrl . '"';


        $client = new \GuzzleHttp\Client();
        $authRequest = $client->createRequest('GET', $this->conf['discogsapi']['request_token_url'], $headers);
        $authResponse = $client->send($authRequest);
        $queryString = \GuzzleHttp\Query::fromString($authResponse->getBody()->getContents());
        $this->persistOauthProperty('oauth_request_token', $queryString->get('oauth_token'));
        $this->persistOauthProperty('oauth_request_token_secret', $queryString->get('oauth_token_secret'));
        return $response->withStatus(302)->withHeader(
            'Location', $this->conf['discogsapi']['authorize_url'] .'?oauth_token='.$queryString->get('oauth_token')
        );
    }

    /**
     * most API calls uses the same base headers
     * those little differences gets completed after calling this method
     */
    protected function getApiCallHeaders() {
        return [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'User-Agent'=> $this->conf['discogsapi']['useragent'],
                'Authorization' => 'OAuth'
                    . ' oauth_consumer_key="' . $this->conf['discogsapi']['consumer_key'].'"'
                    .', oauth_nonce="' . getFilePathHash(microtime(TRUE)) .'"'
                    .', oauth_signature_method="PLAINTEXT"'
                    .', oauth_timestamp="' .time() .'"'
            ]
        ];
    }
}
