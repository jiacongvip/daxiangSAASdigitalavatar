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

// +----------------------------------------------------------------------
// | 公共接口
// +----------------------------------------------------------------------
namespace app\admin\controller;
use app\admin\common\SimulateLoginTokenService;
use think\facade\Db;
class ApiCommon extends ApiBase
{
	public $sysset;
	public $fromid = 0; // 将fromid定义为类属性

	protected function tableColumnExists(string $tableName, string $columnName): bool
	{
		try {
			$prefix = config('database.connections.mysql.prefix', '');
			$tableName = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
			$columnName = preg_replace('/[^a-zA-Z0-9_]/', '', $columnName);
			if ($tableName === '' || $columnName === '') {
				return false;
			}
			$rows = Db::query("SHOW COLUMNS FROM `{$prefix}{$tableName}` LIKE '{$columnName}'");
			return !empty($rows);
		} catch (\Throwable $e) {
			return false;
		}
	}

	protected function tableExists(string $tableName): bool
	{
		try {
			$prefix = config('database.connections.mysql.prefix', '');
			$tableName = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
			if ($tableName === '') {
				return false;
			}
			$rows = Db::query("SHOW TABLES LIKE '{$prefix}{$tableName}'");
			return !empty($rows);
		} catch (\Throwable $e) {
			return false;
		}
	}

	protected function canAccessMemberOrder(int $orderMid): bool
	{
		$orderMid = intval($orderMid);
		if($orderMid <= 0 || !defined('mid') || intval(mid) <= 0){
			return false;
		}
		if($orderMid === intval(mid)){
			return true;
		}
		return $this->canViewSubordinateOrder($orderMid);
	}

	protected function canAccessEnterpriseActivityOrder(int $orderId): bool
	{
		$orderId = intval($orderId);
		if($orderId <= 0 || !defined('aid') || intval(aid) <= 0){
			return false;
		}
		if(!$this->tableExists('lipin2_activity_order') || !$this->tableExists('lipin2')){
			return false;
		}
		try {
			$currentMid = defined('mid') ? intval(mid) : 0;
			$rows = Db::name('lipin2_activity_order')->alias('ao')
				->join('lipin2 card', 'card.id=ao.hid AND card.aid=ao.aid')
				->where('ao.aid', aid)
				->where('ao.orderid', $orderId)
				->where('card.scene', 1)
				->field('ao.hid,ao.enterprise_employee_id,ao.mid,ao.code_id')
				->select()
				->toArray();
			foreach($rows as $row){
				if($currentMid > 0 && intval($row['mid'] ?? 0) === $currentMid){
					return true;
				}
				$employeeId = intval($row['enterprise_employee_id'] ?? 0);
				$hid = intval($row['hid'] ?? 0);
				if($this->isVerifiedEnterpriseActivityEmployee($hid, $employeeId)){
					return true;
				}
				if($hid > 0 && !empty($this->sessionid)){
					$verified = cache($this->sessionid . '_lipin2enterprise_activity_' . $hid);
					if(is_array($verified) && intval($verified['employee_id'] ?? 0) > 0){
						$verifiedEmployeeId = intval($verified['employee_id']);
						$verifiedMid = intval($verified['mid'] ?? 0);
						if($employeeId === 0 || $employeeId === $verifiedEmployeeId || ($verifiedMid > 0 && intval($row['mid'] ?? 0) === $verifiedMid)){
							return true;
						}
					}
				}
				if($currentMid > 0 && $employeeId > 0 && $this->tableExists('lipin2_enterprise_employee')){
					$employeeMid = intval(Db::name('lipin2_enterprise_employee')->where('aid', aid)->where('id', $employeeId)->value('mid'));
					if($employeeMid === $currentMid){
						return true;
					}
					$memberTel = trim(strval($this->member['tel'] ?? ''));
					$employeeTel = trim(strval(Db::name('lipin2_enterprise_employee')->where('aid', aid)->where('id', $employeeId)->value('tel')));
					if($memberTel !== '' && $employeeTel !== '' && $memberTel === $employeeTel){
						return true;
					}
				}
			}
			if($currentMid > 0 && $this->tableExists('lipin2_codelist')){
				$codeMid = intval(Db::name('lipin2_codelist')->where('aid', aid)->where('orderid', $orderId)->value('mid'));
				if($codeMid === $currentMid){
					return true;
				}
			}
		} catch (\Throwable $e) {
			return false;
		}
		return false;
	}

	protected function isVerifiedEnterpriseActivityEmployee(int $activityId, int $employeeId): bool
	{
		if($activityId <= 0 || $employeeId <= 0 || empty($this->sessionid)){
			return false;
		}
		$verified = cache($this->sessionid . '_lipin2enterprise_activity_' . $activityId);
		if(!is_array($verified) || intval($verified['employee_id'] ?? 0) !== $employeeId){
			return false;
		}
		return true;
	}

	protected function normalizeEnterpriseActivityKey($value): string
	{
		$key = trim(strval($value));
		for($i = 0; $i < 3 && strpos($key, '%') !== false; $i++){
			$decoded = rawurldecode($key);
			if($decoded === $key) break;
			$key = $decoded;
		}
		return in_array(strtolower($key), ['', 'undefined', 'null'], true) ? '' : $key;
	}

	protected function canViewSubordinateOrder(int $targetMid): bool
	{
		$targetMid = intval($targetMid);
		if($targetMid <= 0 || !defined('mid') || intval(mid) <= 0 || $targetMid === intval(mid)){
			return false;
		}
		if(!$this->tableColumnExists('member_level', 'can_view_subordinate_orders')){
			return false;
		}
		$member = $this->member ?: Db::name('member')->where('aid', aid)->where('id', mid)->find();
		if(!$member || empty($member['levelid'])){
			return false;
		}
		$level = Db::name('member_level')->where('aid', aid)->where('id', $member['levelid'])->find();
		if(!$level || intval($level['can_view_subordinate_orders']) !== 1){
			return false;
		}
		return $this->isSubordinateMember(intval(mid), $targetMid);
	}

	protected function isSubordinateMember(int $parentMid, int $childMid, int $depth = 0): bool
	{
		if($parentMid <= 0 || $childMid <= 0 || $parentMid === $childMid || $depth > 10){
			return false;
		}
		$child = Db::name('member')->where('aid', aid)->where('id', $childMid)->field('id,pid')->find();
		if(!$child || empty($child['pid']) || intval($child['pid']) <= 0){
			return false;
		}
		if(intval($child['pid']) === $parentMid){
			return true;
		}
		return $this->isSubordinateMember($parentMid, intval($child['pid']), $depth + 1);
	}

	protected function getPeisongSetConfig(): array
	{
		$defaults = [
			'status' => 0,
			'businessst' => 0,
			'make_status' => 0,
			'express_wx_status' => 0,
			'express_wx_paidan' => 0,
			'paidantype' => 0,
			'express_paidan' => 0,
			'businessfee' => 0,
		];

		if (!$this->tableExists('peisong_set')) {
			return $defaults;
		}

		$set = Db::name('peisong_set')->where('aid', aid)->find();
		if (!is_array($set)) {
			$set = [];
		}

		$set = array_merge($defaults, $set);
		if (intval($set['status']) === 1 && defined('bid') && bid > 0 && intval($set['businessst']) === 0 && intval($set['make_status']) === 0) {
			$set['status'] = 0;
		}

		return $set;
	}

	protected function getPeisongUsersSafe(): array
	{
		if (!$this->tableExists('peisong_user')) {
			return [];
		}

		return Db::name('peisong_user')->where('aid', aid)->where('status', 1)->order('sort desc,id')->select()->toArray();
	}

	protected function hasRenzhengService(string $serviceName): bool
	{
		$serviceName = preg_replace('/[^A-Za-z0-9_]/', '', $serviceName);
		if ($serviceName === '') {
			return false;
		}
		return class_exists("\\app\\plugin\\renzheng\\service\\{$serviceName}");
	}

	protected function getRenzhengSettingsSafe(): array
	{
		if (!$this->hasRenzhengService('AuthCenterService')) {
			return [];
		}
		try {
			$settings = \app\plugin\renzheng\service\AuthCenterService::getSettings(aid);
			return is_array($settings) ? $settings : [];
		} catch (\Throwable $e) {
			return [];
		}
	}

	protected function getRenzhengMaxBindUsersSafe(int $fallback = 1): int
	{
		$fallback = max(1, intval($fallback));
		if (!$this->hasRenzhengService('AuthCenterService')) {
			return $fallback;
		}
		try {
			return max(1, intval(\app\plugin\renzheng\service\AuthCenterService::getMaxBindUsers(aid, $fallback)));
		} catch (\Throwable $e) {
			return $fallback;
		}
	}

