<?php
// JK客户定制

namespace app\admin\common;
use think\facade\Db;
class Alipay
{
	//支付宝小程序支付
	function build_alipay($aid,$bid,$mid,$title,$ordernum,$price,$tablename,$notify_url='',$return_url='',$more=1,$alih5=false,$trade_component_order_id='',$openid='',$openid_new=''){
		if(!$notify_url) $notify_url = PRE_URL.'/notify.php';
		$appinfo = \app\admin\common\System::appinfo($aid,'alipay');
		$member = Db::name('member')->where('id',$mid)->find();
		$isbusinesspay = false;
		if($appinfo['sxpay']==1){
			if($bid > 0){
				$business = Db::name('business')->where('id',$bid)->find();
				if($business['sxpay_mno']){
					$rs = \app\custom\Sxpay::build_alipay($aid,$bid,$mid,$title,$ordernum,$price,$tablename,$notify_url);
					return $rs;
				}
			}
			$rs = \app\custom\Sxpay::build_alipay($aid,$bid,$mid,$title,$ordernum,$price,$tablename,$notify_url);
			return $rs;
		}
        require_once(ROOT_PATH.'/extend/aop/AopClient.php');
		require_once(ROOT_PATH.'/extend/aop/AopCertification.php');
		require_once(ROOT_PATH.'/extend/aop/request/AlipayTradeCreateRequest.php');
        $app_auth_token = null;
        $aop = new \AopClient();
		$aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
		$aop->appId = $appinfo['appid'];
		$aop->rsaPrivateKey = $appinfo['appsecret'];
		$aop->alipayrsaPublicKey = $appinfo['publickey'];
		$aop->apiVersion = '1.0';
		$aop->signType = 'RSA2';
		$aop->postCharset = 'utf-8';
		$aop->format = 'json';
		


		$request = new \AlipayTradeCreateRequest ();
		$bizcontent = [];
		//兼容 openid 新规则
        if($member){
            if($appinfo['openid_set'] =='userid'){
                $bizcontent['buyer_id'] = $member['alipayopenid'];
            }else{
                $bizcontent['buyer_open_id'] = $member['alipayopenid_new'];
            }
        }else{
            if($appinfo['openid_set'] =='openid'){
                $bizcontent['buyer_open_id'] = $openid_new;
            }else{
                $bizcontent['buyer_id'] = $openid;
            }
        }

		$bizcontent['subject'] = mb_substr($title,0,42);
		$bizcontent['op_app_id'] = $appinfo['appid'];
		$bizcontent['out_trade_no'] = ''.$ordernum;
		$bizcontent['total_amount'] = $price;
		$bizcontent['product_code'] = 'JSAPI_PAY';
		$bizcontent['passback_params'] = urlencode($aid.':'.$tablename.':alipay:1:'.$bid);
        $extend_params = [];
        //交易组件order_id
        if($trade_component_order_id){
            $extend_params['trade_component_order_id'] = $trade_component_order_id;
        }
        if($extend_params){
            $bizcontent['extend_params'] = $extend_params;
        }
		if($tablename == 'shop'){
			$oglist = Db::name('shop_order_goods')->where('aid',$aid)->where('ordernum',$ordernum)->select()->toArray();
			if($oglist){
				$goodsDetail = [];
				foreach($oglist as $og){
					$goodsDetail[] = [
						'goods_id'=>$og['proid'].'_'.$og['ggid'],
						'goods_name'=>$og['name'].'('.$og['ggname'].')',
						'quantity'=>$og['num'],
						'price'=>$og['sell_price'],
					];
				}
				$bizcontent['goodsDetail'] = $goodsDetail;
			}
		}
        writeLog(json_encode(['appid'=>$appinfo['appid']]));
        writeLog(json_encode($bizcontent,JSON_UNESCAPED_UNICODE));
		$request->setBizContent(jsonEncode($bizcontent));

		$request->setNotifyUrl($notify_url);
		$result = $aop->execute ( $request,null,$app_auth_token);
		$responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
		$resultCode = $result->$responseNode->code;
        writeLog('-----order.trade-----');
        writeLog(json_encode([
            'code4'=>$result->$responseNode->code,
            'msg'=>$result->$responseNode->sub_msg,
            'sub_code'=>$result->$responseNode->sub_code,
        ]));
        writeLog('-----order.trade-----');
		if(!empty($resultCode)&&$resultCode == 10000){
			return ['status'=>1,'data'=>$result->$responseNode];
		} else {
			return ['status'=>0,'msg'=>$result->$responseNode->sub_msg];
		}
	}
	//支付宝H5支付
	function build_h5($aid,$bid,$mid,$title,$ordernum,$price,$tablename,$notify_url='',$return_url='',$more=1,$alih5=false,$trade_component_order_id='',$openid='',$openid_new=''){
		if(!$notify_url) $notify_url = PRE_URL.'/notify.php';
		if(!$return_url) $return_url = m_url('pages/my/usercenter', $aid);
		$appinfo = \app\admin\common\System::appinfo($aid,'h5');
		$app_auth_token = null;
        require_once(ROOT_PATH.'/extend/aop/AopClient.php');
		require_once(ROOT_PATH.'/extend/aop/AopCertification.php');
		require_once(ROOT_PATH.'/extend/aop/request/AlipayTradeWapPayRequest.php');

		$aop = new \AopClient();
		$aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
		if($more ==1 ){
			$aop->appId = $appinfo['ali_appid'];
			$aop->rsaPrivateKey = $appinfo['ali_privatekey'];
			$aop->alipayrsaPublicKey = $appinfo['ali_publickey'];
		}else{
			$aop->appId = $appinfo['ali_appid'.$more];
			$aop->rsaPrivateKey = $appinfo['ali_privatekey'.$more];
			$aop->alipayrsaPublicKey = $appinfo['ali_publickey'.$more];
		}
		
		$aop->apiVersion = '1.0';
		$aop->signType = 'RSA2';
		$aop->postCharset = 'utf-8';
		$aop->format = 'json';

		$request = new \AlipayTradeWapPayRequest ();
		$bizcontent = [];
		$bizcontent['body'] = mb_substr($title,0,42);
		$bizcontent['subject'] = mb_substr($title,0,42);
		$bizcontent['out_trade_no'] = ''.$ordernum;
		$bizcontent['total_amount'] = $price;
		$bizcontent['product_code'] = 'QUICK_WAP_WAY';
		$bizcontent['quit_url'] = $return_url;
		$bizcontent['passback_params'] = urlencode($aid.':'.$tablename.':h5:'.$more.':'.$bid);

		if($tablename == 'shop'){
			$oglist = Db::name('shop_order_goods')->where('aid',$aid)->where('ordernum',$ordernum)->select()->toArray();
			if($oglist){
				$goodsDetail = [];
				foreach($oglist as $og){
					$goodsDetail[] = [
						'goods_id'=>$og['proid'].'_'.$og['ggid'],
						'goods_name'=>$og['name'].'('.$og['ggname'].')',
						'quantity'=>$og['num'],
						'price'=>$og['sell_price'],
					];
				}
				$bizcontent['goodsDetail'] = $goodsDetail;
			}
		}
		
		//echo $notify_url;die;
		$request->setBizContent(jsonEncode($bizcontent));
		$request->setNotifyUrl($notify_url);
		$request->setReturnUrl($return_url);
		$result = $aop->pageExecute($request,'POST',$app_auth_token);
        
		return ['status'=>1,'data'=>$result];
	}
	//支付宝APP支付
	function build_app($aid,$bid,$mid,$title,$ordernum,$price,$tablename,$notify_url='',$return_url='',$more=1,$alih5=false,$trade_component_order_id='',$openid='',$openid_new=''){
		if(!$notify_url) $notify_url = PRE_URL.'/notify.php';
		$appinfo = \app\admin\common\System::appinfo($aid,'app');
		require_once(ROOT_PATH.'/extend/aop/AopClient.php');
		require_once(ROOT_PATH.'/extend/aop/AopCertification.php');
		require_once(ROOT_PATH.'/extend/aop/request/AlipayTradeAppPayRequest.php');

		$aop = new \AopClient();
		$aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';

		if($more ==1 ){
			$aop->appId = $appinfo['ali_appid'];
			$aop->rsaPrivateKey = $appinfo['ali_privatekey'];
			$aop->alipayrsaPublicKey = $appinfo['ali_publickey'];
		}else{
			$aop->appId = $appinfo['ali_appid'.$more];
			$aop->rsaPrivateKey = $appinfo['ali_privatekey'.$more];
			$aop->alipayrsaPublicKey = $appinfo['ali_publickey'.$more];
		}

		$aop->apiVersion = '1.0';
		$aop->signType = 'RSA2';
		$aop->postCharset = 'utf-8';
		$aop->format = 'json';

		$request = new \AlipayTradeAppPayRequest();
		$bizcontent = [];
		$bizcontent['body'] = mb_substr($title,0,42);
		$bizcontent['subject'] = mb_substr($title,0,42);
		$bizcontent['out_trade_no'] = ''.$ordernum;
		$bizcontent['total_amount'] = $price;
		$bizcontent['product_code'] = 'QUICK_MSECURITY_PAY';
		$bizcontent['passback_params'] = urlencode($aid.':'.$tablename.':app:'.$more);

		if($tablename == 'shop'){
			$oglist = Db::name('shop_order_goods')->where('aid',$aid)->where('ordernum',$ordernum)->select()->toArray();
			if($oglist){
				$goodsDetail = [];
				foreach($oglist as $og){
					$goodsDetail[] = [
						'goods_id'=>$og['proid'].'_'.$og['ggid'],
						'goods_name'=>$og['name'].'('.$og['ggname'].')',
						'quantity'=>$og['num'],
						'price'=>$og['sell_price'],
					];
				}
				$bizcontent['goodsDetail'] = $goodsDetail;
			}
		}

		$request->setBizContent(jsonEncode($bizcontent));
		$request->setNotifyUrl($notify_url);
		$result = $aop->sdkExecute($request);
		return ['status'=>1,'data'=>$result];
	}
	//支付宝退款
	public static function refund($aid,$platform,$ordernum,$totalprice,$refundmoney,$refund_desc='退款',$bid=0,$payorder=[]){
		if(!$refund_desc) $refund_desc = '退款';
        if($platform =='cashdesk' || $platform =='restaurant_cashdesk'){
            $appinfo = Db::name('admin_setapp_'.$platform)->where('aid',$aid)->where('bid',0)->find();
            if($bid > 0){
                $bappinfo = Db::name('admin_setapp_'.$platform)->where('aid',$aid)->where('bid',$bid)->find();
                if($platform =='cashdesk') {
                    $restaurant_sysset = Db::name('business_sysset')->where('aid', $aid)->find();
                }else{
                    $restaurant_sysset = Db::name('restaurant_admin_set')->where('aid',$aid)->find();
                }
                if(!$restaurant_sysset || $restaurant_sysset['business_cashdesk_alipay_type'] ==0){
                    return ['status'=>0,'msg'=>'支付宝收款已禁用'];
                }
                //3：独立收款
                if($restaurant_sysset['business_cashdesk_alipay_type'] ==3){
                    $appinfo['ali_appid'] = $bappinfo['ali_appid'];
                    $appinfo['ali_privatekey'] = $bappinfo['ali_privatekey'];
                    $appinfo['ali_publickey'] = $bappinfo['ali_publickey'];
                }
            }
        }else{
            $appinfo = \app\admin\common\System::appinfo($aid,$platform);
            if($appinfo['sxpay']){
                $sxpayrs = \app\custom\Sxpay::refund($aid,$platform,$ordernum,$totalprice,$refundmoney,$refund_desc,$bid);
                return  $sxpayrs;
            }
        }
		if($platform == 'h5' || $platform == 'app' ){
			$appinfo['appid'] = $appinfo['ali_appid'];
			$appinfo['appsecret'] = $appinfo['ali_privatekey'];
			$appinfo['publickey'] = $appinfo['ali_publickey'];
		}
        if($platform == 'cashdesk' || $platform =='restaurant_cashdesk'){
            $appinfo['appid'] = $appinfo['ali_appid'];
            $appinfo['appsecret'] = $appinfo['ali_privatekey'];
            $appinfo['publickey'] = $appinfo['ali_publickey'];
        }
		require_once(ROOT_PATH.'/extend/aop/AopClient.php');
		require_once(ROOT_PATH.'/extend/aop/AopCertification.php');
		require_once(ROOT_PATH.'/extend/aop/request/AlipayTradeRefundRequest.php');

		$aop = new \AopClient();
		$aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
		$aop->appId = $appinfo['appid'];
		$aop->rsaPrivateKey = $appinfo['appsecret'];
		$aop->alipayrsaPublicKey = $appinfo['publickey'];
		$aop->apiVersion = '1.0';
		$aop->signType = 'RSA2';
		$aop->postCharset = 'utf-8';
		$aop->format = 'json';

		$request = new \AlipayTradeRefundRequest();
		$bizcontent = [];
		$bizcontent['out_trade_no'] = $ordernum;
		$bizcontent['out_request_no'] = $ordernum. '_' . rand(1000, 9999);
		$bizcontent['refund_amount'] = $refundmoney*100/100;
		$bizcontent['refund_reason'] = $refund_desc;
		$request->setBizContent(jsonEncode($bizcontent));
		$result = $aop->execute($request);
		$responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
		$resultCode = $result->$responseNode->code;
		if(!empty($resultCode)&&$resultCode == 10000){
			return ['status'=>1,'msg'=>'退款成功'];
		}else{
			return ['status'=>0,'msg'=>$result->$responseNode->sub_msg];
		}
	}
    //支付宝付款码付款
    public static function build_scan($aid,$bid,$mid,$title,$ordernum,$price,$tablename,$notify_url='',$auth_code='',$platform='cashdesk'){      
        if(!$notify_url) $notify_url = PRE_URL.'/notify.php';
        if($platform =='cashdesk' || $platform =='restaurant_cashdesk'){
            $appinfo = Db::name('admin_setapp_'.$platform)->where('aid',$aid)->where('bid',0)->find();
            if($bid > 0){
                $bappinfo = Db::name('admin_setapp_'.$platform)->where('aid',$aid)->where('bid',$bid)->find();
                if($platform =='cashdesk'){
                    $restaurant_sysset = Db::name('business_sysset')->where('aid',$aid)->find();
                }else{
                    $restaurant_sysset = Db::name('restaurant_admin_set')->where('aid',$aid)->find();
                }
                if(!$restaurant_sysset || $restaurant_sysset['business_cashdesk_alipay_type'] ==0){
                    return ['status'=>0,'msg'=>'支付宝收款已禁用'];
                }
                //3：独立收款
                if($restaurant_sysset['business_cashdesk_alipay_type'] ==3){
                    $appinfo['ali_appid'] = $bappinfo['ali_appid'];
                    $appinfo['ali_privatekey'] = $bappinfo['ali_privatekey'];
                    $appinfo['ali_publickey'] = $bappinfo['ali_publickey'];
                }
            }
        } else{
            $appinfo = \app\admin\common\System::appinfo($aid,$platform);
        }
        require_once(ROOT_PATH.'/extend/aop/AopClient.php');
        require_once(ROOT_PATH.'/extend/aop/AopCertification.php');
        require_once(ROOT_PATH.'/extend/aop/request/AlipayTradePayRequest.php');
        $aop = new \AopClient ();
        $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
        $aop->appId = $appinfo['ali_appid'];
        $aop->rsaPrivateKey = $appinfo['ali_privatekey'];
        $aop->alipayrsaPublicKey=$appinfo['ali_publickey'];
        $aop->apiVersion = '1.0';
        $aop->signType = 'RSA2';
        $aop->postCharset='utf-8';
        $aop->format='json';
        $object = new \stdClass();
        $object->out_trade_no = $ordernum;
        $object->total_amount = $price;
        $object->subject = mb_substr($title,0,42);
        $object->scene ='bar_code';
        $object->auth_code = $auth_code;
        
//        $bizcontent['passback_params'] = urlencode($aid.':'.$tablename.':app');
      ;
        $json = json_encode($object,JSON_UNESCAPED_UNICODE);
        $request = new \AlipayTradePayRequest();
        $request->setBizContent($json);
        $result = $aop->execute($request);
        \think\facade\Log::write('支付宝扫码支付日志：');
        \think\facade\Log::write(json_encode($result,JSON_UNESCAPED_UNICODE));
        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $resultCode = $result->$responseNode->code;
        if(!empty($resultCode)&&$resultCode == 10000){
            $return = [
                'trade_no' => $result->$responseNode->trade_no,
                'buyer_open_id' => $result->$responseNode->buyer_open_id,
                'buyer_user_id' => $result->$responseNode->buyer_user_id,
            ];
            return ['status'=>1,'msg'=>'付款成功','data' =>  $return];
        } else {
            //附带返回码：10003=等待用户付款、20000=服务不可用(结果未知)，调用方据此决定是否查询/撤销
            return ['status'=>0,'msg'=>$result->$responseNode->sub_msg,'code'=>strval($resultCode)];
        }
    }

