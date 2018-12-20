<?php
	$redis = new Redis();
	//连接服务
	echo $redis->connect('192.168.168.166', 6379);

	$redis->auth('123456');
	$arr=[
		'ipid'=>5,
		'phone'=>'17621172303',
		'send_id'=>5,
		'is_dx'=>1
	];
	//提交一个用户发卷
	$redis->lpush('sendcardlist',json_encode($arr));