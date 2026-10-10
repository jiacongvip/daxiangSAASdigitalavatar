<?php

namespace app\plugin\zhuibaoagent\common;

use think\facade\Db;

class ZhuibaoRuntimeService
{
    private const QUEUE_HEARTBEAT_KEY = 'queue_worker_heartbeat';
    private const QUEUE_HEARTBEAT_EXPIRE_SECONDS = 180;
    private static $runtimeTableEnsured = false;

    public static function getQueueHeartbeatExpireSeconds(): int
    {
        return self::QUEUE_HEARTBEAT_EXPIRE_SECONDS;
    }

    public static function getQueueLockKey(int $aid): string
    {
        return 'zhuibao_queue:lock:aid:' . max(0, intval($aid));
    }

    public static function touchQueueHeartbeat(int $aid, array $extra = []): void
    {
        self::ensureRuntimeStateTable();

        $aid = max(0, intval($aid));
        $now = time();
        $payload = [
            'worker_type' => 'zhuibao_task_queue',
            'heartbeat_time' => $now,
            'heartbeat_time_text' => date('Y-m-d H:i:s', $now),
            'expire_seconds' => self::QUEUE_HEARTBEAT_EXPIRE_SECONDS,
        ];
        if (!empty($extra)) {
            $payload = array_merge($payload, $extra);
        }

        self::saveState($aid, self::QUEUE_HEARTBEAT_KEY, $payload);
    }

    public static function getQueueRuntimeStatus(int $aid): array
    {
        self::ensureRuntimeStateTable();

        $aid = max(0, intval($aid));
        $heartbeat = self::getState($aid, self::QUEUE_HEARTBEAT_KEY);
        $now = time();
        $heartbeatTime = intval($heartbeat['heartbeat_time'] ?? 0);
        if ($aid > 0 && ($heartbeatTime <= 0 || ($now - $heartbeatTime) > self::QUEUE_HEARTBEAT_EXPIRE_SECONDS)) {
            $globalHeartbeat = self::getState(0, self::QUEUE_HEARTBEAT_KEY);
            $globalHeartbeatTime = intval($globalHeartbeat['heartbeat_time'] ?? 0);
            if ($globalHeartbeatTime > 0 && ($now - $globalHeartbeatTime) <= self::QUEUE_HEARTBEAT_EXPIRE_SECONDS) {
                $heartbeat = $globalHeartbeat;
                $heartbeatTime = $globalHeartbeatTime;
            }
        }
        $isRunning = $heartbeatTime > 0 && ($now - $heartbeatTime) <= self::QUEUE_HEARTBEAT_EXPIRE_SECONDS;

        $pendingQuery = Db::name('zhuibao_task_queue')->where('status', 0);
        $runningQuery = Db::name('zhuibao_task_queue')->where('status', 1);
        $failedQuery = Db::name('zhuibao_task_queue')->where('status', 3);
        if ($aid > 0) {
            $pendingQuery->where('aid', $aid);
            $runningQuery->where('aid', $aid);
            $failedQuery->where('aid', $aid);
        }

        $pendingCount = intval($pendingQuery->count());
        $runningCount = intval($runningQuery->count());
        $failedCount = intval($failedQuery->count());

        $primaryCommands = self::buildHostQueueCommands($aid);
        $dockerCommands = self::buildDockerQueueCommands($aid);
        $commandModeText = '商城统一异步 worker';
        $scheduleCommandLabel = '统一守护命令';
        $scheduleCycleText = '使用 Supervisor、systemd 或宝塔进程守护启动一次并保持常驻';

        $tips = [
            '图片生成、套图、部分异步任务现在统一由商城异步 worker 消费。',
            '如果待处理数量持续大于 0 且最后心跳超过 3 分钟，说明队列大概率没有启动。',
            '系统会在下一次消费时自动回收超时卡死的执行中队列，避免任务长期停在“处理中”。',
            '只需要启动 `php think async:work`，不要再为一键追爆单独启动 `zhuibao:task`。',
            '当前页面默认主显示宝塔/宿主机 PHP CLI 命令，不再自动切成 Docker 命令。',
            '如果宝塔里 `php` 命令不可用，请替换成对应版本的 PHP CLI 完整路径，例如 `/www/server/php/83/bin/php`。',
            '只有你明确需要容器执行时，再使用下面的 Docker 备用命令。',
        ];

        return [
            'running' => $isRunning ? 1 : 0,
            'status_text' => $isRunning ? '运行中' : '未检测到活跃队列',
            'status_color' => $isRunning ? 'green' : ($pendingCount > 0 ? 'red' : 'gold'),
            'heartbeat_time' => $heartbeatTime,
            'heartbeat_time_text' => $heartbeatTime > 0 ? date('Y-m-d H:i:s', $heartbeatTime) : '',
            'heartbeat_age_seconds' => $heartbeatTime > 0 ? max(0, $now - $heartbeatTime) : 0,
            'heartbeat_expire_seconds' => self::QUEUE_HEARTBEAT_EXPIRE_SECONDS,
            'running_timeout_seconds' => max(600, self::QUEUE_HEARTBEAT_EXPIRE_SECONDS * 5),
            'pending_count' => $pendingCount,
            'running_count' => $runningCount,
            'failed_count' => $failedCount,
            'command_mode_text' => $commandModeText,
            'schedule_command_label' => $scheduleCommandLabel,
            'schedule_cycle_text' => $scheduleCycleText,
            'http_schedule_command_label' => '',
            'http_schedule_cycle_text' => '',
            'http_cron_command' => '',
            'http_callback_url' => '',
            'start_command' => strval($primaryCommands['start_command'] ?? ''),
            'cron_command' => strval($primaryCommands['cron_command'] ?? ''),
            'loop_command' => strval($primaryCommands['loop_command'] ?? ''),
            'docker_start_command' => strval($dockerCommands['start_command'] ?? ''),
            'docker_cron_command' => strval($dockerCommands['cron_command'] ?? ''),
            'docker_loop_command' => strval($dockerCommands['loop_command'] ?? ''),
            'tips' => $tips,
        ];
    }

