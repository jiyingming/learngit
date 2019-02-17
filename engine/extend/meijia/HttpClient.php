<?php
namespace meijia;

/**
 * 请求31meijia接口客户端操作
 * Class HttpClient
 * @package meijia
 */
class HttpClient
{
    use HttpRequest;

    /**
     * 创建guid方法
     * @return string 生成后的guid字符
     */
    private function generate_guid(){
        $charid = strtoupper(md5(uniqid(mt_rand(), true)));
        $hyphen = chr(45);// "-"
        $uuid = substr($charid, 0, 8).$hyphen
            .substr($charid, 8, 4).$hyphen
            .substr($charid,12, 4).$hyphen
            .substr($charid,16, 4).$hyphen
            .substr($charid,20,12);

        return $uuid;
    }

    /**
     * 生成授权数据
     * @param $url 授权URL地址
     * @return array 授权后的 header 信息
     */
    private function generate_Auth($url){
        //生成guid
        $guid = $this->generate_guid();
        $data = [
            'APPID'                 => 320447810,
            'httpMethod'            => 'POST',
            'ClientIP'              => '',
            'WebRequestGuid'        => date('YndHis', time()) . ':' . $guid,
            'AppRequestGuid'        => null,
            'RequestURL'            => $url,
            'WebRequestURL'         => null,
            'WebRequestUrlReferrer' => null,
            'APPAuth'               => ''
        ];

        $new_str = md5(json_encode($data,JSON_UNESCAPED_SLASHES) . 'waibaokaifashanglidecheng_31huiyi.com');

        $data['APPAuth'] = $new_str;

        return [
            'X_31HuiYi_LoginCookie' => '',
            'X_31HuiYi_AppAuth'     => json_encode($data,JSON_UNESCAPED_SLASHES)
        ];
    }

    /**
     * 根据查询条件获取与会者用户的完整信息
     * @param $event_id 会议编号
     * @param array $where 查询条件 [RealName，Mobile，Email，JoinId，SignInCode，WeiXinOpenId] 6个字段查询数据，满足一条即可
     * @return mixed 请求成功返回array,否则返回字符串
     */
    public function getUserInfo($event_id,$where = []){
        //请求url地址
        $requestUrl = 'http://openapi.31huiyi.com/rest/event/' . $event_id . '/getjoinuser';
        //根据URL生成授权头部信息
        $headers = $this->generate_Auth($requestUrl);
        
        $result = $this->post_json($requestUrl,$where,$headers);

        if ($result['Code'] === 0){ //获取成功
            return $result['Body'];
        } else {
            return $result['MessageToString'];
        }
    }

    /**
     * 根据条件查询联系人信息
     * @param $event_id 会议编号
     * @param array $where 查询条件 [RealName，MobilePhone，Email，MaxJoinCount，JoinContactId]
     * @param int $pageIndex 当前页面
     * @return mixed
     */
    public function getContactsByPage($event_id,$where = [],$pageIndex = 1){
        //请求url地址
        $requestUrl = 'http://openapi.31huiyi.com/rest/contact/getjoincontactlistvm/page/' . $pageIndex . '/pagesize/10';
        //查询参数
        $data = [
            'search'    => [
                [
                    'EventId'   => $event_id
                ]
            ]
        ];
        //组合查询条件
        array_push($data['search'],$where);
        //根据URL生成授权头部信息
        $headers = $this->generate_Auth($requestUrl);

        $result = $this->post_json($requestUrl,$data,$headers);

        if ($result['Code'] === 0){ //获取成功
            return $result['Body'];
        } else {
            return $result['MessageToString'];
        }
    }

    /**
     * 根据条件查询联系人信息
     * @param $event_id 会议编号
     * @param array $where 查询条件 [RealName，MobilePhone，Email，MaxJoinCount，JoinContactId]
     * @return array
     */
    public function getContactsList($event_id,$where = []){
        $pageIndex = 1;
        //获取联系人
        $data = $this->getContactsByPage($event_id,$where,$pageIndex);
        //计算剩余记录数
        $recordTotal = $data['Total'] - 10;
        //联系人列表
        $result = $data['List'];

        while ($recordTotal >0){
            $pageIndex ++;
            //获取联系人
            $data = $this->getContactsByPage($event_id,$where,$pageIndex);
            //计算剩余记录数
            $recordTotal = $data['Total'] - 10;
            //联系人列表
            $result = array_merge($result,$data['List']);
        }

        return $result;
    }

