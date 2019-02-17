<?php

namespace pay;

use pay\Exceptions\GatewayException;
use pay\Exceptions\InvalidSignException;
use pay\Weixin\Client;
use think\helper\Str;

/**
 * 微信接口
 * @method \pay\Weixin\WapGateway wap(array $config) 手机网页支付
 * @method \pay\Weixin\ScanGateway scan(array $config) 手机网页支付
 * Class Weixin
 * @package pay
 */
class Weixin implements GatewayInterface
{
    /**
     * 正常模式 境内接入点
     */
    const MODE_NORMAL='normal';
    /**
     * 东南亚接入点 香港
     */
    const MODE_HK='hk';
    /**
     * 其它接入点
     */
    const MODE_US='us';
    /**
     * 配置信息
     * @var
     */
    protected $config;
    /**
     * 微信网关
     * @var
     */
    protected $gateway;
    /**
     * 支付宝接口公共参数
     * @var
     */
    protected $publicParams;
    /**
     * 签名方式
     */
    const SIGN_TYPE_MD5='MD5';
    const SIGN_TYPE_HMAC='HMAC-SHA256';
    /**
     * 签名方式
     * @var string
     */
    protected $sign_mode='MD5';
    /**
     * 初始化微信支付
     * Weixin constructor.
     * @param $config 微信配置信息
     */
    public function __construct($config)
    {
        $this->config = $config;
        if (!isset($this->config['mode'])){
            $this->config['mode'] = self::MODE_NORMAL;
        }
        $this->gateway = Client::getBaseUri($this->config['mode']);
        $this->publicParams = [
            //公众账号ID
            'appid'             => $config['app_id'],
            //商户号
            'mch_id'            => $config['mch_id'],
            //随机字符串
            'nonce_str'         => random(),
            //签名
            'sign'              => '',
            //通知地址
            'notify_url'        => $config['notify_url'],
            //交易类型
            'trade_type'        =>'',
            //终端IP
            'spbill_create_ip'  =>request()->ip()
        ];
        //签名类型
        if (isset($this->config['sign_type'])){
            $this->sign_mode=$this->publicParams['sign_type'] = $this->config['sign_type'] === 1 ? self::SIGN_TYPE_MD5 : self::SIGN_TYPE_HMAC;
        }

    }
    /**
     * 支付
     * @param 支付相关类 $gateway 相关支付操作类
     * @param array $params 支付请求参数
     * @return mixed
     * @throws GatewayException
     */
    public function pay($gateway, $params)
    {
        //合并参数
        $this->publicParams = array_merge($this->publicParams,$params);
        //组合调用相应的支付类
        $gateway = get_class($this) . '\\' . Str::studly($gateway) . 'Gateway';
        //检查类是否存在
        if (class_exists($gateway)){
            //创建支付类
            return $this->makePay($gateway);
        }
        throw new GatewayException('支付' . $gateway . '不存在。',1004);
    }
    /**
     * 创建相关支付接口
     * @param $gateway 待创建的支付接口
     * @return mixed
     * @throws GatewayException 支付接口创建异常
     */
    protected function makePay($gateway){
        $app = new $gateway($this->config);
        if ($app instanceof GatewayPayInterface){
            return $app->pay($this->gateway,$this->publicParams);
        }

        throw new GatewayException('支付' . $gateway . '必须要实现GatewayPayInterface接口。',1003);
    }
    /**
     * 根据公钥进行回调参数签名验证
     * @return mixed 验证成功后的回调参数
     * @throws InvalidSignException 无效签名
     */
    public function verifySign(){
        //获取get或post所有参数
        $data = file_get_contents('php://input');
        $data = is_array($data) ? $data : Client::fromXML($data);
        trace('微信回调请求参数：' . json_encode($data),'info');

// file_put_contents('5.txt', var_export($data,true).'-------'.date('m-d H:i:s',time()).PHP_EOL,FILE_APPEND);
        if (Client::generateSign($data,$this->config['key'],$this->sign_mode) === $data['sign']){
            return $data;
        }
        trace('微信回调请求验证签名失败，参数：' . json_encode($data),'info');
        throw new InvalidSignException('微信无效签名。',1005,$data);
    }

    /**
     * 查询订单
     * @param $order 订单参数
     * @return array|mixed|string
     */
    public function query($order){
        $this->publicParams = Client::filter($this->publicParams,$order,$this->config['key'],$this->sign_mode);

        return Client::requestApi('/pay/orderquery',$this->publicParams,$this->config['key'],$this->sign_mode);
    }

    /**
     * 申请退款
     * @param array $order 订单信息
     * @return array|mixed|string
     */
    public function refund(array $order){
        $this->publicParams = Client::filter($this->publicParams,$order,$this->config['key'],$this->sign_mode);

        return Client::requestApi('/secapi/pay/refund',$this->publicParams,$this->config['key'],$this->sign_mode,$this->config['apiclient_cert'],$this->config['apiclient_key']);
    }

    /**
     * 关闭订单
     * @param 请求参数 $order
     * @return string
     */
    public function cancel($order){
        return '';
    }

    /**
     * 订单回调成功
     * @return \think\response\Xml
     */
    public function success(){
        return xml(['return_code' => 'SUCCESS','return_msg' => 'OK'],200,[],['root_node' => 'xml']);
    }
    /**
     * 关闭订单
     * @param $order 订单信息
     * @return array|mixed|string
     */
    public function close($order){
        $this->publicParams = Client::filter($this->publicParams,$order,$this->config['key'],$this->sign_mode);

        return Client::requestApi('/pay/closeorder',$this->publicParams,$this->config['key'],$this->sign_mode);
    }
    /**
     * @param $method 方法名称
     * @param $arguments 参数
     * @return mixed
     */
    public function __call($method, $arguments)
    {
        return $this->pay($method,...$arguments);
    }
}