<?php
namespace app\admin\common;

use think\facade\Db;
use think\facade\Log;

class SimulateLoginTokenService
{
    private const TABLE = 'admin_simulate_login_token';
    private const CACHE_PREFIX = 'simulate_login_';
    private const DEFAULT_TTL = 300;

    private static $tableEnsured = false;
    private static $tableAvailable = null;

    public static function create(int $aid, int $mid, int $adminUid, int $ttl = self::DEFAULT_TTL): array
    {
        $ttl = max(60, $ttl);
        $now = time();
        $token = self::generateToken();
        $data = [
            'mid' => $mid,
            'aid' => $aid,
            'admin_uid' => $adminUid,
            'create_time' => $now,
            'expire_time' => $now + $ttl,
        ];

        cache(self::CACHE_PREFIX . $token, $data, $ttl);

        if (self::ensureTable()) {
            try {
                Db::name(self::TABLE)->insert([
                    'aid' => $aid,
                    'mid' => $mid,
                    'admin_uid' => $adminUid,
                    'token_hash' => self::hashToken($token),
                    'used_time' => 0,
                    'expire_time' => $data['expire_time'],
                    'create_time' => $now,
                    'update_time' => $now,
                ]);
                self::cleanupExpired();
            } catch (\Throwable $e) {
                Log::write('模拟登录令牌数据库写入失败：' . $e->getMessage(), 'error');
            }
        }

        return [
            'token' => $token,
            'data' => $data,
        ];
    }

    public static function findValid(string $token, int $aid): array
    {
        $token = self::sanitizeToken($token);
        if ($token === '' || $aid <= 0) {
            return [];
        }

        $cacheKey = self::CACHE_PREFIX . $token;
        $cacheData = cache($cacheKey);
        if (self::isValidData($cacheData, $aid)) {
            return self::normalizeData($cacheData);
        }

        if (!self::ensureTable()) {
            return [];
        }

        try {
            $row = Db::name(self::TABLE)->where('token_hash', self::hashToken($token))->find();
            if (!$row) {
                return [];
            }
            if (intval($row['aid'] ?? 0) !== $aid) {
                return [];
            }
            if (intval($row['used_time'] ?? 0) > 0) {
                return [];
            }
            if (intval($row['expire_time'] ?? 0) <= time()) {
                return [];
            }

            $data = [
                'mid' => intval($row['mid'] ?? 0),
                'aid' => intval($row['aid'] ?? 0),
                'admin_uid' => intval($row['admin_uid'] ?? 0),
                'create_time' => intval($row['create_time'] ?? 0),
                'expire_time' => intval($row['expire_time'] ?? 0),
            ];
            $ttl = max(1, intval($data['expire_time']) - time());
            cache($cacheKey, $data, $ttl);

            return self::normalizeData($data);
        } catch (\Throwable $e) {
            Log::write('模拟登录令牌数据库读取失败：' . $e->getMessage(), 'error');
            return [];
        }
    }

    public static function markUsed(string $token, int $aid = 0): void
    {
        $token = self::sanitizeToken($token);
        if ($token === '') {
            return;
        }

        cache(self::CACHE_PREFIX . $token, null);

        if (!self::ensureTable()) {
            return;
        }

        try {
            $query = Db::name(self::TABLE)->where('token_hash', self::hashToken($token))->where('used_time', 0);
            if ($aid > 0) {
                $query->where('aid', $aid);
            }
            $query->update([
                'used_time' => time(),
                'update_time' => time(),
            ]);
        } catch (\Throwable $e) {
            Log::write('模拟登录令牌标记已使用失败：' . $e->getMessage(), 'error');
        }
    }

    private static function ensureTable(): bool
    {
        if (self::$tableEnsured) {
            return self::$tableAvailable === true;
        }

        self::$tableEnsured = true;

        try {
            $prefix = config('database.connections.mysql.prefix', '');
            $table = $prefix . self::TABLE;
            Db::execute("CREATE TABLE IF NOT EXISTS `{$table}` (
                `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
                `aid` int(11) NOT NULL DEFAULT '0' COMMENT 'site id',
                `mid` int(11) NOT NULL DEFAULT '0' COMMENT 'member id',
                `admin_uid` int(11) NOT NULL DEFAULT '0' COMMENT 'admin user id',
                `token_hash` varchar(64) NOT NULL DEFAULT '' COMMENT 'sha256 token hash',
                `used_time` int(11) NOT NULL DEFAULT '0' COMMENT 'used time',
                `expire_time` int(11) NOT NULL DEFAULT '0' COMMENT 'expire time',
                `create_time` int(11) NOT NULL DEFAULT '0' COMMENT 'create time',
                `update_time` int(11) NOT NULL DEFAULT '0' COMMENT 'update time',
                PRIMARY KEY (`id`),
                UNIQUE KEY `idx_token_hash` (`token_hash`),
                KEY `idx_aid_token` (`aid`,`token_hash`),
                KEY `idx_expire_time` (`expire_time`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='admin simulate member login token'");
            self::$tableAvailable = true;
        } catch (\Throwable $e) {
            self::$tableAvailable = false;
            Log::write('模拟登录令牌表初始化失败：' . $e->getMessage(), 'error');
        }

        return self::$tableAvailable === true;
    }

    private static function cleanupExpired(): void
    {
        if (mt_rand(1, 50) !== 1) {
            return;
        }

        Db::name(self::TABLE)->where('expire_time', '<', time() - 86400)->delete();
    }

    private static function isValidData($data, int $aid): bool
    {
        if (!is_array($data)) {
            return false;
        }
        if (intval($data['aid'] ?? 0) !== $aid) {
            return false;
        }
        if (intval($data['mid'] ?? 0) <= 0) {
            return false;
        }
        $expireTime = intval($data['expire_time'] ?? 0);
        return $expireTime <= 0 || $expireTime > time();
    }

    private static function normalizeData(array $data): array
    {
        return [
            'mid' => intval($data['mid'] ?? 0),
            'aid' => intval($data['aid'] ?? 0),
            'admin_uid' => intval($data['admin_uid'] ?? 0),
            'create_time' => intval($data['create_time'] ?? 0),
            'expire_time' => intval($data['expire_time'] ?? 0),
        ];
    }

    private static function generateToken(): string
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (\Throwable $e) {
            return md5(uniqid(mt_rand(), true) . microtime(true));
        }
    }

    private static function sanitizeToken(string $token): string
    {
        return preg_replace('/[^0-9a-zA-Z]/', '', $token) ?: '';
    }

    private static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
