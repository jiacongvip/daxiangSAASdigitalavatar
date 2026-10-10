<?php
/**
 * 鲸犀商城 - 数据库驱动菜单系统
 * MenuDatabase - 菜单加载与过滤核心类
 * 
 * @author Augment Agent
 * @date 2025-11-18
 */

namespace app\admin\common;
use think\facade\Db;
use think\facade\Cache;

class MenuDatabase
{
    /**
     * 获取用户菜单树
     * 
     * @param int $aid 账号ID
     * @param int $uid 用户ID
     * @return array 菜单树
     */
    public static function getUserMenuTree($aid, $uid)
    {
        // 1. 获取用户信息
        $user = self::getUserInfo($aid, $uid);
        if(!$user){
            return [];
        }

        // 控制台一键进入站点后，菜单必须跟随该账号所属站点，不能继续用 admin 的 aid。
        $userAid = intval($user['aid'] ?? 0);
        if($userAid > 0){
            $aid = $userAid;
        }

        // 2. 获取所有菜单(包括插件菜单)
        $allMenus = self::getAllMenus($aid);

        // 3. 按用户类型过滤
        $menus = self::filterByUserType($allMenus, $user);
        $menus = self::applyUserMenuOverrides($menus, $user);
        
        // 4. 按权限过滤
        $menus = self::filterByPermission($menus, $user);
        
        // 5. 获取用户自定义配置
        $customConfig = self::getUserCustomConfig($uid);
        
        // 6. 应用自定义配置
        $menus = self::applyCustomConfig($menus, $customConfig);
        
        // 7. 构建树形结构
        $tree = self::buildTree($menus);
        $tree = self::pruneEmptyMenus($tree);
        $isadmin = intval($user['bid'] ?? 0) <= 0;
        $tree = Menu::unifyYingxiaoPluginMenus($tree, intval($aid), $isadmin);
        $tree = self::pruneEmptyMenus($tree);
        $tree = self::promoteBusinessMenu($tree);
        $tree = self::filterMerchantRuntimeTree($tree, $user);
        if (PluginTopMenuConfig::isHidePluginEntry(intval($aid))) {
            $tree = self::removePluginEntry($tree);
        }
        
        return $tree;
    }
    
    /**
     * 获取菜单数据（兼容 Menu::getdata() 接口）
     * 
     * @param int $aid 账号ID
     * @param int $uid 用户ID
     * @return array 菜单树，格式与 Menu::getdata() 保持一致
     */
    public static function getdata($aid = 0, $uid = 0)
    {
        return self::getUserMenuTree($aid, $uid);
    }
    
    /**
     * 获取用户信息
     */
    private static function getUserInfo($aid, $uid)
    {
        if($uid <= 0){
            return null;
        }
        
        $user = Db::name('admin_user')->where('id', $uid)->find();
        if(!$user){
            return null;
        }
        
        // 判断用户类型
        $user['user_type'] = 'admin'; // 默认平台管理员
        
        if($user['bid'] > 0){
            // 检查是否是商户
            $business = Db::name('business')->where('id', $user['bid'])->find();
            if($business){
                $user['user_type'] = 'merchant';
            }else{
                // 检查是否是团长
                $tuanzhang = Db::name('tuanzhang')->where('id', $user['bid'])->find();
                if($tuanzhang){
                    $user['user_type'] = 'tuanzhang';
                }
            }
        }
        
        // 获取权限数据
        if($user['groupid']){
            $group = Db::name('admin_user_group')->where('id', $user['groupid'])->find();
            if($group){
                $user['auth_data'] = $group['auth_data'];
                $user['auth_type'] = $group['auth_type'] ?? 0;
            }
        }
        
        // 如果是子账号，继承主账号权限
        if($user['bid'] > 0 && $user['auth_type'] == 1){
            $adminQuery = Db::name('admin_user')
                ->where('aid', $aid)
                ->where('isadmin', '>', 0);
            if(intval($user['bid']) > 0){
                $adminQuery->where('bid', intval($user['bid']));
            }
            $adminUser = $adminQuery->order('id', 'asc')->find();
            if($adminUser){
                $user['auth_type'] = $adminUser['auth_type'];
                $user['auth_data'] = $adminUser['auth_data'];
            }
        }
        
        return $user;
    }
    
    /**
     * 获取所有菜单(基础菜单+插件菜单)
     *
     * @param int $aid 商户ID，用于过滤插件菜单
     */
    private static function getAllMenus($aid = 0)
    {
        // 直接从数据库读取，不使用缓存
        // 1. 加载基础菜单
        $baseMenus = Db::name('admin_menu')
            ->where('status', 1)
            ->order('sort', 'asc')
            ->select()
            ->toArray();

        // 2. 加载插件菜单（按 aid 过滤）
        $pluginMenuId = self::findPluginParentMenuId($baseMenus);
        $pluginMenus = self::getPluginMenus($aid, $pluginMenuId);

        // 3. 合并菜单并应用站点级插件一级菜单配置
        $menus = self::mergeMenusWithPluginTopConfig($baseMenus, $pluginMenus, intval($aid));

        return $menus;
    }
    
