<?php

/*
 * 进程自动启动插件
 *
 * 该插件会对 START/UPDATE 指令中的 autostart 参数进行处理
 */

class Autostart
{
    private $server = NULL;        // 后台进程服务器对象
    private $data_path = NULL;     // 插件数据保存目录

    public function __construct($server, $setting)
    {
        $this->server = $server;
        $this->data_path = isset($setting['plugins_data_path']) ? $setting['plugins_data_path'] : realpath('./data/plugins');
        $this->data_path .= '/autostart';
        if (!file_exists($this->data_path)) mkdir($this->data_path, 0777);
    }

    public function on_server_inited()
    {
        $jobs = $this->server->config->getall();
        if (!$jobs) return FALSE;

        foreach ($jobs as $jobname=>$setting)
        {
            if (isset($setting['autostart']) && $setting['autostart'])
            {
                $this->server->server_echo("[autostart] starting \"{$jobname}\"...");
                $this->server->command_start(array('jobname'=>$jobname));
            }
        }
    }

}