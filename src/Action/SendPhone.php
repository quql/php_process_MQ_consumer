<?php
namespace Yp\Process\Action;

/**
 * Created by PhpStorm.
 * User: qianglong
 * Date: 2017/12/29
 * Time: 9:12
 */

class SendPhone
{
    public function send($crowid,$redis,$ipid,$logid,$is_dx)
    {
        $arr_phone=GaDb::table('ga_crowd_relation')->where(['cid'=>$crowid])->select('mobile');
        if(!$arr_phone){
            return false;
        }
        $sql="INSERT INTO `ga_integral_sublog`(log_id,mobile) SELECT {$logid} AS log_id,mobile FROM `ga_crowd_relation` WHERE `cid`={$crowid}";
        try{
            GaDb::table()->query($sql);
        }catch (\Exception $e){
            return false;
        }
        if(!isset($e)){
            foreach ($arr_phone as $v){
                $data=[
                    'ipid'=>$ipid,
                    'phone'=>$v['mobile'],
                    'send_id'=>$logid,
                    'is_dx'=>$is_dx
                ];
                $redis->lpush('sendcardlist',json_encode($data));
            }
        }

    }
}
