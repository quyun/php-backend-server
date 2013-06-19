<?php
require_once(dirname(__FILE__).'/../Backend.class.php');

$be = new Backend;

// 身份验证默认关闭，开启身份验证
print_r($be->auth_setenable(TRUE));

/*
array(2) {
  ["code"]=>
  string(2) "OK"
  ["data"]=>
  bool(true)
}
*/
var_dump($be->auth_getenable());

// 还未建用户时，身份验证会被略过
// 创建用户
print_r($be->auth_add('hilyjiang', 'quyun.com', '*'));

/*
Array
(
    [code] => OK
    [data] => Array
        (
            [hilyjiang] => Array
                (
                    [password] => quyun.com
                    [privileges] => *
                )

        )

)
*/
print_r($be->auth_getall());

/*
Array
(
    [code] => DENIED
)
*/
print_r($be->auth_update('hilyjiang', array(
    'password' => 'newpassword',
)));

/*
Array
(
    [code] => OK
)
*/
print_r($be->auth_update('hilyjiang', array(
    'password' => 'newpassword',
    'auth' => array(
        'username' => 'hilyjiang',
        'password' => 'quyun.com',
    ),
)));