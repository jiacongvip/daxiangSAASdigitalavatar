<?php

namespace app\plugin\digitalavatar\common;

use think\facade\Db;

class DigitalAvatarCheckinService
{
    public static function defaultSet(): array
    {
        return [
            'enabled' => 0,
            'daily_credit' => 10,
            'expire_days' => 30,
            'start_time' => '00:00',
            'end_time' => '23:59',
            'rule_text' => "每天签到可获得创作点；\n连续签到达到指定天数可额外获得奖励；\n奖励创作点按设置的有效期到期后自动清空。",
            'streak_rules' => [
                ['days' => 3, 'credit' => 20, 'expire_days' => 0],
                ['days' => 7, 'credit' => 50, 'expire_days' => 0],
                ['days' => 15, 'credit' => 100, 'expire_days' => 0],
            ],
            'quota_json' => DigitalAvatarLotService::normalizeQuotaMap([]),
        ];
    }

    public static function ensureSet(int $aid): array
    {
        $row = [];
        try {
            $row = Db::name('digitalavatar_checkin_set')->where('aid', $aid)->find() ?: [];
        } catch (\Throwable $e) {
            DigitalAvatarInstaller::ensureInstalled(true);
            $row = Db::name('digitalavatar_checkin_set')->where('aid', $aid)->find() ?: [];
        }
        if ($row) {
            return self::formatSet($row);
        }
        $now = time();
        $defaults = self::defaultSet();
        try {
            Db::name('digitalavatar_checkin_set')->insert([
                'aid' => $aid,
                'enabled' => intval($defaults['enabled']),
                'daily_credit' => intval($defaults['daily_credit']),
                'expire_days' => intval($defaults['expire_days']),
                'start_time' => $defaults['start_time'],
                'end_time' => $defaults['end_time'],
                'rule_text' => $defaults['rule_text'],
                'streak_rules_json' => json_encode($defaults['streak_rules'], JSON_UNESCAPED_UNICODE),
                'quota_json' => json_encode($defaults['quota_json'], JSON_UNESCAPED_UNICODE),
                'create_time' => $now,
                'update_time' => $now,
            ]);
        } catch (\Throwable $e) {
            if (stripos($e->getMessage(), 'duplicate') === false) {
                throw $e;
            }
        }
        $row = Db::name('digitalavatar_checkin_set')->where('aid', $aid)->find() ?: [];
        return self::formatSet($row ?: array_merge(['aid' => $aid], $defaults));
    }

    public static function saveSet(int $aid, array $info): array
    {
        self::ensureSet($aid);
        $dailyCredit = max(0, intval($info['daily_credit'] ?? 0));
        $expireDays = max(0, intval($info['expire_days'] ?? 0));
        $startTime = self::normalizeClock(strval($info['start_time'] ?? '00:00'), '00:00');
        $endTime = self::normalizeClock(strval($info['end_time'] ?? '23:59'), '23:59');
        $streakRules = self::normalizeStreakRules($info['streak_rules'] ?? ($info['streak_rules_json'] ?? []));
        $quotas = DigitalAvatarLotService::normalizeQuotaMap($info['quota_json'] ?? $info);
        $data = [
            'enabled' => intval($info['enabled'] ?? 0) === 1 ? 1 : 0,
            'daily_credit' => $dailyCredit,
            'expire_days' => $expireDays,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'rule_text' => mb_substr(trim(strval($info['rule_text'] ?? '')), 0, 2000),
            'streak_rules_json' => json_encode($streakRules, JSON_UNESCAPED_UNICODE),
            'quota_json' => json_encode($quotas, JSON_UNESCAPED_UNICODE),
            'update_time' => time(),
        ];
        Db::name('digitalavatar_checkin_set')->where('aid', $aid)->update($data);
        return self::ensureSet($aid);
    }

