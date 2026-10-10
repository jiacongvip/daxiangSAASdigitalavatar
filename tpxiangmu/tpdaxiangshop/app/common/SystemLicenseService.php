<?php

namespace app\common;

class SystemLicenseService
{
    const DEFAULT_CHECK_INTERVAL = 86400;
    const DEFAULT_OFFLINE_GRACE = 259200;

    public static function check(bool $forceOnline = false): array
    {
        $package = self::packageConfig();
        if (empty($package['license_required'])) {
            return self::ok(['enabled' => 0], '未启用运行期授权');
        }

        $paths = self::paths();
        if (!is_file($paths['lock'])) {
            return self::fail('系统未完成授权安装，请先通过 install.php 安装', 'install_lock_missing');
        }
        if (!is_file($paths['cache'])) {
            return self::fail('本地授权缓存不存在，请重新安装或联系服务商', 'license_cache_missing');
        }

        $cache = self::readJson($paths['cache']);
        if (!$cache) {
            return self::fail('本地授权缓存损坏，请重新安装或联系服务商', 'license_cache_invalid');
        }

        $local = self::validateLocalCache($package, $cache);
        if (!$local['status']) {
            return $local;
        }

        if (!$forceOnline && !self::shouldCheckOnline($package, $cache)) {
            return self::ok([
                'enabled' => 1,
                'source' => 'local_cache',
                'next_check_at' => self::nextCheckAt($package, $cache),
            ], '本地授权缓存有效');
        }

        $online = self::checkOnline($package, $cache);
        if ($online['status']) {
            $cache['runtime_checked_at'] = date('c');
            $cache['runtime'] = $online['data'];
            self::writeJson($paths['cache'], $cache);
            return self::ok([
                'enabled' => 1,
                'source' => 'online',
                'runtime' => $online['data'],
            ], '运行期授权校验通过');
        }

        if (!empty($online['network_error']) && self::withinOfflineGrace($package, $cache)) {
            return self::ok([
                'enabled' => 1,
                'source' => 'offline_grace',
                'reason' => $online['reason'],
            ], '授权中心暂时不可达，仍在离线宽限期内');
        }

        return $online;
    }

    public static function packageConfig(): array
    {
        $path = rtrim(root_path(), '/\\') . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'package.php';
        $defaults = [
            'license_required' => false,
            'product_code' => '',
            'product_name' => '',
            'package_id' => '',
            'package_version' => '',
            'build_id' => '',
            'channel' => 'stable',
            'license_server' => '',
            'runtime_check_interval' => self::DEFAULT_CHECK_INTERVAL,
            'offline_grace_seconds' => self::DEFAULT_OFFLINE_GRACE,
        ];
        if (!is_file($path)) {
            return $defaults;
        }
        $loaded = include $path;
        return is_array($loaded) ? array_merge($defaults, $loaded) : $defaults;
    }

    private static function validateLocalCache(array $package, array $cache): array
    {
        $productCode = trim(strval($package['product_code'] ?? ''));
        if ($productCode !== '' && strcasecmp($productCode, strval($cache['product_code'] ?? '')) !== 0) {
            return self::fail('本地授权产品标识不匹配', 'product_mismatch');
        }

        $ticket = self::latestTicket($cache);
        if (!$ticket) {
            return self::fail('本地授权票据不存在，请重新安装', 'ticket_missing');
        }

        $expireTime = intval($ticket['expire_time'] ?? 0);
        if ($expireTime > 0 && $expireTime < time()) {
            return self::fail('授权已过期，请联系服务商续费', 'license_expired');
        }

        $trialEndTime = intval($ticket['trial_end_time'] ?? 0);
        if (strval($ticket['license_type'] ?? '') === 'trial' && $trialEndTime > 0 && $trialEndTime < time()) {
            return self::fail('试用授权已到期，请联系服务商转正式授权', 'trial_expired');
        }

        return self::ok(['ticket' => $ticket], '本地授权票据有效');
    }

    private static function checkOnline(array $package, array $cache): array
    {
        $server = rtrim(strval($cache['license_server'] ?? ($package['license_server'] ?? '')), '/');
        $licenseKey = trim(strval($cache['license_key'] ?? ''));
        if ($server === '') {
            return self::fail('缺少授权中心地址，无法进行运行期校验', 'license_server_missing');
        }
        if ($licenseKey === '') {
            return self::fail('缺少本地授权码，无法进行运行期校验', 'license_key_missing');
        }

        $payload = [
            'license_key' => $licenseKey,
            'product_code' => strval($package['product_code'] ?? ($cache['product_code'] ?? '')),
            'package_id' => strval($package['package_id'] ?? ($cache['package_id'] ?? '')),
            'package_version' => strval($package['package_version'] ?? ($cache['package_version'] ?? '')),
            'domain' => self::domain(),
            'site_url' => self::baseUrl(),
            'server_ip_detected' => strval($_SERVER['SERVER_ADDR'] ?? ''),
            'machine_fingerprint' => self::machineFingerprint(),
            'php_version' => PHP_VERSION,
            'server_software' => strval($_SERVER['SERVER_SOFTWARE'] ?? ''),
            'database_prefix' => self::databasePrefix(),
            'timestamp' => time(),
            'nonce' => bin2hex(random_bytes(16)),
        ];

        try {
            $json = self::postJson(self::endpoint($server, 'check'), $payload);
        } catch (\Throwable $e) {
            return [
                'status' => 0,
                'msg' => '授权中心连接失败：' . $e->getMessage(),
                'reason' => 'license_server_unreachable',
                'network_error' => 1,
            ];
        }

        if (intval($json['code'] ?? 0) !== 200) {
            return self::fail(strval($json['msg'] ?? '运行期授权校验失败'), strval($json['data']['reason'] ?? 'runtime_check_failed'));
        }

        return self::ok(is_array($json['data'] ?? null) ? $json['data'] : [], '运行期授权校验通过');
    }

