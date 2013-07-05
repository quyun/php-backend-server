<?php

/**
 * 调度配置类
 */
class Scheduler
{
    private $config_file = NULL;    // 配置文件
    private $log_path = NULL;       // 日志文件路径

    public function __construct($config_file, $log_path)
    {
        $this->config_file = $config_file;
        $this->log_path = $log_path;

        if (!file_exists($this->config_file))
        {
            // 初始化配置
            $this->_set(array());
        }

        if (!file_exists($this->log_path))
        {
            mkdir($this->log_path, 0777);
        }
        $this->log_path = realpath($this->log_path);
    }

    public function add_scheduler($jobname, $setting)
    {
        if (!$this->_check_setting($setting)) return FALSE;

        $config = $this->_get();
        $schedulers = isset($config[$jobname]) ? $config[$jobname] : array();
        if ($schedulers)
        {
            foreach ($schedulers as $scheduler)
            {
                // 检测配置是否重复
                if ($scheduler['condition'] == $setting['condition']) return FALSE;
            }
        }

        $uuid = $this->uuid();
        $schedulers[$uuid] = $setting;
        $config[$jobname] = $schedulers;
        return $this->_set($config) ? $uuid : FALSE;
    }

    public function delete_scheduler($jobname, $uuid)
    {
        $config = $this->_get();
        $schedulers = isset($config[$jobname]) ? $config[$jobname] : array();
        if (!$schedulers) return FALSE;

        unset($schedulers[$uuid]);

        $config[$jobname] = $schedulers;
        return $this->_set($config);
    }

    public function update_scheduler($jobname, $uuid, $setting)
    {
        if (isset($setting['condition']) && !$this->_check_setting($setting)) return FALSE;

        $config = $this->_get();
        $schedulers = isset($config[$jobname]) ? $config[$jobname] : array();
        if (!$schedulers) return FALSE;

        if (!isset($schedulers[$uuid])) return FALSE;

        $schedulers[$uuid] = array_merge($schedulers[$uuid], $setting);
        $config[$jobname] = $schedulers;
        return $this->_set($config);
    }

    public function get_scheduler($jobname)
    {
        $config = $this->_get();
        $schedulers = isset($config[$jobname]) ? $config[$jobname] : array();
        if (!$schedulers) return FALSE;
        return $schedulers;
    }

    public function get_all_schedulers()
    {
        $config = $this->_get();
        return $config;
    }

    // 检测配置属于哪种组合
    public function detect_group($fields)
    {
        $match_group = FALSE;

        // 检查组合
        $groups = array(
            array('Y', 'm', 'd', 'H', 'i', 'U'),
            array('Y', 'W', 'N', 'H', 'i', 'U'),
            array('Y', 'z', 'H', 'i', 'U'),
        );
        foreach ($groups as $group)
        {
            if (count(array_intersect($group, $fields)) == count($fields))
            {
                $match_group = $group;
                break;
            }
        }

        return $match_group;
    }

    // 取出最小单位
    public function get_min_field($fields, $group)
    {
        $tarr = array_reverse($group);
        array_shift($tarr);
        $minfield = FALSE;
        foreach ($tarr as $field)
        {
            if (in_array($field, $fields))
            {
                $minfield = $field;
                break;
            }
        }
        if (!$minfield) $minfield = 'Y';

        return $minfield;
    }

    // 检查调度配置是否合法
    private function _check_setting($setting)
    {
        if (!is_array($setting)) return FALSE;
        if (!isset($setting['enable']) || !isset($setting['condition'])) return FALSE;
        if (!is_bool($setting['enable'])) return FALSE;

        $condition = $setting['condition'];
        if (!$condition) return FALSE;

        // 检查参数值
        foreach ($condition as $field=>$value)
        {
            switch ($field)
            {
                case 'd':
                    if ($value == '@t') continue;
                    if (!preg_match('/^\d\d$/', $value)) return FALSE;
                    if (intval($value) == 0 || intval($value) > 31) return FALSE;
                    break;
                case 'N':
                    if (!preg_match('/^\d$/', $value)) return FALSE;
                    if (intval($value) == 0 || intval($value) > 7) return FALSE;
                    break;
                case 'z':
                    if (!preg_match('/^\d{1,3}$/', $value)) return FALSE;
                    if (substr($value, 0, 1) == '0') return FALSE;
                    if (intval($value) > 365) return FALSE;
                    break;
                case 'W':
                    if (!preg_match('/^\d{1,2}$/', $value)) return FALSE;
                    if (substr($value, 0, 1) == '0') return FALSE;
                    if (intval($value) == 0 || intval($value) > 53) return FALSE;
                    break;
                case 'm':
                    if (!preg_match('/^\d\d$/', $value)) return FALSE;
                    if (intval($value) == 0 || intval($value) > 12) return FALSE;
                    break;
                case 'Y':
                    if (!preg_match('/^\d{4}}$/', $value)) return FALSE;
                    if (intval($value) < 2013 || intval($value) > 2099) return FALSE;
                    break;
                case 'H':
                    if (!preg_match('/^\d\d$/', $value)) return FALSE;
                    if (intval($value) > 23) return FALSE;
                    break;
                case 'i':
                    if (!preg_match('/^\d\d$/', $value)) return FALSE;
                    if (intval($value) > 59) return FALSE;
                    break;
                case 'U':
                    if (intval($value) % 60 != 0) return FALSE;

                    $fields = array_keys($condition);
                    $match_group = $this->detect_group($fields);
                    if (!$match_group) return FALSE;
                    $minfield = $this->get_min_field($fields, $match_group);

                    $maxvalues = array(
                        'd' => 86400,
                        'N' => 86400,
                        'z' => 86400,
                        'W' => 86400*7,
                        'm' => 86400*30,
                        'Y' => 86400*365,
                        'H' => 3600,
                        'i' => 60,
                    );
                    $maxvalue = $maxvalues[$minfield];

                    if (intval($value) > $maxvalue) return FALSE;
                    break;
                default:
                    return FALSE;
            }
        }

        return TRUE;
    }

    public function get_log($jobname, $uuid)
    {
        $job_log_path = $this->log_path.'/'.$jobname;
        $log_file = $job_log_path.'/'.$uuid.'.log';
        if (!file_exists($log_file))
        {
            $log = array();
        }
        else
        {
            $json_str = file_get_contents($log_file);
            $log = json_decode($json_str, TRUE);   
        }
        if ($log === FALSE) return FALSE;

        return $log;
    }

    public function add_log($jobname, $uuid, $timestamp)
    {
        $log = $this->get_log($jobname, $uuid);

        array_unshift($log, $timestamp);

        // 只保留最近50条
        $log = array_slice($log, 0, 50);

        $json_str = $this->json_indent(json_encode($log));

        $job_log_path = $this->log_path.'/'.$jobname;
        if (!file_exists($job_log_path)) mkdir($job_log_path, 0777);
        $log_file = $job_log_path.'/'.$uuid.'.log';

        return file_put_contents($log_file, $json_str) == strlen($json_str);
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

    private function uuid()
    {
        return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),

            // 16 bits for "time_mid"
            mt_rand( 0, 0xffff ),

            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand( 0, 0x0fff ) | 0x4000,

            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand( 0, 0x3fff ) | 0x8000,

            // 48 bits for "node"
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
        );
    }
}