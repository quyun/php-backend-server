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

    // 添加进程
    // 返回：OK（成功）、FAILED（失败）
    // $jobname         进程名称
    // $command         程序路径
    // $setting         程序执行设置
    //    * params      程序参数
    //    * buffersize  缓冲区行数
    //    * writelog    是否将进程输出写入日志
    //    * autostart   是否随服务器启动（autostart插件参数）
    public function add($jobname, $command, $setting=array())
    {
    	$p = array_merge($setting, array(
            'jobname' => $jobname,
            'command' => $command,
        ));
        return $this->_cmd('ADD', $p);
    }

    // 删除进程
    // 返回：OK（成功）、FAILED（失败）
    public function delete($jobname, $command, $setting=array())
    {
        return $this->_cmd('DELETE', array(
            'jobname' => $jobname,
        ));
    }

    // 更新进程
    // 返回：OK（成功）、FAILED（失败）
    // $jobname         进程名称
    // $setting         程序执行设置
    //    * command		程序路径
    //    * params      程序参数
    //    * buffersize  缓冲区行数
    //    * writelog    是否将进程输出写入日志
    //    * autostart   是否随服务器启动（autostart插件参数）
    public function update($jobname, $setting=array())
    {
    	$p = array_merge($setting, array(
            'jobname' => $jobname,
        ));
        return $this->_cmd('UPDATE', $p);
    }

    // 查询单个进程信息
    // 返回进程信息数组
    public function query($jobname)
    {
        return json_decode($this->_cmd('QUERY', array(
            'jobname' => $jobname,
        )), TRUE);
    }

    // 查询所有进程信息
    // 返回所有进程的信息数组
    public function queryall()
    {
        return json_decode($this->_cmd('QUERYALL'), TRUE);
    }
    
    // 查询进程状态
    // 返回：UP（正常）、DOWN（当机）
    public function status($jobname)
    {
        return $this->_cmd('STATUS', array(
            'jobname' => $jobname,
        ));
    }
    
    // 查询所有进程状态
    // 返回：所有进程状态数组
    public function statusall()
    {
        return $this->_cmd('STATUSALL');
    }
    
    // 启动进程
    // 返回：OK（成功）、FAILED（失败）
    public function start($jobname)
    {
        return $this->_cmd('START', array(
            'jobname' => $jobname,
        ));
    }
    
    // 结束进程
    // 返回：OK（成功）、FAILED（失败）
    public function stop($jobname)
    {
        return $this->_cmd('STOP', array(
            'jobname' => $jobname,
        ));
    }
    
    // 重启进程
    // 返回：OK（成功）、FAILED（失败）
    public function restart($jobname)
    {
        return $this->_cmd('RESTART', array(
            'jobname' => $jobname,
        ));
    }
    
    // 读取进程输出缓冲
    // 返回：进程输出缓冲区内容
    public function read($jobname)
    {
    	$result = $this->_cmd('READ', array(
            'jobname' => $jobname,
        ));
        return substr($result, 0, -1);
    }
    
    // 读取进程服务器的输出缓冲
    // 返回：进程服务器输出缓冲区内容
    public function servermem()
    {
        return $this->_cmd('SERVERMEM');
    }
    
    // 读取进程服务器的输出缓冲
    // 返回：进程服务器输出缓冲区内容
    public function serverread()
    {
        return $this->_cmd('SERVERREAD');
    }

    // 执行命令并返回结果
    private function _cmd($cmd, $params=NULL)
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

        $primitive = $params ? "$cmd ".json_encode($params) : $cmd;
        socket_write($sock, $primitive);
        $rt = socket_read($sock, 10240);
        socket_close($sock);

        return $rt;
    }
}