    public static function formatSet(array $row): array
    {
        $defaults = self::defaultSet();
        $streakRules = self::normalizeStreakRules($row['streak_rules'] ?? ($row['streak_rules_json'] ?? $defaults['streak_rules']));
        $quotas = DigitalAvatarLotService::normalizeQuotaMap($row['quota_json'] ?? []);
        return [
            'id' => intval($row['id'] ?? 0),
            'aid' => intval($row['aid'] ?? 0),
            'enabled' => intval($row['enabled'] ?? 0) === 1 ? 1 : 0,
            'daily_credit' => max(0, intval($row['daily_credit'] ?? $defaults['daily_credit'])),
            'expire_days' => max(0, intval($row['expire_days'] ?? $defaults['expire_days'])),
            'start_time' => self::normalizeClock(strval($row['start_time'] ?? '00:00'), '00:00'),
            'end_time' => self::normalizeClock(strval($row['end_time'] ?? '23:59'), '23:59'),
            'rule_text' => strval($row['rule_text'] ?? $defaults['rule_text']),
            'streak_rules' => $streakRules,
            'quota_json' => $quotas,
            'update_time' => intval($row['update_time'] ?? 0),
        ];
    }

    public static function memberInfo(int $aid, int $bid, int $memberId): array
    {
        $set = self::ensureSet($aid);
        $today = date('Y-m-d');
        $todayLog = $memberId > 0 ? self::findLog($aid, $bid, $memberId, $today) : [];
        $yesterdayLog = $memberId > 0 ? self::findLog($aid, $bid, $memberId, date('Y-m-d', strtotime('-1 day'))) : [];
        $streakDays = 0;
        if ($todayLog) {
            $streakDays = max(1, intval($todayLog['streak_days'] ?? 1));
        } elseif ($yesterdayLog) {
            $streakDays = max(1, intval($yesterdayLog['streak_days'] ?? 1));
        }
        $nextStreak = $todayLog ? $streakDays : ($streakDays + 1);
        if ($nextStreak <= 0) {
            $nextStreak = 1;
        }
        $preview = self::previewReward($set, $nextStreak);
        $window = self::inTimeWindow($set);
        $enabled = intval($set['enabled'] ?? 0) === 1;
        $canCheckin = $enabled && $memberId > 0 && !$todayLog && $window['ok'];
        $reason = '';
        if (!$enabled) {
            $reason = '签到功能暂未开放';
        } elseif ($memberId <= 0) {
            $reason = '请先登录';
        } elseif ($todayLog) {
            $reason = '今日已签到';
        } elseif (!$window['ok']) {
            $reason = $window['msg'];
        }
        $totalDays = 0;
        if ($memberId > 0) {
            $query = Db::name('digitalavatar_checkin_log')->where('aid', $aid)->where('member_id', $memberId);
            if ($bid > 0) {
                $query->where('bid', max(0, $bid));
            }
            $totalDays = intval($query->count());
        }
        $month = date('Y-m');
        $checkedDates = [];
        if ($memberId > 0) {
            $start = date('Y-m-01', strtotime($month . '-01'));
            $end = date('Y-m-t', strtotime($start));
            $dateQuery = Db::name('digitalavatar_checkin_log')
                ->where('aid', $aid)
                ->where('member_id', $memberId)
                ->where('checkin_date', '>=', $start)
                ->where('checkin_date', '<=', $end);
            if ($bid > 0) {
                $dateQuery->where('bid', max(0, $bid));
            }
            $checkedDates = array_values(array_filter(array_map('strval', $dateQuery->column('checkin_date'))));
        }

        return [
            'enabled' => $enabled ? 1 : 0,
            'today' => $today,
            'today_checked' => $todayLog ? 1 : 0,
            'can_checkin' => $canCheckin ? 1 : 0,
            'cannot_reason' => $reason,
            'streak_days' => $todayLog ? $streakDays : ($yesterdayLog ? $streakDays : 0),
            'next_streak_days' => $nextStreak,
            'total_days' => $totalDays,
            'daily_credit' => intval($set['daily_credit'] ?? 0),
            'expire_days' => intval($set['expire_days'] ?? 0),
            'expire_days_text' => intval($set['expire_days'] ?? 0) > 0 ? (intval($set['expire_days']) . '天') : '长期有效',
            'start_time' => strval($set['start_time'] ?? '00:00'),
            'end_time' => strval($set['end_time'] ?? '23:59'),
            'rule_text' => strval($set['rule_text'] ?? ''),
            'streak_rules' => $set['streak_rules'] ?? [],
            'quota_items' => self::quotaItems($set['quota_json'] ?? []),
            'preview' => $preview,
            'today_log' => $todayLog ? self::formatLog($todayLog) : null,
            'checked_dates' => $checkedDates,
            'calendar' => self::buildCalendar($today, $checkedDates, $set, $yesterdayLog ? $streakDays : 0, $todayLog ? 1 : 0),
            'next_bonus' => self::nextBonusRule($set['streak_rules'] ?? [], $todayLog ? $streakDays : $streakDays),
        ];
    }