    /**
     * 收银台当面付：按 build_scan 相同规则取收款配置（平台收款/商户独立收款），返回 AopClient
     */
    protected static function cashdeskAopClient($aid,$bid,$platform='cashdesk'){
        $appinfo = Db::name('admin_setapp_'.$platform)->where('aid',$aid)->where('bid',0)->find();
        if($bid > 0){
            $bappinfo = Db::name('admin_setapp_'.$platform)->where('aid',$aid)->where('bid',$bid)->find();
            if($platform =='cashdesk'){
                $restaurant_sysset = Db::name('business_sysset')->where('aid',$aid)->find();
            }else{
                $restaurant_sysset = Db::name('restaurant_admin_set')->where('aid',$aid)->find();
            }
            if($restaurant_sysset && $restaurant_sysset['business_cashdesk_alipay_type'] ==3 && $bappinfo){
                $appinfo['ali_appid'] = $bappinfo['ali_appid'];
                $appinfo['ali_privatekey'] = $bappinfo['ali_privatekey'];
                $appinfo['ali_publickey'] = $bappinfo['ali_publickey'];
            }
        }
        require_once(ROOT_PATH.'/extend/aop/AopClient.php');
        $aop = new \AopClient ();
        $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
        $aop->appId = $appinfo['ali_appid'] ?? '';
        $aop->rsaPrivateKey = $appinfo['ali_privatekey'] ?? '';
        $aop->alipayrsaPublicKey = $appinfo['ali_publickey'] ?? '';
        $aop->apiVersion = '1.0';
        $aop->signType = 'RSA2';
        $aop->postCharset='utf-8';
        $aop->format='json';
        return $aop;
    }

