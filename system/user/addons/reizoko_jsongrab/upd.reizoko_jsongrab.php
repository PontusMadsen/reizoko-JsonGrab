<?php

use ExpressionEngine\Service\Addon\Installer;

class Reizoko_jsongrab_upd extends Installer
{
    public $version = '1.0.0';
    public $has_cp_backend = 'y';

    public function __construct()
    {
        parent::__construct();

        ee()->load->dbforge();
    }

    public function install()
    {
        $fields = array(
            'import_id' => array('type' => 'int', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true),
            'name' => array('type' => 'varchar', 'constraint' => 100),
            'url' => array('type' => 'varchar', 'constraint' => 500),
            'http_header' => array('type' => 'varchar', 'constraint' => 255, 'null' => true),
            'channel_id' => array('type' => 'int', 'constraint' => 10, 'unsigned' => true),
            'root_path' => array('type' => 'varchar', 'constraint' => 255, 'null' => true),
            'key_json_path' => array('type' => 'varchar', 'constraint' => 255, 'null' => true),
            'key_field' => array('type' => 'varchar', 'constraint' => 100, 'null' => true),
            'title_json_path' => array('type' => 'varchar', 'constraint' => 255, 'null' => true),
            'url_title_json_path' => array('type' => 'varchar', 'constraint' => 255, 'null' => true),
            'status' => array('type' => 'varchar', 'constraint' => 50, 'default' => 'open'),
            'author_id' => array('type' => 'int', 'constraint' => 10, 'unsigned' => true, 'default' => 1),
            'use_basic_auth' => array('type' => 'char', 'constraint' => 1, 'default' => 'n'),
            'basic_auth_user' => array('type' => 'varchar', 'constraint' => 255, 'null' => true),
            'basic_auth_pass' => array('type' => 'varchar', 'constraint' => 255, 'null' => true),
            'field_map' => array('type' => 'text', 'null' => true),
            'last_run_date' => array('type' => 'int', 'constraint' => 10, 'unsigned' => true, 'null' => true),
            'last_run_summary' => array('type' => 'text', 'null' => true),
        );

        ee()->dbforge->add_field($fields);
        ee()->dbforge->add_key('import_id', true);
        ee()->dbforge->create_table('reizoko_jsongrab_imports');

        return parent::install();
    }

    public function uninstall()
    {
        ee()->dbforge->drop_table('reizoko_jsongrab_imports');

        return parent::uninstall();
    }
}
