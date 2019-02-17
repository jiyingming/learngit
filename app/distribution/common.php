<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2018 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +---------------------------------------------------------------------
// | Author: Dean <zxxjjforever@163.com>
// +----------------------------------------------------------------------
use think\Config;
use think\Db;
use think\Url;
use dir\Dir;
use think\Route;
use think\Loader;
use think\Request;
use cmf\lib\Storage;

/**
 * 上传单个图片
 * @param $file
 * @return array
 */
function createHeadImg($filename, $headimgurl){
    if(!file_exists($filename)) {
        // 第一次生成二维码
        $local_file = fopen($filename, 'w');
        fwrite($local_file, file_get_contents($headimgurl));
        fclose($local_file);
        return $filename;
    }
}
/*
 * @ CURL--POST请求方式
 */
function curl_post($data, $url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    $result = curl_exec($ch);
    //var_dump($result);
    curl_close($ch);
    return json_decode($result,true);
}

function getBonus($id){
    $result = Db::name('user_favorite')->field('id,money,scale,bonus')->where('id', $id)->find();
    return $result;
}
function getBonusMoney($id){
    $result = Db::name('user_favorite')->where('id', $id)->value('money') * 10000;
    return $result;
}
function getBonusSelf($id){
    $result = Db::name('user_favorite')->where('id', $id)->value('bonus');
    return $result;
}

/**
 * 获取团队分红比例
 * @param  $money  订单金额
 * @return  array
 */
function getBonusInfo($money){

    switch($money) {
        case $money < getBonusMoney(1) :
            $bonus = 0;
        break;
        case  $money < getBonusMoney(2) :
            $bonus = getBonusSelf(1);
        break;
        case  $money < getBonusMoney(3) :
            $bonus = getBonusSelf(2);
        break;
        case  $money < getBonusMoney(4) :
            $bonus = getBonusSelf(3);
        break;
        case  $money < getBonusMoney(5) :
            $bonus = getBonusSelf(4);
        break;
        case  $money < getBonusMoney(6) :
            $bonus = getBonusSelf(5);
        break;
        case  $money < getBonusMoney(7) :
            $bonus = getBonusSelf(6);
        break;
        case  $money < getBonusMoney(8) :
            $bonus = getBonusSelf(7);
        break;
        case  $money < getBonusMoney(9) :
            $bonus = getBonusSelf(8);
        break;
        case  $money < getBonusMoney(10) :
            $bonus = getBonusSelf(9);
        break;
        case  $money < getBonusMoney(11) :
            $bonus = getBonusSelf(10);
        break;
        case  $money < getBonusMoney(12) :
            $bonus = getBonusSelf(11);
        break;
        case  $money < getBonusMoney(13) :
            $bonus = getBonusSelf(12);
        break;
        case  $money < getBonusMoney(14) :
            $bonus = getBonusSelf(13);
        break;
        default:
            $bonus = 0;
        break;
    }
    return $bonus;

}
/*
*功能：php完美实现下载远程图片保存到本地
*参数：文件url,保存文件目录,保存文件名称，使用的下载方式
*当保存文件名称为空时则使用远程文件原来的名称
*/
function getImage($url,$save_dir='',$filename='',$type=0){
    if(trim($url)==''){
        return array('file_name'=>'','save_path'=>'','error'=>1);
    }
    if(trim($save_dir)==''){
        $save_dir='./';
    }
    if(trim($filename)==''){//保存文件名
        $ext=strrchr($url,'.');
        if($ext!='.gif'&&$ext!='.jpg'){
            return array('file_name'=>'','save_path'=>'','error'=>3);
        }
        $filename=time().$ext;
    }
    if(0!==strrpos($save_dir,'/')){
        $save_dir.='/';
    }
    //创建保存目录
    if(!file_exists($save_dir)&&!mkdir($save_dir,0777,true)){
        return array('file_name'=>'','save_path'=>'','error'=>5);
    }
    //获取远程文件所采用的方法
    if($type){
        $ch=curl_init();
        $timeout=5;
        curl_setopt($ch,CURLOPT_URL,$url);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,$timeout);
        $img=curl_exec($ch);
        curl_close($ch);
    }else{
        ob_start();
        readfile($url);
        $img=ob_get_contents();
        ob_end_clean();
    }
    //$size=strlen($img);
    //文件大小
    $fp2=@fopen($save_dir.$filename,'a');
    fwrite($fp2,$img);
    fclose($fp2);
    unset($img,$url);
    return array('file_name'=>$filename,'save_path'=>$save_dir.$filename,'error'=>0);
}


