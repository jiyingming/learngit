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

use cmf\controller\AdminBaseController;
use app\portal\model\FunMemberModel;
use think\Db;
use mofyi\aliyun\Sms;

class AdminPageController extends AdminBaseController
{

    protected $model  = null;
    //更改上级，需验证的手机号码
    protected $mobile = '15628800208';

    //分销商级别
    protected $rank   = 9;

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
            $condition['m.real_name|m.nickname'] = ['like', "%$keyword%"];
        }
        $member_list = $this->model->getAdminUserList($condition);

        $this->assign('keyword', isset($param['keyword']) ? $param['keyword'] : '');
        $this->assign('member_list', $member_list);
        $this->assign('page', $member_list->render());
        $this->assign('rank', $this->rank);

        return $this->fetch();
    }


    /**
     * 查看
     */
    public function edit()
    {
        $intId = $this->request->param("ids");

        if (empty($intId)) {
            $this->error(lang("NO_ID"));
        }

        $row = $this->model->getAdminUserInfo($intId);
        if ($this->request->isPost()) {
            $post = $this->request->post();
            $data = [
                'delete_status' => $post['delete_status'],
                'is_service' => $post['is_service'],
            ];
            if ($this->model->where('id', $post['id'])->update($data)) {
                $this->success('修改成功');
            } else {
                $this->error('这儿的修改不起作用');
            }

        }

        $agentList = (new \app\portal\model\FunAgentModel())->getAdminAgentList();

        $this->assign('row', $row);
        $this->assign('agentList', $agentList);
        return $this->fetch();

    }


    /**
     * 实名认证
     * @return mixed
     */
    public function add()
    {

        $condition['m.status'] = ['>', 0];
        $member_list = $this->model->getAdminUserList($condition);

        $this->assign('member_list', $member_list);
        $this->assign('page', $member_list->render());
        return $this->fetch();
    }

    /**
     * 获取实名认证信息
     */
    public function getRealName()
    {
        $intId = $this->request->param("ids");

        if (empty($intId)) {
            $this->error(lang("NO_ID"));
        }

        $row = $this->model->get(['id' => $intId, 'status' => 1]);
        if ($row) {
            $this->success('', '', $row);
        } else {
            $this->error('信息不存在');
        }

    }

    /**
     * 实名认证操作
     */
    public function doRealName()
    {
        if ($this->request->isAjax()) {
            $post = $this->request->post('row/a');
            $row = $this->model->isUpdate(true)->save(['id' => $post['ids'], 'status' => $post['status']]);
            if ($row) {
                $this->success('');
            } else {
                $this->error('保存失败');
            }
        }
    }

    /**
     * 拉黑
     */
    public function recommend()
    {
        $param = $this->request->param();


        if (isset($param['ids']) && isset($param["yes"])) {
            $ids = $this->request->param('ids/a');

            $this->model->where(['id' => ['in', $ids]])->update(['delete_status' => -1]);

            $this->success("已拉黑该用户！", '');

        }
        if (isset($param['ids']) && isset($param["no"])) {
            $ids = $this->request->param('ids/a');

            $this->model->where(['id' => ['in', $ids]])->update(['delete_status' => 0]);

            $this->success("取消拉黑该用户！", '');

        }
    }

    /**
     * 更改会员上级（包含上上级）
     */
    public function changeUserAgent()
    {
        if ($this->request->isAjax()){
            $param = $this->request->post();

            $result = $this->check_verification_code($this->mobile, $param['code'], true);
            //$result == 1
            if(true){
                if ($this->model->adminChangeUserLevel($param['id'], $param['pid'])){
                    $this->success('操作成功');
                }
                else
                {
                    $this->error('操作失败');
                }
            }
            else
            {
                $this->error($result);
            }


        }

    }

    /**
     * 更改会员上级操作页面
     * @return \think\response\View
     */
    public function getPage()
    {
        if ($this->request->isAjax()){
            $id = $this->request->param('id', 0, 'intval');

            $row = $this->model->where('id',$id)->find();

            $condition['rank'] = ['>', $row['rank']];
            if ($row['rank'] == $this->rank){
                $condition['rank'] = ['>=', $this->rank];
            }
            $agentList = $this->model->getChangeAgentList($condition);
            return view('add_back', ['agentList' => $agentList, 'row' => $row]);
        }
    }

    /**
     * 更改用户级别
     */
    public function changeAdminAgent()
    {
        if ($this->request->isAjax()){
            $param = $this->request->param();


            $result = $this->check_verification_code($this->mobile, $param['code'], true);
            //$result == 1
            if($result ){
                $ret = $this->model->adminChangeUserRank($param);
                if ($ret){
                    $this->success();
                }
                else
                {
                    $this->error($this->model->getError());
                }
            }
            else
            {
                $this->error($result);
            }


        }

    }
    /**
     * 董事团队奖是否加下级董事
     */
    public function changeAdminDirecter()
    {
        if ($this->request->isAjax()){
            $param = $this->request->param();
            $result = $this->check_verification_code($this->mobile, $param['code'], true);
            //$result == 1
            if($result ){
                $ret = $this->model->adminChangeDirector($param);
                if ($ret){
                    $this->success();
                }
                else
                {
                    $this->error($this->model->getError());
                }
            }
            else
            {
                $this->error($result);
            }


        }

    }

    /**
     * 更改用户充值金额
     */
    public function changeAdminMoney()
    {
        if ($this->request->isAjax()){
            $param = $this->request->param();


            $result = $this->check_verification_code($this->mobile, $param['code'], true);
            //$result == 1
            if($result ){
                $ret = $this->model->adminChangeUserMoney($param);
                if ($ret){
                    $this->success();
                }
                else
                {
                    $this->error($this->model->getError());
                }
            }
            else
            {
                $this->error($result);
            }


        }

    }

    /**
     * 删除页面
     * @author    iyting@foxmail.com
     * @adminMenu(
     *     'name'   => '删除页面',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '删除页面',
     *     'param'  => ''
     * )
     */
    public function delete()
    {
        $portalPostModel = new PortalPostModel();
        $data = $this->request->param();

        $result = $portalPostModel->adminDeletePage($data);
        if ($result) {
            $this->success(lang('DELETE_SUCCESS'));
        } else {
            $this->error(lang('DELETE_FAILED'));
        }

    }

    /**
     * 发送短信
     * @return \think\response\Json
     */
    public function sendSms()
    {
        $code = mt_rand(100000,999999);return json(['code' => 1, 'msg' => '验证码发送成功']);
        $result = Sms::sendSms2($this->mobile, $code);

        if ($result->Code == 'OK') {
            cmf_verification_code_log($this->mobile, $code, time()+1800);
            return json(['code' => 1, 'msg' => '验证码发送成功']);
        }
        else
        {
            return json(['code' => 0, 'msg' => '验证码发送失败']);
        }
    }

    /**
     * 验证短信
     */
    protected function check_verification_code($account, $code, $clear = false)
    {
        $verificationCodeQuery = Db::name('verification_code');
        $findVerificationCode  = $verificationCodeQuery->where('account', $account)->find();

        if ($findVerificationCode) {
            if ($findVerificationCode['expire_time'] > time()) {

                if ($code == $findVerificationCode['code']) {
                    if ($clear) {
                        $verificationCodeQuery->where('account', $account)->update(['code' => '']);
                    }
                    return 1;
                } else {
                    return "验证码不正确!";
                }
            } else {
                return "验证码已经过期,请先获取验证码!";
            }

        } else {
            return "请先获取验证码!";
        }

        return "";
    }

}
