<?php

//生成url

function router_url($controller='', $action='index', $params=array(), $default_name='default', $suffix='html') 
{
	$ci_bj = get_instance();
	$section_url = $ci_bj->config->item("section_url");
	$url = "";
	if (empty($controller) && empty($action))
		return $url;
	if ($section_url)
	{
		$url .= $controller . '/' . $action . '/';
		if (empty($params))
			$url .= $default_name . '.' . $suffix;
		else
		{
			$count = 1;
			foreach ($params as $k=>$v)
			{
				if ($count===1)
				{
					$url .= $k . '-' . $v;
					$count++;
				}
				else
					$url .= '-' . $k . '-' . $v;
			}
			$url .= '.' . $suffix;
		}
	}
	else
	{
		$url .="index.php?c=" . $controller . '&m=' . $action;
		if (!empty($params))
		{
			foreach ($params as $k=>$v)
				$url .= '&' . $k . '=' . $v;
		}
	}
	return $url;
}


?>