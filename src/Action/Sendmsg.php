<?php
namespace Yp\Process\Action;
require_once __DIR__ . '/../../Commd.php';
date_default_timezone_set('PRC');
/**
 * Created by PhpStorm.
 * User: qianglong
 * Date: 2017/12/29
 * Time: 9:12
 */
/**
 * 发送消息通知类
 * param
 * 用户手机号包含手机号码和兑换码,二维数组(必传)
 * param $card_name 变量（必传）
 * param $msgcate 短信模板类型(必传)
 * param $templateid 微信模板ID(必传)
 */

class Sendmsg
{
    /*
     * 调用发送短信方法
     * $phone
     * msgcate 短信分类（必传）
     * $data 变量(可选)
     * */
    public static function message($phone, $msgcate, $data)
    {
        //查询号码是否在黑名单
        $is_black = Db::table('bn_message_blacklist')->where(['mobile'=>$phone])->find();
        if (!$is_black) {
            self::sendMeesage($phone, $msgcate, $data);
        }

    }

    /*
     * 调用发送微信消息通知
     *$phone
     * $templateid模板id
     * $data 变量
     * */
    public static function weixinmsg($phone, $templateid, $data)
    {
        $tid = Db::table('bn_user_tel')->where(['mobile'=>$phone])->find('tid');
        if($tid){
            $wxid = Db::table('bn_user_relation')->where(['tid'=>$tid['tid']])->find('wxid');
            if ($wxid) {
                $openid = Db::table('bn_user_wx')->where(['wxid'=>$wxid['wxid']])->find('openid');
                self::sendWeixinmsg($openid['openid'], $data, $templateid, $phone);
            }
        }

    }

    //发送短信
    //$phone手机号码
    //短信模板分类id
    protected static function sendMeesage($phone, $msgcate, $data)
    {
        $mid = Db::table('bn_message_cate')->where(['id'=>$msgcate])->find('mid');
        $content = Db::table('bn_message_templet')->where(['id'=>$mid['mid']])->find('count');
        if(is_array($data)){
            $text = str_replace('{data1}', $data[0], $content['count']);
            $text = str_replace('{data2}', $data[1], $text);
            $text = str_replace('{data3}', $data[2], $text);
        }else{
            $text = str_replace('{data}', $data, $content['count']);
        }

//        $res = self::sendDx($text,$phone);
        $res = 'Success';
        //插入短信发送记录 n
        if ($res == 'Success') {
            $rcord = [
                'mobile' => $phone,
                'status' => 1,
                'time' => date('Y-m-d H:i:s', time()),
                'cate' => $msgcate,
                'intnum' =>1
            ];
            Db::table('bn_message_record')->insert($rcord);
        } else {
            $rcord = [
                'mobile' => $phone,
                'status' => 0,
                'time' => date('Y-m-d H:i:s', time()),
                'cate' => $msgcate,
                'intnum' => 1
            ];
            Db::table('bn_message_record')->insert($rcord);
        }
    }


    /*
     * openid 微信用户openid
     * data 卡卷名称
     * templateid 微信消息模板id
     * phone 手机号码
     * */
    protected static function sendWeixinmsg($openid, $data, $templateid, $phone)
    {
        $temp = Db::table('bn_weixin_template')->where(['id'=>$templateid])->find();
        $val = Db::table('bn_weixin_templatevalue')->where(['t_id'=>$templateid])->select();
        $arr = [];
        $str = '';
        if(is_array($data)){
            foreach ($val as $v) {
                if ($v['value'] == '{data1}') {
                    $v['value'] = $data[0];
                }
                if ($v['value'] == '{data2}') {
                    $v['value'] = $data[1];
                }
                if ($v['value'] == '{data3}') {
                    $v['value'] = $data[2];
                }
                $arr[$v['key']] = ["value"=>$v['value'],"color"=>"#173177"];
                $str .= $v['value'] . ',';
            }
        }else{
            foreach ($val as $v) {
                if ($v['value'] == '{data}') {
                    $v['value'] = $data;
                }
                $arr[$v['key']] = ["value"=>$v['value'],"color"=>"#173177"];
                $str .= $v['value'] . ',';
            }
        }
        $data = [
            'touser' => $openid,
            'template_id' => $temp['m_id'],
            'url' => $temp['url'],
            'data' => $arr,
        ];
//        $res = self::SendWx($data);
        $res=true;
        if($res){
            $status = 1;
        }else {
            $status = 0;
        }
        $record = [
            'openid' => $openid,
            'phone' => $phone,
            'content' => $str,
            'sendtime' => date('Y-m-d H:i:s', time()),
            'status' => $status,
        ];
        Db::table('bn_weixinmessage_record')->insert($record);
    }


    private static function sendDx($count,$mobiles)
    {
        $url ="https://sh2.ipyy.com/sms.aspx";
        $extno = "";
        $content='【百脑汇】'.$count;
        $sendtime = "";
        $body=array(
            'action'=>'send',
            'userid'=>'',
            'account'=>"jksc176",
            'password'=>"jksc17688",
            'mobile'=>$mobiles,
            'extno'=>$extno,
            'content'=>$content,
            'sendtime'=>$sendtime
        );
        $ch=curl_init();
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        $result = curl_exec($ch);
        curl_close($ch);
        $xml = simplexml_load_string($result);
        if(is_object($xml)){
            if(isset($xml->returnstatus)){
                return $xml->returnstatus;
            }else{
                return 'fail';
            }
        }else{
            return 'fail';
        }
    }


    public static function getAssessToken($type=0)
    {
        if((time()-filemtime('token.txt'))>6000 || $type=1){
            $appid='wxfe398fdcd678738f';
            $appserver = '3c208aba4deeefbe2ffb07f6b9912f18';
            $url="https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appid}&secret={$appserver}";
            $res=https_request($url);
            $token = json_decode($res,true);
            $token = $token['access_token'];
            file_put_contents('token.txt',$token);
            return $token;
        }else{
            $token = file_get_contents('token.txt');
            return $token;
        }

    }

    public static function SendWx($data)
    {
        $url="https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=".self::getAssessToken();
        $res=https_request($url,json_encode($data,JSON_UNESCAPED_UNICODE));
        $res = json_decode($res,true);
        if($res['errcode']=='40001'){
            self::getAssessToken(1);
            return self::SendWx($data);
        }
        if($res['errcode']==0){
            return true;
        }else{
            return false;
        }
    }


}