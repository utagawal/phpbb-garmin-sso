<?php
namespace utagawavtt\garmin_connect_sso\migrations;

class add_garmin_user_id_column extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return $this->db_tools->sql_column_exists($this->table_prefix . 'users', 'user_garmin_id');
    }

    static public function depends_on()
    {
        return array('\utagawavtt\garmin_connect_sso\migrations\install_config');
    }

    public function update_schema()
    {
        return array(
            'add_columns' => array(
                $this->table_prefix . 'users' => array(
                    'user_garmin_id' => array('VCHAR:255', ''),
                ),
            ),
        );
    }

    public function revert_schema()
    {
        return array(
            'drop_columns' => array(
                $this->table_prefix . 'users' => array(
                    'user_garmin_id',
                ),
            ),
        );
    }
}