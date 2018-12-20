<?php
namespace Yp\Process\Action;
use Yp\Process\Action\Db;
/**
 * Created by PhpStorm.
 * User: qianglong
 * Date: 2017/12/29
 * Time: 9:12
 */
/**
* 发卷类
 * param $data 用户手机号,二维数组(必传)--
 * action_type   1 系统发卷  $data参数
 *                                  array(
     *                                      'phone'=>1452685522,手机号
     *
 *                                  )
 *
 * action_type    2交易领取  $data参数
 *                                  array(

 *                                      'phone'=>1452685522,手机号
*                                          'serial'=>'dad444',兑换码（交易单号）
*                                          'addtime'=>'161156156',建立时间（时间戳）
     *
 *                                  )
 *
 * action_type  3 自主领取  $data参数
 *                                  array(

*                                          'phone'=>1452685522,手机号
 *
 *                                  )
 *
 *
 * param $card_id 发卷卡号(必传)
 * param $card_table 卡卷表名(完整表名)(必传)
 * param $exchange_table 兑换表名(完整表名)(必传)
 * param $tel_table 用户手机号表(完整表名)(必传)
 * param $error
 * param $card 卡卷信息
 * param $vali_begin 卷有效期开始时间
 * param $vali_end 卷有效期结束时间
 * param $result 发卷完成反馈信息
 * param $length 发卷完成反馈信息数组长度
 * param $type_id 卡卷类型（1自主核销，2扫码核销,3备注核销）
 * param $action_type 操作类型
 * param $serial 兑换码
 * param $send_id 操作ID
 * param $send_type消息通知参数，（0微信，短信都不通知，1通知微信不通知短信，2通知短信不通知微信，3两个都通知）
 * 返回数据
 *
    array (size=5)
    'phone' => string '17621172303' (length=11)
    'ieid' => 1
    'msg' => string '此卷种不能前台领取' (length=27)
    'addtime' => string '2018-05-22 15:16:38' (length=19)
    'send_status' => boolean false //此号码发卷状态

 *
 *
*/
class SendCard
{
    private $card_id;
    private $data;
    private $card_table='bn_integral_product';
    private $exchange_table='bn_integral_exchange';
    private $tel_table='bn_user_tel';
    private $error;
    private $card;
    private $vali_begin;
    private $vali_end;
    private $result;
    private $length;
    private $type_id;
    private $action_type;
    private $serial;
    private $send_type;
    private $send_id;

    //数据初始化
    public function __construct()
    {
        $this->card_id='';
        $this->data='';
        $this->error='';
        $this->card='';
        $this->vali_begin='';
        $this->vali_end='';
        $this->result='';
        $this->length='';
        $this->type_id='';
        $this->action_type='';
        $this->serial='';
        $this->send_type='';
    }

    //发卷
    public function send($card_id,$data,$action_type,$send_type,$send_id)
    {
        $this->card_id = $card_id;
        $this->send_id = $send_id;
        $this->action_type = $action_type;
        $this->data = $data;
        $this->send_type = $send_type;
        $this->card = Db::table($this->card_table)->where(['ipid'=>$this->card_id])->find();
        $this->type_id = $this->card['type_id'];


        if($this->checkstatus() && $this->Run()){
            return $this->result;

        }else{
            return $this->error;
        }
	}



	//检查卡卷状态
    protected function checkstatus()
    {
        $card = $this->card;
        if($card['status']==0){
            $this->error = '此卡卷已经关闭';
            return false;
        }else{
            return true;
        }
    }

	//检查卡卷是否上架
    protected function checkUptime()
    {
        $data = $this->data;
        $now = time();
        $card = $this->card;
        if($card['up_begin']<$now && $card['up_end']>$now){
            return true;
        }else{
            $this->error = '卷种已经过期';
            return false;
        }

    }

    //检查卡卷的库存
    protected function ckeckTotal()
    {
        $card = $this->card;
        $store = $card['total']-$card['remain'];
        $sendcount = count($this->data);
        if(($store-$sendcount)>=0){
            return true;
        }else{
            $this->error = '卡卷库存不足本次发送';
            return false;
        }
    }

    /*
     * 检查手机号是否在本系统中
     * $phone 手机号
     * */

