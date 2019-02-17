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

use cmf\controller\HomeBaseController;
use think\Db;

class IndexController extends HomeBaseController
{
    public function index()
    {
        return $this->fetch(':index');
    }

    public function ossTest()
    {
        if (request()->isPost()) {
            $file = input('file.file');
            $dir = date('Ym', time());
            $name = date('dHis', time()) . rand(10000, 99999);
            // 移动到框架应用根目录/public/upload/ 目录下,文件命名格式为：年月/日时分秒三位随机数
            $info = $file->move(ROOT_PATH . 'public/upload', $dir . '/' . $name);
            $res = upFileToOss('./upload/' . $info->getSaveName(), true);
            dump($res);
            exit;
        }

        return '<form method="post" enctype="multipart/form-data"><input type="file" name="file"><input type="submit"></form>';
    }

    public function smsTest()
    {
        $res = sendSms('18663769384',['code'=>1234,'product'=>'沐宜科技']);
        dump($res);
    }

    public function videoTc()
    {
        // $data = file_get_contents('php://input');
        // Db::name('option')->insert(['option_name'=>time().rand(0,999),'option_value'=>$data]);
    }

    public function emailTest()
    {
        $res = sendEmail(['wangshuo@mofyi.com'],'测试一下发邮件的接口好不好用','<b>aaaaaa<b>aaaa',1,'伊泽瑞尔');
        dump($res);
    }
}
