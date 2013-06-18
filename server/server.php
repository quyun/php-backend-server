<?php
if (php_sapi_name() != 'cli') die('Server must run under cli mode!');

$pid = pcntl_fork();

if ($pid == -1)
{
    exit('pcntl_fork error!');
}
else if ($pid)    // 父进程
{
    exit;
}

// 子进程
posix_setsid();
include_once('main.php');