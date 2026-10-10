<?php
/**
 * 鲸犀商城 - 微信公众号小程序商城系统!
 * Copyright © 2020 鲸犀网络科技有限公司 保留所有权利
 * =========================================================
 * 版本：供应链版本
 * 授权主体：无限制版本
 * 授权域名：@@@
     
 * ----------------------------------------------
 * 您只能在商业授权范围内使用，不可二次转售、分发、分享、传播
 * 任何企业和个人不得对代码以任何目的任何形式的再发布
 * =========================================================
 */

namespace app\admin\common;
use think\facade\Db;

use AlibabaCloud\Client\AlibabaCloud;
use AlibabaCloud\Client\Exception\ClientException;
use AlibabaCloud\Client\Exception\ServerException;

// 导入 SMS 的 client
use TencentCloud\Sms\V20190711\SmsClient;
// 导入要请求接口对应的 Request 类
use TencentCloud\Sms\V20190711\Models\SendSmsRequest;
use TencentCloud\Common\Exception\TencentCloudSDKException;
use TencentCloud\Common\Credential;
// 导入可选配置类
use TencentCloud\Common\Profile\ClientProfile;
use TencentCloud\Common\Profile\HttpProfile;
use think\facade\Queue;

class Sms
{
	public const QUOTA_KEY = 'sms';

	public static function templateKeys(): array
	{
		return [
			'tmpl_smscode',
			'tmpl_orderpay',
			'tmpl_orderfahuo',
			'tmpl_collagesuccess',
			'tmpl_tuisuccess',
			'tmpl_tuierror',
			'tmpl_tixiansuccess',
			'tmpl_tixianerror',
			'tmpl_fenxiaosuccess',
			'tmpl_checksuccess',
			'tmpl_checkerror',
			'tmpl_restaurant_booking',
			'tmpl_restaurant_booking_fail',
			'tmpl_recharge',
			'tmpl_checknotice',
			'tmpl_sysmsg_notice',
			'tmpl_neworder',
			'tmpl_yy_user',
			'tmpl_yy_worker',
			'tmpl_yysucess',
		];
	}

	public static function platformSet(): array
	{
		static $cached = null;
		if ($cached !== null) {
			return $cached;
		}
		$cached = [];
		try {
			$webinfoValue = Db::name('sysset')->where('name', 'webinfo')->value('value');
			$webinfo = $webinfoValue ? json_decode($webinfoValue, true) : [];
			if (is_array($webinfo) && is_array($webinfo['sms_set'] ?? null)) {
				$cached = $webinfo['sms_set'];
			}
		} catch (\Throwable $e) {
		}
		return $cached;
	}

	public static function hasOwnCredential($smsset): bool
	{
		if (!is_array($smsset)) {
			return false;
		}
		$key = trim((string)($smsset['accesskey'] ?? ''));
		$secret = trim((string)($smsset['accesssecret'] ?? ''));
		if ($key === '' || $secret === '') {
			return false;
		}
		if (intval($smsset['smstype'] ?? 1) === 2 && trim((string)($smsset['sdkappid'] ?? '')) === '') {
			return false;
		}
		return true;
	}

	public static function resolveConfig(int $aid): array
	{
		$site = [];
		if ($aid > 0) {
			try {
				$row = Db::name('admin_set_sms')->where('aid', $aid)->find();
				if (is_array($row)) {
					$site = $row;
				}
			} catch (\Throwable $e) {
			}
		}
		if (self::hasOwnCredential($site)) {
			$site['own'] = true;
			$site['from'] = 'site';
			return $site;
		}
		$platform = self::platformSet();
		$merged = $site;
		foreach (['smstype', 'accesskey', 'accesssecret', 'sdkappid', 'sign_name'] as $field) {
			if (isset($platform[$field]) && trim((string)$platform[$field]) !== '') {
				$merged[$field] = $platform[$field];
			}
		}
		foreach (self::templateKeys() as $tmpl) {
			$merged[$tmpl] = $platform[$tmpl] ?? '';
			$merged[$tmpl . '_st'] = intval($platform[$tmpl . '_st'] ?? 0);
		}
		$merged['status'] = intval($site['status'] ?? 0);
		$merged['notice_mobile'] = $site['notice_mobile'] ?? '';
		$merged['own'] = false;
		$merged['from'] = self::hasOwnCredential($merged) ? 'platform' : 'none';
		return $merged;
	}

