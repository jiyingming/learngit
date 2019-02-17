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
use think\Db;


class AdminTeamController extends AdminBaseController
{
    public function index()
    {
        $teamList = Db::name('user_favorite')->where('status', 1)->order('id ASC')->paginate(15);
        $this->assign('teamList',$teamList);
        $this->assign('page',$teamList->render());
        return $this->fetch();
    }

    /**
     * 添加
     * @return mixed
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post('post/a');


            if (Db::name('user_favorite')->insert($data)) {
                $this->success('添加成功!', url('AdminTeam/index'));
            } else {
                $this->error('添加失败！');
            }

        }
        return $this->fetch();
    }

    /**
     * 编辑
     * @return mixed
     */
    public function edit()
    {
        $id = $this->request->param('id', 0, 'intval');

        $row = Db::name('user_favorite')->where(['status'=>1, 'id'=>$id])->find();
        if (!$row)
        {
            $this->error('该条信息不存在');
        }
        if ($this->request->isPost()) {
            $data = $this->request->post('post/a');


            if (Db::name('user_favorite')->update($data)) {
                $this->success('保存成功!', url('AdminTeam/index'));
            } else {
                $this->error('保存失败！');
            }

        }

        $this->assign('row', $row);

        return $this->fetch();
    }

    /**
     * 编辑
     * @return mixed
     */
    public function del()
    {
        $id = $this->request->param('id', 0, 'intval');

        $row = Db::name('user_favorite')->where(['status'=>1, 'id'=>$id])->find();
        if (!$row)
        {
            $this->error('该条信息不存在');
        }


        if (Db::name('user_favorite')->update(['id'=>$id, 'status'=>-1])) {
            $this->success('删除成功!', url('AdminTeam/index'));
        } else {
            $this->error('删除失败！');
        }

    }

}
