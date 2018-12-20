<?php
	$redis = new Redis();
	//连接服务
	echo $redis->connect('192.168.168.166', 6379);

	$redis->auth('123456');
	$arr=[
		'ipid'=>3,
		'crowid'=>84,
		'logid'=>1
	];
	//提交一个人群发卷
	$redis->lpush('sendcrowdlist',json_encode($arr));