	protected function getRenzhengSceneAccessSafe(string $sceneKey, int $mid): array
	{
		if ($sceneKey === '' || !$this->hasRenzhengService('AuthSceneService')) {
			return [];
		}
		try {
			$data = \app\plugin\renzheng\service\AuthSceneService::checkSceneAccess(aid, $sceneKey, $mid);
			return is_array($data) ? $data : [];
		} catch (\Throwable $e) {
			return [];
		}
	}

	protected function getRenzhengItemListSafe(int $mid): array
	{
		if (!$this->hasRenzhengService('AuthStatusService')) {
			return [];
		}
		try {
			$items = \app\plugin\renzheng\service\AuthStatusService::getItemList(aid, $mid);
			return is_array($items) ? $items : [];
		} catch (\Throwable $e) {
			return [];
		}
	}

	protected function getRenzhengGlobalGateChecksSafe(int $mid, array $settings): array
	{
		if (empty($settings) || !$this->hasRenzhengService('AuthStatusService')) {
			return [];
		}
		try {
			$checks = \app\plugin\renzheng\service\AuthStatusService::getGlobalGateChecks(aid, $mid, $settings);
			return is_array($checks) ? $checks : [];
		} catch (\Throwable $e) {
			return [];
		}
	}

	protected function buildRenzhengAuthCenterUrlSafe(string $focusKey = ''): string
	{
		if (!$this->hasRenzhengService('AuthStatusService')) {
			return '/pages/my/usercenter';
		}
		try {
			return strval(\app\plugin\renzheng\service\AuthStatusService::buildAuthCenterUrl('', [
				'focus_key' => $focusKey,
			]));
		} catch (\Throwable $e) {
			return '/pages/my/usercenter';
		}
	}

	protected function normalizeDesignerMenu2Items($menudata): array
	{
		if (is_string($menudata)) {
			$decoded = json_decode($menudata, true);
			$menudata = is_array($decoded) ? $decoded : [];
		}
		if (!is_array($menudata)) {
			return [];
		}
		if (isset($menudata['list']) && is_array($menudata['list'])) {
			return array_values($menudata['list']);
		}
		if (empty($menudata)) {
			return [];
		}
		return array_keys($menudata) === range(0, count($menudata) - 1) ? array_values($menudata) : [];
	}

