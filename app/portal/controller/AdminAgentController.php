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

use app\portal\model\FunAgentModel;
use cmf\controller\AdminBaseController;
use meijia\HttpClient;
use think\Db;

/**
 * Class AdminTagController 标签管理控制器
 * @package app\portal\controller
 */
class AdminAgentController extends AdminBaseController
{

    /**
     * @var \app\admin\model\User
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new FunAgentModel();

    }

    /**
     * 提问列表
     *
     */
    public function index()
    {

        $agent_list = $this->model->getAdminAgentList();
        $this->assign('agent_list', $agent_list);

        return $this->fetch();
    }

    /**
     * 添加级别
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post('row/a');

            $result = $this->validate($data, 'AdminAgent');
            if ($result !== true) {
                $this->error($result);
            }


            if ($this->model->addFunAgent($data)) {
                $this->success('添加成功!', url('AdminAgent/index'));
            } else {
                $this->error('添加失败！');
            }

        }
    }

    /**
     * 编辑文章
     */
    public function edit()
    {
        $ids = $this->request->param('ids', 0, 'intval');
        if ($ids > 0){
            $row = $this->model->getAgentInfo($ids);
            if (!$row)
            {
                $this->error('该条信息不存在');
            }
            //$row['content'] = htmlspecialchars_decode($row['content']);
            $this->success('', '', $row);
        }
        else
        {
            if ($this->request->isPost()) {
                $data = $this->request->post('row/a');

                $result = $this->validate($data, 'AdminAgent');
                if ($result !== true) {
                    $this->error($result);
                }
                if ($this->model->editFunAgent($data)) {
                    $this->success('保存成功!', url('AdminAgent/index'));
                } else {
                    $this->error('保存失败！');
                }

            }
        }

    }

    public function ossTest()
    {
        if ($this->request->isPost()) {
            $file = $this->request->file('file');

            if (!$file) {
                $this->msg(0, '上传图片不能为空');
                exit();
            }
            $fileInfo = $file->getInfo();
            $suffix = strtolower(pathinfo($fileInfo['name'], PATHINFO_EXTENSION));
            $suffix = $suffix ? $suffix : 'file';

            $mimetypeArr = array('jpg', 'jpeg', 'png', 'gif');

            //验证文件后缀
            if (!in_array($suffix, $mimetypeArr))
            {
                $this->msg(0 ,'上传文件类型错误');
            }

            $uploadDir = '/upload'. '/' . date('Ym', time());
            $fileName = date('dHis', time()) . rand(10000, 99999) . '.' . $suffix;
            // 移动到框架应用根目录/public/upload/ 目录下,文件命名格式为：年月/日时分秒三位随机数
            $splInfo = $file->move(ROOT_PATH . 'public' . $uploadDir,  $fileName);
            $res = upFileToOss('.'.$uploadDir.'/'.$splInfo->getSaveName(), true);
            if ($res['status'] == 10) {
                return $this->msg(1, '', $res['url']);
            }
            exit;
        }

    }

    protected  function  msg($code,$msg,$data=[])
    {
        return json(['code'=>$code, 'msg'=>$msg, 'data'=>$data]);
    }

    /**
     * 删除提问
     * @return [type] [description]
     */
    public function del()
    {

        $id           = $this->request->param('id', 0, 'intval');
        $result       = $this->model->get(['id' => $id, 'status' => 1]);
        if ($result) {
            $agentNum = (new \app\portal\model\FunMemberModel())->where('rank', $id)->count();
            if ($agentNum == 0)
            {
                if ($result->save(['status' => -1])) {
                    $this->success('删除成功！');
                } else {
                    $this->error('删除失败！');

                }
            }
            else
            {
                $this->error('会员数量大于1人，禁止删除');
            }
        }


    }
}
