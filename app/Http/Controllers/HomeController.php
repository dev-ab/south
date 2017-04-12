<?php

namespace App\Http\Controllers;

session_start();

use \GuzzleHttp\Client;
use \GuzzleHttp\HandlerStack;
use \GuzzleHttp\Subscriber\Oauth\Oauth1;
use \Illuminate\Support\Facades\Storage;
use App\Services\CalcService as Calc;
use App\Services\ApiService as Api;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class HomeController extends Controller {

    protected $api;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {
        //Set Precision Server Config
        ini_set('auto_detect_line_endings', true);
        ini_set('serialize_precision', '5');

        $this->api = new Api();
    }

    public function index(\Illuminate\Http\Request $request) {

        $message = null;

        if (isset($_SESSION['message'])) {
            $message = $_SESSION['message'];
            unset($_SESSION['message']);
        }

        if (!$this->api->token_check) {
            $title = 'Request Token';
            $status = 'no_token';
            return view('index', compact('title', 'status', 'message'));
        } else if (intval($request->input('org'))) {
            $org = intval($request->input('org'));
            $title = 'Select Zone';
            $status = 'zones';
            $zones = $this->api->getZones($org);
            return view('index', compact('title', 'status', 'org', 'zones', 'message'));
        } else {
            $title = 'Select Organization';
            $status = 'organizations';
            $orgs = $this->api->getOrganizations();
            return view('index', compact('title', 'status', 'orgs', 'message'));
        }
    }

    public function viewChart(\Illuminate\Http\Request $request) {
        $data = $request->all();

        if (!isset($data['org']) || !isset($data['zone']) || empty($data['org']) || empty($data['zone'])) {
            return redirect('/');
        }

        $zoneInfo = $this->api->getZonesInfo($data['org'], $data['zone']);
        $title = 'Chart';
        $status = 'chart';

        //print_r($data);
        //print_r($this->getWeatherData($data['org'], $data['zone'], '2017-04-01', '2017-04-06'));
        //print_r($this->getMoistureData($data['org'], $data['zone'], '2017-04-01', '2017-04-06'));
        //print_r($this->getLatestMoistures($data['org'], $data['zone']));
        $calc = new Calc();
        $aData = $calc->getData($data['org'], $zoneInfo, $data['seedDate'], $data['endDate']);

        //$aData = [[], [], [], []];
        $mdWidth = count($aData[3]) * 67;

        return view('index', compact('title', 'status', 'aData', 'mdWidth', 'zoneInfo'));
    }

    public function startTokenRequest() {
        $check = $this->api->requestToken();
        if ($check) {
            return redirect($check);
        }

        return view('index');
    }

    public function authorizeToken(\Illuminate\Http\Request $request) {
        $file = Storage::get('tempToken.txt');
        $token = explode("\n", $file);
        $this->api->token = trim($token[0]);
        $this->api->token_secret = trim($token[1]);

        $data = $request->all();

        $headers = [
            'Accept' => 'application/vnd.deere.axiom.v3+json',
            'Authorization' => $this->api->generateAuthorizationHeaders('GET', $this->api->access_token_uri, ['oauth_verifier' => $data['oauth_verifier']]),
        ];

        $headers['Authorization'] .= ', oauth_verifier="' . $data['oauth_verifier'] . '"';

        $client = new Client();

        try {
            $response = $client->request('GET', $this->api->access_token_uri, ['headers' => $headers]);
        } catch (\Exception $e) {
            $title = 'token request';
            $status = 'no_token';
            $message = 'tokenerror';
            return view('index', compact('title', 'status', 'message'));
        }


        parse_str($response->getBody(), $res);

        if (isset($res['oauth_token']) && isset($res['oauth_token_secret'])) {

            Storage::put('token.txt', "{$res['oauth_token']}\r\n{$res['oauth_token_secret']}");
            $_SESSION['message'] = 'tokensuccess';
            return redirect('/');
        }

        $title = 'token request';
        $status = 'no_token';
        $message = 'tokenerror';
        return view('index', compact('title', 'status', 'message'));
    }

}
