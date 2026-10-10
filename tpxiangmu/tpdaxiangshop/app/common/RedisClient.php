<?php
namespace app\common;

use think\facade\Config;
use think\facade\Log;

class RedisClient
{
    // 缓存的Redis连接实例
    private static $instance = null;
    
    // 记录上次连接失败的时间，避免频繁重试
    private static $lastFailTime = 0;
    
    // 连接失败后的冷却时间（秒）
    private static $failCooldown = 30;
    
    // 标记是否已检查过可用性
    private static $checkedAvailable = false;
    private static $isAvailable = false;
    
    /**
     * 获取 Redis 客户端（直接使用 php-redis 扩展）
     * 优化：使用连接缓存，避免重复连接；连接失败后短时间内不再重试
     * 连接超时：1秒，读写超时：2秒，失败冷却期：30秒
     */
    public static function client()
    {
        // 如果有缓存的连接，直接返回
        if (self::$instance !== null) {
            try {
                // 检查连接是否还有效（ping测试，设置超时避免阻塞）
                $pong = @self::$instance->ping();
                if ($pong === true || $pong === '+PONG' || $pong === 'PONG') {
                    return self::$instance;
                }
            } catch (\Throwable $e) {
                // 连接已断开，清除缓存
                Log::write('RedisClient: ping失败，连接已断开：' . $e->getMessage());
                self::$instance = null;
            }
        }
        
        // 如果最近连接失败过，跳过重试（冷却期内）
        if (self::$lastFailTime > 0 && (time() - self::$lastFailTime) < self::$failCooldown) {
            return null;
        }
        
        try {
            // 优先尝试直接使用 php-redis 扩展，避免 ThinkPHP Cache 层可能的异常
            $class = '\\Redis';
            if (class_exists($class)) {
                $host = Config::get('cache.stores.redis.host', '127.0.0.1');
                $port = Config::get('cache.stores.redis.port', 6379);
                $password = Config::get('cache.stores.redis.password', null);
                $db = Config::get('cache.stores.redis.select', 0);
                $redis = new $class();
                
                // 设置连接超时时间（1秒），避免长时间卡住
                $connected = $redis->connect($host, $port, 1, null, 0, 1);
                if (!$connected) {
                    self::$lastFailTime = time();
                    Log::write('RedisClient: 连接失败，主机：' . $host . ':' . $port);
                    return null;
                }
                
                // 设置读写超时（2秒），防止操作阻塞导致 Connection reset
                $redis->setOption(\Redis::OPT_READ_TIMEOUT, 2);
                
                if (!empty($password)) $redis->auth($password);
                if ($db) $redis->select($db);
                self::$instance = $redis;
                self::$lastFailTime = 0;
                return $redis;
            }
        } catch (\Throwable $e) {
            self::$lastFailTime = time();
            Log::write(['cls'=>__CLASS__, 'fn'=>__FUNCTION__, 'error'=>$e->getMessage()]);
        }
        return null;
    }

