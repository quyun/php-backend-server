<?php

/*
 * 进程自动启动插件
 *
 * 该插件会对 START/UPDATE 指令中的 autostart 参数进行处理
 */

class Autostart
{
    private $server = NULL;        // 后台进程服务器对象

    public function __construct($server, $setting)
    {
        $this->server = $server;
    }

    public function on_server_inited()
    {
        $jobs = $this->server->config->getall();
        if (!$jobs) return TRUE;

        foreach ($jobs as $jobname=>$setting)
        {
            if (isset($setting['autostart']) && $setting['autostart'])
            {
                $this->server->server_echo("[autostart] starting \"{$jobname}\"...");
                $this->server->command_start(array('jobname'=>$jobname, 'newline'=>FALSE));
            }
        }

        return TRUE;
    }

}