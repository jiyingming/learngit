<?php
namespace think\Wechat;

use GuzzleHttp\Cookie\SessionCookieJar;
use think\Wechat\Wechat;
use think\Db;
use think\session;

class WechatAction {
    public $text_name; // 定义了一个公有属性
    public function init() {
        $weixin = cmf_get_option('appid_setting');

        $options = array (
            'token'          => $weixin['token'],                                   // 填写你设定的key
            'encodingaeskey' => $weixin['encodingaeskey'],  // 填写加密用的EncodingAESKey
            'appid'          => $weixin['appid'],                           // 填写高级调用功能的app id
            'appsecret'      => $weixin['appsecret']              // 填写高级调用功能的密钥
            // 'partnerid'      => '',                                          // 财付通商户身份标识
            // 'partnerkey'     => '',                                          // 财付通商户权限密钥Key
            // 'paysignkey'     => ''                                           // 商户签名密钥Key
        );

        $weObj = new Wechat ( $options );
        return $weObj;
    }
    public function index() {
        $weObj = $this->init ();
//        file_put_contents('test.txt','2222'.PHP_EOL,FILE_APPEND);
        $weObj->valid ();

        $type = $weObj->getRev ()->getRevType ();
        $this->createMenu();
//        file_put_contents('type.txt',$type.'quau'.PHP_EOL,FILE_APPEND);
        //---------------------------//
        $openid = $weObj->getRevFrom();//openid
        session('openid', $openid);
        //---------------------------//
        switch ($type) {
            case Wechat::MSGTYPE_TEXT ://发送text消息
                $msg_data = $weObj->getRevData ();//获取发送的消息

                if($msg_data['content'] == '首页'|| $msg_data['content'] == 'index'){
                    $data = array(
                        "0"=>array(
                            'Title'=>'您好，欢迎关注"Honda在华关联企业植树活动"服务平台',
                            'Description'=>'Honda在华关联企业植树活动',
                            'PicUrl'=>'https://img.mofyi.com/201807/1615564832758.jpg',
                            'Url'=>'http://8.mofyi.com/plant_wx/metting'
                        )
                    );
                    $weObj->getRev ()->news ( $data)->reply ();
//                    $weObj->text ( 'laiwen77' )->reply ();
                }
                break;
            case Wechat::MSGTYPE_IMAGE://image

            case Wechat::MSGTYPE_VOICE://语音消息
//                $this->_doVoice($request_xml);
                break;

            case Wechat::MSGTYPE_EVENT :
                $eventype = $weObj->getRev ()->getRevEvent ();
//                file_put_contents("1234.txt", json_encode($eventype).PHP_EOL, FILE_APPEND);
                if ($eventype ['event'] == "CLICK") {
                    if($eventype['key'] == 'pang_click'){
                        $data = array(
                            "0"=>array(
                                'Title'=>'您好，欢迎来减肥',
                                'Description'=>'欢迎来减肥',
                                'PicUrl'=>'https://img.mofyi.com/201807/1615564832758.jpg',
                                'Url'=>'http://8.mofyi.com/plant_wx/metting'
                            ),
                        );
                        $weObj->getRev ()->news ( $data)->reply ();
                    }

                }
                else if($eventype ['event'] == "VIEW_LIMITED"){
                    if($eventype ['media_id']=='MEDIA_ID2'){
                        $text = '88折优惠';
                        $weObj->text ( $text )->reply ();
                    }
                }
                //扫描二维码事件
                elseif ($eventype['event'] == "subscribe") {
                    //网站信息
                    $site_info = cmf_get_option('site_info');
                    //查询是否有openid
                    $result = Db('fun_member')->where('openid', $openid)->find();
                    if(empty($result)) {
                        //获取用户信息 返回json格式数据
                        $info = $weObj->getUserInfo($openid);
                        $wx_info = json_decode($info,true);
//                        file_put_contents('wx_info.txt', $info.PHP_EOL,FILE_APPEND);

                        $user_data['openid'] = $openid;
                        $user_data['nickname'] = $this->filterEmoji($wx_info['nickname']);
                        $user_data['sex'] = $wx_info['sex'];
                        $user_data['head_img'] = $wx_info['headimgurl'];

                        $user_data['rank'] = 0;
                        $user_data['create_time'] = time();
                        $exp_key = explode('_', $eventype['key']);
//                        file_put_contents('parent_id.txt', json_encode($exp_key).PHP_EOL,FILE_APPEND);
                        $parent_id = isset($exp_key[1]) ? $exp_key[1] : $exp_key[0];
//                        file_put_contents('parent_id.txt', $parent_id);
                        //有推荐人
                        if(!empty($parent_id)){
                            $user_data['referee_id'] = $parent_id;
                            $referee_userInfo = Db::name('fun_member')->where('id', $parent_id)->find();
                            //当推荐人的上级不为空时
                            if(!empty($referee_userInfo['referee_id'])){
                                $user_data['pre_referee_id'] = $referee_userInfo['referee_id'];
                            }
                        }
                        Db::name('fun_member')->insert($user_data);
                        $user_id = Db::name('fun_member')->getLastInsID();
                        //更新一下 不可更改的id
                        Db::name('fun_member')->where('id', $user_id)->update(['member_num'=>sprintf("%06d", $user_id)]);

                        $text = '您好：欢迎您【'.$wx_info['nickname'].'】加入'.$site_info['site_name'].'。';
                        $weObj->text ( $text )->reply ();
                    }
                    else {

                        $text = '您好：欢迎您【'.$result['nickname'].'】加入'.$site_info['site_name'].'。';
                        $weObj->text ( $text )->reply ();
                    }
                }

                elseif ($eventype['event'] == "SCAN") {
                    //网站信息
                    $site_info = cmf_get_option('site_info');
                    //查询是否有openid
                    $result = Db('fun_member')->where('openid', $openid)->find();
                    if(empty($result)) {
                        //获取用户信息 返回json格式数据
                        $info = $weObj->getUserInfo($openid);
                        $wx_info = json_decode($info,true);
//                        file_put_contents('wx_info.txt', $info.PHP_EOL,FILE_APPEND);

                        $user_data['openid'] = $openid;
                        $user_data['nickname'] = $this->filterEmoji($wx_info['nickname']);
                        $user_data['sex'] = $wx_info['sex'];
                        $user_data['head_img'] = $wx_info['headimgurl'];
                        $user_data['rank'] = 0;
                        $user_data['create_time'] = time();
//                        file_put_contents('parent_id.txt', json_encode($eventype).PHP_EOL,FILE_APPEND);
                        $exp_key = explode('_', $eventype['key']);
//                        file_put_contents('parent_id.txt', json_encode($exp_key).PHP_EOL,FILE_APPEND);
                        $parent_id = isset($exp_key[1]) ? $exp_key[1] : $exp_key[0];

//                        file_put_contents('parent_id.txt', $parent_id.PHP_EOL,FILE_APPEND);
                        //有推荐人
                        if(!empty($parent_id)){
                            $user_data['referee_id'] = $parent_id;
                            $referee_userInfo = Db::name('fun_member')->where('id', $parent_id)->find();
                            //当推荐人的上级不为空时
                            if(!empty($referee_userInfo['referee_id'])){
                                $user_data['pre_referee_id'] = $referee_userInfo['referee_id'];
                            }
                        }
                        Db::name('fun_member')->insert($user_data);
                        $user_id = Db::name('fun_member')->getLastInsID();
                        //更新一下 不可更改的id
                        Db::name('fun_member')->where('id', $user_id)->update(['member_num'=>sprintf("%06d", $user_id)]);
                        $text = '您好：欢迎您【'.$wx_info['nickname'].'】加入'.$site_info['site_name'].'。';
                        $weObj->text ( $text )->reply ();
                    }
                    else {
                        $text = '您好：欢迎您【'.$result['nickname'].'】加入'.$site_info['site_name'].'。';
                        $weObj->text ( $text )->reply ();
                    }
                }
                exit ();
                break;
            default :
                //网站信息
                $site_info = cmf_get_option('site_info');
                $text = '您好：欢迎您加入'.$site_info['site_name'].'。';
                $weObj->text ( $text )->reply ();
                //发送图文消息
//                $data = array(
//                    "0"=>array(
//                        'Title'=>'您好，欢迎关注"Honda在华关联企业植树活动"服务平台',
//                        'Description'=>'Honda在华关联企业植树活动',
//                        'PicUrl'=>'https://img.mofyi.com/201807/1615564832758.jpg',
//                        'Url'=>'http://8.mofyi.com/plant_wx/metting'
//                    )
//                );
//                $weObj->getRev ()->news ( $data)->reply ();
        }
    }
    // 过滤掉emoji表情
    public function filterEmoji($str)
    {
        $str = preg_replace_callback(
            '/./u',
            function (array $match) {
                return strlen($match[0]) >= 4 ? '' : $match[0];
            },
            $str);
        return $str;
    }

    public function createMenu() {
//        include dirname(__FILE__).'/button_config.php';
        // echo '66';die;
        $newmenu = '{
    "button": [
        {
            "name": "系统首页",
            "type": "view",
            "url": "https://runlijia.mofyi.com/distribution/index"
        },
        {
            "name": "防伪查询",
            "type": "view",
            "url": "http://www.rljhealth.com/index/security.html"
        },
        {
            "name": "素材库",
            "type": "view",
            "url": "https://pan.baidu.com/s/1EunzX2KdvKSRx-TkXFJZFQ"
        }
    ]
}';
//        dump($newmenu);
        $weObj = $this->init ();
        $weObj->createMenu ( $newmenu );

        echo '<script type="text/javascript">alert("菜单创建成功");history.go(-1);</script>';
//         $this->success ( "重新创建菜单成功!" );
    }


}


?>
