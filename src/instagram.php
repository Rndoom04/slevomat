<?php
    /*
     * Example library for PHP by Kollert Slavom�r
     * version: 1.0
     * release date: 6.11.2022
     */

    namespace Rndoom04\instagram;
    
    use GuzzleHttp\Client;
    use GuzzleHttp\RequestOptions;
    use Nette\Utils\Json;
    
    class instagram {
        /* Properties */
        // Base URI
        private $apiBaseURI = "https://api.instagram.com";
        private $graphBaseURI = "https://graph.instagram.com";
        
        // Endpoints
        private $endpoints = [
            "oauth_authorize" => "/oauth/authorize",
            "short_lived_token" => "/oauth/access_token",
            "long_lived_token" => "/access_token",
            "refresh_long_lived_token" => "/refresh_access_token",
            "get_media" => null // will be full during setUserID() method
        ];

        // Errors
        private $errors = [];
        
        // Credentials
        private $clientID;
        private $clientSecret;
        private $userID;
        private $token;
        private $redirectUri;
        
        // Construct
        public function __construct($clientID = null, $clientSecret = null, $redirectUri = null) {
            // Set client ID
            if (!empty($clientID)) { $this->setClientID($clientID); }
            
            // Set client secret
            if (!empty($clientSecret)) { $this->setClientSecret($clientSecret); }
            
            // Set redirect URI
            if (!empty($redirectUri)) {
                $this->setRedirectUri($redirectUri);
            } else {
                $this->redirectUri = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://".$_SERVER['HTTP_HOST']."/verifyInstagram";
            }
        }
        
        // Set clientID
        public function setClientID($clientID) {
            $this->clientID = $clientID;
            return $this;
        }
        
        // Set client secret
        public function setClientSecret($clientSecret) {
            $this->clientSecret = $clientSecret;
            return $this;
        }
        
        // Set token
        public function setToken($token) {
            $this->token = $token;
            return $this;
        }
        
        // Set token
        public function setUserID($userID) {
            $this->userID = $userID;
            $this->endpoints["get_media"] = "/v15.0/".$this->userID."/media";
            return $this;
        }
        
        // Set redirect Uri
        public function setRedirectUri($redirectUri) {
            $this->redirectUri = $redirectUri;
            return $this;
        }

        
        // Is valid?
        public function isValid() {
            if (!empty($this->clientID) && !empty($this->clientSecret) && !empty($this->redirectUri)) { return true; }
            return false;
        }
        
        // Get instagram URL to get permissions
        public function getLoginUrl() {
            // Prepare data
            $data = [
                "client_id" => $this->clientID,
                "redirect_uri" => $this->redirectUri,
                "response_type" => "code",
                "scope" => "user_profile,user_media"
            ];
            
            // Buld query
            $query = http_build_query($data);
            
            // Build full URL
            $link = $this->apiBaseURI.$this->endpoints['oauth_authorize']."?".$query;
            return $link;
        }
        
        // Get short-lived access token
        public function getShortLivedToken($code) {
            // Prepare data
            $data = [
                "grant_type" => "authorization_code",
                "client_id" => $this->clientID,
                "client_secret" => $this->clientSecret,
                "redirect_uri" => $this->redirectUri,
                "code" => $code
            ];

            // Prepare Guzzle
            $client = new \GuzzleHttp\Client([
                'base_uri' => $this->apiBaseURI,
                'http_errors' => false,
            ]);

            // Send request
            try {
                $response = $client->request('POST', $this->endpoints['short_lived_token'], [
                    'form_params' => $data
                ]);

                // Return body data
                $data = Json::decode((string)$response->getBody());
                return $data; // access_token, user_id
            } catch (Exception $e) {
                $this->addError("Exception: ".$e);
                return false;
            }
        }
        
        // Get long-lived access token for 60 days
        public function getLongLivedToken($token) {
            // Prepare data
            $data = [
                "grant_type" => "ig_exchange_token",
                "client_secret" => $this->clientSecret,
                "access_token" => $token,
            ];

            // Prepare Guzzle
            $client = new \GuzzleHttp\Client([
                'base_uri' => $this->graphBaseURI,
                'http_errors' => false,
            ]);

            // Send request
            try {
                $response = $client->request('GET', $this->endpoints['long_lived_token'], [
                    'query' => $data
                ]);

                // Return body data
                $data = Json::decode((string)$response->getBody()); // access_token, token_type, expires_in, 
                return $data;
            } catch (Exception $e) {
                $this->addError("Exception: ".$e);
                return false;
            }
        }
        
        // Get long-lived access token for 60 days
        public function refreshLongLivedToken($token) {
            // Prepare data
            $data = [
                "grant_type" => "ig_refresh_token",
                "access_token" => $token,
            ];

            // Prepare Guzzle
            $client = new \GuzzleHttp\Client([
                'base_uri' => $this->graphBaseURI,
                'http_errors' => false,
            ]);

            // Send request
            try {
                $response = $client->request('GET', $this->endpoints['refresh_long_lived_token'], [
                    'query' => $data
                ]);

                // Return body data
                $data = Json::decode((string)$response->getBody()); // access_token, token_type, expires_in, 
                return $data;
            } catch (Exception $e) {
                $this->addError("Exception: ".$e);
                return false;
            }
        }
        
        // Get long-lived access token for 60 days
        public function getMedia() {
            // Prepare data
            $data = [
                "fields" => "id,caption,comments_count,like_count,media_type,media_url,thumbnail_url",
                "access_token" => $this->token,
            ];

            // Prepare Guzzle
            $client = new \GuzzleHttp\Client([
                'base_uri' => $this->graphBaseURI,
                'http_errors' => false,
            ]);

            // Send request
            try {
                $response = $client->request('GET', $this->endpoints['get_media'], [
                    'query' => $data
                ]);

                // Return body data
                $data = Json::decode((string)$response->getBody());
                return $data;
            } catch (Exception $e) {
                $this->addError("Exception: ".$e);
                return false;
            }
        }
        
        
        
        
        // Add error
        private function addError(string $error) {
            $this->errors[] = $error;
        }
        // Is any error logged?
        public function hasError() {
            return empty($this->errors)?false:true;
        }
        // Get all errors
        public function getErrors() {
            return $this->errors;
        }
    }
?>