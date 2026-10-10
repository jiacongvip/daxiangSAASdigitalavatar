<?php

namespace app\admin\common;

use think\facade\Db;

class PluginDashboardRegistry
{
    private $aid = 0;
    private $bid = 0;
    private $dataScope = 'platform';
    private $warnings = [];
    private $tableExistsCache = [];
    private $columnExistsCache = [];

    public function __construct(int $aid = 0, int $bid = 0, $dataScope = 'platform')
    {
        $this->aid = max(0, $aid);
        $this->bid = max(0, $bid);
        $this->dataScope = $this->normalizeDataScope($dataScope);
    }

    public function getDashboardData(string $scene = 'stats', string $scope = 'site'): array
    {
        $scene = $this->normalizeScene($scene);
        $pluginNames = $this->getInstalledPluginNames($scope);
        $blocks = [];
        $stats = [];
        $declaredPluginNames = [];

        foreach ($pluginNames as $pluginName) {
            $manifest = $this->loadPluginManifest($pluginName);
            if (empty($manifest)) {
                continue;
            }

            if (!$this->matchSceneList($manifest['scene'] ?? ($manifest['scenes'] ?? ['stats']), $scene)) {
                continue;
            }

            $normalizedBlocks = $this->normalizeBlocks($pluginName, $manifest['blocks'] ?? [], $scene);
            if (empty($normalizedBlocks)) {
                continue;
            }

            $declaredPluginNames[] = $pluginName;
            $blocks = array_merge($blocks, $normalizedBlocks);
            $stats = array_merge($stats, $this->resolveMetrics($pluginName, $manifest['metrics'] ?? []));
        }

        usort($blocks, static function (array $a, array $b) {
            $sortCompare = intval($a['sort'] ?? 999) <=> intval($b['sort'] ?? 999);
            if ($sortCompare !== 0) {
                return $sortCompare;
            }
            return strcmp((string)($a['title'] ?? ''), (string)($b['title'] ?? ''));
        });

        $declaredPluginNames = array_values(array_unique(array_filter($declaredPluginNames)));
        sort($declaredPluginNames);
        $undeclaredPluginNames = array_values(array_diff($pluginNames, $declaredPluginNames));
        sort($undeclaredPluginNames);

        return [
            'scene' => $scene,
            'blocks' => $blocks,
            'stats' => $stats,
            'summary' => [
                'installedPluginNames' => $pluginNames,
                'declaredPluginNames' => $declaredPluginNames,
                'undeclaredPluginNames' => $undeclaredPluginNames,
                'warnings' => $this->warnings,
            ],
        ];
    }

    private function normalizeBlocks(string $pluginName, array $blocks, string $scene): array
    {
        $result = [];
        foreach ($blocks as $block) {
            if (!is_array($block) || !$this->matchSceneList($block['scene'] ?? ($block['scenes'] ?? ['stats']), $scene)) {
                continue;
            }

            $type = trim((string)($block['type'] ?? ''));
            if (!in_array($type, ['metric-grid', 'quick-links'], true)) {
                continue;
            }

            $blockId = $this->normalizeKey((string)($block['id'] ?? ''), 'block');
            $items = [];
            foreach (($block['items'] ?? []) as $item) {
                if (!is_array($item)) {
                    continue;
                }
                if ($type === 'metric-grid') {
                    $normalized = $this->normalizeMetricItem($pluginName, $item);
                } else {
                    $normalized = $this->normalizeQuickItem($pluginName, $item);
                }
                if (!empty($normalized)) {
                    $items[] = $normalized;
                }
            }

            if (empty($items)) {
                continue;
            }

            $result[] = [
                'id' => 'plugin_' . $pluginName . '_' . $blockId,
                'type' => $type,
                'title' => trim((string)($block['title'] ?? '插件数据')),
                'description' => trim((string)($block['description'] ?? '')),
                'icon' => $this->normalizeIcon($block['icon'] ?? 'AppstoreOutlined'),
                'visible' => intval($block['visible'] ?? 1) !== 0,
                'audience' => $this->normalizeAudience($block['audience'] ?? 'all'),
                'sort' => intval($block['sort'] ?? 800),
                'sourceType' => 'plugin',
                'sourceName' => $pluginName,
                'items' => $items,
            ];
        }

        return $result;
    }

