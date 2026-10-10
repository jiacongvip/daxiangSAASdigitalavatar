<?php

namespace app\plugin\digitalavatar\common;

use think\facade\Db;
use think\facade\Request;

class DigitalAvatarInviteService
{
    public static function defaultSet(): array
    {
        return [
            'enabled' => 0,
            'inviter_credit' => 50,
            'invitee_credit' => 20,
            'expire_days' => 30,
            'daily_limit' => 0,
            'total_limit' => 0,
            'reward_invitee' => 1,
            'rule_text' => "邀请好友注册成功后，邀请人可获得创作点奖励；\n新用户也可获得见面礼创作点；\n奖励按设置的有效期到期后自动清空。",
            'quota_json' => DigitalAvatarLotService::normalizeQuotaMap([]),
        ];
    }

    public static function ensureSet(int $aid): array
    {
        $row = [];
        try {
            $row = Db::name('digitalavatar_invite_set')->where('aid', $aid)->find() ?: [];
        } catch (\Throwable $e) {
            DigitalAvatarInstaller::ensureInstalled(true);
            $row = Db::name('digitalavatar_invite_set')->where('aid', $aid)->find() ?: [];
        }
        if ($row) {
            return self::formatSet($row);
        }
        $now = time();
        $defaults = self::defaultSet();
        try {
            Db::name('digitalavatar_invite_set')->insert([
                'aid' => $aid,
                'enabled' => intval($defaults['enabled']),
                'inviter_credit' => intval($defaults['inviter_credit']),
                'invitee_credit' => intval($defaults['invitee_credit']),
                'expire_days' => intval($defaults['expire_days']),
                'daily_limit' => intval($defaults['daily_limit']),
                'total_limit' => intval($defaults['total_limit']),
                'reward_invitee' => intval($defaults['reward_invitee']),
                'rule_text' => $defaults['rule_text'],
                'quota_json' => json_encode($defaults['quota_json'], JSON_UNESCAPED_UNICODE),
                'create_time' => $now,
                'update_time' => $now,
            ]);
        } catch (\Throwable $e) {
            if (stripos($e->getMessage(), 'duplicate') === false) {
                throw $e;
            }
        }
        $row = Db::name('digitalavatar_invite_set')->where('aid', $aid)->find() ?: [];
        return self::formatSet($row ?: array_merge(['aid' => $aid], $defaults));
    }

    public static function saveSet(int $aid, array $info): array
    {
        self::ensureSet($aid);
        $quotas = DigitalAvatarLotService::normalizeQuotaMap($info['quota_json'] ?? $info);
        Db::name('digitalavatar_invite_set')->where('aid', $aid)->update([
            'enabled' => intval($info['enabled'] ?? 0) === 1 ? 1 : 0,
            'inviter_credit' => max(0, intval($info['inviter_credit'] ?? 0)),
            'invitee_credit' => max(0, intval($info['invitee_credit'] ?? 0)),
            'expire_days' => max(0, intval($info['expire_days'] ?? 0)),
            'daily_limit' => max(0, intval($info['daily_limit'] ?? 0)),
            'total_limit' => max(0, intval($info['total_limit'] ?? 0)),
            'reward_invitee' => intval($info['reward_invitee'] ?? 0) === 1 ? 1 : 0,
            'rule_text' => mb_substr(trim(strval($info['rule_text'] ?? '')), 0, 2000),
            'quota_json' => json_encode($quotas, JSON_UNESCAPED_UNICODE),
            'update_time' => time(),
        ]);
        return self::ensureSet($aid);
    }

    public static function formatSet(array $row): array
    {
        $defaults = self::defaultSet();
        $quotas = DigitalAvatarLotService::normalizeQuotaMap($row['quota_json'] ?? []);
        return [
            'id' => intval($row['id'] ?? 0),
            'aid' => intval($row['aid'] ?? 0),
            'enabled' => intval($row['enabled'] ?? 0) === 1 ? 1 : 0,
            'inviter_credit' => max(0, intval($row['inviter_credit'] ?? $defaults['inviter_credit'])),
            'invitee_credit' => max(0, intval($row['invitee_credit'] ?? $defaults['invitee_credit'])),
            'expire_days' => max(0, intval($row['expire_days'] ?? $defaults['expire_days'])),
            'daily_limit' => max(0, intval($row['daily_limit'] ?? 0)),
            'total_limit' => max(0, intval($row['total_limit'] ?? 0)),
            'reward_invitee' => intval($row['reward_invitee'] ?? 1) === 1 ? 1 : 0,
            'rule_text' => strval($row['rule_text'] ?? $defaults['rule_text']),
            'quota_json' => $quotas,
            'quota_items' => self::quotaItems($quotas),
            'update_time' => intval($row['update_time'] ?? 0),
        ];
    }

