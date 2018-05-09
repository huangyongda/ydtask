<?php
/**
 * Created by PhpStorm.
 * User: huangyongda
 * Date: 2017/7/28
 * Time: 16:42
 */
namespace Ydtask;
include "src/Ydtask/Ydtask.php";


include_once "test.php";
$command=isset($argv[1])?$argv[1]:"";
switch ($command) {
    case "stop":
        break;
    default :
        break;
}
$obj=new Ydtask();

    $obj->callFunction(function ($data) {
        $newobj = new test();
        return $newobj->test1($data);
    })
    ->isDaemonize(false)//是否守护进程模式
    ->setRedisHost("192.168.10.250")//redis主机
//    ->setRedisHost("127.0.0.1")//redis主机
    ->setRedisPort("6379")//redis主机端口
    ->setCommand($command)//输出
    ->setPrintInfoPath("out.info")//输出
    ->setRedisTasklistName(array("tasklist"))//出队的list队列名称
    ->setRedisTasklistName("tasklist")//出队的list队列名称
    ->setPidPath("ydtask.pid")//出队的list队列名称
    ->setRestartCheckFilePath(dirname(__FILE__) )//服务自动重启 检测路径（自动检测最新修改时间 最新的php文件）
    ->setRunConfig(1,2) //设置运行配置 表示等级1的配置运行进程数量为2 优先级大于setTaskNum方法
    ->setRunConfig(2,2) //设置运行配置 表示等级1的配置运行进程数量为2 优先级大于setTaskNum方法
    ->setRunConfig(3,2) //设置运行配置 表示等级1的配置运行进程数量为2 优先级大于setTaskNum方法
//    ->setTaskNum(2) //任务子进程的数量
    ->run();