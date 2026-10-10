<?php

namespace app\plugin\digitalavatar\common;

use think\facade\Db;

class DigitalAvatarCreditService
{
    public const CREDIT_SCALE = 5;
    public const CREDIT_MIN_UNIT = 0.00001;

    public static function creditSigned($value): float
    {
        if (!is_numeric($value)) {
            return 0.0;
        }
        return round(floatval($value), self::CREDIT_SCALE);
    }

    public static function creditAmount($value): float
    {
        $amount = self::creditSigned($value);
        return $amount > 0 ? $amount : 0.0;
    }

    public static function creditStore($value): string
    {
        return number_format(self::creditSigned($value), self::CREDIT_SCALE, '.', '');
    }

    public static function creditAdd($left, $right): float
    {
        return self::creditSigned(self::creditSigned($left) + self::creditSigned($right));
    }

    public static function creditSub($left, $right): float
    {
        return self::creditSigned(self::creditSigned($left) - self::creditSigned($right));
    }

    public static function creditMul($left, $right): float
    {
        return self::creditSigned(self::creditSigned($left) * floatval($right));
    }

    public static function creditLt($left, $right): bool
    {
        return self::creditSigned($left) < self::creditSigned($right);
    }

    public static function creditGt($left, $right): bool
    {
        return self::creditSigned($left) > self::creditSigned($right);
    }

    public static function creditEq($left, $right): bool
    {
        return self::creditSigned($left) === self::creditSigned($right);
    }

    public static function formatCredit($value): string
    {
        $text = number_format(self::creditSigned($value), self::CREDIT_SCALE, '.', '');
        $text = rtrim(rtrim($text, '0'), '.');
        if ($text === '' || $text === '-') {
            return '0';
        }
        return $text;
    }

    public static function ensureSettings(int $aid): array
    {
        DigitalAvatarInstaller::ensureCreditCnyRatioColumn();
        $row = Db::name('digitalavatar_setting')->where('aid', $aid)->find();
        if ($row) {
            return $row;
        }

        $now = time();
        try {
            Db::name('digitalavatar_setting')->insert([
                'aid' => $aid,
                'product_title' => 'AI数字人工作台',
                'enabled' => 1,
                'charge_mode' => 2,
                'initial_credit' => 100,
                'max_concurrent_tasks' => 3,
                'portal_path' => '/portal/digitalavatar/',
                'notice_text' => '',
                'oem_logo' => '',
                'oem_login_bg' => '',
                'login_style' => 'niwa',
                'oem_color' => '',
                'oem_subtitle' => '',
                'oem_contact_wechat' => '',
                'oem_contact_tel' => '',
                'agent_enabled' => 0,
                'agent_can_grant' => 1,
                'agent_can_redeem' => 1,
                'creative_video_enabled' => 1,
                'avatar_enabled' => 1,
                'audio_enabled' => 1,
                'image_enabled' => 1,
                'image_agent_enabled' => 0,
                'image_agent_llm_enabled' => 0,
                'image_agent_llm_base_url' => '',
                'image_agent_llm_api_key' => '',
                'image_agent_llm_model' => '',
                'image_agent_llm_timeout' => 45,
                'image_agent_prefer_draft' => 1,
                'image_agent_draft_model_id' => 0,
                'image_agent_draft_size' => '1024x1024',
                'image_agent_draft_quality' => 'low',
                'image_agent_hd_model_id' => 0,
                'image_agent_hd_size' => '1024x1024',
                'image_agent_hd_quality' => 'high',
                'create_time' => $now,
                'update_time' => $now,
            ]);
        } catch (\Throwable $e) {
            if (stripos($e->getMessage(), 'duplicate') === false) {
                throw $e;
            }
        }

        return Db::name('digitalavatar_setting')->where('aid', $aid)->find() ?: [];
    }

    public static function saveSettings(int $aid, array $info): array
    {
        self::ensureSettings($aid);
        $allowed = [
            'product_title',
            'enabled',
            'charge_mode',
            'initial_credit',
            'credit_per_yuan',
            'max_concurrent_tasks',
            'portal_path',
            'portal_domain',
            'notice_text',
            'oem_logo',
            'oem_login_bg',
            'login_style',
            'oem_color',
            'oem_subtitle',
            'oem_contact_wechat',
            'oem_contact_tel',
            'agent_enabled',
            'agent_can_grant',
            'agent_can_redeem',
            'creative_video_enabled',
            'avatar_enabled',
            'audio_enabled',
            'image_enabled',
            'image_agent_enabled',
            'image_agent_llm_enabled',
            'image_agent_llm_base_url',
            'image_agent_llm_api_key',
            'image_agent_llm_model',
            'image_agent_llm_timeout',
            'image_agent_prefer_draft',
            'image_agent_draft_model_id',
            'image_agent_draft_size',
            'image_agent_draft_quality',
            'image_agent_hd_model_id',
            'image_agent_hd_size',
            'image_agent_hd_quality',
        ];
        $data = ['update_time' => time()];
        foreach ($allowed as $field) {
            if (!array_key_exists($field, $info)) {
                continue;
            }
            $value = $info[$field];
            if (in_array($field, ['enabled', 'charge_mode', 'agent_enabled', 'agent_can_grant', 'agent_can_redeem', 'creative_video_enabled', 'avatar_enabled', 'audio_enabled', 'image_enabled', 'image_agent_enabled', 'image_agent_llm_enabled', 'image_agent_prefer_draft'], true)) {
                $value = intval($value) === 1 ? 1 : 0;
                if ($field === 'charge_mode') {
                    $value = intval($info[$field]) === 1 ? 1 : 2;
                }
            } elseif ($field === 'initial_credit') {
                $value = self::creditStore($value);
            } elseif ($field === 'credit_per_yuan') {
                $value = self::creditStore(max(0, floatval($value)));
            } elseif ($field === 'max_concurrent_tasks') {
                $value = max(1, min(100, intval($value)));
            } elseif ($field === 'image_agent_llm_timeout') {
                $value = max(15, min(180, intval($value)));
            } elseif (in_array($field, ['image_agent_draft_model_id', 'image_agent_hd_model_id'], true)) {
                $value = max(0, intval($value));
            } elseif (in_array($field, ['image_agent_draft_size', 'image_agent_hd_size'], true)) {
                $value = self::normalizeImageSize(strval($value), $field === 'image_agent_hd_size' ? '2880x2880' : '1024x1024');
            } elseif (in_array($field, ['image_agent_draft_quality', 'image_agent_hd_quality'], true)) {
                $value = self::normalizeImageQuality(strval($value), $field === 'image_agent_hd_quality' ? 'high' : 'low');
            } elseif ($field === 'image_agent_llm_api_key') {
                $raw = trim(strval($value));
                if ($raw === '' || strpos($raw, '*') !== false) {
                    continue;
                }
                $value = mb_substr($raw, 0, 255);
            } elseif ($field === 'image_agent_llm_base_url') {
                $value = mb_substr(trim(strval($value)), 0, 255);
            } elseif ($field === 'image_agent_llm_model') {
                $value = mb_substr(trim(strval($value)), 0, 100);
            } elseif ($field === 'product_title') {
                $value = mb_substr(trim(strval($value)), 0, 120);
            } elseif ($field === 'portal_path') {
                $value = trim(strval($value)) ?: '/portal/digitalavatar/';
            } elseif ($field === 'portal_domain') {
                DigitalAvatarInstaller::ensurePortalDomainColumn();
                $value = DigitalAvatarPortalDomain::normalizeHost(strval($value), true);
                DigitalAvatarPortalDomain::assertAvailable($aid, $value);
            } elseif ($field === 'login_style') {
                $value = strtolower(trim(strval($value)));
                $value = $value === 'classic' ? 'classic' : 'niwa';
            } elseif ($field === 'oem_color') {
                $value = trim(strval($value));
                if ($value !== '' && !preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value)) {
                    throw new \RuntimeException('品牌色请填写 #RRGGBB');
                }
            } elseif (in_array($field, ['oem_subtitle', 'oem_contact_wechat', 'oem_contact_tel'], true)) {
                $value = mb_substr(trim(strval($value)), 0, 80);
            } else {
                $value = mb_substr(trim(strval($value)), 0, 500);
            }
            $data[$field] = $value;
        }

        if (intval($info['clear_image_agent_llm_api_key'] ?? 0) === 1) {
            $data['image_agent_llm_api_key'] = '';
        }

