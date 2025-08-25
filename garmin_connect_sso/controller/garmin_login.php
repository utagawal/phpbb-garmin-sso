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
        if (empty($this->config['garmin_sso_client_id'])) {
            trigger_error('GARMIN_SSO_CLIENT_ID_NOT_CONFIGURED');
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
        curl_setopt($ch, CURLOPT_URL, $token_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

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

        // The rest of the logic (user lookup, creation, login) will be in the next step.
        // For now, we pass the tokens to the next function.
        return $this->fetch_user_and_login($token_data);
    }

    public function fetch_user_and_login($token_data)
    {
        // This will be implemented in the next step.
        echo "Token received successfully! User login logic will be here.";
        exit;
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