<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2017 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: wuwu <15093565100@163.com>
// +----------------------------------------------------------------------
namespace app\distribution\controller;

use think\Db;
use cmf\controller\HomeBaseController;

class TimeTaskController extends HomeBaseController
{
    /**
     * 定时任务（每个月的1号 零点0分定时结算团队奖）
     */
    public function index(){
        //获取上个月的月份
        $temp_month = date('Y年m月', time() - 86400);
        $company_id = Db::name('fun_member')->where('rank', 20)->value('id');
        //全是董事
        $director = Db::name('fun_member')->field('id, nickname, mobile, rank, referee_id, pre_referee_id,fanli_money')
            ->where('rank', 9)->where(function($query )use ($company_id){
                $query->where('referee_id',0)->whereOr('referee_id', $company_id);
            })->select()->toArray();
        $mem = Db::name('fun_member')->field('id, nickname, mobile, rank, referee_id, pre_referee_id, fanli_money')
            ->where('rank', 4)->where(function($query )use ($company_id){
                $query->where('referee_id',0)->whereOr('referee_id', $company_id);
            })->select()->toArray();
        $member_list = array_merge($director, $mem);
        if(empty($member_list)){
            exit();
        }
        foreach($member_list as $k => $member){
            unset($where);
            //获取当月的团队奖
            $sum_bonus = $this->getTeamMoney($member['id'], $temp_month);
            if($sum_bonus){
                if($member['rank'] == 9){
                    $this->director_team_money($member['id'], $sum_bonus, 0, $temp_month);
                }
                else {
                    Db::name('fun_member')->where('id', $member['id'])->update(['fanli_money' => $member['fanli_money'] + $sum_bonus]);
                    $member['content'] = $member['nickname'] . '(' . getRankName($member['rank']) . ')获得' . $temp_month . '的团队奖--' . $sum_bonus;
                    $this->bill_data($member, $sum_bonus);
                }
            }
        }
        return 1;
    }
    /**
     * 获取计算金钻和银钻的团队奖
     * @param  $uid        用户ID
     * @param  $temp_month 月份
     * @return  array
     */
    public function getTeamMoney($uid, $temp_month){
        $member = getMemberInfo($uid);
        if($member['is_team_vip'] == 1){
            //加上 下级董事团队的提货额
            $team_id_str = get_team_list($uid);
        } else {
            $team_id_str = get_team_person($uid);
        }
        $where['reabte_id'] = ['in', $team_id_str];
        $where['post_status'] = ['egt', 1];
        $where['delstate_time'] = 0;
        $where['statistics_time'] = $temp_month;
        $sum_orderamount = Db::name('fun_goodsorder')->where($where)->sum("orderamount");
        $sum_bonus = 0;
        if($sum_orderamount){
            $sum_bonus = getBonusInfo($sum_orderamount);
        }
        return $sum_bonus;
    }
    /**
     * 获取计算金钻和银钻的团队奖
     * @param  $vid        用户ID
     * @param  $bonus      团队获利
     * @param  $sum_pay    下级能得到的money
     * @param  $temp_month 月份
     * @return  array
     */
    public function director_team_money($vid, $sum_bonus, $sum_pay = 0, $temp_month){
        unset($where);
        $member = getMemberInfo($vid);
        $where['delete_status'] = 0;
        $where['referee_id'] = $vid;
        $where['rank'] = ['in', '4,9'];
        $members = Db::name('fun_member')->where($where)
            ->field('id, nickname, mobile, rank, referee_id, pre_referee_id,fanli_money,is_team_vip')->select()->toArray();
        if(!empty($members)){
            foreach ($members as $k=>$v){        //PID符合条件的
                $bonus = $this->getTeamMoney($v['id'], $temp_month);
                if($v['rank'] == 4) {
                    $sum_pay += $bonus;
                    Db::name('fun_member')->where('id', $v['id'])->update(['fanli_money' => $v['fanli_money'] + $bonus]);
                    $v['content'] = $v['nickname'] . '(' . getRankName($v['rank']) . ')获得' . $temp_month . '的团队奖--' . $bonus;
                    $this->bill_data($v, $bonus);
                } else {
                    //加上 下级董事团队的提货额
                    if($member['is_team_vip'] == 1){
                        $sum_pay += $bonus;
                    }
                    //董事的返利
                    $this->director_team_money($v['id'], $bonus, 0, $temp_month);
                }
            }
            $direct_bonus = $sum_bonus - $sum_pay;
            if($direct_bonus){
                Db::name('fun_member')->where('id', $member['id'])->update(['fanli_money'=>$member['fanli_money'] + $direct_bonus ]);
                $member['content'] = $member['nickname'].'('.getRankName($member['rank']).')获得'.$temp_month.'的团队奖--'.$direct_bonus;
                $this->bill_data($member, $direct_bonus);
            }
        } else {
            //最后董事得到的钱
            $direct_bonus = $sum_bonus - $sum_pay;
            if($direct_bonus){
                Db::name('fun_member')->where('id', $member['id'])->update(['fanli_money'=>$member['fanli_money'] + $direct_bonus ]);
                $member['content'] = $member['nickname'].'('.getRankName($member['rank']).')获得'.$temp_month.'的团队奖--'.$direct_bonus;
                $this->bill_data($member, $direct_bonus);
            }
        }
        return $sum_pay;
    }
    /**
     * 走库存的账单的复用代码
     * @param  array $parent_m           收益人
     * @param  float $orderamount        月底分红
     * @return array
     */
    public function bill_data($parent_m, $orderamount){
        $bill_data = array(
            'user_id'         => $parent_m['id'],
            'profit'          => $orderamount,//可提现
            'bill_type'       => 2,//1:消费，2：获利
            'profit_type'     => 4,//获利的方式(1:消费，2：代理升级，3:分流客服的客户充值得到的返利--冻结资金) 4:月底分红
            'create_time'     => time(),
            'content'         => $parent_m['content'],
            'statistics_time' => date('Y年m月', time())
        );
        $result = Db::name('fun_bill')->insert($bill_data);
        return $result;
    }
    /**
     * 定时任务（每个月的1号 零点0分定时结算团队奖(不算下级董事)）
     */
    public function index_old(){
        //获取上个月的月份
        $temp_month = date('Y年m月', time() - 3600);

        //全是董事
        $director = Db::name('fun_member')->field('id, nickname, mobile, rank, referee_id, pre_referee_id,fanli_money')->where('rank', 9)->select()->toArray();
        $company_id = Db::name('fun_member')->where('rank', 20)->value('id');
        $mem = Db::name('fun_member')->field('id, nickname, mobile, rank, referee_id, pre_referee_id,fanli_money')->where('rank', 4)->where(function($query )use ($company_id){
            $query->where('referee_id',0)
                ->whereOr('referee_id', $company_id);
        })->select()->toArray();
        $member_list = array_merge($director, $mem);
        if(empty($member_list)){
            exit();
        }
        foreach($member_list as $k => $member){
            unset($where);
            $team_id_str = get_team_person($member['id']);
            $where['userid'] = ['in', $team_id_str];
            $where['delstate_time'] = 0;
            $where['statistics_time'] = $temp_month;
            $sum_orderamount = Db::name('fun_goodsorder')->where($where)->sum("orderamount");
            if($sum_orderamount){
                $sum_bonus = getBonusInfo($sum_orderamount);
                $sum_pay = 0;
                unset($where);
                //获取当前人的下级是联创
                $where['referee_id'] = $member['id'];
                $where['rank'] = 4;
                $union_list = Db::name('fun_member')->where($where)->select()->toArray();
                if(!empty($union_list)){
                    unset($where);
                    foreach($union_list as $union){
                        $team_id_str = get_team_person($union['id']);
                        $where['userid'] = ['in', $team_id_str];
                        $where['delstate_time'] = 0;
                        $where['statistics_time'] = $temp_month;
                        $union_orderamount = Db::name('fun_goodsorder')->where($where)->sum("orderamount");
                        $bonus = getBonusInfo($union_orderamount);
                        if(!empty($bonus)){
                            $sum_pay += $bonus;
                            Db::name('fun_member')->where('id', $union['id'])->update(['fanli_money'=>$union['fanli_money'] + $bonus ]);
                            $union['content'] = $union['nickname'].'('.getRankName($union['rank']).')获得'.$temp_month.'的团队奖--'.$bonus;
                            $this->bill_data($union, $bonus);
                        }
                    }
                    //最后董事得到的钱
                    $direct_bonus = $sum_bonus - $sum_pay;
                    if($direct_bonus){
                        Db::name('fun_member')->where('id', $member['id'])->update(['fanli_money'=>$member['fanli_money'] + $direct_bonus ]);
                        $member['content'] = $member['nickname'].'('.getRankName($member['rank']).')获得'.$temp_month.'的团队奖--'.$direct_bonus;
                        $this->bill_data($member, $direct_bonus);
                    }
                }
                else{
                    //最后董事得到的钱
                    $direct_bonus = $sum_bonus - $sum_pay;
                    if($direct_bonus){
                        Db::name('fun_member')->where('id', $member['id'])->update(['fanli_money'=>$member['fanli_money'] + $direct_bonus ]);
                        $member['content'] = $member['nickname'].'('.getRankName($member['rank']).')获得'.$temp_month.'的团队奖--'.$direct_bonus;
                        $this->bill_data($member, $direct_bonus);
                    }
                }
            }

        }
        return 1;
    }
    /*写文件*/
    function act_log($week,$content){
        $dir = $_SERVER['DOCUMENT_ROOT']. '/act_log/';
        $dir = iconv("UTF-8", "GBK", $dir);
        if (!file_exists($dir)){
            mkdir ($dir,0777,true);
        }
        $filename =  $dir = iconv("UTF-8", "GBK", $dir.$week.'.txt');;
        $Ts = fopen($filename,"a+");
        fputs($Ts,"执行日期："."\r\n".date('Y-m-d H:i:s',time()).  ' ' . "\n" .$content."\n");
        fclose($Ts);
    }
}
