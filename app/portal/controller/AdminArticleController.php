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
use app\portal\model\PortalPostModel;
use app\portal\service\PostService;
use app\portal\model\PortalCategoryModel;
use app\portal\model\PortalPostLogModel;
use think\Db;
use app\admin\model\ThemeModel;

class AdminArticleController extends AdminBaseController
{
    /**
     * 文章列表
     * @adminMenu(
     *     'name'   => '文章管理',
     *     'parent' => 'portal/AdminIndex/default',
     *     'display'=> true,
     *     'hasView'=> true,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '文章列表',
     *     'param'  => ''
     * )
     */
    public function index()
    {
        $param = $this->request->param();

        $categoryId = $this->request->param('category', 0, 'intval');

        $postService = new PostService();
        $data        = $postService->adminArticleList($param);

        $data->appends($param);

        $portalCategoryModel = new PortalCategoryModel();
        $categoryTree        = $portalCategoryModel->adminCategoryTree($categoryId);

        $this->assign('start_time', isset($param['start_time']) ? $param['start_time'] : '');
        $this->assign('end_time', isset($param['end_time']) ? $param['end_time'] : '');
        $this->assign('keyword', isset($param['keyword']) ? $param['keyword'] : '');
        $this->assign('articles', $data->items());
        $this->assign('category_tree', $categoryTree);
        $this->assign('category', $categoryId);
        $this->assign('page', $data->render());


        return $this->fetch();
    }

    /**
     * 添加文章
     * @adminMenu(
     *     'name'   => '添加文章',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> true,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '添加文章',
     *     'param'  => ''
     * )
     */
    public function add()
    {
        $edit_mode = input('edit_mode'); //编辑模式
        if(!$edit_mode){
            return $this->view->fetch('add_back');
        }

        $themeModel        = new ThemeModel();
        $articleThemeFiles = $themeModel->getActionThemeFiles('portal/Article/index');
        $user_id=cmf_get_current_admin_id();
        $this->assign('article_theme_files', $articleThemeFiles);
        return $this->fetch();
    }

    /**
     * 添加文章提交
     * @adminMenu(
     *     'name'   => '添加文章提交',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '添加文章提交',
     *     'param'  => ''
     * )
     */
    public function addPost()
    {
        if ($this->request->isPost()) {
            $data   = $this->request->param();

            //状态只能设置默认值。未发布、未置顶、未推荐 
            $data['post']['post_status'] = 1; 
           

            $data['post']['is_top'] = 0;
            $data['post']['recommended'] = 0;

            $post   = $data['post'];

            $result = $this->validate($post, 'AdminArticle');
            if ($result !== true) {
                $this->error($result);
            }

            $portalPostModel = new PortalPostModel();

            $portalPostModellog= new PortalPostLogModel();//日志

            if (!empty($data['photo_names']) && !empty($data['photo_urls'])) {
                $data['post']['more']['photos'] = [];
                foreach ($data['photo_urls'] as $key => $url) {
                    $photoUrl = cmf_asset_relative_url($url);
                    array_push($data['post']['more']['photos'], ["url" => $photoUrl, "name" => $data['photo_names'][$key]]);
                }
            }

            if (!empty($data['file_names']) && !empty($data['file_urls'])) {
                $data['post']['more']['files'] = [];
                foreach ($data['file_urls'] as $key => $url) {
                    $fileUrl = $url === '|title' ? '|title' : cmf_asset_relative_url($url);
                    array_push($data['post']['more']['files'], ["url" => $fileUrl, "name" => $data['file_names'][$key],'icon' => $data['file_icons'][$key]]);
                }
            }

            //dump($data);die;
            $portalPostModel->adminAddArticle($data['post'], $data['post']['categories']);
             //向日志表添加 开始
             $data['post']['post_orderNumber']=$portalPostModel->id;
             $portalPostModellog->adminAddArticle($data['post'], $data['post']['categories']);
            //结束
            
            

            $data['post']['id'] = $portalPostModel->id;
            $hookParam          = [
                'is_add'  => true,
                'article' => $data['post']
            ];
            hook('portal_admin_after_save_article', $hookParam);
           
      

          $this->success('添加成功!', url('AdminArticle/index', ['id' => $portalPostModel->id]));

            
        }

    }