    /**
     * 收银台当面付交易查询（使用与下单相同的收款配置）
     * 返回 data.trade_status: WAIT_BUYER_PAY / TRADE_SUCCESS / TRADE_FINISHED / TRADE_CLOSED
     */
    public static function cashdeskTradeQuery($aid,$bid,$ordernum,$platform='cashdesk'){
        try{
            $aop = self::cashdeskAopClient($aid,$bid,$platform);
            require_once(ROOT_PATH.'/extend/aop/request/AlipayTradeQueryRequest.php');
            $request = new \AlipayTradeQueryRequest ();
            $request->setBizContent(json_encode(['out_trade_no'=>''.$ordernum],JSON_UNESCAPED_UNICODE));
            $result = $aop->execute($request);
        }catch(\Throwable $e){
            return ['status'=>0,'msg'=>$e->getMessage()];
        }
        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $node = $result->$responseNode ?? null;
        if($node && $node->code == 10000){
            return ['status'=>1,'msg'=>'成功','data'=>[
                'trade_status' => strval($node->trade_status ?? ''),
                'trade_no' => strval($node->trade_no ?? ''),
                'buyer_open_id' => strval($node->buyer_open_id ?? ''),
                'buyer_user_id' => strval($node->buyer_user_id ?? ''),
            ]];
        }
        return ['status'=>0,'msg'=>$node ? strval($node->sub_msg ?? $node->msg ?? '查询失败') : '查询失败','code'=>$node ? strval($node->code ?? '') : ''];
    }

