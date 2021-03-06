<?php 
namespace Home\Controller;
use Think\Controller;
/**
* 众筹
*/
class PayController extends Controller{
	const  APP_ID='wx8abf422f863923b3';
	const  SECRET='7976d4dfdaee9f2db765c5f3cc48ffb9';
	const  PAY_KEY='EF52668B6FFF6DBE9AD16488E756E22E';
	const  TOKEN_CACHE_KEY = 'weixin_token';
	const  TICKET_CACHE_KEY = 'weixin_ticket';
	const  MCH_ID=1406051302;	
	public function sign($hid) {
		$gid = session('gid');
		if(!$gid) {
			$redirect='http://mei.vshijie.cn/bk/index.php/Home/Pay/Sign?hid='.$hid;
			$_SESSION['redirect']=$redirect;
			header('Location:http://mei.vshijie.cn/bk/index.php/Home/Guest/home');
		} else {
			$result = M('houseinfo')->WHERE("id={$hid}")->find();
			if(!$_SESSION['begin']) {
	        	$_SESSION['begin']=date("Y年m月d日").'(入住)';
	        }
	        if(!$_SESSION['end']) {
	        	$_SESSION['end']=date("Y年m月d日",strtotime("+1 days")).'(退房)';;
	        }
	        $begin=$_SESSION['begin'];
	        $beginYear=((int)substr($begin,0,4));//年
    		$beginMonth=((int)substr($begin,7,2));//月
    		$beginDay=((int)substr($begin,12,2));//天
    		$beginTime = mktime(0,0,0,$beginMonth,$beginDay,$beginYear);
    		$end=$_SESSION['end'];
	        $endYear=((int)substr($end,0,4));//年
    		$endMonth=((int)substr($end,7,2));//月
    		$endDay=((int)substr($end,12,2));//天
    		$endTime = mktime(0,0,0,$endMonth,$endDay,$endYear);
    		$total = ($endTime-$beginTime)/3600/24;
    		if($total<=0) {
    			$total=1;
    		}
    		$r_fee=($total+1)*$result['price'];
    		$this->assign('total',$total);
			$this->assign('result',$result);
			$this->assign('r_fee',$r_fee);
			$this->display();
		}
	}

	public function ajaxSign() {
			$begin=$_SESSION['begin'];
	        $beginYear=((int)substr($begin,0,4));//年
    		$beginMonth=((int)substr($begin,7,2));//月
    		$beginDay=((int)substr($begin,12,2));//天
    		$beginTime = mktime(0,0,0,$beginMonth,$beginDay,$beginYear);
    		$end=$_SESSION['end'];
	        $endYear=((int)substr($end,0,4));//年
    		$endMonth=((int)substr($end,7,2));//月
    		$endDay=((int)substr($end,12,2));//天
    		$endTime = mktime(0,0,0,$endMonth,$endDay,$endYear);
	    	$total = ($endTime-$beginTime)/3600/24;
	    	if($total<=0) {
    			$total=1;
    		}
    		$_POST['begin_time']=$beginYear.'-'.$beginMonth.'-'.$beginDay;
	    	$_POST['end_time']=$endYear.'-'.$endMonth.'-'.$endDay;
    		$beginTime =$_POST['begin_time'];
			$endTime = $_POST['end_time'];
			$hid=$_POST['hid'];
			$houseinfo = M('houseinfo')->WHERE("id={$hid}")->find();
			$r_fee=($total+1)*$houseinfo['price'];
			$maxUsed = M('h_time')->FIELD('MAX(used) as used')->WHERE("date>='{$beginTime}' AND date<='{$endTime}' AND hid={$hid}")->find();
			if(is_null($maxUsed['used']) || $maxUsed['used']<$houseinfo['total']) {
				$_POST['r_fee']=$r_fee;
				$_POST['r_trade_no']=time();
				session('b_time_queue', $_POST);
				$openid=$_POST['openid'];
				$data = $this->payConfig($openid,$hid,$r_fee);
				$this->ajaxReturn($data);
			}
		$data['status'] = 'no';
		$this->ajaxReturn($data);
	}
	
