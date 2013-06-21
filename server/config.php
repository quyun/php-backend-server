<?php

// 服务器及端口设置
$server_ip   = '127.0.0.1';
$server_port = 13123;

// 时区设置
$timezone = 'Asia/Shanghai';

// 选择要自动加载的插件，用逗号分隔，*表示所有插件
$autoload_plugins = '*';

// 插件配置
$plugin_settings = array(
    'logcleaner' => array(              // logcleaner插件配置
        'clean_interval' => 3600,       // 清理间隔时间
        'logfile_expire' => 86400*7,    // 日志过期时间
    ),
    'guard' => array(                   // guard插件配置
        'check_interval' => 1,         // 检测间隔时间
    ),
);