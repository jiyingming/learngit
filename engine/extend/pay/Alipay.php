<?php
/**
 * 支付宝接口
 */

namespace pay;


use pay\Alipay\Client;
use pay\Exceptions\GatewayException;
use pay\Exceptions\InvalidSignException;
use think\helper\Str;

/**
 * 支付宝支付
 * @method \pay\Alipay\WebGateway web(array $config) 电脑web支付
 * @method \pay\Alipay\WapGateway wap(array $config) 手机网页支付
 * @method \pay\Alipay\AppGateway app(array $config) 手机应用支付
 * Class Alipay
 * @package pay
 */
class Alipay implements GatewayInterface
{
    /**
     * 配置信息
     * @var
     */
    protected $config;
    /**
     * 支付宝网关
     * @var
     */
    protected $gateway;
    /**
     * 支付宝接口公共参数
     * @var
     */
    protected $publicParams;

    /**
     * 初始化支付宝支付配置
     * Alipay constructor.
     * @param $config 公共配置
     */
    public function __construct($config)
    {
        $this->config       = $config;
        $this->gateway      = Client::getBaseUri(config('mode','normal'));
        $this->publicParams = [
            //支付宝分配给开发者的应用ID
            'app_id'    => $config['app_id'],
            //接口名称
            'method'    => '',
            //仅支持JSON
            'format'    => 'JSON',
            //请求使用的编码格式，如utf-8,gbk,gb2312等
            'charset'       => 'utf-8',
            //商户生成签名字符串所使用的签名算法类型，目前支持RSA2和RSA，推荐使用RSA2
            'sign_type'     => 'RSA2',
            //商户请求参数的签名串
            'sign'          => '',
            //发送请求的时间，格式"yyyy-MM-dd HH:mm:ss"
            'timestamp'     => date('Y-m-d H:i:s'),
            //调用的接口版本，固定为：1.0
            'version'       => '1.0',
            //同步返回地址，HTTP/HTTPS开头字符串
            'return_url'    => $config['return_url'],
            //支付宝服务器主动通知商户服务器里指定的页面http/https路径。
            'notify_url'    => $config['notify_url'],
            //业务请求参数的集合，最大长度不限，除公共参数外所有请求参数都必须放在这个参数中传递
            'biz_content'   => ''
        ];
    }

    /**
     * 支付
     * @param 支付相关类 $gateway 相关支付操作类
     * @param array $params 支付请求参数
     * @return mixed
     * @throws GatewayException
     */
    public function pay($gateway,$params = []){
        $this->publicParams['biz_content'] = json_encode($params);
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
        $data = input();
        trace('支付宝回调请求参数：' . json_encode($data),'info');

        if (Client::verifySign($data,$this->config['alipay_public_key'])){
            return $data;
        }
        trace('支付宝回调请求验证签名失败，参数：' . json_encode($data),'info');
        throw new InvalidSignException('支付宝无效签名。',1005,$data);
    }

    /**
     * 统一收单线下交易查询
     * 该接口提供所有支付宝支付订单的查询，商户可以通过该接口主动查询订单状态，完成下一步的业务逻辑
     * @param $order
     * @return mixed
     */
    public function query($order){
        $this->publicParams['method'] = 'alipay.trade.query';
        $this->publicParams['biz_content'] = json_encode(is_array($order) ? $order : ['out_trade_no' => $order]);
        //以私钥进行参数签名
        $this->publicParams['sign'] = Client::generateSign($this->publicParams,$this->config['private_key']);

        trace('支付宝订单交易查询，请求地址：' . $this->gateway . '  ，参数：' . json_encode($order),'info');

        return Client::requestApi($this->publicParams,$this->config['alipay_public_key']);
    }

    /**
     * 统一收单交易退款接口
     * @param array $order 请求参数
     * @return mixed
     */
    public function refund(array $order){
        $this->publicParams['method'] = 'alipay.trade.refund';
        $this->publicParams['biz_content'] = json_encode($order);
        //以私钥进行参数签名
        $this->publicParams['sign'] = Client::generateSign($this->publicParams,$this->config['private_key']);

        trace('支付宝交易退款，请求地址：' . $this->gateway . '  ，参数：' . json_encode($order),'info');

        return Client::requestApi($this->publicParams,$this->config['alipay_public_key']);
    }

    /**
     * 统一收单交易撤销接口
     * @param $order 请求参数
     * @return mixed
     */
    public function cancel($order){
        $this->publicParams['method'] = 'alipay.trade.cancel';
        $this->publicParams['biz_content'] = json_encode(is_array($order) ? $order : ['out_trade_no' => $order]);
        //以私钥进行参数签名
        $this->publicParams['sign'] = Client::generateSign($this->publicParams,$this->config['private_key']);

        trace('支付宝订单交易撤销，请求地址：' . $this->gateway . '  ，参数：' . json_encode($order),'info');

        return Client::requestApi($this->publicParams,$this->config['alipay_public_key']);
    }

    /**
     * 统一收单交易关闭接口
     * @param $order 请求参数
     * @return mixed
     */
    public function close($order){
        $this->publicParams['method'] = 'alipay.trade.close';
        $this->publicParams['biz_content'] = json_encode(is_array($order) ? $order : ['out_trade_no' => $order]);
        //以私钥进行参数签名
        $this->publicParams['sign'] = Client::generateSign($this->publicParams,$this->config['private_key']);

        trace('支付宝订单交易关闭，请求地址：' . $this->gateway . '  ，参数：' . json_encode($order),'info');

        return Client::requestApi($this->publicParams,$this->config['alipay_public_key']);
    }
    /**
     * 订单回调成功
     * @return \think\response\Xml
     */
    public function success(){
        return response('success');
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