<?php include dirname(realpath(__FILE__)).'/__header.php'; ?>


<!-- right content -->
<div class="span9 well-deep" style="height:600px;">
	
	<?php if ($serverread===false) { ?> 
	<div class="alert alert-error">
		<div>后台管理进程未启动</div>
	</div>
	<?php } ?>


	<div class="alert" id="alert" style="display:none">
		<button type="button" class="close" data-dismiss="alert">&times;</button>
		<div id="msg"></div>
	</div>


	<div><a href="<?php echo router_url('backends', 'add_backend'); ?>" class="btn"><i class="icon-plus"></i>添加进程</a></div>
	
	<table class="table table-hover">
        <col style="width:150px;"/>
        <col />
        <col style="width:80px;"/>
        <col style="width:120px;"/>
		<thead>
			<tr>
				<th>进程名称</th>
				<th>路径</th>
				<th>状态</th>
				<th>操作</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($backendlist as $backend) { ?>
			<tr>
				<td>
					<?php echo $backend['jobname']; ?>
				</td>
				<td>
					<?php echo $backend['jobpath']; ?>
				</td>
				<td>
					
					<div class="btn-group">
						<label data-toggle="dropdown"><?php echo $backend['status']; ?></label>
						<?php if ($backend['status']!==false) { ?>
						<ul class="dropdown-menu">
							<li <?php if ($backend['status']==='UP') echo 'class="disabled"'; ?>><a tabindex="-1" href="#" class="start">启动</a></li>
							<li <?php if ($backend['status']==='DOWN') echo 'class="disabled"'; ?>><a tabindex="-1" href="#" class="restart">重启</a></li>
							<li <?php if ($backend['status']==='DOWN') echo 'class="disabled"'; ?>><a tabindex="-1" href="#" class="stop">停止</a></li>
							<li><a tabindex="-1" href="#" class="read">读取</a></li>
						</ul>
						<input type="hidden" class="cls-jobname" value="<?php echo $backend['jobname']; ?>" />
						<?php } ?>
					</div>
					
				</td>
				<td>
					<div class="btn-group">
						<a class="btn btn-small btn-primary dropdown-toggle" data-toggle="dropdown" href="#">
							<i class="icon-wrench icon-white"></i> 操作
							<span class="caret"></span>
						</a>
						<ul class="dropdown-menu">
							<li>
								<a class="btn-small" href="<?php echo router_url('backends', 'edit_backend', array('jobname'=>$backend['jobname'])); ?>">
									<i class="icon-pencil"></i> 编辑
								</a>
							</li>
							<li>
								<a class="btn-small del_backend" href="#"><i class="icon-trash"></i> 删除</a>
								<input type="hidden" value="<?php echo $backend['jobname']; ?>" />
							</li>
							<li>
								<a class="btn-small" href="<?php echo router_url('logs', 'list_logdir', array('jobname'=>$backend['jobname'])); ?>">
									<i class="icon-pencil"></i> 查看日志
								</a>
							</li>
						</ul>
					</div>
				</td>
			</tr>
			<?php } ?>
		</tbody>
	</table>	
</div>
<!-- right content end -->
        
<script type="text/javascript">
	$('.start').click(function(){
		var disabled = $(this).parent().attr('class');
		if (disabled) return false;
		
		var jobname = $(this).parent().parent().nextAll('.cls-jobname').val();
		if (!jobname) return false;
		$.getJSON("<?php echo router_url('backends', 'json_startbackend'); ?>", { jobname: jobname}, function(json){
			if (json.flag)
			{
				$('#alert').addClass('alert-success');
			}
			else
			{
				$('#alert').addClass('alert-error');
			}
			$('#msg').html(json.msg);
			$('#alert').show();
			setTimeout("location.reload();", 800);
		});
	});
	
	$('.restart').click(function(){
		var disabled = $(this).parent().attr('class');
		if (disabled) return false;
		
		var jobname = $(this).parent().parent().nextAll('.cls-jobname').val();
		if (!jobname) return false;
		$.getJSON("<?php echo router_url('backends', 'json_restartbackend'); ?>", { jobname: jobname}, function(json){
			if (json.flag)
			{
				$('#alert').addClass('alert-success');
			}
			else
			{
				$('#alert').addClass('alert-error');
			}
			$('#msg').html(json.msg);
			$('#alert').show();
			setTimeout("location.reload();", 800);
		});
	});
	
	$('.stop').click(function(){
		var disabled = $(this).parent().attr('class');
		if (disabled) return false;
		
		var jobname = $(this).parent().parent().nextAll('.cls-jobname').val();
		if (!jobname) return false;
		$.getJSON("<?php echo router_url('backends', 'json_stopbackend'); ?>", { jobname: jobname}, function(json){
			if (json.flag)
			{
				$('#alert').addClass('alert-success');
			}
			else
			{
				$('#alert').addClass('alert-error');
			}
			$('#msg').html(json.msg);
			$('#alert').show();
			setTimeout("location.reload();", 800);
		});
	});
	
	$('.read').click(function(){
		var jobname = $(this).parent().parent().nextAll('.cls-jobname').val();
		if (!jobname) return false;
		$.getJSON("<?php echo router_url('backends', 'json_readbackend'); ?>", { jobname: jobname}, function(json){
			if (json.flag)
			{
				$('#alert').addClass('alert-success');
			}
			else
			{
				$('#alert').addClass('alert-error');
			}
			if (!json.msg)
				json.msg = 'no output!';
			$('#msg').html(json.msg);
			$('#alert').show();
			//setTimeout("location.reload();", 800);
		});
	});
	
	$('.del_backend').click(function(){
		var jobname = $(this).nextAll('input').val();
		if (!jobname) return false;
		$.getJSON("<?php echo router_url('backends', 'json_delbackend'); ?>", { jobname: jobname}, function(json){
			if (json.flag)
			{
				$('#alert').addClass('alert-success');
			}
			else
			{
				$('#alert').addClass('alert-error');
			}
			if (!json.msg)
				json.msg = 'no output!';
			$('#msg').html(json.msg);
			$('#alert').show();
			setTimeout("location.reload();", 800);
		});
	});
	
</script>	
<?php include dirname(realpath(__FILE__)).'/__footer.php'; ?>