    /**
     * 收银台当面付撤销（alipay.trade.cancel）：未支付则关闭，已支付则原路退款；retry_flag=Y 时重试
     */
    public static function cashdeskTradeCancel($aid,$bid,$ordernum,$platform='cashdesk'){
        $msg = '撤销失败';
        for($i=0;$i<3;$i++){
            try{
                $aop = self::cashdeskAopClient($aid,$bid,$platform);
                require_once(ROOT_PATH.'/extend/aop/request/AlipayTradeCancelRequest.php');
                $request = new \AlipayTradeCancelRequest ();
                $request->setBizContent(json_encode(['out_trade_no'=>''.$ordernum],JSON_UNESCAPED_UNICODE));
                $result = $aop->execute($request);
            }catch(\Throwable $e){
                $msg = $e->getMessage();
                continue;
            }
            \think\facade\Log::write('支付宝当面付撤销：'.json_encode($result,JSON_UNESCAPED_UNICODE));
            $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
            $node = $result->$responseNode ?? null;
            if($node && $node->code == 10000){
                return ['status'=>1,'msg'=>'撤销成功'];
            }
            $msg = $node ? strval($node->sub_msg ?? $node->msg ?? '撤销失败') : '撤销失败';
            if(!$node || strtoupper(strval($node->retry_flag ?? 'N')) != 'Y'){
                break;
            }
            sleep(1);
        }
        return ['status'=>0,'msg'=>$msg];
    }

