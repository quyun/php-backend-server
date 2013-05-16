<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Simple extends Base_Controller {


    function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $url = $this->data['base_url'].router_url($this->data['class'], 'login');
        redirect($url);
    }

    public function login()
    {
        $is_init = $this->login_init();
        if (!$is_init)
            $this->data['msg'] = '首次初始化登陆！';
        else
            $this->data['msg'] = '';

        $submit_form = $this->input->post('submit_form');
        if ($submit_form)
        {
            $this->data['form']['username'] = $this->input->post('username');
            $this->data['form']['password'] = $this->input->post('password');

            $flag = true;
            if (empty($this->data['form']['username']))
            {
                $this->data['msg'] = '用户名不能为空！';
                $flag = false;
            }
            if ($flag && empty($this->data['form']['password']))
            {
                $this->data['msg'] = '密码不能为空！';
                $flag = false;
            }

            if ($flag)
            {
                if (!$is_init)
                {
                    $rs = $this->update_userinfo($this->data['form']['username'], $this->data['form']['password']);
                    if ($rs)
                    {
                        $url = $this->data['base_url'].router_url('backends');
                        redirect($url);
                    }
                    else
                    {
                        $this->data['msg'] = '/data 目录权限设置错误！';
                        $flag = false;
                    }

                }
                else
                {
                    $rs = $this->check_login($this->data['form']['username'], $this->data['form']['password']);

                    if (isset($rs['username']))
                    {
                        //登陆成功写session
                        $session['username'] = $rs['username'];
                        $this->session->set_userdata($session);

                        $this->data['msg'] = '登陆成功！';
                        $url = $this->data['base_url'].router_url('backends');
                        redirect($url);
                    }
                    else
                    {
                        $this->data['msg'] = '登陆失败！';
                        $flag = false;
                    }
                }
            }
        }
        $this->load->view($this->data['method'], $this->data);

    }

    public function logout()
    {
        $this->session->unset_userdata('username');
        $url = $this->data['base_url'].router_url('simple', 'login');
        redirect($url);
    }

    private function check_login($username, $password)
    {
        //用户信息文件
        $userinfo_path = $this->data_path.'userinfo';
        $userinfo = file_get_contents($userinfo_path);
        $userinfo = json_decode($userinfo, TRUE);

        if (isset($userinfo[$username]))
        {
            if (md5($password)===$userinfo[$username])
                return array('username'=>$username);
            else
                return false;
        }
        else
            return false;
    }

    private function login_init()
    {
        //用户信息文件
        $userinfo_path = $this->data_path.'userinfo';
        $userinfo = file_get_contents($userinfo_path);
        $userinfo = json_decode($userinfo, true);
        if (!$userinfo)
            return false;
        else
            return true;

    }

    private function update_userinfo($username, $password)
    {
        //用户信息文件
        $userinfo_path = $this->data_path.'userinfo';
        $rs = @unlink($userinfo_path);
        if ($rs)
        {
            $userinfo = array($username=>md5($password));
            file_put_contents($userinfo_path, json_indent(json_encode($userinfo)));
            return true;
        }
        else
            return false;
    }

}
