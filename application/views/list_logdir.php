<?php include dirname(realpath(__FILE__)).'/__header.php'; ?>


<!-- right content -->
<div class="span9 well-deep" style="height:600px;">

	<h3>当前进程名称: <?php echo $jobname; ?></h3>
	<div class="alert alert-info">
		<div id="msg">
			日志按天分目录存储
		</div>
	</div>
	
	<table class="table table-hover">
        
		<thead>
			<tr>
				<th>名称</th>
				<th>路径</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($loglist as $log) { ?>
			<tr>
				<td>
					<a href="<?php echo router_url('logs', 'list_logfile', array('jobname'=>$jobname, 'logdirname'=>$log['logname'])); ?>">
						<?php echo $log['logname']; ?>
					</a>
				</td>
				<td>
					<?php echo $log['logpath']; ?>
				</td>
			</tr>
			<?php } ?>
		</tbody>
	</table>	
	<?php echo $page; ?>
</div>
<!-- right content end -->
        
<script type="text/javascript">
	
	
</script>	
<?php include dirname(realpath(__FILE__)).'/__footer.php'; ?>