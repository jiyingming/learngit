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
use think\Cookie;
use think\Request;

class ExpressController extends HomeBaseController
{
    //快递查询(快宝开放平台 参考地址：https://open.kuaidihelp.com/api/1003)
    public function index()
    {
        $olnum = $this->request->param('olnum');
        if(empty($olnum)){
            echo "<script>alert('参数错误');window.location.href='/distribution/index'</script>";
            exit();
        }
        $good_order = Db::name('fun_goodsorder')->where('orderlistnum', $olnum)->find();
        $waybill_no = $good_order['postid'];//快递单号
        $brand = Db::name('fun_express')->where('name', $good_order['post_name'])->value('tag');//品牌
        $host = "https://kop.kuaidihelp.com/api";
        $method = "POST";
        $headers = array();
        //根据API的要求，定义相对应的Content-Type
        array_push($headers, "Content-Type".":"."application/x-www-form-urlencoded; charset=UTF-8");
        $querys = "";
        $time = time();
        $app_id = '102073';
        $api_name = 'express.info.get';
        $api_key  = "fcf03e3b833275f89186642f7c0cdbac0b5f9c4e";//API key
        //按照规则(md5(app_id + method + ts + api_key))生成的验证合法性签名
        $sign = md5($app_id . $api_name . "$time" . $api_key);
        $bodys = [
            "app_id"=>$app_id,//快宝开放平台用户ID
            "method"=>$api_name,
            "sign"=>$sign,
            "ts"=>"$time",
            "data"=>'{ "waybill_no":"'.$waybill_no.'", "exp_company_code":"'.$brand.'","result_sort":"0"}'
        ];
        $bodys = http_build_query($bodys);
        $url = $host;
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, true);
        if (1 == strpos("$".$host, "https://"))
        {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        }
        curl_setopt($curl, CURLOPT_POSTFIELDS, $bodys);
        $result = curl_exec($curl);
        $result = substr($result,strpos($result,'{'));
        $data = $res = [];
        if($result){
            $express = json_decode($result, true);
            $res['code'] = $express['code'];
            $res['msg'] = $express['msg'];
            if($express['code'] == 0){
                $data = $express['data'];
            }
        }
        $this->assign('data', $data);
        $this->assign('good_order', $good_order);//是否查询到数据
        $this->assign('res', $res);//是否查询到数据
        return $this->fetch(':express/index');
    }

}
