<?php
require_once(dirname(__FILE__) . '/config.php');
require_once(dirname(__FILE__) . '/ShareMemory.class.php');

// 初始化共享内存
$shm = new SharedMemory('shm_key_of_backend_server_'.$server_ip.'_'.$server_port);
if (!$shm->attach())
{
	server_echo("shm attach() failed.\n");
	exit;
}
$rs = $shm->get_var('processes');
var_dump($rs);
exit;


$fp = fopen('/var/log/test1.log', 'r');
var_dump($fp);

$rs = $shm->put_var('fp', $fp);

$tmp = $shm->get_var('fp');
var_dump($tmp);