<?php

/**
 * The PHP class for vk.com API and to support OAuth.
 * @author Vlad Pronsky <vladkens@yandex.ru>
 * @license http://www.gnu.org/licenses/gpl.html GPL v3
 * @version 0.1 (Желе)
 */

class VK
{
    /* VK application ID. */
    private $app_id;
    /* VK application secret key. */
    private $api_secret;
    /* VK access token. */
    private $access_token;
    
    /* Set timeout. */
    private $timeout        = 30;
    /* Set connect timeout. */
    private $connecttimeout = 30;
    /* Check SLL certificate. */
    private $ssl_verifypeer = false;
    /* Set library version. */
    private $lib_version    = '0.1';
    
    /* Contains the last HTTP status code returned. */
    private $http_code;
    /* Contains the last HTTP headers returned. */
    private $http_info;
    /* Authorization status. */
    private $auth = false;
    
    /**
     * Set base API URLs.
     */
    public function baseAuthorizeURL()   { return 'http://oauth.vk.com/authorize'; }
    public function baseAccessTokenURL() { return 'https://oauth.vk.com/access_token'; }
    public function getAPI_URL()         { return 'http://api.vk.com/api.php'; }
    
    /**
     * Construct VK object.
     */
    public function __construct($app_id, $api_secret, $access_token = null) {
        $this->app_id       = $app_id;
        $this->api_secret   = $api_secret;
        $this->access_token = $access_token;
        
        if (!is_null($this->access_token) && !$this->checkAccessToken()) {
            throw new Exception('Invalid access token.');
        } else {
            $this->auth = true;
        }
    }
    
    /* public: */
    
    /**
     * Returns authorization status.
     * @return bool true is auth, false is not auth
     */
    public function is_auth() {
        if (!is_null($this->access_token) && $this->auth) {
            return true;
        }
        else return false;
    }
    
    /**
     * VK API method.
     * @param string $method Contains VK API method.
     * @param array $parameters Contains settings call.
     * @return array
     */
    public function api($method, $parameters = null) {
        if (is_null($parameters)) $parameters = array();
        $parameters['api_id']       = $this->app_id;
        $parameters['v']            = $this->lib_version;
        $parameters['method']       = $method;
        $parameters['timestamp']    = time();
        $parameters['format']       = 'json';
        $parameters['random']       = rand(0, 10000);
        
        if (!is_null($this->access_token))
            $parameters['access_token'] = $this->access_token;
            
        ksort($parameters);
        
        $sig = '';
        foreach ($parameters as $key => $value) {
            $sig .= $key . '=' . $value;
        }
        $sig .= $this->api_secret;
        
        $parameters['sig'] = md5($sig);
        $query = $this->createURL($parameters, $this->getAPI_URL());
        
        return json_decode(file_get_contents($query), true);
    }
    
    /**
     * Get authorize URL.
     * @return string
     */
    public function getAuthorizeURL($api_settings = '', $callback_url = 'http://oauth.vk.com/blank.html') {

        $parameters = array(
            'client_id'     => $this->app_id,
            'scope'         => $api_settings,
            'redirect_uri'  => $callback_url,
            'response_type' => 'code'
        );
        
        return $this->createURL($parameters, $this->baseAuthorizeURL());
    }
    
    /**
     * Get the access token.
     * @return array(
     *      'access_token'  => 'the-access-token',
     *      'expires_in'    => '86399', // time life token in seconds
     *      'user_id'       => '12345')
     */
    public function getAccessToken($code) {
        if (!is_null($this->access_token) && $this->auth) {
            throw new Exception('Already authorized.');
        }
        
        $parameters = array(
            'client_id'     => $this->app_id,
            'client_secret' => $this->api_secret,
            'code'          => $code
        );
        
        $url = $this->createURL($parameters, $this->baseAccessTokenURL());
        $rs  = $this->http($url);
        
        if (isset($rs['error'])) {
            $message = 'HTTP status code: ' . $this->http_code . '. ' . $rs['error'] . ': ' . $rs['error_description'];
            throw new Exception($message);
        } else {
            $this->auth = true;
            $this->access_token = $rs['access_token'];
            return $rs;
        }
    }
    
    /* private: */
    
    /**
     * Make HTTP request.
     * @return array API return
     */
    private function http($url, $method = 'GET', $postfields = null) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_USERAGENT,      'VK v' . $this->lib_version);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connecttimeout);
        curl_setopt($ch, CURLOPT_TIMEOUT,        $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->ssl_verifypeer);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            
            if (!is_null($postfields)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
            }
        }
        
        curl_setopt($ch, CURLOPT_URL, $url);
        
        $rs = curl_exec($ch);
        $this->http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->http_info = curl_getinfo($ch);
        curl_close($ch);
        
        return json_decode($rs, true);
    }
    
    /**
     * Create URL from the sended parameters.
     * @return string 
     */
    private function createURL($parameters, $url) {
        $pice = array();
		foreach ($parameters as $key => $value)
			$pice[] = $key . '=' . urlencode($value);
        
        $url .= '?' . implode('&', $pice);;
        return $url;
    }
    
    /**
     * Check freshness of access token.
     * @return bool true is valid access token else false
     */
    private function checkAccessToken() {
        if (is_null($this->access_token)) return false;
        
        $response = $this->api('getUserSettings');
        
        if (isset($response['response'])) return true;
        else return false;
    }
    
}