<?php
/**
 * Created by PhpStorm.
 * User: huangyongda
 * Date: 2017/7/28
 * Time: 16:27
 */

namespace Ydtask;

class Ydtask
{
    static $kill_sig=0;
    private $run_num;
    private $fn;
    private $restartCheckFilePath;
    private $redis_host;
    private $redis_port;
    private $redis_task_list_name;
    private $isDaemonizeModel;
    private $printInfoPath;

    private $master_process_id=0;//主进程id
    private $child_begin_time=0;//子进程开始时间
    private $cur_task_begin_time=0;//当前任务开始时间
    private $cur_run_level=0;//当前任务运行等级
    private $cur_status=0;//当前任务运行状态
    private $cur_task_run_times=0;//当前进程执行任务次数
    private $cur_task_success_run_times=0;//当前进程执行任务成功次数
    private $task_content=0;//当前任务执行内容
    private $formatStatusInfo=0;//当前任务执行内容

    public function __construct()
    {
        ini_set('date.timezone','Asia/Shanghai');
        self::$kill_sig=0;
        $this->restartCheckFilePath="";
        $this->fn=function(){};
        $this->run_num=2;
        $this->redis_host="127.0.0.1";
        $this->redis_port="6379";
        $this->redis_task_list_name="tasklist";
        $this->isDaemonizeModel=0;
        $this->printInfoPath=array();
        $this->runConfig=array();
        $this->runing=array();
        $this->pidRunLevel=array();
        $this->pidPath="";
        $this->runCommandStr="";//运行命令
        $this->redis = new \Redis();
        $this->myids=array();//子进程id列表
        $this->myids_run_time=array();//子进程id列表对应的开始运行时间
    }

    /**
     * @param int $formatStatusInfo
     */
    public function setFormatStatusInfo($formatStatusInfo)
    {
        $this->formatStatusInfo = $formatStatusInfo;
        return $this;
    }


    /**
     * 设置自动重启的检测目录
     * @param $path
     * @return $this
     */
    public function setRestartCheckFilePath($path)
    {
        $this->restartCheckFilePath=$path;
        return $this;
    }

    /**
     * redis host
     * @param $path
     * @return $this
     */
    public function setRedisHost($str)
    {
        $this->redis_host=$str;
        return $this;
    }
    /**
     * redis port
     * @param $path
     * @return $this
     */
    public function setRedisPort($str)
    {
        $this->redis_port=$str;
        return $this;
    }
    /**
     * redis 任务队列的名称
     * @param $path
     * @return $this
     */
    public function setRedisTasklistName($str)
    {
        $this->redis_task_list_name=$str;
        return $this;
    }
    /**
     * 运行命令
     * @param $path
     * @return $this
     */
    public function setCommand($str)
    {
        $this->runCommandStr=$str;
        return $this;
    }

    /**
     * redis 任务队列的名称
     * @param $path
     * @return $this
     */
    public function setRunConfig($runLevel=1,$runNum=1)
    {
        $this->runConfig[$runLevel]=$runNum;
        return $this;
    }


    public function isDaemonize($daemonize=true)
    {
        $this->isDaemonizeModel=$daemonize;
        return $this;
    }

    /**
     * 守护进程
     */
    private function daemonize()
    {

        umask(0);
        $pid = pcntl_fork();
        if (-1 === $pid) {
            throw new \Exception('fork fail');
        } elseif ($pid > 0) {
            exit(0);
        }
        if (-1 === posix_setsid()) {
            throw new \Exception("setsid fail");
        }
        // Fork again avoid SVR4 system regain the control of terminal.
        $pid = pcntl_fork();
        if (-1 === $pid) {
            throw new \Exception("fork fail");
        } elseif (0 !== $pid) {
            exit(0);
        }

    }

    /**
     * 信号处理
     * @param $signo
     */
    public static function sighandler($signo)
    {
        self::$kill_sig=1;
//        echo ( "进程:".getmypid().",收到结束信号:".$signo."\n" );
    }

    /**
     * 设置任务数量
     * @param int $num
     * @return $this
     */
    public function setTaskNum($num=1)
    {
        $this->run_num=$num;
        return $this;
    }

