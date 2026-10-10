<?php

namespace app\plugin\digitalavatar\common;

use think\facade\Db;

class DigitalAvatarFinanceService
{
    public static function listCommissionLevels(int $aid, int $page = 1, int $limit = 50, string $keyword = '', string $status = ''): array
    {
        $query = Db::name('digitalavatar_commission_level')->where('aid', $aid);
        if ($keyword !== '') {
            $query->whereLike('name', '%' . $keyword . '%');
        }
        if ($status !== '' && is_numeric($status)) {
            $query->where('status', intval($status));
        }
        $total = intval((clone $query)->count());
        $list = $query
            ->page(max(1, $page), max(1, min(200, $limit)))
            ->order('level asc,id desc')
            ->select()
            ->toArray();
        foreach ($list as &$item) {
            $item = self::formatLevel($item);
        }
        unset($item);
        return [
            'list' => $list,
            'total' => $total,
            'page' => max(1, $page),
            'limit' => max(1, min(200, $limit)),
        ];
    }

    public static function saveCommissionLevel(int $aid, array $info): array
    {
        $id = intval($info['id'] ?? 0);
        $name = trim(strval($info['name'] ?? ''));
        if ($name === '') {
            throw new \RuntimeException('请填写团队长级别名称');
        }
        $level = max(1, intval($info['level'] ?? 1));
        $rate = round(floatval($info['rate'] ?? 0), 2);
        if ($rate < 0 || $rate > 100) {
            throw new \RuntimeException('分佣比例需在 0-100 之间');
        }
        $isDefault = intval($info['is_default'] ?? 0) === 1 ? 1 : 0;
        $autoUpgrade = intval($info['auto_upgrade'] ?? 0) === 1 ? 1 : 0;
        $status = intval($info['status'] ?? 1) === 0 ? 0 : 1;
        $now = time();
        $data = [
            'aid' => $aid,
            'name' => mb_substr($name, 0, 120),
            'level' => $level,
            'rate' => $rate,
            'is_default' => $isDefault,
            'auto_upgrade' => $autoUpgrade,
            'status' => $status,
            'sort' => $level,
            'remark' => mb_substr(trim(strval($info['remark'] ?? '')), 0, 255),
            'update_time' => $now,
        ];
        Db::transaction(function () use ($aid, $id, $isDefault, $data, $now) {
            if ($isDefault === 1) {
                Db::name('digitalavatar_commission_level')->where('aid', $aid)->update(['is_default' => 0, 'update_time' => $now]);
            }
            if ($id > 0) {
                $exists = Db::name('digitalavatar_commission_level')->where('aid', $aid)->where('id', $id)->find();
                if (!$exists) {
                    throw new \RuntimeException('分佣等级不存在');
                }
                Db::name('digitalavatar_commission_level')->where('id', $id)->update($data);
            } else {
                $data['create_time'] = $now;
                $id = intval(Db::name('digitalavatar_commission_level')->insertGetId($data));
            }
            if ($isDefault !== 1) {
                $hasDefault = intval(Db::name('digitalavatar_commission_level')->where('aid', $aid)->where('is_default', 1)->count());
                if ($hasDefault <= 0) {
                    Db::name('digitalavatar_commission_level')->where('id', $id)->update(['is_default' => 1, 'update_time' => $now]);
                }
            }
        });
        $row = $id > 0
            ? Db::name('digitalavatar_commission_level')->where('aid', $aid)->where('id', $id)->find()
            : Db::name('digitalavatar_commission_level')->where('aid', $aid)->order('id desc')->find();
        return self::formatLevel($row ?: $data);
    }

    public static function deleteCommissionLevel(int $aid, int $id): void
    {
        if ($id <= 0) {
            throw new \RuntimeException('请选择分佣等级');
        }
        $row = Db::name('digitalavatar_commission_level')->where('aid', $aid)->where('id', $id)->find();
        if (!$row) {
            throw new \RuntimeException('分佣等级不存在');
        }
        Db::name('digitalavatar_commission_level')->where('id', $id)->delete();
        if (intval($row['is_default'] ?? 0) === 1) {
            $next = Db::name('digitalavatar_commission_level')->where('aid', $aid)->order('level asc,id asc')->find();
            if ($next) {
                Db::name('digitalavatar_commission_level')->where('id', intval($next['id']))->update(['is_default' => 1, 'update_time' => time()]);
            }
        }
    }

