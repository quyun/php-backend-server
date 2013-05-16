<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="<?php echo $charset; ?>">
	<title>后台进程管理系统</title>


    <link href="<?php echo $static_url; ?>js/bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo $static_url; ?>js/bootstrap/css/bootstrap-responsive.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo $static_url; ?>css/common.css" rel="stylesheet" type="text/css" />
	
	<script src="<?php echo $static_url; ?>js/jquery-1.9.1.min.js" type="text/javascript"></script>
    <script src="<?php echo $static_url; ?>js/bootstrap/js/bootstrap.min.js" type="text/javascript"></script>

</head>
<body>

    <!-- top menu -->
    <div class="navbar navbar-inverse navbar-fixed-top">
        <div class="navbar-inner">
            <div class="container">
                <a class="brand" href="/">后台进程管理系统</a>
				
				<?php if (isset($username)) { ?>
                <ul class="nav">
                    <li <?php if ($class=='members') echo 'class="active"'; ?>><a href="<?php echo router_url('backend'); ?>">后台进程</a></li>
                </ul>
				<?php } ?>	

				<?php if (isset($username)) { ?>
				<ul class="nav pull-right">
					<li class="brand"><?php echo $username; ?></li>
					<li class="divider-vertical"></li>
					<li><a href="<?php echo router_url('simple', 'logout'); ?>">Logout</a></li>
				</ul>
				<?php } ?>
            </div>
        </div>
    </div>
    <!-- top menu end -->
	
	
	
	<div class="container">
        <div class="row-fluid">
		
            <!-- left menu -->
			<?php if (isset($username)) { ?>
            <div class="span3 well">
                <ul class="nav nav-list">
					<li class="nav-header">后台进程管理</li>
					<li <?php if ($method=='list_backend') echo 'class="active"'; ?>><a href="<?php echo router_url($class, 'list_backend'); ?>">进程列表</a></li>
                </ul>
            </div>
			<?php } ?>
            <!-- left menu end -->
			