	public static function isEnabled(int $aid, string $templateCode = ''): bool
	{
		$set = self::resolveConfig($aid);
		if (intval($set['status'] ?? 0) !== 1) {
			return false;
		}
		if (!self::hasOwnCredential($set)) {
			return false;
		}
		$templateCode = trim($templateCode);
		if ($templateCode === '') {
			return true;
		}
		if (strpos($templateCode, 'tmpl_') === 0) {
			return intval($set[$templateCode . '_st'] ?? 0) === 1 && trim((string)($set[$templateCode] ?? '')) !== '';
		}
		return true;
	}

	//发送短信 $templateCode 模板编号 $templateParam 参数 如:['code'=>589887]
	public static function send($aid=0,$tel,$templateCode,$templateParam=[]){
		$aid = intval($aid);
		$smsset = self::resolveConfig($aid);
		if(intval($smsset['status'] ?? 0)!=1) return ['status'=>0,'msg'=>'短信功能未开启'];
		if(!self::hasOwnCredential($smsset)){
			return ['status'=>0,'msg'=>'短信参数未配置'];
		}
		$signName = $smsset['sign_name'] ?? '';
		if(!$signName) return ['status'=>0,'msg'=>'短信签名未配置'];
		$sourceTemplate = (string)$templateCode;
		if(strpos($templateCode,'tmpl_') === 0){
			if(intval($smsset[$templateCode.'_st'] ?? 0) !=1 ) return ['status'=>0,'msg'=>'该短信模板未开启'];
			$templateCode = $smsset[$templateCode] ?? '';
			if($templateCode === '' || $templateCode === null) return ['status'=>0,'msg'=>'该短信模板未配置'];
		}
		$ownCredential = !empty($smsset['own']);
		$bizNo = is_array($tel) ? implode(',', $tel) : (string)$tel;
		$started = (int)round(microtime(true) * 1000);
		$gate = SiteQuota::beginCall(self::QUOTA_KEY, $aid, [
			'skip_bill' => $ownCredential,
			'source' => 'sms_send',
			'biz_no' => $bizNo,
		]);
		if (intval($gate['status'] ?? 0) !== 1) {
			$msg = (string)($gate['msg'] ?? '短信次数不足，请联系平台充值');
			SiteQuota::finishCall(self::QUOTA_KEY, $aid, $gate, [
				'success' => false,
				'biz_no' => $bizNo,
				'source' => 'sms_send',
				'error_msg' => $msg,
				'duration' => (int)round(microtime(true) * 1000) - $started,
				'extra' => ['template' => $sourceTemplate, 'own_credential' => $ownCredential ? 1 : 0],
			]);
			return ['status'=>0,'msg'=>$msg];
		}

		$rs = ['status'=>0,'msg'=>'短信参数未配置'];
		if(intval($smsset['smstype'] ?? 0) == 1){ //阿里云短信
			$rs = self::alisms($aid,$smsset['accesskey'],$smsset['accesssecret'],$signName,$templateCode,$tel,$templateParam);
		}elseif(intval($smsset['smstype'] ?? 0) == 2){//腾讯云短信
			$rs = self::tencentsms($aid,$smsset['accesskey'],$smsset['accesssecret'],$smsset['sdkappid'],$signName,$templateCode,$tel,$templateParam);
		}

		$success = intval($rs['status'] ?? 0) === 1;
		SiteQuota::finishCall(self::QUOTA_KEY, $aid, $gate, [
			'success' => $success,
			'http_code' => $success ? 200 : 0,
			'biz_no' => $bizNo,
			'source' => 'sms_send',
			'duration' => (int)round(microtime(true) * 1000) - $started,
			'error_msg' => $success ? '' : (string)($rs['msg'] ?? '短信发送失败'),
			'extra' => [
				'template' => $sourceTemplate,
				'from' => $smsset['from'] ?? '',
				'own_credential' => $ownCredential ? 1 : 0,
			],
		]);
		return $rs;
	}

