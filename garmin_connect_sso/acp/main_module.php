<?php
namespace utagawavtt\garmin_connect_sso\acp;

class main_module
{
    public $page_title;
    public $tpl_name;
    public $u_action;

    public function main($id, $mode)
    {
        global $config, $request, $template, $user;

        $this->tpl_name = 'acp_garmin_sso';
        $this->page_title = 'Configuration Garmin Connect SSO';

        $submit = $request->is_set_post('submit');

        if ($submit) {
            if (!check_form_key('acp_garmin_sso')) {
                trigger_error('FORM_INVALID' . adm_back_link($this->u_action), E_USER_WARNING);
            }

            $config->set('garmin_sso_enabled', $request->variable('garmin_sso_enabled', 0));
            $config->set('garmin_auto_register', $request->variable('garmin_auto_register', 0));
            $config->set('garmin_email_domain', $request->variable('garmin_email_domain', ''));
            $config->set('garmin_sso_client_id', $request->variable('garmin_sso_client_id', ''));

            $new_secret = $request->variable('garmin_sso_client_secret', '');
            if ($new_secret && $new_secret !== '********') {
                $config->set('garmin_sso_client_secret', $new_secret);
            }

            trigger_error('Configuration mise à jour avec succès !' . adm_back_link($this->u_action));
        }

        $template->assign_vars(array(
            'GARMIN_SSO_ENABLED'        => isset($config['garmin_sso_enabled']) ? $config['garmin_sso_enabled'] : 0,
            'GARMIN_AUTO_REGISTER'      => isset($config['garmin_auto_register']) ? $config['garmin_auto_register'] : 0,
            'GARMIN_EMAIL_DOMAIN'       => isset($config['garmin_email_domain']) ? $config['garmin_email_domain'] : '',
            'GARMIN_SSO_CLIENT_ID'      => isset($config['garmin_sso_client_id']) ? $config['garmin_sso_client_id'] : '',
            'GARMIN_SSO_CLIENT_SECRET_MASK'  => !empty($config['garmin_sso_client_secret']) ? '********' : '',

            'U_ACTION' => $this->u_action,
            'S_FORM_TOKEN' => add_form_key('acp_garmin_sso'),
        ));
    }
}