    private function normalizeMetricItem(string $pluginName, array $item): array
    {
        $valueKey = trim((string)($item['valueKey'] ?? ($item['metric'] ?? '')));
        if ($valueKey === '') {
            return [];
        }

        $result = [
            'id' => 'plugin_' . $pluginName . '_' . $this->normalizeKey((string)($item['id'] ?? $valueKey), 'metric'),
            'title' => trim((string)($item['title'] ?? $valueKey)),
            'icon' => $this->normalizeIcon($item['icon'] ?? 'AppstoreOutlined'),
            'tone' => $this->normalizeTone($item['tone'] ?? 'slate'),
            'valueKey' => $this->namespaceMetricKey($pluginName, $valueKey),
            'format' => $this->normalizeFormat($item['format'] ?? 'number'),
            'prefix' => (string)($item['prefix'] ?? ''),
            'suffix' => (string)($item['suffix'] ?? ''),
            'deltaKey' => $this->namespaceOptionalMetricKey($pluginName, $item['deltaKey'] ?? ''),
            'deltaPrefix' => (string)($item['deltaPrefix'] ?? ''),
            'deltaSuffix' => (string)($item['deltaSuffix'] ?? ''),
            'footer' => $this->normalizeMetricFooter($pluginName, $item['footer'] ?? []),
            'route' => trim((string)($item['route'] ?? '')),
            'width' => $this->normalizeWidth($item['width'] ?? 3),
            'visible' => intval($item['visible'] ?? 1) !== 0,
            'audience' => $this->normalizeAudience($item['audience'] ?? 'all'),
            'sourceType' => 'plugin',
            'sourceName' => $pluginName,
        ];

        if ($result['deltaKey'] === '') {
            unset($result['deltaKey']);
        }
        if (empty($result['footer'])) {
            unset($result['footer']);
        }

        return $result;
    }

    private function normalizeQuickItem(string $pluginName, array $item): array
    {
        $route = trim((string)($item['route'] ?? ($item['path'] ?? '')));
        $externalUrl = trim((string)($item['externalUrl'] ?? ($item['h5Url'] ?? ($item['url'] ?? ''))));
        $openMode = trim((string)($item['openMode'] ?? ($item['target'] ?? '')));
        if ($externalUrl === '' && preg_match('/^https?:\/\//i', $route)) {
            $externalUrl = $route;
        }
        $title = trim((string)($item['title'] ?? ($item['name'] ?? '')));
        if ($route === '' || $title === '') {
            return [];
        }

        $result = [
            'id' => 'plugin_' . $pluginName . '_' . $this->normalizeKey((string)($item['id'] ?? $title), 'quick'),
            'title' => $title,
            'description' => trim((string)($item['description'] ?? ($item['desc'] ?? ''))),
            'icon' => $this->normalizeIcon($item['icon'] ?? 'AppstoreOutlined'),
            'tone' => $this->normalizeTone($item['tone'] ?? 'slate'),
            'route' => $route,
            'width' => $this->normalizeWidth($item['width'] ?? 3),
            'visible' => intval($item['visible'] ?? 1) !== 0,
            'audience' => $this->normalizeAudience($item['audience'] ?? 'all'),
            'sourceType' => 'plugin',
            'sourceName' => $pluginName,
        ];

        if ($externalUrl !== '') {
            $result['externalUrl'] = $externalUrl;
        }
        if (in_array($openMode, ['external', 'blank', '_blank'], true) || $externalUrl !== '') {
            $result['openMode'] = 'external';
        }

        return $result;
    }