    public static function build_scan_query($aid,$ordernum,$trade_no){
        $appinfo = \app\admin\common\System::appinfo($aid,'app');
        require_once(ROOT_PATH.'/extend/aop/AopClient.php');
        require_once(ROOT_PATH.'/extend/aop/AopCertification.php');
        require_once(ROOT_PATH.'/extend/aop/request/AlipayTradeQueryRequest.php');
        $aop = new \AopClient ();
        $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
        $aop->appId = $appinfo['ali_appid'];
        $aop->rsaPrivateKey = $appinfo['ali_privatekey'];
        $aop->alipayrsaPublicKey=$appinfo['ali_publickey'];
        $aop->apiVersion = '1.0';
        $aop->signType = 'RSA2';
        $aop->postCharset='utf-8';
        $aop->format='json';
        $request = new \AlipayTradeQueryRequest ();
        $bizcontent = [];
        $bizcontent['out_trade_no'] =''.$ordernum;
        $request->setBizContent(jsonEncode($bizcontent));
        $result = $aop->execute ( $request);
        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $resultCode = $result->$responseNode->code;
        if(!empty($resultCode)&&$resultCode == 10000){
            $return = [
                'trade_no' => $result->$responseNode->trade_no,
            ];
            return ['status'=>1,'msg'=>'成功','data' =>  $return];
        } else {
            return ['status'=>0,'msg'=>$result->$responseNode->sub_msg];
        }
    }

    public static function transfers($aid,$ordernum,$money,$order_title,$identity,$name,$remark='账户提现'){
        //开发 > 服务端 > 营销产品 > 红包 > API 列表 > “B2C”现金红包 > 单笔转账接口 https://opendocs.alipay.com/open/02byvi?pathHash=b367173b
        $cashWith = new \app\admin\custom\alipay\Cashwith();
        $rs = $cashWith->withdr($ordernum, $identity, $money, $name, $order_title, $remark);

        if (!is_array($rs)) {
            $msg = (string)$rs;
            return ['status' => 0, 'msg' => $msg, 'sub_msg' => $msg];
        }

        if (!isset($rs['sub_msg'])) {
            $rs['sub_msg'] = $rs['msg'] ?? '';
        }
        if (!isset($rs['resp']) || !is_array($rs['resp'])) {
            $rs['resp'] = [];
        }
        if (!isset($rs['resp']['payment_no'])) {
            $rs['resp']['payment_no'] = $rs['resp']['order_id'] ?? ($rs['out_biz_no'] ?? '');
        }

        return $rs;
    }