    /**
     * 设置屏幕输出的内容到文件
     * @param $path
     * @return $this
     */
    public function setPrintInfoPath($path)
    {
        $this->printInfoPath=$path;
        return $this;
    }

    public function printInfo($info)
    {
        if($this->printInfoPath)
        {
            if($this->printInfoPath instanceof \Closure){
                $func=$this->printInfoPath;

                $data=array(
                    "master_process_id"=>$this->master_process_id,
                    "child_begin_time"=>$this->child_begin_time,
                    "cur_task_begin_time"=>$this->cur_task_begin_time,
                    "cur_run_level"=>$this->cur_run_level,
                    "cur_status"=>$this->cur_status,
                    "cur_task_run_times"=>$this->cur_task_run_times,
                    "cur_task_success_run_times"=>$this->cur_task_success_run_times,
                    "task_content"=>$this->task_content,
                );
                $printInfoPath=$func($data);
                foreach ($printInfoPath as $Path) {
                    $fh = fopen($Path, "a");
                    fwrite($fh, $info);
                    fclose($fh);
                }
            }
            if(is_array($this->printInfoPath)){
                foreach ($this->printInfoPath as $Path) {
                    $fh = fopen($Path, "a");
                    fwrite($fh, $info);
                    fclose($fh);
                }
            }

        }
        if(!$this->isDaemonizeModel )
        {
            echo $info;
        }
    }

    /**
     * 设置屏幕输出的内容到文件
     * @param $path
     * @return $this
     */
    public function setPidPath($path)
    {
        $this->pidPath=$path;
        return $this;
    }

    private function writePid($pid)
    {
        $fh = fopen($this->pidPath, "w");
        fwrite($fh, $pid);
        fclose($fh);
    }

    /**
     * 获取主进程id
     */
    private function getMasterProcessId()
    {
        if(!file_exists($this->pidPath)){
            throw new \Exception("主进程id不存在");
        }
        $myfile = fopen($this->pidPath, "r") or die("不能读取pid文件");
        $pid= trim(fread($myfile,filesize($this->pidPath)));
        fclose($myfile);
        return $pid;
    }

    private function check()
    {
        if(!trim($this->redis_task_list_name))
        {
            $this->printInfo( "请设置进程的队列名称..\n");
            exit(0);
        }
        if(count($this->run_num)<=0)
        {
            $this->printInfo( "请设置进程的数量..\n");
            exit(0);
        }
        if(!$this->pidPath)
        {
            $this->printInfo( "请设置pid的路径..\n");
            exit(0);
        }
        if(file_exists($this->pidPath) )
        {
            $pid=$this->getMasterProcessId();
            $shell_str="ps -ef|awk '{print $2}' |"."grep ".$pid." " ;
            exec($shell_str,$out);
            if($out){
                $this->printInfo( "程序已经运行，请不要重复运行..\n");
                exit(0);
            }

        }

    }

    private function stop()
    {
        $cur_run_pid = "";
        if(file_exists($this->pidPath)){
            $cur_run_pid = file_get_contents($this->pidPath);//将整个文件内容读入到一个字符串中
        }
        if(!$cur_run_pid)
        {
            $this->printInfo( "找不到pid程序停止失败..\n");
            exit(0);
        }
        $shell_str="ps -ef|awk '{print $1\" \"$2\" \"$3\" \"$4}'|grep \"\ ".$cur_run_pid."\ \" |awk '{print $2}'";
        exec($shell_str,$out);
//                print_r($out);
        //echo var_export($out,true);
        //$out=explode("\n",$out);
        //print_r($out);
        $this->printInfo( "[".date("Y-m-d H:i:s")."]停止程序中".$cur_run_pid."...\n");
        foreach ($out as $pid) {
            $kill_info=posix_kill($pid, 2);
            $this->printInfo( "[".date("Y-m-d H:i:s")."]结束子进程 pid:【".$pid."结果". var_export($kill_info,true)."】\n");
        }
        for (;;){
            if(exec($shell_str."|wc -l") >0  ){
                sleep(1);
            }
            else{
                $this->printInfo( "[".date("Y-m-d H:i:s")."]程序".$cur_run_pid."停止成功...\n");exit(0);
            }
        }
    }

