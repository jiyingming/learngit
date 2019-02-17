<?php
namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use app\admin\model\HookModel;
use app\admin\model\PluginModel;
use app\admin\model\HookPluginModel;
use think\Db;

/**
 * 编辑器图片上传控制器
 * @package app\asdf\controller
 */
class UploadController extends AdminBaseController
{
    public function uploadImages(){
        $file = request()->file('upload');
        if ($file) {
            $dir = date('Ym', time());
            $name = date('dHis', time()) . rand(10000, 99999);
            // 移动到框架应用根目录/public/upload/ 目录下,文件命名格式为：年月/日时分秒三位随机数
            $info = $file->move(ROOT_PATH . 'public/upload', $dir . '/' . $name);
            $res = upFileToOss('./upload/' . $info->getSaveName());
            if ($res['status'] == 10) {
                return json_encode(['uploaded'=>true,'msg'=>'上传成功','file_path'=>$res['url']]);
            } else {
                return json(['uploaded'=>false,'msg'=>'上传失败','file_path'=>'']);
            }
        }
    }

}