    /*+++++++++++++++++++++++++++++支付宝交易组件接口 Start+++++++++++++++++++++++++++++++++++++++*/
    //支付宝交易组件订单创建
    //https://opendocs.alipay.com/mini/54f80876_alipay.open.mini.order.create?pathHash=b9743ab7&ref=api&scene=common
    public static function pluginOrderCreate($aid,$orderid,$mid,$title,$ordernum,$price,$tablename,$source_id=''){
        $appinfo = \app\admin\common\System::appinfo($aid,'alipay');
        $member = Db::name('member')->where('id',$mid)->find();
        require_once(ROOT_PATH.'/extend/aop/AopClient.php');
        require_once(ROOT_PATH.'/extend/aop/request/AlipayOpenMiniOrderCreateRequest.php');

        $bizcontent = [];
        //兼容 openid 新规则
        if($member['alipayopenid']){
            $bizcontent['buyer_id'] = $member['alipayopenid'];
        }else{
//            $bizcontent['buyer_open_id'] = $member['alipayopenid_new'];
        }
        $bizcontent['out_order_id'] = ''.$ordernum;;
        $bizcontent['title'] = $title;
        $bizcontent['merchant_biz_type'] = 'KX_SHOPPING';
        if($source_id){
            $bizcontent['source_id'] = $source_id;
        }
        $bizcontent['path'] = "/shopPackage/order/detail?id={$orderid}";//小程序订单详情链接
        $goodsDetail = [];
        if($tablename == 'shop' || $tablename=='shop_hb'){
            $oglist = Db::name('shop_order_goods')->where('aid',$aid)->where('ordernum',$ordernum)->select()->toArray();
            if($oglist){
                foreach($oglist as $og){
                    $goodsDetail[] = [
                        'goods_id'=>$og['proid'].'_'.$og['ggid'],
                        'goods_name'=>$og['name'].'('.$og['ggname'].')',
                        'item_cnt'=>$og['num'],
                        'sale_price'=>$og['sell_price'],
                    ];
                }
            }
        }
        $orderdetail = [];
        $orderdetail['item_infos'] = $goodsDetail;
        $orderdetail['price_info'] = ['order_price'=>$price];
        $bizcontent['order_detail'] = $orderdetail;//订单信息
        $aop = new \AopClient();
        $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
        $aop->appId = $appinfo['appid'];
        $aop->rsaPrivateKey = $appinfo['appsecret'];
        $aop->alipayrsaPublicKey = $appinfo['publickey'];
        $aop->apiVersion = '1.0';
        $aop->signType = 'RSA2';
        $aop->postCharset = 'utf-8';
        $aop->format = 'json';
        $request = new \AlipayOpenMiniOrderCreateRequest();
        writeLog('--------plugin order biz----------');
        writeLog(jsonEncode($bizcontent));
        writeLog('--------plugin order biz----------');
        $request->setBizContent(jsonEncode($bizcontent));
        $responseResult = $aop->execute($request);
        $responseApiName = str_replace(".","_",$request->getApiMethodName())."_response";
        $response = $responseResult->$responseApiName;
        writeLog('--------------plugin order result-----------');
        writeLog(json_encode([
            'code'=>$response->code,
            'sub_msg'=>$response->sub_msg??$response->msg,
            'order_id'=>$response->order_id,
            'out_order_id'=>$response->out_order_id,
        ]));
        writeLog('--------------plugin order result-----------');
        if(!empty($response->code)&&$response->code==10000){
            return ['status'=>1,'msg'=>'ok','order_id'=>$response->order_id,'out_order_id'=>$response->out_order_id];
        }else{
            $sub_msg = $response->sub_msg?$response->sub_msg:'';
            return ['status'=>0,'msg'=>'组件订单创建失败','sub_msg'=>$sub_msg];
        }
    }

    //交易组件订单发货同步
    public static function pluginOrderSend($orderid,$tablename='shop'){
        $order = Db::name('shop_order')->where('id',$orderid)->find();
        $aid = $order['aid'];
        $mid = $order['mid'];
        $ordernum = $order['ordernum'];
        if(strpos($ordernum, '_')!==false){
            $ordernum = explode('-',$ordernum)[0];
        }
        $appinfo = \app\admin\common\System::appinfo($aid,'alipay');
        $member = Db::name('member')->where('id',$mid)->find();
        require_once(ROOT_PATH.'/extend/aop/AopClient.php');
        require_once(ROOT_PATH.'/extend/aop/AopCertification.php');
        require_once(ROOT_PATH.'/extend/aop/request/AlipayOpenMiniOrderDeliverySendRequest.php');
        $aop = new \AopClient();
        $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
        $aop->appId = $appinfo['appid'];
        $aop->rsaPrivateKey = $appinfo['appsecret'];
        $aop->alipayrsaPublicKey = $appinfo['publickey'];
        $aop->apiVersion = '1.0';
        $aop->signType = 'RSA2';
        $aop->postCharset = 'utf-8';
        $aop->format = 'json';

        $bizcontent = [];
        //兼容 openid 新规则
        if($member['alipayopenid']){
            $bizcontent['user_id'] = $member['alipayopenid'];
        }else{
//            $bizcontent['buyer_open_id'] = $member['alipayopenid_new'];
        }
        $goodsDetail = [];
        if($tablename == 'shop' || $tablename=='shop_hb'){
            $oglist = Db::name('shop_order_goods')->where('aid',$aid)->where('id',$orderid)->select()->toArray();
            if($oglist){
                foreach($oglist as $og){
                    $goodsDetail[] = [
                        'out_item_id'=>$og['proid'].'_'.$og['ggid'],
                        'out_sku_id'=>$og['ggid'],
                        'item_cnt'=>$og['num'],
                        'goods_id'=>$og['proid'],
                    ];
                }
            }
        }
        $delivery = [];
        //'express_com'=>$express_com,'express_no'=>$express_no,'express_ogids'=>$express_ogids,'express_content'=>$express_content,'express_isbufen'=>$express_isbufen

        $delivery[] = [
            'delivery_id'=>getExpressTag($order['express_com']),//快递公司ID
            'waybill_id'=>$order['express_no'],//快递单号
            'item_info_list'=>$goodsDetail
        ];

        $bizcontent['out_order_id'] = ''.$ordernum;
        $bizcontent['finish_all_delivery'] = $order['express_isbufen']?0:1;//0: 未发完, 1:已发完
        $bizcontent['ship_done_time'] = date('Y-m-d H:i:s',time());//发货时间
        $bizcontent['delivery_list'] = $delivery;
        writeLog('--------plugin send--------');
        writeLog(json_encode($bizcontent,JSON_UNESCAPED_UNICODE));
        writeLog('--------plugin send--------');
        $request = new \AlipayOpenMiniOrderDeliverySendRequest();
        $request->setBizContent(jsonEncode($bizcontent));
        $result = $aop->execute( $request);
        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $response = $result->$responseNode;
        $resultCode = $response->code;
        writeLog('--------------plugin order send result-----------');
        writeLog(json_encode([
            'code'=>$response->code,
            'sub_msg'=>$response->sub_msg??$response->msg
        ]));
        writeLog('--------------plugin order send result-----------');
        if(!empty($resultCode) && $resultCode == 10000){
            return ['status'=>1,'data'=>$response];
        } else {
            return ['status'=>0,'msg'=>$response->sub_msg?$response->sub_msg:''];
        }
    }

