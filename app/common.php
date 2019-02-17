<?php
// 加载阿里云sms sdk
use mofyi\aliyun\Sms;
// 加载阿里云oss sdk
require_once EXTEND_PATH . 'mofyi/aliyun/oss/autoload.php';
// 加载阿里云dm sdk
require_once EXTEND_PATH . 'mofyi/aliyun/aliyun-php-sdk-core/Config.php';
use Dm\Request\V20151123 as Dm;

use think\Db;

/**
 * 发送短信
 * @param  array $addresses     收件邮箱地址
 * @param  str $subject         邮件标题
 * @param  str $body            邮件正文
 * @param  int $body_type       邮件正文类型，如果1则为html否则为text
 * @param  str $from_alias      发件人昵称
 * @return array                发送成功status为10，发送失败msg为失败信息
 */
function sendEmail(array $addresses,  $subject,  $body,  $body_type = 1,  $from_alias = '')
{
    if (!is_array($addresses)) {
        return ['status' => 0, 'msg' => '收件地址必须为数据'];
    } elseif (count($addresses) > 100) {
        return ['status' => 1, 'msg' => '收件地址不能多于100个'];
    } else {
        $addresses = implode(',', $addresses);
    }

    $email_config = cmf_get_option('email_setting');

    $iClientProfile = DefaultProfile::getProfile("cn-hangzhou", $email_config['accessKeyId'], $email_config['accessKeySecret']);
    $client = new DefaultAcsClient($iClientProfile);
    $request = new Dm\SingleSendMailRequest();

    // 发件人地址，需在阿里云后台配置好
    $request->setAccountName($email_config['account_name']);
    // 发件人昵称，长度小于15个字符
    $request->setFromAlias($from_alias);
    // 0 为随机账号；1 为发信地址
    $request->setAddressType(1);
    // 是否使用阿里云后台配置的回信地址
    $request->setReplyToAddress(true);
    // 收件人地址，多个收件人之间用,隔开，最多可100个地址
    $request->setToAddress($addresses);
    // 邮件标题
    $request->setSubject($subject);
    if ($body_type == 1) {
        // 邮件html正文，限制28k
        $request->setHtmlBody($body);
    } else {
        // 邮件text正文,限制28k
        $request->setTextBody($body);
    }

    try {
        $response = $client->getAcsResponse($request);
        return ['status' => 10, 'msg' => '邮件发送成功'];
    } catch (ClientException $e) {
        return ['status' => 2, 'msg' => $e->getErrorMessage()];
    } catch (ServerException $e) {
        return ['status' => 3, 'msg' => $e->getErrorMessage()];
    }
}

/**
 * 发送短信
 * @param  int $mobile      手机号
 * @param  array $content   模板中设置的变量，变量名必须跟阿里云后台对应
 * @return array            发送成功status为10，发送失败msg为失败信息
 */
function sendSms($mobile, $content)
{
    try {
        $response = Sms::sendSms($mobile, $content);
    } catch (\Exception $e) {
        return ['status' => 0, 'msg' => $e->getMessage()];
    }

    if ($response->Code == 'OK') {
        return ['status' => 10, 'msg' => ''];
    } else {
        return ['status' => 0, 'msg' => $response->Message];
    }
}

/**
 * 批量发送短信
 * @param  array $mobiles       手机号数组
 * @param  int   $code          验证码
 * @return array                发送成功status为10，发送失败msg为失败信息
 */
function sendBatchSms($mobiles, $code)
{
    try{
        $response = Sms::sendBatchSms($mobiles, $code);
    } catch(\Exception $e) {
        return ['status'=>0,'msg'=>$e->getMessage()];
    }

    if ($response->Code == 'OK') {
        return ['status'=>10,'msg'=>''];
    } else {
        return ['status'=>0,'msg'=>$response->Message];
    }
}

/**
 * 短信发送记录查询
 * @param  int $mobile      手机号
 * @param  str $date        日期，格式为'20180904'
 * @param  int $date        页码
 * @return array            查询成功status为10，msg为信息
 */
