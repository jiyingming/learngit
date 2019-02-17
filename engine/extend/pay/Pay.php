<?php

namespace pay;

use think\helper\Str;
use pay\Exceptions\GatewayException;
/**
 * 支付
 * @method \pay alipay(array $config) 支付宝接口相关
 * @method \pay weixin(array $config) 微信接口相关
 * Class Pay
 * @package pay
 */
class Pay
{
    /**
     * 配置数组
     * @var
     */
    protected $config;

    /**
     * 初始化支付
     * Pay constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * 创建指定应用
     * @param $method 方法名
     * @return mixed
     * @throws GatewayException
     */
    protected function create($method){
        //组合调用相应的支付类
        $gateway = __NAMESPACE__ . '\\' . Str::studly($method) ;
        //检查类是否存在
        if (class_exists($gateway)){
            //创建支付类
            return $this->make($gateway);
        }
        throw new GatewayException('网关' . $gateway . '不存在。',1004);
    }

    /**
     * 生成具体的支付网关相关的类
     * @param $gateway 支付相关类
     * @return mixed
     * @throws GatewayException
     */
    protected function make($gateway){
        $app = new $gateway($this->config);
        if ($app instanceof GatewayInterface){
            return $app;
        }

        throw new GatewayException('支付' . $gateway . '必须要实现GatewayPayInterface接口。',1003);
    }

    public static function __callStatic($method, $arguments)
    {
        // TODO: Implement __callStatic() method.
        $app = new self(...$arguments);

        return $app->create($method);
    }
}