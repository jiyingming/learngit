<?php
/**
 * Created by PhpStorm.
 * User: daiguanghui
 * Date: 2018/5/3
 * Time: 上午11:55
 */

namespace pay;

/**
 * 网关接口
 * Interface GatewayInterface
 * @package pay
 */
interface GatewayInterface
{
    /**
     * 支付
     * @param $gateway 支付相关类
     * @param $params 参数
     * @return mixed
     */
    public function pay($gateway,$params);
    /**
     * 统一收单线下交易查询
     * 该接口提供所有支付宝支付订单的查询，商户可以通过该接口主动查询订单状态，完成下一步的业务逻辑
     * @param $order
     * @return mixed
     */
    public function query($order);
    /**
     * 统一收单交易退款接口
     * @param array $order 请求参数
     * @return mixed
     */
    public function refund(array $order);
    /**
     * 统一收单交易撤销接口
     * @param $order 请求参数
     * @return mixed
     */
    public function cancel($order);
    /**
     * 统一收单交易关闭接口
     * @param $order 请求参数
     * @return mixed
     */
    public function close($order);
    /**
     * 订单回调成功
     * @return \think\response\Xml
     */
    public function success();
}