    public static function listWithdraws(
        int $aid,
        int $page = 1,
        int $limit = 100,
        string $keyword = '',
        string $status = ''
    ): array {
        $query = Db::name('digitalavatar_withdraw')
            ->alias('w')
            ->leftJoin('member m', 'm.aid = w.aid and m.id = w.member_id')
            ->where('w.aid', $aid);
        if ($keyword !== '') {
            $like = '%' . $keyword . '%';
            $query->where(function ($sub) use ($like) {
                $sub->whereLike('m.nickname|m.tel|m.realname|w.pay_info', $like);
            });
        }
        if ($status !== '' && is_numeric($status)) {
            $query->where('w.status', intval($status));
        }
        $total = intval((clone $query)->count());
        $list = $query
            ->field('w.*,m.nickname,m.tel,m.realname,m.headimg')
            ->page(max(1, $page), max(1, min(200, $limit)))
            ->order('w.id desc')
            ->select()
            ->toArray();
        foreach ($list as &$item) {
            $item = self::formatWithdraw($item);
        }
        unset($item);
        return [
            'list' => $list,
            'total' => $total,
            'page' => max(1, $page),
            'limit' => max(1, min(200, $limit)),
        ];
    }

    public static function auditWithdraw(int $aid, int $id, int $status, string $remark = ''): array
    {
        if (!in_array($status, [1, 2], true)) {
            throw new \RuntimeException('请选择通过或拒绝');
        }
        Db::transaction(function () use ($aid, $id, $status, $remark) {
            $row = Db::name('digitalavatar_withdraw')->where('aid', $aid)->where('id', $id)->lock(true)->find();
            if (!$row) {
                throw new \RuntimeException('提现记录不存在');
            }
            if (intval($row['status'] ?? 0) !== 0) {
                throw new \RuntimeException('该提现已处理');
            }
            $amount = round(floatval($row['amount'] ?? 0), 2);
            $memberId = intval($row['member_id'] ?? 0);
            $bid = intval($row['bid'] ?? 0);
            DigitalAvatarCreditService::ensureAccount($aid, $bid, $memberId);
            $account = Db::name('digitalavatar_member_credit')
                ->where('aid', $aid)
                ->where('bid', $bid)
                ->where('member_id', $memberId)
                ->lock(true)
                ->find();
            if (!$account) {
                throw new \RuntimeException('会员额度账户不存在');
            }
            if ($status === 1) {
                $balance = round(floatval($account['commission_balance'] ?? 0), 2);
                if ($balance + 0.001 < $amount) {
                    throw new \RuntimeException('分佣余额不足，无法通过提现');
                }
                Db::name('digitalavatar_member_credit')->where('id', intval($account['id']))->update([
                    'commission_balance' => round($balance - $amount, 2),
                    'commission_withdrawn' => round(floatval($account['commission_withdrawn'] ?? 0) + $amount, 2),
                    'update_time' => time(),
                ]);
            }
            Db::name('digitalavatar_withdraw')->where('id', $id)->update([
                'status' => $status,
                'audit_remark' => mb_substr(trim($remark), 0, 255),
                'audit_time' => time(),
                'update_time' => time(),
            ]);
        });
        $fresh = Db::name('digitalavatar_withdraw')
            ->alias('w')
            ->leftJoin('member m', 'm.aid = w.aid and m.id = w.member_id')
            ->where('w.aid', $aid)
            ->where('w.id', $id)
            ->field('w.*,m.nickname,m.tel,m.realname,m.headimg')
            ->find();
        return self::formatWithdraw($fresh ?: []);
    }

