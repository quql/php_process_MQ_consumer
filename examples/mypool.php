<?php
require_once __DIR__ . '/../vendor/autoload.php';
set_time_limit(0); // 取消脚本运行时间的超时上限

//发卷消费
$action = new \Yp\Process\Action\Action(function ($control, $context) {
    $redis = new Redis();
    $redis->connect('192.168.168.166', 6379);
    $redis->auth('123456');
    $send = new \Yp\Process\Action\SendCard();
    while(True){
        $data=$redis->rpop('sendcardlist');
        if($data){
            $data = json_decode($data,true);
            if($data['is_dx']==1){
                $send_type=3;
            }else{
                $send_type=1;
            }
            //业务逻辑
            $res=$send->send($data['ipid'],['phone'=>$data['phone']],1,$send_type,$data['send_id']);
            if($res['send_status']){
                $status=1;
                $detail='';
            }else{
                $status=-1;
                $detail=$res['msg'];
            }
            \Yp\Process\Action\GaDb::table('ga_integral_sublog')
                ->where(['mobile'=>$res['phone']])
                ->update(['status'=>$status,'detail'=>$detail]);

        }else{
            exit();
        }

    }
});


//人群消费
$action1 = new \Yp\Process\Action\Action(function ($control, $context) {
    $redis = new Redis();
    $redis->connect('192.168.168.166', 6379);
    $redis->auth('123456');
    $sendphone = new \Yp\Process\Action\SendPhone();
    while(True){
        $data=$redis->rpop('sendcrowdlist');
        if($data){
            $data = json_decode($data,true);
            //业务逻辑
            $sendphone->send($data['crowid'],$redis,$data['ipid'],$data['logid'],$data['is_dx']);
        }else{
            exit();
        }

    }
});

$child = new \Yp\Process\Child($action);
$child1 = new \Yp\Process\Child($action1);

$pool = new \Yp\Process\Pool();

$pool->add($child, 5);
$pool->add($child1, 1);
$pool->start();

$pool->running();