php-backend-server
==================

PHP实现的后台进程管理服务器，可以通过SOCKET API接口对后台进程进行管理和控制。


## 环境依赖

请确保已安装以下 PHP 模块/扩展：

- pcntl
- posix
- sysvsem
- sysvshm

可选模块（弥补 proc_open 无法关闭父进程 fd 的问题）：

- [fildes](https://github.com/hilyjiang/php-fildes)


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

// 插件配置
$plugin_settings = array(
    'logcleaner' => array(              // logcleaner插件配置
        'clean_interval' => 3600,       // 清理间隔时间
        'logfile_expire' => 86400*7,    // 日志过期时间
    ),
    'guarder' => array(                 // guarder插件配置
        'check_interval' => 60,         // 检测间隔时间
    ),
);
```

请确保 server/data 目录可写入。

## 客户端示例

该文件位于 client/examples/ 目录下。

```php
<?php
require_once(dirname(__FILE__).'/../Backend.class.php');

$be = new Backend;
$be->init('127.0.0.1', 13123);

print_r($be->add('test', __DIR__.'/scripts/test.php', array('writelog'=>TRUE)));
print_r($be->start('test'));
print_r($be->read('test'));
```


## 客户端类快速参考

```
Class Backend
- void init($server_ip, $server_port)           初始化服务器信息
- array add($jobname, $command, $setting)       添加进程配置
- array delete($jobname, $setting)              删除进程配置
- array update($jobname, $setting, $setting)    更新进程配置
- array get($jobname, $setting)                 查看进程配置
- array getall($setting)                        查看所有进程配置
- array start($jobname, $setting)               开启进程
- array stop($jobname, $setting)                结束进程
- array restart($jobname, $setting)             重启进程
- array status($jobname, $setting)              查询后台进程状态
- array statusall($setting)                     查询所有后台进程状态
- array read($jobname, $setting)                读取进程输出缓冲
- array mem($jobname, $setting, $setting)       查询进程的内存使用量
- array memall($setting)                        查询所有进程的内存使用量
- array servermem($setting)                     查询进程服务器的内存使用量
- array serverread($setting)                    读取进程服务器的输出
- void set_auth($username, $password)                            (auth插件) 设置用户名/密码
- array auth_getenable($setting)                                 (auth插件) 获取是否启用身份验证
- array auth_setenable($enable, $setting)                        (auth插件) 设置身份验证启用/禁用
- array auth_add($username, $password, $privileges, $setting)    (auth插件) 添加用户
- array auth_delete($username, $setting)                         (auth插件) 删除用户
- array auth_update($username, $setting)                         (auth插件) 更新用户信息
- array auth_get($username, $setting)                            (auth插件) 查询单个用户信息
- array auth_getall($setting)                                    (auth插件) 查询所有用户信息
- array logexplorer_listdir($jobname, $setting)                  (logexplorer插件) 查询进程的日志目录列表
- array logexplorer_listfile($jobname, $dirname, $setting)       (logexplorer插件) 查询进程某个日志目录下的日志列表
- array logexplorer_get($jobname, $dirname, $filename, $setting) (logexplorer插件) 读取进程某个日志文件内容
- array logexplorer_serverlistdir($setting)                      (logexplorer插件) 查询服务器的日志目录列表
- array logexplorer_serverlistfile($dirname, $setting)           (logexplorer插件) 查询服务器某个日志目录下的日志列表
- array logexplorer_serverget($dirname, $filename, $setting)     (logexplorer插件) 读取服务器日志文件内容
- array scheduler_add($jobname, $setting)                        (scheduler插件) 添加新的进程调度配置
- array scheduler_delete($jobname, $uuid, $setting)              (scheduler插件) 删除进程调度配置
- array scheduler_update($jobname, $uuid, $setting)              (scheduler插件) 更新进程调度配置
- array scheduler_get($jobname, $uuid, $setting)                 (scheduler插件) 查询进程调度配置信息
- array scheduler_getall($setting)                               (scheduler插件) 查询所有的进程调度配置信息
- array scheduler_getlog($jobname, $uuid, $setting)              (scheduler插件) 查询进程调度执行历史
```

## 客户端类参考

客户端类库位于 client 目录下，下面是各个接口的说明。

#### init - 初始化服务器信息

###### 定义
    void init($server_ip, $server_port)

###### 参数
    $server_ip       服务器IP
    $server_port     服务器端口

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
    array add($jobname, $command, $setting)

###### 参数
    $jobname         进程名称
    $command         程序路径
    $setting         程序执行设置，已知参数如下：
       - params      程序参数
       - buffersize  缓冲区行数，默认为20行
       - writelog    是否将进程输出写入日志，默认为否
       - autostart   是否随服务器启动（autostart插件参数）
       - guard       是否监控该进程，非人为退出后自动启动（guarder插件参数）
       - auth        auth插件参数
         - username  用户名
         - password  密码

###### 返回
    array('code'=>$code)
       - code       'OK', 'FAILED', 'DENIED'（auth插件）

###### 示例

```php
<?php
require_once('Backend.class.php');

$be = new Backend();
print_r($be->add('testproc', '/work/www/test.php', array('writelog'=>TRUE)));
```


#### delete - 删除进程配置

###### 定义
    array delete($jobname, $setting)

###### 参数
    $jobname         进程名称
    $setting         程序执行设置，已知参数如下：
       - auth        auth插件参数
         - username  用户名
         - password  密码

###### 返回
    array('code'=>$code)
       - code       'OK', 'FAILED', 'DENIED'（auth插件）
                    删除成功返回OK，删除失败返回FAILED。
                    进程不存在，进程还在运行，都将返回FAILED。
                    删除配置时不会删除日志文件。

###### 示例

```php
<?php
require_once('Backend.class.php');

$be = new Backend();
print_r($be->delete('testproc'));
```


#### update - 更新进程配置

###### 定义
    array update($jobname, $setting, $setting)

###### 参数
    $jobname         进程名称
    $setting         程序执行设置，同 add

###### 返回
    array('code'=>$code)
       - code       'OK', 'FAILED', 'DENIED'（auth插件）

###### 示例

```php
<?php
require_once('Backend.class.php');

$be = new Backend();

// 记录日志
print_r($be->add('testproc', '/work/www/test.php', array('writelog'=>TRUE)));

// 更新为不记录日志
print_r($be->update('testproc', array('writelog'=>FALSE)));
```


#### get - 查看进程配置

###### 定义
    array get($jobname, $setting)

###### 参数
    $jobname         进程名称
    $setting         程序执行设置，已知参数如下：
       - auth        auth插件参数
         - username  用户名
         - password  密码

###### 返回
    array('code'=>$code, 'data'=>$data)
       - code        'OK', 'FAILED', 'DENIED'（auth插件）
       - data        进程信息数组

###### 示例

```php
<?php
require_once('Backend.class.php');

$be = new Backend();

print_r($be->add('testproc', '/work/www/test.php', array('writelog'=>TRUE)));

/* 输出示例：
Array
(
    [command] => /work/www/test.php
    [params] => 
    [buffersize] => 20
    [writelog] => 1
)
*/
$result = $be->get('testproc');
print_r($result['data']);
```

#### getall - 查看所有进程配置

###### 定义
    array getall($setting)

###### 参数
    $setting         程序执行设置，已知参数如下：
       - auth        auth插件参数
         - username  用户名
         - password  密码

###### 返回
    array('code'=>$code, 'data'=>$data)
       - code        'OK', 'FAILED', 'DENIED'（auth插件）
       - data        所有进程的信息数组

###### 示例

```php
<?php
require_once('Backend.class.php');

$be = new Backend();

print_r($be->add('testproc', '/work/www/test.php', array('writelog'=>TRUE)));

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
$result = $be->getall();
print_r($result['data']);
```


#### start - 开启进程

###### 定义
    array start($jobname, $setting)

###### 参数
    $jobname         进程名称
    $setting         程序执行设置，已知参数如下：
       - auth        auth插件参数
         - username  用户名
         - password  密码

###### 返回
    array('code'=>$code)
       - code       'OK', 'FAILED', 'DENIED'（auth插件）
                    进程已在运行，或程序路径不存在，都将返回FAILED。

###### 示例

```php
<?php
require_once('Backend.class.php');

$be = new Backend();
print_r($be->add('testproc', '/work/www/test.php'));
print_r($be->start('testproc'));
```


#### stop - 结束进程

###### 定义
    array stop($jobname, $setting)

###### 参数
    $jobname         进程名称
    $setting         程序执行设置，已知参数如下：
       - auth        auth插件参数
         - username  用户名
         - password  密码

###### 返回
    array('code'=>$code)
       - code       'OK', 'FAILED', 'DENIED'（auth插件）
                    进程名不存在，或进程已停止，都将返回FAILED。

###### 示例

```php
<?php
require_once('Backend.class.php');

$be = new Backend();
print_r($be->stop('testproc'));
```


#### restart - 重启进程

###### 定义
    array restart($jobname, $setting)

###### 参数
    $jobname         进程名称
    $setting         程序执行设置，已知参数如下：
       - auth        auth插件参数
         - username  用户名
         - password  密码

###### 返回
    array('code'=>$code)
       - code       'OK', 'FAILED', 'DENIED'（auth插件）
                    进程名不存在，或进程已停止，或程序路径不存在，都将返回FAILED。

###### 示例

```php
<?php
require_once('Backend.class.php');

$be = new Backend();
print_r($be->restart('testproc'));
```


#### status - 查询后台进程状态

###### 定义
    array status($jobname, $setting)

###### 参数
    $jobname         进程名称
    $setting         程序执行设置，已知参数如下：
       - auth        auth插件参数
         - username  用户名
         - password  密码

###### 返回
    array('code'=>$code, 'data'=>$data)
       - code        'OK', 'FAILED', 'DENIED'（auth插件）
       - data        UP（正常）、DOWN（未启动）

###### 示例

```php
<?php
require_once('Backend.class.php');

$be = new Backend();
print_r($be->status('testproc'));
```


#### statusall - 查询所有后台进程状态

###### 定义
    array statusall($setting)

###### 参数
    $setting         程序执行设置，已知参数如下：
       - auth        auth插件参数
         - username  用户名
         - password  密码

###### 返回
    array('code'=>$code, 'data'=>$data)
       - code        'OK', 'FAILED', 'DENIED'（auth插件）
       - data        所有进程状态数组

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
$result = $be->statusall();
print_r($result['data']);
```


#### read - 读取进程输出缓冲
###### 定义
    array read($jobname, $setting)

###### 参数
    $jobname         进程名称
    $setting         程序执行设置，已知参数如下：
       - auth        auth插件参数
         - username  用户名
         - password  密码

###### 返回
    array('code'=>$code, 'data'=>$data)
       - code        'OK', 'FAILED', 'DENIED'（auth插件）
       - data        进程输出缓冲区内容

###### 示例

```php
<?php
require_once('Backend.class.php');

$be = new Backend();
$result = $be->read('testproc');
print_r($result);
```


#### mem - 查询进程的内存使用量

###### 定义
    array mem($jobname, $setting, $setting)

###### 参数
    $jobname         进程名称
    $setting         程序执行设置，已知参数如下：
       - auth        auth插件参数
         - username  用户名
         - password  密码

###### 返回
    array('code'=>$code, 'data'=>$data)
       - code        'OK', 'FAILED', 'DENIED'（auth插件）
       - data        进程的内存使用量，单位为 kB

###### 示例

```php
<?php
require_once('Backend.class.php');

$be = new Backend();
print_r($be->mem('testproc'));
```


#### memall - 查询所有进程的内存使用量

###### 定义
    array memall($setting)

###### 参数
    $setting         程序执行设置，已知参数如下：
       - auth        auth插件参数
         - username  用户名
         - password  密码

###### 返回
    array('code'=>$code, 'data'=>$data)
       - code        'OK', 'FAILED', 'DENIED'（auth插件）
       - data        所有进程的内存使用量，单位为 kB

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
$result = $be->memall();
print_r($result['data']);
```


#### servermem - 查询进程服务器的内存使用量

###### 定义
    array servermem($setting)

###### 参数
    $setting         程序执行设置，已知参数如下：
       - auth        auth插件参数
         - username  用户名
         - password  密码

###### 返回
    array('code'=>$code, 'data'=>$data)
       - code        'OK', 'FAILED', 'DENIED'（auth插件）
       - data        进程服务器的内存使用量，单位为 kB

###### 示例

```php
<?php
require_once('Backend.class.php');

$be = new Backend();
print_r($be->servermem());
```


#### serverread - 读取进程服务器的输出

###### 定义
    array serverread($setting)

###### 参数
    $setting         程序执行设置，已知参数如下：
       - auth        auth插件参数
         - username  用户名
         - password  密码

###### 返回
    array('code'=>$code, 'data'=>$data)
       - code        'OK', 'FAILED', 'DENIED'（auth插件）
       - data        进程服务器的输出缓冲区内容

###### 示例

```php
<?php
require_once('Backend.class.php');

$be = new Backend();

/* 输出示例：
[13-06-18 20:54:08] Plugin "autostart" loaded.
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
$result = $be->serverread();
print_r($result['data']);
```


#### set_auth - 设置用户名/密码

###### 定义
    void set_auth($username, $password)

###### 参数
    $username        用户名
    $password        密码

###### 说明
    set_auth 设置用户名/密码后，之后的请求参数中就不需要在参数中指定用户名和密码了。

###### 返回
    无返回值。

###### 示例

```php
<?php
require_once('Backend.class.php');

$be = new Backend();
$be->set_auth('username', 'password');
```


#### auth_getenable - 获取是否启用身份验证

###### 定义
    array auth_getenable($setting)

###### 参数
    $setting         程序执行设置
       - auth        auth插件参数
         - username  用户名
         - password  密码

###### 返回
    array('code'=>$code, 'data'=>$data)
       - code       'OK', 'FAILED', 'DENIED'（auth插件）
       - data       是否启用身份验证, TRUE/FALSE


#### auth_setenable - 设置身份验证启用/禁用

###### 定义
    array auth_setenable($enable, $setting)

###### 参数
    $enable          TRUE：启用 FALSE：禁用
    $setting         程序执行设置
       - auth        auth插件参数
         - username  用户名
         - password  密码

###### 返回
    array('code'=>$code)
       - code       'OK', 'FAILED', 'DENIED'（auth插件）


#### auth_add - 添加用户

###### 定义
    array auth_add($username, $password, $privileges, $setting)

###### 参数
    $username        用户名
    $password        密码
    $privileges      权限，用逗号分隔，*表示所有权限
    $setting         程序执行设置&更多用户配置
       - auth        auth插件参数
         - username  用户名
         - password  密码
       剩余值将作为用户配置项

###### 返回
    array('code'=>$code)
       - code       'OK', 'FAILED', 'DENIED'（auth插件）


#### auth_delete - 删除用户

###### 定义
    array auth_delete($username, $setting)

###### 参数
    $username        用户名
    $setting         程序执行设置，已知参数如下：
       - auth        auth插件参数
         - username  用户名
         - password  密码

###### 返回
    array('code'=>$code)
       - code       'OK', 'FAILED', 'DENIED'（auth插件）
                    删除成功返回OK，删除失败返回FAILED。
                    进程不存在，进程还在运行，都将返回FAILED。
                    删除配置时不会删除日志文件。


#### auth_update - 更新用户信息

###### 定义
    array auth_update($username, $setting)

###### 参数
    $username        用户名
    $setting         程序执行设置&更多用户配置
       - password    密码
       - privileges  权限，用逗号分隔，*表示所有权限
       - auth        auth插件参数
         - username  用户名
         - password  密码
       剩余值将作为用户配置项

###### 返回
    array('code'=>$code)
       - code       'OK', 'FAILED', 'DENIED'（auth插件）


#### auth_get - 查询单个用户信息

###### 定义
    array auth_get($username, $setting)

###### 参数
    $username        用户名
    $setting         程序执行设置，已知参数如下：
       - auth        auth插件参数
         - username  用户名
         - password  密码

###### 返回
    array('code'=>$code, 'data'=>$data)
       - code        'OK', 'FAILED', 'DENIED'（auth插件）
       - data        用户信息数组


#### auth_getall - 查询所有用户信息

###### 定义
    array auth_getall($setting)

###### 参数
    $setting         程序执行设置，已知参数如下：
       - auth        auth插件参数
         - username  用户名
         - password  密码

###### 返回
    array('code'=>$code, 'data'=>$data)
       - code        'OK', 'FAILED', 'DENIED'（auth插件）
       - data        所有用户的信息数组


#### logexplorer_listdir - 查询进程的日志目录列表

###### 定义
    array logexplorer_listdir($jobname, $setting)

###### 参数
    $jobname         进程名称
    $setting         程序执行设置，已知参数如下：
       - auth        auth插件参数
         - username  用户名
         - password  密码

###### 返回
    array('code'=>$code, 'data'=>$data)
       - code        'OK', 'FAILED', 'DENIED'（auth插件）
       - data        日志目录列表


#### logexplorer_listfile - 查询进程某个日志目录下的日志列表

###### 定义
    array logexplorer_listfile($jobname, $dirname, $setting)

###### 参数
    $jobname         进程名称
    $dirname         日志目录名
    $setting         程序执行设置，已知参数如下：
       - auth        auth插件参数
         - username  用户名
         - password  密码

###### 返回
    array('code'=>$code, 'data'=>$data)
       - code        'OK', 'FAILED', 'DENIED'（auth插件）
       - data        日志文件列表


#### logexplorer_get - 读取进程某个日志文件内容

###### 定义
    array logexplorer_get($jobname, $dirname, $filename, $setting)

###### 参数
    $jobname         进程名称
    $dirname         日志目录名
    $filename        日志文件名
    $setting         程序执行设置，已知参数如下：
       - auth        auth插件参数
         - username  用户名
         - password  密码

###### 返回
    array('code'=>$code, 'data'=>$data)
       - code        'OK', 'FAILED', 'DENIED'（auth插件）
       - data        日志内容


#### logexplorer_serverlistdir - 查询服务器的日志目录列表

###### 定义
    array logexplorer_serverlistdir($setting)

###### 参数
    $setting         程序执行设置，已知参数如下：
       - auth        auth插件参数
         - username  用户名
         - password  密码

###### 返回
    array('code'=>$code, 'data'=>$data)
       - code        'OK', 'FAILED', 'DENIED'（auth插件）
       - data        日志目录列表


#### logexplorer_serverlistfile - 查询服务器某个日志目录下的日志列表

###### 定义
    array logexplorer_serverlistfile($dirname, $setting)

###### 参数
    $dirname         日志目录名
    $setting         程序执行设置，已知参数如下：
       - auth        auth插件参数
         - username  用户名
         - password  密码

###### 返回
    array('code'=>$code, 'data'=>$data)
       - code        'OK', 'FAILED', 'DENIED'（auth插件）
       - data        日志文件列表


#### logexplorer_serverget - 读取服务器日志文件内容

###### 定义
    array logexplorer_serverget($dirname, $filename, $setting)

###### 参数
    $dirname         日志目录名
    $filename        日志文件名
    $setting         程序执行设置，已知参数如下：
       - auth        auth插件参数
         - username  用户名
         - password  密码

###### 返回
    array('code'=>$code, 'data'=>$data)
       - code        'OK', 'FAILED', 'DENIED'（auth插件）
       - data        日志内容


#### scheduler_add - 添加新的进程调度配置

###### 定义
    array scheduler_add($jobname, $setting)

###### 参数
    $jobname         进程名
    $setting         程序执行设置，已知参数如下：
     - enable      是否立即开启调度
     - condition   调度时间条件设置
       - auth        auth插件参数
         - username  用户名
         - password  密码

###### 返回
    array('code'=>$code, 'data'=>$data)
       - code        'OK', 'FAILED', 'DENIED'（auth插件）
       - data        新创建的进程调度配置的UUID


#### scheduler_delete - 删除进程调度配置

###### 定义
    array scheduler_delete($jobname, $uuid, $setting)

###### 参数
    $jobname         进程名
    $uuid            进程调度配置的UUID
    $setting         程序执行设置，已知参数如下：
       - auth        auth插件参数
         - username  用户名
         - password  密码

###### 返回
    array('code'=>$code)
       - code        'OK', 'FAILED', 'DENIED'（auth插件）


#### scheduler_update - 更新进程调度配置

###### 定义
    array scheduler_update($jobname, $uuid, $setting)

###### 参数
    $jobname         进程名
    $uuid            进程调度配置的UUID
    $setting         程序执行设置，已知参数如下：
       - enable      是否立即开启调度
       - condition   调度时间条件设置
       - auth        auth插件参数
         - username  用户名
         - password  密码

###### 返回
    array('code'=>$code)
       - code        'OK', 'FAILED', 'DENIED'（auth插件）


#### scheduler_get - 查询进程调度配置信息

###### 定义
    array scheduler_get($jobname, $uuid, $setting)

###### 参数
    $jobname         进程名
    $uuid            进程调度配置的UUID
    $setting         程序执行设置，已知参数如下：
       - auth        auth插件参数
         - username  用户名
         - password  密码

###### 返回
    array('code'=>$code, 'data'=>$data)
       - code        'OK', 'FAILED', 'DENIED'（auth插件）
       - data        进程调度配置信息列表


#### scheduler_getall - 查询所有的进程调度配置信息

###### 定义
    array scheduler_getall($setting)

###### 参数
    $setting         程序执行设置，已知参数如下：
       - auth        auth插件参数
         - username  用户名
         - password  密码

###### 返回
    array('code'=>$code, 'data'=>$data)
       - code        'OK', 'FAILED', 'DENIED'（auth插件）
       - data        进程调度配置信息列表


#### scheduler_getlog - 查询进程调度执行历史

###### 定义
    array scheduler_getlog($jobname, $uuid, $setting)

###### 参数
    $jobname         进程名
    $uuid            进程调度配置的UUID
    $setting         程序执行设置，已知参数如下：
       - auth        auth插件参数
         - username  用户名
         - password  密码

###### 返回
    array('code'=>$code, 'data'=>$data)
       - code        'OK', 'FAILED', 'DENIED'（auth插件）
       - data        进程调度执行时间列表



## 相关资源：PHP 后台进程控制面板

这是一套针对 php-backend-server 实现的面板，通过它可以可视化地对后台进程进行管理操作。

项目地址：[php-backend-web](https://github.com/quyun/php-backend-web)