    private function time_to_date($time=0)
    {
        $str="";
        if($time>=86400){
            $str.=intval($time/86400)."天";
            $time=$time-(intval($time/86400)*86400);
        }
        if($time>=3600){
            $str.=intval($time/3600)."小时";
            $time=$time-(intval($time/3600)*3600);
        }
        if($time>=60){
            $str.=intval($time/60)."分钟";
            $time=$time-(intval($time/60)*60);
        }
        $str.=$time."秒";

        return $str;
    }

    private function show_status()
    {
        echo `clear`;
        //信号处理函数
        while(1){
            $cols=exec("tput cols");
            $lines=exec("tput lines");
            echo "\e[0;0H";//重置光标位置


            $this->status();

            for ($i=0;$i<=$lines;$i++){
                echo "\e[".$lines.";0H\033[K";
            }

            sleep(1);
        }
        exit();
    }

    /**
     * 获取中文数量
     * @param $str
     * @return int
     */
    private function getCnNum($str){
        $encode = 'UTF-8';
        $str_num = mb_strlen($str, $encode);

        $j = 0;

        for($i=0; $i < $str_num; $i++)
        {
            if(ord(mb_substr($str, $i, 1, $encode))> 0xa0)
            {
                $j++;
            }
        }
        return $j;
    }
    private function status()
    {
        $head=array(
            "主进程",
            "子进程",
            "内存",
            "子进程启动时间",
            "当前运行状态",
            "进程已运行时间",
            "当前任务等级",
            "当前任务已运行时间",
            "总执行次数",
            "执行成功次数",
            "执行失败次数",
            "正在运行",
        );

        $print_info=array();
        $print_info[]=$head;


        try {
            $ping_str=$this->redis->ping();
            if(!$ping_str){
                throw new \RedisException("ping服务器redis失败".$this->redis_host.":".$this->redis_port,231301);
            }
        } catch (\RedisException $e) {
            $this->redisConnect();
        }
        try{
            $masterId=$this->getMasterProcessId();
        } catch (\Exception $e) {
            echo $e->getMessage();
            exit();
        }



        $info=$this->redis->hGetAll($this->redis_task_list_name."_chind_info".$masterId);

        $shell_str="ps -ef|awk '{print $3 \" \" $2}'|grep \"^".$masterId."\" |awk '{print   $2}'";
        exec($shell_str,$out);
        foreach ($info as $key=> $item) {
            $process_info=unserialize($item);
            if(
                isset($process_info["master_process_id"])
                && $process_info["master_process_id"]!=$masterId
            ){
                $this->redis->hDel($this->redis_task_list_name."_chind_info",$key);
                continue;
            }
            if($process_info['pid'] && !in_array($process_info['pid'],$out)){
                $this->redis->hDel($this->redis_task_list_name."_chind_info",$key);
                continue;
            }
            $list=array(
                $process_info['master_process_id'],
                $process_info['pid'],
                str_replace(" ","", $process_info['cur_menory']),
                date("Y-m-d/H:i:s",$process_info['child_begin_time']),
                $process_info['cur_status']==1?"运行中":"空闲",
                $this->time_to_date((time()-intval($process_info['child_begin_time']) ) ),
                $process_info['cur_run_level'],
                $process_info['cur_status']?$this->time_to_date((time()-intval($process_info['cur_task_begin_time']) ) ):"无",
                $process_info['cur_task_run_times'],
                $process_info['cur_task_success_run_times'],
                $process_info['cur_task_run_times']-$process_info['cur_task_success_run_times'],
                $process_info['task_content'],
            );
            $print_info[]=$list;
        }
        if($this->formatStatusInfo instanceof \Closure){
            $formatStatusInfo=$this->formatStatusInfo;
            $print_info=$formatStatusInfo($print_info);
        }

        $maxLenghtList=array();
        foreach ($print_info as $key=>$item) {
            foreach ($item as $key2=>$item2) {
                $max_lenght=strlen($item2);
                $max_lenght-=$this->getCnNum($item2);
                if(!isset($maxLenghtList[$key2])){
                    $maxLenghtList[$key2]=0;
                }
                if($max_lenght>$maxLenghtList[$key2]){
                    $maxLenghtList[$key2]=$max_lenght;
                }
            }

        }
        foreach ($print_info as $item) {
            foreach ($item as $key2=>$val) {
                echo str_pad($val, $maxLenghtList[$key2]+$this->getCnNum($val)," ");
//                $mask = "%".$maxLenghtList[$key2]."s ";
//                printf("%-20.20s ", $val);
//                printf("%-20".$maxLenghtList[$key2]."s ", $val);
            }
            echo "\n";
        }
    }

