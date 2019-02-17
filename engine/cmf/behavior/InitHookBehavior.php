<?php

namespace cmf\behavior;

use think\Hook;
use think\Db;

class InitHookBehavior
{

    // 行为扩展的执行入口必须是run
    public function run(&$param)
    {


        $plugins = cache('init_hook_plugins');

        if (empty($plugins)) {
            $plugins = Db::name('hook_plugin')->field('hook,plugin')->where('status', 1)
                ->order('list_order ASC')
                ->select();
            cache('init_hook_plugins', $plugins);
        }

        if (!empty($plugins)) {
            foreach ($plugins as $hookPlugin) {
                Hook::add($hookPlugin['hook'], cmf_get_plugin_class($hookPlugin['plugin']));
            }
        }
    }
}