<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2018 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: Powerless < wzxaini9@gmail.com>
// +----------------------------------------------------------------------
namespace app\user\controller;

use cmf\controller\HomeBaseController;
use think\Validate;
use app\user\model\UserModel;
use \lib\SecretPair;
use \lib\NECaptchaVerifier;

define("YIDUN_CAPTCHA_ID", "d80f64de8c5941f1a4449afa2c742a92"); // 验证码id
define("YIDUN_CAPTCHA_SECRET_ID", "98e05b00fc67755f2e86d1060733b59c");   // 验证码密钥对id
define("YIDUN_CAPTCHA_SECRET_KEY", "13a492574f0af050a75402a3de9d175d"); // 验证码密钥对key


class RegisterController extends HomeBaseController
{

    /**
     * 前台用户注册
     */
    public function index()
    {
        $redirect = $this->request->post("redirect");
        if (empty($redirect)) {
            $redirect = $this->request->server('HTTP_REFERER');
        } else {
            $redirect = base64_decode($redirect);
        }
        session('login_http_referer', $redirect);

        if (cmf_is_user_login()) {
            return redirect($this->request->root() . '/');
        } else {
            return $this->fetch(":register");
        }
    }

    /**
     * 前台用户注册提交
     */
    public function doRegister()
    {
        if ($this->request->isPost()) {
            $rules = [
//                'captcha'  => 'require',
                'code'     => 'require',
                'password' => 'require|min:6|max:32',

            ];

            $isOpenRegistration = cmf_is_open_registration();

            if ($isOpenRegistration) {
                unset($rules['code']);
            }

            $validate = new Validate($rules);
            $validate->message([
                'code.require'     => '验证码不能为空',
                'password.require' => '密码不能为空',
                'password.max'     => '密码不能超过32个字符',
                'password.min'     => '密码不能小于6个字符',
//                'captcha.require'  => '验证码不能为空',
            ]);

            $verifier = new NECaptchaVerifier(YIDUN_CAPTCHA_ID, new SecretPair(YIDUN_CAPTCHA_SECRET_ID, YIDUN_CAPTCHA_SECRET_KEY));
            $validate = $_POST['NECaptchaValidate']; // 获得验证码二次校验数据

            if(get_magic_quotes_gpc()){// PHP 5.4之前默认会将参数值里的 \ 转义成 \\，这里做一下反转义
                $validate = stripcslashes($validate);
            }
            $admin = "{'user':}"; // 当前用户信息，值可为空

            $result = $verifier->verify($validate, $admin);
            if(empty($validate)){
                $this->error(lang('CAPTCHA_REQUIRED'));  // 验证码不能为空！
            }
            if(!$result){
                $this->error(lang('CAPTCHA_NOT_RIGHT'));   // 验证码错误
            }

            $data = $this->request->post();
            if (!$validate->check($data)) {
                $this->error($validate->getError());
            }

//            $captchaId = empty($data['_captcha_id']) ? '' : $data['_captcha_id'];
//            if (!cmf_captcha_check($data['captcha'], $captchaId)) {
//                $this->error('验证码错误');
//            }

            if (!$isOpenRegistration) {
                $errMsg = cmf_check_verification_code($data['username'], $data['code']);
                if (!empty($errMsg)) {
                    $this->error($errMsg);
                }
            }

            $register          = new UserModel();
            $user['user_pass'] = $data['password'];
            if (Validate::is($data['username'], 'email')) {
                $user['user_email'] = $data['username'];
                $log                = $register->register($user, 3);
            } else if (cmf_check_mobile($data['username'])) {
                $user['mobile'] = $data['username'];
                $log            = $register->register($user, 2);
            } else {
                $log = 2;
            }
            $sessionLoginHttpReferer = session('login_http_referer');
            $redirect                = empty($sessionLoginHttpReferer) ? cmf_get_root() . '/' : $sessionLoginHttpReferer;
            switch ($log) {
                case 0:
                    $this->success('注册成功', $redirect);
                    break;
                case 1:
                    $this->error("您的账户已注册过");
                    break;
                case 2:
                    $this->error("您输入的账号格式错误");
                    break;
                default :
                    $this->error('未受理的请求');
            }

        } else {
            $this->error("请求错误");
        }

    }
}