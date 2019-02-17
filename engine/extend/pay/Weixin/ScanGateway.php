<?php
/**
 * Created by PhpStorm.
 * User: daiguanghui
 * Date: 2018/5/15
 * Time: 下午2:52
 */

namespace pay\Weixin;
use pay\Weixin as cha;

/**
 * 微信扫描支付
 * Class ScanGateway
 * @package pay\Weixin
 */
class ScanGateway  extends Gateway
{
    /**
     * 微信扫描支付
     * @param \pay\网关地址 $gatewayUrl
     * @param array $params
     * @return string
     */
    public function pay($gatewayUrl, array $params)
    {
        //获取交易类型
        $params['trade_type'] = $this->getTradeType();
        //生成场景信息
        if(isset($params['scene_info'])) {
            $params['scene_info'] = json_encode($params['scene_info']);
        }

        $Sign_mode = isset($this->config['sign_type']) ? ($this->config['sign_type'] === 1? cha::SIGN_TYPE_MD5 : cha::SIGN_TYPE_HMAC) :cha::SIGN_TYPE_MD5 ;
        //获取签名
        $params['sign'] = Client::generateSign($params,$this->config['key'],$Sign_mode);

        trace('请求微信支付订单，请求地址：' . $gatewayUrl . '  ，参数：' . json_encode($params),'info');

        $result = Client::requestApi('/pay/unifiedorder',$params,$this->config['key'],$Sign_mode);

        return $result;
    }

    /**
     * 获取交易类型
     * @return string
     */
    protected  function getTradeType(){
        return 'NATIVE';
    }
}