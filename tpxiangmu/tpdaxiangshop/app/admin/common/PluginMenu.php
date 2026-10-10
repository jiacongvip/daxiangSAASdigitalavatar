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
// | 插件菜单管理
// +----------------------------------------------------------------------
namespace app\admin\common;
use think\facade\Db;
use think\facade\Session;

class PluginMenu
{
    private static $tableColumns = [];
    private static $menuTableReady = false;
    private static $pluginConfigs = [];

    /**
     * 获取当前商户ID
     * @return int
     */
    private static function getAid()
    {
        // 优先使用常量
        if(defined('aid')){
            return aid;
        }
        // 从session获取
        return Session::get('ADMIN_AID', 0);
    }
    /**
     * 从插件配置文件注册菜单
     * @param string $pluginName 插件名称
     * @param int $aid 商户ID，如果为0则使用当前session中的aid
     * @return bool
     */
    public static function registerFromConfig($pluginName, $aid = 0)
    {
        $config = self::loadPluginConfig($pluginName);
        $menus = $config['menus'] ?? $config['menu'] ?? [];

        // 如果没有传入aid，则使用当前session中的aid
        $currentAid = $aid > 0 ? $aid : self::getAid();

        self::ensureMenuTableReady();

        // 检查是否有菜单配置 (兼容 menu 和 menus 两种格式)
        if(empty($menus)){
            return true; // 没有菜单配置也算成功
        }

        $lockName = 'plugin_menu_register_' . preg_replace('/[^a-zA-Z0-9_]/', '_', strval($pluginName)) . '_' . intval($currentAid);
        if (strlen($lockName) > 64) {
            $lockName = 'pmreg_' . md5($pluginName . '_' . $currentAid);
        }
        $lockAcquired = false;
        try {
            $lockResult = Db::query("SELECT GET_LOCK('" . addslashes($lockName) . "', 10) AS got_lock");
            $lockAcquired = intval($lockResult[0]['got_lock'] ?? 0) === 1;
            if (!$lockAcquired) {
                return true;
            }

            // 先删除旧的菜单
            Db::name('admin_plugin_menu')
                ->where('aid', $currentAid)
                ->where('plugin_name', $pluginName)
                ->delete();

            // 注册新菜单
            $pluginUseVue = intval($config['use_vue'] ?? 0);
            foreach($menus as $menu){
                self::registerMenu($menu, $pluginName, 0, $currentAid, $pluginUseVue);
            }

            // 清除菜单缓存
            self::clearCache();
        } finally {
            if ($lockAcquired) {
                Db::query("SELECT RELEASE_LOCK('" . addslashes($lockName) . "')");
            }
        }

        return true;
    }

