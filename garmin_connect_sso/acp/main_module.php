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

            trigger_error('Configuration mise à jour avec succès !' . adm_back_link($this->u_action));
        }

        $template->assign_vars(array(
            'GARMIN_SSO_ENABLED' => $config['garmin_sso_enabled'],
            'GARMIN_AUTO_REGISTER' => $config['garmin_auto_register'],
            'GARMIN_EMAIL_DOMAIN' => $config['garmin_email_domain'],
            'U_ACTION' => $this->u_action,
            'S_FORM_TOKEN' => add_form_key('acp_garmin_sso'),
        ));
    }
}