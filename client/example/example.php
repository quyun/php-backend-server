<?php
include_once('../Backend.class.php');

$be = new Backend;
$be->init('127.0.0.1', 13123);

var_dump($be->add('test', __DIR__.'/scripts/test.php', array('writelog'=>TRUE)));
var_dump($be->start('test'));
var_dump($be->read('test'));