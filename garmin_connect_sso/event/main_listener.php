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
            'core.page_header' => 'add_garmin_template_vars',
        );
    }

    public function add_garmin_template_vars($event)
    {
        if (!$this->user->data['is_registered'] && !empty($this->config['garmin_sso_enabled'])) {
            $this->template->assign_vars(array(
                'S_GARMIN_SSO_ENABLED' => true,
                'U_GARMIN_LOGIN' => $this->helper->route('utagawavtt_garmin_connect_sso_login'),
                'L_LOGIN_GARMIN' => 'Connexion Garmin Connect',
            ));
        }
    }
}