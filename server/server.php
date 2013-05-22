<?php

require_once(dirname(__FILE__) . '/config.php');
require_once(dirname(__FILE__) . '/ShareMemory.class.php');

$jobname = 'server';

$php_path = get_php_path();
$script_cmd = '/var/www/php-backend/server/serverbk.php';

$descriptorspec = array(
	0 => array("pipe", "r"),
	1 => array("pipe", "w"),
	2 => array("file", realpath(dirname(__FILE__).'/../../data/log/server')."/{$jobname}.error.log", "a")
);

$pid = pcntl_fork();

if ($pid == -1)
{
	exit('pcntl_fork error!');
}
else if ($pid)	// 父进程
{	
	pcntl_waitpid($pid, $status);
}
else	// 子进程
{
	$ppid = pcntl_fork();
	if ($ppid == -1)
	{
		exit('pcntl_fork error!');
	}
	else if ($ppid)
	{	
		//hooks钩子脚本
		if (isset($hooks) && !empty($hooks))
		{
			//server_echo("Hooks Start!\n");
			sleep(1);
			foreach ($hooks as $key=>$hook)
			{
				include_once($hook);
				//server_echo("$key: $hook Start Success!\n");
			}
			//server_echo("Hooks Completed!\n");
		}
		exit;
	}
	else
	{
		include_once($script_cmd);
	}
}




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
	$processes = $shm->get_var('processes');
	$extra_settings = $shm->get_var('extra_settings');
	$child_pids = $shm->get_var('child_pids');

	// 删除共享内存中的缓冲区数据
	$shm->remove_var($jobname);

	// 强制结束proc_open打开的进程
	exec('kill -9 '.$processes[$jobname].' 2>/dev/null >&- >/dev/null');
	exec('kill -9 '.$child_pids[$jobname].' 2>/dev/null >&- >/dev/null');
	
	unset($processes[$jobname], $extra_settings[$jobname], $child_pids[$jobname]);
	$shm->put_var('processes', $processes);
	$shm->put_var('extra_settings', $extra_settings);
	$shm->put_var('child_pids', $child_pids);
}

// 查看进程状态
function backend_status($jobname)
{
	global $cnt;
	global $shm;
	$processes = $shm->get_var('processes');
	
	if (!isset($processes[$jobname]))
	{
		// 进程不存在
		socket_write($cnt, 'DOWN');
		server_echo("DOWN. (process $jobname does not exist.)\n");
		return FALSE;
	}

	$status = my_proc_get_status($processes[$jobname]);
	if (file_exists('/proc/'.$processes[$jobname]))
		$status = true;
	else
		$status = false;

	if (!$status)
	{
		force_stop_process($jobname);
		socket_write($cnt, 'DOWN');
		server_echo("DOWN. (proc_get_status failed.)\n");
		return FALSE;
	}
	
	if ($status)
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
function backend_start($jobname, $script_cmd, $script_params, $buffer_lines, $writelog, $autostart, $logpath)
{
	global $sock;
	global $cnt;
	global $shm;
	$processes = $shm->get_var('processes');
	$extra_settings = $shm->get_var('extra_settings');
	$child_pids = $shm->get_var('child_pids');
	
	// 检查进程名是否已经存在
	if (isset($processes[$jobname]))
	{
		// 取进程状态
		$status = my_proc_get_status($processes[$jobname]);
		if (!$status)
		{
			force_stop_process($jobname);
			socket_write($cnt, 'FAILED');
			server_echo("FAILED. (proc_get_status failed.)\n");
			return FALSE;
		}
		
		// 检查进程是否正在运行
		if ($status)
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

	if (!file_exists($script_cmd))
	{
		// 文件不存在
		socket_write($cnt, 'FAILED');
		server_echo("FAILED. ($script_cmd does not exist.)\n");
		return FALSE;
	}

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
		$shm->put_var('child_pids', $child_pids);
		
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
			socket_close($sock);
			
			// 执行后台进程
			$descriptorspec = array(
				0 => array("pipe", "r"),
				1 => array("pipe", "w"),
				2 => array("file", realpath(dirname(__FILE__).'/../../data/log/server')."/{$jobname}.error.log", "a")
			);
			
			$php_path = get_php_path();
			$resource = proc_open("{$php_path} {$script_cmd} {$script_params}", $descriptorspec, $pipes[$jobname], dirname($script_cmd));
			$tmp_status = proc_get_status($resource);
			$processes[$jobname] = $tmp_status['pid'];
			$shm->put_var('processes', $processes);
			
			if (!isset($processes[$jobname]))
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
				'writelog'	  => $writelog,
				'autostart'	  => $autostart,
			);
			$shm->put_var('extra_settings', $extra_settings);
			
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
			
			// 取出共享内存中的输出缓冲
			$output_buffer = $shm->get_var($jobname);

			while (TRUE)
			{
				$read   = array($output_pipe);
				$write  = NULL;
				$except = NULL;

				if (FALSE === ($num_changed_streams = stream_select($read, $write, $except, 3)))
				{
					$jobstatus = backend_status($jobname);
					if ($jobstatus !== 'UP')
					{
						// 关闭输出管道
						fclose($pipes[$jobname][1]);
						// 删除共享内存中的缓冲区数据
						$shm->remove_var($jobname);
						//回收资源
						unset($processes[$jobname], $extra_settings[$jobname], $child_pids[$jobname]);
						$shm->put_var('processes', $processes);
						$shm->put_var('extra_settings', $extra_settings);
						pcntl_waitpid($processes[$jobname], $status);
						exit;
					}
					else
						continue;
				}
				elseif ($num_changed_streams > 0)
				{
					$output = stream_get_contents($output_pipe);
					
					// 缓存输出
					if ($output !== '')
					{
						//把进程所有输出都写日志
						if ($writelog)
						{
							if (!is_dir($logpath.$jobname.'/'))
								mkdir($logpath.$jobname.'/', 0777);
							if (!is_dir($logpath.$jobname.'/'.date('Ymd').'/'))
								mkdir($logpath.$jobname.'/'.date('Ymd').'/', 0777);
							file_put_contents($logpath.$jobname.'/'.date('Ymd').'/'.$jobname.'_'.date('YmdH'), $output."\n", FILE_APPEND);
						}
						
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
	global $shm;
	global $cnt;
	$processes = $shm->get_var('processes');
	$pstopping = $shm->get_var('pstopping');
	// 优雅方式结束，则直接设置进程结束标志即可
	if ($graceful)
	{
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
	$shm->put_var('pstopping', $pstopping);

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

	$status = my_proc_get_status($processes[$jobname]);
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
		server_echo("OK\n");
	}
	
	return TRUE;
}

// 读取进程输出缓冲区
function backend_read($jobname)
{
	global $cnt;
	global $shm;
	$processes = $shm->get_var('processes');
	
	if (!isset($processes[$jobname]))
	{
		// 进程不存在
		socket_write($cnt, "\0");
		server_echo("NULL. (process does not exist.)\n");
		return FALSE;
	}

	$status = my_proc_get_status($processes[$jobname]);
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


function my_proc_get_status($pid)
{
	if (file_exists('/proc/'.$pid))
		return true;
	else
		return false;
}