    /**
     * 编辑文章
     * @adminMenu(
     *     'name'   => '编辑文章',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> true,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '编辑文章',
     *     'param'  => ''
     * )
     */
    public function edit()
    {
        $id = $this->request->param('id', 0, 'intval');
        $edit_mode = input('edit_mode'); //编辑模式

        $portalPostModel = new PortalPostModel();
        $post            = $portalPostModel->where('id', $id)->find();
        $postCategories  = $post->categories()->alias('a')->column('a.name', 'a.id');
        $postCategoryIds = implode(',', array_keys($postCategories));

        $themeModel        = new ThemeModel();
        $articleThemeFiles = $themeModel->getActionThemeFiles('portal/Article/index');
        $this->assign('article_theme_files', $articleThemeFiles);
//        $post['post_content'] = html_entity_decode($post['post_content']);
//        dump($post);
//        die;
        $this->assign('post', $post);
        $this->assign('post_categories', $postCategories);
        $this->assign('post_category_ids', $postCategoryIds);
        if(!$edit_mode){
            $this->assign('id',$id);
            $this->assign('url',url('AdminArticle/edit').'?id='.$id);
            return $this->view->fetch('edit_back');
        }
        return $this->fetch();
    }

    /**
     * 编辑文章提交
     * @adminMenu(
     *     'name'   => '编辑文章提交',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '编辑文章提交',
     *     'param'  => ''
     * )
     */
    public function editPost()
    {

        if ($this->request->isPost()) {
            $data   = $this->request->param();
      
            //需要抹除发布、置顶、推荐的修改。
          /*  unset($data['post']['post_status']);*/
          if($data['post']['post_status']==2){

                $data['post']['post_status'] = 0;
                $post_status=1;
            }elseif($data['post']['post_status']==3){

                $data['post']['post_status'] = 0;
                $post_status=2;
            }else{

                $data['post']['post_status'] = 1;
                $post_status=1;
            }
            unset($data['post']['is_top']);
            unset($data['post']['recommended']);

            $post   = $data['post'];
            $result = $this->validate($post, 'AdminArticle');
            if ($result !== true) {
                $this->error($result);
            }

            $portalPostModel = new PortalPostModel();

            if (!empty($data['photo_names']) && !empty($data['photo_urls'])) {
                $data['post']['more']['photos'] = [];
                foreach ($data['photo_urls'] as $key => $url) {
                    $photoUrl = cmf_asset_relative_url($url);
                    array_push($data['post']['more']['photos'], ["url" => $photoUrl, "name" => $data['photo_names'][$key]]);
                }
            }

            if (!empty($data['file_names']) && !empty($data['file_urls'])) {
                $data['post']['more']['files'] = [];
                foreach ($data['file_urls'] as $key => $url) {
                    $fileUrl = $url === '|title' ? '|title' : cmf_asset_relative_url($url);
                    array_push($data['post']['more']['files'], ["url" => $fileUrl, "name" => $data['file_names'][$key],'icon' => $data['file_icons'][$key]]);
                }
            }

            if( $data['post']['post_status'] == 1){

                // $data['post']['published_time'] = time();
                 $portalPostModel->adminEditArticle($data['post'], $data['post']['categories']);
            }
            //像日志表添加纪录 cj 2018 7 11
            $portalPostModellog= new PortalPostLogModel();
            $data['post']['post_orderNumber']=$data['post']['id'];
            unset($data['post']['id']);

           $idd=$portalPostModellog->adminAddArticle($data['post'], $data['post']['categories']);


            $hookParam = [
                'is_add'  => false,
                'article' => $data['post']
            ];
            hook('portal_admin_after_save_article', $hookParam);

            if($post_status==2){
                //预览
                 $this->success('保存成功!',url('portal/article/preview',['id'=>$idd]),['cj_code'=>6,'edit_url'=>url('portal/article/preview',['id'=>$idd])]);

                }else{

                 $this->success('保存成功!',url('portal/AdminArticle/index'));
            }


        }
    }


