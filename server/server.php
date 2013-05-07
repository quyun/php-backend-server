<?php
if (php_sapi_name() != 'cli') die('Server must run under cli mode!');

require_once(dirname(__FILE__) . '/config.php');
require_once(dirname(__FILE__) . '/ShareMemory.class.php');

// 开始监听
if (!($sock = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP)))
{
	echo "socket_create() failed.\n";
	exit;
}

if (!socket_set_option($sock, SOL_SOCKET, SO_REUSEADDR, 1))
{
	echo "socket_set_option() failed.\n";
	exit;
}

if (!($ret = @socket_bind($sock, $server_ip, $server_port)))
{
	echo "socket_bind() failed.\n";
	exit;
}

if (!($ret = @socket_listen($sock, 5)))
{
	echo "socket_listen() failed.\n";
	exit;
}

// 保存服务器输出内容
$server_output_buffer = array();

server_echo("Backend server starting, binding $server_ip:$server_port.\n");

// 初始化共享内存
$shm = new SharedMemory('shm_key_of_backend_server_'.$server_ip.'_'.$server_port);
if (!$shm->attach())
{
	server_echo("shm attach() failed.\n");
	exit;
}

// 初始化全局资源表
$processes = array();
$pipes = array();
$extra_settings = array();
$child_pids = array();
$pstopping = array();	// 进程结束标志


// 循环处理
while (TRUE)
{
	// 等待连接
	server_echo("\nWaiting for new command...\n");
	if (!($cnt = @socket_accept($sock)))
	{
		server_echo("socket_accept() failed.\n");
		break;
	}
	
	// 读取输入
	if (!($input = @socket_read($cnt, 1024))) {
		server_echo("socket_read() failed.\n");
		break 2;
	}
	
	// 分析并执行命令
	$input_arr = explode(' ', trim($input), 2);
	if (count($input_arr) > 1)
	{
		list($cmd, $params) = explode(' ', trim($input), 2);
	}
	else
	{
		$cmd = $input;
		$params = '';
	}

	server_echo(date('Y-m-d H:i:s e')."\n$cmd $params\n");

	switch ($cmd)
	{
	case 'STATUS':	// 获取进程状态
		$jobname = $params;
		backend_status($jobname);
		break;

	case 'START':	// 开启进程
		$params = explode(' ', $params);
		$params_len = count($params);
		if ($params_len == 1)
		{
			// 没有输入程序路径
			socket_write($cnt, 'FAILED');
			server_echo("FAILED. (no program path input.)\n");
			break;
		}

		$jobname = array_shift($params);
		$script_cmd = array_shift($params);
		$buffer_lines = array_pop($params);
		$script_params = implode(' ', $params);

		backend_start($jobname, $script_cmd, $script_params, $buffer_lines);
		break;

	case 'STOP':	// 结束进程
		list($jobname, $graceful) = explode(' ', $params);
		backend_stop($jobname, $graceful);
		break;
	
	case 'RESTART':	// 重启进程
		list($jobname, $graceful) = explode(' ', $params);
		$setting = isset($extra_settings[$jobname]) ? $extra_settings[$jobname] : FALSE;
		if ($setting)
		{
			if (backend_stop($jobname, $graceful, TRUE))
			{
				backend_start($jobname, $setting['scriptcmd'], $setting['scriptparams'], $setting['bufferlines']);
			}
			else
			{
				socket_write($cnt, 'FAILED');
			}
		}
		else
		{
			socket_write($cnt, 'FAILED');
			server_echo("FAILED. (process $jobname does not exist.)\n");
		}
		break;

	case 'READ':	// 读取进程缓冲
		$jobname = $params;
		backend_read($jobname);
		break;

	case 'SERVERMEM':	// 读取服务器内存占用情况
		socket_write($cnt, my_memory_get_usage());
		break;

	case 'SERVERREAD':	// 读取服务器输出缓冲
		socket_write($cnt, implode('', $server_output_buffer));
		break;
	}
}

socket_close($sock);


// 获取运行当前脚本的PHP解析器路径
function get_php_path()
{
	return readlink('/proc/'.getmypid().'/exe');
}

// 强制结束进程
function force_stop_process($jobname)
{
	stop_process($jobname, FALSE);
}

// 优雅结束进程
function graceful_stop_process($jobname)
{
	stop_process($jobname, TRUE);
}

