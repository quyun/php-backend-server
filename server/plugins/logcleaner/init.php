<?php

/*
 * 日志清理插件
 *
 * 该插件会定时清理过期的日志文件
 */

class Logcleaner
{
    private $server = NULL;             // 后台进程服务器对象
    private $log_path = NULL;           // 后台进程服务器日志目录
    private $clean_interval = FALSE;    // 清理间隔时间
    private $logfile_expire = FALSE;    // 日志过期时间

    public function __construct($server, $setting)
    {
        $this->server = $server;
        $this->log_path = isset($setting['log_path']) ? $setting['log_path'] : realpath(dirname(__FILE__).'/../../').'/data/log';
        $this->clean_interval = isset($setting['clean_interval']) ? $setting['clean_interval'] : 3600;
        $this->logfile_expire = isset($setting['logfile_expire']) ? $setting['logfile_expire'] : 86400*7;
    }

    public function on_server_inited()
    {
        // 新建子进程用于清理日志
        $pid = pcntl_fork();
        if ($pid == -1)
        {
            $this->server_echo('[logcleaner] init failed.');
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
            $this->_clean();
            sleep($this->clean_interval);
        } 

        return TRUE;
    }

    private function _clean()
    {
        $jobs = $this->server->config->getall();
        $jobnames = array_keys($jobs);

        foreach ($jobnames as $jobname)
        {
            $job_logpath = $this->log_path.'/jobs/'.$jobname;
            $this->_clean_dir($job_logpath);
        }

        $server_logpath = $this->log_path.'/server';
        $this->_clean_dir($server_logpath);
    }

    // 清除指定目录下的日志文件，如果目录为空，则目录也将被删除
    // 如果目录也被删除，返回 TRUE，否则返回 FALSE
    private function _clean_dir($logdir)
    {
        if (!is_dir($logdir)) return FALSE;

        $now = time();

        if ($handle = opendir($logdir))
        {
            $item_count = 0;
            while (($file = readdir($handle)) !== FALSE)
            {
                if ($file == "." || $file == "..") continue;
                if (strpos($file, '__') === 0) continue;

                $item_count++;

                $filepath = $logdir.'/'.$file;
                if (is_dir($filepath))
                {
                    if ($this->_clean_dir($filepath)) $item_count--;
                    continue;
                }

                if ($now - filemtime($filepath) > $this->logfile_expire)
                {
                    if (@unlink($filepath)) $item_count--;
                }
            }
            closedir($handle);

            // 删除空目录
            if ($item_count == 0)
            {
                if (@rmdir($logdir)) return TRUE;
            }
        }

        return FALSE;
    }

}