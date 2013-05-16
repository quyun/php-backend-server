<?php include '__header.php'; ?>

<!-- right content -->
<div class="well">
	<div id="main">
		<div class="alert alert-error" <?php if (!isset($msg) || empty($msg)) echo 'style="display:none"'; ?>>
			<button type="button" class="close" data-dismiss="alert">&times;</button>
			<?php echo $msg; ?>
		</div>
		
		<form action="<?php echo router_url($class, $method); ?>" method="post" class="form-horizontal form-horizontal-small" name="form_login">
			<legend><span class="legend">登录</span></legend>
			<div>
				<div class="control-group">
					<label for="username" class="control-label">用户名</label>
					<div class="controls">
						<input type="text" autofocus="" placeholder="用户名" id="username" name="username">
					</div>
				</div>
				<div class="control-group">
					<label for="password" class="control-label">密码</label>
					<div class="controls">
						<input type="password" placeholder="密码" id="password" name="password">
					</div>
				</div>
				<div class="control-group">
					<div class="controls">
						<button class="btn btn-primary" value="1" name="submit_form" type="submit"><i class="icon-user icon-white"></i> <span>登录</span></button>
						<span style="padding-left:20px" class="help-inline"><a onclick="return false" href="#">忘记密码？</a></span>
					</div>
				</div>
			</div>
		</form>
	</div>
</div>
<!-- right content end -->
        
<?php include '__footer.php'; ?>