	public static function alisms($aid,$accessKey,$accessSecret,$signName,$templateID,$tel,$templateParam){
		$paramdata = ['PhoneNumbers'=>$tel,'SignName'=>$signName,'TemplateCode'=>$templateID,'TemplateParam'=>jsonEncode($templateParam)];
		if(!class_exists(AlibabaCloud::class)){
			return self::alismsByHttp($aid,$accessKey,$accessSecret,$signName,$templateID,$tel,$templateParam);
		}
		try{
			AlibabaCloud::accessKeyClient($accessKey,$accessSecret)->regionId('cn-hangzhou')->asDefaultClient();
			$result = AlibabaCloud::rpc()->product('Dysmsapi')->scheme('https')->version('2017-05-25')->action('SendSms')->method('POST')->host('dysmsapi.aliyuncs.com')->options(['query'=>$paramdata])->request();
			$rs = $result->toArray();
			return self::handleAliSmsResult($aid,$tel,$signName,$templateID,$paramdata['TemplateParam'],$rs);
		} catch (ClientException $e) {
			return ['status'=>0,'msg'=>$e->getErrorMessage()];
		} catch (ServerException $e) {
			return ['status'=>0,'msg'=>$e->getErrorMessage()];
		} catch (\Throwable $e) {
			return self::alismsByHttp($aid,$accessKey,$accessSecret,$signName,$templateID,$tel,$templateParam);
		}
	}

	private static function alismsByHttp($aid,$accessKey,$accessSecret,$signName,$templateID,$tel,$templateParam){
		$templateParamJson = jsonEncode($templateParam);
		$params = [
			'AccessKeyId' => $accessKey,
			'Action' => 'SendSms',
			'Format' => 'JSON',
			'PhoneNumbers' => $tel,
			'RegionId' => 'cn-hangzhou',
			'SignName' => $signName,
			'SignatureMethod' => 'HMAC-SHA1',
			'SignatureNonce' => self::makeAliSignatureNonce(),
			'SignatureVersion' => '1.0',
			'TemplateCode' => $templateID,
			'TemplateParam' => $templateParamJson,
			'Timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
			'Version' => '2017-05-25',
		];
		$params['Signature'] = self::makeAliSignature($params,$accessSecret);
		$response = self::postForm('https://dysmsapi.aliyuncs.com/',$params);
		if($response === false || $response === ''){
			return ['status'=>0,'msg'=>'短信发送失败，请检查服务器网络或短信配置'];
		}
		$rs = json_decode($response,true);
		if(!is_array($rs)){
			return ['status'=>0,'msg'=>'短信服务返回异常'];
		}
		return self::handleAliSmsResult($aid,$tel,$signName,$templateID,$templateParamJson,$rs);
	}

	private static function handleAliSmsResult($aid,$tel,$signName,$templateID,$templateParamJson,$rs){
		$code = $rs['Code'] ?? ($rs['data']['Code'] ?? '');
		if($code == 'OK'){
			$data = [];
			$data['aid'] = $aid;
			$data['PhoneNumbers'] = $tel;
			$data['SignName'] = $signName;
			$data['TemplateCode'] = $templateID;
			$data['ip'] = request()->ip();
			$data['createtime'] = time();
			$data['createdate'] = date('Ymd');
			$data['biz_id'] = $rs['BizId'] ?? ($rs['data']['BizId'] ?? '');
			$data['TemplateParam'] = $templateParamJson;
			Db::name('smslog')->insert($data);
			return ['status'=>1,'data'=>$rs,'msg'=>'操作成功'];
		}else{
			$msg = $rs['Message'] ?? ($rs['data']['Message'] ?? '短信发送失败');
			return ['status'=>0,'data'=>$rs,'msg'=>$msg];
		}
	}

	private static function makeAliSignatureNonce(){
		return str_replace('.', '', uniqid('', true)) . mt_rand(100000,999999);
	}

	private static function makeAliSignature($params,$accessSecret){
		unset($params['Signature']);
		ksort($params);
		$query = [];
		foreach($params as $key => $value){
			$query[] = self::aliPercentEncode($key) . '=' . self::aliPercentEncode($value);
		}
		$stringToSign = 'POST&%2F&' . self::aliPercentEncode(implode('&',$query));
		return base64_encode(hash_hmac('sha1',$stringToSign,$accessSecret . '&',true));
	}

	private static function aliPercentEncode($value){
		return str_replace(['+','*','%7E'],['%20','%2A','~'],urlencode((string)$value));
	}