    public function initialize(){
		parent::initialize();
		
		// 初始化fromid变量，避免未定义错误
		$this->fromid = 0;
		if(input('param.pid')){
			$this->fromid = input('param.pid/d');
		}
		
		$tuanzhangid = 0;
		if(input('param.tuanzhangid'))
		{
		    $tuanzhangid = input('param.tuanzhangid/d');
		}
		
		

		//后台模拟登录会员（通过simulate_token）
		// 如果是 simulateLogin 方法，跳过这里的处理，让方法自己处理
		$request_action = strtolower(request()->controller() . '/' . request()->action());
		$simulate_token = input('param.simulate_token');
		$simulate_login_success = false;
		if($simulate_token && $request_action != 'apiindex/simulatelogin'){
			\think\facade\Log::write('模拟登录尝试 - Token:' . $simulate_token, 'info');
			$cacheData = SimulateLoginTokenService::findValid($simulate_token, intval(aid));
			\think\facade\Log::write('模拟登录缓存数据 - Token:' . $simulate_token . ' Data:' . json_encode($cacheData), 'info');
			if($cacheData && $cacheData['aid'] == aid){
				$mid = $cacheData['mid'];
				$member = Db::name('member')->where('aid',aid)->where('id',$mid)->find();
				if($member){
					$this->sessionid = \think\facade\Session::getId() . '_simulate_' . $mid;
					cache($this->sessionid.'_mid', $mid, 7*86400);
					Db::name('session')->where('aid',aid)->where('session_id',$this->sessionid)->delete();
					$session = [
						'session_id' => $this->sessionid,
						'aid' => aid,
						'mid' => $mid,
						'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
						'login_time' => time(),
						'login_ip' => request()->ip(),
						'platform' => platform
					];
					Db::name('session')->insert($session);
					// 使用后删除token（一次性使用）
					SimulateLoginTokenService::markUsed($simulate_token, intval(aid));
					\think\facade\Log::write('模拟登录成功 - 会员ID:' . $mid . ' Token:' . $simulate_token, 'info');
					$simulate_login_success = true;
				}
			}
		}
		
		//后台查看h5管理端（如果模拟登录成功则跳过）
		$uid = input('param.uid/d');
		if(!$simulate_login_success && $uid/* && session("?ADMIN_LOGIN") && (session('ADMIN_UID') == $uid || session('ADMIN_AUTH_UID') == $uid)*/){
			$user = Db::name('admin_user')->where('id',$uid)->find();
			if(!$user) die(jsonEncode(['status'=>0,'msg'=>'管理员不存在']));
			$member = Db::name('member')->where('aid',aid)->where('id',$user['mid'])->find();
			if(!$member) die(jsonEncode(['status'=>0,'msg'=>'管理员绑定的会员不存在']));
			$mid = $member['id'];
			$this->sessionid = \think\facade\Session::getId().$uid;
			cache($this->sessionid.'_mid',$mid,7*86400);
			Db::name('session')->where('aid',aid)->where('session_id',$this->sessionid)->delete();
			$session = [
				'session_id' => $this->sessionid,
				'aid' => aid,
				'mid' => $mid,
				'user_agent' => $_SERVER['HTTP_USER_AGENT'],
				'login_time' => time(),
				'login_ip' => request()->ip(),
				'platform' => platform
			];
			Db::name('session')->insert($session);
		}else if(!$simulate_login_success){
			// 登录态 session_id 只从 GET 读取，避免与业务参数（POST 的 session_id）冲突导致登录循环
			$sessionIdParam = input('get.session_id');
			// 正常 session_id 一般为非纯数字字符串；若被业务参数污染成纯数字则忽略，避免登录态错乱
			if(!empty($sessionIdParam) && preg_match('/^\d+$/', $sessionIdParam)){
				$sessionIdParam = '';
			}
			// 兼容历史：若 GET 未带 session_id，但 param.session_id 看起来像真实 token（非纯数字），则兜底使用
			if((empty($sessionIdParam) || $sessionIdParam == 'undefined' || $sessionIdParam == 'null') && input('param.session_id')){
				$fallbackSessionId = input('param.session_id');
				if(!empty($fallbackSessionId) && $fallbackSessionId != 'undefined' && $fallbackSessionId != 'null' && !preg_match('/^\d+$/', $fallbackSessionId)){
					$sessionIdParam = $fallbackSessionId;
				}
			}

			if($sessionIdParam && $sessionIdParam != 'undefined' && $sessionIdParam != 'null' && !empty($sessionIdParam)){
				$session = Db::name('session')->where('aid',aid)->where('session_id',$sessionIdParam)->find();
				if(empty($session)) {
					$this->sessionid = $sessionIdParam;//\think\facade\Session::getId();
					$mid = cache($this->sessionid.'_mid');
					$session = [
						'session_id' => $this->sessionid,
						'aid' => aid,
						'mid' => $mid,
						'user_agent' => $_SERVER['HTTP_USER_AGENT'],
						'login_time' => time(),
						'login_ip' => request()->ip(),
						'platform' => platform
					];
					Db::name('session')->insert($session);
				} else {
					$this->sessionid = $sessionIdParam;
					// 兼容缓存失效场景：若 session 表里已有 mid，则回填缓存，避免被误判未登录
					if(!empty($session['mid'])){
						cache($this->sessionid.'_mid', intval($session['mid']), 7 * 86400);
					}
				}
			}else{
				$this->sessionid = \think\facade\Session::getId();
				$mid = cache($this->sessionid.'_mid');
				$session = [
					'session_id' => $this->sessionid,
					'aid' => aid,
					'mid' => $mid,
					'user_agent' => $_SERVER['HTTP_USER_AGENT'],
					'login_time' => time(),
					'login_ip' => request()->ip(),
					'platform' => platform
				];
				Db::name('session')->insert($session);
			}
			$mid = cache($this->sessionid.'_mid');

			$member = [];
			if($mid){
				$member = Db::name('member')->where('aid',aid)->where('id',$mid)->find();
				//$session = Db::name('session')->where('aid',aid)->where('mid',$mid)->order('login_time desc')->find();
				//if($member && $session['session_id']) $this->sessionid = $session['session_id'];
			}elseif($this->sessionid){
				// 缓存丢失时，允许从 session 表恢复会员态，避免登录态只依赖 cache(session_id_mid)。
				$session_mid = 0;
				if(!empty($session) && !empty($session['mid'])){
					$session_mid = intval($session['mid']);
				}else{
					$session_mid = intval(Db::name('session')->where('aid',aid)->where('session_id',$this->sessionid)->value('mid'));
				}
				if($session_mid > 0){
					$member = Db::name('member')->where('aid',aid)->where('id',$session_mid)->find();
					if($member){
						$sessionid_time = 7 * 86400;
						$nologin_day = Db::name('admin_set')->where('aid', aid)->value('nologin_day');
						if($nologin_day > 0){
							$sessionid_time = $nologin_day * 86400;
						}
						cache($this->sessionid.'_mid', $session_mid, $sessionid_time);
					}
				}
			}
		}
		if(empty($simulate_login_success) && !empty($this->sessionid)){
			if (!isset($member) || !is_array($member)) $member = [];
			$preferredMember = $this->preferEnterpriseVerifiedMember($member, intval($member['id'] ?? 0));
			if ($preferredMember) $member = $preferredMember;
		}
		if(!$member){
			define('mid',0);
		}else{
			// 定时任务可能延迟执行，会员请求入口也要及时清理已过期等级。
				if(intval($member['levelendtime'] ?? 0) > 0 && intval($member['levelendtime']) < time()){
					$activeMember = \app\admin\common\Member::expireLevelIfNeeded(intval(aid), intval($member['id']));
					if($activeMember){
						$member = $activeMember;
					}
				}
				\app\admin\common\Member::expireMemberLevelRecords(intval(aid), intval($member['id']));
			define('mid',$member['id']);
			if($this->fromid && $this->fromid!=mid && $this->fromid!=$member['pid']){
				$upuser = Db::name('member')->where('id',$this->fromid)->find();
				$uplv = Db::name('member_level')->where('aid',aid)->where('id',$upuser['levelid'])->find();
				if($upuser && $uplv['can_agent']!=0 && $uplv['agent_rule']>0){
					if(!$member['pid'] || $uplv['agent_rule']==2){
						// 【修复】更新 pid 时同步更新 path
						$update_data = ['pid' => $this->fromid];
						if($upuser['path']){
							$update_data['path'] = $upuser['path'] . ',' . $upuser['id'];
						}else{
							$update_data['path'] = (string)$upuser['id'];
						}
						Db::name('member')->where('id',mid)->update($update_data);
						// 同步更新下级用户的 path
						\app\admin\model\Member::updatepath(mid, $update_data['path']);
						\app\admin\common\Common::user_tjscore(aid,mid);
					}elseif($uplv['agent_rule']==3){ //首次消费后绑定推荐关系
						$haspayorder = Db::name('payorder')->where('mid',mid)->where('money','>',0)->where('status',1)->find();
						if(!$haspayorder){
							// 【修复】更新 pid 时同步更新 path
							$update_data = ['pid' => $this->fromid];
							if($upuser['path']){
								$update_data['path'] = $upuser['path'] . ',' . $upuser['id'];
							}else{
								$update_data['path'] = (string)$upuser['id'];
							}
							Db::name('member')->where('id',mid)->update($update_data);
							// 同步更新下级用户的 path
							\app\admin\model\Member::updatepath(mid, $update_data['path']);
							//\app\admin\common\Common::user_tjscore(aid,mid);
						}
					}
				}
			}
            $member['money'] = \app\admin\common\Member::getmoney($member);
            $member['score'] = \app\admin\common\Member::getscore($member);
		}
		$this->mid = mid;
		$this->member = $member;
		if($this->sessionid && $mid){
			// 回写缓存和 session 表，避免登录态只依赖缓存，缓存失效后被误判未登录。
			cache($this->sessionid.'_mid', mid, 7 * 86400);
			Db::name('session')->where('aid',aid)->where('session_id',$this->sessionid)->update([
				'mid' => mid,
				'login_time' => time(),
			]);
		}
		
		$this->sysset = Db::name('admin_set')->where('aid',aid)->find();
		$isRestaurantCashier = !empty($this->isRestaurantCashier);
		$publicEnterpriseIdentityAction = in_array(strtolower(request()->controller() . '/' . request()->action()), [
			'apilipin2enterprise/activityinfo',
			'apilipin2enterprise/verifyemployee',
			'apilipin2enterprise/sendemployeesmscode',
		], true);
		$gettj = explode(',',$this->sysset['gettj']);
		if(!in_array('-1',$gettj)){ //不是所有人
			$request_action = request()->controller().'/'.request()->action();
			if(!$isRestaurantCashier && !$publicEnterpriseIdentityAction && ($request_action=='ApiIndex/index' || request()->controller()!='ApiIndex')){
				$this->checklogin();
				if(!in_array($this->member['levelid'],$gettj)){
					echojson(['status'=>-4,'msg'=>$this->sysset['gettjtip']]);
				}
			}
		}
		//设置了进入系统必须先登录
		if($this->sysset['login_mast'] && in_array(platform,explode(',',$this->sysset['login_mast']))){
			$request_action = request()->controller().'/'.request()->action();
			if(!$isRestaurantCashier && !$publicEnterpriseIdentityAction && ($request_action=='ApiIndex/index' || !in_array(request()->controller(),['ApiIndex','ApiImageupload']))){
				$this->checklogin();
			}
		}
		
		
		$request_action = request()->controller().'/'.request()->action();
        $renzhengItems = $this->getRenzhengSettingsSafe();

// 		if($this->sysset['idcard_check'] == 1 && request()->controller()!='ApiIndex' && $request_action != 'ApiMy/setfield')
// 		{
// 		    $this->checklogin();
// 		    $usercard = Db::name('member')->where('id',$this->mid)->value('usercard');
//             if(!$usercard)
//             {
//               echojson(['status'=>-4,'msg'=>'请去认证','url'=>'/shopPackage/my/setidcard']);
//             }
// 		}
// 		if($this->sysset['idcard_check'] == 1 && $request_action=='ApiIndex/index')
// 		{
// 		    $this->checklogin();
// 	        $usercard = Db::name('member')->where('id',$this->mid)->value('usercard');
// 	        if(!$usercard)
//             {
//                 echojson(['status'=>-4,'msg'=>'请去认证','url'=>'/shopPackage/my/setidcard']);
//             }
// 		}
        	
        // 认证中心开启仅代表能力生效；是否做全站强制拦截由独立开关控制
        if (
            $renzhengItems
            && isset($renzhengItems['status'])
            && intval($renzhengItems['status']) === 1
            && intval($renzhengItems['global_gate_enabled'] ?? 0) === 1
        ) {
        
     // 定义需要排除的请求动作数组
            $excluded_actions = [
                'ApiCommon/init',
                'ApiMy/getRenzhengItems',
                'ApiMy/set',
                'ApiAddress/address',
                'ApiImageupload/uploadImg',
                'ApiMiaosha/setfield',
                'ApiMy/setfield',
                'ApiFace/info',
                'ApiFace/detect',
                'ApiFace/record',
                'ApiFace/query',
            ];
            $request_action_lower = strtolower($request_action);
            $excluded_actions_lower = array_map('strtolower', $excluded_actions);

            if (strtolower(request()->controller()) != 'apiindex' && !in_array($request_action_lower, $excluded_actions_lower, true)) {
                $gateChecks = $this->getRenzhengGlobalGateChecksSafe(intval($this->mid), $renzhengItems);
                if (!empty($gateChecks)) {
                    $this->checklogin();
                    foreach ($gateChecks as $check) {
                        if (intval($check['passed'] ?? 0) === 1) {
                            continue;
                        }
                        $authCenterUrl = $this->buildRenzhengAuthCenterUrlSafe(strval($check['key'] ?? ''));
                        echojson([
                            'status' => -4,
                            'msg' => strval($check['block_msg'] ?? '请先完成认证'),
                            'url' => $authCenterUrl,
                        ]);
                    }
                }
            }

            
            
        }
		if($tuanzhangid >0 && !empty($member))
		{
		    if($member['pid'] == 0)
		    {
		        $tunzhang = Db::name('tuanzhang')->where('aid',aid)->where('id',$tuanzhangid)->field('mid2')->find();
		        if($tunzhang['mid2'] >0)
		        {
		            $tuozhang_member = Db::name('member')->field('levelid')->where('aid',aid)->where('id',$tunzhang['mid2'])->find();
		            
		            if(!empty($tuozhang_member) && $tuozhang_member['levelid'] >0)
		            {
		                $tuozhang_member_level = Db::name('member_level')->field('can_agent')->where('aid',aid)->where('id',$tuozhang_member['levelid'])->find();
		                if($tuozhang_member_level['can_agent'] >0){
		                     Db::name('member')->where('id',mid)->update(['pid'=>$tunzhang['mid2']]);
		                     \app\admin\common\Common::user_tjscore(aid,mid);
		                }
		            }
		        }
		    }
		}
	}

