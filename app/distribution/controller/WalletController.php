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

use cmf\controller\HomeBaseController;
use think\Db;
use think\Db\raw;
use think\Session;
use think\Cookie;

class WalletController extends HomeBaseController
{
    //我的钱包
    public function index()
    {
        $user_id = Cookie::get('user_id');
        $member = getMemberInfo($user_id);
        if(empty($member['mobile'])){
            header('Location: /distribution/index/login');exit();
        }
        $this->assign('member', $member);
        //当保证金提高的时候，之前的会员要补交保证金
        $rank_info = getRank($member['rank']);

        if(floatval($member['deposit']) < floatval($rank_info['deposit'])){
            $plus_deposit = $rank_info['deposit'] - floatval($member['deposit']);
            $plus_money = $member['recharge_money'] - $plus_deposit;
            if($plus_money >= 0){
                $member_update['recharge_money'] = $plus_money;
                $member_update['deposit'] = $rank_info['deposit'];
                Db::name('fun_member')->where('id',$user_id )->update($member_update);
                // 代理升级人的账单 和发送模板消息 start
                $member_bill_data = array(
                    'user_id'         => $user_id,
                    'rebate_id'       => 0,
                    'order_id'        => '',
                    'head_img'        => $member['head_img'],
                    'user_name'       => $member['nickname'],
                    'mobile'          => $member['mobile'],
                    'total_money'     => $plus_deposit,//从充值里面扣除
                    'profit'          => 0,//可提现
                    'bill_type'       => 3,//1:消费，2：获利 3:代理升级
                    'profit_type'     => 2,//获利的方式(1:消费，2：代理升级，3:分流客服的客户充值得到的返利--冻结资金)
                    'profit_from'     => 0,//1:购买走上级库存，2：返给董事 3：买断关系（更换上级）
                    'create_time'     => time(),
                    'content'         => $member['nickname'].',恭喜您已经成功升级为'.getRankName($member['rank']).',已从货款里扣去加盟费：'.$plus_deposit,
                    'statistics_time' => date('Y年m月', time())
                );
                Db::name('fun_bill')->insert($member_bill_data);
            }
        }
        elseif(floatval($member['deposit']) > floatval($rank_info['deposit'])){
            $plus_deposit = floatval($member['deposit']) - $rank_info['deposit'];
            $plus_m = $member['fanli_money'] + $plus_deposit;
            $member_update['fanli_money'] = $plus_m;
            $member_update['deposit'] = $rank_info['deposit'];
            Db::name('fun_member')->where('id',$user_id )->update($member_update);
            $member_bill_data = array(
                'user_id'         => $user_id,
                'rebate_id'       => 0,
                'order_id'        => '',
                'head_img'        => $member['head_img'],
                'user_name'       => $member['nickname'],
                'mobile'          => $member['mobile'],
                'total_money'     => $plus_deposit,//从充值里面扣除
                'profit'          => 0,//可提现
                'bill_type'       => 3,//1:消费，2：获利 3:代理升级
                'profit_type'     => 2,//获利的方式(1:消费，2：代理升级，3:分流客服的客户充值得到的返利--冻结资金)
                'profit_from'     => 0,//1:购买走上级库存，2：返给董事 3：买断关系（更换上级）
                'create_time'     => time(),
                'content'         => '公司推出了优惠，减少加盟费，多出的钱('.$plus_deposit.'),已返到可提现',
                'statistics_time' => date('Y年m月', time())
            );
            Db::name('fun_bill')->insert($member_bill_data);
        }

        //提现的明细
        $where['userid'] = $user_id;
        $where['post_status'] = ['in','0,1,2'];
        $withdrawal = Db::name('fun_withdrawal')->where($where)->order('create_time', 'desc')->select()->toArray();
        $this->assign('withdrawal', $withdrawal);
        return $this->fetch(':wallet/index');
    }
    //加盟费
    public function deposit()
    {
        $user_id = Cookie::get('user_id');
        if(empty($user_id)){
            header('Location: /distribution/index/login');exit();
        }
        $member = getMemberInfo($user_id);
        $rank_info = getRank($member['rank']);
        $money = floatval($rank_info['deposit']) - floatval($member['deposit']);
        if($money <= 0 ){
            header('Location: /distribution/index/index');exit();
        }
        $this->assign('user_id', $user_id);
        $this->assign('money', $money);
        return $this->fetch(':wallet/deposit');
    }
    //判断加盟费是否交齐
    public function check_deposit(){
        $user_id = Cookie::get('user_id');
        if(empty($user_id)){
            header('Location: /distribution/index/login');exit();
        }
        $member = getMemberInfo($user_id);
        $rank_info = getRank($member['rank']);
        //当保证金小于系统规定的保证金时
        if($member['deposit'] < $rank_info['deposit'] ){
            $data['code'] = 1;
            $data['msg'] = '请补交加盟费';
        }
        else {
            $data['code'] = 0;
            $data['msg'] = '加盟费已交齐';
        }
        return $data;
    }
    //充值
    public function recharge()
    {
        if(empty(Cookie::get('user_id'))){
            header('Location: /distribution/index/login');exit();
        }
        $this->assign('user_id', Cookie::get('user_id'));
        return $this->fetch(':wallet/recharge');
    }
    //最低充值额
    public function mini_recharge(){
        $user_id = Cookie::get('user_id');
        $money = $this->request->param('money', '0' ,'float');
        if(!$money){
            $data['code'] = 0;
            $data['msg'] = '请求错误';
            return $data;
        }
        $member = getMemberInfo($user_id);
        if(!empty($member)){
            $rank = getRank($member['rank']);
            //最低充值额
            if($rank['mini_recharge']){
                //当输入的钱小于最低充值额
                if($money < $rank['mini_recharge']){
                    $data['code'] = 0;
                    $data['msg'] = '最低充值为'.$rank['mini_recharge'];
                } else {
                    $data['code'] = 1;
                }
            } else {
                $data['code'] = 1;
            }
            return $data;
        }
    }
    //提现
    public function withdrawal()
    {
        if(empty(Cookie::get('user_id'))){
            header('Location: /distribution/index/login');exit();
        }
        $user_id = Cookie::get('user_id');
        $member = Db::name('fun_member')->where('id', $user_id)->find();
        $this->assign('member', $member);

        //提现的明细
        $where['userid'] = $user_id;
        $where['post_status'] = ['in','0,1,2'];
        $withdrawal = Db::name('fun_withdrawal')->where($where)->order('create_time', 'desc')->select()->toArray();
        $this->assign('withdrawal', $withdrawal);
        return $this->fetch(':wallet/withdrawal');
    }
    //填写提现信息
    public function withdrawal_recharge(){
        if(empty(Cookie::get('user_id'))){
            header('Location: /distribution/index/login');exit();
        }
        $user_id = Cookie::get('user_id');
        $where['userid'] = $user_id;
        $where['post_status'] = 0;//未审核
        $withdrawal = Db::name('fun_withdrawal')->where($where)->find();
        if(!empty($withdrawal)){
            echo "<script>alert('您还有提现未审核的信息');window.history.go(-1)</script>";exit();
        }
        $member = getMemberInfo($user_id);
        $this->assign('member', $member);
        $agent_setting = cmf_get_option('agent_setting');
        $this->assign('agent_setting', $agent_setting);
        return $this->fetch(':wallet/withdrawal_recharge');
    }
    //提现信息提交
    public function withdrawal_recharge_post(){
        if($this->request->isPost()){
            $user_id = Cookie::get('user_id');
            $member = getMemberInfo($user_id);
            $param = $this->request->param();
            if(empty($param['money']) || $param['money'] < 0){
                echo "<script>alert('转到货款的钱不正确');window.history.go(-1)</script>";exit();
            }
            $update = array(
                'fanli_money'=> $member['fanli_money'] - $param['money'],
                'recharge_money'=> $member['recharge_money'] + $param['money'],
            );
            $res = Db::name('fun_member')->where('id', $user_id)->update($update);
            if($res){
                $data['userid'] = $user_id;
                $data['user_name'] = $member['real_name'];
                $data['mobile'] = $member['mobile'];
                $data['money'] = $param['money'];
                $data['post_status'] = 1;
                $data['create_time'] = time();
                $data['message'] = '体现方式：从可提现到货款';
                $data['type'] = 2;
                Db::name('fun_withdrawal')->insert($data);
            }
            echo "<script>alert('可提现转到货款成功');window.location.href = '/distribution/wallet/index'</script>";exit();
        }
    }

