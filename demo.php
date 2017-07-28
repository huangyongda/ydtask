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
$obj=new Ydtask();

    $obj->callFunction(function ($data) {
        $newobj = new test();
        return $newobj->test1($data);
    })
    ->isDaemonize(false)//是否守护进程模式
    ->setRedisHost("127.0.0.1")//redis主机
    ->setRedisPort("6379")//redis主机端口
    ->setPrintInfoPath("out.info")//输出
    ->setRedisTasklistName(array("tasklist"))//出队的list队列名称
    ->setRestartCheckFilePath(dirname(__FILE__) )//服务自动重启 检测路径（自动检测最新修改时间 最新的php文件）
    ->setTaskNum(2) //任务子进程的数量
    ->run();