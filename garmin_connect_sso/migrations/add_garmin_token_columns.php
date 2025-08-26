<?php
namespace utagawavtt\garmin_connect_sso\migrations;

class add_garmin_token_columns extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        // The API for checking for a column's existence has proven to be unreliable.
        // Returning false forces the update to run, and we assume the db_tools
        // method is idempotent and handles cases where the column already exists.
        return false;
    }

    static public function depends_on()
    {
        return array('\utagawavtt\garmin_connect_sso\migrations\add_garmin_user_id_column');
    }

    public function update_data()
    {
        return array(
            array('callable', array(
                array($this->db_tools, 'add_columns'),
                array(
                    $this->table_prefix . 'users' => array(
                        'user_garmin_access_token'    => array('TEXT', ''),
                        'user_garmin_refresh_token'   => array('TEXT', ''),
                        'user_garmin_token_expires'   => array('BINT', 0),
                    ),
                ),
            )),
        );
    }

    public function revert_data()
    {
        return array(
            array('callable', array(
                array($this->db_tools, 'drop_columns'),
                array(
                    $this->table_prefix . 'users' => array(
                        'user_garmin_access_token',
                        'user_garmin_refresh_token',
                        'user_garmin_token_expires',
                    ),
                ),
            )),
        );
    }
}
