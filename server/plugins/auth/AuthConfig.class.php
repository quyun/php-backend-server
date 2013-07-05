<?php

/**
 * 用户验证配置类
 */
class AuthConfig
{
    private $config_file = NULL;    // 配置文件

    public function __construct($config_file)
    {
        $this->config_file = $config_file;

        if (!file_exists($this->config_file))
        {
            // 初始化配置
            $this->_set(array(
                'enable' => FALSE,
                'users' => array(),
            ));
        }
    }
    
    /*
     * 获取是否启用身份验证
     *
     * @return bool TRUE/FALSE
     */
    public function getenable()
    {
        $config = $this->_get();
        if ($config === FALSE) return FALSE;

        return $config['enable'];
    }
    
    /*
     * 设置身份验证启用/禁用
     *
     * @param bool $enable 启用/禁用
     *
     * @return bool TRUE/FALSE
     */
    public function setenable($enable)
    {
        $config = $this->_get();
        if ($config === FALSE) return FALSE;

        $config['enable'] = $enable;
        return $this->_set($config);
    }
    
    /*
     * 添加一个用户
     *
     * @param string $username 用户名
     * @param string $password 密码
     * @param string $privileges 权限列表，用逗号分隔，*表示所有权限
     * @param string $setting 其它配置
     *
     * @return bool TRUE/FALSE
     */
    public function add($username, $password, $privileges, $setting=array())
    {
        $config = $this->_get();
        if ($config === FALSE) return FALSE;

        if (isset($config['users'][$username])) return FALSE;

        $privileges = explode(',', $privileges);
        if (in_array('*', $privileges))
        {
            $privileges = '*';
        }
        else
        {
            $privileges = implode(',', array_unique($privileges));
        }

        $config['users'][$username] = array_merge($setting, array(
            'password' => $password,
            'privileges' => $privileges,
        ));

        return $this->_set($config);
    }
    
    /*
     * 删除一个用户
     *
     * @param string $username 用户名
     *
     * @return bool TRUE/FALSE
     */
    public function delete($username)
    {
        $config = $this->_get();
        if ($config === FALSE) return FALSE;

        if (!isset($config['users'][$username])) return FALSE;
        unset($config['users'][$username]);

        return $this->_set($config);
    }
    
    /*
     * 更新一个用户
     *
     * @param string $username 用户名
     * @param string $setting 用户配置
     *
     * @return bool TRUE/FALSE
     */
    public function update($username, $setting)
    {
        $config = $this->_get();
        if ($config === FALSE) return FALSE;

        if (!isset($config['users'][$username])) return FALSE;

        if (isset($setting['privileges']))
        {
            $privileges = $setting['privileges'];
            $privileges = explode(',', $privileges);
            if (in_array('*', $privileges))
            {
                $privileges = '*';
            }
            else
            {
                $privileges = implode(',', array_unique($privileges));
            }
            $setting['privileges'] = $privileges;
        }

        $config['users'][$username] = array_merge($config['users'][$username], $setting);

        return $this->_set($config);
    }
    
    /*
     * 获取一个用户信息
     *
     * @param string $username 用户名
     *
     * @return array 用户信息
     */
    public function get($username)
    {
        $config = $this->_get();
        if ($config === FALSE) return FALSE;

        if (!isset($config['users'][$username])) return FALSE;
        return $config['users'][$username];
    }
    
    /*
     * 获取所有用户信息
     *
     * @return array 所有用户信息
     */
    public function getall()
    {
        $config = $this->_get();
        if ($config === FALSE) return FALSE;
        return $config['users'];
    }

    private function _get()
    {
        $json_str = file_get_contents($this->config_file);
        $config = json_decode($json_str, TRUE);
        if ($config === FALSE) return FALSE;
        return $config;
    }

    private function _set($config)
    {
        $json_str = $this->json_indent(json_encode($config));
        return file_put_contents($this->config_file, $json_str) == strlen($json_str);
    }

    /**
     * Indents a flat JSON string to make it more human-readable.
     *
     * @param string $json The original JSON string to process.
     *
     * @return string Indented version of the original JSON string.
     */
    private function json_indent($json)
    {
        $result      = '';
        $pos         = 0;
        $strLen      = strlen($json);
        $indentStr   = '  ';
        $newLine     = "\n";
        $prevChar    = '';
        $outOfQuotes = true;

        for ($i=0; $i<=$strLen; $i++) {

            // Grab the next character in the string.
            $char = substr($json, $i, 1);

            // Are we inside a quoted string?
            if ($char == '"' && $prevChar != '\\') {
                $outOfQuotes = !$outOfQuotes;

            // If this character is the end of an element, 
            // output a new line and indent the next line.
            } else if(($char == '}' || $char == ']') && $outOfQuotes) {
                $result .= $newLine;
                $pos --;
                for ($j=0; $j<$pos; $j++) {
                    $result .= $indentStr;
                }
            }

            // Add the character to the result string.
            $result .= $char;

            // If the last character was the beginning of an element, 
            // output a new line and indent the next line.
            if (($char == ',' || $char == '{' || $char == '[') && $outOfQuotes) {
                $result .= $newLine;
                if ($char == '{' || $char == '[') {
                    $pos ++;
                }

                for ($j = 0; $j < $pos; $j++) {
                    $result .= $indentStr;
                }
            }

            $prevChar = $char;
        }

        return $result;
    }
}