    public static function resolveInviter(int $aid, int $inviteMid, string $inviteCode, int $siteInviteType = 0): ?array
    {
        $code = trim($inviteCode);
        if ($inviteMid <= 0 && ctype_digit($code)) {
            $inviteMid = intval($code);
        }
        if ($inviteMid > 0) {
            $row = Db::name('member')->where('aid', $aid)->where('id', $inviteMid)->find();
            if ($row) {
                return $row;
            }
        }
        if ($code === '') {
            return null;
        }
        $query = Db::name('member')->where('aid', $aid);
        if ($siteInviteType === 1) {
            $row = (clone $query)->where('yqcode', $code)->find();
            if ($row) {
                return $row;
            }
        } elseif ($siteInviteType === 2) {
            $row = (clone $query)->where('id', intval($code))->find();
            if ($row) {
                return $row;
            }
        } elseif ($siteInviteType === 0 && preg_match('/^1[3456789]\d{9}$/', $code)) {
            $row = (clone $query)->where('tel', $code)->find();
            if ($row) {
                return $row;
            }
        }
        $row = Db::name('member')->where('aid', $aid)->where('yqcode', $code)->find();
        if ($row) {
            return $row;
        }
        if (preg_match('/^1[3456789]\d{9}$/', $code)) {
            return Db::name('member')->where('aid', $aid)->where('tel', $code)->find() ?: null;
        }
        return null;
    }

    public static function memberInfo(int $aid, int $bid, int $memberId): array
    {
        $set = self::ensureSet($aid);
        $enabled = intval($set['enabled'] ?? 0) === 1;
        $inviteCode = $memberId > 0 ? strval($memberId) : '';
        $stats = [
            'invited_count' => 0,
            'rewarded_count' => 0,
            'today_count' => 0,
            'inviter_credit_total' => 0,
        ];
        if ($memberId > 0) {
            try {
                $query = Db::name('digitalavatar_invite_log')->where('aid', $aid)->where('inviter_id', $memberId);
                if ($bid > 0) {
                    $query->where('bid', max(0, $bid));
                }
                $stats['invited_count'] = intval((clone $query)->count());
                $stats['rewarded_count'] = intval((clone $query)->where('status', 1)->count());
                $stats['today_count'] = intval((clone $query)->where('invite_date', date('Y-m-d'))->count());
                $stats['inviter_credit_total'] = intval((clone $query)->where('status', 1)->sum('inviter_credit'));
            } catch (\Throwable $e) {
                DigitalAvatarInstaller::ensureInstalled(true);
            }
        }
        $remainToday = '';
        if (intval($set['daily_limit'] ?? 0) > 0) {
            $remainToday = strval(max(0, intval($set['daily_limit']) - intval($stats['today_count'])));
        }
        return [
            'enabled' => $enabled ? 1 : 0,
            'invite_code' => $inviteCode,
            'invite_link' => $memberId > 0 ? self::buildInviteLink($aid, $bid, $memberId) : '',
            'inviter_credit' => intval($set['inviter_credit'] ?? 0),
            'invitee_credit' => intval($set['invitee_credit'] ?? 0),
            'expire_days' => intval($set['expire_days'] ?? 0),
            'expire_days_text' => intval($set['expire_days'] ?? 0) > 0 ? (intval($set['expire_days']) . '天') : '长期有效',
            'daily_limit' => intval($set['daily_limit'] ?? 0),
            'total_limit' => intval($set['total_limit'] ?? 0),
            'reward_invitee' => intval($set['reward_invitee'] ?? 0),
            'rule_text' => strval($set['rule_text'] ?? ''),
            'quota_items' => $set['quota_items'] ?? [],
            'stats' => $stats,
            'remain_today' => $remainToday,
        ];
    }