        Db::name('digitalavatar_setting')->where('aid', $aid)->update($data);
        return self::formatSettings(self::ensureSettings($aid));
    }

    private static function normalizeImageSize(string $value, string $fallback = '1024x1024'): string
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return $fallback;
        }
        if (preg_match('/^(\d{3,5})x(\d{3,5})$/', $value, $match)) {
            return intval($match[1]) . 'x' . intval($match[2]);
        }
        if (preg_match('/^(\d{1,2}):(\d{1,2})$/', $value)) {
            return $value;
        }
        return $fallback;
    }

    private static function normalizeImageQuality(string $value, string $fallback = 'high'): string
    {
        $value = strtolower(trim($value));
        return in_array($value, ['low', 'medium', 'high'], true) ? $value : $fallback;
    }

    public static function formatSettings(array $row): array
    {
        if ($row === []) {
            return $row;
        }
        $row['image_agent_llm_has_key'] = trim(strval($row['image_agent_llm_api_key'] ?? '')) !== '' ? 1 : 0;
        $row['image_agent_llm_api_key'] = '';
        unset($row['clear_image_agent_llm_api_key']);
        $row['login_style'] = strtolower(trim(strval($row['login_style'] ?? ''))) === 'classic' ? 'classic' : 'niwa';
        if (!array_key_exists('credit_per_yuan', $row) || $row['credit_per_yuan'] === '' || $row['credit_per_yuan'] === null) {
            $row['credit_per_yuan'] = 1000;
        } else {
            $row['credit_per_yuan'] = self::creditAmount($row['credit_per_yuan']);
        }
        $row['portal_domain'] = DigitalAvatarPortalDomain::normalizeHost(strval($row['portal_domain'] ?? ''), false);
        unset($row['sidebar_json']);
        $aid = intval($row['aid'] ?? 0);
        if ($aid > 0) {
            $draft = DigitalAvatarImageAgentService::imageStageProfile($aid, 'draft', $row);
            $hd = DigitalAvatarImageAgentService::imageStageProfile($aid, 'final', $row);
            $row['image_agent_prefer_draft'] = intval($row['image_agent_prefer_draft'] ?? 1) === 1 ? 1 : 0;
            $row['image_agent_draft_model_id'] = intval($row['image_agent_draft_model_id'] ?? 0);
            $row['image_agent_hd_model_id'] = intval($row['image_agent_hd_model_id'] ?? 0);
            $row['image_agent_draft_ready'] = intval($draft['ready'] ?? 0);
            $row['image_agent_hd_ready'] = intval($hd['ready'] ?? 0);
            $row['image_agent_draft_model_title'] = strval($draft['title'] ?? '');
            $row['image_agent_hd_model_title'] = strval($hd['title'] ?? '');
        }
        return $row;
    }

    public static function ensureAccount(int $aid, int $bid, int $memberId): array
    {
        if ($aid <= 0 || $memberId <= 0) {
            return [];
        }

        $bid = max(0, $bid);
        $row = Db::name('digitalavatar_member_credit')
            ->where('aid', $aid)
            ->where('bid', $bid)
            ->where('member_id', $memberId)
            ->find();
        if ($row) {
            return $row;
        }

        $initialCredit = self::creditAmount(self::ensureSettings($aid)['initial_credit'] ?? 0);
        $now = time();
        try {
            Db::name('digitalavatar_member_credit')->insert([
                'aid' => $aid,
                'bid' => $bid,
                'member_id' => $memberId,
                'credit_total' => self::creditStore($initialCredit),
                'credit_used' => self::creditStore(0),
                'credit_balance' => self::creditStore($initialCredit),
                'credit_frozen' => self::creditStore(0),
                'package_id' => 0,
                'expire_time' => 0,
                'status' => 1,
                'last_recharge_time' => $initialCredit > 0 ? $now : 0,
                'last_used_time' => 0,
                'max_concurrent_tasks' => 0,
                'create_time' => $now,
                'update_time' => $now,
            ]);
            if ($initialCredit > 0) {
                self::writeLog($aid, $bid, $memberId, 'grant', $initialCredit, 0, $initialCredit, 'initial', 0, '注册赠送创作点', 0);
                DigitalAvatarLotService::grantCreditLot($aid, $bid, $memberId, $initialCredit, 0, 'initial', 0, '注册赠送创作点');
            }
        } catch (\Throwable $e) {
            if (stripos($e->getMessage(), 'Unknown column') !== false && stripos($e->getMessage(), 'max_concurrent_tasks') !== false) {
                try {
                    Db::name('digitalavatar_member_credit')->insert([
                        'aid' => $aid,
                        'bid' => $bid,
                        'member_id' => $memberId,
                        'credit_total' => self::creditStore($initialCredit),
                        'credit_used' => self::creditStore(0),
                        'credit_balance' => self::creditStore($initialCredit),
                        'credit_frozen' => self::creditStore(0),
                        'package_id' => 0,
                        'expire_time' => 0,
                        'status' => 1,
                        'last_recharge_time' => $initialCredit > 0 ? $now : 0,
                        'last_used_time' => 0,
                        'create_time' => $now,
                        'update_time' => $now,
                    ]);
                    if ($initialCredit > 0) {
                        self::writeLog($aid, $bid, $memberId, 'grant', $initialCredit, 0, $initialCredit, 'initial', 0, '注册赠送创作点', 0);
                        DigitalAvatarLotService::grantCreditLot($aid, $bid, $memberId, $initialCredit, 0, 'initial', 0, '注册赠送创作点');
                    }
                } catch (\Throwable $retryException) {
                    if (stripos($retryException->getMessage(), 'duplicate') === false) {
                        throw $retryException;
                    }
                }
            } elseif (stripos($e->getMessage(), 'duplicate') === false) {
                throw $e;
            }
        }

        return Db::name('digitalavatar_member_credit')
            ->where('aid', $aid)
            ->where('bid', $bid)
            ->where('member_id', $memberId)
            ->find() ?: [];
    }

    public static function getSummary(int $aid, int $bid, int $memberId): array
    {
        self::expireAccountLots($aid, $bid, $memberId);
        $settings = self::ensureSettings($aid);
        $row = self::ensureAccount($aid, $bid, $memberId);
        $balance = self::creditAmount($row['credit_balance'] ?? 0);
        $frozen = self::creditAmount($row['credit_frozen'] ?? 0);
        $available = self::creditAmount(self::creditSub($balance, $frozen));
        $expireTime = intval($row['expire_time'] ?? 0);
        $systemConcurrent = self::systemConcurrentLimit($aid);
        $memberConcurrent = max(0, intval($row['max_concurrent_tasks'] ?? 0));
        $packageConcurrent = self::activePackageConcurrent($aid, $bid, $memberId);
        $baseConcurrent = $memberConcurrent > 0 ? min(100, $memberConcurrent) : $systemConcurrent;
        $effectiveConcurrent = max($baseConcurrent, $packageConcurrent);
        $runningTasks = 0;
        $queuedTasks = 0;
        if ($memberId > 0) {
            $taskQuery = Db::name('digitalavatar_task')
                ->where('aid', $aid)
                ->where('member_id', $memberId)
                ->where('deleted_time', 0);
            if ($bid > 0) {
                $taskQuery->where('bid', $bid);
            }
            $runningTasks = intval((clone $taskQuery)->where('status', 1)->count());
            $queuedTasks = intval((clone $taskQuery)->whereIn('status', [0, 5])->count());
        }
        $creditTotal = self::creditAmount($row['credit_total'] ?? 0);
        $ratio = self::creditAmount($settings['credit_per_yuan'] ?? 1000);
        $totalCny = $ratio > 0 ? round($creditTotal / $ratio, 2) : 0.0;
        $expiring7d = 0.0;
        try {
            $expiring7d = DigitalAvatarLotService::expiringCreditAmount($aid, $bid, $memberId, 7);
        } catch (\Throwable $e) {
            $expiring7d = 0.0;
        }

        return [
            'credit_total' => $creditTotal,
            'credit_total_cny' => $totalCny,
            'credit_total_cny_text' => '约' . number_format($totalCny, 2, '.', '') . '元',
            'credit_used' => self::creditAmount($row['credit_used'] ?? 0),
            'credit_balance' => $balance,
            'credit_frozen' => $frozen,
            'credit_frozen_text' => number_format($frozen, 2, '.', ''),
            'credit_available' => $available,
            'expiring_7d' => $expiring7d,
            'expiring_7d_text' => number_format($expiring7d, 2, '.', ''),
            'credit_per_yuan' => $ratio,
            'charge_mode' => intval($settings['charge_mode'] ?? 2),
            'enabled' => intval($settings['enabled'] ?? 1),
            'expire_time' => $expireTime,
            'expire_time_text' => $expireTime > 0 ? date('Y-m-d H:i:s', $expireTime) : '长期有效',
            'has_access' => intval($row['status'] ?? 0) === 1 && ($expireTime <= 0 || $expireTime >= time()) ? 1 : 0,
            'system_max_concurrent_tasks' => $systemConcurrent,
            'member_max_concurrent_tasks' => $memberConcurrent,
            'package_max_concurrent_tasks' => $packageConcurrent,
            'max_concurrent_tasks' => $effectiveConcurrent,
            'running_tasks' => $runningTasks,
            'queued_tasks' => $queuedTasks,
            'model_quotas' => DigitalAvatarLotService::memberQuotaSummary($aid, $bid, $memberId),
            'package_remain' => DigitalAvatarLotService::memberPackageRemainDetail($aid, $bid, $memberId),
        ];
    }

    public static function expireAccountLots(int $aid, int $bid, int $memberId): void
    {
        try {
            DigitalAvatarLotService::expireLots($aid, $bid, $memberId);
        } catch (\Throwable $e) {
        }
    }

    public static function writeExpireLog(
        int $aid,
        int $bid,
        int $memberId,
        $change,
        $before,
        $after,
        int $sourceId,
        string $remark
    ): void {
        self::writeLog($aid, $bid, $memberId, 'expire', $change, $before, $after, 'package_expire', $sourceId, $remark, 0, [
            'source_type' => 'package_expire',
            'frozen_amount' => 0,
        ]);
    }

    public static function systemConcurrentLimit(int $aid): int
    {
        return max(1, min(100, intval(self::ensureSettings($aid)['max_concurrent_tasks'] ?? 3)));
    }

    public static function memberConcurrentLimit(int $aid, int $bid, int $memberId): int
    {
        $system = self::systemConcurrentLimit($aid);
        if ($memberId <= 0) {
            return $system;
        }
        $row = self::ensureAccount($aid, $bid, $memberId);
        $custom = max(0, intval($row['max_concurrent_tasks'] ?? 0));
        $base = $custom > 0 ? max(1, min(100, $custom)) : $system;
        $package = self::activePackageConcurrent($aid, $bid, $memberId);
        return max(1, min(100, max($base, $package)));
    }

    public static function activePackageConcurrent(int $aid, int $bid, int $memberId): int
    {
        if ($aid <= 0 || $memberId <= 0) {
            return 0;
        }
        try {
            DigitalAvatarInstaller::ensurePackageConcurrentColumns();
            DigitalAvatarLotService::expireLots($aid, $bid, $memberId);
            $now = time();
            $query = Db::name('digitalavatar_credit_lot')
                ->where('aid', $aid)
                ->where('member_id', $memberId)
                ->where('lot_type', 'credit')
                ->where('source_type', 'package')
                ->where('status', 1)
                ->whereRaw('(expire_time = 0 OR expire_time >= ' . $now . ')');
            if ($bid > 0) {
                $query->where('bid', $bid);
            }
            $lots = $query->field('source_id,package_id')->select()->toArray();
            $packageIds = [];
            foreach ($lots as $lot) {
                $packageId = intval($lot['package_id'] ?? 0);
                if ($packageId <= 0) {
                    $packageId = self::packageIdFromLotSource($aid, $memberId, intval($lot['source_id'] ?? 0));
                }
                if ($packageId > 0) {
                    $packageIds[$packageId] = $packageId;
                }
            }
            if ($packageIds === []) {
                return 0;
            }
            $maxConcurrent = Db::name('digitalavatar_package')
                ->where('aid', $aid)
                ->whereIn('id', array_values($packageIds))
                ->where('package_type', 'credit')
                ->max('max_concurrent_tasks');
            return max(0, min(100, intval($maxConcurrent)));
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private static function packageIdFromLotSource(int $aid, int $memberId, int $sourceId): int
    {
        if ($sourceId <= 0) {
            return 0;
        }
        $orderPackageId = intval(Db::name('digitalavatar_package_order')
            ->where('aid', $aid)
            ->where('member_id', $memberId)
            ->where('id', $sourceId)
            ->where('status', 1)
            ->value('package_id'));
        if ($orderPackageId > 0 && self::isCreditPackage($aid, $orderPackageId)) {
            return $orderPackageId;
        }
        $redeemPackageId = intval(Db::name('digitalavatar_redeem_code')
            ->where('aid', $aid)
            ->where('id', $sourceId)
            ->value('package_id'));
        if ($redeemPackageId > 0 && self::isCreditPackage($aid, $redeemPackageId)) {
            return $redeemPackageId;
        }
        return 0;
    }

    private static function isCreditPackage(int $aid, int $packageId): bool
    {
        if ($packageId <= 0) {
            return false;
        }
        $type = strval(Db::name('digitalavatar_package')->where('aid', $aid)->where('id', $packageId)->value('package_type'));
        return $type !== 'boost';
    }

    public static function saveMemberConcurrent(int $aid, int $bid, int $memberId, int $maxConcurrent): array
    {
        if ($memberId <= 0) {
            throw new \RuntimeException('请选择会员');
        }
        $maxConcurrent = max(0, min(100, $maxConcurrent));
        self::ensureAccount($aid, $bid, $memberId);
        try {
            Db::name('digitalavatar_member_credit')
                ->where('aid', $aid)
                ->where('bid', max(0, $bid))
                ->where('member_id', $memberId)
                ->update([
                    'max_concurrent_tasks' => $maxConcurrent,
                    'update_time' => time(),
                ]);
        } catch (\Throwable $e) {
            if (stripos($e->getMessage(), 'Unknown column') === false || stripos($e->getMessage(), 'max_concurrent_tasks') === false) {
                throw $e;
            }
            DigitalAvatarInstaller::ensureInstalled(true);
            Db::name('digitalavatar_member_credit')
                ->where('aid', $aid)
                ->where('bid', max(0, $bid))
                ->where('member_id', $memberId)
                ->update([
                    'max_concurrent_tasks' => $maxConcurrent,
                    'update_time' => time(),
                ]);
        }
        return self::getSummary($aid, $bid, $memberId);
    }

    public static function lockMemberAccount(int $aid, int $bid, int $memberId): array
    {
        self::ensureAccount($aid, $bid, $memberId);
        return self::lockAccount($aid, $bid, $memberId);
    }

    public static function listLogs(int $aid, int $bid, int $memberId, int $page = 1, int $limit = 20, array $filters = []): array
    {
        $query = self::creditLogQuery($aid, $bid, $memberId, $filters);
        $total = intval((clone $query)->count());
        $list = $query->page(max(1, $page), max(1, min(200, $limit)))
            ->order('l.id desc')
            ->select()
            ->toArray();
        $formatted = [];
        foreach ($list as $row) {
            $formatted[] = self::formatLog($row);
        }
        return ['list' => $formatted, 'total' => $total, 'page' => $page, 'limit' => $limit];
    }

    public static function exportMemberLogs(int $aid, int $bid, int $memberId, array $filters = []): array
    {
        $query = self::creditLogQuery($aid, $bid, $memberId, $filters);
        $rows = $query->order('l.id desc')->limit(2000)->select()->toArray();
        $list = [];
        foreach ($rows as $row) {
            $list[] = self::formatLog($row);
        }
        return $list;
    }

    public static function buildCreditLogExcelBody(array $list): string
    {
        $headers = ['时间', '任务类型', '任务ID', '算力机ID', '模型名称', '模型版本', '计费秒数', '计费文字', '变动类型', '额度', '预扣/解冻', '变动后余额', '说明'];
        $html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="UTF-8"></head><body><table border="1"><tr>';
        foreach ($headers as $header) {
            $html .= '<th>' . htmlspecialchars($header, ENT_QUOTES, 'UTF-8') . '</th>';
        }
        $html .= '</tr>';
        foreach ($list as $item) {
            $cells = [
                strval($item['create_time_text'] ?? ''),
                strval($item['task_type_text'] ?? ''),
                strval($item['task_id'] ?? ''),
                strval($item['api_task_id'] ?? ''),
                strval($item['platform_code'] ?? ''),
                strval($item['api_model'] ?? ''),
                strval($item['billing_seconds_text'] ?? ''),
                strval($item['billing_chars_text'] ?? ''),
                strval($item['change_type_text'] ?? ''),
                strval($item['credit_change'] ?? ''),
                strval($item['frozen_amount_text'] ?? ''),
                strval($item['change_after'] ?? ''),
                strval($item['detail_text'] ?? ''),
            ];
            $html .= '<tr>';
            foreach ($cells as $cell) {
                $html .= '<td>' . htmlspecialchars($cell, ENT_QUOTES, 'UTF-8') . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</table></body></html>';
        return $html;
    }

    public static function grant(int $aid, int $bid, int $memberId, $delta, string $remark, int $operatorId = 0, array $context = []): array
    {
        $delta = self::creditSigned($delta);
        if (self::creditEq($delta, 0)) {
            throw new \RuntimeException('调整额度不能为 0');
        }
        $member = Db::name('member')->where('aid', $aid)->where('id', $memberId)->find();
        if (!$member) {
            throw new \RuntimeException('会员不存在');
        }

        self::ensureAccount($aid, $bid, $memberId);
        Db::transaction(function () use ($aid, $bid, $memberId, $delta, $remark, $operatorId, $context) {
            $row = self::lockAccount($aid, $bid, $memberId);
            $before = self::creditSigned($row['credit_balance'] ?? 0);
            $after = self::creditAdd($before, $delta);
            if (self::creditLt($after, 0)) {
                throw new \RuntimeException('扣减后额度不能小于 0');
            }
            $now = time();
            Db::name('digitalavatar_member_credit')->where('id', intval($row['id']))->update([
                'credit_total' => self::creditStore(self::creditAdd($row['credit_total'] ?? 0, self::creditAmount($delta))),
                'credit_used' => self::creditStore(self::creditAdd($row['credit_used'] ?? 0, self::creditLt($delta, 0) ? abs($delta) : 0)),
                'credit_balance' => self::creditStore($after),
                'last_recharge_time' => self::creditGt($delta, 0) ? $now : intval($row['last_recharge_time'] ?? 0),
                'last_used_time' => self::creditLt($delta, 0) ? $now : intval($row['last_used_time'] ?? 0),
                'update_time' => $now,
            ]);
            $sourceType = trim(strval($context['source_type'] ?? 'admin'));
            $sourceId = intval($context['source_id'] ?? $operatorId);
            $changeType = self::creditGt($delta, 0) ? 'grant' : strval($context['change_type'] ?? 'manual_deduct');
            $context['task_type'] = strval($context['task_type'] ?? '');
            $context['frozen_amount'] = self::creditAmount($context['frozen_amount'] ?? 0);
            self::writeLog($aid, $bid, $memberId, $changeType, $delta, $before, $after, $sourceType, $sourceId, $remark, $operatorId, $context);
            if (self::creditGt($delta, 0)) {
                DigitalAvatarLotService::grantCreditLot(
                    $aid,
                    $bid,
                    $memberId,
                    $delta,
                    0,
                    $sourceType !== '' ? $sourceType : 'admin',
                    $sourceId,
                    $remark
                );
            } elseif (self::creditLt($delta, 0)) {
                DigitalAvatarLotService::confirmCreditLots($aid, $bid, $memberId, abs($delta));
            }
        });

        return self::getSummary($aid, $bid, $memberId);
    }

    public static function grantPackage(
        int $aid,
        int $bid,
        int $memberId,
        $amount,
        int $packageId,
        int $validityDays,
        int $sourceId,
        string $remark
    ): array {
        $amount = self::creditAmount($amount);
        if ($memberId <= 0) {
            throw new \RuntimeException('会员账号无效');
        }

        $member = Db::name('member')->where('aid', $aid)->where('id', $memberId)->find();
        if (!$member) {
            throw new \RuntimeException('会员不存在');
        }

        $expireTime = 0;
        if ($validityDays > 0) {
            $expireTime = strtotime('+' . $validityDays . ' day') ?: 0;
        }

        self::ensureAccount($aid, $bid, $memberId);
        if ($amount <= 0) {
            return self::getSummary($aid, $bid, $memberId);
        }
        DigitalAvatarInstaller::ensurePackageConcurrentColumns();
        Db::transaction(function () use ($aid, $bid, $memberId, $amount, $packageId, $expireTime, $sourceId, $remark) {
            $row = self::lockAccount($aid, $bid, $memberId);
            $before = self::creditSigned($row['credit_balance'] ?? 0);
            $now = time();

            Db::name('digitalavatar_member_credit')->where('id', intval($row['id']))->update([
                'credit_total' => self::creditStore(self::creditAdd($row['credit_total'] ?? 0, $amount)),
                'credit_balance' => self::creditStore(self::creditAdd($before, $amount)),
                'package_id' => $packageId,
                'expire_time' => $expireTime > 0 ? $expireTime : intval($row['expire_time'] ?? 0),
                'status' => 1,
                'last_recharge_time' => $now,
                'update_time' => $now,
            ]);
            $lotId = DigitalAvatarLotService::grantCreditLot($aid, $bid, $memberId, $amount, $expireTime, 'package', $sourceId, $remark);
            if ($lotId > 0 && $packageId > 0) {
                try {
                    Db::name('digitalavatar_credit_lot')->where('id', $lotId)->update(['package_id' => $packageId]);
                } catch (\Throwable $e) {
                    if (stripos($e->getMessage(), 'Unknown column') !== false) {
                        DigitalAvatarInstaller::ensurePackageConcurrentColumns();
                        Db::name('digitalavatar_credit_lot')->where('id', $lotId)->update(['package_id' => $packageId]);
                    }
                }
            }
            self::writeLog(
                $aid,
                $bid,
                $memberId,
                'grant',
                $amount,
                $before,
                self::creditAdd($before, $amount),
                'package',
                $sourceId,
                $remark,
                0,
                [
                    'task_type' => '',
                    'source_type' => 'package',
                    'frozen_amount' => 0,
                ]
            );
        });

        return self::getSummary($aid, $bid, $memberId);
    }

    public static function grantCheckin(
        int $aid,
        int $bid,
        int $memberId,
        $amount,
        int $expireTime,
        int $sourceId,
        string $remark,
        array $quotas = []
    ): array {
        if ($memberId <= 0) {
            throw new \RuntimeException('会员账号无效');
        }
        self::ensureAccount($aid, $bid, $memberId);
        $amount = self::creditAmount($amount);
        $quotas = DigitalAvatarLotService::normalizeQuotaMap($quotas);
        $hasQuota = false;
        foreach ($quotas as $qty) {
            if (intval($qty) > 0) {
                $hasQuota = true;
                break;
            }
        }
        if ($amount <= 0 && !$hasQuota) {
            return self::getSummary($aid, $bid, $memberId);
        }
        Db::transaction(function () use ($aid, $bid, $memberId, $amount, $expireTime, $sourceId, $remark, $quotas, $hasQuota) {
            $row = self::lockAccount($aid, $bid, $memberId);
            $before = self::creditSigned($row['credit_balance'] ?? 0);
            $now = time();
            if ($amount > 0) {
                Db::name('digitalavatar_member_credit')->where('id', intval($row['id']))->update([
                    'credit_total' => self::creditStore(self::creditAdd($row['credit_total'] ?? 0, $amount)),
                    'credit_balance' => self::creditStore(self::creditAdd($before, $amount)),
                    'status' => 1,
                    'last_recharge_time' => $now,
                    'update_time' => $now,
                ]);
                DigitalAvatarLotService::grantCreditLot($aid, $bid, $memberId, $amount, $expireTime, 'checkin', $sourceId, $remark);
                self::writeLog(
                    $aid,
                    $bid,
                    $memberId,
                    'grant',
                    $amount,
                    $before,
                    self::creditAdd($before, $amount),
                    'checkin',
                    $sourceId,
                    $remark,
                    0,
                    [
                        'task_type' => 'checkin',
                        'source_type' => 'checkin',
                        'frozen_amount' => 0,
                    ]
                );
            }
            if ($hasQuota) {
                DigitalAvatarLotService::grantQuotaLots($aid, $bid, $memberId, $quotas, $expireTime, 'checkin', $sourceId, $remark);
            }
        });
        return self::getSummary($aid, $bid, $memberId);
    }

    public static function grantInvite(
        int $aid,
        int $bid,
        int $memberId,
        $amount,
        int $expireTime,
        int $sourceId,
        string $remark,
        array $quotas = []
    ): array {
        if ($memberId <= 0) {
            throw new \RuntimeException('会员账号无效');
        }
        self::ensureAccount($aid, $bid, $memberId);
        $amount = self::creditAmount($amount);
        $quotas = DigitalAvatarLotService::normalizeQuotaMap($quotas);
        $hasQuota = false;
        foreach ($quotas as $qty) {
            if (intval($qty) > 0) {
                $hasQuota = true;
                break;
            }
        }
        if ($amount <= 0 && !$hasQuota) {
            return self::getSummary($aid, $bid, $memberId);
        }
        Db::transaction(function () use ($aid, $bid, $memberId, $amount, $expireTime, $sourceId, $remark, $quotas, $hasQuota) {
            $row = self::lockAccount($aid, $bid, $memberId);
            $before = self::creditSigned($row['credit_balance'] ?? 0);
            $now = time();
            if ($amount > 0) {
                Db::name('digitalavatar_member_credit')->where('id', intval($row['id']))->update([
                    'credit_total' => self::creditStore(self::creditAdd($row['credit_total'] ?? 0, $amount)),
                    'credit_balance' => self::creditStore(self::creditAdd($before, $amount)),
                    'status' => 1,
                    'last_recharge_time' => $now,
                    'update_time' => $now,
                ]);
                DigitalAvatarLotService::grantCreditLot($aid, $bid, $memberId, $amount, $expireTime, 'invite', $sourceId, $remark);
                self::writeLog(
                    $aid,
                    $bid,
                    $memberId,
                    'grant',
                    $amount,
                    $before,
                    self::creditAdd($before, $amount),
                    'invite',
                    $sourceId,
                    $remark,
                    0,
                    [
                        'task_type' => 'invite',
                        'source_type' => 'invite',
                        'frozen_amount' => 0,
                    ]
                );
            }
            if ($hasQuota) {
                DigitalAvatarLotService::grantQuotaLots($aid, $bid, $memberId, $quotas, $expireTime, 'invite', $sourceId, $remark);
            }
        });
        return self::getSummary($aid, $bid, $memberId);
    }

    public static function computeCost(int $aid, string $taskType, string $modelCode, array $input): array
    {
        $apiModel = self::extractApiModel($input);
        if ($apiModel === '') {
            $modelRow = Db::name('digitalavatar_model')
                ->where('aid', $aid)
                ->where('task_type', $taskType)
                ->where('model_code', $modelCode)
                ->find();
            $config = [];
            if ($modelRow) {
                $decoded = json_decode(strval($modelRow['config_json'] ?? ''), true);
                $config = is_array($decoded) ? $decoded : [];
            }
            $spec = DigitalAvatarNiwaCatalog::resolve($modelCode, $config, array_merge($input, ['task_type' => $taskType]));
            $apiModel = trim(strval($spec['model'] ?? ''));
        }

        $rules = Db::name('digitalavatar_model_charge_rule')
            ->where('aid', $aid)
            ->where('task_type', $taskType)
            ->where('status', 1)
            ->where(function ($query) use ($modelCode) {
                $query->where('model_code', $modelCode)->whereOr('model_code', '*');
            })
            ->select()
            ->toArray();

        $rule = null;
        $matchedExact = false;
        if ($apiModel !== '') {
            foreach ($rules as $row) {
                if (strval($row['model_code'] ?? '') === $modelCode && strcasecmp(trim(strval($row['api_model'] ?? '')), $apiModel) === 0) {
                    $rule = $row;
                    $matchedExact = true;
                    break;
                }
            }
        }
        if (!$rule) {
            foreach ($rules as $row) {
                if (strval($row['model_code'] ?? '') !== $modelCode) {
                    continue;
                }
                $rowMatch = strval($row['match_mode'] ?? 'platform_code') === 'model' ? 'model' : 'platform_code';
                if ($rowMatch === 'model' && $apiModel !== '') {
                    continue;
                }
                if (trim(strval($row['api_model'] ?? '')) === '') {
                    $rule = $row;
                    break;
                }
            }
        }
        if (!$rule && !$matchedExact) {
            foreach ($rules as $row) {
                if (strval($row['model_code'] ?? '') === '*') {
                    $rule = $row;
                    break;
                }
            }
        }

        if (!$rule) {
            return [
                'credit_cost' => 0,
                'quota_units' => 1,
                'unit_count' => 1,
                'billing_mode' => 'fixed',
                'rule_id' => 0,
                'api_model' => $apiModel,
                'match_mode' => 'platform_code',
                'input' => $input,
            ];
        }

        $mode = self::normalizeBillingMode(strval($rule['billing_mode'] ?? 'fixed'));
        if (in_array($mode, ['seconds', 'minutes'], true) && floatval($input['audio_seconds'] ?? ($input['audio_duration'] ?? 0)) <= 0) {
            $probed = self::probeRemoteAudioSeconds(trim(strval($input['audio_url'] ?? ($input['existing_audio_url'] ?? ($input['audio_oss_url'] ?? '')))));
            if ($probed > 0) {
                $input['audio_seconds'] = $probed;
                $input['audio_duration'] = $probed;
            }
        }
        $unitCount = max(1, intval($rule['unit_count'] ?? 1));
        $unitCredit = self::creditAmount($rule['unit_credit'] ?? 0);
        $baseCredit = self::creditAmount($rule['base_credit'] ?? 0);
        $quantity = self::quantityForMode($mode, $input);
        $units = $mode === 'fixed'
            ? 1
            : ($quantity > 0 ? (int)ceil($quantity / $unitCount) : 0);
        if ($units <= 0 && $taskType === 'avatar_video' && in_array($mode, ['seconds', 'minutes'], true)) {
            $units = 1;
        }
        if ($units <= 0 && $taskType === 'image' && $mode === 'count') {
            $units = 1;
        }
        $cost = self::creditAdd($baseCredit, self::creditMul($unitCredit, $units));

        return [
            'credit_cost' => self::creditAmount($cost),
            'quota_units' => max(1, $units),
            'unit_count' => $unitCount,
            'unit_credit' => $unitCredit,
            'base_credit' => $baseCredit,
            'quantity' => $quantity,
            'billing_mode' => $mode,
            'rule_id' => intval($rule['id'] ?? 0),
            'rule_text' => self::formatRuleText($rule),
            'api_model' => $apiModel,
            'match_mode' => strval($rule['match_mode'] ?? 'platform_code') === 'model' ? 'model' : 'platform_code',
            'platform_code' => strval($rule['platform_code'] ?? ''),
            'input' => $input,
        ];
    }

    public static function extractApiModel(array $input): string
    {
        foreach (['api_model', 'xpro_model', 'max_avatar_model', 'image_model', 'model', 'target_model'] as $key) {
            $value = trim(strval($input[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }
        foreach (['minimax_model', 'vpro_model'] as $key) {
            $value = trim(strval($input[$key] ?? ''));
            if ($value === '' || preg_match('/speech-|minimax/i', $value) || $value === '1') {
                continue;
            }
            return $value;
        }
        return '';
    }

    public static function bindSelectedApiModel(int $aid, string $taskType, string $modelCode, array $input, array $config = []): array
    {
        $modelCode = trim($modelCode);
        $apiModel = self::extractApiModel($input);
        $rules = [];
        if ($aid > 0 && $taskType !== '' && $modelCode !== '') {
            try {
                $rules = Db::name('digitalavatar_model_charge_rule')
                    ->where('aid', $aid)
                    ->where('task_type', $taskType)
                    ->where('model_code', $modelCode)
                    ->where('status', 1)
                    ->select()
                    ->toArray();
            } catch (\Throwable $e) {
                $rules = [];
            }
        }
        $matchMode = 'platform_code';
        $versionModels = [];
        foreach ($rules as $rule) {
            if (strval($rule['match_mode'] ?? '') === 'model') {
                $matchMode = 'model';
            }
            $code = trim(strval($rule['api_model'] ?? ''));
            if ($code !== '') {
                $versionModels[] = $code;
            }
        }
        if ($matchMode !== 'model') {
            return $input;
        }
        if ($apiModel === '' && $versionModels !== []) {
            $apiModel = $versionModels[0];
        }
        if ($apiModel === '') {
            $spec = DigitalAvatarNiwaCatalog::resolve($modelCode, $config, array_merge($input, ['task_type' => $taskType]));
            $apiModel = trim(strval($spec['model'] ?? ''));
        }
        if ($apiModel === '') {
            return $input;
        }
        $matched = $apiModel;
        foreach ($versionModels as $code) {
            if (strcasecmp($code, $apiModel) === 0) {
                $matched = $code;
                break;
            }
        }
        $input['api_model'] = $matched;
        $input['model'] = $matched;
        $spec = DigitalAvatarNiwaCatalog::resolve($modelCode, $config, array_merge($input, ['task_type' => $taskType]));
        $family = strval($spec['family'] ?? '');
        if ($family === 'xpro' || strcasecmp($modelCode, 'XPro') === 0) {
            $input['xpro_model'] = $matched;
        }
        if ($family === 'max_avatar' || strcasecmp($modelCode, 'Max') === 0) {
            $input['max_avatar_model'] = $matched;
        }
        return $input;
    }

    public static function allowedBillingModes(): array
    {
        return ['fixed', 'chars', 'seconds', 'minutes', 'count'];
    }

    public static function normalizeBillingMode($mode): string
    {
        $mode = strtolower(trim(strval($mode)));
        return in_array($mode, self::allowedBillingModes(), true) ? $mode : 'fixed';
    }

    public static function formatRuleText(array $rule): string
    {
        $mode = self::normalizeBillingMode(strval($rule['billing_mode'] ?? 'fixed'));
        $unitCount = max(1, intval($rule['unit_count'] ?? 1));
        $unitCredit = self::creditAmount($rule['unit_credit'] ?? 0);
        $baseCredit = self::creditAmount($rule['base_credit'] ?? 0);
        if ($mode === 'fixed') {
            $text = self::formatCredit($unitCredit) . '点/次';
        } else {
            $unitName = '单位';
            if ($mode === 'chars') {
                $unitName = '字';
            } elseif ($mode === 'seconds') {
                $unitName = '秒';
            } elseif ($mode === 'minutes') {
                $unitName = '分钟';
            } elseif ($mode === 'count') {
                $unitName = '张';
            }
            $text = '每' . $unitCount . $unitName . self::formatCredit($unitCredit) . '点';
        }
        if ($baseCredit > 0 && $mode !== 'fixed') {
            $text .= ' +基础' . self::formatCredit($baseCredit) . '点';
        }
        return $text;
    }

    public static function resolveAudioSeconds(int $aid, int $bid, int $memberId, array $input): float
    {
        $seconds = floatval($input['audio_seconds'] ?? ($input['audio_duration'] ?? 0));
        if ($seconds > 0) {
            return $seconds;
        }
        $assetId = intval($input['history_audio_id'] ?? ($input['asset_id'] ?? 0));
        if ($assetId > 0) {
            $row = Db::name('digitalavatar_asset')
                ->where('aid', $aid)
                ->where('member_id', $memberId)
                ->where('id', $assetId)
                ->where('delete_time', 0)
                ->find();
            if ($row && floatval($row['duration'] ?? 0) > 0) {
                return floatval($row['duration']);
            }
        }
        $url = trim(strval($input['audio_url'] ?? ($input['existing_audio_url'] ?? ($input['audio_oss_url'] ?? ''))));
        if ($url === '') {
            return 0;
        }
        $query = Db::name('digitalavatar_asset')
            ->where('aid', $aid)
            ->where('member_id', $memberId)
            ->where('asset_type', 'audio')
            ->where('url', $url)
            ->where('delete_time', 0);
        if ($bid > 0) {
            $query->whereIn('bid', [0, $bid]);
        }
        $row = $query->order('id desc')->find();
        if ($row && floatval($row['duration'] ?? 0) > 0) {
            return floatval($row['duration']);
        }
        return self::probeRemoteAudioSeconds($url);
    }

    public static function probeRemoteAudioSeconds(string $url): float
    {
        $url = trim($url);
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            return 0;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'da_audio_');
        if ($tmp === false) {
            return 0;
        }
        $path = $tmp . '.audio';
        @unlink($tmp);
        try {
            $fp = @fopen($path, 'wb');
            if (!$fp) {
                return 0;
            }
            $ch = curl_init($url);
            if ($ch === false) {
                fclose($fp);
                return 0;
            }
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
            curl_setopt($ch, CURLOPT_RANGE, '0-1048575');
            curl_exec($ch);
            $httpCode = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
            curl_close($ch);
            fclose($fp);
            if ($httpCode >= 400 || !is_file($path) || filesize($path) < 32) {
                return 0;
            }
            if (class_exists('getID3')) {
                try {
                    $analyzer = new \getID3();
                    $info = $analyzer->analyze($path);
                    $duration = floatval($info['playtime_seconds'] ?? 0);
                    if ($duration > 0) {
                        return $duration;
                    }
                } catch (\Throwable $e) {
                }
            }
            $ffprobe = trim(strval(@shell_exec('command -v ffprobe 2>/dev/null')));
            if ($ffprobe !== '') {
                $cmd = escapeshellarg($ffprobe)
                    . ' -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 '
                    . escapeshellarg($path) . ' 2>/dev/null';
                $output = [];
                $code = 0;
                @exec($cmd, $output, $code);
                if ($code === 0 && !empty($output)) {
                    $duration = floatval(trim(strval($output[0])));
                    if ($duration > 0) {
                        return $duration;
                    }
                }
            }
            return 0;
        } finally {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    public static function reserveDailyQuota(int $aid, int $bid, int $memberId, string $modelCode, int $units): int
    {
        $units = max(0, $units);
        if ($units <= 0 || trim($modelCode) === '') {
            return 0;
        }

        $rule = Db::name('digitalavatar_daily_quota_rule')
            ->where('aid', $aid)
            ->where('model_code', $modelCode)
            ->where('status', 1)
            ->find();
        if (!$rule || intval($rule['quota_count'] ?? 0) <= 0) {
            return 0;
        }

        $date = date('Y-m-d');
        $quotaCount = max(0, intval($rule['quota_count']));
        $usage = Db::name('digitalavatar_daily_quota_usage')
            ->where('aid', $aid)
            ->where('bid', max(0, $bid))
            ->where('member_id', $memberId)
            ->where('model_code', $modelCode)
            ->where('quota_date', $date)
            ->find();
        if (!$usage) {
            try {
                Db::name('digitalavatar_daily_quota_usage')->insert([
                    'aid' => $aid,
                    'bid' => max(0, $bid),
                    'member_id' => $memberId,
                    'model_code' => $modelCode,
                    'quota_date' => $date,
                    'quota_used' => 0,
                    'quota_reserved' => 0,
                    'create_time' => time(),
                    'update_time' => time(),
                ]);
            } catch (\Throwable $e) {
                if (stripos($e->getMessage(), 'duplicate') === false) {
                    throw $e;
                }
            }
        }

        return intval(Db::transaction(function () use ($aid, $bid, $memberId, $modelCode, $date, $units, $quotaCount) {
            $usage = Db::name('digitalavatar_daily_quota_usage')
                ->where('aid', $aid)
                ->where('bid', max(0, $bid))
                ->where('member_id', $memberId)
                ->where('model_code', $modelCode)
                ->where('quota_date', $date)
                ->lock(true)
                ->find();
            if (!$usage) {
                return 0;
            }
            $available = max(0, $quotaCount - intval($usage['quota_used'] ?? 0) - intval($usage['quota_reserved'] ?? 0));
            $reserved = min($units, $available);
            if ($reserved > 0) {
                Db::name('digitalavatar_daily_quota_usage')
                    ->where('id', intval($usage['id']))
                    ->update([
                        'quota_reserved' => intval($usage['quota_reserved'] ?? 0) + $reserved,
                        'update_time' => time(),
                    ]);
            }
            return $reserved;
        }));
    }

    public static function dailyQuotaUnitsFromTask(array $task): int
    {
        $decoded = json_decode(strval($task['input_json'] ?? ''), true);
        if (is_array($decoded) && array_key_exists('daily_quota_units', $decoded)) {
            return max(0, intval($decoded['daily_quota_units']));
        }
        if (strval($task['credit_source'] ?? '') === 'daily_quota') {
            return max(0, intval($task['quota_units'] ?? 0));
        }
        return 0;
    }

    public static function settleDailyQuota(int $aid, int $taskId, bool $success, int $unitsOverride = 0): void
    {
        if ($taskId <= 0) {
            return;
        }
        $task = Db::name('digitalavatar_task')->where('aid', $aid)->where('id', $taskId)->find();
        if (!$task) {
            return;
        }
        $units = $unitsOverride > 0 ? $unitsOverride : self::dailyQuotaUnitsFromTask($task);
        if ($units <= 0) {
            return;
        }
        $modelCode = trim(strval($task['model_code'] ?? ''));
        $date = date('Y-m-d', intval($task['create_time'] ?? time()));
        Db::transaction(function () use ($task, $units, $modelCode, $date, $success) {
            $query = Db::name('digitalavatar_daily_quota_usage')
                ->where('aid', intval($task['aid']))
                ->where('bid', intval($task['bid']))
                ->where('member_id', intval($task['member_id']))
                ->where('model_code', $modelCode)
                ->where('quota_date', $date)
                ->lock(true);
            $usage = $query->find();
            if (!$usage) {
                return;
            }
            Db::name('digitalavatar_daily_quota_usage')
                ->where('id', intval($usage['id']))
                ->update([
                    'quota_reserved' => max(0, intval($usage['quota_reserved'] ?? 0) - $units),
                    'quota_used' => intval($usage['quota_used'] ?? 0) + ($success ? $units : 0),
                    'update_time' => time(),
                ]);
        });
    }

    public static function freeze(int $aid, int $bid, int $memberId, $amount, string $sourceType, int $sourceId, string $remark): void
    {
        $amount = self::creditAmount($amount);
        if ($amount <= 0) {
            return;
        }
        self::ensureAccount($aid, $bid, $memberId);
        Db::transaction(function () use ($aid, $bid, $memberId, $amount, $sourceType, $sourceId, $remark) {
            $row = self::lockAccount($aid, $bid, $memberId);
            $available = self::creditSub($row['credit_balance'] ?? 0, $row['credit_frozen'] ?? 0);
            if (self::creditLt($available, $amount)) {
                throw new \RuntimeException('创作点不足，请先购买套餐或联系管理员');
            }
            $before = self::creditSigned($row['credit_balance'] ?? 0);
            Db::name('digitalavatar_member_credit')->where('id', intval($row['id']))->update([
                'credit_frozen' => self::creditStore(self::creditAdd($row['credit_frozen'] ?? 0, $amount)),
                'update_time' => time(),
            ]);
            $task = ($sourceType === 'task' && $sourceId > 0)
                ? (Db::name('digitalavatar_task')->where('id', $sourceId)->find() ?: [])
                : [];
            $extra = self::taskCreditExtra($task, 'freeze', $amount);
            self::writeLog(
                $aid,
                $bid,
                $memberId,
                'freeze',
                0,
                $before,
                $before,
                $sourceType,
                $sourceId,
                self::composeTaskCreditRemark($task, 'freeze', $amount, $before, $before, $remark),
                0,
                $extra
            );
        });
    }

    public static function confirm(int $aid, int $taskId): void
    {
        $task = Db::name('digitalavatar_task')->where('aid', $aid)->where('id', $taskId)->find();
        if (!$task) {
            return;
        }
        $amount = self::creditAmount($task['credit_cost'] ?? 0);
        if ($amount > 0) {
            self::changeTaskCredit($aid, $taskId, true);
            try {
                DigitalAvatarLotService::confirmCreditLots(intval($task['aid']), intval($task['bid']), intval($task['member_id']), $amount);
            } catch (\Throwable $e) {
            }
            return;
        }
        if (intval($task['credit_status'] ?? 0) !== 2) {
            Db::name('digitalavatar_task')->where('id', $taskId)->update([
                'credit_status' => 2,
                'update_time' => time(),
            ]);
        }
    }

    public static function refund(int $aid, int $taskId): void
    {
        $task = Db::name('digitalavatar_task')->where('aid', $aid)->where('id', $taskId)->find();
        if ($task) {
            try {
                DigitalAvatarLotService::refundTaskLots($task);
            } catch (\Throwable $e) {
            }
        }
        $amount = self::creditAmount($task['credit_cost'] ?? 0);
        if ($amount > 0) {
            self::changeTaskCredit($aid, $taskId, false);
            return;
        }
        if ($task && intval($task['credit_status'] ?? 0) !== 3) {
            Db::name('digitalavatar_task')->where('id', $taskId)->update([
                'credit_status' => 3,
                'update_time' => time(),
            ]);
        }
    }

    private static function changeTaskCredit(int $aid, int $taskId, bool $confirm): void
    {
        $task = Db::name('digitalavatar_task')->where('aid', $aid)->where('id', $taskId)->find();
        if (!$task || self::creditAmount($task['credit_cost'] ?? 0) <= 0) {
            return;
        }
        $creditStatus = intval($task['credit_status'] ?? 0);
        if (($confirm && $creditStatus === 2) || (!$confirm && $creditStatus === 3)) {
            return;
        }
        $amount = self::creditAmount($task['credit_cost']);

        Db::transaction(function () use ($task, $taskId, $amount, $confirm) {
            $row = self::lockAccount(intval($task['aid']), intval($task['bid']), intval($task['member_id']));
            $frozen = self::creditAmount(self::creditSub($row['credit_frozen'] ?? 0, $amount));
            $before = self::creditSigned($row['credit_balance'] ?? 0);
            $balance = $before;
            $used = self::creditAmount($row['credit_used'] ?? 0);
            $action = $confirm ? 'deduct' : 'refund';
            $change = 0;
            $prevStatus = intval($task['credit_status'] ?? 0);
            if ($confirm) {
                $balance = self::creditAmount(self::creditSub($before, $amount));
                $used = self::creditAdd($used, $amount);
                $change = self::creditSigned(-1 * $amount);
            } elseif ($prevStatus === 2) {
                $balance = self::creditAdd($before, $amount);
                $used = self::creditAmount(self::creditSub($used, $amount));
                $change = $amount;
            }
            Db::name('digitalavatar_member_credit')->where('id', intval($row['id']))->update([
                'credit_balance' => self::creditStore($balance),
                'credit_frozen' => self::creditStore($frozen),
                'credit_used' => self::creditStore($used),
                'last_used_time' => $confirm ? time() : intval($row['last_used_time'] ?? 0),
                'update_time' => time(),
            ]);
            $extra = self::taskCreditExtra($task, $action, $amount);
            self::writeLog(
                intval($task['aid']),
                intval($task['bid']),
                intval($task['member_id']),
                $action,
                $change,
                $before,
                $balance,
                'task',
                $taskId,
                self::composeTaskCreditRemark($task, $action, $amount, $before, $balance),
                0,
                $extra
            );
            Db::name('digitalavatar_task')->where('id', $taskId)->update([
                'credit_status' => $confirm ? 2 : 3,
                'update_time' => time(),
            ]);
        });
    }

    private static function lockAccount(int $aid, int $bid, int $memberId): array
    {
        $row = Db::name('digitalavatar_member_credit')
            ->where('aid', $aid)
            ->where('bid', max(0, $bid))
            ->where('member_id', $memberId)
            ->lock(true)
            ->find();
        if (!$row) {
            throw new \RuntimeException('额度账户不存在');
        }
        return $row;
    }

    private static function writeLog(
        int $aid,
        int $bid,
        int $memberId,
        string $changeType,
        $change,
        $before,
        $after,
        string $sourceType,
        int $sourceId,
        string $remark,
        int $operatorId,
        array $extra = []
    ): void {
        $payload = [
            'aid' => $aid,
            'bid' => max(0, $bid),
            'member_id' => $memberId,
            'change_type' => $changeType,
            'credit_change' => self::creditStore($change),
            'change_before' => self::creditStore($before),
            'change_after' => self::creditStore($after),
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'remark' => mb_substr(trim($remark), 0, 500),
            'operator_id' => $operatorId,
            'create_time' => time(),
        ];
        if (!isset($extra['task_id']) && $sourceType === 'task' && $sourceId > 0) {
            $extra['task_id'] = $sourceId;
        }
        $payload['extra_json'] = $extra ? json_encode($extra, JSON_UNESCAPED_UNICODE) : '';
        try {
            Db::name('digitalavatar_credit_log')->insert($payload);
        } catch (\Throwable $e) {
            if (stripos($e->getMessage(), 'Unknown column') !== false && stripos($e->getMessage(), 'extra_json') !== false) {
                unset($payload['extra_json']);
                Db::name('digitalavatar_credit_log')->insert($payload);
                return;
            }
            throw $e;
        }
    }

    private static $creditLogHasExtraJson = null;

    private static function creditLogHasExtraJson()
    {
        if (self::$creditLogHasExtraJson !== null) {
            return self::$creditLogHasExtraJson;
        }
        try {
            $prefix = strval(config('database.connections.mysql.prefix', ''));
            $rows = Db::query("SHOW COLUMNS FROM `{$prefix}digitalavatar_credit_log` LIKE 'extra_json'");
            self::$creditLogHasExtraJson = !empty($rows);
        } catch (\Throwable $e) {
            self::$creditLogHasExtraJson = false;
        }
        return self::$creditLogHasExtraJson;
    }

    private static function creditLogQuery(int $aid, int $bid, int $memberId, array $filters = [])
    {
        $query = Db::name('digitalavatar_credit_log')
            ->alias('l')
            ->leftJoin('digitalavatar_task t', "t.id = l.source_id AND l.source_type = 'task'")
            ->where('l.aid', $aid)
            ->field('l.*,t.task_type as join_task_type,t.model_code as join_model_code,t.credit_cost as join_credit_cost,t.result_json as join_result_json,t.input_json as join_input_json,t.title as join_task_title');
        if ($memberId > 0) {
            $query->where('l.member_id', $memberId);
        }
        if ($bid > 0) {
            $query->where('l.bid', max(0, $bid));
        }
        $taskType = trim(strval($filters['task_type'] ?? ''));
        if ($taskType !== '') {
            $query->where(function ($q) use ($taskType) {
                $q->where('t.task_type', $taskType)
                    ->whereOr('l.source_type', $taskType);
                if (self::creditLogHasExtraJson()) {
                    $q->whereOr('l.extra_json', 'like', '%"task_type":"' . str_replace(['%', '_', '"'], '', $taskType) . '"%');
                }
            });
        }
        $taskId = trim(strval($filters['task_id'] ?? ''));
        if ($taskId !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $taskId) . '%';
            $query->where(function ($q) use ($taskId, $like) {
                $q->where('t.result_json', 'like', $like)
                    ->whereOr('l.remark', 'like', $like);
                if (self::creditLogHasExtraJson()) {
                    $q->whereOr('l.extra_json', 'like', $like);
                }
                if (ctype_digit($taskId)) {
                    $q->whereOr('l.source_id', intval($taskId))
                        ->whereOr('t.id', intval($taskId));
                }
            });
        }
        $startTime = intval($filters['start_time'] ?? 0);
        if ($startTime > 0) {
            $query->where('l.create_time', '>=', $startTime);
        }
        $endTime = intval($filters['end_time'] ?? 0);
        if ($endTime > 0) {
            $query->where('l.create_time', '<=', $endTime);
        }
        if (isset($filters['credit_change']) && $filters['credit_change'] !== '' && is_numeric($filters['credit_change'])) {
            $change = self::creditSigned($filters['credit_change']);
            $query->where(function ($q) use ($change) {
                $q->where('l.credit_change', self::creditStore($change))
                    ->whereOr('l.credit_change', self::creditStore(-1 * $change))
                    ->whereOr('t.credit_cost', self::creditStore(abs($change)));
            });
        }
        if (isset($filters['change_after']) && $filters['change_after'] !== '' && is_numeric($filters['change_after'])) {
            $query->where('l.change_after', self::creditStore($filters['change_after']));
        }
        return $query;
    }

    public static function formatLog(array $row): array
    {
        $extra = [];
        $rawExtra = $row['extra_json'] ?? '';
        if (is_string($rawExtra) && $rawExtra !== '') {
            $decoded = json_decode($rawExtra, true);
            if (is_array($decoded)) {
                $extra = $decoded;
            }
        } elseif (is_array($rawExtra)) {
            $extra = $rawExtra;
        }
        $sourceType = strval($row['source_type'] ?? '');
        $sourceId = intval($row['source_id'] ?? 0);
        $changeType = strval($row['change_type'] ?? '');
        $taskType = trim(strval($extra['task_type'] ?? ($row['join_task_type'] ?? '')));
        if ($taskType === '') {
            if ($sourceType === 'preview') {
                $taskType = 'preview';
            } elseif (in_array($sourceType, ['audio', 'package', 'admin', 'initial', 'checkin', 'invite'], true)) {
                $taskType = $sourceType;
            }
        }
        $taskId = intval($extra['task_id'] ?? 0);
        if ($taskId <= 0 && $sourceType === 'task') {
            $taskId = $sourceId;
        }
        $apiTaskId = trim(strval($extra['api_task_id'] ?? ''));
        if ($apiTaskId === '' && !empty($row['join_result_json'])) {
            $result = json_decode(strval($row['join_result_json']), true);
            if (is_array($result)) {
                foreach (['remote_id', 'audio_id', 'video_id', 'task_id', 'profile_id', 'voice_id', 'model_id'] as $key) {
                    $value = trim(strval($result[$key] ?? ''));
                    if ($value !== '' && $value !== '0') {
                        $apiTaskId = $value;
                        break;
                    }
                }
            }
        }
        $frozenAmount = self::creditAmount($extra['frozen_amount'] ?? 0);
        if ($frozenAmount <= 0) {
            $frozenAmount = self::creditAmount($row['join_credit_cost'] ?? 0);
        }
        if ($frozenAmount <= 0 && in_array($changeType, ['freeze', 'refund'], true) && self::creditEq($row['credit_change'] ?? 0, 0)) {
            $frozenAmount = self::creditAmount($row['join_credit_cost'] ?? 0);
        }
        $amount = abs(self::creditSigned($row['credit_change'] ?? 0));
        if ($amount <= 0) {
            $amount = $frozenAmount;
        }
        $task = [
            'id' => $taskId,
            'task_type' => $taskType,
        ];
        $detail = strval($row['remark'] ?? '');
        if (in_array($detail, ['AI任务排队冻结额度', 'AI任务完成扣点', 'AI任务失败退回额度', '试听配音'], true) || $taskType !== '') {
            $composed = self::composeTaskCreditRemark(
                $task,
                $changeType === 'freeze' ? 'freeze' : ($changeType === 'refund' ? 'refund' : ($changeType === 'deduct' || $changeType === 'manual_deduct' ? 'deduct' : $changeType)),
                $amount,
                self::creditSigned($row['change_before'] ?? 0),
                self::creditSigned($row['change_after'] ?? 0),
                $detail
            );
            if ($composed !== '') {
                $detail = $composed;
            }
        }
        $changeTypeTextMap = [
            'freeze' => '预扣费',
            'deduct' => '成功扣费',
            'manual_deduct' => '成功扣费',
            'refund' => '失败退还',
            'expire' => '到期清空',
            'grant' => '充值到账',
        ];
        $creditChange = self::creditSigned($row['credit_change'] ?? 0);
        $billing = self::resolveLogBillingMeta($extra, $row);
        return [
            'id' => intval($row['id'] ?? 0),
            'member_id' => intval($row['member_id'] ?? 0),
            'change_type' => $changeType,
            'change_type_text' => strval($changeTypeTextMap[$changeType] ?? $changeType),
            'credit_change' => $creditChange,
            'credit_change_text' => ($creditChange > 0 ? '+' : '') . self::formatCredit($creditChange),
            'change_before' => self::creditSigned($row['change_before'] ?? 0),
            'change_after' => self::creditSigned($row['change_after'] ?? 0),
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'task_type' => $taskType,
            'task_type_text' => self::taskTypeLabel($taskType !== '' ? $taskType : $sourceType),
            'task_id' => $taskId,
            'api_task_id' => $apiTaskId,
            'platform_code' => strval($billing['platform_code'] ?? ''),
            'api_model' => strval($billing['api_model'] ?? ''),
            'billing_seconds' => $billing['billing_seconds'] ?? '',
            'billing_seconds_text' => strval($billing['billing_seconds_text'] ?? ''),
            'billing_chars' => $billing['billing_chars'] ?? '',
            'billing_chars_text' => strval($billing['billing_chars_text'] ?? ''),
            'frozen_amount' => $frozenAmount,
            'frozen_amount_text' => $frozenAmount > 0 ? (self::formatCredit($frozenAmount) . '点') : '--',
            'remark' => strval($row['remark'] ?? ''),
            'detail_text' => $detail,
            'create_time' => intval($row['create_time'] ?? 0),
            'create_time_text' => intval($row['create_time'] ?? 0) > 0 ? date('Y-m-d H:i:s', intval($row['create_time'])) : '--',
        ];
    }

    private static function decodeAssoc($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || trim($value) === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function pickAssocString(array $bags, array $keys): string
    {
        foreach ($bags as $bag) {
            if (!is_array($bag)) {
                continue;
            }
            foreach ($keys as $key) {
                $value = trim(strval($bag[$key] ?? ''));
                if ($value !== '' && $value !== '0') {
                    return $value;
                }
            }
        }
        return '';
    }

    private static function formatBillingNumber($value): string
    {
        $number = floatval($value);
        if ($number <= 0) {
            return '';
        }
        if (abs($number - intval($number)) < 0.0001) {
            return strval(intval($number));
        }
        return rtrim(rtrim(number_format($number, 3, '.', ''), '0'), '.');
    }

    private static function resolveLogBillingMeta(array $extra, array $row): array
    {
        $input = self::decodeAssoc($row['join_input_json'] ?? '');
        $result = self::decodeAssoc($row['join_result_json'] ?? '');
        $bags = [$extra, $input, $result];
        $taskType = trim(strval($extra['task_type'] ?? ($row['join_task_type'] ?? '')));
        $platformCode = self::pickAssocString($bags, ['platform_code', 'avatar_platform_code']);
        if ($platformCode === '') {
            $platformCode = trim(strval($extra['model_code'] ?? ($row['join_model_code'] ?? ($input['model_code'] ?? ''))));
        }
        $apiModel = self::pickAssocString($bags, ['api_model', 'xpro_model', 'max_avatar_model', 'image_model']);
        if ($apiModel === '') {
            $apiModel = self::extractApiModel($input);
        }
        if ($apiModel === '') {
            $apiModel = self::extractApiModel($extra);
        }
        $mode = self::normalizeBillingMode(strval($extra['billing_mode'] ?? ($input['billing_mode'] ?? '')));
        $seconds = floatval($extra['billing_seconds'] ?? 0);
        if ($seconds <= 0) {
            $seconds = floatval($input['audio_seconds'] ?? ($input['audio_duration'] ?? ($input['duration'] ?? ($result['duration'] ?? ($result['audio_seconds'] ?? 0)))));
        }
        if ($seconds <= 0) {
            $quantity = floatval($extra['quantity'] ?? ($input['billing_quantity'] ?? 0));
            if ($quantity > 0 && $mode === 'minutes') {
                $seconds = $quantity * 60;
            } elseif ($quantity > 0 && $mode === 'seconds') {
                $seconds = $quantity;
            }
        }
        $chars = intval($extra['billing_chars'] ?? 0);
        if ($chars <= 0) {
            $text = trim(strval($extra['preview_text'] ?? ($input['text'] ?? ($input['content'] ?? ($input['prompt'] ?? ($input['script'] ?? ''))))));
            if ($text !== '') {
                $chars = function_exists('mb_strlen') ? intval(mb_strlen($text, 'UTF-8')) : strlen($text);
            }
        }
        if ($chars <= 0 && $mode === 'chars') {
            $chars = intval($extra['quantity'] ?? ($input['billing_quantity'] ?? 0));
        }

        $secondsText = '';
        $charsText = '';
        $isDuration = in_array($mode, ['seconds', 'minutes'], true) || in_array($taskType, ['avatar_video', 'video'], true);
        $isChars = $mode === 'chars' || in_array($taskType, ['audio', 'preview'], true);
        if ($isDuration) {
            $secondsText = self::formatBillingNumber($seconds);
        }
        if ($isChars) {
            $charsText = $chars > 0 ? strval($chars) : '';
        }

        return [
            'platform_code' => $platformCode,
            'api_model' => $apiModel,
            'billing_seconds' => $secondsText,
            'billing_seconds_text' => $secondsText,
            'billing_chars' => $charsText,
            'billing_chars_text' => $charsText,
        ];
    }

    private static function taskCreditExtra(array $task, string $action, $amount): array
    {
        $result = self::decodeAssoc($task['result_json'] ?? '');
        $input = self::decodeAssoc($task['input_json'] ?? '');
        $apiTaskId = '';
        foreach (['remote_id', 'audio_id', 'video_id', 'task_id', 'profile_id', 'voice_id', 'model_id'] as $key) {
            $value = trim(strval($result[$key] ?? ''));
            if ($value !== '' && $value !== '0') {
                $apiTaskId = $value;
                break;
            }
        }
        $billing = self::resolveLogBillingMeta([
            'task_type' => strval($task['task_type'] ?? ''),
            'model_code' => strval($task['model_code'] ?? ''),
        ], [
            'join_task_type' => strval($task['task_type'] ?? ''),
            'join_model_code' => strval($task['model_code'] ?? ''),
            'join_input_json' => $input,
            'join_result_json' => $result,
        ]);
        return [
            'action' => $action,
            'task_type' => strval($task['task_type'] ?? ''),
            'task_id' => intval($task['id'] ?? 0),
            'api_task_id' => $apiTaskId,
            'frozen_amount' => self::creditAmount($amount),
            'model_code' => strval($task['model_code'] ?? ''),
            'platform_code' => strval($billing['platform_code'] ?? ''),
            'api_model' => strval($billing['api_model'] ?? ''),
            'model' => strval($billing['api_model'] ?? ''),
            'billing_mode' => strval($input['billing_mode'] ?? ''),
            'billing_seconds' => strval($billing['billing_seconds'] ?? ''),
            'billing_chars' => strval($billing['billing_chars'] ?? ''),
            'quantity' => $input['billing_quantity'] ?? '',
        ];
    }

    private static function composeTaskCreditRemark(array $task, string $action, $amount, $before, $after, string $fallback = ''): string
    {
        $typeLabel = self::taskTypeLabel(strval($task['task_type'] ?? ''));
        if ($typeLabel === '额度变动' && $fallback === '试听配音') {
            $typeLabel = '试听配音';
        }
        $taskId = intval($task['id'] ?? 0);
        $parts = [];
        if ($typeLabel !== '') {
            $parts[] = $typeLabel;
        }
        if ($taskId > 0) {
            $parts[] = '任务ID ' . $taskId;
        }
        $amountText = self::formatCredit($amount);
        $afterText = self::formatCredit($after);
        if ($action === 'freeze') {
            $parts[] = '预扣费 ' . $amountText . '点（先冻结可用额度，总余额不变 ' . $afterText . '）';
        } elseif ($action === 'deduct' || $action === 'manual_deduct') {
            $parts[] = '成功扣费 ' . $amountText . '点，扣费后总余额 ' . $afterText;
        } elseif ($action === 'refund') {
            $refunded = self::creditSub($after, $before);
            if ($refunded > 0) {
                $parts[] = '失败退还 ' . self::formatCredit($refunded) . '点（解冻预扣费 ' . $amountText . '点），退还后总余额 ' . $afterText;
            } else {
                $parts[] = '失败解冻预扣费 ' . $amountText . '点，总余额不变 ' . $afterText;
            }
        } elseif ($fallback !== '') {
            $parts[] = $fallback . '，变动后总余额 ' . $afterText;
        }
        $text = implode(' · ', array_filter($parts));
        return mb_substr($text !== '' ? $text : $fallback, 0, 500);
    }

    private static function taskTypeLabel(string $taskType): string
    {
        $map = [
            'avatar_video' => '数字人视频',
            'voice_clone' => '语音克隆',
            'avatar_clone' => '数字人克隆',
            'audio' => '语音合成',
            'video' => '视频生成',
            'image' => '图片生成',
            'preview' => '试听配音',
            'package' => '套餐充值',
            'checkin' => '每日签到',
            'invite' => '邀请拉新',
            'admin' => '后台调整',
            'initial' => '注册赠送',
            'task' => 'AI任务',
        ];
        $taskType = trim($taskType);
        return strval($map[$taskType] ?? ($taskType !== '' ? $taskType : '额度变动'));
    }

    private static function quantityForMode(string $mode, array $input): float
    {
        if ($mode === 'seconds' || $mode === 'minutes') {
            $seconds = floatval($input['audio_seconds'] ?? ($input['audio_duration'] ?? 0));
            if ($seconds <= 0) {
                $seconds = 0;
            }
            if ($mode === 'minutes') {
                return $seconds > 0 ? ($seconds / 60.0) : 0;
            }
            return $seconds;
        }
        if ($mode === 'chars') {
            $text = trim(strval($input['text'] ?? ($input['content'] ?? ($input['prompt'] ?? ($input['script'] ?? '')))));
            if ($text === '') {
                return 0;
            }
            if (function_exists('mb_strlen')) {
                return max(0, intval(mb_strlen($text, 'UTF-8')));
            }
            return max(0, strlen($text));
        }
        if ($mode === 'count') {
            return max(0, floatval($input['gen_count'] ?? ($input['count'] ?? ($input['n'] ?? 0))));
        }
        return 1;
    }
}