    public static function checkin(int $aid, int $bid, int $memberId): array
    {
        if ($memberId <= 0) {
            throw new \RuntimeException('请先登录会员账号');
        }
        $set = self::ensureSet($aid);
        if (intval($set['enabled'] ?? 0) !== 1) {
            throw new \RuntimeException('签到功能暂未开放');
        }
        $window = self::inTimeWindow($set);
        if (!$window['ok']) {
            throw new \RuntimeException($window['msg']);
        }
        $today = date('Y-m-d');
        $result = null;
        Db::transaction(function () use ($aid, $bid, $memberId, $set, $today, &$result) {
            $exists = Db::name('digitalavatar_checkin_log')
                ->where('aid', $aid)
                ->where('bid', max(0, $bid))
                ->where('member_id', $memberId)
                ->where('checkin_date', $today)
                ->lock(true)
                ->find();
            if ($exists) {
                throw new \RuntimeException('今日已签到');
            }
            $yesterday = self::findLog($aid, $bid, $memberId, date('Y-m-d', strtotime('-1 day')));
            $streakDays = $yesterday ? (max(1, intval($yesterday['streak_days'] ?? 1)) + 1) : 1;
            $reward = self::previewReward($set, $streakDays);
            $expireDays = intval($reward['expire_days'] ?? 0);
            $expireTime = $expireDays > 0 ? (strtotime('+' . $expireDays . ' day') ?: 0) : 0;
            $remark = self::composeRemark($streakDays, $reward);
            $now = time();
            $logId = intval(Db::name('digitalavatar_checkin_log')->insertGetId([
                'aid' => $aid,
                'bid' => max(0, $bid),
                'member_id' => $memberId,
                'checkin_date' => $today,
                'streak_days' => $streakDays,
                'daily_credit' => intval($reward['daily_credit'] ?? 0),
                'extra_credit' => intval($reward['extra_credit'] ?? 0),
                'total_credit' => intval($reward['total_credit'] ?? 0),
                'expire_days' => $expireDays,
                'expire_time' => $expireTime,
                'quota_json' => json_encode($reward['quota_json'] ?? [], JSON_UNESCAPED_UNICODE),
                'reward_json' => json_encode($reward, JSON_UNESCAPED_UNICODE),
                'remark' => mb_substr($remark, 0, 255),
                'create_time' => $now,
                'update_time' => $now,
            ]));
            DigitalAvatarCreditService::grantCheckin(
                $aid,
                $bid,
                $memberId,
                intval($reward['total_credit'] ?? 0),
                $expireTime,
                $logId,
                $remark,
                is_array($reward['quota_json'] ?? null) ? $reward['quota_json'] : []
            );
            $log = Db::name('digitalavatar_checkin_log')->where('id', $logId)->find() ?: [];
            $result = [
                'log' => self::formatLog($log),
                'credit' => DigitalAvatarCreditService::getSummary($aid, $bid, $memberId),
                'info' => self::memberInfo($aid, $bid, $memberId),
            ];
        });
        return is_array($result) ? $result : self::memberInfo($aid, $bid, $memberId);
    }

