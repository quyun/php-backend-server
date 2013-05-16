<?php  if (!defined('BASEPATH')) exit('No direct script access allowed');

class Backend_model extends Base_Model {

    function __construct()
    {
        parent::__construct();
        $this->filename = 'backendinfo';
        $this->filepath = $this->data_path.$this->filename;
    }
	
	protected function insert_backend($jobname, $jobpath, $writelog=false, $autostart=false)
    {
        $data = array(
            'jobname'   => $jobname,
            'jobpath'   => $jobpath,
            'writelog'  => $writelog,
            'autostart' => $autostart,
        );

        $backendlist = $this->get_backendlist();
        if (!empty($backendlist))
        {
            foreach ($backendlist as $backend)
            {
                if ($backend['jobname'] === $jobname)
                    return false;
            }
        }
        $backendlist[] = $data;
        $backendlist = json_indent(json_encode($backendlist));
        return file_put_contents($this->filepath, $backendlist);
    }

    protected function delete_backend($jobname)
    {
        $backendlist = $this->get_backendlist();
        if (!empty($backendlist))
        {
            foreach ($backendlist as $key=>$backend)
            {
                if ($backend['jobname'] === $jobname)
                {
                    unset($backendlist[$key]);
                    $backendlist = json_indent(json_encode($backendlist));
                    return file_put_contents($this->filepath, $backendlist);
                }
            }
        }
        return false;
    }

    protected function update_backend($jobname, $jobpath='', $writelog='', $autostart='')
    {
        $backendlist = $this->get_backendlist();
        if (!empty($backendlist))
        {
            foreach ($backendlist as $key=>$backend)
            {

            }
        }
        else
            return false;
    }

    protected function get_backendlist($jobname='')
    {

        if (!file_exists($this->filepath))
            return array();
        $backendlist = file_get_contents($this->filepath);
        $backendlist = json_decode($backendlist, true);

        if ($jobname!=='')
        {
            if (!empty($backendlist))
            {
                foreach ($backendlist as $key=>$backend)
                {
                    if ($backend['jobname']==$jobname)
                        return $backendlist[$key];
                }
            }
            return array();
        }
        else
        {
            return $backendlist;
        }

    }



}