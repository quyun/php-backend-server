<?php
if (php_sapi_name() != 'cli') exit;

require_once(dirname(__FILE__) . '/config.php');
require_once(dirname(__FILE__) . '/lib/BackendServer.class.php');

date_default_timezone_set($timezone);

$server = new BackendServer(array(
    'server_ip'   => $server_ip,
    'server_port' => $server_port,
));
$server->load_plugins();
$server->run();