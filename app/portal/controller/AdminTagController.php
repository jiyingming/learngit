<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2018 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author:kane < chengjin005@163.com>
// +----------------------------------------------------------------------
namespace app\portal\controller;

use app\portal\model\FunWithdrawalModel;
use cmf\controller\AdminBaseController;
use think\Db;

/**
 * Class AdminTagController 返利管理
 * @package app\portal\controller
 */
class AdminTagController extends AdminBaseController
{
    /**
     * 文章标签管理
     * @adminMenu(
     *     'name'   => '文章标签',
     *     'parent' => 'portal/AdminIndex/default',
     *     'display'=> true,
     *     'hasView'=> true,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '文章标签',
     *     'param'  => ''
     * )
     */
    public function index()
    {
        $param = $this->request->param();

        $where['b.status']    = 1;
        $where['profit_type'] = 1;
        $where['bill_type']   = 2;
        if (isset($param['keyword'])) $where['user_name'] = ['like', '%'.$param['keyword'].'%'];
        $startTime = empty($param['start_time']) ? 0 : strtotime($param['start_time']);
        $endTime   = empty($param['end_time']) ? 0 : strtotime($param['end_time']);
        if (!empty($startTime) && !empty($endTime)) {
            $where['b.create_time'] = [['>= time', $startTime], ['<= time', $endTime]];
        } else {
            if (!empty($startTime)) {
                $where['b.create_time'] = ['>= time', $startTime];
            }
            if (!empty($endTime)) {
                $where['b.create_time'] = ['<= time', $endTime];
            }
        }

        $bill_list = Db::name('fun_bill')
                        ->alias('b')
                        ->join('__FUN_MEMBER__ m', 'b.user_id=m.id')
                        ->where($where)
                        ->field('b.*, m.rank, m.nickname, m.real_name')
                        ->order('id DESC')
                        ->paginate(15);

        $this->assign("bill_list", $bill_list);
        $this->assign('keyword', isset($param['keyword']) ? $param['keyword'] : '');
        $this->assign('start_time', isset($param['start_time']) ? $param['start_time'] : '');
        $this->assign('end_time', isset($param['end_time']) ? $param['end_time'] : '');
        $this->assign('page', $bill_list->render());
        return $this->fetch();
    }


    /**
     * 充值返利
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function rindex()
    {
        $param = $this->request->param();

        $where['b.status']    = 1;
        $where['profit_type'] = 2;
        $where['bill_type']   = 2;
        if (isset($param['keyword'])) $where['user_name'] = ['like', '%'.$param['keyword'].'%'];
        $startTime = empty($param['start_time']) ? 0 : strtotime($param['start_time']);
        $endTime   = empty($param['end_time']) ? 0 : strtotime($param['end_time']);
        if (!empty($startTime) && !empty($endTime)) {
            $where['b.create_time'] = [['>= time', $startTime], ['<= time', $endTime]];
        } else {
            if (!empty($startTime)) {
                $where['b.create_time'] = ['>= time', $startTime];
            }
            if (!empty($endTime)) {
                $where['b.create_time'] = ['<= time', $endTime];
            }
        }

        $bill_list = Db::name('fun_bill')
            ->alias('b')
            ->join('__FUN_MEMBER__ m', 'b.user_id=m.id')
            ->where($where)
            ->field('b.*, m.rank, m.nickname, m.real_name')
            ->order('id DESC')
            ->paginate(15);

        $this->assign("bill_list", $bill_list);
        $this->assign('keyword', isset($param['keyword']) ? $param['keyword'] : '');
        $this->assign('start_time', isset($param['start_time']) ? $param['start_time'] : '');
        $this->assign('end_time', isset($param['end_time']) ? $param['end_time'] : '');
        $this->assign('page', $bill_list->render());
        return $this->fetch('select');
    }


}
