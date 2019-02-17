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
use pay\Pay;
use think\Session;
use think\Cookie;
use think\Request;
use app\distribution\controller\PersonalController;


class OrderController extends HomeBaseController
{
    public function _initialize()
    {
        $this->access_token();
    }
    //订单页面
    public function index()
    {
        $user_id = Cookie::get('user_id');
        $member = getMemberInfo($user_id);
        if(empty($member['mobile'])){
            header('Location: /distribution/index/login');exit();
        }
        $where['userid'] = $user_id;
        $where['delstate'] = ['neq', 'true'];
        $order_list = Db::name('fun_goodsorder')->field('id, orderamount, posttime, post_status, orderlistnum,pay_type,postid,post_name')->where($where)->order('posttime desc')->select()->toArray();
        if($order_list){
            foreach($order_list as $k=>$val){
                $shop_list = Db::name('fun_goodsshopcart')->field('id, gid, gtitle, gpicurl, price, num')->where('gorderlistnum', $val['orderlistnum'])
                    ->select()->toArray();
                $sum_num = 0;
                foreach($shop_list as $shop_k =>$v){
                    $sum_num += $v['num'];
                }
                $order_list[$k]['shop_list'] = $shop_list;
                $order_list[$k]['sum_num'] = $sum_num;
            }
        }
        $wh['userid'] = $user_id;
        $wh['delstate'] = ['neq', 'true'];
        $wh['post_status'] = ['egt', 1];
        $sum_money = Db::name('fun_goodsorder')->where($wh)->sum('orderamount');
        $other = cmf_get_option('other_setting');
        $agent_label = 0;
        $update_money = 0;
        //vip升为总代
        if($sum_money >= $other['vip_agent'] && $member['rank'] == 2){
//            Db::name('fun_member')->where('id', $user_id)->update(['rank'=>3]);
            $agent_label = 1;
            $update_money = $other['vip_agent'];
        }
        //总代升为银钻
        if($sum_money >= $other['agent_founder'] && $member['rank'] == 3){
//            Db::name('fun_member')->where('id', $user_id)->update(['rank'=>4]);
            $agent_label = 2;
            $update_money = $other['agent_founder'];
        }
        //银钻升级金钻
        if($sum_money >= $other['agent_founder'] && $member['rank'] == 4){
//            Db::name('fun_member')->where('id', $user_id)->update(['rank'=>4]);
            $agent_label = 3;
            $update_money = $other['agent_founder'];
        }
        //可以升级的标志
        $this->assign('agent_label', $agent_label);
        $this->assign('update_money', $update_money);
        $this->assign('order_list', $order_list);
        return $this->fetch(':order/index');
    }
    public function getOrderDetail(){
        $param = $this->request->param();
        if(empty($param['order_id'])){
            echo "<script>alert('参数错误');history.go(-1);</script>";exit;
        }
        $goods_order = Db::name('fun_goodsorder')->where('id', $param['order_id'])->find();
        $shop_list = Db::name('fun_goodsshopcart')->field('id, gid, gtitle, gpicurl, price, num')->where('gorderlistnum', $goods_order['orderlistnum'])
            ->select()->toArray();
        $express_list = Db::name('fun_express')->where('post_status', 1)->select()->toArray();
        //购买者信息
        $member = getMemberInfo($goods_order['userid']);
        $order_list['member'] = $member;
        $order_list['member']['rank_name'] = getRankName($member['rank']);
        $order_list['shop_list'] = $shop_list;//订单的购物车信息
        $order_list['goods_order'] = $goods_order;//订单信息
        $order_list['express_list'] = $express_list;//快递列表
        return $order_list;
    }
    //下级订单需要发货
    public function order_need_post(){
        if(empty(session('openid'))){
            header("Location: /distribution/index/authority");exit();
//            $this->huoquop();
        }
        $user_id = Cookie::get('user_id');
        $member = getMemberInfo($user_id);
        if(empty($member['mobile'])){
            header('Location: /distribution/index/login');exit();
        }
        $where['post_id'] = $user_id;
        $where['delstate'] = ['neq', 'true'];
        $order_list = Db::name('fun_goodsorder')->field('id, orderamount, posttime, post_status, orderlistnum,pay_type,postid,post_name,userid')->where($where)->order('posttime desc')->select()->toArray();
        if($order_list){
            foreach($order_list as $k=>$val){
                $shop_list = Db::name('fun_goodsshopcart')->field('id, gid, gtitle, gpicurl, price, num')->where('gorderlistnum', $val['orderlistnum'])
                    ->select()->toArray();
                $sum_num = 0;
                foreach($shop_list as $shop_k =>$v){
                    $sum_num += $v['num'];
                }
                $order_list[$k]['shop_list'] = $shop_list;
                $order_list[$k]['sum_num'] = $sum_num;
            }
        }
        $this->assign('order_list', $order_list);
        return $this->fetch(':order/order_need_post');
    }
    //上级给下级发货
    public function pre_delivery(){
        $param = $this->request->param();
        //发货方式
        if(!empty($param['order_id'] )){
            if($param['post_type'] == '自己发货'){
                $post_id = Db::name('fun_goodsorder')->where('id', $param['order_id'])->value('post_id');
                $olnum = Db::name('fun_goodsorder')->where('id', $param['order_id'])->value('orderlistnum');
                $update['postid'] = $param['postid'];//货号
                $update['post_name'] = $param['post_name'];//快递公司
                $update['reabte_id'] = $post_id;//为了计算团队奖
                $update['post_status'] = 2;
                $res = Db::name('fun_goodsorder')->where('id', $param['order_id'])->update($update);
                if($res){
                    //返利的逻辑开始
                    $this->rebate_logic($olnum);
                    echo "<script>alert('发货成功');window.location.href='/distribution/order/order_need_post'</script>";exit();
                }
            }
            elseif($param['post_type'] == '自提'){
                $post_id = Db::name('fun_goodsorder')->where('id', $param['order_id'])->value('post_id');
                $olnum = Db::name('fun_goodsorder')->where('id', $param['order_id'])->value('orderlistnum');
                $update['post_type'] = 2;//发货方式：自提
                $update['reabte_id'] = $post_id;//为了计算团队奖
                $update['post_status'] = 2;
                $res = Db::name('fun_goodsorder')->where('id', $param['order_id'])->update($update);
                if($res){
                    //返利的逻辑开始
                    $this->rebate_logic($olnum);
                    echo "<script>alert('发货成功');window.location.href='/distribution/order/order_need_post'</script>";exit();
                }
            }
            else {
                $goods_order = Db::name('fun_goodsorder')->where('id', $param['order_id'])->find();
                $company = Db::name('fun_member')->where('rank', 20)->find();
                $update['post_id'] = $company['id'];
                $this->buySuccessMsg($company['openid'], $goods_order);
//                $this->buySuccessMsg('oHCfwwFy5Tp5ceuSjpKukgdEa7PY', $goods_order);
                $res = Db::name('fun_goodsorder')->where('id', $param['order_id'])->update($update);
                if($res){
                    echo "<script>alert('公司代发货提醒成功');window.location.href='/distribution/order/order_need_post'</script>";exit();
                }
            }

        }
    }
    //购买
    public function purchase ()
    {
        $user_id = Cookie::get('user_id');
        if(empty($user_id)){
            header('Location: /distribution/index/login');exit();
        }
        $member = getMemberInfo($user_id);
        if($member['recharge_money'] < 0){
            echo "<script>alert('请先补齐货款');window.location.href = '/distribution/wallet/index'</script>";exit();
        }
        if($member['delete_status'] == -1){
            echo "<script>alert('你已经被系统拉黑了，不能购买');window.location.href='/distribution/personal/index'</script>";exit();
        }
        //总代以上必须要实名认证
        $rank = getRank($member['rank']);
        if($rank['need_certification'] == 1){
            //实名认证是否通过
            if($member['status'] != 2){
                echo "<script>alert('请先通过实名认证');window.location.href='/distribution/personal/index'</script>";exit();
            }
        }
        $param = $this->request->param();

        //判断是否有地址
        $address = Db::name('fun_address')->where('userid', $user_id)->find();
        session('from', $param['from']);
        //立即购买
        if ($param['from'] == 'nowbuy') {
            $price = isset($param['price']) ? $param['price'] : 0;
            $good_num = isset($param['good_num']) ? $param['good_num'] : 0;
            $sum_price = $good_num * $price;
            //一次性购买的钱，等级不同有限制
            if(floatval($sum_price) < $rank['one_time_buy']){
                echo "<script>alert('你一次性购买的钱数须到{$rank['one_time_buy']}');history.go(-1);</script>";exit();
            }
            //收货地址返回到购买页面
            if(isset($param['address_id']) ){
                $goods = Db::name('goods')->where('id', session('gid'))->find();
                $price = session('price');
                $good_num = session('good_num');
                $address = Db::name('fun_address')->where('id', $param['address_id'])->find();
            } else {
                if(empty($param['gid']) || empty($param['price']) || empty($param['good_num'])){
                    echo "<script>alert('参数错误');history.go(-1)</script>";exit();
                }
                //商品详情
                $gid = isset($param['gid']) ? $param['gid'] : 0;
                $goods = Db::name('goods')->where('id', $gid)->find();
                $price = isset($param['price']) ? $param['price'] : 0;
                $good_num = isset($param['good_num']) ? $param['good_num'] : 0;
                session('good_num', $good_num);
                session('price', $price);
                session('gid', $gid);
            }
            $sum_price = $good_num * $price;
            $sum_num = $good_num;
            $this->assign('goods', $goods);
            $this->assign('sum_num', $good_num);
            $this->assign('price', $price);
        }
        //购物车
        else if($param['from'] == 'shop_cart') {
            $wh['uid'] = $user_id;
            $wh['Status'] = 'cart';
            $result = Db::name('fun_goodsshopcart')->where($wh)->select()->toArray();
            $this->assign('result', $result);
            $sum_price = 0;
            $sum_num = 0;
            if($result){
                foreach($result as $k=>$val){
                    $sum_price += $result[$k]['price'] * $result[$k]['num'];
                    $sum_num += $result[$k]['num'];
                }
            }
            //一次性购买的商品的钱数，等级不同有限制
            if($sum_price < $rank['one_time_buy']){
                echo "<script>alert('你一次性购买的钱数须到{$rank['one_time_buy']}');history.go(-1);</script>";exit();
            }
            if( isset($param['address_id']) ){
                $address = Db::name('fun_address')->where('id', $param['address_id'])->find();
            }
        }
        $this->assign('sum_price', $sum_price);
        $this->assign("hide_sum_price", base64_encode(sprintf("%01.2f", $sum_price)));
        $this->assign('sum_num', $sum_num);
        $this->assign('address', $address);
        $this->assign('from', $param['from']);
        return $this->fetch(':order/purchase');
    }
    //生成订单
    public function purchase_post(){
        if( $this->request->isAjax() ){
            $param = $this->request->param();

            $address_id = $param['address_id'];
            if( empty($address_id) ){
                $data['code'] = 0;
                $data['msg'] = '请先选择地址';
                return $data;
            }
            if($param['from'] != "shop_cart" && $param['from'] != "nowbuy"){
                $data['code'] = 0;
                $data['msg'] = '提交方式错误';
                return $data;
            }
            //立即购买
            if( $param['from'] == "nowbuy" ){
                if(empty($param['gid']) || empty($param['price']) || empty($param['goods_num'])){
                    $data['code'] = 0;
                    $data['msg'] = '参数错误';
                    return json($data);
                }
            }
            $total_price = base64_decode($param['hide_sum_price']);
            //当前所选的地址
            $addr_info = Db::name('fun_address')->where('id', $address_id)->find();
            $user_id = Cookie::get('user_id');
            $member_info = getMemberInfo($user_id);//会员信息
            $order_data['username'] = $member_info['nickname'];//昵称
            $order_data['rec_name'] = $addr_info['rec_name'];//收货人姓名
            $order_data['mobile'] = $addr_info['mobile'];//收货人手机号
            $order_data['cur_address'] = $addr_info['road_address'].$addr_info['cur_address'];//详细地址
            $order_data['orderamount'] = $total_price;//解密后的价格
            $order_data['buyremark'] = $param['buyremark'];//购物备注
            $order_data['posttime'] = time();//下单时间
            $order_data['post_status'] = 0;//未支付的状态

            //订单号
            $orderlistnum = time() + rand(1000,9999);
            $orderlistnum .= $user_id;
            $order_data['orderlistnum'] = $orderlistnum;//订单号
            $order_data['userid'] = $user_id;//用户id
            $order_data['statistics_time'] = date('Y年m月', time());//用户id
            //银钻下单，是要选择发货人
            if($member_info['rank'] == 4){
                if($member_info['referee_id'] ){
                    $order_data['post_id'] = $member_info['referee_id'];//未支付的状态
                }
            }
            //金钻
            else if ($member_info['rank'] == 9){
                $company_id = Db::name('fun_member')->where('rank', 20)->value('id');
                $order_data['post_id'] = $company_id;//未支付的状态
            }
            $order_status = Db::name('fun_goodsorder')->insert($order_data);
            if($order_status){
                //立即购买
                if( $param['from'] == "nowbuy" ){
                    $gid = isset($param['gid']) ? $param['gid'] : 0;
                    $price = isset($param['price']) ? $param['price'] : 0;
                    $good_num = isset($param['goods_num']) ? $param['goods_num'] : 0;
                    $good_info = Db::name('goods')->where('id', $gid)->find();
                    $shop_data['gid'] = $gid;
                    $shop_data['gtitle'] = $good_info['post_title'];
                    $shop_data['gpicurl'] = $good_info['thumbnail'];
                    $shop_data['price'] = $price;//商品原价*折扣
                    $shop_data['num'] = $good_num;
                    $shop_data['uid'] = $user_id;
                    $shop_data['gorderlistnum'] = $orderlistnum;
                    $shop_data['buyprice'] = $good_info['price'];
                    $shop_data['Status'] = 'order';

                    $shop_data['edit_date'] = date('Y-m-d', time());
                    $res = Db::name('fun_goodsshopcart')->insert($shop_data);
                }
                //购物车
                elseif( $param['from'] == "shop_cart" ){
                    $where['Status'] = 'cart';
                    $where['uid'] = $user_id;
                    $result = Db::name('fun_goodsshopcart')->where($where)->select()->toArray();
                    if($result){
                        foreach($result as $k=>$val){
                            Db::name('fun_goodsshopcart')->where('id', $val['id'])->update(['gorderlistnum'=>$orderlistnum, 'Status'=>'order']);
                        }
                    }
                }
                $data['code'] = 1;
                $data['olnum'] = $orderlistnum;
                return $data;
            }
            else {
                $data['code'] = 0;
                $data['msg'] = '订单未生成';
                return $data;
            }
        } else{
            $data['code'] = 0;
            $data['msg'] = '网络错误';
            return $data;
        }
    }

