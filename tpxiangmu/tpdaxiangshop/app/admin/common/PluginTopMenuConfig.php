<?php

namespace app\admin\common;

use think\facade\Db;

class PluginTopMenuConfig
{
    public const HIDE_PLUGIN_ENTRY_KEY = '__hide_plugin_entry';

    private $aid = 0;
    private $table = 'admin_plugin_top_menu';

    public function __construct(int $aid = 0)
    {
        $this->aid = max(0, $aid);
    }

    public function ensureReady(): void
    {
        $this->ensureSchema();
    }

    public function getManageList(): array
    {
        $this->ensureReady();

        $authorizedMap = $this->getAuthorizedPluginIndex();
        if (empty($authorizedMap)) {
            return [];
        }

        $menuMetaMap = $this->getPluginMenuMetaMap();
        $configMap = $this->getConfigMap(false);
        $list = [];

        foreach ($authorizedMap as $pluginName => $pluginMeta) {
            $menuMeta = $menuMetaMap[$pluginName] ?? [];
            $hasAdminMenu = !empty($menuMeta);
            $config = $configMap[$pluginName] ?? [];
            $customName = trim((string)($config['custom_name'] ?? ''));
            $customIcon = trim((string)($config['custom_icon'] ?? ''));
            $sort = array_key_exists('sort', $config)
                ? intval($config['sort'])
                : intval($menuMeta['default_sort'] ?? 0);
            $status = $hasAdminMenu ? intval($config['status'] ?? 0) : 0;

            $pluginTitle = trim((string)($pluginMeta['plugin_title'] ?? '')) ?: $pluginName;
            $defaultName = trim((string)($menuMeta['default_name'] ?? '')) ?: $pluginTitle;
            $defaultIcon = trim((string)($menuMeta['default_icon'] ?? '')) ?: 'AppstoreOutlined';
            $effectiveName = $customName !== '' ? $customName : $defaultName;
            $effectiveIcon = $customIcon !== ''
                ? $customIcon
                : $defaultIcon;

            $menuTree = $hasAdminMenu && is_array($menuMeta['menu_tree'] ?? null)
                ? $menuMeta['menu_tree']
                : [];
            $promoteItems = self::filterPromoteItems(
                self::decodePromoteItems($config['promote_items'] ?? []),
                $menuTree
            );
            $promoteIcons = self::filterPromoteIcons(
                self::decodePromoteIcons($config['promote_icons'] ?? []),
                $promoteItems,
                $menuTree
            );

            $list[] = array_merge($pluginMeta, $menuMeta, [
                'id' => intval($config['id'] ?? 0),
                'aid' => $this->aid,
                'root_menu_count' => intval($menuMeta['root_menu_count'] ?? 0),
                'root_menu_names' => array_values($menuMeta['root_menu_names'] ?? []),
                'menu_tree' => $menuTree,
                'promote_items' => $promoteItems,
                'promote_icons' => $promoteIcons,
                'default_name' => $defaultName,
                'default_icon' => $defaultIcon,
                'default_sort' => intval($menuMeta['default_sort'] ?? 0),
                'custom_name' => $customName,
                'custom_icon' => $customIcon,
                'sort' => $sort,
                'status' => $status,
                'is_top_level' => $status === 1 ? 1 : 0,
                'effective_name' => $effectiveName,
                'effective_icon' => $effectiveIcon,
                'has_admin_menu' => $hasAdminMenu ? 1 : 0,
                'can_promote' => $hasAdminMenu ? 1 : 0,
            ]);
        }

        usort($list, static function (array $a, array $b) {
            $sortCompare = intval($a['sort'] ?? 0) <=> intval($b['sort'] ?? 0);
            if ($sortCompare !== 0) {
                return $sortCompare;
            }

            return strcmp((string)($a['plugin_title'] ?? ''), (string)($b['plugin_title'] ?? ''));
        });

        return $list;
    }

