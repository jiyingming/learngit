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
namespace app\portal\controller

;
use app\admin\model\RouteModel;
use cmf\controller\HomeBaseController;
use app\portal\service\PostService;
use app\portal\service\PostLogService;

class PageController extends HomeBaseController
{
    public function index()
    {
        $postService = new PostService();
        $pageId      = $this->request->param('id', 0, 'intval');
      
        $page        = $postService->publishedPage($pageId);

        $routeModel         = new RouteModel();
        $alias              = $routeModel->getUrl('portal/Page/index', ['id' => $pageId]);
        $page['post_alias'] = $alias;


        if (empty($page)) {
            abort(404, ' 页面不存在!');
        }
        $this->assign('article', $page);
        $this->assign('page', $page);

        $more = $page['more'];

        $tplName = empty($more['template']) ? 'page' : $more['template'];

        $is_admin = 0;
        if(cmf_get_current_admin_id())
        {
            $is_admin = 1;  //判断是否后台管理登陆
        }
        $this->assign('is_admin',$is_admin);
        return $this->fetch("/$tplName");
    }
    // 历史记录页面预览 

 
    public function Preview(){


        $postService = new PostLogService();
        $pageId      = $this->request->param('id', 0, 'intval');
        $page        = $postService->publishedPage($pageId);

        if (empty($page)) {
            abort(404, ' 页面不存在!');
        }

        $routeModel         = new RouteModel();
        $alias              = $routeModel->getUrl('portal/Page/index', ['id' => $pageId]);
        $page['post_alias'] = $alias;

        $this->assign('article', $page);
        $this->assign('page', $page);

        $more = $page['more'];

        $tplName = empty($more['template']) ? 'page' : $more['template'];

        $is_admin = 0;
//        if(cmf_get_current_admin_id())
//        {
//            $is_admin = 1;  //判断是否后台管理登陆    预览没有页面修改
//        }
        $this->assign('is_admin',$is_admin);
        return $this->fetch("/$tplName");





    }
}
