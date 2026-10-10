<?php
/**
 * 用户业绩缓存服务类
 * 专门处理用户业绩数据的缓存管理
 *
 * @author 开发团队
 * @date 2025-01-27
 * @version 1.0
 */

namespace app\common;

use think\facade\Db;
use think\facade\Log;

class PerformanceCacheService
{
    // 缓存类型常量
    const CACHE_MINUTE = 'minute';    // 分钟缓存
    const CACHE_HOURLY = 'hourly';    // 小时缓存
    const CACHE_DAILY = 'daily';      // 日缓存
    const CACHE_MONTHLY = 'monthly';  // 月缓存
    const CACHE_TOTAL = 'total';      // 累计缓存
    const CACHE_CUSTOM = 'custom';    // 自定义时间段缓存

    const RELATION_DIRECT = 'direct'; // 直推关系(pid)
    const RELATION_NODE = 'node';     // 接点关系(jiedianpid)

    const METHOD_SALES = 'sales';
    const METHOD_COST = 'cost';
    const METHOD_PROFIT = 'profit';
    const DEFAULT_SCOPE_HASH = 'default';

    /**
     * 获取用户业绩缓存数据
     * @param int $mid 会员ID
     * @param string $cacheType 缓存类型：minute/hourly/daily/monthly/total
     * @param string $date 日期，格式：Y-m-d，为空则使用当前日期
     * @param int $aid 站点ID
     * @return array|null 业绩数据
     */
    public static function getPerformance($mid, $cacheType = self::CACHE_DAILY, $date = '', $aid = null, array $options = [])
    {
        $startTime = microtime(true);
        $aid = $aid ?: (defined('aid') ? aid : 0);
        $date = self::normalizeCacheDate($cacheType, $date ?: date('Y-m-d'));
        $scope = self::normalizeSnapshotOptions($options);

        try {
            self::ensureSchema();

            // 从缓存表获取数据
            $cacheData = Db::name('member_performance_cache')
                ->where('aid', $aid)
                ->where('mid', $mid)
                ->where('cache_date', $date)
                ->where('cache_type', $cacheType)
                ->where('relation_mode', $scope['relation_mode'])
                ->where('amount_method', $scope['amount_method'])
                ->where('scope_hash', $scope['scope_hash'])
                ->where('is_valid', 1)
                ->find();

            if ($cacheData) {
                // 记录命中日志
                self::logOperation($aid, $mid, 'get', 'hit', microtime(true) - $startTime, $cacheType);

                // 格式化返回数据
                return self::formatPerformanceData($cacheData);
            }

            // 缓存未命中，尝试计算
            self::logOperation($aid, $mid, 'get', 'miss', microtime(true) - $startTime, $cacheType);

            // 自动计算并缓存
            return self::calculateAndCache($mid, $cacheType, $date, $aid, $options);

        } catch (\Exception $e) {
            Log::error('获取业绩缓存异常：' . $e->getMessage());
            self::logOperation($aid, $mid, 'get', 'failed', microtime(true) - $startTime, $cacheType, 0, 0, 'system', '', $e->getMessage());
            return null;
        }
    }

    /**
     * 计算并缓存用户业绩数据
     * @param int $mid 会员ID
     * @param string $cacheType 缓存类型
     * @param string $date 日期
     * @param int $aid 站点ID
     * @return array|null 计算结果
     */
    public static function calculateAndCache($mid, $cacheType = self::CACHE_DAILY, $date = '', $aid = null, array $options = [])
    {
        $startTime = microtime(true);
        $aid = $aid ?: (defined('aid') ? aid : 0);
        $date = self::normalizeCacheDate($cacheType, $date ?: date('Y-m-d'));
        $scope = self::normalizeSnapshotOptions($options);

        try {
            self::rebuildPerformanceSnapshot($aid, $cacheType, $date, $options, [intval($mid)]);

            $cacheData = Db::name('member_performance_cache')
                ->where('aid', $aid)
                ->where('mid', intval($mid))
                ->where('cache_date', $date)
                ->where('cache_type', $cacheType)
                ->where('relation_mode', $scope['relation_mode'])
                ->where('amount_method', $scope['amount_method'])
                ->where('scope_hash', $scope['scope_hash'])
                ->where('is_valid', 1)
                ->find();

            if (!$cacheData) return null;

            self::logOperation($aid, $mid, 'calculate', 'success', microtime(true) - $startTime, $cacheType);
            return self::formatPerformanceData($cacheData);

        } catch (\Exception $e) {
            Log::error('计算业绩缓存异常：' . $e->getMessage());
            self::logOperation($aid, $mid, 'calculate', 'failed', microtime(true) - $startTime, $cacheType, 0, 0, 'system', '', $e->getMessage());
            return null;
        }
    }

