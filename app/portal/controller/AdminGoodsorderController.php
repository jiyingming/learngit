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
namespace app\portal\controller;

use cmf\controller\AdminBaseController;
use app\portal\model\FunMemberModel;
use app\admin\model\FunGoodsorderModel;
use think\Db;
use app\distribution\controller\OrderController;


class AdminGoodsorderController extends AdminBaseController
{

    protected  $model = '';
    public function _initialize()
    {
        $this->model = new FunGoodsorderModel();

        parent::_initialize();
    }

    public function index()
    {
        /**搜索条件**/
        $param = $this->request->param();

        $order_list = $this->model->getGoodsorderList($param);

        $this->assign('start_time', isset($param['start_time']) ? $param['start_time'] : '');
        $this->assign('end_time', isset($param['end_time']) ? $param['end_time'] : '');
        $this->assign('keyword', isset($param['keyword']) ? $param['keyword'] : '');

        $this->assign('order_list', $order_list);
        $this->assign('page', $order_list->render());

        return $this->fetch();
    }

    /**
     * 获取发货信息
     * @return \think\response\View
     */
    public function getShip()
    {
        if ($this->request->isAjax()){
            $ids = $this->request->param('ids', 0, 'intval');

            $condition['post_status'] = ['>', 0];
            $condition['g.id'] = $ids;

            $row = $this->model->getShippingInfo($condition);

            $shipList = Db::name('fun_express')->where('post_status', 1)->select();

            $row['agent_name'] = $this->getAgentName($row['frank']);
            return view('add', ['row' => $row, 'shipList' => $shipList]);
        }
    }

    /**
     * 修改
     * @return mixed
     */
    public function edit()
    {
        $initId = $this->request->param('id', 0, 'intval');

        $condition['post_status'] = ['>=', -1];
        $condition['g.id'] = $initId;

        $row = $this->model->getShippingInfo($condition);

        $shipList = Db::name('fun_express')->where('post_status', 1)->select();

        $this->assign('row', $row);
        $this->assign('shipList', $shipList);
        return $this->fetch();
    }

    public function editPost()
    {
        $post_name = $this->request->post('post_name');
        $id = $this->request->post('id', 0, 'intval');
        $postid = $this->request->post('postid');
        if ($post_name == '' || $postid == ''){
            $this->error('快递公司或订单号码不能为空');
        }
        $res = $this->model->where('id', $id)->update(['post_name' => $post_name, 'postid' => $postid]);
        if ($res){

            $this->success('保存成功', url('AdminGoodsorder/index'));
        }
        else
        {
            $this->error('保存失败');
        }
    }

    /**
     * 确认收款（货到付款）
     */
    public function confirmReceipt()
    {

        $id = $this->request->param('id', 0, 'intval');

        $res = $this->model->where('id', $id)->update(['is_pay' => 1]);
        if ($res){
            $order = new OrderController();
            $order->cash_on_delivery();
            $this->success('收款成功');
        }
        else
        {
            $this->error('收款失败');
        }
    }