    public static function memberLogs(int $aid, int $bid, int $memberId, int $page = 1, int $limit = 20): array
    {
        if ($memberId <= 0) {
            throw new \RuntimeException('请先登录会员账号');
        }
        $query = Db::name('digitalavatar_checkin_log')
            ->where('aid', $aid)
            ->where('member_id', $memberId);
        if ($bid > 0) {
            $query->where('bid', max(0, $bid));
        }
        $total = intval((clone $query)->count());
        $list = $query->page(max(1, $page), max(1, min(50, $limit)))
            ->order('id desc')
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
        $query = Db::name('digitalavatar_checkin_log')
            ->alias('l')
            ->leftJoin('member m', 'm.aid = l.aid and m.id = l.member_id')
            ->where('l.aid', $aid);
        if ($bid > 0) {
            $query->where('l.bid', $bid);
        }
        $keyword = trim($keyword);
        if ($keyword !== '') {
            $like = '%' . $keyword . '%';
            $query->where(function ($sub) use ($like) {
                $sub->where('m.nickname', 'like', $like)
                    ->whereOr('m.tel', 'like', $like)
                    ->whereOr('m.id', 'like', $like)
                    ->whereOr('l.remark', 'like', $like);
            });
        }
        if ($startDate !== '') {
            $query->where('l.checkin_date', '>=', $startDate);
        }
        if ($endDate !== '') {
            $query->where('l.checkin_date', '<=', $endDate);
        }
        $today = date('Y-m-d');
        $monthStart = date('Y-m-01');
        $statsQuery = Db::name('digitalavatar_checkin_log')->where('aid', $aid);
        if ($bid > 0) {
            $statsQuery->where('bid', $bid);
        }
        $stats = [
            'today_count' => intval((clone $statsQuery)->where('checkin_date', $today)->count()),
            'month_count' => intval((clone $statsQuery)->where('checkin_date', '>=', $monthStart)->count()),
            'today_credit' => intval((clone $statsQuery)->where('checkin_date', $today)->sum('total_credit')),
            'month_credit' => intval((clone $statsQuery)->where('checkin_date', '>=', $monthStart)->sum('total_credit')),
        ];
        $total = intval((clone $query)->count());
        $list = $query
            ->field('l.*,m.nickname,m.tel,m.headimg')
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
        $memberId = intval($row['member_id'] ?? 0);
        $nickname = trim(strval($row['nickname'] ?? ''));
        return [
            'id' => intval($row['id'] ?? 0),
            'member_id' => $memberId,
            'member_name' => $nickname !== '' ? $nickname : ('会员' . $memberId),
            'tel' => strval($row['tel'] ?? ''),
            'headimg' => strval($row['headimg'] ?? ''),
            'checkin_date' => strval($row['checkin_date'] ?? ''),
            'streak_days' => intval($row['streak_days'] ?? 0),
            'daily_credit' => intval($row['daily_credit'] ?? 0),
            'extra_credit' => intval($row['extra_credit'] ?? 0),
            'total_credit' => intval($row['total_credit'] ?? 0),
            'expire_days' => intval($row['expire_days'] ?? 0),
            'expire_time' => $expireTime,
            'expire_time_text' => $expireTime > 0 ? date('Y-m-d H:i', $expireTime) : '长期有效',
            'quota_items' => self::quotaItems($quota),
            'quota_text' => self::quotaText($quota),
            'reward_text' => self::rewardText($row),
            'remark' => strval($row['remark'] ?? ''),
            'create_time' => intval($row['create_time'] ?? 0),
            'create_time_text' => intval($row['create_time'] ?? 0) > 0 ? date('Y-m-d H:i:s', intval($row['create_time'])) : '',
        ];
    }

    private static function previewReward(array $set, int $streakDays): array
    {
        $daily = max(0, intval($set['daily_credit'] ?? 0));
        $expireDays = max(0, intval($set['expire_days'] ?? 0));
        $extra = 0;
        $hit = null;
        foreach (self::normalizeStreakRules($set['streak_rules'] ?? []) as $rule) {
            if (intval($rule['days']) === $streakDays) {
                $extra = max(0, intval($rule['credit']));
                $hit = $rule;
                if (intval($rule['expire_days'] ?? 0) > 0) {
                    $expireDays = intval($rule['expire_days']);
                }
                break;
            }
        }
        $quotas = DigitalAvatarLotService::normalizeQuotaMap($set['quota_json'] ?? []);
        return [
            'streak_days' => $streakDays,
            'daily_credit' => $daily,
            'extra_credit' => $extra,
            'total_credit' => $daily + $extra,
            'expire_days' => $expireDays,
            'bonus_rule' => $hit,
            'quota_json' => $quotas,
            'quota_items' => self::quotaItems($quotas),
        ];
    }

    private static function nextBonusRule(array $rules, int $currentStreak): ?array
    {
        $rules = self::normalizeStreakRules($rules);
        usort($rules, function ($a, $b) {
            return intval($a['days']) <=> intval($b['days']);
        });
        foreach ($rules as $rule) {
            if (intval($rule['days']) > $currentStreak) {
                return $rule;
            }
        }
        return $rules ? $rules[0] : null;
    }

