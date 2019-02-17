<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2018 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 老猫 <zxxjjforever@163.com>
// +----------------------------------------------------------------------
namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;
use think\Validate;

/**
 * Class LangController 语言包管理控制器
 * @package app\asdf\controller
 */
class LangController extends AdminBaseController
{
    // 语言列表
    private $lang_list;

    public function _initialize(){
        $this->lang_list = config('lang_list');
        parent::_initialize();
    }

    /**
     * 列表页
     */
    public function index(){
        $range = input('param.range');
        $range = empty($range) ? 0 : $range;

        $datas = Db::name('lang')->where(['status'=>1,'lang_range'=>$range])->order('add_time DESC')->paginate();
        $fields = $this->getLangField();


        // 在lang_list中删除已有的语言
        $lang_list = $this->lang_list;
        foreach ($fields as $v) {
            unset($lang_list[$v['key']]);
        }

        $this->assign('lang_list',$lang_list);
        $this->assign('range',$range);
        $this->assign('datas',$datas);
        $this->assign('fields',$fields);
        return view();
    }

    /**
     * 增加一个语言包常量
     */
    public function add(){
        $input = input('param.');

        $validate = new Validate([
            'name' => 'require|alphaDash',
        ]);
        if (!$validate->check($input)) {
            return $this->error($validate->getError());
        }
        // 常量全部大写
        $input['name'] = strtoupper($input['name']);

        $data = Db::name('lang')->where(['status'=>1,'name'=>$input['name'],'lang_range'=>$input['lang_range']])->find();
        if (!empty($data)) {
            return $this->error('已存在');
        }

        $input['add_time'] = time();
        $input['status'] = 1;
        if ($id = Db::name('lang')->insertGetId($input)) {
            return json(['code'=>1,'msg'=>lang('SUCCESS'),'id'=>$id]);
        } else {
            return $this->success(lang('ERROR'));
        }

    }

    /**
     * 编辑一个语言常量的内容
     */
    public function edit(){
        $input = input('param.');

        if (empty($input['id'])) {
            return $this->error(lang('ERROR'));
        }
        $data = Db::name('lang')->where(['status'=>1,'id'=>$input['id']])->find();
        if (empty($data)) {
            return $this->error(lang('ERROR'));
        }

        if (Db::name('lang')->update($input)) {
            return $this->success(lang('SUCCESS'));
        } else {
            return $this->error(lang('ERROR'));
        }

    }

    /**
     * 删除一个语言常量的内容
     */
    public function delete(){
        $input = input('param.');

        if (empty($input['id'])) {
            return $this->error(lang('ERROR'));
        }
        $data = Db::name('lang')->where(['status'=>1,'id'=>$input['id']])->find();
        if (empty($data)) {
            return $this->error(lang('ERROR'));
        }

        if (Db::name('lang')->where('id',$input['id'])->update(['status'=>-1])) {
            return $this->success(lang('SUCCESS'));
        } else {
            return $this->success(lang('ERROR'));
        }
    }

    /**
     * 添加语言包
     */
    public function addLang(){
        if (request()->isPost()) {
            $key = input('param.key');
            // 如果传入的key不在lang_list中就错误
            if (!isset($this->lang_list[$key])) {
                return $this->success(lang('ERROR'));
            }

            // 字段名必须是lang_0这种
            $lang = 'lang_'.$key;
            // 注释是语言名称
            $comment = $this->lang_list[$key]['name'];

            try {
                // 数据库中添加一个字段
                Db::name('lang')->query('ALTER TABLE `zoo_lang` ADD `'.$lang.'` varchar(100) COMMENT "'.$comment.'"');
                $flag = True;
            } catch (\Exception $e) {
                $flag = False;
            }

            if ($flag) {
                return $this->success(lang('SUCCESS'));
            } else {
                return $this->error(lang('ERROR'));
            }
        }
    }

    /**
     * 更新语言包文件
     */
    public function export(){
        $range = input('param.range');

        if (!in_array($range,[0,1,2])) {
            return $this->error(lang('ERROR'));
        }

        // 取语言包文件路径
        switch ($range) {
            case 0:
                $path = APP_PATH.'lang'.DIRECTORY_SEPARATOR ;
                break;
            case 1:
                $path = APP_PATH.'portal'.DIRECTORY_SEPARATOR.'lang'.DIRECTORY_SEPARATOR ;
                break;
            case 2:
                $path = APP_PATH.'admin'.DIRECTORY_SEPARATOR.'lang'.DIRECTORY_SEPARATOR ;
                break;
        }

        $datas = Db::name('lang')->where(['status'=>1,'lang_range'=>$range])->select()->toArray();

        $fields = $this->getLangField();
        foreach ($fields as $v) {
            // 取出单一列语言数据
            $write_data = array_column($datas,$v['field'],'name');
            // 写入语言包文件
            if (!is_dir($path)) {
                mkdir($path,0775);
            }
            file_put_contents($path.$v['file'].'.php', '<?php '.PHP_EOL.' return '.var_export($write_data,True).PHP_EOL.'?>');
        }

        return $this->success(lang('SUCCESS'),'index');
    }

    /**
     * 获取已有的语言字段
     * @return array field：字段名；name：语言名称；file：语言包文件名；key：在lang_list中的键值
     */
    public function getLangField(){
        return getLangField();
    }

    /**
     * 把语言包文件导入到数据库
     */
    public function import(){
        exit;
        $file_path = APP_PATH.'portal'.DIRECTORY_SEPARATOR.'lang'.DIRECTORY_SEPARATOR.'en-us.php';
        $lang = include $file_path;

        foreach ($lang as $n => $v) {
            $d = Db::name('lang')->where(['name'=>$n,'lang_range'=>1])->find();
            if (empty($d)) {
                Db::name('lang')->insert(['name'=>$n,'lang_2'=>$v,'lang_range'=>1]);
            } else {
                Db::name('lang')->where(['name'=>$n,'lang_range'=>1])->update(['lang_2'=>$v]);
            }
        }
    }

}