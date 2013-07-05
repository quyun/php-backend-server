<?php
require_once(dirname(__FILE__).'/Scheduler.class.php');

/*
 * 进程调度插件
 *
 * 该插件用于定时执行指定进程
 */

class SchedulerPlugin
{
    private $server = NULL;                 // 后台进程服务器对象
    private $check_interval = FALSE;        // 检测间隔时间
    public  $config = NULL;                 // 配置对象

    private $schedule_list = array();       // 保存各进程的调度时间信息
    private $time_field_schedules = array();// 保存各个时间字段对应的进程调度信息
    private $schedule_lasttimes = array();  // 保存各个进程调度配置最好一次执行的信息

    private $commands = array(
        'SCHEDULER.ADD',
        'SCHEDULER.DELETE',
        'SCHEDULER.UPDATE',
        'SCHEDULER.GET',
        'SCHEDULER.GETALL',
        'SCHEDULER.GETLOG',
    );

    public function __construct($server, $setting)
    {
        $this->server = $server;
        $this->check_interval = isset($setting['check_interval']) ? $setting['check_interval'] : 60;

        $this->data_path = isset($setting['plugins_data_path']) ? $setting['plugins_data_path'] : realpath(dirname(__FILE__).'/../../').'/data/plugins';
        $this->data_path .= '/scheduler';
        if (!file_exists($this->data_path)) mkdir($this->data_path, 0777);

        $this->config_file = $this->data_path.'/config.json';
        $this->log_path = $this->data_path.'/log/';

        $this->scheduler = new Scheduler($this->config_file, $this->log_path);
    }

    public function on_server_inited()
    {
        // 新建子进程用于清理日志
        $pid = pcntl_fork();
        if ($pid == -1)
        {
            $this->server_echo('[scheduler] init failed.');
            return FALSE;
        }
        else if ($pid)    // 父进程
        {
            // 不等待子进程，直接返回
            //pcntl_waitpid($pid, $status);
            return TRUE;
        }

        while (TRUE)
        {
            // 每次循环重新读取配置
            $this->_load_schedulers();
            $this->_schedule();
            sleep($this->check_interval);
        } 

        return TRUE;
    }

    private function _schedule()
    {
        // 检查是否有符合条件的进程，并执行它
        $now = time();
        $fields = array('d', 'N', 'z', 'W', 'm', 'Y', 'H', 'i');

        // 找出满足各个时间条件的进程调度信息
        $match_list = array();
        foreach ($fields as $field)
        {
            $value = date($field, $now);
            $match_list[$field] = array();

            if (isset($this->schedule_list[$field][$value]))
            {
                $match_list[$field] = array_merge($match_list[$field], $this->schedule_list[$field][$value]);
            }
            if ($field == 'd' && $value == date('t', $now))
            {
                if (isset($this->schedule_list[$field]['@t']))
                {
                    $match_list[$field] = array_merge($match_list[$field], $this->schedule_list[$field]['@t']);
                }
            }
        }

        // 'U' 单独处理
        if (isset($this->schedule_list['U']))
        {
            foreach ($this->schedule_list['U'] as $interval=>$schedule_nodes)
            {
                $lasttime = $schedule_nodes['last'];
                unset($schedule_nodes['last']);

                if ($now - $lasttime >= $interval)
                {
                    $match_list['U'] = $schedule_nodes;
                    $this->schedule_list['U'][$interval]['last'] = $now;
                }
            }
        }

        // 找出条件未完全满足的调度信息
        $unmatch_nodes = array();
        foreach ($this->time_field_schedules as $field=>$schedule_node)
        {
            if (!isset($match_list[$field]) || !in_array($schedule_node, $match_list[$field]))
            {
                if (!in_array($schedule_node, $unmatch_nodes)) $unmatch_nodes[] = $schedule_node;
            }
        }

        $result_list = array();
        foreach ($match_list as $field=>$schedule_nodes)
        {
            foreach ($schedule_nodes as $schedule_node)
            {
                if (in_array($schedule_node, $unmatch_nodes)) continue;
                $result_list[$schedule_node][] = $field;
            }
        }

        // 判断是否执行进程
        foreach ($result_list as $schedule_node=>$fields)
        {
            if (in_array('U', $fields))
            {
                $match = TRUE;
            }
            else
            {
                $time_format = $this->_get_time_format($fields);
                $timestr = date($time_format, $now);
                if (!isset($this->schedule_lasttimes[$schedule_node]) || $this->schedule_lasttimes[$schedule_node] != $timestr)
                {
                    $match = TRUE;
                    $this->schedule_lasttimes[$schedule_node] = $timestr;
                }
            }

            // 执行
            $node_info = explode(':', $schedule_node);
            $uuid = array_pop($node_info);
            $jobname = implode(':', $node_info);
            $this->server->server_echo("[scheduler] starting \"{$jobname}\"...");
            $this->server->command_start(array('jobname'=>$jobname, 'newline'=>FALSE));

            // 写日志
            $this->scheduler->add_log($jobname, $uuid, $now);
        }

    }