    /**
     * 按插件配置同步已有菜单的权限元数据。
     *
     * 该方法只更新指定插件的 admin_plugin_menu 记录，不重建菜单、不修改 status，
     * 适用于旧插件菜单升级以及插件配置新增权限元数据的场景。
     * $aid 为 null 时使用当前会话站点；显式传入 0 可同步平台级菜单记录。
     *
     * @param string $pluginName 插件名称
     * @param int|null $aid 站点ID
     * @return int 更新的菜单记录数
     */
    public static function syncPermissionMetadata($pluginName, $aid = null)
    {
        $pluginName = trim((string) $pluginName);
        if ($pluginName === '') {
            return 0;
        }

        $config = self::getPluginInfo($pluginName);
        if (!is_array($config)) {
            throw new \Exception('插件配置文件不存在或格式错误');
        }

        $menus = $config['menus'] ?? $config['menu'] ?? [];
        self::validateMenuVisibilityConfig($pluginName, (array) $menus);

        if (!self::checkTableExists()) {
            return 0;
        }

        $hasAdminOnly = self::columnExists('admin_plugin_menu', 'admin_only');
        $hasMerchantVisible = self::columnExists('admin_plugin_menu', 'merchant_visible');
        $hasTuanzhangVisible = self::columnExists('admin_plugin_menu', 'tuanzhang_visible');
        $hasAuthMode = self::columnExists('admin_plugin_menu', 'auth_mode');
        $hasAuthData = self::columnExists('admin_plugin_menu', 'auth_data');
        if (!$hasAdminOnly && !$hasMerchantVisible && !$hasTuanzhangVisible && !$hasAuthMode && !$hasAuthData) {
            return 0;
        }

        $visibilityMap = [];
        self::collectPermissionMetadata((array) $menus, $visibilityMap);
        if (empty($visibilityMap)) {
            return 0;
        }

        $query = Db::name('admin_plugin_menu')->where('plugin_name', $pluginName);
        if ($aid !== null) {
            $query->where('aid', intval($aid));
        } else {
            $query->where('aid', self::getAid());
        }
        $existingMenus = $query->field('id,path,name')->select()->toArray();
        if (empty($existingMenus)) {
            return 0;
        }

        $updateCount = 0;
        foreach ($existingMenus as $menu) {
            $path = trim((string) ($menu['path'] ?? ''));
            $name = trim((string) ($menu['name'] ?? ''));
            $key = $path !== '' ? 'path:' . $path : 'group:' . $name;
            if (!isset($visibilityMap[$key])) {
                continue;
            }

            $metadata = $visibilityMap[$key];
            $update = [];
            if ($hasAdminOnly && array_key_exists('admin_only', $metadata)) {
                $update['admin_only'] = intval($metadata['admin_only']);
            }
            if ($hasMerchantVisible && array_key_exists('merchant_visible', $metadata)) {
                $update['merchant_visible'] = intval($metadata['merchant_visible']);
            }
            if ($hasTuanzhangVisible && array_key_exists('tuanzhang_visible', $metadata)) {
                $update['tuanzhang_visible'] = intval($metadata['tuanzhang_visible']);
            }
            if ($hasAuthMode && array_key_exists('auth_mode', $metadata)) {
                $update['auth_mode'] = intval($metadata['auth_mode']);
            }
            if ($hasAuthData && array_key_exists('auth_data', $metadata)) {
                $update['auth_data'] = (string) $metadata['auth_data'];
            }
            if (empty($update)) {
                continue;
            }

            $updated = Db::name('admin_plugin_menu')
                ->where('id', intval($menu['id']))
                ->update($update);
            $updateCount += intval($updated);
        }

        return $updateCount;
    }

    /**
     * 批量同步多个站点的插件菜单权限元数据。
     *
     * @param string $pluginName 插件名称
     * @param array $aids 站点ID列表，可包含 0
     * @return int 更新的菜单记录数
     */
    public static function syncPermissionMetadataForAids($pluginName, array $aids)
    {
        $config = self::getPluginInfo($pluginName);
        if (is_array($config)) {
            $menus = $config['menus'] ?? $config['menu'] ?? [];
            self::validateMenuVisibilityConfig($pluginName, (array) $menus);
        }

        $total = 0;
        $aids = array_values(array_unique(array_map('intval', $aids)));
        foreach ($aids as $aid) {
            try {
                $total += self::syncPermissionMetadata($pluginName, $aid);
            } catch (\Throwable $e) {
                // 单个站点失败不影响其他站点同步，调用方可继续清理菜单缓存。
            }
        }
        return $total;
    }

    /**
     * 递归校验插件菜单角色可见性配置。
     */
    private static function validateMenuVisibilityConfig($pluginName, array $menus, array $parents = [])
    {
        foreach ($menus as $index => $menu) {
            if (!is_array($menu)) {
                continue;
            }

            $label = trim((string) ($menu['name'] ?? $menu['path'] ?? ''));
            if ($label === '') {
                $label = 'menu[' . $index . ']';
            }
            $menuPath = array_merge($parents, [$label]);

            $adminOnly = intval($menu['admin_only'] ?? 0);
            $merchantVisible = intval($menu['merchant_visible'] ?? 1);
            if ($adminOnly === 1 && $merchantVisible === 1) {
                throw new \InvalidArgumentException(
                    '插件 ' . $pluginName . ' 菜单配置冲突：' . implode(' > ', $menuPath)
                    . ' 不能同时设置 admin_only=1 和 merchant_visible=1'
                );
            }

            if (!empty($menu['children']) && is_array($menu['children'])) {
                self::validateMenuVisibilityConfig($pluginName, $menu['children'], $menuPath);
            }
        }
    }

