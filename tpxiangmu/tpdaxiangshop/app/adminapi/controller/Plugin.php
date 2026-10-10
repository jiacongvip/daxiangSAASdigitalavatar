<?php
/**
 * 站点后台 - 插件管理 API
 * 用于站点管理员管理已授权的插件
 */

namespace app\adminapi\controller;

use app\admin\common\PluginCategoryRegistry;
use app\admin\common\PluginMenu;
use think\facade\Db;

class Plugin extends Common
{
    private const MENU_AUTO_SYNC_PLUGIN_NAMES = ['mendiangroupbuy', 'carouselpay', 'aizhenliao', 'peisong', 'restaurant', 'lipin2', 'timedlottery', 'jushuitan', 'digitalavatar'];

    /**
     * 获取已授权的插件列表
     * 只显示控制台授权给当前站点的插件；
     * 子账号（auth_type=0）再按自身权限二次过滤，避免看到未分配的插件
     */
    public function index()
    {
        try {
            // 扫描所有插件
            $plugins = $this->scanPlugins();

            $authorizedPlugins = $this->getAuthorizedPluginMap();
            $allowedPluginNames = $this->getCurrentUserAllowedPluginNames();

            // 过滤：只显示已授权的插件，并附加授权信息
            $result = [];
            foreach ($plugins as $plugin) {
                $pluginName = $plugin['name'];
                if (isset($authorizedPlugins[$pluginName])) {
                    if (is_array($allowedPluginNames) && !isset($allowedPluginNames[$pluginName])) {
                        continue;
                    }

                    $license = $authorizedPlugins[$pluginName];
                    
                    $plugin['installed'] = 1;
                    $plugin['status'] = $license['status'] ?? 1;
                    $plugin['license_type'] = $license['license_type'] ?? 0;
                    $plugin['license_type_text'] = $this->getLicenseTypeText($license['license_type'] ?? 0);
                    $plugin['expire_time'] = $license['expire_time'] ?? 0;
                    $plugin['createtime'] = $license['createtime'] ?? time();
                    $plugin['remark'] = $license['remark'] ?? '';
                    
                    // 计算是否过期
                    if ($plugin['expire_time'] > 0) {
                        $plugin['is_expired'] = $plugin['expire_time'] < time();
                        $plugin['expire_days'] = max(0, ceil(($plugin['expire_time'] - time()) / 86400));
                    } else {
                        $plugin['is_expired'] = false;
                        $plugin['expire_days'] = -1; // -1 表示永久
                    }
                    
                    // 状态文本
                    if ($plugin['is_expired']) {
                        $plugin['status_text'] = '已过期';
                    } else if ($plugin['status'] == 1) {
                        $plugin['status_text'] = '正常';
                    } else {
                        $plugin['status_text'] = '已禁用';
                    }
                    
                    $result[] = $plugin;
                }
            }

            return $this->success([
                'list' => $result,
                'total' => count($result)
            ], '获取成功');

        } catch (\Exception $e) {
            return $this->error('获取失败：' . $e->getMessage());
        }
    }