    protected function ckeckPhone($phone)
    {
        $is_phone = Db::table($this->tel_table)->where(['mobile'=>$phone])->select();
        if($is_phone){
            return true;
        }else{
            $this->error = '不是该系统会员';
            return false;
        }
    }

    /*
     * 判断该用户是否已经达到该卡卷的领取上线
     * $phone 手机号
     * */
    protected function checkNumber($phone)
    {
        $card = $this->card;
        $is_draw = Db::table($this->exchange_table)
                   ->where(['ipid'=>$this->card_id,'mobile'=>$phone,'statu'=>['gt',-1]])
                   ->count();
        $max = $card['limited'];
        if($is_draw<$max){
            return true;
        }else{
            $this->error = '该用户已达领取上线';
            return false;
        }
    }

    /*
    * 判断该卷是否前台显示
    * $phone 手机号
    * */
    protected function isShow()
    {
        $card = $this->card;
        if($card['show_bit']==1){
            return true;
        }else{
            $this->error = '此卷种不能前台领取';
            return false;
        }
    }

    //有效期处理
    protected function validityType()
    {
        $card = $this->card;
        if($card['validity_type']==1){
            $this->vali_begin = $card['validity_begin'];
            $this->vali_end = $card['validity_end'];

        }else{
            $validity_day = $card['validity_day'];
            $this->vali_begin = time();
            $time = date('Y-m-d',time());
            $time = strtotime($time.'23:59:59');
            $this->vali_end = strtotime("+$validity_day day",$time);
        }
    }

    /*
     * 得到扫码核销方式的未领取的第一个卷的ieid
     *
     * */
    protected function getCardOne()
    {
        $card = $this->card;
        $ieid = Db::table($this->exchange_table)
                    ->where(['ipid'=>$card['ipid'],'statu'=>-1])
                    ->find('ieid');
        if($ieid){
            return $ieid['ieid'];
        }else{
            $this->error = '此卷种无库存';
            return false;
        }
    }

    //预警监测
    protected function storeCheck()
    {
        $card = Db::table($this->card_table)->where(['ipid'=>$this->card_id])->find();
        $store = $card['total']-$card['remain'];
        if(empty($card['alertMobile'])){
            return false;
        }
        if($store==0){
            $point=0;
        }else{
            $point = round(($store/$card['total'])*100,2);
        }
        if($point>10 && $point<=20){
            if($this->isNotice(20)){
                return false;
            }else{
                $this->sendWxNotice($card,$store,20);
            }
        }elseif ($point>0 && $point<=10){
            if($this->isNotice(10)){
                return false;
            }else{
                $this->sendWxNotice($card,$store,10);
            }
        }elseif ($point==0){
            if($this->isNotice(0)){
                return false;
            }else{
                $this->sendWxNotice($card,$store,0);
            }
        }

    }


    //预警号码是否通知
    protected function isNotice($num)
    {
        $is = Db::table('bn_integral_alert')
            ->where(['statusBit'=>1,'keyBit'=>$num,'ipid'=>$this->card['ipid']])
            ->find();
        if($is){
            return true;
        }else{
            return false;
        }
    }

    //发送预警通知
    protected function sendWxNotice($card,$store,$num)
    {
        $arr_phone = explode(',',$card['alertMobile']);
        $data = [$card['ipid'],$card['name'],$store];
        foreach ($arr_phone as $phone){
            //卷预警通知,正式线6,测试线4
            Sendmsg::weixinmsg($phone,6,$data);
        }
        //插入记录
        $in_data=[
            'ipid'=>$card['ipid'],
            'keyBit'=>$num,
            'statusBit'=>1,
            'addtime'=>time(),
            'uptime'=>time(),
            'remark'=>"剩余卷{$store}张"
        ];
        Db::table('bn_integral_alert')->insert($in_data);
    }



    //生成6位随机数
    protected function randStr()
    {
        $length = 6;
        $str='abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $len=strlen($str)-1;
        $randstr='';
        for($i=0;$i<$length;$i++){
            $num=mt_rand(0,$len);
            $randstr.= $str[$num];
        }
        return $randstr;
    }