    /**
     * 从 plugin.json 递归提取菜单权限元数据。
     */
    private static function collectPermissionMetadata(array $menus, array &$metadataMap)
    {
        foreach ($menus as $menu) {
            if (!is_array($menu)) {
                continue;
            }

            $path = trim((string) ($menu['path'] ?? ''));
            $name = trim((string) ($menu['name'] ?? ''));
            $metadata = [
                'admin_only' => intval($menu['admin_only'] ?? 0),
                'merchant_visible' => intval($menu['merchant_visible'] ?? 1),
                'tuanzhang_visible' => intval($menu['tuanzhang_visible'] ?? 0),
                'auth_mode' => intval($menu['auth_mode'] ?? 0),
                'auth_data' => (string) ($menu['auth_data'] ?? ''),
            ];
            if ($path !== '' || $name !== '') {
                $metadataMap[$path !== '' ? 'path:' . $path : 'group:' . $name] = $metadata;
            }

            if (!empty($menu['children']) && is_array($menu['children'])) {
                self::collectPermissionMetadata($menu['children'], $metadataMap);
            }
        }
    }
    
    /**
     * 递归注册菜单
     * @param array $menuConfig 菜单配置
     * @param string $pluginName 插件名称
     * @param int $pid 父级ID
     * @param int $aid 商户ID
     * @return int 返回插入的菜单ID
     */
    private static function registerMenu($menuConfig, $pluginName, $pid = 0, $aid = 0, $pluginUseVue = 0)
    {
        $currentAid = $aid > 0 ? $aid : self::getAid();

        // 如果是顶级菜单（pid=0），则挂载到"插件管理"父菜单下
        if($pid == 0){
            $pid = self::getOrCreatePluginParentMenu($currentAid);
        }

        $data = [
            'aid' => $currentAid,
            'pid' => $pid,
            'plugin_name' => $pluginName,
            'key' => $menuConfig['key'] ?? '',
            'name' => $menuConfig['name'] ?? '',
            'path' => $menuConfig['path'] ?? '',
            'icon' => $menuConfig['icon'] ?? '',
            'sort' => $menuConfig['sort'] ?? 0,
            'status' => 1,
            'createtime' => time()
        ];
        
        // 如果表中有 vue_path 字段，则保存
        if (self::columnExists('admin_plugin_menu', 'vue_path')) {
            $data['vue_path'] = $menuConfig['vue_path'] ?? '';
        }
        if (self::columnExists('admin_plugin_menu', 'use_vue')) {
            $data['use_vue'] = isset($menuConfig['use_vue']) ? intval($menuConfig['use_vue']) : intval($pluginUseVue);
        }
        if (self::columnExists('admin_plugin_menu', 'auth_mode')) {
            $data['auth_mode'] = isset($menuConfig['auth_mode']) ? intval($menuConfig['auth_mode']) : 0;
        }
        if (self::columnExists('admin_plugin_menu', 'auth_data')) {
            $data['auth_data'] = $menuConfig['auth_data'] ?? '';
        }
        if (self::columnExists('admin_plugin_menu', 'admin_only')) {
            $data['admin_only'] = isset($menuConfig['admin_only']) ? intval($menuConfig['admin_only']) : 0;
        }
        if (self::columnExists('admin_plugin_menu', 'merchant_visible')) {
            $data['merchant_visible'] = isset($menuConfig['merchant_visible']) ? intval($menuConfig['merchant_visible']) : 1;
        }
        if (self::columnExists('admin_plugin_menu', 'tuanzhang_visible')) {
            $data['tuanzhang_visible'] = isset($menuConfig['tuanzhang_visible']) ? intval($menuConfig['tuanzhang_visible']) : 0;
        }

        $menuId = Db::name('admin_plugin_menu')->insertGetId($data);

        // 如果有子菜单，递归注册
        if(!empty($menuConfig['children'])){
            foreach($menuConfig['children'] as $child){
                self::registerMenu($child, $pluginName, $menuId, $currentAid, $pluginUseVue);
            }
        }

        return $menuId;
    }
    