/**
 * 上传单个图片
 * @param $file
 * @return array
 */
function up_file($file, $prefix = 'upload', $rule = '')
{
    $file = request()->file($file);
    $result = $file->validate([
        'ext' => 'gif,jpg,jpeg,bmp,png',
        'size' => 41943040
    ])->rule(function ($file) {
        return md5(mt_rand());
    })->move('.' . DS . $prefix . DS);

    if ($result) {
        $avatarSaveName = str_replace('//', '/', str_replace('\\', '/', $result->getSaveName()));
        $avatar = '/' . $prefix . '/' . $avatarSaveName;

        $oss_file = curl_file('.' . $avatar);
        $return_file = '';

        if ($oss_file !== 0)  //上传成功就使用oss地址
        {
            unset($result);
            unlink('.' . $avatar);
            $return_file = $oss_file;
        } else {
            unlink('.' . $avatar);
            return [
                'code' => 0,
                "msg" => '上传失败',
                "data" => $oss_file, //上传失败返回 curl上传返回信息
                "url" => ''
            ];
        }
        return [
            'code' => 1,
            "msg" => lang('SUCCESS'),
            "data" => ['file' => $return_file],
            "url" => $return_file
        ];
    } else {
        return [
            'code' => 0,
            "msg" => $file->getError(),
            "data" => "",
            "url" => ''
        ];
    }
}


/**
 * curl 上传文件
 * @param $file //图片地址
 * @return mixed
 */
function curl_file($file)
{
    $data = array('file_data' => new \CURLFile(realpath($file)));//>=5.5


    $url = "https://www.mofyi.com/tool/uploader/fileup";  //接受上传文件地址

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_SAFE_UPLOAD, true);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    $result = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    $decode = json_decode($result, true);
    if (isset($decode['success_url'])) {
        return $decode['success_url'];
    } else {
        return 0;
    }
}


/**
 * 把数组按个数再次分组
 * @param array $array 传入的数组
 * @param int $length 多少个分为一组
 * @return array 返回处理完的数组
 */
function cut_array($array = array(),$length = 1)
{
    $count = ceil(count($array));
    $result = array();
    for($i = 0;$i < $count;$i++)
    {
        $result[] = array_slice($array,$i *$length,$length);
    }
    return array_filter($result);
}


function getJoinUser($mobile = 0)
{
    $eid = \think\Config::get('MEETING_ID');
    $url = "http://openapi.31huiyi.com/rest/event/{$eid}/getjoinuser";
    $auth = getAuth($url);
    $post = [
        "RealName" => "",
        "Mobile" => $mobile,
        "Email" => "",
        "JoinId" => 0,
        "SignInCode" => "",
        "WeiXinOpenId" => ""
    ];
    $result = curl_request($url, json_encode($post), 'json', $auth);
    $resultData = json_decode($result, true);
    return $resultData;
}
/**
 * 获取用户提交授权
 * @param $url 待授权的URL地址
 * @return array
 */
function getAuth($url)
{

    $guid = create_guid();

    $guid = str_replace('}', '', str_replace('{', '', $guid));

    $str = '{"APPID":320447810,"httpMethod":"POST","ClientIP":"","WebRequestGuid":"' . date('YndHis', time()) . ':' . $guid . '","AppRequestGuid":null,"RequestURL":"' . $url . '","WebRequestURL":null,"WebRequestUrlReferrer":null,"APPAuth":""}';

    $new_str = md5($str . 'waibaokaifashanglidecheng_31huiyi.com');

    $str = str_replace('"}', $new_str, $str);
    $str = $str . '"}';

    $host = array(
        "X_31HuiYi_LoginCookie:",
        "X_31HuiYi_AppAuth:" . $str,
        "Content-Type:application/json"
    );

    return $host;
}
function create_guid() {
    $charid = strtoupper(md5(uniqid(mt_rand(), true)));
    $hyphen = chr(45);// "-"
    $uuid = chr(123)// "{"
        .substr($charid, 0, 8).$hyphen
        .substr($charid, 8, 4).$hyphen
        .substr($charid,12, 4).$hyphen
        .substr($charid,16, 4).$hyphen
        .substr($charid,20,12)
        .chr(125);// "}"
    return $uuid;
}