    //付款页面
    public function pay(){
        $param = $this->request->param();
        //调用支付
        if($this->request->isPost()){
            $user_id = Cookie::get('user_id');
            // 生成订单号，年月日时分秒+6位用户id+3位随机数
            $order_id = date('YmdHis', time()) . sprintf("%06d", $user_id) . rand(100, 999);

            // 获取当前域名
            $domain = 'http://'.$_SERVER['SERVER_NAME'];
            //购买商品
            if($param['pay_type'] == 'purchase'){
                $olnum = $param['olnum'];
                $order_info = Db::name('fun_goodsorder')->where('orderlistnum', $olnum)->find();

                //更新订单表 (支付成功的回调会用到)
                $data2['paynum'] = $order_id;
                Db::name('fun_goodsorder')->where(['orderlistnum' => $olnum])->update($data2);
                //微信支付
                if($param['pay_mode'] == 2){
                    $notify_url = $domain.'/distribution/order/pay_notify';
                    $status = $this->weixin_pay($order_id, $order_info['orderamount'], $notify_url);
                    if($status == 'SUCCESS'){
                        return $this->fetch(':order/pay');
                    } else {
                        return $this->error('微信支付失败');
                    }
                }
                //货到付款
                else if($param['pay_mode'] == 3){
                    $post_status = Db::name('fun_goodsorder')->where('orderlistnum', $olnum)->value('post_status');
                    if ( $post_status != 0){
                        return xml(['return_code' => 'SUCCESS', 'return_msg' => 'OK'], 200, [], ['root_node' => 'xml']);
                    }
                    // 更新订单表
                    $data1['post_status'] = 1;
                    $data1['pay_type'] = 3;//支付方式--货到付款
                    //更新订单表
                    Db::name('fun_goodsorder')->where('orderlistnum', $olnum)->update($data1);
                    echo "<script>alert('订单提交成功，等待发货');window.location.href = '/distribution/index'</script>";exit();

                }
                //余额支付
                else {
                    $post_status = Db::name('fun_goodsorder')->where('orderlistnum', $olnum)->value('post_status');
                    if ( $post_status != 0){
                        return xml(['return_code' => 'SUCCESS', 'return_msg' => 'OK'], 200, [], ['root_node' => 'xml']);
                    }
                    // 更新订单表
                    $data1['post_status'] = 1;
                    $data1['pay_type'] = 1;
                    //更新订单表
                    Db::name('fun_goodsorder')->where('orderlistnum', $olnum)->update($data1);
                    $good_order = Db::name('fun_goodsorder')->where('orderlistnum', $olnum)->find();
                    $member = Db::name('fun_member')->where('id', $user_id)->find();
                    //更新 当前消费的人 的余额
                    $balance = $member['recharge_money'] - $order_info['orderamount'];
                    $res = Db::name('fun_member')->where('id', $user_id)->update(['recharge_money'=>$balance]);
                    if($res){
                        //自己消费的账单
                        $goods_shop = Db::name('fun_goodsshopcart')->where(['gorderlistnum'=>$olnum])->select()->toArray();
                        $content1 = count($goods_shop) == 1 ?  '购买'.$goods_shop[0]['gtitle'] : '购买'.$goods_shop[0]['gtitle'].'等商品';
                        $member_bill = array(
                            'user_id'         =>$member['id'],
                            'orderlistnum'    => $olnum,
                            'head_img'        => $member['head_img'],
                            'user_name'       => $member['nickname'],
                            'mobile'          => $member['mobile'],
                            'total_money'     => $order_info['orderamount'],
                            'bill_type'       => 1,//1:消费，2：获利
                            'profit_type'       => 1,//获利的方式(1:消费，2：代理升级)
                            'create_time'     => time(),
                            'content'         =>$content1,
                            'statistics_time' => date('Y年m月', time())
                        );
                        Db::name('fun_bill')->insert($member_bill);
                        //是否有推荐人
                        if(!empty($member['referee_id'])){
                            $parent_m = Db::name('fun_member')->where('id', $member['referee_id'])->find();
                            //发送模板消息
                            $this->buySuccessMsg($parent_m['openid'], $good_order);

                        }
                        header('Location: /distribution/order/succ/olnum/'.$olnum);exit();
//                        echo "<script>alert('支付成功');window.location.href = '/distribution/index'</script>";exit();
                    }

                }
            }
            //充值
            elseif($param['pay_type'] == 'recharge'){
                if( empty($param['money']) || !is_numeric($param['money'])){
                    echo "<script>alert('余额不足');window.history.go(-1)</script>";exit();
                }
                $member = Db::name('fun_member')->where('id', Cookie::get('user_id'))->find();
                $recharge_data['userid'] = Cookie::get('user_id');
                $recharge_data['user_name'] = $member['nickname'];
                $recharge_data['mobile'] = $member['mobile'];
                $recharge_data['head_img'] = $member['head_img'];
                $recharge_data['money'] = $param['money'];
                $recharge_data['out_trade_no'] = $order_id;//支付号
                $recharge_data['create_time'] = time();
                $recharge_data['from'] = 1;
                $recharge_data['statistics_time'] = date('Y年m月', time());
                $res = Db::name('fun_recharge')->insert($recharge_data);
                if($res){
                    //调用微信支付
                    $notify_url = $domain.'/distribution/order/recharge_pay_notify';
                    $status = $this->weixin_pay($order_id, $param['money'], $notify_url);
                    if($status == 'SUCCESS'){
                        return $this->fetch(':order/pay');
                    } else {
                        return $this->error('微信支付失败');
                    }
                }

            }
            //代理升级
            elseif($param['pay_type'] == 'rank_update'){
                if(empty($param['money'])){
                    $data['code'] = 0;
                    $data['msg'] = '网络错误';
                    return $data;
                }
                $user_id = Cookie::get('user_id');
                $member = Db::name('fun_member')->where('id', $user_id)->find();
                $agent_list = Db::name('fun_agent')->where('status', 1)->field('id, money')->select()->toArray();
                foreach($agent_list as $k=>$v){
                    if(floatval($v['money']) == floatval($param['money'])){
                        $rank = $v['id'];
                        break;
                    }
                }
                if($member['rank'] >= $rank){
                    echo "<script>alert('您的等级大于您购买的等级(或已是当前等级)，请勿重复购买');history.go(-1);</script>";
                }
                $recharge_data['userid'] = $user_id;
                $recharge_data['user_name'] = $member['nickname'];
                $recharge_data['mobile'] = $member['mobile'];
                $recharge_data['head_img'] = $member['head_img'];
                $recharge_data['money'] = $param['money'];
                $recharge_data['out_trade_no'] = $order_id;//支付号
                $recharge_data['create_time'] = time();
                $recharge_data['from'] = 2;//代理升级
                $recharge_data['statistics_time'] = date('Y年m月', time());
                $res = Db::name('fun_recharge')->insert($recharge_data);
                if($res){
                    //调用微信支付
                    $notify_url = $domain.'/distribution/order/rank_update_notify';
                    $status = $this->weixin_pay($order_id, $param['money'], $notify_url);
                    if($status == 'SUCCESS'){
                        return $this->fetch(':order/pay');
                    } else {
                        return $this->error('微信支付失败');
                    }
                }
            }
            //补足保证金
            elseif($param['pay_type'] == 'deposit'){
                $member = Db::name('fun_member')->where('id', Cookie::get('user_id'))->find();
                $recharge_data['userid'] = Cookie::get('user_id');
                $recharge_data['user_name'] = $member['nickname'];
                $recharge_data['mobile'] = $member['mobile'];
                $recharge_data['head_img'] = $member['head_img'];
                $recharge_data['money'] = $param['money'];
                $recharge_data['out_trade_no'] = $order_id;//支付号
                $recharge_data['create_time'] = time();
                $recharge_data['from'] = 3;
                $recharge_data['statistics_time'] = date('Y年m月', time());
                $res = Db::name('fun_recharge')->insert($recharge_data);
                if($res){
                    //调用微信支付
                    $notify_url = $domain.'/distribution/order/deposit_pay_notify';
                    $status = $this->weixin_pay($order_id, $param['money'], $notify_url);
                    if($status == 'SUCCESS'){
                        return $this->fetch(':order/pay');
                    } else {
                        return $this->error('微信支付失败');
                    }
                }
            }

        } else {
            return $this->error('请求方式错误');
        }
    }
    //货到付款
    public function cash_on_delivery(){
        $param = $this->request->param();
        if($param['order_id']){
            $this->error('参数错误');
        }

        // 更新订单表
        $data2['is_pay'] = 1;
        $data2['pay_type'] = 3;
        //更新订单表
        Db::name('fun_goodsorder')->where(['id' => $param['order_id']])->update($data2);

        $good_order = Db::name('fun_goodsorder')->where(['id' => $param['order_id']])->find();
        $member = Db::name('fun_member')->where('id', $good_order['userid'])->find();
        //自己消费的账单
        $goods_shop = Db::name('fun_goodsshopcart')->where(['gorderlistnum'=>$good_order['orderlistnum']])->select()->toArray();
        $content1 = count($goods_shop) == 1 ?  '购买'.$goods_shop[0]['gtitle'] : '购买'.$goods_shop[0]['gtitle'].'等商品';
        $member_bill = array(
            'user_id'         =>$member['id'],
            'orderlistnum'    => $good_order['orderlistnum'],
            'head_img'        => $member['head_img'],
            'user_name'       => $member['nickname'],
            'mobile'          => $member['mobile'],
            'total_money'     => $good_order['orderamount'],
            'bill_type'       => 1,
            'profit_type'       => 1,
            'create_time'     => time(),
            'content'         =>$content1,
            'statistics_time' => date('Y年m月', time())
        );
        Db::name('fun_bill')->insert($member_bill);
        //返利逻辑 start
        //首先判断是否有上级
        if(!empty($member['referee_id'])){
            $parent_m = Db::name('fun_member')->where('id', $member['referee_id'])->find();
            //判断用户是否被拉黑
            if($parent_m['delete_status'] == 0){
                $other_setting = cmf_get_option('other_setting');
                $agent_type = $other_setting['agent_type'];
                //所有代理只允许走上级库存，同级别的库存不走
                if($agent_type == 2){
                    if($member['rank'] <= $parent_m['rank']){
                        $this->multiplex2($parent_m, $member, $good_order);
                    }
                } else{
                    //只有比上级的等级低的时候才会返利（如果自己比上级等级高的时候，在升级的时候一次性结清）
                    if($member['rank'] < $parent_m['rank']){
                        //普通会员
                        if($member['rank'] == 0){
                            $agent_rebate = cmf_get_option('agent_setting');
                            $this->multiplex($parent_m, $member, $agent_rebate, $good_order);
                        }
                        //是代理级别的时候
                        else{
                            //获得代理消费返佣比例
                            $agent_rebate = Db::name('fun_agent')->where('id', $member['rank'])->find();
                            $this->multiplex($parent_m, $member, $agent_rebate, $good_order);
                        }
                    }
                }
            }
        }
        //返利逻辑 end
        $this->success('保存成功', url('AdminGoodsorder/index'));
    }
    /**
     * 微信支付的复用代码
     * @param  int $parent_m            推荐人id
     * @param  float $orderamount       金额
     * @return array                发送成功status为1，发送失败msg为失败信息
     */
    public function weixin_pay($order_id, $orderamount, $notify_url){
        // 获取微信支付参数
        $config = config('weixin');
        $openid = session('openid');

        $config['app_id'] = $config['appid'];
        unset($config['appsecret'],$config['appid']);
        // 支付成功发送通知的页面
        $config['notify_url'] = $notify_url;

        // 支付信息
        $order = array(
            'out_trade_no' => $order_id,//支付号
            'body' => cmf_get_option('site_info')['site_name'],
            'total_fee' => $orderamount * 100,
//            'total_fee' => 1,
            'openid' => $openid,
        );
        // 调用支付接口
        $res = Pay::weixin($config)->jsapi($order);
//        dump($res);
        file_put_contents('notify.txt', json_encode($res).PHP_EOL, FILE_APPEND);
        if ($res['return_code'] == 'SUCCESS' && $res['result_code'] == 'SUCCESS') {

            $time = time();
            $random = random();
            $sign = $this->sign($res, $time, $random);

            $jsApiParameters = [
                'appId' => config('weixin.appid'),
                'timeStamp' => "$time",
                'nonceStr' => $random,
                'package' => 'prepay_id=' . $res['prepay_id'],
                'signType' => 'MD5',
                'paySign' => $sign
            ];
            file_put_contents('notify.txt', json_encode($jsApiParameters).PHP_EOL, FILE_APPEND);
            $this->assign('jsApiParameters', json_encode($jsApiParameters));
//        return $this->fetch(':order/pay');
            return $res['return_code'];
        } else {
            return $res['return_code'];
//        return $this->error('微信支付失败');
        }
    }
    //代理升级的支付异步通知页面
    public function rank_update_notify(){
        // 获取参数
        $config = config('weixin');
        $config['app_id'] = $config['appid'];
        unset($config['appid'],$config['appsecret']);
        $config['notify_url'] = '';

        try {
            // 验证参数，如果未通过验证会抛出exception
            $data = Pay::weixin($config)->verifySign();
            file_put_contents('notify.txt', date('Y-m-d H:i:s', time()) );
            file_put_contents('notify.txt', json_encode($data) . PHP_EOL, FILE_APPEND);
            // 如果此订单状态不为0说明已经处理过回调，不再重复处理
            $post_status = Db::name('fun_recharge')->where(['out_trade_no' => $data['out_trade_no']])->value('post_status');
            if ($post_status != 0) {
                return xml(['return_code' => 'SUCCESS', 'return_msg' => 'OK'], 200, [], ['root_node' => 'xml']);
            }
            // 更新充值表
            $data2['post_status'] = $data['result_code'] == 'SUCCESS' ? 1 : 0;
            // 更新充值表
            Db::name('fun_recharge')->where(['out_trade_no' => $data['out_trade_no']])->update($data2);
            $recharge = Db::name('fun_recharge')->where(['out_trade_no' => $data['out_trade_no']])->find();
            Db::name('fun_member')->where('id', $recharge['userid'])->setInc('recharge_money', $recharge['money']);
            // 更新代理升级的等级 start
            $agent_list = Db::name('fun_agent')->where('status', 1)->field('id, money')->select()->toArray();
            foreach($agent_list as $k=>$v){
                if(floatval($v['money']) == floatval($recharge['money'])){
                    $rank = $v['id'];
                    break;
                }
            }

            $user_id = $recharge['userid'];
            $other_setting = cmf_get_option('other_setting');
            //当前充值人
            $member = Db::name('fun_member')->where('id', $user_id)->find();
            //走上级库存()
            if($other_setting['agent_type'] == 2){
                if(!empty($rank)){
                    //更新自身的等级和扣除保证金 start
                    $member_update['rank'] = $rank;
                    $rank_info = getRank($rank);
                    $plus_money = $member['recharge_money'] - $rank_info['deposit'] + floatval($member['deposit']);
                    if($plus_money >= 0 ){
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
                        'order_id'        => $data['out_trade_no'],
                        'head_img'        => $member['head_img'],
                        'user_name'       => $member['nickname'],
                        'mobile'          => $member['mobile'],
                        'total_money'     => $recharge['money'],//从充值里面扣除
                        'profit'          => 0,//可提现
                        'bill_type'       => 3,//1:消费，2：获利 3:代理升级
                        'profit_type'     => 2,//获利的方式(1:消费，2：代理升级，3:分流客服的客户充值得到的返利--冻结资金)
                        'profit_from'     => 0,//1:购买走上级库存，2：返给董事 3：买断关系（更换上级）
                        'create_time'     => time(),
                        'content'         => $member['nickname'].',恭喜您已经成功升级为'.getRankName($member['rank']).',已从货款里扣去加盟费：'.$plus_money,
                        'statistics_time' => date('Y年m月', time())
                    );
                    Db::name('fun_bill')->insert($member_bill_data);
                    //发送模板消息
                    file_put_contents('notify.txt', '$member[\'openid\']:'.$member['openid'] . PHP_EOL, FILE_APPEND);
                    $this->template_msg($member['openid'], $rank);

                    // 代理升级人的账单 和发送模板消息 end
                    if(!empty($member['referee_id'])){
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
                            //推荐联创（买断）
                            else if($rank == 4){
                                $agent_money = $other_setting['recom_founder'];
                                //新上级
                                $recharge_money = $new_parent['recharge_money'] - $agent_money;
                                //成为过去的上级
                                $fanli_money = $parent_m['recharge_money'] + $agent_money;

                                //更新一下推荐联合创始人的人数
//                                $rank4_count = $parent_m['rank4_count'] + 1;
//                                Db::name('fun_member')->where('id', $parent_m['id'])->update(['rank4_count'=>$rank4_count]);
//                                //升为董事需要推荐联创的人数 并且 他的等级是联创的话就可以升为董事
//                                if($rank4_count >= $other_setting['uptoDirectorCount'] && $parent_m['rank'] == 4 ){
//                                    //更新等级为董事，并且扣除保证金
//                                    $parent_update['rank'] = 9;
//                                    $director = getRank(9);
//                                    $parent_update['recharge_money'] = $parent_m['recharge_money'] - $director['deposit'] + floatval($parent_m['deposit']);
//                                    $parent_update['deposit'] = $director['deposit'];
//                                    Db::name('fun_member')->where('id', $parent_m['id'])->update($parent_update);
//                                    //发送模板消息
//                                    $this->template_msg($parent_m['openid'], 9);
//
//                                }
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
                                'order_id'        => $data['out_trade_no'],
                                'head_img'        => $new_parent['head_img'],
                                'user_name'       => $new_parent['nickname'],
                                'mobile'          => $new_parent['mobile'],
                                'total_money'     => $agent_money,//从充值里面扣除
                                'profit'          => 0,//可提现
                                'bill_type'       => 3,//1:消费，2：获利 3:代理升级
                                'profit_type'     => 2,//获利的方式(1:消费，2：代理升级，3:分流客服的客户充值得到的返利--冻结资金)
                                'profit_from'     => 3,//1:购买走上级库存，2：返给董事 3：买断关系（更换上级）
                                'create_time'     => time(),
                                'content'         => $new_parent['nickname'].'支出了'.$agent_money.'元买断'.$member['nickname'].'和'.$parent_m['nickname'].'之间的推荐关系('.
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
                                'order_id'        => $data['out_trade_no'],
                                'head_img'        => $new_parent['head_img'],
                                'user_name'       => $new_parent['nickname'],
                                'mobile'          => $new_parent['mobile'],
                                'total_money'     => 0,//从充值里面扣除
                                'profit'          => $agent_money,//可提现
                                'bill_type'       => 3,//1:消费，2：获利 3:代理升级
                                'profit_type'     => 2,//获利的方式(1:消费，2：代理升级，3:分流客服的客户充值得到的返利--冻结资金)
                                'profit_from'     => 3,//1:购买走上级库存，2：返给董事 3：买断关系（更换上级）
                                'create_time'     => time(),
                                'content'         => $parent_m['nickname'].'得到了一笔推荐奖--'.$agent_money. ',是因为'.$parent_m['nickname'].'和'.$member['nickname'].
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
                }

            }
            else {
                if(!empty($rank)){
                    Db::name('fun_member')->where('id',$user_id )->update(['rank'=>$rank]);
                }

                //首先判断是否有上级
                if(!empty($member['referee_id'])){
                    $parent_m = Db::name('fun_member')->where('id', $member['referee_id'])->find();
                    file_put_contents('notify.txt', '$parent_m'.$parent_m['rank'] . PHP_EOL, FILE_APPEND);
                    file_put_contents('notify.txt', '$member'.$member['rank'] . PHP_EOL, FILE_APPEND);
                    //自己比上级等级高的时候，在升级的时候一次性结清
                    if($member['rank'] >= $parent_m['rank']){
                        //获得代理消费返佣比例
                        $agent_rebate = Db::name('fun_agent')->where('id', $member['rank'])->find();
                        file_put_contents('notify.txt', '$agent_rebate'.json_encode($agent_rebate) . PHP_EOL, FILE_APPEND);
                        $this->rank_multiplex($parent_m, $member, $agent_rebate, $recharge);

                    }
                }
            }

            // 所有处理结果存到日志
            trace('微信回调' . json_encode($data), 'info');
            // 微信回调成功后必须返回此xml，不能加其他内容
            return xml(['return_code' => 'SUCCESS', 'return_msg' => 'OK'], 200, [], ['root_node' => 'xml']);
        } catch (\Exception $e) {
            trace('微信回调' . json_encode($e->getMessage()), 'notice');
            return xml(['return_code' => 'FAIL', 'return_msg' => 'failed'], 200, [], ['root_node' => 'xml']);
        }
    }

    //充值的支付异步通知页面
    public function recharge_pay_notify(){
        // 获取参数
        $config = config('weixin');
        $config['app_id'] = $config['appid'];
        unset($config['appid'],$config['appsecret']);
        $config['notify_url'] = '';

        try {
            // 验证参数，如果未通过验证会抛出exception
            $data = Pay::weixin($config)->verifySign();
            file_put_contents('notify.txt', date('Y-m-d H:i:s', time()) . PHP_EOL, FILE_APPEND);
            file_put_contents('notify.txt', json_encode($data) . PHP_EOL, FILE_APPEND);
            // 如果此订单状态不为0说明已经处理过回调，不再重复处理
            $post_status = Db::name('fun_recharge')->where(['out_trade_no' => $data['out_trade_no']])->value('post_status');
            if ($post_status != 0) {
                return xml(['return_code' => 'SUCCESS', 'return_msg' => 'OK'], 200, [], ['root_node' => 'xml']);
            }
            // 更新充值表
            $data2['post_status'] = $data['result_code'] == 'SUCCESS' ? 1 : 0;
            // 更新充值表
            Db::name('fun_recharge')->where(['out_trade_no' => $data['out_trade_no']])->update($data2);

            $recharge = Db::name('fun_recharge')->where(['out_trade_no' => $data['out_trade_no']])->find();
            Db::name('fun_member')->where('id', $recharge['userid'])->setInc('recharge_money', $recharge['money']);
            //判断是否是分流客服 start


            // 所有处理结果存到日志
            trace('微信回调' . json_encode($data), 'info');
            // 微信回调成功后必须返回此xml，不能加其他内容
            return xml(['return_code' => 'SUCCESS', 'return_msg' => 'OK'], 200, [], ['root_node' => 'xml']);
        } catch (\Exception $e) {
            trace('微信回调' . json_encode($e->getMessage()), 'notice');
            return xml(['return_code' => 'FAIL', 'return_msg' => 'failed'], 200, [], ['root_node' => 'xml']);
        }
    }
    //补足保证金的支付回调
    public function deposit_pay_notify(){
        // 获取参数
        $config = config('weixin');
        $config['app_id'] = $config['appid'];
        unset($config['appid'],$config['appsecret']);
        $config['notify_url'] = '';

        try {
            // 验证参数，如果未通过验证会抛出exception
            $data = Pay::weixin($config)->verifySign();
            file_put_contents('notify.txt', date('Y-m-d H:i:s', time()) . PHP_EOL, FILE_APPEND);
            file_put_contents('notify.txt', json_encode($data) . PHP_EOL, FILE_APPEND);
            // 如果此订单状态不为0说明已经处理过回调，不再重复处理
            $post_status = Db::name('fun_recharge')->where(['out_trade_no' => $data['out_trade_no']])->value('post_status');
            if ($post_status != 0) {
                return xml(['return_code' => 'SUCCESS', 'return_msg' => 'OK'], 200, [], ['root_node' => 'xml']);
            }
            // 更新充值表
            $data2['post_status'] = $data['result_code'] == 'SUCCESS' ? 1 : 0;
            // 更新充值表
            Db::name('fun_recharge')->where(['out_trade_no' => $data['out_trade_no']])->update($data2);

            $recharge = Db::name('fun_recharge')->where(['out_trade_no' => $data['out_trade_no']])->find();
            $user_id = $recharge['userid'];
            $member = getMemberInfo($user_id);
            Db::name('fun_member')->where('id', $user_id)->update(['deposit'=> $member['deposit'] + $recharge['money']]);
            //获得推荐奖金
            $bill_data = array(
                'user_id'         => $user_id,
                'rebate_id'       => 0,
                'order_id'        => $data['out_trade_no'],
                'head_img'        => $member['head_img'],
                'user_name'       => $member['nickname'],
                'mobile'          => $member['mobile'],
                'total_money'     => $recharge['money'],//从充值里面扣除
                'profit'          => 0,//可提现
                'bill_type'       => 3,//1:消费，2：获利 3:代理升级
                'profit_type'     => 2,//获利的方式(1:消费，2：代理升级，
                'profit_from'     => 3,//1:购买走上级库存，2：返给董事 3：买断关系（更换上级）
                'create_time'     => time(),
                'content'         => '补足保证金：'.$recharge['money'],
                'statistics_time' => date('Y年m月', time()),
                'rank' => $member['rank'],
            );
            Db::name('fun_bill')->insert($bill_data);

            // 所有处理结果存到日志
            trace('微信回调' . json_encode($data), 'info');
            // 微信回调成功后必须返回此xml，不能加其他内容
            return xml(['return_code' => 'SUCCESS', 'return_msg' => 'OK'], 200, [], ['root_node' => 'xml']);
        } catch (\Exception $e) {
            trace('微信回调' . json_encode($e->getMessage()), 'notice');
            return xml(['return_code' => 'FAIL', 'return_msg' => 'failed'], 200, [], ['root_node' => 'xml']);
        }
    }
    // 支付异步通知页面（购买商品）
    public function pay_notify()
    {
        // 获取参数
        $config = config('weixin');
        $config['app_id'] = $config['appid'];
        unset($config['appid'],$config['appsecret']);
        $config['notify_url'] = '';

        try {
            // 验证参数，如果未通过验证会抛出exception
            $data = Pay::weixin($config)->verifySign();
            file_put_contents('notify.txt', date('Y-m-d H:i:s',time()).PHP_EOL,FILE_APPEND);
            file_put_contents('notify.txt', json_encode($data).PHP_EOL,FILE_APPEND);
            // 如果此订单状态不为0说明已经处理过回调，不再重复处理
            $post_status = Db::name('fun_goodsorder')->where(['paynum' => $data['out_trade_no']])->value('post_status');
            if ( $post_status != 0){
                return xml(['return_code' => 'SUCCESS', 'return_msg' => 'OK'], 200, [], ['root_node' => 'xml']);
            }
            // 更新订单表
            $data2['post_status'] = $data['result_code'] == 'SUCCESS' ? 1 : 0;
            $data2['pay_type'] = 2;
            //更新订单表
            Db::name('fun_goodsorder')->where(['paynum' => $data['out_trade_no']])->update($data2);

            $good_order = Db::name('fun_goodsorder')->where(['paynum' => $data['out_trade_no']])->find();
            $member = Db::name('fun_member')->where('id', $good_order['userid'])->find();
            //当推荐人不为空时
            if(!empty($member['referee_id'])){
                $recom_member = getMemberInfo($member['referee_id']);
                file_put_contents('notify.txt', '$recom_member:'.json_encode($recom_member) . PHP_EOL, FILE_APPEND );
                $this->buySuccessMsg($recom_member['openid'], $good_order);
            }
            //自己消费的账单
            $goods_shop = Db::name('fun_goodsshopcart')->where(['gorderlistnum'=>$good_order['orderlistnum']])->select()->toArray();
            $content1 = count($goods_shop) == 1 ?  '购买'.$goods_shop[0]['gtitle'] : '购买'.$goods_shop[0]['gtitle'].'等商品';
            $member_bill = array(
                'user_id'         =>$member['id'],
                'orderlistnum'    => $good_order['orderlistnum'],
                'head_img'        => $member['head_img'],
                'user_name'       => $member['nickname'],
                'mobile'          => $member['mobile'],
                'total_money'     => $good_order['orderamount'],
                'bill_type'       => 1,
                'profit_type'       => 1,
                'create_time'     => time(),
                'content'         =>$content1,
                'statistics_time' => date('Y年m月', time())
            );
            Db::name('fun_bill')->insert($member_bill);
            //返利逻辑 start

            //返利逻辑 end
            // 所有处理结果存到日志
            trace('微信回调' . json_encode($data), 'info');

            // 微信回调成功后必须返回此xml，不能加其他内容
            return xml(['return_code' => 'SUCCESS', 'return_msg' => 'OK'], 200, [], ['root_node' => 'xml']);

        } catch (\Exception $e) {
            trace('微信回调' . json_encode($e->getMessage()), 'notice');
            return xml(['return_code' => 'FAIL', 'return_msg' => 'failed'], 200, [], ['root_node' => 'xml']);
        }

    }
    //购买商品（消耗上级库存）
    public function rebate_logic($olnum){
        if(empty($olnum)){
            echo "<script>alert('参数错误');history.go(-1);</script>"; exit();
        }
        $good_order = Db::name('fun_goodsorder')->where(['orderlistnum' => $olnum])->find();
        $member = Db::name('fun_member')->where('id', $good_order['userid'])->find();
        //首先判断是否有上级
        if(!empty($member['referee_id'])){
            $parent_m = Db::name('fun_member')->where('id', $member['referee_id'])->find();
            //判断用户是否被拉黑
            if($parent_m['delete_status'] == 0){
                $other_setting = cmf_get_option('other_setting');
                $agent_type = $other_setting['agent_type'];
                //所有代理只允许走上级库存，同级别的库存不走
                if($agent_type == 2){
                    if($member['rank'] <= $parent_m['rank']){
                        $this->multiplex2($parent_m, $member, $good_order);
                    }
                } else{
                    //只有比上级的等级低的时候才会返利（如果自己比上级等级高的时候，在升级的时候一次性结清）
                    if($member['rank'] < $parent_m['rank']){
                        //普通会员
                        if($member['rank'] == 0){
                            $agent_rebate = cmf_get_option('agent_setting');
                            $this->multiplex($parent_m, $member, $agent_rebate, $good_order);
                        }
                        //是代理级别的时候
                        else{
                            //获得代理消费返佣比例
                            $agent_rebate = Db::name('fun_agent')->where('id', $member['rank'])->find();
                            $this->multiplex($parent_m, $member, $agent_rebate, $good_order);
                        }
                    }
                }
            }
        }
    }
    /**
     * 消费返利的复用代码
     * @param  array $parent_m            推荐人信息
     * @param  array $member            消费人的信息
     * @param  array $agent_rebate       返利比例
     * @param  array $order_info        订单信息
     * @return array                发送成功status为1，发送失败msg为失败信息
     */
    public function multiplex($parent_m, $member, $agent_rebate, $order_info){
        //订单金额
        $orderamount = $order_info['orderamount'];
        //一级返利比例不为空
        if($agent_rebate['one_bonus_sharing']){

            $money = floatval($agent_rebate['one_bonus_sharing']/100) * $orderamount;
            $parent_m['content'] = $member['nickname'].'|-|'.$member['mobile'].'购买商品产生的费用让您获得一级返利:'.$money;
            $fanli_money = $parent_m['fanli_money']+$money;
            $res = Db::name('fun_member')->where('id', $parent_m['id'])->update(['fanli_money'=>$fanli_money]);
            if($res){
                //账单
                $bill_data = array(
                    'user_id'         =>$parent_m['id'],
                    'rebate_id'       => $member['id'],
                    'orderlistnum'     => $order_info['orderlistnum'],
                    'head_img'        => $member['head_img'],
                    'user_name'       => $member['nickname'],
                    'mobile'          => $member['mobile'],
                    'total_money'     => $orderamount,
                    'profit'          => $money,
                    'bill_type'       => 2,
                    'profit_type'     => 1,
                    'create_time'     => time(),
                    'content'         => $parent_m['content'],
                    'statistics_time' => date('Y年m月', time())
                );
                $result = Db::name('fun_bill')->insert($bill_data);
            }
        }
        //推挤人上级
        if( !empty($member['pre_referee_id']) ){
            $pre_referee_m = Db::name('fun_member')->where('id', $member['pre_referee_id'])->find();
            //推荐人的等级 必须比推荐人上级等级低才能获取返利
            if($parent_m['rank'] < $pre_referee_m['rank']){
                //消费的二级返利比例不为空
                if($agent_rebate['two_bonus_sharing']){
                    $money = floatval($agent_rebate['two_bonus_sharing']/100) * $orderamount;
                    $pre_referee_m['content'] = $member['nickname'].'|-|'.$member['mobile'].'购买商品产生的费用让您获得二级返利:'.$money;
                    $rebate_money = floatval($pre_referee_m['fanli_money']) + $money;
                    $res = Db::name('fun_member')->where('id', $pre_referee_m['id'])->update(['fanli_money'=>$rebate_money]);
                    if($res){
                        //账单
                        $bill_data = array(
                            'user_id'         =>$pre_referee_m['id'],
                            'orderlistnum'    => $order_info['orderlistnum'],
                            'rebate_id'       => $member['id'],
                            'head_img'        => $member['head_img'],
                            'user_name'       => $member['nickname'],
                            'mobile'          => $member['mobile'],
                            'total_money'     => $orderamount,
                            'profit'          => $money,
                            'bill_type'       => 2,
                            'create_time'     => time(),
                            'content'         => $pre_referee_m['content'],
                            'statistics_time' => date('Y年m月', time())
                        );
                        $result = Db::name('fun_bill')->insert($bill_data);
                    }
                }
            }
        }
        return $result;
    }
    /**
     * 消费（走上级库存）的复用代码
     * @param  array $parent_m           推荐人信息
     * @param  array $member            消费人的信息
     * @param  array $order_info        订单信息
     * @return array                发送成功status为1，发送失败msg为失败信息
     */
    public function multiplex2($parent_m, $member, $order_info){
        //订单号
        $orderlistnum = $order_info['orderlistnum'];
        //订单金额
        $orderamount = $order_info['orderamount'];
        //当会员的级别是董事(平级不走库存)
        if($member['rank'] == 9){
            //上级的等级也是董事
            if($parent_m['rank'] == 9){
                //上级董事返利
                $this->director_profit_one($parent_m, $member,  $orderlistnum, $orderamount);
                //上级的推荐人不为空
                if( !empty($parent_m['referee_id'])){
                    $pre_parent = getMemberInfo($parent_m['referee_id']);
                    if($pre_parent['rank'] == 9){
                        $this->director_profit_two($pre_parent, $member,  $orderlistnum, $orderamount);
                    }
                }
            }
        }
        else{
            //上级的返利开始
            $this->rebate_data($parent_m, $member, $orderlistnum, $orderamount, $member);
            //当上级级别是9(董事)
            if($parent_m['rank'] == 9){
                if($parent_m['referee_id']) {
                    $pre_parent = getMemberInfo($parent_m['referee_id']);
                    if($pre_parent['rank'] == 9){
                        //上上级董事返利 5*n(董事的补助每盒5元)
                        $this->director_profit_one($pre_parent, $member, $orderlistnum, $orderamount);
                        //继续寻找董事
                        if (!empty($pre_parent['referee_id'])) {
                            $pre_parent_director = getMemberInfo($pre_parent['referee_id']);
                            if ($pre_parent_director['rank'] == 9) {
                                //董事的补助每盒3元
                                $this->director_profit_two($pre_parent_director, $member, $orderlistnum, $orderamount);
                            }
                        }
                    }
                }
            }
            else{
                //上上级的返利 start
                if($parent_m['referee_id']){
                    $pre_parent = getMemberInfo($parent_m['referee_id']);
                    if($parent_m['rank'] < $pre_parent['rank']) {
                        $this->rebate_data($pre_parent, $parent_m, $orderlistnum, $orderamount, $member);
                    }
                    //上上级是董事（9）
                    if($pre_parent['rank'] == 9){
                        //董事返利
                        if($pre_parent['referee_id']) {
                            $director = getMemberInfo($pre_parent['referee_id']);
                            //上上级董事返利 5*n(董事的补助每盒5元)
                            $this->director_profit_one($director, $member, $orderlistnum, $orderamount);
                            //继续寻找董事
                            if (!empty($director['referee_id'])) {
                                $pre_director = getMemberInfo($director['referee_id']);
                                if ($pre_director['rank'] == 9) {
                                    //董事的补助每盒3元
                                    $this->director_profit_two($pre_director, $member, $orderlistnum, $orderamount);
                                }
                            }
                        }
                    }
                    else {
                        //查找是否有董事
                        $member_list = Db::name('fun_member')->field('id, referee_id, rank')->select()->toArray();
                        $director_id = get_top_parent($member_list, $parent_m['referee_id']);
                        //当有董事的时候
                        if($director_id){
                            //董事减库存
                            $director_info = getMemberInfo($director_id);
                            $this->rebate_data($director_info, $pre_parent, $orderlistnum, $orderamount, $member);
                            if($director_info['referee_id']){
                                $pre_director = getMemberInfo($director_info['referee_id']);
                                if($pre_director['rank'] == 9){
                                    //董事的上级董事获得每盒5元补助
                                    $this->director_profit_one($pre_director, $member,  $orderlistnum, $orderamount);
                                    //董事的上上级董事不为空
                                    if( !empty($pre_director['referee_id'])){
                                        $pre_pre_director = getMemberInfo($pre_director['referee_id']);
                                        if($pre_pre_director['rank'] == 9){
                                            //董事的上上级董事获得每盒3元补助
                                            $this->director_profit_two($pre_pre_director, $member,  $orderlistnum, $orderamount);
                                        }

                                    }
                                }
                            }
                        }
                    }
                }
                //上上级的返利 end
            }

        }
    }

    /**
     * 董事的返利的复用代码
     * @param  array $parent_m           收益人
     * @param  array $member            消费人的信息
     * @param  int $orderlistnum       订单号
     * @param  int $orderamount        消费的金额
     * @return array                发送成功status为1，发送失败msg为失败信息
     */
    public function director_profit_one($parent_m, $member,  $orderlistnum, $orderamount){
        $where['gorderlistnum'] = $orderlistnum;
        $where['Status'] = 'order';
        $sum_num = Db::name('fun_goodsshopcart')->where($where)->sum('num');
        //返5*n(董事级别)
        $other_setting = cmf_get_option('other_setting');
        $parent_profit = $other_setting['director_rebate_one'] * $sum_num;
        $update['fanli_money'] = $parent_m['fanli_money'] + $parent_profit;
        Db::name('fun_member')->where('id',$parent_m['id'])->update($update);
        $parent_m['content'] = getRankName($member['rank']).$member['nickname'].'购买商品（'.$orderamount.'--'.$sum_num.'件商品)，'
            .getRankName($parent_m['rank']).$parent_m['nickname'].'获得金额：'.$parent_profit;
        $result = $this->bill_data($parent_m, $member, $orderlistnum, $orderamount, $parent_profit, 2);
        return $result;
    }
    /**
     * 董事的返利的复用代码
     * @param  array $parent_m           收益人
     * @param  array $member            消费人的信息
     * @param  int $orderlistnum       订单号
     * @param  int $orderamount        消费的金额
     * @return array                发送成功status为1，发送失败msg为失败信息
     */
    public function director_profit_two($parent_m, $member,  $orderlistnum, $orderamount){
        $where['gorderlistnum'] = $orderlistnum;
        $where['Status'] = 'order';
        $sum_num = Db::name('fun_goodsshopcart')->where($where)->sum('num');
        //返3*n(董事级别)
        //上上级是董事时
        $other_setting = cmf_get_option('other_setting');
        //返3*n
        $pre_parent_profit = $other_setting['director_rebate_two'] * $sum_num;
        $update['fanli_money'] = floatval($parent_m['fanli_money']) + floatval($pre_parent_profit);
        Db::name('fun_member')->where('id', $parent_m['id'])->update($update);

        $parent_m['content'] = getRankName($member['rank']).$member['nickname'].'购买商品（'.$orderamount.'--'.$sum_num.'件商品)'
            .getRankName($parent_m['rank']).$parent_m['nickname'].'获得金额：'.$pre_parent_profit;
        $result = $this->bill_data($parent_m, $member, $orderlistnum, $orderamount, $pre_parent_profit, 2);
        return $result;
    }
    /**
     * 走库存的返利的复用代码
     * @param  array $parent_m           收益人
     * @param  array $member            下级
     * @param  int $orderlistnum       订单号
     * @param  int $orderamount        购买商品的money
     * @param  array  $buy_member       消费人的信息
     * @return array                发送成功status为1，发送失败msg为失败信息
     */
    public function rebate_data($parent_m, $member, $orderlistnum, $orderamount, $buy_member){
        //上级id
        $parent_id = $parent_m['id'];
        $pre_price = getUserOrderamount($orderlistnum, $parent_id);//上级消费商品花的钱
        $my_price = getUserOrderamount($orderlistnum, $member['id']);//下级消费商品花的钱
        $good_order = getOrderInfo($orderlistnum);
        //发货人是上级（返钱的模式不一样了）
        if($good_order['post_id'] == $parent_id ){
            $pre_update['fanli_money'] = $parent_m['fanli_money'] + $my_price;
            //账单
            $parent_m['content'] = getRankName($buy_member['rank']).$buy_member['nickname'].'购买商品（'.$orderamount.'元），'
                .getRankName($parent_m['rank']).getMemberNickname($parent_id).'直接发货，可提现增加'.$orderamount;
        } else {
            //当上级的货款小于上级消费商品花的钱时，就在返利里面扣
            if($parent_m['recharge_money'] < $pre_price){
                $w_money = $my_price - $pre_price;
                $pre_update['fanli_money'] = $parent_m['fanli_money'] + $w_money;
                //账单
                $parent_m['content'] = getRankName($buy_member['rank']).$buy_member['nickname'].'购买商品（'.$orderamount.'元），'
                    .getRankName($parent_m['rank']).getMemberNickname($parent_id).'但是因为货款不足，直接在可提现扣钱，可提现增加'.$w_money;
            }
            else {
                $pre_update['recharge_money'] = $parent_m['recharge_money'] - $pre_price;
                $pre_update['fanli_money'] = $parent_m['fanli_money'] + $my_price;
                //账单
                $parent_m['content'] = getRankName($buy_member['rank']).$buy_member['nickname'].'购买商品（'.$orderamount.'元），'
                    .getRankName($parent_m['rank']).getMemberNickname($parent_id).'货款减少'.$pre_price.'，可提现增加'.$my_price;
            }
        }
        Db::name('fun_member')->where('id', $parent_id)->update($pre_update);
        $result = $this->bill_data($parent_m, $buy_member, $orderlistnum, $orderamount, $pre_price, 1);
        return $result;
    }
    /**
     * 走库存的账单的复用代码
     * @param  array $parent_m           收益人
     * @param  array $member            消费人的信息
     * @param  int $orderlistnum       订单号
     * @param  float $pre_price        不打折的价格
     * @param  float $orderamount        不打折的价格
     * @param  int $profit_from        1:购买走上级库存，2：返给董事 3：买断关系（更换上级）
     * @return array                发送成功status为1，发送失败msg为失败信息
     */
    public function bill_data($parent_m, $member, $orderlistnum, $pre_price, $orderamount, $profit_from){
        $bill_data = array(
            'user_id'         => $parent_m['id'],
            'rebate_id'       => $member['id'],
            'orderlistnum'    => $orderlistnum,
            'head_img'        => $member['head_img'],
            'user_name'       => $member['nickname'],
            'mobile'          => $member['mobile'],
            'total_money'     => $pre_price,//从充值里面扣除
            'profit'          => $orderamount,//可提现
            'bill_type'       => 2,//1:消费，2：获利
            'profit_type'     => 1,//获利的方式(1:消费，2：代理升级，3:分流客服的客户充值得到的返利--冻结资金)
            'profit_from'     => $profit_from,//1:购买走上级库存，2：返给董事 3：买断关系（更换上级）
            'create_time'     => time(),
            'content'         => $parent_m['content'],
            'statistics_time' => date('Y年m月', time())
        );
        $result = Db::name('fun_bill')->insert($bill_data);
        return $result;
    }

    /**
     * 充值返利的复用代码
     * @param  int $parent_m            推荐人id
     * @param  array $member            消费人的信息
     * @param  array $agent_rebate       返利比例
     * @param  array $recharge        充值信息
     * @return array                发送成功status为1，发送失败msg为失败信息
     */
    public function rank_multiplex($parent_m, $member, $agent_rebate, $recharge){
        //订单金额
        $orderamount = $recharge['money'];
//        file_put_contents('notify.txt', '$orderamount'. $orderamount . PHP_EOL, FILE_APPEND);
        //一级返利比例不为空
        if($agent_rebate['one_recharge_rebate']){
//            file_put_contents('notify.txt', '$agent_rebate[\'one_recharge_rebate\']'.$agent_rebate['one_recharge_rebate'] . PHP_EOL, FILE_APPEND);
            $money = floatval($agent_rebate['one_recharge_rebate']/100) * $orderamount;
            $parent_m['content'] = $member['nickname'].'|-|'.$member['mobile'].'充值'.$orderamount.',您获得充值一级返利比例:'.$money;
            $fanli_money = $parent_m['fanli_money']+$money;
            $res = Db::name('fun_member')->where('id', $parent_m['id'])->update(['fanli_money'=>$fanli_money]);
            if($res){
                //账单
                $bill_data = array(
                    'user_id'         =>$parent_m['id'],
                    'rebate_id'       => $member['id'],
                    'order_id'        => $recharge['out_trade_no'],
                    'head_img'        => $member['head_img'],
                    'user_name'       => $member['nickname'],
                    'mobile'          => $member['mobile'],
                    'total_money'     => $orderamount,
                    'profit'          => $money,
                    'bill_type'       => 2,
                    'profit_type'     => 2,
                    'create_time'     => time(),
                    'content'         => $parent_m['content'],
                    'statistics_time' => date('Y年m月', time())
                );
                $result = Db::name('fun_bill')->insert($bill_data);
            }
        }
        //推挤人上级
        if( !empty($member['pre_referee_id']) ){
            $pre_referee_m = Db::name('fun_member')->where('id', $member['pre_referee_id'])->find();
            //推荐人的等级 必须比推荐人上级等级高才能获取返利
            if($parent_m['rank'] > $pre_referee_m['rank']){
                //代理升级的二级返利比例不为空
                if($agent_rebate['two_recharge_rebate']){
                    $money = floatval($agent_rebate['two_recharge_rebate']/100) * $orderamount;
                    $pre_referee_m['content'] = $member['nickname'].'|-|'.$member['mobile'].'充值'.$orderamount.',您获得充值级返利比例:'.$money;
                    $rebate_money = floatval($pre_referee_m['fanli_money']) + $money;
                    $res = Db::name('fun_member')->where('id', $pre_referee_m['id'])->update(['fanli_money'=>$rebate_money]);
                    if($res){
                        //账单
                        $bill_data = array(
                            'user_id'         =>$pre_referee_m['id'],
                            'rebate_id'       => $member['id'],
                            'order_id'        => $recharge['out_trade_no'],
                            'head_img'        => $member['head_img'],
                            'user_name'       => $member['nickname'],
                            'mobile'          => $member['mobile'],
                            'total_money'     => $orderamount,
                            'profit'          => $money,
                            'bill_type'       => 2,
                            'profit_type'     => 2,
                            'create_time'     => time(),
                            'content'         => $pre_referee_m['content'],
                            'statistics_time' => date('Y年m月', time())
                        );
                        $result = Db::name('fun_bill')->insert($bill_data);
                    }
                }
            }
        }
        return $result;
    }

//选择支付方式（余额、微信）
    public function ckpay(){
        $param = $this->request->param();
        if( empty($param['olnum']) ){
            $data['code'] = 0;
            $data['msg'] = '订单不正确';
            return $data;
        }
        $info = Db::name('fun_goodsorder')->where('orderlistnum', $param['olnum'])->find();
        $member = Db::name('fun_member')->where('id', Cookie::get('user_id'))->find();
        if($member['recharge_money'] < $info['orderamount']){
            $is_recharge = 0;
        } else {
            $is_recharge = 1;
        }

        //顾问和普通会员可以使用货到付款
        if($member['rank'] == 0 || $member['rank'] == 2)
            $COD = 1;
        else
            $COD = 0;

        $this->assign('COD', $COD);
        $this->assign('info', $info);
        $this->assign('member', $member);
        $this->assign('is_recharge', $is_recharge);
        $this->assign("olnum", $param['olnum']);
        return $this->fetch(':order/ckpay');
    }
// 微信计算签名
    private function sign($data, $time, $random)
    {
        $string = 'appId=' . $data['appid'] . '&nonceStr=' . $random . '&package=prepay_id=' . $data['prepay_id'] . '&signType=MD5&timeStamp=' . $time;
        //签名步骤二：在string后加入KEY
        $string = $string . "&key=" . config('weixin.key');
        //签名步骤三：MD5加密
        $string = md5($string);
        //签名步骤四：所有字符转为大写
        $result = strtoupper($string);
        return $result;
    }

    //购物车列表
    public function shop_list(){
        $user_id = Cookie::get('user_id');
        if(empty($user_id)){
            header('Location: /distribution/index/login');exit();
        }
        //购物车列表
        $where['Status'] = 'cart';
        $where['uid'] = $user_id;
        $result = Db::name('fun_goodsshopcart')->where($where)->select()->toArray();
        $sum_price = 0;
        $sum_num = 0;
        if($result){
            //用户信息
            $member = getMemberInfo($user_id);
            foreach($result as $k=>$val){
                $buy_price = getGoodsPrice($val['gid']);
                //获取用户等级价格
                $price = getGoodsRankPrice($val['gid'], $member['rank']);
                //如果现在的价格（价格*折扣）和加入购物车的价格（价格*折扣）不一致的话
                if($price != $val['price']){
                    Db::name('fun_goodsshopcart')->where('id', $val['id'])->update(['price'=>$price, 'buyprice'=>$buy_price]);
                    $result[$k]['price'] = $price;
                }
                $sum_price += $result[$k]['price'] * $result[$k]['num'];
                $sum_num += $result[$k]['num'];
            }
        }
        $this->assign('sum_num', $sum_num);
        $this->assign('sum_price', $sum_price);
        $this->assign('result', $result);
        return $this->fetch(':order/shop_list');
    }
//购物车列表 添加或减少商品
    public function deal_shop(){
        $user_id = Cookie::get('user_id');
        if(empty($user_id)){
            header('Location: /distribution/index/login');exit();
        }
        $param = $this->request->param();
        if(empty($param['gid']) || empty($param['shop_id']) ){
            $data['code'] = 0;
            $data['msg'] = '缺少参数';
        }
        //当商品数量为零的时候，从购物车里面删除该商品
        if($param['num'] == 0){
            Db::name('fun_goodsshopcart')->where('id', $param['shop_id'])->delete();
        } else {
            Db::name('fun_goodsshopcart')->where('id', $param['shop_id'])->update(['num'=> $param['num']]);
        }
        //购物车列表
        $where['Status'] = 'cart';
        $where['uid'] = $user_id;
        $result = Db::name('fun_goodsshopcart')->where($where)->select()->toArray();
        $sum_price = 0;
        $sum_num = 0;
        if($result){
            //用户信息
            $member = getMemberInfo($user_id);
            foreach($result as $k=>$val){
                $buy_price = getGoodsPrice($val['gid']);
                //获取用户等级价格
                $price = getGoodsRankPrice($val['gid'], $member['rank']);
                //如果现在的价格（价格*折扣）和加入购物车的价格（价格*折扣）不一致的话
                if($price != $val['price']){
                    Db::name('fun_goodsshopcart')->where('id', $val['id'])->update(['price'=>$price, 'buyprice'=>$buy_price]);
                    $result[$k]['price'] = $price;
                }
                $sum_price += $result[$k]['price'] * $result[$k]['num'];
                $sum_num += $result[$k]['num'];
            }
        }
        $data['code'] = 1;
        $data['sum_price'] = $sum_price;
        $data['sum_num'] = $sum_num;
        return $data;
    }

    //确认收货
    public function confirm_order(){
        $olnum = $this->request->param('olnum');
        if(empty($olnum)){
            echo "<script>alert('参数错误');window.location.href='/distribution/index'</script>";
            exit();
        }
        $res = Db::name('fun_goodsorder')->where('orderlistnum', $olnum)->update(['post_status'=>3]);
        if($res){
            echo "<script>alert('确认收货成功');window.location.href='/distribution/order/index'</script>";
            exit();
        }
    }
    //取消订单
    public function cancel_order(){
        $olnum = $this->request->param('olnum');
        if(empty($olnum)){
            echo "<script>alert('参数错误');window.location.href='/distribution/index'</script>";
            exit();
        }
        $res = Db::name('fun_goodsorder')->where('orderlistnum', $olnum)->update(['post_status'=>-1]);
        if($res){
            echo "<script>alert('取消订单成功');window.location.href='/distribution/order/index'</script>";
            exit();
        }
    }
    //余额支付的成功页面
    public function succ(){
        $olnum = $this->request->param('olnum');
        if(empty($olnum)){
            echo "<script>alert('参数错误');window.location.href='/distribution/index'</script>";
            exit();
        }
        $good_order = Db::name('fun_goodsorder')->where('orderlistnum', $olnum)->find();
        if(empty($good_order)){
            echo "<script>alert('参数错误');window.location.href='/distribution/index'</script>";
            exit();
        }
        $this->assign('goods_order', $good_order);
        return $this->fetch(':order/succ');
    }
    //判断当前人员是否实名认证和交齐保证金
    public function check_member(){
        $user_id = Cookie::get('user_id');
        if(empty($user_id)){
            header('Location: /distribution/index/login');exit();
        }
        $member = getMemberInfo($user_id);
        $rank_info = getRank($member['rank']);
        //判断是否要实名认证
        if($rank_info['need_certification']){
            if($member['status'] != 2){
                $data['code'] = -1;
                $data['msg'] = '需要实名认证';
                return $data;
            }
        }
        if($member['deposit'] < $rank_info['deposit']){
            $data['code'] = 0;
            $data['msg'] = '需要交保证金';
            return $data;
        }
        $data['code'] = 1;
        return $data;
    }
    /**

     * 发送 模板消息

     * @param varchar $openid 微信id

     * @param int $rank 等级级别

     */
    public function template_msg($openid,$rank){
        $this->access_token();
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
        file_put_contents('notify.txt', '$data:'.json_encode($data) . PHP_EOL, FILE_APPEND);
        $data1=json_encode($data);
        $re = curl_request($url,$data1,'json');
        $re1 = json_decode($re, true);
        if(!empty($re1)){
            if($re1['errcode']==0){
                return true;
            }
        }
    }
    /**
     * 发送购买成功给推荐人发模板消息
     * @param varchar $openid 推荐人的微信id
     * @param array $goods_order  订单信息
     * @param int $rank 等级级别
     */
    public function buySuccessMsg($openid, $goods_order){
        $this->access_token();
        $access_token = Db::name('access_token')->where(['id'=>1])->field('access_token')->find();
        $url = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=".$access_token['access_token'];
        $data = [];
        //消费者的信息
        $buy_member = Db::name('fun_member')->where('id', $goods_order['userid'])->find();
        $shop_cart = Db::name('fun_goodsshopcart')->where('gorderlistnum', $goods_order['orderlistnum'])->select()->toArray();
        $content1 = count($shop_cart) == 1 ?  '购买'.$shop_cart[0]['gtitle'] : '购买'.$shop_cart[0]['gtitle'].'等商品';
        $data['touser'] = $openid;//接受者
        $data['template_id'] = 'vtQHcrDG3MCIKEM6MBb08rhQCchGq3NadvdCti9Wzug';//模板id
        $data['url'] = 'http://'.$_SERVER['SERVER_NAME'].'/distribution/order/order_need_post/order_type/3.html';//链接
        $data['data']['first']=['value'=>'你推荐的'.$buy_member['nickname'].'('.getRankName($buy_member['rank']).')','color'=>'##173177'];
        $data['data']['keyword1']=['value'=>$content1,'color'=>'##173177'];
        $data['data']['keyword2']=['value'=>$goods_order['orderlistnum'] ,'color'=>'##173177'];
        $data['data']['keyword3']=['value'=>$goods_order['orderamount'] ,'color'=>'##173177'];
        $data['data']['keyword4']=['value'=>date('Y-m-d',$goods_order['posttime']),'color'=>'##173177'];
        file_put_contents('notify.txt', '$data:'.json_encode($data) . PHP_EOL, FILE_APPEND);
        $data1 = json_encode($data);
        $re = curl_request($url,$data1,'json');
//        dump($re);
        $re1 = json_decode($re, true);
        if(!empty($re1)){
            if($re1['errcode']==0){
                return true;
            }
        }
    }
    public function test1(){
        $goods_order = Db::name('fun_goodsorder')->where('id', 30)->find();
        $this->buySuccessMsg('oHCfwwFy5Tp5ceuSjpKukgdEa7PY', $goods_order);
    }
    public function access_token()
    {
        $weixin_setting = cmf_get_option('appid_setting');

        //获取access_token
        $appid  = $weixin_setting['appid'];
        $secret  = $weixin_setting['appsecret'];
        //查询access_token 时间 判断是否过期
        $rea = Db::name('access_token')->where(['id' => 1])->field('time')->find();
//        dump(time());
//        dump($rea['time']);
        $time = time() - $rea['time'];
        if ($time > 5000) {
            $url   = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appid}&secret={$secret}";
//            $url   = "http://www.mofyi.com/api/weixin/access_token?appid={$appid}&secret={$secret}";
            $data  = curl_request($url);
            $data1 = json_decode($data, true);
            if (isset($data1['access_token'])) {
                $re['access_token'] = $data1['access_token'];
                $re['time']         = time();
                Db::name('access_token')->where(['id' => 1])->update($re);
            }
        }
    }
}