    /**
     * 检查Redis是否可用
     * 优化：缓存检查结果，避免重复连接测试
     */
    public static function available(): bool
    {
        // 如果已经检查过，直接返回缓存结果
        if (self::$checkedAvailable) {
            // 如果之前失败过且还在冷却期，返回false
            if (!self::$isAvailable && self::$lastFailTime > 0 && (time() - self::$lastFailTime) < self::$failCooldown) {
                return false;
            }
            // 如果之前成功过，返回缓存结果
            if (self::$isAvailable) {
                return true;
            }
        }
        
        try {
            // 先检查php-redis扩展是否安装
            if (!extension_loaded('redis') && !class_exists('\\Redis')) {
                self::$checkedAvailable = true;
                self::$isAvailable = false;
                Log::write('RedisClient: php-redis扩展未安装');
                return false;
            }
            
            // 快速端口检查（0.5秒超时）
            $host = Config::get('cache.stores.redis.host', '127.0.0.1');
            $port = Config::get('cache.stores.redis.port', 6379);
            
            $socket = @fsockopen($host, $port, $errno, $errstr, 0.5);
            if (!$socket) {
                self::$checkedAvailable = true;
                self::$isAvailable = false;
                self::$lastFailTime = time();
                Log::write('RedisClient: 端口检查失败，' . $host . ':' . $port . ' 不可达，错误：' . $errstr);
                return false;
            }
            @fclose($socket);
            
            self::$checkedAvailable = true;
            self::$isAvailable = self::client() ? true : false;
            return self::$isAvailable;
        } catch (\Throwable $e) {
            self::$checkedAvailable = true;
            self::$isAvailable = false;
            self::$lastFailTime = time();
            Log::write('RedisClient: available检查异常：' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 重置连接状态（用于强制重新连接）
     */
    public static function reset(): void
    {
        self::$instance = null;
        self::$lastFailTime = 0;
        self::$checkedAvailable = false;
        self::$isAvailable = false;
    }

    public static function get(string $key)
    {
        try {
            $r = self::client();
            return $r ? $r->get($key) : null;
        } catch (\Throwable $e) {
            Log::write('RedisClient: get异常，key=' . $key . '，错误：' . $e->getMessage());
            return null;
        }
    }

    public static function set(string $key, $value, int $ttl = null): bool
    {
        try {
            $r = self::client();
            if (!$r) return false;
            if ($ttl !== null && $ttl > 0) return (bool)$r->setex($key, $ttl, $value);
            return (bool)$r->set($key, $value);
        } catch (\Throwable $e) {
            Log::write('RedisClient: set异常，key=' . $key . '，错误：' . $e->getMessage());
            return false;
        }
    }

    public static function setJson(string $key, $value, int $ttl = null): bool
    {
        return self::set($key, json_encode($value, JSON_UNESCAPED_UNICODE), $ttl);
    }

    public static function getJson(string $key)
    {
        $val = self::get($key);
        return $val ? json_decode($val, true) : null;
    }

    public static function del(string $key): int
    {
        try {
            $r = self::client();
            return $r ? (int)$r->del($key) : 0;
        } catch (\Throwable $e) {
            Log::write('RedisClient: del异常，key=' . $key . '，错误：' . $e->getMessage());
            return 0;
        }
    }

    public static function exists(string $key): bool
    {
        try {
            $r = self::client();
            return $r ? (bool)$r->exists($key) : false;
        } catch (\Throwable $e) {
            Log::write('RedisClient: exists异常，key=' . $key . '，错误：' . $e->getMessage());
            return false;
        }
    }

    public static function rPush(string $queue, string $value): int
    {
        try {
            $r = self::client();
            return $r ? (int)$r->rPush($queue, $value) : 0;
        } catch (\Throwable $e) {
            Log::write('RedisClient: rPush异常，queue=' . $queue . '，错误：' . $e->getMessage());
            return 0;
        }
    }

    public static function lPush(string $queue, string $value): int
    {
        try {
            $r = self::client();
            return $r ? (int)$r->lPush($queue, $value) : 0;
        } catch (\Throwable $e) {
            Log::write('RedisClient: lPush异常，queue=' . $queue . '，错误：' . $e->getMessage());
            return 0;
        }
    }

    public static function brPop(array $queues, int $timeout = 5)
    {
        $r = self::client();
        if (!$r) return null;
        
        try {
            // 限制最大阻塞时间为10秒，避免长时间阻塞
            $timeout = min($timeout, 10);
            return $r->brPop($queues, $timeout);
        } catch (\Throwable $e) {
            Log::write('RedisClient: brPop异常：' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 增量计数器（带过期时间）
     */
    public static function incr(string $key, int $ttl = null): int
    {
        $r = self::client();
        if (!$r) return 0;
        
        try {
            $val = $r->incr($key);
            if ($ttl !== null && $ttl > 0 && $val == 1) {
                $r->expire($key, $ttl);
            }
            return (int)$val;
        } catch (\Throwable $e) {
            Log::write('RedisClient: incr异常：' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * 安全执行Redis操作（带异常保护）
     */
    public static function safeExecute(callable $callback, $default = null)
    {
        try {
            $r = self::client();
            if (!$r) return $default;
            return $callback($r);
        } catch (\Throwable $e) {
            Log::write('RedisClient: safeExecute异常：' . $e->getMessage());
            return $default;
        }
    }
}



