<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2018 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 小夏 < 449134904@qq.com>
// +----------------------------------------------------------------------
namespace app\portal\validate;

use think\Validate;

class AdminAgentValidate extends Validate
{
    protected $rule = [
        'agent_name'    => 'require',
        'discount'      => ['require', 'regex'=>'/^[0-9]+(.[0-9]{1,2})?$/'],
        'img_url'       => 'require',
        'money'         => ['require', 'regex'=>'/^[0-9]+(.[0-9]{1,2})?$/'],
        'mini_recharge' => ['require', 'regex'=>'/^[0-9]+(.[0-9]{1,2})?$/'],
    ];
    protected $message = [
        'agent_name.require'    => '请填写级别名称',
        'img_url.require'       => '请上传级别标识！',
        'discount.require'      => '请填写折扣',
        'discount.regex'        => '折扣格式填写不正确',
        'money.require'         => '请填写单次充值金额',
        'money.regex'           => '充值金额格式不正确',
        'mini_recharge.require' => '请填写最低充值金额',
        'mini_recharge.regex'   => '最低充值金额格式不正确',
    ];

    protected $scene = [
//        'add'  => ['user_login,user_pass,user_email'],
//        'edit' => ['user_login,user_email'],
    ];
}