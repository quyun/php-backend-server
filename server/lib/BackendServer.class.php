<?php
require_once(dirname(__FILE__) . '/JobConfig.class.php');
require_once(dirname(__FILE__) . '/ShareMemory.class.php');
require_once(dirname(__FILE__) . '/ProcessContainer.class.php');

/**
 * 服务器类
 */
class BackendServer
{
    public  $config = NULL;             // 进程配置对象

    private $socket = NULL;             // socket
    private $cnt = NULL;                // 当前socket连接
    private $shm = NULL;                // 共享内存
    private $server_ob = array();       // 服务器输出缓冲

    private $event_handlers = array(    // 事件处理函数
        'server_inited' => array(),     // 服务器初始化完毕
        'command_received' => array(),  // 接收到命令
    );

    private $plugins = array();         // 插件对象列表

    public function __construct($setting)
    {
        $this->server_ip = isset($setting['server_ip']) ? $setting['server_ip'] : '127.0.0.1';
        $this->server_port = isset($setting['server_port']) ? $setting['server_port'] : 13123;
        $this->config_file = isset($setting['config_file']) ? $setting['config_file'] : realpath('./data').'/config.json';

        $this->log_path = isset($setting['log_path']) ? $setting['log_path'] : realpath('./data/log/');
        $this->plugins_path = isset($setting['plugins_path']) ? $setting['plugins_path'] : realpath('./plugins/');
        $this->plugins_data_path = isset($setting['plugins_data_path']) ? $setting['plugins_data_path'] : realpath('./data/plugins/');

        if (function_exists('fildes_dup2'))
        {
            $errorlog_fd = fopen($this->log_path.'/server.error.log', 'a+');
            fildes_dup2(fildes_fileno($errorlog_fd), fildes_fileno(STDERR));   
        }
    }

    // 加载插件
    public function load_plugins()
    {
        $this->server_echo("\n");

        if ($handle = opendir($this->plugins_path))
        {
            while (($file = readdir($handle)) != FALSE)
            {
                if ($file == '.' || $file == '..') continue;

                $plugin_name = $file;
                $plugin_dir = $this->plugins_path.'/'.$plugin_name;
                if (is_dir($plugin_dir))
                {
                    $initfile = $plugin_dir.'/init.php';
                    if (is_file($initfile))
                    {
                        // 禁止插件输出
                        ob_start();
                        require_once($initfile);
                        ob_clean();

                        $class_name = ucfirst($plugin_name);
                        if (!class_exists($class_name)) continue;

                        $ph = new $class_name($this, array(
                            'server_ip' => $this->server_ip,
                            'server_port' => $this->server_port,
                            'log_path' => $this->log_path,
                            'plugins_data_path' => $this->plugins_data_path,
                        ));
                        $this->plugins[$plugin_name] = $ph;

                        if (method_exists($ph, 'on_server_inited'))
                        {
                            $this->event_handlers['server_inited'][] = array($ph, 'on_server_inited');
                        }
                        if (method_exists($ph, 'on_command_received'))
                        {
                            $this->event_handlers['command_received'][] = array($ph, 'on_command_received');
                        }

                        $this->server_echo("Plugin \"{$plugin_name}\" loaded.\n");
                    }
                }
            }

            closedir($handle);
            return TRUE;
        }
        else
        {
            return FALSE;
        }
    }
    
    // 启动服务器
    public function run()
    {
        if ($this->socket) return TRUE;
        if (!$this->listen()) return FALSE;
        if (!$this->init_shm()) return FALSE;
        if (!$this->init_config()) return FALSE;

        if ($this->event_handlers['server_inited'])
        {
            foreach ($this->event_handlers['server_inited'] as $handler)
            {
                call_user_func($handler);
            }
        }

        return $this->loop();
    }

    // 监听
    private function listen()
    {
        if (!($this->socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP)))
        {
            $this->server_echo("socket_create() failed.\n");
            return FALSE;
        }

