<?php
/**
 * Created by PhpStorm.
 * User: daiguanghui
 * Date: 2018/5/3
 * Time: 下午2:58
 */

namespace pay\Exceptions;
/**
 * 无效的配置异常
 * Class InvalidConfigException
 * @package pay
 */
class InvalidConfigException extends Exception
{
    /**
     * 未经处理的错误信息
     * @var array|string
     */
    public $raw;

    /**
     * 初始化无效配置
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