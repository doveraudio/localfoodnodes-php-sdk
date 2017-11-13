<?php

namespace App\Http\Controllers\Admin;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class LocalFoodNodes
{
    private $client;
    private $apiUrl;
    private $clientId;
    private $clientSecret;
    private $username;
    private $password;

    /**
     * Constructor.
     *
     * @param string $apiUrl
     * @param int $clientId
     * @param string $clientSecret
     * @param string $username
     * @param string $password
     */
    public function __construct($apiUrl, $clientId, $clientSecret, $username, $password)
    {
        $this->client = new Client();
        $this->apiUrl = $apiUrl;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * Helper function to perform a GET request.
     *
     * @param string $url
     * @param array $params
     * @return mixed
     */
    public function get($url, $params)
    {
        return $this->request('GET', $url, $params);
    }

    /**
     * Helper function to perform a POST request.
     *
     * @param string $url
     * @param array $params
     * @return mixed
     */
    public function post($url, $params)
    {
        return $this->request('POST', $url, $params);
    }

    /**
     * Helper function to perform a PUT request.
     *
     * @param string $url
     * @param array $params
     * @return mixed
     */
    public function put($url, $params)
    {
        return $this->request('PUT', $url, $params);
    }

    /**
     * Helper function to perform a DELETE request.
     *
     * @param string $url
     * @param array $params
     * @return mixed
     */
    public function delete($url, $params)
    {
        return $this->request('DELETE', $url, $params);
    }

    /**
     * Perform a request to the api.
     *
     * @param string $method
     * @param string $url
     * @param  array $params
     * @return mixed
     */
    public function request($method, $url, $params = [])
    {
        $token = $this->getToken();
        $params = $this->setHeaders($params, $token);

        try {
            $response = $this->client->request($method, $this->buildUrl($url), $params);

            return (string) $response->getBody();
        } catch (RequestException $e) {
            // Try refresh token is response is 401
            if ($e->getResponse()->getStatusCode() === 401) {
                $token = $this->refreshToken($token);
                $data = $this->setHeaders($params, $token);

                // Retry action with refreshed token
                try {
                    $response = $this->client->request($method, $this->buildUrl($url), $params);
                } catch (RequestException $e) {
                    return $e;
                }
            }
        }
    }

    /**
     * Add headers to params.
     *
     * @param array $params
     * @param array $token
     */
    private function setHeaders($data, $token)
    {
        return array_merge([
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token['access_token'],
            ]
        ], $data);
    }

    /**
     * Combine api url with the endpoint.
     *
     * @param string $endpoint
     * @return string
     */
    private function buildUrl($endpoint) {
        return $this->apiUrl . $endpoint;
    }

    /**
     * Get token. Use an old token if one is stored in session, otherwise request a new one.
     *
     * @return array
     */
    public function getToken()
    {
        session_start();

        if (isset($_SESSION['localfoodnodes_api_token'])) {
            return $_SESSION['localfoodnodes_api_token'];
        } else {
            return $this->requestToken();
        }
    }

    /**
     * Request a token from the api.
     *
     * @return array
     */
    private function requestToken()
    {
        $response = $this->client->post($this->apiUrl . '/oauth/token', [
            'form_params' => [
                'grant_type' => 'password',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'username' => $this->username,
                'password' => $this->password,
                'scope' => '*',
            ],
        ]);

        $token = json_decode((string) $response->getBody(), true);

        // If success, store token in session
        if ($token) {
            $_SESSION['localfoodnodes_api_token'] = $token;
        }

        return $token;
    }

    /**
     * Refresh token.
     *
     * @return array
     */
    private function refreshToken($token)
    {
        $response = $this->client->post($this->apiUrl . '/oauth/token', [
            'form_params' => [
                'grant_type' => 'refresh_token',
                'refresh_token' => $token['refresh_token'],
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope' => '*',
            ],
        ]);

        $token = json_decode((string) $response->getBody(), true);

        // If success, store token in session
        if ($token) {
            $_SESSION['localfoodnodes_api_token'] = $token;
        }

        return $token;
    }
}
