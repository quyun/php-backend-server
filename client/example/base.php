<?php
require_once(dirname(__FILE__).'/../Backend.class.php');

$be = new Backend;
$be->init('127.0.0.1', 13123);

print_r($be->add('test', __DIR__.'/scripts/test.php', array('writelog'=>TRUE)));
print_r($be->start('test'));
print_r($be->read('test'));