    public static function onRegister(int $aid, int $bid, int $inviteeId, int $inviterId): array
    {
        if ($aid <= 0 || $inviteeId <= 0 || $inviterId <= 0 || $inviteeId === $inviterId) {
            return [];
        }
        $set = self::ensureSet($aid);
        if (intval($set['enabled'] ?? 0) !== 1) {
            return [];
        }
        $inviter = Db::name('member')->where('aid', $aid)->where('id', $inviterId)->find();
        $invitee = Db::name('member')->where('aid', $aid)->where('id', $inviteeId)->find();
        if (!$inviter || !$invitee) {
            return [];
        }
        $exists = Db::name('digitalavatar_invite_log')
            ->where('aid', $aid)
            ->where('invitee_id', $inviteeId)
            ->find();
        if ($exists) {
            return self::formatLog($exists);
        }

        $today = date('Y-m-d');
        $status = 1;
        $skipReason = '';
        $dailyLimit = intval($set['daily_limit'] ?? 0);
        $totalLimit = intval($set['total_limit'] ?? 0);
        if ($dailyLimit > 0) {
            $todayCount = intval(Db::name('digitalavatar_invite_log')
                ->where('aid', $aid)
                ->where('inviter_id', $inviterId)
                ->where('invite_date', $today)
                ->where('status', 1)
                ->count());
            if ($todayCount >= $dailyLimit) {
                $status = 0;
                $skipReason = '超过每日拉新上限';
            }
        }
        if ($status === 1 && $totalLimit > 0) {
            $totalCount = intval(Db::name('digitalavatar_invite_log')
                ->where('aid', $aid)
                ->where('inviter_id', $inviterId)
                ->where('status', 1)
                ->count());
            if ($totalCount >= $totalLimit) {
                $status = 0;
                $skipReason = '超过累计拉新上限';
            }
        }

        $inviterCredit = $status === 1 ? intval($set['inviter_credit'] ?? 0) : 0;
        $inviteeCredit = ($status === 1 && intval($set['reward_invitee'] ?? 0) === 1) ? intval($set['invitee_credit'] ?? 0) : 0;
        $expireDays = intval($set['expire_days'] ?? 0);
        $expireTime = $expireDays > 0 ? (strtotime('+' . $expireDays . ' day') ?: 0) : 0;
        $quotas = $status === 1 ? DigitalAvatarLotService::normalizeQuotaMap($set['quota_json'] ?? []) : DigitalAvatarLotService::normalizeQuotaMap([]);
        $remark = $status === 1
            ? ('邀请好友注册，邀请人+' . $inviterCredit . '点' . ($inviteeCredit > 0 ? ('，新用户+' . $inviteeCredit . '点') : ''))
            : $skipReason;
        $now = time();
        $logId = 0;
        try {
            Db::transaction(function () use (
            $aid,
            $bid,
            $inviterId,
            $inviteeId,
            $today,
            $inviterCredit,
            $inviteeCredit,
            $expireDays,
            $expireTime,
            $quotas,
            $status,
            $skipReason,
            $remark,
            $now,
            &$logId
        ) {
            $logId = intval(Db::name('digitalavatar_invite_log')->insertGetId([
                'aid' => $aid,
                'bid' => max(0, $bid),
                'inviter_id' => $inviterId,
                'invitee_id' => $inviteeId,
                'invite_date' => $today,
                'inviter_credit' => $inviterCredit,
                'invitee_credit' => $inviteeCredit,
                'expire_days' => $expireDays,
                'expire_time' => $expireTime,
                'quota_json' => json_encode($quotas, JSON_UNESCAPED_UNICODE),
                'status' => $status,
                'skip_reason' => mb_substr($skipReason, 0, 120),
                'remark' => mb_substr($remark, 0, 255),
                'create_time' => $now,
                'update_time' => $now,
            ]));
            if ($status !== 1 || $logId <= 0) {
                return;
            }
            if ($inviterCredit > 0 || self::hasQuota($quotas)) {
                DigitalAvatarCreditService::grantInvite(
                    $aid,
                    $bid,
                    $inviterId,
                    $inviterCredit,
                    $expireTime,
                    $logId,
                    '邀请好友注册奖励',
                    $quotas
                );
            }
            if ($inviteeCredit > 0) {
                DigitalAvatarCreditService::grantInvite(
                    $aid,
                    $bid,
                    $inviteeId,
                    $inviteeCredit,
                    $expireTime,
                    $logId,
                    '受邀注册见面礼',
                    []
                );
            }
        });
        } catch (\Throwable $e) {
            if (stripos($e->getMessage(), 'duplicate') === false) {
                throw $e;
            }
            $exists = Db::name('digitalavatar_invite_log')
                ->where('aid', $aid)
                ->where('invitee_id', $inviteeId)
                ->find();
            return $exists ? self::formatLog($exists) : [];
        }
        $log = $logId > 0 ? (Db::name('digitalavatar_invite_log')->where('id', $logId)->find() ?: []) : [];
        return $log ? self::formatLog($log) : [];
    }

