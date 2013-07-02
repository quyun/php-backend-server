<?php
require_once(dirname(__FILE__).'/../Backend.class.php');

$be = new Backend;

$result = $be->scheduler_add('test', array(
    'enable' => TRUE,
    'condition' => array(
        'U' => 300, // 每5分钟执行一次
    ),
));
print_r($result);

$result = $be->scheduler_get('test');
print_r($result);

$result = $be->scheduler_getlog('test');
print_r($result);