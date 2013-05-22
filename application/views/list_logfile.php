<?php include dirname(realpath(__FILE__)).'/__header.php'; ?>


<!-- right content -->
<div class="span9 well-deep" style="height:600px;">
	
	<h3>
		当前日志路径: 
		<a href="<?php echo router_url('logs', 'list_logdir', array('jobname'=>$jobname)); ?>"><?php echo $jobname; ?></a>
		/<?php echo $logdirname; ?>
	</h3>
	<div class="alert alert-info">
		<div id="msg">
			日志按小时分文件存储
		</div>
	</div>
	
	
	<table class="table table-hover">
		<thead>
			<tr>
				<th>名称</th>
				<th>路径</th>
				<th>大小</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($logfilelist as $logfile) { ?>
			<tr>
				<td>
					<a target="_blank" href="<?php echo router_url('logs', 'detail_logfile', array('logfilepath'=>$logfile['logfilepath'])); ?>"><?php echo $logfile['logfilename']; ?></a>
				</td>
				<td>
					<?php echo $logfile['logfilepath']; ?>
				</td>
				<td>
					<?php echo number_format($logfile['logfilesize']); ?>K
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