    // 根据调度配置信息确定保存的时间格式
    private function _get_time_format($fields)
    {
        $group = $this->scheduler->detect_group($fields);

        if (in_array('d', $group))
        {
            $time_formats = array(
                'Y' => 'Y',
                'm' => 'Y-m',
                'd' => 'Y-m-d',
                'H' => 'Y-m-d H',
                'i' => 'Y-m-d H:i',
            );
        }
        elseif (in_array('N', $group))
        {
            $time_formats = array(
                'Y' => 'Y',
                'W' => 'Y-W',
                'N' => 'Y-W-N',
                'H' => 'Y-W-N H',
                'i' => 'Y-W-N H:i',
            );
        }
        else
        {
            $time_formats = array(
                'Y' => 'Y',
                'z' => 'Y-z',
                'H' => 'Y-z H',
                'i' => 'Y-z H:i',
            );
        }

        $minfield = $this->scheduler->get_min_field($fields, $group);

        return $time_formats[$minfield];
    }

    private function _load_schedulers()
    {
        // 从配置文件中加载进程调度信息
        $jobs = $this->scheduler->get_all_schedulers();
        foreach ($jobs as $jobname=>$schedulers)
        {
            foreach ($schedulers as $uuid=>$scheduler)
            {
                if ($scheduler['enable'])
                {
                    $schedule_node = "{$jobname}:{$uuid}";
                    $condition = $scheduler['condition'];

                    // 从日志中取出上次执行时间
                    $log = $this->scheduler->get_log($jobname, $uuid);
                    if (isset($log[0]))
                    {
                        $time_format = $this->_get_time_format(array_keys($condition));
                        $timestr = date($time_format, $log[0]);
                        $this->schedule_lasttimes[$schedule_node] = $log[0];
                    }

                    foreach ($condition as $field=>$value)
                    {
                        $this->schedule_list[$field][$value][] = $schedule_node;
                        $this->time_field_schedules[$field][] = $schedule_node;
                        if ($field == 'U' && isset($log[0]))
                        {
                            if (!isset($this->schedule_list['U'][$value]['last']))
                            {
                                $this->schedule_list['U'][$value]['last'] = $log[0];
                            }
                            else
                            {
                                $this->schedule_list['U'][$value]['last'] = max($this->schedule_list['U'][$value]['last'], $log[0]);
                            }
                        }
                    }
                }
            }   
        }
    }

    public function on_command_received($cmd, $params)
    {
        if (!in_array($cmd, $this->commands)) return TRUE;

        $cmdfunc = 'command_'.str_replace('.', '_', strtolower($cmd));
        if (!method_exists($this, $cmdfunc)) return TRUE;

        call_user_func(array($this, $cmdfunc), $params);
        return FALSE;
    }

    private function command_scheduler_add($params)
    {
        if (!$this->_require($params, 'jobname')) return FALSE;
        if (!$this->_require($params, 'enable')) return FALSE;
        if (!$this->_require($params, 'condition')) return FALSE;
        $jobname = $params['jobname'];

        $result = $this->scheduler->add_scheduler($jobname, array(
            'enable' => $params['enable'] ? TRUE : FALSE,
            'condition' => $params['condition'],
        ));
        return $this->_echo_result($result, $result === FALSE ? NULL : $result);
    }

    private function command_scheduler_delete($params)
    {
        if (!$this->_require($params, 'jobname')) return FALSE;
        if (!$this->_require($params, 'uuid')) return FALSE;
        $jobname = $params['jobname'];

        $result = $this->scheduler->delete_scheduler($jobname, $params['uuid']);
        return $this->_echo_result($result);
    }

    private function command_scheduler_update($params)
    {
        if (!$this->_require($params, 'jobname')) return FALSE;
        if (!$this->_require($params, 'uuid')) return FALSE;
        $jobname = $params['jobname'];

        $setting = array();
        if (isset($params['enable'])) $setting['enable'] = $params['enable'] ? TRUE : FALSE;
        if (isset($params['condition'])) $setting['condition'] = $params['condition'];

        $result = $this->scheduler->update_scheduler($jobname, $params['uuid'], $setting);
        return $this->_echo_result($result);
    }

    private function command_scheduler_get($params)
    {
        if (!$this->_require($params, 'jobname')) return FALSE;
        if (!$this->_require($params, 'uuid')) return FALSE;
        $jobname = $params['jobname'];

        $result = $this->scheduler->get_scheduler($jobname, $params['uuid']);
        return $this->_echo_result($result, $result === FALSE ? NULL : $result);
    }

    private function command_scheduler_getall($params)
    {
        $result = $this->scheduler->get_all_schedulers();
        return $this->_echo_result($result, $result === FALSE ? NULL : $result);
    }

    private function command_scheduler_getlog($params)
    {
        if (!$this->_require($params, 'jobname')) return FALSE;
        if (!$this->_require($params, 'uuid')) return FALSE;
        $jobname = $params['jobname'];

        $result = $this->scheduler->get_log($jobname, $params['uuid']);
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