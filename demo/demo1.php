<?php
return array(
    'fields' =>
        array(
            'uname' =>
                array(
                    'name' => 'uname',
                    'type' => 'char(30)',
                    'notnull' => false,
                    'default' => NULL,
                    'primary' => false,
                    'autoinc' => false,
                    'version' => '1.0',
                ),
            'password' =>
                array(
                    'name' => 'password',
                    'type' => 'char(32)',
                    'notnull' => false,
                    'default' => NULL,
                    'primary' => false,
                    'autoinc' => false,
                    'version' => '1.0',
                ),
            'start_status' =>
                array(
                    'name' => 'start_status',
                    'type' => 'enum(\'Y\',\'N\')',
                    'notnull' => false,
                    'default' => 'Y',
                    'primary' => false,
                    'autoinc' => false,
                    'version' => '1.0',
                ),
            'login_status' =>
                array(
                    'name' => 'login_status',
                    'type' => 'enum(\'Y\',\'N\')',
                    'notnull' => false,
                    'default' => 'N',
                    'primary' => false,
                    'autoinc' => false,
                    'version' => '1.1',
                ),
            'age' =>
                array(
                    'name' => 'age',
                    'type' => 'int(2)',
                    'notnull' => false,
                    'default' => NULL,
                    'primary' => false,
                    'autoinc' => false,
                    'version' => '1.0',
                ),
            'sex' =>
                array(
                    'name' => 'sex',
                    'type' => 'enum(\'M\',\'W\')',
                    'notnull' => false,
                    'default' => 'M',
                    'primary' => false,
                    'autoinc' => false,
                    'version' => '1.0',
                ),
            'info' =>
                array(
                    'name' => 'info',
                    'type' => 'longtext',
                    'notnull' => true,
                    'default' => NULL,
                    'primary' => false,
                    'autoinc' => false,
                    'version' => '1.1',
                ),
            'savetime' =>
                array(
                    'name' => 'savetime',
                    'type' => 'int(10)',
                    'notnull' => true,
                    'default' => NULL,
                    'primary' => false,
                    'autoinc' => false,
                    'version' => '1.1',
                ),
        ),
    'index' =>
        array(
            'idx_demo_uname'=>
                array(
                    'name'=>'idx_demo_uname',
                    'type' => 'unique',
                    'fields'=> 'uname',
                    'method'=>'',
                    'version'=>'1.0',
            ),
            'idx_demo_savetime'=>
                array(
                    'name'=>'idx_demo_savetime',
                    'type' => '',
                    'fields'=> 'savetime',
                    'method'=>'',
                    'version'=>'1.3',
                ),
            'idx_demo_status'=>
                array(
                    'name'=>'idx_demo_status',
                    'type' => '',
                    'fields'=> array(
                        'start_status',
                        'login_status',
                    ),
                    'method'=>'',
                    'version'=>'1.3',
                ),
        ),
    'version' => '1.5',
    'engine' => 'innodb',
    'comment' => '`admin_account`',
);