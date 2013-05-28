<?php

// 服务器及端口设置
$server_ip   = '127.0.0.1';
$server_port = 13123;

// 时区设置
date_default_timezone_set('Asia/Shanghai');

//日志路径设置
$logpath = '/var/www/php-backend/data/log/';

//进程配置文件路径
$backendinfo_path = '/var/www/php-backend/data/backendinfo';

//服务进程路径
$server_path = '/var/www/php-backend/server/serverbk.php';


$hooks = array(
	'hooks/autostart.php',
);