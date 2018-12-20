<?php
require_once __DIR__ . '/../vendor/autoload.php';
//ignore_user_abort(); // 后台运行
set_time_limit(0); // 取消脚本运行时间的超时上限
$redis = new Redis();
$redis->connect('192.168.168.166', 6379);

$action = new \Yp\Process\Action\Action(function ($control, $context) {
    global $redis;
    $send = new \Yp\Process\Action\SendCard();
    while(True){
        $data=$redis->rpop('sendcardlist');
        if($data){
            $data = json_decode($data,true);
            //业务逻辑
            $send->send($data['ipid'],['phone'=>$data['phone']],1,3,$data['send_id']);
        }else{
            exit();
        }

    }

});
$child = new \Yp\Process\Child($action);

$process = new \Yp\Process\Process();

$process->add($child, 5);

$process->start();

$process->running();




