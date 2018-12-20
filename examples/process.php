<?php
use Yp\Process\Control\Common;
/**
 * Created by PhpStorm.
 */
require_once __DIR__ . '/../vendor/autoload.php';
//ignore_user_abort(); // 后台运行
set_time_limit(0); // 取消脚本运行时间的超时上限
$action = new \Yp\Process\Action\Action(function ($control, $context) {
    //rabbit服务配置
    $conn_args = [
        'host' => 'rd5-hq',  //rabbitmq 服务器host
        'port' => 5672,         //rabbitmq 服务器端口
        'login' => 'qql',     //登录用户
        'password' => '123456',   //登录密码
        'vhost' => 'qql'         //虚拟主机
    ];

//队列配置
    $config = [
        'exchangeName' => 'sendmsg',//交换机名称
        'eqeueName' => 'msg',//队列名称
        'routeName' => 'qql',//队列和消息key
    ];
    $http=new Common();
    $q_name = $config['eqeueName'];
    $conn = new AMQPConnection($conn_args);
    if (!$conn->connect()) {
        die('Cannot connect to the broker');
    }
    $channel = new AMQPChannel($conn);
    $q = new AMQPQueue($channel);
    $q->setName($q_name);
    while ($control()) {
        $arr = $q->get();
        if($arr){
            $ack = $arr->getDeliveryTag();
            $res = $q->ack($ack);
            $msg = $arr->getBody();
            if(!empty($msg)){
                echo $msg;
//                $data = json_decode($msg,true);
//                $data['time']=date('Y-m-d H:i:s',time());
//                $data['process']= posix_getpid();
//                $url = 'https://www.qqlong.top/sendceshi';
//                $res=$http->https_request($url,$data);
//                echo $res;
            }
        }else{
            sleep(5);
        }
    }
});
$child = new \Yp\Process\Child($action);

$process = new \Yp\Process\Process();

$process->add($child, 4);

$process->start();

$process->running();