// 结束进程，并释放相关资源
function stop_process($jobname, $graceful)
{
	global $shm;
	global $processes;
	global $pipes;
	global $extra_settings;
	global $child_pids;

	// 关闭输出管道
	fclose($pipes[$jobname][1]);

	// 删除共享内存中的缓冲区数据
	$shm->remove_var($jobname);

	if (!$graceful) {
		// 强制结束proc_open打开的进程
		$status = proc_get_status($processes[$jobname]);
		exec('kill -9 '.$status['pid'].' 2>/dev/null >&- >/dev/null');
	}
	
	proc_terminate($processes[$jobname]);
	proc_close($processes[$jobname]);

//	if (!$graceful) {
		// 杀死子进程
		exec('kill -9 '.$child_pids[$jobname].' 2>/dev/null >&- >/dev/null');
//	}

	
	unset($processes[$jobname], $pipes[$jobname], $extra_settings[$jobname], $child_pids[$jobname]);
}

// 查看进程状态
function backend_status($jobname)
{
	global $processes;
	global $pipes;
	global $extra_settings;
	global $cnt;
	global $pstopping;

	if (!isset($processes[$jobname]))
	{
		// 进程不存在
		socket_write($cnt, 'DOWN');
		server_echo("DOWN. (process $jobname does not exist.)\n");
		return FALSE;
	}

	$status = proc_get_status($processes[$jobname]);
	if (!$status)
	{
		force_stop_process($jobname);
		socket_write($cnt, 'DOWN');
		server_echo("DOWN. (proc_get_status failed.)\n");
		return FALSE;
	}
	
	if ($status['running'])
	{
		socket_write($cnt, 'UP');
		server_echo("UP\n");
	}
	else
	{
		socket_write($cnt, 'DOWN');
		server_echo("DOWN\n");
	}
	
	return TRUE;
}

// 开启进程
function backend_start($jobname, $script_cmd, $script_params, $buffer_lines)
{
	global $processes;
	global $pipes;
	global $extra_settings;
	global $child_pids;
	global $cnt;
	global $shm;
	
	// 检查进程名是否已经存在
	if (isset($processes[$jobname]))
	{
		// 取进程状态
		$status = proc_get_status($processes[$jobname]);
		if (!$status)
		{
			force_stop_process($jobname);
			socket_write($cnt, 'FAILED');
			server_echo("FAILED. (proc_get_status failed.)\n");
			return FALSE;
		}
		
		// 检查进程是否正在运行
		if ($status['running'])
		{
			socket_write($cnt, 'FAILED');
			server_echo("FAILED. (process $jobname has already exist.)\n");
			return FALSE;
		}
		else
		{
			// 停止
			force_stop_process($jobname);
		}
	}

	// 读取源文件
//	if (($source_code = @file_get_contents($script_cmd)) === FALSE)
//	{
//		// 读取失败
//		socket_write($cnt, 'FAILED');
//		server_echo("FAILED. ($script_cmd does not exist.)\n");
//		return FALSE;
//	}
	if (!file_exists($script_cmd))
	{
		// 文件不存在
		socket_write($cnt, 'FAILED');
		server_echo("FAILED. ($script_cmd does not exist.)\n");
		return FALSE;
	}

	// 执行后台进程
	$descriptorspec = array(
		0 => array("pipe", "r"),
		1 => array("pipe", "w"),
		2 => array("file", realpath(dirname(__FILE__).'/../../data/log/server')."/{$jobname}.error.log", "a")
	);

	$php_path = get_php_path();
	$processes[$jobname] = proc_open("{$php_path} {$script_cmd} {$script_params}", $descriptorspec, $pipes[$jobname], dirname($script_cmd));

	if (!is_resource($processes[$jobname]))
	{
		socket_write($cnt, 'FAILED');
		server_echo("FAILED. (proc_open failed.)\n");
		return FALSE;
	}

	// 非阻塞模式读取
	$output_pipe = $pipes[$jobname][1];
	stream_set_blocking($output_pipe, 0);

	// 记录缓冲区行数
	$extra_settings[$jobname] = array(
		'bufferlines' => $buffer_lines,
		'scriptcmd'   => $script_cmd,
		'scriptparams'=> $script_params,
	);

	// 创建共享变量用于存储输出缓冲
	$output_buffer = array();
	if (!$shm->put_var($jobname, $output_buffer))
	{
		socket_write($cnt, 'FAILED');
		server_echo("shm put_var() failed.\n");
		return FALSE;
	}

//	fwrite($pipes[$jobname][0], $source_code);
	fclose($pipes[$jobname][0]);

	// 新建一个子进程用于读取进程输出
	$pid = pcntl_fork();
	if ($pid == -1)
	{
		socket_write($cnt, 'FAILED');
		server_echo("pcntl_fork() failed.\n");
		return FALSE;
	}
	else if ($pid)	// 父进程
	{
		$child_pids[$jobname] = $pid;

		socket_write($cnt, 'OK');
		server_echo("OK\n");
		
		pcntl_waitpid($pid, $status);
	}
	else	// 子进程
	{
		// 新建一个孙子进程用于避免僵尸进程
		$t_pid = pcntl_fork();
		if ($t_pid == -1)
		{
			socket_write($cnt, 'FAILED');
			server_echo("pcntl_fork() failed.\n");
			return FALSE;
		}
		else if ($t_pid)	// 父进程
		{
			exit;
		}
		else
		{
			// 取出共享内存中的输出缓冲
			$output_buffer = $shm->get_var($jobname);

			while (TRUE)
			{
				$read   = array($output_pipe);
				$write  = NULL;
				$except = NULL;

				if (FALSE === ($num_changed_streams = stream_select($read, $write, $except, 3)))
				{
					continue;
				}
				elseif ($num_changed_streams > 0)
				{
					$output = stream_get_contents($output_pipe);

					// 缓存输出
					if ($output !== '')
					{
						$buffer_lines = $extra_settings[$jobname]['bufferlines'] + 1;
						$output_lines = explode("\n", $output);
						$old_len = count($output_buffer);
						if ($old_len > 0)
						{
							$output_buffer[$old_len-1] .= array_shift($output_lines);
						}
						$output_buffer = array_merge($output_buffer, $output_lines);
						$output_buffer = array_slice($output_buffer, -$buffer_lines, $buffer_lines);

						// 更新共享变量
						if (!$shm->put_var($jobname, $output_buffer))
						{
							server_echo("shm put_var() failed.\n");
						}
					}
					else
					{
						break;
					}
				}
			}
			exit;
		}
	}
	
	return TRUE;
}

