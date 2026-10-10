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
use app\BaseController;
use think\facade\Db;
class ApiBase extends BaseController
{
	public $aid;
	public $mid;
	public $member;
	public $indexurl;
	public $sysset;
	protected $restaurantCashierAuth = [];
	protected $isRestaurantCashier = false;
	protected $restaurantCashierUseToken = false;
    public function initialize(){

		$request = request();
		if(!in_array($request->controller(),['ApiAdminProduct','ApiAdminRestaurantProduct'])){
			\think\facade\Request::filter(['strip_tags','htmlspecialchars']);
		}
		
		//die(json_encode(['status'=>0,'msg'=>'test']));
		$controller = request()->controller();
		$is_admin_controller = (strpos($controller, 'ApiAdmin') === 0);
		$is_restaurant_cashier = strtolower($controller) === 'apiadminrestaurantcashier';
		$this->isRestaurantCashier = $is_restaurant_cashier;
		if($is_restaurant_cashier){
			$this->restaurantCashierAuth = RestaurantCashierLogin::getTokenContext();
		}
		$aid = input('param.aid/d');
		if($is_restaurant_cashier){
			// POS 令牌优先；没有令牌时回退后台会话，避免 Vue 后台收银页被卡死。
			// 禁止从请求参数切换租户。
			$tokenAid = intval($this->restaurantCashierAuth['aid'] ?? 0);
			$sessionAid = (session('?ADMIN_LOGIN') && intval(session('ADMIN_AID')) > 0) ? intval(session('ADMIN_AID')) : 0;
			if($tokenAid > 0){
				$aid = $tokenAid;
				$this->restaurantCashierUseToken = true;
			}elseif($sessionAid > 0){
				$aid = $sessionAid;
				$this->restaurantCashierUseToken = false;
			}else{
				die(jsonEncode(['status'=>-10,'msg'=>'请先登录餐饮收银台']));
			}
		}
		if(!$aid){
			$portalController = strtolower(strval($controller));
			if(strpos($portalController, 'portalapi') !== false){
				try{
					$aid = \app\plugin\digitalavatar\common\DigitalAvatarPortalDomain::findAidByHost(strval(request()->host()));
				}catch(\Throwable $e){
					$aid = 0;
				}
			}
		}
		if(!$aid) die(jsonEncode(['status'=>0,'msg'=>'参数错误']));
		$admin = Db::name('admin')->where('id',$aid)->find();
		if(!$admin) die(jsonEncode(['status'=>0,'msg'=>'参数错误']));
		if($admin['status'] == 0 ) die(jsonEncode(['status'=>0,'msg'=>'账号未启用']));
		if($admin['endtime'] < time()) die(jsonEncode(['status'=>0,'msg'=>'账号过期']));

		$platform = input('param.platform');
		if($platform && !in_array($platform,['mp','wx','alipay','baidu','toutiao','qq','h5','app'])) die(jsonEncode(['status'=>0,'msg'=>'参数错误']));
		if($platform){
			define('platform',$platform);
		}else{
			if(!is_weixin()){
				define('platform','h5');
			}else{
				define('platform','mp');
			}
		}
		if(input('param.isdouyin') == 1){
			$douyinset = Db::name('douyin_sysset')->where('aid',$aid)->find();
			if($douyinset['status'] == 1){
				define('isdouyin',1);
			}else{
				define('isdouyin',0);
			}
		}else{
			define('isdouyin',0);
		}
		$this->aid = $aid;
		define('aid',$aid);

		// 定义 bid 常量（业务ID）
		// 避免在未定义 bid 常量时，被 PHP 当作字符串 'bid' 导致写库报错（如 exam_recordlog.bid）
		$bid = 0;
		if($is_admin_controller){
			// 商家后台接口：bid 必须来自登录账号（禁止从请求参数读取，避免越权/数据错乱）
			if($is_restaurant_cashier){
				if($this->restaurantCashierUseToken){
					$bid = intval($this->restaurantCashierAuth['bid'] ?? 0);
				}else{
					$bid = intval(session('ADMIN_BID') ?: 0);
				}
			}
			if(!$is_restaurant_cashier){
				$session_id = input('get.session_id');
				if((empty($session_id) || $session_id == 'undefined' || $session_id == 'null') && input('param.session_id')){
					$session_id = input('param.session_id');
				}
				// 正常 session_id 一般为非纯数字字符串；若被业务参数污染成纯数字则忽略
				if(!empty($session_id) && preg_match('/^\d+$/', (string)$session_id)){
					$session_id = '';
				}
				if(!empty($session_id) && $session_id != 'undefined' && $session_id != 'null'){
					// 优先：通过缓存 uid 反查商家 bid
					$uid = cache($session_id.'_uid');
					$hasAdminUid = false;
					if($uid){
						$bid_db = Db::name('admin_user')->where('aid',$aid)->where('id',$uid)->value('bid');
						if($bid_db !== null && $bid_db !== ''){
							$hasAdminUid = true;
							$bid = intval($bid_db);
						}
					}
					// 手机端后台身份只认显式登录后的 uid。mid 是前台会员态/通知绑定，不参与后台 bid 判定。
					if(!$hasAdminUid){
						$bid = 0;
					}
				}
			}
		}else{
			$bid = input('param.bid/d', 0);
		}
		if(!defined('bid')) define('bid', $bid);
		
		
		
    }
}