    /**
     * 卸载插件菜单
     * @param string $pluginName 插件名称
     * @return bool
     */
    public static function unregister($pluginName)
    {
        Db::name('admin_plugin_menu')
            ->where('aid', self::getAid())
            ->where('plugin_name', $pluginName)
            ->delete();

        // 清除菜单缓存
        self::clearCache();

        return true;
    }
    
    /**
     * 获取插件信息
     * @param string $pluginName 插件名称
     * @return array|null
     */
    public static function getPluginInfo($pluginName)
    {
        $configFile = app()->getRootPath() . "app/plugin/{$pluginName}/plugin.json";
        
        if(!file_exists($configFile)){
            return null;
        }
        
        $config = json_decode(file_get_contents($configFile), true);
        return $config;
    }
    
    /**
     * 启用插件
     * @param string $pluginName 插件名称
     * @param int $status 状态，仅支持 1 启用
     * @return bool
     */
    public static function toggleStatus($pluginName, $status)
    {
        if(intval($status) !== 1){
            return false;
        }

        Db::name('admin_plugin_menu')
            ->where('aid', self::getAid())
            ->where('plugin_name', $pluginName)
            ->update(['status' => 1]);

        // 清除菜单缓存
        self::clearCache();

        return true;
    }

    /**
     * 检查插件菜单表是否存在
     * @return bool
     */
    private static function checkTableExists()
    {
        try{
            $prefix = config('database.connections.mysql.prefix');
            $fullTableName = $prefix . 'admin_plugin_menu';
            $result = Db::query("SHOW TABLES LIKE '{$fullTableName}'");
            return !empty($result);
        }catch(\Exception $e){
            return false;
        }
    }

    private static function loadPluginConfig($pluginName)
    {
        if (isset(self::$pluginConfigs[$pluginName])) {
            return self::$pluginConfigs[$pluginName];
        }

        $configFile = app()->getRootPath() . "app/plugin/{$pluginName}/plugin.json";
        if(!file_exists($configFile)){
            throw new \Exception("插件配置文件不存在: {$configFile}");
        }

        $config = json_decode(file_get_contents($configFile), true);
        if(!$config){
            throw new \Exception("插件配置文件格式错误");
        }

        $menus = $config['menus'] ?? $config['menu'] ?? [];
        self::validateMenuVisibilityConfig($pluginName, (array) $menus);
        self::$pluginConfigs[$pluginName] = $config;
        return $config;
    }

    private static function ensureMenuTableReady()
    {
        if (self::$menuTableReady) {
            return;
        }

        if(!self::checkTableExists()){
            self::createTable();
        }else{
            $hasCorrectStructure = self::checkTableStructure();
            if(!$hasCorrectStructure){
                self::rebuildTable();
            }
        }

        self::$menuTableReady = true;
    }

