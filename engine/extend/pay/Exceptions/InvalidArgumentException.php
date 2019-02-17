<?php
/**
 * Created by PhpStorm.
 * User: daiguanghui
 * Date: 2018/5/3
 * Time: 下午3:05
 */

namespace pay\Exceptions;

/**
 * 无效参数异常
 * Class InvalidArgumentException
 * @package pay
 */
class InvalidArgumentException extends Exception
{
    /**
     * 未经处理的错误信息
     * @var array|string
     */
    public $raw;

    /**
     * 初始化无效参数
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