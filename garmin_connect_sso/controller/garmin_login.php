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
            trigger_error('Extension Garmin Connect SSO désactivée');
        }

        if ($this->user->data['is_registered']) {
            redirect(append_sid(generate_board_url() . '/index.' . $this->php_ext));
        }

        if ($this->request->is_set_post('garmin_login')) {
            if (!check_form_key('garmin_login')) {
                trigger_error('FORM_INVALID');
            }

            $username = $this->request->variable('garmin_username', '', true);
            $password = $this->request->variable('garmin_password', '', true);

            if ($username && $password) {
                $result = $this->process_garmin_login($username, $password);
                
                if ($result['success']) {
                    $this->login_user($result['user_data']);
                    
                    $redirect_url = $this->request->variable('redirect', generate_board_url() . '/index.' . $this->php_ext);
                    redirect($redirect_url);
                } else {
                    $this->template->assign_var('GARMIN_ERROR', $result['error']);
                }
            } else {
                $this->template->assign_var('GARMIN_ERROR', 'Veuillez saisir vos identifiants Garmin Connect');
            }
        }

        $this->template->assign_vars(array(
            'S_GARMIN_LOGIN' => true,
            'U_GARMIN_ACTION' => $this->helper->route('utagawavtt_garmin_connect_sso_login'),
            'GARMIN_USERNAME' => $this->request->variable('garmin_username', '', true),
            'S_FORM_TOKEN' => add_form_key('garmin_login'),
            'U_BACK_LOGIN' => append_sid(generate_board_url() . '/ucp.' . $this->php_ext, 'mode=login'),
        ));

        return $this->helper->render('garmin_login_form.html', 'Connexion Garmin Connect');
    }

    private function process_garmin_login($username, $password)
    {
        $user_data = $this->get_user_data($username);
        
        if ($user_data) {
            if ($this->authenticate_with_garmin($username, $password)) {
                return array('success' => true, 'user_data' => $user_data);
            } else {
                return array('success' => false, 'error' => 'Identifiants Garmin Connect incorrects');
            }
        } else {
            if ($this->config['garmin_auto_register']) {
                if ($this->authenticate_with_garmin($username, $password)) {
                    $garmin_user_data = $this->get_garmin_user_info($username);
                    $user_id = $this->create_user_from_garmin($garmin_user_data);
                    
                    if ($user_id) {
                        $user_data = $this->get_user_data($username);
                        return array('success' => true, 'user_data' => $user_data);
                    }
                }
                return array('success' => false, 'error' => 'Impossible de créer le compte avec ces identifiants Garmin');
            } else {
                return array('success' => false, 'error' => 'Aucun compte trouvé. La création automatique est désactivée.');
            }
        }
    }

    private function authenticate_with_garmin($username, $password)
    {
        $login_url = 'https://sso.garmin.com/sso/signin';
        $auth_url = 'https://connect.garmin.com/signin/';
        
        try {
            $ch = curl_init();
            $cookie_file = sys_get_temp_dir() . '/garmin_cookies_' . md5($username . time()) . '.txt';
            
            curl_setopt_array($ch, array(
                CURLOPT_COOKIEJAR => $cookie_file,
                CURLOPT_COOKIEFILE => $cookie_file,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; phpBB-Garmin-SSO)',
            ));
            
            curl_setopt($ch, CURLOPT_URL, $auth_url);
            $response = curl_exec($ch);
            
            if (curl_error($ch)) {
                curl_close($ch);
                @unlink($cookie_file);
                return false;
            }
            
            preg_match('/<input[^>]*name="_eventId"[^>]*value="([^"]*)">/i', $response, $matches);
            $event_id = isset($matches[1]) ? $matches[1] : 'submit';
            
            preg_match('/<input[^>]*name="lt"[^>]*value="([^"]*)">/i', $response, $matches);
            $lt = isset($matches[1]) ? $matches[1] : '';
            
            $post_data = http_build_query(array(
                'username' => $username,
                'password' => $password,
                '_eventId' => $event_id,
                'lt' => $lt,
                'displayNameRequired' => 'false'
            ));
            
            curl_setopt_array($ch, array(
                CURLOPT_URL => $login_url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $post_data,
                CURLOPT_HEADER => true,
            ));
            
            $login_response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            curl_close($ch);
            @unlink($cookie_file);
            
            if ($http_code >= 200 && $http_code < 400) {
                return (strpos($login_response, 'error') === false && 
                        strpos($login_response, 'invalid') === false &&
                        (strpos($login_response, 'Location:') !== false || strpos($login_response, 'connect.garmin.com') !== false));
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log('Garmin SSO Error: ' . $e->getMessage());
            return false;
        }
    }

    private function get_garmin_user_info($username)
    {
        return array(
            'username' => $username,
            'email' => $this->generate_email($username),
            'display_name' => $username,
        );
    }

    private function generate_email($username)
    {
        $domain = !empty($this->config['garmin_email_domain']) ? $this->config['garmin_email_domain'] : 'garmin.local';
        return $username . '@' . $domain;
    }

    private function create_user_from_garmin($garmin_data)
    {
        if (!function_exists('user_add')) {
            include($this->phpbb_root_path . 'includes/functions_user.' . $this->php_ext);
        }

        $user_row = array(
            'username' => $garmin_data['username'],
            'user_password' => phpbb_hash('garmin_' . time()),
            'user_email' => $garmin_data['email'],
            'group_id' => $this->config['new_member_group_default'] ?: 2,
            'user_timezone' => $this->config['board_timezone'],
            'user_lang' => $this->config['default_lang'],
            'user_type' => USER_NORMAL,
            'user_actkey' => '',
            'user_ip' => $this->user->ip,
            'user_regdate' => time(),
            'user_inactive_reason' => 0,
            'user_inactive_time' => 0,
        );

        return user_add($user_row);
    }

    private function get_user_data($username)
    {
        $sql = 'SELECT * FROM ' . USERS_TABLE . "
            WHERE username_clean = '" . $this->db->sql_escape(utf8_clean_string($username)) . "'";
        $result = $this->db->sql_query($sql);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        return $row;
    }

    private function login_user($user_data)
    {
        if (!function_exists('user_login')) {
            include($this->phpbb_root_path . 'includes/functions_user.' . $this->php_ext);
        }

        // Connexion manuelle de l'utilisateur
        $this->user->session_kill();
        $this->user->session_begin();
        
        // Mise à jour des données utilisateur
        $this->user->data = array_merge($this->user->data, $user_data);
        $this->user->data['is_registered'] = true;
        $this->user->data['is_bot'] = false;
        
        // Authentification
        $this->auth->acl($this->user->data);
        
        // Mise à jour de la session
        $sql = 'UPDATE ' . SESSIONS_TABLE . '
            SET session_user_id = ' . (int) $user_data['user_id'] . '
            WHERE session_id = "' . $this->db->sql_escape($this->user->session_id) . '"';
        $this->db->sql_query($sql);

        return true;
    }
}