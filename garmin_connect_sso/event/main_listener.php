<?php
namespace utagawavtt\garmin_connect_sso\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class main_listener implements EventSubscriberInterface
{
    protected $config;
    protected $template;
    protected $user;
    protected $helper;

    public function __construct(\phpbb\config\config $config, \phpbb\template\template $template,
        \phpbb\user $user, \phpbb\controller\helper $helper)
    {
        $this->config = $config;
        $this->template = $template;
        $this->user = $user;
        $this->helper = $helper;
    }

    public static function getSubscribedEvents()
    {
        return array(
            'core.page_header'          => 'add_garmin_template_vars',
            'core.ucp_login_link_template_after' => 'add_garmin_login_template',
        );
    }

    /**
     * Ajouter les variables template globales
     */
    public function add_garmin_template_vars($event)
    {
        // Toujours définir les variables, même si désactivé (pour éviter les erreurs de template)
        $is_enabled = !empty($this->config['garmin_sso_enabled']);
        $is_configured = !empty($this->config['garmin_sso_client_id']) && !empty($this->config['garmin_sso_client_secret']);
        
        $this->template->assign_vars(array(
            'S_GARMIN_SSO_ENABLED' => ($is_enabled && $is_configured && !$this->user->data['is_registered']),
            'U_GARMIN_LOGIN' => $this->helper->route('utagawavtt_garmin_connect_sso_login'),
            'L_LOGIN_GARMIN' => 'Se connecter avec Garmin',
            'L_LOGIN_GARMIN_EXPLAIN' => 'Utilisez votre compte Garmin Connect existant',
        ));
    }
    
    /**
     * Ajouter le template de connexion Garmin sur la page UCP
     */
    public function add_garmin_login_template($event)
    {
        if (!empty($this->config['garmin_sso_enabled']) && !$this->user->data['is_registered']) {
            $this->template->assign_vars(array(
                'S_GARMIN_LOGIN_AVAILABLE' => true,
            ));
        }
    }
}