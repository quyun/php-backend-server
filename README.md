php-backend-server
==================

PHP实现的后台进程管理服务器，可以通过SOCKET API接口对后台进程进行管理和控制。

## 启动服务器

启动服务器很容易，只需要直接运行以下命令即可：

$ php server.php


## 服务器配置

打开 config.php，可对 Socket 监听地址和时区进行配置。

```php
<?php
// 服务器及端口设置
$server_ip   = '127.0.0.1';
$server_port = 13123;

// 时区设置
$timezone = 'Asia/Shanghai';

// 选择要自动加载的插件，用逗号分隔，*表示所有插件
$autoload_plugins = '*';
```

## 客户端类参考

客户端类库位于 client 目录下，下面是各个接口的说明。

#### init - 初始化服务器信息

###### 定义
	void init($server_ip, $server_port)

###### 参数
	$server_ip：服务器IP
	$server_port：服务器端口

###### 说明
	如果不调用init进行初始化，则默认的服务器IP和端口为127.0.0.1:13123。

###### 返回
	无返回值。

###### 示例

```php
<?php
require_once('Backend.class.php');

// 服务器及端口设置
$be = new Backend();
$be->init('127.0.0.1', 13123); // 显示初始化服务器IP和端口
```


#### add - 添加进程配置

###### 定义
	string add($jobname, $command, $setting)

###### 参数
	$jobname：要添加的新进程的名称
	$command：程序路径
    $setting         程序执行设置，已知参数如下：
       * params      程序参数
       * buffersize  缓冲区行数，默认为20行
       * writelog    是否将进程输出写入日志，默认为否
       * autostart   是否随服务器启动（autostart插件参数）

###### 返回
	添加成功返回OK，添加失败返回FAILED。
	进程已经存在，将返回FAILED。

###### 示例

```php
<?php
require_once('Backend.class.php');

$be = new Backend();
echo $be->add('testproc', '/work/www/test.php', array('writelog'=>TRUE));
```


#### delete - 删除进程配置

###### 定义
	string delete($jobname)

###### 参数
	$jobname：要删除的进程的名称

###### 返回
	删除成功返回OK，删除失败返回FAILED。
	进程不存在，进程还在运行，都将返回FAILED。
	删除配置时不会删除日志文件。

###### 示例

```php
<?php
require_once('Backend.class.php');

$be = new Backend();
echo $be->delete('testproc');
```


#### update - 更新进程配置

###### 定义
	string update($jobname, $setting)

###### 参数
	$jobname：要更新的进程的名称
	$setting：进程配置项，同 add

###### 返回
	更新成功返回OK，更新失败返回FAILED。

###### 示例

```php
<?php
require_once('Backend.class.php');

$be = new Backend();

// 记录日志
echo $be->add('testproc', '/work/www/test.php', array('writelog'=>TRUE));

// 更新为不记录日志
echo $be->update('testproc', array('writelog'=>FALSE));
```


#### get - 查看进程配置

###### 定义
	string get($jobname)

###### 参数
	$jobname：要查看的进程的名称

###### 返回
	更新成功返回进程配置信息，失败返回NULL。

###### 示例

```php
<?php
require_once('Backend.class.php');

$be = new Backend();

echo $be->add('testproc', '/work/www/test.php', array('writelog'=>TRUE));

/* 输出示例：
Array
(
    [command] => /work/www/test.php
    [params] => 
    [buffersize] => 20
    [writelog] => 1
)
*/
print_r($be->get('testproc'));
```

#### getall - 查看所有进程配置

###### 定义
	string getall()

###### 返回
	更新成功返回进程配置信息，失败返回NULL。

###### 示例

```php
<?php
require_once('Backend.class.php');

$be = new Backend();

echo $be->add('testproc', '/work/www/test.php', array('writelog'=>TRUE));

/* 输出示例：
Array
(
    [testproc] => Array
        (
            [command] => /work/www/test.php
            [params] => 
            [buffersize] => 20
            [writelog] => 1
        )

)
*/
print_r($be->getall());
```


#### start - 开启进程

###### 定义
	string start($jobname)

###### 参数
	$jobname：要开启的进程的名称

###### 返回
	开启成功则返回OK，开启失败返回FAILED。
	进程已在运行，或程序路径不存在，都将返回FAILED。

###### 示例

```php
<?php
require_once('Backend.class.php');

$be = new Backend();
echo $be->add('testproc', '/work/www/test.php');
echo $be->start('testproc');
```


#### stop - 结束进程

###### 定义
	string stop($jobname)

###### 参数
	$jobname：要停止的进程的名称

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


#### restart - 重启进程
###### 定义
	string restart($jobname)

###### 参数
	$jobname：要重启的进程的名称

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


#### status - 查询后台进程状态

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


#### statusall - 查询所有后台进程状态

###### 定义
	string statusall()

###### 返回
	返回所有进程的运行状态。
	UP表示正在运行；
	DOWN表示进程不存在或已停止。

###### 示例

```php
<?php
require_once('Backend.class.php');

$be = new Backend();

/* 输出示例：
Array
(
    [testproc] => UP
)
*/
print_r($be->statusall());
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


#### mem - 查询进程的内存使用量

###### 定义
	string mem($jobname)

###### 参数
	$jobname：要获取内存使用量的进程名称

###### 返回
	进程的内存使用量，单位为 kB。

###### 示例

```php
<?php
require_once('Backend.class.php');

$be = new Backend();
echo $be->mem('testproc');
```


#### memall - 查询所有进程的内存使用量

###### 定义
	string memall()

###### 返回
	所有进程的内存使用量，单位为 kB。

###### 示例

```php
<?php
require_once('Backend.class.php');

$be = new Backend();

/* 输出示例：
Array
(
    [testproc] => 9464
)
*/
echo $be->memall();
```


#### servermem - 查询进程服务器的内存使用量

###### 定义
	string servermem()

###### 返回
	进程服务器的内存使用量，单位为 kB。

###### 示例

```php
<?php
require_once('Backend.class.php');

$be = new Backend();
echo $be->servermem();
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

/* 输出示例：
Plugin "autostart" loaded.
[13-06-18 20:54:08] Backend server starting, binding 127.0.0.1:13123.
[13-06-18 20:54:08] 
[13-06-18 20:54:08] 
[13-06-18 20:54:08] Waiting for new command...
[13-06-18 20:54:08] ADD {"writelog":true,"jobname":"testproc","command":"\/work\/www\/test.php"}
[13-06-18 20:54:10] FAILED
[13-06-18 20:54:10] 
[13-06-18 20:54:10] Waiting for new command...
[13-06-18 20:54:10] SERVERREAD
[13-06-18 20:54:10]
*/
echo $be->serverread();
```


## 相关资源：PHP 后台进程控制面板

这是一套针对 php-backend-server 实现的面板，通过它可以可视化地对后台进程进行管理操作。

项目地址：[php-backend-web](https://github.com/quyun/php-backend-web)