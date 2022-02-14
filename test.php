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
        static $num=0;
        static $time=0;
        if($time<=0){
            $time=time();
        }
        $num++;
        $info=json_encode($date,true);
        $runtime=(time()-$time);
        $avg=$runtime>0?intval($num/$runtime ):0;
        return $info."次：".$avg."/每秒";
    }
}
