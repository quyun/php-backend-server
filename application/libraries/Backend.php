<?php
/**********************************
 * 后端进程管理器客户端操作类
 *
 * author: hilyjiang
 * version: 1.0
 **********************************/
 
Class Backend
{
	function __construct()
	{
		// 默认设置
		$this->server_ip = '127.0.0.1';
		$this->server_port = 13123;
	}

	// 初始化服务器信息
	public function init($server_ip, $server_port)
	{
		$this->server_ip = $server_ip;
		$this->server_port = $server_port;
	}
	
	// 查询进程状态
	// 返回：UP（正常）、DOWN（当机）
	public function status($jobname)
	{
		return $this->_cmd("STATUS {$jobname}");
	}
	
	// 开启新进程
	// 返回：OK（成功）、FAILED（失败）
	// $jobname			进程名称
	// $script_cmd		脚本路径
	// $buffer_line		缓冲区行数
	// $writelog		是否把所有输出都写入日志
	// $autostart		随管理进程启动而启动
	public function start($jobname, $script_cmd, $buffer_lines=20, $writelog=FALSE, $autostart=FALSE)
	{
		return $this->_cmd("START {$jobname} {$script_cmd} {$buffer_lines} {$writelog} {$autostart}");
	}
	
	// 结束进程
	// 返回：OK（成功）、FAILED（失败）
	public function stop($jobname, $graceful=FALSE)
	{
		$p2 = $graceful ? 1 : 0;
		return $this->_cmd("STOP {$jobname} {$p2}");
	}
	
	// 重启进程
	// 返回：OK（成功）、FAILED（失败）
	public function restart($jobname, $graceful=FALSE)
	{
		$p2 = $graceful ? 1 : 0;
		return $this->_cmd("RESTART {$jobname} {$p2}");
	}
	
	// 读取进程输出缓冲
	// 返回：进程输出缓冲区内容
	public function read($jobname)
	{
		return substr($this->_cmd("READ {$jobname}"), 0, -1);
	}
	
	// 读取进程服务器的输出缓冲
	// 返回：进程服务器输出缓冲区内容
	public function servermem()
	{
		return $this->_cmd("SERVERMEM");
	}
	
	// 读取进程服务器的输出缓冲
	// 返回：进程服务器输出缓冲区内容
	public function serverread()
	{
		return $this->_cmd("SERVERREAD");
	}

	// 执行命令并返回结果
	private function _cmd($primitive)
	{
		if (!($sock = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP)))
		{
			//echo "socket_create() failed.\n";
			return FALSE;
		}

		if (!@socket_connect($sock, $this->server_ip, $this->server_port))
		{
			//echo "socket_connect() failed.\n";

			return FALSE;
		}

		socket_write($sock, $primitive);
		$rt = socket_read($sock, 10240);
		socket_close($sock);

		return $rt;
	}
}
