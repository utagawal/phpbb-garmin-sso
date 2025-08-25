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

    /**
     * Initiates the Garmin OAuth 2.0 PKCE flow.
     */
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

        // 1. Generate and store code_verifier
        $code_verifier = $this->generate_random_string(64);
        $this->user->session->set('garmin_sso_code_verifier', $code_verifier);

        // 2. Generate code_challenge
        $code_challenge = $this->base64url_encode(hash('sha256', $code_verifier, true));

        // 3. Generate and store state
        $state = $this->generate_random_string(32);
        $this->user->session->set('garmin_sso_state', $state);

        // 4. Construct the authorization URL
        $params = [
            'response_type'         => 'code',
            'client_id'             => $this->config['garmin_sso_client_id'],
            'code_challenge'        => $code_challenge,
            'code_challenge_method' => 'S256',
            'redirect_uri'          => $this->helper->route('utagawavtt_garmin_connect_sso_callback', [], true),
            'state'                 => $state,
        ];

        $auth_url = 'https://connect.garmin.com/oauth2Confirm?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        // 5. Redirect user to Garmin
        redirect($auth_url);
    }

    /**
     * Handles the callback from Garmin after user authorization.
     */
    public function handle_callback()
    {
        // This will be implemented in the next step.
        trigger_error('Callback not yet implemented.');
    }

    /**
     * Generates a cryptographically secure random string.
     */
    private function generate_random_string($length)
    {
        return substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes($length))), 0, $length);
    }

    /**
     * Base64-URL encodes data.
     */
    private function base64url_encode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}