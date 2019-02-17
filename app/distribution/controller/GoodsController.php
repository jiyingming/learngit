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
use app\portal\model\GoodsModel;
use think\Session;
use think\Cookie;

class GoodsController extends HomeBaseController
{
    public function index()
    {
        $user_id = Cookie::get('user_id');
        $member = getMemberInfo($user_id);
        if(empty($member['mobile'])){
            header('Location: /distribution/index/login');exit();
        }
        $join = [
            ['__GOODS_CATEGORY_LINK__ b', 'a.id = b.post_id'],
        ];
        $where = [
            'a.delete_time' => 0,
            'b.category_id' => 1,//商品的分类
        ];
        $field = 'a.id,a.post_title,a.post_excerpt,a.thumbnail,a.price,a.published_time,a.more';
        $GoodsModel = new GoodsModel();
        $goods_list        = $GoodsModel->alias('a')->field($field)
            ->join($join)
            ->where($where)
            ->order(['a.create_time'=>'desc'])
            ->select()->toArray();
        $this->assign('goods_list', $goods_list);
        $person = getMemberInfo($user_id);

        $this->assign('person', $person);
        return $this->fetch(':goods/index');
    }
    public function detail()
    {
        $id = $this->request->param('gid', 1, 'intval');
        $portalPostModel = new GoodsModel();
        $post            = $portalPostModel->where('id', $id)->find();
        $this->assign('post', $post);
        $person = Db::name('fun_member')->where('openid', session('openid'))->find();

        $real_price = getGoodsRankPrice($id, $person['rank']);

        $this->assign('person', $person);
        $this->assign('real_price', $real_price);
        //购物车的数量
        $where['Status'] = 'cart';
        $where['uid'] = Cookie::get('user_id');
        $shop_count = Db::name('fun_goodsshopcart')->where($where)->count();
        $this->assign('shop_count', $shop_count);
        return $this->fetch(':goods/detail');
    }
    //添加购物车
    public function add_shop(){
        $param = $this->request->param();
        if ($this->request->isAjax()) {
            //gid:gid, price:price, good_num:good_num
            if(empty($param['gid']) || empty($param['price']) || empty($param['good_num'])){
                $data['code'] = 0;
                $data['msg'] = '参数错误';
                return json($data);
            }
            $user_id = Cookie::get('user_id');
            $gid = isset($param['gid']) ? $param['gid'] : 0;
            $price = isset($param['price']) ? $param['price'] : 0;
            $good_num = isset($param['good_num']) ? $param['good_num'] : 0;
            //判断购物车表是否有该商品的记录
            $where['gid'] = $gid;
            $where['uid'] = $user_id;
            $where['Status'] = 'cart';
            $shop_info = Db::name('fun_goodsshopcart')->where($where)->find();
            if(empty($shop_info)){
                $good_info = Db::name('goods')->where('id', $gid)->find();
                $shop_data['gid'] = $gid;
                $shop_data['gtitle'] = $good_info['post_title'];
                $shop_data['gpicurl'] = $good_info['thumbnail'];
                $shop_data['price'] = $price;//商品原价*折扣
                $shop_data['num'] = $good_num;
                $shop_data['uid'] = $user_id;
                $shop_data['buyprice'] = $good_info['price'];
                $shop_data['Status'] = 'cart';
                $shop_data['edit_date'] = date('Y-m-d', time());
                $res = Db::name('fun_goodsshopcart')->insert($shop_data);
            } else {
                $wh['gid'] = $gid;
                $wh['uid'] = $user_id;
                $wh['Status'] = 'cart';
               $res = Db::name('fun_goodsshopcart')->where($wh)->setInc('num', $good_num);
            }
            if($res){
                $data['code'] = 1;
                $data['msg'] = 'ok';
                return json($data);
            }
        }
    }
}
