<?php
namespace app\admin\common;

use think\facade\Db;
use think\facade\Log;

class AsyncWorkerLogService
{
    private const TABLE = 'async_worker_log';
    private const MAX_TEXT_LENGTH = 500;

    private static $tableEnsured = false;

    public static function record(array $data): void
    {
        try {
            self::ensureTable();
            $now = time();
            $payload = [
                'aid' => max(0, intval($data['aid'] ?? 0)),
                'bid' => max(0, intval($data['bid'] ?? 0)),
                'uid' => max(0, intval($data['uid'] ?? 0)),
                'worker_key' => self::limitText($data['worker_key'] ?? 'async_work', 50),
                'task_type' => self::limitText($data['task_type'] ?? '', 50),
                'queue_key' => self::limitText($data['queue_key'] ?? '', 120),
                'task_id' => self::limitText($data['task_id'] ?? '', 100),
                'status' => self::normalizeStatus($data['status'] ?? 'success'),
                'message' => self::limitText($data['message'] ?? '', self::MAX_TEXT_LENGTH),
                'duration_ms' => max(0, intval($data['duration_ms'] ?? 0)),
                'payload_json' => self::encodeJson($data['payload'] ?? ($data['payload_json'] ?? null)),
                'result_json' => self::encodeJson($data['result'] ?? ($data['result_json'] ?? null)),
                'error_msg' => self::limitText($data['error_msg'] ?? '', 2000),
                'create_time' => max(0, intval($data['create_time'] ?? $now)),
            ];

            Db::name(self::TABLE)->insert($payload);
        } catch (\Throwable $e) {
            Log::write([
                'service' => __CLASS__,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public static function getList(array $params = []): array
    {
        self::ensureTable();

        $page = max(1, intval($params['page'] ?? 1));
        $limit = min(100, max(1, intval($params['limit'] ?? 20)));
        $where = [];

        $taskType = trim(strval($params['task_type'] ?? ''));
        if ($taskType !== '') {
            $where[] = ['task_type', '=', $taskType];
        }

        $status = trim(strval($params['status'] ?? ''));
        if ($status !== '') {
            $where[] = ['status', '=', $status];
        }

        $queueKey = trim(strval($params['queue_key'] ?? ''));
        if ($queueKey !== '') {
            $where[] = ['queue_key', '=', $queueKey];
        }

        $taskId = trim(strval($params['task_id'] ?? ''));
        if ($taskId !== '') {
            $where[] = ['task_id', '=', $taskId];
        }

        $keyword = trim(strval($params['keyword'] ?? ''));

        $query = Db::name(self::TABLE)->where($where);
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $like = '%' . $keyword . '%';
                $q->where('task_id', 'like', $like)
                    ->whereOr('message', 'like', $like)
                    ->whereOr('error_msg', 'like', $like)
                    ->whereOr('queue_key', 'like', $like);
            });
        }

        $total = 0 + $query->count();

        $listQuery = Db::name(self::TABLE)->where($where);
        if ($keyword !== '') {
            $listQuery->where(function ($q) use ($keyword) {
                $like = '%' . $keyword . '%';
                $q->where('task_id', 'like', $like)
                    ->whereOr('message', 'like', $like)
                    ->whereOr('error_msg', 'like', $like)
                    ->whereOr('queue_key', 'like', $like);
            });
        }

        $list = $listQuery
            ->order('id desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        foreach ($list as &$row) {
            $row['create_time_text'] = !empty($row['create_time']) ? date('Y-m-d H:i:s', intval($row['create_time'])) : '';
            $row['status_text'] = self::statusText(strval($row['status'] ?? ''));
            $row['task_type_text'] = self::taskTypeText(strval($row['task_type'] ?? ''));
            $row['duration_text'] = self::formatDuration(intval($row['duration_ms'] ?? 0));
        }

        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    public static function taskTypeText(string $taskType): string
    {
        $map = [
            'async_worker' => '系统日志',
            'member_export' => '会员导出',
            'shop_order_export' => '订单导出',
            'sweepstakes_order_export' => '幸运抽奖订单导出',
            'customdiy_order_export' => '定制订单导出',
            'customdiy_order_package' => '定制订单生产包',
            'zhuibao_task' => '一键追爆任务',
            'geooptimizer_task' => 'GEO优化任务',
            'member_uplv' => '会员升级',
            'performance_cache' => '业绩缓存',
            'zichanfenqi' => '资产分期',
            'tokenrelease' => '分销持续奖励',
        ];
        return $map[$taskType] ?? ($taskType !== '' ? $taskType : '未知任务');
    }

    public static function statusText(string $status): string
    {
        $map = [
            'success' => '成功',
            'failed' => '失败',
            'skipped' => '跳过',
        ];
        return $map[$status] ?? ($status !== '' ? $status : '未知');
    }

    public static function ensureTable(): void
    {
        if (self::$tableEnsured) {
            return;
        }

        $prefix = config('database.connections.mysql.prefix', '');
        $table = $prefix . self::TABLE;
        Db::execute("CREATE TABLE IF NOT EXISTS `{$table}` (
            `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '日志ID',
            `aid` int(11) NOT NULL DEFAULT '0' COMMENT '站点ID',
            `bid` int(11) NOT NULL DEFAULT '0' COMMENT '商家ID',
            `uid` int(11) NOT NULL DEFAULT '0' COMMENT '触发后台用户ID',
            `worker_key` varchar(50) NOT NULL DEFAULT 'async_work' COMMENT 'worker标识',
            `task_type` varchar(50) NOT NULL DEFAULT '' COMMENT '任务类型',
            `queue_key` varchar(120) NOT NULL DEFAULT '' COMMENT '队列Key',
            `task_id` varchar(100) NOT NULL DEFAULT '' COMMENT '任务ID',
            `status` varchar(20) NOT NULL DEFAULT 'success' COMMENT '执行状态',
            `message` varchar(500) NOT NULL DEFAULT '' COMMENT '执行说明',
            `duration_ms` int(11) NOT NULL DEFAULT '0' COMMENT '耗时毫秒',
            `payload_json` mediumtext NULL COMMENT '任务载荷JSON',
            `result_json` mediumtext NULL COMMENT '执行结果JSON',
            `error_msg` text NULL COMMENT '错误信息',
            `create_time` int(11) NOT NULL DEFAULT '0' COMMENT '创建时间',
            PRIMARY KEY (`id`),
            KEY `idx_task_type_time` (`task_type`,`create_time`),
            KEY `idx_status_time` (`status`,`create_time`),
            KEY `idx_queue_task` (`queue_key`(80),`task_id`(80)),
            KEY `idx_aid_time` (`aid`,`create_time`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商城异步worker执行日志'");

        self::$tableEnsured = true;
    }

    private static function normalizeStatus($status): string
    {
        $status = trim(strval($status));
        if ($status === 'completed') {
            return 'success';
        }
        if (!in_array($status, ['success', 'failed', 'skipped'], true)) {
            return $status !== '' ? self::limitText($status, 20) : 'success';
        }
        return $status;
    }

    private static function encodeJson($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (is_string($value)) {
            return $value;
        }
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($json) ? $json : '';
    }

    private static function limitText($value, int $length): string
    {
        $value = trim(strval($value));
        if ($value === '') {
            return '';
        }
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $length, 'UTF-8');
        }
        return substr($value, 0, $length);
    }

    private static function formatDuration(int $durationMs): string
    {
        if ($durationMs <= 0) {
            return '-';
        }
        if ($durationMs < 1000) {
            return $durationMs . 'ms';
        }
        return round($durationMs / 1000, 2) . 's';
    }
}
