<?php
require_once(dirname(__FILE__).'/AuthConfig.class.php');

/*
 * 身份认证插件
 *
 * 该插件会对所有指令进行身份认证，如果认证失败返回DENIED
 */

class AuthPlugin
{
    private $server = NULL;        // 后台进程服务器对象
    private $data_path = NULL;     // 插件数据保存目录
    public  $config = NULL;        // 配置对象

    private $commands = array(
        'AUTH.GETENABLE',
        'AUTH.SETENABLE',
        'AUTH.ADD',
        'AUTH.UPDATE',
        'AUTH.DELETE',
        'AUTH.GET',
        'AUTH.GETALL',
    );

    public function __construct($server, $setting)
    {
        $this->server = $server;
        $this->data_path = isset($setting['plugins_data_path']) ? $setting['plugins_data_path'] : realpath(dirname(__FILE__).'/../../').'/data/plugins';
        $this->data_path .= '/auth';
        if (!file_exists($this->data_path)) mkdir($this->data_path, 0777);

        $this->config_file = $this->data_path.'/config.json';
        $this->config = new AuthConfig($this->config_file);
    }

    public function on_command_received($cmd, $params)
    {
        $auth = isset($params['auth']) && is_array($params['auth']) ? $params['auth'] : array();
        $username = isset($auth['username']) ? $auth['username'] : '';
        $password = isset($auth['password']) ? $auth['password'] : '';
        if (!$this->auth($username, $password, $cmd))
        {
            $this->server->client_return('DENIED');
            $this->server->server_echo('DENIED');
            return FALSE;
        }
        unset($params['auth']);

        if (!in_array($cmd, $this->commands)) return TRUE;

        $cmdfunc = 'command_'.str_replace('.', '_', strtolower($cmd));
        if (!method_exists($this, $cmdfunc)) return TRUE;

        call_user_func(array($this, $cmdfunc), $params);
        return FALSE;
    }

    public function auth($username, $password, $cmd)
    {
        if (!$this->config->getenable()) return TRUE;

        $users = $this->config->getall();
        if (!$users) return TRUE; // 无用户，永远验证成功

        if (!isset($users[$username])) return FALSE;
        $user = $users[$username];
        if ($user['password'] != $password) return FALSE;

        if (!in_array($cmd, explode(',', $user['privileges']))
             && $user['privileges'] != '*') return FALSE;

        return TRUE;
    }

    private function command_auth_getenable($params)
    {
        $result = $this->config->getenable();
        $this->server->client_return('OK', $result);
        $this->server->server_echo('OK. (Auth is '.($result ? 'enabled' : 'disabled').')');
        return TRUE;
    }

    private function command_auth_setenable($params)
    {
        if (!$this->_require($params, 'enable')) return FALSE;

        $result = $this->config->setenable($params['enable']);
        return $this->_echo_result($result);
    }

    private function command_auth_add($params)
    {
        if (!$this->_require($params, 'username')) return FALSE;
        if (!$this->_require($params, 'password')) return FALSE;
        if (!$this->_require($params, 'privileges')) return FALSE;

        $username = $params['username'];
        $password = $params['password'];
        $privileges = $params['privileges'];
        unset($params['username']);
        unset($params['password']);
        unset($params['privileges']);

        $result = $this->config->add($username, $password, $privileges, $params);
        return $this->_echo_result($result);
    }

    private function command_auth_update($params)
    {
        if (!$this->_require($params, 'username')) return FALSE;

        $username = $params['username'];
        unset($params['username']);

        $result = $this->config->update($username, $params);
        return $this->_echo_result($result);
    }

    private function command_auth_delete($params)
    {
        if (!$this->_require($params, 'username')) return FALSE;

        $result = $this->config->delete($params['username']);
        return $this->_echo_result($result);
    }

    private function command_auth_get($params)
    {
        if (!$this->_require($params, 'username')) return FALSE;

        $result = $this->config->get($params['username']);
        return $this->_echo_result($result, $result === FALSE ? NULL : $result);
    }

    private function command_auth_getall($params)
    {
        $result = $this->config->getall();
        return $this->_echo_result($result, $result === FALSE ? NULL : $result);
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