    private static function buildHttpQueueCommands(int $aid): array
    {
        $baseUrl = self::resolveCronBaseUrl();
        $configPath = root_path() . 'config.php';
        $config = [];
        if (is_file($configPath)) {
            $loaded = @include $configPath;
            if (is_array($loaded)) {
                $config = $loaded;
            }
        }

        $token = trim(strval($config['authtoken'] ?? ''));
        $query = http_build_query([
            'token' => $token,
            'aid' => max(0, intval($aid)),
            'limit' => 10,
        ]);
        $callbackUrl = rtrim($baseUrl, '/') . '/admin.php/cron_task_direct/zhuibao_queue?' . $query;

        return [
            'callback_url' => $callbackUrl,
            'cron_command' => $callbackUrl,
        ];
    }

    private static function buildHostQueueCommands(int $aid): array
    {
        $phpBinary = self::shellQuote(self::resolvePhpCliBinary());
        $thinkPath = self::shellQuote(self::normalizePath(root_path() . 'think'));
        $logPath = self::shellQuote(self::normalizePath(root_path() . 'runtime/log/async_work.log'));

        return [
            'start_command' => sprintf(
                '%s %s async:work --once',
                $phpBinary,
                $thinkPath
            ),
            'cron_command' => sprintf(
                '%s %s async:work >> %s 2>&1',
                $phpBinary,
                $thinkPath,
                $logPath
            ),
            'loop_command' => sprintf(
                '%s %s async:work',
                $phpBinary,
                $thinkPath
            ),
        ];
    }

    private static function buildDockerQueueCommands(int $aid): array
    {
        return [
            'start_command' => sprintf(
                'docker exec tpdaxiangshop_php php /app/think async:work --once'
            ),
            'cron_command' => '',
            'loop_command' => sprintf(
                'docker exec tpdaxiangshop_php php /app/think async:work'
            ),
        ];
    }