    //交易组件订单确认收货
    public static function pluginOrderConfirm($aid,$mid,$ordernum){
        $appinfo = \app\admin\common\System::appinfo($aid,'alipay');
        $member = Db::name('member')->where('id',$mid)->find();
        require_once(ROOT_PATH.'/extend/aop/AopClient.php');
        require_once(ROOT_PATH.'/extend/aop/AopCertification.php');
        require_once(ROOT_PATH.'/extend/aop/request/AlipayOpenMiniOrderDeliveryReceiveRequest.php');
        $aop = new \AopClient();
        $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
        $aop->appId = $appinfo['appid'];
        $aop->rsaPrivateKey = $appinfo['appsecret'];
        $aop->alipayrsaPublicKey = $appinfo['publickey'];
        $aop->apiVersion = '1.0';
        $aop->signType = 'RSA2';
        $aop->postCharset = 'utf-8';
        $aop->format = 'json';

        $bizcontent = [];
        //兼容 openid 新规则
        if($member['alipayopenid']){
            $bizcontent['user_id'] = $member['alipayopenid'];
        }else{
            //如需启用，需使用$appinfo['openid_set'] 进行判断
//            $bizcontent['buyer_open_id'] = $member['alipayopenid_new'];
        }
        $bizcontent['out_order_id'] = ''.$ordernum;
        writeLog('--------plugin receive--------');
        writeLog(json_encode($bizcontent,JSON_UNESCAPED_UNICODE));
        writeLog('--------plugin receive--------');
        $request = new \AlipayOpenMiniOrderDeliveryReceiveRequest();
        $request->setBizContent(jsonEncode($bizcontent));
        $result = $aop->execute( $request);
        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $response = $result->$responseNode;
        $resultCode = $response->code;
        writeLog('--------------plugin order send result-----------');
        writeLog(json_encode([
            'code'=>$response->code,
            'sub_msg'=>$response->sub_msg??$response->msg,
        ]));
        writeLog('--------------plugin order send result-----------');
        if(!empty($resultCode) && $resultCode == 10000){
            return ['status'=>1,'data'=>$response];
        } else {
            return ['status'=>0,'msg'=>$response->sub_msg?$response->sub_msg:''];
        }
    }

    //交易组件订单状态改变
    public static function pluginOrderStatusChange($aid,$mid){

    }
    //交易组件订单查询
    public static function pluginOrderQuery($aid,$mid,$ordernum){
        $appinfo = \app\admin\common\System::appinfo($aid,'alipay');
        $member = Db::name('member')->where('id',$mid)->find();
        require_once(ROOT_PATH.'/extend/aop/AopClient.php');
        require_once(ROOT_PATH.'/extend/aop/AopCertification.php');
        require_once(ROOT_PATH.'/extend/aop/request/AlipayOpenMiniOrderQueryRequest.php');
        $aop = new \AopClient();
        $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
        $aop->appId = $appinfo['appid'];
        $aop->rsaPrivateKey = $appinfo['appsecret'];
        $aop->alipayrsaPublicKey = $appinfo['publickey'];
        $aop->apiVersion = '1.0';
        $aop->signType = 'RSA2';
        $aop->postCharset = 'utf-8';
        $aop->format = 'json';

        $bizcontent = [];
        //兼容 openid 新规则
        if($member['alipayopenid']){
            $bizcontent['user_id'] = $member['alipayopenid'];
        }else{
//            $bizcontent['buyer_open_id'] = $member['alipayopenid_new'];
        }
        $bizcontent['out_order_id'] = ''.$ordernum;
        writeLog('--------plugin order query--------');
        writeLog(json_encode($bizcontent,JSON_UNESCAPED_UNICODE));
        writeLog('--------plugin order query--------');
        $request = new \AlipayOpenMiniOrderQueryRequest();
        $request->setBizContent(jsonEncode($bizcontent));
        $result = $aop->execute( $request);
        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $resultCode = $result->$responseNode->code;
        if(!empty($resultCode) && $resultCode == 10000){
            return ['status'=>1,'data'=>$result->$responseNode];
        } else {
            return ['status'=>0,'msg'=>$result->$responseNode->sub_msg];
        }
    }
    /*+++++++++++++++++++++++++++++支付宝交易组件接口 End+++++++++++++++++++++++++++++++++++++++*/