    /**
     * 批量重建会员业绩快照。
     * 核心思路：订单按会员聚合一次，关系树内存汇总一次，避免逐会员重复扫订单。
     */
    public static function rebuildPerformanceSnapshot($aid, $cacheType = self::CACHE_DAILY, $date = '', array $options = [], array $memberIds = [])
    {
        $startTime = microtime(true);
        $aid = intval($aid ?: (defined('aid') ? aid : 0));
        $date = self::normalizeCacheDate($cacheType, $date ?: date('Y-m-d'));
        $scope = self::normalizeSnapshotOptions($options);
        [$periodStart, $periodEnd] = self::resolvePeriodRange($cacheType, $date, $scope);

        self::ensureSchema();

        $memberRows = Db::name('member')
            ->where('aid', $aid)
            ->field('id,pid,jiedianpid,levelid')
            ->select()
            ->toArray();

        if (empty($memberRows)) {
            return [
                'success' => 0,
                'failed' => 0,
                'saved_count' => 0,
                'member_count' => 0,
                'cache_type' => $cacheType,
                'cache_date' => $date,
                'relation_mode' => $scope['relation_mode'],
                'amount_method' => $scope['amount_method'],
                'scope_hash' => $scope['scope_hash'],
                'execution_time' => round(microtime(true) - $startTime, 4),
            ];
        }

        $memberMap = [];
        foreach ($memberRows as $row) {
            $memberMap[intval($row['id'])] = $row;
        }

        $hasMemberFilter = !empty(self::normalizeIntArray($memberIds));
        $targetIds = self::normalizeIntArray($memberIds);
        if (empty($targetIds)) {
            $targetIds = array_keys($memberMap);
        } else {
            $targetIds = array_values(array_filter($targetIds, function ($mid) use ($memberMap) {
                return isset($memberMap[$mid]);
            }));
        }

        if (empty($targetIds)) {
            return [
                'success' => 0,
                'failed' => 0,
                'saved_count' => 0,
                'member_count' => 0,
                'cache_type' => $cacheType,
                'cache_date' => $date,
                'relation_mode' => $scope['relation_mode'],
                'amount_method' => $scope['amount_method'],
                'scope_hash' => $scope['scope_hash'],
                'execution_time' => round(microtime(true) - $startTime, 4),
            ];
        }

        $parentField = $scope['relation_mode'] === self::RELATION_NODE ? 'jiedianpid' : 'pid';
        $childrenByParent = [];
        foreach ($memberRows as $row) {
            $mid = intval($row['id']);
            $pid = intval($row[$parentField] ?? 0);
            if ($pid > 0 && isset($memberMap[$pid]) && $pid !== $mid) {
                $childrenByParent[$pid][] = $mid;
            }
        }

        $selfStats = self::loadSelfPerformanceStats($aid, $periodStart, $periodEnd, $scope);

        $selfPerformance = [];
        $selfOrders = [];
        $lastOrderTime = [];
        foreach ($memberMap as $mid => $row) {
            $selfPerformance[$mid] = floatval($selfStats[$mid]['self_performance'] ?? 0);
            $selfOrders[$mid] = intval($selfStats[$mid]['self_orders'] ?? 0);
            $lastOrderTime[$mid] = intval($selfStats[$mid]['last_order_time'] ?? 0);
        }

        $subtreePerformance = [];
        $subtreeCount = [];
        $subtreeActiveCount = [];
        $teamPerformance = [];
        $teamCount = [];
        $teamActiveCount = [];
        $directPerformance = [];
        $directActiveCount = [];
        $processing = [];

        $compute = function ($mid) use (&$compute, &$processing, &$subtreePerformance, &$subtreeCount, &$subtreeActiveCount, &$teamPerformance, &$teamCount, &$teamActiveCount, &$directPerformance, &$directActiveCount, $childrenByParent, $selfPerformance) {
            $mid = intval($mid);
            if (isset($subtreePerformance[$mid])) {
                return [
                    $subtreePerformance[$mid],
                    $subtreeCount[$mid],
                    $subtreeActiveCount[$mid],
                ];
            }
            if (isset($processing[$mid])) {
                // 关系异常成环时切断，避免死循环。
                return [floatval($selfPerformance[$mid] ?? 0), 1, floatval($selfPerformance[$mid] ?? 0) > 0 ? 1 : 0];
            }

            $processing[$mid] = true;
            $self = floatval($selfPerformance[$mid] ?? 0);
            $subPerf = $self;
            $subCount = 1;
            $subActive = $self > 0 ? 1 : 0;
            $directPerf = 0;
            $directActive = 0;

            foreach (($childrenByParent[$mid] ?? []) as $childId) {
                $childSelf = floatval($selfPerformance[$childId] ?? 0);
                $directPerf += $childSelf;
                if ($childSelf > 0) $directActive++;

                [$childPerf, $childCount, $childActive] = $compute($childId);
                $subPerf += $childPerf;
                $subCount += $childCount;
                $subActive += $childActive;
            }

            unset($processing[$mid]);
            $subtreePerformance[$mid] = $subPerf;
            $subtreeCount[$mid] = $subCount;
            $subtreeActiveCount[$mid] = $subActive;
            $teamPerformance[$mid] = max(0, $subPerf - $self);
            $teamCount[$mid] = max(0, $subCount - 1);
            $teamActiveCount[$mid] = max(0, $subActive - ($self > 0 ? 1 : 0));
            $directPerformance[$mid] = $directPerf;
            $directActiveCount[$mid] = $directActive;

            return [$subPerf, $subCount, $subActive];
        };

        foreach (array_keys($memberMap) as $mid) {
            $compute($mid);
        }

        $now = time();
        $records = [];
        foreach ($targetIds as $mid) {
            $areas = self::calculateAreaPerformance($mid, $childrenByParent, $subtreePerformance);
            $records[] = [
                'aid' => $aid,
                'mid' => $mid,
                'cache_date' => $date,
                'cache_type' => $cacheType,
                'relation_mode' => $scope['relation_mode'],
                'amount_method' => $scope['amount_method'],
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'scope_hash' => $scope['scope_hash'],
                'extra_data' => json_encode($scope['extra_data'], JSON_UNESCAPED_UNICODE),
                'direct_count' => count($childrenByParent[$mid] ?? []),
                'direct_active_count' => intval($directActiveCount[$mid] ?? 0),
                'direct_performance' => round(floatval($directPerformance[$mid] ?? 0), 2),
                'direct_commission' => 0,
                'team_count' => intval($teamCount[$mid] ?? 0),
                'team_active_count' => intval($teamActiveCount[$mid] ?? 0),
                'team_performance' => round(floatval($teamPerformance[$mid] ?? 0), 2),
                'team_commission' => 0,
                'self_orders' => intval($selfOrders[$mid] ?? 0),
                'self_performance' => round(floatval($selfPerformance[$mid] ?? 0), 2),
                'self_commission' => 0,
                'big_area_performance' => round(floatval($areas['big_area_performance']), 2),
                'small_area_performance' => round(floatval($areas['small_area_performance']), 2),
                'big_area_mid' => intval($areas['big_area_mid']),
                'balance_performance' => round(min(floatval($areas['big_area_performance']), floatval($areas['small_area_performance'])), 2),
                'current_level' => intval($memberMap[$mid]['levelid'] ?? 0),
                'level_commission' => 0,
                'leadership_commission' => 0,
                'special_bonus' => 0,
                'last_order_time' => intval($lastOrderTime[$mid] ?? 0),
                'calculation_time' => round(microtime(true) - $startTime, 4),
                'cache_version' => 1,
                'is_valid' => 1,
                'create_time' => $now,
                'update_time' => $now,
            ];
        }

        Db::startTrans();
        try {
            $deleteQuery = Db::name('member_performance_cache')
                ->where('aid', $aid)
                ->where('cache_date', $date)
                ->where('cache_type', $cacheType)
                ->where('relation_mode', $scope['relation_mode'])
                ->where('amount_method', $scope['amount_method'])
                ->where('scope_hash', $scope['scope_hash']);
            if ($hasMemberFilter && !empty($targetIds)) {
                $deleteQuery->whereIn('mid', $targetIds);
            }
            $deleteQuery->delete();

            foreach (array_chunk($records, 500) as $chunk) {
                if (!empty($chunk)) {
                    Db::name('member_performance_cache')->insertAll($chunk);
                }
            }
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        self::logOperation($aid, 0, 'rebuild_snapshot', 'success', microtime(true) - $startTime, $cacheType, count($records), 0, 'system', '', '');

        return [
            'success' => count($records),
            'failed' => max(0, count($targetIds) - count($records)),
            'saved_count' => count($records),
            'member_count' => count($targetIds),
            'cache_type' => $cacheType,
            'cache_date' => $date,
            'relation_mode' => $scope['relation_mode'],
            'amount_method' => $scope['amount_method'],
            'scope_hash' => $scope['scope_hash'],
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'period_text' => date('Y-m-d H:i:s', $periodStart) . ' 至 ' . date('Y-m-d H:i:s', $periodEnd),
            'execution_time' => round(microtime(true) - $startTime, 4),
        ];
    }

    /**
     * 批量读取指定指标权重。
     */
    public static function getMetricWeights($aid, array $mids, $metric, $cacheType = self::CACHE_DAILY, $date = '', array $options = [], $strict = true)
    {
        $aid = intval($aid ?: (defined('aid') ? aid : 0));
        $date = self::normalizeCacheDate($cacheType, $date ?: date('Y-m-d'));
        $scope = self::normalizeSnapshotOptions($options);
        $mids = self::normalizeIntArray($mids);
        $column = self::metricToColumn($metric);

        if (empty($mids)) {
            return ['weights' => [], 'missing' => [], 'total_weight' => 0, 'cached_count' => 0, 'required_count' => 0];
        }

        self::ensureSchema();

        $weights = [];
        foreach (array_chunk($mids, 1000) as $chunk) {
            $rows = Db::name('member_performance_cache')
                ->where('aid', $aid)
                ->where('cache_date', $date)
                ->where('cache_type', $cacheType)
                ->where('relation_mode', $scope['relation_mode'])
                ->where('amount_method', $scope['amount_method'])
                ->where('scope_hash', $scope['scope_hash'])
                ->where('is_valid', 1)
                ->whereIn('mid', $chunk)
                ->field('mid,' . $column)
                ->select()
                ->toArray();
            foreach ($rows as $row) {
                $weights[intval($row['mid'])] = floatval($row[$column] ?? 0);
            }
        }

        $missing = [];
        foreach ($mids as $mid) {
            if (!array_key_exists($mid, $weights)) {
                $missing[] = $mid;
            }
        }

        if ($strict && !empty($missing)) {
            throw new \Exception('业绩缓存不完整，请先重建缓存：缺少 ' . count($missing) . ' 个会员的 ' . $date . ' ' . $scope['relation_mode'] . '/' . $scope['amount_method'] . ' 快照');
        }

        return [
            'weights' => $weights,
            'missing' => $missing,
            'total_weight' => array_sum($weights),
            'cached_count' => count($weights),
            'required_count' => count($mids),
            'metric' => $column,
            'cache_type' => $cacheType,
            'cache_date' => $date,
            'relation_mode' => $scope['relation_mode'],
            'amount_method' => $scope['amount_method'],
            'scope_hash' => $scope['scope_hash'],
        ];
    }

    /**
     * 重建默认快照组合，供定时任务和订单触发队列复用。
     */
    public static function rebuildDefaultSnapshotScopes($aid, $cacheType = self::CACHE_DAILY, $date = '', array $baseOptions = [], array $amountMethods = [], array $relationModes = [])
    {
        $aid = intval($aid ?: (defined('aid') ? aid : 0));
        $date = self::normalizeCacheDate($cacheType, $date ?: date('Y-m-d'));
        if (empty($relationModes)) {
            $relationModes = [self::RELATION_DIRECT, self::RELATION_NODE];
        }
        if (empty($amountMethods)) {
            $amountMethods = [self::METHOD_SALES, self::METHOD_COST, self::METHOD_PROFIT];
        }

        $results = [];
        $savedCount = 0;
        $errors = [];
        foreach ($relationModes as $relationMode) {
            foreach ($amountMethods as $amountMethod) {
                $options = array_merge($baseOptions, [
                    'relation_mode' => $relationMode,
                    'amount_method' => $amountMethod,
                ]);

                try {
                    $result = self::rebuildPerformanceSnapshot($aid, $cacheType, $date, $options);
                    $key = $relationMode . '/' . $amountMethod;
                    $results[$key] = $result;
                    $savedCount += intval($result['saved_count'] ?? 0);
                } catch (\Throwable $e) {
                    $errors[] = $relationMode . '/' . $amountMethod . ': ' . $e->getMessage();
                }
            }
        }

        return [
            'saved_count' => $savedCount,
            'snapshot_count' => count($results),
            'results' => $results,
            'errors' => $errors,
        ];
    }

    public static function getSnapshotCoverage($aid, $cacheType = self::CACHE_DAILY, $date = '', array $options = [], array $memberIds = [])
    {
        $aid = intval($aid ?: (defined('aid') ? aid : 0));
        $date = self::normalizeCacheDate($cacheType, $date ?: date('Y-m-d'));
        $scope = self::normalizeSnapshotOptions($options);
        $memberIds = self::normalizeIntArray($memberIds);
        self::ensureSchema();

        $required = empty($memberIds)
            ? Db::name('member')->where('aid', $aid)->count()
            : count($memberIds);

        $query = Db::name('member_performance_cache')
            ->where('aid', $aid)
            ->where('cache_date', $date)
            ->where('cache_type', $cacheType)
            ->where('relation_mode', $scope['relation_mode'])
            ->where('amount_method', $scope['amount_method'])
            ->where('scope_hash', $scope['scope_hash'])
            ->where('is_valid', 1);

        if (!empty($memberIds)) {
            $query->whereIn('mid', $memberIds);
        }

        $cached = $query->count();
        return [
            'required_count' => intval($required),
            'cached_count' => intval($cached),
            'missing_count' => max(0, intval($required) - intval($cached)),
            'coverage_rate' => intval($required) > 0 ? round((intval($cached) / intval($required)) * 100, 2) : 100,
            'cache_type' => $cacheType,
            'cache_date' => $date,
            'relation_mode' => $scope['relation_mode'],
            'amount_method' => $scope['amount_method'],
            'scope_hash' => $scope['scope_hash'],
        ];
    }

    public static function ensureSchema()
    {
        static $checked = false;
        if ($checked) return;

        $prefix = config('database.connections.mysql.prefix', '');
        $table = $prefix . 'member_performance_cache';

        try {
            self::ensureBaseTables($prefix);

            $columns = self::getTableColumns($table);
            if (empty($columns)) {
                $checked = true;
                return;
            }

            $alterParts = [];
            if (!isset($columns['relation_mode'])) {
                $alterParts[] = "ADD COLUMN `relation_mode` varchar(20) NOT NULL DEFAULT 'direct' AFTER `cache_type`";
            }
            if (!isset($columns['amount_method'])) {
                $alterParts[] = "ADD COLUMN `amount_method` varchar(20) NOT NULL DEFAULT 'sales' AFTER `relation_mode`";
            }
            if (!isset($columns['period_start'])) {
                $alterParts[] = "ADD COLUMN `period_start` int NOT NULL DEFAULT 0 AFTER `amount_method`";
            }
            if (!isset($columns['period_end'])) {
                $alterParts[] = "ADD COLUMN `period_end` int NOT NULL DEFAULT 0 AFTER `period_start`";
            }
            if (!isset($columns['scope_hash'])) {
                $alterParts[] = "ADD COLUMN `scope_hash` varchar(64) NOT NULL DEFAULT '" . self::DEFAULT_SCOPE_HASH . "' AFTER `period_end`";
            }
            if (!isset($columns['extra_data'])) {
                $alterParts[] = "ADD COLUMN `extra_data` text NULL AFTER `scope_hash`";
            }

            if (!empty($alterParts)) {
                Db::execute("ALTER TABLE `{$table}` " . implode(', ', $alterParts));
            }

            $indexes = self::getTableIndexes($table);
            if (isset($indexes['aid_mid_date_type'])) {
                try {
                    Db::execute("ALTER TABLE `{$table}` DROP INDEX `aid_mid_date_type`");
                    unset($indexes['aid_mid_date_type']);
                } catch (\Throwable $e) {
                    Log::warning('删除旧业绩缓存唯一索引失败：' . $e->getMessage());
                }
            }
            if (!isset($indexes['uk_perf_cache_scope'])) {
                Db::execute("ALTER TABLE `{$table}` ADD UNIQUE KEY `uk_perf_cache_scope` (`aid`,`mid`,`cache_date`,`cache_type`,`relation_mode`,`amount_method`,`scope_hash`)");
            }
            if (!isset($indexes['idx_perf_cache_scope'])) {
                Db::execute("ALTER TABLE `{$table}` ADD KEY `idx_perf_cache_scope` (`aid`,`cache_date`,`cache_type`,`relation_mode`,`amount_method`,`scope_hash`)");
            }
        } catch (\Throwable $e) {
            Log::error('业绩缓存表结构检查失败：' . $e->getMessage());
        }

        $checked = true;
    }

    private static function ensureBaseTables($prefix)
    {
        $memberCacheTable = $prefix . 'member_performance_cache';
        $configTable = $prefix . 'performance_cache_config';
        $taskTable = $prefix . 'performance_cache_tasks';
        $logTable = $prefix . 'performance_cache_logs';

        Db::execute("CREATE TABLE IF NOT EXISTS `{$memberCacheTable}` (
            `id` int NOT NULL AUTO_INCREMENT COMMENT '记录ID',
            `aid` int NOT NULL DEFAULT 0 COMMENT '站点ID',
            `mid` int NOT NULL DEFAULT 0 COMMENT '会员ID',
            `cache_date` date NOT NULL COMMENT '缓存日期',
            `cache_type` varchar(50) NOT NULL DEFAULT 'daily' COMMENT '缓存类型',
            `relation_mode` varchar(20) NOT NULL DEFAULT 'direct',
            `amount_method` varchar(20) NOT NULL DEFAULT 'sales',
            `period_start` int NOT NULL DEFAULT 0,
            `period_end` int NOT NULL DEFAULT 0,
            `scope_hash` varchar(64) NOT NULL DEFAULT 'default',
            `extra_data` text NULL,
            `direct_count` int NOT NULL DEFAULT 0,
            `direct_active_count` int NOT NULL DEFAULT 0,
            `direct_performance` decimal(15,2) NOT NULL DEFAULT 0.00,
            `direct_commission` decimal(15,2) NOT NULL DEFAULT 0.00,
            `team_count` int NOT NULL DEFAULT 0,
            `team_active_count` int NOT NULL DEFAULT 0,
            `team_performance` decimal(15,2) NOT NULL DEFAULT 0.00,
            `team_commission` decimal(15,2) NOT NULL DEFAULT 0.00,
            `self_orders` int NOT NULL DEFAULT 0,
            `self_performance` decimal(15,2) NOT NULL DEFAULT 0.00,
            `self_commission` decimal(15,2) NOT NULL DEFAULT 0.00,
            `big_area_performance` decimal(15,2) NOT NULL DEFAULT 0.00,
            `small_area_performance` decimal(15,2) NOT NULL DEFAULT 0.00,
            `big_area_mid` int NOT NULL DEFAULT 0,
            `balance_performance` decimal(15,2) NOT NULL DEFAULT 0.00,
            `current_level` int NOT NULL DEFAULT 0,
            `level_commission` decimal(15,2) NOT NULL DEFAULT 0.00,
            `leadership_commission` decimal(15,2) NOT NULL DEFAULT 0.00,
            `special_bonus` decimal(15,2) NOT NULL DEFAULT 0.00,
            `last_order_time` int NOT NULL DEFAULT 0,
            `calculation_time` decimal(10,4) NOT NULL DEFAULT 0.0000,
            `cache_version` int NOT NULL DEFAULT 1,
            `is_valid` tinyint(1) NOT NULL DEFAULT 1,
            `create_time` int NOT NULL DEFAULT 0,
            `update_time` int NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_perf_cache_scope` (`aid`,`mid`,`cache_date`,`cache_type`,`relation_mode`,`amount_method`,`scope_hash`),
            KEY `aid_mid` (`aid`,`mid`),
            KEY `cache_date` (`cache_date`),
            KEY `cache_type` (`cache_type`),
            KEY `idx_perf_cache_scope` (`aid`,`cache_date`,`cache_type`,`relation_mode`,`amount_method`,`scope_hash`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户业绩缓存主表'");

        Db::execute("CREATE TABLE IF NOT EXISTS `{$configTable}` (
            `id` int NOT NULL AUTO_INCREMENT COMMENT '配置ID',
            `aid` int NOT NULL DEFAULT 0 COMMENT '站点ID',
            `config_key` varchar(100) NOT NULL COMMENT '配置键名',
            `config_name` varchar(200) NOT NULL COMMENT '配置名称',
            `config_value` text NULL COMMENT '配置值JSON',
            `is_enabled` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否启用',
            `description` text NULL COMMENT '配置说明',
            `create_time` int NOT NULL DEFAULT 0,
            `update_time` int NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `aid_config_key` (`aid`,`config_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='业绩缓存配置表'");

        Db::execute("CREATE TABLE IF NOT EXISTS `{$taskTable}` (
            `id` int NOT NULL AUTO_INCREMENT COMMENT '任务ID',
            `aid` int NOT NULL DEFAULT 0 COMMENT '站点ID',
            `mid` int NOT NULL DEFAULT 0 COMMENT '会员ID',
            `task_type` varchar(50) NOT NULL DEFAULT 'update' COMMENT '任务类型',
            `cache_type` varchar(50) NOT NULL DEFAULT 'daily' COMMENT '缓存类型',
            `target_date` date DEFAULT NULL COMMENT '目标日期',
            `priority` tinyint(1) NOT NULL DEFAULT 1 COMMENT '优先级',
            `trigger_reason` varchar(200) DEFAULT '' COMMENT '触发原因',
            `retry_count` int NOT NULL DEFAULT 0 COMMENT '重试次数',
            `max_retry` int NOT NULL DEFAULT 3 COMMENT '最大重试次数',
            `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态',
            `error_msg` text NULL COMMENT '错误信息',
            `start_time` int NOT NULL DEFAULT 0,
            `complete_time` int NOT NULL DEFAULT 0,
            `execution_time` decimal(10,4) NOT NULL DEFAULT 0.0000,
            `create_time` int NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            KEY `aid_status` (`aid`,`status`),
            KEY `priority_create` (`priority`,`create_time`),
            KEY `mid_task_type` (`mid`,`task_type`),
            KEY `target_date` (`target_date`),
            KEY `idx_status_priority_time` (`status`,`priority`,`create_time`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='业绩缓存任务队列表'");

        Db::execute("CREATE TABLE IF NOT EXISTS `{$logTable}` (
            `id` int NOT NULL AUTO_INCREMENT COMMENT '日志ID',
            `aid` int NOT NULL DEFAULT 0 COMMENT '站点ID',
            `mid` int NOT NULL DEFAULT 0 COMMENT '会员ID',
            `operation_type` varchar(50) NOT NULL COMMENT '操作类型',
            `cache_type` varchar(50) NOT NULL DEFAULT 'daily' COMMENT '缓存类型',
            `operation_result` varchar(20) NOT NULL DEFAULT 'success' COMMENT '操作结果',
            `execution_time` decimal(10,4) NOT NULL DEFAULT 0.0000,
            `affected_records` int NOT NULL DEFAULT 0,
            `user_id` int NOT NULL DEFAULT 0,
            `user_type` varchar(20) NOT NULL DEFAULT 'system',
            `ip_address` varchar(45) DEFAULT '',
            `error_msg` text NULL,
            `extra_data` text NULL,
            `create_time` int NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            KEY `aid_mid` (`aid`,`mid`),
            KEY `operation_type` (`operation_type`),
            KEY `operation_result` (`operation_result`),
            KEY `create_time` (`create_time`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='业绩缓存操作日志表'");
    }

    private static function getTableColumns($table)
    {
        try {
            $rows = Db::query("SHOW COLUMNS FROM `{$table}`");
            $columns = [];
            foreach ($rows as $row) {
                $field = $row['Field'] ?? $row['field'] ?? '';
                if ($field !== '') $columns[$field] = true;
            }
            return $columns;
        } catch (\Throwable $e) {
            return [];
        }
    }

    private static function getTableIndexes($table)
    {
        try {
            $rows = Db::query("SHOW INDEX FROM `{$table}`");
            $indexes = [];
            foreach ($rows as $row) {
                $key = $row['Key_name'] ?? $row['key_name'] ?? '';
                if ($key !== '') $indexes[$key] = true;
            }
            return $indexes;
        } catch (\Throwable $e) {
            return [];
        }
    }

    private static function normalizeSnapshotOptions(array $options)
    {
        $relationMode = $options['relation_mode'] ?? self::RELATION_DIRECT;
        if ($relationMode === 'jiedian') $relationMode = self::RELATION_NODE;
        if (!in_array($relationMode, [self::RELATION_DIRECT, self::RELATION_NODE], true)) {
            $relationMode = self::RELATION_DIRECT;
        }

        $amountMethod = $options['amount_method'] ?? ($options['calculation_type'] ?? self::METHOD_SALES);
        if ($amountMethod === 'turnover') $amountMethod = self::METHOD_SALES;
        if (!in_array($amountMethod, [self::METHOD_SALES, self::METHOD_COST, self::METHOD_PROFIT], true)) {
            $amountMethod = self::METHOD_SALES;
        }

        $statuses = self::normalizeIntArray($options['order_status'] ?? ($options['statuses'] ?? [1, 2, 3]));
        if (empty($statuses)) $statuses = [1, 2, 3];
        sort($statuses);

        $productIds = self::normalizeIntArray($options['product_ids'] ?? []);
        $levelIds = self::normalizeIntArray($options['level_ids'] ?? []);
        sort($productIds);
        sort($levelIds);

        $isYeji = array_key_exists('is_yeji', $options) ? intval($options['is_yeji']) : 1;
        $periodStart = intval($options['period_start'] ?? 0);
        $periodEnd = intval($options['period_end'] ?? 0);

        $scopePayload = [
            'order_status' => $statuses,
            'is_yeji' => $isYeji,
            'product_ids' => $productIds,
            'level_ids' => $levelIds,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
        ];

        $isDefaultScope = $statuses === [1, 2, 3]
            && $isYeji === 1
            && empty($productIds)
            && empty($levelIds)
            && $periodStart <= 0
            && $periodEnd <= 0;

        $scopeHash = trim(strval($options['scope_hash'] ?? ''));
        if ($scopeHash === '') {
            $scopeHash = $isDefaultScope ? self::DEFAULT_SCOPE_HASH : md5(json_encode($scopePayload));
        }

        return [
            'relation_mode' => $relationMode,
            'amount_method' => $amountMethod,
            'order_status' => $statuses,
            'is_yeji' => $isYeji,
            'product_ids' => $productIds,
            'level_ids' => $levelIds,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'scope_hash' => $scopeHash,
            'extra_data' => $scopePayload,
        ];
    }

    private static function normalizeIntArray($value)
    {
        if ($value === null || $value === '') return [];
        if (!is_array($value)) {
            $value = explode(',', strval($value));
        }
        $result = [];
        foreach ($value as $item) {
            $id = intval($item);
            if ($id > 0) $result[$id] = $id;
        }
        return array_values($result);
    }

    private static function normalizeCacheDate($cacheType, $date)
    {
        $date = trim(strval($date));
        if ($date === '') $date = date('Y-m-d');
        if ($cacheType === self::CACHE_MONTHLY && preg_match('/^\d{4}-\d{2}$/', $date)) {
            return $date . '-01';
        }
        if ($cacheType === self::CACHE_TOTAL && $date === 'total') {
            return date('Y-m-d');
        }
        $ts = strtotime($date);
        return $ts ? date('Y-m-d', $ts) : date('Y-m-d');
    }

    private static function resolvePeriodRange($cacheType, $date, array $scope)
    {
        if (!empty($scope['period_start']) && !empty($scope['period_end']) && $scope['period_start'] <= $scope['period_end']) {
            return [intval($scope['period_start']), intval($scope['period_end'])];
        }
        return self::getTimeRange($cacheType, $date);
    }

    private static function loadSelfPerformanceStats($aid, $periodStart, $periodEnd, array $scope)
    {
        $amountSql = self::getOrderAmountSql($scope['amount_method']);
        $query = Db::name('shop_order_goods')
            ->alias('og')
            ->leftJoin('member m', 'og.aid = m.aid AND og.mid = m.id')
            ->where('og.aid', $aid)
            ->where('og.createtime', '>=', $periodStart)
            ->where('og.createtime', '<=', $periodEnd)
            ->whereIn('og.status', $scope['order_status']);

        if ($scope['is_yeji'] !== -1) {
            $query->where('og.is_yeji', $scope['is_yeji']);
        }
        if (!empty($scope['product_ids'])) {
            $query->whereIn('og.proid', $scope['product_ids']);
        }
        if (!empty($scope['level_ids'])) {
            $query->whereIn('m.levelid', $scope['level_ids']);
        }

        $rows = $query
            ->fieldRaw("og.mid, COUNT(*) AS self_orders, SUM({$amountSql}) AS self_performance, MAX(og.createtime) AS last_order_time")
            ->group('og.mid')
            ->select()
            ->toArray();

        $stats = [];
        foreach ($rows as $row) {
            $mid = intval($row['mid'] ?? 0);
            if ($mid <= 0) continue;
            $stats[$mid] = [
                'self_orders' => intval($row['self_orders'] ?? 0),
                'self_performance' => floatval($row['self_performance'] ?? 0),
                'last_order_time' => intval($row['last_order_time'] ?? 0),
            ];
        }
        return $stats;
    }

    private static function getOrderAmountSql($amountMethod)
    {
        $sales = 'COALESCE(og.real_totalprice, og.totalprice, 0)';
        $cost = '(COALESCE(og.cost_price, 0) * COALESCE(og.num, 0))';
        if ($amountMethod === self::METHOD_COST) return $cost;
        if ($amountMethod === self::METHOD_PROFIT) return "GREATEST({$sales} - {$cost}, 0)";
        return $sales;
    }

    private static function calculateAreaPerformance($mid, array $childrenByParent, array $subtreePerformance)
    {
        $areas = [];
        foreach (($childrenByParent[$mid] ?? []) as $childId) {
            $areas[] = [
                'mid' => intval($childId),
                'performance' => floatval($subtreePerformance[$childId] ?? 0),
            ];
        }

        usort($areas, function ($a, $b) {
            $compare = $b['performance'] <=> $a['performance'];
            return $compare !== 0 ? $compare : ($a['mid'] <=> $b['mid']);
        });

        if (empty($areas)) {
            return ['big_area_performance' => 0, 'small_area_performance' => 0, 'big_area_mid' => 0];
        }

        $big = $areas[0];
        $small = 0;
        for ($i = 1; $i < count($areas); $i++) {
            $small += floatval($areas[$i]['performance']);
        }

        return [
            'big_area_performance' => floatval($big['performance']),
            'small_area_performance' => floatval($small),
            'big_area_mid' => intval($big['mid']),
        ];
    }

    private static function metricToColumn($metric)
    {
        $metric = trim(strval($metric));
        $map = [
            'big_area' => 'big_area_performance',
            'big_area_performance' => 'big_area_performance',
            'small_area' => 'small_area_performance',
            'small_area_performance' => 'small_area_performance',
            'team' => 'team_performance',
            'team_performance' => 'team_performance',
            'self' => 'self_performance',
            'self_performance' => 'self_performance',
            'direct' => 'direct_performance',
            'direct_performance' => 'direct_performance',
            'balance' => 'balance_performance',
            'balance_performance' => 'balance_performance',
        ];
        if (!isset($map[$metric])) {
            throw new \Exception('不支持的业绩指标：' . $metric);
        }
        return $map[$metric];
    }

    /**
     * 计算用户业绩数据
     * @param array $member 会员信息
     * @param string $cacheType 缓存类型
     * @param string $date 日期
     * @return array 业绩数据
     */
    private static function calculatePerformanceData($member, $cacheType, $date)
    {
        $aid = $member['aid'];
        $mid = $member['id'];

        Log::write("开始计算业绩数据：aid={$aid}, mid={$mid}, cacheType={$cacheType}, date={$date}");
        Log::write("会员信息：" . json_encode($member));

        // 根据缓存类型设置时间范围
        $timeRange = self::getTimeRange($cacheType, $date);

        // 初始化业绩数据
        $performanceData = [
            'direct_count' => 0,
            'direct_active_count' => 0,
            'direct_performance' => 0.00,
            'direct_commission' => 0.00,
            'team_count' => 0,
            'team_active_count' => 0,
            'team_performance' => 0.00,
            'team_commission' => 0.00,
            'self_orders' => 0,
            'self_performance' => 0.00,
            'self_commission' => 0.00,
            'big_area_performance' => 0.00,
            'small_area_performance' => 0.00,
            'big_area_mid' => 0,
            'balance_performance' => 0.00,
            'current_level' => $member['level'] ?? 0,
            'level_commission' => 0.00,
            'leadership_commission' => 0.00,
            'special_bonus' => 0.00,
            'last_order_time' => 0
        ];

        // 1. 计算个人业绩
        Log::write("开始计算用户{$mid}的个人业绩，时间范围：" . json_encode($timeRange));

        $selfOrders = Db::name('shop_order')
            ->where('aid', $aid)
            ->where('mid', $mid)
            ->where('status', '>=', 1) // 已支付订单（参考ApiAgent.php的逻辑）
            ->where('createtime', 'between', $timeRange)
            ->select();

        Log::write("用户{$mid}个人订单查询结果：" . json_encode($selfOrders->toArray()));

        foreach ($selfOrders as $order) {
            $performanceData['self_orders']++;
            $performanceData['self_performance'] += $order['totalprice'];
            $performanceData['last_order_time'] = max($performanceData['last_order_time'], $order['createtime']);
        }

        Log::write("用户{$mid}个人业绩计算结果：订单数={$performanceData['self_orders']}，业绩={$performanceData['self_performance']}");

        // 2. 计算直推数据
        $directMembers = Db::name('member')
            ->where('aid', $aid)
            ->where('pid', $mid)
            ->select();

        Log::write("用户{$mid}的直推会员：" . json_encode($directMembers->toArray()));

        $performanceData['direct_count'] = count($directMembers);

        foreach ($directMembers as $directMember) {
            // 计算直推会员的业绩
            $directOrders = Db::name('shop_order')
                ->where('aid', $aid)
                ->where('mid', $directMember['id'])
                ->where('status', '>=', 1)
                ->where('createtime', 'between', $timeRange)
                ->sum('totalprice');

            Log::write("直推会员{$directMember['id']}的业绩：{$directOrders}");

            if ($directOrders > 0) {
                $performanceData['direct_active_count']++;
                $performanceData['direct_performance'] += $directOrders;
            }
        }

        Log::write("用户{$mid}直推业绩汇总：直推人数={$performanceData['direct_count']}，活跃人数={$performanceData['direct_active_count']}，直推业绩={$performanceData['direct_performance']}");

        // 3. 计算团队数据
        $teamMembers = self::getTeamMembers($aid, $mid);
        $performanceData['team_count'] = count($teamMembers);

        foreach ($teamMembers as $teamMember) {
            $teamOrders = Db::name('shop_order')
                ->where('aid', $aid)
                ->where('mid', $teamMember['id'])
                ->where('status', '>=', 1)
                ->where('createtime', 'between', $timeRange)
                ->sum('totalprice');

            if ($teamOrders > 0) {
                $performanceData['team_active_count']++;
                $performanceData['team_performance'] += $teamOrders;
            }
        }

        // 4. 计算大小区业绩（双轨制）
        $leftAreaPerformance = 0;
        $rightAreaPerformance = 0;
        $bigAreaMid = 0;

        // 注释掉双轨制逻辑，因为member表中没有position字段
        // 如果需要双轨制，请确认member表结构并启用以下代码
        /*
        // 获取左右区直推
        $leftMember = Db::name('member')
            ->where('aid', $aid)
            ->where('pid', $mid)
            ->where('position', 'left')
            ->find();

        $rightMember = Db::name('member')
            ->where('aid', $aid)
            ->where('pid', $mid)
            ->where('position', 'right')
            ->find();

        if ($leftMember) {
            $leftTeam = self::getTeamMembers($aid, $leftMember['id']);
            foreach ($leftTeam as $member) {
                $leftAreaPerformance += Db::name('shop_order')
                    ->where('aid', $aid)
                    ->where('mid', $member['id'])
                    ->where('status', '>=', 1)
                    ->where('createtime', 'between', $timeRange)
                    ->sum('totalprice');
            }
        }

        if ($rightMember) {
            $rightTeam = self::getTeamMembers($aid, $rightMember['id']);
            foreach ($rightTeam as $member) {
                $rightAreaPerformance += Db::name('shop_order')
                    ->where('aid', $aid)
                    ->where('mid', $member['id'])
                    ->where('status', '>=', 1)
                    ->where('createtime', 'between', $timeRange)
                    ->sum('totalprice');
            }
        }
        */

        // 确定大小区（暂时设为0，因为双轨制逻辑被注释）
        $performanceData['big_area_performance'] = $leftAreaPerformance;
        $performanceData['small_area_performance'] = $rightAreaPerformance;
        $performanceData['big_area_mid'] = 0;
        $performanceData['balance_performance'] = min($leftAreaPerformance, $rightAreaPerformance);

        // 5. 计算佣金数据（这里需要根据具体的佣金规则来实现）
        $performanceData['direct_commission'] = self::calculateDirectCommission($aid, $mid, $performanceData['direct_performance'], $timeRange);
        $performanceData['team_commission'] = self::calculateTeamCommission($aid, $mid, $performanceData['team_performance'], $timeRange);
        $performanceData['level_commission'] = self::calculateLevelCommission($aid, $mid, $performanceData, $timeRange);
        $performanceData['leadership_commission'] = self::calculateLeadershipCommission($aid, $mid, $performanceData, $timeRange);
        $performanceData['special_bonus'] = self::calculateSpecialBonus($aid, $mid, $performanceData, $timeRange);

        return $performanceData;
    }

    /**
     * 获取团队成员列表
     * @param int $aid 站点ID
     * @param int $mid 会员ID
     * @return array 团队成员列表
     */
    private static function getTeamMembers($aid, $mid)
    {
        // 递归获取所有下级成员
        $members = [];
        $directMembers = Db::name('member')
            ->where('aid', $aid)
            ->where('pid', $mid)
            ->select()
            ->toArray();

        foreach ($directMembers as $member) {
            $members[] = $member;
            $subMembers = self::getTeamMembers($aid, $member['id']);
            $members = array_merge($members, $subMembers);
        }

        return $members;
    }

    /**
     * 获取时间范围
     * @param string $cacheType 缓存类型
     * @param string $date 日期或时间
     * @return array 时间范围 [开始时间戳, 结束时间戳]
     */
    private static function getTimeRange($cacheType, $date)
    {
        switch ($cacheType) {
            case self::CACHE_MINUTE:
                // 分钟缓存：当前分钟的开始到结束
                if (strlen($date) > 10) {
                    // 如果传入的是完整时间，精确到分钟
                    $dateTime = date('Y-m-d H:i:00', strtotime($date));
                    $startTime = strtotime($dateTime);
                    $endTime = strtotime($dateTime) + 59; // 当前分钟的最后一秒
                } else {
                    // 如果只传入日期，使用当前时间的分钟
                    $currentMinute = date('Y-m-d H:i:00');
                    $startTime = strtotime($currentMinute);
                    $endTime = strtotime($currentMinute) + 59;
                }
                break;
            case self::CACHE_HOURLY:
                // 小时缓存：当前小时的开始到结束
                if (strlen($date) > 10) {
                    $dateTime = date('Y-m-d H:00:00', strtotime($date));
                    $startTime = strtotime($dateTime);
                    $endTime = strtotime($dateTime . ' +1 hour') - 1;
                } else {
                    $currentHour = date('Y-m-d H:00:00');
                    $startTime = strtotime($currentHour);
                    $endTime = strtotime($currentHour . ' +1 hour') - 1;
                }
                break;
            case self::CACHE_DAILY:
                $startTime = strtotime($date . ' 00:00:00');
                $endTime = strtotime($date . ' 23:59:59');
                break;
            case self::CACHE_MONTHLY:
                $startTime = strtotime(date('Y-m-01', strtotime($date)));
                $endTime = strtotime(date('Y-m-t 23:59:59', strtotime($date)));
                break;
            case self::CACHE_TOTAL:
                $startTime = 0;
                $endTime = time();
                break;
            case self::CACHE_CUSTOM:
                $startTime = strtotime($date . ' 00:00:00');
                $endTime = strtotime($date . ' 23:59:59');
                break;
            default:
                $startTime = strtotime($date . ' 00:00:00');
                $endTime = strtotime($date . ' 23:59:59');
        }

        return [$startTime, $endTime];
    }

    /**
     * 计算直推佣金
     * @param int $aid 站点ID
     * @param int $mid 会员ID
     * @param float $directPerformance 直推业绩
     * @param array $timeRange 时间范围
     * @return float 直推佣金
     */
    private static function calculateDirectCommission($aid, $mid, $directPerformance, $timeRange)
    {
        // 这里需要根据具体的佣金规则来实现
        // 示例：直推佣金 = 直推业绩 * 直推比例
        $directRate = 0.1; // 10%的直推比例，实际应该从配置中获取
        return $directPerformance * $directRate;
    }

    /**
     * 计算团队佣金
     * @param int $aid 站点ID
     * @param int $mid 会员ID
     * @param float $teamPerformance 团队业绩
     * @param array $timeRange 时间范围
     * @return float 团队佣金
     */
    private static function calculateTeamCommission($aid, $mid, $teamPerformance, $timeRange)
    {
        // 这里需要根据具体的佣金规则来实现
        $teamRate = 0.05; // 5%的团队比例
        return $teamPerformance * $teamRate;
    }

    /**
     * 计算级差奖
     * @param int $aid 站点ID
     * @param int $mid 会员ID
     * @param array $performanceData 业绩数据
     * @param array $timeRange 时间范围
     * @return float 级差奖
     */
    private static function calculateLevelCommission($aid, $mid, $performanceData, $timeRange)
    {
        // 根据等级差计算级差奖
        return 0.00; // 需要根据具体规则实现
    }

    /**
     * 计算领导奖
     * @param int $aid 站点ID
     * @param int $mid 会员ID
     * @param array $performanceData 业绩数据
     * @param array $timeRange 时间范围
     * @return float 领导奖
     */
    private static function calculateLeadershipCommission($aid, $mid, $performanceData, $timeRange)
    {
        // 根据领导力计算领导奖
        return 0.00; // 需要根据具体规则实现
    }

    /**
     * 计算特殊奖金
     * @param int $aid 站点ID
     * @param int $mid 会员ID
     * @param array $performanceData 业绩数据
     * @param array $timeRange 时间范围
     * @return float 特殊奖金
     */
    private static function calculateSpecialBonus($aid, $mid, $performanceData, $timeRange)
    {
        // 计算特殊奖金
        return 0.00; // 需要根据具体规则实现
    }

    /**
     * 格式化业绩数据
     * @param array $cacheData 缓存数据
     * @return array 格式化后的数据
     */
    private static function formatPerformanceData($cacheData)
    {
        $aid = intval($cacheData['aid'] ?? (defined('aid') ? aid : 0));
        $totalCommission = floatval($cacheData['direct_commission'] + $cacheData['team_commission'] + $cacheData['level_commission'] + $cacheData['leadership_commission'] + $cacheData['special_bonus']);
        $totalPerformance = self::calculateTotalPerformance($cacheData, $aid);

        return [
            'aid' => $aid,
            'mid' => $cacheData['mid'],
            'cache_date' => $cacheData['cache_date'],
            'cache_type' => $cacheData['cache_type'],
            'relation_mode' => $cacheData['relation_mode'] ?? self::RELATION_DIRECT,
            'amount_method' => $cacheData['amount_method'] ?? self::METHOD_SALES,
            'period_start' => intval($cacheData['period_start'] ?? 0),
            'period_end' => intval($cacheData['period_end'] ?? 0),
            'scope_hash' => $cacheData['scope_hash'] ?? self::DEFAULT_SCOPE_HASH,

            // 兼容旧调用的顶层字段
            'direct_count' => intval($cacheData['direct_count']),
            'direct_active_count' => intval($cacheData['direct_active_count']),
            'direct_performance' => floatval($cacheData['direct_performance']),
            'team_count' => intval($cacheData['team_count']),
            'team_active_count' => intval($cacheData['team_active_count']),
            'team_performance' => floatval($cacheData['team_performance']),
            'self_orders' => intval($cacheData['self_orders']),
            'self_performance' => floatval($cacheData['self_performance']),
            'big_area_performance' => floatval($cacheData['big_area_performance']),
            'small_area_performance' => floatval($cacheData['small_area_performance']),
            'big_area_mid' => intval($cacheData['big_area_mid']),
            'balance_performance' => floatval($cacheData['balance_performance']),
            'total_performance' => $totalPerformance,
            'total_commission' => $totalCommission,

            // 直推数据
            'direct' => [
                'count' => $cacheData['direct_count'],
                'active_count' => $cacheData['direct_active_count'],
                'performance' => floatval($cacheData['direct_performance']),
                'commission' => floatval($cacheData['direct_commission'])
            ],

            // 团队数据
            'team' => [
                'count' => $cacheData['team_count'],
                'active_count' => $cacheData['team_active_count'],
                'performance' => floatval($cacheData['team_performance']),
                'commission' => floatval($cacheData['team_commission'])
            ],

            // 个人数据
            'self' => [
                'orders' => $cacheData['self_orders'],
                'performance' => floatval($cacheData['self_performance']),
                'commission' => floatval($cacheData['self_commission'])
            ],

            // 大小区数据
            'areas' => [
                'big_area_performance' => floatval($cacheData['big_area_performance']),
                'small_area_performance' => floatval($cacheData['small_area_performance']),
                'big_area_mid' => $cacheData['big_area_mid'],
                'balance_performance' => floatval($cacheData['balance_performance'])
            ],

            // 等级和奖金
            'level' => [
                'current_level' => $cacheData['current_level'],
                'level_commission' => floatval($cacheData['level_commission']),
                'leadership_commission' => floatval($cacheData['leadership_commission']),
                'special_bonus' => floatval($cacheData['special_bonus'])
            ],

            // 汇总数据
            'summary' => [
                'total_commission' => $totalCommission,
                'total_performance' => $totalPerformance,
                'last_order_time' => $cacheData['last_order_time'],
                'cache_version' => $cacheData['cache_version'],
                'update_time' => $cacheData['update_time']
            ]
        ];
    }

    /**
     * 计算总业绩（根据配置决定是否包含个人业绩）
     * @param array $cacheData 缓存数据
     * @param int $aid 站点ID
     * @return float
     */
    private static function calculateTotalPerformance($cacheData, $aid)
    {
        // 获取配置：总业绩是否包含个人业绩
        $includePersonal = self::getConfig($aid, 'total_performance_include_personal', true);

        if ($includePersonal) {
            // 包含个人业绩：个人 + 团队
            return floatval($cacheData['self_performance'] + $cacheData['team_performance']);
        } else {
            // 不包含个人业绩：仅团队业绩
            return floatval($cacheData['team_performance']);
        }
    }

    /**
     * 获取缓存配置
     * @param int $aid 站点ID
     * @param string $configKey 配置键名
     * @param mixed $defaultValue 默认值
     * @return mixed
     */
    public static function getConfig($aid, $configKey, $defaultValue = null)
    {
        try {
            $config = Db::name('performance_cache_config')
                ->where('aid', $aid)
                ->where('config_key', $configKey)
                ->where('is_enabled', 1)
                ->find();

            if ($config && !empty($config['config_value'])) {
                $value = json_decode($config['config_value'], true);
                return $value !== null ? $value : $defaultValue;
            }

            return $defaultValue;
        } catch (\Exception $e) {
            Log::error('获取缓存配置异常：' . $e->getMessage());
            return $defaultValue;
        }
    }

    /**
     * 设置缓存配置
     * @param int $aid 站点ID
     * @param string $configKey 配置键名
     * @param string $configName 配置名称
     * @param mixed $configValue 配置值
     * @param string $description 配置说明
     * @return bool
     */
    public static function setConfig($aid, $configKey, $configName, $configValue, $description = '')
    {
        try {
            $data = [
                'aid' => $aid,
                'config_key' => $configKey,
                'config_name' => $configName,
                'config_value' => json_encode($configValue),
                'description' => $description,
                'update_time' => time()
            ];

            $exists = Db::name('performance_cache_config')
                ->where('aid', $aid)
                ->where('config_key', $configKey)
                ->find();

            if ($exists) {
                return Db::name('performance_cache_config')
                    ->where('aid', $aid)
                    ->where('config_key', $configKey)
                    ->update($data);
            } else {
                $data['create_time'] = time();
                return Db::name('performance_cache_config')->insert($data);
            }
        } catch (\Exception $e) {
            Log::error('设置缓存配置异常：' . $e->getMessage());
            return false;
        }
    }

    /**
     * 清除用户业绩缓存
     * @param int $mid 会员ID
     * @param string $cacheType 缓存类型，为空则清除所有类型
     * @param int $aid 站点ID
     * @return int 清除数量
     */
    /**
     * 清除指定时间和类型的缓存
     * @param int $mid 会员ID
     * @param string $cacheType 缓存类型
     * @param string $timeKey 时间键（日期格式，根据cacheType不同而不同）
     * @param int $aid 站点ID
     * @return int 清除数量
     */
    public static function clearCache($mid, $cacheType, $timeKey, $aid = null)
    {
        $startTime = microtime(true);
        $aid = $aid ?: (defined('aid') ? aid : 0);

        try {
            self::ensureSchema();

            $query = Db::name('member_performance_cache')
                ->where('aid', $aid)
                ->where('mid', $mid)
                ->where('cache_type', $cacheType);

            // 根据缓存类型处理时间键
            if ($cacheType == self::CACHE_TOTAL) {
                // 累计缓存，timeKey 为 'total'，不需要日期过滤
                // 或者可以根据需要清除所有累计缓存
            } else {
                // 其他类型需要根据 timeKey 转换为 cache_date 格式
                // timeKey 格式可能是：'Y-m-d H:i:00'、'Y-m-d H:00:00'、'Y-m-d'、'Y-m'
                // cache_date 字段存储的是 'Y-m-d' 格式
                if ($cacheType == self::CACHE_MINUTE || $cacheType == self::CACHE_HOURLY) {
                    // 分钟和小时缓存，提取日期部分
                    $date = substr($timeKey, 0, 10); // 提取 'Y-m-d' 部分
                    $query->where('cache_date', $date);
                } elseif ($cacheType == self::CACHE_DAILY) {
                    // 日缓存，timeKey 就是日期
                    $query->where('cache_date', $timeKey);
                } elseif ($cacheType == self::CACHE_MONTHLY) {
                    // 月缓存，timeKey 是 'Y-m' 格式，需要匹配该月的所有日期
                    $query->where('cache_date', 'like', $timeKey . '%');
                }
            }

            $count = $query->count();
            $result = $query->delete();

            // 记录操作日志
            self::logOperation($aid, $mid, 'clear', 'success', microtime(true) - $startTime, $cacheType, $count);

            return $count;

        } catch (\Exception $e) {
            Log::error('清除业绩缓存异常：' . $e->getMessage());
            self::logOperation($aid, $mid, 'clear', 'failed', microtime(true) - $startTime, $cacheType, 0, 0, 'system', '', $e->getMessage());
            return 0;
        }
    }

    /**
     * 清除用户业绩缓存（兼容旧方法）
     * @param int $mid 会员ID
     * @param string $cacheType 缓存类型，为空则清除所有类型
     * @param int $aid 站点ID
     * @return int 清除数量
     */
    public static function clearPerformanceCache($mid, $cacheType = '', $aid = null)
    {
        $startTime = microtime(true);
        $aid = $aid ?: (defined('aid') ? aid : 0);

        try {
            self::ensureSchema();

            $query = Db::name('member_performance_cache')
                ->where('aid', $aid)
                ->where('mid', $mid);

            if (!empty($cacheType)) {
                $query->where('cache_type', $cacheType);
            }

            $count = $query->count();
            $result = $query->delete();

            // 记录操作日志
            self::logOperation($aid, $mid, 'clear', 'success', microtime(true) - $startTime, $cacheType, $count);

            return $count;

        } catch (\Exception $e) {
            Log::error('清除业绩缓存异常：' . $e->getMessage());
            self::logOperation($aid, $mid, 'clear', 'failed', microtime(true) - $startTime, $cacheType, 0, 0, 'system', '', $e->getMessage());
            return 0;
        }
    }

    /**
     * 清理缓存记录
     * @param string $cacheType 缓存类型，为空表示全部
     * @param int|null $aid 站点ID
     * @param string $mode expired/all
     * @return int
     */
    public static function clear($cacheType = '', $aid = null, $mode = 'expired')
    {
        $startTime = microtime(true);
        $aid = $aid ?: (defined('aid') ? aid : 0);

        try {
            self::ensureSchema();

            $query = Db::name('member_performance_cache')->where('aid', $aid);
            if ($cacheType !== '') {
                $query->where('cache_type', $cacheType);
            }

            if ($mode === 'expired') {
                $expireConfig = self::getConfig($aid, 'cache_expire_days', [
                    self::CACHE_MINUTE => 1,
                    self::CACHE_HOURLY => 7,
                    self::CACHE_DAILY => 90,
                    self::CACHE_MONTHLY => 365,
                    self::CACHE_TOTAL => 0,
                ]);
                $days = 30;
                if ($cacheType !== '' && is_array($expireConfig) && isset($expireConfig[$cacheType])) {
                    $days = intval($expireConfig[$cacheType]);
                }
                if ($days <= 0) {
                    return 0;
                }
                $query->where('cache_date', '<', date('Y-m-d', strtotime("-{$days} days")));
            }

            $count = $query->count();
            $query->delete();
            self::logOperation($aid, 0, 'clear', 'success', microtime(true) - $startTime, $cacheType, $count);
            return intval($count);
        } catch (\Throwable $e) {
            Log::error('清理业绩缓存异常：' . $e->getMessage());
            self::logOperation($aid, 0, 'clear', 'failed', microtime(true) - $startTime, $cacheType, 0, 0, 'system', '', $e->getMessage());
            return 0;
        }
    }

    /**
     * 批量更新业绩缓存
     * @param array $mids 会员ID数组
     * @param string $cacheType 缓存类型
     * @param string $date 日期
     * @param int $aid 站点ID
     * @return array 更新结果
     */
    public static function batchUpdatePerformanceCache($mids, $cacheType = self::CACHE_DAILY, $date = '', $aid = null, array $options = [])
    {
        $aid = $aid ?: (defined('aid') ? aid : 0);
        $date = $date ?: date('Y-m-d');

        try {
            return self::rebuildPerformanceSnapshot($aid, $cacheType, $date, $options, self::normalizeIntArray($mids));
        } catch (\Throwable $e) {
            Log::error('批量更新业绩缓存异常：' . $e->getMessage());
            return [
                'success' => 0,
                'failed' => count(self::normalizeIntArray($mids)),
                'errors' => [$e->getMessage()],
            ];
        }
    }

    /**
     * 记录操作日志
     * @param int $aid 站点ID
     * @param int $mid 会员ID
     * @param string $operationType 操作类型
     * @param string $operationResult 操作结果
     * @param float $executionTime 执行时间
     * @param string $cacheType 缓存类型
     * @param int $affectedRecords 影响记录数
     * @param int $userId 用户ID
     * @param string $userType 用户类型
     * @param string $ipAddress IP地址
     * @param string $errorMsg 错误信息
     */
    private static function logOperation($aid, $mid, $operationType, $operationResult, $executionTime, $cacheType = '', $affectedRecords = 0, $userId = 0, $userType = 'system', $ipAddress = '', $errorMsg = '')
    {
        try {
            $logData = [
                'aid' => $aid,
                'mid' => $mid,
                'operation_type' => $operationType,
                'cache_type' => $cacheType,
                'operation_result' => $operationResult,
                'execution_time' => round($executionTime, 4),
                'affected_records' => $affectedRecords,
                'user_id' => $userId,
                'user_type' => $userType,
                'ip_address' => $ipAddress ?: request()->ip(),
                'error_msg' => $errorMsg,
                'create_time' => time()
            ];

            Db::name('performance_cache_logs')->insert($logData);

        } catch (\Exception $e) {
            Log::error('业绩缓存日志记录失败：' . $e->getMessage());
        }
    }

    /**
     * 添加异步任务来重新计算缓存
     * @param int $mid 会员ID
     * @param string $cacheType 缓存类型
     * @param string $timeKey 时间键
     * @param int $aid 站点ID
     * @param array $extraData 额外数据
     * @return bool 是否成功
     */
    /**
     * 添加异步任务来重新计算缓存
     * @param int $mid 会员ID
     * @param string $cacheType 缓存类型
     * @param string $timeKey 时间键
     * @param int $aid 站点ID
     * @param array $extraData 额外数据
     * @return bool 是否成功
     */
    public static function addTask($mid, $cacheType, $timeKey, $aid = null, $extraData = [])
    {
        $aid = $aid ?: (defined('aid') ? aid : 0);

        try {
            self::ensureSchema();

            // 根据缓存类型将 timeKey 转换为 cache_date 格式
            $cacheDate = '';
            if ($cacheType == self::CACHE_TOTAL) {
                // 累计缓存，使用当前日期作为标识（实际计算时会忽略日期）
                $cacheDate = date('Y-m-d');
            } elseif ($cacheType == self::CACHE_MINUTE || $cacheType == self::CACHE_HOURLY) {
                // 分钟和小时缓存，提取日期部分
                $cacheDate = substr($timeKey, 0, 10); // 提取 'Y-m-d' 部分
            } elseif ($cacheType == self::CACHE_DAILY) {
                // 日缓存，timeKey 就是日期
                $cacheDate = $timeKey;
            } elseif ($cacheType == self::CACHE_MONTHLY) {
                // 月缓存，使用该月的第一天
                $cacheDate = $timeKey . '-01';
            }

            $exists = Db::name('performance_cache_tasks')
                ->where('aid', $aid)
                ->where('mid', intval($mid))
                ->where('task_type', 'update')
                ->where('cache_type', $cacheType)
                ->where('target_date', $cacheDate)
                ->whereIn('status', [0, 1])
                ->find();

            if ($exists) {
                return true;
            }

            Db::name('performance_cache_tasks')->insert([
                'aid' => $aid,
                'mid' => intval($mid),
                'task_type' => 'update',
                'cache_type' => $cacheType,
                'target_date' => $cacheDate,
                'priority' => intval($extraData['priority'] ?? 1),
                'trigger_reason' => substr(json_encode($extraData, JSON_UNESCAPED_UNICODE), 0, 200),
                'retry_count' => 0,
                'max_retry' => intval($extraData['max_retry'] ?? 3),
                'status' => 0,
                'error_msg' => '',
                'start_time' => 0,
                'complete_time' => 0,
                'execution_time' => 0,
                'create_time' => time(),
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('添加缓存任务异常：' . $e->getMessage());
            return false;
        }
    }

    /**
     * 添加整站默认快照重建任务。用于订单触发后合并刷新，避免按上级逐个重复扫订单。
     */
    public static function addSnapshotTask($aid, $cacheType = self::CACHE_DAILY, $date = '', array $extraData = [])
    {
        $aid = intval($aid ?: (defined('aid') ? aid : 0));
        $cacheType = $cacheType ?: self::CACHE_DAILY;
        $cacheDate = self::normalizeCacheDate($cacheType, $date ?: date('Y-m-d'));

        try {
            self::ensureSchema();

            $exists = Db::name('performance_cache_tasks')
                ->where('aid', $aid)
                ->where('mid', 0)
                ->where('task_type', 'snapshot_defaults')
                ->where('cache_type', $cacheType)
                ->where('target_date', $cacheDate)
                ->whereIn('status', [0, 1])
                ->find();

            if ($exists) {
                return true;
            }

            Db::name('performance_cache_tasks')->insert([
                'aid' => $aid,
                'mid' => 0,
                'task_type' => 'snapshot_defaults',
                'cache_type' => $cacheType,
                'target_date' => $cacheDate,
                'priority' => intval($extraData['priority'] ?? 3),
                'trigger_reason' => substr(json_encode($extraData, JSON_UNESCAPED_UNICODE), 0, 200),
                'retry_count' => 0,
                'max_retry' => intval($extraData['max_retry'] ?? 3),
                'status' => 0,
                'error_msg' => '',
                'start_time' => 0,
                'complete_time' => 0,
                'execution_time' => 0,
                'create_time' => time(),
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('添加业绩快照任务异常：' . $e->getMessage());
            return false;
        }
    }

    /**
     * 获取业绩缓存统计信息
     * @param int $aid 站点ID
     * @param string $dateRange 日期范围
     * @return array 统计信息
     */
    public static function getCacheStatistics($aid = null, $dateRange = 'today')
    {
        $aid = $aid ?: (defined('aid') ? aid : 0);

        try {
            $query = Db::name('performance_cache_logs')->where('aid', $aid);

            // 设置日期范围
            switch ($dateRange) {
                case 'today':
                    $query->whereTime('create_time', 'today');
                    break;
                case 'week':
                    $query->whereTime('create_time', 'week');
                    break;
                case 'month':
                    $query->whereTime('create_time', 'month');
                    break;
            }

            $logs = $query->select()->toArray();

            $stats = [
                'total_requests' => count($logs),
                'hit_count' => 0,
                'miss_count' => 0,
                'calculate_count' => 0,
                'clear_count' => 0,
                'avg_response_time' => 0,
                'hit_rate' => 0,
                'cache_count' => 0,
                'total_records' => 0
            ];

            $totalTime = 0;
            foreach ($logs as $log) {
                switch ($log['operation_result']) {
                    case 'hit':
                        $stats['hit_count']++;
                        break;
                    case 'miss':
                        $stats['miss_count']++;
                        break;
                }

                switch ($log['operation_type']) {
                    case 'calculate':
                        $stats['calculate_count']++;
                        break;
                    case 'clear':
                        $stats['clear_count']++;
                        break;
                }

                $totalTime += $log['execution_time'];
            }

            if ($stats['total_requests'] > 0) {
                $stats['avg_response_time'] = round($totalTime / $stats['total_requests'], 4);
                $stats['hit_rate'] = round(($stats['hit_count'] / $stats['total_requests']) * 100, 2);
            }

            // 获取当前缓存数量
            $stats['cache_count'] = Db::name('member_performance_cache')
                ->where('aid', $aid)
                ->where('is_valid', 1)
                ->count();
            $stats['total_records'] = $stats['cache_count'];

            return $stats;

        } catch (\Exception $e) {
            Log::error('获取业绩缓存统计异常：' . $e->getMessage());
            return [];
        }
    }

    /**
     * 检查用户每条线业绩是否都达到阈值（用于接点滑落判断）
     * @param int $mid 用户ID
     * @param float $threshold 业绩阈值
     * @param int $aid 站点ID
     * @param string $calculationType 计算类型：turnover=流水，profit=利润
     * @return bool 是否所有线都达标
     */
    public static function checkAllLinesPerformance($mid, $threshold, $aid = 0, $calculationType = 'turnover')
    {
        if ($aid == 0) {
            $aid = defined('aid') ? aid : 1;
        }

        $startTime = microtime(true);

        try {
            // 优先从缓存获取用户业绩数据
            $performance = self::getPerformance($mid, self::CACHE_DAILY, date('Y-m-d'), $aid);

            if (!$performance) {
                // 缓存不存在，使用实时计算
                Log::write('checkAllLinesPerformance缓存不存在，用户ID='.$mid.'，使用实时计算');
                return self::checkLinesPerformanceRealtime($mid, $threshold, $aid);
            }

            // 获取用户的直推下级
            $directMembers = Db::name('member')
                ->where('aid', $aid)
                ->where('pid', $mid)
                ->field('id')
                ->select()
                ->toArray();

            if (empty($directMembers)) {
                // 没有直推下级，无法形成线路
                return false;
            }

            // 检查每条线的团队业绩
            $qualifiedLines = 0;
            foreach ($directMembers as $member) {
                $linePerformance = self::getLineTeamPerformance($member['id'], $aid, $calculationType);
                if ($linePerformance >= $threshold) {
                    $qualifiedLines++;
                }
            }

            // 记录性能统计
            $responseTime = (microtime(true) - $startTime) * 1000;
            self::logOperation($mid, 'check_lines', 'daily', '', $aid);

            // 所有线都达标才返回true
            $allQualified = ($qualifiedLines == count($directMembers));

            Log::write('checkAllLinesPerformance用户ID='.$mid.'，总线数='.count($directMembers).'，达标线数='.$qualifiedLines.'，阈值='.$threshold.'，计算类型='.$calculationType.'，结果='.($allQualified ? '达标' : '不达标').'，耗时='.$responseTime.'ms');

            return $allQualified;

        } catch (\Exception $e) {
            Log::error('checkAllLinesPerformance异常：' . $e->getMessage());
            // 异常时降级到实时计算
            return self::checkLinesPerformanceRealtime($mid, $threshold, $aid);
        }
    }

    /**
     * 获取指定线路的团队业绩
     * @param int $lineMid 线头用户ID
     * @param int $aid 站点ID
     * @param string $calculationType 计算类型：turnover=流水，profit=利润
     * @return float 团队业绩
     */
    private static function getLineTeamPerformance($lineMid, $aid, $calculationType = 'turnover')
    {
        // 注意：缓存数据是按流水计算的，如果需要利润计算，必须实时计算
        if ($calculationType === 'profit') {
            // 利润计算不使用缓存，直接实时计算
            return self::calculateLineTeamPerformanceRealtime($lineMid, $aid, $calculationType);
        }

        // 先尝试从缓存获取（仅流水计算）
        $performance = self::getPerformance($lineMid, self::CACHE_DAILY, date('Y-m-d'), $aid);

        if ($performance && isset($performance['team_performance'])) {
            return floatval($performance['team_performance']);
        }

        // 缓存不存在，实时计算该线的团队业绩
        return self::calculateLineTeamPerformanceRealtime($lineMid, $aid, $calculationType);
    }

    /**
     * 实时检查每条线业绩（降级方案）
     * 参考ApiAgent.php中的业绩计算方法，保持统一性和准确性
     * @param int $mid 用户ID
     * @param float $threshold 业绩阈值
     * @param int $aid 站点ID
     * @return bool
     */
    private static function checkLinesPerformanceRealtime($mid, $threshold, $aid)
    {
        try {
            // 获取用户的直推下级
            $directMembers = Db::name('member')
                ->where('aid', $aid)
                ->where('pid', $mid)
                ->field('id')
                ->select()
                ->toArray();

            if (empty($directMembers)) {
                Log::write('checkLinesPerformanceRealtime用户ID='.$mid.'没有直推下级，无法形成线路');
                return false;
            }

            // 获取等级ID列表（与ApiAgent.php保持一致）
            $levelids = Db::name('member_level')->where('aid', $aid)->where('id', '>=', 2)->column('id');
            if (empty($levelids)) {
                $levelids = Db::name('member_level')->where('aid', $aid)->column('id');
            }

            // 检查每条线的团队业绩
            foreach ($directMembers as $member) {
                $lineMid = $member['id'];

                // 使用与ApiAgent.php相同的团队成员获取方法
                $teamMemberIds = \app\admin\common\Member::getteammids($aid, $lineMid, 999, $levelids);
                $teamMemberIds[] = $lineMid; // 包含线头本人

                if (empty($teamMemberIds)) {
                    Log::write('checkLinesPerformanceRealtime用户ID='.$mid.'线头ID='.$lineMid.'没有团队成员');
                    return false;
                }

                // 参考ApiAgent.php中的业绩计算方式
                // 1. 计算商品订单业绩
                $yejiwhere = [['status', 'in', '1,2,3']]; // 与ApiAgent.php保持一致
                $teamyeji_shop = Db::name('shop_order_goods')
                    ->where('aid', $aid)
                    ->where('mid', 'in', $teamMemberIds)
                    ->where($yejiwhere)
                    ->sum('totalprice');

                // 2. 计算会员升级订单业绩
                $levelup_where = [
                    ['aid', '=', $aid],
                    ['mid', 'in', $teamMemberIds],
                    ['status', 'in', '1,2'] // 会员升级订单状态
                ];
                $teamyeji_levelup = Db::name('member_levelup_order')
                    ->where($levelup_where)
                    ->sum('totalprice');

                // 该线的总业绩
                $linePerformance = floatval($teamyeji_shop) + floatval($teamyeji_levelup);

                // 如果有任何一条线不达标，返回false
                if ($linePerformance < $threshold) {
                    Log::write('checkLinesPerformanceRealtime用户ID='.$mid.'线头ID='.$lineMid.'业绩='.$linePerformance.'(商品:'.$teamyeji_shop.'+升级:'.$teamyeji_levelup.')未达到阈值='.$threshold);
                    return false;
                }

                Log::write('checkLinesPerformanceRealtime用户ID='.$mid.'线头ID='.$lineMid.'业绩='.$linePerformance.'达到阈值='.$threshold);
            }

            Log::write('checkLinesPerformanceRealtime用户ID='.$mid.'所有'.count($directMembers).'条线路业绩都达到阈值='.$threshold);
            return true; // 所有线都达标

        } catch (\Exception $e) {
            Log::error('checkLinesPerformanceRealtime异常：' . $e->getMessage());
            return false;
        }
    }

    /**
     * 实时计算指定线路的团队业绩
     * 参考ApiAgent.php中的业绩计算方法，保持统一性和准确性
     * @param int $lineMid 线头用户ID
     * @param int $aid 站点ID
     * @param string $calculationType 计算类型：turnover=流水，profit=利润
     * @return float
     */
    private static function calculateLineTeamPerformanceRealtime($lineMid, $aid, $calculationType = 'turnover')
    {
        try {
            // 使用与ApiAgent.php相同的团队成员获取方法
            $levelids = Db::name('member_level')->where('aid', $aid)->where('id', '>=', 2)->column('id');
            if (empty($levelids)) {
                $levelids = Db::name('member_level')->where('aid', $aid)->column('id');
            }

            // 获取该线头下的所有团队成员ID（使用Member类的方法保持一致性）
            $teamMemberIds = \app\admin\common\Member::getteammids($aid, $lineMid, 999, $levelids);
            $teamMemberIds[] = $lineMid; // 包含线头本人

            if (empty($teamMemberIds)) {
                return 0;
            }

            // 参考ApiAgent.php中的业绩计算方式
            // 1. 计算商品订单业绩（使用shop_order_goods表）
            $yejiwhere = [['status', 'in', '1,2,3']]; // 与ApiAgent.php保持一致的状态判断

            if ($calculationType === 'profit') {
                // 利润计算：先获取所有记录，然后在PHP中计算
                $records = Db::name('shop_order_goods')
                    ->where('aid', $aid)
                    ->where('mid', 'in', $teamMemberIds)
                    ->where($yejiwhere)
                    ->select();
                $teamyeji_shop = 0;
                foreach ($records as $record) {
                    $profit = $record['real_totalprice'] - ($record['cost_price'] * $record['num']);
                    $teamyeji_shop += $profit;
                }
            } else {
                // 流水计算：使用 totalprice 字段（默认）
                $teamyeji_shop = Db::name('shop_order_goods')
                    ->where('aid', $aid)
                    ->where('mid', 'in', $teamMemberIds)
                    ->where($yejiwhere)
                    ->sum('totalprice'); // 使用totalprice字段
            }

            // 2. 计算会员升级订单业绩
            $levelup_where = [
                ['aid', '=', $aid],
                ['mid', 'in', $teamMemberIds],
                ['status', 'in', '1,2'] // 会员升级订单状态：1-已支付待审核，2-已完成
            ];
            $teamyeji_levelup = Db::name('member_levelup_order')
                ->where($levelup_where)
                ->sum('totalprice');

            // 团队总业绩 = 商品订单业绩 + 会员升级订单业绩
            $totalPerformance = floatval($teamyeji_shop) + floatval($teamyeji_levelup);

            Log::write('calculateLineTeamPerformanceRealtime线头ID=' . $lineMid . '，团队成员数=' . count($teamMemberIds) . '，计算类型=' . $calculationType . '，商品业绩=' . $teamyeji_shop . '，升级业绩=' . $teamyeji_levelup . '，总业绩=' . $totalPerformance);

            return $totalPerformance;

        } catch (\Exception $e) {
            Log::error('calculateLineTeamPerformanceRealtime异常：' . $e->getMessage());
            return 0;
        }
    }

    /**
     * 检查用户现有线路业绩是否都达到阈值（用于新用户注册时的接点滑落判断）
     * 注意：这个方法检查的是推荐人现有的线路，不包括当前正在注册的新用户
     * @param int $mid 推荐人ID
     * @param float $threshold 业绩阈值
     * @param int $aid 站点ID
     * @param int $excludeNewUserId 排除的新用户ID（当前正在注册的用户）
     * @param string $calculationType 计算类型：turnover=流水，profit=利润
     * @return bool 是否所有现有线路都达标
     */
    public static function checkExistingLinesPerformance($mid, $threshold, $aid = 0, $excludeNewUserId = 0, $calculationType = 'turnover')
    {
        if ($aid == 0) {
            $aid = defined('aid') ? aid : 1;
        }

        $startTime = microtime(true);

        try {
            // 获取推荐人的现有接点下级（排除新注册的用户）
            $query = Db::name('member')
                ->where('aid', $aid)
                ->where('jiedianpid', $mid)
                ->field('id');

            if ($excludeNewUserId > 0) {
                $query->where('id', '<>', $excludeNewUserId);
            }

            $existingMembers = $query->select()->toArray();

            if (empty($existingMembers)) {
                return true;
            }

            // 检查每条现有接点线路的团队业绩
            $qualifiedLines = 0;
            foreach ($existingMembers as $i => $member) {
                $linePerformance = self::calculateLineTeamPerformanceRealtime($member['id'], $aid, $calculationType);

                if ($linePerformance >= $threshold) {
                    $qualifiedLines++;
                }
            }

            // 所有现有接点线路都达标才返回true
            $allQualified = ($qualifiedLines == count($existingMembers));

            return $allQualified;

        } catch (\Exception $e) {
            Log::error('checkExistingLinesPerformance异常：' . $e->getMessage());
            // 异常时返回false，按原有逻辑处理
            return false;
        }
    }
}
