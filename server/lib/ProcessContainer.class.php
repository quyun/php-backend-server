<?php

/**
 * 进程容器
 */
class ProcessContainer
{
    private $process_path = NULL;       // 进程路径
    private $process_params = NULL;     // 进程参数

    private $process_pid = NULL;        // 进程PID
    private $process_stdout = NULL;     // 进程输出句柄
    private $process_stderr = NULL;     // 进程错误输出句柄

    private $output_handler = NULL;     // 进程输出处理函数句柄
    private $error_handler = NULL;      // 进程错误输出处理函数句柄
    private $exit_handler = NULL;       // 进程退出处理函数句柄
    private $child_init_handler = NULL; // 子进程初始化函数句柄

    private $is_php = NULL;             // 进程是否为PHP脚本
    private $php_path = NULL;           // PHP解析器路径


    /*
     * 初始化进程容器
     * 
     * @param process_path 进程路径
     * @param process_params 进程参数
     * @param is_php 进程是否为PHP脚本
     */
    public function __construct($process_path, $process_params, $is_php=TRUE)
    {
        $this->process_path = $process_path;
        $this->process_params = $process_params;
        $this->is_php = $is_php;

        if ($this->is_php) $this->php_path = $this->get_php_path();
    }

    /*
     * 注册进程输出处理函数
     *
     * @param output_handler 进程输出处理函数句柄
     */
    public function register_output_handler($output_handler)
    {
        $this->output_handler = $output_handler;
    }

    /*
     * 注册进程错误输出处理函数
     *
     * @param error_handler 进程输出处理函数句柄
     */
    public function register_error_handler($error_handler)
    {
        $this->error_handler = $error_handler;
    }

    /*
     * 注册进程输出处理函数
     *
     * @param exit_handler 进程退出处理函数句柄
     */
    public function register_exit_handler($exit_handler)
    {
        $this->exit_handler = $exit_handler;
    }

    /*
     * 注册子进程初始化处理函数
     *
     * @param exit_handler 子进程初始化函数句柄
     */
    public function register_child_init_handler($child_init_handler)
    {
        $this->child_init_handler = $child_init_handler;
    }

    /*
     * 启动进程
     */
    public function start()
    {
        if (!ProcessContainer::dup2_available())
        {
            $cmd = "{$this->process_path} {$this->process_params}";
            if ($this->is_php) $cmd = "{$this->php_path} {$cmd}";
            $descriptorspec = array(
                0 => array("pipe", "r"),
                1 => array("pipe", "w"),
                2 => array("pipe", "w"),
            );

            $process = proc_open($cmd, $descriptorspec, $pipes, dirname($this->process_path));
            if (!is_resource($process)) return FALSE;

            $status = proc_get_status($process);
            $this->process_pid = $status['pid'];

            // 关闭输入
            fclose($pipes[0]);
            $this->process_stdout = $pipes[1];
            $this->process_stderr = $pipes[2];
            $this->process = $process;

            return TRUE;
        }
        else
        {
            $pipes = array(
                //0 => $this->pipe_open('r+'),
                1 => $this->pipe_open('w+'),
                2 => $this->pipe_open('w+'),
            );

            // 利用 dup2 重定向管道
            $pid = pcntl_fork();
            if ($pid == -1)
            {
                return FALSE;
            }
            else if ($pid)    // 父进程
            {
                $this->process_pid = $pid;
                $this->process_stdout = $pipes[1];
                $this->process_stderr = $pipes[2];
                return TRUE;
            }

            // 子进程
            if ($this->child_init_handler) call_user_func($this->child_init_handler);

            $params = explode(' ', $this->process_params);
            if ($this->is_php)
            {
                $path = $this->php_path;
                array_unshift($params, $this->process_path);
            }
            else
            {
                $path = $this->process_path;
            }

            chdir(dirname($this->process_path));
            //fildes_dup2(fildes_fileno($pipes[0]), fildes_fileno(STDIN));
            fildes_dup2(fildes_fileno($pipes[1]), fildes_fileno(STDOUT));
            fildes_dup2(fildes_fileno($pipes[2]), fildes_fileno(STDERR));
            pcntl_exec($path, $params);
            exit;
        }
    }

    /*
     * 读取进程输出
     */
    public function loop_read()
    {
        // 检测子进程是否已退出
        if (!$this->is_alive($this->process_pid))
        {
            // 关闭输出
            @fclose($this->process_stdout);
            pcntl_waitpid($this->process_pid, $status);
            if ($this->exit_handler) call_user_func($this->exit_handler, $status);
            return;
        }

        // 非阻塞模式读取
        stream_set_blocking($this->process_stdout, 0);
        stream_set_blocking($this->process_stderr, 0);

        while (TRUE)
        {
            $read   = array($this->process_stdout);
            $write  = NULL;
            $except = array($this->process_stderr);

            $may_exit = FALSE;
            $num_changed_streams = stream_select($read, $write, $except, 3);

            if (FALSE === $num_changed_streams)
            {
                // 返回失败，进程可能已退出
                $may_exit = TRUE;                        
            }
            elseif ($num_changed_streams > 0)
            {
                $output = stream_get_contents($this->process_stdout);

                // 缓存输出
                if ($output !== '')
                {
                    if ($this->output_handler) call_user_func($this->output_handler, $output);
                }
                else
                {
                    // 有select到，但输出为空，进程可能已退出
                    $may_exit = TRUE;
                }
            }

            if (may_exit)
            {
                $jobstatus = $this->is_alive($this->process_pid);
                if (!$jobstatus)
                {
                    // 关闭输出
                    fclose($this->process_stdout);
                    pcntl_waitpid($this->process_pid, $status);
                    if ($this->exit_handler) call_user_func($this->exit_handler, $status);
                    return;
                }
            }
        }
    }

    /*
     * 检测进程是否存在
     */
    private function is_alive()
    {
        $proc_dir = '/proc/'.$this->process_pid;
        if (!file_exists($proc_dir)) return FALSE;

        // 检测进程状态
        $status = file_get_contents($proc_dir.'/status');
        preg_match('/State:\s+(\S+)/', $status, $matches);
        if (!$matches) return FALSE;
        if ($matches[1] == 'Z') return FALSE;

        return TRUE;
    }

    /*
     * 返回进程PID
     */
    public function get_pid()
    {
        return $this->process_pid;
    }

    /*
     * 打开一个随机命名的管道
     */
    private function pipe_open($mode)
    {
        $pipename = tempnam("/tmp", "pipe");
        unlink($pipename);
        if (!posix_mkfifo($pipename, 0600)) return FALSE;
        return fopen($pipename, $mode);
    }

    /*
     * 关闭管道
     */
    private function pipe_close($pipe)
    {
        return fclose($pipe);
    }

    // 获取运行当前脚本的PHP解析器路径
    private function get_php_path()
    {
        return readlink('/proc/'.getmypid().'/exe');
    }

    // 检查 dup2 机制是否要用
    public static function dup2_available()
    {
        return function_exists('fildes_dup2');
    }

}