	/**
	 * 企业礼品卡验证成功后，同一 session 后续请求优先使用员工会员，
	 * 避免微信静默登录把身份切回另一个账号。
	 */
	protected function preferEnterpriseVerifiedMember(array $member, int $mid): array
	{
		$sessionId = trim((string)($this->sessionid ?? ''));
		if ($sessionId === '') return $member;
		$enterpriseMid = intval(cache($sessionId . '_lipin2enterprise_mid'));
		if ($enterpriseMid <= 0) return $member;
		if ($mid === $enterpriseMid && !empty($member['id'])) return $member;
		try {
			$enterpriseMember = Db::name('member')->where('aid', aid)->where('id', $enterpriseMid)->find();
		} catch (\Throwable $e) {
			return $member;
		}
		if (!$enterpriseMember || intval($enterpriseMember['isfreeze'] ?? 0) === 1) return $member;
		cache($sessionId . '_mid', $enterpriseMid, 7 * 86400);
		try {
			Db::name('session')->where('aid', aid)->where('session_id', $sessionId)->update([
				'mid' => $enterpriseMid,
				'login_time' => time(),
			]);
		} catch (\Throwable $e) {
		}
		return $enterpriseMember;
	}

	//判断登录 - 增强版支持静默登录
	public function checklogin($authlogin = 0, $params = []){
		if(!$this->member){
			//是否直接用授权登录
			$logintype = $this->sysset['logintype_'.platform];
			if($logintype == 3){
				$xieyi = Db::name('admin_set_xieyi')->where('aid',aid)->find();
				if(!$xieyi || $xieyi['status'] == 0){
					$authlogin = 1;
				}
			}
			
							// 检查是否启用自动注册登录功能
		\think\facade\Log::write('ApiCommon::checklogin 尝试检查member_auto_addlogin字段，平台: '.platform);
		\think\facade\Log::write('ApiCommon::checklogin 当前请求URI: '.$_SERVER['REQUEST_URI']);
		
		if(isset($this->sysset['member_auto_addlogin']) && intval($this->sysset['member_auto_addlogin']) == 1 && in_array(platform, ['wx', 'alipay', 'mp'])){
			// 对于支持静默登录的平台，检查登录类型配置
			$logintype = $this->sysset['logintype_'.platform];
			if(!$logintype){ 
				$logintype = [];
			}else{
				$logintype = explode(',',$logintype);
			}
			
			\think\facade\Log::write('ApiCommon::checklogin 平台登录类型配置: '.json_encode($logintype));
			
			// 优化逻辑：优先检查是否支持授权登录(3)，如果支持则让前端先尝试静默授权
			$supportsAuth = in_array('3', $logintype);
			$isInitRequest = strpos($_SERVER['REQUEST_URI'], 'needinit=1') !== false;
			
			\think\facade\Log::write('ApiCommon::checklogin 支持授权登录: '.($supportsAuth ? 'true' : 'false').', 初始化请求: '.($isInitRequest ? 'true' : 'false'));
			
			// 如果是初始化请求且支持授权登录，优先让前端处理静默授权
			if($isInitRequest && $supportsAuth){
				\think\facade\Log::write('ApiCommon::checklogin 检测到初始化请求且支持授权登录，优先让前端处理静默授权');
				return $this->json(['status'=>-1,'msg'=>'请先登录','authlogin'=>$authlogin,'data'=>$params],1);
			}
			
			// 如果logintype为空（纯静默登录模式），也让前端处理
			if(empty($logintype)){
				\think\facade\Log::write('ApiCommon::checklogin 检测到纯静默登录配置，让前端处理');
				return $this->json(['status'=>-1,'msg'=>'请先登录','authlogin'=>$authlogin,'data'=>$params],1);
			}
			
			// 其他情况才执行自动注册（非初始化请求或不支持授权登录）
			\think\facade\Log::write('ApiCommon::checklogin 执行自动注册逻辑');
			$member = \app\admin\common\Member::autoReg(aid, $this->sessionid, platform);
			if($member){
				$this->member = $member;
				define('mid', $member['id']);
				$this->mid = $member['id'];
				// 更新访问时间
				Db::name('member')->where('aid',aid)->where('id',mid)->update(['last_visittime'=>time()]);
				return; // 自动登录成功，直接返回
			}
		}
			
			return $this->json(['status'=>-1,'msg'=>'请先登录','authlogin'=>$authlogin,'data'=>$params],1);
		}elseif($this->member['checkst'] == 0){
			echojson(['status'=>-4,'msg'=>'账号审核中']);
		}elseif($this->member['checkst'] == 2){
			echojson(['status'=>-4,'msg'=>'账号审核未通过,驳回原因：'.$this->member['checkreason']]);
		}elseif($this->member['isfreeze'] == 1){
			echojson(['status'=>-4,'msg'=>'账号已冻结']);
		}elseif(isset($this->member['is_blacklist']) && $this->member['is_blacklist'] == 1){
			echojson(['status'=>-4,'msg'=>'该账号已被加入黑名单，无法登录使用']);
		}
        //更新访问时间
        Db::name('member')->where('aid',aid)->where('id',mid)->update(['last_visittime'=>time()]);
	}
	protected function json($data,$isexit=0){
		if(input('param.needinit') == 1){
			$platform = platform;
			$menuset = Db::name('designer_menu')->where('aid',aid)->where('platform',$platform)->find();
			$menudata = json_decode($menuset['menudata'],true);
			$menulist = array();
			foreach($menudata['list'] as $k=>$v){
				if($k < $menuset['menucount']){
					$menulist[] = $v;
				}
			}
			$menudata['list'] = $menulist;
			$indexurl = $menuset['indexurl'];
			
			$menu2datalist = [];
			//多商户默认导航
			$menubusinessset = Db::name('designer_menu_business')->where('aid',aid)->where('platform',$platform)->find();
			if($menubusinessset){
				$menubusinessdata = json_decode($menubusinessset['menudata'],true);
				$menubusinesslist = array();
				foreach($menubusinessdata['list'] as $k=>$v){
					if($k < $menubusinessset['menucount']){
						$menu2data = [];
						$menu2data['color'] = $menubusinessdata['color'];
						$menu2data['selectedColor'] = $menubusinessdata['selectedColor'];
						$menu2data['backgroundColor'] = $menubusinessdata['backgroundColor'];
						$menu2data['indexurl'] = $v['pagePath'];
						$menu2data['list'] = $menubusinessdata['list'];
						$menulist = [];
						foreach($menu2data['list'] as $k2=>$v2){
							if($k2 < $menubusinessset['menucount']){
								$menulist[] = $v2;
							}
						}
						$menu2data['list'] = $menulist;
						$menu2datalist[] = $menu2data;
					}
				}
			}

			//内页菜单
			$menu2list = Db::name('designer_menu2')->where('aid',aid)->where('status',1)->where('platform','in',['all',$platform])->order('id desc')->select()->toArray();
			if($menu2list){
				foreach($menu2list as $k=>$v){
					$menu2data = [];
					$menu2data['backgroundColor'] = $v['backgroundColor'];
					$menu2data['indexurl'] = $v['indexurl'];
					$menu2data['list'] = $this->normalizeDesignerMenu2Items($v['menudata']);
					$menulist = [];
					foreach($menu2data['list'] as $k2=>$v2){
						if($k2 < $v['menucount']){
							$menulist[] = $v2;
						}
					}
					$menu2data['list'] = $menulist;
					$menu2datalist[] = $menu2data;
				}
			}


				$sysset = $this->sysset;
				$textset = json_decode($sysset['textset'],true);
				if(!$textset) $textset = [];
				$textset = \app\admin\common\System::mergeDefaultTextsetConfig($textset);

			//分享设置（添加allow_friend,allow_timeline字段用于控制单页面分享权限）
			$sharelist = Db::name('designer_share')->field('title,desc,pic,url,indexurl,is_rootpath,allow_friend,allow_timeline')->where('aid',aid)->where('status',1)->where('platform','in',['all',$platform])->select()->toArray();
			if(!$sharelist) $sharelist = [];

			if($platform == 'wx'){
				if($sysset['wxkf'] == 1){
					$sysset['kfurl'] = 'contact::';
				}else{
					$sysset['kfurl'] = $sysset['wxkfurl'];
				}
			}
			if(!$sysset['kfurl']) $sysset['kfurl'] = '';
			$logintype = $sysset['logintype_'.$platform];
			if(!$logintype){
				$logintype = [];
			}else{
				$logintype = explode(',',$logintype);
			}

			$shopdetail_style = 'default';
			$shopdetail_menudata_str = Db::name('designer_shopdetail')->where('aid',aid)->where('bid',0)->value('menudata');
			if($shopdetail_menudata_str){
				$shopdetail_menudata_tmp = json_decode($shopdetail_menudata_str,true);
				if($shopdetail_menudata_tmp && is_array($shopdetail_menudata_tmp) && !empty($shopdetail_menudata_tmp['detailStyle'])){
					$shopdetail_style = $shopdetail_menudata_tmp['detailStyle'];
				}
			}

			$sysset = $this->sysset; // 使用类属性中的sysset
			if(in_array($platform, ['mp', 'h5'], true)){
				\app\admin\common\H5ShareMetadata::sync(intval(aid), [
					'name' => $sysset['name'] ?? '',
					'logo' => $sysset['logo'] ?? '',
					'desc' => $sysset['desc'] ?? '',
					'base_url' => PRE_URL,
				]);
			}
			$shopset = Db::name('shop_sysset')->where('aid', aid)->find();
			if(!is_array($shopset)) $shopset = [];
			$sysset['show_prolist_member_price'] = intval($shopset['member_price_checkout_only'] ?? 0) === 1
				? 0
				: intval($shopset['show_prolist_member_price'] ?? 1);
			$sysset['member_price_checkout_only'] = intval($shopset['member_price_checkout_only'] ?? 0);
			$chat_notify_sound = '';
			try {
				if (class_exists('\\app\\plugin\\messagenotify\\common\\MessageSettings')) {
					$chat_notify_sound = \app\plugin\messagenotify\common\MessageSettings::notifySoundAbsoluteUrl(intval(aid));
				}
			} catch (\Throwable $e) {
				$chat_notify_sound = '';
			}
			if ($chat_notify_sound === '') {
				try {
					if (class_exists('\\app\\plugin\\enterprisechat\\common\\EnterpriseChatSettings')) {
						$chat_notify_sound = \app\plugin\enterprisechat\common\EnterpriseChatSettings::notifySoundAbsoluteUrl(intval(aid));
					}
				} catch (\Throwable $e2) {
					$chat_notify_sound = '';
				}
			}
			$data['_initdata'] = [
				'name'=>$sysset['name'],
				'logo'=>$sysset['logo'],
				'desc'=>\app\admin\common\System::plainText($sysset['desc'] ?? ''),
				'wxkf'=>$sysset['wxkf'],
				'corpid'=>$sysset['corpid'],
				'kfurl'=>$sysset['kfurl'],
				'mid'=>$this->mid,
				'pre_url'=>PRE_URL,
				'session_id'=>$this->sessionid,
				'platform'=>platform,
				'indexurl'=>$indexurl,
				'menudata'=>$menudata,
				'menu2data'=>$menu2datalist,
				'sharelist'=>$sharelist,
				'textset'=>$textset,
				'color1'=>$sysset['color1'],
				'color2'=>$sysset['color2'],
				'color1rgb'=>hex2rgb($sysset['color1']),
				'color2rgb'=>hex2rgb($sysset['color2']),
				'logintype'=>$logintype,
				// 新增：静默/自动登录相关开关下发到前端
				'member_auto_addlogin' => isset($sysset['member_auto_addlogin']) ? intval($sysset['member_auto_addlogin']) : 0,
				'member_auto_reg' => isset($sysset['member_auto_reg']) ? intval($sysset['member_auto_reg']) : 0,
				'nologin_day' => isset($sysset['nologin_day']) ? intval($sysset['nologin_day']) : 7,
				'login_popup_status' => isset($sysset['login_popup_status']) ? intval($sysset['login_popup_status']) : 0,
				'login_popup_platforms' => isset($sysset['login_popup_platforms']) ? (string)$sysset['login_popup_platforms'] : '',
				// 新增：加载动效配置
				'loading_effect_version' => isset($sysset['loading_effect_version']) ? $sysset['loading_effect_version'] : 'old',
				'loading_icon' => isset($sysset['loading_icon']) ? $sysset['loading_icon'] : '',
				'loading_style' => isset($sysset['loading_style']) ? $sysset['loading_style'] : '0',
				'show_prolist_member_price' => $sysset['show_prolist_member_price'],
				'member_price_checkout_only' => intval($sysset['member_price_checkout_only'] ?? 0),
				'address_name_label' => trim((string)($shopset['address_name_label'] ?? '')) ?: '姓名',
				'shopdetail_style' => $shopdetail_style,
				'isdouyin'=>isdouyin,
				'app_push_ready' => (class_exists('\\app\\admin\\common\\PluginBusinessAuth') && \app\admin\common\PluginBusinessAuth::isAidPluginReady(intval(aid), 'apppush', ['apppush_set', 'apppush_device'])) ? 1 : 0,
				'chat_notify_sound' => $chat_notify_sound,
				'h5_qq_map_key' => trim(strval($sysset['h5_qq_map_key'] ?? '')),
				'usd_label' => (class_exists('\\app\\plugin\\jishou\\Plugin') ? \app\plugin\jishou\Plugin::resolveUsdLabel(aid) : '地址'),
			];
			
			// 添加sysset到返回数据中，供前端组件使用
			$data['sysset'] = $sysset;
			
			if(platform == 'mp'){
				$share_package = \app\admin\common\Wechat::share_package(aid);
				$data['share_package'] = $share_package;
			}
		}
		if((input('param.needinit') == 1 && $this->mid) || $data['mid']){
			if($data['mid']) $this->mid = $data['mid'];
			$config = include(ROOT_PATH.'config.php');
			$authtoken = $config['authtoken'];
			$token = md5(md5($authtoken.$this->mid));
			$data['socket_token'] = Db::name('member')->where('id',$this->mid)->value('random_str');
		}
		if($isexit == 1){
			echojson($data);
		}
		return json($data);
	}