	private static function postForm($url,$params){
		if(!function_exists('curl_init')){
			return false;
		}
		$ch = curl_init();
		curl_setopt($ch,CURLOPT_URL,$url);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($ch,CURLOPT_POST,true);
		curl_setopt($ch,CURLOPT_POSTFIELDS,http_build_query($params,'','&',PHP_QUERY_RFC3986));
		curl_setopt($ch,CURLOPT_HTTPHEADER,['Content-Type: application/x-www-form-urlencoded']);
		curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);
		curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,false);
		curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,10);
		curl_setopt($ch,CURLOPT_TIMEOUT,30);
		$response = curl_exec($ch);
		if($response === false){
			curl_close($ch);
			return false;
		}
		curl_close($ch);
		return $response;
	}
	public static function tencentsms($aid,$accessKey,$accessSecret,$sdkAppid,$signName,$templateID,$tel,$templateParam){
		
		try {
			if(is_array($tel)){
				$tels = [];
				foreach($tel as $v){
					$tels[] = '+86'.$v;
				}
			}else{
				$tels = ['+86'.$tel];
			}
			$cred = new Credential($accessKey,$accessSecret);
			$httpProfile = new HttpProfile();
			$httpProfile->setReqMethod("GET");  // POST 请求（默认为 POST 请求）
			$httpProfile->setReqTimeout(30);    // 请求超时时间，单位为秒（默认60秒）
			$httpProfile->setEndpoint("sms.tencentcloudapi.com");  // 指定接入地域域名（默认就近接入）
			// 实例化一个 client 选项，可选，无特殊需求时可以跳过
			$clientProfile = new ClientProfile();
			$clientProfile->setSignMethod("TC3-HMAC-SHA256");  // 指定签名算法（默认为 HmacSHA256）
			$clientProfile->setHttpProfile($httpProfile);
			$client = new SmsClient($cred,"ap-shanghai",$clientProfile);
			// 实例化一个 sms 发送短信请求对象，每个接口都会对应一个 request 对象。
			$req = new SendSmsRequest();
			/* 填充请求参数，这里 request 对象的成员变量即对应接口的入参
			* 您可以通过官网接口文档或跳转到 request 对象的定义处查看请求参数的定义
			* 基本类型的设置:
			* 帮助链接：
			* 短信控制台：https://console.cloud.tencent.com/smsv2
			* sms helper：https://cloud.tencent.com/document/product/382/3773 */
			/* 短信应用 ID: 在 [短信控制台] 添加应用后生成的实际 SDKAppID，例如1400006666 */
			$req->SmsSdkAppid = $sdkAppid;
			/* 短信签名内容: 使用 UTF-8 编码，必须填写已审核通过的签名，可登录 [短信控制台] 查看签名信息 */
			$req->Sign = $signName;
			/* 短信码号扩展号: 默认未开通，如需开通请联系 [sms helper] */
			//$req->ExtendCode = "0";
			/* 下发手机号码，采用 e.164 标准，+[国家或地区码][手机号]
			* 例如+8613711112222， 其中前面有一个+号 ，86为国家码，13711112222为手机号，最多不要超过200个手机号*/
			$req->PhoneNumberSet = $tels;
			/* 国际/港澳台短信 senderid: 国内短信填空，默认未开通，如需开通请联系 [sms helper] */
			$req->SenderId = "";
			/* 用户的 session 内容: 可以携带用户侧 ID 等上下文信息，server 会原样返回 */
			$req->SessionContext = "";
			/* 模板 ID: 必须填写已审核通过的模板 ID。可登录 [短信控制台] 查看模板 ID */
			$req->TemplateID = $templateID;
			/* 模板参数: 若无模板参数，则设置为空*/
			if($templateParam){
				$req->TemplateParamSet = array_values($templateParam);
			}else{
				$req->TemplateParamSet = [];
			}
			// 通过 client 对象调用 SendSms 方法发起请求。注意请求方法名与请求对象是对应的
			$resp = $client->SendSms($req);
			// 输出 JSON 格式的字符串回包
			if($resp->SendStatusSet[0]->Code=='Ok'){
				$data = [];
				$data['aid'] = $aid;
				$data['PhoneNumbers'] = is_array($tel) ? implode(',',$tel) : $tel;
				$data['SignName'] = $signName;
				$data['TemplateCode'] = $templateID;
				$data['ip'] = request()->ip();
				$data['createtime'] = time();
				$data['createdate'] = date('Ymd');
				$data['biz_id'] = '';
				if($req->TemplateParamSet)
				$data['TemplateParam'] = json_encode($req->TemplateParamSet);
				Db::name('smslog')->insert($data);
				return ['status'=>1,'msg'=>'发送成功'];
			}else{
				return ['status'=>0,'msg'=>$resp->SendStatusSet[0]->Message];
			}
		}catch(TencentCloudSDKException $e) {
			return ['status'=>0,'msg'=>'发送失败'];
		}
	}

	/**
	 * 发送短信给管理员
	 * @param int $aid 商家ID
	 * @param string $templateCode 模板代码
	 * @param array $params 模板参数
	 * @return array
	 */
	public static function sendToAdmin($aid, $templateCode, $params = [])
	{
		try {
			$smsset = self::resolveConfig(intval($aid));
			if(empty($smsset) || empty($smsset['status'])) {
				return ['status' => 0, 'msg' => '短信功能未开启'];
			}

			// 检查模板开关和ID
			if(empty($smsset[$templateCode.'_st']) || empty($smsset[$templateCode])) {
				return ['status' => 0, 'msg' => '短信模板未开启或未配置'];
			}

			// 检查通知手机号
			if(empty($smsset['notice_mobile'])) {
				return ['status' => 0, 'msg' => '未配置通知手机号'];
			}

			// 发送给多个手机号
			$mobiles = array_filter(array_map('trim', explode(',', $smsset['notice_mobile'])));

			// 依据模板进行参数规范化（兼容阿里云/腾讯云）
			$params = self::normalizeTemplateParams($templateCode, $params, (int)($smsset['smstype'] ?? 0));

			$success = false;
			\app\admin\common\System::plog('管理员短信准备发送：模板=' . $templateCode . '，参数=' . json_encode($params, JSON_UNESCAPED_UNICODE) . '，接收=' . implode(',', $mobiles));

			foreach($mobiles as $mobile) {
				// 调用发送方法
				$result = self::send($aid, $mobile, $templateCode, $params);
				\app\admin\common\System::plog('管理员短信发送结果：模板=' . $templateCode . '，手机=' . $mobile . '，状态=' . ($result['status'] ?? 0) . '，信息=' . ($result['msg'] ?? ''));
				AdminNotice::logPush([
					'aid' => intval($aid),
					'channel' => 'sms',
					'scene' => (string)$templateCode,
					'target' => (string)$mobile,
					'title' => mb_substr(json_encode($params, JSON_UNESCAPED_UNICODE), 0, 191),
					'biz_no' => mb_substr((string)($params['ordernum'] ?? ''), 0, 64),
					'status' => intval($result['status'] ?? 0) === 1 ? 1 : 0,
					'errmsg' => (string)($result['msg'] ?? ''),
				]);
				if($result['status'] == 1) {
					$success = true;
				}
			}

			return $success ? ['status' => 1, 'msg' => '发送成功'] : ['status' => 0, 'msg' => '发送失败'];

		} catch(\Exception $e) {
			return ['status' => 0, 'msg' => '发送异常:'.$e->getMessage()];
		}
	}

	/**
	 * 测试管理员通知短信发送
	 * @param int $aid 商家ID
	 * @return array
	 */
	public static function testAdminNotice($aid)
	{
		// 测试数据（按照阿里云新模板：仅需要 user_nick）
		$params = [
			'user_nick' => '测试用户'
		];
		
		// 发送测试
		$result = self::sendToAdmin($aid, 'tmpl_neworder', $params);
		
		return $result;
	}

	/**
	 * 根据模板对参数进行规范化
	 * - tmpl_neworder：统一输出 ['user_nick' => 'xxx']
	 * @param string $templateCode
	 * @param array $params
	 * @return array
	 */
	private static function normalizeTemplateParams(string $templateCode, array $params, int $smsType = 0): array
	{
		switch ($templateCode) {
			case 'tmpl_neworder':
				// 阿里云(1)：仅传 user_nick；腾讯云(2)：保持原有参数顺序
				if ($smsType === 1) {
					$nick = $params['user_nick'] ?? $params['nickname'] ?? $params['consignee'] ?? $params['name'] ?? $params['user'] ?? $params['username'] ?? '';
					if($nick === '' || $nick === null){
						$nick = '用户';
					}
					return ['user_nick' => (string)$nick];
				}
				return $params;
			default:
				return $params;
		}
	}
}
