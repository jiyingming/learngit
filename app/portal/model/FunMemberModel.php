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
use think\Db;

class FunMemberModel extends Model
{

    protected $_error = '';
    /**
     * 后台分销商管理
     * @param array $condition
     * @param string $order
     * @param int $list_rows
     * @throws \think\exception\DbException
     */
    public function getAdminMemberList($condition = [], $order = 'id DESC', $list_rows = 15)
    {
        $condition['rank'] = ['>', 0];
        $result = $this->where($condition)->order($order)->paginate($list_rows);
        foreach ($result as $key=>$val){
            $row = $this->where('referee_id', $val['id'])->column('id');
            //下级数量
            $val['lower_level'] = count($row);
            $val['crack_level'] = 0;
            if ($row){

                //订单总量
                $val['all_num']   = Db::name('fun_goodsorder')->where(['userid' => ['in', array_values($row)]])->count('id');
                //返利金额
                $val['all_bill']     = Db::name('fun_bill')->where(['user_id' => ['in', array_values($row)]])->sum('profit');
                //裂级数量
                $row = array_merge($row, [$val['id']]);
                $val['crack_level'] = $this->where(['pre_referee_id|referee_id'=>['in', array_values($row)]])->count();
            }
            //充值金额
            $val['all_recharge'] = Db::name('fun_recharge')->where(['post_status' => 1, 'userid' => $val['id']])->sum('money');
        }
        return $result;

    }

    /**
     * 后台用户管理
     * @param array $condition
     * @param string $order
     * @param int $list_rows
     * @throws \think\exception\DbException
     */
    public function getAdminUserList($condition = [], $order = 'id DESC', $list_rows = 10)
    {

        $res = $this->alias('m')
            ->join('__FUN_MEMBER__ f','m.referee_id=f.id','left')
            ->field('m.*, f.nickname as fnickname, f.real_name as freal_name, f.rank as frank, f.status as fstatus')
            ->where($condition)
            ->order($order)
            ->paginate($list_rows);
        return $res;
    }

    /**
     * 普通用户
     * @param int $ids
     * @return array|false|\PDOStatement|string|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getAdminUserInfo($ids = 0)
    {
        $condition['m.id'] = $ids;
        //$condition['m.rank'] = 0;
        $res = $this->alias('m')
            ->join('__FUN_MEMBER__ f','m.referee_id=f.id','left')
            ->field('m.*, f.nickname as fnickname, f.real_name as freal_name, f.rank as frank')
            ->where($condition)
            ->find();
        return $res;
    }


    /**
     * 获取分销商详情
     * @param $ids
     * @return array|false|\PDOStatement|string|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getAdminMemberInfo($ids)
    {
        $condition['m.id'] = $ids;
        $condition['m.rank'] = ['>', 0];
        $res = $this->alias('m')
                    ->join('__FUN_MEMBER__ f','m.referee_id=f.id','left')
                    ->field('m.*, f.nickname as fnickname')
                    ->where($condition)
                    ->find();
        $row = $this->where('referee_id', $res['id'])->column('id');
        $res['lower_level'] = count($row);
        $res['crack_level'] = 0;
        $res['all_amount']  = 0;
        $res['all_bill']    = 0;
        if ($row){

            $res['all_amount']   = Db::name('fun_goodsorder')->where(['userid' => ['in', array_values($row)]])->sum('orderamount');
            $res['all_bill']     = Db::name('fun_bill')->where(['user_id' => ['in', array_values($row)]])->sum('profit');
            $row = array_merge($row, [$res['id']]);
            $res['crack_level']  = $this->where(['id|pre_referee_id|referee_id'=>['in', array_values($row)]])->count();
        }

        $res['all_cash']     = Db::name('fun_withdrawal')->where(['post_status' => 1, 'userid' => $res['id']])->sum('money');
        $res['all_recharge'] = Db::name('fun_recharge')->where(['post_status' => 1, 'userid' => $res['id']])->sum('money');

        return $res;
    }

    /**
     * 新进分销商
     * @param int $ids
     * @param string $order
     * @param int $list_rows
     * @return \think\Paginator
     * @throws \think\exception\DbException
     */
    public function getAgentList($ids = 0, $order = 'm.create_time DESC', $list_rows = 10)
    {
        //新进分销商
        $condition['m.rank'] = ['>', 0];
        $condition['m.referee_id'] = $ids;
        $shop_list = $this->alias('m')
            ->join('__FUN_MEMBER__ f', 'm.referee_id=f.id', 'left')
            ->where($condition)
            ->field('m.*, f.nickname as fnickname, f.rank as frank')
            ->order($order)
            ->paginate($list_rows);

        return $shop_list;
    }

