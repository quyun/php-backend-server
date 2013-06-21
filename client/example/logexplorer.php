<?php
require_once(dirname(__FILE__).'/../Backend.class.php');

$be = new Backend;

$result = $be->logexplorer_listdir('test');
$logdirs = $result['data'];
foreach ($logdirs as $logdir)
{
    echo "* $logdir\n";
    $result = $be->logexplorer_listfile('test', $logdir);
    $logfiles = $result['data'];
    foreach ($logfiles as $logfile)
    {
        echo "  - $logfile\n";
    }
}