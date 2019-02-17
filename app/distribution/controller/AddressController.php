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
use think\Session;
use think\Cookie;
use think\Request;

class AddressController extends HomeBaseController
{
    //收货地址
    public function index()
    {
        $user_id = Cookie::get('user_id');
        $member = getMemberInfo($user_id);
        if(empty($member['mobile'])){
            header('Location: /distribution/index/login');exit();
        }
        if(!empty(session('from'))){
            $this->assign('from', session('from'));
        }
        $where['userid'] = $user_id;
        $where['status'] = 1;
        $addr_list = Db::name('fun_address')->where($where)->select()->toArray();
        $this->assign('addr_list', $addr_list);
        return $this->fetch(':address/index');
    }
    public function add_addr()
    {
        $param = $this->request->param();
        $addr_id = 0;
        //编辑
        if(!empty($param['addr_id'])){
            $addr_id = $param['addr_id'];
            $addr_info = Db::name('fun_address')->where('id', $param['addr_id'])->find();
            $this->assign('addr_info', $addr_info);
        }
        $this->assign('addr_id', $addr_id);
        return $this->fetch(':address/add_addr');
    }

    public function add_post(){

        $param = $this->request->param();
        $data['rec_name'] = $param['rec_name'];
        $data['mobile'] = $param['mobile'];
        $data['road_address'] = $param['road_address'];
        $data['cur_address'] = $param['cur_address'];
        $data['userid'] = Cookie::get('user_id');

        if(empty($data['rec_name']) || empty($data['mobile']) || empty($data['road_address']) || empty($data['cur_address'])){
            echo "<script>alert('信息填写不完整');window.history.go(-1)</script>";exit();
        }
        //编辑提交
        if(!empty($param['addr_id'])){
            $res = Db::name('fun_address')->where('id', $param['addr_id'])->update($data);
        } else{
            $data['create_time'] = time();
            $res = Db::name('fun_address')->insert($data);
        }
        if($res){
            echo "<script>alert('提交成功');window.location.href = '/distribution/address/index'</script>";exit();
        } else {
            echo "<script>alert('提交失败');window.history.go(-1)</script>";exit();
        }
    }
    //删除地址
    public function del_addr(){
        $addr_id = $this->request->param('addr_id', 0, 'intval');
        if(empty($addr_id)){
            echo "<script>alert('请求错误');window.location.href='/distribution/address/index'</script>";exit();
        }
        $res = Db::name('fun_address')->where('id', $addr_id)->update(['status'=>0]);
        echo "<script>alert('删除成功');window.location.href = '/distribution/address/index'</script>";exit();
    }
    //测试不用管（测试找董事的上级）
    public function test(){
        $member_list = Db::name('fun_member')->field('id, referee_id, rank')->select()->toArray();
        $director = get_top_parent($member_list, 19);
        if($director){
            echo $director;
        } else {
            echo 'no director';
        }
    }
    //判断上级是不是可以升为董事
    public function upto_director(){
        $id = 8;
        //当充值的人的等级升为联创时
        //充值人的上级
        $referee_id = getMemberParent($id);
        if($referee_id){
            $rank_where['referee_id'] = $referee_id;
            $rank_where['rank'] = 4;
            $count = Db::name('fun_member')->where($rank_where)->count();
            if($count >= 3){
                Db::name('fun_member')->where('id', $referee_id)->update(['rank'=>10]);
            }
        }
    }
    public function test1(){
        $re = getUserOrderamount('154745437635', 3);
        dump($re);
    }


}