    /*
    *
    * 发布旧版本
    *
    *
    *
    *
    */

    public function releaseversion(){

            $paramid=$this->request->param('id', 0, 'intval'); //历史版本表id
            $post_orderNumber=$this->request->param('post_orderNumber',0,'intval');//正式表里的id
            if($paramid==0||$post_orderNumber==0){

                $arr=['code'=>1,'msg'=>'参数错误'];
                return json($arr);exit;
             }
             Db::name('portal_post_log')->where(['post_orderNumber'=>$post_orderNumber])->update(['post_status'=>0]);

          
             //把历史版本表里的数据同步到正式表里
            $sqlshu=['user_id','post_title','post_keywords','post_excerpt','post_source','thumbnail','post_content','post_content_filtered','more','edit_mode','post_alias'];
            //历史版本
            $log=Db::name('portal_post_log')->where(['id'=>$paramid])->field(true)->find();
            //修改正式表里的数据

            foreach ($sqlshu as $k=>$v) {

                $updateshuju[$v]=$log[$v];

            }
            $updateshuju['post_status']=1;
            $updateshuju['log_id']=$paramid;
            $updateshuju['published_time'] = time();
            $updateshuju['update_time']=time();
            //修改正式表
            $data=Db::name('portal_post')->where(['id'=>$post_orderNumber])->update($updateshuju);
            //修改历史日志
            Db::name('portal_post_log')->where(['id'=>$paramid])->update(['post_status'=>1]);
                    
           /* foreach ($sqlshu as $k => $v) {

                $sql.=",`b`.`{$v}`=`a`.`{$v}`";
            }
           
            $sql.="WHERE `a`.`id` =".$paramid;
            $data=Db::execute($sql);*/
           
            if($data>0){

                $arr=['code'=>2,'msg'=>'更新成功'];
                return json($arr);exit;
               }else{

                $arr=['code'=>1,'msg'=>'更新失败'];
                return json($arr);exit;

            }

    }


    /**
     * 文章删除
     * @adminMenu(
     *     'name'   => '文章删除',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '文章删除',
     *     'param'  => ''
     * )
     */
    public function delete()
    {
        $param           = $this->request->param();
        $portalPostModel = new PortalPostModel();

        if (isset($param['id'])) {
            $id           = $this->request->param('id', 0, 'intval');
            $result       = $portalPostModel->where(['id' => $id])->find();
            $data         = [
                'object_id'   => $result['id'],
                'create_time' => time(),
                'table_name'  => 'portal_post',
                'name'        => $result['post_title'],
                'user_id'=>cmf_get_current_admin_id()
            ];
            $resultPortal = $portalPostModel
                ->where(['id' => $id])
                ->update(['delete_time' => time()]);
            if ($resultPortal) {
                Db::name('portal_category_post')->where(['post_id'=>$id])->update(['status'=>0]);
                Db::name('portal_tag_post')->where(['post_id'=>$id])->update(['status'=>0]);

                Db::name('recycleBin')->insert($data);
            }
            $this->success("删除成功！", '');

        }

        if (isset($param['ids'])) {
            $ids     = $this->request->param('ids/a');
            $recycle = $portalPostModel->where(['id' => ['in', $ids]])->select();
            $result  = $portalPostModel->where(['id' => ['in', $ids]])->update(['delete_time' => time()]);
            if ($result) {
                Db::name('portal_category_post')->where(['post_id' => ['in', $ids]])->update(['status'=>0]);
                Db::name('portal_tag_post')->where(['post_id' => ['in', $ids]])->update(['status'=>0]);
                foreach ($recycle as $value) {
                    $data = [
                        'object_id'   => $value['id'],
                        'create_time' => time(),
                        'table_name'  => 'portal_post',
                        'name'        => $value['post_title'],
                        'user_id'=>cmf_get_current_admin_id()
                    ];
                    Db::name('recycleBin')->insert($data);
                }
                $this->success("删除成功！", '');
            }
        }
    }

