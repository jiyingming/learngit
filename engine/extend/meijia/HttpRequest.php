<?php
namespace meijia;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

/**
 * Http curl 请求数据
 * Class HttpRequest
 * @package meijia
 */
trait HttpRequest
{
    /**
     * 以get方式请求数据
     * @param $url 请求的url地址
     * @param array $query 请求参数
     * @param array $headers 头部信息
     * @return mixed|string
     */
    protected function get($url,$query = [],$headers = []){
        return $this->request('get',$url,[
           'headers'    => $headers,
            'query'     => $query
        ]);
    }
    /**
     * 以post方式请求数据
     * @param $url 请求的url地址
     * @param $data 请求数据
     * @param array $options 配置选项
     * @return mixed|string
     */
    protected function post($url,$data,$options = []){
        if (!is_array($data)){
            $options['body'] = $data;
        } else {
            $options['form_params'] = $data;
        }

        return $this->request('post',$url,$options);
    }

    /**
     * 以post方式发送application/json 数据类型
     * @param $url 请求的url地址
     * @param $data 请求数据
     * @param array $headers 头部数据
     * @return mixed|string
     */
    protected function post_json($url,$data,$headers = []){
        $options = [
           'headers'    => $headers,
           'json'       => $data
       ];

        return $this->request('post',$url,$options);
    }
    /**
     * 请求数据
     * @param $method 请求方法名称
     * @param $url 请求的url
     * @param array $options 配置选项
     * @return mixed|string 接收到的数据
     */
    protected function request($method,$url,$options = []){
        return $this->unwrapResponse($this->createHttpClient($this->setBaseOptions())->{$method}($url,$options));
    }
    /**
     * 基础配置选项
     * @return array
     */
    protected function setBaseOptions(){
        $options = [
            'base_uri' => property_exists($this, 'baseUri') ? $this->baseUri : '',
            'timeout'  => property_exists($this, 'timeout') ? $this->timeout : 60,
        ];

        return $options;
    }

    /**
     * 创建网络请求客户端
     * @param array $options 参数信息
     * @return Client 创建后的客户端
     */
    protected function createHttpClient(array $options = []){
        return new Client($options);
    }
    /**
     * 对获取的内容拆装
     * @param ResponseInterface $response
     * @return mixed|string 转换后的数据内容
     */
    protected function unwrapResponse(ResponseInterface $response){
        //获取请求类型
        $contentType = $response->getHeaderLine('Content-Type');
        //获取请求数据内容
        $contents = $response->getBody()->getContents();

        //dump($contents);exit;
        if (stripos($contentType,'json') !== false){
            return json_decode($contents, true);
        }
        return $contents;
    }
}