    /**
     * 根据会议编号获取所有与会者信息
     * @param $event_id 会议编号
     * @param int $pageIndex 当前页码
     * @return mixed
     */
    public function getAllUserByPage($event_id,$pageIndex = 1){
        //请求url地址
        $requestUrl = 'http://openapi.31huiyi.com/rest/event/' . $event_id . '/changeeventjoin/page/' . $pageIndex . '/pagesize/500/updatetime/1';
        //根据URL生成授权头部信息
        $headers = $this->generate_Auth($requestUrl);

        $result = $this->get($requestUrl,[],$headers);
        if ($result['Code'] === 0){ //获取成功
            return $result['Body'];
        } else {
            return $result['MessageToString'];
        }
    }

    /**
     * 获取所有与会者信息列表
     * @param $event_id 会议编号
     * @return array
     */
    public function getAllUserList($event_id){
        $pageIndex = 1;
        //获取与会者信息
        $data = $this->getAllUserByPage($event_id,$pageIndex);
        //计算剩余记录数
        $recordTotal = $data['Total'] - 500;
        //与会者信息列表
        $result = $data['List'];

        while ($recordTotal >0){
            $pageIndex ++;
            //获取联系人
            $data = $this->getContactsByPage($event_id,$pageIndex);
            //计算剩余记录数
            $recordTotal = $data['Total'] - 500;
            //与会者信息列表
            $result = array_merge($result,$data['List']);
        }

        return $result;
    }

    /**
     * 根据订单编号获取所有与会者信息
     * @param $event_id 会议编号
     * @param $order_id 订单编号
     * @param int $pageIndex 当前页码
     * @return mixed
     */
    public function getAllUserByOrderIdPage($event_id,$order_id,$pageIndex = 1){
        //请求url地址
        $requestUrl = 'http://openapi.31huiyi.com/rest/event/'.$event_id.'/getjoins/page/'. $pageIndex .'/pagesize/20';
        //发送数据
        $senddata = [
            'search' => [
                [
                    'OrderIdList' => [
                        'dollar__in'    => [
                            $order_id
                        ]
                    ]
                ]
            ]
        ];
        //根据URL生成授权头部信息
        $headers = $this->generate_Auth($requestUrl);
        $result = $this->post_json($requestUrl,$senddata,$headers);
        
        if ($result['Code'] === 0){ //获取成功
            return $result['Body'];
        } else {
            return $result['MessageToString'];
        }
    }

    /**
     * 根据订单编号获取所有与会者信息
     * @param $event_id 会议编号
     * @param $order_id 订单编号
     * @param int $pageIndex 当前页码
     * @return mixed
     */
    public function getAllUserPage($event_id,$where = [],$pageIndex = 1){
        //请求url地址
        $requestUrl = 'http://openapi.31huiyi.com/rest/event/'.$event_id.'/getjoins/page/'. $pageIndex .'/pagesize/500';
        //发送数据
        $senddata = [
            'search' => [
                    $where
            ]
        ];
        //根据URL生成授权头部信息
        $headers = $this->generate_Auth($requestUrl);

		//dump($senddata);
        $result = $this->post_json($requestUrl,$senddata,$headers);
 /*         dump($result);
        die;  */
        if ($result['Code'] === 0){ //获取成功
            return $result['Body'];
        } else {
            return $result['MessageToString'];
        }
    }



