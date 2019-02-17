<?php

namespace pay\Alipay;

use pay\GatewayPayInterface;

/**
 * 手机网页支付
 * Class WapGateway
 * @package pay\Alipay
 */
class WapGateway  implements GatewayPayInterface
{
    /**
     * 配置信息
     * @var
     */
    protected $config;

    /**
     * 初始化支付宝手机网页方式支付
     * WebGateway constructor.
     * @param $config
     */
    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * 获取支付宝接口方法名称
     * @return string
     */
    protected function getMethod()
    {
        return 'alipay.trade.wap.pay';
    }

    /**
     * 获取销售产品码，与支付宝签约的产品码名称。支付宝目前仅支持FAST_INSTANT_TRADE_PAY
     * @return string
     */
    protected function getProductCode(){
        return 'QUICK_WAP_WAY';
    }

    /**
     * 支付
     * @param $gatewayUrl 网关地址
     * @param array $params 请求参数
     * @return string 支付表单
     */
    public function pay($gatewayUrl,array $params){
        //获取支付宝接口方法
        $params['method'] = $this->getMethod();
        //业务请求参数的集合，最大长度不限，除公共参数外所有请求参数都必须放在这个参数中传递
        $params['biz_content'] = json_encode(array_merge(json_decode($params['biz_content'],true),['product_code' => $this->getProductCode()]));
        //以私钥进行参数签名
        $params['sign'] = Client::generateSign($params,$this->config['private_key']);

        trace('支付宝支付web订单，请求地址：' . $gatewayUrl . '  ，参数：' . json_encode($params),'info');

        return $this->builderPayForm($gatewayUrl,$params);
    }

    /**
     * 生成支付表单
     * @param $gatewayUrl 网关地址
     * @param $params 请求参数
     * @return string 生成的支付表单
     */
    public function builderPayForm($gatewayUrl,$params){
        $sHtml = "<form id='alipaysubmit' name='alipaysubmit' action='" . $gatewayUrl ."?charset=".trim($params['charset'])."' method='POST'>";
        foreach ($params as $key => $val){
            $val = str_replace("'", '&apos;', $val);
            $sHtml .= "<input type='hidden' name='".$key."' value='".$val."'/>";
        }
        //submit按钮控件请不要含有name属性
        $sHtml = $sHtml."<input type='submit' value='ok' style='display:none;''></form>";

        $sHtml = $sHtml."<script>document.forms['alipaysubmit'].submit();</script>";

        return $sHtml;
    }
}