    public function saveConfig(array $info): array
    {
        $this->ensureReady();

        $pluginName = trim((string)($info['plugin_name'] ?? ''));
        if ($pluginName === '') {
            throw new \RuntimeException('插件标识不能为空');
        }

        $authorizedMap = $this->getAuthorizedPluginIndex();
        if (!isset($authorizedMap[$pluginName])) {
            throw new \RuntimeException('当前站点未识别到该插件');
        }

        $menuMetaMap = $this->getPluginMenuMetaMap();
        if (!isset($menuMetaMap[$pluginName])) {
            throw new \RuntimeException('当前插件暂无后台菜单，暂不支持提升为一级菜单');
        }

        $exists = Db::name($this->table)
            ->where('aid', $this->aid)
            ->where('plugin_name', $pluginName)
            ->find();

        $sort = isset($info['sort'])
            ? intval($info['sort'])
            : intval($exists['sort'] ?? ($menuMetaMap[$pluginName]['default_sort'] ?? 0));

        $menuTree = $menuMetaMap[$pluginName]['menu_tree'] ?? [];
        $promoteItems = array_key_exists('promote_items', $info)
            ? self::filterPromoteItems(self::decodePromoteItems($info['promote_items'] ?? []), $menuTree)
            : self::filterPromoteItems(self::decodePromoteItems($exists['promote_items'] ?? ''), $menuTree);
        $promoteIcons = array_key_exists('promote_icons', $info)
            ? self::decodePromoteIcons($info['promote_icons'] ?? [])
            : self::decodePromoteIcons($exists['promote_icons'] ?? '');
        $promoteIcons = self::filterPromoteIcons($promoteIcons, $promoteItems, $menuTree);

        $data = [
            'aid' => $this->aid,
            'plugin_name' => $pluginName,
            'custom_name' => trim((string)($info['custom_name'] ?? '')),
            'custom_icon' => trim((string)($info['custom_icon'] ?? '')),
            'sort' => $sort,
            'status' => intval($info['status'] ?? 0) === 1 ? 1 : 0,
            'promote_items' => json_encode($promoteItems, JSON_UNESCAPED_UNICODE),
            'promote_icons' => json_encode($promoteIcons, JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT),
            'updatetime' => time(),
        ];

        if ($exists) {
            Db::name($this->table)
                ->where('id', intval($exists['id']))
                ->where('aid', $this->aid)
                ->update($data);
        } else {
            $data['createtime'] = time();
            Db::name($this->table)->insert($data);
        }

        MenuDatabase::clearCache($this->aid);

        $list = $this->getManageList();
        foreach ($list as $row) {
            if (($row['plugin_name'] ?? '') === $pluginName) {
                return $row;
            }
        }

        throw new \RuntimeException('保存成功，但读取配置失败');
    }

    public static function getEnabledMap(int $aid): array
    {
        $service = new self($aid);
        $service->ensureReady();

        $rows = Db::name($service->table)
            ->where('aid', max(0, $aid))
            ->where('status', 1)
            ->select()
            ->toArray();

        $map = [];
        foreach ($rows as $row) {
            $pluginName = trim((string)($row['plugin_name'] ?? ''));
            if ($pluginName === '' || self::isReservedPluginName($pluginName)) {
                continue;
            }
            $map[$pluginName] = self::formatAdjustRow($row);
        }

        return $map;
    }

    public function getHidePluginEntry(): int
    {
        $this->ensureReady();
        $status = Db::name($this->table)
            ->where('aid', $this->aid)
            ->where('plugin_name', self::HIDE_PLUGIN_ENTRY_KEY)
            ->value('status');

        return intval($status) === 1 ? 1 : 0;
    }

    public function saveHidePluginEntry(int $hide): int
    {
        $this->ensureReady();
        $hide = $hide === 1 ? 1 : 0;
        $now = time();
        $exists = Db::name($this->table)
            ->where('aid', $this->aid)
            ->where('plugin_name', self::HIDE_PLUGIN_ENTRY_KEY)
            ->find();

        if ($exists) {
            Db::name($this->table)->where('id', intval($exists['id']))->update([
                'status' => $hide,
                'updatetime' => $now,
            ]);
        } else {
            Db::name($this->table)->insert([
                'aid' => $this->aid,
                'plugin_name' => self::HIDE_PLUGIN_ENTRY_KEY,
                'custom_name' => '',
                'custom_icon' => '',
                'sort' => 0,
                'status' => $hide,
                'promote_items' => '',
                'createtime' => $now,
                'updatetime' => $now,
            ]);
        }

        MenuDatabase::clearCache($this->aid);
        return $hide;
    }

    public static function isHidePluginEntry(int $aid): bool
    {
        if ($aid <= 0) {
            return false;
        }

        $service = new self($aid);
        return $service->getHidePluginEntry() === 1;
    }

    private static function isReservedPluginName(string $pluginName): bool
    {
        return $pluginName === self::HIDE_PLUGIN_ENTRY_KEY;
    }

