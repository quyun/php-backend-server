<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Backends extends Base_Controller {


    function __construct()
    {
        parent::__construct();

        //登陆验证
        $this->check_login_status();

        $this->load->config('backends');
        $backend_config = $this->config->item('backend');
        $server_ip = $backend_config['server_ip'];
        $server_port = $backend_config['server_port'];
        $this->obj_backend = new Backend();
        $this->obj_backend->init($server_ip, $server_port);

    }

	public function index()
	{
        $url = $this->data['base_url'].router_url($this->data['class'], 'list_backend');
        redirect($url);

	}

    public function list_backend()
    {
        $this->load->model('backend_model');
        $backendlist = $this->backend_model->get_backendlist();
        if (!empty($backendlist))
        {
            foreach ($backendlist as $key=>$backend)
            {
                $backendlist[$key]['status'] = $this->obj_backend->status($backend['jobname']);//==='UP'? 'UP':'DOWN';
            }
        }
        $this->data['serverread'] = $this->obj_backend->serverread();
        $this->data['backendlist'] = $backendlist;
        $this->load->view('list_backend', $this->data);
    }

    public function add_backend()
    {
        $submit_form = $this->input->post('submit_form');
        if ($submit_form)
        {
            $this->data['form']['jobname'] = $this->input->post('jobname');
            $this->data['form']['jobpath'] = $this->input->post('jobpath');
            $this->data['form']['writelog'] = $this->input->post('writelog') ? true:false;
            $this->data['form']['autostart'] = $this->input->post('autostart') ? true:false;

            $flag = true;
            $this->data['msg'] = '';
            if (empty($this->data['form']['jobname']))
            {
                $flag = false;
                $this->data['msg'] = '进程名称不能为空';
            }
            if ($flag && empty($this->data['form']['jobpath']))
            {
                $flag = false;
                $this->data['msg'] = '进程路径不能为空';
            }
            if ($flag && !file_exists($this->data['form']['jobpath']))
            {
                $flag = false;
                $this->data['msg'] = '进程文件不存在';
            }


            if ($flag)
            {
                $this->load->model('backend_model');
                $rs = $this->backend_model->insert_backend($this->data['form']);
                if ($rs)
                {
                    $url = $this->data['base_url'].router_url($this->data['class'], 'list_backend');
                    redirect($url);
                }
                else
                {
                    $flag = false;
                    $this->data['msg'] = '添加进程失败';
                }
            }
        }
        $this->load->view('add_backend', $this->data);

    }

    public function json_delbackend()
    {
        $jobname = $this->input->get_post('jobname');
        $status = $this->obj_backend->status($jobname);

        $rs['flag'] = true;
        $rs['msg'] = '删除操作成功';
        if ($status==='UP')
        {
            $rs['flag'] = false;
            $rs['msg'] = '请先停止进程';
        }
        else
        {
            $this->load->model('backend_model');
            $result = $this->backend_model->delete_backend(array('jobname'=>$jobname));
            if (!$result)
            {
                $rs['flag'] = false;
                $rs['msg'] = '删除失败';
            }
        }
        echo json_encode($rs);
    }

    public function json_startbackend()
    {
        $jobname = $this->input->get_post('jobname');
        $rs['flag'] = true;
        $rs['msg'] = '启动操作成功';
        if (empty($jobname))
        {
            $rs['flag'] = false;
            $rs['msg'] = '非法参数';
            echo json_encode($rs);
            exit;
        }
        $this->load->model('backend_model');
        $backendinfo = $this->backend_model->get_backendlist(array('jobname'=>$jobname));
        $result = $this->obj_backend->start($backendinfo['jobname'], $backendinfo['jobpath'], 20, $backendinfo['writelog'], $backendinfo['autostart']);
        if ($result!=='OK')
        {
            $rs['flag'] = false;
            $rs['msg'] = '启动失败';
        }
        echo json_encode($rs);
        exit;
    }

    public function json_stopbackend()
    {
        $jobname = $this->input->get_post('jobname');
        $rs['flag'] = true;
        $rs['msg'] = '停止操作成功';
        if (empty($jobname))
        {
            $rs['flag'] = false;
            $rs['msg'] = '非法参数';
            echo json_encode($rs);
            exit;
        }
        $result = $this->obj_backend->stop($jobname);
        if ($result !== 'OK')
        {
            $rs['flag'] = false;
            $rs['msg'] = '停止失败';
        }
        echo json_encode($rs);
        exit;
    }

    public function json_restartbackend()
    {
        $jobname = $this->input->get_post('jobname');
        $rs['flag'] = true;
        $rs['msg'] = '重启操作成功';
        if (empty($jobname))
        {
            $rs['flag'] = false;
            $rs['msg'] = '非法参数';
            echo json_encode($rs);
            exit;
        }
        $result = $this->obj_backend->restart($jobname);
        if ($result !== 'OK')
        {
            $rs['flag'] = false;
            $rs['msg'] = '重启失败';
        }
        echo json_encode($rs);
        exit;
    }

    public function json_readbackend()
    {
        $jobname = $this->input->get_post('jobname');
        $rs['flag'] = true;
        $rs['msg'] = '';
        if (empty($jobname))
        {
            $rs['flag'] = false;
            $rs['msg'] = '非法参数';
            echo json_encode($rs);
            exit;
        }
        $result = $this->obj_backend->read($jobname);
        $rs['msg'] = $result;
        echo json_encode($rs);
    }

}

/* End of file welcome.php */
/* Location: ./application/controllers/welcome.php */