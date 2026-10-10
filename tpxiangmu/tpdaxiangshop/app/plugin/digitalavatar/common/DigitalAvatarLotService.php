<?php

namespace app\plugin\digitalavatar\common;

use think\facade\Db;

class DigitalAvatarLotService
{
    public static function quotaFields(): array
    {
        return [
            'max_avatar_seconds' => ['label' => 'Max数字人合成额度', 'remain_label' => 'Max数字人合成', 'unit' => '秒'],
            'xpro_avatar_seconds' => ['label' => 'XPro全驱数字人额度', 'remain_label' => 'XPro数字人合成', 'unit' => '秒'],
            'max_voice_clone' => ['label' => 'Max声音克隆额度', 'remain_label' => 'Max声音克隆', 'unit' => '个'],
            'max_audio_chars' => ['label' => 'Max音频合成额度', 'remain_label' => 'Max文本转音频', 'unit' => '字'],
            'nwmax_voice_clone' => ['label' => 'NWMax声音克隆额度', 'remain_label' => 'NWMax声音克隆', 'unit' => '个'],
            'nwmax_audio_chars' => ['label' => 'NWMax音频合成额度', 'remain_label' => 'NWMax文本转音频', 'unit' => '字'],
            'spro_voice_clone' => ['label' => 'SPro声音克隆额度', 'remain_label' => 'SPro声音克隆', 'unit' => '个'],
            'spro_audio_chars' => ['label' => 'SPro音频合成额度', 'remain_label' => 'SPro文本转音频', 'unit' => '字'],
            'pro_voice_clone' => ['label' => 'Pro声音克隆额度', 'remain_label' => 'Pro声音克隆', 'unit' => '个'],
            'pro_audio_chars' => ['label' => 'Pro音频合成额度', 'remain_label' => 'Pro文本转音频', 'unit' => '字'],
            'imagea_count' => ['label' => 'imageA图片生成额度', 'remain_label' => 'imageA图片生成', 'unit' => '次'],
        ];
    }

