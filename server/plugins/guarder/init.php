<?php

/*
 * 进程守护插件
 *
 * 该插件在检测到进程状态因为非人为因素变为DOWN时，立即启动该进程
 */

class GuarderPlugin
{
    private $server = NULL;                 // 后台进程服务器对象
    private $gurading_jobnames = array();   // 受监控的进程名称列表，以进程名为键，值固定为TRUE
    private $check_interval = FALSE;        // 检测间隔时间

    public function __construct($server, $setting)
    {
        $this->server = $server;
        $this->check_interval = isset($setting['check_interval']) ? $setting['check_interval'] : 60;
    }

    public function on_server_inited()
    {
        $this->_detect_guard_jobs();

        // 新建子进程用于定时检测进程状态
        $pid = pcntl_fork();
        if ($pid == -1)
        {
            $this->server_echo('[guarder] init failed.');
            return FALSE;
        }
        else if ($pid)    // 父进程
        {
            // 不等待子进程，直接返回
            //pcntl_waitpid($pid, $status);
            return TRUE;
        }

        while (TRUE)
        {
            $this->_check();
            sleep($this->check_interval);
        } 

        return TRUE;
    }

    public function on_command_received($cmd, $params)
    {
        $jobname = $params['jobname'];
        $job = $this->server->config->get($jobname);

        switch ($cmd)
        {
            case 'STOP':
                unset($this->gurading_jobnames[$jobname]);
                break;
        }
        return TRUE;
    }

    public function on_command_finished($cmd, $params, $result)
    {
        if (!$result) return FALSE;

        $jobname = $params['jobname'];
        $job = $this->server->config->get($jobname);

        switch ($cmd)
        {
            case 'START':
            case 'RESTART':
            case 'UPDATE':
                if ($job['guard'])
                    $this->gurading_jobnames[$jobname] = TRUE;
                else
                    unset($this->gurading_jobnames[$jobname]);
                break;

            case 'DELETE':
                unset($this->gurading_jobnames[$jobname]);
                break;
        }
        return TRUE;
    }

    private function _detect_guard_jobs()
    {
        $jobs = $this->server->config->getall();
        $pids = $this->server->shm->get_var('pids');

        $statuses = array();
        foreach ($pids as $jobname=>$pid)
        {
            if (isset($jobs[$jobname]['guard']) && $jobs[$jobname]['guard']
                && $this->server->process_exists($pid))
            $this->gurading_jobnames[$jobname] = TRUE;
        }
    }

    private function _check()
    {
        $pids = $this->server->shm->get_var('pids');

        foreach ($this->gurading_jobnames as $jobname=>$dummy)
        {
            $pid = isset($pids[$jobname]) ? $pids[$jobname] : FALSE;
            if (!$pid || !$this->server->process_exists($pid))
            {
                $this->server->server_echo(getmypid()."[guarder] starting \"{$jobname}\"...");
                $this->server->command_start(array('jobname'=>$jobname, 'newline'=>FALSE));
            }
        }
    }

}