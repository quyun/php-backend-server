<?php  if (!defined('BASEPATH')) exit('No direct script access allowed');

class Base_Model extends CI_Model {

    function __construct()
    {
        parent::__construct();
    }
	
	public function __call($method_name, $params)
    {

        if (!method_exists($this, $method_name))
            throw new Exception("Method \"{$method_name}\" does not exist!");

        $r_method = new ReflectionMethod($this, $method_name);
        $r_params = $r_method->getParameters();

        $params = isset($params[0]) ? $params[0] : $params;
        $merged_params = array();

        foreach ($r_params as $r_param)
        {
            $r_param_name = $r_param->getName();

            if ($r_param->isDefaultValueAvailable())
            {
                if (isset($params[$r_param_name]))
                    $merged_params[$r_param_name] = $params[$r_param_name];
                else
                    $merged_params[$r_param_name] = $r_param->getDefaultValue();
            }
            else
            {
                if (!isset($params[$r_param_name]))
                    throw new Exception("Method \"$method_name\" Parameter \"{$r_param_name}\" is missing!");

                $merged_params[$r_param_name] = $params[$r_param_name];
            }
        }

        return call_user_func_array(array($this, $method_name), $merged_params);
    }
}