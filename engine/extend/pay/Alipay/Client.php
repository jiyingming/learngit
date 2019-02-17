<?php
namespace pay\Alipay;
use pay\HttpRequest;
use pay\Exceptions\InvalidArgumentException;
use pay\Exceptions\InvalidConfigException;
use pay\Exceptions\InvalidSignException;
use think\helper\Str;

/**
 * 支付宝客户端对象
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
     * 阿里支付宝 固定网关地址
     * @var string
     */
    protected $baseUri='https://openapi.alipay.com/gateway.do';

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
            case 'dev':
                self::getInstance()->baseUri = 'https://openapi.alipaydev.com/gateway.do';
                break;

            default:
                break;
        }

        return self::getInstance()->baseUri;
    }

    /**
     * 根据私钥生成参数签名
     * @param array $params 待签名参数
     * @param null $privateKey 私钥字符或文件全路径格式以.pem
     * @return string 返回签名后的字符
     * @throws InvalidArgumentException 无效参数异常
     * @throws InvalidConfigException 无效配置异常
     */
    public static function generateSign(array $params,$privateKey = null) {
        if (empty($privateKey)){
            throw new InvalidConfigException('请检查RSA私钥配置--[private_Key]',1001);
        }
        //如果是.pem文件，则通过openssl_pkey_get_private 加载秘钥，否则配置PEM字符秘钥
        if (Str::endsWith($privateKey,'.pem')){
            $privateKey = openssl_pkey_get_private($privateKey);
        } else {
            $privateKey = "-----BEGIN RSA PRIVATE KEY-----\n" .
                wordwrap($privateKey,64,"\n",true) .
                "\n-----END RSA PRIVATE KEY-----";
        }
        if (empty($privateKey)){
            throw new InvalidArgumentException('您使用的私钥格式错误，请检查RSA私钥配置--[private_Key]',1002);
        }

        //执行参数签名 因支付宝签名是非对称的并且秘钥长度至少2048，因此使用OPENSSL_ALGO_SHA256签名算法
        //必须以私钥进行签名
        openssl_sign(self::getSignContent($params),$signature,$privateKey,OPENSSL_ALGO_SHA256);

        return base64_encode($signature);
    }

    /**
     * 根据公钥验证接收到的签名
     * @param array $params 回调请求的参数数组
     * @param null $publicKey 公钥
     * @param bool $sync 是否同步
     * @param null $sign 签名
     * @return bool true 验证签名成功，否则失败
     * @throws InvalidArgumentException 无效参数
     * @throws InvalidConfigException 无效配置
     */
    public static function verifySign(array $params,$publicKey=null,$sync=false,$sign=null){
        if (empty($publicKey)){
            throw new InvalidConfigException('请检查RSA公钥配置--[alipay_public_key]',1001);
        }
        //如果是.pem文件，则通过openssl_pkey_get_private 加载秘钥，否则配置PEM字符秘钥
        if (Str::endsWith($publicKey,'.pem')){
            $publicKey = openssl_pkey_get_public($publicKey);
        } else {
            $publicKey = "-----BEGIN PUBLIC KEY-----\n" .
                wordwrap($publicKey,64,"\n",true) .
                "\n-----END PUBLIC KEY-----";
        }
        if (empty($publicKey)){
            throw new InvalidArgumentException('您使用的公钥格式错误，请检查RSA私钥配置--[alipay_public_key]',1002);
        }

        $sign = $sign ?? $params['sign'];
        $toVerify= $sync? mb_convert_encoding(json_encode($params,JSON_UNESCAPED_UNICODE),'gb2312','utf-8') : self::getSignContent($params,$sync);

        return openssl_verify($toVerify,base64_decode($sign),$publicKey,OPENSSL_ALGO_SHA256)===1;
    }

    /**
     * 获取转码后的待签名内容
     * @param array $params 待签名参数
     * @param bool $sync 是否为异步通知，异步通知保留sign_type和签名
     * @return string
     */
    public static function getSignContent(array $params,$sync = true) {
        $params = encoding($params,$params['charset'] ?? 'gb2312','utf-8');
        ksort($params);

        $stringToBeSigned='';
        foreach ($params as $k => $v){
            if (!empty($v) && "@" !=substr($v,0,1)){
                if ($sync && $k != 'sign'){
                    $stringToBeSigned .= $k . '=' . $v . '&';
                }
                if (!$sync && $k !='sign' && $k != 'sign_type'){
                    $stringToBeSigned .= $k . '=' . $v . '&';
                }
            }
        }

        return trim($stringToBeSigned,'&');
    }

    /**
     * 通过curl 请求API接口
     * @param array $params 请求参数
     * @param $publicKey 公钥
     * @return mixed
     */
    public static function requestApi(array $params,$publicKey){
        trace('请求支付宝订单，请求地址：' . self::getInstance()->getBaseUri(). '  ，参数：' . json_encode($params),'info');
        // 替换成，支付接口返回的参数名 格式为alipay_trade_query_response
        // 即支付宝方请求方法中的【.】替换成【_】，然后再加上【_response】，作为请求结果后的数组参数名
        $method = str_replace('.', '_', $params['method']).'_response';
        //将请求后的结果参数 字符编码转换
        $result = mb_convert_encoding(self::getInstance()->post('', $params), 'utf-8', 'gb2312');
        $result = json_decode($result, true);
        //验证结果返回的 签名
        if (!self::verifySign($result[$method], $publicKey, true, $result['sign'])) {
            trace('请求支付宝订单失败，请求结果：' . json_encode($result),'notice');

            throw new InvalidSignException('支付宝无效签名', 1005, $result);
        }
        //状态为10000 表示成功，则直接返回数据
        if (isset($result[$method]['code']) && $result[$method]['code'] == '10000') {
            return $result[$method];
        }

        throw new GatewayException(
            '请求支付宝订单接口失败，异常信息:'.$result[$method]['msg'].($result[$method]['sub_code'] ?? ''),
            $result[$method]['code'],
            $result
        );
    }

}