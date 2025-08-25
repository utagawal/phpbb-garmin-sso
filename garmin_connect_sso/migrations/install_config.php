<?php
/**
 * Migration corrigée - Extension Garmin Connect SSO
 * Sans modules ACP pour éviter les erreurs
 */

namespace utagawavtt\garmin_connect_sso\migrations;

class install_config extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['garmin_sso_enabled']);
    }

    static public function depends_on()
    {
        return array('\phpbb\db\migration\data\v320\v320');
    }

    public function update_data()
    {
        return array(
            // Configuration seulement - pas de modules ACP
            array('config.add', array('garmin_sso_enabled', 1)),
            array('config.add', array('garmin_auto_register', 1)),
            array('config.add', array('garmin_email_domain', 'garmin.local')),
        );
    }

    public function revert_data()
    {
        return array(
            // Suppression de la configuration lors de la désinstallation
            array('config.remove', array('garmin_sso_enabled')),
            array('config.remove', array('garmin_auto_register')),
            array('config.remove', array('garmin_email_domain')),
        );
    }
}