<?php

namespace app\admin\common;

use think\facade\Db;

class PageVisitStatService
{
    private const SITE_SCENE = 'site';
    private static $tablesEnsured = false;

    public static function ensureTables(): void
    {
        if (self::$tablesEnsured) {
            return;
        }

        $prefix = config('database.connections.mysql.prefix', '');

        Db::execute("
            CREATE TABLE IF NOT EXISTS `{$prefix}page_visit_log` (
              `id` bigint unsigned NOT NULL AUTO_INCREMENT,
              `aid` int unsigned NOT NULL DEFAULT 0,
              `bid` int NOT NULL DEFAULT 0,
              `mid` int unsigned NOT NULL DEFAULT 0,
              `visitor_key` varchar(64) NOT NULL DEFAULT '',
              `session_key` varchar(64) NOT NULL DEFAULT '',
              `scene` varchar(64) NOT NULL DEFAULT 'mobile',
              `page_path` varchar(255) NOT NULL DEFAULT '',
              `page_key` char(32) NOT NULL DEFAULT '',
              `page_title` varchar(120) NOT NULL DEFAULT '',
              `query_hash` char(32) NOT NULL DEFAULT '',
              `platform` varchar(32) NOT NULL DEFAULT '',
              `ip` varchar(45) NOT NULL DEFAULT '',
              `user_agent` varchar(255) NOT NULL DEFAULT '',
              `stat_day` int unsigned NOT NULL DEFAULT 0,
              `createtime` int unsigned NOT NULL DEFAULT 0,
              `updatetime` int unsigned NOT NULL DEFAULT 0,
              PRIMARY KEY (`id`),
              KEY `idx_aid_bid_day` (`aid`,`bid`,`stat_day`),
              KEY `idx_visitor_day` (`aid`,`bid`,`visitor_key`,`stat_day`),
              KEY `idx_session_day` (`aid`,`bid`,`session_key`,`stat_day`),
              KEY `idx_page_day` (`aid`,`bid`,`scene`,`page_key`,`stat_day`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='前台页面访问日志';
        ");

        Db::execute("
            CREATE TABLE IF NOT EXISTS `{$prefix}page_visit_daily_stat` (
              `id` bigint unsigned NOT NULL AUTO_INCREMENT,
              `aid` int unsigned NOT NULL DEFAULT 0,
              `bid` int NOT NULL DEFAULT 0,
              `stat_day` int unsigned NOT NULL DEFAULT 0,
              `scene` varchar(64) NOT NULL DEFAULT 'site',
              `page_path` varchar(255) NOT NULL DEFAULT '',
              `page_key` char(32) NOT NULL DEFAULT '',
              `pv` int unsigned NOT NULL DEFAULT 0,
              `uv` int unsigned NOT NULL DEFAULT 0,
              `vv` int unsigned NOT NULL DEFAULT 0,
              `createtime` int unsigned NOT NULL DEFAULT 0,
              `updatetime` int unsigned NOT NULL DEFAULT 0,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uniq_day_page` (`aid`,`bid`,`stat_day`,`scene`,`page_key`),
              KEY `idx_aid_bid_day` (`aid`,`bid`,`stat_day`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='前台页面访问按天汇总';
        ");

        self::$tablesEnsured = true;
    }

    public static function track(array $payload): array
    {
        self::ensureTables();

        $now = time();
        $statDay = strtotime(date('Y-m-d', $now));
        $aid = max(0, intval($payload['aid'] ?? 0));
        if ($aid <= 0) {
            return ['status' => 0, 'msg' => 'aid missing'];
        }

        $bid = max(0, intval($payload['bid'] ?? 0));
        $mid = max(0, intval($payload['mid'] ?? 0));
        $scene = self::sanitizeKey((string)($payload['scene'] ?? 'mobile'), 'mobile', 64);
        $pagePath = self::normalizePagePath((string)($payload['page_path'] ?? ''));
        $pageKey = self::pageKey($scene, $pagePath);
        $visitorKey = self::sanitizeKey((string)($payload['visitor_key'] ?? ''), '', 64);
        $sessionKey = self::sanitizeKey((string)($payload['session_key'] ?? ''), '', 64);
        $platform = self::sanitizeKey((string)($payload['platform'] ?? ''), '', 32);
        $pageTitle = self::trimText((string)($payload['page_title'] ?? ''), 120);
        $queryHash = self::sanitizeHash((string)($payload['query_hash'] ?? ''));
        $ip = self::trimText((string)($payload['ip'] ?? ''), 45);
        $userAgent = self::trimText((string)($payload['user_agent'] ?? ''), 255);

        if ($visitorKey === '') {
            $visitorKey = $mid > 0 ? 'mid_' . $mid : 'guest_' . md5($ip . '|' . $userAgent);
        }
        if ($sessionKey === '') {
            $sessionKey = 'auto_' . md5($visitorKey . '|' . floor($now / 1800));
        }

        $sitePageKey = self::pageKey(self::SITE_SCENE, '');
        $isNewSiteUv = self::isFirstVisitForDay($aid, $bid, $statDay, 'visitor_key', $visitorKey);
        $isNewSiteVv = self::isFirstVisitForDay($aid, $bid, $statDay, 'session_key', $sessionKey);
        $isNewPageUv = self::isFirstVisitForDay($aid, $bid, $statDay, 'visitor_key', $visitorKey, $scene, $pageKey);
        $isNewPageVv = self::isFirstVisitForDay($aid, $bid, $statDay, 'session_key', $sessionKey, $scene, $pageKey);

        Db::name('page_visit_log')->insert([
            'aid' => $aid,
            'bid' => $bid,
            'mid' => $mid,
            'visitor_key' => $visitorKey,
            'session_key' => $sessionKey,
            'scene' => $scene,
            'page_path' => $pagePath,
            'page_key' => $pageKey,
            'page_title' => $pageTitle,
            'query_hash' => $queryHash,
            'platform' => $platform,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'stat_day' => $statDay,
            'createtime' => $now,
            'updatetime' => $now,
        ]);

        self::increaseDailyStat($aid, $bid, $statDay, self::SITE_SCENE, '', $sitePageKey, 1, $isNewSiteUv ? 1 : 0, $isNewSiteVv ? 1 : 0, $now);
        self::increaseDailyStat($aid, $bid, $statDay, $scene, $pagePath, $pageKey, 1, $isNewPageUv ? 1 : 0, $isNewPageVv ? 1 : 0, $now);

        return [
            'status' => 1,
            'pv' => 1,
            'uv' => $isNewSiteUv ? 1 : 0,
            'vv' => $isNewSiteVv ? 1 : 0,
        ];
    }

    public static function getSummary(int $aid, int $bid, bool $includeAllBids = false): array
    {
        $empty = [
            'viewCount' => 0,
            'todayView' => 0,
            'yesterdayView' => 0,
            'viewThisMonth' => 0,
            'pvCount' => 0,
            'todayPv' => 0,
            'yesterdayPv' => 0,
            'pvThisMonth' => 0,
            'uvCount' => 0,
            'todayUv' => 0,
            'yesterdayUv' => 0,
            'uvThisMonth' => 0,
            'vvCount' => 0,
            'todayVv' => 0,
            'yesterdayVv' => 0,
            'vvThisMonth' => 0,
        ];

        try {
            self::ensureTables();
            $todayStart = strtotime(date('Y-m-d'));
            $yesterdayStart = $todayStart - 86400;
            $thisMonthStart = strtotime(date('Y-m-1'));

            $total = self::sumSiteRange($aid, $bid, 0, 0, $includeAllBids);
            $today = self::sumSiteRange($aid, $bid, $todayStart, $todayStart + 86400, $includeAllBids);
            $yesterday = self::sumSiteRange($aid, $bid, $yesterdayStart, $todayStart, $includeAllBids);
            $month = self::sumSiteRange($aid, $bid, $thisMonthStart, time() + 1, $includeAllBids);

            return [
                'viewCount' => intval($total['pv']),
                'todayView' => intval($today['pv']),
                'yesterdayView' => intval($yesterday['pv']),
                'viewThisMonth' => intval($month['pv']),
                'pvCount' => intval($total['pv']),
                'todayPv' => intval($today['pv']),
                'yesterdayPv' => intval($yesterday['pv']),
                'pvThisMonth' => intval($month['pv']),
                'uvCount' => intval($total['uv']),
                'todayUv' => intval($today['uv']),
                'yesterdayUv' => intval($yesterday['uv']),
                'uvThisMonth' => intval($month['uv']),
                'vvCount' => intval($total['vv']),
                'todayVv' => intval($today['vv']),
                'yesterdayVv' => intval($yesterday['vv']),
                'vvThisMonth' => intval($month['vv']),
            ];
        } catch (\Throwable $e) {
            return $empty;
        }
    }

    public static function getTrendData(int $aid, int $bid, string $metric, int $startDay, int $days = 30, bool $includeAllBids = false): array
    {
        $metric = in_array($metric, ['pv', 'uv', 'vv'], true) ? $metric : 'pv';
        $days = max(1, min(90, intval($days)));
        $result = [];
        for ($i = 0; $i < $days; $i++) {
            $result[$startDay + $i * 86400] = 0;
        }

        try {
            self::ensureTables();
            $query = Db::name('page_visit_daily_stat')
                ->where('aid', max(0, $aid))
                ->where('scene', self::SITE_SCENE)
                ->where('page_key', self::pageKey(self::SITE_SCENE, ''))
                ->where('stat_day', '>=', $startDay)
                ->where('stat_day', '<', $startDay + $days * 86400)
                ->fieldRaw('stat_day,SUM(' . $metric . ') as ' . $metric)
                ->group('stat_day');

            if (!$includeAllBids) {
                $query->where('bid', max(0, $bid));
            }

            $rows = $query
                ->select()
                ->toArray();

            foreach ($rows as $row) {
                $day = intval($row['stat_day'] ?? 0);
                if (array_key_exists($day, $result)) {
                    $result[$day] = intval($row[$metric] ?? 0);
                }
            }
        } catch (\Throwable $e) {
        }

        return array_values($result);
    }

    private static function sumSiteRange(int $aid, int $bid, int $start, int $end, bool $includeAllBids = false): array
    {
        $query = Db::name('page_visit_daily_stat')
            ->where('aid', max(0, $aid))
            ->where('scene', self::SITE_SCENE)
            ->where('page_key', self::pageKey(self::SITE_SCENE, ''));

        if (!$includeAllBids) {
            $query->where('bid', max(0, $bid));
        }

        if ($start > 0) {
            $query->where('stat_day', '>=', $start);
        }
        if ($end > 0) {
            $query->where('stat_day', '<', $end);
        }

        $row = $query->fieldRaw('SUM(pv) as pv,SUM(uv) as uv,SUM(vv) as vv')->find();
        return [
            'pv' => intval($row['pv'] ?? 0),
            'uv' => intval($row['uv'] ?? 0),
            'vv' => intval($row['vv'] ?? 0),
        ];
    }

    private static function isFirstVisitForDay(
        int $aid,
        int $bid,
        int $statDay,
        string $field,
        string $value,
        string $scene = '',
        string $pageKey = ''
    ): bool {
        if ($value === '' || !in_array($field, ['visitor_key', 'session_key'], true)) {
            return false;
        }

        $query = Db::name('page_visit_log')
            ->where('aid', $aid)
            ->where('bid', $bid)
            ->where('stat_day', $statDay)
            ->where($field, $value);

        if ($scene !== '') {
            $query->where('scene', $scene);
        }
        if ($pageKey !== '') {
            $query->where('page_key', $pageKey);
        }

        return intval($query->count()) === 0;
    }

    private static function increaseDailyStat(
        int $aid,
        int $bid,
        int $statDay,
        string $scene,
        string $pagePath,
        string $pageKey,
        int $pv,
        int $uv,
        int $vv,
        int $now
    ): void {
        $where = [
            ['aid', '=', $aid],
            ['bid', '=', $bid],
            ['stat_day', '=', $statDay],
            ['scene', '=', $scene],
            ['page_key', '=', $pageKey],
        ];

        $row = Db::name('page_visit_daily_stat')->where($where)->find();
        if ($row) {
            $query = Db::name('page_visit_daily_stat')->where('id', intval($row['id']))->inc('pv', $pv);
            if ($uv > 0) {
                $query->inc('uv', $uv);
            }
            if ($vv > 0) {
                $query->inc('vv', $vv);
            }
            $query->update(['updatetime' => $now]);
            return;
        }

        try {
            Db::name('page_visit_daily_stat')->insert([
                'aid' => $aid,
                'bid' => $bid,
                'stat_day' => $statDay,
                'scene' => $scene,
                'page_path' => $pagePath,
                'page_key' => $pageKey,
                'pv' => $pv,
                'uv' => $uv,
                'vv' => $vv,
                'createtime' => $now,
                'updatetime' => $now,
            ]);
        } catch (\Throwable $e) {
            $query = Db::name('page_visit_daily_stat')->where($where)->inc('pv', $pv);
            if ($uv > 0) {
                $query->inc('uv', $uv);
            }
            if ($vv > 0) {
                $query->inc('vv', $vv);
            }
            $query->update(['updatetime' => $now]);
        }
    }

    private static function pageKey(string $scene, string $pagePath): string
    {
        return md5($scene . '|' . $pagePath);
    }

    private static function normalizePagePath(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '/';
        }
        if (strpos($value, 'http://') === 0 || strpos($value, 'https://') === 0) {
            $path = parse_url($value, PHP_URL_PATH);
            $value = is_string($path) && $path !== '' ? $path : '/';
        }
        if ($value[0] !== '/') {
            $value = '/' . $value;
        }
        $value = preg_replace('/[\x00-\x1F\x7F]/', '', $value);
        return self::trimText($value, 255);
    }

    private static function sanitizeKey(string $value, string $fallback = '', int $maxLength = 64): string
    {
        $value = preg_replace('/[^a-zA-Z0-9_\-:.]/', '', trim($value));
        $value = substr($value, 0, $maxLength);
        return $value !== '' ? $value : $fallback;
    }

    private static function sanitizeHash(string $value): string
    {
        $value = strtolower(trim($value));
        if (preg_match('/^[a-f0-9]{32}$/', $value)) {
            return $value;
        }
        return $value !== '' ? md5($value) : '';
    }

    private static function trimText(string $value, int $maxLength): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $maxLength);
        }
        return substr($value, 0, $maxLength);
    }
}