    /**
     * 启用/禁用插件
     * 站点管理员只能启用或禁用已授权的插件，不能安装或卸载
     */
    public function toggleStatus()
    {
        $pluginName = input('post.plugin_name', '');
        $status = input('post.status', 1);
        $status = intval($status) === 1 ? 1 : 0;

        if (empty($pluginName)) {
            return $this->error('插件名称不能为空');
        }

        try {
            if (!$this->canCurrentUserAccessPlugin($pluginName)) {
                return $this->error('无权限操作该插件');
            }

            if ($this->checkTableExists('admin_plugin_license')) {
                $license = Db::name('admin_plugin_license')
                    ->where('aid', $this->aid)
                    ->where('plugin_name', $pluginName)
                    ->find();
                if (!$license) {
                    return $this->error('插件未授权，请联系平台管理员');
                }
                Db::name('admin_plugin_license')
                    ->where('id', intval($license['id']))
                    ->update([
                        'status' => $status,
                        'updatetime' => time(),
                    ]);
            }

            if ($status === 1) {
                $hasPluginMenuTable = $this->checkTableExists('admin_plugin_menu');
                $shouldRebuildMenus = in_array($pluginName, self::MENU_AUTO_SYNC_PLUGIN_NAMES, true) || !$hasPluginMenuTable;
                if (!$shouldRebuildMenus && $hasPluginMenuTable) {
                    $shouldRebuildMenus = Db::name('admin_plugin_menu')
                        ->where('aid', $this->aid)
                        ->where('plugin_name', $pluginName)
                        ->count() <= 0;
                }

                if ($shouldRebuildMenus) {
                    try {
                        PluginMenu::registerFromConfig($pluginName, intval($this->aid));
                    } catch (\Throwable $e) {
                    }
                }

                try {
                    PluginMenu::syncPermissionMetadata($pluginName, intval($this->aid));
                } catch (\Throwable $e) {
                    \think\facade\Log::warning('插件菜单权限元数据同步失败', [
                        'aid' => intval($this->aid),
                        'plugin_name' => $pluginName,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if ($this->checkTableExists('admin_plugin_menu')) {
                Db::name('admin_plugin_menu')
                    ->where('aid', $this->aid)
                    ->where('plugin_name', $pluginName)
                    ->update(['status' => $status]);
            }

            // 清除菜单缓存
            \think\facade\Cache::clear();

            $msg = $status == 1 ? '插件已启用' : '插件已禁用';
            return $this->success([], $msg);

        } catch (\Exception $e) {
            return $this->error('操作失败：' . $e->getMessage());
        }
    }

    /**
     * 扫描插件目录
     */
    private function scanPlugins()
    {
        $pluginPath = app()->getRootPath() . 'app/plugin/';
        $plugins = [];

        if (!is_dir($pluginPath)) {
            return $plugins;
        }

        $dirs = scandir($pluginPath);
        foreach ($dirs as $dir) {
            if ($dir == '.' || $dir == '..') continue;

            $dirPath = $pluginPath . $dir;
            if (!is_dir($dirPath)) continue;

            $configFile = $dirPath . '/plugin.json';
            if (file_exists($configFile)) {
                $content = file_get_contents($configFile);
                $config = json_decode($content, true);
                if ($config && is_array($config)) {
                    if (!PluginCategoryRegistry::isScannablePluginDirectory($dir, $config)) {
                        continue;
                    }
                    // 确保有 name 字段
                    if (!isset($config['name'])) {
                        $config['name'] = $dir;
                    }
                    // 确保有必要的字段
                    if (!isset($config['title'])) $config['title'] = $dir;
                    if (!isset($config['description'])) $config['description'] = '';
                    if (!isset($config['version'])) $config['version'] = '1.0.0';
                    if (!isset($config['author'])) $config['author'] = '';
                    // 插件图标（云图片地址），为空时前端用首字+主题色占位
                    if (!isset($config['icon'])) $config['icon'] = '';

                    $config = PluginCategoryRegistry::normalizePluginConfig($config, $dir);
                    $plugins[] = $config;
                }
            }
        }

        return $plugins;
    }

    /**
     * 获取插件菜单树（用于管理页面）
     */
    public function getMenuTree()
    {
        $pluginName = input('get.plugin_name', '');
        if (empty($pluginName)) {
            return $this->error('插件名称不能为空');
        }

        try {
            if (!$this->canCurrentUserAccessPlugin($pluginName)) {
                return $this->error('无权限访问该插件');
            }

            if ($this->checkTableExists('admin_plugin_license')) {
                $license = Db::name('admin_plugin_license')
                    ->where('aid', $this->aid)
                    ->where('plugin_name', $pluginName)
                    ->find();

                if (!$license) {
                    return $this->error('插件未授权，请联系平台管理员');
                }
                if (intval($license['expire_time'] ?? 0) > 0 && intval($license['expire_time']) < time()) {
                    return $this->error('插件授权已过期，请联系平台管理员');
                }
                if (intval($license['status'] ?? 0) !== 1) {
                    return $this->error('插件已禁用，请先启用后再管理');
                }
            }

            $this->syncPluginMenuTreeIfNeeded($pluginName);

            // 检查插件是否已授权
            if ($this->checkTableExists('admin_plugin_menu')) {
                // 查询菜单
                $menus = Db::name('admin_plugin_menu')
                    ->where('aid', $this->aid)
                    ->where('plugin_name', $pluginName)
                    ->where('status', 1)
                    ->order('sort', 'asc')
                    ->select()
                    ->toArray();

                // 构建菜单树（优先使用数据库；如数据库未写入子菜单，则回退读取 plugin.json）
                $tree = $this->buildMenuTree($menus, $pluginName);
                if (empty($tree) || $this->isOnlyEmptyParentMenu($menus)) {
                    // 兜底：菜单异常时先尝试按 plugin.json 重建数据库菜单
                    try {
                        \app\admin\common\PluginMenu::registerFromConfig($pluginName, intval($this->aid));
                        $menus = Db::name('admin_plugin_menu')
                            ->where('aid', $this->aid)
                            ->where('plugin_name', $pluginName)
                            ->where('status', 1)
                            ->order('sort', 'asc')
                            ->select()
                            ->toArray();
                        $tree = $this->buildMenuTree($menus, $pluginName);
                    } catch (\Throwable $e) {
                    }
                }
                if (empty($tree) || $this->isOnlyEmptyParentMenu($menus)) {
                    $tree = $this->buildMenuTreeFromConfig($pluginName);
                }

                // 子账号只保留权限组里勾选的插件菜单，与主导航过滤规则一致
                $userAuthData = $this->getCurrentUserPluginMenuAuthData();
                if (is_array($userAuthData)) {
                    $menus = $this->filterMenuRowsByAuth($menus, $userAuthData);
                    $tree = $this->buildMenuTree($menus, $pluginName);
                    if (empty($tree)) {
                        return $this->error('当前账号未分配该插件的菜单权限，请联系管理员在权限组中勾选');
                    }
                }
                
                // 获取插件信息
                $pluginInfo = $this->getPluginInfo($pluginName);
                
                // 获取默认菜单（第一个有path的菜单）
                $defaultMenu = $this->getDefaultMenu($menus);
                if (empty($defaultMenu)) {
                    $defaultMenu = $this->getDefaultMenuFromTree($tree);
                }

                return $this->success([
                    'tree' => $tree,
                    'plugin_info' => $pluginInfo,
                    'default_menu' => $defaultMenu
                ], '获取成功');
            }

            return $this->success([
                'tree' => [],
                'plugin_info' => [],
                'default_menu' => ''
            ], '暂无菜单');

        } catch (\Exception $e) {
            return $this->error('获取失败：' . $e->getMessage());
        }
    }

    private function syncPluginMenuTreeIfNeeded(string $pluginName): void
    {
        if (!in_array($pluginName, self::MENU_AUTO_SYNC_PLUGIN_NAMES, true)) {
            return;
        }

        $pluginConfig = $this->getPluginConfig($pluginName);
        $configMenus = $pluginConfig['menu'] ?? $pluginConfig['menus'] ?? [];
        if (empty($configMenus) || !is_array($configMenus)) {
            return;
        }

        $menus = [];
        if ($this->checkTableExists('admin_plugin_menu')) {
            $menus = Db::name('admin_plugin_menu')
                ->where('aid', $this->aid)
                ->where('plugin_name', $pluginName)
                ->order('sort', 'asc')
                ->order('id', 'asc')
                ->select()
                ->toArray();
        }

        if ($this->menuSnapshotsMatch($menus, $configMenus, intval($pluginConfig['use_vue'] ?? 0))) {
            return;
        }

        try {
            PluginMenu::registerFromConfig($pluginName, intval($this->aid));
        } catch (\Throwable $e) {
        }
    }

    private function getPluginConfig(string $pluginName): array
    {
        if ($pluginName === '') {
            return [];
        }

        $configFile = app()->getRootPath() . "app/plugin/{$pluginName}/plugin.json";
        if (!file_exists($configFile)) {
            return [];
        }

        $config = json_decode(file_get_contents($configFile), true);
        return is_array($config) ? $config : [];
    }

    private function menuSnapshotsMatch(array $dbMenus, array $configMenus, int $defaultUseVue = 0): bool
    {
        return $this->buildStoredMenuSnapshot($dbMenus) === $this->buildConfigMenuSnapshot($configMenus, $defaultUseVue);
    }

    private function buildConfigMenuSnapshot(array $menus, int $defaultUseVue = 0, int $depth = 0): array
    {
        $snapshot = [];
        $menus = $this->sortMenuNodes($menus);

        foreach ($menus as $menu) {
            $snapshot[] = $this->normalizeMenuSnapshotRow($menu, $depth, $defaultUseVue);
            if (!empty($menu['children']) && is_array($menu['children'])) {
                $snapshot = array_merge($snapshot, $this->buildConfigMenuSnapshot($menu['children'], $defaultUseVue, $depth + 1));
            }
        }

        return $snapshot;
    }

    private function buildStoredMenuSnapshot(array $menus): array
    {
        if (empty($menus)) {
            return [];
        }

        $menuMap = [];
        $childrenMap = [];
        foreach ($menus as $menu) {
            $menu['id'] = intval($menu['id'] ?? 0);
            $menu['pid'] = intval($menu['pid'] ?? 0);
            $menuMap[$menu['id']] = $menu;
            $childrenMap[$menu['pid']][] = $menu;
        }

        $rootMenus = [];
        foreach ($menus as $menu) {
            if (!isset($menuMap[intval($menu['pid'] ?? 0)])) {
                $rootMenus[] = $menu;
            }
        }
        $rootMenus = $this->sortMenuNodes($rootMenus);

        $snapshot = [];
        $flatten = function (array $items, int $depth) use (&$flatten, &$snapshot, $childrenMap) {
            foreach ($items as $item) {
                $snapshot[] = $this->normalizeMenuSnapshotRow($item, $depth);
                $children = $childrenMap[intval($item['id'] ?? 0)] ?? [];
                if (!empty($children)) {
                    $flatten($this->sortMenuNodes($children), $depth + 1);
                }
            }
        };

        $flatten($rootMenus, 0);

        return $snapshot;
    }

    private function sortMenuNodes(array $menus): array
    {
        usort($menus, static function ($a, $b) {
            $sortCompare = intval($a['sort'] ?? 0) <=> intval($b['sort'] ?? 0);
            if ($sortCompare !== 0) {
                return $sortCompare;
            }

            $idA = intval($a['id'] ?? 0);
            $idB = intval($b['id'] ?? 0);
            if ($idA > 0 || $idB > 0) {
                return $idA <=> $idB;
            }

            return strcmp(strval($a['name'] ?? ''), strval($b['name'] ?? ''));
        });

        return $menus;
    }

    private function normalizeMenuSnapshotRow(array $menu, int $depth, ?int $defaultUseVue = null): array
    {
        return [
            'depth' => $depth,
            'name' => strval($menu['name'] ?? ''),
            'path' => strval($menu['path'] ?? ''),
            'vue_path' => strval($menu['vue_path'] ?? ''),
            'sort' => intval($menu['sort'] ?? 0),
            'auth_mode' => intval($menu['auth_mode'] ?? 0),
            'auth_data' => strval($menu['auth_data'] ?? ''),
            'use_vue' => array_key_exists('use_vue', $menu)
                ? intval($menu['use_vue'])
                : intval($defaultUseVue ?? 0),
            'admin_only' => intval($menu['admin_only'] ?? 0),
            'merchant_visible' => intval($menu['merchant_visible'] ?? 1),
            'tuanzhang_visible' => intval($menu['tuanzhang_visible'] ?? 0),
        ];
    }

    /**
     * 构建菜单树（与ThinkPHP原版逻辑一致）
     */
    private function buildMenuTree($menus, string $pluginName = '')
    {
        if (empty($menus)) {
            return [];
        }

        // 获取所有菜单ID
        $menuIds = array_column($menus, 'id');
        
        // 找出顶级菜单（pid 不在当前菜单ID列表中的）
        $topMenus = [];
        foreach ($menus as $menu) {
            if (!in_array($menu['pid'], $menuIds)) {
                $topMenus[] = $menu;
            }
        }

        // 为每个顶级菜单构建子树
        $tree = [];
        foreach ($topMenus as $topMenu) {
            $tree[] = $this->buildMenuChildren($menus, $topMenu['id'], $topMenu, $pluginName);
        }

        return $tree;
    }

    /**
     * 递归构建子菜单
     */
    private function buildMenuChildren($menus, $parentId, $parentMenu, $pluginName = '')
    {
        $children = [];
        foreach ($menus as $menu) {
            if ($menu['pid'] == $parentId) {
                $children[] = $this->buildMenuChildren($menus, $menu['id'], $menu, $pluginName);
            }
        }

        $result = [
            'id' => $parentMenu['id'],
            'name' => $parentMenu['name'],
            'path' => $parentMenu['path'] ?? '',
            // 重要：Vue 站点后台需要 vue_path 来做精准跳转；没有则前端会走转换函数
            'vue_path' => $parentMenu['vue_path'] ?? '',
            'icon' => $parentMenu['icon'] ?? '',
        ];
        
        // 如果数据库中没有 vue_path，尝试从配置文件补充
        if (empty($result['vue_path']) && !empty($pluginName) && !empty($result['path'])) {
            $configPath = $this->getVuePathFromConfig($pluginName, $result['path']);
            if ($configPath) {
                $result['vue_path'] = $configPath;
            }
        }

        if (!empty($children)) {
            $result['children'] = $children;
        }

        return $result;
    }
    
    /**
     * 从配置文件获取 vue_path
     */
    private function getVuePathFromConfig($pluginName, $path)
    {
        try {
            $configFile = app()->getRootPath() . "app/plugin/{$pluginName}/plugin.json";
            if (!file_exists($configFile)) return '';
            
            $config = json_decode(file_get_contents($configFile), true);
            if (!$config || empty($config['menu'])) return '';
            
            $findPath = function($items, $targetPath) use (&$findPath) {
                foreach ($items as $item) {
                    if (($item['path'] ?? '') === $targetPath) {
                        return $item['vue_path'] ?? '';
                    }
                    if (!empty($item['children'])) {
                        $found = $findPath($item['children'], $targetPath);
                        if ($found) return $found;
                    }
                }
                return '';
            };
            
            return $findPath($config['menu'], $path);
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * 获取默认菜单（第一个有path的菜单）
     */
    private function getDefaultMenu($menus)
    {
        // 按sort和id排序，确保获取第一个有path的菜单
        usort($menus, function($a, $b) {
            if ($a['sort'] == $b['sort']) {
                return $a['id'] - $b['id'];
            }
            return $a['sort'] - $b['sort'];
        });

        foreach ($menus as $menu) {
            if (!empty($menu['path'])) {
                return $menu['path'];
            }
        }
        return '';
    }

    /**
     * 获取授权类型文本
     */
    private function getLicenseTypeText($type)
    {
        $types = [
            0 => '永久',
            1 => '试用',
            2 => '付费'
        ];
        return $types[$type] ?? '未知';
    }

    /**
     * 获取插件信息
     */
    private function getPluginInfo($pluginName)
    {
        $configFile = app()->getRootPath() . "app/plugin/{$pluginName}/plugin.json";
        if (!file_exists($configFile)) {
            $info = [
                'name' => $pluginName,
                'title' => $pluginName,
                'version' => '1.0.0',
                'author' => '未知',
                'use_vue' => 0
            ];
        } else {
            $content = file_get_contents($configFile);
            $config = json_decode($content, true);
            $info = $config ?: [];
        }

        if (is_array($info)) {
            $info = PluginCategoryRegistry::normalizePluginConfig($info, $pluginName);
        }

        $useVue = null;

        if ($this->checkTableExists('admin_plugin_version')) {
            $versionInfo = Db::name('admin_plugin_version')
                ->where('plugin_name', $pluginName)
                ->find();
            if ($versionInfo && array_key_exists('use_vue', $versionInfo)) {
                $useVue = intval($versionInfo['use_vue']);
            }
        }

        if ($useVue === null && $this->checkTableExists('admin_plugin_menu') && $this->checkColumnExists('admin_plugin_menu', 'use_vue')) {
            $menuUseVue = Db::name('admin_plugin_menu')
                ->where('aid', $this->aid)
                ->where('plugin_name', $pluginName)
                ->max('use_vue');
            if ($menuUseVue !== null && $menuUseVue !== '') {
                $useVue = intval($menuUseVue);
            }
        }

        if ($useVue === null) {
            // 兼容：如果版本表/菜单表都没有记录，则使用 plugin.json 的 use_vue
            $useVue = intval($info['use_vue'] ?? 0);
        }

        $info['use_vue'] = $useVue;

        $info['supports_vue'] = $this->pluginSupportsVue($info);
        if (!$info['supports_vue']) {
            $info['use_vue'] = 0;
        }

        return $info;
    }

    private function pluginSupportsVue(array $pluginInfo): int
    {
        if (array_key_exists('supports_vue', $pluginInfo)) {
            return intval($pluginInfo['supports_vue']) === 1 ? 1 : 0;
        }

        return 1;
    }

    /**
     * 判断数据库里是否只有一个“空父菜单”（仅有 plugin_name 顶级记录，且无可用 path）
     */
    private function isOnlyEmptyParentMenu(array $menus): bool
    {
        if (empty($menus)) return true;
        $hasRealPath = false;
        foreach ($menus as $m) {
            if (!empty($m['path'])) {
                $hasRealPath = true;
                break;
            }
        }
        // 只有一条记录且 path 为空，基本就是安装兜底插入的父菜单
        if (count($menus) === 1 && !$hasRealPath) return true;
        // 有多条但全部 path 为空，也视为异常/未注册子菜单
        if (!$hasRealPath) return true;
        return false;
    }

    /**
     * 从 plugin.json 的 menu 配置构建菜单树（用于数据库未注册成功时的回退）
     */
    private function buildMenuTreeFromConfig(string $pluginName): array
    {
        $configFile = app()->getRootPath() . "app/plugin/{$pluginName}/plugin.json";
        if (!file_exists($configFile)) return [];
        $content = file_get_contents($configFile);
        $config = json_decode($content, true);
        if (!$config || empty($config['menu']) || !is_array($config['menu'])) return [];

        $id = 100000; // 给前端一个稳定的伪ID区间
        $convert = function ($item) use (&$convert, &$id) {
            $node = [
                'id' => $id++,
                'name' => $item['name'] ?? '',
                'path' => $item['path'] ?? '',
                'vue_path' => $item['vue_path'] ?? '',
                'icon' => $item['icon'] ?? '',
            ];
            if (!empty($item['children']) && is_array($item['children'])) {
                $node['children'] = [];
                foreach ($item['children'] as $c) {
                    $node['children'][] = $convert($c);
                }
            }
            return $node;
        };

        $tree = [];
        foreach ($config['menu'] as $m) {
            $tree[] = $convert($m);
        }
        return $tree;
    }

    /**
     * 从树中提取默认菜单（第一个叶子节点的 path）
     */
    private function getDefaultMenuFromTree(array $tree): string
    {
        $stack = $tree;
        while (!empty($stack)) {
            $node = array_shift($stack);
            if (!empty($node['children']) && is_array($node['children'])) {
                foreach ($node['children'] as $c) {
                    $stack[] = $c;
                }
                continue;
            }
            if (!empty($node['path'])) return $node['path'];
        }
        return '';
    }

    private function getAuthorizedPluginMap(): array
    {
        if ($this->checkTableExists('admin_plugin_license')) {
            $rows = Db::name('admin_plugin_license')
                ->where('aid', $this->aid)
                ->select()
                ->toArray();

            $map = [];
            foreach ($rows as $row) {
                $pluginName = trim((string)($row['plugin_name'] ?? ''));
                if ($pluginName === '') {
                    continue;
                }
                $map[$pluginName] = $row;
            }

            return $map;
        }

        if ($this->checkTableExists('admin_plugin_menu')) {
            $pluginMenus = Db::name('admin_plugin_menu')
                ->where('aid', $this->aid)
                ->group('plugin_name')
                ->column('status,createtime', 'plugin_name');

            $map = [];
            foreach ($pluginMenus as $name => $info) {
                $pluginName = trim((string)$name);
                if ($pluginName === '') {
                    continue;
                }
                $map[$pluginName] = [
                    'plugin_name' => $pluginName,
                    'status' => intval($info['status'] ?? 1),
                    'license_type' => 0,
                    'expire_time' => 0,
                    'createtime' => intval($info['createtime'] ?? time()),
                    'remark' => '',
                ];
            }

            return $map;
        }

        return [];
    }

    /**
     * 当前登录账号可访问的插件集合
     * null = 不限制（超管/全权限）；array = 仅允许集合内插件
     */
    private function getCurrentUserAllowedPluginNames(): ?array
    {
        $authContext = $this->getResolvedAuthContext();
        if (!empty($authContext['isAllAuth'])) {
            return null;
        }

        // 站点管理员只按平台授权判断插件可见性；插件菜单权限只限制普通子账号。
        if (intval($this->bid ?? 0) <= 0 && intval($this->user['isadmin'] ?? 0) > 0) {
            return null;
        }

        $authData = is_array($authContext['authData'] ?? null) ? $authContext['authData'] : [];
        if ($this->hasExplicitPluginManagerAuth($authData)) {
            return null;
        }

        $pluginNames = \app\admin\common\Menu::getAccessiblePluginNames(intval($this->aid), $authData);
        $map = [];
        foreach ($pluginNames as $pluginName) {
            $pluginName = trim((string)$pluginName);
            if ($pluginName !== '') {
                $map[$pluginName] = true;
            }
        }

        return $map;
    }

    /**
     * 当前账号用于过滤插件菜单的权限值
     * null = 不限制（超管/全权限/站点管理员）；array = 权限组 auth_data
     */
    private function getCurrentUserPluginMenuAuthData(): ?array
    {
        $authContext = $this->getResolvedAuthContext();
        if (!empty($authContext['isAllAuth'])) {
            return null;
        }

        if (intval($this->bid ?? 0) <= 0 && intval($this->user['isadmin'] ?? 0) > 0) {
            return null;
        }

        $authData = is_array($authContext['authData'] ?? null) ? $authContext['authData'] : [];
        if ($this->hasExplicitPluginManagerAuth($authData)) {
            return null;
        }

        return $authData;
    }

    /**
     * 按权限过滤插件菜单行：有路径的菜单需命中权限，分组在有可见后代时保留
     */
    private function filterMenuRowsByAuth(array $menus, array $authData): array
    {
        $authMap = [];
        $authPathMap = [];
        foreach ($authData as $item) {
            if (is_string($item) && $item !== '') {
                $authMap[$item] = true;
                $authPath = trim(explode(',', $item)[0]);
                if ($authPath !== '') {
                    $authPathMap[$authPath] = true;
                }
            }
        }

        $menuMap = [];
        foreach ($menus as $menu) {
            $menuMap[intval($menu['id'] ?? 0)] = $menu;
        }

        $keepIds = [];
        foreach ($menus as $menu) {
            $path = trim((string)($menu['path'] ?? ''));
            if ($path === '') {
                continue;
            }
            $menuAuth = trim((string)($menu['auth_data'] ?? ''));
            if ($menuAuth === '') {
                $menuAuth = $path . '/*';
            }
            if (!isset($authMap[$path . ',' . $menuAuth]) && !isset($authPathMap[$path])) {
                continue;
            }

            $currentId = intval($menu['id'] ?? 0);
            while ($currentId > 0 && isset($menuMap[$currentId]) && !isset($keepIds[$currentId])) {
                $keepIds[$currentId] = true;
                $currentId = intval($menuMap[$currentId]['pid'] ?? 0);
            }
        }

        return array_values(array_filter($menus, function ($menu) use ($keepIds) {
            return isset($keepIds[intval($menu['id'] ?? 0)]);
        }));
    }

    private function canCurrentUserAccessPlugin(string $pluginName): bool
    {
        $pluginName = trim($pluginName);
        if ($pluginName === '') {
            return false;
        }

        $allowedPluginNames = $this->getCurrentUserAllowedPluginNames();
        if ($allowedPluginNames === null) {
            return true;
        }

        return isset($allowedPluginNames[$pluginName]);
    }

    private function hasExplicitPluginManagerAuth(array $authData): bool
    {
        foreach ($authData as $item) {
            if (!is_string($item)) {
                continue;
            }
            $item = trim($item);
            if ($item === '' ) {
                continue;
            }
            if ($item === 'MyPlugin/*' || $item === 'MyPlugin/index' || strpos($item, 'MyPlugin/') === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * 检查数据表是否存在
     */
    private function checkTableExists($tableName)
    {
        try {
            $prefix = config('database.connections.mysql.prefix');
            $fullTableName = $prefix . $tableName;
            $result = Db::query("SHOW TABLES LIKE '{$fullTableName}'");
            return !empty($result);
        } catch (\Exception $e) {
            return false;
        }
    }

    private function checkColumnExists($tableName, $columnName)
    {
        try {
            $prefix = config('database.connections.mysql.prefix');
            $fullTableName = $prefix . $tableName;
            $result = Db::query("SHOW COLUMNS FROM `{$fullTableName}` LIKE '{$columnName}'");
            return !empty($result);
        } catch (\Exception $e) {
            return false;
        }
    }
}
