<?php
namespace utagawavtt\garmin_connect_sso\controller;

class garmin_login
{
    protected $auth;
    protected $config;
    protected $db;
    protected $helper;
    protected $request;
    protected $template;
    protected $user;
    protected $phpbb_root_path;
    protected $php_ext;

    public function __construct(\phpbb\auth\auth $auth, \phpbb\config\config $config,
        \phpbb\db\driver\driver_interface $db, \phpbb\controller\helper $helper,
        \phpbb\request\request $request, \phpbb\template\template $template,
        \phpbb\user $user, $phpbb_root_path, $php_ext)
    {
        $this->auth = $auth;
        $this->config = $config;
        $this->db = $db;
        $this->helper = $helper;
        $this->request = $request;
        $this->template = $template;
        $this->user = $user;
        $this->phpbb_root_path = $phpbb_root_path;
        $this->php_ext = $php_ext;
    }

    public function login()
    {
        if (empty($this->config['garmin_sso_enabled'])) {
            trigger_error('GARMIN_SSO_DISABLED');
        }
        if (empty($this->config['garmin_sso_client_id']) || empty($this->config['garmin_sso_client_secret'])) {
            trigger_error('GARMIN_SSO_NOT_CONFIGURED');
        }
        if ($this->user->data['is_registered']) {
            redirect($this->helper->route('phpbb_index'));
        }

        $code_verifier = $this->generate_random_string(64);
        $this->user->session->set('garmin_sso_code_verifier', $code_verifier);

        $code_challenge = $this->base64url_encode(hash('sha256', $code_verifier, true));

        $state = $this->generate_random_string(32);
        $this->user->session->set('garmin_sso_state', $state);

        $params = [
            'response_type'         => 'code',
            'client_id'             => $this->config['garmin_sso_client_id'],
            'code_challenge'        => $code_challenge,
            'code_challenge_method' => 'S256',
            'redirect_uri'          => $this->helper->route('utagawavtt_garmin_connect_sso_callback', [], true),
            'state'                 => $state,
        ];
        $auth_url = 'https://connect.garmin.com/oauth2Confirm?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        redirect($auth_url);
    }

    public function handle_callback()
    {
        $code = $this->request->variable('code', '');
        $state = $this->request->variable('state', '');
        $session_state = $this->user->session->get('garmin_sso_state');
        $code_verifier = $this->user->session->get('garmin_sso_code_verifier');

        $this->user->session->delete('garmin_sso_state');
        $this->user->session->delete('garmin_sso_code_verifier');

        if (empty($code) || empty($state) || empty($session_state) || $state !== $session_state) {
            trigger_error('GARMIN_SSO_INVALID_STATE');
        }
        if (empty($code_verifier)) {
            trigger_error('GARMIN_SSO_INVALID_VERIFIER');
        }

        $token_url = 'https://diauth.garmin.com/di-oauth2-service/oauth/token';
        $redirect_uri = $this->helper->route('utagawavtt_garmin_connect_sso_callback', [], true);
        $post_data = http_build_query([
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => $redirect_uri,
            'client_id'     => $this->config['garmin_sso_client_id'],
            'client_secret' => $this->config['garmin_sso_client_secret'],
            'code_verifier' => $code_verifier,
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $token_url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $post_data,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $http_code !== 200) {
            trigger_error('GARMIN_SSO_TOKEN_ERROR: ' . $response);
        }

        $token_data = json_decode($response, true);
        if (empty($token_data['access_token'])) {
            trigger_error('GARMIN_SSO_TOKEN_INVALID: ' . $response);
        }

        return $this->fetch_user_and_login($token_data);
    }

    private function fetch_user_and_login($token_data)
    {
        $access_token = $token_data['access_token'];
        $user_id_url = 'https://apis.garmin.com/wellness-api/rest/user/id';

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $user_id_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $access_token],
        ]);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $http_code !== 200) {
            trigger_error('GARMIN_SSO_USER_ID_ERROR: ' . $response);
        }

        $user_id_data = json_decode($response, true);
        $garmin_user_id = isset($user_id_data['userId']) ? $user_id_data['userId'] : null;

        if (empty($garmin_user_id)) {
            trigger_error('GARMIN_SSO_USER_ID_EMPTY');
        }

        $sql = 'SELECT user_id FROM ' . USERS_TABLE . " WHERE user_garmin_id = '" . $this->db->sql_escape($garmin_user_id) . "'";
        $result = $this->db->sql_query($sql);
        $user_row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        $user_id = ($user_row) ? (int) $user_row['user_id'] : 0;

        if ($user_id) {
            $this->update_user_tokens($user_id, $token_data);
        } else {
            if ($this->config['garmin_auto_register']) {
                $user_id = $this->create_user($garmin_user_id, $token_data);
                if ($user_id === false) {
                    trigger_error('GARMIN_SSO_USER_CREATION_FAILED');
                }
            } else {
                trigger_error('GARMIN_SSO_REGISTRATION_DISABLED');
            }
        }

        // Manually log the user in by creating a session
        $this->user->session_create($user_id, false, true, true);
        redirect($this->helper->route('phpbb_index'));
    }

    private function create_user($garmin_user_id, $token_data)
    {
        if (!function_exists('user_add')) {
            include($this->phpbb_root_path . 'includes/functions_user.' . $this->php_ext);
        }

        $username = 'garmin_' . substr($garmin_user_id, 0, 20);
        $email = $username . '@' . ($this->config['garmin_email_domain'] ?: 'garmin.local');

        // Check for username collisions
        $sql = 'SELECT user_id FROM ' . USERS_TABLE . " WHERE username_clean = '" . $this->db->sql_escape(utf8_clean_string($username)) . "'";
        $result = $this->db->sql_query($sql);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);
        if ($row) {
            $username .= '_' . uniqid();
        }

        $user_row = [
            'username'              => $username,
            'user_password'         => phpbb_hash(uniqid('', true)),
            'user_email'            => $email,
            'group_id'              => (int) $this->config['new_member_group_default'],
            'user_type'             => USER_NORMAL,
            'user_regdate'          => time(),
            'user_garmin_id'        => $garmin_user_id,
        ];

        $user_id = user_add($user_row);
        if ($user_id === false) {
            return false;
        }
        $this->update_user_tokens($user_id, $token_data);
        return $user_id;
    }

    private function update_user_tokens($user_id, $token_data)
    {
        $sql_arr = [
            'user_garmin_access_token'  => $token_data['access_token'],
            'user_garmin_refresh_token' => $token_data['refresh_token'],
            'user_garmin_token_expires' => time() + $token_data['expires_in'],
        ];
        $sql = 'UPDATE ' . USERS_TABLE . ' SET ' . $this->db->sql_build_array('UPDATE', $sql_arr) . ' WHERE user_id = ' . (int) $user_id;
        $this->db->sql_query($sql);
    }

    private function generate_random_string($length)
    {
        return substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes($length))), 0, $length);
    }

    private function base64url_encode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}