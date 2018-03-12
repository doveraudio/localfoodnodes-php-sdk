<?php

namespace LocalFoodNodes\Sdk;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\Session;

class LocalFoodNodes
{
    /**
     * @var GuzzleHttp\Client
     */
    private $client;

    private $sessionKey = 'localfoodnodes_api_token';

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
        return $this->request('GET', $url, ['query' => $params]);
    }

    /**
     * Helper function to perform a POST request.
     * Check header Content-Type for correct data format.
     *
     * @param string $url
     * @param array $params
     * @return mixed
     */
    public function post($url, $params)
    {
        if (isset($params['header']['Content-Type']) && $params['header']['Content-Type'] === 'multipart/form-data') {
            $params = ['multipart' => $params];
        } else {
            $params = ['form_params' => $params];
        }

        return $this->request('POST', $url, $params);
    }

    /**
     * Helper function to perform a PUT request.
     * Check header Content-Type for correct data format.
     *
     * @param string $url
     * @param array $params
     * @return mixed
     */
    public function put($url, $params)
    {
        if (isset($params['header']['Content-Type']) && $params['header']['Content-Type'] === 'multipart/form-data') {
            $params = ['multipart' => $params];
        } else {
            $params = ['form_params' => $params];
        }

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
        return $this->request('DELETE', $url, ['query' => $params]);
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
        if (!$this->client) {
            $this->client = new Client();
        }

        try {
            $token = $this->getToken();
            $params = $this->setHeaders($params, $token);
            $response = $this->client->request($method, $this->buildUrl($url), $params);

            return (string) $response->getBody();
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 401) {
                try {
                    // Retry with refreshed token
                    $refreshedToken = $this->refreshToken($token);
                    $data = $this->setHeaders($params, $refreshedToken);
                    $response = $this->client->request($method, $this->buildUrl($url), $params);
                    return (string) $response->getBody();
                } catch (ClientException $e) {
                    $this->unsetSession();

                    return $e->getResponse();
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
    private function setHeaders($params, $token)
    {
        return array_merge([
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token['access_token'],
            ]
        ], $params);
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
        if ($this->getSession()) {
            return $this->getSession();
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

        return $this->setSession($token);
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

        $refreshedToken = json_decode((string) $response->getBody(), true);

        return $this->setSession($refreshedToken);
    }

    /**
     * Get token from session.
     *
     * @return array
     */
    private function getSession()
    {
        return Session::has($this->sessionKey) ? Session::get($this->sessionKey) : null;
    }

    /**
     * Save token to session.
     *
     * @param array $token
     * @return array
     */
    private function setSession($token)
    {
        Session::put($this->sessionKey, $token);

        return $token;
    }

    /**
     * Remove token from session.
     */
    private function unsetSession()
    {
        Session::forget($this->sessionKey);
    }
}