    /**
     * 根据订单编号获取所有与会者信息
     * @param $event_id 会议编号
     * @param $order_id 订单编号
     * @return array
     */
    public function getAllUserByOrderIdList($event_id,$order_id){

        $pageIndex = 1;
        //获取与会者信息
        $data = $this->getAllUserByOrderIdPage($event_id,$order_id,$pageIndex);
       // dump($data);exit;
        //计算剩余记录数
        $recordTotal = $data['Total'] - 20;
        //与会者信息列表
        $result = $data['List'];

        while ($recordTotal >0){
            
            $pageIndex ++;
            //获取联系人
            $data = $this->getAllUserByOrderIdPage($event_id,$order_id,$pageIndex);
            //计算剩余记录数
            $recordTotal = $data['Total'] - 20*$pageIndex;
            //与会者信息列表
            $result = array_merge($result,$data['List']);
        }

        return $result;
    }

    /**
     * 根据订单编号获取订单信息
     * @param $event_id 会议编号
     * @param $order_id 订单编号
     * @return mixed
     */
    public function getOrder($event_id,$order_id){
        //请求url地址
        $requestUrl ='http://openapi.31huiyi.com/rest/order/'. $event_id .'/getorder/'. $order_id;
        //根据URL生成授权头部信息

        $headers = $this->generate_Auth($requestUrl);
       // echo json_encode($headers);exit;
        $result = $this->get($requestUrl,[],$headers);
        
        return json_decode($result,true);
        /*if ($result['Code'] === 0){ //获取成功
            return $result['Body'];
        } else {
            return $result['MessageToString'];
        }*/
    }

    /**该接口是更新参会人的数据的接口，更新指定字段的数据
     * @param $eid //会议iD
     * @param $join_id //参会人ID
     * @param array $change_filed  //需要修改的字段序列化的内容 数组
     * @return mixed
     */
    public function updateEventJoin($eid,$join_id,$change_filed = [])
    {

        $enc = md5($join_id);
        //请求url地址
        $requestUrl = 'http://openapi.31huiyi.com/rest/'.$eid.'/updateeventjoin/'.$join_id.'?enc='.$enc;
        //发送数据
        $senddata = [
            'changeField' => $change_filed
        ];
        //根据URL生成授权头部信息
        $headers = $this->generate_Auth($requestUrl);
        $result = $this->post_json($requestUrl,$senddata,$headers);
//
//        dump($senddata);
//        die;
        if ($result['Code'] === 0){ //获取成功
            return 1;
        } else {
            return $result['MessageToString'];
        }
    }


	public function deleteJoin($eid,$join_id)
	{
		$enc = md5($join_id);
        //请求url地址
        //$requestUrl = 'http://openapi.31huiyi.com/rest/event/'.$eid.'/deletejoin/'.$join_id.'?enc='.$enc;
		$requestUrl = 'http://openapi.31huiyi.com/rest/event/'.$eid.'/deletejoin?JoinId='.$join_id.'&Enc='.$enc;
/* echo $requestUrl;
die; */
	   //发送数据
        $senddata = [
            'JoinId' => $join_id,
			'Enc' => $enc,
        ];
        //根据URL生成授权头部信息
        $headers = $this->generate_Auth($requestUrl);
        $result = $this->get($requestUrl,$senddata,$headers);
        $result = json_decode($result,True);
/*         dump($result);
        die; */
        if ($result['Code'] === 0){ //获取成功
            return 1;
        } else {
            return $result['MessageToString'];
        }
	}

    /**
     * 修改联系人信息
     * @param $eid
     * @param array $where
     * @param array $change_filed
     * @return int
     */
    public function updateContact($eid,$where = [],$change_filed = [])
    {
        //请求url地址
        $requestUrl = 'http://openapi.31huiyi.com/rest/contact/updateordercontact';
        //发送数据
        $senddata = [
            'EventId' => $eid
        ];
        $senddata = array_merge($senddata,$where,$change_filed);
        //根据URL生成授权头部信息
        $headers = $this->generate_Auth($requestUrl);
        $result = $this->post_json($requestUrl,$senddata,$headers);
        if ($result['Code'] === 0){ //获取成功
            return 1;
        } else {
            return $result['MessageToString'];
        }
    }

