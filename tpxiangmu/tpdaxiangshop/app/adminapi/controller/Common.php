<?php
/**
 * Vue前端 API 基类
 * 统一的API规范和认证处理
 */
namespace app\adminapi\controller;
use app\BaseController;
use think\facade\Db;

class Common extends BaseController
{
    public $user = [];
    public $aid = 0;
    public $bid = 0;
    public $uid = 0;

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
            \think\facade\Log::warning('AdminApi Common 贡献值插件安装器不存在，按未安装处理');
            $checked = false;
            return false;
        }

        try {
            $installerClass::ensureInstalled();
            $checked = true;
            return true;
        } catch (\Throwable $e) {
            \think\facade\Log::warning('AdminApi Common 贡献值插件安装失败，按未安装处理：' . $e->getMessage());
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

    protected function hasAuthorizedPluginAccess(string $controller): bool
    {
        try {
            if (empty($controller) || intval($this->aid) <= 0) {
                return false;
            }
            if (intval($this->bid) > 0) {
                return false;
            }
            if (intval($this->user['isadmin'] ?? 0) <= 0) {
                return false;
            }

            $prefix = config('database.connections.mysql.prefix');
            $menuTable = $prefix . 'admin_plugin_menu';
            $licenseTable = $prefix . 'admin_plugin_license';

            if (empty(Db::query("SHOW TABLES LIKE '{$menuTable}'")) || empty(Db::query("SHOW TABLES LIKE '{$licenseTable}'"))) {
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
            if (empty($pluginName) || intval($this->aid) <= 0) {
                return false;
            }

            $prefix = config('database.connections.mysql.prefix');
            $menuTable = $prefix . 'admin_plugin_menu';
            if (empty(Db::query("SHOW TABLES LIKE '{$menuTable}'"))) {
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

    protected function matchesDesignerAuthPaths(array $authData, array $allowPaths): bool
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

    protected function hasPluginBackedDesignerAccess(
        array $authData,
        bool $isAllAuth,
        array $allowPaths,
        array $controllers = [],
        string $pluginName = ''
    ): bool {
        // 先检查插件是否在站点启用（不受 isAllAuth 影响）
        if (!empty($pluginName)) {
            if (!$this->hasEnabledPluginMenu($pluginName)) {
                return false;
            }
            // 插件已启用，管理员直接放行
            if ($isAllAuth) {
                return true;
            }
        } else {
            // 无插件绑定的组件，管理员直接放行
            if ($isAllAuth) {
                return true;
            }
        }

        if ($this->matchesDesignerAuthPaths($authData, $allowPaths)) {
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

        return false;
    }

    protected function getResolvedAuthContext(): array
    {
        $user = $this->user;

        if (!empty($user['groupid'])) {
            $group = Db::name('admin_user_group')->where('id', $user['groupid'])->find();
            if (!empty($group)) {
                $user['auth_data'] = $group['auth_data'] ?? ($user['auth_data'] ?? '[]');
                $user['wxauth_data'] = $group['wxauth_data'] ?? ($user['wxauth_data'] ?? '[]');
                $user['notice_auth_data'] = $group['notice_auth_data'] ?? ($user['notice_auth_data'] ?? '[]');
                $user['hexiao_auth_data'] = $group['hexiao_auth_data'] ?? ($user['hexiao_auth_data'] ?? '[]');
                $user['mdid'] = $group['mdid'] ?? ($user['mdid'] ?? 0);
                $user['showtj'] = $group['showtj'] ?? ($user['showtj'] ?? 0);
            }
        }

        if (intval($user['bid'] ?? 0) > 0 && intval($user['auth_type'] ?? 0) === 1) {
            $adminUser = Db::name('admin_user')
                ->where('aid', $this->aid)
                ->where('bid', intval($user['bid']))
                ->where('isadmin', '>', 0)
                ->order('id asc')
                ->find();
            if (!empty($adminUser)) {
                $user['auth_type'] = $adminUser['auth_type'] ?? ($user['auth_type'] ?? 1);
                $user['auth_data'] = $adminUser['auth_data'] ?? ($user['auth_data'] ?? '[]');
            }
        }

        $this->user = is_array($user) ? $user : (array)$user;

        if (intval($this->user['auth_type'] ?? 1) !== 0) {
            return [
                'authData' => [],
                'isAllAuth' => true,
            ];
        }

        $rawAuthData = $this->user['auth_data'] ?? [];
        if (is_string($rawAuthData)) {
            $trimmedAuthData = trim($rawAuthData);
            if ($trimmedAuthData === 'all') {
                return [
                    'authData' => [],
                    'isAllAuth' => true,
                ];
            }
            $rawAuthData = json_decode($trimmedAuthData, true);
        }

        if (!is_array($rawAuthData)) {
            $rawAuthData = [];
        }

        $authPath = \app\admin\common\Menu::blacklist();
        foreach ($rawAuthData as $item) {
            foreach (explode(',', strval($item)) as $path) {
                $path = trim($path);
                if ($path !== '') {
                    $authPath[] = $path;
                }
            }
        }

        return [
            'authData' => array_values(array_unique($authPath)),
            'isAllAuth' => false,
        ];
    }

    protected function hasArticleDesignerAccess(array $authData = [], bool $isAllAuth = false): bool
    {
        return $this->hasPluginBackedDesignerAccess(
            $authData,
            $isAllAuth,
            ['Article/*', 'Article/index', 'Article'],
            ['Article'],
            'article'
        ) && $this->tableExists('article')
            && $this->tableExists('article_category');
    }

    protected function hasHuodongBaomingDesignerAccess(array $authData = [], bool $isAllAuth = false): bool
    {
        return $this->hasPluginBackedDesignerAccess(
            $authData,
            $isAllAuth,
            ['HuodongBaomingList/*', 'HuodongBaomingList/index', 'HuodongBaomingList'],
            ['HuodongBaomingList'],
            'activity'
        );
    }

    protected function hasFormDesignerAccess(array $authData = [], bool $isAllAuth = false): bool
    {
        return $this->hasPluginBackedDesignerAccess(
            $authData,
            $isAllAuth,
            ['Form/*', 'Form/index', 'Form', 'Form/record'],
            ['Form'],
            'form'
        );
    }

    protected function hasShortvideoDesignerAccess(array $authData = [], bool $isAllAuth = false): bool
    {
        return $this->hasPluginBackedDesignerAccess(
            $authData,
            $isAllAuth,
            ['Shortvideo/*', 'Shortvideo/index', 'Shortvideo'],
            ['Shortvideo'],
            'shortvideo'
        ) && $this->tableExists('shortvideo')
            && $this->tableExists('shortvideo_category');
    }

    protected function hasYuyueDesignerAccess(array $authData = [], bool $isAllAuth = false): bool
    {
        return $this->hasPluginBackedDesignerAccess(
            $authData,
            $isAllAuth,
            ['YuyueList/*', 'Yuyue/*', 'YuyueList', 'Yuyue'],
            ['YuyueList', 'Yuyue'],
            'yuyue'
        );
    }

    protected function hasWorkerDesignerAccess(array $authData = [], bool $isAllAuth = false): bool
    {
        if ($this->hasPluginBackedDesignerAccess(
            $authData,
            $isAllAuth,
            ['YuyueWorker/*', 'YuyueList/*', 'Yuyue/*', 'YuyueWorker'],
            ['YuyueWorker', 'YuyueList'],
            'yuyue'
        )) {
            return true;
        }
        return $this->hasPluginBackedDesignerAccess(
            $authData,
            $isAllAuth,
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

    protected function hasDaojiaDesignerAccess(array $authData = [], bool $isAllAuth = false): bool
    {
        return $this->hasPluginBackedDesignerAccess(
            $authData,
            $isAllAuth,
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

    protected function hasKechengDesignerAccess(array $authData = [], bool $isAllAuth = false): bool
    {
        return $this->hasPluginBackedDesignerAccess(
            $authData,
            $isAllAuth,
            ['KechengList/*', 'KechengList/index', 'KechengList'],
            ['KechengList'],
            'kecheng'
        ) && $this->tableExists('kecheng_list')
            && $this->tableExists('kecheng_category');
    }

    protected function hasVenuesDesignerAccess(array $authData = [], bool $isAllAuth = false): bool
    {
        return $this->hasPluginBackedDesignerAccess(
            $authData,
            $isAllAuth,
            ['Venues/*', 'Venues/index', 'Venues'],
            ['Venues'],
            'venues'
        );
    }

    protected function hasZhaopinDesignerAccess(array $authData = [], bool $isAllAuth = false): bool
    {
        return $this->hasPluginBackedDesignerAccess(
            $authData,
            $isAllAuth,
            ['Zhaopin/*', 'zhaopin/*', 'Zhaopin'],
            ['Zhaopin', 'zhaopin'],
            'zhaopin'
        ) && $this->tableExists('zhaopin_category')
            && $this->tableExists('zhaopin_company')
            && $this->tableExists('zhaopin_position');
    }

    protected function hasDaihuobijiDesignerAccess(array $authData = [], bool $isAllAuth = false): bool
    {
        return $this->hasPluginBackedDesignerAccess(
            $authData,
            $isAllAuth,
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
        ) && $this->tableExists('daihuobiji')
            && $this->tableExists('daihuobiji_category');
    }

    protected function hasExamDesignerAccess(array $authData = [], bool $isAllAuth = false): bool
    {
        return $this->hasPluginBackedDesignerAccess(
            $authData,
            $isAllAuth,
            ['ExamList/*', 'ExamList/index', 'ExamList'],
            ['ExamList'],
            'exam'
        ) && $this->tableExists('exam_list')
            && $this->tableExists('exam_category');
    }

    protected function hasErshouDesignerAccess(array $authData = [], bool $isAllAuth = false): bool
    {
        return $this->hasPluginBackedDesignerAccess(
            $authData,
            $isAllAuth,
            [
                'ErshouGoods/*', 'ErshouGoods/index', 'ErshouGoods',
                'ErshouCampus/*', 'ErshouCampus/index', 'ErshouCampus',
                'ErshouPost/*', 'ErshouPost/index', 'ErshouPost',
                'ErshouSet/*', 'ErshouSet/index', 'ErshouSet',
            ],
            ['ErshouGoods', 'ErshouCampus', 'ErshouPost', 'ErshouSet'],
            'ershou'
        );
    }

    protected function hasJishouDesignerAccess(array $authData = [], bool $isAllAuth = false): bool
    {
        return $this->hasPluginBackedDesignerAccess(
            $authData,
            $isAllAuth,
            [
                'Miaosha/*', 'Miaosha/index', 'Miaosha/changci', 'Miaosha',
                'MiaoshaProduct/*', 'MiaoshaProduct/index', 'MiaoshaProduct',
                'MiaoshaSet/*', 'MiaoshaSet/index', 'MiaoshaSet',
            ],
            ['Miaosha', 'MiaoshaProduct', 'MiaoshaSet'],
            'jishou'
        );
    }

    protected function hasHotelDesignerAccess(array $authData = [], bool $isAllAuth = false): bool
    {
        return $this->hasPluginBackedDesignerAccess(
            $authData,
            $isAllAuth,
            ['Hotel/*', 'Hotel/index', 'Hotel'],
            ['Hotel'],
            'hotel'
        ) && $this->tableExists('hotel')
            && $this->tableExists('hotel_set');
    }

    protected function hasLiveroomDesignerAccess(array $authData = [], bool $isAllAuth = false): bool
    {
        return $this->hasPluginBackedDesignerAccess(
            $authData,
            $isAllAuth,
            ['Liveroom/*', 'Liveroom/index', 'Liveroom'],
            ['Liveroom'],
            'live'
        );
    }

    protected function hasLivewebDesignerAccess(array $authData = [], bool $isAllAuth = false): bool
    {
        return $this->hasPluginBackedDesignerAccess(
            $authData,
            $isAllAuth,
            ['LiveWebPlugin/*', 'LiveWebPlugin/index', 'LiveWebPlugin'],
            ['LiveWebPlugin', 'LiveWeb'],
            'live'
        ) && $this->tableExists('live_web');
    }

    protected function hasCycleDesignerAccess(array $authData = [], bool $isAllAuth = false): bool
    {
        return $this->hasPluginBackedDesignerAccess(
            $authData,
            $isAllAuth,
            ['CycleProduct/*', 'CycleProduct/index', 'CycleProduct', 'Cycle/*'],
            ['CycleProduct', 'Cycle'],
            'cycle'
        ) && $this->tableExists('cycle_product')
            && $this->tableExists('cycle_category');
    }

    protected function hasDaihuoyiyuanDesignerAccess(array $authData = [], bool $isAllAuth = false): bool
    {
        return $this->hasPluginBackedDesignerAccess(
            $authData,
            $isAllAuth,
            ['Daihuobiji/*', 'Daihuobiji/index', 'Daihuobiji'],
            ['Daihuobiji'],
            'daihuobiji'
        );
    }

    protected function hasRestaurantProductDesignerAccess(array $authData = [], bool $isAllAuth = false): bool
    {
        return $this->hasPluginBackedDesignerAccess(
            $authData,
            $isAllAuth,
            [
                'RestaurantProduct/*',
                'RestaurantProduct/index',
                'RestaurantProduct',
                'RestaurantProductCategory/*',
                'RestaurantProductCategory/index',
                'RestaurantProductCategory',
            ],
            ['RestaurantProduct', 'RestaurantProductCategory'],
            'restaurant'
        ) && $this->tableExists('restaurant_product')
            && $this->tableExists('restaurant_product_category');
    }

    protected function hasElectricityFormDesignerAccess(array $authData = [], bool $isAllAuth = false): bool
    {
        return $this->hasPluginBackedDesignerAccess(
            $authData,
            $isAllAuth,
            ['Form/*', 'Form/index', 'Form'],
            ['Form'],
            'form'
        );
    }

    protected function hasHuizhanDesignerAccess(array $authData = [], bool $isAllAuth = false): bool
    {
        return $this->hasPluginBackedDesignerAccess(
            $authData,
            $isAllAuth,
            ['Huizhan/*', 'Huizhan/index', 'Huizhan', 'HuizhanExhibitor/*'],
            ['Huizhan', 'HuizhanExhibitor'],
            'huizhan'
        );
    }

    protected function hasGoldpriceDesignerAccess(array $authData = [], bool $isAllAuth = false): bool
    {
        return $this->hasPluginBackedDesignerAccess(
            $authData,
            $isAllAuth,
            ['Goldprice/*', 'Goldprice/index', 'Goldprice', 'Goldprice/settings'],
            ['Goldprice', 'AdminapiGoldprice'],
            'goldprice'
        );
    }

    protected function hasPaiduiDesignerAccess(array $authData = [], bool $isAllAuth = false): bool
    {
        return $this->hasPluginBackedDesignerAccess(
            $authData,
            $isAllAuth,
            ['Paidui/*', 'Paidui/index', 'Paidui', 'Paidui/sysset', 'Paidui/paiduilist'],
            ['Paidui'],
            'paidui'
        );
    }
    
    public function initialize(){
        // 跨域处理
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*';
        header("Access-Control-Allow-Origin: $origin");
        header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Access-Control-Allow-Credentials: true");

        // 设置session cookie为跨域可用
        session_set_cookie_params([
            'lifetime' => 604800,
            'path' => '/',
            'domain' => 'localhost', // 允许localhost和localhost:8443共享
            'secure' => false, // 在HTTP和HTTPS下都可访问
            'httponly' => true,
            'samesite' => 'None' // 允许跨站点
        ]);

        // OPTIONS 请求直接返回
        if(request()->isOptions()){
            exit();
        }
        
        // 登录验证（和原 admin/Common.php 保持一致）
        if(!session('?ADMIN_LOGIN')){
            echo json_encode(['status'=>-1, 'msg'=>'请先登录']);
            exit();
        }
        
        // 获取用户信息
        $uid = session('ADMIN_UID');
        $userData = Db::name('admin_user')->where('id', $uid)->find();
        
        if(!$userData){
            echo json_encode(['status'=>-1, 'msg'=>'用户不存在']);
            exit();
        }
        
        // 转换为数组
        $this->user = is_array($userData) ? $userData : (array)$userData;
        
        // 设置属性值（强制转换为整数）
        $this->aid = intval(session('ADMIN_AID') ?: 0);
        $this->bid = intval(session('ADMIN_BID') ?: 0);
        $this->uid = intval(session('ADMIN_UID') ?: 0);
        
        // 定义常量（和原admin保持一致）
        if(!defined('aid')) define('aid', $this->aid);
        if(!defined('bid')) define('bid', $this->bid);
        if(!defined('uid')) define('uid', $this->uid);
    }
    
    /**
     * 读取 JSON POST 原文，避免 input()/htmlspecialchars 把嵌套数组或数字规格字段处理异常
     */
    protected function readJsonPost(): array
    {
        $raw = $this->request->getInput();
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }
        $post = $this->request->post(false);
        return is_array($post) ? $post : [];
    }

    protected function postScalar($value, $default = '')
    {
        if (is_array($value) || is_object($value)) {
            $json = json_encode($value, JSON_UNESCAPED_UNICODE);
            return $json === false ? $default : $json;
        }
        if ($value === null) {
            return $default;
        }
        return $value;
    }

    protected function encodeGuigedata($specs, $infoGuigedata = null): string
    {
        if (is_array($specs) && $specs !== []) {
            $json = json_encode($specs, JSON_UNESCAPED_UNICODE);
            return $json === false ? '' : $json;
        }
        if (is_string($specs) && $specs !== '') {
            $decoded = json_decode($specs, true);
            return is_array($decoded) ? $specs : '';
        }
        if (is_array($infoGuigedata) && $infoGuigedata !== []) {
            $json = json_encode($infoGuigedata, JSON_UNESCAPED_UNICODE);
            return $json === false ? '' : $json;
        }
        if (is_string($infoGuigedata) && $infoGuigedata !== '') {
            return $infoGuigedata;
        }
        return '';
    }

    protected function normalizeSpecOption($option): array
    {
        if (!is_array($option)) {
            return [];
        }
        $result = [];
        foreach ($option as $ks => $opt) {
            if (is_array($opt)) {
                $result[(string)$ks] = $opt;
            }
        }
        return $result;
    }

    /**
     * 成功响应
     */
    protected function success($data = [], $msg = '操作成功'){
        return json([
            'status' => 1,
            'msg' => $msg,
            'data' => $data
        ])->options(['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE]);
    }
    
    /**
     * 失败响应
     */
    protected function error($msg = '操作失败', $data = []){
        return json([
            'status' => 0,
            'msg' => $msg,
            'data' => $data
        ])->options(['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE]);
    }
}
