<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Logs extends Base_Controller
{


    function __construct()
    {
        parent::__construct();

        //登陆验证
        $this->check_login_status();

        $this->load->config('backends');
        $backend_config = $this->config->item('backend');
        $this->logpath = $backend_config['logpath'];

    }

    public function list_logdir()
    {
		$jobname = $this->input->get_post('jobname');
        $page = $this->input->get_post('page');

        //分页参数
        $page  = empty($page) ? 1 : $page;
        $total = 0;
        $limit = 12;
        $cururl = router_url($this->data['class'], 'list_logdir', array('jobname'=>$jobname));

        $loglist = array();
        $loglist_total = array();
		if (!empty($jobname))
		{
            $joblog_path = $this->logpath . $jobname . '/';
            if (is_dir($joblog_path))
            {
                if ($dh = opendir($joblog_path))
                {
                    while (($file = readdir($dh)) !== false)
                    {
                        if ($file !== '.' && $file !== '..')
                        {

                            $loglist_total[] = $file;
                            $total ++;
                        }
                    }
                    closedir($dh);
                }
            }
            $coure = ($page-1)*$limit;

            for ($i=$coure; $i<$coure+$limit; $i++)
            {
                if (isset($loglist_total[$i]))
                {
                    $loglist[] = array(
                        'logname' => $loglist_total[$i],
                        'logpath' => $joblog_path.$loglist_total[$i].'/',
                        //'logsize' => filesize($joblog_path.$loglist_total[$i]),
                    );
                }
            }
		}

        $this->data['loglist'] = $loglist;
        $this->data['jobname'] = $jobname;
        $this->data['page'] = multi($total, $limit, $page, $cururl);
        $this->load->view('list_logdir', $this->data);
    }

    public function list_logfile()
    {
        $jobname = $this->input->get_post('jobname');
        $logdirname = $this->input->get_post('logdirname');
        $logdirpath = $this->logpath.$jobname.'/'.$logdirname.'/';
        $page = $this->input->get_post('page');

        //分页参数
        $page  = empty($page) ? 1 : $page;
        $total = 0;
        $limit = 14;
        $cururl = router_url($this->data['class'], 'list_logfile', array('jobname'=>$jobname, 'logdirname'=>$logdirname));

        $logfilelist = array();
        $logfilelist_total = array();
        if (!empty($logdirpath))
        {
            if (is_dir($logdirpath))
            {
                if ($dh = opendir($logdirpath))
                {
                    while (($file = readdir($dh)) !== false)
                    {
                        if ($file !== '.' && $file !== '..')
                        {

                            $logfilelist_total[] = $file;
                            $total ++;
                        }
                    }
                    closedir($dh);
                }
            }
            $coure = ($page-1)*$limit;

            for ($i=$coure; $i<$coure+$limit; $i++)
            {
                if (isset($logfilelist_total[$i]))
                {
                    $logfilelist[] = array(
                        'logfilename' => $logfilelist_total[$i],
                        'logfilepath' => $logdirpath.$logfilelist_total[$i],
                        'logfilesize' => filesize($logdirpath.$logfilelist_total[$i])/1024,
                    );
                }
            }
        }
        $this->data['jobname'] = $jobname;
        $this->data['logdirname'] = $logdirname;
        $this->data['logfilelist'] = $logfilelist;
        $this->data['logdirpath'] = $logdirpath;
        $this->data['page'] = multi($total, $limit, $page, $cururl);
        $this->load->view('list_logfile', $this->data);
    }

    public function detail_logfile()
    {
        $logfilepath = $this->input->get('logfilepath');
        if (!file_exists($logfilepath))
        {
            exit('file not exists!');
        }
        $handle = fopen($logfilepath, 'r');
        $buffers = array();
        if ($handle)
        {
            while (!feof($handle))
            {
                $buffers[] = fgets($handle, 4096);
            }
        }
        fclose($handle);

        $logdirpath = dirname($logfilepath).'/';
        if (is_dir($logdirpath))
        {
            if ($dh = opendir($logdirpath))
            {
                while (($file = readdir($dh)) !== false)
                {
                    if ($file !== '.' && $file !== '..')
                        $logfilelist_total[] = $file;
                }
                closedir($dh);
            }
        }
        $this->data['buffers'] = $buffers;
        $this->data['logdirpath'] = $logdirpath;
        $this->data['logfilepath'] = $logfilepath;
        $this->data['logfilelist'] = $logfilelist_total;
        $this->load->view('detail_logfile', $this->data);
    }
}

