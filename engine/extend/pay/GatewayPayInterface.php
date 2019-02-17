<?php

namespace pay;
/**
 * 网关支付接口
 */
interface GatewayPayInterface
{
    /**
     * 支付接口
     * @param $gatewayUrl 网关地址
     * @param array $params 请求参数
     * @return mixed
     */
    public function pay($gatewayUrl,array $params);
}