    private function runCommand()
    {
        switch ($this->runCommandStr) {
            case "stop":
                $this->stop();
                exit(0);
                break;
            case "status":
                $this->show_status();
                exit(0);
                break;
            case "":
                break;
            case "":
                break;
            default :
                break;
        }
    }


    public function run()
    {
        if(count($this->runConfig)>0){
            $this->run_num=array_sum($this->runConfig);
        }
        $this->runCommand();
        $this->check();

        $this->myids=array();


        declare(ticks=1);
        if($this->isDaemonizeModel)
        {
            $this->daemonize();
        }

        $this->writePid(getmypid());

        pcntl_signal(SIGINT, [__CLASS__, 'sighandler']);

        pcntl_signal(SIGCHLD, SIG_IGN); //如果父进程不关心子进程什么时候结束,子进程结束后，内核会回收。

        clearstatcache();//清除文件状态缓存。
        if($this->restartCheckFilePath){
            $last_update_time=$this->getFileNewTime($this->restartCheckFilePath);
        }
        $restart=false;
        $this->run_task($this->run_num);
        for (;;){
            $cur_fiele_time=0;
            if($this->restartCheckFilePath){
                $cur_fiele_time=$this->getFileNewTime($this->restartCheckFilePath);
            }
            foreach ($this->myids as $key=>$pid) {
                $res = pcntl_waitpid($pid, $status, WNOHANG);
                if($res == -1 || $res > 0){
                    unset($this->myids[$key]);
                    $runLevel=$this->pidRunLevel[$pid];
                    unset($this->pidRunLevel[$pid]);
                    if(isset($this->myids_run_time[$pid]) ){
                        unset($this->myids_run_time[$pid]);
                    }
                    if(isset($this->runing[$runLevel]) ){
                        $this->runing[$runLevel]--;
                    }
                }
            }
            //echo "self::kill_sig".self::$kill_sig.">>".count($this->myids)."\n";
            if(self::$kill_sig==1 && count($this->myids)==0){
                $this->printInfo(  "[".date("Y-m-d H:i:s")."]主进程结束..\n");
                $this->redisConnect();
                $this->redis->hDel($this->redis_task_list_name."_chind_info".$this->getMasterProcessId());
                unlink ($this->pidPath);//删除pid的文件
                exit(0);

            }
            if(self::$kill_sig==0 && count($this->myids)==0){
                $this->printInfo(  "[".date("Y-m-d H:i:s")."]主进程重启..\n");
                $restart=false;
                $this->run_task($this->run_num);
            }
            clearstatcache();//清除文件状态缓存。

            foreach ($this->myids_run_time as $start_rumtime) {
                if($start_rumtime<$cur_fiele_time){
                    $restart=true;
                }
            }
            if($restart || self::$kill_sig==1
            ){
                foreach ($this->myids as $key=>$pid) {
                    if($this->myids_run_time[$pid] > $cur_fiele_time){
                        continue;
                    }
                    $res = pcntl_waitpid($pid, $status, WNOHANG);
                    if($res==0){
                        $kill_info=posix_kill($pid, 2);
                        $this->printInfo(  "[".date("Y-m-d H:i:s")."]主进程结束子进程 pid:【".$pid."结果". var_export($kill_info,true)."】\n");
                    }
                }
            }
            if(count($this->myids) <$this->run_num && self::$kill_sig==0 ){
                $this->run_task($this->run_num-count($this->myids) );
            }
//            $this->printInfo(  "[".date("Y-m-d H:i:s")."]主进程结束信号为".intval(self::$kill_sig)."..\n");
            sleep(2);
        }



    }