// 结束进程
// $is_restart 是否是重启进程，如果是，则SOCKET不输出
function backend_stop($jobname, $graceful=FALSE, $is_restart=FALSE)
{
	// 优雅方式结束，则直接设置进程结束标志即可
	if ($graceful)
	{
		global $pstopping;
		$pstopping[$jobname] = TRUE;
		
		server_echo("Process $jobname receive graceful stop signal.\n");
	}
	else
	{
		// 清空进程标志
		if (isset($pstopping[$jobname]))
		{
			unset($pstopping[$jobname]);
		}
	}

	global $processes;
	global $pipes;
	global $extra_settings;
	global $child_pids;
	global $cnt;
	global $shm;

	if (!isset($processes[$jobname]))
	{
		// 进程不存在
		if (!$is_restart)
		{
			socket_write($cnt, 'FAILED');
		}
		server_echo("FAILED. (process $jobname does not exist.)\n");
		return FALSE;
	}

	$status = proc_get_status($processes[$jobname]);
	if (!$status)
	{
		force_stop_process($jobname);
		if (!$is_restart)
		{
			socket_write($cnt, 'FAILED');
		}
		server_echo("FAILED. (proc_get_status failed.)\n");
		return FALSE;
	}

	if ($graceful)
	{
		graceful_stop_process($jobname);
	}
	else
	{
		force_stop_process($jobname);
	}

	if (!$is_restart)
	{
		socket_write($cnt, 'OK');
	}
	server_echo("OK\n");
	
	return TRUE;
}

// 读取进程输出缓冲区
function backend_read($jobname)
{
	global $processes;
	global $pipes;
	global $extra_settings;
	global $cnt;
	global $shm;

	if (!isset($processes[$jobname]))
	{
		// 进程不存在
		socket_write($cnt, "\0");
		server_echo("NULL. (process does not exist.)\n");
		return FALSE;
	}

	$status = proc_get_status($processes[$jobname]);
	if (!$status)
	{
		force_stop_process($jobname);
		socket_write($cnt, "\n");
		server_echo("NULL. (proc_get_status failed.)\n");
		return FALSE;
	}

	// 取出共享内存中的输出缓冲
	$output_buffer = $shm->get_var($jobname);
	if ($output_buffer)
	{
		socket_write($cnt, implode("\n", $output_buffer)."\n");
	}
	else
	{
		socket_write($cnt, "\n");
	}
	
	return TRUE;
}

// 服务器输出
function server_echo($str)
{
	global $server_output_buffer;

	$server_output_buffer[] = $str;
	$server_output_buffer = array_slice($server_output_buffer, -20, 20);

	echo $str;
}

// 返回进程占用的实际内存值
function my_memory_get_usage() 
{
	$pid = getmypid();
	$status = file_get_contents("/proc/{$pid}/status");
	preg_match('/VmRSS\:\s+(\d+)\s+kB/', $status, $matches);
	$vmRSS = $matches[1];
	return $vmRSS*1024;
}