    /**
     * 后台获取统计数据
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getECharts()
    {

        $current_month = (int) date('m', time());

        $monthArr = [];
        for($i=0;$i<6;$i++){
            $monthArr[] = $current_month;
            $current_month = $current_month - 1;
            if ($current_month === 0) { // 月份减一等于零，证明是要从1月跳到上一年的12月
                $current_month = 12; // 本次要获取订单数的月份

            }

        }
        $monthArr = array_reverse($monthArr);

        $row1 = $this->where(['rank' => 0])
            ->field("count( id ) AS allnum, FROM_UNIXTIME( create_time, '%m' ) AS yearmonth")
            ->group('yearmonth')
            ->select()->toArray();

        $row2 = $this->where(['rank' => ['>',0]])
            ->field("count( id ) AS allnum, FROM_UNIXTIME( update_time, '%m' ) AS yearmonth")
            ->group('yearmonth')
            ->select()->toArray();

        $ret = [];
        $res = [];
        if ($row1){
            foreach ($row1 as $val)
            {
                $res[(int)$val['yearmonth']] = $val['allnum'];
            }
        }

        if ($row2){
            foreach ($row2 as $val)
            {

                $ret[(int)$val['yearmonth']] = $val['allnum'];
            }
        }

        $user   = [];
        $seller = [];
        foreach ($monthArr as $k=>$v){

            if ( isset($ret[$v])){
                $seller[$v] = $ret[$v];
            }
            else
            {
                $seller[$v] = 0;
            }
            if (isset($res[$v]) ){
                $user[$v]  = $res[$v];
            }
            else
            {
                $user[$v]   = 0;
            }

        }

        return array('user'=>json_encode(array_values($user)), 'seller'=>json_encode(array_values($seller)));
    }


    public function getChangeAgentList($condition = [])
    {

        return $this->where($condition)->order('rank DESC')->column('real_name,nickname,rank','id');
    }

    /**
     * 后台更改用户级别
     * @param $param
     * @return bool
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function adminChangeUserRank($param)
    {
        $user = $this->get($param['id']);
        if (!$user){
            $this->setError('该条信息不存在');
            return false;
        }

        if ($user->rank == $param['rank']){
            $this->setError('请选择要更改的级别');
            return false;
        }

        self::startTrans();
        try {
            $agentInfo = (new FunAgentModel())->get(['status' => 1, 'id' => $param['rank']]);

            if ($param['rank'] > $user->rank){
                $new_amount   = round($agentInfo->deposit - $user->deposit, 2);

                if ($new_amount <= $user->recharge_money){
                    $user->recharge_money = ['dec', $new_amount];
                    $user->deposit        = ['inc', $new_amount];

                    $data = [
                        'user_id'    => $user->id,
                        'mobile'     => $user->mobile,
                        'user_name'  => $user->nickname,
                        'total_money'=> $new_amount,
                        'bill_type'  => 4,
                        'head_img'   => $user->head_img,
                        'create_time'=> time(),
                        'statistics_time' => date('Y年m月', time()),
                        'content'    => '管理员更改级别为'.$agentInfo->agent_name,
                    ];
                    Db::name('fun_bill')->insert($data);

                }

            }
            else
            {
                $new_amount   = round($user->deposit - $agentInfo->deposit, 2);
                if ($new_amount > 0){
                    $user->fanli_money = ['inc', $new_amount];
                    $user->deposit     = ['dec', $new_amount];
                    $user->rank        = $param['rank'];
                    $user->update_time = time();


                    $data = [
                        'user_id'    => $user->id,
                        'mobile'     => $user->mobile,
                        'user_name'  => $user->nickname,
                        'profit'     => $new_amount,
                        'bill_type'  => 4,
                        'create_time'=> time(),
                        'head_img'   => $user->head_img,
                        'statistics_time' => date('Y年m月', time()),
                        'content'    => '管理员更改级别为'.$agentInfo->agent_name,
                    ];
                    Db::name('fun_bill')->insert($data);

                }


            }

            $user->rank           = $param['rank'];
            $user->update_time    = time();
            $user->save();

            // 提交事务
            self::commit();
            return true;
        } catch (\Exception $e) {
            // 回滚事务
            self::rollback();
            $this->setError($e->getMessage());
            return false;
        }
    }

    /**
     * 后台更改董事团队奖（是否包括下级董事的团队奖）
     * @param $param
     * @return bool
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function adminChangeDirector($param)
    {
        $user = $this->get($param['id']);
        if (!$user){
            $this->setError('该条信息不存在');
            return false;
        }
        self::startTrans();
        try {
            $user->is_team_vip           = $param['is_team_vip'];
            $user->save();
            // 提交事务
            self::commit();
            return true;
        } catch (\Exception $e) {
            // 回滚事务
            self::rollback();
            $this->setError($e->getMessage());
            return false;
        }
    }

    /**
     * 后台更改用户充值金额
     * @param $param
     * @return bool
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function adminChangeUserMoney($param)
    {
        $user = $this->get($param['id']);
        if (!$user){
            $this->setError('该条信息不存在');
            return false;
        }

        if ($user->recharge_money >= $param['money']){
            $this->setError('更改的金额不得低于现有金额');
            return false;
        }
        $new_amount = round($param['money'] - $user->recharge_money, 2);

        self::startTrans();
        try {

            $user->recharge_money = $param['money'];

            $data = [
                'userid'     => $user->id,
                'mobile'     => $user->mobile,
                'user_name'  => $user->nickname,
                'money'      => $new_amount,
                'post_status'=> 1,
                'head_img'   => $user->head_img,
                'create_time'=> time(),
                'statistics_time' => date('Y年m月', time()),
                'from'       => 1,
            ];
            Db::name('fun_recharge')->insert($data);

            $user->save();

            // 提交事务
            self::commit();
            return true;
        } catch (\Exception $e) {
            // 回滚事务
            self::rollback();
            $this->setError($e->getMessage());
            return false;
        }
    }

    /**
     * 后台更改用户级别
     * @param int $id
     * @param int $pid
     * @return bool
     * @throws \think\exception\PDOException
     */
    public function adminChangeUserLevel($id = 0, $pid = 0)
    {
        self::startTrans();
        try {
            $row = $this->where('referee_id', $id)->column('id');
            if ($row){
                $row = array_values($row);
                $this->where(['id' => ['in',$row]])->update(['pre_referee_id'=>$pid]);

            }
            $res = $this->where('id', $pid)->value('pre_referee_id');
            if ($res > 0){
                $this->where('id',$id)->update(['referee_id'=>$pid, 'pre_referee_id'=>$res]);
            }
            else
            {
                $this->where('id',$id)->update(['referee_id'=>$pid, 'pre_referee_id'=>0]);
            }


            // 提交事务
            self::commit();
            return true;
        } catch (\Exception $e) {
            // 回滚事务
            self::rollback();
            return false;
        }

    }

    /**
     * 设置错误信息
     *
     * @param $error 错误信息
     * @return Auth
     */
    public function setError($error)
    {
        $this->_error = $error;
        return $this;
    }

    /**
     * 获取错误信息
     * @return string
     */
    public function getError()
    {
        return $this->_error ? ($this->_error) : '';
    }

}