    /*+++++++++++++++++++++++++++++支付宝消息通知 Start+++++++++++++++++++++++++++++++++++++++*/
    public static function sendTemplateMessage($aid,$mid,$templatecontent =[]){
        }
    /*+++++++++++++++++++++++++++++支付宝消息通知 End+++++++++++++++++++++++++++++++++++++++*/

    /*+++++++++++++++++++++++++++++支付宝服务商代商家管理和分账 Start+++++++++++++++++++++++++++++++++++++++*/
    /**
     * @Title 第三方应用链授权链接生成  isv代指第三方应用
     * @Params  $appid:需要授权的appid 
     * @Params  isvAppId:第三方应用appid 
     * @Params  appTypes限制类型：例如：["TINYAPP","WEBAPP"]MOBILEAPP（移动应用）    WEBAPP（网页应用） PUBLICAPP（生活号）TINYAPP（小程序）BASEAPP（基础应用）  
     * @Params redirectUri:回调链接    必须与第三方应用配置的 授权回调地址 一致
     * https://opendocs.alipay.com/isv/04h3ue?pathHash=0fec5099
     */
    public static function isvAuthorizationUrl($aid=0,$bid=0,$appid=''){
        }
    
   //app_auth_code 换取app_auth_token
    //$grant_type  => authorization_code  取应用授权令牌/refresh_token 使用app_refresh_token刷新获取新授权令牌
    public static function appauthcodeToAppauthtoken($aid,$bid,$appid,$param,$refresh_token='',$grant_type='authorization_code'){
        }
    /*
     * 支付宝分账功能
     * */
    public static function orderSettle($aid,$bid,$userId,$trans_in,$amount){
        }
    public static function DoFenzhang(){
        }

    /**
     * 支付宝小程序码
     * @param $aid
     * @param $page 跳转小程序的页面路径
     * @param $describe 对应的二维码描述
     * @return array|int[]|void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author: liud
     * @time: 2024/12/10 上午10:52
     */
    public static function getQRCode($aid,$page='',$describe=''){
        $page = explode("?",$page);
        $appinfo = \app\admin\common\System::appinfo($aid,'alipay');
        $admin_set = Db::name('admin_set')->where('aid',$aid)->find();
        require_once(ROOT_PATH.'/extend/aop/AopClient.php');
        require_once(ROOT_PATH.'/extend/aop/AopCertClient.php');
        require_once(ROOT_PATH.'/extend/aop/AopCertification.php');
        require_once(ROOT_PATH.'/extend/aop/AlipayConfig.php');
        require_once(ROOT_PATH.'/extend/aop/request/AlipayOpenAppQrcodeCreateRequest.php');
        $aop = new \AopClient();
        $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
        $aop->appId = $appinfo['appid'];
        $aop->rsaPrivateKey = $appinfo['appsecret'];
        $aop->alipayrsaPublicKey = $appinfo['publickey'];
        //$aop->apiVersion = '1.0';
        $aop->signType = 'RSA2';
        $aop->postCharset = 'utf-8';
        $aop->format = 'json';
        $request = new \AlipayOpenAppQrcodeCreateRequest ();
        $model = [];
        // 设置跳转小程序的页面路径
        $model['url_param'] = !empty($page[0]) ? $page[0] : 'pages/index/index';
        // 设置小程序的启动参数
        $model['query_param'] = !empty($page[1]) ? $page[1] : 'aid='.$aid;
        // 设置码描述
        $model['describe'] = !empty($describe) ? $describe : $admin_set['name'];
        $request->setBizContent(json_encode($model,JSON_UNESCAPED_UNICODE));
        // 执行操作  请求对应的接口
        $responseResult = $aop->execute($request);
        $responseApiName = str_replace(".","_",$request->getApiMethodName())."_response";
        $response = $responseResult->$responseApiName;
        //var_dump($response);
        if(!empty($response->code)&&$response->code==10000){
            return ['status'=>1,'msg'=>'成功','url'=>$response->qr_code_url_circle_blue];
        }else{
            return ['status'=>0,'msg'=>$response->sub_msg?$response->sub_msg:''];
        }
    }
}
