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
use think\Wechat\WechatAction;

class WechatController extends HomeBaseController
{
    //关注
    public function index()
    {
        session_start();
        $referee_id = $this->request->param('referee_id', 0, 'intval');
        if(!empty($referee_id)){
            session('referee_id', $referee_id);
        }
        $WechatAction = new WechatAction();
        $WechatAction->index();
    }
    //公众号的底部菜单
    public function create_menu()
    {
        session_start();
        $referee_id = $this->request->param('referee_id', 0, 'intval');
        if(!empty($referee_id)){
            session('referee_id', $referee_id);
        }
        $WechatAction = new WechatAction();
        $WechatAction->createMenu();
    }
}