    public static function grantOrderCommission(array $order): void
    {
        $aid = intval($order['aid'] ?? 0);
        $memberId = intval($order['member_id'] ?? 0);
        $orderId = intval($order['id'] ?? 0);
        $price = round(floatval($order['price'] ?? 0), 2);
        if ($aid <= 0 || $memberId <= 0 || $orderId <= 0 || $price <= 0) {
            return;
        }
        if (round(floatval($order['commission_amount'] ?? 0), 2) > 0) {
            return;
        }
        $parentId = self::resolveParentMid($aid, $memberId);
        if ($parentId <= 0 || $parentId === $memberId) {
            Db::name('digitalavatar_package_order')->where('id', $orderId)->update([
                'parent_mid' => 0,
                'commission_amount' => 0,
                'commission_rate' => 0,
                'commission_level_id' => 0,
                'update_time' => time(),
            ]);
            return;
        }
        $bid = intval($order['bid'] ?? 0);
        self::maybeAutoUpgrade($aid, $bid, $parentId);
        $level = self::memberLevel($aid, $bid, $parentId);
        $rate = round(floatval($level['rate'] ?? 0), 2);
        $amount = round($price * $rate / 100, 2);
        Db::name('digitalavatar_package_order')->where('id', $orderId)->update([
            'parent_mid' => $parentId,
            'commission_amount' => $amount,
            'commission_rate' => $rate,
            'commission_level_id' => intval($level['id'] ?? 0),
            'update_time' => time(),
        ]);
        if ($amount <= 0) {
            return;
        }
        DigitalAvatarCreditService::ensureAccount($aid, $bid, $parentId);
        $account = Db::name('digitalavatar_member_credit')
            ->where('aid', $aid)
            ->where('bid', $bid)
            ->where('member_id', $parentId)
            ->lock(true)
            ->find();
        if (!$account) {
            return;
        }
        Db::name('digitalavatar_member_credit')->where('id', intval($account['id']))->update([
            'commission_balance' => round(floatval($account['commission_balance'] ?? 0) + $amount, 2),
            'commission_total' => round(floatval($account['commission_total'] ?? 0) + $amount, 2),
            'commission_level_id' => intval($level['id'] ?? ($account['commission_level_id'] ?? 0)),
            'update_time' => time(),
        ]);
    }

