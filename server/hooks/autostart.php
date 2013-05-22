<?php

	require_once(dirname(__FILE__) . '/../Backend.class.php');
	
	$obj_backend = new Backend();
    $obj_backend->init($server_ip, $server_port);
	
	
	$backendinfo = file_get_contents($backendinfo_path);
	$backendinfo = json_decode($backendinfo, true);
	
	if (!empty($backendinfo))
	{
		foreach ($backendinfo as $backend)
		{
			if ($backend['autostart'] === true)
			{
				$status = $obj_backend->status($backend['jobname']);
				if ($status !== 'UP')
					$obj_backend->start($backend['jobname'], $backend['jobpath'], 20, $backend['writelog'], $backend['autostart']);
			}
		}
	}