    /**
     * 更改联系人密码
     * @param $eid
     * @param array $where
     * @param array $change_filed
     * @return int
     */
    public function updateContactPassword($eid,$where = [],$change_filed = [])
    {
        //请求url地址
        $requestUrl = 'http://openapi.31huiyi.com/rest/contact/updateordercontactpassword';
        //发送数据
        $senddata = [
            'EventId' => $eid
        ];
        $senddata = array_merge($senddata,$where,$change_filed);
        //根据URL生成授权头部信息
        $headers = $this->generate_Auth($requestUrl);
        $result = $this->post_json($requestUrl,$senddata,$headers);
/* 		dump($change_filed);

		dump($senddata);
		die; */
//        dump($result);
//        die;
		if(isset($result['message']))
		{
			return $result['message'];
		}
        //$result = json_decode($result,True);
        if ($result['Code'] === 0){ //获取成功
            return 1;
        } else {
            return $result['MessageToString'] ?? $result['Message'];
        }
    }

    /*
    * 购买门票
    *$eid 会议id
    *$cate_id 通道id
    *$send_data 请求数据 数组
    */

    public function submitBuyTicket($eid,$cate_id,$send_data)
    {
        //请求url地址
        $requestUrl = 'http://openapi.31huiyi.com/BuyTicket/'.$eid.'/BuyTicketOrderSubmit/'.$cate_id;
        //根据URL生成授权头部信息
        $headers = $this->generate_Auth($requestUrl);
        $result = $this->post_json($requestUrl,$send_data,$headers);

        return $result;
       /* if ($result['Code'] === 0){ //获取成功

            return $result;
        } else {
            return $result['MessageToString'];
        }*/
    }



	public function sendEmail($send_data)
	{
		        //请求url地址
        $requestUrl = 'http://openapi.31huiyi.com/rest/system/sendemail';
        //根据URL生成授权头部信息
        $headers = $this->generate_Auth($requestUrl);
        $result = $this->post_json($requestUrl,$send_data,$headers);
		//dump($result);
		//die;

        if ($result['Code'] === 0){ //获取成功
            return 1;
        } else {
            return $result['MessageToString'];
        }
	}

    /*
    *发送短息
    *
    * $da=['Mobile'=>$data['tel'],'Code'=>$code,'UserId'=>1305923961];
    * 手机号  验证码 短息id
      返回   1 成功  body 验证码 2 失败
    **/
    public function sendcode($send_data){

        $requestUrl="http://openapi.31huiyi.com/rest/system/sendcode";
        $headers = $this->generate_Auth($requestUrl);
        $result = $this->post_json($requestUrl,$send_data,$headers);

        if($result['Code']==0){
            $arr=['code'=>1,'Body'=>$result['Body']];
            return $arr;
            exit;
        }else{

            $arr=['code'=>2];
            return $arr;
            exit;
        }


    }
     /* 
     *
     * 取消订单 返回只有调用接口成功  是否成功 查看返回字符串 或 重新获取订单信息 
     *
     **/

     public function cancelorder($eid,$orderId){

        $requestUrl="http://openapi.31huiyi.com/rest/".$eid."/cancelorder/".$orderId;
        $headers = $this->generate_Auth($requestUrl);
        $result = $this->post_json($requestUrl,[],$headers);

        return $result;


     }

     /*
     * 获取门票价格
     *
     *$eid 会议id
     *$TicketId
     *$num 数量
     *$DiscountCode 优惠码 没有 就是 空
     *InviteCode 邀约码
     * 返回 TotalMoney是原价，CurrentMoney当前价格,PromotionMoney公开优惠的金额价格,InviteMoney邀约码优惠金额，AfterDiscountMoney优惠码优惠后金额
     */
         public function  getTicketDiscounts($eid,$TicketId,$num,$DiscountCode,$InviteCode){
                
                
                $requestUrl="http://openapi.31huiyi.com/rest/{$eid}/getTicketDiscounts";
                $headers = $this->generate_Auth($requestUrl);
                $data1['InviteCode'] =$InviteCode;
                $data1['DiscountCode']=$DiscountCode;
                $data1['TicketGTaskDetails']=[['TicketId'=>$TicketId,'Num'=>$num]];
                $result=$this->post_json($requestUrl,$data1,$headers);
               // echo 1;exit;
                 return $result;
                //return 2;

        
    }


}