	//初始化接口
	public function init(){
		return $this->json([]);
	}

	//收藏
	public function addfavorite(){
		$this->checklogin();
		$post = input('post.');
		$rs = Db::name('member_favorite')->where('aid',aid)->where('mid',mid)->where('proid',$post['proid'])->where('type',$post['type'])->find();
		if($rs){
			Db::name('member_favorite')->where('aid',aid)->where('mid',mid)->where('proid',$post['proid'])->where('type',$post['type'])->delete();
			return json(['status'=>1,'msg'=>'已取消','url'=>true]);
		}else{
			Db::name('member_favorite')->insert(['aid'=>aid,'mid'=>mid,'proid'=>$post['proid'],'type'=>$post['type'],'createtime'=>time()]);
			return json(['status'=>1,'msg'=>'已收藏','url'=>true]);
		}
	}

	//生成海报
	function _getposter($aid,$platform,$posterdata,$page,$scene,$textReplaceArr){
		set_time_limit(0);
		$posterdata = json_decode($posterdata,true);
		$poster_bg = $posterdata['poster_bg'];
		$poster_data = $posterdata['poster_data'];
		@ini_set('memory_limit', -1);

		if(strpos($poster_bg,'http') ===false){
			$poster_bg = PRE_URL.$poster_bg;
		}
		$bg = imagecreatefromstring(request_get($poster_bg));
		if($bg){
			$bgwidth = imagesx($bg);
			$bgheight = imagesy($bg);
			if($bgheight/$bgwidth > 1.92) $bgheight = floor($bgwidth * 1.92);
			$target = imagecreatetruecolor($bgwidth, $bgheight);
			imagecopy($target, $bg, 0, 0, 0, 0,$bgwidth,$bgheight);
			imagedestroy($bg);
		}else{
			$bgwidth = 680;
			$bgheight = 1080;
			$target = imagecreatetruecolor(680, 1080);
			imagefill($target,0,0,imagecolorallocate($target, 255, 255, 255));
		}
		$huansuan = $bgwidth/340;
		//$bgwidth = imagesx($bg);
		//$bgheight = imagesy($bg);
		$font = ROOT_PATH."static/fonts/msyh.ttf";
		foreach ($poster_data as $d){
			$d['left'] = intval(str_replace('px', '', $d['left'])) * $huansuan;
			$d['top'] = intval(str_replace('px', '', $d['top'])) * $huansuan;
			$d['width'] = intval(str_replace('px', '', $d['width'])) * $huansuan;
			$d['height'] = intval(str_replace('px', '', $d['height'])) * $huansuan;
			$d['size'] = intval(str_replace('px', '', $d['size'])) * $huansuan/2*1.5;

			if ($d['type'] == 'qrwx'){
				$access_token = \app\admin\common\Wechat::access_token($aid,'wx');
				if(!$access_token){
					echojson(array('status'=>0,'msg'=>'微信小程序access_token获取失败'));
				}
				
				$url = 'https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token='.$access_token;
				$data = array();
				$data['scene'] = $scene;
				$data['page'] = ltrim($page,'/');
				
				$res = request_post($url,jsonEncode($data));
				$errmsg =json_decode($res,true);
				
				if($errmsg){
					if($errmsg['errcode'] == 41030){
						echojson(array('status'=>0,'msg'=>'小程序页面路径不存在或未发布，请检查页面：'.$data['page']));
					}elseif($errmsg['errcode'] == 45009){
						echojson(array('status'=>0,'msg'=>'接口调用超过限制，请稍后重试'));
					}elseif($errmsg['errcode'] == 40001){
						echojson(array('status'=>0,'msg'=>'access_token无效或过期'));
					}else{
						echojson(array('status'=>0,'msg'=>'生成小程序码失败：'.$errmsg['errmsg'].'(错误码：'.$errmsg['errcode'].')'));
					}
				}
				//$filepath = '/upload/poster/'.$aid.'/'.date('Ym/d').'/'.date('His').rand(1000,9999).'.jpg';
				//mk_dir(dirname(ROOT_PATH.ltrim($filepath,'/')));
				//file_put_contents(ROOT_PATH.ltrim($filepath,'/'),$res);
				//$qrcode = PRE_URL.$filepath;
				//$img = imagecreatefromstring(request_get($qrcode));
				if(!$errmsg){
					$img = imagecreatefromstring($res);
					imagecopyresampled($target, $img, $d['left'], $d['top'], 0, 0, $d['width'], $d['height'],imagesx($img), imagesy($img));
				}else{
					//echojson(array('status'=>0,'msg'=>$errmsg['errmsg']));
				}
			} else if ($d['type'] == 'qrmp') {
				$qrcode = createqrcode(PRE_URL .'/h5/'.$aid.'.html#'.$page.'?scene='.$scene.'&t='.time());
				$img = imagecreatefromstring(request_get($qrcode));
				imagecopyresampled($target, $img, $d['left'], $d['top'], 0, 0, $d['width'], $d['height'],imagesx($img), imagesy($img));
			}else if($d['type'] == 'qrgz'){
				$url = 'https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token='.\app\admin\common\Wechat::access_token($aid,'mp');
				$data = array();
				$data['action_name'] = 'QR_LIMIT_STR_SCENE';
				$data['action_info'] = ['scene'=>['scene_str'=>$scene]];
				$rs = request_post($url,jsonEncode($data));
				$rs = json_decode($rs,true);
				if($rs['url']){
					$qrcode = createqrcode($rs['url']);
					$img = imagecreatefromstring(request_get($qrcode));
					imagecopyresampled($target, $img, $d['left'], $d['top'], 0, 0, $d['width'], $d['height'],imagesx($img), imagesy($img));
				}else{
					//echojson(array('status'=>0,'msg'=>\app\admin\common\Wechat::geterror($rs)));
				}
			}else if($d['type'] == 'qrpay_web'){
				// 网页付款二维码 - 按照用户设计，所有参数都放在scene中
				if(strpos($scene, 'pid_') === 0){
					// 如果是pid场景，从数据库获取对应的商家ID，构建bid_xxx-pid_xxx格式
					$mid = str_replace('pid_', '', $scene);
					$business = Db::name('business')->where('aid', $aid)->where('mid', $mid)->find();
					$business_id = $business ? $business['id'] : 0;
					$final_scene = 'bid_' . $business_id . '-' . $scene;
				} else if(strpos($scene, 'bid_') === 0) {
					// 如果是bid场景，直接使用
					$final_scene = $scene;
				} else {
					// 其他格式保持原样
					$final_scene = $scene;
				}
				// 构建URL时，所有参数都放在scene中
				$payUrl = PRE_URL . '/h5/' . $aid . '.html#/pagesExt/maidan/pay?scene=' . $final_scene . '&t=' . time();
				$qrcode = createqrcode($payUrl);
				$img = imagecreatefromstring(request_get($qrcode));
				imagecopyresampled($target, $img, $d['left'], $d['top'], 0, 0, $d['width'], $d['height'],imagesx($img), imagesy($img));
			}else if($d['type'] == 'qrpay_mini'){
				// 小程序付款二维码 - 按照用户设计，所有参数都放在scene中
				if(strpos($scene, 'pid_') === 0){
					// 如果是pid场景，从数据库获取对应的商家ID，构建bid_xxx-pid_xxx格式
					$mid = str_replace('pid_', '', $scene);
					$business = Db::name('business')->where('aid', $aid)->where('mid', $mid)->find();
					$business_id = $business ? $business['id'] : 0;
					$final_scene = 'bid_' . $business_id . '-' . $scene;
				} else if(strpos($scene, 'bid_') === 0) {
					// 如果是bid场景，直接使用
					$final_scene = $scene;
				} else {
					// 其他格式保持原样
					$final_scene = $scene;
				}
				
				$url = 'https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token='.\app\admin\common\Wechat::access_token($aid,'wx');
				$data = array();
				$data['scene'] = $final_scene;
				$data['page'] = 'pagesExt/maidan/pay';
				
				$res = request_post($url,jsonEncode($data));
				$errmsg =json_decode($res,true);
				if($errmsg){
					if($errmsg['errcode'] == 41030){
						echojson(array('status'=>0,'msg'=>'小程序发布后才能生成分享海报'));
					}else{
						echojson(array('status'=>0,'msg'=>$errmsg['errmsg']));
					}
				}
				if(!$errmsg){
					$img = imagecreatefromstring($res);
					imagecopyresampled($target, $img, $d['left'], $d['top'], 0, 0, $d['width'], $d['height'],imagesx($img), imagesy($img));
				}
			}else if ($d['type'] == 'img') {
				if($d['src'][0] == '/') $d['src'] = PRE_URL.$d['src'];
				$img = imagecreatefromstring(request_get($d['src']));
				if($img)
				imagecopyresampled($target, $img, $d['left'], $d['top'], 0, 0, $d['width'], $d['height'],imagesx($img), imagesy($img));
			} else if ($d['type'] == 'text') {
				$d['content'] = str_replace(array_keys($textReplaceArr),array_values($textReplaceArr),$d['content']);
				$colors = hex2rgb($d['color']);
				$color = imagecolorallocate($target, $colors['red'], $colors['green'], $colors['blue']);
				
				// 检查是否需要居中
				$x_position = $d['left'];
				if(isset($d['center']) && $d['center'] == '1'){
					// 计算文本宽度并居中
					$text_box = imagettfbbox($d['size'], 0, $font, $d['content']);
					$text_width = $text_box[4] - $text_box[0];
					$x_position = $d['left'] + ($d['width'] - $text_width) / 2;
				}
				
				imagettftext($target, $d['size'], 0, $x_position, $d['top'] + $d['size'], $color, $font,  $d['content']);
			} else if ($d['type'] == 'textarea') {
				$d['content'] = str_replace(array_keys($textReplaceArr),array_values($textReplaceArr),$d['content']);
				$colors = hex2rgb($d['color']);
				$color = imagecolorallocate($target, $colors['red'], $colors['green'], $colors['blue']);
				$string = $d['content'];
				$_string='';
				$__string='';
				$_height = 0;
				mb_internal_encoding("UTF-8"); // 设置编码
				for($i=0;$i<mb_strlen($string);$i++){
					$box = imagettfbbox($d['size'],0,$font,$_string);
					$_string_length = $box[2]-$box[0];
					$box = imagettfbbox($d['size'],0,$font,mb_substr($string,$i,1));
					if($_string_length+$box[2]-$box[0]<$d['width']*1){
						$_string.=mb_substr($string,$i,1);
					}else{
						$_height += $box[1]-$box[7]+4;
						//var_dump($_height.'--'.$d['height']);
						if($_height >= $d['height']*1){
							break;
						}
						$__string.=$_string."\n";
						$_string=mb_substr($string,$i,1);
					}
				}
				$__string.=$_string; 
				$box=imagettfbbox($d['size'],0,$font,mb_substr($__string,0,1));
				
				// 检查是否需要居中处理多行文本
				if(isset($d['center']) && $d['center'] == '1'){
					// 将文本按换行符分割成多行
					$lines = explode("\n", $__string);
					$line_height = $box[3] - $box[7] + 4;
					$y_position = $d['top'] + ($box[3] - $box[7]);
					
					foreach($lines as $line_index => $line){
						if(trim($line) !== ''){
							// 计算每行文本的居中位置
							$line_box = imagettfbbox($d['size'], 0, $font, $line);
							$line_width = $line_box[4] - $line_box[0];
							$x_position = $d['left'] + ($d['width'] - $line_width) / 2;
							
							imagettftext($target, $d['size'], 0, $x_position, $y_position + ($line_index * $line_height), $color, $font, $line);
						}
					}
				} else {
					// 原来的左对齐逻辑
					imagettftext($target,$d['size'],0,$d['left'],$d['top']+($box[3]-$box[7]),$color,$font,$__string);
				}

			} else if ($d['type'] == 'pro_img') {
				$img = imagecreatefromstring(request_get($textReplaceArr['[商品图片]']));
				if($img)
				imagecopyresampled($target, $img, $d['left'], $d['top'], 0, 0, $d['width'], $d['height'],imagesx($img), imagesy($img));
			} else if ($d['type'] == 'shadow') {
				$rgba = explode(',',str_replace(array(' ','(',')','rgba'),'',$d['shadow']));
				//dump($rgba);
				$black = imagecreatetruecolor($d['width'], $d['height']);
				imagealphablending($black, false);
				imagesavealpha($black, true);
				$blackcolor = imagecolorallocatealpha($black,$rgba[0],$rgba[1],$rgba[2],(1-$rgba[3])*127);
				imagefill($black, 0, 0, $blackcolor);
				imagecopy($target, $black, $d['left'], $d['top'], 0, 0, $d['width'], $d['height']);
				imagedestroy($black);
			} else if($d['type'] == 'head') {
				$src_img = imagecreatefromstring(request_get($textReplaceArr['[头像]']));
				if($src_img){
					$w = imagesx($src_img);
					$h = imagesy($src_img);
					$radius = $d['radius']*0.01*$w/2;
					if($radius > 0){
						$img = imagecreatetruecolor($w, $h);
						//这一句一定要有
						imagesavealpha($img, true);
						//拾取一个完全透明的颜色,最后一个参数127为全透明
						$bg = imagecolorallocatealpha($img, 255, 255, 255, 127);
						imagefill($img, 0, 0, $bg);
						$r = $radius; //圆 角半径
						for ($x = 0; $x < $w; $x++) {
							for ($y = 0; $y < $h; $y++) {
								$rgbColor = imagecolorat($src_img, $x, $y);
								if (($x >= $radius && $x <= ($w - $radius)) || ($y >= $radius && $y <= ($h - $radius))) {
									//不在四角的范围内,直接画
									imagesetpixel($img, $x, $y, $rgbColor);
								} else {
									//在四角的范围内选择画
									//上左
									$y_x = $r; //圆心X坐标
									$y_y = $r; //圆心Y坐标
									if (((($x - $y_x) * ($x - $y_x) + ($y - $y_y) * ($y - $y_y)) <= ($r * $r))) {
										imagesetpixel($img, $x, $y, $rgbColor);
									}
									//上右
									$y_x = $w - $r; //圆心X坐标
									$y_y = $r; //圆心Y坐标
									if (((($x - $y_x) * ($x - $y_x) + ($y - $y_y) * ($y - $y_y)) <= ($r * $r))) {
										imagesetpixel($img, $x, $y, $rgbColor);
									}
									//下左
									$y_x = $r; //圆心X坐标
									$y_y = $h - $r; //圆心Y坐标
									if (((($x - $y_x) * ($x - $y_x) + ($y - $y_y) * ($y - $y_y)) <= ($r * $r))) {
										imagesetpixel($img, $x, $y, $rgbColor);
									}
									//下右
									$y_x = $w - $r; //圆心X坐标
									$y_y = $h - $r; //圆心Y坐标
									if (((($x - $y_x) * ($x - $y_x) + ($y - $y_y) * ($y - $y_y)) <= ($r * $r))) {
										imagesetpixel($img, $x, $y, $rgbColor);
									}
								}
							}
						}
						imagecopyresampled($target, $img, $d['left'], $d['top'], 0, 0, $d['width'], $d['height'],imagesx($img), imagesy($img));
					}else{
						imagecopyresampled($target, $src_img, $d['left'], $d['top'], 0, 0, $d['width'], $d['height'],imagesx($src_img), imagesy($src_img));
					}
				}
			}
		}
		$url = '/upload/'.date('Ym/d_His').rand(1000,9999).'.jpg';
		$filepath = ROOT_PATH.ltrim($url,'/');
		mk_dir(dirname($filepath));
		imagejpeg($target,$filepath,100);
		return PRE_URL.$url;
	}
	
