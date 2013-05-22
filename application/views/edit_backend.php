<?php include dirname(realpath(__FILE__)).'/__header.php'; ?>

<!-- right content -->
<div class="span9 well">

	<div class="alert alert-error" <?php if (!isset($msg) || empty($msg)) echo 'style="display:none"'; ?>>
		<button type="button" class="close" data-dismiss="alert">&times;</button>
		<strong>错误：</strong> <?php echo $msg; ?>
	</div>
	
	<form class="form-horizontal" action="<?php echo router_url($class, $method); ?>" method="post" name="formbackend">
		
		<div class="control-group">
			<label class="control-label" for="jobname">进程名称</label>
			<div class="controls">
				<input type="text" value="<?php if (isset($form)) echo $form['jobname']; ?>" disabled>
				<input type="hidden" id="jobname" name="jobname" value="<?php if (isset($form)) echo $form['jobname']; ?>" />
			</div>
		</div>
		
		
		<div class="control-group">
			<label class="control-label" for="jobpath">进程路径</label>
			<div class="controls">
				<input type="text" id="jobpath" name="jobpath" style="width:500px" placeholder="进程路径" value="<?php if (isset($form)) echo $form['jobpath']; ?>">
			</div>
		</div>
		
		<div class="control-group">
			<label class="control-label" for="writelog">自动记录日志</label>
			<div class="controls">
				<input type="checkbox" id="writelog" name="writelog" value="1" <?php if (isset($form) && $form['writelog']) echo 'checked="checked"'; ?>/> 
			</div>
		</div>
		
		<div class="control-group">
			<label class="control-label" for="autostart">自动启动</label>
			<div class="controls">
				<input type="checkbox" id="autostart" name="autostart" value="1" <?php if (isset($form) && $form['autostart']) echo 'checked="checked"'; ?>/> 
			</div>
		</div>
		
		<div class="control-group">
			<div class="controls">
				<button type="submit" class="btn" value="1" name="submit_form">更新</button>
				<button type="button" class="btn return">返回</button>
			</div>
		</div>
		
			
	</form>
</div>
<!-- right content end -->
        

<?php include dirname(realpath(__FILE__)).'/__footer.php'; ?>
