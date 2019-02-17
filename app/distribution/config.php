<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2018 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: Dean <zxxjjforever@163.com>
// +----------------------------------------------------------------------
if (file_exists(CMF_ROOT . "data/conf/config.php")) {
    $runtimeConfig = include CMF_ROOT . "data/conf/config.php";
} else {
    $runtimeConfig = [];
}
$weixin_setting = cmf_get_option('appid_setting');
$configs = [
    // 上线后关闭调试模式和trace
    'app_trace' => false,
    'app_debug' => false,

    // 微信支付参数
    'weixin' => [
        // 沐宜科技公众号
        'appid' => $weixin_setting['appid'],
        'appsecret' => $weixin_setting['appsecret'],
        // 沐宜科技商户号
        'mch_id' => $weixin_setting['mch_id'],
        'key' => $weixin_setting['key'],
    ],
];
return array_merge($configs, $runtimeConfig);