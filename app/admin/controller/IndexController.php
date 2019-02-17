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
namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;
use app\admin\model\AdminMenuModel;
use app\portal\model\FunMemberModel;
use app\admin\model\FunGoodsorderModel;

class IndexController extends AdminBaseController
{

    public function _initialize()
    {
        $adminSettings = cmf_get_option('admin_settings');
        if (empty($adminSettings['admin_password']) || $this->request->path() == $adminSettings['admin_password']) {
            $adminId = cmf_get_current_admin_id();
            if (empty($adminId)) {
                session("__LOGIN_BY_CMF_ADMIN_PW__", 1);//设置后台登录加密码
            }
        }

        parent::_initialize();
    }

    /**
     * 后台首页
     */
    public function index()
    {
        $result = Db::name('AdminMenu')->order(["app" => "ASC", "controller" => "ASC", "action" => "ASC"])->select();
        $menusTmp = array();
        foreach ($result as $item){
            //去掉/ _ 全部小写。作为索引。
            $indexTmp = $item['app'].$item['controller'].$item['action'];
            $indexTmp = preg_replace("/[\\/|_]/","",$indexTmp);
            $indexTmp = strtolower($indexTmp);
            $menusTmp[$indexTmp] = $item;
        }
        $this->assign("menus_js_var",json_encode($menusTmp));

        $lang_field = getLangField();
        $this->assign('lang_field',$lang_field);


        $dashboardWidgets = [];
        $widgets          = cmf_get_option('admin_dashboard_widgets');

        $defaultDashboardWidgets = [
            '_SystemCmfHub'           => ['name' => 'CmfHub', 'is_system' => 1],
            '_SystemCmfDocuments'     => ['name' => 'CmfDocuments', 'is_system' => 1],
            '_SystemMainContributors' => ['name' => 'MainContributors', 'is_system' => 1],
            '_SystemContributors'     => ['name' => 'Contributors', 'is_system' => 1],
            '_SystemCustom1'          => ['name' => 'Custom1', 'is_system' => 1],
            '_SystemCustom2'          => ['name' => 'Custom2', 'is_system' => 1],
            '_SystemCustom3'          => ['name' => 'Custom3', 'is_system' => 1],
            '_SystemCustom4'          => ['name' => 'Custom4', 'is_system' => 1],
            '_SystemCustom5'          => ['name' => 'Custom5', 'is_system' => 1],
        ];

        if (empty($widgets)) {
            $dashboardWidgets = $defaultDashboardWidgets;
        } else {
            foreach ($widgets as $widget) {
                if ($widget['is_system']) {
                    $dashboardWidgets['_System' . $widget['name']] = ['name' => $widget['name'], 'is_system' => 1];
                } else {
                    $dashboardWidgets[$widget['name']] = ['name' => $widget['name'], 'is_system' => 0];
                }
            }

            foreach ($defaultDashboardWidgets as $widgetName => $widget) {
                $dashboardWidgets[$widgetName] = $widget;
            }


        }

        $dashboardWidgetPlugins = [];

        $hookResults = hook('admin_dashboard');

        if (!empty($hookResults)) {
            foreach ($hookResults as $hookResult) {
                if (isset($hookResult['width']) && isset($hookResult['view']) && isset($hookResult['plugin'])) { //验证插件返回合法性
                    $dashboardWidgetPlugins[$hookResult['plugin']] = $hookResult;
                    if (!isset($dashboardWidgets[$hookResult['plugin']])) {
                        $dashboardWidgets[$hookResult['plugin']] = ['name' => $hookResult['plugin'], 'is_system' => 0];
                    }
                }
            }
        }
        $smtpSetting = cmf_get_option('smtp_setting');


        $this->assign('data',$this->getSysData());
        $this->assign('info',$this->getSysInfo());

        $this->assign('dashboard_widgets', $dashboardWidgets);
        $this->assign('dashboard_widget_plugins', $dashboardWidgetPlugins);
        $this->assign('has_smtp_setting', empty($smtpSetting) ? false : true);

        //首页数据
        $memberModel = new FunMemberModel();
        $orderModel  = new FunGoodsorderModel();

        $shop_count   = $memberModel->where(['rank' => ['>', 0]])->count();
        $member_count = $memberModel->count();
        $card_count   = $memberModel->where(['status' => 1])->count();

        $morning_time = strtotime(date('Ymd'));
        $night_time   = strtotime(date('Ymd')) + 86400;
        $order_count  = $orderModel->where(['post_status' => ['>', 0]])->count();

        $post_count   = $orderModel->where(['post_status' => 1,'delstate' => ''])->count();

        $cash_count   = Db::name('fun_withdrawal')->where('post_status', 0)->count();


        //柱状图需要的数据 end

        //新进分销商
        $shop_list = $memberModel->alias('m')
                                 ->join('__FUN_MEMBER__ f', 'm.referee_id=f.id', 'left')
                                 ->where('m.rank', '>', 0)
                                 ->field('m.*, f.nickname as fnickname, f.real_name as freal_name, f.status as fstatus, f.id as fid')
                                 ->order('m.create_time DESC')
                                 ->limit(4)
                                 ->select();

        //最新订单
        $order_list = $orderModel->alias('g')
                                 ->join('__FUN_MEMBER__ m', 'g.userid=m.id', 'left')
                                 ->join('__FUN_MEMBER__ f', 'm.referee_id=f.id', 'left')
                                 ->where(['post_status' => ['>', 0], 'delstate' => ['<>', TRUE]])
                                 ->field('g.*, f.rank as frank, f.nickname as fnickname, m.rank as mrank')
                                 ->order('posttime DESC')
                                 ->limit(8)
                                 ->select();

        $orderArr = $orderModel->getECharts();
        $this->assign('amount',$orderArr['amount']);
        $this->assign('anum',$orderArr['anum']);
        $this->assign('month',$orderArr['month']);

        $userArr = $memberModel->getECharts();
        $this->assign('user',$userArr['user']);
        $this->assign('seller',$userArr['seller']);

        $this->assign('shop_count',$shop_count);
        $this->assign('member_count',$member_count);
        $this->assign('card_count',$card_count);
        $this->assign('order_count',$order_count);
        $this->assign('post_count',$post_count);
        $this->assign('cash_count',$cash_count);
        $this->assign('shop_list',$shop_list);
        $this->assign('order_list',$order_list);

        return $this->fetch();
    }

