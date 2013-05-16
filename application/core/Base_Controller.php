<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Base_Controller extends CI_Controller {

    /**
     * Constructor
     *
     * Calls the initialize() function
     */
    function __construct()
    {
        parent::__construct();

        $this->data['base_url'] = $this->config->item('base_url');		//项目URL
        $this->data['static_url'] = $this->config->item('static_url');	//图片域名
        $this->data['charset'] = $this->config->item('charset');		//页面编码

        $this->data['class'] = $this->router->class;
        $this->data['method'] = $this->router->method;

        //data路径
        $this->data_path = $this->config->item('data_path');
    }

    protected function check_login_status()
    {
        $this->username = $this->session->userdata('username');

        if (!empty($this->username))
        {
            $this->data['username'] = $this->username;
        }
        else
        {
            $url = $this->data['base_url'].router_url('simple', 'login');
            redirect($url);
        }
    }
}

/* End of file welcome.php */
/* Location: ./application/controllers/welcome.php */
