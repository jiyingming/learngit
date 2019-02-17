<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2018 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 老猫 <thinkcmf@126.com>
// +----------------------------------------------------------------------
namespace app\distribution\controller;

// 加载阿里云sms sdk
use mofyi\aliyun\Sms;
use cmf\controller\HomeBaseController;
use think\Db;
use think\Session;
use think\Cookie;

class IndexController extends HomeBaseController
{
    public function _initialize()
    {

        $referee_id = $this->request->param('referee_id', 0, 'intval');
        if(!empty($referee_id)){
            session('referee_id', $referee_id);
        }
        $this->access_token();
    }
    public function index()
    {
        if(empty(session('openid'))){
            header("Location: /distribution/index/authority");exit();
//            $this->huoquop();
        }

        $person = Db::name('fun_member')->where('openid', session('openid'))->find();
        $this->assign('person', $person);

        $user_id = $person['id'];
        $cookie_time = 30*24*3600;
        Cookie::set('user_id', $user_id ,$cookie_time);
        // 判断是否已经生成过头像图片 start
        if( strlen($person['head_img']) > 60){
            $dir = 'ticket/head_img/';
            $filename = 'ticket_'.$user_id.'.jpg';
            // 第一次生成二维码
            $result = getImage($person['head_img'], $dir, $filename, 1);
            Db::name('fun_member')->where('id', $user_id)->update(['head_img'=> '/'.$result['save_path']]);
        }
        // 判断是否已经生成过头像图片 end
        //如果货款小于0，就先补齐货款
        $money_label = 0;
        if($person['recharge_money'] < 0){
            $money_label = 1;
        }
        $this->assign('money_label', $money_label);
        return $this->fetch(':index/index');
    }
    //授权页面
    public function authority (){
        return $this->fetch(':index/authority');
    }
    //授权页面
    public function authority_openid (){
        $this->huoquop();
        header("Location: /distribution/index");
        exit();
    }
    //登录
    public function login(){
        $person = Db::name('fun_member')->where('openid', session('openid'))->find();
        if( !empty( $person['mobile'] )) {
            header('Location: /distribution/index');
            exit();
        }
        $this->assign('person', $person);
        return $this->fetch(':index/login');
    }
    //登录验证
    public function login_post(){
        $param = $this->request->param();
        if (!preg_match("/^1[3456789]{1}\d{9}$/", $param['mobile'])) {
            $msg['code'] = 0;
            $msg['info'] = '请输入正确的手机';
            return $msg;
        }
        //判断手机号是否重复
        $user_info = Db::name('fun_member')->where('mobile', $param['mobile'])->find();
        if(!empty($user_info)){
            $msg['code'] = 0;
            $msg['info'] = '当前手机号已经绑定其他账号，请更换';
            return $msg;
        }
        $error = cmf_check_verification_code($param['mobile'], $param['smsCode']);

        if (!empty($error)) {
            return json(['code' => 0, 'msg' => $error]);
        }

        if(!empty(session('openid'))){
            $user_info = Db::name('fun_member')->where('openid', session('openid'))->find();
            if(empty($user_info['mobile'])){
                Db::name('fun_member')->where('openid', session('openid'))->update(['mobile'=>$param['mobile']]);
            }

            $cookie_time = 30*24*3600;
            Cookie::set('user_id', $user_info['id'] ,$cookie_time);
            $msg['code'] = 1;
            $msg['info'] = 'ok';
            return $msg;
        }
    }
    //引导页
    public function lead_page(){
        //推荐人的id
        $user_id = $referee_id = $this->request->param('referee_id', 1, 'intval');

        $person = getMemberInfo($user_id);

        // 判断是否已经生成过二维码图片
        $dir = 'ticket/rankCode/';
        if(!file_exists($dir)){
            mkdir($dir,0777,true);
        }
        // 判断是否已经生成过二维码图片 start
        $filename = $dir.'ticket_'.$user_id.'.jpg';
        //获取微信公众号的配置
        $weixin = cmf_get_option('appid_setting');
        if(!file_exists($filename)) {
            $options = array (
                'token'          => $weixin['token'],                                   // 填写你设定的key
                'encodingaeskey' => $weixin['encodingaeskey'],  // 填写加密用的EncodingAESKey
                'appid'          => $weixin['appid'],                           // 填写高级调用功能的app id
                'appsecret'      => $weixin['appsecret']              // 填写高级调用功能的密钥
            );
            // 第一次生成二维码
            $weObj = new Wechat ($options);

            $ticket = $weObj->getQRCode($user_id, 1);
            $httpurl = $weObj->getQRUrl($ticket['ticket']);//http网址 打开是图片
            $local_file = fopen($filename, 'w');
            fwrite($local_file, file_get_contents($httpurl));
            fclose($local_file);
        }

        //更新完头像，再获取一次用户信息
        $filename = $dir.'ticket_'.$user_id.'.jpg';

        $this->assign("filename", '/'.$filename);
        $this->assign('person', $person);
        $this->assign('user_id', $user_id);
        return $this->fetch(':index/lead_page');
    }
    //退出登录
    public function logout(){
        session('openid', null);
        return redirect($this->request->root() . "/distribution/index");
    }
    //获取access_token
    public function access_token()
    {
        $weixin_setting = cmf_get_option('appid_setting');

        //获取access_token
        $appid  = $weixin_setting['appid'];
        $secret  = $weixin_setting['appsecret'];
        //查询access_token 时间 判断是否过期
        $rea = Db::name('access_token')->where(['id' => 1])->field('time')->find();
        $time = time() - $rea['time'];
        if ($time > 5000) {
            $url   = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appid}&secret={$secret}";
//            $url   = "http://www.mofyi.com/api/weixin/access_token?appid={$appid}&secret={$secret}";
            $data  = $this->curl_request($url);
            $data1 = json_decode($data, true);
            if (isset($data1['access_token'])) {
                $re['access_token'] = $data1['access_token'];
                $re['time']         = time();
                Db::name('access_token')->where(['id' => 1])->update($re);
            }
        }
    }
    //获取用户openid
    public function openid()
    {
        $data = $this->request->param();
        if (!empty($data['code'])) {
            $weixin_setting = cmf_get_option('appid_setting');
            $appid     = $weixin_setting['appid'];
            $code      = $data['code'];
            $appsecret = $weixin_setting['appsecret'];
            $url       = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=" . $appid . "&secret=" . $appsecret . "&code=" . $code . "&grant_type=authorization_code";
            $openids = $this->curl_request($url);
            $openidss = json_decode($openids, true);
            if (!empty($openidss['openid'])) {
                $user_info = Db::name('fun_member')->where('openid', $openidss['openid'])->find();
                if( empty($user_info)) {
                    //获取用户信息
//                    $access_token = Db::name('access_token')->where(['id' => 1])->value('access_token');
                    $openid = $openidss['openid'];
                    $access_token = $openidss['access_token'];
                    $url_user = "https://api.weixin.qq.com/sns/userinfo?access_token=$access_token&openid=$openid&lang=zh_CN";
//                    $url_user = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=$access_token&openid=$openid";
                    $user_wx  = $this->curl_request($url_user);

                    $user_info_wx = json_decode($user_wx, true);
                    if(!empty($user_info_wx)){
                        //有推荐人
                        if(!empty(session('referee_id'))){
                            $user_data['referee_id'] = session('referee_id');
                            $referee_userInfo = Db::name('fun_member')->where('id', session('referee_id'))->find();
                            //当推荐人的上级不为空时
                            if(!empty($referee_userInfo['referee_id'])){
                                $user_data['pre_referee_id'] = $referee_userInfo['referee_id'];
                            }
                        }
                        $user_data['openid'] = $openidss['openid'];
                        $user_data['nickname'] = $this->filterEmoji($user_info_wx['nickname']);
                        $user_data['sex'] = $user_info_wx['sex'];
                        $user_data['head_img'] = $user_info_wx['headimgurl'];
                        $user_data['rank'] = 0;
                        $user_data['create_time'] = time();
                        Db::name('fun_member')->insert($user_data);
                        $user_id = Db::name('fun_member')->getLastInsID();
                        //更新一下 不可更改的id
                        Db::name('fun_member')->where('id', $user_id)->update(['member_num'=>sprintf("%06d", $user_id)]);
                        // 判断是否已经生成过头像图片 start
                        $dir = 'ticket/head_img/';
                        $filename = 'ticket_'.$user_id.'.jpg';
                        // 第一次生成二维码
                        $result = getImage($user_info_wx['headimgurl'], $dir, $filename, 1);
                        Db::name('fun_member')->where('id', $user_id)->update(['head_img'=> '/'.$result['save_path']]);
                    }
                }
                session('openid', $openidss['openid']);
                header("Location:/distribution/index/index");
                exit;
            }

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
    //php保存任意网络图片到服务器的方法
    function get_photo($url, $filename='',$savefile='/upload/head_img/')
    {
        $imgArr = array('gif','bmp','png','ico','jpg','jepg');

        if(!$url) return false;

        if(!is_dir($savefile)) mkdir($savefile, 0777, true);
        if(!is_readable($savefile)) chmod($savefile, 0777, true);

        $filename = $savefile.$filename;
        ob_start();
        readfile($url);
        $img = ob_get_contents();
        ob_end_clean();
        $size = strlen($img);

        $fp2 = @fopen($filename, "a");
        fwrite($fp2,$img);
        fclose($fp2);

        return $filename;
    }
    //微信获取opendid
    public function huoquop()
    {
        $weixin_setting = cmf_get_option('appid_setting');
        $redirectUri=  urlencode($_SERVER["REQUEST_SCHEME"].'://'.$_SERVER["SERVER_NAME"].'/distribution/index/openid');
        $appid     = $weixin_setting['appid'];

        $url = "https://open.weixin.qq.com/connect/oauth2/authorize?appid={$appid}&redirect_uri={$redirectUri}&response_type=code&scope=snsapi_userinfo&state=STATE#wechat_redirect";
        header("Location:{$url}");
        exit;
    }
    // 发送短信
    public function send_sms(){

        $data = $this->request->param();

        if(empty($data['mobile'])){

            $code=['code'=>1,'msg'=>'缺少参数'];
            return json($code);exit;
        }
        $is_modile = preg_match("/^1[3456789]{1}\d{9}$/",$data['mobile']);
        if(!$is_modile){
            $code=['code'=>1,'msg'=>'手机号不正确'];
            return json($code);exit;
        }
        $account = $data['mobile'];
        // 获取验证码
        $code = cmf_get_verification_code($account);
        if (empty($code)) {
            return json(['code' => 0, 'msg' => '验证码发送过多,请明天再试!']);
        }
        //查询短息有效期
        $gtime = time() - 60;
        $where1['account'] = $account;
        $where1['send_time'] = ['gt', $gtime];
        $duanx = Db::name('verification_code')->where($where1)->field(true)->find();

        if (empty($duanx)) {
            //发送短息
            $expireTime = time() + 60; //短息有效期
            $result = Sms::sendSms($account, $code);

            if ($result->Code == 'OK') {
                cmf_verification_code_log($account, $code, $expireTime);
                return json(['code' => 1, 'msg' => '验证码已经发送成功']);
            } else if ($result->Code == 'isv.MOBILE_NUMBER_ILLEGAL') {
                return json(['code' => 0, 'msg' => '手机号格式错误或使用国内手机号']);
            } else if ($result->Code == 'isv.BUSINESS_LIMIT_CONTROL') {
                return json(['code' => 0, 'msg' => '获取验证码已经超过当天限制']);
            } else {
                return json(['code' => 0, 'msg' => '发送失败']);
            }
        } else {
            return json(['code' => 0, 'msg' => '验证码已发送，有效期为30分钟!']);
        }

    }
    // 发送短信(31会议的接口)
    public function send_sms_old(){

        $data=$this->request->param();

        if(empty($data['mobile'])){

            $code=['code'=>1,'msg'=>'缺少参数'];
            return json($code);exit;
        }
        $is_modile = preg_match("/^1[3456789]{1}\d{9}$/",$data['mobile']);
        if(!$is_modile){
            $code=['code'=>1,'msg'=>'手机号不正确'];
            return json($code);exit;
        }
        $requestUrl = "http://openapi.31huiyi.com/rest/system/sendcode";
        $code = $this->getCode();
        $da = ['Mobile'=>$data['mobile'],'Code'=>$code,'UserId'=>1519913507];

        $re = json_encode($da);
        $authinfo = getAuth($requestUrl);
        $result = curl_request($requestUrl,$re,'json',$authinfo);
        $resultData = json_decode($result, true);
        $sms_data =  Db::name('sms_code')->where('mobile', $data['mobile'])->find();
        if(!empty($sms_data)){
            Db::name('sms_code')->where('id', $sms_data['id'])->update(['sms_code'=>$resultData['Body'], 'create_time'=>time()]);

        } else {
            $where['mobile'] = $data['mobile'];
            $where['sms_code'] = $resultData['Body'];
            $where['create_time'] = time();
            Db::name('sms_code')->insert($where);
        }
        $code=['code'=>2,'msg'=>'发送成功'];
        return json($code);
    }
    //获取随机数
    public  function getCode($m=6,$type=0){
        $str = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $t = array(9,35,strlen($str)-1);
        //随机生成验证码所需内容
        $c="";
        for($i=0;$i<$m;$i++){
            $c.=$str[rand(0,$t[$type])];
        }
        return $c;
    }
    protected function curl_request($url, $post = '', $type = 'json', $header = ['Content-Type: application/json'], $cookie = '', $returnCookie = 0)
    {
        //初始化 创建一个新的CURL资源
        $curl = curl_init();
        if (0 === strpos(strtolower($url), 'https')) {
            // 信任任何证书,https需设置
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            // 从证书中检查SSL加密算法是否存在
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($curl, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1);
        }
        //启用时会将头文件的信息作为数据流输出。
        curl_setopt($curl, CURLOPT_HEADER, $returnCookie);
        //允许 cURL 函数执行的最长秒数。
        curl_setopt($curl, CURLOPT_TIMEOUT, 120);
        //TRUE 将curl_exec()获取的信息以字符串返回，而不是直接输出。
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        //设置抓取的url
        curl_setopt($curl, CURLOPT_URL, $url);
        //设置post数据
        if ($post) {
            if ($type === 'json') { //json方式提交数据
                //$headarray = array('Content-Length: ' . strlen($post));
                array_push($header, 'Content-Length: ' . strlen($post));
                //设置 HTTP 头字段的数组。格式： array('Content-type: text/plain', 'Content-length: 100')
                curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
                //TRUE 时会发送 POST 请求，类型为：application/x-www-form-urlencoded，是 HTML 表单提交时最常见的一种。
                curl_setopt($curl, CURLOPT_POST, 1);
                //发送的数据
                curl_setopt($curl, CURLOPT_POSTFIELDS, $post);//$data JSON类型字符串

            } else {//正常form方式提交数据
                //下面发送一个常规的POST请求，类型为application/x-www-form-urlencoded,就像提交表单一样
                curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 5.2; rv:19.0) Gecko/20100101 Firefox/19.0");
                //TRUE 时会发送 POST 请求，类型为：application/x-www-form-urlencoded，是 HTML 表单提交时最常见的一种。
                curl_setopt($curl, CURLOPT_POST, 1);
                curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post));
            }
        }else{

            curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        }
        //设置请求cookie
        if ($cookie) {
            curl_setopt($curl, CURLOPT_COOKIE, $cookie);
        }
        //执行命令
        $data = curl_exec($curl);
        if (curl_errno($curl)) {
            //返回最后一次的错误代码 无错误时 返回0
            $array = [
                'Code' => -1,
                'MessageToString' => curl_error($curl)
            ];
            return json_encode($array);
        }
        //关闭url请求
        curl_close($curl);
        //返回COOKIE信息
        if ($returnCookie) {
            list($header, $body) = explode("\r\n\r\n", $data, 2);
            preg_match_all("/Set\-Cookie:([^;]*);/", $header, $matches);
            $info['cookie'] = substr($matches[1][0], 1);
            $info['content'] = $body;
            return $info;
        } else {
            return $data;
        }
    }
}
