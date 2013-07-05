<?php
/**********************************
 * 后端进程管理器客户端操作类
 *
 * author: hilyjiang
 * version: 2013-06-19
 **********************************/
 
Class Backend
{
    function __construct()
    {
        // 默认设置
        $this->server_ip = '127.0.0.1';
        $this->server_port = 13123;
        $this->auth_setting = array();
    }

    /*
     * 初始化服务器信息
     */
    public function init($server_ip, $server_port)
    {
        $this->server_ip = $server_ip;
        $this->server_port = $server_port;
    }

    /*
     * 添加进程
     * 
     * 参数：
     * $jobname         进程名称
     * $command         程序路径
     * $setting         程序执行设置
     *    - params      程序参数
     *    - buffersize  缓冲区行数
     *    - writelog    是否将进程输出写入日志
     *    - autostart   是否随服务器启动（autostart插件参数）
     *    - guard       是否监控该进程，非人为退出后自动启动（guarder插件参数）
     *    - auth        auth插件参数
     *      - username  用户名
     *      - password    密码
     *    剩余值也将作为进程配置项
	 *
	 * 返回值：
     * array('code'=>$code)
     *    - code        'OK', 'FAILED', 'DENIED'（auth插件）
     *
     */
    public function add($jobname, $command, $setting=array())
    {
    	$p = array_merge($this->auth_setting, $setting, array(
            'jobname' => $jobname,
            'command' => $command,
        ));
        return $this->_cmd('ADD', $p);
    }

    /*
     * 删除进程
     * 
     * 参数：
     * $jobname         进程名称
     * $setting         程序执行设置
     *    - auth        auth插件参数
     *      - username  用户名
     *      - password    密码
     *
     * 返回值：
     * array('code'=>$code)
     *    - code        'OK', 'FAILED', 'DENIED'（auth插件）
     *
     */
    public function delete($jobname, $setting=array())
    {
    	$p = array_merge($this->auth_setting, $setting, array(
            'jobname' => $jobname,
        ));
        return $this->_cmd('DELETE', $p);
    }

    /*
     * 更新进程
     *
     * 参数：
     * $jobname         进程名称
     * $setting         程序执行设置
     *    - command		程序路径
     *    - params      程序参数
     *    - buffersize  缓冲区行数
     *    - writelog    是否将进程输出写入日志
     *    - autostart   是否随服务器启动（autostart插件参数）
     *    - auth        auth插件参数
     *      - username  用户名
     *      - password    密码
     *    剩余值也将作为进程配置项
     *
     * 返回值：
     * array('code'=>$code)
     *    - code        'OK', 'FAILED', 'DENIED'（auth插件）
     *
     */
    public function update($jobname, $setting=array())
    {
    	$p = array_merge($this->auth_setting, $setting, array(
            'jobname' => $jobname,
        ));
        return $this->_cmd('UPDATE', $p);
    }

    /*
     * 查询单个进程信息
     *
     * 参数：
     * $jobname         进程名称
     * $setting         程序执行设置
     *    - auth        auth插件参数
     *      - username  用户名
     *      - password    密码
     *
     * 返回值：
     * array('code'=>$code, 'data'=>$data)
     *    - code        'OK', 'FAILED', 'DENIED'（auth插件）
     *    - data        进程信息数组
     *
     */
    public function get($jobname, $setting=array())
    {
    	$p = array_merge($this->auth_setting, $setting, array(
            'jobname' => $jobname,
        ));
        return $this->_cmd('GET', $p);
    }

    /*
     * 查询所有进程信息
     *
     * 参数：
     * $setting         程序执行设置
     *    - auth        auth插件参数
     *      - username  用户名
     *      - password    密码
     *
     * 返回值：
     * array('code'=>$code, 'data'=>$data)
     *    - code        'OK', 'FAILED', 'DENIED'（auth插件）
     *    - data        所有进程的信息数组
     *
     */
    public function getall($setting=array())
    {
        $p = array_merge($this->auth_setting, $setting);
        return $this->_cmd('GETALL', $p);
    }
    
    /*
     * 启动进程
     * 
     * 参数：
     * $setting         程序执行设置
     *    - auth        auth插件参数
     *      - username  用户名
     *      - password    密码
     *
     * 返回值：
     * array('code'=>$code)
     *    - code        'OK', 'FAILED', 'DENIED'（auth插件）
     *
     */
    public function start($jobname, $setting=array())
    {
    	$p = array_merge($this->auth_setting, $setting, array(
            'jobname' => $jobname,
        ));
        return $this->_cmd('START', $p);
    }
    
    /*
     * 结束进程
     *
     * 参数：
     * $setting         程序执行设置
     *    - auth        auth插件参数
     *      - username  用户名
     *      - password    密码
     *
     * 返回值：
     * 
     * array('code'=>$code)
     *    - code        'OK', 'FAILED'
     *
     */
    public function stop($jobname, $setting=array())
    {
    	$p = array_merge($this->auth_setting, $setting, array(
            'jobname' => $jobname,
        ));
        return $this->_cmd('STOP', $p);
    }
    
    /*
     * 重启进程
     *
     * 参数：
     * $setting         程序执行设置
     *    - auth        auth插件参数
     *      - username  用户名
     *      - password    密码
     *
     * 返回值：
     * array('code'=>$code)
     *    - code        'OK', 'FAILED', 'DENIED'（auth插件）
     *
     */
    public function restart($jobname, $setting=array())
    {
    	$p = array_merge($this->auth_setting, $setting, array(
            'jobname' => $jobname,
        ));
        return $this->_cmd('RESTART', $p);
    }
    
    /*
     * 查询进程状态
     *
     * 参数：
     * $setting         程序执行设置
     *    - auth        auth插件参数
     *      - username  用户名
     *      - password    密码
     *
     * 返回值：
     * array('code'=>$code, 'data'=>$data)
     *    - code        'OK', 'FAILED', 'DENIED'（auth插件）
     *    - data        UP（正常）、DOWN（未启动）
     *
     */
    public function status($jobname, $setting=array())
    {
    	$p = array_merge($this->auth_setting, $setting, array(
            'jobname' => $jobname,
        ));
        return $this->_cmd('STATUS', $p);
    }
    
    /*
     * 查询所有进程状态
     *
     * 参数：
     * $setting         程序执行设置
     *    - auth        auth插件参数
     *      - username  用户名
     *      - password    密码
     *
     * 返回值：
     * array('code'=>$code, 'data'=>$data)
     *    - code        'OK', 'FAILED', 'DENIED'（auth插件）
     *    - data        所有进程状态数组
     *
     */
    public function statusall($setting=array())
    {
        $p = array_merge($this->auth_setting, $setting);
        return $this->_cmd('STATUSALL', $p);
    }
    
    /*
     * 读取进程输出缓冲
     *
     * 参数：
     * $setting         程序执行设置
     *    - auth        auth插件参数
     *      - username  用户名
     *      - password    密码
     *
     * 返回值：
     * array('code'=>$code, 'data'=>$data)
     *    - code        'OK', 'FAILED', 'DENIED'（auth插件）
     *    - data        进程输出缓冲区内容
     *
     */
    public function read($jobname, $setting=array())
    {
    	$p = array_merge($this->auth_setting, $setting, array(
            'jobname' => $jobname,
        ));
    	return $this->_cmd('READ', $p);
    }
    
    /*
     * 查询进程内存占用量
     *
     * 参数：
     * $setting         程序执行设置
     *    - auth        auth插件参数
     *      - username  用户名
     *      - password    密码
     *
     * 返回值：
     * array('code'=>$code, 'data'=>$data)
     *    - code        'OK', 'FAILED', 'DENIED'（auth插件）
     *    - data        内存占用量，单位为 kB
     *
     */
    public function mem($jobname, $setting=array())
    {
    	$p = array_merge($this->auth_setting, $setting, array(
            'jobname' => $jobname,
        ));
        return $this->_cmd('MEM', $p);
    }
    
    /*
     * 查询所有进程的内存占用量
     *
     * 参数：
     * $setting         程序执行设置
     *    - auth        auth插件参数
     *      - username  用户名
     *      - password    密码
     *
     * 返回值：
     * array('code'=>$code, 'data'=>$data)
     *    - code        'OK', 'FAILED', 'DENIED'（auth插件）
     *    - data        所有进程的内存占用量数组，单位为 kB
     *
     */
    public function memall($setting=array())
    {
        $p = array_merge($this->auth_setting, $setting);
        return $this->_cmd('MEMALL', $p);
    }
    
    /*
     * 查询进程服务器的内存占用量
     *
     * 参数：
     * $setting         程序执行设置
     *    - auth        auth插件参数
     *      - username  用户名
     *      - password    密码
     *
     * 返回值
     * array('code'=>$code, 'data'=>$data)
     *    - code        'OK', 'FAILED', 'DENIED'（auth插件）
     *    - data        进程服务器的内存占用量，单位为kB
     *
     */
    public function servermem($setting=array())
    {
        $p = array_merge($this->auth_setting, $setting);
        return $this->_cmd('SERVERMEM', $p);
    }
    
    /*
     * 读取进程服务器的输出缓冲
     * 
     * 参数：
     * $setting         程序执行设置
     *    - auth        auth插件参数
     *      - username  用户名
     *      - password    密码
     *
     * 返回值：
     * array('code'=>$code, 'data'=>$data)
     *    - code        'OK', 'FAILED', 'DENIED'（auth插件）
     *    - data        进程服务器输出缓冲区内容
     *
     */
    public function serverread($setting=array())
    {
        $p = array_merge($this->auth_setting, $setting);
        return $this->_cmd('SERVERREAD', $p);
    }

    /*
     * 初始化用户名/密码
     */
    public function set_auth($username, $password)
    {
        $this->auth_setting = array(
            'auth' => array(
                'username' => $username,
                'password' => $password,
            )
        );
    }
    
    /*
     * 获取是否启用身份验证
     * 
     * 参数：
     * $setting         程序执行设置
     *    - auth        auth插件参数
     *      - username  用户名
     *      - password    密码
     *
     * 返回值：
     * array('code'=>$code, 'data'=>$data)
     *    - code        'OK', 'FAILED', 'DENIED'（auth插件）
     *    - data        是否启用身份验证, TRUE/FALSE
     *
     */
    public function auth_getenable($setting=array())
    {
        $p = array_merge($this->auth_setting, $setting);
        return $this->_cmd('AUTH.GETENABLE', $p);
    }
    
    /*
     * 设置身份验证启用/禁用
     * 
     * 参数：
     * $enable          TRUE：启用 FALSE：禁用
     * $setting         程序执行设置
     *    - auth        auth插件参数
     *      - username  用户名
     *      - password    密码
     *
     * 返回值：
     * array('code'=>$code)
     *    - code        'OK', 'FAILED', 'DENIED'（auth插件）
     *
     */
    public function auth_setenable($enable, $setting=array())
    {
        $p = array_merge($this->auth_setting, $setting, array(
            'enable' => $enable,
        ));
        return $this->_cmd('AUTH.SETENABLE', $p);
    }

    /*
     * 添加用户
     * 
     * 参数：
     * $username        用户名
     * $password        密码
     * $privileges      权限，用逗号分隔，*表示所有权限
     * $setting         程序执行设置&更多用户配置
     *    - auth        auth插件参数
     *      - username  用户名
     *      - password    密码
     *    剩余值将作为用户配置项
     *
     * 返回值：
     * array('code'=>$code)
     *    - code        'OK', 'FAILED', 'DENIED'（auth插件）
     *
     */
    public function auth_add($username, $password, $privileges, $setting=array())
    {
        $p = array_merge($this->auth_setting, $setting, array(
            'username' => $username,
            'password' => $password,
            'privileges' => $privileges,
        ));
        return $this->_cmd('AUTH.ADD', $p);
    }

    /*
     * 删除用户
     * 
     * 参数：
     * $username        用户名
     * $setting         程序执行设置
     *    - auth        auth插件参数
     *      - username  用户名
     *      - password    密码
     *
     * 返回值：
     * array('code'=>$code)
     *    - code        'OK', 'FAILED', 'DENIED'（auth插件）
     *
     */
    public function auth_delete($username, $setting=array())
    {
        $p = array_merge($this->auth_setting, $setting, array(
            'username' => $username,
        ));
        return $this->_cmd('AUTH.DELETE', $p);
    }

    /*
     * 更新用户信息
     *
     * 参数：
     * $username        用户名
     * $setting         程序执行设置
     *    - password 新密码
     *    - privileges  权限，用逗号分隔，*表示所有权限
     *    - auth        auth插件参数
     *      - username  用户名
     *      - password    密码
     *    剩余值将作为用户配置项
     *
     * 返回值：
     * array('code'=>$code)
     *    - code        'OK', 'FAILED', 'DENIED'（auth插件）
     *
     */
    public function auth_update($username, $setting=array())
    {
        $p = array_merge($this->auth_setting, $setting, array(
            'username' => $username,
        ));
        return $this->_cmd('AUTH.UPDATE', $p);
    }

    /*
     * 查询单个用户信息
     *
     * 参数：
     * $username        用户名
     * $setting         程序执行设置
     *    - auth        auth插件参数
     *      - username  用户名
     *      - password    密码
     *
     * 返回值：
     * array('code'=>$code, 'data'=>$data)
     *    - code        'OK', 'FAILED', 'DENIED'（auth插件）
     *    - data        用户信息数组
     *
     */
    public function auth_get($username, $setting=array())
    {
        $p = array_merge($this->auth_setting, $setting, array(
            'username' => $username,
        ));
        return $this->_cmd('AUTH.GET', $p);
    }

    /*
     * 查询所有用户信息
     *
     * 参数：
     * $setting         程序执行设置
     *    - auth        auth插件参数
     *      - username  用户名
     *      - password    密码
     *
     * 返回值：
     * array('code'=>$code, 'data'=>$data)
     *    - code        'OK', 'FAILED', 'DENIED'（auth插件）
     *    - data        所有用户的信息数组
     *
     */
    public function auth_getall($setting=array())
    {
        $p = array_merge($this->auth_setting, $setting);
        return $this->_cmd('AUTH.GETALL', $p);
    }

    /*
     * 查询进程的日志目录列表
     *
     * 参数：
     * $jobname         进程名称
     * $setting         程序执行设置
     *    - auth        auth插件参数
     *      - username  用户名
     *      - password    密码
     *
     * 返回值：
     * array('code'=>$code, 'data'=>$data)
     *    - code        'OK', 'FAILED', 'DENIED'（auth插件）
     *    - data        日志目录列表
     *
     */
    public function logexplorer_listdir($jobname, $setting=array())
    {
        $p = array_merge($this->auth_setting, $setting, array(
            'jobname' => $jobname,
        ));
        return $this->_cmd('LOGEXPLORER.LISTDIR', $p);
    }

    /*
     * 查询进程某个日志目录下的日志列表
     *
     * 参数：
     * $jobname         进程名称
     * $dirname         日志目录名
     * $setting         程序执行设置
     *    - auth        auth插件参数
     *      - username  用户名
     *      - password    密码
     *
     * 返回值：
     * array('code'=>$code, 'data'=>$data)
     *    - code        'OK', 'FAILED', 'DENIED'（auth插件）
     *    - data        日志文件列表
     *
     */
    public function logexplorer_listfile($jobname, $dirname, $setting=array())
    {
        $p = array_merge($this->auth_setting, $setting, array(
            'jobname' => $jobname,
            'dirname' => $dirname,
        ));
        return $this->_cmd('LOGEXPLORER.LISTFILE', $p);
    }

    /*
     * 读取进程某个日志文件内容
     *
     * 参数：
     * $jobname         进程名称
     * $dirname         日志目录名
     * $filename        日志文件名
     * $setting         程序执行设置
     *    - auth        auth插件参数
     *      - username  用户名
     *      - password    密码
     *
     * 返回值：
     * array('code'=>$code, 'data'=>$data)
     *    - code        'OK', 'FAILED', 'DENIED'（auth插件）
     *    - data        日志内容
     *
     */
    public function logexplorer_get($jobname, $dirname, $filename, $setting=array())
    {
        $p = array_merge($this->auth_setting, $setting, array(
            'jobname' => $jobname,
            'dirname' => $dirname,
            'filename' => $filename,
        ));
        return $this->_cmd('LOGEXPLORER.GET', $p);
    }

    /*
     * 查询服务器的日志目录列表
     *
     * 参数：
     * $setting         程序执行设置
     *    - auth        auth插件参数
     *      - username  用户名
     *      - password    密码
     *
     * 返回值：
     * array('code'=>$code, 'data'=>$data)
     *    - code        'OK', 'FAILED', 'DENIED'（auth插件）
     *    - data        日志目录列表
     *
     */
    public function logexplorer_serverlistdir($setting=array())
    {
        $p = array_merge($this->auth_setting, $setting);
        return $this->_cmd('LOGEXPLORER.SERVERLISTDIR', $p);
    }

    /*
     * 查询服务器某个日志目录下的日志列表
     *
     * 参数：
     * $dirname         日志目录名
     * $setting         程序执行设置
     *    - auth        auth插件参数
     *      - username  用户名
     *      - password    密码
     *
     * 返回值：
     * array('code'=>$code, 'data'=>$data)
     *    - code        'OK', 'FAILED', 'DENIED'（auth插件）
     *    - data        日志文件列表
     *
     */
    public function logexplorer_serverlistfile($dirname, $setting=array())
    {
        $p = array_merge($this->auth_setting, $setting, array(
            'dirname' => $dirname,
        ));
        return $this->_cmd('LOGEXPLORER.SERVERLISTFILE', $p);
    }

    /*
     * 读取服务器日志文件内容
     *
     * 参数：
     * $dirname         日志目录名
     * $filename        日志文件名
     * $setting         程序执行设置
     *    - auth        auth插件参数
     *      - username  用户名
     *      - password    密码
     *
     * 返回值：
     * array('code'=>$code, 'data'=>$data)
     *    - code        'OK', 'FAILED', 'DENIED'（auth插件）
     *    - data        日志内容
     *
     */
    public function logexplorer_serverget($dirname, $filename, $setting=array())
    {
        $p = array_merge($this->auth_setting, $setting, array(
            'dirname' => $dirname,
            'filename' => $filename,
        ));
        return $this->_cmd('LOGEXPLORER.SERVERGET', $p);
    }

    /*
     * 添加新的进程调度配置
     *
     * 参数：
     * $jobname         进程名
     * $setting         程序执行设置
     *    - enable      是否立即开启调度
     *    - condition   调度时间条件设置
     *    - auth        auth插件参数
     *      - username  用户名
     *      - password    密码
     *
     * 返回值：
     * array('code'=>$code)
     *    - code        'OK', 'FAILED', 'DENIED'（auth插件）
     *
     */
    public function scheduler_add($jobname, $setting=array())
    {
        $p = array_merge($this->auth_setting, $setting, array(
            'jobname' => $jobname,
        ));
        return $this->_cmd('SCHEDULER.ADD', $p);
    }

    /*
     * 删除进程调度配置
     *
     * 参数：
     * $jobname         进程名
     * $uuid            进程调度配置的UUID
     * $setting         程序执行设置
     *    - enable      是否立即开启调度
     *    - condition   调度时间条件设置
     *    - auth        auth插件参数
     *      - username  用户名
     *      - password    密码
     *
     * 返回值：
     * array('code'=>$code)
     *    - code        'OK', 'FAILED', 'DENIED'（auth插件）
     *
     */
    public function scheduler_delete($jobname, $uuid, $setting=array())
    {
        $p = array_merge($this->auth_setting, $setting, array(
            'jobname' => $jobname,
            'uuid' => $uuid,
        ));
        return $this->_cmd('SCHEDULER.DELETE', $p);
    }

    /*
     * 更新进程调度配置
     *
     * 参数：
     * $jobname         进程名
     * $uuid            进程调度配置的UUID
     * $setting         程序执行设置
     *    - enable      是否立即开启调度
     *    - condition   调度时间条件设置
     *    - auth        auth插件参数
     *      - username  用户名
     *      - password    密码
     *
     * 返回值：
     * array('code'=>$code)
     *    - code        'OK', 'FAILED', 'DENIED'（auth插件）
     *
     */
    public function scheduler_update($jobname, $uuid, $setting=array())
    {
        $p = array_merge($this->auth_setting, $setting, array(
            'jobname' => $jobname,
            'uuid' => $uuid,
        ));
        return $this->_cmd('SCHEDULER.UPDATE', $p);
    }

    /*
     * 查询进程调度配置信息
     *
     * 参数：
     * $jobname         进程名
     * $uuid            进程调度配置的UUID
     * $setting         程序执行设置
     *    - auth        auth插件参数
     *      - username  用户名
     *      - password    密码
     *
     * 返回值：
     * array('code'=>$code, 'data'=>$data)
     *    - code        'OK', 'FAILED', 'DENIED'（auth插件）
     *    - data        进程调度配置信息
     *
     */
    public function scheduler_get($jobname, $uuid, $setting=array())
    {
        $p = array_merge($this->auth_setting, $setting, array(
            'jobname' => $jobname,
            'uuid' => $uuid,
        ));
        return $this->_cmd('SCHEDULER.GET', $p);
    }

    /*
     * 查询所有的进程调度配置信息
     *
     * 参数：
     * $setting         程序执行设置
     *    - auth        auth插件参数
     *      - username  用户名
     *      - password    密码
     *
     * 返回值：
     * array('code'=>$code, 'data'=>$data)
     *    - code        'OK', 'FAILED', 'DENIED'（auth插件）
     *    - data        进程调度配置信息列表
     *
     */
    public function scheduler_getall($setting=array())
    {
        $p = array_merge($this->auth_setting, $setting);
        return $this->_cmd('SCHEDULER.GETALL', $p);
    }

    /*
     * 查询进程调度执行历史
     *
     * 参数：
     * $jobname         进程名
     * $uuid            进程调度配置的UUID
     * $setting         程序执行设置
     *    - auth        auth插件参数
     *      - username  用户名
     *      - password    密码
     *
     * 返回值：
     * array('code'=>$code, 'data'=>$data)
     *    - code        'OK', 'FAILED', 'DENIED'（auth插件）
     *    - data        进程调度执行时间列表
     *
     */
    public function scheduler_getlog($jobname, $uuid, $setting=array())
    {
        $p = array_merge($this->auth_setting, $setting, array(
            'jobname' => $jobname,
            'uuid' => $uuid,
        ));
        return $this->_cmd('SCHEDULER.GETLOG', $p);
    }

    /*
     * 执行命令并返回结果
     */
    private function _cmd($cmd, $params=NULL)
    {
        if (!($sock = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP))) return FALSE;
        if (!@socket_connect($sock, $this->server_ip, $this->server_port)) return FALSE;

        // 发送
        $primitive = $params ? "$cmd ".json_encode($params) : $cmd;
        $length = strlen($primitive);
        $length = pack('I', $length);

        $package = $length.$primitive;
        $package_length = strlen($package);
        while (TRUE)
        {
            $sent = socket_write($sock, $package, $package_length);

            if ($sent === FALSE) return FALSE;
            if ($sent == $package_length) break;

            $package = substr($package, $sent);
            $package_length -= $sent;
        }

        // 接收
        $bin_length = '';
        while (strlen($bin_length) != 4)
        {
            $bin_length .= socket_read($sock, 4-strlen($bin_length), PHP_BINARY_READ);
        }
        list(, $length, ) = unpack('I', $bin_length);

        $result = '';
        while (strlen($result) != $length)
        {
            $result .= socket_read($sock, $length-strlen($result), PHP_BINARY_READ);   
        }

        socket_close($sock);

        $result = json_decode($result, TRUE);

        return $result;
    }
}
