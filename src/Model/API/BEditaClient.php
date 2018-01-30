<?php
/**
 * BEdita, API-first content management framework
 * Copyright 2018 ChannelWeb Srl, Chialab Srl
 *
 * This file is part of BEdita: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * See LICENSE.LGPL or <http://gnu.org/licenses/lgpl-3.0.html> for more details.
 */

namespace App\Model\API;

use Cake\Network\Exception\ServiceUnavailableException;
use Cake\Utility\Hash;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use Http\Adapter\Guzzle6\Client;
use Psr\Http\Message\ResponseInterface;
use WoohooLabs\Yang\JsonApi\Client\JsonApiClient;

/**
 * BEdita4 API Client class
 */
class BEditaClient
{

    /**
     * Last response.
     *
     * @var \Psr\Http\Message\ResponseInterface
     */
    private $response;

    /**
     * BEdita4 API base URL
     *
     * @var string
     */
    private $apiBaseUrl = null;

    /**
     * BEdita4 API KEY
     *
     * @var string
     */
    private $apiKey = null;

    /**
     * Default headers in request
     *
     * @var array
     */
    private $defaultHeaders = [
        'Accept' => 'application/vnd.api+json',
    ];

    /**
     * JWT Auth tokens
     *
     * @var array
     */
    private $tokens = [];

    /**
     * JSON API BEdita4 client
     *
     * @var \WoohooLabs\Yang\JsonApi\Client\JsonApiClient
     */
    private $jsonApiClient = null;

    /**
     * Setup main client options:
     *  - API base URL
     *  - API KEY
     *  - Auth tokens 'jwt' and 'renew' (optional)
     *
     * @param string $apiUrl API base URL
     * @param string $apiKey API key
     * @param array $tokens JWT Autorization tokens as associative array ['jwt' => '###', 'renew' => '###']
     * @return void
     */
    public function __construct($apiUrl, $apiKey = null, array $tokens = [])
    {
        $this->apiBaseUrl = $apiUrl;
        $this->apiKey = $apiKey;

        $this->defaultHeaders['X-Api-Key'] = $this->apiKey;
        $this->setupTokens($tokens);

        // setup an asynchronous JSON API client
        $guzzleClient = Client::createWithConfig([]);
        $this->jsonApiClient = new JsonApiClient($guzzleClient);
    }

    /**
     * Setup JWT access and refresh tokens.
     *
     * @param array $tokens JWT tokens as associative array ['jwt' => '###', 'renew' => '###']
     * @return void
     */
    public function setupTokens(array $tokens)
    {
        $this->tokens = $tokens;
        if (!empty($tokens['jwt'])) {
            $this->defaultHeaders['Authorization'] = sprintf('Bearer %s', $tokens['jwt']);
        } else {
            unset($this->defaultHeaders['Authorization']);
        }
    }

    /**
     * Get API base URL used tokens
     *
     * @return string API base URL
     */
    public function getApiBaseUrl()
    {
        return $this->apiBaseUrl;
    }

    /**
     * Get current used tokens
     *
     * @return array Current tokens
     */
    public function getTokens()
    {
        return $this->tokens;
    }

    /**
     * Get last HTTP response
     *
     * @return ResponseInterface Response PSR interface
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Get HTTP response status code
     *
     * @return int Status code.
     */
    public function getStatusCode()
    {
        return $this->response->getStatusCode();
    }

    /**
     * Get HTTP response status message
     *
     * @return string Message related to status code.
     */
    public function getStatusMessage()
    {
        return $this->response->getReasonPhrase();
    }

    /**
     * Get response body serialized into a PHP array
     *
     * @return array Response body as PHP array.
     */
    public function getResponseBody()
    {
        return json_decode((string)$this->response->getBody(), true);
    }

    /**
     * Classic authentication via POST /auth using username and password
     *
     * @param string $username username
     * @param string $password password
     * @return array Response in array format
     */
    public function authenticate($username, $password)
    {
        $body = json_encode(compact('username', 'password'));

        return $this->post('/auth', $body, ['Content-Type' => 'application/json']);
    }

    /**
     * Send a GET request a list of resources or objects or a single resource or object
     *
     * @param string $path Endpoint URL path to invoke
     * @param array $query Optional query string
     * @param array $headers Headers
     * @return array Response in array format
     */
    public function get($path, $query = [], $headers = [])
    {
        $this->sendRequestRetry('GET', $path, $query, $headers);

        return $this->getResponseBody();
    }

    /**
     * GET a list of objects of a given type
     *
     * @param string $type Object type name
     * @param array $query Optional query string
     * @param array $headers Custom request headers
     * @return array Response in array format
     */
    public function getObjects($type = 'objects', $query = [], $headers = [])
    {
        return $this->get(sprintf('/%s', $type), $query, $headers);
    }

    /**
     * GET a single object of a given type
     *
     * @param int|string $id Object id
     * @param string $type Object type name
     * @param array $query Optional query string
     * @param array $headers Custom request headers
     * @return array Response in array format
     */
    public function getObject($id, $type = 'objects', $query = [], $headers = [])
    {
        return $this->get(sprintf('/%s/%s', $type, $id), $query, $headers);
    }

    /**
     * Create a new object (POST) or modify an existing one (PATCH)
     *
     * @param string $type Object type name
     * @param array $data Object data to save
     * @param array $headers Custom request headers
     * @return array Response in array format
     */
    public function saveObject($type, $data, $headers = [])
    {
        $id = Hash::get($data, 'id');
        unset($data['id']);

        $body = [
            'data' => [
                'type' => $type,
                'attributes' => $data,
            ],
        ];
        $headers['Content-Type'] = 'application/vnd.api+json';
        if (!$id) {
            return $this->post(sprintf('/%s', $type), json_encode($body), $headers);
        }
        $body['data']['id'] = $id;

        return $this->patch(sprintf('/%s/%s', $type, $id), json_encode($body), $headers);
    }