    private static function shouldCheckOnline(array $package, array $cache): bool
    {
        $next = self::nextCheckAt($package, $cache);
        if ($next <= 0) {
            return true;
        }
        return time() >= $next;
    }

    private static function nextCheckAt(array $package, array $cache): int
    {
        foreach (['runtime', 'confirm', 'verify'] as $key) {
            $data = is_array($cache[$key] ?? null) ? $cache[$key] : [];
            $next = intval($data['next_check_at'] ?? 0);
            if ($next > 0) {
                return $next;
            }
            $checked = intval($data['checked_at'] ?? 0);
            if ($checked > 0) {
                return $checked + max(300, intval($package['runtime_check_interval'] ?? self::DEFAULT_CHECK_INTERVAL));
            }
        }
        return 0;
    }

    private static function withinOfflineGrace(array $package, array $cache): bool
    {
        $last = 0;
        foreach (['runtime', 'confirm', 'verify'] as $key) {
            $data = is_array($cache[$key] ?? null) ? $cache[$key] : [];
            $last = max($last, intval($data['checked_at'] ?? 0));
        }
        if ($last <= 0) {
            return false;
        }
        return time() <= $last + max(3600, intval($package['offline_grace_seconds'] ?? self::DEFAULT_OFFLINE_GRACE));
    }

    private static function latestTicket(array $cache): array
    {
        foreach (['runtime', 'confirm', 'verify'] as $key) {
            $data = is_array($cache[$key] ?? null) ? $cache[$key] : [];
            $ticket = self::decodeTicket(strval($data['ticket'] ?? ''));
            if ($ticket) {
                return $ticket;
            }
        }
        return [];
    }

    private static function decodeTicket(string $ticket): array
    {
        if ($ticket === '') {
            return [];
        }
        $json = base64_decode($ticket, true);
        if ($json === false || $json === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function postJson(string $url, array $payload): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => $body,
            ]);
            $response = curl_exec($ch);
            $error = curl_error($ch);
            curl_close($ch);
            if ($response === false) {
                throw new \RuntimeException($error ?: 'curl error');
            }
            $json = json_decode(strval($response), true);
            if (!is_array($json)) {
                throw new \RuntimeException('invalid json');
            }
            return $json;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $body,
                'timeout' => 10,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            throw new \RuntimeException('request failed');
        }
        $json = json_decode(strval($response), true);
        if (!is_array($json)) {
            throw new \RuntimeException('invalid json');
        }
        return $json;
    }

    private static function endpoint(string $server, string $action): string
    {
        if (preg_match('/\.php(?:\?.*)?$/i', $server)) {
            return $server . (strpos($server, '?') === false ? '?' : '&') . 'action=' . rawurlencode($action);
        }
        return rtrim($server, '/') . '/licensecenter.php?action=' . rawurlencode($action);
    }

    private static function paths(): array
    {
        $root = rtrim(root_path(), '/\\');
        return [
            'lock' => $root . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'install.lock',
            'cache' => $root . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'license' . DIRECTORY_SEPARATOR . 'license.cache',
        ];
    }

    private static function readJson(string $path): array
    {
        $decoded = json_decode(strval(@file_get_contents($path)), true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function writeJson(string $path, array $data): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        @file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    }

    private static function domain(): string
    {
        $host = strval($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? ''));
        $host = preg_replace('/:\d+$/', '', $host);
        return strtolower(trim(strval($host)));
    }

    private static function baseUrl(): string
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || strval($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        $host = strval($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost'));
        return ($https ? 'https' : 'http') . '://' . $host;
    }

    private static function machineFingerprint(): string
    {
        return hash('sha256', implode('|', [
            php_uname('n'),
            self::domain(),
            realpath(root_path()) ?: root_path(),
            strval($_SERVER['SERVER_ADDR'] ?? ''),
        ]));
    }

    private static function databasePrefix(): string
    {
        return strval(config('database.connections.mysql.prefix', ''));
    }

    private static function ok(array $data = [], string $msg = 'success'): array
    {
        return ['status' => 1, 'msg' => $msg, 'data' => $data];
    }

    private static function fail(string $msg, string $reason): array
    {
        return ['status' => 0, 'msg' => $msg, 'reason' => $reason];
    }
}
