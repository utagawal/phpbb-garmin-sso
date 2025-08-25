<?php
namespace utagawavtt\garmin_connect_sso\acp;

class main_info
{
    public function module()
    {
        return array(
            'filename' => '\\utagawavtt\\garmin_connect_sso\\acp\\main_module',
            'title' => 'Garmin Connect SSO',
            'modes' => array(
                'settings' => array(
                    'title' => 'Configuration',
                    'auth' => 'ext_utagawavtt/garmin_connect_sso && acl_a_extensions',
                    'cat' => array('ACP_EXTENSION_MANAGEMENT')
                ),
            ),
        );
    }
}