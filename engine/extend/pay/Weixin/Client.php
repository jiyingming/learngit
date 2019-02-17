<?php

namespace pay\Weixin;
use pay\Exceptions\GatewayException;
use pay\Exceptions\InvalidArgumentException;
use pay\Exceptions\InvalidConfigException;
use pay\HttpRequest;
use pay\Weixin;

/**
 * 微信客户端
 * Class Client
 * @package pay\Weixin
 */
class Client
{
    use HttpRequest;
    /**
     * 客户端实例对象
     * @var
     */
    private static $instance;
    /**
     * 固定网关地址
     * @var string
     */
    protected $baseUri='https://api.mch.weixin.qq.com';

    /**
     * 获取客户端对象实例
     * @return Client
     */
    public static function getInstance()
    {
        if (!(self::$instance instanceof self)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * 获取支付宝默认网关地址
     * @param null $mode 网关模式 默认为正式网关，dev为开发网关
     * @return mixed 支付宝的网关地址
     */
    public static function getBaseUri($mode=null){
        switch ($mode) {
            case Weixin::MODE_HK:
                self::getInstance()->baseUri = 'https://apihk.mch.weixin.qq.com';
                break;
            case Weixin::MODE_US:
                self::getInstance()->baseUri = 'https://apius.mch.weixin.qq.com';
                break;
            default:
                break;
        }

        return self::getInstance()->baseUri;
    }

    /**
     * 生成微信签名
     * @param array $params 待签名的参数
     * @param null $key 商户平台设置的密钥
     * @param string $sign_type 签名类型 默认md5
     * @return string 签名后的字符串
     * @throws InvalidArgumentException 无效参数
     * @throws InvalidConfigException 无效配置
     */
    public static function generateSign(array $params,$key=null,$sign_type = 'MD5'){
        if (empty($key)){
            throw new InvalidConfigException('请检查微信配置，商户密钥 -- [key]',1001);
        }
        //获取待签名参数
        $signContent = self::getSignContent($params);

        if (empty($signContent)){
            throw new InvalidArgumentException('无效的待签名参数，param 不允许为空！',1002);
        }

        $signContent .= 'key=' . $key;
        $sign = strtoupper($sign_type) ==='MD5' ? md5($signContent) : hash_hmac('sha256',$signContent,$key);

        return strtoupper($sign);
    }
    /**
     * 组合待签名的参数 post方式
     * @param array $params 参数数组
     * @return string 组合后的参数
     */
    public static function getSignContent(array $params){

        ksort($params);

        $stringToBeSigned = '';
        foreach ($params as $k => $v){
            if (!empty($k) && !empty($v) && $k!='sign' && !is_array($v)){
                $stringToBeSigned .= $k . '=' . $v . '&';
            }
        }

        return $stringToBeSigned;
    }

    /**
     * 将数组转换为xml
     * @param $params 待转换的数组
     * @return string 转换后的XML
     * @throws InvalidArgumentException 无效参数
     */
    public static function toXML($params){
        if (!is_array($params) || count($params) < 1){
            throw new InvalidArgumentException('将参数数组转换为XML失败，因为参数params 数组为空！',1002);
        }

        $xml = '<xml>';
        foreach ($params as $k => $v){
            if (is_numeric($v)){
                $xml .= '<' . $k . '>' . $v . '</' . $k . '>';
            }else{
                $xml .= '<' . $k . '><![CDATA[' . $v . ']]></' . $k . '>';
            }
        }

        $xml .= '</xml>';

        return $xml;
    }

    /**
     * 将XML转换为数组
     * @param $xml 待转换的XML
     * @return mixed
     * @throws InvalidArgumentException
     */
    public static function fromXML($xml){
        if (empty($xml)){
            throw new InvalidArgumentException('将XML转换为数组失败，因为参数xml为空！',1002);
        }
        //禁止引用外部xml实体
       $disableLibxmlEntityLoader = libxml_disable_entity_loader(true);

        $values= json_decode(json_encode(simplexml_load_string($xml,'SimpleXMLElement',LIBXML_NOCDATA),JSON_UNESCAPED_UNICODE),true);
        libxml_disable_entity_loader($disableLibxmlEntityLoader); //添加这句

        return $values;
    }

    /**
     * 通过curl 请求API接口
     * @param $requestUrl 请求URL
     * @param array $params 请求参数
     * @param null $key 商户平台设置的密钥
     * @param string $sign_type 签名类型 默认MD5
     * @param string $apiclient_cert 证书pem格式
     * @param string $apiclient_key 证书密钥pem格式
     * @return array|mixed|string
     * @throws GatewayException
     */
    public static function requestApi($requestUrl,array $params,$key = null,$sign_type = 'MD5',$apiclient_cert = null,$apiclient_key = null){
        trace('请求微信订单，请求地址：' . self::getInstance()->getBaseUri() . $requestUrl . '  ，参数：' . json_encode($params),'info');

        //请求接口
        $result = self::getInstance()->post(self::getInstance()->getBaseUri() . $requestUrl,self::toXML($params),($apiclient_cert !== null && $apiclient_key !== null) ?['cert' => $apiclient_cert,'ssl_key' => $apiclient_key] : []);

        $result = is_array($result) ? $result : self::fromXML($result);

        if (!isset($result['return_code']) || $result['return_code'] != 'SUCCESS' || $result['result_code'] != 'SUCCESS'){
            throw new GatewayException('调用微信接口异常，' . $result['return_msg'] . $result['err_code_des'],1006,$result);
        }
        //验证签名
        if (self::generateSign($result,$key,$sign_type) === $result['sign']){
            return $result;
        }
        trace('请求微信订单失败，请求结果：' . $result,'notice');

        throw new InvalidSignException('微信无效签名', 1005, $result);
    }

    /**
     * 过滤接口多余项
     * @param $params 公共参数
     * @param $order 订单参数
     * @param $key 商户平台设置的密钥
     * @param $sign_mode 加密方式
     * @return array 带有签名的参数
     */
    public static function filter($params,$order,$key,$sign_mode,$type = 0){
        //将参数合并
        $params = array_merge($params,is_array($order) ? $order : ['out_trade_no'=>$order]);
        //删除多余项
        unset($params['trade_type'],$params['spbill_create_ip'],$params['scene_info']);
        if ($type === 0){
            unset($params['notify_url']);
        }
        //获取签名
        $params['sign'] = Client::generateSign($params,$key,$sign_mode);

        return $params;
    }
}