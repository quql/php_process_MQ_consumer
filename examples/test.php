<?php
use Yp\Process\Action\SendCard;
require_once __DIR__ . '/../vendor/autoload.php';

$send = new SendCard();
$data = [
    'phone'=>'17621172303'
];
$res=$send->send('3',$data,1,3,1);
var_dump($res);