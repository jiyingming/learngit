<?php

namespace pay\Weixin;


use pay\GatewayPayInterface;

/**
 * 微信支付抽象类
 * Class Gateway
 * @package pay\Weixin
 */
abstract class Gateway implements GatewayPayInterface
{

    /**
     * 配置信息
     * @var
     */
    protected $config;

    /**
     * 初始化微信配置
     * Gateway constructor.
     * @param $config 配置信息
     */
    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * 支付
     * @param \pay\网关地址 $gatewayUrl 相关支付操地址
     * @param array $params 参数
     * @return mixed
     */
    abstract public function pay($gatewayUrl, array $params);

    /**
     * 获取交易类型
     * @return mixed
     */
    abstract protected function getTradeType();

}