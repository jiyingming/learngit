<?php

namespace pay\Exceptions;

/**
 * 支付异常
 * Class GatewayException
 * @package pay
 */
class GatewayException extends Exception
{
    /**
     * 未经处理的错误信息
     * @var array|string
     */
    public $raw;

    /**
     * 初始化支付异常
     * InvalidConfigException constructor.
     * @param string $message 异常消息
     * @param int $code 错误代码
     * @param string $raw 未经处理的错误信息
     */
    public function __construct($message, $code, $raw='')
    {
        parent::__construct($message, $code);

        $this->raw=$raw;
    }
}