    /**
     * 整插件提升，或勾选了单独提到主菜单的项。
     */
    public static function getAdjustMap(int $aid): array
    {
        $service = new self($aid);
        $service->ensureReady();

        $rows = Db::name($service->table)
            ->where('aid', max(0, $aid))
            ->select()
            ->toArray();

        $map = [];
        foreach ($rows as $row) {
            $pluginName = trim((string)($row['plugin_name'] ?? ''));
            if ($pluginName === '' || self::isReservedPluginName($pluginName)) {
                continue;
            }
            $formatted = self::formatAdjustRow($row);
            if (intval($formatted['status'] ?? 0) !== 1 && empty($formatted['promote_items'])) {
                continue;
            }
            $map[$pluginName] = $formatted;
        }

        return $map;
    }

    public static function buildMenuItemKey(array $menu, string $parentKey = ''): string
    {
        $path = trim((string)($menu['path'] ?? ''));
        if ($path !== '') {
            return 'path:' . $path;
        }

        $name = trim((string)($menu['name'] ?? ''));
        if ($parentKey === '') {
            return 'group:' . $name;
        }

        return $parentKey . '>' . $name;
    }

    private static function formatAdjustRow(array $row): array
    {
        return [
            'id' => intval($row['id'] ?? 0),
            'plugin_name' => trim((string)($row['plugin_name'] ?? '')),
            'custom_name' => trim((string)($row['custom_name'] ?? '')),
            'custom_icon' => trim((string)($row['custom_icon'] ?? '')),
            'sort' => intval($row['sort'] ?? 0),
            'status' => intval($row['status'] ?? 0),
            'promote_items' => self::decodePromoteItems($row['promote_items'] ?? ''),
            'promote_icons' => self::decodePromoteIcons($row['promote_icons'] ?? ''),
        ];
    }

    public static function decodePromoteItems($raw): array
    {
        if (is_string($raw)) {
            $raw = trim($raw);
            if ($raw === '') {
                return [];
            }
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw)) {
            return [];
        }

        $result = [];
        foreach ($raw as $item) {
            $item = trim((string)$item);
            if ($item === '' || isset($result[$item])) {
                continue;
            }
            $result[$item] = $item;
        }

