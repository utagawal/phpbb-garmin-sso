<?php
namespace utagawavtt\garmin_connect_sso\controller;

use Symfony\Component\HttpFoundation\RedirectResponse;

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
        
        // Charger la langue si nécessaire
        $this->user->add_lang_ext('utagawavtt/garmin_connect_sso', 'common');
    }

    /**
     * Initier le processus de connexion OAuth avec Garmin
     */
    public function login()
    {
        // Debug - Afficher l'appel pour vérifier que la route fonctionne
        error_log('Garmin Login Controller appelé');
        
        // Vérifications de configuration
        if (empty($this->config['garmin_sso_enabled'])) {
            trigger_error('L\'authentification Garmin SSO est désactivée.', E_USER_ERROR);
        }
        
        if (empty($this->config['garmin_sso_client_id']) || empty($this->config['garmin_sso_client_secret'])) {
            trigger_error('Configuration Garmin SSO incomplète. Veuillez configurer le client ID et secret.', E_USER_ERROR);
        }
        
        // Si déjà connecté, rediriger vers l'index
        if ($this->user->data['is_registered'] && !$this->user->data['is_bot']) {
            return new RedirectResponse($this->helper->route('phpbb_index'));
        }

        // Générer PKCE code verifier et challenge
        $code_verifier = $this->generate_pkce_verifier();
        $_SESSION['garmin_code_verifier'] = $code_verifier;
        
        $code_challenge = $this->generate_pkce_challenge($code_verifier);

        // Générer un state pour prévenir CSRF
        $state = $this->generate_random_string(32);
        $_SESSION['garmin_state'] = $state;
        
        // Stocker l'URL de redirection finale
        $redirect = $this->request->variable('redirect', '');
        if ($redirect) {
            $_SESSION['garmin_redirect'] = $redirect;
        }

        // Construire l'URL d'autorisation Garmin
        $params = [
            'response_type'         => 'code',
            'client_id'             => $this->config['garmin_sso_client_id'],
            'code_challenge'        => $code_challenge,
            'code_challenge_method' => 'S256',
            'redirect_uri'          => $this->helper->route('utagawavtt_garmin_connect_sso_callback', [], true, null, \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL),
            'state'                 => $state,
        ];
        
        $auth_url = 'https://connect.garmin.com/oauth2Confirm?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        
        return new RedirectResponse($auth_url);
    }

    /**
     * Gérer le callback OAuth de Garmin
     */
    public function handle_callback()
    {
        $code = $this->request->variable('code', '');
        $state = $this->request->variable('state', '');
        $error = $this->request->variable('error', '');
        
        // Gérer les erreurs OAuth
        if ($error) {
            $error_description = $this->request->variable('error_description', '');
            trigger_error('Erreur Garmin OAuth : ' . $error . ' - ' . $error_description, E_USER_ERROR);
        }
        
        // Vérifier le state
        $session_state = isset($_SESSION['garmin_state']) ? $_SESSION['garmin_state'] : '';
        if (empty($state) || $state !== $session_state) {
            trigger_error('État de sécurité invalide. Veuillez réessayer.', E_USER_ERROR);
        }
        
        // Récupérer le code verifier
        $code_verifier = isset($_SESSION['garmin_code_verifier']) ? $_SESSION['garmin_code_verifier'] : '';
        if (empty($code_verifier)) {
            trigger_error('Code verifier manquant. Veuillez réessayer.', E_USER_ERROR);
        }
        
        // Nettoyer la session
        unset($_SESSION['garmin_state']);
        unset($_SESSION['garmin_code_verifier']);
        
        if (empty($code)) {
            trigger_error('Code d\'autorisation manquant.', E_USER_ERROR);
        }

        // Échanger le code contre un token
        $token_data = $this->exchange_code_for_token($code, $code_verifier);
        
        // Récupérer les informations utilisateur et connecter
        return $this->fetch_user_and_login($token_data);
    }

    /**
     * Échanger le code d'autorisation contre un access token
     */
    private function exchange_code_for_token($code, $code_verifier)
    {
        $token_url = 'https://diauth.garmin.com/di-oauth2-service/oauth/token';
        
        $post_data = [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => $this->helper->route('utagawavtt_garmin_connect_sso_callback', [], true, null, \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL),
            'client_id'     => $this->config['garmin_sso_client_id'],
            'client_secret' => $this->config['garmin_sso_client_secret'],
            'code_verifier' => $code_verifier,
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $token_url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($post_data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            trigger_error('Erreur cURL lors de l\'échange de token : ' . $curl_error, E_USER_ERROR);
        }
        
        if ($http_code !== 200) {
            trigger_error('Erreur HTTP ' . $http_code . ' lors de l\'échange de token : ' . $response, E_USER_ERROR);
        }

        $token_data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            trigger_error('Réponse JSON invalide : ' . json_last_error_msg(), E_USER_ERROR);
        }
        
        if (empty($token_data['access_token'])) {
            trigger_error('Access token manquant dans la réponse.', E_USER_ERROR);
        }

        return $token_data;
    }

    /**
     * Récupérer l'ID utilisateur Garmin et connecter/créer l'utilisateur
     */
    private function fetch_user_and_login($token_data)
    {
        $access_token = $token_data['access_token'];
        
        // Récupérer l'ID utilisateur Garmin
        $user_id_url = 'https://apis.garmin.com/wellness-api/rest/user/id';

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $user_id_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $access_token],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $http_code !== 200) {
            trigger_error('Erreur lors de la récupération de l\'ID utilisateur Garmin (HTTP ' . $http_code . ')', E_USER_ERROR);
        }

        $user_id_data = json_decode($response, true);
        $garmin_user_id = isset($user_id_data['userId']) ? $user_id_data['userId'] : null;

        if (empty($garmin_user_id)) {
            trigger_error('ID utilisateur Garmin non trouvé.', E_USER_ERROR);
        }

        // Chercher un utilisateur existant avec cet ID Garmin
        $sql = 'SELECT user_id, username, user_password, user_passchg, user_email, user_type
                FROM ' . USERS_TABLE . "
                WHERE user_garmin_id = '" . $this->db->sql_escape($garmin_user_id) . "'";
        $result = $this->db->sql_query($sql);
        $user_row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        if ($user_row) {
            // Utilisateur existant - mise à jour des tokens
            $user_id = (int) $user_row['user_id'];
            $this->update_user_tokens($user_id, $token_data);
        } else {
            // Nouvel utilisateur
            if (!$this->config['garmin_auto_register']) {
                trigger_error('L\'inscription automatique est désactivée. Veuillez vous inscrire manuellement d\'abord.', E_USER_ERROR);
            }
            
            $user_id = $this->create_user($garmin_user_id, $token_data);
            if ($user_id === false) {
                trigger_error('Impossible de créer l\'utilisateur.', E_USER_ERROR);
            }
            
            // Récupérer les données du nouvel utilisateur
            $sql = 'SELECT user_id, username, user_password, user_passchg, user_email, user_type
                    FROM ' . USERS_TABLE . '
                    WHERE user_id = ' . (int) $user_id;
            $result = $this->db->sql_query($sql);
            $user_row = $this->db->sql_fetchrow($result);
            $this->db->sql_freeresult($result);
        }

        // Connexion de l'utilisateur
        $result = $this->user->session_create($user_id, false, true, true);
        
        if ($result !== true) {
            trigger_error('Impossible de créer la session utilisateur.', E_USER_ERROR);
        }

        // Redirection
        $redirect = isset($_SESSION['garmin_redirect']) ? $_SESSION['garmin_redirect'] : '';
        unset($_SESSION['garmin_redirect']);
        
        if ($redirect) {
            $redirect_url = append_sid($this->phpbb_root_path . $redirect);
        } else {
            $redirect_url = $this->helper->route('phpbb_index');
        }
        
        return new RedirectResponse($redirect_url);
    }

    /**
     * Créer un nouvel utilisateur phpBB
     */
    private function create_user($garmin_user_id, $token_data)
    {
        if (!function_exists('user_add')) {
            include($this->phpbb_root_path . 'includes/functions_user.' . $this->php_ext);
        }

        // Générer un nom d'utilisateur unique
        $base_username = 'garmin_' . substr($garmin_user_id, 0, 8);
        $username = $this->get_unique_username($base_username);
        
        // Email fictif
        $email = $username . '@' . ($this->config['garmin_email_domain'] ?: 'garmin.local');

        // Données du nouvel utilisateur
        $user_row = [
            'username'              => $username,
            'user_password'         => phpbb_hash(bin2hex(random_bytes(16))),
            'user_email'            => $email,
            'group_id'              => (int) $this->config['new_member_group_default'],
            'user_type'             => USER_NORMAL,
            'user_regdate'          => time(),
            'user_garmin_id'        => $garmin_user_id,
            'user_lang'             => $this->config['default_lang'],
            'user_timezone'         => $this->config['board_timezone'],
            'user_garmin_access_token'  => $token_data['access_token'],
            'user_garmin_refresh_token' => isset($token_data['refresh_token']) ? $token_data['refresh_token'] : '',
            'user_garmin_token_expires' => time() + (isset($token_data['expires_in']) ? $token_data['expires_in'] : 86400),
        ];

        $user_id = user_add($user_row);
        
        return $user_id;
    }

    /**
     * Obtenir un nom d'utilisateur unique
     */
    private function get_unique_username($base_username)
    {
        $username = $base_username;
        $i = 1;
        
        while (true) {
            $sql = 'SELECT user_id FROM ' . USERS_TABLE . "
                    WHERE username_clean = '" . $this->db->sql_escape(utf8_clean_string($username)) . "'";
            $result = $this->db->sql_query($sql);
            $row = $this->db->sql_fetchrow($result);
            $this->db->sql_freeresult($result);
            
            if (!$row) {
                break;
            }
            
            $username = $base_username . '_' . $i;
            $i++;
        }
        
        return $username;
    }

    /**
     * Mettre à jour les tokens de l'utilisateur
     */
    private function update_user_tokens($user_id, $token_data)
    {
        $sql_arr = [
            'user_garmin_access_token'  => $token_data['access_token'],
            'user_garmin_refresh_token' => isset($token_data['refresh_token']) ? $token_data['refresh_token'] : '',
            'user_garmin_token_expires' => time() + (isset($token_data['expires_in']) ? $token_data['expires_in'] : 86400),
        ];
        
        $sql = 'UPDATE ' . USERS_TABLE . ' 
                SET ' . $this->db->sql_build_array('UPDATE', $sql_arr) . '
                WHERE user_id = ' . (int) $user_id;
        $this->db->sql_query($sql);
    }

    /**
     * Générer un code verifier PKCE
     */
    private function generate_pkce_verifier()
    {
        // Générer 32 octets aléatoires et encoder en base64url
        $random_bytes = random_bytes(32);
        return $this->base64url_encode($random_bytes);
    }
    
    /**
     * Générer un challenge PKCE à partir du verifier
     */
    private function generate_pkce_challenge($verifier)
    {
        // SHA256 du verifier, encodé en base64url
        return $this->base64url_encode(hash('sha256', $verifier, true));
    }

    /**
     * Générer une chaîne aléatoire
     */
    private function generate_random_string($length)
    {
        return substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes($length))), 0, $length);
    }

    /**
     * Encoder en base64url (RFC 4648)
     */
    private function base64url_encode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}