<?php

/**
 * 进程配置类
 */
class JobConfig
{
    private $config_file = NULL;    // 配置文件

    public function __construct($config_file)
    {
        $this->config_file = $config_file;

        if (!file_exists($this->config_file))
        {
            $this->_set(array());
        }
    }
    
    /*
     * 添加一个进程配置
     *
     * @param string $jobname 进程名称
     * @param string $setting 进程配置
     *
     * @return bool TRUE/FALSE
     */
    public function add($jobname, $setting)
    {
        $jobs = $this->_get();
        if ($jobs === FALSE) return FALSE;

        if (isset($jobs[$jobname])) return FALSE;
        $jobs[$jobname] = $setting;

        return $this->_set($jobs);
    }
    
    /*
     * 删除一个进程配置
     *
     * @param string $jobname 进程名称
     *
     * @return bool TRUE/FALSE
     */
    public function delete($jobname)
    {
        $jobs = $this->_get();
        if ($jobs === FALSE) return FALSE;

        if (!isset($jobs[$jobname])) return FALSE;
        unset($jobs[$jobname]);

        return $this->_set($jobs);
    }
    
    /*
     * 更新一个进程配置
     *
     * @param string $jobname 进程名称
     * @param string $setting 进程设置
     *
     * @return bool TRUE/FALSE
     */
    public function update($jobname, $setting)
    {
        $jobs = $this->_get();
        if ($jobs === FALSE) return FALSE;

        if (!isset($jobs[$jobname])) return FALSE;
        $jobs[$jobname] = array_merge($jobs[$jobname], $setting);

        return $this->_set($jobs);
    }
    
    /*
     * 获取一个进程配置
     *
     * @param string $jobname 进程名称
     *
     * @return array 进程配置
     */
    public function get($jobname)
    {
        $jobs = $this->_get();
        if ($jobs === FALSE) return FALSE;

        if (!isset($jobs[$jobname])) return FALSE;
        return $jobs[$jobname];
    }
    
    /*
     * 获取所有进程配置
     *
     * @return array 各个进程配置
     */
    public function getall()
    {
        return $this->_get();
    }

    private function _get()
    {
        $json_str = file_get_contents($this->config_file);
        $jobs = json_decode($json_str, TRUE);
        if ($jobs === FALSE) return FALSE;
        return $jobs;
    }

    private function _set($jobs)
    {
        $json_str = $this->json_indent(json_encode($jobs));
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