        return array_values($result);
    }

    private static function filterPromoteItems(array $promoteItems, array $menuTree): array
    {
        $validKeys = [];
        $walker = function (array $nodes) use (&$walker, &$validKeys) {
            foreach ($nodes as $node) {
                if (!is_array($node)) {
                    continue;
                }
                $itemKey = trim((string)($node['item_key'] ?? ''));
                if ($itemKey !== '') {
                    $validKeys[$itemKey] = true;
                }
                if (!empty($node['children']) && is_array($node['children'])) {
                    $walker($node['children']);
                }
            }
        };
        $walker($menuTree);

        $result = [];
        foreach ($promoteItems as $itemKey) {
            $itemKey = trim((string)$itemKey);
            if ($itemKey !== '' && isset($validKeys[$itemKey])) {
                $result[$itemKey] = $itemKey;
            }
        }

        return array_values($result);
    }

    public static function decodePromoteIcons($raw): array
    {
        if (is_string($raw)) {
            $raw = trim($raw);
            if ($raw === '') {
                return [];
            }
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw)) {
            return [];
        }

        $allowed = self::allowedPromoteIcons();
        $result = [];
        foreach ($raw as $itemKey => $icon) {
            $itemKey = trim((string)$itemKey);
            $icon = trim((string)$icon);
            if ($itemKey === '' || $icon === '' || !isset($allowed[$icon]) || isset($result[$itemKey])) {
                continue;
            }
            $result[$itemKey] = $icon;
        }

        return $result;
    }

    private static function filterPromoteIcons(array $promoteIcons, array $promoteItems, array $menuTree): array
    {
        $validKeys = [];
        $walker = function (array $nodes) use (&$walker, &$validKeys) {
            foreach ($nodes as $node) {
                if (!is_array($node)) {
                    continue;
                }
                $itemKey = trim((string)($node['item_key'] ?? ''));
                if ($itemKey !== '') {
                    $validKeys[$itemKey] = true;
                }
                if (!empty($node['children']) && is_array($node['children'])) {
                    $walker($node['children']);
                }
            }
        };
        $walker($menuTree);

        $promoted = [];
        foreach ($promoteItems as $itemKey) {
            $itemKey = trim((string)$itemKey);
            if ($itemKey !== '') {
                $promoted[$itemKey] = true;
            }
        }

        $result = [];
        foreach ($promoteIcons as $itemKey => $icon) {
            $itemKey = trim((string)$itemKey);
            $icon = trim((string)$icon);
            if ($itemKey === '' || $icon === '' || !isset($promoted[$itemKey]) || !isset($validKeys[$itemKey])) {
                continue;
            }
            $result[$itemKey] = $icon;
        }

        return $result;
    }

    private static function allowedPromoteIcons(): array
    {
        $icons = [
            'AppstoreOutlined',
            'ShopOutlined',
            'UserOutlined',
            'TeamOutlined',
            'GiftOutlined',
            'SettingOutlined',
            'ReadOutlined',
            'CalendarOutlined',
            'NotificationOutlined',
            'PictureOutlined',
            'ToolOutlined',
            'RocketOutlined',
            'CrownOutlined',
            'DatabaseOutlined',
            'HomeOutlined',
            'FormOutlined',
            'FileTextOutlined',
            'CloudOutlined',
        ];

        return array_fill_keys($icons, true);
    }

    public static function resolvePluginTitle(string $pluginName, string $fallback = ''): string
    {
        $fallback = trim($fallback);
        $pluginInfo = self::loadPluginInfo($pluginName);
        $title = trim((string)($pluginInfo['title'] ?? ''));
        if ($title !== '') {
            return $title;
        }

        return $fallback !== '' ? $fallback : $pluginName;
    }

    private function getConfigMap(bool $enabledOnly = false): array
    {
        $query = Db::name($this->table)->where('aid', $this->aid);
        if ($enabledOnly) {
            $query->where('status', 1);
        }

        $rows = $query->select()->toArray();
        $map = [];
        foreach ($rows as $row) {
            $pluginName = trim((string)($row['plugin_name'] ?? ''));
            if ($pluginName === '' || self::isReservedPluginName($pluginName)) {
                continue;
            }
            $map[$pluginName] = $row;
        }

        return $map;
    }

    private function getAuthorizedPluginIndex(): array
    {
        $pluginNames = [];

        if ($this->hasTable('admin_plugin_license')) {
            // 优先以授权表为准：只识别当前站点已授权且启用的插件
            $licenseRows = Db::name('admin_plugin_license')
                ->where('aid', $this->aid)
                ->where('status', 1)
                ->where(function ($query) {
                    $query->where('expire_time', 0)->whereOr('expire_time', '>', time());
                })
                ->column('plugin_name');

            foreach ($licenseRows as $pluginName) {
                $pluginName = trim((string)$pluginName);
                if ($pluginName !== '') {
                    $pluginNames[$pluginName] = true;
                }
            }
        } elseif ($this->hasTable('admin_plugin_menu')) {
            // 兼容历史：没有授权表时，回退到菜单表识别
            $menuRows = Db::name('admin_plugin_menu')
                ->where('aid', $this->aid)
                ->group('plugin_name')
                ->column('plugin_name');

            foreach ($menuRows as $pluginName) {
                $pluginName = trim((string)$pluginName);
                if ($pluginName !== '') {
                    $pluginNames[$pluginName] = true;
                }
            }
        }

        if (empty($pluginNames)) {
            return [];
        }

        $result = [];
        foreach (array_keys($pluginNames) as $pluginName) {
            $pluginInfo = self::loadPluginInfo($pluginName);
            $pluginTitle = trim((string)($pluginInfo['title'] ?? ''));
            if ($pluginTitle === '') {
                $pluginTitle = $pluginName;
            }

            $result[$pluginName] = [
                'plugin_name' => $pluginName,
                'plugin_title' => $pluginTitle,
                'plugin_description' => trim((string)($pluginInfo['description'] ?? '')),
                'plugin_version' => trim((string)($pluginInfo['version'] ?? '')),
            ];
        }

        return $result;
    }

    private function getPluginMenuMetaMap(): array
    {
        if (!$this->hasTable('admin_plugin_menu')) {
            return [];
        }

        $rows = Db::name('admin_plugin_menu')
            ->where('aid', $this->aid)
            ->order('sort asc,id asc')
            ->select()
            ->toArray();

        if (empty($rows)) {
            return [];
        }

        $grouped = [];
        foreach ($rows as $row) {
            $pluginName = trim((string)($row['plugin_name'] ?? ''));
            if ($pluginName === '') {
                continue;
            }
            if (!isset($grouped[$pluginName])) {
                $grouped[$pluginName] = [];
            }
            $grouped[$pluginName][] = $row;
        }

        $result = [];
        foreach ($grouped as $pluginName => $items) {
            $idSet = [];
            foreach ($items as $item) {
                $idSet[intval($item['id'] ?? 0)] = true;
            }

            $roots = [];
            foreach ($items as $item) {
                $pid = intval($item['pid'] ?? 0);
                if (!isset($idSet[$pid])) {
                    $roots[] = $item;
                }
            }

            usort($roots, static function (array $a, array $b) {
                $sortCompare = intval($a['sort'] ?? 0) <=> intval($b['sort'] ?? 0);
                if ($sortCompare !== 0) {
                    return $sortCompare;
                }

                return intval($a['id'] ?? 0) <=> intval($b['id'] ?? 0);
            });

            $pluginInfo = self::loadPluginInfo($pluginName);
            $pluginTitle = trim((string)($pluginInfo['title'] ?? ''));
            if ($pluginTitle === '') {
                $pluginTitle = trim((string)($roots[0]['name'] ?? ''));
            }
            if ($pluginTitle === '') {
                $pluginTitle = $pluginName;
            }

            $rootNames = array_values(array_filter(array_map(static function (array $root) {
                return trim((string)($root['name'] ?? ''));
            }, $roots)));

            $result[$pluginName] = [
                'plugin_name' => $pluginName,
                'plugin_title' => $pluginTitle,
                'plugin_description' => trim((string)($pluginInfo['description'] ?? '')),
                'plugin_version' => trim((string)($pluginInfo['version'] ?? '')),
                'root_menu_count' => count($roots),
                'root_menu_names' => $rootNames,
                'menu_tree' => self::buildPluginMenuTree($items),
                'default_name' => trim((string)($roots[0]['name'] ?? '')) ?: $pluginTitle,
                'default_icon' => trim((string)($roots[0]['icon'] ?? '')),
                'default_sort' => intval($roots[0]['sort'] ?? 0),
            ];
        }

        return $result;
    }

    private static function buildPluginMenuTree(array $items): array
    {
        $idSet = [];
        $childrenOf = [];
        foreach ($items as $item) {
            $idSet[intval($item['id'] ?? 0)] = true;
        }
        foreach ($items as $item) {
            $pid = intval($item['pid'] ?? 0);
            if (isset($idSet[$pid])) {
                $childrenOf[$pid][] = $item;
            }
        }

        $sortMenus = static function (array $rows): array {
            usort($rows, static function (array $a, array $b) {
                $sortCompare = intval($a['sort'] ?? 0) <=> intval($b['sort'] ?? 0);
                if ($sortCompare !== 0) {
                    return $sortCompare;
                }
                return intval($a['id'] ?? 0) <=> intval($b['id'] ?? 0);
            });
            return $rows;
        };

        $build = function (array $menu, string $parentKey) use (&$build, $childrenOf, $sortMenus): array {
            $itemKey = self::buildMenuItemKey($menu, $parentKey);
            $childRows = $sortMenus($childrenOf[intval($menu['id'] ?? 0)] ?? []);
            $children = [];
            foreach ($childRows as $child) {
                $children[] = $build($child, $itemKey);
            }

            return [
                'item_key' => $itemKey,
                'name' => trim((string)($menu['name'] ?? '')),
                'path' => trim((string)($menu['path'] ?? '')),
                'has_children' => $children ? 1 : 0,
                'children' => $children,
            ];
        };

        $tree = [];
        foreach ($sortMenus(array_values(array_filter($items, static function (array $item) use ($idSet) {
            return !isset($idSet[intval($item['pid'] ?? 0)]);
        }))) as $root) {
            $tree[] = $build($root, '');
        }

        return $tree;
    }

    private function ensureSchema(): void
    {
        if (!$this->tableExists()) {
            $this->createTable();
            return;
        }

        $this->ensureColumns();
    }

    private function tableExists(): bool
    {
        return $this->hasTable($this->table);
    }

    private function hasTable(string $tableName): bool
    {
        try {
            $prefix = config('database.connections.mysql.prefix');
            $fullTableName = $prefix . $tableName;
            $result = Db::query("SHOW TABLES LIKE '{$fullTableName}'");
            return !empty($result);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function ensureColumns(): void
    {
        $prefix = config('database.connections.mysql.prefix');
        $fullTableName = $prefix . $this->table;
        $columns = Db::query("SHOW COLUMNS FROM `{$fullTableName}`");
        $fields = array_column($columns, 'Field');

        $columnSql = [
            'custom_name' => "ALTER TABLE `{$fullTableName}` ADD COLUMN `custom_name` varchar(100) NOT NULL DEFAULT '' COMMENT '自定义一级菜单名称' AFTER `plugin_name`",
            'custom_icon' => "ALTER TABLE `{$fullTableName}` ADD COLUMN `custom_icon` varchar(100) NOT NULL DEFAULT '' COMMENT '自定义一级菜单图标' AFTER `custom_name`",
            'sort' => "ALTER TABLE `{$fullTableName}` ADD COLUMN `sort` int(11) NOT NULL DEFAULT '0' COMMENT '排序' AFTER `custom_icon`",
            'status' => "ALTER TABLE `{$fullTableName}` ADD COLUMN `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否提升为一级菜单' AFTER `sort`",
            'promote_items' => "ALTER TABLE `{$fullTableName}` ADD COLUMN `promote_items` text COMMENT '提到主菜单的菜单项' AFTER `status`",
            'promote_icons' => "ALTER TABLE `{$fullTableName}` ADD COLUMN `promote_icons` text COMMENT '提到主菜单的菜单项图标' AFTER `promote_items`",
            'createtime' => "ALTER TABLE `{$fullTableName}` ADD COLUMN `createtime` int(11) NOT NULL DEFAULT '0' COMMENT '创建时间' AFTER `promote_icons`",
            'updatetime' => "ALTER TABLE `{$fullTableName}` ADD COLUMN `updatetime` int(11) NOT NULL DEFAULT '0' COMMENT '更新时间' AFTER `createtime`",
        ];

        foreach ($columnSql as $field => $sql) {
            if (!in_array($field, $fields, true)) {
                Db::execute($sql);
            }
        }
    }

    private function createTable(): void
    {
        $prefix = config('database.connections.mysql.prefix');
        $sql = "CREATE TABLE IF NOT EXISTS `{$prefix}{$this->table}` (
          `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键ID',
          `aid` int(11) NOT NULL DEFAULT '0' COMMENT '站点ID',
          `plugin_name` varchar(64) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT '' COMMENT '插件标识',
          `custom_name` varchar(100) NOT NULL DEFAULT '' COMMENT '自定义一级菜单名称',
          `custom_icon` varchar(100) NOT NULL DEFAULT '' COMMENT '自定义一级菜单图标',
          `sort` int(11) NOT NULL DEFAULT '0' COMMENT '排序',
          `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否提升为一级菜单',
          `promote_items` text COMMENT '提到主菜单的菜单项',
          `promote_icons` text COMMENT '提到主菜单的菜单项图标',
          `createtime` int(11) NOT NULL DEFAULT '0' COMMENT '创建时间',
          `updatetime` int(11) NOT NULL DEFAULT '0' COMMENT '更新时间',
          PRIMARY KEY (`id`),
          UNIQUE KEY `uniq_aid_plugin` (`aid`,`plugin_name`),
          KEY `idx_aid_status` (`aid`,`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=COMPACT COMMENT='站点插件一级菜单配置表'";

        Db::execute($sql);
    }

    private static function loadPluginInfo(string $pluginName): array
    {
        $pluginName = trim($pluginName);
        if ($pluginName === '') {
            return [];
        }

        $configFile = app()->getRootPath() . "app/plugin/{$pluginName}/plugin.json";
        if (!is_file($configFile)) {
            return [];
        }

        $content = @file_get_contents($configFile);
        if ($content === false || $content === '') {
            return [];
        }

        $info = json_decode($content, true);
        return is_array($info) ? $info : [];
    }

    /**
     * H5 插件页面没有对应的 Vue 页面。提到主菜单后如果仍跳 vue_path，内容区是空的。
     * 这种菜单改去插件管理页，并由 legacy 参数指定原来的 ThinkPHP 地址。
     */
    public static function legacyAdminVuePath(array $menu): string
    {
        if (!array_key_exists('use_vue', $menu) || intval($menu['use_vue']) !== 0) {
            return '';
        }

        $pluginName = trim((string)($menu['plugin_name'] ?? ''));
        $path = trim((string)($menu['path'] ?? ''));
        if ($pluginName === '' || $path === '') {
            return '';
        }

        if (preg_match('#^(https?:|external:)#i', $path)) {
            return '';
        }

        $vuePath = trim((string)($menu['vue_path'] ?? ''));
        if (preg_match('#^(https?:|external:)#i', $vuePath)) {
            return '';
        }

        return '/plugin/manage?plugin=' . rawurlencode($pluginName) . '&legacy=' . rawurlencode($path);
    }
}
