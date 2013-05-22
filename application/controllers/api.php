<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Api extends Base_Controller {


    function __construct()
    {
        parent::__construct();

        $this->load->config('backends');
        $backend_config = $this->config->item('backend');
        $server_ip = $backend_config['server_ip'];
        $server_port = $backend_config['server_port'];
        $this->obj_backend = new Backend();
        $this->obj_backend->init($server_ip, $server_port);

    }

	public function jiankongbao()
    {
        $jobname = $this->input->get('jobname');
        if (empty($jobname))
        {
            $this->output->set_status_header('500');
            exit;
        }

        $status = $this->obj_backend->status($jobname);
        if ($status === 'UP')
            $this->output->set_status_header('200');
        else
            $this->output->set_status_header('500');
    }

}