    /**
     * 获取插件菜单
     *
     * @param int $aid 商户ID，0表示获取所有插件菜单（去重）
     * @param int $pluginMenuId plugin 菜单在 admin_menu 中的 ID
     */
    private static function getPluginMenus($aid = 0, $pluginMenuId = 0)
    {
        self::ensurePluginMenusSynced(intval($aid));

        // 从扁平化的插件菜单表中读取
        $query = Db::name('admin_plugin_menu')
            ->where('status', 1);

        // 如果指定了 aid，则过滤
        if($aid > 0){
            $query->where('aid', $aid);
        }

        $pluginMenus = $query->order('sort', 'asc')
            ->select()
            ->toArray();

        if(empty($pluginMenus)){
            return [];
        }

        // 【修复】根据插件授权表过滤菜单
        if($aid > 0 && self::tableExists('admin_plugin_license')){
            $licensedPlugins = self::getLicensedPlugins($aid);
            if(!empty($licensedPlugins)){
                $pluginMenus = array_filter($pluginMenus, function($menu) use ($licensedPlugins){
                    $pluginName = trim((string)($menu['plugin_name'] ?? ''));
                    return $pluginName === '' || in_array($pluginName, $licensedPlugins);
                });
                $pluginMenus = array_values($pluginMenus); // 重建索引
            }else{
                // 如果没有任何授权，则不显示任何插件菜单
                return [];
            }
        }

        // 如果 aid=0，需要去重（按 plugin_name + name + path 去重）
        // 策略：优先保留 pid 指向 pluginMenuId 的菜单树
        if($aid == 0){
            // 第一步：找出所有 pid 指向 pluginMenuId 的顶级插件菜单
            $topLevelMenuIds = [];
            foreach($pluginMenus as $menu){
                if($pluginMenuId > 0 && $menu['pid'] == $pluginMenuId){
                    $topLevelMenuIds[] = $menu['id'];
                }
            }

            // 第二步：去重，优先保留顶级菜单及其子菜单
            $uniqueMenus = [];
            $seen = [];
            foreach($pluginMenus as $menu){
                $key = $menu['plugin_name'] . '|' . $menu['name'] . '|' . $menu['path'];

                // 判断是否应该保留这个菜单
                $shouldKeep = false;
                if(!isset($seen[$key])){
                    // 第一次遇到这个菜单
                    $shouldKeep = true;
                } else {
                    // 已经存在同名菜单，判断优先级
                    // 如果当前菜单是顶级菜单（pid=pluginMenuId）或其子菜单，则替换
                    if($menu['pid'] == $pluginMenuId || in_array($menu['pid'], $topLevelMenuIds)){
                        $shouldKeep = true;
                        // 移除旧的
                        foreach($uniqueMenus as $idx => $existingMenu){
                            $existingKey = $existingMenu['plugin_name'] . '|' . $existingMenu['name'] . '|' . $existingMenu['path'];
                            if($existingKey == $key){
                                unset($uniqueMenus[$idx]);
                                break;
                            }
                        }
                    }
                }

                if($shouldKeep){
                    $seen[$key] = true;
                    $uniqueMenus[] = $menu;
                }
            }
            $pluginMenus = array_values($uniqueMenus);  // 重新索引
        }

        // ID 偏移量，避免与 admin_menu 表的 ID 冲突
        // 使用 100000 作为起始偏移量
        $idOffset = 100000;

        // 第一步：建立原始ID到新ID的映射
        $idMap = [];
        foreach($pluginMenus as $menu){
            $idMap[$menu['id']] = $menu['id'] + $idOffset;
        }
        $adminMenuIdSet = $pluginMenuId > 0 ? self::getAdminMenuIdSet() : [];

        // 第二步：转换为标准菜单格式
        $menus = [];
        foreach($pluginMenus as $menu){
            // 处理 pid：
            // - 如果 pid 在插件菜单中存在，使用映射后的新 ID
            // - 如果 pid 指向 admin_menu 中的 plugin 菜单（通常是 328），也使用映射后的新 ID
            // - 否则保持不变
            $originalPid = intval($menu['pid'] ?? 0);
            $newPid = $originalPid;
            if(isset($idMap[$originalPid])){
                // pid 指向另一个插件菜单
                $newPid = $idMap[$originalPid];
            } elseif($pluginMenuId > 0 && $originalPid == $pluginMenuId){
                // pid 指向 admin_menu 中的 plugin 菜单，这种情况下 pid 应该保持为 pluginMenuId
                // 这样在 buildTree 时，这些菜单会成为 plugin 菜单的子菜单
                $newPid = $pluginMenuId;
            } elseif($pluginMenuId > 0 && self::shouldAttachPluginMenuToParent($originalPid, $adminMenuIdSet)){
                // 兼容旧数据：历史插件根菜单曾挂在已不存在的 admin_menu ID 下（如 pid=6）。
                $newPid = $pluginMenuId;
            }

            // 插件菜单优先使用 plugin.json 中声明的权限串，兼容旧菜单权限口径。
            $authdata = trim((string)($menu['auth_data'] ?? ''));
            if($authdata === '' && !empty($menu['path'])){
                $authdata = $menu['path'] . '/*';
            }

            $vuePath = $menu['vue_path'] ?? '';
            $legacyVuePath = PluginTopMenuConfig::legacyAdminVuePath($menu);
            if ($legacyVuePath !== '') {
                $vuePath = $legacyVuePath;
            }

            $menus[] = [
                'id' => $menu['id'] + $idOffset,  // 加上偏移量
                'pid' => $newPid,  // 使用处理后的 pid
                'key' => $menu['key'] ?: $menu['plugin_name'],
                'name' => $menu['name'],
                'path' => $menu['path'],
                'vue_path' => $vuePath,
                'authdata' => $authdata,  // 添加 authdata 字段
                'icon' => $menu['icon'],
                'sort' => $menu['sort'],
                'status' => $menu['status'],
                'plugin_name' => $menu['plugin_name'],
                'auth_mode' => intval($menu['auth_mode'] ?? 0),
                'auth_data' => $menu['auth_data'] ?? '',
                'admin_only' => intval($menu['admin_only'] ?? 0),
                'merchant_visible' => intval($menu['merchant_visible'] ?? 1),
                'tuanzhang_visible' => intval($menu['tuanzhang_visible'] ?? 0),
                'createtime' => $menu['createtime'],
                'is_plugin' => true  // 标记为插件菜单
            ];
        }

        return $menus;
    }

    private static function ensurePluginMenusSynced(int $aid): void
    {
        if ($aid <= 0 || !self::tableExists('admin_plugin_menu')) {
            return;
        }

        self::ensureLicensedPluginMenus($aid);
        self::ensureAizhenliaoMemberQuotaMenu($aid);
    }