    //检测扫码核销卷的卷ID，手机号码，订单号是否唯一
    protected function checkOnly($arr)
    {
        $is = Db::table($this->exchange_table)
                    ->where([
                        'mobile'=>$arr['phone'],
                        'ipid'=>$this->card_id,
                        'saleid'=>$arr['serial']
                    ])
                    ->select();
        if($is){
            $this->error='此卡卷已经发送';
            return true;
        }else{
            return false;
        }
    }

    //循环发卷
    protected function Run()
    {
        $data = $this->data;
        $res_data=array();
        if($this->type_id==1 || $this->type_id==3){
            //库存检测
            $c = $this->ckeckTotal();
        }else{
            $c=true;
        }
        if($c){
            if($this->checkUptime()){
                //自主核销
                if($this->type_id==1 || $this->type_id==3){
                    switch ($this->action_type){
                        //系统领卷
                        case 1:$this->SendactionOne($data,1);break;
                        //交易发卷
                        case 2:$this->SendactionOne($data,2);break;
                        //自主领卷
                        case 3:$this->SendactionOne($data,3);break;
                    }
                //扫码核销
                }else{
                    switch ($this->action_type){
                        //系统领卷
                        case 1:$this->SendactionTwo($data,1);break;
                        //交易发卷
                        case 2:$this->SendactionTwo($data,2);break;
                        //自主领卷
                        case 3:$this->SendactionTwo($data,3);break;
                    }
                }

            }else{
                return false;
            }

        }else{
            return false;
        }
        return true;
    }

    //卡卷类型为自主核销的系统发卷1和交易发卷2，自主领卷3
    protected function SendactionOne($v,$get_type)
    {
        if($get_type==3){
            //是否前台显示
            $three = $this->isShow();
        }else{
            $three = true;
        }

        if(!$this->ckeckPhone($v['phone']) || !$this->checkNumber($v['phone']) || !$three){
            $this->result[]=[
                'phone'=>$v['phone'],
                'serial'=>'',
                'msg'=>$this->error,
                'addtime'=>date('Y-m-d H:i:s',time()),
                'send_status'=>False
            ];
        }else {
            $this->validityType();
            $tid = Db::table($this->tel_table)->where(['mobile'=>$v['phone']])->find('tid');
            if (empty($tid)) {
                $wid['wxid'] = 0;
            } else {
                $wid = Db::table('bn_user_relation')->where(['tid'=>$tid['tid']])->find('wxid');
                if (empty($wid)) {
                    $wid['wxid'] = 0;
                }
            }
            //传递兑换码和时间
            if ($get_type == 2) {
                $exchange_data = [
                    'ipid' => $this->card_id,
                    'wxid' => $wid['wxid'],
                    'mobile' => $v['phone'],
                    'serial' => $v['serial'],
                    'vali_begin' => $this->vali_begin,
                    'vali_end' => $this->vali_end,
                    'statu' => 0,
                    'addtime' => $v['addtime'],
                    'saleid'=>$this->send_id
                ];
                //不传递兑换码和时间
            } else {
                $this->serial = $this->randStr();
                $exchange_data = [
                    'ipid' => $this->card_id,
                    'wxid' => $wid['wxid'],
                    'mobile' => $v['phone'],
                    'serial' => $this->serial,
                    'vali_begin' => $this->vali_begin,
                    'vali_end' => $this->vali_end,
                    'statu' => 0,
                    'addtime' => time(),
                    'saleid'=>$this->send_id
                ];
            }


            // 启动事务
            Db::table()->Transaction();
            try {
                $ieid = Db::table($this->exchange_table)->insert($exchange_data);
                $num = Db::table($this->card_table)->where(['ipid'=>$this->card_id])->find('remain');
                $num = $num['remain'] + 1;
                Db::table($this->card_table)->where(['ipid'=>$this->card_id])->update(['remain' => $num]);
                // 提交事务
                Db::table()->commit();
            } catch (\Exception $e) {
                // 回滚事务
                Db::table()->rollback();
            }

            if (isset($e)) {
                $this->result= [
                    'phone' => $v['phone'],
                    'msg' => '该卡卷已领取',
                    'addtime' => date('Y-m-d H:i:s', time()),
                    'send_status' => False
                ];
            } else {
                $this->result= [
                    'phone' => $v['phone'],
                    'msg' => '发卷成功',
                    'addtime' => date('Y-m-d H:i:s', time()),
                    'send_status' => True,
                    'ieid'=>$ieid
                ];
                $this->sendCardmsg($v['phone']);
            }
        }
    }