    private static function buildCalendar(string $today, array $checkedDates, array $set, int $yesterdayStreak, int $todayChecked): array
    {
        $checkedMap = [];
        foreach ($checkedDates as $date) {
            $checkedMap[$date] = 1;
        }
        $start = date('Y-m-01', strtotime($today));
        $days = intval(date('t', strtotime($start)));
        $list = [];
        for ($i = 1; $i <= $days; $i++) {
            $date = date('Y-m-', strtotime($start)) . str_pad(strval($i), 2, '0', STR_PAD_LEFT);
            $isToday = $date === $today;
            $checked = isset($checkedMap[$date]) ? 1 : 0;
            $list[] = [
                'date' => $date,
                'day' => $i,
                'is_today' => $isToday ? 1 : 0,
                'checked' => $checked,
            ];
        }
        return $list;
    }

    private static function inTimeWindow(array $set): array
    {
        $start = self::normalizeClock(strval($set['start_time'] ?? '00:00'), '00:00');
        $end = self::normalizeClock(strval($set['end_time'] ?? '23:59'), '23:59');
        $now = date('H:i');
        $ok = true;
        if ($start !== $end) {
            if ($start < $end) {
                $ok = $now >= $start && $now <= $end;
            } else {
                $ok = $now >= $start || $now <= $end;
            }
        }
        return [
            'ok' => $ok,
            'msg' => $ok ? '' : ('当前不在签到时间（' . $start . ' - ' . $end . '）'),
        ];
    }

    private static function findLog(int $aid, int $bid, int $memberId, string $date): array
    {
        $query = Db::name('digitalavatar_checkin_log')
            ->where('aid', $aid)
            ->where('member_id', $memberId)
            ->where('checkin_date', $date);
        if ($bid > 0) {
            $query->where('bid', max(0, $bid));
        }
        return $query->find() ?: [];
    }

    private static function normalizeStreakRules($raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw)) {
            $raw = [];
        }
        $result = [];
        $seen = [];
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $days = max(2, intval($item['days'] ?? 0));
            $credit = max(0, intval($item['credit'] ?? 0));
            if ($days <= 1 || $credit <= 0) {
                continue;
            }
            if (isset($seen[$days])) {
                continue;
            }
            $seen[$days] = 1;
            $result[] = [
                'days' => $days,
                'credit' => $credit,
                'expire_days' => max(0, intval($item['expire_days'] ?? 0)),
            ];
        }
        usort($result, function ($a, $b) {
            return intval($a['days']) <=> intval($b['days']);
        });
        return array_values($result);
    }

    private static function normalizeClock(string $value, string $fallback): string
    {
        $value = trim($value);
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $value, $match)) {
            $hour = max(0, min(23, intval($match[1])));
            $minute = max(0, min(59, intval($match[2])));
            return str_pad(strval($hour), 2, '0', STR_PAD_LEFT) . ':' . str_pad(strval($minute), 2, '0', STR_PAD_LEFT);
        }
        return $fallback;
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
        $parts = [];
        $daily = intval($row['daily_credit'] ?? 0);
        $extra = intval($row['extra_credit'] ?? 0);
        if ($daily > 0) {
            $parts[] = '每日 ' . $daily . '点';
        }
        if ($extra > 0) {
            $parts[] = '连续' . intval($row['streak_days'] ?? 0) . '天额外 ' . $extra . '点';
        }
        $quotaText = self::quotaText($row['quota_json'] ?? []);
        if ($quotaText !== '') {
            $parts[] = $quotaText;
        }
        if ($parts === []) {
            $parts[] = '无额度奖励';
        }
        return implode(' + ', $parts);
    }

    private static function composeRemark(int $streakDays, array $reward): string
    {
        $parts = ['每日签到'];
        if ($streakDays > 1) {
            $parts[] = '连续' . $streakDays . '天';
        }
        $credit = intval($reward['total_credit'] ?? 0);
        if ($credit > 0) {
            $parts[] = '+' . $credit . '创作点';
        }
        if (intval($reward['extra_credit'] ?? 0) > 0) {
            $parts[] = '含连续奖励' . intval($reward['extra_credit']) . '点';
        }
        return implode('，', $parts);
    }
}
