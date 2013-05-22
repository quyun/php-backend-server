php-backend-server
==================

PHP实现的后台进程管理服务器，可以通过SOCKET API接口控制进程。


## Web 控制面板

这是一套针对 php-backend-server 实现的面板，通过它可以可视化地对后台进程进行操作。

项目地址：[php-backend-web](https://github.com/quyun/php-backend-web)


## 客户端类库接口

客户端类库位于 client 目录下，下面是各个接口的说明。

#### init - 初始化服务器信息

###### 定义
	void init($server_ip, $server_port)

###### 参数
	$server_ip：服务器IP
	$server_port：服务器端口

###### 说明
	如果不调用init进行初始化，则默认的服务器IP和端口为127.0.0.1:13467。

###### 返回
	无返回值。

###### 示例

```php
<?php
require_once('Backend.class.php');

// 服务器及端口设置
$be = new Backend();
$be->init('127.0.0.1', 13456); // 显示初始化服务器IP和端口
```


#### status - 查询服务器状态

###### 定义
	string status($jobname)

###### 参数
	$jobname：进程名称

###### 返回
	返回进程名为$jobname的进程的运行状态。
	UP表示正在运行；
	DOWN表示进程不存在或已停止。

###### 示例

```php
<?php
require_once('Backend.class.php');

$be = new Backend();
echo $be->status('testproc');
```


#### start - 开启新进程

###### 定义
	string start($jobname, $script_cmd, $buffer_lines)

###### 参数
	$jobname：要开启的新进程的名称
	$script_cmd：要开启的新进程的脚本命令
	$buffer_lines：要开启的新进程输出缓冲区的行数，默认为20行

###### 返回
	开启成功则返回OK，开启失败返回FAILED。
	进程名已经存在，或程序路径不存在，都将返回FAILED。

###### 示例

```php
<?php
require_once('Backend.class.php');

$be = new Backend();
echo $be->start('testproc', '/work/www/test.php someparams');
```


#### stop - 结束进程

###### 定义
	string stop($jobname, $graceful)

###### 参数
	$jobname：要停止的进程的名称
	$graceful：是否以优雅方式结束进程

###### 返回
	结束成功则返回OK，结束失败返回FAILED。
	进程名不存在，或进程已停止，都将返回FAILED。

###### 示例

```php
<?php
require_once('Backend.class.php');

$be = new Backend();
echo $be->stop('testproc');
```

###### 优雅结束进程示例

控制端：

```php
<?php
require_once('Backend.class.php');

$be = new Backend();
echo $be->stop('testproc', TRUE); // 优雅结束
```

进程端：

```php
<?php
declare(ticks = 1);

// signal handler function
function sig_handler($signo)
{
	global $terminated;
	switch ($signo) {
		case SIGTERM:
			$terminated = TRUE;
	}
}

// setup signal handlers
pcntl_signal(SIGTERM, "sig_handler");

$terminated = FALSE;

$be = new Backend();
while (1)
{
     // 循环执行任务

     if ($terminated) break;
}
```


#### restart - 重启进程
###### 定义
	string restart($jobname, $graceful)

###### 参数
	$jobname：要重启的进程的名称
	$graceful：是否以优雅方式结束进程

###### 返回
	重启成功则返回OK，重启失败返回FAILED。
	进程名不存在，或进程已停止，或程序路径不存在，都将返回FAILED。

###### 示例

```php
<?php
require_once('Backend.class.php');

$be = new Backend();
echo $be->restart('testproc');
```


#### read - 读取进程输出缓冲
###### 定义
	string read($jobname)

###### 参数
	$jobname：要读取的进程名称

###### 返回
	进程名称为$jobname的输出缓冲区内容。

###### 示例

```php
<?php
require_once('Backend.class.php');

$be = new Backend();
echo $be->read('testproc');
```


#### servermem - 读取进程服务器的内存使用量

###### 定义
	string servermem()

###### 返回
	进程服务器的内存使用量。

###### 示例

```php
<?php
require_once('Backend.class.php');

$be = new Backend();
echo $be-> servermem ();
```


#### serverread - 读取进程服务器的输出

###### 定义
	string serverread()

###### 返回
	进程服务器的输出缓冲区内容。

###### 示例

```php
<?php
require_once('Backend.class.php');

$be = new Backend();
echo $be->serverread();
```