	public function payConfig($openid,$hid,$r_fee) {
		$body='自在乡居-预订民宿支付';
		$unifiedorder['total_fee']=100*$r_fee;
		$unifiedorder['appid']=self::APP_ID;
		$unifiedorder['mch_id']=self::MCH_ID;
		$unifiedorder['nonce_str']='suijizifuchuanhao';
		$unifiedorder['body']=$body;
		$_POST=session('b_time_queue');
		$unifiedorder['out_trade_no']=$_POST['r_trade_no'];
		$unifiedorder['spbill_create_ip']=getIP();
		$unifiedorder['notify_url']='http://mei.vshijie.cn/minsu/index.php/Home/Weixin/payResult';
		$unifiedorder['trade_type']='JSAPI';
		$unifiedorder['openid']=$openid;
		ksort($unifiedorder);
		$sign="";
		foreach ($unifiedorder as $key => $val) {
		    $sign=($sign.$key.'='.$val.'&');
		}
		$sign=$sign.'key='.self::PAY_KEY;
		$sign=md5($sign);
		$unifiedorder['sign']=strtoupper($sign);
		$prepayIdXml = send_post('https://api.mch.weixin.qq.com/pay/unifiedorder', arrayToXml($unifiedorder));
//		var_dump($r_fee.'---'.htmlentities($prepayIdXml));
		if(strpos($prepayIdXml,'prepay_id')) {
			$prepayIdObj = simplexml_load_string($prepayIdXml, 'SimpleXMLElement', LIBXML_NOCDATA);
			$prepayId=$prepayIdObj->xpath('prepay_id');
			if(!is_null($prepayId)) {
				$data['appId']=self::APP_ID;
				$data['nonceStr']='signRandom';
				$data['timeStamp']=time(true);
				$data['package']='prepay_id='.$prepayId[0];
				$data['signType']='MD5';
				ksort($data);
				$paySign="";
				foreach ($data as $key => $val) {
				    $paySign=($paySign.$key.'='.$val.'&');
				}
				$paySign=$paySign.'key='.self::PAY_KEY;
				$data['paySign']=strtoupper(md5($paySign));
				$data['status']=1;
				return $data;
				
			}
		}
		$data['status']=0;
		return $data;
	}
	
	public function ajaxAppoint($u,$oid) {
		$bTimeQueue = session('b_time_queue');
		if($bTimeQueue) {
			$guest = M('guest')->WHERE("openid='{$oid}'")->find();
			$r_id=$guest['id'];
			$bTimeQueue['r_name']=$guest['name'];
			$bTimeQueue['r_phone']=$guest['phone'];
			$bTimeQueue['r_sex']=$guest['sex'];
			$createtime=date('Y-m-d H:i:s');
			$bTimeQueue['createtime']=$createtime;
			$bTimeQueue['status']=1;
			$bTimeQueue['amount']=1;
			$bTimeQueue['r_id']=$r_id;
			M('h_time_queue')->add($bTimeQueue);
			$begin_time = strtotime(date($bTimeQueue['begin_time']));
			$end_time = strtotime(date($bTimeQueue['end_time']));
			$hid=$bTimeQueue['hid'];
			while($begin_time<=$end_time) {
				$begin=date('Y-m-d',$begin_time);
				$sql="insert into bk_h_time (hid,date)values({$hid},'{$begin}') ON DUPLICATE KEY UPDATE used=used+1";
				M()->execute($sql);
				$begin_time=$begin_time+86400;
			}
			unset($_SESSION['b_time_queue']);
			$house=M('houseinfo')->WHERE("id={$hid}")->find();
			sendText($house['contact'],'【自在乡居】您好，您在自在乡居发布的乡居刚刚接到定单，请尽快处理订单信息，准备为客户提供及时周到的服务。生意兴隆从服务做起。');
			sendText($bTimeQueue['r_phone'],'【自在乡居】亲爱的村民，您的订单已确认。村支书温馨提示：入住前24小时内可免费取消或修改订单，如果不满24小时内的调整，则需要扣除当天房费，调整流程欢迎致电13718138279，祝您享用乡下美好时光！');
		}
		header('Location: '.$u);
	}
}
 ?>