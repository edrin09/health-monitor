<?php

return [
    'controller_namespace' => 'app\admin\controller\\',

    'super_admin_id'       => 1,

    'admin_alias_name'     => 'admin',
    'admin_domain_status'  => false,
    'admin_domain'         => '',

    'no_login_controller'  => [
        'login',
    ],

    'no_login_node'        => [
        'login/index',
        'login/out',
    ],

    'no_auth_controller'   => [
        'ajax',
        'login',
        'index',
    ],

    'no_auth_node'         => [
        'login/index',
        'login/out',
    ],

    'upload_types'         => [
        'local' => '本地存储',
        'oss'   => '阿里云oss',
        'cos'   => '腾讯云cos',
    ],

    'editor_types'         => [
        'wangEditor' => 'wangEditor(推荐使用)',
    ],
];