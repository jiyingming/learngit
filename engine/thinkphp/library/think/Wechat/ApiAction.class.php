<?php
class ApiAction {

	public function get_user_pic($uid,$ticket,$logo,$name)
	{
		$user_pic = "./imgpublic/user_".$uid.".jpg";
		
		if(!file_exists($user_pic))
		{
			$pic = 'https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket='.$ticket;
			$data = file_get_contents($pic);
			
			
			
			$filepath = "./imgpublic/";
			$filename = "ticket_".$uid.".jpg";
			$fp = @fopen($filepath.$filename,"w");
			@fwrite($fp,$data);
			fclose($fp);
			$ticket = $filepath.$filename;
			
			if(!empty($logo)){
				$pic = $logo;
				$data = file_get_contents($pic);
				$filepath = "./imgpublic/";
				$filename = "logo_".$uid.".jpg";
				$fp = @fopen($filepath.$filename,"w");
				@fwrite($fp,$data);
				fclose($fp);
				$logo = $filepath.$filename;
			}
			
// 			import ( 'ThinkImage', APP_PATH . 'Common/ThinkImage', '.class.php' );
			require 'ThinkImage.class.php';

			$img = new ThinkImage(1, './card.jpg'); 
			
			$img->open($ticket)->thumb(250, 250)->save($ticket);
			if(!empty($logo)){$img->open($logo)->thumb(70, 70)->save($logo);}
			
			
			if(!empty($logo)){
				$img->open('./card.jpg')->water($ticket, THINKIMAGE_WATER_SOUTHEAST)->water($logo, THINKIMAGE_WATER_NORTHWEST)->text($name,'./hei.ttf','16','#FFFF66', THINKIMAGE_WATER_NORTHWEST)->save($user_pic);
			}
			else
			{
				$img->open('./card.jpg')->water($ticket, THINKIMAGE_WATER_SOUTHEAST)->text($name,'./hei.ttf','16','#FFFF66', THINKIMAGE_WATER_NORTHWEST)->save($user_pic);
			}
		
		}
		return $user_pic;
	}
	
	public function ticket($usersresult)
	{
// 		推荐人
// 		$result_l = M("User")->where(array('id'=>$usersresult['l_id']))->find();

		//二维码
		if($usersresult['member']==1)
		{
			$ticket = $usersresult['ticket'];
			$wx_info = json_decode($usersresult['wx_info'],true);
			$logo = $wx_info['headimgurl'];
			$name = $wx_info['nickname'];
			
			$ticket = $this->get_user_pic($usersresult['openid'],$ticket,$logo,$name); 
			$pic = 'user_'.$usersresult['openid'].'.jpg';
		}
		else
		{
			$ticket = "./imgpublic/weiyun.jpg";
			$pic = 'weiun.jpg';
		}
		
		$returnValue['ticket'] = $ticket;
		$returnValue['pic'] = $pic;
		return $returnValue;
	}
		
}