    /**
     * 获取目录最新文件的更新时间
     * @param $dir
     * @return bool|int
     */
    private function getFileNewTime($dir)
    {
        $last_update_time=0;
        if(is_dir($dir))
        {
            if ($dh = opendir($dir))
            {
                while (($file = readdir($dh)) !== false)
                {
                    if($file=="." || $file==".."){
                        continue;
                    }
                    if(is_dir($dir."/".$file))
                    {
                        $fmtime=$this->getFileNewTime($dir."/".$file);
                        $last_update_time=$fmtime>$last_update_time?$fmtime:$last_update_time;
                    }
                    else
                    {
                        if(substr(strrchr($file, '.'), 1) == "php"){
                            $fmtime=filemtime($dir."/".$file);
                            $last_update_time=$fmtime>$last_update_time?$fmtime:$last_update_time;
                        }
                    }
                }
                closedir($dh);
            }
        }
        return $last_update_time;
    }


    private function convert($size){
        $unit=array('b','kb','mb','gb','tb','pb');
        return @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
    }
    private function run_task($num=2)
    {
        for ($i=1;$i<=$num;$i++){
            $run_level="";
            foreach ($this->runConfig as $level=>$run_num) {
                if(!isset($this->runing[$level])){
                    $this->runing[$level]=0;
                }
                if($this->runing[$level]<$run_num){
                    $run_level=$level;//
                    $this->runing[$level]++;
                    break;//跳出
                }
            }
            $pid = pcntl_fork();    //创建子进程
            //父进程和子进程都会执行下面代码
            if ($pid == -1) {
                //错误处理：创建子进程失败时返回-1.
                die('错误处理：创建子进程失败时返回-1.');
            } else if ($pid) {
                $this->myids[] = $pid;
                $this->pidRunLevel[$pid] = $run_level;//
                $this->myids_run_time[$pid] = time();//
                //父进程会得到子进程号，所以这里是父进程执行的逻辑
                //如果不需要阻塞进程，而又想得到子进程的退出状态，则可以注释掉pcntl_wait($status)语句，或写成：
//                pcntl_wait($status,WNOHANG); //等待子进程中断，防止子进程成为僵尸进程。
                //pcntl_wait($status,WNOHANG); //等待子进程中断，防止子进程成为僵尸进程。
                //echo "父进程结束".$status;
            } else {
                //子进程得到的$pid为0, 所以这里是子进程执行的逻辑。
                //sleep(1);
                $this->child($run_level);
                exit(0) ;
            }
        }
    }

    public function callFunction($fn)
    {
        $this->fn=$fn;
        return $this;
    }

    private function saveChindInfo()
    {
        try {
            $ping_str=$this->redis->ping();
            if(!$ping_str){
                throw new \RedisException("ping服务器redis失败".$this->redis_host.":".$this->redis_port,231301);
            }
        } catch (\RedisException $e) {
            $this->redisConnect();
        }
        $pid=getmypid();

        $data=array(
            "master_process_id"=>$this->master_process_id,
            "pid"=>$pid,
            "cur_menory"=>$this->convert(memory_get_usage(true)),
            "cur_status"=>$this->cur_status,
            "child_begin_time"=>$this->child_begin_time,
            "cur_task_begin_time"=>$this->cur_task_begin_time,
            "cur_run_level"=>$this->cur_run_level,
            "cur_task_run_times"=>$this->cur_task_run_times,
            "cur_task_success_run_times"=>$this->cur_task_success_run_times,
            "task_content"=>$this->task_content,
        );
        $this->redis->hSet($this->redis_task_list_name."_chind_info".$this->master_process_id,"process_".$pid,serialize($data) );
    }