        if (!socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1))
        {
            $this->server_echo("socket_set_option() failed.\n");
            return FALSE;
        }

        if (!($ret = @socket_bind($this->socket, $this->server_ip, $this->server_port)))
        {
            $this->server_echo("socket_bind() failed.\n");
            return FALSE;
        }

        if (!($ret = @socket_listen($this->socket, 5)))
        {
            $this->server_echo("socket_listen() failed.\n");
            return FALSE;
        }
        
        $this->server_echo("Backend server starting, binding {$this->server_ip}:{$this->server_port}.\n");
        return TRUE;
    }
    
    // 初始化共享内存
    private function init_shm()
    {
        $this->shm = new SharedMemory('shm_key_of_backend_server_'.$this->server_ip.'_'.$this->server_port);
        if (!$this->shm->attach())
        {
            $this->server_echo("shm attach() failed.\n");
            return FALSE;
        }

        $this->shm->lock();
        if (!$this->shm->has_var('pids'))
        {
            $pids = array();
            $this->shm->put_var('pids', $pids);
        }
        $this->shm->unlock();

        return TRUE;
    }

    // 初始化进程配置
    private function init_config()
    {
        $this->config = new JobConfig($this->config_file);

        return TRUE;
    }
    
    // 进入监听循环
    private function loop()
    {
        // 循环处理
        while (TRUE)
        {
            // 等待连接
            $this->server_echo("\nWaiting for new command...\n");
            if (!($this->cnt = @socket_accept($this->socket)))
            {
                $this->server_echo("socket_accept() failed.\n");
                break;
            }
            
            // 读取输入
            if (!($input = @socket_read($this->cnt, 1024))) {
                $this->server_echo("socket_read() failed.\n");
                break;
            }

            $this->server_echo("$input\n");

            // 分析并执行命令
            $input_arr = explode(' ', trim($input), 2);
            if (count($input_arr) > 1)
            {
                list($cmd, $params) = $input_arr;
                $params = json_decode($params, TRUE);
                if ($params === FALSE)
                {
                    $this->socket_write('FAILED');
                    $this->server_echo("FAILED. (params decode failed.)\n");
                    continue;
                }
            }
            else
            {
                $cmd = $input;
                $params = array();
            }

            $this->run_command($cmd, $params);
        }

        $this->socket_close();
    }
    
    // 执行命令
    private function run_command($cmd, $params)
    {
        if ($this->event_handlers['command_received'])
        {
            foreach ($this->event_handlers['command_received'] as $handler)
            {
                call_user_func($handler, $cmd, $params);
            }
        }

        if (in_array($cmd, array('ADD', 'DELETE', 'UPDATE', 'QUERY', 'START', 'STOP', 'RESTART', 'STATUS', 'READ')))
        {
            if (!isset($params['jobname']))
            {
                $this->socket_write('FAILED');
                $this->server_echo("FAILED. (jobname is required.)\n");
                return FALSE;
            }
        }

        switch ($cmd)
        {
            case 'ADD':     // 添加进程
                if (!isset($params['command']))
                {
                    $this->socket_write('FAILED');
                    $this->server_echo("FAILED. (no command specified.)\n");
                    break;
                }
                $jobname = $params['jobname'];
                $command = $params['command'];
                $cmdparams = isset($params['params']) ? $params['params'] : '';
                $buffersize = isset($params['buffersize']) ? $params['buffersize'] : 20;
                $writelog = isset($params['writelog']) ? $params['writelog'] : FALSE;
                $this->command_add($jobname, $command, $cmdparams, $buffersize, $writelog);
                break;

            case 'DELETE':  // 删除进程
                $this->command_delete($params['jobname']);
                break;

            case 'UPDATE':  // 更新进程
                $jobname = $params['jobname'];
                unset($params['jobname']);
                $this->command_update($jobname, $params);
                break;

            case 'QUERY':   // 查询进程信息
                $this->command_query($params['jobname']);
                break;

            case 'QUERYALL':   // 查询所有进程信息
                $this->command_queryall();
                break;

            case 'START':   // 开启进程
                $this->command_start($params['jobname']);
                break;

            case 'STOP':	// 结束进程
                $this->command_stop($params['jobname']);
                break;

            case 'RESTART':	// 重启进程
                $this->command_restart($params['jobname']);
                break;

            case 'STATUS':  // 获取进程状态
                $this->command_status($params['jobname']);
                break;

            case 'STATUSALL':// 获取所有进程状态
                $this->command_statusall();
                break;

            case 'READ':	// 读取进程缓冲
                $this->command_read($params['jobname']);
                break;

            case 'SERVERMEM':	// 读取服务器内存占用情况
                $this->command_servermem();
                break;

            case 'SERVERREAD':	// 读取服务器输出缓冲
                $this->command_serverread();
                break;
        }
    }

    // 添加进程配置信息
    public function command_add($jobname, $cmd, $params, $buffersize, $writelog)
    {
        $rt = $this->config->add($jobname, array(
            'command' => $cmd,
            'params' => $params,
            'buffersize' => $buffersize,
            'writelog' => $writelog,
        ));

        if ($rt)
        {
            $this->socket_write('OK');
            $this->server_echo("OK\n");
            return TRUE;
        }
        else
        {
            $this->socket_write('FAILED');
            $this->server_echo("FAILED\n");
            return FALSE;
        }
    }

    // 删除进程配置信息
    public function command_delete($jobname)
    {
        // 检查进程是否还在运行
        $pid = $this->shm_process_getpid($jobname);
        if ($pid && $this->process_exists($pid))
        {
            $this->socket_write('FAILED');
            $this->server_echo("FAILED. (Process is still running)\n");
            return FALSE;
        }

        // 删除共享内存中的数据
        $this->shm->remove_var('ob_'.$this->jobname, TRUE);
        $this->shm_process_deletepid($this->jobname);

        return $this->config->delete($jobname);
    }

    // 更新进程配置信息
    public function command_update($jobname, $setting)
    {
        $rt = $this->config->update($jobname, $setting);
        if ($rt)
        {
            $this->socket_write('OK');
            $this->server_echo("OK\n");
            return TRUE;
        }
        else
        {
            $this->socket_write('FAILED');
            $this->server_echo("FAILED\n");
            return FALSE;
        }
        return $rt;
    }

    // 查询单个进程信息
    public function command_query($jobname)
    {
        $result = $this->config->get($jobname);
        $this->socket_write(json_encode($result)."\n");
        return $result;
    }

    // 查询所有进程信息
    public function command_queryall()
    {
        $result = $this->config->getall();
        $this->socket_write(json_encode($result)."\n");
        return $result;
    }

    // 开启进程
    public function command_start($jobname)
    {
        $setting = $this->config->get($jobname);
        $command = $setting['command'];
        $params = $setting['params'];
        $buffersize = $setting['buffersize'];
        $writelog = $setting['writelog'];

        $pid = $this->shm_process_getpid($jobname);
        if ($pid)
        {
            // 检查进程是否正在运行
            if ($this->process_exists($pid))
            {
                $this->socket_write('FAILED');
                $this->server_echo("FAILED. (process \"$jobname\"({$pid}) has already exist.)\n");
                return FALSE;
            }
        }

        if (!file_exists($command))
        {
            // 文件不存在
            $this->socket_write('FAILED');
            $this->server_echo("FAILED. (command path \"$command\" does not exist.)\n");
            return FALSE;
        }

        // 新建孙子进程
        $pid = pcntl_fork();
        if ($pid == -1)
        {
            $this->socket_write('FAILED');
            $this->server_echo("pcntl_fork() failed.\n");
            return FALSE;
        }
        else if ($pid)    // 父进程
        {
            pcntl_waitpid($pid, $status);
            return TRUE;
        }

        // 子进程
        $t_pid = pcntl_fork();
        if ($t_pid == -1)
        {
            $this->socket_write('FAILED');
            $this->server_echo("pcntl_fork() failed.\n");
            return FALSE;
        }
        else if ($t_pid)
        {
            exit;
        }

        // 孙子进程
        $this->jobname = $jobname;
        $this->writelog = $writelog;
        $this->buffersize = $buffersize;

        if (ProcessContainer::dup2_available())
        {
            // 使用 dup2 手工配置管道定向
        }
        else
        {
            // 需要在proc_open前关闭socket，否则会被proc_open进程继承，导致socket端口被占用
            $this->socket_write('OK');
            $this->server_echo("OK\n");
            $this->socket_close();
        }

        $process = new ProcessContainer($command, $params, TRUE);
        $process->register_output_handler(array($this, 'process_output_handler'));
        $process->register_error_handler(array($this, 'process_error_handler'));
        $process->register_exit_handler(array($this, 'process_exit_handler'));
        $process->register_child_init_handler(array($this, 'process_child_init_handler'));
        if (!$process->start())
        {
            if (ProcessContainer::dup2_available())
            {
                $this->socket_write('FAILED');
                $this->server_echo("output_buffer init failed.\n");
                $this->socket_close();
            }
            return FALSE;
        }

        // 进程配置写入共享内存
        $process_pid = $process->get_pid();
        $this->shm_process_updatepid($jobname, $process_pid);
        
        // 创建共享变量用于输出缓冲
        $output_buffer = array();
        if (!$this->shm->put_var('ob_'.$jobname, $output_buffer, TRUE))
        {
            if (ProcessContainer::dup2_available())
            {
                $this->socket_write('FAILED');
                $this->server_echo("output_buffer init failed.\n");
                $this->socket_close();
            }
            return FALSE;
        }

        if (ProcessContainer::dup2_available())
        {
            $this->socket_write('OK');
            $this->server_echo("OK\n");
            $this->socket_close();
        }

        $process->loop_read();
        exit;
    }

    // 结束进程
    // $is_restart 是否是重启进程，如果是，则SOCKET不输出
    public function command_stop($jobname, $is_restart=FALSE)
    {
        $pid = $this->shm_process_getpid($jobname);
        if (!$pid)
        {
            // 进程不存在
            if (!$is_restart)
            {
                $this->socket_write('FAILED');
            }
            $this->server_echo("FAILED. (process \"$jobname\" does not exist.)\n");
            return FALSE;
        }

        // 强制结束子进程
        if (!posix_kill($pid, SIGKILL))
        {
            if (!$is_restart)
            {
                $this->socket_write('FAILED');
            }
            $this->server_echo("FAILED. (failed to kill process \"$jobname\".)\n");
            return FALSE;
        }

        if (!$is_restart)
        {
            $this->socket_write('OK');
        }
        $this->server_echo("OK\n");
        
        return TRUE;
    }

    // 重启进程
    public function command_restart($jobname)
    {
        $pid = $this->shm_process_getpid($jobname);
        if (!$pid)
        {
            $this->socket_write('FAILED');
            $this->server_echo("FAILED. (process \"$jobname\" does not exist.)\n");
            return FALSE;
        }

        if ($this->command_stop($jobname, TRUE))
        {
            return $this->command_start($jobname);
        }
        else
        {
            $this->socket_write('FAILED');
            return FALSE;
        }
    }

    // 查看进程状态
    public function command_status($jobname)
    {
        $pid = $this->shm_process_getpid($jobname);
        if (!$pid)
        {
            // 进程不存在
            $this->socket_write('DOWN');
            $this->server_echo("DOWN. (process \"$jobname\" does not exist.)\n");
            return FALSE;
        }
       
        if ($this->process_exists($pid))
        {
            $this->socket_write('UP');
            $this->server_echo("UP\n");
        }
        else
        {
            $this->socket_write('DOWN');
            $this->server_echo("DOWN\n");
        }
        
        return TRUE;
    }

    // 查看所有进程状态
    public function command_statusall()
    {
        $pids = $this->shm->get_var('pids');

        $statuses = array();
        foreach ($pids as $jobname=>$pid)
        {
            $statuses[$jobname] = $this->process_exists($pid) ? 'UP' : 'DOWN';
        }
        $this->socket_write(json_encode($statuses));
        
        return TRUE;
    }

    // 读取进程输出缓冲区
    public function command_read($jobname)
    {
        $pid = $this->shm_process_getpid($jobname);
        if (!$pid)
        {
            // 进程不存在
            $this->socket_write("\0");
            $this->server_echo("NULL. (process \"$jobname\" does not exist.)\n");
            return FALSE;
        }

        // 取出共享内存中的输出缓冲
        $output_buffer = $this->shm->get_var('ob_'.$jobname);
        if ($output_buffer)
        {
            $this->socket_write(implode("\n", $output_buffer)."\n");
        }
        else
        {
            $this->socket_write("\n");
        }
        
        return TRUE;
    }

    // 读取服务器输出缓冲区
    public function command_serverread()
    {
        $this->socket_write(implode('', $this->server_ob));
    }

    // 读取服务器内存占用量
    public function command_servermem()
    {
        $this->socket_write($this->memory_get_usage());
    }

    // 获取共享内存中的进程PID
    // 若进程不存在，返回FALSE
    private function shm_process_getpid($jobname)
    {
        $pids = $this->shm->get_var('pids');
        return $pids[$jobname] ? $pids[$jobname] : FALSE;
    }

    // 更新共享内存中的进程PID
    private function shm_process_updatepid($jobname, $pid)
    {
        $this->shm->lock();
        $pids = $this->shm->get_var('pids');
        if ($pids === FALSE)
        {
            $this->shm->unlock();
            return FALSE;
        }
        $pids[$jobname] = $pid;
        $result = $this->shm->put_var('pids', $pids);
        $this->shm->unlock();
        return $result;
    }

    // 删除共享内存中的进程PID
    private function shm_process_deletepid($jobname)
    {
        $this->shm->lock();
        $pids = $this->shm->get_var('pids');
        if ($pids === FALSE)
        {
            $this->shm->unlock();
            return FALSE;
        }
        unset($pids[$jobname]);
        $result = $this->shm->put_var('pids', $pids);
        $this->shm->unlock();
        return $result;
    }

    // 进程输出处理
    public function process_output_handler($output)
    {
        $output = $this->time_prefix($output);

        if ($this->writelog)
        {
            // 写日志
            $job_path = "{$this->log_path}/jobs/{$this->jobname}";
            $job_daily_path = "{$job_path}/".date('Ymd');
            if (!is_dir($job_path)) mkdir($job_path, 0777);
            if (!is_dir($job_daily_path)) mkdir($job_daily_path, 0777);
            file_put_contents($job_daily_path.'/'.date('YmdH').'.log', $output, FILE_APPEND);
        }

        $buffersize = $this->buffersize + 1;
        $lines = explode("\n", $output);

        $output_buffer = $this->shm->get_var('ob_'.$this->jobname);
        if (!is_array($output_buffer)) $output_buffer = array();
        $old_len = count($output_buffer);
        if ($old_len > 0)
        {
            // 连接断行
            $output_buffer[$old_len-1] .= array_shift($lines);
        }
        $output_buffer = array_merge($output_buffer, $lines);
        $output_buffer = array_slice($output_buffer, -$buffersize, $buffersize);

        // 更新共享变量
        if (!$this->shm->put_var('ob_'.$this->jobname, $output_buffer, TRUE))
        {
            $this->server_echo("shm put_var() failed.\n");
        }
    }

    // 进程错误输出处理
    public function process_error_handler($output)
    {
        $output = $this->time_prefix($output);

        if ($this->writelog)
        {
            // 写日志
            $job_path = "{$this->log_path}/jobs/{$this->jobname}";
            $job_daily_path = "{$job_path}/".date('Ymd');
            if (!is_dir($job_path)) mkdir($job_path, 0777);
            if (!is_dir($job_daily_path)) mkdir($job_daily_path, 0777);
            file_put_contents($job_daily_path.'/'.date('YmdH').'.error'.'.log', $output, FILE_APPEND);
        }
    }

    // 进程退出处理
    public function process_exit_handler($status)
    {
        $message = "Process \"{$this->jobname}\" exit with code $status.\n";
        $this->server_echo($message);
        $this->process_output_handler($message);

        // 删除共享内存中的数据
        $this->shm->remove_var('ob_'.$this->jobname, TRUE);
        $this->shm_process_deletepid($this->jobname);

        return TRUE;
    }

    // 子进程初始化处理
    public function process_child_init_handler()
    {
        $this->socket_close();
    }
    
    private function socket_write($str)
    {
        if (!$this->cnt) return FALSE;
        return socket_write($this->cnt, $str);
    }
    
    private function socket_close()
    {
        if (!$this->socket) return FALSE;
        return socket_close($this->socket);
    }

    // 服务器输出
    public function server_echo($str)
    {
        $str = $this->time_prefix($str);

        $this->server_ob[] = $str;
        $this->server_ob = array_slice($this->server_ob, -20, 20);
        file_put_contents($this->log_path.'/server.'.date('YmdH').'.log', $str, FILE_APPEND);

        if (posix_ttyname(STDOUT)) echo $str;
    }

    // 在输出字符串前添加时间
    private function time_prefix($str)
    {
        $lines = explode("\n", $str);
        array_walk($lines, array($this, 'time_prefix_alter'));

        return implode("\n", $lines);
    }

    private function time_prefix_alter(&$item, $key)
    {
        if ($key > 0) $item = '['.date('y-m-d H:i:s').'] '.$item;
    }

    // 检查进程是否存在
    private function process_exists($pid)
    {
        if (!$pid) return FALSE;

        $proc_dir = '/proc/'.$pid;
        if (!file_exists($proc_dir)) return FALSE;

        // 检测进程状态
        $status = file_get_contents($proc_dir.'/status');
        preg_match('/State:\s+(\S+)/', $status, $matches);
        if (!$matches) return FALSE;
        if ($matches[1] == 'Z') return FALSE;

        return TRUE;
    }

    // 返回进程占用的实际内存值
    private function memory_get_usage() 
    {
        $pid = getmypid();
        $status = file_get_contents("/proc/{$pid}/status");
        preg_match('/VmRSS\:\s+(\d+)\s+kB/', $status, $matches);
        $vmRSS = $matches[1];
        return $vmRSS*1024;
    }
    
}