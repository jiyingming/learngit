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

use app\portal\model\FunMemberModel;
use app\admin\model\FunGoodsorderModel;
use cmf\controller\AdminBaseController;
use think\Db;

/**
 * Class AdminTagController 标签管理控制器
 * @package app\portal\controller
 */
class AdminMemberController extends AdminBaseController
{
    /**
     * @var \app\admin\model\User
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new FunMemberModel();

    }

    /**
     * 列表页面
     * @return mixed
     */
    public function index()
    {
        $param = $this->request->param();
        $condition = [];
        $keyword = empty($param['keyword']) ? '' : $param['keyword'];
        if (!empty($keyword)) {
            $condition['real_name|nickname'] = ['like', "%$keyword%"];
        }
        $member_list = $this->model->getAdminMemberList($condition);

        $this->assign('keyword', isset($param['keyword']) ? $param['keyword'] : '');
        $this->assign('member_list', $member_list);
        $this->assign('page', $member_list->render());
        return $this->fetch();
    }


    /**
     * 下级分销商
     * @return mixed
     */
    public function add()
    {
        $intId     = $this->request->param("ids");

        if (empty($intId)) {
            $this->error(lang("NO_ID"));
        }

        $row        = $this->model->getAdminMemberInfo($intId);

        $where['referee_id'] = $intId;
        $shop_list  = $this->model->getAdminMemberList($where);

        $this->assign('shop_list', $shop_list);
        $this->assign('row', $row);
        return $this->fetch();
    }

    /**
     * 查看
     */
    public function edit()
    {
        $intId     = $this->request->param("ids");

        if (empty($intId)) {
            $this->error(lang("NO_ID"));
        }

        $row        = $this->model->getAdminMemberInfo($intId);

        $shop_list  = $this->model->getAgentList($intId);

        $order_list = (new FunGoodsorderModel())->getAgentOrderList($intId);

        $this->assign('shop_list', $shop_list);
        $this->assign('order_list', $order_list);
        $this->assign('row', $row);
        return $this->fetch();

    }

    /**
     * 删除文章标签
     * @adminMenu(
     *     'name'   => '删除文章标签',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '删除文章标签',
     *     'param'  => ''
     * )
     */
    public function delete()
    {
        $intId = $this->request->param("id", 0, 'intval');

        if (empty($intId)) {
            $this->error(lang("NO_ID"));
        }
        $portalTagModel = new PortalTagModel();

        $portalTagModel->where(['id' => $intId])->delete();
        Db::name('portal_tag_post')->where('tag_id', $intId)->delete();
        $this->success(lang("DELETE_SUCCESS"));
    }
}