    //卡卷类型为扫码核销的系统发卷1和交易发卷2，自主领卷3
    protected function SendactionTwo($v,$get_type)
    {
        if($get_type==3){
            //是否前台显示
            $three = $this->isShow();
        }else{
            $three = true;
        }
        $ieid = $this->getCardOne();

        if ($get_type == 2) {
            //唯一性检测
            $is_only = $this->checkOnly($v);
        }else{
            $is_only = False;
        }

        if(!$this->checkNumber($v['phone']) || !$ieid || !$three || $is_only){
            $this->result=[
                'phone'=>$v['phone'],
                'serial'=>'',
                'msg'=>$this->error,
                'addtime'=>date('Y-m-d H:i:s',time()),
                'send_status'=>False
            ];
        }else {
            $this->validityType();
            $tid = Db::table($this->tel_table)->where('mobile', $v['phone'])->find('tid');
            if (empty($tid)) {
                $wid['wxid'] = 0;
            } else {
                $wid = Db::table('bn_user_relation')->where(['tid'=>$tid['tid']])->find('wxid');
                if (empty($wid)) {
                    $wid['wxid'] = 0;
                }
            }
            $exchange_data = [];
            //交易单号和时间
            if ($get_type == 2) {
                $remark_arr = explode('_',$v['serial']);
                $remark=$remark_arr[0];
                $exchange_data = [
                    'wxid' => $wid['wxid'],
                    'mobile' => $v['phone'],
                    'vali_begin' => $this->vali_begin,
                    'vali_end' => $this->vali_end,
                    'statu' => 0,
                    'addtime' => $v['addtime'],
                    'saleid' => $v['serial'],
                    'remark' => $remark,
                ];
                //传递兑换码和时间
            } else {
                $exchange_data = [
                    'wxid' => $wid['wxid'],
                    'mobile' => $v['phone'],
                    'vali_begin' => $this->vali_begin,
                    'vali_end' => $this->vali_end,
                    'statu' => 0,
                    'addtime' => time(),
                    'saleid' =>$this->send_id
                ];
            }
            // 启动事务
            Db::table()->Transaction();
            try {
                Db::table($this->exchange_table)->where(['ieid'=>$ieid])->update($exchange_data);
                $num = Db::table($this->card_table)->where(['ipid'=>$this->card_id])->find('remain');
                $num = $num['remain'] + 1;
                Db::table($this->card_table)->where(['ipid'=>$this->card_id])->update(['remain' => $num]);
                // 提交事务
                Db::table()->commit();
            } catch (\Exception $e) {
                // 回滚事务
                Db::table()->rollback();
            }
            if (isset($e)) {
                $this->result= [
                    'phone' => $v['phone'],
                    'msg' => '该卡卷已领取',
                    'addtime' => date('Y-m-d H:i:s', time()),
                    'send_status' => false
                ];
            } else {
                $this->result= [
                    'phone' => $v['phone'],
                    'msg' => '发卷成功',
                    'addtime' => date('Y-m-d H:i:s', time()),
                    'send_status' => True,
                    'ieid'=>$ieid,
                ];
                $this->sendCardmsg($v['phone']);
                //预警监测
                $this->storeCheck();
            }
        }
    }


    //触发消息通知
    //发卷后的信息包含发送状态，手机号码，兑换码
	protected function sendCardmsg($phone)
    {
        $card = $this->card;
        $card_name = $card['name'];
        if($this->send_type!=0){
            switch ($this->send_type)
            {
                case 1:
                    Sendmsg::weixinmsg($phone,1,$card_name);
                break;
                case 2:
                    Sendmsg::message($phone,1,$card_name);
                break;
                case 3:
                    Sendmsg::message($phone,1,$card_name);
                    Sendmsg::weixinmsg($phone,1,$card_name);
            }
        }
    }


}