    private function normalizeMetricFooter(string $pluginName, $footer): array
    {
        if (!is_array($footer)) {
            return [];
        }

        $result = [];
        foreach ($footer as $item) {
            if (!is_array($item)) {
                continue;
            }
            $valueKey = trim((string)($item['valueKey'] ?? ($item['metric'] ?? '')));
            $label = trim((string)($item['label'] ?? ''));
            if ($valueKey === '' || $label === '') {
                continue;
            }
            $result[] = [
                'label' => $label,
                'valueKey' => $this->namespaceMetricKey($pluginName, $valueKey),
                'prefix' => (string)($item['prefix'] ?? ''),
                'suffix' => (string)($item['suffix'] ?? ''),
                'tone' => $this->normalizeTone($item['tone'] ?? 'slate'),
            ];
        }
        return $result;
    }

    private function resolveMetrics(string $pluginName, $metrics): array
    {
        if (!is_array($metrics)) {
            return [];
        }

        $result = [];
        foreach ($metrics as $key => $metric) {
            $metricKey = $this->normalizeKey((string)$key, 'metric');
            if ($metricKey === '') {
                continue;
            }

            $result[$this->namespaceMetricKey($pluginName, $metricKey)] = $this->resolveMetricValue($pluginName, $metricKey, is_array($metric) ? $metric : []);
        }
        return $result;
    }

    private function resolveMetricValue(string $pluginName, string $metricKey, array $metric)
    {
        $table = $this->sanitizeIdentifier((string)($metric['table'] ?? ''));
        $aggregate = strtolower(trim((string)($metric['aggregate'] ?? 'count')));
        if ($table === '' || !$this->tableExists($table)) {
            $this->warnings[] = $pluginName . '.' . $metricKey . ': table not found';
            return 0;
        }
        if (!in_array($aggregate, ['count', 'distinct_count', 'sum', 'avg', 'min', 'max'], true)) {
            $this->warnings[] = $pluginName . '.' . $metricKey . ': aggregate not allowed';
            return 0;
        }

        try {
            $query = Db::name($table);
            if ($this->columnExists($table, 'aid')) {
                $query->where('aid', $this->aid);
            }
            if ($this->shouldApplyBid($table, $metric)) {
                $query->where('bid', $this->bid);
            }

            $this->applyWhere($query, $table, $metric['where'] ?? []);
            $this->applyTimeRange($query, $table, $metric);

            if ($aggregate === 'count') {
                return 0 + $query->count();
            }

            $field = $this->sanitizeIdentifier((string)($metric['field'] ?? ''));
            if ($field === '' || !$this->columnExists($table, $field)) {
                $this->warnings[] = $pluginName . '.' . $metricKey . ': field not found';
                return 0;
            }

            if ($aggregate === 'distinct_count') {
                return 0 + $query->distinct(true)->count($field);
            }

            $value = $query->{$aggregate}($field);
            if (is_numeric($value)) {
                return in_array($aggregate, ['sum', 'avg'], true) ? number_format((float)$value, 2, '.', '') : 0 + $value;
            }
            return $value ?: 0;
        } catch (\Throwable $e) {
            $this->warnings[] = $pluginName . '.' . $metricKey . ': ' . $e->getMessage();
            return 0;
        }
    }

