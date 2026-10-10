<?php

namespace app\admin\common;

use think\facade\Db;
use think\facade\Log;

/**
 * Safely binds platform OpenIDs to an already authenticated member.
 * A bind never overwrites another OpenID and never merges member accounts.
 */
class MemberOpenidBindService
{
    private const PLATFORM_FIELDS = [
        'mp' => 'mpopenid',
        'wx' => 'wxopenid',
    ];

    private const DEFAULT_PLATFORMS = ['mp', 'wx'];

    public static function ensureSchema(): void
    {
        $prefix = config('database.connections.mysql.prefix', '');
        $adminSet = $prefix . 'admin_set';
        $logTable = $prefix . 'member_openid_bind_log';

        try {
            if (!self::tableColumnExists('admin_set', 'wechat_openid_auto_bind_platforms')) {
                Db::execute("ALTER TABLE `{$adminSet}` ADD COLUMN `wechat_openid_auto_bind_platforms` varchar(32) NOT NULL DEFAULT 'mp,wx' COMMENT '微信OpenID自动绑定平台：mp公众号 wx小程序' AFTER `login_bind`");
            }
        } catch (\Throwable $e) {
            Log::write('微信OpenID自动绑定配置字段补齐失败：' . $e->getMessage());
        }

        try {
            Db::execute("CREATE TABLE IF NOT EXISTS `{$logTable}` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `aid` int unsigned NOT NULL DEFAULT 0 COMMENT '站点ID',
                `platform` varchar(8) NOT NULL DEFAULT '' COMMENT '平台：mp公众号 wx小程序',
                `mid` int unsigned NOT NULL DEFAULT 0 COMMENT '当前会员ID',
                `conflict_mid` int unsigned NOT NULL DEFAULT 0 COMMENT '冲突会员ID',
                `source` varchar(32) NOT NULL DEFAULT '' COMMENT '来源：login/pay/register/manual',
                `action` varchar(32) NOT NULL DEFAULT '' COMMENT '动作：bound/already/conflict/skipped/error',
                `old_openid` varchar(128) NOT NULL DEFAULT '' COMMENT '原OpenID',
                `new_openid` varchar(128) NOT NULL DEFAULT '' COMMENT '新OpenID',
                `unionid` varchar(128) NOT NULL DEFAULT '' COMMENT 'UnionID',
                `reason` varchar(255) NOT NULL DEFAULT '' COMMENT '结果说明',
                `ip` varchar(64) NOT NULL DEFAULT '' COMMENT 'IP地址',
                `user_agent` varchar(500) NOT NULL DEFAULT '' COMMENT 'User-Agent',
                `createtime` int unsigned NOT NULL DEFAULT 0 COMMENT '创建时间',
                PRIMARY KEY (`id`),
                KEY `idx_aid_platform_mid` (`aid`,`platform`,`mid`),
                KEY `idx_aid_platform_action` (`aid`,`platform`,`action`),
                KEY `idx_aid_conflict_mid` (`aid`,`conflict_mid`),
                KEY `idx_aid_createtime` (`aid`,`createtime`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='会员微信OpenID绑定与冲突审计'");
        } catch (\Throwable $e) {
            Log::write('会员OpenID绑定审计表创建失败：' . $e->getMessage());
        }
    }

    public static function enabledPlatforms(?array $sysset = null): array
    {
        if ($sysset === null) {
            $sysset = Db::name('admin_set')->where('aid', aid)->find() ?: [];
        }
        if (!array_key_exists('wechat_openid_auto_bind_platforms', $sysset)) {
            return self::DEFAULT_PLATFORMS;
        }

        $raw = $sysset['wechat_openid_auto_bind_platforms'];
        if (is_array($raw)) {
            $values = $raw;
        } else {
            $values = explode(',', (string)$raw);
        }

        $platforms = [];
        foreach ($values as $platform) {
            $platform = trim((string)$platform);
            if (isset(self::PLATFORM_FIELDS[$platform]) && !in_array($platform, $platforms, true)) {
                $platforms[] = $platform;
            }
        }
        return $platforms;
    }

    public static function isEnabled(string $platform, ?array $sysset = null): bool
    {
        return in_array($platform, self::enabledPlatforms($sysset), true);
    }

    public static function normalizePlatforms($value): string
    {
        $values = is_array($value) ? $value : explode(',', (string)$value);
        $platforms = [];
        foreach (['mp', 'wx'] as $platform) {
            foreach ($values as $valueItem) {
                if (trim((string)$valueItem) === $platform) {
                    $platforms[] = $platform;
                    break;
                }
            }
        }
        return implode(',', $platforms);
    }

    /**
     * Exchange a one-time mini-program login code for the current OpenID.
     * The short cache makes the request idempotent when the frontend retries.
     */
    public static function exchangeWxCode(int $aid, string $code): array
    {
        $code = trim($code);
        if ($code === '') {
            return ['status' => 0, 'msg' => '缺少微信登录凭证'];
        }

        $cacheKey = 'wx_openid_exchange_' . $aid . '_' . sha1($code);
        $cached = cache($cacheKey);
        if (is_array($cached) && !empty($cached['openid'])) {
            return $cached;
        }

        $wxapp = System::appinfo($aid, 'wx');
        if (empty($wxapp['appid'])) {
            return ['status' => 0, 'msg' => '小程序配置不完整'];
        }

        if (intval($wxapp['authtype'] ?? 0) === 1) {
            $url = 'https://api.weixin.qq.com/sns/component/jscode2session?appid=' . $wxapp['appid']
                . '&component_appid=' . Wechat::component_appid()
                . '&js_code=' . urlencode($code)
                . '&grant_type=authorization_code&component_access_token=' . Wechat::component_access_token($aid);
        } else {
            $url = 'https://api.weixin.qq.com/sns/jscode2session?appid=' . $wxapp['appid']
                . '&secret=' . $wxapp['appsecret']
                . '&js_code=' . urlencode($code)
                . '&grant_type=authorization_code';
        }

        $raw = request_get($url);
        $result = json_decode($raw, true);
        if (!is_array($result) || intval($result['errcode'] ?? 0) > 0 || empty($result['openid'])) {
            $msg = is_array($result) ? Wechat::geterror($result) : '微信登录凭证无效';
            return ['status' => 0, 'msg' => $msg ?: '获取微信身份失败'];
        }

        $data = [
            'status' => 1,
            'openid' => trim((string)$result['openid']),
            'unionid' => trim((string)($result['unionid'] ?? '')),
            'session_key' => trim((string)($result['session_key'] ?? '')),
        ];
        cache($cacheKey, $data, 300);
        return $data;
    }

    public static function bindWxCode(int $aid, int $mid, string $code, string $source, bool $allowBind = true): array
    {
        try {
            $identity = self::exchangeWxCode($aid, $code);
        } catch (\Throwable $e) {
            $identity = ['status' => 0, 'action' => 'error', 'msg' => '获取微信身份失败，请稍后重试'];
            Log::write('小程序微信身份获取异常：' . $e->getMessage());
        }
        if (intval($identity['status'] ?? 0) !== 1) {
            self::writeLog($aid, 'wx', $mid, 0, $source, 'error', '', '', '', $identity['msg'] ?? '获取微信身份失败');
            return $identity;
        }

        return self::bindIdentity($aid, $mid, 'wx', $identity, $source, $allowBind);
    }

    public static function bindIdentity(int $aid, int $mid, string $platform, array $identity, string $source, bool $allowBind = true): array
    {
        $field = self::PLATFORM_FIELDS[$platform] ?? '';
        $openid = trim((string)($identity['openid'] ?? ''));
        $unionid = trim((string)($identity['unionid'] ?? ''));
        $sessionKey = trim((string)($identity['session_key'] ?? ''));
        if ($field === '' || $openid === '') {
            return ['status' => 0, 'action' => 'error', 'msg' => '微信身份信息不完整'];
        }

        $lockName = 'member_openid_bind_' . sha1($aid . '|' . $platform . '|' . $openid);
        $lockAcquired = false;
        try {
            $lockResult = Db::query("SELECT GET_LOCK('{$lockName}', 3) AS bind_lock");
            $lockAcquired = intval($lockResult[0]['bind_lock'] ?? 0) === 1;
            if (!$lockAcquired) {
                $msg = '微信身份绑定处理中，请稍后重试';
                self::writeLog($aid, $platform, $mid, 0, $source, 'error', '', $openid, $unionid, $msg);
                return ['status' => 0, 'bound' => 0, 'action' => 'error', 'openid' => $openid, 'msg' => $msg];
            }

            return self::bindIdentityWithLock(
                $aid,
                $mid,
                $platform,
                $field,
                $openid,
                $unionid,
                $sessionKey,
                $source,
                $allowBind
            );
        } catch (\Throwable $e) {
            $msg = '微信身份绑定失败，请稍后重试';
            self::writeLog($aid, $platform, $mid, 0, $source, 'error', '', $openid, $unionid, $msg);
            Log::write('会员OpenID安全绑定异常：' . $e->getMessage());
            return ['status' => 0, 'bound' => 0, 'action' => 'error', 'openid' => $openid, 'msg' => $msg];
        } finally {
            if ($lockAcquired) {
                try {
                    Db::query("SELECT RELEASE_LOCK('{$lockName}')");
                } catch (\Throwable $e) {
                    Log::write('会员OpenID绑定锁释放失败：' . $e->getMessage());
                }
            }
        }
    }

    private static function bindIdentityWithLock(
        int $aid,
        int $mid,
        string $platform,
        string $field,
        string $openid,
        string $unionid,
        string $sessionKey,
        string $source,
        bool $allowBind
    ): array {
        $member = Db::name('member')->where('aid', $aid)->where('id', $mid)->find();
        if (!$member) {
            return ['status' => 0, 'bound' => 0, 'action' => 'error', 'msg' => '会员不存在'];
        }

        $oldOpenid = trim((string)($member[$field] ?? ''));
        if ($oldOpenid !== '' && $oldOpenid === $openid) {
            $update = [];
            if ($sessionKey !== '' && $platform === 'wx') {
                $update['session_key'] = $sessionKey;
            }
            if ($unionid !== '' && empty($member['unionid'])) {
                $update['unionid'] = $unionid;
            }
            if ($update) {
                Db::name('member')->where('aid', $aid)->where('id', $mid)->update($update);
            }
            return ['status' => 1, 'bound' => 0, 'action' => 'already', 'openid' => $openid, 'msg' => '微信身份已绑定'];
        }

        if (!empty($member['unionid']) && $unionid !== '' && trim((string)$member['unionid']) !== $unionid) {
            $msg = '当前会员UnionID与当前微信身份不一致';
            self::writeLog($aid, $platform, $mid, 0, $source, 'conflict', $oldOpenid, $openid, $unionid, $msg);
            return ['status' => 0, 'bound' => 0, 'action' => 'conflict', 'openid' => $openid, 'msg' => $msg];
        }

        $conflict = Db::name('member')->where('aid', $aid)->where($field, $openid)->where('id', '<>', $mid)->find();
        if (!$conflict && $unionid !== '') {
            $conflict = Db::name('member')->where('aid', $aid)->where('unionid', $unionid)->where('id', '<>', $mid)->find();
        }
        if ($conflict) {
            $msg = '当前微信身份已绑定其他会员，请退出后使用正确账号或联系管理员';
            self::writeLog($aid, $platform, $mid, intval($conflict['id'] ?? 0), $source, 'conflict', $oldOpenid, $openid, $unionid, $msg);
            return [
                'status' => 0,
                'bound' => 0,
                'action' => 'conflict',
                'openid' => $openid,
                'msg' => $msg,
            ];
        }

        if (!$allowBind) {
            self::writeLog($aid, $platform, $mid, 0, $source, 'skipped', $oldOpenid, $openid, $unionid, '自动绑定已关闭');
            return ['status' => 0, 'bound' => 0, 'action' => 'skipped', 'openid' => $openid, 'msg' => '当前未开启微信身份自动绑定'];
        }

        if ($oldOpenid !== '') {
            $msg = '当前会员已绑定其他微信身份，请勿直接覆盖';
            self::writeLog($aid, $platform, $mid, 0, $source, 'conflict', $oldOpenid, $openid, $unionid, $msg);
            return ['status' => 0, 'bound' => 0, 'action' => 'conflict', 'openid' => $openid, 'msg' => $msg];
        }

        $update = [$field => $openid];
        if ($unionid !== '' && empty($member['unionid'])) {
            $update['unionid'] = $unionid;
        }
        if ($sessionKey !== '' && $platform === 'wx') {
            $update['session_key'] = $sessionKey;
        }
        $updated = Db::name('member')
            ->where('aid', $aid)
            ->where('id', $mid)
            ->whereRaw("(`{$field}` = '' OR `{$field}` IS NULL)")
            ->update($update);
        if (!$updated) {
            $latestOpenid = trim((string)Db::name('member')->where('aid', $aid)->where('id', $mid)->value($field));
            if ($latestOpenid === $openid) {
                return ['status' => 1, 'bound' => 0, 'action' => 'already', 'openid' => $openid, 'msg' => '微信身份已绑定'];
            }
            $msg = '会员微信身份已发生变化，请刷新后重试';
            self::writeLog($aid, $platform, $mid, 0, $source, 'conflict', $latestOpenid, $openid, $unionid, $msg);
            return ['status' => 0, 'bound' => 0, 'action' => 'conflict', 'openid' => $openid, 'msg' => $msg];
        }
        self::writeLog($aid, $platform, $mid, 0, $source, 'bound', $oldOpenid, $openid, $unionid, '自动绑定成功');

        return ['status' => 1, 'bound' => 1, 'action' => 'bound', 'openid' => $openid, 'msg' => '微信身份绑定成功'];
    }

    private static function writeLog(int $aid, string $platform, int $mid, int $conflictMid, string $source, string $action, string $oldOpenid, string $newOpenid, string $unionid, string $reason): void
    {
        try {
            Db::name('member_openid_bind_log')->insert([
                'aid' => $aid,
                'platform' => $platform,
                'mid' => $mid,
                'conflict_mid' => $conflictMid,
                'source' => substr(trim($source), 0, 32),
                'action' => substr(trim($action), 0, 32),
                'old_openid' => substr($oldOpenid, 0, 128),
                'new_openid' => substr($newOpenid, 0, 128),
                'unionid' => substr($unionid, 0, 128),
                'reason' => substr($reason, 0, 255),
                'ip' => substr((string)request()->ip(), 0, 64),
                'user_agent' => substr((string)request()->header('user-agent'), 0, 500),
                'createtime' => time(),
            ]);
        } catch (\Throwable $e) {
            Log::write('会员OpenID绑定审计写入失败：' . $e->getMessage());
        }
    }

    private static function tableColumnExists(string $table, string $column): bool
    {
        try {
            $prefix = config('database.connections.mysql.prefix', '');
            return !empty(Db::query("SHOW COLUMNS FROM `{$prefix}{$table}` LIKE '{$column}'"));
        } catch (\Throwable $e) {
            return false;
        }
    }
}