	protected function isShopMemberPriceCheckoutOnly($scene): bool{
		if(!in_array($scene, ['shop_browse', 'shop_checkout'], true)){
			return false;
		}
		static $cache = [];
		$aidKey = intval(aid);
		if(!array_key_exists($aidKey, $cache)){
			try{
				$shopset = Db::name('shop_sysset')->where('aid', aid)->find();
				$cache[$aidKey] = intval($shopset['member_price_checkout_only'] ?? 0) === 1;
			}catch(\Throwable $e){
				$cache[$aidKey] = false;
			}
		}
		return $cache[$aidKey];
	}
	protected function hideWholesaleMemberPriceData(array $data): array{
		foreach(['wholesale_data', 'wholesale_tiers'] as $field){
			if(empty($data[$field])){
				continue;
			}
			$isJson = is_string($data[$field]);
			$tiers = $isJson ? json_decode($data[$field], true) : $data[$field];
			if(!is_array($tiers)){
				continue;
			}
			foreach($tiers as $key => $tier){
				if(is_array($tier)){
					unset($tiers[$key]['lvprices']);
				}
			}
			$data[$field] = $isJson ? json_encode($tiers, JSON_UNESCAPED_UNICODE) : $tiers;
		}
		return $data;
	}
	protected function hideMemberPriceData(array $data): array{
		unset($data['lvprice_data'], $data['member_price_value'], $data['member_price_min']);
		$data['member_price_match'] = 0;
		$data['member_price_current_match'] = 0;
		$data['member_price_current_is_default'] = 0;
		$data['member_price_level_count'] = 0;
		$data['member_price_hidden'] = 1;
		return $this->hideWholesaleMemberPriceData($data);
	}
	protected function sanitizeCheckoutMemberPricePayload(array $data): array{
		if(!$this->isShopMemberPriceCheckoutOnly('shop_checkout')){
			return $data;
		}
		unset(
			$data['lvprice_data'],
			$data['member_price_min'],
			$data['member_price_level_count'],
			$data['member_price_match'],
			$data['member_price_current_match'],
			$data['member_price_current_is_default']
		);
		return $this->hideWholesaleMemberPriceData($data);
	}
	protected function formatCheckoutMemberPriceData(array $data, bool $applied, $originPrice): array{
		unset(
			$data['lvprice_data'],
			$data['member_price_min'],
			$data['member_price_level_count'],
			$data['member_price_match'],
			$data['member_price_current_match'],
			$data['member_price_current_is_default']
		);
		$data['sell_price_origin'] = $originPrice;
		$data['member_price_applied'] = $applied ? 1 : 0;
		$data['member_price_saving'] = $applied
			? round(max(0, floatval($originPrice) - floatval($data['sell_price'])), 2)
			: 0;
		if(!$applied){
			unset($data['member_price_value']);
		}
		return $this->hideWholesaleMemberPriceData($data);
	}