    /**
     * Delete an object (DELETE) => move to trashcan.
     *
     * @param int|string $id Object id
     * @param string $type Object type name
     * @return array Response in array format
     */
    public function deleteObject($id, $type)
    {
        return $this->delete(sprintf('/%s/%s', $type, $id));
    }

    /**
     * Remove an object => permanently remove object from trashcan.
     *
     * @param int|string $id Object id
     * @return array Response in array format
     */
    public function remove($id)
    {
        return $this->delete(sprintf('/trash/%s', $id));
    }

    /**
     * Get JSON SCHEMA of a resource or object
     *
     * @param string $type Object or resource type name
     * @return array JSON SCHEMA in array format
     */
    public function schema($type)
    {
        $h = ['Accept' => 'application/schema+json'];

        return $this->get(sprintf('/model/schema/%s', $type), null, $h);
    }

    /**
     * Restore object from trash
     *
     * @param int|string $id Object id
     * @param string $type Object type name
     * @return array Response in array format
     */
    public function restoreObject($id, $type)
    {
        $body = [
            'data' => [
                'id' => $id,
                'type' => $type,
            ],
        ];
        $headers['Content-Type'] = 'application/json';

        return $this->patch(sprintf('/%s/%s', 'trash', $id), json_encode($body), $headers);
    }

    /**
     * Send a PATCH request to modify a single resource or object
     *
     * @param string $path Endpoint URL path to invoke
     * @param mixed $body Request body
     * @param array $headers Custom request headers
     * @return array Response in array format
     */
    public function patch($path, $body, $headers = [])
    {
        $this->sendRequestRetry('PATCH', $path, null, $headers, $body);

        return $this->getResponseBody();
    }

    /**
     * Send a POST request for creating resources or objects or other operations like /auth
     *
     * @param string $path Endpoint URL path to invoke
     * @param mixed $body Request body
     * @param array $headers Custom request headers
     * @return array Response in array format
     */
    public function post($path, $body, $headers = [])
    {
        $this->sendRequestRetry('POST', $path, null, $headers, $body);

        return $this->getResponseBody();
    }

    /**
     * Send a DELETE request
     *
     * @param string $path Endpoint URL path to invoke.
     * @return array Response in array format.
     */
    public function delete($path)
    {
        $this->sendRequestRetry('DELETE', $path);

        return $this->getResponseBody();
    }

    /**
     * Send a generic JSON API request with a basic retry policy on expired token exception.
     *
     * @param string $method HTTP Method.
     * @param string $path Endpoint URL path.
     * @param array|null $query Query string parameters.
     * @param string[]|null $headers Custom request headers.
     * @param string|resource|\Psr\Http\Message\StreamInterface|null $body Request body.
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function sendRequestRetry($method, $path, array $query = null, array $headers = null, $body = null)
    {
        try {
            return $this->sendRequest($method, $path, $query, $headers, $body);
        } catch (BEditaClientException $e) {
            // Handle error.
            if ($e->getCode() !== 401 || Hash::get($e->getAttributes(), 'code') !== 'be_token_expired') {
                // Not an expired token's fault.
                throw $e;
            }

            // Refresh and retry.
            $this->refreshTokens();
            unset($headers['Authorization']);

            return $this->sendRequest($method, $path, $query, $headers, $body);
        }
    }

    /**
     * Send a generic JSON API request and retrieve response $this->response
     *
     * @param string $method HTTP Method.
     * @param string $path Endpoint URL path.
     * @param array|null $query Query string parameters.
     * @param string[]|null $headers Custom request headers.
     * @param string|resource|\Psr\Http\Message\StreamInterface|null $body Request body.
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \App\Model\API\BEditaClientException Throws an exception if server response code is not 20x.
     */
    protected function sendRequest($method, $path, array $query = null, array $headers = null, $body = null)
    {
        $uri = new Uri($this->apiBaseUrl);
        $uri = $uri->withPath($uri->getPath() . '/' . $path);
        if ($query) {
            $uri = $uri->withQuery(http_build_query((array)$query));
        }
        $headers = array_merge($this->defaultHeaders, (array)$headers);

        // Send the request synchronously to retrieve the response.
        $this->response = $this->jsonApiClient->sendRequest(new Request($method, $uri, $headers, $body));
        if ($this->getStatusCode() >= 400) {
            // Something bad just happened.
            $statusCode = $this->getStatusCode();
            $response = $this->getResponseBody();

            $code = Hash::get($response, 'error.code', (string)$statusCode);
            $reason = Hash::get($response, 'error.title', $this->getStatusMessage());

            throw new BEditaClientException(compact('code', 'reason'), $statusCode);
        }

        return $this->response;
    }

    /**
     * Refresh JWT access token.
     *
     * On success `$this->tokens` data will be updated with new access and renew tokens.
     *
     * @throws \BadMethodCallException Throws an exception if client has no renew token available.
     * @throws \Cake\Network\Exception\ServiceUnavailableException Throws an exception if server response doesn't
     *      include the expected data.
     * @return void
     */
    public function refreshTokens()
    {
        if (empty($this->tokens['renew'])) {
            throw new \BadMethodCallException(__('You must be logged in to renew token'));
        }

        $headers = [
            'Authorization' => sprintf('Bearer %s', $this->tokens['renew']),
        ];

        $this->sendRequest('POST', '/auth', [], $headers);
        $body = $this->getResponseBody();
        if (empty($body['meta']['jwt'])) {
            throw new ServiceUnavailableException(__('Invalid response from server'));
        }

        $this->setupTokens($body['meta']);
    }
}