    public static function normalizeQuotaMap($value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($value)) {
            $value = [];
        }
        $result = [];
        foreach (self::quotaFields() as $key => $meta) {
            $result[$key] = max(0, intval($value[$key] ?? 0));
        }
        foreach ($value as $key => $amount) {
            $key = trim(strval($key));
            if (preg_match('/^model_\d+$/', $key)) {
                $result[$key] = max(0, intval($amount));
            }
        }
        return $result;
    }

    public static function dedupeQuotaMap(array $quotas, int $aid = 0): array
    {
        foreach (self::legacyKeysShadowedByModels($quotas, $aid) as $legacyKey) {
            $quotas[$legacyKey] = 0;
        }
        return $quotas;
    }

    public static function legacyKeysShadowedByModels(array $quotas, int $aid = 0): array
    {
        $modelIds = [];
        foreach ($quotas as $key => $amount) {
            if (intval($amount) <= 0) {
                continue;
            }
            if (preg_match('/^model_(\d+)$/', strval($key), $match)) {
                $modelIds[] = intval($match[1]);
            }
        }
        if ($modelIds === []) {
            return [];
        }
        try {
            $query = Db::name('digitalavatar_model')->whereIn('id', $modelIds);
            if ($aid > 0) {
                $query->where('aid', $aid);
            }
            $models = $query->select()->toArray();
        } catch (\Throwable $e) {
            return [];
        }
        $shadowed = [];
        foreach ($models as $model) {
            $config = json_decode(strval($model['config_json'] ?? ''), true);
            $legacy = self::quotaKeyForTask(
                strval($model['task_type'] ?? ''),
                strval($model['model_code'] ?? ''),
                is_array($config) ? $config : []
            );
            if ($legacy !== '') {
                $shadowed[$legacy] = $legacy;
            }
        }
        return array_values($shadowed);
    }

    public static function quotaUnit(string $billingMode, string $taskType = ''): string
    {
        $mode = DigitalAvatarCreditService::normalizeBillingMode($billingMode);
        if ($mode === 'seconds') {
            return '秒';
        }
        if ($mode === 'minutes') {
            return '分钟';
        }
        if ($mode === 'chars') {
            return '字';
        }
        if ($mode === 'count') {
            return '次';
        }
        if ($taskType === 'voice_clone' || $taskType === 'avatar_clone') {
            return '个';
        }
        if ($taskType === 'audio') {
            return '字';
        }
        if ($taskType === 'avatar_video' || $taskType === 'video') {
            return '秒';
        }
        return '次';
    }

    public static function quotaMeta(string $key, int $aid = 0): array
    {
        $legacy = self::quotaFields()[$key] ?? null;
        if (is_array($legacy)) {
            return $legacy;
        }
        if (!preg_match('/^model_(\d+)$/', $key, $match)) {
            return ['label' => $key, 'remain_label' => $key, 'unit' => ''];
        }
        $modelId = intval($match[1]);
        $model = [];
        if ($modelId > 0) {
            try {
                $query = Db::name('digitalavatar_model')->where('id', $modelId);
                if ($aid > 0) {
                    $query->where('aid', $aid);
                }
                $model = $query->find() ?: [];
            } catch (\Throwable $e) {
                $model = [];
            }
        }
        $title = trim(strval($model['title'] ?? ''));
        if ($title === '') {
            $title = '模型#' . $modelId;
        }
        $taskType = strval($model['task_type'] ?? '');
        $config = json_decode(strval($model['config_json'] ?? ''), true);
        $config = is_array($config) ? $config : [];
        $unit = self::quotaUnit(strval($config['billing_mode'] ?? ''), $taskType);
        return [
            'label' => $title . '额度',
            'remain_label' => $title,
            'unit' => $unit,
        ];
    }

    public static function quotaKeyForTask(string $taskType, string $modelCode, array $config = [], array $input = []): string
    {
        $spec = DigitalAvatarNiwaCatalog::resolve($modelCode, $config, array_merge($input, ['task_type' => $taskType]));
        $family = strval($spec['family'] ?? '');
        if ($taskType === 'avatar_video' && $family === 'max_avatar') {
            return 'max_avatar_seconds';
        }
        if ($taskType === 'avatar_video' && $family === 'xpro') {
            return 'xpro_avatar_seconds';
        }
        if ($taskType === 'voice_clone' && $family === 'max_voice') {
            return 'max_voice_clone';
        }
        if ($taskType === 'audio' && $family === 'max_voice') {
            return 'max_audio_chars';
        }
        if ($taskType === 'voice_clone' && $family === 'nwmax') {
            return 'nwmax_voice_clone';
        }
        if ($taskType === 'audio' && $family === 'nwmax') {
            return 'nwmax_audio_chars';
        }
        if ($taskType === 'voice_clone' && $family === 'spro') {
            return 'spro_voice_clone';
        }
        if ($taskType === 'audio' && $family === 'spro') {
            return 'spro_audio_chars';
        }
        if ($taskType === 'voice_clone' && $family === 'pro_voice') {
            return 'pro_voice_clone';
        }
        if ($taskType === 'audio' && $family === 'pro_voice') {
            return 'pro_audio_chars';
        }
        if ($taskType === 'image' && $family === 'imagea') {
            return 'imagea_count';
        }
        return '';
    }

    public static function expireLots(int $aid = 0, int $bid = 0, int $memberId = 0): int
    {
        $now = time();
        $query = Db::name('digitalavatar_credit_lot')
            ->where('status', 1)
            ->where('expire_time', '>', 0)
            ->where('expire_time', '<', $now)
            ->where('amount_remain', '>', 0);
        if ($aid > 0) {
            $query->where('aid', $aid);
        }
        if ($memberId > 0) {
            $query->where('member_id', $memberId);
            if ($bid > 0) {
                $query->where('bid', $bid);
            }
        }
        $rows = $query->order('id asc')->limit(200)->select()->toArray();
        $cleared = 0;
        foreach ($rows as $row) {
            $cleared += self::expireLot($row);
        }
        return $cleared;
    }

    public static function grantCreditLot(
        int $aid,
        int $bid,
        int $memberId,
        $amount,
        int $expireTime,
        string $sourceType,
        int $sourceId,
        string $remark = ''
    ): int {
        $amount = DigitalAvatarCreditService::creditAmount($amount);
        if ($amount <= 0 || $memberId <= 0) {
            return 0;
        }
        return intval(Db::name('digitalavatar_credit_lot')->insertGetId([
            'aid' => $aid,
            'bid' => max(0, $bid),
            'member_id' => $memberId,
            'lot_type' => 'credit',
            'quota_key' => '',
            'amount_total' => DigitalAvatarCreditService::creditStore($amount),
            'amount_remain' => DigitalAvatarCreditService::creditStore($amount),
            'expire_time' => max(0, $expireTime),
            'source_type' => mb_substr($sourceType, 0, 60),
            'source_id' => $sourceId,
            'status' => 1,
            'remark' => mb_substr($remark, 0, 255),
            'create_time' => time(),
            'update_time' => time(),
        ]));
    }

    public static function grantQuotaLots(
        int $aid,
        int $bid,
        int $memberId,
        array $quotas,
        int $expireTime,
        string $sourceType,
        int $sourceId,
        string $remark = ''
    ): int {
        $count = 0;
        $quotas = self::dedupeQuotaMap(self::normalizeQuotaMap($quotas), $aid);
        foreach ($quotas as $key => $amount) {
            if ($amount <= 0) {
                continue;
            }
            Db::name('digitalavatar_credit_lot')->insert([
                'aid' => $aid,
                'bid' => max(0, $bid),
                'member_id' => $memberId,
                'lot_type' => 'quota',
                'quota_key' => $key,
                'amount_total' => $amount,
                'amount_remain' => $amount,
                'expire_time' => max(0, $expireTime),
                'source_type' => mb_substr($sourceType, 0, 60),
                'source_id' => $sourceId,
                'status' => 1,
                'remark' => mb_substr($remark, 0, 255),
                'create_time' => time(),
                'update_time' => time(),
            ]);
            $count++;
        }
        return $count;
    }

    public static function remainingQuota(int $aid, int $bid, int $memberId, string $quotaKey): int
    {
        if ($quotaKey === '' || $memberId <= 0) {
            return 0;
        }
        self::expireLots($aid, $bid, $memberId);
        $query = Db::name('digitalavatar_credit_lot')
            ->where('aid', $aid)
            ->where('member_id', $memberId)
            ->where('lot_type', 'quota')
            ->where('quota_key', $quotaKey)
            ->where('status', 1)
            ->where('amount_remain', '>', 0);
        if ($bid > 0) {
            $query->where('bid', $bid);
        }
        return intval($query->sum('amount_remain'));
    }

    public static function memberQuotaSummary(int $aid, int $bid, int $memberId): array
    {
        self::expireLots($aid, $bid, $memberId);
        $summary = [];
        foreach (self::quotaFields() as $key => $meta) {
            $summary[$key] = [
                'key' => $key,
                'label' => $meta['label'],
                'unit' => $meta['unit'],
                'remain' => self::remainingQuota($aid, $bid, $memberId, $key),
            ];
        }
        return $summary;
    }

    public static function quotaRemainLabel(string $key): string
    {
        $meta = self::quotaFields()[$key] ?? [];
        $label = trim(strval($meta['remain_label'] ?? ''));
        if ($label !== '') {
            return $label;
        }
        $label = trim(strval($meta['label'] ?? $key));
        return str_replace('额度', '', $label);
    }

    public static function memberPackageRemainDetail(int $aid, int $bid, int $memberId): array
    {
        self::expireLots($aid, $bid, $memberId);
        $empty = [
            'items' => [],
            'packages' => [],
            'boosts' => [],
            'daily' => [],
            'rewards' => [],
            'regular' => [],
            'member_packages' => [],
        ];
        if ($memberId <= 0) {
            return $empty;
        }
        $query = Db::name('digitalavatar_credit_lot')
            ->where('aid', $aid)
            ->where('member_id', $memberId)
            ->where('status', 1)
            ->where('amount_remain', '>', 0);
        if ($bid > 0) {
            $query->where('bid', $bid);
        }
        $lots = $query->order('expire_time asc, id desc')->select()->toArray();
        $aggregated = [];
        foreach (self::quotaFields() as $key => $meta) {
            $aggregated[$key] = [
                'key' => $key,
                'label' => self::quotaRemainLabel($key),
                'unit' => strval($meta['unit'] ?? ''),
                'remain' => 0,
            ];
        }
        $groups = [];
        foreach ($lots as $lot) {
            $remain = DigitalAvatarCreditService::creditAmount($lot['amount_remain'] ?? 0);
            if ($remain <= 0) {
                continue;
            }
            $sourceType = trim(strval($lot['source_type'] ?? ''));
            $sourceId = intval($lot['source_id'] ?? 0);
            $expireKey = intval($lot['expire_time'] ?? 0);
            $groupKey = $sourceType . '-' . $sourceId . '-' . $expireKey;
            if ($groupKey === '-0-0') {
                $groupKey = 'lot-' . intval($lot['id'] ?? 0);
            }
            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'expire_time' => intval($lot['expire_time'] ?? 0),
                    'credit_remain' => 0,
                    'quotas' => [],
                    'remark' => trim(strval($lot['remark'] ?? '')),
                ];
            }
            $expireTime = intval($lot['expire_time'] ?? 0);
            if ($expireTime > 0) {
                $currentExpire = intval($groups[$groupKey]['expire_time'] ?? 0);
                if ($currentExpire <= 0 || $expireTime < $currentExpire) {
                    $groups[$groupKey]['expire_time'] = $expireTime;
                }
            }
            if (strval($lot['lot_type'] ?? '') === 'credit') {
                $groups[$groupKey]['credit_remain'] += $remain;
                continue;
            }
            $quotaKey = trim(strval($lot['quota_key'] ?? ''));
            if ($quotaKey === '') {
                continue;
            }
            if (!isset($groups[$groupKey]['quotas'][$quotaKey])) {
                $groups[$groupKey]['quotas'][$quotaKey] = 0;
            }
            $groups[$groupKey]['quotas'][$quotaKey] += $remain;
            if (isset($aggregated[$quotaKey])) {
                $aggregated[$quotaKey]['remain'] += $remain;
            }
        }

        $orderIds = [];
        foreach ($groups as $group) {
            if (intval($group['source_id'] ?? 0) > 0) {
                $orderIds[] = intval($group['source_id']);
            }
        }
        $orders = [];
        if ($orderIds) {
            try {
                $rows = Db::name('digitalavatar_package_order')
                    ->alias('o')
                    ->leftJoin('digitalavatar_package p', 'p.id = o.package_id')
                    ->whereIn('o.id', array_values(array_unique($orderIds)))
                    ->field('o.id,o.package_id,o.ordernum,p.title as package_title,p.package_type,p.subtitle')
                    ->select()
                    ->toArray();
                foreach ($rows as $row) {
                    $orders[intval($row['id'] ?? 0)] = $row;
                }
            } catch (\Throwable $e) {
                $orders = [];
            }
        }

        $rewards = [];
        $memberPackages = [];
        $boosts = [];
        $regular = [];
        $rewardCredit = 0;
        $memberCredit = 0;
        $regularLotCredit = 0;
        foreach ($groups as $group) {
            $items = [];
            $creditRemain = DigitalAvatarCreditService::creditAmount($group['credit_remain'] ?? 0);
            if ($creditRemain > 0) {
                $items[] = [
                    'key' => 'credit',
                    'label' => '创作点',
                    'remain' => $creditRemain,
                    'unit' => '点',
                    'remain_text' => '剩余 ' . DigitalAvatarCreditService::formatCredit($creditRemain) . '点',
                ];
            }
            $quotaKeys = array_keys($group['quotas']);
            foreach (array_keys(self::quotaFields()) as $key) {
                if (!in_array($key, $quotaKeys, true)) {
                    $quotaKeys[] = $key;
                }
            }
            $shadowed = array_fill_keys(self::legacyKeysShadowedByModels($group['quotas'], $aid), true);
            foreach ($quotaKeys as $key) {
                if (isset($shadowed[$key])) {
                    continue;
                }
                $quotaRemain = max(0, intval($group['quotas'][$key] ?? 0));
                if ($quotaRemain <= 0) {
                    continue;
                }
                $meta = self::quotaMeta($key, $aid);
                $items[] = [
                    'key' => $key,
                    'label' => strval($meta['remain_label'] ?? $key),
                    'remain' => $quotaRemain,
                    'unit' => strval($meta['unit'] ?? ''),
                    'remain_text' => '剩余 ' . $quotaRemain . strval($meta['unit'] ?? ''),
                ];
            }
            if ($items === []) {
                continue;
            }
            $order = $orders[intval($group['source_id'] ?? 0)] ?? [];
            $sourceType = trim(strval($group['source_type'] ?? ''));
            $orderType = strval($order['package_type'] ?? '');
            $packageType = $orderType === 'boost' ? 'boost' : ($orderType === 'credit' ? 'credit' : '');
            if ($packageType === '' && $sourceType === 'boost_pack') {
                $packageType = 'boost';
            }
            if ($packageType === '' && $sourceType === 'package') {
                $packageType = 'credit';
            }
            $bucket = 'regular';
            if (in_array($sourceType, ['checkin', 'invite'], true)) {
                $bucket = 'rewards';
            } elseif ($packageType === 'boost' || $sourceType === 'boost_pack') {
                $bucket = 'boosts';
            } elseif ($packageType === 'credit' || $sourceType === 'package') {
                $bucket = 'member';
            }
            $title = '';
            $remark = trim(strval($group['remark'] ?? ''));
            $isRedeemLot = $sourceType === 'redeem' || strpos($remark, '兑换码') !== false;
            if ($sourceType === 'checkin') {
                $title = '签到奖励';
            } elseif ($sourceType === 'invite') {
                $title = '拉新奖励';
            } elseif ($isRedeemLot) {
                $title = '兑换码充值';
            } else {
                $title = trim(strval($order['package_title'] ?? ''));
            }
            if ($title === '') {
                if ($bucket === 'rewards' && $sourceType === 'checkin') {
                    $title = '签到奖励';
                } elseif ($bucket === 'rewards' && $sourceType === 'invite') {
                    $title = '拉新奖励';
                } elseif ($sourceType === 'task_refund') {
                    $title = '任务退回额度';
                } elseif (in_array($sourceType, ['admin', 'initial'], true)) {
                    $title = $sourceType === 'initial' ? '注册赠送' : '后台开通';
                } elseif ($remark !== '') {
                    $title = $remark;
                } elseif ($bucket === 'boosts') {
                    $title = '加油包';
                } elseif ($bucket === 'member') {
                    $title = '会员套餐';
                } else {
                    $title = '普通算力';
                }
            }
            $expireTime = intval($group['expire_time'] ?? 0);
            $typeMeta = self::remainTypeMeta($bucket);
            $card = [
                'title' => $title,
                'subtitle' => trim(strval($order['subtitle'] ?? '')),
                'package_type' => strval($typeMeta['package_type']),
                'package_type_text' => strval($typeMeta['package_type_text']),
                'group' => $bucket,
                'ordernum' => strval($order['ordernum'] ?? ''),
                'expire_time' => $expireTime,
                'expire_time_text' => $expireTime > 0 ? date('Y-m-d', $expireTime) : '长期有效',
                'items' => $items,
            ];
            if ($bucket === 'rewards') {
                $rewardCredit += $creditRemain;
                $rewards[] = $card;
            } elseif ($bucket === 'boosts') {
                $boosts[] = $card;
            } elseif ($bucket === 'member') {
                $memberCredit += $creditRemain;
                $memberPackages[] = $card;
            } else {
                $regularLotCredit += $creditRemain;
                $regular[] = $card;
            }
        }

        $daily = self::memberDailyRemainCards($aid, $bid, $memberId);
        $unlottedRegular = self::unlottedRegularCredit($aid, $bid, $memberId, $rewardCredit + $memberCredit + $regularLotCredit);
        if ($unlottedRegular > 0) {
            $regular[] = [
                'title' => '普通算力',
                'subtitle' => '',
                'package_type' => 'regular',
                'package_type_text' => '普通算力',
                'group' => 'regular',
                'ordernum' => '',
                'expire_time' => 0,
                'expire_time_text' => '长期有效',
                'items' => [[
                    'key' => 'credit',
                    'label' => '创作点',
                    'remain' => $unlottedRegular,
                    'unit' => '点',
                    'remain_text' => '剩余 ' . DigitalAvatarCreditService::formatCredit($unlottedRegular) . '点',
                ]],
            ];
        }

        $aggItems = [];
        foreach ($aggregated as $row) {
            if (intval($row['remain'] ?? 0) <= 0) {
                continue;
            }
            $row['remain_text'] = '剩余 ' . intval($row['remain']) . strval($row['unit'] ?? '');
            $aggItems[] = $row;
        }

        $spaPackages = array_merge($daily, $rewards, $regular, $memberPackages);
        return [
            'items' => $aggItems,
            'packages' => array_values($spaPackages),
            'boosts' => array_values($boosts),
            'daily' => array_values($daily),
            'rewards' => array_values($rewards),
            'regular' => array_values($regular),
            'member_packages' => array_values($memberPackages),
        ];
    }

    private static function remainTypeMeta(string $bucket): array
    {
        $map = [
            'daily' => ['package_type' => 'daily', 'package_type_text' => '每日赠送'],
            'rewards' => ['package_type' => 'reward', 'package_type_text' => '奖励算力'],
            'regular' => ['package_type' => 'regular', 'package_type_text' => '普通算力'],
            'member' => ['package_type' => 'credit', 'package_type_text' => '会员算力'],
            'boosts' => ['package_type' => 'boost', 'package_type_text' => '加油包'],
        ];
        return $map[$bucket] ?? $map['regular'];
    }

    private static function memberDailyRemainCards(int $aid, int $bid, int $memberId): array
    {
        try {
            $rules = Db::name('digitalavatar_daily_quota_rule')
                ->where('aid', $aid)
                ->where('status', 1)
                ->where('quota_count', '>', 0)
                ->order('id asc')
                ->select()
                ->toArray();
        } catch (\Throwable $e) {
            return [];
        }
        if ($rules === []) {
            return [];
        }
        $date = date('Y-m-d');
        $usageMap = [];
        try {
            $usageRows = Db::name('digitalavatar_daily_quota_usage')
                ->where('aid', $aid)
                ->where('bid', max(0, $bid))
                ->where('member_id', $memberId)
                ->where('quota_date', $date)
                ->select()
                ->toArray();
            foreach ($usageRows as $row) {
                $usageMap[strval($row['model_code'] ?? '')] = $row;
            }
        } catch (\Throwable $e) {
            $usageMap = [];
        }
        $items = [];
        foreach ($rules as $rule) {
            $modelCode = trim(strval($rule['model_code'] ?? ''));
            if ($modelCode === '') {
                continue;
            }
            $quotaCount = max(0, intval($rule['quota_count'] ?? 0));
            $usage = $usageMap[$modelCode] ?? [];
            $used = intval($usage['quota_used'] ?? 0) + intval($usage['quota_reserved'] ?? 0);
            $remain = max(0, $quotaCount - $used);
            $label = trim(strval($rule['quota_name'] ?? ''));
            if ($label === '') {
                $label = $modelCode;
            }
            $items[] = [
                'key' => 'daily-' . $modelCode,
                'label' => $label,
                'remain' => $remain,
                'unit' => '次',
                'remain_text' => '剩余 ' . $remain . '次',
            ];
        }
        if ($items === []) {
            return [];
        }
        return [[
            'title' => '每日赠送',
            'subtitle' => '',
            'package_type' => 'daily',
            'package_type_text' => '每日赠送',
            'group' => 'daily',
            'ordernum' => '',
            'expire_time' => strtotime('tomorrow') ?: 0,
            'expire_time_text' => '当日清空',
            'items' => $items,
        ]];
    }

    private static function unlottedRegularCredit(int $aid, int $bid, int $memberId, $lottedCredit)
    {
        try {
            $row = Db::name('digitalavatar_member_credit')
                ->where('aid', $aid)
                ->where('bid', max(0, $bid))
                ->where('member_id', $memberId)
                ->find();
        } catch (\Throwable $e) {
            return 0;
        }
        if (!$row) {
            return 0;
        }
        $available = DigitalAvatarCreditService::creditAmount(
            DigitalAvatarCreditService::creditSub($row['credit_balance'] ?? 0, $row['credit_frozen'] ?? 0)
        );
        return DigitalAvatarCreditService::creditAmount(DigitalAvatarCreditService::creditSub($available, $lottedCredit));
    }

    /**
     * 扣费顺序：奖励模型额度 → 签到/拉新算力 → 会员算力 → 加油包模型额度 → 普通算力。
     * 每日赠送由任务创建流程先扣，剩余数量通过 remaining_quantity 传入。
     */
    public static function reserveForTask(int $aid, int $bid, int $memberId, array $task, array $cost): array
    {
        self::expireLots($aid, $bid, $memberId);
        $taskType = strval($task['task_type'] ?? '');
        $modelCode = strval($task['model_code'] ?? '');
        $config = [];
        $model = Db::name('digitalavatar_model')
            ->where('aid', $aid)
            ->where('task_type', $taskType)
            ->where('model_code', $modelCode)
            ->find();
        if ($model) {
            $decoded = json_decode(strval($model['config_json'] ?? ''), true);
            $config = is_array($decoded) ? $decoded : [];
        }
        $input = is_array($cost['input'] ?? null) ? $cost['input'] : [];
        $quotaKeys = [];
        if ($model && intval($model['id'] ?? 0) > 0) {
            $quotaKeys[] = 'model_' . intval($model['id']);
        }
        $legacyKey = self::quotaKeyForTask($taskType, $modelCode, $config, $input);
        if ($legacyKey !== '' && !in_array($legacyKey, $quotaKeys, true)) {
            $modelKey = $quotaKeys[0] ?? '';
            $hasModelLot = false;
            if ($modelKey !== '' && strpos($modelKey, 'model_') === 0) {
                $hasModelLot = Db::name('digitalavatar_credit_lot')
                    ->where('aid', $aid)
                    ->where('member_id', $memberId)
                    ->where('lot_type', 'quota')
                    ->where('quota_key', $modelKey)
                    ->count() > 0;
            }
            if (!$hasModelLot) {
                $quotaKeys[] = $legacyKey;
            }
        }
        $quotaKey = $quotaKeys[0] ?? '';
        $mode = DigitalAvatarCreditService::normalizeBillingMode(strval($cost['billing_mode'] ?? 'fixed'));
        $quantity = max(0, intval(ceil(floatval($cost['remaining_quantity'] ?? ($cost['quantity'] ?? ($cost['quota_units'] ?? 1))))));
        if ($mode === 'fixed') {
            $quantity = $quantity > 0 ? $quantity : 1;
        }
        $skipBase = !empty($cost['skip_base']);
        $quotaLots = [];
        $rewardQuotaTaken = 0;
        $boostQuotaTaken = 0;
        $creditCost = 0.0;
        $quotaTakenAny = false;

        // 扣费顺序：先临近到期；同到期日则 奖励(签到/拉新) → 加油包额度 → 普通/会员算力点数。
        // 只从一个池扣；当前池不够再从下一个池补扣，避免点数够却不扣加油包、或无故多池同扣。
        Db::transaction(function () use (
            $aid,
            $bid,
            $memberId,
            $quotaKeys,
            $cost,
            &$quantity,
            &$skipBase,
            &$quotaLots,
            &$rewardQuotaTaken,
            &$boostQuotaTaken,
            &$creditCost,
            &$quotaTakenAny
        ) {
            $candidates = self::loadDeductCandidates($aid, $bid, $memberId, $quotaKeys);
            foreach ($candidates as $cand) {
                if ($quantity <= 0) {
                    break;
                }
                $lotId = intval($cand['id'] ?? 0);
                if ($lotId <= 0) {
                    continue;
                }
                $fresh = Db::name('digitalavatar_credit_lot')->where('id', $lotId)->lock(true)->find();
                if (!$fresh || intval($fresh['status'] ?? 0) !== 1) {
                    continue;
                }
                $lotType = strval($fresh['lot_type'] ?? '');
                $sourceType = strval($fresh['source_type'] ?? '');

                if ($lotType === 'quota') {
                    $remain = max(0, intval($fresh['amount_remain'] ?? 0));
                    if ($remain <= 0) {
                        continue;
                    }
                    $take = min($remain, $quantity);
                    if ($take <= 0) {
                        continue;
                    }
                    $nextRemain = $remain - $take;
                    Db::name('digitalavatar_credit_lot')->where('id', $lotId)->update([
                        'amount_remain' => $nextRemain,
                        'status' => $nextRemain > 0 ? 1 : 0,
                        'update_time' => time(),
                    ]);
                    $quotaLots[] = [
                        'id' => $lotId,
                        'amount' => $take,
                        'lot_type' => 'quota',
                        'quota_key' => strval($fresh['quota_key'] ?? ''),
                        'source_type' => $sourceType,
                    ];
                    $quantity = max(0, $quantity - $take);
                    $quotaTakenAny = true;
                    if (in_array($sourceType, ['checkin', 'invite'], true)) {
                        $rewardQuotaTaken += $take;
                        $skipBase = true;
                    } elseif ($sourceType === 'boost_pack') {
                        $boostQuotaTaken += $take;
                        $skipBase = true;
                    }
                    continue;
                }

                if ($lotType !== 'credit') {
                    continue;
                }
                $remain = DigitalAvatarCreditService::creditAmount($fresh['amount_remain'] ?? 0);
                if ($remain <= 0) {
                    continue;
                }
                $need = self::creditCostForQuantity($cost, $quantity, $skipBase || $quotaTakenAny);
                $still = DigitalAvatarCreditService::creditAmount(DigitalAvatarCreditService::creditSub($need, $creditCost));
                if ($still <= 0) {
                    $quantity = 0;
                    break;
                }
                $take = min($remain, $still);
                if (DigitalAvatarCreditService::creditAmount($take) <= 0) {
                    continue;
                }
                // 点数在 confirm 时按同样排序实扣；这里只累计预扣金额。
                $creditCost = DigitalAvatarCreditService::creditAdd($creditCost, $take);
                if (!DigitalAvatarCreditService::creditLt($creditCost, $need)) {
                    $quantity = 0;
                    break;
                }
            }

            if ($quantity > 0) {
                $need = self::creditCostForQuantity($cost, $quantity, $skipBase || $quotaTakenAny);
                if (DigitalAvatarCreditService::creditGt($need, $creditCost)) {
                    $creditCost = $need;
                }
                // 剩余数量改由点数账户余额兜底（freeze 会校验可用点）
                $quantity = 0;
            }
        });

        $quotaTaken = $rewardQuotaTaken + $boostQuotaTaken;
        $creditCost = DigitalAvatarCreditService::creditAmount($creditCost);
        $creditSource = 'member_credit';
        if ($quotaTaken > 0 && $creditCost <= 0) {
            $creditSource = 'model_quota';
        } elseif ($quotaTaken > 0 && $creditCost > 0) {
            $creditSource = 'mixed';
        }

        return [
            'quota_key' => $quotaKey,
            'quota_taken' => $quotaTaken,
            'quota_lots' => $quotaLots,
            'billing_quantity' => intval(ceil(floatval($cost['quantity'] ?? ($cost['quota_units'] ?? 1)))),
            'credit_cost' => $creditCost,
            'credit_source' => $creditSource,
            'reward_quota_taken' => $rewardQuotaTaken,
            'boost_quota_taken' => $boostQuotaTaken,
        ];
    }

    /**
     * 扣费候选：额度(奖励/加油包) + 点数，按到期日与来源优先级排序。
     */
    private static function loadDeductCandidates(int $aid, int $bid, int $memberId, array $quotaKeys): array
    {
        $creditQuery = Db::name('digitalavatar_credit_lot')
            ->where('aid', $aid)
            ->where('member_id', $memberId)
            ->where('lot_type', 'credit')
            ->where('status', 1)
            ->where('amount_remain', '>', 0);
        if ($bid > 0) {
            $creditQuery->where('bid', $bid);
        }
        $rows = $creditQuery->lock(true)->select()->toArray();

        if ($quotaKeys !== []) {
            $quotaQuery = Db::name('digitalavatar_credit_lot')
                ->where('aid', $aid)
                ->where('member_id', $memberId)
                ->where('lot_type', 'quota')
                ->whereIn('quota_key', $quotaKeys)
                ->whereIn('source_type', ['checkin', 'invite', 'boost_pack'])
                ->where('status', 1)
                ->where('amount_remain', '>', 0);
            if ($bid > 0) {
                $quotaQuery->where('bid', $bid);
            }
            $rows = array_merge($rows, $quotaQuery->lock(true)->select()->toArray());
        }

        usort($rows, static function ($left, $right) {
            return self::compareDeductLots($left, $right);
        });
        return $rows;
    }

    private static function sourceDeductPriority(string $sourceType): int
    {
        if (in_array($sourceType, ['checkin', 'invite'], true)) {
            return 0;
        }
        if ($sourceType === 'boost_pack') {
            return 1;
        }
        if ($sourceType === 'package') {
            return 2;
        }
        return 3;
    }

    private static function compareDeductLots($left, $right): int
    {
        $leftExpire = intval(is_array($left) ? ($left['expire_time'] ?? 0) : 0);
        $rightExpire = intval(is_array($right) ? ($right['expire_time'] ?? 0) : 0);
        $leftEternal = $leftExpire <= 0 ? 1 : 0;
        $rightEternal = $rightExpire <= 0 ? 1 : 0;
        if ($leftEternal !== $rightEternal) {
            return $leftEternal <=> $rightEternal;
        }
        if ($leftExpire !== $rightExpire) {
            return $leftExpire <=> $rightExpire;
        }
        $leftPri = self::sourceDeductPriority(strval(is_array($left) ? ($left['source_type'] ?? '') : ''));
        $rightPri = self::sourceDeductPriority(strval(is_array($right) ? ($right['source_type'] ?? '') : ''));
        if ($leftPri !== $rightPri) {
            return $leftPri <=> $rightPri;
        }
        return intval(is_array($left) ? ($left['id'] ?? 0) : 0) <=> intval(is_array($right) ? ($right['id'] ?? 0) : 0);
    }

    private static function creditCostForQuantity(array $cost, int $quantity, bool $skipBase)
    {
        $quantity = max(0, $quantity);
        if ($quantity <= 0) {
            return 0;
        }
        $mode = DigitalAvatarCreditService::normalizeBillingMode(strval($cost['billing_mode'] ?? 'fixed'));
        $unitCount = max(1, intval($cost['unit_count'] ?? 1));
        $unitCredit = DigitalAvatarCreditService::creditAmount($cost['unit_credit'] ?? 0);
        $baseCredit = $skipBase ? 0 : DigitalAvatarCreditService::creditAmount($cost['base_credit'] ?? 0);
        if ($mode === 'fixed') {
            // 按剩余次数计价，避免已扣额度后仍按整单点数再扣一次
            $per = DigitalAvatarCreditService::creditAmount($cost['credit_cost'] ?? $unitCredit);
            if ($per <= 0 && $unitCredit > 0) {
                $per = $unitCredit;
            }
            $total = DigitalAvatarCreditService::creditMul($per, $quantity);
            if (!$skipBase && $baseCredit > 0 && $quantity > 0) {
                // credit_cost 通常已含 base；仅当 credit_cost 为空时补 base
                if (DigitalAvatarCreditService::creditAmount($cost['credit_cost'] ?? 0) <= 0) {
                    $total = DigitalAvatarCreditService::creditAdd($baseCredit, $total);
                }
            }
            return DigitalAvatarCreditService::creditAmount($total);
        }
        $units = (int)ceil($quantity / $unitCount);
        return DigitalAvatarCreditService::creditAdd($baseCredit, DigitalAvatarCreditService::creditMul($unitCredit, $units));
    }

    public static function remainingBySources(
        int $aid,
        int $bid,
        int $memberId,
        string $lotType,
        string $quotaKey,
        array $sourceTypes
    ) {
        $query = Db::name('digitalavatar_credit_lot')
            ->where('aid', $aid)
            ->where('member_id', $memberId)
            ->where('lot_type', $lotType)
            ->where('status', 1)
            ->where('amount_remain', '>', 0);
        if ($bid > 0) {
            $query->where('bid', $bid);
        }
        if ($lotType === 'quota' && $quotaKey !== '') {
            $query->where('quota_key', $quotaKey);
        }
        if ($sourceTypes !== []) {
            $query->whereIn('source_type', $sourceTypes);
        }
        $sum = $query->sum('amount_remain');
        if ($lotType === 'credit') {
            return DigitalAvatarCreditService::creditAmount($sum);
        }
        return max(0, intval($sum));
    }

    public static function expiringCreditAmount(int $aid, int $bid, int $memberId, int $withinDays = 7): float
    {
        if ($memberId <= 0) {
            return 0.0;
        }
        $now = time();
        $until = $now + max(1, $withinDays) * 86400;
        $query = Db::name('digitalavatar_credit_lot')
            ->where('aid', $aid)
            ->where('member_id', $memberId)
            ->where('lot_type', 'credit')
            ->where('status', 1)
            ->where('amount_remain', '>', 0)
            ->where('expire_time', '>', $now)
            ->where('expire_time', '<=', $until);
        if ($bid > 0) {
            $query->where('bid', $bid);
        }
        return DigitalAvatarCreditService::creditAmount($query->sum('amount_remain'));
    }

    public static function refundTaskLots(array $task): void
    {
        $input = [];
        $decoded = json_decode(strval($task['input_json'] ?? ''), true);
        if (is_array($decoded)) {
            $input = $decoded;
        }
        $lots = is_array($input['lot_consume'] ?? null) ? $input['lot_consume'] : [];
        foreach ($lots as $item) {
            $lotId = intval($item['id'] ?? 0);
            $lot = $lotId > 0 ? Db::name('digitalavatar_credit_lot')->where('id', $lotId)->find() : [];
            if (!$lot) {
                continue;
            }
            $isCredit = strval($lot['lot_type'] ?? '') === 'credit';
            $amount = $isCredit
                ? DigitalAvatarCreditService::creditAmount($item['amount'] ?? 0)
                : max(0, intval($item['amount'] ?? 0));
            if ($lotId <= 0 || $amount <= 0) {
                continue;
            }
            $remain = $isCredit
                ? DigitalAvatarCreditService::creditAdd($lot['amount_remain'] ?? 0, $amount)
                : (intval($lot['amount_remain'] ?? 0) + $amount);
            $total = $isCredit
                ? max($remain, DigitalAvatarCreditService::creditAmount($lot['amount_total'] ?? 0))
                : max($remain, intval($lot['amount_total'] ?? 0));
            $expired = intval($lot['expire_time'] ?? 0) > 0 && intval($lot['expire_time']) < time();
            Db::name('digitalavatar_credit_lot')->where('id', $lotId)->update([
                'amount_remain' => $isCredit ? DigitalAvatarCreditService::creditStore($remain) : $remain,
                'amount_total' => $isCredit ? DigitalAvatarCreditService::creditStore($total) : $total,
                'status' => $expired ? 0 : 1,
                'update_time' => time(),
            ]);
        }
    }

    public static function confirmCreditLots(int $aid, int $bid, int $memberId, $amount): void
    {
        $amount = DigitalAvatarCreditService::creditAmount($amount);
        if ($amount <= 0) {
            return;
        }
        // 与 reserve 一致：按到期日优先，同到期日奖励点数 → 会员套餐 → 普通点数
        self::consumeLots($aid, $bid, $memberId, 'credit', '', $amount);
    }

    public static function refundCreditLots(int $aid, int $bid, int $memberId, $amount, int $expireTime = 0): void
    {
        $amount = DigitalAvatarCreditService::creditAmount($amount);
        if ($amount <= 0) {
            return;
        }
        self::grantCreditLot($aid, $bid, $memberId, $amount, $expireTime, 'task_refund', 0, '任务失败退回算力');
    }

    private static function consumeLots(
        int $aid,
        int $bid,
        int $memberId,
        string $lotType,
        string $quotaKey,
        $amount,
        array $sourceTypes = []
    ): array {
        $isCredit = $lotType === 'credit';
        $need = $isCredit
            ? DigitalAvatarCreditService::creditAmount($amount)
            : max(0, intval($amount));
        $used = [];
        $taken = 0;
        if ($need <= 0) {
            return ['amount' => 0, 'lots' => []];
        }
        Db::transaction(function () use ($aid, $bid, $memberId, $lotType, $quotaKey, $sourceTypes, $isCredit, &$need, &$used, &$taken) {
            $query = Db::name('digitalavatar_credit_lot')
                ->where('aid', $aid)
                ->where('member_id', $memberId)
                ->where('lot_type', $lotType)
                ->where('status', 1)
                ->where('amount_remain', '>', 0);
            if ($bid > 0) {
                $query->where('bid', $bid);
            }
            if ($lotType === 'quota') {
                $query->where('quota_key', $quotaKey);
            }
            if ($sourceTypes !== []) {
                $query->whereIn('source_type', $sourceTypes);
            }
            $rows = $query->lock(true)->select()->toArray();
            usort($rows, static function ($left, $right) {
                return self::compareDeductLots($left, $right);
            });
            foreach ($rows as $row) {
                if ($need <= 0) {
                    break;
                }
                $remain = $isCredit
                    ? DigitalAvatarCreditService::creditAmount($row['amount_remain'] ?? 0)
                    : max(0, intval($row['amount_remain'] ?? 0));
                if ($remain <= 0) {
                    continue;
                }
                $take = min($remain, $need);
                $nextRemain = $isCredit
                    ? DigitalAvatarCreditService::creditAmount(DigitalAvatarCreditService::creditSub($remain, $take))
                    : ($remain - $take);
                Db::name('digitalavatar_credit_lot')->where('id', intval($row['id']))->update([
                    'amount_remain' => $isCredit ? DigitalAvatarCreditService::creditStore($nextRemain) : $nextRemain,
                    'status' => $nextRemain > 0 ? 1 : 0,
                    'update_time' => time(),
                ]);
                $used[] = [
                    'id' => intval($row['id']),
                    'amount' => $take,
                    'lot_type' => $lotType,
                    'quota_key' => strval($row['quota_key'] ?? ''),
                    'source_type' => strval($row['source_type'] ?? ''),
                ];
                $taken = $isCredit
                    ? DigitalAvatarCreditService::creditAdd($taken, $take)
                    : ($taken + $take);
                $need = $isCredit
                    ? DigitalAvatarCreditService::creditAmount(DigitalAvatarCreditService::creditSub($need, $take))
                    : ($need - $take);
            }
        });
        return ['amount' => $taken, 'lots' => $used];
    }

    private static function expireLot(array $row): int
    {
        $lotId = intval($row['id'] ?? 0);
        $remain = strval($row['lot_type'] ?? '') === 'credit'
            ? DigitalAvatarCreditService::creditAmount($row['amount_remain'] ?? 0)
            : max(0, intval($row['amount_remain'] ?? 0));
        if ($lotId <= 0) {
            return 0;
        }
        Db::transaction(function () use ($row, $lotId, $remain) {
            $fresh = Db::name('digitalavatar_credit_lot')->where('id', $lotId)->lock(true)->find();
            if (!$fresh || intval($fresh['status'] ?? 0) !== 1) {
                return;
            }
            $remainNow = strval($fresh['lot_type'] ?? '') === 'credit'
                ? DigitalAvatarCreditService::creditAmount($fresh['amount_remain'] ?? 0)
                : max(0, intval($fresh['amount_remain'] ?? 0));
            if (strval($fresh['lot_type'] ?? '') === 'credit' && $remainNow > 0) {
                $account = Db::name('digitalavatar_member_credit')
                    ->where('aid', intval($fresh['aid']))
                    ->where('bid', intval($fresh['bid']))
                    ->where('member_id', intval($fresh['member_id']))
                    ->lock(true)
                    ->find();
                if ($account) {
                    $frozen = DigitalAvatarCreditService::creditAmount($account['credit_frozen'] ?? 0);
                    $balance = DigitalAvatarCreditService::creditAmount($account['credit_balance'] ?? 0);
                    $available = DigitalAvatarCreditService::creditAmount(DigitalAvatarCreditService::creditSub($balance, $frozen));
                    $cut = min($remainNow, $available);
                    if ($cut > 0) {
                        $after = max($frozen, DigitalAvatarCreditService::creditSub($balance, $cut));
                        Db::name('digitalavatar_member_credit')->where('id', intval($account['id']))->update([
                            'credit_balance' => DigitalAvatarCreditService::creditStore($after),
                            'update_time' => time(),
                        ]);
                        DigitalAvatarCreditService::writeExpireLog(
                            intval($fresh['aid']),
                            intval($fresh['bid']),
                            intval($fresh['member_id']),
                            -$cut,
                            $balance,
                            $after,
                            intval($fresh['source_id']),
                            self::expireRemark(strval($fresh['source_type'] ?? ''))
                        );
                    }
                }
            }
            Db::name('digitalavatar_credit_lot')->where('id', $lotId)->update([
                'amount_remain' => 0,
                'status' => 0,
                'update_time' => time(),
            ]);
        });
        return $remain > 0 ? 1 : 0;
    }

    private static function expireRemark(string $sourceType): string
    {
        if ($sourceType === 'checkin') {
            return '签到奖励到期清空剩余额度';
        }
        if ($sourceType === 'invite') {
            return '拉新奖励到期清空剩余额度';
        }
        return '套餐/加油包到期清空剩余额度';
    }
}
