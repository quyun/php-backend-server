<?php
if (php_sapi_name() != 'cli') die('Server must run under cli mode!');

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

/////////////////////////////
if ($shm->has_var('processes'))
	$processes = $shm->get_var('processes');
else
{
	$processes = array();
	$shm->put_var('processes', $processes);
}

if ($shm->has_var('extra_settings'))
	$extra_settings = $shm->get_var('extra_settings');
else
{
	$extra_settings = array();
	$shm->put_var('extra_settings', $extra_settings);
}

if ($shm->has_var('child_pids'))
	$child_pids = $shm->get_var('child_pids');
else
{
	$child_pids = array();
	$shm->put_var('child_pids', $child_pids);
}

if ($shm->has_var('pstopping'))
	$pstopping = $shm->get_var('pstopping');
else
{
	$pstopping = array();
	$shm->put_var('pstopping', $pstopping);
}


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
		
		$jobname = array_shift($params);		//第一个：进程名称		
		$script_cmd = array_shift($params);		//第二个：进程路径
		$autostart = array_pop($params);		//倒一个：随管理进程而启动
		$writelog = array_pop($params);			//倒二个：是否写日志
		$buffer_lines = array_pop($params);		//倒三个：缓冲区行数
		$script_params = implode(' ', $params);	//其他都为脚本参数

		backend_start($jobname, $script_cmd, $script_params, $buffer_lines, $writelog, $autostart, $logpath);
		break;

	case 'STOP':	// 结束进程
		list($jobname, $graceful) = explode(' ', $params);
		backend_stop($jobname, $graceful);
		break;
	
	case 'RESTART':	// 重启进程
		list($jobname, $graceful) = explode(' ', $params);
		$extra_settings = $shm->get_var('extra_settings');
		$setting = isset($extra_settings[$jobname]) ? $extra_settings[$jobname] : FALSE;
		if ($setting)
		{
			if (backend_stop($jobname, $graceful, TRUE))
			{
				backend_start($jobname, $setting['scriptcmd'], $setting['scriptparams'], $setting['bufferlines'], $setting['writelog'], $setting['autostart'], $logpath);
			}
			else
			{
				socket_write($cnt, 'FAILED');
			}
		}
		else
		{
			socket_write($cnt, 'FAILED');
			server_echo("FAILED later. (process $jobname does not exist.)\n");
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


