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
namespace app\admin\model;

use think\Db;
use think\Model;

class FunGoodsorderModel extends Model
{

    public function getGoodsorderList($filter, $field = '*', $order = 'g.id DESC', $list_rows = 15)
    {
        $condition['delstate'] = '';

        $startTime = empty($filter['start_time']) ? 0 : strtotime($filter['start_time']);
        $endTime   = empty($filter['end_time']) ? 0 : strtotime($filter['end_time']);
        if (!empty($startTime) && !empty($endTime)) {
            $condition['g.posttime'] = [['>= time', $startTime], ['<= time', $endTime]];
        } else {
            if (!empty($startTime)) {
                $condition['g.posttime'] = ['>= time', $startTime];
            }
            if (!empty($endTime)) {
                $condition['g.posttime'] = ['<= time', $endTime];
            }
        }

        $keyword = empty($filter['keyword']) ? '' : $filter['keyword'];
        if (!empty($keyword)) {
            $condition['gtitle'] = ['like', "%$keyword%"];
        }

        $field = 'g.*, f.rank as frank, f.nickname as fnickname, f.real_name as freal_name, m.referee_id as mreferee_id, m.status as mstatus, 
        m.real_name as mreal_name, m.rank as mrank, f.id as fid, m.id as mid';
        $order_list  = $this->alias('g')
                            ->join('__FUN_MEMBER__ m', 'g.userid=m.id', 'left')
                            ->join('__FUN_MEMBER__ f', 'm.referee_id=f.id', 'left')
                            ->where($condition)
                            ->field($field)
                            ->order($order)
                            ->paginate($list_rows);
        foreach ($order_list as $k=>$v){
            $ext_info = Db::name('fun_goodsshopcart')->where('gorderlistnum', $v['orderlistnum'])->select();
            $order_list[$k]['goods_num']  = 0;
            $order_list[$k]['goods_name'] = '';
            if ($ext_info){

                foreach ($ext_info as $key=>$value){
                    $order_list[$k]['goods_name'] .= $value['gtitle'].'&nbsp;&nbsp;';
                    $order_list[$k]['goods_num']  += $value['num'];
                }

            }
            $order_list[$k]['ext_info'] = $ext_info;
        }

        return $order_list;
    }

    /**
     * 获取分销商订单
     * @param array $condition
     * @param string $order
     * @param int $list_rows
     * @return mixed
     */
    public function getAgentOrderList($ids = 0, $order = 'posttime DESC', $list_rows = 10)
    {
        //最新订单
        $allIds = (new \app\portal\model\FunMemberModel())->where(['referee_id|pre_referee_id|id' => $ids])->column('id');
        $condition['g.userid'] = ['in', $allIds];
        $condition['delstate']    = '';
        $condition['post_status'] = ['>=', -1];
        $order_list = $this->alias('g')
                        ->join('__FUN_MEMBER__ m', 'g.userid=m.id', 'left')
                        ->join('__FUN_MEMBER__ f', 'm.referee_id=f.id', 'left')
                        ->where($condition)
                        ->field('g.*, f.rank as frank, f.nickname as fnickname, m.create_time, m.nickname as mnickname')
                        ->order($order)
                        ->paginate($list_rows);

        foreach ($order_list as $k=>$v){
            $ext_info = Db::name('fun_goodsshopcart')->where('gorderlistnum', $v['orderlistnum'])->select();
            if ($ext_info){
                $order_list[$k]['goods_name'] = '';
                foreach ($ext_info as $key=>$value){
                    $order_list[$k]['goods_name'] .= $value['gtitle'].'&nbsp;&nbsp;';
                }

            }
            $order_list[$k]['ext_info'] = $ext_info;
        }
        return $order_list;
    }


    public function getShippingInfo($condition = [])
    {

        $condition['delstate']    = '';
        //$condition['post_status'] = ['>', 0];
        //$condition['g.id'] = $ids;
        $field = 'g.*, f.rank as frank, f.nickname as fnickname, f.mobile as fmobile';
        $order_info = $this->alias('g')
            ->join('__FUN_MEMBER__ m', 'g.userid=m.id', 'left')
            ->join('__FUN_MEMBER__ f', 'm.referee_id=f.id', 'left')
            ->where($condition)
            ->field($field)
            ->find();

        $order_info['goods_name'] = '';
        $ext_info = Db::name('fun_goodsshopcart')->where('gorderlistnum', $order_info['orderlistnum'])->select();
        if ($ext_info) {

            foreach ($ext_info as $key => $value) {
                $order_info['goods_name'] .= $value['gtitle'] . '&nbsp;&nbsp;';
            }

            $order_info['ext_info'] = $ext_info;
        }
        return $order_info;

    }

    public function getECharts()
    {

        $current_month = (int) date('m', time());

        $monthArr = [];
        for($i=0;$i<6;$i++){
            $monthArr[] = $current_month;
            $current_month = $current_month - 1;
            if ($current_month === 0) { // 月份减一等于零，证明是要从1月跳到上一年的12月
                $current_month = 12; // 本次要获取订单数的月份

            }

        }
        $row = $this->where(['post_status' => ['>',0]])
                    ->field("SUM( orderamount ) AS allamount, count( id ) AS allnum, FROM_UNIXTIME( posttime, '%m' ) AS yearmonth")
                    ->group('yearmonth')
                    ->select()->toArray();
        $ret = [];
        $res = [];
        foreach ($row as $val)
        {
            $ret[(int)$val['yearmonth']] = $val['allamount'];
            $res[(int)$val['yearmonth']] = $val['allnum'];
        }

        $amount = [];
        $anum   = [];
        foreach ($monthArr as $k=>$v){
            if (isset($res[$v]) && isset($ret[$v])){
                $amount[$v] = $ret[$v];
                $anum[$v]   = $res[$v];
            }
            else
            {
                $amount[$v] = 0;
                $anum[$v]   = 0;
            }
            $monthArr[$k] = $v.'月';
        }

        $amount = array_reverse($amount);
        $anum = array_reverse($anum);
        $monthArr = array_reverse($monthArr);
        return array('amount'=>json_encode(array_values($amount)), 'anum'=>json_encode(array_values($anum)), 'month'=>json_encode(array_values($monthArr)));
    }


}