    private function applyWhere($query, string $table, $where): void
    {
        if (!is_array($where)) {
            return;
        }

        foreach ($where as $key => $condition) {
            if (is_string($key)) {
                $field = $this->sanitizeIdentifier($key);
                if ($field !== '' && $this->columnExists($table, $field)) {
                    $query->where($field, $condition);
                }
                continue;
            }

            if (!is_array($condition) || count($condition) < 3) {
                continue;
            }

            $field = $this->sanitizeIdentifier((string)$condition[0]);
            $operator = strtolower(trim((string)$condition[1]));
            $value = $condition[2];
            if ($field === '' || !$this->columnExists($table, $field)) {
                continue;
            }
            if (!in_array($operator, ['=', '<>', '>', '>=', '<', '<=', 'in', 'not in', 'like'], true)) {
                continue;
            }
            if (in_array($operator, ['in', 'not in'], true) && !is_array($value)) {
                $value = array_values(array_filter(array_map('trim', explode(',', (string)$value)), static function ($item) {
                    return $item !== '';
                }));
            }
            $query->where($field, $operator, $value);
        }
    }

    private function applyTimeRange($query, string $table, array $metric): void
    {
        $field = $this->sanitizeIdentifier((string)($metric['timeField'] ?? ''));
        $range = trim((string)($metric['timeRange'] ?? ''));
        if ($field === '' || $range === '' || !$this->columnExists($table, $field)) {
            return;
        }

        $now = time();
        $todayStart = strtotime(date('Y-m-d', $now));
        $start = 0;
        $end = 0;

        if ($range === 'today') {
            $start = $todayStart;
            $end = $now + 1;
        } elseif ($range === 'yesterday') {
            $start = $todayStart - 86400;
            $end = $todayStart;
        } elseif ($range === 'this_month') {
            $start = strtotime(date('Y-m-1', $now));
            $end = $now + 1;
        } elseif ($range === 'last_30_days') {
            $start = $todayStart - 86400 * 29;
            $end = $now + 1;
        }

        if ($start > 0 && $end > $start) {
            $query->where($field, '>=', $start)->where($field, '<', $end);
        }
    }

    private function shouldApplyBid(string $table, array $metric): bool
    {
        if (!$this->columnExists($table, 'bid')) {
            return false;
        }
        if ($this->bid === 0 && $this->dataScope === 'all') {
            return false;
        }
        if (isset($metric['applyBid'])) {
            return intval($metric['applyBid']) === 1;
        }
        return ($metric['scope'] ?? '') === 'bid';
    }

    private function normalizeDataScope($dataScope): string
    {
        $value = strtolower(trim((string)$dataScope));
        return in_array($value, ['all', 'all_bids', 'all_business', 'platform_and_merchants'], true) ? 'all' : 'platform';
    }

    private function getInstalledPluginNames(string $scope = 'site'): array
    {
        if ($scope === 'global') {
            return $this->getGlobalPluginNames();
        }

        // 站点驾驶舱只认当前站点的有效授权。
        // 插件安装会给全部站点写入 admin_plugin_menu，菜单存在不代表该站点已授权。
        if ($this->checkTableExists('admin_plugin_license') && $this->aid > 0) {
            $pluginNames = Db::name('admin_plugin_license')
                ->where('aid', $this->aid)
                ->where('status', 1)
                ->where('plugin_name', '<>', '')
                ->where(function ($query) {
                    $query->where('expire_time', 0)->whereOr('expire_time', '>', time());
                })
                ->column('plugin_name');
            $pluginNames = array_values(array_unique(array_filter(array_map('trim', $pluginNames))));
            sort($pluginNames);
            return $pluginNames;
        }

        return $this->getEnabledMenuPluginNames($this->aid > 0);
    }

    private function getGlobalPluginNames(): array
    {
        $pluginNames = [];
        if ($this->checkTableExists('admin_plugin_license')) {
            $licenseNames = Db::name('admin_plugin_license')
                ->where('status', 1)
                ->where('plugin_name', '<>', '')
                ->column('plugin_name');
            $pluginNames = array_merge($pluginNames, $licenseNames);
        }

        $pluginNames = array_merge($pluginNames, $this->getEnabledMenuPluginNames(false));
        $pluginNames = array_merge($pluginNames, $this->getManifestPluginNames());
        $pluginNames = array_values(array_unique(array_filter(array_map('trim', $pluginNames))));
        sort($pluginNames);
        return $pluginNames;
    }

