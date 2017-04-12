<?php

namespace App\Services;

use \GuzzleHttp\Client;
use \GuzzleHttp\HandlerStack;
use \GuzzleHttp\Subscriber\Oauth\Oauth1;
use \Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Session;

class ApiService {

    public $consumer_key;
    public $consumer_secret;
    public $token;
    public $token_secret;
    public $request_token_uri;
    public $authorize_token_uri;
    public $access_token_uri;
    public $api_uri;
    public $token_check;

    public function __construct() {
        //app credentials
        $this->consumer_key = 'johndeere-1PVcV2uRbI26hqKA00o4cbA0';
        $this->consumer_secret = '2360cd51084c5a7c46c596f755577a5550fe235b';
        //uris
        $this->request_token_uri = 'https://developer.deere.com/oauth/oauth10/initiate';
        $this->authorize_token_uri = 'https://developer.deere.com/oauth/auz/authorize';
        $this->access_token_uri = 'https://developer.deere.com/oauth/oauth10/token';
        $this->api_uri = 'https://apicert.soa-proxy.deere.com/fc';

        $this->token_check = $this->checkToken();
    }

    /**
     * Check if there's a valid access token
     */
    public function checkToken() {
        $file = Storage::get('token.txt');

        $token = explode("\n", $file);

        if (count($token) < 2)
            return false;

        $this->token = trim($token[0]);
        $this->token_secret = trim($token[1]);

        return $this->checkData();
    }

    public function checkData() {
        $data = $this->requestApiData('/organizations');
        if (!is_array($data) || !isset($data['values']) || empty($data['values']))
            return false;

        return true;
    }

    public function requestApiData($link, $params = []) {
        $headers = [
            'Accept' => 'application/vnd.deere.axiom.v3+json',
            'Authorization' => $this->generateAuthorizationHeaders('GET', $this->api_uri . $link, $params),
        ];

        $client = new Client();

        try {
            $response = $client->request('GET', $this->api_uri . $link, ['headers' => $headers, 'query' => $params]);
        } catch (\Exception $e) {
            echo $e->getMessage();
            return false;
        }

        return json_decode($response->getBody(), true);
    }

    public function getOrganizations() {
        return $this->requestApiData('/organizations');
    }

    public function getZones($orgId) {
        return $this->requestApiData("/organizations/$orgId/managementZones");
    }

    public function getZonesInfo($orgId, $zoneId) {
        return $this->requestApiData("/organizations/$orgId/managementZones/$zoneId");
    }

    public function getLatestWeather($orgId, $zoneId) {
        return $this->requestApiData("/organizations/$orgId/managementZones/$zoneId/latestWeatherSensorMeasurements");
    }

    public function getLatestMoistures($orgId, $zoneId) {
        return $this->requestApiData("/organizations/$orgId/managementZones/$zoneId/latestSoilMoistureMeasurements");
    }

    public function getWeatherData($orgId, $zoneId, $startDate, $endDate) {

        $startDate = gmdate("Y-m-d\TH:i:s\Z", strtotime($startDate));
        $endDate = gmdate("Y-m-d\TH:i:s\Z", strtotime($endDate));

        return $this->requestApiData("/organizations/$orgId/managementZones/$zoneId/weatherSensorMeasurements", ['startDate' => $startDate, 'endDate' => $endDate]);
    }

    public function getMoistureData($orgId, $zoneId, $startDate, $endDate) {

        $startDate = gmdate("Y-m-d\TH:i:s\Z", strtotime($startDate));
        $endDate = gmdate("Y-m-d\TH:i:s\Z", strtotime($endDate));

        return $this->requestApiData("/organizations/$orgId/managementZones/$zoneId/soilMoistureMeasurements", ['startDate' => $startDate, 'endDate' => $endDate]);
    }

    public function requestToken() {
        $headers = [
            'Accept' => 'application/vnd.deere.axiom.v3+json',
            'Authorization' => $this->generateAuthorizationHeaders('GET', $this->request_token_uri . "?oauth_callback=" . urlencode(url('authorize')), ['oauth_callback' => url('authorize')]),
        ];

        $client = new Client();

        try {
            $response = $client->request('GET', $this->request_token_uri . "?oauth_callback=" . urlencode(url('authorize')), ['headers' => $headers]);
        } catch (\Exception $e) {
            echo $e->getMessage();
            return false;
        }

        parse_str($response->getBody(), $res);

        Storage::put('tempToken.txt', "{$res['oauth_token']}\r\n{$res['oauth_token_secret']}");

        return $this->authorize_token_uri . "?oauth_token={$res['oauth_token']}";
    }

    // Generates an OAuth HMAC-SHA1 signature
    // @param $url -- the url portion of the signature base 
    // @param $http_method -- the HTTP method in the signature base 
    // @param $params -- parameters to append to the signature base 
    // @return OAuth signature ; always returns successfully
    public function generateSignature($url, $http_method, array $params) {
        // Sort params alphabetically
        ksort($params);

        // Generate the signature base 
        $baseString = $http_method . "&" . rawurlencode($url) . "&";
        foreach ($params as $key => $value)
            $baseString .= rawurlencode($key . '=' . $value . "&");
        $baseString = rtrim($baseString, "%26"); // Remove extra "&" fromm end
        // Generate the signature signing key
        $signatureKey = rawurlencode($this->consumer_secret) . "&";
        if (!empty($this->token_secret))
            $signatureKey .= rawurlencode($this->token_secret);

        // Hash the base  with the signature key, convert to base64
        $signature = base64_encode(hash_hmac("sha1", $baseString, $signatureKey, true));
        return urlencode($signature);
    }

    // Generates an OAuth authorization header for a request
    // @param $http_method -- the HTTP method of the request
    // @param $protected_resource_url -- the url of the request
    // @param $query_parameters -- extra parameters for the signature
    // @return OAuth header ; always returns successfully
    public function generateAuthorizationHeaders($http_method, $protected_resource_url, array $query_parameters = []) {
        // Create the OAuth authorization header parameters
        $params = [
            "oauth_consumer_key" => $this->consumer_key,
            "oauth_nonce" => $this->generateNonce(),
            "oauth_signature_method" => "HMAC-SHA1",
            "oauth_timestamp" => strval(time()),
            "oauth_version" => "1.0"];

        if (!empty($this->token))
            $params["oauth_token"] = $this->token;

        // Create the authorization headers from the parameters
        $headers = "OAuth ";
        foreach ($params as $key => $value)
            $headers .= $key . '="' . $value . '", ';

        // Generate a signature, including any query parameters
        foreach ($query_parameters as $key => $value)
            $params[urlencode($key)] = urlencode($value);
        $signature = $this->generateSignature($protected_resource_url, $http_method, $params);

        // Append the signature to authorization headers
        $headers .= 'oauth_signature="' . $signature . '"';
        return $headers;
    }

    // Generates a nonce; unix timestamp concatenated with 10 char random 
    // This format gives 107 billion unique nonces per second
    // @return OAuth nonce
    public function generateNonce() {
        $suffix = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 10);
        return time() . $suffix;
    }

}