function querySendDetails($mobile, $date, $page = 1)
{
    try{
        $response = Sms::querySendDetails($mobile, $date, $page);
    } catch(\Exception $e) {
        return ['status'=>0,'msg'=>$e->getMessage()];
    }

    if ($response->Code == 'OK') {
        return ['status'=>10,'msg'=>'success','data'=>$response->SmsSendDetailDTOs];
    } else {
        return ['status'=>0,'msg'=>$response->Message];
    }
}
/**
 * 创建随机数
 * @param int $length 随机数的长度  默认为32
 * @return string 生成后的随机数
 */
function random($length=32){
    $string = '';

    while (($len = strlen($string)) < $length) {
        $size = $length - $len;

        $bytes = random_bytes($size);

        $string .= substr(str_replace(['/', '+', '='], '', base64_encode($bytes)), 0, $size);
    }

    return $string;
}

/**
 * 上传文件到oss接口
 * @param  str $file      要上传的文件路径
 * @param  str $delete_after_up 上传成功后是否删除源文件
 * @return array          上传成功status为10，url为文件地址，上传失败msg为失败信息
 */
function upFileToOss($file, $delete_after_up = false)
{
    if (!is_file($file)) {
        return ['status' => 0, 'msg' => '没有文件', 'url' => ''];
    }

    // 获取小写后缀名
    $fe = pathinfo($file)['extension'];
    $fe = strtolower($fe);

    // 限制上传文件类型
    if (in_array($fe, ['exe'])) {
        return ['status' => 0, 'msg' => '不允许上传此类型文件', 'url' => ''];
    }

    $oss_config = cmf_get_option('oss_setting');

    if ($oss_config['is_split']) {
        // 按文件类型区分bucket
        if (in_array($fe, ['jpg', 'jpeg', 'png', 'gif'])) {
            // 图片
            $bucket = $oss_config['bucket']['img'];
            $endpoint = $oss_config['endpoint']['img'];
            $domain = $oss_config['domain']['img'];
            $type = 'img';
        } elseif (in_array($fe, ['mp4', 'flv', 'wmv', 'avi'])) {
            // 视频
            $bucket = $oss_config['bucket']['video'];
            $endpoint = $oss_config['endpoint']['video'];
            $domain = $oss_config['domain']['video'];
            $type = 'video';
        } else {
            // 其他文件
            $bucket = $oss_config['bucket']['file'];
            $endpoint = $oss_config['endpoint']['file'];
            $domain = $oss_config['domain']['file'];
            $type = 'file';
        }
    } else {
        // 不区分bucket
        $bucket = $oss_config['bucket']['all'];
        $endpoint = $oss_config['endpoint']['all'];
        $domain = $oss_config['domain']['all'];
    }

    $dir = date('Ym', time());
    $name = date('dHis', time()) . rand(10000, 99999);
    $oss_filename = $dir . '/' . $name . '.' . $fe;

    try {
        // 实例化一个oss客户端类
        $oss_client = new \OSS\OssClient($oss_config['accessKeyId'], $oss_config['accessKeySecret'], $endpoint);
        // 调用oss上传
        $oss_client->uploadFile($bucket, $oss_filename, $file);
        // 上传成功
        if ($delete_after_up) {
            @unlink($file);
        }
        return ['status' => 10, 'msg' => '上传成功', 'url' => $domain . $oss_filename];
    } catch (\Exception $e) {
        // 上传失败
        return ['status' => 0, 'msg' => '上传失败,' . $e->getMessage(), 'url' => ''];
    }
}

/**
 * 获取当前已有的语言
 * @return array field：字段名；name：语言名称；file：语言包文件名；key：在lang_list中的键值
 */
function getLangField()
{
    $fields = Db::getTableInfo(config('database.prefix') . 'lang', 'fields');
    $lang_list = config('lang_list');

    foreach ($fields as $k => $v) {
        // 去掉不是语言的字段
        if ($v == 'id' || $v == 'name' || $v == 'lang_range' || $v == 'status' || $v == 'add_time') {
            unset($fields[$k]);
            continue;
        }

        // 去掉语言字段前面的'lang_'
        $key = substr($v, 5);
        $fields[$k] = ['field' => $v, 'name' => $lang_list[$key]['name'], 'file' => $lang_list[$key]['file'], 'key' => $key];
    }

    return $fields;
}

