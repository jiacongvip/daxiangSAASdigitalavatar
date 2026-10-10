<?php

namespace app\plugin\digitalavatar\common;

use think\facade\Db;

class DigitalAvatarPortalDomain
{
    public static function normalizeHost(string $raw, bool $strict = false): string
    {
        $host = trim($raw);
        if ($host === '') {
            return '';
        }
        if (stripos($host, '://') === false) {
            $host = 'http://' . $host;
        }
        $parsed = parse_url($host);
        $host = strtolower(trim(strval($parsed['host'] ?? '')));
        $host = rtrim($host, '.');
        if ($host === '' || !self::isHost($host)) {
            if ($strict) {
                throw new \RuntimeException('站点域名格式不正确，只填写域名，例如 qixian.example.com');
            }
            return '';
        }
        return $host;
    }

    public static function storedHost(array $settings): string
    {
        return self::normalizeHost(strval($settings['portal_domain'] ?? ''), false);
    }

    public static function findAidByHost(string $host): int
    {
        $host = self::normalizeHost($host, false);
        if ($host === '') {
            return 0;
        }
        try {
            DigitalAvatarInstaller::ensurePortalDomainColumn();
            return intval(Db::name('digitalavatar_setting')->where('portal_domain', $host)->value('aid') ?: 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public static function assertAvailable(int $aid, string $host): void
    {
        if ($host === '') {
            return;
        }
        $other = intval(Db::name('digitalavatar_setting')
            ->where('portal_domain', $host)
            ->where('aid', '<>', $aid)
            ->value('aid') ?: 0);
        if ($other > 0) {
            throw new \RuntimeException('该域名已被其他数字人站点使用');
        }

        try {
            $prefix = strval(config('database.connections.mysql.prefix', ''));
            $table = str_replace('`', '', $prefix . 'website_site_route');
            $rows = Db::query(
                "SELECT `aid` FROM `{$table}` WHERE `custom_domain` = ? OR `custom_domain` LIKE ? LIMIT 1",
                [$host, $host . ':%']
            );
            if (!empty($rows)) {
                throw new \RuntimeException('该域名已绑定官网，请先在官网域名设置里清空，再绑定到数字人');
            }
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
        }
    }

    public static function entryUrl(array $settings, int $bid = 0, array $query = []): string
    {
        $host = self::storedHost($settings);
        if ($host === '') {
            return '';
        }
        unset($query['aid']);
        if ($bid > 0) {
            $query['bid'] = $bid;
        } else {
            unset($query['bid']);
        }
        $query = array_filter($query, function ($value) {
            return $value !== '' && $value !== null;
        });
        $qs = http_build_query($query);
        return 'https://' . $host . '/' . ($qs !== '' ? '?' . $qs : '');
    }

    private static function isHost(string $host): bool
    {
        if (preg_match('/^\d{1,3}(?:\.\d{1,3}){3}$/', $host)) {
            return true;
        }
        return preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)+$/', $host) === 1;
    }
}