	//商品列表数据会员价处理
	public function formatprolist($datalist, $scene = 'default'){
		$checkoutOnly = $this->isShopMemberPriceCheckoutOnly($scene);
		foreach($datalist as $k=>$v){
			if($scene === 'shop_browse' && $checkoutOnly){
				$datalist[$k]['sell_price_origin'] = $datalist[$k]['sell_price'];
				$datalist[$k] = $this->hideMemberPriceData($datalist[$k]);
				continue;
			}
			$datalist[$k] = $this->appendProductMemberPriceMeta($v);
			// 始终保留“非会员原价”，供前端会员价样式渲染（会员价为辅时展示原价）
			$datalist[$k]['sell_price_origin'] = $datalist[$k]['sell_price'];
			if($datalist[$k]['member_price_current_match'] == 1){
				$datalist[$k]['sell_price'] = $datalist[$k]['member_price_value'];
			}
		}
		$shopset = Db::name('shop_sysset')->where('aid',aid)->find();
		if($shopset){
			foreach($datalist as $k=>$v){
				if($shopset['hide_sales']==1){
					$datalist[$k]['sales'] = 0;
				}
			}
		}

		// 2024-07-09 - 隐藏价格
		foreach ($datalist as $key => $value) {			
			if($value['show_price'] > 0) {
			    //限制等级
			    $levelids = explode(',', $value['show_price']);
			    if(!in_array($this->member['levelid'], $levelids)) {
			    	$datalist[$key]['sell_price'] = $value['hide_price_text'];
			    	unset($datalist[$key]['market_price']);
			    }
			}

		}

		// 批发阶梯价：集中补充每个商品的 wholesale_enable 与阶梯最低价 wholesale_min_price，供各列表展示
		$wsProIds = [];
		foreach($datalist as $v){
			if(!empty($v['id'])) $wsProIds[] = $v['id'];
		}
		if($wsProIds){
			$wsRows = Db::name('shop_product')->where('id','in',array_unique($wsProIds))->where('wholesale_enable',1)->column('wholesale_data','id');
			foreach($datalist as $k=>$v){
				$datalist[$k]['wholesale_enable'] = 0;
				$datalist[$k]['wholesale_min_price'] = 0;
				$pid = $v['id'] ?? 0;
				if($pid && isset($wsRows[$pid])){
					$tiers = json_decode($wsRows[$pid], true);
					if(is_array($tiers) && $tiers){
						$minp = null;
						foreach($tiers as $t){
							if(isset($t['price']) && is_numeric($t['price'])){
								$p = (float)$t['price'];
								if($minp === null || $p < $minp) $minp = $p;
							}
						}
						if($minp !== null){
							$datalist[$k]['wholesale_enable'] = 1;
							$datalist[$k]['wholesale_min_price'] = round($minp,2);
						}
					}
				}
			}
		}

		return $datalist;
	}
	protected function getMemberLevelDefaultMap(): array{
		static $cache = [];
		$aidKey = intval(aid);
		if(isset($cache[$aidKey])){
			return $cache[$aidKey];
		}
		$levelRows = Db::name('member_level')->where('aid', aid)->field('id,isdefault')->select()->toArray();
		$defaultMap = [];
		foreach($levelRows as $levelRow){
			$defaultMap[(string)$levelRow['id']] = intval($levelRow['isdefault'] ?? 0) === 1 ? 1 : 0;
		}
		$cache[$aidKey] = $defaultMap;
		return $defaultMap;
	}
	protected function getProductMemberPriceMeta(array $product): array{
		$meta = [
			'member_price_match' => 0,
			'member_price_current_match' => 0,
			'member_price_current_is_default' => 0,
			'member_price_value' => 0,
			'member_price_min' => 0,
			'member_price_level_count' => 0,
		];
		if(!isset($product['lvprice']) || intval($product['lvprice']) !== 1){
			return $meta;
		}
		$rawPrices = [];
		if(isset($product['lvprice_data'])){
			if(is_array($product['lvprice_data'])){
				$rawPrices = $product['lvprice_data'];
			}else{
				$rawPrices = json_decode((string)$product['lvprice_data'], true);
			}
		}
		if(!$rawPrices || !is_array($rawPrices)){
			return $meta;
		}
		$levelDefaultMap = $this->getMemberLevelDefaultMap();
		$priceMap = [];
		$previewPriceMap = [];
		foreach($rawPrices as $levelId => $price){
			if(is_array($price) || $price === '' || $price === null || !is_numeric($price)){
				continue;
			}
			$levelKey = (string)$levelId;
			$priceMap[$levelKey] = round((float)$price, 2);
			if(empty($levelDefaultMap[$levelKey])){
				$previewPriceMap[$levelKey] = $priceMap[$levelKey];
			}
		}
		if(!$priceMap){
			return $meta;
		}
		$meta['member_price_level_count'] = count($previewPriceMap);
		$meta['member_price_min'] = $previewPriceMap ? min($previewPriceMap) : 0;
		if($this->member && isset($this->member['levelid'])){
			$levelKey = (string)$this->member['levelid'];
			if(array_key_exists($levelKey, $priceMap)){
				$meta['member_price_current_match'] = 1;
				$meta['member_price_value'] = $priceMap[$levelKey];
				if(!empty($levelDefaultMap[$levelKey])){
					$meta['member_price_current_is_default'] = 1;
				}else{
					$meta['member_price_match'] = 1;
				}
			}
		}
		return $meta;
	}
	protected function appendProductMemberPriceMeta(array $product): array{
		$meta = $this->getProductMemberPriceMeta($product);
		foreach($meta as $key => $value){
			$product[$key] = $value;
		}
		return $product;
	}
	//商品数据会员价处理
	public function formatproduct($product, $scene = 'default'){
		$product['sell_price_origin'] = $product['sell_price'];
		if($scene === 'shop_browse' && $this->isShopMemberPriceCheckoutOnly($scene)){
			return $this->hideMemberPriceData($product);
		}
		$product = $this->appendProductMemberPriceMeta($product);
		if($product['member_price_current_match'] == 1){
			$product['sell_price'] = $product['member_price_value'];
		}
		if($scene === 'shop_checkout' && $this->isShopMemberPriceCheckoutOnly($scene)){
			$product = $this->formatCheckoutMemberPriceData(
				$product,
				intval($product['member_price_current_match'] ?? 0) === 1,
				$product['sell_price_origin']
			);
		}
		return $product;
	}
    //商品数据会员价处理
    public function formatScoreProduct($product){
        if(!$this->member) return $product;
        if($product['lvprice']==1){
            $lvprice_data = json_decode($product['lvprice_data'],true);
            if($lvprice_data && isset($lvprice_data[$this->member['levelid']])){
                if(isset($lvprice_data[$this->member['levelid']]['money_price']))
                $product['money_price'] = $lvprice_data[$this->member['levelid']]['money_price'];
                if(isset($lvprice_data[$this->member['levelid']]['score_price']))
                $product['score_price'] = $lvprice_data[$this->member['levelid']]['score_price'];
            }
        }
        return $product;
    }
	//商品规格列表数据会员价处理
	public function formatgglist($gglist, $bid = 0, $scene = 'default'){
		$checkoutOnly = $this->isShopMemberPriceCheckoutOnly($scene);
		if($scene === 'shop_browse' && $checkoutOnly){
			foreach($gglist as $k=>$v){
				$gglist[$k]['sell_price_origin'] = $v['sell_price'];
				$gglist[$k] = $this->hideMemberPriceData($gglist[$k]);
			}
			return $gglist;
		}
		if(!$this->member){
			if($scene === 'shop_checkout' && $checkoutOnly){
				foreach($gglist as $k => $v){
					$gglist[$k] = $this->sanitizeCheckoutMemberPricePayload($v);
				}
			}
			return $gglist;
		}
		foreach($gglist as $k=>$v){
			$originPrice = $v['sell_price'];
			$applied = false;
			$lvprice_data = json_decode($v['lvprice_data'],true);
			if($lvprice_data && isset($lvprice_data[$this->member['levelid']])){
			    if($bid && getcustom('plug_businessqr') && $lvprice_data[$this->member['levelid']] == 0) {
			        $show_business = Db::name('member_level')->where('id', $this->member['levelid'])->value('show_business');
			        if($show_business) {
	                        $gglist[$k]['sell_price'] = $lvprice_data[$this->member['levelid']];
						$applied = true;
	                    }
	                } else {
	                    $gglist[$k]['sell_price'] = $lvprice_data[$this->member['levelid']];
					$applied = true;
	                }
			}
			if($scene === 'shop_checkout' && $checkoutOnly){
				if($applied){
					$gglist[$k]['member_price_value'] = $gglist[$k]['sell_price'];
				}
				$gglist[$k] = $this->formatCheckoutMemberPriceData($gglist[$k], $applied, $originPrice);
			}
		}
		return $gglist;
	}
	public function formatguige($guige, $bid = 0, $scene = 'default'){
		$checkoutOnly = $this->isShopMemberPriceCheckoutOnly($scene);
		if($scene === 'shop_browse' && $checkoutOnly){
			$guige['sell_price_origin'] = $guige['sell_price'];
			return $this->hideMemberPriceData($guige);
		}
		if(!$this->member){
			return $scene === 'shop_checkout' && $checkoutOnly
				? $this->sanitizeCheckoutMemberPricePayload($guige)
				: $guige;
		}
		$originPrice = $guige['sell_price'];
		$applied = false;
		$lvprice_data = json_decode($guige['lvprice_data'],true);
		if($lvprice_data && isset($lvprice_data[$this->member['levelid']])){
            if($bid && getcustom('plug_businessqr') && $lvprice_data[$this->member['levelid']] == 0) {
                $show_business = Db::name('member_level')->where('id', $this->member['levelid'])->value('show_business');
	                if($show_business) {
	                    $guige['sell_price'] = $lvprice_data[$this->member['levelid']];
					$applied = true;
	                }
	            } else {
	                $guige['sell_price'] = $lvprice_data[$this->member['levelid']];
				$applied = true;
	            }
		}
		if($scene === 'shop_checkout' && $checkoutOnly){
			if($applied){
				$guige['member_price_value'] = $guige['sell_price'];
			}
			$guige = $this->formatCheckoutMemberPriceData($guige, $applied, $originPrice);
		}
		return $guige;
	}
}
