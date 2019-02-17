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
use app\portal\model\GoodsModel;
use app\portal\service\GoodsService;
use app\portal\model\GoodsCategoryModel;
use app\portal\model\PortalPostLogModel;
use think\Db;

class GoodsController extends AdminBaseController
{
    /**
     * 商品列表
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

        $postService = new GoodsService();
        $data        = $postService->adminArticleList($param);

        $data->appends($param);

        $portalCategoryModel = new GoodsCategoryModel();
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
     * 添加商品
     * @adminMenu(
     *     'name'   => '添加商品',
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
        //等级列表
        $where['status'] = 1;
        $where['post_status'] = 1;
        $agent_list = Db::name('fun_agent')->where($where)->select()->toArray();
        $this->assign('agent_list', $agent_list);
        return $this->fetch();
    }

    /**
     * 添加商品提交
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
            $agent_price = $post['agent_price'];
            $result = $this->validate($post, 'Goods');
            if ($result !== true) {
                $this->error($result);
            }

            $portalPostModel = new GoodsModel();

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

            $portalPostModel->adminAddArticle($data['post'], $data['post']['categories']);
            //向日志表添加 开始
            $data['post']['post_orderNumber']=$portalPostModel->id;
            $portalPostModellog->adminAddArticle($data['post'], $data['post']['categories']);
            //结束

            $goods_id = $portalPostModel->id;
            //更新商品等级价格表
            if(!empty($agent_price)){
                foreach($agent_price as $k=>$price){
                    unset($goods_price);
                    if(empty($price))
                        continue;
                    $goods_price = array(
                        'goodsid' =>$goods_id, 'rank_id' => $k, 'rank_price' =>$price,
                    );
                    Db::name('goods_price')->insert($goods_price);
                }
            }

            $data['post']['id'] = $portalPostModel->id;
            $hookParam          = [
                'is_add'  => true,
                'article' => $data['post']
            ];
            hook('portal_admin_after_save_article', $hookParam);

            $this->success('添加成功!', url('Goods/index', ['id' => $portalPostModel->id]));

        }

    }

    /**
     * 编辑商品
     * @adminMenu(
     *     'name'   => '编辑商品',
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

        $portalPostModel = new GoodsModel();
        $post            = $portalPostModel->where('id', $id)->find();
        $postCategories  = $post->categories()->alias('a')->column('a.name', 'a.id');
        $postCategoryIds = implode(',', array_keys($postCategories));
        //等级列表
        $where['status'] = 1;
        $where['post_status'] = 1;
        $agent_list = Db::name('fun_agent')->where($where)->select()->toArray();

        $this->assign('post', $post);
        $this->assign('goods_id', $id);//商品id
        $this->assign('post_categories', $postCategories);
        $this->assign('post_category_ids', $postCategoryIds);
        $this->assign('agent_list', $agent_list);//等级列表
        return $this->fetch();
    }

    /**
     * 编辑商品提交
     * @adminMenu(
     *     'name'   => '编辑商品提交',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '编辑商品提交',
     *     'param'  => ''
     * )
     */
    public function editPost()
    {

        if ($this->request->isPost()) {
            $data   = $this->request->param();

            $post   = $data['post'];
            $agent_price = $post['agent_price'];//商品等级价格
            $result = $this->validate($post, 'Goods');
            if ($result !== true) {
                $this->error($result);
            }

            $portalPostModel = new GoodsModel();

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
            $goods_id = $data['post']['id'];//商品id
            $data['post']['post_orderNumber']=$data['post']['id'];
            unset($data['post']['id']);
            //更新商品等级价格表
            if(!empty($agent_price)){
                foreach($agent_price as $k=>$price){
                    unset($goods_price);
                    if(empty($price))
                        continue;
                    $goods_price = array(
                        'goodsid' =>$goods_id, 'rank_id' => $k
                    );
                    $res = Db::name('goods_price')->where($goods_price)->find();
                    if(!empty($res)){
                        if($res['rank_price'] != $price){
                            Db::name('goods_price')->where('id', $res['id'])->update(['rank_price'=>$price]);
                        }
                    } else {
                        $goods_price['rank_price'] = $price;
                        Db::name('goods_price')->insert($goods_price);
                    }
                }
            }
            $idd=$portalPostModellog->adminAddArticle($data['post'], $data['post']['categories']);


            $hookParam = [
                'is_add'  => false,
                'article' => $data['post']
            ];
            hook('portal_admin_after_save_article', $hookParam);
            $this->success('保存成功!',url('portal/Goods/index'));
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
        $portalPostModel = new GoodsModel();

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
                Db::name('goods_category_link')->where(['post_id'=>$id])->update(['status'=>0]);
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
        $portalPostModel = new GoodsModel();

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
        $portalPostModel = new GoodsModel();

        if ( isset($param["yes"])) {
            $ids = $this->request->param('yes');

            $portalPostModel->where(['id' => $ids])->update(['post_status' => 1]);

            $this->redirect(url('goods/index'));

        }
        if (isset($param["no"])) {
            $ids = $this->request->param('no');

            $portalPostModel->where(['id' => $ids])->update(['post_status' => 0]);

            $this->redirect(url('goods/index'));

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
        parent::listOrders(Db::name('goods_category_link'));
        $this->success("排序更新成功！", '');
    }



}
