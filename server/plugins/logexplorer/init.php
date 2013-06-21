<?php
require_once(dirname(__FILE__).'/Log.class.php');

/*
 * 身份认证插件
 *
 * 该插件会对所有指令进行身份认证，如果认证失败返回DENIED
 */

class Logexplorer
{
    private $server = NULL;        // 后台进程服务器对象
    private $log_path = NULL;      // 后台进程服务器日志目录

    private $commands = array(
        'LOGEXPLORER.LISTDIR',
        'LOGEXPLORER.LISTFILE',
        'LOGEXPLORER.GET',
        'LOGEXPLORER.SERVERLISTDIR',
        'LOGEXPLORER.SERVERLISTFILE',
        'LOGEXPLORER.SERVERGET',
    );

    public function __construct($server, $setting)
    {
        $this->server = $server;
        $this->log_path = isset($setting['log_path']) ? $setting['log_path'] : realpath(dirname(__FILE__).'/../../').'/data/log';
    }

    public function on_command_received($cmd, $params)
    {
        if (!in_array($cmd, $this->commands)) return TRUE;

        $cmdfunc = 'command_'.str_replace('.', '_', strtolower($cmd));
        if (!method_exists($this, $cmdfunc)) return TRUE;

        call_user_func(array($this, $cmdfunc), $params);
        return FALSE;
    }

    private function command_logexplorer_listdir($params)
    {
        if (!$this->_require($params, 'jobname')) return FALSE;
        $jobname = $params['jobname'];

        $log = new Log($this->log_path.'/jobs/'.$jobname);
        $result = $log->listdir('desc');
        return $this->_echo_result(TRUE, $result);
    }

    private function command_logexplorer_listfile($params)
    {
        if (!$this->_require($params, 'jobname')) return FALSE;
        if (!$this->_require($params, 'dirname')) return FALSE;
        $jobname = $params['jobname'];
        $dirname = $params['dirname'];

        $log = new Log($this->log_path.'/jobs/'.$jobname);
        $result = $log->listfile($dirname, 'desc');
        return $this->_echo_result(TRUE, $result);
    }

    private function command_logexplorer_get($params)
    {
        if (!$this->_require($params, 'jobname')) return FALSE;
        if (!$this->_require($params, 'dirname')) return FALSE;
        if (!$this->_require($params, 'filename')) return FALSE;
        $jobname = $params['jobname'];
        $dirname = $params['dirname'];
        $filename = $params['filename'];

        $log = new Log($this->log_path.'/jobs/'.$jobname);
        $result = $log->get($dirname, $filename);
        return $this->_echo_result(TRUE, $result === FALSE ? NULL : $result);
    }

    private function command_logexplorer_serverlistdir($params)
    {
        $log = new Log($this->log_path.'/server');
        $result = $log->listdir('desc');
        return $this->_echo_result(TRUE, $result);
    }

    private function command_logexplorer_serverlistfile($params)
    {
        if (!$this->_require($params, 'dirname')) return FALSE;
        $dirname = $params['dirname'];

        $log = new Log($this->log_path.'/server');
        $result = $log->listfile($dirname, 'desc');
        return $this->_echo_result(TRUE, $result);
    }

    private function command_logexplorer_serverget($params)
    {
        if (!$this->_require($params, 'dirname')) return FALSE;
        if (!$this->_require($params, 'filename')) return FALSE;
        $dirname = $params['dirname'];
        $filename = $params['filename'];

        $log = new Log($this->log_path.'/server');
        $result = $log->get($dirname, $filename);
        return $this->_echo_result(TRUE, $result === FALSE ? NULL : $result);
    }

    private function _require($params, $field)
    {
        if (!isset($params[$field]))
        {
            $this->server->client_return('FAILED');
            $this->server->server_echo("FAILED. ($field is required but missing.)");
            return FALSE;
        }
        return TRUE;
    }

    private function _echo_result($result, $data=NULL)
    {
        if ($result)
        {
            $this->server->client_return('OK', $data);
            $this->server->server_echo('OK');
            return TRUE;
        }
        else
        {
            $this->server->client_return('FAILED');
            $this->server->server_echo('FAILED');
            return FALSE;
        }
    }

}