    private static function checkTableStructure()
    {
        try{
            $prefix = config('database.connections.mysql.prefix');
            $tableName = $prefix . 'admin_plugin_menu';
            $fields = self::getTableColumns('admin_plugin_menu');

            // 检查必需的字段是否存在
            $requiredFields = ['id', 'aid', 'pid', 'plugin_name', 'key', 'name', 'path', 'icon', 'sort', 'status', 'createtime'];
            foreach($requiredFields as $field){
                if(!in_array($field, $fields, true)){
                    return false;
                }
            }
            
            $optionalColumns = [
                'vue_path' => "ALTER TABLE `{$tableName}` ADD COLUMN `vue_path` varchar(200) NOT NULL DEFAULT '' COMMENT 'Vue路由路径' AFTER `path`",
                'use_vue' => "ALTER TABLE `{$tableName}` ADD COLUMN `use_vue` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否使用Vue页面' AFTER `status`",
                'auth_mode' => "ALTER TABLE `{$tableName}` ADD COLUMN `auth_mode` tinyint(1) NOT NULL DEFAULT '0' COMMENT '权限模式 0自动 1手动 2无需权限' AFTER `use_vue`",
                'auth_data' => "ALTER TABLE `{$tableName}` ADD COLUMN `auth_data` varchar(500) NOT NULL DEFAULT '' COMMENT '权限标识' AFTER `auth_mode`",
                'admin_only' => "ALTER TABLE `{$tableName}` ADD COLUMN `admin_only` tinyint(1) NOT NULL DEFAULT '0' COMMENT '仅管理员可见' AFTER `auth_data`",
                'merchant_visible' => "ALTER TABLE `{$tableName}` ADD COLUMN `merchant_visible` tinyint(1) NOT NULL DEFAULT '1' COMMENT '商户可见' AFTER `admin_only`",
                'tuanzhang_visible' => "ALTER TABLE `{$tableName}` ADD COLUMN `tuanzhang_visible` tinyint(1) NOT NULL DEFAULT '0' COMMENT '团长可见' AFTER `merchant_visible`",
            ];
            foreach ($optionalColumns as $field => $alterSql) {
                if (in_array($field, $fields, true)) {
                    continue;
                }
                try{
                    Db::execute($alterSql);
                    self::rememberColumn('admin_plugin_menu', $field);
                    $fields[] = $field;
                }catch(\Exception $e){
                    // 添加失败不影响主流程
                }
            }
            
            return true;
        }catch(\Exception $e){
            return false;
        }
    }

    private static function getTableColumns($tableName)
    {
        $tableName = strval($tableName);
        if (isset(self::$tableColumns[$tableName])) {
            return self::$tableColumns[$tableName];
        }

        try{
            $prefix = config('database.connections.mysql.prefix');
            $fullTableName = $prefix . $tableName;
            $columns = Db::query("SHOW COLUMNS FROM `{$fullTableName}`");
            self::$tableColumns[$tableName] = array_values(array_filter(array_map(function ($column) {
                return strval($column['Field'] ?? $column['field'] ?? '');
            }, (array) $columns)));
        }catch(\Exception $e){
            self::$tableColumns[$tableName] = [];
        }

        return self::$tableColumns[$tableName];
    }

    private static function rememberColumn($tableName, $columnName)
    {
        $columns = self::getTableColumns($tableName);
        if (!in_array($columnName, $columns, true)) {
            $columns[] = $columnName;
            self::$tableColumns[$tableName] = $columns;
        }
    }

    private static function resetSchemaCache()
    {
        self::$tableColumns = [];
        self::$menuTableReady = false;
    }
    
    /**
     * 检查字段是否存在
     * @param string $tableName 表名（不含前缀）
     * @param string $columnName 字段名
     * @return bool
     */
    private static function columnExists($tableName, $columnName)
    {
        return in_array(strval($columnName), self::getTableColumns($tableName), true);
    }

    /**
     * 重建表（删除旧表并创建新表）
     * @return bool
     */
    private static function rebuildTable()
    {
        try{
            $prefix = config('database.connections.mysql.prefix');
            $tableName = $prefix . 'admin_plugin_menu';

            // 删除旧表
            Db::execute("DROP TABLE IF EXISTS `{$tableName}`");
            self::resetSchemaCache();

            // 创建新表
            self::createTable();

            return true;
        }catch(\Exception $e){
            throw new \Exception("重建表失败: " . $e->getMessage());
        }
    }