    //填写提现信息
    public function withdrawal_info(){
        if(empty(Cookie::get('user_id'))){
            header('Location: /distribution/index/login');exit();
        }
        $user_id = Cookie::get('user_id');
        $where['userid'] = $user_id;
        $where['post_status'] = 0;//未审核
        $withdrawal = Db::name('fun_withdrawal')->where($where)->find();
        if(!empty($withdrawal)){
            echo "<script>alert('您还有提现未审核的信息');window.history.go(-1)</script>";exit();
        }

        $member = getMemberInfo($user_id);
        $this->assign('member', $member);
        $agent_setting = cmf_get_option('agent_setting');
        $this->assign('agent_setting', $agent_setting);
        return $this->fetch(':wallet/withdrawal_info');
    }
    //提现信息提交
    public function withdrawal_post(){
        if($this->request->isPost()){
            $user_id = Cookie::get('user_id');
            $param = $this->request->param();
            if( empty($param['user_name']) || empty($param['mobile']) || empty($param['wechat_num']) || empty($param['money'])){
                echo "<script>alert('请完整填写信息');window.history.go(-1)</script>";exit();
            }
            //获取提现的金额
            $agent_setting = cmf_get_option('agent_setting');

            //当输入金额小于后台设置的可提现金额
            $member = getMemberInfo($user_id);
            $agent_money = $agent_setting['money'];
            if( empty($member['fanli_money']) || $agent_money > floatval($param['money']) || $agent_money > floatval($member['fanli_money'])){
                echo "<script>alert('未达到提现金额');window.history.go(-1)</script>";exit();
            }
            if($member['fanli_money'] < $param['money']){
                echo "<script>alert('余额不足');window.history.go(-1)</script>";exit();
            }
            $data = $param;
            $data['userid'] = Cookie::get('user_id');
            $data['create_time'] = time();
            $res = Db::name('fun_withdrawal')->insert($data);
            if($res){
                Db::name('fun_member')->where('id', $user_id)->setDec('fanli_money', $param['money']);
                echo "<script>alert('提交成功');window.location.href = '/distribution/wallet/index'</script>";exit();
            }
        }
    }
    //提现信息提交--废弃
    public function withdrawal_post_old(){
        if($this->request->isPost()){
            //判断是否有正在提现
            $wh['userid'] = Cookie::get('user_id');
            $wh['post_status'] = 0;
            $info = Db::name('fun_withdrawal')->where($wh)->find();
            if($info){
                echo "<script>alert('您还有尚未处理的提现信息,请耐心等候');window.history.go(-1)</script>";exit();
            }
            $param = $this->request->param();
            $member = Db::name('fun_member')->where('id', Cookie::get('user_id'))->find();
            if(empty($member['fanli_money']) || $member['fanli_money'] < $param['money']){
                echo "<script>alert('余额不足');window.history.go(-1)</script>";exit();
            }
            if( empty($param['user_name']) || empty($param['mobile']) || empty($param['card_no']) || empty($param['money'])){
                echo "<script>alert('请完整填写信息');window.history.go(-1)</script>";exit();
            }
            $data = $param;
            $data['userid'] = Cookie::get('user_id');
            $data['create_time'] = time();
            $res = Db::name('fun_withdrawal')->insert($data);
            if($res){
                echo "<script>alert('提交成功');window.location.href = '/distribution/wallet/index'</script>";exit();
            }
        }
    }
    //钱包明细
    public function wallet_detail(){
        if(empty(Cookie::get('user_id'))){
            header('Location: /distribution/index/login');exit();
        }
        $type = $this->request->param('type', 1, 'intval');
        //充值
        if($type == 1){
            $where['userid'] = Cookie::get('user_id');
            $where['post_status'] = 1;
            $bill_month = Db::name('fun_recharge')->where($where)->group('statistics_time')->column('statistics_time');
            $result = [];
            foreach($bill_month as $k=>$v){
                $where['statistics_time'] = $v;
                $result[$v] = Db::name('fun_recharge')->where($where)
                    ->order(['create_time'=>'desc'])->select()->toArray();
            }
        }
        //消费的账单
        else{
            $where['b.user_id'] = Cookie::get('user_id');
            $where['b.bill_type'] = 1;
            $where[] = ['exp',Db::raw('b.orderlistnum is not null')];
            $join = [
                ['zoo_fun_goodsorder o', 'o.orderlistnum = b.orderlistnum']
            ];
            $where['o.pay_type'] = 1;//订单表(余额支付)
            $bill_month = Db::name('fun_bill')->alias('b')->join($join)->where($where)->group('b.statistics_time')->column('b.statistics_time');
            $result = [];
            foreach($bill_month as $k=>$v){
                $where['b.statistics_time'] = $v;
                $result[$v] = Db::name('fun_bill')->alias('b')->join($join)->where($where)
                    ->order(['b.create_time'=>'desc'])->select()->toArray();
            }
        }
        $this->assign('result', $result);
        $this->assign('type', $type);
        return $this->fetch(':wallet/wallet_detail');
    }