    public static function resolveParentMid(int $aid, int $memberId): int
    {
        try {
            $invite = Db::name('digitalavatar_invite_log')
                ->where('aid', $aid)
                ->where('invitee_id', $memberId)
                ->order('id desc')
                ->find();
            if ($invite && intval($invite['inviter_id'] ?? 0) > 0) {
                return intval($invite['inviter_id']);
            }
        } catch (\Throwable $e) {
        }
        try {
            $member = Db::name('member')->where('aid', $aid)->where('id', $memberId)->field('id,pid')->find();
            return intval($member['pid'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private static function memberLevel(int $aid, int $bid, int $memberId): array
    {
        $account = [];
        try {
            $account = Db::name('digitalavatar_member_credit')
                ->where('aid', $aid)
                ->where('bid', max(0, $bid))
                ->where('member_id', $memberId)
                ->find() ?: [];
        } catch (\Throwable $e) {
        }
        $levelId = intval($account['commission_level_id'] ?? 0);
        if ($levelId > 0) {
            $row = Db::name('digitalavatar_commission_level')->where('aid', $aid)->where('id', $levelId)->where('status', 1)->find();
            if ($row) {
                return $row;
            }
        }
        $default = Db::name('digitalavatar_commission_level')->where('aid', $aid)->where('is_default', 1)->where('status', 1)->find();
        if ($default) {
            return $default;
        }
        return Db::name('digitalavatar_commission_level')->where('aid', $aid)->where('status', 1)->order('level asc,id asc')->find() ?: [];
    }

    private static function maybeAutoUpgrade(int $aid, int $bid, int $parentId): void
    {
        $autoLevels = Db::name('digitalavatar_commission_level')
            ->where('aid', $aid)
            ->where('status', 1)
            ->where('auto_upgrade', 1)
            ->order('level desc,id desc')
            ->select()
            ->toArray();
        if ($autoLevels === []) {
            return;
        }
        $teamPaid = 0;
        try {
            $teamPaid = intval(
                Db::name('digitalavatar_package_order')
                    ->where('aid', $aid)
                    ->where('parent_mid', $parentId)
                    ->where('status', 1)
                    ->count()
            );
        } catch (\Throwable $e) {
            $teamPaid = 0;
        }
        if ($teamPaid <= 0) {
            $invitees = [];
            try {
                $invitees = Db::name('digitalavatar_invite_log')->where('aid', $aid)->where('inviter_id', $parentId)->column('invitee_id');
            } catch (\Throwable $e) {
            }
            if ($invitees) {
                $teamPaid = intval(
                    Db::name('digitalavatar_package_order')
                        ->where('aid', $aid)
                        ->where('status', 1)
                        ->whereIn('member_id', $invitees)
                        ->count()
                );
            }
        }
        if ($teamPaid <= 0) {
            return;
        }
        $current = self::memberLevel($aid, $bid, $parentId);
        $currentLevelNum = intval($current['level'] ?? 0);
        $target = [];
        foreach ($autoLevels as $candidate) {
            if (intval($candidate['level'] ?? 0) > $currentLevelNum) {
                $target = $candidate;
                break;
            }
        }
        if ($target === []) {
            return;
        }
        DigitalAvatarCreditService::ensureAccount($aid, $bid, $parentId);
        Db::name('digitalavatar_member_credit')
            ->where('aid', $aid)
            ->where('bid', max(0, $bid))
            ->where('member_id', $parentId)
            ->update([
                'commission_level_id' => intval($target['id'] ?? 0),
                'update_time' => time(),
            ]);
    }

    private static function formatLevel($row): array
    {
        $row = is_array($row) ? $row : [];
        return [
            'id' => intval($row['id'] ?? 0),
            'aid' => intval($row['aid'] ?? 0),
            'name' => strval($row['name'] ?? ''),
            'level' => intval($row['level'] ?? 0),
            'rate' => round(floatval($row['rate'] ?? 0), 2),
            'is_default' => intval($row['is_default'] ?? 0) === 1 ? 1 : 0,
            'auto_upgrade' => intval($row['auto_upgrade'] ?? 0) === 1 ? 1 : 0,
            'status' => intval($row['status'] ?? 0) === 1 ? 1 : 0,
            'remark' => strval($row['remark'] ?? ''),
            'create_time' => intval($row['create_time'] ?? 0),
            'update_time' => intval($row['update_time'] ?? 0),
        ];
    }

    private static function formatWithdraw(array $row): array
    {
        $status = intval($row['status'] ?? 0);
        $map = [0 => '审核中', 1 => '已通过', 2 => '已拒绝'];
        $memberId = intval($row['member_id'] ?? 0);
        $nickname = trim(strval($row['nickname'] ?? ''));
        $realname = trim(strval($row['realname'] ?? ''));
        return [
            'id' => intval($row['id'] ?? 0),
            'member_id' => $memberId,
            'member_name' => $nickname !== '' ? $nickname : ($realname !== '' ? $realname : ('会员' . $memberId)),
            'tel' => strval($row['tel'] ?? ''),
            'amount' => number_format(floatval($row['amount'] ?? 0), 2, '.', ''),
            'pay_info' => strval($row['pay_info'] ?? ''),
            'status' => $status,
            'status_text' => $map[$status] ?? '未知',
            'audit_remark' => strval($row['audit_remark'] ?? ''),
            'audit_time' => intval($row['audit_time'] ?? 0),
            'audit_time_text' => intval($row['audit_time'] ?? 0) > 0 ? date('Y-m-d H:i:s', intval($row['audit_time'])) : '',
            'create_time' => intval($row['create_time'] ?? 0),
            'create_time_text' => intval($row['create_time'] ?? 0) > 0 ? date('Y-m-d H:i:s', intval($row['create_time'])) : '',
        ];
    }
}
