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
// | 公共 验证权限
// +----------------------------------------------------------------------
namespace app\admin\controller;
use think\facade\View;
use think\facade\Db;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class Common extends Base
{
	public $aid;
	public $uid;
	public $user;
    public $admin;
	public $mdid = 0;
	public $auth_data = 'all';
	public $platform = 'mp';
	public $xcxaid = 0;
	public $adminSet;
    public $consoleUid = 0;
    public $consoleRole = 0;
    public $consoleRootUid = 0;

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

	protected function ensureGongxianzhiInstalledIfAvailable(): bool
	{
		static $checked = null;
		if ($checked !== null) {
			return $checked;
		}

		$installerClass = '\\app\\plugin\\gongxianzhi\\common\\GongxianzhiInstaller';
		if (!class_exists($installerClass)) {
			\think\facade\Log::warning('Common::ensureGongxianzhiInstalledIfAvailable 贡献值插件安装器不存在，按未安装处理');
			$checked = false;
			return false;
		}

		try {
			$installerClass::ensureInstalled();
			$checked = true;
			return true;
		} catch (\Throwable $e) {
			\think\facade\Log::warning('Common::ensureGongxianzhiInstalledIfAvailable 贡献值插件安装失败，按未安装处理：' . $e->getMessage());
			$checked = false;
			return false;
		}
	}

	protected function isGongxianzhiFeatureReady(array $requiredTables = []): bool
	{
		if (!$this->ensureGongxianzhiInstalledIfAvailable()) {
			return false;
		}

		foreach ($requiredTables as $tableName) {
			if (!$this->tableExists($tableName)) {
				return false;
			}
		}

		return true;
	}

	protected function buildPublicEntrypointUrl(string $entryFile, string $route = ''): string
	{
		$entryFile = ltrim(trim($entryFile), '/');
		$basePath = $this->getRequestPublicBasePath();
		$path = ($basePath === '' ? '' : $basePath) . '/' . $entryFile;
		$url = $this->normalizeRequestDomain((string) request()->domain()) . $path;

		$route = trim($route);
		if ($route !== '') {
			$url .= '?s=/' . ltrim($route, '/');
		}

		return $url;
	}

	protected function getRequestPublicBasePath(): string
	{
		$baseFile = str_replace('\\', '/', (string) request()->baseFile());
		if ($baseFile === '') {
			return '';
		}
		$dir = str_replace('\\', '/', dirname($baseFile));
		if ($dir === '.' || $dir === '/' || $dir === '\\') {
			return '';
		}
		return '/' . trim($dir, '/');
	}

	protected function normalizeRequestDomain(string $domain): string
	{
		return rtrim(trim($domain), '/');
	}

	protected function getPeisongSetConfig(): array
	{
		$defaults = [
			'status' => 0,
			'businessst' => 0,
			'make_status' => 0,
			'express_wx_status' => 0,
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
		if (intval($set['status']) === 1 && bid > 0 && intval($set['businessst']) === 0 && intval($set['make_status']) === 0) {
			$set['status'] = 0;
		}

		return $set;
	}

	protected function getPeisongUsersSafe(): array
	{
		if (!$this->tableExists('peisong_user')) {
			return [];
		}

		return Db::name('peisong_user')->select()->toArray();
	}

	protected function getWarehouseShipmentsStatus(): int
	{
		if (!$this->tableExists('warehouse_set')) {
			return 0;
		}

		return intval(Db::name('warehouse_set')->where('aid', aid)->value('is_shipments'));
	}

	protected function buildSchemaInsertData(string $tableName, array $data, array $defaults = []): array
	{
		try {
			$prefix = config('database.connections.mysql.prefix', '');
			$tableName = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
			if ($tableName === '') {
				return array_merge($defaults, $data);
			}

			$rows = Db::query("SHOW COLUMNS FROM `{$prefix}{$tableName}`");
			if (empty($rows)) {
				return array_merge($defaults, $data);
			}

			$fields = [];
			foreach ($rows as $row) {
				if (!empty($row['Field'])) {
					$fields[$row['Field']] = true;
				}
			}

			$result = [];
			foreach ($defaults as $key => $value) {
				if (isset($fields[$key]) && !array_key_exists($key, $data)) {
					$result[$key] = $value;
				}
			}
			foreach ($data as $key => $value) {
				if (isset($fields[$key])) {
					$result[$key] = $value;
				}
			}

			return $result;
		} catch (\Throwable $e) {
			return array_merge($defaults, $data);
		}
	}

	/**
	 * 已授权插件自动放行（用于站点管理员免勾选权限）
	 * - 仅在用户 auth_type=0 且即将被拦截时触发兜底
	 * - 仅对站点管理员（bid=0 且 isadmin>0）生效，避免误放行子账号
	 */
	protected function hasAuthorizedPluginAccess(string $controller): bool
	{
		try {
			if (empty($controller) || !isset($this->aid) || intval($this->aid) <= 0) {
				return false;
			}
			if (!isset($this->bid) || intval($this->bid) > 0) {
				return false;
			}
			if (empty($this->user) || intval($this->user['isadmin'] ?? 0) <= 0) {
				return false;
			}

			$prefix = config('database.connections.mysql.prefix');
			$menuTable = $prefix . 'admin_plugin_menu';
			$licenseTable = $prefix . 'admin_plugin_license';

			$menuExists = Db::query("SHOW TABLES LIKE '{$menuTable}'");
			if (empty($menuExists)) {
				return false;
			}
			$licenseExists = Db::query("SHOW TABLES LIKE '{$licenseTable}'");
			if (empty($licenseExists)) {
				return false;
			}

			$pluginNames = Db::name('admin_plugin_menu')
				->where('aid', $this->aid)
				->where('status', 1)
				->where('path', 'like', $controller . '/%')
				->distinct(true)
				->column('plugin_name');

			if (empty($pluginNames)) {
				return false;
			}

			$now = time();
			$license = Db::name('admin_plugin_license')
				->where('aid', $this->aid)
				->where('status', 1)
				->whereIn('plugin_name', $pluginNames)
				->where(function ($q) use ($now) {
					$q->where('expire_time', 0)->whereOr('expire_time', '>', $now);
				})
				->find();

			return !empty($license);
		} catch (\Throwable $e) {
			return false;
		}
	}

	protected function hasEnabledPluginMenu(string $pluginName): bool
	{
		try {
			if (empty($pluginName) || !isset($this->aid) || intval($this->aid) <= 0) {
				return false;
			}
			$prefix = config('database.connections.mysql.prefix');
			$menuTable = $prefix . 'admin_plugin_menu';
			$menuExists = Db::query("SHOW TABLES LIKE '{$menuTable}'");
			if (empty($menuExists)) {
				return false;
			}
			return Db::name('admin_plugin_menu')
				->where('aid', $this->aid)
				->where('plugin_name', $pluginName)
				->where('status', 1)
				->count() > 0;
		} catch (\Throwable $e) {
			return false;
		}
	}

	protected function shouldUseBusinessAuthSessionForLegacyController($request): bool
	{
		if (defined('MN') && MN == 'business') {
			return true;
		}

		if (intval(session('ADMIN_AUTH_UID')) <= 0 || intval(session('ADMIN_AUTH_BID')) <= 0) {
			return false;
		}

		if (input('param.apifrom') !== 'vue') {
			return false;
		}

		$controller = strtolower(trim(strval($request->controller())));
		return in_array($controller, [
			'liveplugin',
			'livewebplugin',
			'livewebproductplugin',
			'livewebshareplugin',
			'livewebsharegroupplugin',
			'livewebposterplugin',
			'livewebatmosphereplugin',
			'livewebconsoleshare',
		], true);
	}

	/**
	 * 权限等价路径钩子：子类控制器可重写，把当前 controller/action 映射为已授权的等价路径放行。
	 * 基类默认不放行（如 plugin/cashier 的 CashierPlugin 已重写此方法）。
	 */
	protected function hasEquivalentAuthPath(string $controller, string $action, array $authPath): bool
	{
		return $this->hasCashierPluginFrontendAuth($controller, $action, $authPath);
	}

	/**
	 * 新版收银台前端（public/cashier_new，及旧版 public/cashier）直接调用核心 Cashier/CashierExtend/MemberManage/PayChannel/Member 接口；
	 * 收银台已改为插件授权（菜单/权限节点为 CashierPlugin/*），核心菜单“收银台 Cashier/*”通常不会再勾选，
	 * 自定义权限(auth_type=0)账号会在这里被 die('无访问权限')。
	 * 已拥有收银台插件权限（权限节点含任一 CashierPlugin/xxx，或站点管理员已授权并启用收银台插件）时，放行收银台前端所需接口。
	 * 仅限下列接口，不放行核心收银设置页(Cashier/index、Cashier/save 等)。
	 */
	protected function hasCashierPluginFrontendAuth(string $controller, string $action, array $authPath): bool
	{
		$map = [
			'cashier' => [
				'getcashierinfo', 'getwaitpayorder', 'getproductlist', 'getcategorylist', 'addtocashier', 'cashierchangenum', 'cashierchangeprice',
				'cashierchangeremark', 'delcashierorder', 'hangup', 'cancelhangup', 'getcashierorder', 'searchmember', 'getmemberbycode',
				'membercouponlist', 'paypreview', 'pay', 'print', 'getshiftstatistics', 'confirmshift', 'printshiftreport',
				'checkorderpermission', 'cancelorder', 'modifyorder', 'submitcancelaudit', 'submitmodifyaudit', 'auditorder',
				'getordercommissiondetail', 'adjustcommission', 'getyuyueworkercommission', 'adjustworkercommission',
				'getcommissionstafflist', 'getcommissionmemberlist', 'getcommissionworkerlist', 'savegoodscommission', 'getgoodscommission',
				'getyuyuecategorylist', 'getyuyueproductlist', 'getyuyueworkerlist', 'getavailabletimeslots', 'addyuyuetocashier',
				'getcalendardata', 'getdayorders', 'getworkerlist', 'getworkerdetail', 'getworkerservicerecords',
				'memberrecharge', 'addrechargetocashier', 'getrechargecardlist', 'addrechargecardtocashier',
				'getlevelcardlist', 'addlevelcardtocashier', 'getmemberlevellist',
				'getpackagesforsale', 'getmemberpackages', 'getpackageorderdetail', 'addpackagetocart', 'addpackagetocashier', 'usepackageservice',
			],
			'cashierextend' => ['getcashierconfig', 'recordpaychannel'],
			'membermanage' => ['addmember', 'getmembersources', 'getmembertags'],
			'paychannel' => ['getenabledchannels', 'getpaychannels'],
			'member' => ['getmembercode'],
		];
		$c = strtolower(trim($controller));
		$a = strtolower(trim($action));
		if (!isset($map[$c]) || !in_array($a, $map[$c], true)) {
			return false;
		}
		foreach ($authPath as $item) {
			if (is_string($item) && stripos(trim($item), 'CashierPlugin/') === 0) {
				return true;
			}
		}
		return $this->hasAuthorizedPluginAccess('CashierPlugin');
	}

	protected function matchesDesignerAuthPaths(array $allowPaths, array $authData): bool
	{
		foreach ($allowPaths as $path) {
			if (!is_string($path)) {
				continue;
			}

			$path = trim($path);
			if ($path === '') {
				continue;
			}

			if (in_array($path, $authData, true)) {
				return true;
			}

			if (substr($path, -2) !== '/*') {
				continue;
			}

			$prefix = substr($path, 0, -1);
			foreach ($authData as $authItem) {
				if (!is_string($authItem)) {
					continue;
				}

				$authItem = trim($authItem);
				if ($authItem !== '' && strpos($authItem, $prefix) === 0) {
					return true;
				}
			}
		}

		return false;
	}

	protected function hasPluginBackedDesignerAccess(array $allowPaths, array $controllers = [], string $pluginName = ''): bool
	{
		if ($this->auth_data === 'all') {
			return true;
		}

		if (is_array($this->auth_data) && $this->matchesDesignerAuthPaths($allowPaths, $this->auth_data)) {
			return true;
		}

		foreach ($controllers as $controller) {
			if (!is_string($controller) || $controller === '') {
				continue;
			}
			if ($this->hasAuthorizedPluginAccess($controller)) {
				return true;
			}
		}

		return !empty($pluginName)
			&& intval($this->bid ?? 0) === 0
			&& intval($this->user['isadmin'] ?? 0) > 0
			&& $this->hasEnabledPluginMenu($pluginName);
	}

	protected function hasArticleDesignerAccess(): bool
	{
		return $this->hasPluginBackedDesignerAccess(
			['Article/*', 'Article/index', 'Article'],
			['Article'],
			'article'
		) && $this->hasEnabledPluginMenu('article')
			&& $this->tableExists('article')
			&& $this->tableExists('article_category');
	}

	protected function hasHotelDesignerAccess(): bool
	{
		return $this->hasPluginBackedDesignerAccess(
			[
				'Hotel/*', 'Hotel/index', 'Hotel',
				'HotelList/*', 'HotelList/index', 'HotelList',
				'HotelCategory/*', 'HotelCategory/index', 'HotelCategory',
				'HotelRoom/*', 'HotelRoom/index', 'HotelRoom',
				'HotelRoomPrices/*', 'HotelRoomPrices/index', 'HotelRoomPrices',
				'HotelPhotos/*', 'HotelPhotos/index', 'HotelPhotos',
				'HotelOrder/*', 'HotelOrder/index', 'HotelOrder',
				'HotelCoupon/*', 'HotelCoupon/index', 'HotelCoupon',
				'HotelComment/*', 'HotelComment/index', 'HotelComment',
				'HotelSales/*', 'HotelSales/index', 'HotelSales',
				'HotelOrderYajin/*', 'HotelOrderYajin/index', 'HotelOrderYajin',
				'HotelGroup/*', 'HotelGroup/index', 'HotelGroup',
				'HotelSet/*', 'HotelSet/index', 'HotelSet',
			],
			[
				'Hotel', 'HotelList', 'HotelCategory', 'HotelRoom', 'HotelRoomPrices',
				'HotelPhotos', 'HotelOrder', 'HotelCoupon', 'HotelComment',
				'HotelSales', 'HotelOrderYajin', 'HotelGroup', 'HotelSet',
			],
			'hotel'
		) && $this->hasEnabledPluginMenu('hotel')
			&& $this->tableExists('hotel')
			&& $this->tableExists('hotel_set');
	}

	protected function hasCycleDesignerAccess(): bool
	{
		return $this->hasPluginBackedDesignerAccess(
			[
				'CycleProduct/*', 'CycleProduct/index', 'CycleProduct',
				'CycleCategory/*', 'CycleCategory/index', 'CycleCategory',
				'CycleOrder/*', 'CycleOrder/index', 'CycleOrder',
				'CycleComment/*', 'CycleComment/index', 'CycleComment',
				'CyclePoster/*', 'CyclePoster/index', 'CyclePoster',
				'CycleSet/*', 'CycleSet/index', 'CycleSet',
				'CycleCode/*', 'CycleCode/index', 'CycleCode',
			],
			['CycleProduct', 'CycleCategory', 'CycleOrder', 'CycleComment', 'CyclePoster', 'CycleSet', 'CycleCode'],
			'cycle'
		) && $this->hasEnabledPluginMenu('cycle')
			&& $this->tableExists('cycle_product')
			&& $this->tableExists('cycle_category');
	}

	protected function hasHuodongBaomingDesignerAccess(): bool
	{
		return $this->hasPluginBackedDesignerAccess(
			['HuodongBaomingList/*', 'HuodongBaomingList/index', 'HuodongBaomingList'],
			['HuodongBaomingList'],
			'activity'
		);
	}

	protected function hasFormDesignerAccess(): bool
	{
		return $this->hasPluginBackedDesignerAccess(
			['Form/*', 'Form/index', 'Form', 'Form/record'],
			['Form'],
			'form'
		);
	}

	protected function hasShortvideoDesignerAccess(): bool
	{
		return $this->hasPluginBackedDesignerAccess(
			['Shortvideo/*', 'Shortvideo/index', 'Shortvideo'],
			['Shortvideo'],
			'shortvideo'
		) && $this->hasEnabledPluginMenu('shortvideo')
			&& $this->tableExists('shortvideo')
			&& $this->tableExists('shortvideo_category');
	}

	protected function hasZhaopinDesignerAccess(): bool
	{
		return $this->hasPluginBackedDesignerAccess(
			['Zhaopin/*', 'zhaopin/*', 'Zhaopin'],
			['Zhaopin', 'zhaopin'],
			'zhaopin'
		) && $this->hasEnabledPluginMenu('zhaopin')
			&& $this->tableExists('zhaopin_category')
			&& $this->tableExists('zhaopin_company')
			&& $this->tableExists('zhaopin_position');
	}

	protected function hasYuyueDesignerAccess(): bool
	{
		return $this->hasPluginBackedDesignerAccess(
			['YuyueList/*', 'Yuyue/*', 'YuyueList', 'Yuyue'],
			['YuyueList', 'Yuyue'],
			'yuyue'
		);
	}

	protected function hasWorkerDesignerAccess(): bool
	{
		if ($this->hasPluginBackedDesignerAccess(
			['YuyueWorker/*', 'YuyueList/*', 'Yuyue/*', 'YuyueWorker'],
			['YuyueWorker', 'YuyueList'],
			'yuyue'
		)) {
			return true;
		}
		return $this->hasPluginBackedDesignerAccess(
			[
				'DaojiaWorker/*',
				'DaojiaWorker/index',
				'DaojiaWorker',
				'DaojiaProduct/*',
				'DaojiaProduct/index',
				'DaojiaProduct',
			],
			['DaojiaWorker', 'DaojiaProduct'],
			'daojia'
		);
	}

	protected function hasDaojiaDesignerAccess(): bool
	{
		return $this->hasPluginBackedDesignerAccess(
			[
				'DaojiaProduct/*',
				'DaojiaProduct/index',
				'DaojiaProduct',
				'DaojiaCategory/*',
				'DaojiaCategory/index',
				'DaojiaCategory',
			],
			['DaojiaProduct', 'DaojiaCategory'],
			'daojia'
		);
	}

	protected function hasDaihuobijiDesignerAccess(): bool
	{
		return $this->hasPluginBackedDesignerAccess(
			[
				'Daihuobiji/*',
				'Daihuobiji/index',
				'Daihuobiji/sysset',
				'DaihuobijiCategory/*',
				'Daihuobijibiaoqian/*',
				'DaihuobijiGuanzhu/*',
				'DaihuobijiPinglun/*',
				'DaihuobijiPlreply/*',
				'Daihuobiji',
			],
			['Daihuobiji'],
			'daihuobiji'
		) && $this->hasEnabledPluginMenu('daihuobiji')
			&& $this->tableExists('daihuobiji')
			&& $this->tableExists('daihuobiji_category');
	}

	protected function hasKechengDesignerAccess(): bool
	{
		return $this->hasPluginBackedDesignerAccess(
			['KechengList/*', 'KechengList/index', 'KechengList'],
			['KechengList'],
			'kecheng'
		) && $this->hasEnabledPluginMenu('kecheng')
			&& $this->tableExists('kecheng_list')
			&& $this->tableExists('kecheng_category');
	}

	protected function hasExamDesignerAccess(): bool
	{
		return $this->hasPluginBackedDesignerAccess(
			['ExamList/*', 'ExamList/index', 'ExamList'],
			['ExamList'],
			'exam'
		) && $this->hasEnabledPluginMenu('exam')
			&& $this->tableExists('exam_list')
			&& $this->tableExists('exam_category');
	}

	protected function hasVenuesDesignerAccess(): bool
	{
		return $this->hasPluginBackedDesignerAccess(
			['Venues/*', 'Venues/index', 'Venues'],
			['Venues'],
			'venues'
		);
	}

	protected function hasHolidayDesignerAccess(): bool
	{
		return $this->hasPluginBackedDesignerAccess(
			['Holiday/*', 'Holiday/index', 'Holiday'],
			['Holiday'],
			'holiday'
		);
	}

	protected function hasJishouDesignerAccess(): bool
	{
		return $this->hasPluginBackedDesignerAccess(
			[
				'Miaosha/*', 'Miaosha/index', 'Miaosha/changci', 'Miaosha',
				'MiaoshaProduct/*', 'MiaoshaProduct/index', 'MiaoshaProduct',
				'MiaoshaSet/*', 'MiaoshaSet/index', 'MiaoshaSet',
			],
			['Miaosha', 'MiaoshaProduct', 'MiaoshaSet'],
			'jishou'
		);
	}
    public function initialize(){
		parent::initialize();
		$request = request();

		// 设置session cookie为跨域可用
		if(input('param.apifrom')=='vue' || strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'Mozilla') !== false){
			session_set_cookie_params([
				'lifetime' => 604800,
				'path' => '/',
				'domain' => 'localhost', // 允许localhost和localhost:8443共享
				'secure' => false, // 在HTTP和HTTPS下都可访问
				'httponly' => true,
				'samesite' => 'None' // 允许跨站点
			]);
		}

        //如果是vueapi的请求，则不执行header跳转，直接返回状态给接口前端
		if(input('param.apifrom')=='vue'){
            //测试
            //测试 end
            if(!session("?ADMIN_LOGIN")){
                echojson(['status'=>-1,'msg'=>'请重新登录']);die();
            }
        }else{
            if(!in_array($request->controller(),array('login')) && !session("?ADMIN_LOGIN")){
                header('Location:'.(string)url('login/index'));die;
            }
        }
		$this->aid = session('ADMIN_AID');
		if($this->shouldUseBusinessAuthSessionForLegacyController($request)){
			$authUid = intval(session('ADMIN_AUTH_UID'));
			$authBid = intval(session('ADMIN_AUTH_BID'));
			if($authUid > 0 && $authBid > 0){
				$this->uid = $authUid;
				$this->bid = $authBid;
			}else{
				$this->uid = session('ADMIN_UID');
				$this->bid = session('ADMIN_BID');
			}
		}else{
			$this->uid = session('ADMIN_UID');
			$this->bid = session('ADMIN_BID');
		}
		define('aid',$this->aid);
		define('bid',$this->bid);
		define('uid',$this->uid);
		$user = Db::name('admin_user')->where('id',$this->uid)->find();
		if($user['groupid']){
			$group = Db::name('admin_user_group')->where('id',$user['groupid'])->find();
			$user['auth_data'] = $group['auth_data'];
			$user['wxauth_data'] = $group['wxauth_data'];
			$user['notice_auth_data'] = $group['notice_auth_data'];
			$user['hexiao_auth_data'] = $group['hexiao_auth_data'];
			$user['mdid'] = $group['mdid'];
			$user['showtj'] = $group['showtj'];
		}
		if($user['bid'] > 0 && $user['auth_type'] == 1){
			$adminuser = Db::name('admin_user')
				->where('aid',$this->aid)
				->where('bid',intval($user['bid']))
				->where('isadmin','>',0)
				->order('id asc')
				->find();
			if($adminuser){
				$user['auth_type'] = $adminuser['auth_type'];
				$user['auth_data'] = $adminuser['auth_data'];
			}
		}
		$this->user = $user;
        $this->consoleUid = \app\admin\common\ConsoleAuth::getConsoleUid();
        $this->consoleRole = \app\admin\common\ConsoleAuth::getConsoleRole($user);
        $this->consoleRootUid = \app\admin\common\ConsoleAuth::getConsoleRootUid($user);
		$this->mdid = $user['mdid'];
			if($user['auth_type']==0){
				$auth_data = json_decode($user['auth_data'],true);
				$auth_path = \app\admin\common\Menu::blacklist();
				foreach($auth_data as $v){
					$auth_path = array_merge($auth_path,explode(',',$v));
				}
				// 兼容历史影子账号：排班独立管理员（sm_account_*/sm_member_*）统一放行 Shiftmaster 控制器下的内部接口
				$shadowUsername = strtolower(trim(strval($user['un'] ?? '')));
				if(strpos($shadowUsername, 'sm_account_') === 0 || strpos($shadowUsername, 'sm_member_') === 0){
					if(!in_array('Shiftmaster/*', $auth_path, true)){
						$auth_path[] = 'Shiftmaster/*';
					}
				}
				// 兼容历史权限组：已有“报名管理”权限时，自动补齐报名日历看板接口权限
				if(strtolower($request->controller()) === 'shiftmaster' && strtolower($request->action()) === 'assignmentboard'){
					if(in_array('Shiftmaster/assignment', $auth_path, true) || in_array('Shiftmaster/*', $auth_path, true)){
						if(!in_array('Shiftmaster/assignmentBoard', $auth_path, true)){
							$auth_path[] = 'Shiftmaster/assignmentBoard';
						}
						if(!in_array('Shiftmaster/assignmentboard', $auth_path, true)){
							$auth_path[] = 'Shiftmaster/assignmentboard';
						}
					}
				}

				$thispath = $request->controller() .'/'.$request->action();
				$isSuperConsole = \app\admin\common\ConsoleAuth::isSuperConsole($user);
				if(!in_array($request->controller().'/*',$auth_path) && !in_array($thispath,$auth_path) && !$isSuperConsole){
					// 兜底：如果当前路径属于“已授权插件”，则不再要求手动勾选权限
				if(!$this->hasEquivalentAuthPath($request->controller(), $request->action(), $auth_path) && !$this->hasAuthorizedPluginAccess($request->controller())){
					die('无访问权限');
				}
			}
			$this->auth_data = $auth_path;
		}else{
			$this->auth_data = 'all';
		}
		View::assign('aid',$this->aid);
		View::assign('bid',$this->bid);
		View::assign('auth_data',$this->auth_data);
		View::assign('has_article_designer_access', $this->hasArticleDesignerAccess());
		View::assign('has_activity_designer_access', $this->hasHuodongBaomingDesignerAccess());
		View::assign('has_form_designer_access', $this->hasFormDesignerAccess());
		View::assign('has_shortvideo_designer_access', $this->hasShortvideoDesignerAccess());
		View::assign('has_zhaopin_designer_access', $this->hasZhaopinDesignerAccess());
		View::assign('has_daojia_designer_access', $this->hasDaojiaDesignerAccess());
		View::assign('has_yuyue_designer_access', $this->hasYuyueDesignerAccess());
		View::assign('has_worker_designer_access', $this->hasWorkerDesignerAccess());
		View::assign('has_daihuobiji_designer_access', $this->hasDaihuobijiDesignerAccess());
		View::assign('has_kecheng_designer_access', $this->hasKechengDesignerAccess());
		View::assign('has_exam_designer_access', $this->hasExamDesignerAccess());
		View::assign('has_venues_designer_access', $this->hasVenuesDesignerAccess());
		View::assign('has_holiday_designer_access', $this->hasHolidayDesignerAccess());
		View::assign('has_hotel_designer_access', $this->hasHotelDesignerAccess());
		View::assign('has_cycle_designer_access', $this->hasCycleDesignerAccess());
		View::assign('has_jishou_designer_access', $this->hasJishouDesignerAccess());
        View::assign('console_role', $this->consoleRole);
        View::assign('console_root_uid', $this->consoleRootUid);
        $this->adminSet = Db::name('admin_set')->where('aid',aid)->find();
		$platform = \app\admin\common\Common::getplatform(aid);
		$this->platform = $platform;
		View::assign('platform',$platform);

		$admin = Db::name('admin')->where('id',aid)->find();
        $this->admin = $admin;
		if($admin['domain']){
			define('PRE_URL2','https://'.$admin['domain']);
		}else{
			define('PRE_URL2',PRE_URL);
		}
    }

    /**
     * @param array $titleArr
     * @param array $dataArr
     * @param array $typeArr 类型数组
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
	function export_excel(array $titleArr,array $dataArr,array $typeArr=[]){
		$phpexcel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();//实例化
		$phpsheet = $phpexcel->getActiveSheet();
		//填充表头信息
		$widthArr = [];
		for($i = 0;$i < count($titleArr);$i++) {
			$phpsheet->setCellValue(IntToChr($i)."1","$titleArr[$i]");
			$widthArr[] = strlen($titleArr[$i]);
		}
		//填充表格信息
		for ($i = 2;$i <= count($dataArr) + 1;$i++) {
			$j = 0;
			foreach ($dataArr[$i-2] as $key=>$value) {
			    if($typeArr && $typeArr[$j] == 1)//数字
                    $phpsheet->setCellValue(IntToChr($j)."$i","$value");
			    else
				    $phpsheet->setCellValueExplicit(IntToChr($j)."$i","$value",\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				if(strlen($value) > $widthArr[$j]){
					$widthArr[$j] = strlen($value);
					if($widthArr[$j] > 25) $widthArr[$j] = 25;
				}
				$j++;
			}
		}
		//设置列宽
		foreach($widthArr as $k=>$v){
			$phpsheet->getColumnDimension(IntToChr($k))->setWidth($v+2);
		}
		$phpwriter = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($phpexcel,"Xlsx");
		header('Content-Disposition: attachment;filename="'.date('YmdHis').'.xlsx"');
		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		$phpwriter->save("php://output");
	}
    function import_excel($file='', $startrow=2){
        // 处理URL与常见相对路径，避免 public/public 重复
        if(is_string($file) && $file !== ''){
            $root = rtrim(ROOT_PATH, DIRECTORY_SEPARATOR);
            $rootIsPublic = (strtolower(basename($root)) === 'public');

            // URL -> 物理路径
            if(strpos($file, PRE_URL) === 0){
                $pathAfterDomain = ltrim(str_replace(PRE_URL, '', $file), '/\\');
                $pathAfterDomain = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $pathAfterDomain);
                if($rootIsPublic){
                    // ROOT 已在 public 下，移除路径中的 public 前缀（如有）
                    $publicPrefix = 'public'.DIRECTORY_SEPARATOR;
                    if(stripos($pathAfterDomain, $publicPrefix) === 0){
                        $pathAfterDomain = substr($pathAfterDomain, strlen($publicPrefix));
                    }
                    $file = $root . DIRECTORY_SEPARATOR . $pathAfterDomain;
                }else{
                    // ROOT 不在 public 下，如果缺失 public 前缀则补上
                    if(stripos($pathAfterDomain, 'public'.DIRECTORY_SEPARATOR) === 0){
                        $file = $root . DIRECTORY_SEPARATOR . $pathAfterDomain;
                    }else{
                        $file = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . $pathAfterDomain;
                    }
                }
            }else{
                // 处理以 /public/、public/ 或 upload/ 开头的相对/绝对路径
                $normalized = ltrim($file, '/\\');
                $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $normalized);
                if(stripos($normalized, 'public'.DIRECTORY_SEPARATOR) === 0){
                    if($rootIsPublic){
                        // 去掉前缀 public/
                        $normalized = substr($normalized, strlen('public'.DIRECTORY_SEPARATOR));
                        $file = $root . DIRECTORY_SEPARATOR . $normalized;
                    }else{
                        $file = $root . DIRECTORY_SEPARATOR . $normalized;
                    }
                }elseif(stripos($normalized, 'upload'.DIRECTORY_SEPARATOR) === 0){
                    if($rootIsPublic){
                        $file = $root . DIRECTORY_SEPARATOR . $normalized;
                    }else{
                        $file = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . $normalized;
                    }
                }
            }
        }
        
        // 统一路径分隔符为系统分隔符
        $file = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file);
		
		// Windows环境下只在必要时转码，避免路径问题
		if (PHP_OS_FAMILY === 'Windows' && !mb_check_encoding($file, 'ASCII')) {
			$originalFile = $file;
			$file = iconv("utf-8", "gb2312//IGNORE", $file);
			// 记录调试信息
			\think\facade\Log::write('礼品导入文件路径转码: ' . $originalFile . ' -> ' . $file);
		}
		
		// 记录调试信息
		\think\facade\Log::write('礼品导入文件最终路径: ' . $file);
		\think\facade\Log::write('文件是否存在: ' . (file_exists($file) ? '是' : '否'));
		
		if(empty($file) OR !file_exists($file)) {
			echojson(['status'=>0,'msg'=>'文件不存在！路径：' . $file]);
		}
		$objRead = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');   //建立reader对象
		if(!$objRead->canRead($file)){
			$objRead = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xls');
			if(!$objRead->canRead($file)){
				echojson(['status'=>0,'msg'=>'No Excel!']);
			}
		}
		$PHPExcel = $objRead->load($file);
		$currentSheet = $PHPExcel->getSheet(0);  //读取excel文件中的第一个工作表
		$allColumn = $currentSheet->getHighestColumn(); //取得最大的列号
		$allRow = $currentSheet->getHighestRow(); //取得一共有多少行
		$exceldata = array();
		/**从第二行开始输出，因为excel表中第一行为列名*/
		for($currentRow = $startrow;$currentRow <= $allRow;$currentRow++){
			/**从第A列开始输出*/
			$erp_orders_id = array();  //声明数组
			for($currentColumn= 'A';$currentColumn<= $allColumn; $currentColumn++){
				$val = $currentSheet->getCellByColumnAndRow(ord($currentColumn) - 64,$currentRow)->getValue();/**ord()将字符转为十进制数*/
				$erp_orders_id[] = $val;
			}
			$exceldata[] = $erp_orders_id;
		}
		return $exceldata;
	}

    
    function import_excel2($file='', $startrow=2){
        // 处理URL与常见相对路径，避免 public/public 重复
        if(is_string($file) && $file !== ''){
            $root = rtrim(ROOT_PATH, DIRECTORY_SEPARATOR);
            $rootIsPublic = (strtolower(basename($root)) === 'public');

            // URL -> 物理路径
            if(strpos($file, PRE_URL) === 0){
                $pathAfterDomain = ltrim(str_replace(PRE_URL, '', $file), '/\\');
                $pathAfterDomain = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $pathAfterDomain);
                if($rootIsPublic){
                    $publicPrefix = 'public'.DIRECTORY_SEPARATOR;
                    if(stripos($pathAfterDomain, $publicPrefix) === 0){
                        $pathAfterDomain = substr($pathAfterDomain, strlen($publicPrefix));
                    }
                    $file = $root . DIRECTORY_SEPARATOR . $pathAfterDomain;
                }else{
                    if(stripos($pathAfterDomain, 'public'.DIRECTORY_SEPARATOR) === 0){
                        $file = $root . DIRECTORY_SEPARATOR . $pathAfterDomain;
                    }else{
                        $file = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . $pathAfterDomain;
                    }
                }
            }else{
                // 处理以 /public/、public/ 或 upload/ 开头的相对/绝对路径
                $normalized = ltrim($file, '/\\');
                $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $normalized);
                if(stripos($normalized, 'public'.DIRECTORY_SEPARATOR) === 0){
                    if($rootIsPublic){
                        $normalized = substr($normalized, strlen('public'.DIRECTORY_SEPARATOR));
                        $file = $root . DIRECTORY_SEPARATOR . $normalized;
                    }else{
                        $file = $root . DIRECTORY_SEPARATOR . $normalized;
                    }
                }elseif(stripos($normalized, 'upload'.DIRECTORY_SEPARATOR) === 0){
                    if($rootIsPublic){
                        $file = $root . DIRECTORY_SEPARATOR . $normalized;
                    }else{
                        $file = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . $normalized;
                    }
                }
            }
        }
        
        // 统一路径分隔符为系统分隔符
        $file = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file);
		
		// Windows环境下只在必要时转码，避免路径问题
		if (PHP_OS_FAMILY === 'Windows' && !mb_check_encoding($file, 'ASCII')) {
			$originalFile = $file;
			$file = iconv("utf-8", "gb2312//IGNORE", $file);
			// 记录调试信息
			\think\facade\Log::write('礼品导入文件路径转码2: ' . $originalFile . ' -> ' . $file);
		}
		
		// 记录调试信息
		\think\facade\Log::write('礼品导入文件最终路径2: ' . $file);
		\think\facade\Log::write('文件是否存在2: ' . (file_exists($file) ? '是' : '否'));
		
		if(empty($file) OR !file_exists($file)) {
			echojson(['status'=>0,'msg'=>'文件不存在！路径：' . $file]);
		}
		$objRead = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');   //建立reader对象
		if(!$objRead->canRead($file)){
			$objRead = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xls');
			if(!$objRead->canRead($file)){
				echojson(['status'=>0,'msg'=>'No Excel!']);
			}
		}
		$PHPExcel = $objRead->load($file);
		$currentSheet = $PHPExcel->getSheet(0);  //读取excel文件中的第一个工作表
		$allColumn = $currentSheet->getHighestColumn(); //取得最大的列号
		$allRow = $currentSheet->getHighestRow(); //取得一共有多少行
		$exceldata = array();
		/**从第二行开始输出，因为excel表中第一行为列名*/
		for($currentRow = $startrow;$currentRow <= $allRow;$currentRow++){
			/**从第A列开始输出*/
			$erp_orders_id = array();  //声明数组
			for($currentColumn= 'A';$currentColumn<= $allColumn; $currentColumn++){
			    $cell = $currentSheet->getCell($currentColumn . $currentRow);
			    $cellValue = $cell->getValue();
			    if(gettype($cellValue)=='object'){
			        $richTextText = $cellValue->__toString(); // 直接转换为字符串
			        $erp_orders_id[] = $richTextText;
			    }else{
			        $val = $currentSheet->getCellByColumnAndRow(ord($currentColumn) - 64,$currentRow)->getValue();/**ord()将字符转为十进制数*/
			        $erp_orders_id[] = $val;
			    }
			}
			$exceldata[] = $erp_orders_id;
		}
		return $exceldata;
	}
	
	
	
	public function inputlockpwd(){
		$lockpwd = input('param.lockpwd');
		$realpwd = Db::name('admin_set')->where('aid',aid)->value('locking_pwd');
		if($lockpwd != $realpwd){
			session('isunlock',0);
			return json(['status'=>0,'msg'=>'密码错误']);
		}else{
			session('isunlock',1);
			return json(['status'=>1,'msg'=>'操作成功']);
		}
	}
	
	
 public  function _pr($arr,$type=0){
            echo "<pre>";
                print_r($arr);
                echo "</pre>";
                if($type==1){
                    exit();
                }
    }
}