    public static function memberLogs(int $aid, int $bid, int $memberId, int $page = 1, int $limit = 20): array
    {
        if ($memberId <= 0) {
            throw new \RuntimeException('请先登录会员账号');
        }
        $query = Db::name('digitalavatar_invite_log')
            ->alias('l')
            ->leftJoin('member m', 'm.aid = l.aid and m.id = l.invitee_id')
            ->where('l.aid', $aid)
            ->where('l.inviter_id', $memberId);
        if ($bid > 0) {
            $query->where('l.bid', max(0, $bid));
        }
        $total = intval((clone $query)->count());
        $list = $query
            ->field('l.*,m.nickname as invitee_nickname,m.tel as invitee_tel,m.headimg as invitee_headimg')
            ->page(max(1, $page), max(1, min(50, $limit)))
            ->order('l.id desc')
            ->select()
            ->toArray();
        foreach ($list as &$item) {
            $item = self::formatLog($item);
        }
        unset($item);
        return ['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit];
    }

    public static function adminLogs(
        int $aid,
        int $bid,
        int $page = 1,
        int $limit = 20,
        string $keyword = '',
        string $startDate = '',
        string $endDate = ''
    ): array {
        $query = Db::name('digitalavatar_invite_log')
            ->alias('l')
            ->leftJoin('member a', 'a.aid = l.aid and a.id = l.inviter_id')
            ->leftJoin('member b', 'b.aid = l.aid and b.id = l.invitee_id')
            ->where('l.aid', $aid);
        if ($bid > 0) {
            $query->where('l.bid', $bid);
        }
        $keyword = trim($keyword);
        if ($keyword !== '') {
            $like = '%' . $keyword . '%';
            $query->where(function ($sub) use ($like) {
                $sub->where('a.nickname', 'like', $like)
                    ->whereOr('a.tel', 'like', $like)
                    ->whereOr('a.id', 'like', $like)
                    ->whereOr('b.nickname', 'like', $like)
                    ->whereOr('b.tel', 'like', $like)
                    ->whereOr('b.id', 'like', $like)
                    ->whereOr('l.remark', 'like', $like);
            });
        }
        if ($startDate !== '') {
            $query->where('l.invite_date', '>=', $startDate);
        }
        if ($endDate !== '') {
            $query->where('l.invite_date', '<=', $endDate);
        }
        $today = date('Y-m-d');
        $monthStart = date('Y-m-01');
        $statsQuery = Db::name('digitalavatar_invite_log')->where('aid', $aid);
        if ($bid > 0) {
            $statsQuery->where('bid', $bid);
        }
        $stats = [
            'today_count' => intval((clone $statsQuery)->where('invite_date', $today)->count()),
            'month_count' => intval((clone $statsQuery)->where('invite_date', '>=', $monthStart)->count()),
            'today_credit' => intval((clone $statsQuery)->where('invite_date', $today)->where('status', 1)->sum('inviter_credit')),
            'month_credit' => intval((clone $statsQuery)->where('invite_date', '>=', $monthStart)->where('status', 1)->sum('inviter_credit')),
        ];
        $total = intval((clone $query)->count());
        $list = $query
            ->field('l.*,a.nickname as inviter_nickname,a.tel as inviter_tel,a.headimg as inviter_headimg,b.nickname as invitee_nickname,b.tel as invitee_tel,b.headimg as invitee_headimg')
            ->page(max(1, $page), max(1, min(100, $limit)))
            ->order('l.id desc')
            ->select()
            ->toArray();
        foreach ($list as &$item) {
            $item = self::formatLog($item);
        }
        unset($item);
        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'stats' => $stats,
        ];
    }