    private static function resolvePhpCliBinary(): string
    {
        $phpBinary = trim(strval(PHP_BINARY ?? ''));
        if ($phpBinary === '') {
            return 'php';
        }

        $candidates = [$phpBinary];
        $normalizedBinary = self::normalizePath($phpBinary);
        $binaryDir = dirname($normalizedBinary);

        if (preg_match('/php-cgi|phpdbg|php-fpm/i', basename($normalizedBinary))) {
            $candidates[] = rtrim($binaryDir, '/\\') . DIRECTORY_SEPARATOR . 'php';
        }

        if (preg_match('#/sbin/php-fpm[^/]*$#i', $normalizedBinary)) {
            $candidates[] = preg_replace('#/sbin/php-fpm[^/]*$#i', '/bin/php', $normalizedBinary);
        }

        foreach ($candidates as $candidate) {
            $candidate = trim(strval($candidate));
            if ($candidate === '') {
                continue;
            }
            if ($candidate === 'php') {
                return $candidate;
            }
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return 'php';
    }

    private static function normalizePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    private static function resolveCronBaseUrl(): string
    {
        $host = 'localhost';

        if (!empty($_SERVER['HTTP_HOST'])) {
            $host = trim(strval($_SERVER['HTTP_HOST']));
        } elseif (!empty($_SERVER['SERVER_NAME'])) {
            $host = trim(strval($_SERVER['SERVER_NAME']));
        }

        $scheme = 'http';
        if (
            (!empty($_SERVER['REQUEST_SCHEME']) && strtolower(strval($_SERVER['REQUEST_SCHEME'])) === 'https')
            || (!empty($_SERVER['HTTPS']) && strtolower(strval($_SERVER['HTTPS'])) !== 'off')
            || (!empty($_SERVER['SERVER_PORT']) && intval($_SERVER['SERVER_PORT']) === 443)
        ) {
            $scheme = 'https';
        }

        return $scheme . '://' . $host;
    }

    private static function shellQuote(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return "''";
        }

        if (preg_match('/^[A-Za-z0-9_\\/.:\\-]+$/', $value)) {
            return $value;
        }

        return "'" . str_replace("'", "'\\''", $value) . "'";
    }

    private static function saveState(int $aid, string $stateKey, array $payload): void
    {
        self::ensureRuntimeStateTable();

        $aid = max(0, intval($aid));
        $stateKey = trim($stateKey);
        if ($stateKey === '') {
            return;
        }

        $now = time();
        $data = [
            'aid' => $aid,
            'state_key' => $stateKey,
            'state_value' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'update_time' => $now,
        ];

        $existing = Db::name('zhuibao_runtime_state')
            ->where('aid', $aid)
            ->where('state_key', $stateKey)
            ->find();

        if ($existing) {
            Db::name('zhuibao_runtime_state')->where('id', intval($existing['id']))->update($data);
            return;
        }

        $data['create_time'] = $now;
        Db::name('zhuibao_runtime_state')->insert($data);
    }

    private static function getState(int $aid, string $stateKey): array
    {
        self::ensureRuntimeStateTable();

        $row = Db::name('zhuibao_runtime_state')
            ->where('aid', max(0, intval($aid)))
            ->where('state_key', trim($stateKey))
            ->find();
        if (!$row) {
            return [];
        }

        $decoded = json_decode(strval($row['state_value'] ?? ''), true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function ensureRuntimeStateTable(): void
    {
        if (self::$runtimeTableEnsured) {
            return;
        }
        self::$runtimeTableEnsured = true;

        $prefix = config('database.connections.mysql.prefix');
        $tableName = $prefix . 'zhuibao_runtime_state';
        $exists = Db::query("SHOW TABLES LIKE '{$tableName}'");
        if (!empty($exists)) {
            return;
        }

        Db::execute("
CREATE TABLE IF NOT EXISTS `{$tableName}` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL DEFAULT '0',
  `state_key` varchar(80) NOT NULL DEFAULT '',
  `state_value` longtext,
  `create_time` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_aid_state_key` (`aid`,`state_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=COMPACT COMMENT='一键追爆运行态'
");
    }
}
