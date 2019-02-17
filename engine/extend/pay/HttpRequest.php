<?php

namespace pay;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

/**
 * HTTP curl 请求数据
 * Trait HttpRequest
 * @package pay
 */
trait HttpRequest
{
    /**
     * 以get方式请求数据
     * @param $endpoint 请求的url地址
     * @param array $query 请求参数
     * @param array $headers 请求header信息
     * @return mixed|string
     */
    protected function get($endpoint, $query = [], $headers = [])
    {
        return $this->request('get', $endpoint, [
            'headers' => $headers,
            'query'   => $query,
        ]);
    }

    /**
     * 以post方式请求数据
     * @param $endpoint 请求的url地址
     * @param $data 请求数据
     * @param array $options 配置选项
     * @return array|string
     */
    protected function post($endpoint, $data, $options = [])
    {
        if (! is_array($data)) {
            $options['body'] = $data;
        } else {
            $options['form_params'] = $data;
        }

        return $this->request('post', $endpoint, $options);
    }

    /**
     * 请求数据
     * @param $method 请求方法
     * @param $endpoint 请求的url地址
     * @param array $options 配置选项
     * @return mixed|string
     */
    protected function request($method, $endpoint, $options = [])
    {
        return $this->unwrapResponse($this->getHttpClient($this->getBaseOptions())->{$method}($endpoint, $options));
    }

    /**
     * 基础配置选项
     * @return array
     */
    protected function getBaseOptions()
    {
        $options = [
            'base_uri' => property_exists($this, 'baseUri') ? $this->baseUri : '',
            'timeout'  => property_exists($this, 'timeout') ? $this->timeout : 10,
        ];

        return $options;
    }

    /**
     * 获取http客户端信息
     * @param array $options
     * @return Client
     */
    protected function getHttpClient(array $options = [])
    {
        return new Client($options);
    }

    /**
     * 组装信息
     * @param ResponseInterface $response 响应接口
     * @return mixed|string 组装后的内容
     */
    protected function unwrapResponse(ResponseInterface $response)
    {
        $contentType = $response->getHeaderLine('Content-Type');
        $contents = $response->getBody()->getContents();

        if (false !== stripos($contentType, 'json') || stripos($contentType, 'javascript')) {
            return json_decode($contents, true);
        } elseif (false !== stripos($contentType, 'xml')) {
            return json_decode(json_encode(simplexml_load_string($contents, 'SimpleXMLElement', LIBXML_NOCDATA), JSON_UNESCAPED_UNICODE), true);
        }

        return $contents;
    }
}