    /**
     * 已授权但菜单记录缺失时，按插件配置补注册一次。
     * 仅处理完全没有菜单记录的插件，避免覆盖已有菜单或运营调整。
     */
    private static function ensureLicensedPluginMenus(int $aid): void
    {
        if (!self::tableExists('admin_plugin_license')) {
            return;
        }

        try {
            $pluginNames = Db::name('admin_plugin_license')
                ->where('aid', $aid)
                ->where('status', 1)
                ->column('plugin_name');

            foreach ($pluginNames as $pluginName) {
                $pluginName = trim((string) $pluginName);
                if ($pluginName === '') {
                    continue;
                }

                $menuCount = Db::name('admin_plugin_menu')
                    ->where('aid', $aid)
                    ->where('plugin_name', $pluginName)
                    ->count();
                if ($menuCount > 0) {
                    continue;
                }

                try {
                    PluginMenu::registerFromConfig($pluginName, $aid);
                } catch (\Throwable $e) {
                    \think\facade\Log::warning('已授权插件菜单补注册失败', [
                        'aid' => $aid,
                        'plugin_name' => $pluginName,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            \think\facade\Log::warning('检查已授权插件菜单失败', [
                'aid' => $aid,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private static function ensureAizhenliaoMemberQuotaMenu(int $aid): void
    {
        try {
            $memberQuotaMenus = Db::name('admin_plugin_menu')
                ->where('aid', $aid)
                ->where('name', '会员次数管理')
                ->select()
                ->toArray();
            if (empty($memberQuotaMenus)) {
                return;
            }

            $targetPath = 'SheZhen/memberQuota';
            $targetAuthData = 'SheZhen/memberQuota,SheZhen/adjustMemberDetectTimes';

            foreach ($memberQuotaMenus as $menu) {
                $update = [];

                if (($menu['path'] ?? '') !== $targetPath) {
                    $update['path'] = $targetPath;
                }

                if (trim((string) ($menu['auth_data'] ?? '')) !== $targetAuthData) {
                    $update['auth_data'] = $targetAuthData;
                }

                if (!empty($menu['vue_path'] ?? '')) {
                    $update['vue_path'] = '';
                }

                if (!empty($update)) {
                    Db::name('admin_plugin_menu')->where('id', intval($menu['id']))->update($update);
                }
            }
        } catch (\Throwable $e) {
        }
    }
    
    /**
     * 按用户类型过滤菜单
     */
    private static function filterByUserType($menus, $user)
    {
        $filtered = [];
        $userType = $user['user_type'];
        
        foreach($menus as $menu){
            // 1. 检查是否仅限平台管理员
            if($menu['admin_only'] == 1 && $userType != 'admin'){
                continue;
            }
            
            // 2. 检查商户可见性
            if($userType == 'merchant' && $menu['merchant_visible'] == 0){
                continue;
            }
            
            // 3. 检查团长可见性
            if($userType == 'tuanzhang' && $menu['tuanzhang_visible'] == 0){
                continue;
            }
            
            // 4. 检查平台限制
            if(!empty($menu['platform_limit'])){
                $platforms = explode(',', $menu['platform_limit']);
                $currentPlatform = $user['platform'] ?? 'mp';
                if(!in_array($currentPlatform, $platforms)){
                    continue;
                }
            }
            
            $filtered[] = $menu;
        }
        
        return $filtered;
    }

    private static function applyUserMenuOverrides(array $menus, array $user): array
    {
        if(($user['user_type'] ?? '') !== 'merchant'){
            return $menus;
        }

        $result = [];
        foreach($menus as $menu){
            $path = strval($menu['path'] ?? '');

            // HTML 商家后台仍走 Backstage/sysset 的商家分支，
            // Vue 后台则优先跳转到 merchant-store-set。
            if($path === 'Backstage/sysset'){
                $menu['key'] = 'merchant_store_set';
                $menu['name'] = '店铺设置';
                $menu['fullname'] = '店铺设置';
                $menu['path'] = 'Backstage/sysset';
                $menu['vue_path'] = 'merchant-store-set';
                $menu['auth_data'] = 'Backstage/sysset';
                $menu['authdata'] = 'Backstage/sysset';
                $result[] = $menu;
                continue;
            }

            // 避免“商城”下继续出现平台级商城系统设置入口。
            if($path === 'ShopSet/index'){
                continue;
            }

            $result[] = $menu;
        }

        return $result;
    }
    
    /**
     * 按权限过滤菜单
     */
    private static function filterByPermission($menus, $user)
    {
        // 总控账号保持全量菜单。站点主账号按控制台勾选的权限显示，不能直接套用总控菜单。
        if(($user['user_type'] ?? '') === 'admin' && intval($user['isadmin'] ?? 0) >= 2){
            return $menus;
        }

        // 获取用户权限数据
        $authData = [];
        $authSelections = [];
        if(!empty($user['auth_data'])){
            $authDataRaw = json_decode($user['auth_data'], true);
            if(is_array($authDataRaw)){
                foreach($authDataRaw as $item){
                    if(is_string($item)){
                        $selection = self::normalizePermissionSelectionValue($item);
                        if($selection !== ''){
                            $authSelections[] = $selection;
                            $authData = array_merge($authData, explode(',', $selection));
                        }
                    }
                }
            }
        }
        $hasPluginEntryAccess = self::hasPluginEntryAccess(intval($user['aid'] ?? 0), $user, $authData);
        $authSelectionSet = array_flip($authSelections);
        
        // 权限类型为1表示拥有所有权限
        if($user['auth_type'] == 1){
            return $menus;
        }

        // 站点主账号：插件菜单只看插件授权，不要求在模块权限里勾选。
        $grantPluginMenusByLicense = ($user['user_type'] ?? '') === 'admin'
            && intval($user['bid'] ?? 0) <= 0
            && intval($user['isadmin'] ?? 0) > 0;
        $hasLicensedPluginMenu = false;
        if($grantPluginMenusByLicense){
            foreach($menus as $menu){
                if(!empty($menu['is_plugin'])){
                    $hasLicensedPluginMenu = true;
                    break;
                }
            }
        }
        
        $filtered = [];
        foreach($menus as $menu){
            if($grantPluginMenusByLicense && !empty($menu['is_plugin'])){
                $filtered[] = $menu;
                continue;
            }

            $menuPath = (string)($menu['path'] ?? '');
            $menuKey = (string)($menu['key'] ?? '');
            if($grantPluginMenusByLicense && $hasLicensedPluginMenu && ($menuPath === 'MyPlugin/index' || $menuKey === 'plugin')){
                $filtered[] = $menu;
                continue;
            }

            // 获取菜单权限标识
            $menuAuth = self::getMenuAuthData($menu);
            if($menuPath === 'MyPlugin/index' && $hasPluginEntryAccess){
                $filtered[] = $menu;
                continue;
            }
            
            // 无需权限的菜单（分组菜单）
            if($menuAuth === null){
                $filtered[] = $menu;
                continue;
            }

            if(self::requiresPathScopedSelection($menu, $menuAuth, $authSelectionSet)){
                if(self::hasPathScopedSelection($menu, $menuAuth, $authSelectionSet)){
                    $filtered[] = $menu;
                }
                continue;
            }
            
            // 检查权限
            if(self::checkAuthData($menuAuth, $authData)){
                $filtered[] = $menu;
            }
        }
        
        return $filtered;
    }
    
    /**
     * 获取菜单权限标识(支持自动推导)
     */
    private static function getMenuAuthData($menu)
    {
        // 1. 无需权限模式
        if(intval($menu['auth_mode'] ?? 0) == 2){
            return null;
        }

        // 2. 插件菜单优先使用已经展开好的权限串
        if(!empty($menu['is_plugin']) && !empty($menu['authdata'])){
            return $menu['authdata'];
        }

        // 3. 数据库菜单如果已经显式保存 auth_data，则优先使用它。
        // 旧版菜单定义和权限分配页都是按 auth_data 生效的，不能在运行时再被 path 自动推导覆盖。
        if(!empty($menu['auth_data'])){
            return $menu['auth_data'];
        }
        
        // 4. 自动推导模式
        if(empty($menu['path'])){
            return null; // 分组菜单,无需权限
        }
        
        // 从路径推导权限
        $path = trim($menu['path'], '/');
        $parts = explode('/', $path);
        
        if(count($parts) >= 2){
            // 取前两级作为模块
            // Member/index → Member/*
            // System/Config/index → System/Config/*
            $module = $parts[0];
            if(count($parts) > 2 && $parts[0] == 'plugin'){
                // plugin/sign/Sign/index → plugin/sign/Sign/*
                return $parts[0] . '/' . $parts[1] . '/' . $parts[2] . '/*';
            }else{
                return $module . '/*';
            }
        }
        
        return null;
    }

    private static function requiresPathScopedSelection(array $menu, $menuAuth, array $authSelectionSet): bool
    {
        if(empty($authSelectionSet)){
            return false;
        }

        $path = trim((string)($menu['path'] ?? ''));
        if($path === '' || $menuAuth === null){
            return false;
        }

        $authKey = self::normalizePermissionSelectionValue($menuAuth);
        return $authKey !== '';
    }

    private static function hasPathScopedSelection(array $menu, $menuAuth, array $authSelectionSet): bool
    {
        $path = trim((string)($menu['path'] ?? ''));
        $authKey = self::normalizePermissionSelectionValue($menuAuth);
        if($path === '' || $authKey === ''){
            return false;
        }

        if(isset($authSelectionSet[$authKey])){
            return true;
        }

        $pathPrefix = self::normalizePermissionSelectionValue($path . ',');
        foreach($authSelectionSet as $selection => $_){
            if(strpos((string)$selection, $pathPrefix) === 0){
                return true;
            }
        }

        foreach(explode(',', $authKey) as $authPart){
            $authPart = trim((string)$authPart);
            if($authPart !== '' && isset($authSelectionSet[$authPart])){
                return true;
            }
        }

        $selection = self::normalizePermissionSelectionValue($path . ',' . $authKey);
        return isset($authSelectionSet[$selection]);
    }

    private static function hasPluginEntryAccess(int $aid, array $user = [], array $userAuthData = []): bool
    {
        if($aid <= 0){
            return false;
        }

        if(intval($user['bid'] ?? 0) > 0 && self::hasActiveBusinessPluginAuth($aid, intval($user['bid']))){
            return true;
        }

        return self::hasPluginMenuAuthInData($aid, $userAuthData);
    }

    private static function hasActiveBusinessPluginAuth(int $aid, int $bid): bool
    {
        if($aid <= 0 || $bid <= 0 || !class_exists('\\app\\admin\\common\\PluginBusinessAuth') || !self::tableExists('plugin_business_auth')){
            return false;
        }

        try{
            $rows = Db::name('plugin_business_auth')
                ->where('aid', $aid)
                ->where('bid', $bid)
                ->where('status', 1)
                ->where(function($query){
                    $query->where('expire_time', 0)->whereOr('expire_time', '>', time());
                })
                ->column('plugin_key');
        }catch(\Throwable $e){
            return false;
        }

        foreach($rows as $pluginKey){
            if(PluginBusinessAuth::isBusinessPluginEnabled($aid, $bid, strval($pluginKey))){
                return true;
            }
        }

        return false;
    }

    private static function hasPluginMenuAuthInData(int $aid, array $userAuthData = []): bool
    {
        if($aid <= 0 || empty($userAuthData) || !self::tableExists('admin_plugin_menu')){
            return false;
        }

        $normalizedAuthData = [];
        foreach($userAuthData as $item){
            $item = trim(strval($item));
            if($item !== ''){
                $normalizedAuthData[] = $item;
            }
        }
        if(empty($normalizedAuthData)){
            return false;
        }

        try{
            $rows = Db::name('admin_plugin_menu')
                ->where('aid', $aid)
                ->where('status', 1)
                ->where('path', '<>', '')
                ->field('path,auth_data')
                ->select()
                ->toArray();
        }catch(\Throwable $e){
            return false;
        }

        foreach($rows as $row){
            $path = trim(strval($row['path'] ?? ''));
            if($path === ''){
                continue;
            }
            $authData = trim(strval($row['auth_data'] ?? ''));
            $menuAuth = $authData !== '' ? $authData : $path . '/*';
            if(self::checkAuthData($menuAuth, $normalizedAuthData)){
                return true;
            }
        }

        return false;
    }

    private static function tableExists(string $tableName): bool
    {
        try{
            $prefix = config('database.connections.mysql.prefix');
            $fullTableName = $prefix . $tableName;
            return !empty(Db::query("SHOW TABLES LIKE '{$fullTableName}'"));
        }catch(\Throwable $e){
            return false;
        }
    }

    /**
     * 获取站点已授权的插件列表
     *
     * @param int $aid 站点ID
     * @return array 已授权且启用的插件名称数组
     */
    private static function getLicensedPlugins(int $aid): array
    {
        if($aid <= 0){
            return [];
        }

        try{
            $licenses = Db::name('admin_plugin_license')
                ->where('aid', $aid)
                ->where('status', 1)
                ->column('plugin_name');

            // 过滤并规范化插件名称
            $result = [];
            foreach($licenses as $pluginName){
                $pluginName = trim((string)$pluginName);
                if($pluginName !== ''){
                    $result[] = $pluginName;
                }
            }

            return array_values(array_unique($result));
        }catch(\Throwable $e){
            return [];
        }
    }

    private static function getAdminMenuIdSet(): array
    {
        try{
            $ids = Db::name('admin_menu')->column('id');
        }catch(\Throwable $e){
            return [];
        }

        $set = [];
        foreach($ids as $id){
            $set[intval($id)] = true;
        }

        return $set;
    }

    private static function shouldAttachPluginMenuToParent(int $pid, array $adminMenuIdSet): bool
    {
        if($pid <= 0){
            return true;
        }

        return !isset($adminMenuIdSet[$pid]);
    }

    private static function normalizePermissionSelectionValue($value): string
    {
        $value = trim((string)$value);
        if($value === ''){
            return '';
        }

        return str_replace('^_^', '/*', $value);
    }
    
    /**
     * 检查权限
     */
    private static function checkAuthData($menuAuth, $userAuthData)
    {
        if(empty($menuAuth)){
            return true;
        }

        $menuAuthList = array_values(array_filter(array_map(function($item){
            return trim(str_replace('^_^', '/*', strval($item)));
        }, explode(',', strval($menuAuth)))));

        $userAuthList = array_values(array_filter(array_map(function($item){
            return trim(str_replace('^_^', '/*', strval($item)));
        }, $userAuthData)));

        foreach($menuAuthList as $menuAuthItem){
            foreach($userAuthList as $auth){
                if($auth === $menuAuthItem){
                    return true;
                }

                if(strpos($auth, '*') !== false){
                    $pattern = str_replace('*', '.*', preg_quote($auth, '/'));
                    if(preg_match('/^' . $pattern . '$/', $menuAuthItem)){
                        return true;
                    }
                }
            }
        }

        return false;
    }
    
    /**
     * 获取用户自定义配置
     */
    private static function getUserCustomConfig($uid)
    {
        $configs = Db::name('admin_user_menu_custom')
            ->where('uid', $uid)
            ->select()
            ->toArray();
        
        $customMap = [];
        foreach($configs as $config){
            $customMap[$config['menu_id']] = $config;
        }
        
        return $customMap;
    }
    
    /**
     * 应用用户自定义配置
     */
    private static function applyCustomConfig($menus, $customConfig)
    {
        if(empty($customConfig)){
            return $menus;
        }
        
        $result = [];
        foreach($menus as $menu){
            $menuId = $menu['id'];
            
            // 检查是否隐藏
            if(isset($customConfig[$menuId]) && $customConfig[$menuId]['is_hidden'] == 1){
                continue;
            }
            
            // 应用自定义配置
            if(isset($customConfig[$menuId])){
                $custom = $customConfig[$menuId];
                
                if($custom['custom_pid'] !== null){
                    $menu['pid'] = $custom['custom_pid'];
                }
                if($custom['custom_level'] !== null){
                    $menu['level'] = $custom['custom_level'];
                }
                if($custom['custom_sort'] !== null){
                    $menu['sort'] = $custom['custom_sort'];
                }
                if($custom['custom_name'] !== null){
                    $menu['name'] = $custom['custom_name'];
                }
            }
            
            $result[] = $menu;
        }
        
        return $result;
    }
    
    /**
     * 构建树形结构
     */
    private static function buildTree($menus, $parentId = 0)
    {
        $tree = [];
        
        foreach($menus as $menu){
            if($menu['pid'] == $parentId){
                $children = self::buildTree($menus, $menu['id']);
                if(!empty($children)){
                    // ✅ 使用 'child' 而不是 'children'，兼容原有视图
                    $menu['child'] = $children;
                    // 同时保留 'children' 用于管理页面
                    $menu['children'] = $children;
                }
                
                // ✅ 字段兼容：is_hidden -> hide
                if(!isset($menu['hide'])){
                    $menu['hide'] = isset($menu['is_hidden']) ? (bool)$menu['is_hidden'] : false;
                }
                
                $tree[] = $menu;
            }
        }
        
        // 按sort排序
        usort($tree, function($a, $b){
            return ($a['sort'] ?? 999) - ($b['sort'] ?? 999);
        });

        return $tree;
    }

    /**
     * 清理无路径且无子节点的空分组，避免出现空一级菜单。
     */
    private static function pruneEmptyMenus(array $tree): array
    {
        $result = [];

        foreach ($tree as $menu) {
            if (!empty($menu['child']) && is_array($menu['child'])) {
                $menu['child'] = self::pruneEmptyMenus($menu['child']);
                $menu['children'] = $menu['child'];
            }

            $hasChildren = !empty($menu['child']);
            $hasPath = !empty($menu['path']) || !empty($menu['vue_path']);
            if (!$hasChildren && !$hasPath) {
                continue;
            }

            $result[] = $menu;
        }

        return $result;
    }

    /**
     * 将“扩展 -> 商户”提升为一级菜单，兼容传统后台菜单展示。
     */
    private static function promoteBusinessMenu(array $menus): array
    {
        foreach($menus as $menu){
            if(self::isBusinessMenuGroup($menu) && !self::menuLooksLikeExtension($menu)){
                return $menus;
            }
        }

        $promotedMenu = null;
        $insertIndex = null;

        foreach($menus as $topIndex => $topMenu){
            $children = $topMenu['child'] ?? [];
            if(!is_array($children) || empty($children)){
                continue;
            }

            $nextChildren = [];
            $removed = false;

            foreach($children as $child){
                if($promotedMenu === null && self::isBusinessMenuGroup($child)){
                    $promotedMenu = $child;
                    $promotedMenu['name'] = '多商户';
                    $promotedMenu['fullname'] = $promotedMenu['fullname'] ?? '多商户管理';
                    $promotedMenu['icon'] = $promotedMenu['icon'] ?? 'my-icon my-icon-business';
                    $promotedMenu['child'] = array_values($promotedMenu['child'] ?? []);
                    $promotedMenu['children'] = $promotedMenu['child'];
                    $removed = true;
                    continue;
                }

                $nextChildren[] = $child;
            }

            if($removed){
                $menus[$topIndex]['child'] = array_values($nextChildren);
                $menus[$topIndex]['children'] = $menus[$topIndex]['child'];
                $insertIndex = $topIndex;
                break;
            }
        }

        if($promotedMenu === null){
            return $menus;
        }

        $result = [];
        $inserted = false;
        foreach($menus as $index => $menu){
            if(!$inserted && $insertIndex !== null && $index === $insertIndex){
                $result[] = $promotedMenu;
                $inserted = true;
            }
            $result[] = $menu;
        }

        if(!$inserted){
            $result[] = $promotedMenu;
        }

        return $result;
    }

    private static function filterMerchantRuntimeTree(array $menus, array $user = []): array
    {
        if (($user['user_type'] ?? '') !== 'merchant') {
            return $menus;
        }

        $allowedTopKeys = ['shop', 'finance', 'yingxiao', 'restaurant', 'hotel', 'system'];
        $result = [];

        foreach ($menus as $menu) {
            if (!is_array($menu)) {
                continue;
            }

            if (self::isBusinessMenuGroup($menu)) {
                continue;
            }

            $key = trim((string)($menu['key'] ?? ''));
            $isPluginMenu = $key === 'plugin' || strpos($key, 'plugin_') === 0;

            if (!$isPluginMenu && !in_array($key, $allowedTopKeys, true)) {
                continue;
            }

            $menu = self::filterMerchantUnsafeMenuNode($menu);
            if ($menu === null) {
                continue;
            }

            $hasChildren = !empty($menu['child']) && is_array($menu['child']);
            $hasPath = !empty($menu['path']) || !empty($menu['vue_path']);
            if (!$hasChildren && !$hasPath) {
                continue;
            }

            $result[] = $menu;
        }

        return $result;
    }

    private static function filterMerchantUnsafeMenuNode(array $menu): ?array
    {
        $path = trim((string)($menu['path'] ?? ''));
        if (in_array($path, self::getMerchantUnsafeMenuPaths(), true)) {
            return null;
        }

        if (!empty($menu['child']) && is_array($menu['child'])) {
            $children = [];
            foreach ($menu['child'] as $child) {
                if (!is_array($child)) {
                    continue;
                }

                $child = self::filterMerchantUnsafeMenuNode($child);
                if ($child !== null) {
                    $children[] = $child;
                }
            }
            $menu['child'] = $children;
            $menu['children'] = $children;
        }

        return $menu;
    }

    private static function getMerchantUnsafeMenuPaths(): array
    {
        return [
            'Business/sysset',
            'ShopSet/index',
        ];
    }

    private static function isBusinessMenuGroup(array $menu): bool
    {
        $children = $menu['child'] ?? [];
        if(!is_array($children) || empty($children)){
            return false;
        }

        $name = trim((string)($menu['name'] ?? ''));
        if(in_array($name, ['商户', '多商户', '商户管理'], true)){
            return true;
        }

        foreach($children as $child){
            $path = trim((string)($child['path'] ?? ''));
            if(
                $path === 'Business/index'
                || $path === 'Business/category'
                || $path === 'Business/feilv'
                || $path === 'Business/sysset'
                || $path === 'BusinessPoster/index'
                || $path === 'BusinessSplitRecords/index'
                || $path === 'MerchantBalanceQuery/index'
            ){
                return true;
            }
        }

        return false;
    }

    private static function menuLooksLikeExtension(array $menu): bool
    {
        $key = trim((string)($menu['key'] ?? ''));
        $name = trim((string)($menu['name'] ?? ''));

        return in_array($key, ['component', 'kuozhan'], true)
            || in_array($name, ['扩展', '拓展'], true);
    }
    
    /**
     * 清除菜单缓存
     *
     * @param int $aid 商户ID，为0则清除所有
     */
    public static function clearCache($aid = 0)
    {
        if($aid > 0){
            // 清除指定商户的缓存
            Cache::delete('admin_all_menus_' . $aid);
        }else{
            // 清除所有缓存（使用通配符）
            Cache::delete('admin_all_menus');
            // 也清除带 aid 的缓存（尝试清除常见的 aid）
            for($i = 1; $i <= 100; $i++){
                Cache::delete('admin_all_menus_' . $i);
            }
        }
    }
    
    /**
     * 获取菜单列表(用于管理后台)
     *
     * @param array $params 查询参数
     * @param int $aid 商户ID，用于获取插件菜单
     */
    public static function getMenuList($params = [], $aid = 0)
    {
        // 1. 获取基础菜单
        $query = Db::name('admin_menu');

        // 搜索条件
        if(!empty($params['keyword'])){
            $query->whereLike('name', '%' . $params['keyword'] . '%');
        }

        if(isset($params['status']) && $params['status'] !== ''){
            $query->where('status', $params['status']);
        }

        if(isset($params['admin_only']) && $params['admin_only'] !== ''){
            $query->where('admin_only', $params['admin_only']);
        }

        if(!empty($params['plugin_name'])){
            $query->where('plugin_name', $params['plugin_name']);
        }

        $baseMenus = $query->order('sort', 'asc')->select()->toArray();

        // 2. 查找 plugin 菜单的 ID
        $pluginMenuId = 0;
        foreach($baseMenus as $menu){
            if(isset($menu['key']) && $menu['key'] == 'plugin'){
                $pluginMenuId = $menu['id'];
                break;
            }
        }

        // 3. 获取插件菜单（传递 pluginMenuId）
        $pluginMenus = self::getPluginMenus($aid, $pluginMenuId);

        // 4. 合并菜单并应用站点级插件一级菜单配置
        $allMenus = self::mergeMenusWithPluginTopConfig($baseMenus, $pluginMenus, intval($aid));

        // 5. 构建树形结构
        return self::pruneEmptyMenus(self::buildTree($allMenus));
    }

    /**
     * 获取用于权限设置的菜单数据
     * 返回格式兼容旧的 Menu::getdata() 格式，用于用户权限设置页面
     *
     * @param int $aid 商户ID（权限设置页面只显示当前站点可用插件，且不应用一级菜单提升配置）
     * @return array 菜单数组，格式：['key' => ['name'=>'', 'child'=>[...]]]
     */
    public static function getMenuForPermission($aid, bool $includePluginMenus = true)
    {
        try{
            // 1. 获取基础菜单
            $baseMenus = Db::name('admin_menu')
                ->where('status', 1)
                ->order('sort', 'asc')
                ->select()
                ->toArray();

            // 2. 查找 plugin 菜单的 ID（只有存在 plugin 菜单时才加载插件菜单）
            $pluginMenuId = 0;
            foreach($baseMenus as $menu){
                if(isset($menu['key']) && $menu['key'] == 'plugin'){
                    $pluginMenuId = $menu['id'];
                    break;
                }
            }

            // 3. 只有存在 plugin 菜单时才获取当前站点的插件菜单
            $pluginMenus = [];
            if($includePluginMenus && $pluginMenuId > 0){
                $pluginMenus = self::getPluginMenus(intval($aid), $pluginMenuId);
            }

            // 4. 权限设置页面不应用站点级插件一级菜单提升配置，避免权限树被展示层配置污染
            $allMenus = array_merge($baseMenus, $pluginMenus);

            // 调试：记录插件菜单数量
            \think\facade\Log::write('getMenuForPermission - aid=' . $aid . ', 总菜单数=' . count($allMenus) . ', 插件菜单数=' . count($pluginMenus), 'debug');

            // 过滤出启用的菜单
            $enabledMenus = array_filter($allMenus, function($menu){
                return isset($menu['status']) && $menu['status'] == 1;
            });

            // 构建树形结构
            $tree = self::pruneEmptyMenus(self::buildTree($enabledMenus));
            $tree = Menu::unifyYingxiaoPluginMenus($tree, intval($aid), true);

            return self::formatPermissionTree($tree);

        }catch(\Exception $e){
            // 出错时返回空数组
            return [];
        }
    }

    public static function getScopedMenuForPermission($aid, $uid, bool $includePluginMenus = true)
    {
        try{
            $user = self::getUserInfo(intval($aid), intval($uid));
            if(!$user){
                return [];
            }

            $baseMenus = Db::name('admin_menu')
                ->where('status', 1)
                ->order('sort', 'asc')
                ->select()
                ->toArray();

            $pluginMenuId = 0;
            foreach($baseMenus as $menu){
                if(isset($menu['key']) && $menu['key'] == 'plugin'){
                    $pluginMenuId = $menu['id'];
                    break;
                }
            }

            $pluginMenus = [];
            if($includePluginMenus && $pluginMenuId > 0){
                $pluginMenus = self::getPluginMenus(intval($aid), $pluginMenuId);
            }

            $allMenus = self::mergeMenusWithPluginTopConfig($baseMenus, $pluginMenus, intval($aid));
            $menus = self::filterByUserType($allMenus, $user);
            $menus = self::applyUserMenuOverrides($menus, $user);
            $menus = self::filterByPermission($menus, $user);
            $enabledMenus = array_filter($menus, function($menu){
                return isset($menu['status']) && $menu['status'] == 1;
            });
            $tree = self::pruneEmptyMenus(self::buildTree($enabledMenus));
            $isadmin = intval($user['bid'] ?? 0) <= 0;
            $tree = Menu::unifyYingxiaoPluginMenus($tree, intval($aid), $isadmin);

            return self::formatPermissionTree($tree);
        }catch(\Exception $e){
            return [];
        }
    }

    public static function getMerchantMenuForPermission($aid, bool $includePluginMenus = true)
    {
        try{
            $baseMenus = Db::name('admin_menu')
                ->where('status', 1)
                ->order('sort', 'asc')
                ->select()
                ->toArray();

            $pluginMenuId = 0;
            foreach($baseMenus as $menu){
                if(isset($menu['key']) && $menu['key'] == 'plugin'){
                    $pluginMenuId = $menu['id'];
                    break;
                }
            }

            $pluginMenus = [];
            if($includePluginMenus && $pluginMenuId > 0){
                $pluginMenus = self::getPluginMenus(intval($aid), $pluginMenuId);
            }

            $allMenus = self::mergeMenusWithPluginTopConfig($baseMenus, $pluginMenus, intval($aid));
            $merchantMenus = self::filterByUserType($allMenus, [
                'user_type' => 'merchant',
            ]);
            $merchantMenus = self::applyUserMenuOverrides($merchantMenus, [
                'user_type' => 'merchant',
            ]);
            $enabledMenus = array_filter($merchantMenus, function($menu){
                return isset($menu['status']) && $menu['status'] == 1;
            });
            $tree = self::pruneEmptyMenus(self::buildTree($enabledMenus));
            $tree = Menu::unifyYingxiaoPluginMenus($tree, intval($aid), false);

            return self::formatPermissionTree($tree);

        }catch(\Exception $e){
            // 出错时返回空数组
            return [];
        }
    }

    private static function formatPermissionTree(array $tree): array
    {
        $result = [];
        foreach($tree as $menu){
            $key = $menu['key'] ?? 'menu_' . $menu['id'];

            $result[$key] = self::formatPermissionMenuNode($menu);
        }

        return $result;
    }

    private static function formatPermissionMenuNode(array $menu): array
    {
        $menuPath = $menu['path'] ?? '';
        $menuAuthdata = $menu['authdata'] ?? $menu['auth_data'] ?? '';
        if(empty($menuAuthdata) && !empty($menuPath)){
            $menuAuthdata = explode('/', $menuPath)[0] . '/*';
        }

        $item = [
            'name' => $menu['name'] ?? '',
            'fullname' => $menu['fullname'] ?? ($menu['name'] ?? ''),
            'icon' => $menu['icon'] ?? '',
            'path' => $menuPath,
            'authdata' => $menuAuthdata,
            'child' => []
        ];

        if(!empty($menu['child']) && is_array($menu['child'])){
            foreach($menu['child'] as $child){
                if(is_array($child)){
                    $item['child'][] = self::formatPermissionMenuNode($child);
                }
            }
        }

        return $item;
    }

    private static function mergeMenusWithPluginTopConfig(array $baseMenus, array $pluginMenus, int $aid): array
    {
        if (empty($pluginMenus)) {
            return $baseMenus;
        }

        $allMenus = array_merge($baseMenus, $pluginMenus);
        if ($aid <= 0) {
            return $allMenus;
        }

        $pluginMenuId = self::findPluginParentMenuId($baseMenus);
        if ($pluginMenuId <= 0) {
            return $allMenus;
        }

        $configMap = PluginTopMenuConfig::getAdjustMap($aid);
        if (empty($configMap)) {
            return $allMenus;
        }

        return self::applyPluginTopConfig($allMenus, $pluginMenuId, $configMap);
    }

    private static function removePluginEntry(array $tree): array
    {
        $result = [];
        foreach ($tree as $menu) {
            if (!is_array($menu)) {
                continue;
            }
            $menuKey = (string)($menu['key'] ?? '');
            $path = (string)($menu['path'] ?? '');
            if ($menuKey === 'plugin' || $path === 'MyPlugin/index') {
                continue;
            }
            $result[] = $menu;
        }

        return $result;
    }

    private static function findPluginParentMenuId(array $baseMenus): int
    {
        foreach ($baseMenus as $menu) {
            if (($menu['key'] ?? '') === 'plugin') {
                return intval($menu['id'] ?? 0);
            }
        }

        return 0;
    }

    private static function applyPluginTopConfig(array $menus, int $pluginMenuId, array $configMap): array
    {
        $menus = self::liftPromotedPluginItems($menus, $pluginMenuId, $configMap);

        $pluginRoots = [];
        foreach ($menus as $index => $menu) {
            if (empty($menu['is_plugin']) || empty($menu['plugin_name'])) {
                continue;
            }

            if (intval($menu['pid'] ?? 0) !== $pluginMenuId) {
                continue;
            }

            $pluginName = trim((string)$menu['plugin_name']);
            if ($pluginName === '') {
                continue;
            }

            if (!isset($pluginRoots[$pluginName])) {
                $pluginRoots[$pluginName] = [];
            }
            $pluginRoots[$pluginName][] = $index;
        }

        if (empty($pluginRoots)) {
            return $menus;
        }

        $virtualId = 500000000;
        foreach ($configMap as $pluginName => $config) {
            if (intval($config['status'] ?? 0) !== 1) {
                continue;
            }
            $rootIndexes = $pluginRoots[$pluginName] ?? [];
            if (empty($rootIndexes)) {
                continue;
            }

            $customName = trim((string)($config['custom_name'] ?? ''));
            $customIcon = trim((string)($config['custom_icon'] ?? ''));
            $customSort = intval($config['sort'] ?? 0);

            if (count($rootIndexes) === 1) {
                $rootIndex = $rootIndexes[0];
                $menus[$rootIndex]['pid'] = 0;
                $menus[$rootIndex]['sort'] = $customSort;
                if ($customName !== '') {
                    $menus[$rootIndex]['name'] = $customName;
                    $menus[$rootIndex]['fullname'] = $customName;
                }
                if ($customIcon !== '') {
                    $menus[$rootIndex]['icon'] = $customIcon;
                }
                continue;
            }

            $firstRoot = $menus[$rootIndexes[0]];
            $displayName = $customName !== ''
                ? $customName
                : PluginTopMenuConfig::resolvePluginTitle($pluginName, trim((string)($firstRoot['name'] ?? $pluginName)));
            $displayIcon = $customIcon !== ''
                ? $customIcon
                : trim((string)($firstRoot['icon'] ?? ''));

            $menus[] = [
                'id' => $virtualId,
                'pid' => 0,
                'key' => 'plugin_top_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $pluginName),
                'name' => $displayName,
                'fullname' => $displayName,
                'path' => '',
                'vue_path' => '',
                'auth_data' => '',
                'authdata' => '',
                'auth_mode' => 2,
                'icon' => $displayIcon,
                'sort' => $customSort,
                'status' => 1,
                'plugin_name' => $pluginName,
                'admin_only' => intval($firstRoot['admin_only'] ?? 0),
                'merchant_visible' => intval($firstRoot['merchant_visible'] ?? 1),
                'tuanzhang_visible' => intval($firstRoot['tuanzhang_visible'] ?? 0),
                'platform_limit' => (string)($firstRoot['platform_limit'] ?? ''),
                'createtime' => time(),
                'is_plugin' => true,
                'is_plugin_virtual' => true,
            ];

            foreach ($rootIndexes as $rootIndex) {
                $menus[$rootIndex]['pid'] = $virtualId;
            }

            $virtualId++;
        }

        return $menus;
    }

    /**
     * 把勾选的插件菜单项提到主菜单。整插件已提升时，插件根节点仍由整插件逻辑处理。
     */
    private static function liftPromotedPluginItems(array $menus, int $pluginMenuId, array $configMap): array
    {
        $childrenOf = [];
        foreach ($menus as $index => $menu) {
            $pid = intval($menu['pid'] ?? 0);
            $childrenOf[$pid][] = $index;
        }

        foreach ($configMap as $pluginName => $config) {
            $promoteItems = $config['promote_items'] ?? [];
            if (!is_array($promoteItems) || $promoteItems === []) {
                continue;
            }

            $keySet = [];
            foreach ($promoteItems as $itemKey) {
                $itemKey = trim((string)$itemKey);
                if ($itemKey !== '') {
                    $keySet[$itemKey] = true;
                }
            }
            if ($keySet === []) {
                continue;
            }

            $wholeEnabled = intval($config['status'] ?? 0) === 1;
            $baseSort = intval($config['sort'] ?? 0);
            $promoteIcons = is_array($config['promote_icons'] ?? null) ? $config['promote_icons'] : [];
            $roots = [];
            foreach ($menus as $index => $menu) {
                if (empty($menu['is_plugin']) || !empty($menu['is_plugin_virtual'])) {
                    continue;
                }
                if (trim((string)($menu['plugin_name'] ?? '')) !== $pluginName) {
                    continue;
                }
                if (intval($menu['pid'] ?? 0) !== $pluginMenuId) {
                    continue;
                }
                $roots[] = $index;
            }

            $openVisibility = [
                'admin_only' => 0,
                'merchant_visible' => 1,
                'tuanzhang_visible' => 1,
                'platform_limit' => '',
            ];
            $walk = function (int $index, string $parentKey, bool $isRoot, array $inherited) use (&$walk, &$menus, $childrenOf, $keySet, $wholeEnabled, $baseSort, $pluginName, $promoteIcons) {
                $itemKey = PluginTopMenuConfig::buildMenuItemKey($menus[$index], $parentKey);
                $effective = self::mergePromotedVisibility($inherited, $menus[$index]);
                $id = intval($menus[$index]['id'] ?? 0);
                foreach ($childrenOf[$id] ?? [] as $childIndex) {
                    $walk($childIndex, $itemKey, false, $effective);
                }
                if (!isset($keySet[$itemKey]) || ($wholeEnabled && $isRoot)) {
                    return;
                }
                $menus[$index]['admin_only'] = $effective['admin_only'];
                $menus[$index]['merchant_visible'] = $effective['merchant_visible'];
                $menus[$index]['tuanzhang_visible'] = $effective['tuanzhang_visible'];
                if ($effective['platform_limit'] !== '' && trim((string)($menus[$index]['platform_limit'] ?? '')) === '') {
                    $menus[$index]['platform_limit'] = $effective['platform_limit'];
                }
                $itemSort = intval($menus[$index]['sort'] ?? 0);
                $anchor = $baseSort > 0 ? $baseSort : 900000;
                $menus[$index]['sort'] = $anchor * 1000 + max(0, $itemSort);
                $customIcon = trim((string)($promoteIcons[$itemKey] ?? ''));
                if ($customIcon !== '') {
                    $menus[$index]['icon'] = $customIcon;
                }
                $menus[$index]['pid'] = 0;
                self::assignPromotedMenuKey($menus, $index, $pluginName, $childrenOf);
            };

            foreach ($roots as $rootIndex) {
                $walk($rootIndex, '', true, $openVisibility);
            }
        }

        return $menus;
    }

    private static function mergePromotedVisibility(array $inherited, array $menu): array
    {
        $platformLimit = trim((string)($inherited['platform_limit'] ?? ''));
        if ($platformLimit === '') {
            $platformLimit = trim((string)($menu['platform_limit'] ?? ''));
        }

        return [
            'admin_only' => (intval($inherited['admin_only'] ?? 0) === 1 || intval($menu['admin_only'] ?? 0) === 1) ? 1 : 0,
            'merchant_visible' => (intval($inherited['merchant_visible'] ?? 1) === 1 && intval($menu['merchant_visible'] ?? 1) === 1) ? 1 : 0,
            'tuanzhang_visible' => (intval($inherited['tuanzhang_visible'] ?? 1) === 1 && intval($menu['tuanzhang_visible'] ?? 0) === 1) ? 1 : 0,
            'platform_limit' => $platformLimit,
        ];
    }

    private static function assignPromotedMenuKey(array &$menus, int $index, string $pluginName, array $childrenOf): void
    {
        $currentKey = trim((string)($menus[$index]['key'] ?? ''));
        if ($currentKey === '' || $currentKey === $pluginName) {
            $menus[$index]['key'] = 'plugin_item_' . intval($menus[$index]['id'] ?? $index);
        }

        $id = intval($menus[$index]['id'] ?? 0);
        foreach ($childrenOf[$id] ?? [] as $childIndex) {
            self::assignPromotedMenuKey($menus, $childIndex, $pluginName, $childrenOf);
        }
    }
}
