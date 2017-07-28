<?php
/**
 * Created by PhpStorm.
 * User: huangyd
 * Date: 17-7-29
 * Time: 上午2:41
 */
$redis_host="127.0.0.1";
$redis_port="6379";
$RedisTasklistName="tasklist";
$info="";
for (;;){
    try {
        $redis = new \Redis();
        $connetct_redis=@$redis->connect($redis_host, $redis_port);
        if(!$connetct_redis){
            throw new \RedisException("连接redis失败[".$redis_host.":".$redis_port."]");
        }
        $list = @$redis->lPush($RedisTasklistName, “队列信息”.date("Y-m-d H:i:s" ));
        echo var_export($connetct_redis,true);
        echo var_export($list,true);
        $info="";
    } catch (\RedisException $e) {
        $info= "\e[0;31mRedisException >>>>" . $e->getMessage()."\e[0m";
    } catch (\Exception $e) {
        $info= "\e[0;31mException >>>>" . $e->getMessage() ."\e[0m";
    }
    echo $info;
    sleep(1);
}