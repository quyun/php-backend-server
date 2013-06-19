<?php
require_once(dirname(__FILE__).'/../Backend.class.php');

$be = new Backend;

print_r($be->add('test', __DIR__.'/scripts/test.php'));
print_r($be->update('test', array('autostart'=>TRUE)));

/* restart your server and you will see the result below
Array
(
    [code] => OK
    [data] => UP
)
*/
print_r($be->status('test'));