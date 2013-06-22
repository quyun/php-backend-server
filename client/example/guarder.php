<?php
require_once(dirname(__FILE__).'/../Backend.class.php');

$be = new Backend;

print_r($be->add('test', __DIR__.'/scripts/test.php', array('writelog'=>TRUE)));
print_r($be->update('test', array('guard'=>TRUE)));
print_r($be->start('test'));

// 在系统中 kill 该进程，过1分钟后查看到进程自动恢复