    public static function formatLog(array $row): array
    {
        $quota = DigitalAvatarLotService::normalizeQuotaMap($row['quota_json'] ?? []);
        $expireTime = intval($row['expire_time'] ?? 0);
        $status = intval($row['status'] ?? 0);
        $inviterId = intval($row['inviter_id'] ?? 0);
        $inviteeId = intval($row['invitee_id'] ?? 0);
        $inviterName = trim(strval($row['inviter_nickname'] ?? ''));
        $inviteeName = trim(strval($row['invitee_nickname'] ?? ''));
        return [
            'id' => intval($row['id'] ?? 0),
            'inviter_id' => $inviterId,
            'inviter_name' => $inviterName !== '' ? $inviterName : ('会员' . $inviterId),
            'inviter_tel' => strval($row['inviter_tel'] ?? ''),
            'invitee_id' => $inviteeId,
            'invitee_name' => $inviteeName !== '' ? $inviteeName : ('会员' . $inviteeId),
            'invitee_tel' => strval($row['invitee_tel'] ?? ($row['tel'] ?? '')),
            'invite_date' => strval($row['invite_date'] ?? ''),
            'inviter_credit' => intval($row['inviter_credit'] ?? 0),
            'invitee_credit' => intval($row['invitee_credit'] ?? 0),
            'expire_days' => intval($row['expire_days'] ?? 0),
            'expire_time' => $expireTime,
            'expire_time_text' => $expireTime > 0 ? date('Y-m-d H:i', $expireTime) : '长期有效',
            'quota_items' => self::quotaItems($quota),
            'quota_text' => self::quotaText($quota),
            'status' => $status,
            'status_text' => $status === 1 ? '已奖励' : '未奖励',
            'skip_reason' => strval($row['skip_reason'] ?? ''),
            'reward_text' => self::rewardText($row),
            'remark' => strval($row['remark'] ?? ''),
            'create_time' => intval($row['create_time'] ?? 0),
            'create_time_text' => intval($row['create_time'] ?? 0) > 0 ? date('Y-m-d H:i:s', intval($row['create_time'])) : '',
        ];
    }

    public static function buildInviteLink(int $aid, int $bid, int $memberId): string
    {
        $settings = [];
        try {
            $settings = DigitalAvatarCreditService::ensureSettings($aid);
        } catch (\Throwable $e) {
        }
        $cleanUrl = DigitalAvatarPortalDomain::entryUrl($settings, $bid, ['invite_mid' => $memberId]);
        if ($cleanUrl !== '') {
            return $cleanUrl;
        }
        $path = trim(strval($settings['portal_path'] ?? '/portal/digitalavatar/')) ?: '/portal/digitalavatar/';
        $path = '/' . ltrim($path, '/');
        $loginPath = rtrim($path, '/') . '/profile/';
        $domain = '';
        try {
            $domain = rtrim(strval(Request::domain()), '/');
        } catch (\Throwable $e) {
        }
        $query = 'aid=' . $aid . '&invite_mid=' . $memberId;
        if ($bid > 0) {
            $query .= '&bid=' . $bid;
        }
        return ($domain !== '' ? $domain : '') . $loginPath . '?' . $query;
    }

    private static function quotaItems($quotas): array
    {
        $quotas = DigitalAvatarLotService::normalizeQuotaMap($quotas);
        $items = [];
        foreach (DigitalAvatarLotService::quotaFields() as $key => $meta) {
            $amount = max(0, intval($quotas[$key] ?? 0));
            if ($amount <= 0) {
                continue;
            }
            $items[] = [
                'key' => $key,
                'label' => DigitalAvatarLotService::quotaRemainLabel($key),
                'remain' => $amount,
                'unit' => strval($meta['unit'] ?? ''),
                'text' => DigitalAvatarLotService::quotaRemainLabel($key) . ' ' . $amount . strval($meta['unit'] ?? ''),
            ];
        }
        return $items;
    }

    private static function quotaText($quotas): string
    {
        $parts = [];
        foreach (self::quotaItems($quotas) as $item) {
            $parts[] = strval($item['text'] ?? '');
        }
        return implode('，', array_filter($parts));
    }

    private static function rewardText(array $row): string
    {
        if (intval($row['status'] ?? 0) !== 1) {
            $reason = trim(strval($row['skip_reason'] ?? ''));
            return $reason !== '' ? $reason : '未发放奖励';
        }
        $parts = [];
        $inviter = intval($row['inviter_credit'] ?? 0);
        $invitee = intval($row['invitee_credit'] ?? 0);
        if ($inviter > 0) {
            $parts[] = '邀请人 ' . $inviter . '点';
        }
        if ($invitee > 0) {
            $parts[] = '新用户 ' . $invitee . '点';
        }
        $quotaText = self::quotaText($row['quota_json'] ?? []);
        if ($quotaText !== '') {
            $parts[] = $quotaText;
        }
        if ($parts === []) {
            $parts[] = '已记录，无额度奖励';
        }
        return implode(' + ', $parts);
    }

    private static function hasQuota(array $quotas): bool
    {
        foreach ($quotas as $qty) {
            if (intval($qty) > 0) {
                return true;
            }
        }
        return false;
    }
}
