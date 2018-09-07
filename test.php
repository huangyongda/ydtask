<?php
/**
 * Created by PhpStorm.
 * User: huangyongda
 * Date: 2017/7/28
 * Time: 17:02
 */
namespace Ydtask;
//
class test{
    public function test1($date)
    {
        $info=var_export($date,true);
//        sleep(1);

        sleep(rand(1,11));
        $info="测试";
        return $info;
    }
}
