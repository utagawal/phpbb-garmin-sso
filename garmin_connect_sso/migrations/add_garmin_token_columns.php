<?php
namespace utagawavtt\garmin_connect_sso\migrations;

class add_garmin_token_columns extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        // Due to API uncertainty, we cannot reliably check.
        // We will rely on the update_data method to fail gracefully.
        return false;
    }

    static public function depends_on()
    {
        return array('\utagawavtt\garmin_connect_sso\migrations\add_garmin_user_id_column');
    }

    public function update_data()
    {
        // We cannot reliably check for the column's existence, so we will
        // attempt to add it and catch any exception if it already exists.
        try {
            return array(
                array('db_tools.add_columns', array(
                    $this->table_prefix . 'users' => array(
                        'user_garmin_access_token'    => array('TEXT', ''),
                        'user_garmin_refresh_token'   => array('TEXT', ''),
                        'user_garmin_token_expires'   => array('BINT', 0),
                    ),
                )),
            );
        } catch (\phpbb\db\exception $e) {
            // Column likely already exists.
            return array();
        }
    }

    public function revert_data()
    {
        return array(
            array('db_tools.drop_columns', array(
                $this->table_prefix . 'users' => array(
                    'user_garmin_access_token',
                    'user_garmin_refresh_token',
                    'user_garmin_token_expires',
                ),
            )),
        );
    }
}