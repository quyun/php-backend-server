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
    public  $shm = NULL;                // 共享内存

    private $socket = NULL;             // socket
    private $cnt = NULL;                // 当前socket连接
    private $server_ob = array();       // 服务器输出缓冲
    private $server_muted = FALSE;      // 服务器不输出标志

    private $log_path = NULL;           // 进程日志路径
    private $plugins_path = NULL;       // 插件路径
    private $plugins_data_path = NULL;  // 插件数据路径

    private $event_handlers = array(    // 事件处理函数
        'server_inited' => array(),     // 服务器初始化完毕
        'command_received' => array(),  // 接收到命令
        'command_finished' => array(),  // 命令执行完成
    );
    private $plugins = array();         // 插件对象列表


    public function __construct($setting)
    {
        $this->server_ip = isset($setting['server_ip']) ? $setting['server_ip'] : '127.0.0.1';
        $this->server_port = isset($setting['server_port']) ? $setting['server_port'] : 13123;

        $basedir = isset($setting['basedir']) ? $setting['basedir'] : realpath(dirname(__FILE__).'/../');
        $this->config_file = isset($setting['config_file']) ? $setting['config_file'] : $basedir.'/data/config.json';
        $this->log_path = isset($setting['log_path']) ? $setting['log_path'] : $basedir.'/data/log';
        $this->plugins_path = isset($setting['plugins_path']) ? $setting['plugins_path'] : $basedir.'/plugins';
        $this->plugins_data_path = isset($setting['plugins_data_path']) ? $setting['plugins_data_path'] : $basedir.'/data/plugins';
    }

    // 批量加载插件
    public function load_plugins($plugin_names, $plugin_settings)
    {
        if (!($handle = opendir($this->plugins_path))) return FALSE;

        if ($plugin_names == '*')
        {
            while (($file = readdir($handle)) != FALSE)
            {
                if ($file == '.' || $file == '..') continue;
                $plugin_setting = isset($plugin_settings[$file]) ? $plugin_settings[$file] : array();
                $this->load_plugin($file, $plugin_setting);
            }

            closedir($handle);
        }
        else
        {
            $plugin_names = explode(',', $plugin_names);
            foreach ($plugin_names as $plugin_name)
            {
                $plugin_name = trim($plugin_name);
                if (!$plugin_name) continue;
                $plugin_setting = isset($plugin_settings[$plugin_name]) ? $plugin_settings[$plugin_name] : array();
                $this->load_plugin($plugin_name, $plugin_setting);
            }
        }

        return TRUE;
    }

    // 加载插件
    public function load_plugin($plugin_name, $plugin_setting=array())
    {
        $plugin_dir = $this->plugins_path.'/'.$plugin_name;
        if (!is_dir($plugin_dir)) return FALSE;

        $initfile = $plugin_dir.'/init.php';
        if (is_file($initfile))
        {
            // 禁止插件输出
            ob_start();
            require_once($initfile);
            ob_end_clean();

            $class_name = ucfirst($plugin_name).'Plugin';
            if (!class_exists($class_name)) return FALSE;

            $plugin_setting = array_merge($plugin_setting, array(
                'server_ip' => $this->server_ip,
                'server_port' => $this->server_port,
                'log_path' => $this->log_path,
                'plugins_data_path' => $this->plugins_data_path,
            ));
            $ph = new $class_name($this, $plugin_setting);
            $this->plugins[$plugin_name] = $ph;

            if (method_exists($ph, 'on_server_inited'))
            {
                $this->event_handlers['server_inited'][] = array($ph, 'on_server_inited');
            }
            if (method_exists($ph, 'on_command_received'))
            {
                $this->event_handlers['command_received'][] = array($ph, 'on_command_received');
            }
            if (method_exists($ph, 'on_command_finished'))
            {
                $this->event_handlers['command_finished'][] = array($ph, 'on_command_finished');
            }

            $this->server_echo("Plugin \"{$plugin_name}\" loaded.");
        }

        return TRUE;
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
                if (!call_user_func($handler)) return FALSE;
            }
        }

        // 关闭终端输出
        $this->server_muted = TRUE;

        if (function_exists('fildes_dup2'))
        {
            $errorlog_path = $this->log_path.'/server/error';
            if (!is_dir($errorlog_path)) mkdir($errorlog_path, 0777);
            $errorlog_fd = fopen($errorlog_path.'/error.log', 'a+');
            fildes_dup2(fildes_fileno($errorlog_fd), fildes_fileno(STDERR));   
        }

        return $this->loop();
    }

    // 监听
    private function listen()
    {
        if (!($this->socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP)))
        {
            $this->server_echo('socket_create() failed.');
            return FALSE;
        }

        if (!socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1))
        {
            $this->server_echo('socket_set_option() failed.');
            return FALSE;
        }

        if (!($ret = @socket_bind($this->socket, $this->server_ip, $this->server_port)))
        {
            $this->server_echo('socket_bind() failed.');
            return FALSE;
        }

        if (!($ret = @socket_listen($this->socket, 5)))
        {
            $this->server_echo('socket_listen() failed.');
            return FALSE;
        }
        
        $this->server_echo("Backend server starting, binding {$this->server_ip}:{$this->server_port}.");
        return TRUE;
    }
    
    // 初始化共享内存
    private function init_shm()
    {
        $this->shm = new SharedMemory('shm_key_of_backend_server_'.$this->server_ip.'_'.$this->server_port);
        if (!$this->shm->attach())
        {
            $this->server_echo('shm attach() failed.');
            return FALSE;
        }

        $this->shm->lock();

        // 检测共享内存中是否存在垃圾进程信息
        if (!$this->shm->has_var('pids'))
        {
            $pids = array();
        }
        else
        {
            $pids = $this->shm->get_var('pids');
            foreach ($pids as $jobname=>$pid)
            {
                // 删除不存在的进程信息
                if (!$this->process_exists($pid))
                {
                    unset($pids[$jobname]);
                    $this->shm->remove_var('ob_'.$jobname);
                }
            }
        }

        $this->shm->put_var('pids', $pids);
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
            $this->server_echo("\nWaiting for new command...");
            if (!($this->cnt = @socket_accept($this->socket)))
            {
                $this->server_echo('socket_accept() failed.');
                break;
            }

            // 读取输入
            $bin_length = '';
            while (strlen($bin_length) != 4)
            {
                $bin_length .= @socket_read($this->cnt, 4-strlen($bin_length), PHP_BINARY_READ); 
                if ($bin_length === FALSE)
                {
                    $this->server_echo('socket_read() failed.');
                    break;
                }
            }
            if (strlen($bin_length) != 4) break;
            list(, $length, ) = unpack('I', $bin_length);

            $input = '';
            while (strlen($input) != $length)
            {
                $read = @socket_read($this->cnt, $length-strlen($input), PHP_BINARY_READ);
                if ($read === FALSE)
                {
                    $this->server_echo('socket_read() failed.');
                    break;
                }
                $input .= $read;
            }
            if (strlen($input) != $length) break;

            $this->server_echo($input);

            // 分析并执行命令
            $input_arr = explode(' ', trim($input), 2);
            if (count($input_arr) > 1)
            {
                list($cmd, $params) = $input_arr;
                $params = json_decode($params, TRUE);
                if ($params === FALSE)
                {
                    $this->client_return('FAILED');
                    $this->server_echo('FAILED. (params decode failed.)');
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
        if (in_array($cmd, array('ADD', 'DELETE', 'UPDATE', 'GET', 'START', 'STOP', 'RESTART', 'STATUS', 'READ', 'MEM')))
        {
            if (!isset($params['jobname']))
            {
                $this->client_return('FAILED');
                $this->server_echo('FAILED. (jobname is required.)');
                return FALSE;
            }
        }

        if ($this->event_handlers['command_received'])
        {
            foreach ($this->event_handlers['command_received'] as $handler)
            {
                if (!call_user_func($handler, $cmd, $params)) return FALSE;
            }
        }

        $cmdfunc = 'command_'.strtolower($cmd);
        if (method_exists($this, $cmdfunc))
        {
            $result = call_user_func(array($this, $cmdfunc), $params);

            if ($this->event_handlers['command_finished'])
            {
                foreach ($this->event_handlers['command_finished'] as $handler)
                {
                    if (!call_user_func($handler, $cmd, $params, $result)) return FALSE;
                }
            }
        }
        else
        {
            $this->command_unknown($cmd);
        }
    }

    // 添加进程配置信息
    public function command_add($params)
    {
        $jobname = $params['jobname'];

        if (!isset($params['command']))
        {
            $this->client_return('FAILED');
            $this->server_echo("FAILED. (no command specified.)");
            return FALSE;
        }

        $command = $params['command'];
        $cmdparams = isset($params['params']) ? $params['params'] : '';
        $buffersize = isset($params['buffersize']) ? $params['buffersize'] : 20;
        $writelog = isset($params['writelog']) && $params['writelog'] ? TRUE : FALSE;
        // extend options
        unset($params['command'], $params['params'], $params['buffersize'], $params['writelog']);

        $rt = $this->config->add($jobname, array_merge($params, array(
            'command' => $command,
            'params' => $cmdparams,
            'buffersize' => $buffersize,
            'writelog' => $writelog,
        )));

        if ($rt)
        {
            $this->client_return('OK');
            $this->server_echo('OK');
            return TRUE;
        }
        else
        {
            $this->client_return('FAILED');
            $this->server_echo('FAILED');
            return FALSE;
        }
    }

    // 删除进程配置信息
    public function command_delete($params)
    {
        $jobname = $params['jobname'];

        // 检查进程是否还在运行
        $pid = $this->shm_process_getpid($jobname);
        if ($pid && $this->process_exists($pid))
        {
            $this->client_return('FAILED');
            $this->server_echo('FAILED. (Process is still running)');
            return FALSE;
        }

        // 删除共享内存中的数据
        $this->shm->remove_var('ob_'.$this->jobname, TRUE);
        $this->shm_process_deletepid($this->jobname);

        $rt = $this->config->delete($jobname);
        if ($rt)
        {
            $this->client_return('OK');
            $this->server_echo('OK');
            return TRUE;
        }
        else
        {
            $this->client_return('FAILED');
            $this->server_echo('FAILED');
            return FALSE;
        }
        return $rt;
    }

    // 更新进程配置信息
    public function command_update($params)
    {
        $jobname = $params['jobname'];

        unset($params['jobname']);

        $rt = $this->config->update($jobname, $params);
        if ($rt)
        {
            $this->client_return('OK');
            $this->server_echo('OK');
            return TRUE;
        }
        else
        {
            $this->client_return('FAILED');
            $this->server_echo('FAILED');
            return FALSE;
        }
        return $rt;
    }

    // 查询单个进程信息
    public function command_get($params)
    {
        $jobname = $params['jobname'];

        $result = $this->config->get($jobname);
        if ($result)
        {
            $this->client_return('OK', $result);
        }
        else
        {
            $this->client_return('FAILED');   
        }
        return $result;
    }

    // 查询所有进程信息
    public function command_getall($params)
    {
        $result = $this->config->getall();
        if ($result)
        {
            $this->client_return('OK', $result);
        }
        else
        {
            $this->client_return('FAILED');   
        }
        return $result;
    }

    // 开启进程
    public function command_start($params)
    {
        $jobname = $params['jobname'];

        $setting = $this->config->get($jobname);
        $command = $setting['command'];
        $cmdparams = $setting['params'];
        $buffersize = $setting['buffersize'];
        $writelog = $setting['writelog'];

        $pid = $this->shm_process_getpid($jobname);
        if ($pid)
        {
            // 检查进程是否正在运行
            if ($this->process_exists($pid))
            {
                $this->client_return('FAILED');
                $this->server_echo("FAILED. (process \"$jobname\"({$pid}) has already exist.)");
                return FALSE;
            }
            else
            {
                // 检查进程是否回收结束
                if ($this->shm_process_getpid($jobname))
                {
                    $this->client_return('FAILED');
                    $this->server_echo("FAILED. (process \"$jobname\"({$pid}) is still exiting.)");
                    return FALSE;
                }
            }
        }

        if (!file_exists($command))
        {
            // 文件不存在
            $this->client_return('FAILED');
            $this->server_echo("FAILED. (command path \"$command\" does not exist.)");
            return FALSE;
        }

        // 新建孙子进程
        $pid = pcntl_fork();
        if ($pid == -1)
        {
            $this->client_return('FAILED');
            $this->server_echo('pcntl_fork() failed.');
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
            $this->client_return('FAILED');
            $this->server_echo('pcntl_fork() failed.');
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
        $newline = !isset($params['newline']) ? TRUE : $params['newline'];

        if (ProcessContainer::dup2_available())
        {
            // 使用 dup2 手工配置管道定向
        }
        else
        {
            // 需要在proc_open前关闭socket，否则会被proc_open进程继承，导致socket端口被占用
            $this->client_return('OK');
            $this->server_echo('OK', $newline);
            $this->socket_close();
        }

        $process = new ProcessContainer($command, $cmdparams, TRUE);
        $process->register_output_handler(array($this, 'process_output_handler'));
        $process->register_error_handler(array($this, 'process_error_handler'));
        $process->register_exit_handler(array($this, 'process_exit_handler'));
        $process->register_child_init_handler(array($this, 'process_child_init_handler'));
        if (!$process->start())
        {
            if (ProcessContainer::dup2_available())
            {
                $this->client_return('FAILED');
                $this->server_echo('FAILED. (output_buffer init failed.)');
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
                $this->client_return('FAILED');
                $this->server_echo('FAILED. (output_buffer init failed.)');
                $this->socket_close();
            }
            return FALSE;
        }

        if (ProcessContainer::dup2_available())
        {
            $this->client_return('OK');
            $this->server_echo('OK', $newline);
            $this->socket_close();
        }

        $process->loop_read();
        exit;
    }

    // 结束进程
    public function command_stop($params)
    {
        $jobname = $params['jobname'];
        $newline = !isset($params['newline']) ? TRUE : $params['newline'];

        // 是否是重启进程，如果是，则SOCKET不输出
        $is_restart = (isset($params['is_restart']) && $params['is_restart']);

        $pid = $this->shm_process_getpid($jobname);
        if (!$pid)
        {
            // 进程不存在
            if (!$is_restart)
            {
                $this->client_return('FAILED');
            }
            $this->server_echo("FAILED. (process \"$jobname\" does not exist.)");
            return FALSE;
        }

        // 强制结束子进程
        if (!posix_kill($pid, SIGKILL))
        {
            if (!$is_restart)
            {
                $this->client_return('FAILED');
            }
            $this->server_echo("FAILED. (failed to kill process \"$jobname\".)");
            return FALSE;
        }

        if (!$is_restart)
        {
            $this->client_return('OK');
        }
        $this->server_echo('OK', $newline);
        
        return TRUE;
    }

    // 重启进程
    public function command_restart($params)
    {
        $jobname = $params['jobname'];

        $pid = $this->shm_process_getpid($jobname);
        if (!$pid)
        {
            $this->client_return('FAILED');
            $this->server_echo("FAILED. (process \"$jobname\" does not exist.)");
            return FALSE;
        }

        $this->server_echo('Stopping "'.$jobname.'"...');
        if (!$this->command_stop(array('jobname'=>$jobname, 'is_restart'=>TRUE, 'newline'=>FALSE)))
        {
            $this->client_return('FAILED');
            return FALSE;
        }
        
        // 等待进程完全退出
        $this->server_echo('Process "'.$jobname.'" exiting...');
        while ($this->shm_process_getpid($jobname)) sleep(1);

        $this->server_echo('Starting "'.$jobname.'"...');
        return $this->command_start(array('jobname'=>$jobname, 'newline'=>FALSE));
    }

    // 查看进程状态
    public function command_status($params)
    {
        $jobname = $params['jobname'];

        $pid = $this->shm_process_getpid($jobname);
        if (!$pid)
        {
            // 进程不存在
            $this->client_return('FAILED');
            $this->server_echo("DOWN. (process \"$jobname\" does not exist.)");
            return FALSE;
        }
       
        if ($this->process_exists($pid))
        {
            $this->client_return('OK', 'UP');
            $this->server_echo('UP');
        }
        else
        {
            $this->client_return('OK', 'DOWN');
            $this->server_echo('DOWN');
        }
        
        return TRUE;
    }

    // 查看所有进程状态
    public function command_statusall($params)
    {
        $pids = $this->shm->get_var('pids');

        $statuses = array();
        foreach ($pids as $jobname=>$pid)
        {
            $statuses[$jobname] = $this->process_exists($pid) ? 'UP' : 'DOWN';
        }
        $this->client_return('OK', $statuses);
        
        return $statuses;
    }

    // 读取进程输出缓冲区
    public function command_read($params)
    {
        $jobname = $params['jobname'];

        $pid = $this->shm_process_getpid($jobname);
        if (!$pid)
        {
            // 进程不存在
            $this->client_return('FAILED');
            $this->server_echo("NULL. (process \"$jobname\" does not exist.)");
            return FALSE;
        }

        // 取出共享内存中的输出缓冲
        $output_buffer = $this->shm->get_var('ob_'.$jobname);
        if ($output_buffer)
        {
            $this->client_return('OK', implode("\n", $output_buffer));
        }
        else
        {
            $this->client_return('OK', '');
        }
        
        return $output_buffer;
    }

    // 读取进程内存占用量
    public function command_mem($params)
    {
        $jobname = $params['jobname'];

        $pid = $this->shm_process_getpid($jobname);
        if (!$pid)
        {
            // 进程不存在
            $this->client_return('FAILED');
            $this->server_echo("NULL. (process \"$jobname\" does not exist.)");
            return FALSE;
        }

        $this->client_return('OK', $this->memory_get_usage($pid));
    }

    // 读取所有进程的内存占用量
    public function command_memall($params)
    {
        $pids = $this->shm->get_var('pids');

        $usages = array();
        foreach ($pids as $jobname=>$pid)
        {
            $usages[$jobname] = $this->memory_get_usage($pid);
        }
        $this->client_return('OK', $usages);
    }

    // 读取服务器输出缓冲区
    public function command_serverread($params)
    {
        $this->client_return('OK', implode('', $this->server_ob));
    }

    // 读取服务器内存占用量
    public function command_servermem($params)
    {
        $pid = getmypid();
        $this->client_return('OK', $this->memory_get_usage($pid));
    }

    // 未知指令
    public function command_unknown($command)
    {
        $this->client_return('UNKNOWN');
        $this->server_echo("UNKNOWN. (unknown command \"$command\".)");
        return FALSE;
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
            $this->server_echo("shm put_var() failed.");
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
        $message = "Process \"{$this->jobname}\" exit with code $status.";
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
    
    private function socket_close()
    {
        if (!$this->socket) return FALSE;
        return socket_close($this->socket);
    }
    
    // 返回客户端
    public function client_return($code, $data=NULL)
    {
        if (!$this->cnt) return FALSE;
        $result = array('code'=>$code);
        if (!is_null($data)) $result['data'] = $data;

        $result_str = json_encode($result);
        $length = pack('I', strlen($result_str));

        $package = $length.$result_str;
        $package_length = strlen($package);

        while (TRUE)
        {
            $sent = socket_write($this->cnt, $package, $package_length);

            if ($sent === FALSE) return FALSE;
            if ($sent == $package_length) return TRUE;

            $package = substr($package, $sent);
            $package_length -= $sent;
        }
    }

    // 服务器输出
    public function server_echo($str, $newline=TRUE)
    {
        if ($newline) $str = "\n".$str;
        $str = $this->time_prefix($str);

        $this->server_ob[] = $str;
        $this->server_ob = array_slice($this->server_ob, -20, 20);
        $server_logpath = $this->log_path.'/server/';
        $server_daily_logpath = $server_logpath.'/'.date('Ymd');
        if (!is_dir($server_logpath)) mkdir($server_logpath, 0777);
        if (!is_dir($server_daily_logpath)) mkdir($server_daily_logpath, 0777);
        $server_logfile = $server_daily_logpath.'/'.date('YmdH').'.log';
        file_put_contents($server_logfile, $str, FILE_APPEND);

        if (!$this->server_muted && posix_ttyname(STDOUT)) echo $str;
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
    public function process_exists($pid)
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
    public function memory_get_usage($pid) 
    {
        $status = file_get_contents("/proc/{$pid}/status");
        if (!$status) return 0;
        preg_match('/VmRSS\:\s+(\d+)\s+kB/', $status, $matches);
        $vmRSS = $matches[1];
        return $vmRSS;
    }
    
}