<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2018 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 老猫 <thinkcmf@126.com>
// +----------------------------------------------------------------------
namespace app\portal\model;

use think\Model;


class FunAgentModel extends Model
{


    /**
     * 获取提问列表
     * @param $data
     * @return bool
     */
    public function getAdminAgentList()
    {

        $row = $this->where('status', 1)->order('id DESC')->select();
        foreach ($row as $k=>$v)
        {
            $v['agent_num'] = (new FunMemberModel())->where('rank', $v['id'])->count();
        }

        return $row;

    }


    public function getFrontQuestionList()
    {
        $condition['status'] = 1;
        return $this->where($condition)->order('id DESC')->select();
    }

    /**
     * 前台获取选中问题
     * @param  array  $condition [description]
     * @return [type]            [description]
     */
    public function getFrontQuestionInfo($condition = [], $field = '*')
    {
        $condition['status'] = 1;
        return $this->where($condition)->field($field)->order('id DESC')->find();
    }

    /**
     * 获取信息
     * @param  string $ids [description]
     * @return [type]      [description]
     */
    public function getAgentInfo($ids = '')
    {

        return $this->where(['id'=>$ids, 'status'=>1])->find();
    }

    /**
     * 添加提问
     * @param $data
     * @return bool
     */
    public function addFunAgent($data = [])
    {
        $result = true;
        self::startTrans();
        try {
            unset($data['ids']);
            $data['need_certification'] = ($data['need_certification'] == 1)?$data['need_certification']:0;
            $data['create_time'] = time();
            $data['status']      = 1;
            $data['count']       = 0;
            $this->allowField(true)->save($data);
            
            self::commit();
        } catch (\Exception $e) {
            self::rollback();
            $result = false;
        }

        return $result;
    }

    /**
     * 编辑提问
     * @param  array  $data [description]
     * @return [type]       [description]
     */
    public function editFunAgent($data = [])
    {
        $result = true;

        $ids = intval($data['ids']);
        $data['need_certification'] = isset($data['need_certification'])?1:0;
        self::startTrans();
        try {

            $this->allowField(true)->save($data, ['id' => $ids]);
            
            self::commit();
        } catch (\Exception $e) {
            self::rollback();
            $result = false;
        }
        
        return $result;
    }


}