    public function dashboardWidget()
    {
        $dashboardWidgets = [];
        $widgets          = $this->request->param('widgets/a');
        if (!empty($widgets)) {
            foreach ($widgets as $widget) {
                if ($widget['is_system']) {
                    array_push($dashboardWidgets, ['name' => $widget['name'], 'is_system' => 1]);
                } else {
                    array_push($dashboardWidgets, ['name' => $widget['name'], 'is_system' => 0]);
                }
            }
        }

        cmf_set_option('admin_dashboard_widgets', $dashboardWidgets, true);

        $this->success('更新成功!');
    }

    // 获取网站数据
    public function getSysData(){
        $data['category'] = Db::name('portal_category')->where(['delete_time'=>0])->count();
        $data['page'] = Db::name('portal_post')->where(['delete_time'=>0,'post_type'=>2])->count();
        $data['user'] =  Db::name('user')->where("1=1")->count();
        $data['article'] =  Db::name('portal_post')->where(['delete_time'=>0,'post_type'=>1])->count();
        return $data;
    }

    // 获取系统信息
    public function getSysInfo(){
        $sys_info['os']             = PHP_OS;
        $sys_info['os2']            = php_uname();
        $sys_info['web_server']     = $_SERVER['SERVER_SOFTWARE'];
        $sys_info['phpv']           = phpversion();
        $sys_info['ip']             = GetHostByName($_SERVER['SERVER_NAME']);
        $sys_info['fileupload']     = @ini_get('file_uploads') ? ini_get('upload_max_filesize') :'unknown';
        $sys_info['domain']         = $_SERVER['HTTP_HOST'];
        $sys_info['port']           = $_SERVER['SERVER_PORT'];
        $sys_info['interface']      = php_sapi_name();
        $sys_info['time']           = date('Y-m-d H:i:s');
        $sys_info['time_beijing']   = gmdate("Y-m-d H:i:s",time()+8*3600);
        $sys_info['run_time']       = $this->getLinuxRunningTime();

        return $sys_info;
    }

    // 获取Linux系统运行时间
    private function getLinuxRunningTime(){
        if(false === ($str = @file("/proc/uptime")))
            return false;
        $str = explode(" ", implode("", $str));
        $str = trim($str[0]);
        $min = $str / 60;
        $hours = $min / 60;
        $days = floor($hours / 24);
        $hours = floor($hours - ($days * 24));
        $min = floor($min - ($days * 60 * 24) - ($hours * 60));
        if($days !== 0) $res = $days . "天";
        if($hours !== 0) $res .= $hours . "小时";
        $res .= $min . "分钟";
        return $res;
    }
}
