<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Google OAuth 2.0 Authentication Library
 * 
 * Handles Google Sign-In flow using OAuth 2.0 (server-side)
 * No external SDK required - uses raw HTTP (Guzzle or cURL)
 */
class Google_auth
{
    private $client_id;
    private $client_secret;
    private $redirect_uri;
    private $CI;

    // Google OAuth endpoints
    const AUTH_URL  = 'https://accounts.google.com/o/oauth2/v2/auth';
    const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    const USER_URL  = 'https://www.googleapis.com/oauth2/v2/userinfo';

    public function __construct()
    {
        $this->CI =& get_instance();
        
        // Read from options (admin settings) or environment variables
        $this->client_id     = get_option('google_auth_client_id', getenv('GOOGLE_CLIENT_ID') ?: '');
        $this->client_secret = get_option('google_auth_client_secret', getenv('GOOGLE_CLIENT_SECRET') ?: '');
        $this->redirect_uri  = base_url() . 'auth/google_callback';
    }

    /**
     * Check if Google Auth is enabled and properly configured
     */
    public function is_enabled()
    {
        return get_option('enable_google_login', 0) && !empty($this->client_id) && !empty($this->client_secret);
    }

    /**
     * Get the Google OAuth authorization URL
     */
    public function get_auth_url()
    {
        $params = [
            'client_id'     => $this->client_id,
            'redirect_uri'  => $this->redirect_uri,
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'access_type'   => 'online',
            'prompt'        => 'select_account',
            'state'         => $this->generate_state_token(),
        ];

        return self::AUTH_URL . '?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for access token
     */
    public function get_access_token($code)
    {
        $post_data = [
            'code'          => $code,
            'client_id'     => $this->client_id,
            'client_secret' => $this->client_secret,
            'redirect_uri'  => $this->redirect_uri,
            'grant_type'    => 'authorization_code',
        ];

        $response = $this->http_post(self::TOKEN_URL, $post_data);
        
        if (!$response || isset($response['error'])) {
            log_message('error', 'Google Auth Token Error: ' . json_encode($response));
            return false;
        }

        return $response['access_token'] ?? false;
    }

    /**
     * Get user profile from Google using access token
     */
    public function get_user_profile($access_token)
    {
        $response = $this->http_get(self::USER_URL, $access_token);
        
        if (!$response || isset($response['error'])) {
            log_message('error', 'Google Auth Profile Error: ' . json_encode($response));
            return false;
        }

        return [
            'google_id'  => $response['id'] ?? '',
            'email'      => $response['email'] ?? '',
            'first_name' => $response['given_name'] ?? '',
            'last_name'  => $response['family_name'] ?? '',
            'avatar'     => $response['picture'] ?? '',
            'verified'   => $response['verified_email'] ?? false,
        ];
    }

    /**
     * Generate a CSRF state token
     */
    private function generate_state_token()
    {
        $token = bin2hex(random_bytes(16));
        $this->CI->session->set_userdata('google_auth_state', $token);
        return $token;
    }

    /**
     * Verify the CSRF state token
     */
    public function verify_state_token($state)
    {
        $stored = $this->CI->session->userdata('google_auth_state');
        $this->CI->session->unset_userdata('google_auth_state');
        return $stored && hash_equals($stored, $state);
    }

    /**
     * HTTP POST request using cURL
     */
    private function http_post($url, $data)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $result = curl_exec($ch);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($error) {
            log_message('error', 'Google Auth cURL Error: ' . $error);
            return false;
        }

        return json_decode($result, true);
    }

    /**
     * HTTP GET request using cURL (with Bearer token)
     */
    private function http_get($url, $access_token)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token,
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $result = curl_exec($ch);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($error) {
            log_message('error', 'Google Auth cURL Error: ' . $error);
            return false;
        }

        return json_decode($result, true);
    }
}