    /**
     * 文章发布
     * @adminMenu(
     *     'name'   => '文章发布',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '文章发布',
     *     'param'  => ''
     * )
     */
    public function publish()
    {
        $param           = $this->request->param();
        $portalPostModel = new PortalPostModel();

        if (isset($param['ids']) && isset($param["yes"])) {
            $ids = $this->request->param('ids/a');

            //$portalPostModel->where(['id' => ['in', $ids]])->update(['post_status' => 1, 'published_time' => time()]);
            //cj 修改 如果在列表页发布成功  修改日志表里的 状态
            $portalPostModel->alias('a')->join('__PORTAL_POST_LOG__ b','a.log_id=b.id')->where(['a.id'=>['in',$ids]])->update(['a.post_status'=>1,'a.published_time'=>time(),'b.post_status'=>1]);


            $this->success("发布成功！", '');
        }

        if (isset($param['ids']) && isset($param["no"])) {
            $ids = $this->request->param('ids/a');

            
            $portalPostModel->alias('a')->join('__PORTAL_POST_LOG__ b','a.log_id=b.id')->where(['a.id'=>['in',$ids]])->update(['a.post_status'=>0,'b.post_status'=>0]);
            

            $this->success("取消发布成功！", '');
        }

    }

    /**
     * 文章置顶
     * @adminMenu(
     *     'name'   => '文章置顶',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '文章置顶',
     *     'param'  => ''
     * )
     */
    public function top()
    {
        $param           = $this->request->param();
        $portalPostModel = new PortalPostModel();

        if (isset($param['ids']) && isset($param["yes"])) {
            $ids = $this->request->param('ids/a');

            $portalPostModel->where(['id' => ['in', $ids]])->update(['is_top' => 1]);

            $this->success("置顶成功！", '');

        }

        if (isset($_POST['ids']) && isset($param["no"])) {
            $ids = $this->request->param('ids/a');

            $portalPostModel->where(['id' => ['in', $ids]])->update(['is_top' => 0]);

            $this->success("取消置顶成功！", '');
        }
    }

    /**
     * 文章推荐
     * @adminMenu(
     *     'name'   => '文章推荐',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '文章推荐',
     *     'param'  => ''
     * )
     */
    public function recommend()
    {
        $param           = $this->request->param();
        $portalPostModel = new PortalPostModel();

        if (isset($param['ids']) && isset($param["yes"])) {
            $ids = $this->request->param('ids/a');

            $portalPostModel->where(['id' => ['in', $ids]])->update(['recommended' => 1]);

            $this->success("推荐成功！", '');

        }
        if (isset($param['ids']) && isset($param["no"])) {
            $ids = $this->request->param('ids/a');

            $portalPostModel->where(['id' => ['in', $ids]])->update(['recommended' => 0]);

            $this->success("取消推荐成功！", '');

        }
    }

    /**
     * 文章排序
     * @adminMenu(
     *     'name'   => '文章排序',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '文章排序',
     *     'param'  => ''
     * )
     */
    public function listOrder()
    {
        parent::listOrders(Db::name('portal_category_post'));
        $this->success("排序更新成功！", '');
    }

    public function move()
    {

    }

    public function copy()
    {

    }

    /*历史版本*/
    public function historic(){

        //版本号 数据库没有存  是在页面自动计算
        $param= $this->request->param();

         $portalPostModellog= new PortalPostLogModel();
         $data=$portalPostModellog->historic($param);
       //  dump($data);
       //  dump($data->currentPage());
        // dump($data->render());
        // exit;
        // $this->assign('currentPage',$data->currentPage());//当前页
        // $this->assign('listRows',$data->listRows());//每页的数量
         $this->assign('data',$data);
         $this->assign('page', $data->render());
         return $this->fetch();
    }

}