    //账单
    public function bill()
    {
        if(empty(Cookie::get('user_id'))){
            header('Location: /distribution/index/login');exit();
        }
        $param = $this->request->param();
        $bill_type = $this->request->param('bill_type', 1, 'intval');
        if( isset($param['statistics_time']) ){
            $where['statistics_time'] = $param['statistics_time'];
        }

        $where['b.bill_type'] = $bill_type;
        $where['b.user_id'] = Cookie::get('user_id');
        $join = [];
        if($bill_type == 3){
            $where[] = ['exp',Db::raw('b.order_id is not null')];
            $join = [
                ['zoo_fun_recharge r', 'r.out_trade_no = b.order_id']
            ];
        }
        elseif($bill_type == 1) {
            $where[] = ['exp',Db::raw('b.orderlistnum is not null')];
            $join = [
                ['zoo_fun_goodsorder o', 'o.orderlistnum = b.orderlistnum']
            ];
        }

        $bill_month = Db::name('fun_bill')->alias('b')->join($join)->where($where)->group('b.statistics_time')->column('b.statistics_time');
        $result = [];
        foreach($bill_month as $k=>$v){
            $where['b.statistics_time'] = $v;
            $result[$v] = Db::name('fun_bill')->alias('b')->join($join)->where($where)
                ->order(['b.create_time'=>'desc'])->select()->toArray();

        }

        $this->assign('result', $result);
        $this->assign('bill_type', $bill_type);
        return $this->fetch(':wallet/bill');
    }

}
