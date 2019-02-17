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
use think\Request;
use think\Wechat\Wechat;

class PersonalController extends HomeBaseController
{
    //个人资料
    public function index()
    {
        $user_id = Cookie::get('user_id');
        $member = getMemberInfo($user_id);
        if(empty($member['mobile'])){
            header('Location: /distribution/index/login');exit();
        }
        $person = getMemberInfo(Cookie::get('user_id'));
        if(empty($person['mobile'])){
            header('Location: /distribution/index/login');exit();
        }
        $this->assign('person', $person);
        return $this->fetch(':personal/index');
    }
    //个人资料
    public function edit()
    {
        if(empty(Cookie::get('user_id'))){
            header('Location: /distribution/index/login');exit();
        }
        $option = $this->request->param('option', '');
        $person = Db::name('fun_member')->where('id', Cookie::get('user_id'))->find();
        $this->assign('person', $person);
        $this->assign('option', $option);
        return $this->fetch(':personal/edit');
    }
    //性别
    public function person_sex(){
        if(empty(Cookie::get('user_id'))){
            header('Location: /distribution/index/login');exit();
        }
        $person = getMemberInfo(Cookie::get('user_id'));
        $this->assign('person', $person);
        return $this->fetch(':personal/person_sex');
    }
    //个人资料-- 保存
    public function edit_post(){
        $param = $this->request->param();
        $user_id = Cookie::get('user_id');
        switch($param['option']){
            case '昵称':
                $res = Db::name('fun_member')->where('id', $user_id)->update(['nickname'=> $param['nickname']]);
                break;
            case 'mobile':
                $res = Db::name('fun_member')->where('id', $user_id)->update(['mobile'=> $param['mobile']]);
                break;
            case '微信号':
                $res = Db::name('fun_member')->where('id', $user_id)->update(['wechat_number'=> $param['wechat_number']]);
                break;
            case '性别':
                $res = Db::name('fun_member')->where('id', $user_id)->update(['sex'=> $param['sex']]);
                break;
            case '所在地':
                $res = Db::name('fun_member')->where('id', $user_id)->update(['place'=> $param['place']]);
                break;
            case '出生日期':
                $res = Db::name('fun_member')->where('id', $user_id)->update(['birthDate'=> $param['birthDate']]);
                break;
        }
        if($res){
            header('Location: /distribution/personal');
            exit();
        }

    }
    //上传照片
    public function up_photo(){
        if(empty(Cookie::get('user_id'))){
            header('Location: /distribution/index/login');exit();
        }
        $person = getMemberInfo(Cookie::get('user_id'));
        $this->assign('person', $person);
        return $this->fetch(':personal/up_photo');
    }
    public function check_mobile(){
        if(empty(Cookie::get('user_id'))){
            header('Location: /distribution/index/login');exit();
        }
        $person = getMemberInfo(Cookie::get('user_id'));
        $this->assign('person', $person);
        return $this->fetch(':personal/check_mobile');
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
            $result = Sms::sendSms2($account, $code);

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
    //
    public function check_next(){
        $param = $this->request->param();
        if (!preg_match("/^1[3456789]{1}\d{9}$/", $param['mobile'])) {
            $msg['code'] = 0;
            $msg['info'] = '请输入正确的手机';
            return $msg;
        }
        //判断手机号是否重复
        $user_info = Db::name('fun_member')->where('mobile', $param['mobile'])->find();
        if(empty($user_info)){
            $msg['code'] = 0;
            $msg['msg'] = '当前手机号不存在';
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
            $msg['msg'] = 'ok';
            return $msg;
        }
    }
    //等级更新
    public function rank_update ()
    {
        if(empty(Cookie::get('user_id'))){
            header('Location: /distribution/index/login');exit();
        }
        $where['status'] = 1;//删除
        $where['post_status'] = 1;//是否显示在前台
        $res = Db::name('fun_agent')->where($where)->order('id','desc')->select()->toArray();
        $result = array_reverse($res);
        $this->assign('result', $result);
        return $this->fetch(':personal/rank_update');
    }
    //更新会员等级
    public function update_rank(){
        $rank = $this->request->param('rank');
        $update_money = $this->request->param('update_money');
        if(empty($rank)){
            echo "<script>alert('请求错误');window.location.href='/distribution/order/index'</script>";
        }
        $user_id = Cookie::get('user_id');
        $member = $member1 = getMemberInfo($user_id);
        //更新自身的等级和扣除保证金 start
        $member_update['rank'] = $rank;
        $rank_info = getRank($rank);
        $plus_money = $member['recharge_money'] - $rank_info['deposit'] + floatval($member['deposit']);
        if($plus_money >= 0){
            $member_update['recharge_money'] = $plus_money;
            $member_update['deposit'] = $rank_info['deposit'];
            $member_update['update_time'] = time();
        }
        Db::name('fun_member')->where('id',$user_id )->update($member_update);

        //更新自身的等级和扣除保证金 end
        $member = getMemberInfo($user_id);
        file_put_contents('notify.txt', '$member:'.json_encode($member) . PHP_EOL, FILE_APPEND);
        // 代理升级人的账单 和发送模板消息 start
        $member_bill_data = array(
            'user_id'         => $user_id,
            'rebate_id'       => 0,
            'order_id'        => '',
            'head_img'        => $member['head_img'],
            'user_name'       => $member['nickname'],
            'mobile'          => $member['mobile'],
            'total_money'     => $update_money,//从充值里面扣除
            'profit'          => 0,//可提现
            'bill_type'       => 3,//1:消费，2：获利 3:代理升级
            'profit_type'     => 2,//获利的方式(1:消费，2：代理升级，3:分流客服的客户充值得到的返利--冻结资金)
            'profit_from'     => 0,//1:购买走上级库存，2：返给董事 3：买断关系（更换上级）
            'create_time'     => time(),
            'content'         => $member['nickname'].'('.getRankName($member1['rank']).'),您累计订单金额：'.$update_money.'得到免费升级的资格,恭喜您已经成功升级为'.getRankName($member['rank']),
            'statistics_time' => date('Y年m月', time())
        );
        Db::name('fun_bill')->insert($member_bill_data);
        //发送模板消息
        $this->sign($member['openid'], $rank);

        // 代理升级人的账单 和发送模板消息 end
        if(!empty($member['referee_id'])){
            $other_setting = cmf_get_option('other_setting');
            //推荐人信息
            $parent_m = getMemberInfo($member['referee_id']);
            //当上级的等级小于自己时 到了买断和上级关系的时候了
            if($parent_m['rank'] <= $rank){
                //获取新的推荐人信息
                $new_parent = get_new_parent($parent_m, $member);
                //推荐金钻（买断）
                if($rank == 9){
                    $agent_money = $other_setting['recom_gold'];
                    //新上级
                    $recharge_money = $new_parent['recharge_money'] - $agent_money;
                    //成为过去的上级
                    $fanli_money = $parent_m['recharge_money'] + $agent_money;
                    Db::name('fun_member')->where('id',$user_id )->update(['buy_gold'=>1]);
                }
                //推荐银钻（买断）
                else if($rank == 4){
                    $agent_money = $other_setting['recom_founder'];
                    //新上级
                    $recharge_money = $new_parent['recharge_money'] - $agent_money;
                    //成为过去的上级
                    $fanli_money = $parent_m['recharge_money'] + $agent_money;
                    //现在废弃 推荐3人升董事 start
//                    //更新一下推荐联合创始人的人数
//                    $rank4_count = $parent_m['rank4_count'] + 1;
//                    Db::name('fun_member')->where('id', $parent_m['id'])->update(['rank4_count'=>$rank4_count]);
//                    //升为董事（9）需要推荐联创（4）的人数 并且 他的等级是联创（4）的话就可以升为董事（9）
//                    if($rank4_count >= $other_setting['uptoDirectorCount'] && $parent_m['rank'] == 4 ){
//                        //更新等级为董事，并且扣除保证金
//                        $parent_update['rank'] = 9;
//                        $director = getRank(9);
//                        $parent_update['recharge_money'] = $parent_m['recharge_money'] - $director['deposit'] + floatval($parent_m['deposit']);
//                        $parent_update['deposit'] = $director['deposit'];
//                        Db::name('fun_member')->where('id', $parent_m['id'])->update($parent_update);
//                        //发送模板消息
//                        $this->sign($parent_m['openid'], 9);
//
//                    }
                    //现在废弃 推荐3人升董事 end
                }
                else if($rank == 3){
                    $agent_money = $other_setting['recom_agent'];
                    //新上级
                    $recharge_money = $new_parent['recharge_money'] - $agent_money;
                    //成为过去的上级
                    $fanli_money = $parent_m['recharge_money'] + $agent_money;
                }
                else if($rank == 2){
                    $agent_money = $other_setting['recom_vip'];
                    //新上级
                    $recharge_money = $new_parent['recharge_money'] - $agent_money;
                    //成为过去的上级
                    $fanli_money = $parent_m['recharge_money'] + $agent_money;
                }
                //新上级
                Db::name('fun_member')->where('id', $new_parent['id'])->update(['recharge_money'=>$recharge_money]);
                //支出买断关系的money
                $bill_data = array(
                    'user_id'         => $new_parent['id'],
                    'rebate_id'       => 0,
                    'order_id'        => '',
                    'head_img'        => $new_parent['head_img'],
                    'user_name'       => $new_parent['nickname'],
                    'mobile'          => $new_parent['mobile'],
                    'total_money'     => $agent_money,//从充值里面扣除
                    'profit'          => 0,//可提现
                    'bill_type'       => 3,//1:消费，2：获利 3:代理升级
                    'profit_type'     => 2,//获利的方式(1:消费，2：代理升级，3:分流客服的客户充值得到的返利--冻结资金)
                    'profit_from'     => 3,//1:购买走上级库存，2：返给董事 3：买断关系（更换上级）
                    'create_time'     => time(),
                    'content'         => '升级的情况（累计订单金额），'.$new_parent['nickname'].'支出了'.$agent_money.'元买断'.$member['nickname'].'和'.$parent_m['nickname'].'之间的推荐关系('.
                        $member['nickname'].'代理升级为'.getRankName($member['rank']).',等级比'.$parent_m['nickname'].'的等级高)',
                    'statistics_time' => date('Y年m月', time()),
                    'rank' => $rank,
                    'buyout_id'=>$member['id']
                );
                Db::name('fun_bill')->insert($bill_data);
                //成为过去的上级
                Db::name('fun_member')->where('id', $parent_m['id'])->update(['fanli_money'=>$fanli_money]);
                //获得推荐奖金
                $bill_data = array(
                    'user_id'         => $parent_m['id'],
                    'rebate_id'       => $new_parent['id'],
                    'order_id'        => '',
                    'head_img'        => $new_parent['head_img'],
                    'user_name'       => $new_parent['nickname'],
                    'mobile'          => $new_parent['mobile'],
                    'total_money'     => 0,//从充值里面扣除
                    'profit'          => $agent_money,//可提现
                    'bill_type'       => 3,//1:消费，2：获利 3:代理升级
                    'profit_type'     => 2,//获利的方式(1:消费，2：代理升级，3:分流客服的客户充值得到的返利--冻结资金)
                    'profit_from'     => 3,//1:购买走上级库存，2：返给董事 3：买断关系（更换上级）
                    'create_time'     => time(),
                    'content'         =>  '升级的情况（累计订单金额），'.$parent_m['nickname'].'得到了一笔推荐奖--'.$agent_money. ',是因为'.$parent_m['nickname'].'和'.$member['nickname'].
                        getRankName($member['rank']).'之间的推荐关系被'.$new_parent['nickname']. '买断.',
                    'statistics_time' => date('Y年m月', time()),
                    'rank' => $rank,
                    'buyout_id'=>$member['id']
                );
                Db::name('fun_bill')->insert($bill_data);
                // 代理升级更换上级
                if($parent_m['rank'] < $rank){
                    $pre_referee_id = isset($new_parent['referee_id']) ? $new_parent['referee_id'] : 0;
                    Db::name('fun_member')->where('id', $member['id'])->update(['referee_id'=>$new_parent['id'], 'pre_referee_id'=>$pre_referee_id]);
                    //代理升级人的下级要更换上上级
                    $member_list = Db::name('fun_member')->field('id, referee_id, pre_referee_id')->where('referee_id', $member['id'])->select()->toArray();
                    if(!empty($member_list)){
                        foreach($member_list as $k=>$v){
                            Db::name('fun_member')->where('id', $v['id'])->update(['pre_referee_id'=>$new_parent['id']]);
                        }
                    }
                }
            }
        }
        else {
            $company = Db::name('fun_member')->where('rank',20)->find();
            Db::name('fun_member')->where('id',$user_id )->update(['referee_id'=>$company['id']]);
        }
        echo "<script>alert('升级成功');window.location.href='/distribution/order/index'</script>";

    }
    //代理码
    public function rank_code ()
    {
        if(empty(Cookie::get('user_id'))){
            header('Location: /distribution/index/login');exit();
        }
        $user_id = Cookie::get('user_id');
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
        // 判断是否已经生成过二维码图片 end
        //微信分享 start
        $request = Request::instance();
        $url =  "https://www.mofyi.com/api/weixin/getSignPackagecj";
        $post['appid'] = $weixin['appid'];
        $post['appSecret'] = $weixin['appsecret'];
        $post['url'] = $request->url(true);
//        $post['url'] = 'http://'.$_SERVER['SERVER_NAME'].'/distribution/wechat/referee_id/'.$user_id;
        $dataa = curl_request($url, json_encode($post) );

        $datas = json_decode($dataa, true);
        $this->assign('signPackage', $datas['data']);

        //更新完头像，再获取一次用户信息
        $filename = $dir.'ticket_'.$user_id.'.jpg';

        $this->assign("filename", '/'.$filename);
        $this->assign('person', $person);
        $this->assign('user_id', $user_id);
        $this->assign('domain_url', $_SERVER["REQUEST_SCHEME"].'://'.$_SERVER["SERVER_NAME"]);
        return $this->fetch(':personal/rank_code');
    }
    //我的团队
    public function my_team ()
    {
        if(empty(Cookie::get('user_id'))){
            header('Location: /distribution/index/login');exit();
        }
        $param = $this->request->param();
        $user_id = Cookie::get('user_id');
        if(isset($param['agent']) && !empty($param['agent'])){
            $wh['nickname'] = ['like', '%'.$param['agent'].'%'];
        }
        //团队的所有人
        $id_str = get_team_list($user_id);
        $id_arr = explode(',', $id_str);
        //团队人数
        $count = count($id_arr);
        $this->assign('count', $count);
        //团队订单金额
        $sum_orderamount = 0;
        if(!empty($id_str)){
            //团队销售额(能获得团队奖)
            $where['userid'] = ['in', get_team_person($user_id)];
            $where['delstate_time'] = 0;
            $where['post_status'] = ['egt', 1];
            $where['statistics_time'] = date('Y年m月', time());
            $sum_orderamount = Db::name('fun_goodsorder')->where($where)->sum("orderamount");
        }
        $this->assign('sum_orderamount', $sum_orderamount);
        $wh['referee_id'] = $user_id;
        $wh['delete_status'] = 0;
        //直属团队
        $direct_team = Db::name('fun_member')->where($wh)->select()->toArray();
        if(!empty($direct_team)){
            foreach($direct_team as $k=>$team){
                unset($where);
                //直属代理数
                $where['referee_id'] = $team['id'];
                $where['delete_status'] = 0;
                $team_count =  Db::name('fun_member')->where($where)->count('id');
                $direct_team[$k]['team_count'] = $team_count;

                unset($where);
                //加上 下级董事团队的提货额
                if($team['is_team_vip'] == 1){
                    $team_id_arr = get_team_list($team['id']);
                }
                else {
                    $team_id_arr = get_team_person($team['id']);
                }
                //订单总额
                $where['userid'] = ['in', $team_id_arr];
                $where['delstate_time'] = 0;
                $where['post_status'] = ['egt', 1];
                $where['statistics_time'] = date('Y年m月', time());
                $direct_team[$k]['sum_orderamount'] = Db::name('fun_goodsorder')->where($where)->sum("orderamount");
            }
        }

        $this->assign('agent', isset($param['agent']) ? $param['agent'] : '');
        $this->assign('direct_team', $direct_team);
        $this->assign('direct_team_count', count($direct_team));//直属团队人数

        return $this->fetch(':personal/my_team');
    }
    //团队详情
    public function team_detail ()
    {
        if(empty(Cookie::get('user_id'))){
            header('Location: /distribution/index/login');exit();
        }
        $user_id = $this->request->param('user_id',0,'intval');

        $member = Db::name('fun_member')->where('id', $user_id)->find();
        //推荐人
        $referee_member = Db::name('fun_member')->where('id', $member['referee_id'])->find();
        //直属团队
        $where['referee_id'] = $user_id;
        $where['delete_status'] = 0;
        $direct_team = Db::name('fun_member')->where($where)->select()->toArray();
        if(!empty($direct_team)){
            foreach($direct_team as $k=>$team){
                //直属代理数
                unset($where);
                $where['referee_id'] = $team['id'];
                $where['delete_status'] = 0;
                $team_count =  Db::name('fun_member')->where($where)->count('id');
                $direct_team[$k]['team_count'] = $team_count;
                //订单总额
                unset($where);
                //加上 下级董事团队的提货额
                if($team['is_team_vip'] == 1){
                    $team_id_arr = get_team_list($team['id']);
                }
                else {
                    $team_id_arr = get_team_person($team['id']);
                }
                $where['userid'] = ['in', $team_id_arr];
                $where['delstate_time'] = 0;
                $where['post_status'] = ['egt', 1];
                $where['statistics_time'] = date('Y年m月', time());
                $direct_team[$k]['sum_orderamount'] = Db::name('fun_goodsorder')->where($where)->sum("orderamount");
            }
        }
        $this->assign('direct_team', $direct_team);
        $this->assign('direct_team_count', count($direct_team));//直属团队人数
        $this->assign('member', $member);
        $this->assign('referee_member', $referee_member);//推荐人
        return $this->fetch(':personal/team_detail');
    }
    //上传图片
    public function upload_img(){
        if ($this->request->file('photo'))
        {
            $up_id_photo = up_file('photo');
            if ($up_id_photo['code'] == 1) {
                $res = Db::name('fun_member')->where('id', Cookie::get('user_id'))->update(['head_img'=>$up_id_photo['url']]);
                if($res){
                    $resp['code'] = 1;
                    $resp['msg'] = $up_id_photo['url'];
                }
//                halt($up_id_photo['url']);
//                $this->result($up_id_photo['url'],1,'success','json');
            } elseif ($up_id_photo['code'] === 0) {
                $resp['code'] = 0;
                $resp['msg'] = $up_id_photo['msg'];
            }else{
                $resp['code'] = 0;
                $resp['msg'] = $up_id_photo['msg'];
            }
        }else{
            $resp['code'] = 0;
            $resp['imgurl'] = 'No File input.';
        }
        return $resp;
    }
    //实名认证
    public function certification(){
        if(empty(Cookie::get('user_id'))){
            header('Location: /distribution/index/login');exit();
        }
        $person = getMemberInfo(Cookie::get('user_id'));
        $this->assign('person', $person);
        return $this->fetch(':personal/certification');
    }
    //实名认证(企业)
    public function company_check(){

        $user_id = Cookie::get('user_id');
        if(empty($user_id)){
            header('Location: /distribution/index/login');exit();
        }
        $person = Db::name('fun_company')->where('user_id', $user_id)->find();
        $this->assign('person', $person);
        return $this->fetch(':personal/company_check');
    }
    //上传图片
    public function license_upload(){
        $param = $this->request->param();
        if($param['license_status']){
            if ($this->request->file('license1'))
            {
                $up_id_photo = up_file('license1');
                if ($up_id_photo['code'] == 1) {
                    $resp['code'] = 1;
                    $resp['msg'] = $up_id_photo['url'];
                } elseif ($up_id_photo['code'] === 0) {
                    $resp['code'] = 0;
                    $resp['msg'] = $up_id_photo['msg'];
                }else{
                    $resp['code'] = 0;
                    $resp['msg'] = $up_id_photo['msg'];
                }
            }else{
                $resp['code'] = 0;
                $resp['imgurl'] = 'No File input.';
            }
        } else {
            if ($this->request->file('tax_photo1'))
            {
                $up_id_photo = up_file('tax_photo1');
                if ($up_id_photo['code'] == 1) {
                    $resp['code'] = 1;
                    $resp['msg'] = $up_id_photo['url'];
//                halt($up_id_photo['url']);
//                $this->result($up_id_photo['url'],1,'success','json');
                } elseif ($up_id_photo['code'] === 0) {
                    $resp['code'] = 0;
                    $resp['msg'] = $up_id_photo['msg'];
                }else{
                    $resp['code'] = 0;
                    $resp['msg'] = $up_id_photo['msg'];
                }
            }else{
                $resp['code'] = 0;
                $resp['imgurl'] = 'No File input.';
            }
        }
        return $resp;
    }
    //上传图片
    public function certification_upload(){
        if ($this->request->file('photo'))
        {
            $up_id_photo = up_file('photo');
            if ($up_id_photo['code'] == 1) {
                $res = Db::name('fun_member')->where('id', Cookie::get('user_id'))->update(['face_photo'=>$up_id_photo['url']]);
                if($res){
                    $resp['code'] = 1;
                    $resp['msg'] = $up_id_photo['url'];
                }
//                halt($up_id_photo['url']);
//                $this->result($up_id_photo['url'],1,'success','json');
            } elseif ($up_id_photo['code'] === 0) {
                $resp['code'] = 0;
                $resp['msg'] = $up_id_photo['msg'];
            }else{
                $resp['code'] = 0;
                $resp['msg'] = $up_id_photo['msg'];
            }
        }else{
            $resp['code'] = 0;
            $resp['imgurl'] = 'No File input.';
        }
        return $resp;
    }
    public function back_photo_upload(){
        if ($this->request->file('back_photo'))
        {
            $up_id_photo = up_file('back_photo');
            if ($up_id_photo['code'] == 1) {
                $res = Db::name('fun_member')->where('id', Cookie::get('user_id'))->update(['back_photo'=>$up_id_photo['url']]);
                if($res){
                    $resp['code'] = 1;
                    $resp['msg'] = $up_id_photo['url'];
                }
//                halt($up_id_photo['url']);
//                $this->result($up_id_photo['url'],1,'success','json');
            } elseif ($up_id_photo['code'] === 0) {
                $resp['code'] = 0;
                $resp['msg'] = $up_id_photo['msg'];
            }else{
                $resp['code'] = 0;
                $resp['msg'] = $up_id_photo['msg'];
            }
        }else{
            $resp['code'] = 0;
            $resp['imgurl'] = 'No File input.';
        }
        return $resp;
    }
    //实名认证提交(企业)
    public function company_post(){
        $param = $this->request->param();
        unset($param['license_status']);
        unset($param['tax_photo_status']);
        if( empty($param['company_name']) || empty($param['company_tax']) ){
            $data['code'] = 0;
            $data['msg'] = '请完整填写信息';
            return $data;
        }
        $user_id = Cookie::get('user_id');
        $company = Db::name('fun_company')->where('id', $user_id)->find();
        if(!empty($company)){
            $res = Db::name('fun_company')->where('id', $user_id)->update($param);
        } else {
            $param['status'] = 1;
            $param['user_id'] = $user_id;
            $res = Db::name('fun_company')->insert($param);
        }
        $data['code'] = 1;
        return $data;
    }
    //实名认证提交
    public function certification_post(){
        $param = $this->request->param();
        if( empty($param['real_name']) || empty($param['card_no']) ){
            $data['code'] = 0;
            $data['msg'] = '请完整填写信息';
            return $data;
        }
        $where['card_no'] = $param['card_no'];
        $where['delete_status'] = '-1';
        $info = Db::name('fun_member')->where($where)->find();
        if(!empty($info) ){
            $data['code'] = 0;
            $data['msg'] = '你曾经的账户有被拉黑的记录';
            return $data;
        }
        $user_id = Cookie::get('user_id');
        $param['status'] = 1;
        $res = Db::name('fun_member')->where('id', $user_id)->update($param);
        $data['code'] = 1;
        return $data;
    }
    //发送 模板消息
    public function sign($openid,$rank){
        $access_token = Db::name('access_token')->where(['id'=>1])->field('access_token')->find();
        $url = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=".$access_token['access_token'];
        $data = [];
        $member = Db::name('fun_member')->where('openid', $openid)->find();
        $data['touser'] = $openid;//接受者
        $data['template_id'] = 'rZsoC2gZOqGimOq0cswLHQGKMjhEX9TSrc94laNqCqw';//模板id

        $data['data']['first']=['value'=>'您好，您已成功升级。','color'=>'##173177'];
        $data['data']['keyword1']=['value'=>getRankName($rank),'color'=>'##173177'];
        $data['data']['keyword2']=['value'=>$member['nickname'] ,'color'=>'##173177'];
        $data['data']['keyword3']=['value'=>date('Y-m-d',time()),'color'=>'##173177'];

        $data1=json_encode($data);
        $re = curl_request($url,$data1,'json');
        $re1 = json_decode($re, true);
        if(!empty($re1)){
            if($re1['errcode']==0){
                return true;
            }else{
                return false;
            }
        }else{
            return false;
        }
    }

}