    private function getEnabledMenuPluginNames(bool $limitAid): array
    {
        if (!$this->checkTableExists('admin_plugin_menu')) {
            return [];
        }

        $query = Db::name('admin_plugin_menu')
            ->field('plugin_name,MAX(status) as max_status')
            ->where('plugin_name', '<>', '');
        if ($limitAid && $this->aid > 0 && $this->columnExists('admin_plugin_menu', 'aid')) {
            $query->where('aid', $this->aid);
        }

        $pluginNames = [];
        $rows = $query->group('plugin_name')->select()->toArray();
        foreach ($rows as $row) {
            if (intval($row['max_status'] ?? 0) === 1) {
                $pluginNames[] = trim((string)($row['plugin_name'] ?? ''));
            }
        }

        return array_values(array_unique(array_filter($pluginNames)));
    }

    private function loadPluginManifest(string $pluginName): array
    {
        $file = $this->resolvePluginDashboardFile($pluginName);
        if ($file !== '') {
            $data = include $file;
            return is_array($data) ? $data : [];
        }

        $jsonFile = $this->resolvePluginJsonFile($pluginName);
        if ($jsonFile === '') {
            return [];
        }

        $decoded = json_decode((string)file_get_contents($jsonFile), true);
        if (!is_array($decoded) || empty($decoded['dashboard']) || !is_array($decoded['dashboard'])) {
            return [];
        }
        return $decoded['dashboard'];
    }

    private function getManifestPluginNames(): array
    {
        $files = [];
        foreach ($this->getPluginBaseDirs() as $baseDir) {
            $matches = glob(rtrim($baseDir, '/') . '/*/dashboard-widgets.php') ?: [];
            if (!empty($matches)) {
                $files = array_merge($files, $matches);
            }
        }

        $pluginNames = [];
        foreach ($files as $file) {
            $pluginName = basename(dirname($file));
            if ($pluginName !== '') {
                $pluginNames[] = trim($pluginName);
            }
        }

        $pluginNames = array_values(array_unique(array_filter($pluginNames)));
        sort($pluginNames);
        return $pluginNames;
    }

    private function resolvePluginDashboardFile(string $pluginName): string
    {
        $pluginName = $this->sanitizeIdentifier($pluginName);
        if ($pluginName === '') {
            return '';
        }

        foreach ($this->getPluginBaseDirs() as $baseDir) {
            $file = rtrim($baseDir, '/') . '/' . $pluginName . '/dashboard-widgets.php';
            if (is_file($file)) {
                return $file;
            }
        }
        return '';
    }

    private function resolvePluginJsonFile(string $pluginName): string
    {
        $pluginName = $this->sanitizeIdentifier($pluginName);
        if ($pluginName === '') {
            return '';
        }

        foreach ($this->getPluginBaseDirs() as $baseDir) {
            $file = rtrim($baseDir, '/') . '/' . $pluginName . '/plugin.json';
            if (is_file($file)) {
                return $file;
            }
        }
        return '';
    }

    private function getPluginBaseDirs(): array
    {
        $dirs = [
            app()->getRootPath() . 'app/plugin',
            app()->getRootPath() . '../app/plugin',
            dirname(__DIR__, 2) . '/plugin',
        ];

        $result = [];
        foreach ($dirs as $dir) {
            $real = realpath($dir);
            if ($real && is_dir($real)) {
                $result[] = rtrim($real, '/');
            }
        }

        return array_values(array_unique($result));
    }

    private function namespaceMetricKey(string $pluginName, string $key): string
    {
        $key = $this->normalizeKey($key, 'metric');
        $prefix = 'plugin_' . $pluginName . '_';
        if (strpos($key, $prefix) === 0) {
            return $key;
        }
        return $prefix . $key;
    }