    /**
     * 子进程
     */
    private function child($run_level="")
    {
        $id = getmypid();
        $this->master_process_id=$this->getMasterProcessId();
        $this->child_begin_time=time();//子进程开始时间
        $this->cur_run_level=$run_level;//当前任务运行等级
        $this->printInfo( "[".date("Y-m-d H:i:s")."]创建子进程：".($run_level?"level[".$run_level."]":"")."[" . $id . "]>>>\n");
        for (;;) {
            $this->cur_task_begin_time=time();//当前任务开始时间
            $info="";
            $list=array();
            $task_doing_key="";
            $redis_task_name_doing_key="";
            list($msec, $sec) = explode(' ', microtime());
            $begin_time=round($msec,3) + $sec;
            try {
                $this->cur_status=0;
                $this->task_content=null;
                $this->saveChindInfo();
                if(self::$kill_sig==1){
                    $this->printInfo( "[".date("Y-m-d H:i:s")."]结束子进程".($run_level?"level[".$run_level."]":"")."[".$id."]\n");exit(0);
                    $this->redisConnect();
                    $this->redis->hDel($this->redis_task_list_name."_chind_info".$this->master_process_id,"process_".$id);
                }
                $list = $this->TaskPop($run_level);
                if (count($list) <= 0) {
                    continue;
                }
                $this->cur_status=1;
                $this->task_content=$list[1];
                $this->saveChindInfo();
                $this->cur_task_run_times++;

                //进行中的任务
                $task_doing_key=md5(rand(1000,9999).$list[1]);
                $redis_task_name_doing_key=$this->redis_task_list_name."_doing";
                $this->redis->hSetNx($redis_task_name_doing_key,$task_doing_key,$list[1]);

                $fn=$this->fn;
                $info = $fn($list);
                if(!$info){
                    throw new \Exception("任务调用失败返回值[".var_export($info,true)."]");
                }
                $this->cur_task_success_run_times++;
                $this->redis->hDel($redis_task_name_doing_key,$task_doing_key);

            } catch (\PDOException $e) {
                $info= "\e[0;31mPDOException >>>>" . $e->getMessage()."\e[0m";
            } catch (\ErrorException $e) {
                $this->exec_error($list,$redis_task_name_doing_key,$task_doing_key);
                $info= "\e[0;31mErrorException >>>>" . $e->getMessage()."\e[0m";
            } catch (\RedisException $e) {
                $info= "\e[0;31mRedisException >>>>" . $e->getMessage().",line:".$e->getLine()."\e[0m";
            } catch (\Exception $e) {
                $this->exec_error($list,$redis_task_name_doing_key,$task_doing_key);
                $info= "\e[0;31mException >>>>" . $e->getMessage() ."\e[0m";
            }

            list($msec, $sec) = explode(' ', microtime());
            $end_time=round($msec,3) + $sec;


            $runTime=round($end_time-$begin_time,3);

            $color="\e[1;32m";//绿色
            if($runTime>5){
                $color="\e[1;33m";//黄色
            }
            if($runTime>10){
                $color="\e[1;31m";//红色
            }

            $this->printInfo( $color."[子进程:" . $id . ",".($run_level?"level:".$run_level.",":"")."".
            $this->convert(memory_get_usage(true)).
            ", ".$runTime." 秒," . date("Y-m-d H:i:s") . "]\e[0m ".
                var_export($info,true) . "\n");
            if(self::$kill_sig==1){
                $this->printInfo( "[".date("Y-m-d H:i:s")."]结束子进程".$id."\n");exit(0);
            }

        }
    }

    private function exec_error($list,$redis_task_name_doing_key,$task_doing_key)
    {
        if(count($list)>0 ){
            $this->redis->lPush($list[0]."_error", $list[1]);
        }
        if($redis_task_name_doing_key && $task_doing_key){
            $this->redis->hDel($redis_task_name_doing_key,$task_doing_key);
        }
    }

    /**
     * 任务出队
     */
    public function TaskPop($run_level="")
    {
        try {
            $ping_str=$this->redis->ping();
            if(!$ping_str){
                throw new \RedisException("ping服务器redis失败".$this->redis_host.":".$this->redis_port,231301);
            }
        } catch (\RedisException $e) {
            $this->redis->close();
            $this->redisConnect();
        }
        $redis_task_list_name=$this->redis_task_list_name;
        if(is_string($redis_task_list_name))
        {
            $redis_task_list_name.=$run_level;
        }
        if(is_array($redis_task_list_name)){
            foreach ($redis_task_list_name as $key=>$item) {
                $redis_task_list_name[$key].=$run_level;
            }
        }
        $list = $this->redis->brPop($redis_task_list_name, 5);//这个时间必须小于connect配置的时间

        return $list;
    }

    private function redisConnect()
    {
        $connetct_redis=@$this->redis->connect($this->redis_host, $this->redis_port,10);//10秒超时
        if(!$connetct_redis){
            $this->redis->close();
            throw new \RedisException("连接redis失败[".$this->redis_host.":".$this->redis_port."]",231302);
        }

    }

    public function __destruct()
    {
        if($this->cur_run_level>0 && $this->redis){
            @$this->redis->close();
        }
    }
}