    /**
     * 创建插件菜单表
     * @return bool
     */
    private static function createTable()
    {
        try{
            $prefix = config('database.connections.mysql.prefix');
            $sql = "CREATE TABLE IF NOT EXISTS `{$prefix}admin_plugin_menu` (
              `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键ID',
              `aid` int(11) NOT NULL DEFAULT '0' COMMENT '商户ID',
              `pid` int(11) NOT NULL DEFAULT '0' COMMENT '父级ID',
              `plugin_name` varchar(50) NOT NULL DEFAULT '' COMMENT '插件名称',
              `key` varchar(100) NOT NULL DEFAULT '' COMMENT '菜单标识',
              `name` varchar(50) NOT NULL DEFAULT '' COMMENT '菜单名称',
              `path` varchar(100) NOT NULL DEFAULT '' COMMENT '菜单路径',
              `icon` varchar(50) NOT NULL DEFAULT '' COMMENT '图标',
              `sort` int(11) NOT NULL DEFAULT '0' COMMENT '排序',
              `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态 1启用 0禁用',
              `use_vue` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否使用Vue页面',
              `auth_mode` tinyint(1) NOT NULL DEFAULT '0' COMMENT '权限模式 0自动 1手动 2无需权限',
              `auth_data` varchar(500) NOT NULL DEFAULT '' COMMENT '权限标识',
              `admin_only` tinyint(1) NOT NULL DEFAULT '0' COMMENT '仅管理员可见',
              `merchant_visible` tinyint(1) NOT NULL DEFAULT '1' COMMENT '商户可见',
              `tuanzhang_visible` tinyint(1) NOT NULL DEFAULT '0' COMMENT '团长可见',
              `createtime` int(11) NOT NULL DEFAULT '0' COMMENT '创建时间',
              PRIMARY KEY (`id`),
              KEY `aid` (`aid`),
              KEY `plugin_name` (`plugin_name`),
              KEY `status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='插件菜单表'";

            Db::execute($sql);
            return true;
        }catch(\Exception $e){
            throw new \Exception("创建插件菜单表失败: " . $e->getMessage());
        }
    }

    /**
     * 获取或创建"插件管理"父菜单
     * @param int $aid 商户ID
     * @return int 返回父菜单ID
     */
    private static function getOrCreatePluginParentMenu($aid)
    {
        // 先从 admin_menu 表中查找"插件管理"菜单
        $parentMenu = Db::name('admin_menu')
            ->where('key', 'plugin')
            ->where('status', 1)
            ->find();

        if($parentMenu){
            return $parentMenu['id'];
        }

        // 如果不存在，创建一个"插件管理"菜单
        $menuId = Db::name('admin_menu')->insertGetId([
            'pid' => 0,
            'key' => 'plugin',
            'name' => '插件管理',
            'path' => '',
            'icon' => 'puzzle-piece',
            'sort' => 999,
            'status' => 1,
            'admin_only' => 0,
            'merchant_visible' => 1,
            'tuanzhang_visible' => 0,
            'createtime' => time()
        ]);

        // 清除缓存
        self::clearCache();

        return $menuId;
    }

    /**
     * 清除菜单缓存
     * @return void
     */
    private static function clearCache()
    {
        try{
            // 清除所有菜单相关缓存
            \think\facade\Cache::delete('admin_all_menus');  // MenuDatabase 的缓存
            \think\facade\Cache::delete('admin_menu_' . self::getAid());  // 旧菜单缓存

            // 清除用户菜单缓存（可能有多个用户）
            $prefix = config('database.connections.mysql.prefix');
            $users = Db::name('admin_user')->where('aid', self::getAid())->column('id');
            foreach($users as $uid){
                \think\facade\Cache::delete("user_menu_{$uid}_" . self::getAid());
            }
        }catch(\Exception $e){
            // 忽略缓存清除错误
        }
    }
}
