<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2018 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 小夏 < 449134904@qq.com>
// +----------------------------------------------------------------------
namespace app\portal\controller;


use app\portal\model\FunMemberModel;
use app\portal\model\FunWithdrawalModel;
use cmf\controller\AdminBaseController;
use think\Db;

class AdminCashController extends AdminBaseController
{

    /**
     * 页面管理
     * @adminMenu(
     *     'name'   => '页面管理',
     *     'parent' => 'portal/AdminIndex/default',
     *     'display'=> true,
     *     'hasView'=> true,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '页面管理',
     *     'param'  => ''
     * )
     */
    public function index()
    {

        $param = $this->request->param();

        $where['f.status'] = 1;

        if (isset($param['keyword'])) $where['user_name|mobile'] = ['like', '%'.$param['keyword'].'%'];
        $cash_list = Db::name('fun_withdrawal')->alias('f')
                                               ->join('fun_member m','f.userid=m.id')
                                               ->field('f.*, m.openid, m.fanli_money')
                                               ->where($where)
                                               ->order('f.id DESC')
                                               ->paginate(15);
        
        $this->assign('keyword', isset($param['keyword']) ? $param['keyword'] : '');
        $this->assign('cash_list', $cash_list);
        $this->assign('page', $cash_list->render());

        return $this->fetch();
    }


    /**
     * 编辑页面
     * @adminMenu(
     *     'name'   => '编辑页面',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> true,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '编辑页面',
     *     'param'  => ''
     * )
     */
    public function edit()
    {
        $id = $this->request->param('id', 0, 'intval');
        $where['status'] = 1;
        $where['type']   = 1;
        $where['id']     = $id;
        $row = Db::name('fun_withdrawal')->where($where)->find();

        $this->assign('row', $row);

        return $this->fetch();
    }

    /**
     * 编辑页面提交
     * @adminMenu(
     *     'name'   => '编辑页面提交',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '编辑页面提交',
     *     'param'  => ''
     * )
     */
    public function editPost()
    {
        $data = $this->request->post('row/a');
        $status  = FALSE;
        $balance = 0;

        Db::startTrans();
        $cashModel = new FunWithdrawalModel();

        try {

            $res = $cashModel->get($data['id']);

            if ($res ){
                $userInfo = (new FunMemberModel())->get($res->userid);

                if ($userInfo->fanli_money > $res->money){

                    $res->post_status = $data['post_status'];
                    $res->message     = $data['message'];

                    if ($data['post_status'] == 1 ){
                        $balance = $userInfo->fanli_money -= $res->money;
                        $userInfo->save();
                    }

                    $data = [
                        'user_id'     => $res->userid,
                        'create_time' => time(),
                        'change'      => $res->money,
                        'balance'     => $balance,
                        'remark'      => $data['post_status'],
                    ];

                    $res->save();
                    Db::name('user_balance_log')->insert($data);
                }


            }

            // 提交事务
            Db::commit();
            $status = TRUE;
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
        }

        if($status){

            $this->success('保存成功!',url('AdminCash/index'));
        }else{

            $this->error('保存失败');
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
            $result       = Db::name('fun_withdrawal')->where(['id' => $id, 'status' => 1])->find();
            if ($result) {
                if (Db::name('fun_withdrawal')->update(['status' => -1, 'id' => $id])) {
                    $this->success('删除成功！');
                } else {
                    $this->error('删除失败！');

                }
            }


        }

        if (isset($param['ids'])) {
            $ids     = $this->request->param('ids/a');
            $count = 0;
            $list = Db::name('fun_withdrawal')->where('id', 'in', $ids)->select();
            foreach ($list as $index => $item) {
                $count += Db::name('fun_withdrawal')->update(['status' => -1, 'id' => $item['id']]);
            }
            if ($count) {
                $this->success('删除成功！');
            } else {
                $this->error('删除失败！');

            }
        }

    }

}