    private function namespaceOptionalMetricKey(string $pluginName, $key): string
    {
        $key = trim((string)$key);
        return $key !== '' ? $this->namespaceMetricKey($pluginName, $key) : '';
    }

    private function normalizeScene(string $scene): string
    {
        $scene = preg_replace('/[^a-zA-Z0-9_\-]/', '', $scene);
        $scene = substr(trim((string)$scene), 0, 64);
        return $scene !== '' ? $scene : 'stats';
    }

    private function matchSceneList($scenes, string $scene): bool
    {
        if (!is_array($scenes)) {
            $scenes = [(string)$scenes];
        }
        if (in_array('*', array_map('trim', array_map('strval', $scenes)), true)) {
            return true;
        }
        $scenes = array_values(array_filter(array_map(function ($item) {
            return $this->normalizeScene((string)$item);
        }, $scenes)));
        if (empty($scenes)) {
            return true;
        }
        return in_array($scene, $scenes, true) || in_array('*', $scenes, true);
    }

    private function normalizeKey(string $value, string $fallback): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $value);
        $value = trim($value, '_-');
        return $value !== '' ? substr($value, 0, 80) : $fallback;
    }

    private function normalizeAudience($audience): string
    {
        $audience = trim((string)$audience);
        return in_array($audience, ['all', 'platform', 'business'], true) ? $audience : 'all';
    }

    private function normalizeTone($tone): string
    {
        $tone = trim((string)$tone);
        $allowed = ['blue', 'purple', 'amber', 'rose', 'green', 'orange', 'red', 'indigo', 'cyan', 'slate'];
        return in_array($tone, $allowed, true) ? $tone : 'slate';
    }

    private function normalizeFormat($format): string
    {
        $format = trim((string)$format);
        return in_array($format, ['number', 'currency'], true) ? $format : 'number';
    }

    private function normalizeIcon($icon): string
    {
        $icon = trim((string)$icon);
        return preg_match('/^[a-zA-Z0-9_]+$/', $icon) ? $icon : 'AppstoreOutlined';
    }

    private function normalizeWidth($width): int
    {
        $width = intval($width);
        return in_array($width, [3, 4, 6, 12], true) ? $width : 3;
    }

    private function sanitizeIdentifier(string $value): string
    {
        $value = trim($value);
        return preg_match('/^[a-zA-Z0-9_]+$/', $value) ? $value : '';
    }

    private function checkTableExists(string $table): bool
    {
        return $this->tableExists($table);
    }

    private function tableExists(string $table): bool
    {
        $table = $this->sanitizeIdentifier($table);
        if ($table === '') {
            return false;
        }
        if (array_key_exists($table, $this->tableExistsCache)) {
            return $this->tableExistsCache[$table];
        }

        try {
            $prefix = config('database.connections.mysql.prefix', '');
            $rows = Db::query("SHOW TABLES LIKE '{$prefix}{$table}'");
            $this->tableExistsCache[$table] = !empty($rows);
        } catch (\Throwable $e) {
            $this->tableExistsCache[$table] = false;
        }
        return $this->tableExistsCache[$table];
    }

    private function columnExists(string $table, string $column): bool
    {
        $table = $this->sanitizeIdentifier($table);
        $column = $this->sanitizeIdentifier($column);
        if ($table === '' || $column === '' || !$this->tableExists($table)) {
            return false;
        }

        $cacheKey = $table . '.' . $column;
        if (array_key_exists($cacheKey, $this->columnExistsCache)) {
            return $this->columnExistsCache[$cacheKey];
        }

        try {
            $prefix = config('database.connections.mysql.prefix', '');
            $rows = Db::query("SHOW COLUMNS FROM `{$prefix}{$table}` LIKE '{$column}'");
            $this->columnExistsCache[$cacheKey] = !empty($rows);
        } catch (\Throwable $e) {
            $this->columnExistsCache[$cacheKey] = false;
        }
        return $this->columnExistsCache[$cacheKey];
    }
}
