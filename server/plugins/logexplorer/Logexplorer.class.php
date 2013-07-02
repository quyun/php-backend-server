<?php

/**
 * 日志操作类
 */
class Logexplorer
{
    private $log_path = NULL;    // 日志保存路径

    public function __construct($log_path)
    {
        $this->log_path = $log_path;
    }
    
    /*
     * 获取日志目录下的目录名列表
     *
     * @return array 目录名列表，按名称倒序
     */
    public function listdir($sort='asc')
    {
        $dirs = array();

        if ($handle = opendir($this->log_path))
        {
            while (($item = readdir($handle)) !== FALSE)
            {
                if ($item == "." || $item == "..") continue;
                if (!is_dir($this->log_path.'/'.$item)) continue;
                $dirs[] = $item;
            }
            closedir($handle);
        }

        if ($sort == 'desc')
            rsort($dirs);
        else
            sort($dirs);

        return $dirs;
    }
    
    /*
     * 获取日志目录下的文件名列表
     *
     * @return array 文件名列表，按名称倒序
     */
    public function listfile($dirname, $sort='asc')
    {
        $path = $this->log_path.'/'.$dirname;
        $files = array();

        if ($handle = opendir($path))
        {
            while (($item = readdir($handle)) !== FALSE)
            {
                if ($item == "." || $item == "..") continue;
                if (!is_file($path.'/'.$item)) continue;
                $files[] = $item;
            }
            closedir($handle);
        }

        if ($sort == 'desc')
            rsort($files);
        else
            sort($files);

        return $files;
    }

    /*
     * 读取文件内容
     *
     * @return string 文件内容
     */
    public function get($dirname, $filename)
    {
        $path = $this->log_path.'/'.$dirname.'/'.$filename;
        return file_get_contents($path);
    }
}