    /**
     * 发货
     */
    public function doShipping()
    {

        $id = $this->request->post('id', 0, 'intval');

        $type = $this->request->post('type');
        $user_id = $this->model->where('id', $id)->value('userid');
        $olnum = $this->model->where('id', $id)->value('orderlistnum');
        if ($type == 1){

            $postid = $this->request->post('postid');
            $post_name = $this->request->post('post_name');

            if ($post_name == '' || $postid == ''){
                $this->error('快递公司或订单号码不能为空');
            }

            $data = [
                'post_name'   => $post_name,
                'postid'      => $postid,
                'post_status' => 2,
                'post_type'   => 1,
                'reabte_id'   => $user_id,//为了计算团队奖
                'sendtime'    => time(),
            ];
        }
        else
        {
            $data = [
                'post_status' => 2,
                'post_type'   => 2,
                'sendtime'    => time(),
                'reabte_id'   => $user_id,//为了计算团队奖
            ];
        }

        $res = $this->model->where('id', $id)->update($data);
        if ($res){
            //返利的逻辑开始
            $order = new OrderController();
            $order->rebate_logic($olnum);
            $this->success();
        }
        else
        {
            $this->error('保存失败');
        }
    }
    /**
     * 发送提醒消息
     */
    public function remindMsg(){
        if($this->request->isAjax()){
            $param = $this->request->param();
            $openid = Db::name('fun_member')->where('id', $param['post_id'])->value('openid');
            $good_order = Db::name('fun_goodsorder')->where('id', $param['order_id'])->find();
            $this->access_token();
            $res = $this->remindSuccessMsg($openid, $good_order);
            return $res;
        }
    }
    /**
     * 删除提问
     * @return [type] [description]
     */
    public function del()
    {
        $param           = $this->request->param();

        if (isset($param['id'])) {
            $id           = $this->request->param('id', 0, 'intval');
            $result       = $this->model->where(['id' => $id])->find();
            if ($result) {
                if ($this->model->update(['delstate' => true, 'delstate_time' => time(), 'id' => $id])) {
                    $this->success('删除成功！');
                } else {
                    $this->error('删除失败！');

                }
            }


        }

        if (isset($param['ids'])) {
            $ids     = $this->request->param('ids/a');

            $count = $this->model->where(['id' => ['in', $ids], 'post_status' => ['in', [-1, 0, 3]]])->update(['delstate' => true, 'delstate_time' => time()]);

            if ($count) {
                $this->success('删除成功！');
            } else {
                $this->error('删除失败！');

            }
        }

    }

    protected function  getAgentName($status)
    {
        $order_status = '';
        switch ($status)
        {
            case 1:
                $order_status = '铜牌代理';
                break;
            case 2:
                $order_status = '银牌代理';
                break;
            case 3:
                $order_status = '金牌代理';
                break;
            case 4:
                $order_status = '钻石代理';
                break;
            default:
                $order_status = '普通会员';
                break;

        }
        return $order_status;
    }

    /**
     * 发送购买成功给推荐人发模板消息
     * @param varchar $openid 推荐人的微信id
     * @param array $goods_order  订单信息
     * @param int $rank 等级级别
     */
    public function remindSuccessMsg($openid, $goods_order){

        $access_token = Db::name('access_token')->where(['id'=>1])->field('access_token')->find();
        $url = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=".$access_token['access_token'];
        $data = [];
        //消费者的信息
        $buy_member = Db::name('fun_member')->where('id', $goods_order['userid'])->find();
        $shop_cart = Db::name('fun_goodsshopcart')->where('gorderlistnum', $goods_order['orderlistnum'])->select()->toArray();
        $content1 = count($shop_cart) == 1 ?  '购买'.$shop_cart[0]['gtitle'] : '购买'.$shop_cart[0]['gtitle'].'等商品';
        $data['touser'] = $openid;//接受者
        $data['template_id'] = 'Huu_YCV1tQKbRztfx-sd6m2p2zWFemPn9XNgcVE_fGE';//模板id
        $data['data']['first']=['value'=>'您有一个新的待发货订单','color'=>'##173177'];
        $data['data']['keyword1']=['value'=>$goods_order['orderlistnum']    ,'color'=>'##173177'];
        $data['data']['keyword2']=['value'=>$goods_order['orderamount'] ,'color'=>'##173177'];
        $data['data']['keyword3']=['value'=>$buy_member['nickname'].'('.getRankName($buy_member['rank']).')','color'=>'##173177'];
        $data['data']['keyword4']=['value'=>'已支付，待发货','color'=>'##173177'];
        $data['data']['remark']=['value'=>'买家已支付，请尽快发货或处理','color'=>'##173177'];
//        file_put_contents('notify.txt', '$data:'.json_encode($data) . PHP_EOL, FILE_APPEND);
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
