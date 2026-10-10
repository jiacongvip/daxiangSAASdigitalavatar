<?php

namespace app\plugin\zhuibaoagent\common;

use think\facade\Db;

class ZhuibaoCreditService
{
    public const WORKFLOW_VIDEO = 'video';
    public const WORKFLOW_GRAPHIC = 'graphic_note';
    public const WORKFLOW_IMAGE = 'text_to_image';

    private const DEFAULT_STANDARD_VIDEO_CREDIT = 16;
    private const DEFAULT_LOW_BALANCE_THRESHOLD = 20;
    private const DEFAULT_STANDARD_VIDEO_SECONDS = 60;
    private const DEFAULT_CREDIT_UNIT_LABEL = '算力';
    private const MEMBER_MEDIA_PLATFORM = 'zbportal';

    private static $creditUnitLabelCache = [];

    public static function getDashboard(int $aid): array
    {
        ZhuibaoInstaller::ensureInstalled();
        self::ensureAidDefaults($aid);

        $config = Db::name('zhuibao_credit_config')->where('aid', $aid)->find();
        $creditUnitLabel = self::normalizeCreditUnitLabel($config['credit_unit_label'] ?? self::DEFAULT_CREDIT_UNIT_LABEL);
        $standardVideoCredit = self::resolveStandardVideoCredit($aid, is_array($config) ? $config : []);
        $lowBalanceThreshold = max(0, intval($config['low_balance_threshold'] ?? self::DEFAULT_LOW_BALANCE_THRESHOLD));
        $now = time();

        $packageTotal = intval(Db::name('zhuibao_package')->where('aid', $aid)->count());
        $enabledPackageTotal = intval(Db::name('zhuibao_package')->where('aid', $aid)->where('status', 1)->count());

        $activeMemberPackageRows = Db::name('zhuibao_member_package')
            ->where('aid', $aid)
            ->where('status', 1)
            ->where(function ($query) use ($now) {
                $query->where('effective_time', 0)->whereOr('effective_time', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->where('expire_time', 0)->whereOr('expire_time', '>', $now);
            })
            ->field('uid')
            ->group('uid')
            ->select()
            ->toArray();

        $totalCreditBalance = intval(Db::name('zhuibao_member_credit')
            ->where('aid', $aid)
            ->sum('credit_balance'));

        $lowBalanceMemberTotal = $lowBalanceThreshold > 0
            ? intval(Db::name('zhuibao_member_credit')
                ->where('aid', $aid)
                ->where('credit_balance', '>', 0)
                ->where('credit_balance', '<=', $lowBalanceThreshold)
                ->count())
            : 0;

        $insufficientMemberTotal = intval(Db::name('zhuibao_member_credit')
            ->where('aid', $aid)
            ->where('credit_balance', '<=', 0)
            ->count());

        return [
            'package_total' => $packageTotal,
            'enabled_package_total' => $enabledPackageTotal,
            'member_package_total' => count($activeMemberPackageRows),
            'total_credit_balance' => $totalCreditBalance,
            'low_balance_member_total' => $lowBalanceMemberTotal,
            'insufficient_member_total' => $insufficientMemberTotal,
            'standard_video_credit' => $standardVideoCredit,
            'low_balance_threshold' => $lowBalanceThreshold,
            'credit_unit_label' => $creditUnitLabel,
        ];
    }

    public static function getMemberCreditSummary(int $aid, int $bid, int $uid): array
    {
        ZhuibaoInstaller::ensureInstalled();
        self::ensureAidDefaults($aid);

        $config = Db::name('zhuibao_credit_config')->where('aid', $aid)->find();
        $creditUnitLabel = self::normalizeCreditUnitLabel($config['credit_unit_label'] ?? self::DEFAULT_CREDIT_UNIT_LABEL);
        $standardVideoCredit = self::resolveStandardVideoCredit($aid, is_array($config) ? $config : []);
        $lowBalanceThreshold = max(0, intval($config['low_balance_threshold'] ?? self::DEFAULT_LOW_BALANCE_THRESHOLD));
        $rawInsufficientTips = trim(strval($config['insufficient_tips'] ?? ''));
        $insufficientTips = self::isDefaultInsufficientTips($rawInsufficientTips)
            ? self::buildDefaultInsufficientTips($creditUnitLabel)
            : $rawInsufficientTips;
        $packageEntrySettings = self::normalizePackageEntrySettings(is_array($config) ? $config : []);

        $creditRow = Db::name('zhuibao_member_credit')
            ->where('aid', $aid)
            ->where('bid', max(0, $bid))
            ->where('uid', $uid)
            ->find();

        $balance = max(0, intval($creditRow['credit_balance'] ?? 0));
        $frozen = max(0, intval($creditRow['credit_frozen'] ?? 0));
        $total = max(0, intval($creditRow['credit_total'] ?? 0));
        $used = max(0, intval($creditRow['credit_used'] ?? 0));

        $packageRow = self::getActiveMemberPackage($aid, $bid, $uid);
        $isMember = !empty($packageRow) ? 1 : 0;
        $expireTime = intval($packageRow['expire_time'] ?? ($creditRow['expire_time'] ?? 0));

        return [
            'charge_mode' => intval($config['charge_mode'] ?? 2),
            'package_enabled' => $packageEntrySettings['package_enabled'],
            'recharge_enabled' => $packageEntrySettings['recharge_enabled'],
            'credit_unit_label' => $creditUnitLabel,
            'is_member' => $isMember,
            'member_status_text' => $isMember === 1 ? '会员' : '非会员',
            'standard_video_credit' => $standardVideoCredit,
            'low_balance_threshold' => $lowBalanceThreshold,
            'insufficient_tips' => $insufficientTips,
            'credit_total' => $total,
            'credit_used' => $used,
            'credit_balance' => $balance,
            'credit_frozen' => $frozen,
            'estimated_standard_videos' => intval(floor($balance / $standardVideoCredit)),
            'low_balance' => $lowBalanceThreshold > 0 && $balance > 0 && $balance <= $lowBalanceThreshold ? 1 : 0,
            'insufficient' => $balance <= 0 ? 1 : 0,
            'current_package' => [
                'id' => intval($packageRow['package_id'] ?? 0),
                'name' => trim(strval($packageRow['package_name'] ?? '')) ?: '非会员',
                'granted_credit' => max(0, intval($packageRow['granted_credit'] ?? 0)),
                'gifted_credit' => max(0, intval($packageRow['gifted_credit'] ?? 0)),
                'expire_time' => $expireTime,
                'expire_time_text' => $expireTime > 0 ? date('Y-m-d H:i:s', $expireTime) : '长期有效',
                'status' => intval($packageRow['status'] ?? 0),
                'description' => trim(strval($packageRow['description'] ?? '')),
            ],
        ];
    }

    public static function hasActiveMemberPackage(int $aid, int $bid, int $uid): bool
    {
        if ($aid <= 0 || $uid <= 0) {
            return false;
        }

        return !empty(self::getActiveMemberPackage($aid, $bid, $uid));
    }

    public static function getPackageList(int $aid, int $page = 1, int $limit = 20, string $keyword = '', string $status = ''): array
    {
        self::ensureAidDefaults($aid);
        $where = [['aid', '=', $aid]];
        if ($keyword !== '') {
            $where[] = ['name|description', 'like', '%' . $keyword . '%'];
        }
        if ($status !== '' && $status !== null) {
            $where[] = ['status', '=', intval($status)];
        }

        $query = Db::name('zhuibao_package')->where($where);
        $count = intval($query->count());
        $list = $query
            ->page(max(1, $page), max(1, $limit))
            ->order('sort asc,id desc')
            ->select()
            ->toArray();

        foreach ($list as &$item) {
            $item['rights_json'] = self::decodeJson($item['rights_json'] ?? '');
            $item['open_reward_json'] = self::normalizeOpenRewardConfig(self::decodeJson($item['open_reward_json'] ?? ''));
            $item['recharge_reward_json'] = self::normalizeCommissionTierConfig(self::decodeJson($item['recharge_reward_json'] ?? ''));
            $item['recharge_ratio'] = self::normalizeRechargeRatio($item['recharge_ratio'] ?? 0);
            $item['gift_credit_amount'] = max(0, intval($item['gift_credit_amount'] ?? 0));
            $item['is_default_text'] = intval($item['is_default'] ?? 0) === 1 ? '默认套餐' : '';
            $item['frontend_visible'] = intval($item['frontend_visible'] ?? 1) === 1 ? 1 : 0;
            $item['frontend_visible_text'] = $item['frontend_visible'] === 1 ? '显示' : '隐藏';
            $item['purchase_enabled'] = intval($item['purchase_enabled'] ?? 1) === 1 ? 1 : 0;
            $item['purchase_enabled_text'] = $item['purchase_enabled'] === 1 ? '可购买' : '仅展示';
            $item['status_text'] = intval($item['status'] ?? 0) === 1 ? '启用' : '停用';
            $item['validity_text'] = intval($item['validity_days'] ?? 0) > 0
                ? intval($item['validity_days']) . '天'
                : '长期有效';
            self::formatPackageDisplayFields($item);
        }
        unset($item);

        return [
            'list' => $list,
            'total' => $count,
            'member_levels' => self::getMemberLevelOptions($aid),
            'agent_levels' => self::getAgentLevelOptions($aid),
        ];
    }

    public static function getPackageOrderList(
        int $aid,
        int $page = 1,
        int $limit = 20,
        string $keyword = '',
        string $orderType = '',
        string $status = '',
        string $grantStatus = '',
        string $startDate = '',
        string $endDate = ''
    ): array {
        ZhuibaoInstaller::ensureInstalled();

        $query = self::buildPackageOrderQuery(
            $aid,
            $keyword,
            $orderType,
            $status,
            $grantStatus,
            $startDate,
            $endDate
        );
        $count = intval($query->count('o.id'));
        $list = $query
            ->field('o.*,p.group_key as package_group_key,m.nickname,m.realname,m.tel,m.headimg,po.status as unified_pay_status,po.paytype as unified_paytype,po.paytypeid as unified_paytypeid,po.paynum as unified_paynum,po.platform as unified_platform,po.paytime as unified_paytime')
            ->page(max(1, $page), max(1, $limit))
            ->order('o.id desc')
            ->select()
            ->toArray();

        $creditUnitLabel = self::getCreditUnitLabel($aid);
        foreach ($list as &$item) {
            self::formatPackageOrderRow($item, $creditUnitLabel);
        }
        unset($item);

        $statsRow = self::buildPackageOrderQuery(
            $aid,
            $keyword,
            $orderType,
            $status,
            $grantStatus,
            $startDate,
            $endDate
        )
            ->fieldRaw(
                "COUNT(o.id) AS total_order_count," .
                "SUM(CASE WHEN o.status = 1 OR po.status = 1 THEN 1 ELSE 0 END) AS paid_order_count," .
                "SUM(CASE WHEN p.group_key IN ('recharge','non_member') THEN 1 ELSE 0 END) AS recharge_order_count," .
                "SUM(CASE WHEN p.group_key IS NULL OR p.group_key NOT IN ('recharge','non_member') THEN 1 ELSE 0 END) AS member_order_count," .
                "SUM(CASE WHEN o.status = 1 OR po.status = 1 THEN o.price ELSE 0 END) AS paid_amount," .
                "SUM(CASE WHEN o.grant_status = 1 THEN o.credit_amount + o.gifted_credit ELSE 0 END) AS granted_credit"
            )
            ->find();
        $statsRow = is_array($statsRow) ? $statsRow : [];

        return [
            'list' => $list,
            'total' => $count,
            'credit_unit_label' => $creditUnitLabel,
            'stats' => [
                'total_order_count' => intval($statsRow['total_order_count'] ?? 0),
                'paid_order_count' => intval($statsRow['paid_order_count'] ?? 0),
                'member_order_count' => intval($statsRow['member_order_count'] ?? 0),
                'recharge_order_count' => intval($statsRow['recharge_order_count'] ?? 0),
                'paid_amount' => number_format(floatval($statsRow['paid_amount'] ?? 0), 2, '.', ''),
                'granted_credit' => intval($statsRow['granted_credit'] ?? 0),
            ],
        ];
    }

    public static function getPackageOrderDetail(int $aid, int $id): array
    {
        ZhuibaoInstaller::ensureInstalled();
        if ($id <= 0) {
            throw new \RuntimeException('请选择订单');
        }

        $item = self::buildPackageOrderQuery($aid)
            ->where('o.id', $id)
            ->field('o.*,p.group_key as package_group_key,m.nickname,m.realname,m.tel,m.headimg,po.status as unified_pay_status,po.paytype as unified_paytype,po.paytypeid as unified_paytypeid,po.paynum as unified_paynum,po.platform as unified_platform,po.paytime as unified_paytime')
            ->find();
        if (!$item) {
            throw new \RuntimeException('订单不存在或无权查看');
        }

        self::formatPackageOrderRow($item, self::getCreditUnitLabel($aid));
        return $item;
    }

    public static function getPackageEntrySettings(int $aid): array
    {
        ZhuibaoInstaller::ensureInstalled();
        self::ensureAidDefaults($aid);

        $config = Db::name('zhuibao_credit_config')->where('aid', $aid)->find();
        return self::normalizePackageEntrySettings(is_array($config) ? $config : []);
    }

    public static function getCreditUnitLabel(int $aid): string
    {
        if ($aid <= 0) {
            return self::DEFAULT_CREDIT_UNIT_LABEL;
        }
        if (isset(self::$creditUnitLabelCache[$aid])) {
            return self::$creditUnitLabelCache[$aid];
        }

        ZhuibaoInstaller::ensureInstalled();
        self::ensureAidDefaults($aid);

        $config = Db::name('zhuibao_credit_config')->where('aid', $aid)->find();
        self::$creditUnitLabelCache[$aid] = self::normalizeCreditUnitLabel(is_array($config) ? ($config['credit_unit_label'] ?? '') : '');
        return self::$creditUnitLabelCache[$aid];
    }

    public static function buildDefaultInsufficientTips(string $creditUnitLabel): string
    {
        $creditUnitLabel = self::normalizeCreditUnitLabel($creditUnitLabel);
        return $creditUnitLabel . '不足，请开通套餐或充值' . $creditUnitLabel;
    }

    public static function isDefaultInsufficientTips($value): bool
    {
        $tips = trim(strval($value ?? ''));
        if ($tips === '') {
            return true;
        }

        return in_array($tips, [
            '创作点不足，请开通套餐或充值创作点',
            self::buildDefaultInsufficientTips(self::DEFAULT_CREDIT_UNIT_LABEL),
        ], true);
    }

    public static function savePackageEntrySettings(int $aid, array $info): array
    {
        ZhuibaoInstaller::ensureInstalled();
        self::ensureAidDefaults($aid);

        $current = self::getPackageEntrySettings($aid);
        $data = [
            'package_enabled' => array_key_exists('package_enabled', $info)
                ? (intval($info['package_enabled']) === 1 ? 1 : 0)
                : intval($current['package_enabled']),
            'recharge_enabled' => array_key_exists('recharge_enabled', $info)
                ? (intval($info['recharge_enabled']) === 1 ? 1 : 0)
                : intval($current['recharge_enabled']),
            'credit_unit_label' => array_key_exists('credit_unit_label', $info)
                ? self::normalizeCreditUnitLabel($info['credit_unit_label'])
                : strval($current['credit_unit_label']),
            'update_time' => time(),
        ];

        Db::name('zhuibao_credit_config')->where('aid', $aid)->update($data);
        self::$creditUnitLabelCache[$aid] = strval($data['credit_unit_label']);
        return self::getPackageEntrySettings($aid);
    }

    public static function getRuleList(
        int $aid,
        int $page = 1,
        int $limit = 20,
        string $keyword = '',
        string $status = '',
        string $ruleType = ''
    ): array {
        self::ensureAidDefaults($aid);

        $where = [['aid', '=', $aid]];
        if ($keyword !== '') {
            $where[] = ['rule_name|rule_code|step_code|remark', 'like', '%' . $keyword . '%'];
        }
        if ($status !== '' && $status !== null) {
            $where[] = ['status', '=', intval($status)];
        }
        if ($ruleType !== '' && $ruleType !== null) {
            $where[] = ['rule_type', '=', intval($ruleType)];
        }

        $query = Db::name('zhuibao_credit_rule')->where($where);
        $count = intval($query->count());
        $list = $query
            ->page(max(1, $page), max(1, $limit))
            ->order('sort asc,id asc')
            ->select()
            ->toArray();

        foreach ($list as &$item) {
            $item['workflow_type'] = self::normalizeWorkflowType(strval($item['workflow_type'] ?? self::WORKFLOW_VIDEO));
            $item['workflow_type_text'] = self::getWorkflowTypeText(strval($item['workflow_type'] ?? self::WORKFLOW_VIDEO));
            $item['rule_type_text'] = intval($item['rule_type'] ?? 1) === 2 ? '附加项' : '步骤';
            $item['billing_mode_text'] = self::getBillingModeText(intval($item['billing_mode'] ?? 1));
            $item['charge_timing_text'] = intval($item['charge_timing'] ?? 1) === 2 ? '成功后实扣' : '执行前冻结';
            $item['refund_text'] = intval($item['is_refund_on_fail'] ?? 0) === 1 ? '失败退回' : '不退回';
            $item['status_text'] = intval($item['status'] ?? 0) === 1 ? '启用' : '停用';
        }
        unset($item);

        return [
            'list' => $list,
            'total' => $count,
        ];
    }

    public static function getRuleSettings(int $aid): array
    {
        self::ensureAidDefaults($aid);

        $presets = self::ruleSettingPresets();
        $existingRows = Db::name('zhuibao_credit_rule')
            ->where('aid', $aid)
            ->where('rule_code', 'in', array_column($presets, 'rule_code'))
            ->select()
            ->toArray();

        $existingMap = [];
        foreach ($existingRows as $row) {
            $existingMap[strval($row['rule_code'])] = $row;
        }

        $list = [];
        foreach ($presets as $preset) {
            $row = $existingMap[$preset['rule_code']] ?? [];
            $workflowType = self::normalizeWorkflowType(strval($row['workflow_type'] ?? ($preset['workflow_type'] ?? self::WORKFLOW_VIDEO)));
            $chargeFields = self::normalizeRuleChargeFields(
                $workflowType,
                array_key_exists('billing_mode', $row)
                    ? intval($row['billing_mode'])
                    : intval($preset['billing_mode'] ?? 1),
                array_key_exists('charge_credit', $row)
                    ? intval($row['charge_credit'])
                    : intval($preset['charge_credit']),
                array_key_exists('base_credit', $row)
                    ? intval($row['base_credit'])
                    : intval($preset['base_credit'] ?? 0),
                array_key_exists('free_seconds', $row)
                    ? intval($row['free_seconds'])
                    : intval($preset['free_seconds'] ?? 0),
                array_key_exists('unit_seconds', $row)
                    ? intval($row['unit_seconds'])
                    : intval($preset['unit_seconds'] ?? 30),
                array_key_exists('unit_credit', $row)
                    ? intval($row['unit_credit'])
                    : intval($preset['unit_credit'] ?? 0),
                array_key_exists('max_credit', $row)
                    ? intval($row['max_credit'])
                    : intval($preset['max_credit'] ?? 0)
            );
            $billingMode = intval($chargeFields['billing_mode'] ?? 1);
            $chargeCredit = intval($chargeFields['charge_credit'] ?? 0);
            $baseCredit = intval($chargeFields['base_credit'] ?? 0);
            $freeSeconds = intval($chargeFields['free_seconds'] ?? 0);
            $unitSeconds = intval($chargeFields['unit_seconds'] ?? 30);
            $unitCredit = intval($chargeFields['unit_credit'] ?? 0);
            $maxCredit = intval($chargeFields['max_credit'] ?? 0);
            $status = array_key_exists('status', $row)
                ? (intval($row['status']) === 1 ? 1 : 0)
                : self::computeRuleEnabledStatus([
                    'billing_mode' => $billingMode,
                    'charge_credit' => $chargeCredit,
                    'base_credit' => $baseCredit,
                    'unit_seconds' => $unitSeconds,
                    'unit_credit' => $unitCredit,
                ]);
            $list[] = [
                'id' => intval($row['id'] ?? 0),
                'rule_code' => $preset['rule_code'],
                'rule_name' => $preset['rule_name'],
                'workflow_type' => $workflowType,
                'workflow_type_text' => self::getWorkflowTypeText($workflowType),
                'rule_type' => intval($preset['rule_type']),
                'rule_type_text' => intval($preset['rule_type']) === 2 ? '附加项' : '步骤',
                'step_code' => $preset['step_code'],
                'billing_mode' => $billingMode,
                'billing_mode_text' => self::getBillingModeText($billingMode),
                'charge_credit' => $chargeCredit,
                'base_credit' => $baseCredit,
                'free_seconds' => $freeSeconds,
                'unit_seconds' => $unitSeconds,
                'unit_credit' => $unitCredit,
                'max_credit' => $maxCredit,
                'charge_timing' => array_key_exists('charge_timing', $row)
                    ? (intval($row['charge_timing']) === 2 ? 2 : 1)
                    : intval($preset['charge_timing']),
                'charge_timing_text' => intval($row['charge_timing'] ?? $preset['charge_timing']) === 2 ? '成功后实扣' : '执行前冻结',
                'is_refund_on_fail' => array_key_exists('is_refund_on_fail', $row)
                    ? (intval($row['is_refund_on_fail']) === 1 ? 1 : 0)
                    : intval($preset['is_refund_on_fail']),
                'refund_text' => intval($row['is_refund_on_fail'] ?? $preset['is_refund_on_fail']) === 1 ? '失败退回' : '不退回',
                'status' => $status,
                'status_text' => $status === 1 ? '启用' : '停用',
                'sort' => array_key_exists('sort', $row) ? max(0, intval($row['sort'])) : intval($preset['sort']),
                'remark' => trim(strval($row['remark'] ?? '')) ?: strval($preset['remark']),
            ];
        }

        return $list;
    }

    public static function savePackage(int $aid, array $info): array
    {
        self::ensureAidDefaults($aid);
        $creditUnitLabel = self::getCreditUnitLabel($aid);
        $id = intval($info['id'] ?? 0);
        $name = trim(strval($info['name'] ?? ''));
        $description = trim(strval($info['description'] ?? ''));
        $price = round(floatval($info['price'] ?? 0), 2);
        $creditAmount = intval($info['credit_amount'] ?? 0);
        $validityDays = max(0, intval($info['validity_days'] ?? 0));
        $status = intval($info['status'] ?? 1) === 1 ? 1 : 0;
        $sort = max(0, intval($info['sort'] ?? 0));
        $isDefault = intval($info['is_default'] ?? 0) === 1 ? 1 : 0;
        $frontendVisible = intval($info['frontend_visible'] ?? 1) === 1 ? 1 : 0;
        $purchaseEnabled = intval($info['purchase_enabled'] ?? 1) === 1 ? 1 : 0;
        $rights = is_array($info['rights_json'] ?? null) ? $info['rights_json'] : [];
        $openReward = self::normalizeOpenRewardConfig(is_array($info['open_reward_json'] ?? null) ? $info['open_reward_json'] : []);
        $rechargeReward = self::normalizeCommissionTierConfig(is_array($info['recharge_reward_json'] ?? null) ? $info['recharge_reward_json'] : []);
        $syncPlatformLevel = intval($info['sync_platform_level'] ?? 0) === 1 ? 1 : 0;
        $syncPlatformLevelid = $syncPlatformLevel === 1 ? max(0, intval($info['sync_platform_levelid'] ?? 0)) : 0;
        $groupKey = self::normalizePackageGroupKey(strval($info['group_key'] ?? 'member'));
        $groupName = self::cleanPackageText(strval($info['group_name'] ?? ''), 50);
        if ($groupName === '') {
            $groupName = self::defaultPackageGroupName($groupKey);
        }
        $buttonAction = self::normalizePackageButtonAction(strval($info['button_action'] ?? 'pay'));

        if ($name === '') {
            throw new \RuntimeException('请填写套餐名称');
        }
        if ($creditAmount < 0) {
            throw new \RuntimeException($creditUnitLabel . '数量不能小于 0');
        }
        if ($syncPlatformLevel === 1) {
            if ($syncPlatformLevelid <= 0) {
                throw new \RuntimeException('请选择要同步的平台会员等级');
            }
            $targetLevelExists = Db::name('member_level')
                ->where('aid', $aid)
                ->where('id', $syncPlatformLevelid)
                ->value('id');
            if (!$targetLevelExists) {
                throw new \RuntimeException('同步的平台会员等级不存在，请重新选择');
            }
        }

        $now = time();
        $data = [
            'aid' => $aid,
            'name' => $name,
            'description' => $description,
            'group_key' => $groupKey,
            'group_name' => $groupName,
            'subtitle' => self::cleanPackageText(strval($info['subtitle'] ?? ''), 120),
            'badge_text' => self::cleanPackageText(strval($info['badge_text'] ?? ''), 30),
            'button_text' => self::cleanPackageText(strval($info['button_text'] ?? ''), 50),
            'button_action' => $buttonAction,
            'button_tips' => self::cleanPackageText(strval($info['button_tips'] ?? ''), 120),
            'style_key' => self::normalizePackageStyleKey(strval($info['style_key'] ?? 'purple')),
            'price' => sprintf('%.2f', $price),
            'credit_amount' => $creditAmount,
            'validity_days' => $validityDays,
            'rights_json' => json_encode($rights, JSON_UNESCAPED_UNICODE),
            'open_reward_json' => json_encode($openReward, JSON_UNESCAPED_UNICODE),
            'recharge_ratio' => sprintf('%.4f', self::normalizeRechargeRatio($info['recharge_ratio'] ?? 0)),
            'gift_credit_amount' => max(0, intval($info['gift_credit_amount'] ?? 0)),
            'recharge_reward_json' => json_encode($rechargeReward, JSON_UNESCAPED_UNICODE),
            'sync_platform_level' => $syncPlatformLevel,
            'sync_platform_levelid' => $syncPlatformLevelid,
            'is_default' => $isDefault,
            'frontend_visible' => $frontendVisible,
            'purchase_enabled' => $purchaseEnabled,
            'status' => $status,
            'sort' => $sort,
            'update_time' => $now,
        ];

        Db::startTrans();
        try {
            if ($isDefault === 1) {
                Db::name('zhuibao_package')->where('aid', $aid)->update([
                    'is_default' => 0,
                    'update_time' => $now,
                ]);
            }

            if ($id > 0) {
                $exists = Db::name('zhuibao_package')->where('aid', $aid)->where('id', $id)->value('id');
                if (!$exists) {
                    throw new \RuntimeException('套餐不存在或已删除');
                }
                Db::name('zhuibao_package')->where('aid', $aid)->where('id', $id)->update($data);
            } else {
                $data['create_time'] = $now;
                $id = intval(Db::name('zhuibao_package')->insertGetId($data));
            }
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        $row = Db::name('zhuibao_package')->where('aid', $aid)->where('id', $id)->find();
        if (!$row) {
            throw new \RuntimeException('套餐保存后读取失败');
        }
        $row['rights_json'] = self::decodeJson($row['rights_json'] ?? '');
        $row['open_reward_json'] = self::normalizeOpenRewardConfig(self::decodeJson($row['open_reward_json'] ?? ''));
        $row['recharge_reward_json'] = self::normalizeCommissionTierConfig(self::decodeJson($row['recharge_reward_json'] ?? ''));
        $row['recharge_ratio'] = self::normalizeRechargeRatio($row['recharge_ratio'] ?? 0);
        $row['gift_credit_amount'] = max(0, intval($row['gift_credit_amount'] ?? 0));
        $row['sync_platform_level'] = intval($row['sync_platform_level'] ?? 0) === 1 ? 1 : 0;
        $row['sync_platform_levelid'] = max(0, intval($row['sync_platform_levelid'] ?? 0));
        $row['is_default_text'] = intval($row['is_default'] ?? 0) === 1 ? '默认套餐' : '';
        $row['frontend_visible'] = intval($row['frontend_visible'] ?? 1) === 1 ? 1 : 0;
        $row['frontend_visible_text'] = $row['frontend_visible'] === 1 ? '显示' : '隐藏';
        $row['purchase_enabled'] = intval($row['purchase_enabled'] ?? 1) === 1 ? 1 : 0;
        $row['purchase_enabled_text'] = $row['purchase_enabled'] === 1 ? '可购买' : '仅展示';
        $row['status_text'] = intval($row['status'] ?? 0) === 1 ? '启用' : '停用';
        $row['validity_text'] = intval($row['validity_days'] ?? 0) > 0 ? intval($row['validity_days']) . '天' : '长期有效';
        self::formatPackageDisplayFields($row);

        return $row;
    }

    public static function saveRule(int $aid, array $info): array
    {
        self::ensureAidDefaults($aid);

        $id = intval($info['id'] ?? 0);
        $ruleCode = trim(strval($info['rule_code'] ?? ''));
        $ruleName = trim(strval($info['rule_name'] ?? ''));
        $workflowType = self::normalizeWorkflowType(strval($info['workflow_type'] ?? self::WORKFLOW_VIDEO));
        $ruleType = intval($info['rule_type'] ?? 1) === 2 ? 2 : 1;
        $stepCode = trim(strval($info['step_code'] ?? ''));
        $chargeFields = self::normalizeRuleChargeFields(
            $workflowType,
            intval($info['billing_mode'] ?? 1),
            intval($info['charge_credit'] ?? 0),
            intval($info['base_credit'] ?? 0),
            intval($info['free_seconds'] ?? 0),
            intval($info['unit_seconds'] ?? 30),
            intval($info['unit_credit'] ?? 0),
            intval($info['max_credit'] ?? 0)
        );
        $billingMode = intval($chargeFields['billing_mode'] ?? 1);
        $chargeCredit = intval($chargeFields['charge_credit'] ?? 0);
        $baseCredit = intval($chargeFields['base_credit'] ?? 0);
        $freeSeconds = intval($chargeFields['free_seconds'] ?? 0);
        $unitSeconds = intval($chargeFields['unit_seconds'] ?? 30);
        $unitCredit = intval($chargeFields['unit_credit'] ?? 0);
        $maxCredit = intval($chargeFields['max_credit'] ?? 0);
        $chargeTiming = intval($info['charge_timing'] ?? 1) === 2 ? 2 : 1;
        $isRefundOnFail = intval($info['is_refund_on_fail'] ?? 0) === 1 ? 1 : 0;
        $status = intval($info['status'] ?? 1) === 1 ? 1 : 0;
        $sort = max(0, intval($info['sort'] ?? 0));
        $remark = trim(strval($info['remark'] ?? ''));

        if ($ruleCode === '') {
            throw new \RuntimeException('请填写规则标识');
        }
        if (!preg_match('/^[a-zA-Z0-9_\\-]+$/', $ruleCode)) {
            throw new \RuntimeException('规则标识只支持字母、数字、中划线和下划线');
        }
        if ($ruleName === '') {
            throw new \RuntimeException('请填写规则名称');
        }
        if ($stepCode === '') {
            throw new \RuntimeException('请填写关联步骤');
        }

        $existsQuery = Db::name('zhuibao_credit_rule')
            ->where('aid', $aid)
            ->where('rule_code', $ruleCode);
        if ($id > 0) {
            $existsQuery->where('id', '<>', $id);
        }
        if ($existsQuery->count() > 0) {
            throw new \RuntimeException('规则标识已存在，请更换');
        }

        if ($ruleType === 1) {
            $mainRuleExistsQuery = Db::name('zhuibao_credit_rule')
                ->where('aid', $aid)
                ->where('workflow_type', $workflowType)
                ->where('rule_type', 1)
                ->where('step_code', $stepCode);
            if ($id > 0) {
                $mainRuleExistsQuery->where('id', '<>', $id);
            }
            if ($mainRuleExistsQuery->count() > 0) {
                throw new \RuntimeException('同一步骤只允许一条主扣点规则');
            }
        }

        $now = time();
        $data = [
            'aid' => $aid,
            'rule_code' => $ruleCode,
            'rule_name' => $ruleName,
            'workflow_type' => $workflowType,
            'rule_type' => $ruleType,
            'step_code' => $stepCode,
            'billing_mode' => $billingMode,
            'charge_credit' => $chargeCredit,
            'base_credit' => $baseCredit,
            'free_seconds' => $freeSeconds,
            'unit_seconds' => $unitSeconds,
            'unit_credit' => $unitCredit,
            'max_credit' => $maxCredit,
            'charge_timing' => $chargeTiming,
            'is_refund_on_fail' => $isRefundOnFail,
            'status' => $status,
            'sort' => $sort,
            'remark' => $remark,
            'update_time' => $now,
        ];

        if ($id > 0) {
            Db::name('zhuibao_credit_rule')->where('aid', $aid)->where('id', $id)->update($data);
        } else {
            $data['create_time'] = $now;
            $id = intval(Db::name('zhuibao_credit_rule')->insertGetId($data));
        }

        $row = Db::name('zhuibao_credit_rule')->where('aid', $aid)->where('id', $id)->find();
        if (!$row) {
            throw new \RuntimeException('扣点规则保存后读取失败');
        }

        $row['workflow_type'] = self::normalizeWorkflowType(strval($row['workflow_type'] ?? self::WORKFLOW_VIDEO));
        $row['workflow_type_text'] = self::getWorkflowTypeText(strval($row['workflow_type'] ?? self::WORKFLOW_VIDEO));
        $row['rule_type_text'] = intval($row['rule_type'] ?? 1) === 2 ? '附加项' : '步骤';
        $row['billing_mode_text'] = self::getBillingModeText(intval($row['billing_mode'] ?? 1));
        $row['charge_timing_text'] = intval($row['charge_timing'] ?? 1) === 2 ? '成功后实扣' : '执行前冻结';
        $row['refund_text'] = intval($row['is_refund_on_fail'] ?? 0) === 1 ? '失败退回' : '不退回';
        $row['status_text'] = intval($row['status'] ?? 0) === 1 ? '启用' : '停用';

        return $row;
    }

    public static function saveRuleSettings(int $aid, array $rows): array
    {
        self::ensureAidDefaults($aid);

        $rows = is_array($rows) ? $rows : [];
        $inputMap = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $ruleCode = trim(strval($row['rule_code'] ?? ''));
            if ($ruleCode === '') {
                continue;
            }
            $inputMap[$ruleCode] = $row;
        }

        $presets = self::ruleSettingPresets();
        $now = time();
        $standardVideoCredit = 0;

        Db::startTrans();
        try {
            foreach ($presets as $preset) {
                $ruleCode = strval($preset['rule_code']);
                $incoming = is_array($inputMap[$ruleCode] ?? null) ? $inputMap[$ruleCode] : [];
                $workflowType = self::normalizeWorkflowType(strval($preset['workflow_type'] ?? self::WORKFLOW_VIDEO));
                $chargeFields = self::normalizeRuleChargeFields(
                    $workflowType,
                    intval($incoming['billing_mode'] ?? ($preset['billing_mode'] ?? 1)),
                    intval($incoming['charge_credit'] ?? $preset['charge_credit']),
                    intval($incoming['base_credit'] ?? ($preset['base_credit'] ?? 0)),
                    intval($incoming['free_seconds'] ?? ($preset['free_seconds'] ?? 0)),
                    intval($incoming['unit_seconds'] ?? ($preset['unit_seconds'] ?? 30)),
                    intval($incoming['unit_credit'] ?? ($preset['unit_credit'] ?? 0)),
                    intval($incoming['max_credit'] ?? ($preset['max_credit'] ?? 0))
                );
                $billingMode = intval($chargeFields['billing_mode'] ?? 1);
                $chargeCredit = intval($chargeFields['charge_credit'] ?? 0);
                $baseCredit = intval($chargeFields['base_credit'] ?? 0);
                $freeSeconds = intval($chargeFields['free_seconds'] ?? 0);
                $unitSeconds = intval($chargeFields['unit_seconds'] ?? 30);
                $unitCredit = intval($chargeFields['unit_credit'] ?? 0);
                $maxCredit = intval($chargeFields['max_credit'] ?? 0);
                $status = self::computeRuleEnabledStatus([
                    'billing_mode' => $billingMode,
                    'charge_credit' => $chargeCredit,
                    'base_credit' => $baseCredit,
                    'unit_seconds' => $unitSeconds,
                    'unit_credit' => $unitCredit,
                ]);

                $existing = Db::name('zhuibao_credit_rule')
                    ->where('aid', $aid)
                    ->where('rule_code', $ruleCode)
                    ->find();

                $chargeTiming = $existing
                    ? (intval($existing['charge_timing'] ?? $preset['charge_timing']) === 2 ? 2 : 1)
                    : intval($preset['charge_timing']);
                $isRefundOnFail = $existing
                    ? (intval($existing['is_refund_on_fail'] ?? $preset['is_refund_on_fail']) === 1 ? 1 : 0)
                    : intval($preset['is_refund_on_fail']);
                $sort = $existing
                    ? max(0, intval($existing['sort'] ?? $preset['sort']))
                    : intval($preset['sort']);
                $remark = trim(strval($existing['remark'] ?? '')) ?: strval($preset['remark']);

                $data = [
                    'aid' => $aid,
                    'rule_code' => $ruleCode,
                    'rule_name' => strval($preset['rule_name']),
                    'workflow_type' => $workflowType,
                    'rule_type' => intval($preset['rule_type']),
                    'step_code' => strval($preset['step_code']),
                    'billing_mode' => $billingMode,
                    'charge_credit' => $chargeCredit,
                    'base_credit' => $baseCredit,
                    'free_seconds' => $freeSeconds,
                    'unit_seconds' => $unitSeconds,
                    'unit_credit' => $unitCredit,
                    'max_credit' => $maxCredit,
                    'charge_timing' => $chargeTiming,
                    'is_refund_on_fail' => $isRefundOnFail,
                    'status' => $status,
                    'sort' => $sort,
                    'remark' => $remark,
                    'update_time' => $now,
                ];

                if ($existing) {
                    Db::name('zhuibao_credit_rule')->where('id', intval($existing['id']))->update($data);
                } else {
                    $data['create_time'] = $now;
                    Db::name('zhuibao_credit_rule')->insert($data);
                }

                if (intval($preset['rule_type']) === 1) {
                    Db::name('zhuibao_credit_rule')
                        ->where('aid', $aid)
                        ->where('workflow_type', $workflowType)
                        ->where('rule_type', 1)
                        ->where('step_code', strval($preset['step_code']))
                        ->where('rule_code', '<>', $ruleCode)
                        ->update([
                            'status' => 0,
                            'update_time' => $now,
                        ]);

                    if ($status === 1 && $workflowType === self::WORKFLOW_VIDEO) {
                        $standardVideoCredit += self::computeRuleChargeCredit([
                            'billing_mode' => $billingMode,
                            'charge_credit' => $chargeCredit,
                            'base_credit' => $baseCredit,
                            'free_seconds' => $freeSeconds,
                            'unit_seconds' => $unitSeconds,
                            'unit_credit' => $unitCredit,
                            'max_credit' => $maxCredit,
                        ], self::DEFAULT_STANDARD_VIDEO_SECONDS);
                    }
                }
            }

            Db::name('zhuibao_credit_config')
                ->where('aid', $aid)
                ->update([
                    'standard_video_credit' => max(1, $standardVideoCredit),
                    'update_time' => $now,
                ]);

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        return self::getRuleSettings($aid);
    }

    public static function deletePackage(int $aid, int $id): void
    {
        if ($id <= 0) {
            throw new \RuntimeException('请选择充值包或套餐');
        }

        $exists = Db::name('zhuibao_member_package')
            ->where('aid', $aid)
            ->where('package_id', $id)
            ->count();
        if (intval($exists) > 0) {
            throw new \RuntimeException('该套餐已有会员使用，暂不支持删除');
        }

        Db::name('zhuibao_package')->where('aid', $aid)->where('id', $id)->delete();
    }

    public static function getPackageRedeemCodeList(int $aid, int $page = 1, int $limit = 20, string $keyword = '', string $status = ''): array
    {
        self::ensureAidDefaults($aid);

        $query = Db::name('zhuibao_package_redeem_code')
            ->alias('c')
            ->leftJoin('zhuibao_package p', 'p.aid = c.aid and p.id = c.package_id')
            ->where('c.aid', $aid);
        if ($keyword !== '') {
            $query->where(function ($subQuery) use ($keyword) {
                $subQuery
                    ->where('c.code', 'like', '%' . $keyword . '%')
                    ->whereOr('c.remark', 'like', '%' . $keyword . '%')
                    ->whereOr('p.name', 'like', '%' . $keyword . '%');
            });
        }
        if ($status !== '' && $status !== null) {
            $query->where('c.status', intval($status));
        }
        $count = intval($query->count());
        $list = $query
            ->field('c.*,p.name as package_name,p.status as package_status,p.credit_amount,p.gift_credit_amount,p.validity_days')
            ->page(max(1, $page), max(1, $limit))
            ->order('c.id desc')
            ->select()
            ->toArray();

        foreach ($list as &$item) {
            self::formatRedeemCodeRow($item);
        }
        unset($item);

        return [
            'list' => $list,
            'total' => $count,
            'package_options' => self::getRedeemPackageOptions($aid),
        ];
    }

    public static function savePackageRedeemCode(int $aid, array $info): array
    {
        self::ensureAidDefaults($aid);

        $id = intval($info['id'] ?? 0);
        $code = self::normalizeRedeemCode(strval($info['code'] ?? ''));
        $packageId = intval($info['package_id'] ?? 0);
        $maxUseCount = max(0, intval($info['max_use_count'] ?? 1));
        $perMemberLimit = max(0, intval($info['per_member_limit'] ?? 1));
        $status = intval($info['status'] ?? 1) === 1 ? 1 : 0;
        $remark = self::cleanPackageText(strval($info['remark'] ?? ''), 255);
        $range = is_array($info['valid_time_range'] ?? null) ? $info['valid_time_range'] : [];
        $startTime = self::normalizeRedeemTimestamp($info['start_time'] ?? ($range[0] ?? 0));
        $endTime = self::normalizeRedeemTimestamp($info['end_time'] ?? ($range[1] ?? 0));

        if ($code === '') {
            throw new \RuntimeException('请填写兑换码，支持字母、数字、横线和下划线');
        }
        if ($packageId <= 0) {
            throw new \RuntimeException('请选择关联套餐或非会员充值包');
        }
        if ($startTime > 0 && $endTime > 0 && $endTime < $startTime) {
            throw new \RuntimeException('兑换结束时间不能早于开始时间');
        }

        $package = Db::name('zhuibao_package')
            ->where('aid', $aid)
            ->where('id', $packageId)
            ->find();
        if (!$package) {
            throw new \RuntimeException('关联套餐或非会员充值包不存在');
        }

        $existsQuery = Db::name('zhuibao_package_redeem_code')
            ->where('aid', $aid)
            ->where('code', $code);
        if ($id > 0) {
            $existsQuery->where('id', '<>', $id);
        }
        if ($existsQuery->find()) {
            throw new \RuntimeException('兑换码已存在');
        }

        $now = time();
        $data = [
            'aid' => $aid,
            'code' => $code,
            'package_id' => $packageId,
            'max_use_count' => $maxUseCount,
            'per_member_limit' => $perMemberLimit,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => $status,
            'remark' => $remark,
            'update_time' => $now,
        ];

        if ($id > 0) {
            $row = Db::name('zhuibao_package_redeem_code')->where('aid', $aid)->where('id', $id)->find();
            if (!$row) {
                throw new \RuntimeException('兑换码不存在');
            }
            Db::name('zhuibao_package_redeem_code')->where('aid', $aid)->where('id', $id)->update($data);
        } else {
            $data['used_count'] = 0;
            $data['create_time'] = $now;
            $id = intval(Db::name('zhuibao_package_redeem_code')->insertGetId($data));
        }

        $row = Db::name('zhuibao_package_redeem_code')
            ->alias('c')
            ->leftJoin('zhuibao_package p', 'p.aid = c.aid and p.id = c.package_id')
            ->field('c.*,p.name as package_name,p.status as package_status,p.credit_amount,p.gift_credit_amount,p.validity_days')
            ->where('c.aid', $aid)
            ->where('c.id', $id)
            ->find();
        if (!$row) {
            throw new \RuntimeException('兑换码保存后读取失败');
        }
        self::formatRedeemCodeRow($row);

        return $row;
    }

    public static function deletePackageRedeemCode(int $aid, int $id): void
    {
        if ($id <= 0) {
            throw new \RuntimeException('请选择兑换码');
        }

        $row = Db::name('zhuibao_package_redeem_code')->where('aid', $aid)->where('id', $id)->find();
        if (!$row) {
            throw new \RuntimeException('兑换码不存在');
        }

        $used = Db::name('zhuibao_package_redeem_log')
            ->where('aid', $aid)
            ->where('code_id', $id)
            ->count();
        if (intval($used) > 0) {
            throw new \RuntimeException('该兑换码已有兑换记录，建议改为停用');
        }

        Db::name('zhuibao_package_redeem_code')->where('aid', $aid)->where('id', $id)->delete();
    }

    public static function redeemMemberPackageCode(int $aid, int $bid, int $uid, string $rawCode): array
    {
        ZhuibaoInstaller::ensureInstalled();
        self::ensureAidDefaults($aid);

        if ($uid <= 0) {
            throw new \RuntimeException('请先登录会员账号');
        }

        $code = self::normalizeRedeemCode($rawCode);
        if ($code === '') {
            throw new \RuntimeException('请输入正确的兑换码');
        }

        $now = time();
        Db::startTrans();
        try {
            $row = Db::name('zhuibao_package_redeem_code')
                ->where('aid', $aid)
                ->where('code', $code)
                ->lock(true)
                ->find();
            if (!$row) {
                throw new \RuntimeException('兑换码不存在');
            }
            if (intval($row['status'] ?? 0) !== 1) {
                throw new \RuntimeException('兑换码已停用');
            }
            if (intval($row['start_time'] ?? 0) > 0 && intval($row['start_time']) > $now) {
                throw new \RuntimeException('兑换码尚未开始');
            }
            if (intval($row['end_time'] ?? 0) > 0 && intval($row['end_time']) < $now) {
                throw new \RuntimeException('兑换码已过期');
            }

            $maxUseCount = max(0, intval($row['max_use_count'] ?? 1));
            $usedCount = max(0, intval($row['used_count'] ?? 0));
            if ($maxUseCount > 0 && $usedCount >= $maxUseCount) {
                throw new \RuntimeException('兑换码已被使用完');
            }

            $perMemberLimit = max(0, intval($row['per_member_limit'] ?? 1));
            if ($perMemberLimit > 0) {
                $memberUsed = Db::name('zhuibao_package_redeem_log')
                    ->where('aid', $aid)
                    ->where('code_id', intval($row['id']))
                    ->where('uid', $uid)
                    ->count();
                if (intval($memberUsed) >= $perMemberLimit) {
                    throw new \RuntimeException('该兑换码当前账号已兑换过');
                }
            }

            $package = Db::name('zhuibao_package')
                ->where('aid', $aid)
                ->where('id', intval($row['package_id'] ?? 0))
                ->where('status', 1)
                ->find();
            if (!$package) {
                throw new \RuntimeException('兑换码关联套餐或非会员充值包不存在或已停用');
            }
            self::assertPackageEntryEnabled($package, self::getPackageEntrySettings($aid));

            $grantPayload = [
                'uid' => $uid,
                'bid' => max(0, $bid),
                'package_id' => intval($package['id']),
                'source_type' => 'redeem',
                'source_id' => intval($row['id']),
                'remark' => '兑换码兑换：' . $code,
            ];
            $grantResult = self::isCreditRechargePackage($package)
                ? self::grantMemberCreditRecharge($aid, $uid, 'member', $grantPayload)
                : self::grantMemberPackage($aid, $uid, 'member', $grantPayload);

            Db::name('zhuibao_package_redeem_code')
                ->where('aid', $aid)
                ->where('id', intval($row['id']))
                ->update([
                    'used_count' => Db::raw('used_count + 1'),
                    'update_time' => $now,
                ]);

            Db::name('zhuibao_package_redeem_log')->insert([
                'aid' => $aid,
                'bid' => max(0, $bid),
                'uid' => $uid,
                'code_id' => intval($row['id']),
                'code' => $code,
                'package_id' => intval($package['id']),
                'package_name' => trim(strval($package['name'] ?? '')),
                'credit_amount' => max(0, intval($package['credit_amount'] ?? 0)),
                'gifted_credit' => max(0, intval($package['gift_credit_amount'] ?? 0)),
                'remark' => '兑换充值包或套餐：' . trim(strval($package['name'] ?? '')),
                'create_time' => $now,
                'update_time' => $now,
            ]);

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        return [
            'code' => $code,
            'package' => [
                'id' => intval($package['id'] ?? 0),
                'name' => trim(strval($package['name'] ?? '')),
                'credit_amount' => max(0, intval($package['credit_amount'] ?? 0)),
                'gift_credit_amount' => max(0, intval($package['gift_credit_amount'] ?? 0)),
            ],
            'member_credit' => $grantResult,
            'credit_summary' => self::getMemberCreditSummary($aid, max(0, $bid), $uid),
        ];
    }

    /**
     * 会员端套餐展示列表（仅启用且前台显示的套餐）
     */
    public static function getMemberPackageOptions(int $aid): array
    {
        ZhuibaoInstaller::ensureInstalled();
        self::ensureAidDefaults($aid);
        $entrySettings = self::getPackageEntrySettings($aid);

        $list = Db::name('zhuibao_package')
            ->where('aid', $aid)
            ->where('status', 1)
            ->where('frontend_visible', 1)
            ->order('sort asc,id desc')
            ->select()
            ->toArray();

        $options = [];
        foreach ($list as $item) {
            $item['validity_text'] = intval($item['validity_days'] ?? 0) > 0
                ? intval($item['validity_days']) . '天'
                : '长期有效';
            self::formatPackageDisplayFields($item);
            if (!self::isPackageEntryEnabled($item, $entrySettings)) {
                continue;
            }
            $options[] = [
                'id' => intval($item['id']),
                'name' => trim(strval($item['name'] ?? '')),
                'description' => trim(strval($item['description'] ?? '')),
                'group_key' => trim(strval($item['group_key'] ?? 'member')),
                'group_name' => trim(strval($item['group_name'] ?? '会员套餐')),
                'subtitle' => trim(strval($item['subtitle'] ?? '')),
                'badge_text' => trim(strval($item['badge_text'] ?? '')),
                'button_text' => trim(strval($item['button_text'] ?? '')),
                'button_action' => trim(strval($item['button_action'] ?? 'pay')),
                'button_tips' => trim(strval($item['button_tips'] ?? '')),
                'style_key' => trim(strval($item['style_key'] ?? 'purple')),
                'recharge_ratio' => self::normalizeRechargeRatio($item['recharge_ratio'] ?? 0),
                'gift_credit_amount' => max(0, intval($item['gift_credit_amount'] ?? 0)),
                'price' => number_format(floatval($item['price'] ?? 0), 2, '.', ''),
                'credit_amount' => max(0, intval($item['credit_amount'] ?? 0)),
                'validity_days' => max(0, intval($item['validity_days'] ?? 0)),
                'validity_text' => trim(strval($item['validity_text'] ?? '')),
                'credit_unit_label' => trim(strval($item['credit_unit_label'] ?? self::DEFAULT_CREDIT_UNIT_LABEL)),
                'is_default' => intval($item['is_default'] ?? 0),
                'purchase_enabled' => intval($item['purchase_enabled'] ?? 1) === 1 ? 1 : 0,
                'rights' => self::decodeJson($item['rights_json'] ?? ''),
            ];
        }

        return $options;
    }

    /**
     * 会员端额度流水
     */
    public static function getMemberCreditLogs(int $aid, int $uid, int $page = 1, int $limit = 20): array
    {
        ZhuibaoInstaller::ensureInstalled();

        $typeTextMap = [
            'recharge' => '套餐到账',
            'freeze' => '冻结',
            'release' => '解冻',
            'deduct' => '扣除',
            'consume' => '消耗',
            'refund' => '退回',
            'adjust' => '调整',
            'expire' => '过期',
        ];

        $query = Db::name('zhuibao_credit_log')
            ->where('aid', $aid)
            ->where('uid', $uid);
        $count = intval($query->count());
        $list = $query
            ->order('id desc')
            ->page(max(1, $page), max(1, min(100, $limit)))
            ->select()
            ->toArray();

        $rows = [];
        foreach ($list as $item) {
            $changeType = trim(strval($item['change_type'] ?? ''));
            $rows[] = [
                'id' => intval($item['id']),
                'change_type' => $changeType,
                'change_type_text' => $typeTextMap[$changeType] ?? $changeType,
                'credit_change' => intval($item['credit_change'] ?? 0),
                'after_balance' => intval($item['after_balance'] ?? 0),
                'step_code' => trim(strval($item['step_code'] ?? '')),
                'remark' => trim(strval($item['remark'] ?? '')),
                'create_time' => intval($item['create_time'] ?? 0),
                'create_time_text' => !empty($item['create_time']) ? date('Y-m-d H:i', intval($item['create_time'])) : '',
            ];
        }

        return [
            'list' => $rows,
            'total' => $count,
        ];
    }

    /**
     * 会员端购买充值包或套餐下单（返回统一支付单ID，免费项目直接发放）
     */
    public static function createMemberPackageOrder(int $aid, int $bid, int $uid, int $packageId): array
    {
        ZhuibaoInstaller::ensureInstalled();
        self::ensureAidDefaults($aid);

        if ($uid <= 0) {
            throw new \RuntimeException('请先登录会员账号');
        }
        if ($packageId <= 0) {
            throw new \RuntimeException('请选择充值包或套餐');
        }

        $package = Db::name('zhuibao_package')
            ->where('aid', $aid)
            ->where('id', $packageId)
            ->where('status', 1)
            ->where('frontend_visible', 1)
            ->where('purchase_enabled', 1)
            ->find();
        if (!$package) {
            throw new \RuntimeException('充值包或套餐不存在、已停用或不允许购买');
        }
        self::assertPackageEntryEnabled($package, self::getPackageEntrySettings($aid));
        if (self::normalizePackageButtonAction(strval($package['button_action'] ?? 'pay')) !== 'pay') {
            $tips = trim(strval($package['button_tips'] ?? '')) ?: '该项目暂不支持在线购买，请联系管理员开通';
            throw new \RuntimeException($tips);
        }

        $price = round(floatval($package['price'] ?? 0), 2);
        $now = time();
        $ordernum = 'ZBP' . date('YmdHis') . str_pad(strval(mt_rand(0, 9999)), 4, '0', STR_PAD_LEFT);

        $orderId = intval(Db::name('zhuibao_package_order')->insertGetId([
            'aid' => $aid,
            'bid' => max(0, $bid),
            'mid' => $uid,
            'ordernum' => $ordernum,
            'package_id' => $packageId,
            'package_name' => trim(strval($package['name'] ?? '')),
            'credit_amount' => max(0, intval($package['credit_amount'] ?? 0)),
            'gifted_credit' => max(0, intval($package['gift_credit_amount'] ?? 0)),
            'validity_days' => max(0, intval($package['validity_days'] ?? 0)),
            'price' => number_format($price, 2, '.', ''),
            'status' => 0,
            'payorderid' => 0,
            'grant_status' => 0,
            'createtime' => $now,
            'updatetime' => $now,
        ]));

        if ($price <= 0) {
            Db::name('zhuibao_package_order')
                ->where('aid', $aid)
                ->where('id', $orderId)
                ->update([
                    'status' => 1,
                    'paytype' => '免费领取',
                    'paytime' => $now,
                    'updatetime' => $now,
                ]);
            self::onPackageOrderPaid($orderId);

            return [
                'order_id' => $orderId,
                'ordernum' => $ordernum,
                'need_pay' => 0,
                'payorderid' => 0,
            ];
        }

        $creditUnitLabel = self::getCreditUnitLabel($aid);
        $orderTitlePrefix = self::isCreditRechargePackage($package) ? ('一键追爆' . $creditUnitLabel . '-') : '一键追爆套餐-';
        $payorderid = intval(\app\admin\model\Payorder::createorder(
            $aid,
            max(0, $bid),
            $uid,
            'zhuibao_package',
            $orderId,
            $ordernum,
            $orderTitlePrefix . trim(strval($package['name'] ?? '')),
            $price
        ));

        return [
            'order_id' => $orderId,
            'ordernum' => $ordernum,
            'need_pay' => 1,
            'payorderid' => $payorderid,
        ];
    }

    /**
     * 充值包或套餐订单支付成功后发放额度（统一支付回调调用）
     */
    public static function onPackageOrderPaid(int $orderId): void
    {
        $order = Db::name('zhuibao_package_order')->where('id', $orderId)->find();
        if (!$order) {
            throw new \RuntimeException('充值包或套餐订单不存在');
        }
        if (intval($order['grant_status'] ?? 0) === 1) {
            return;
        }

        $aid = intval($order['aid']);
        $package = Db::name('zhuibao_package')
            ->where('aid', $aid)
            ->where('id', intval($order['package_id']))
            ->find();
        if (!$package) {
            throw new \RuntimeException('充值包或套餐不存在或已删除');
        }

        $grantPayload = [
            'uid' => intval($order['mid']),
            'bid' => intval($order['bid'] ?? 0),
            'package_id' => intval($order['package_id']),
            'gifted_credit' => max(0, intval($order['gifted_credit'] ?? 0)),
            'source_type' => 'purchase',
            'source_id' => intval($order['id']),
            'remark' => '手机端购买充值包或套餐 ' . trim(strval($order['ordernum'] ?? '')),
        ];
        if (self::isCreditRechargePackage($package)) {
            $creditUnitLabel = self::getCreditUnitLabel($aid);
            $grantPayload['remark'] = '手机端充值' . $creditUnitLabel . ' ' . trim(strval($order['ordernum'] ?? ''));
            self::grantMemberCreditRecharge($aid, intval($order['mid']), 'member', $grantPayload);
        } else {
            self::grantMemberPackage($aid, intval($order['mid']), 'member', $grantPayload);
        }

        Db::name('zhuibao_package_order')
            ->where('id', $orderId)
            ->update([
                'status' => 1,
                'paytype' => trim(strval($order['paytype'] ?? '')) ?: '在线支付',
                'paytime' => intval($order['paytime'] ?? 0) > 0 ? intval($order['paytime']) : time(),
                'grant_status' => 1,
                'updatetime' => time(),
            ]);
    }

    /**
     * 直接充值额度：不生成会员套餐授权，只增加额度余额。
     */
    private static function grantMemberCreditRecharge(int $aid, int $operatorId, string $operatorType, array $info): array
    {
        self::ensureAidDefaults($aid);
        $creditUnitLabel = self::getCreditUnitLabel($aid);

        $uid = intval($info['uid'] ?? 0);
        $bid = max(0, intval($info['bid'] ?? 0));
        $packageId = intval($info['package_id'] ?? 0);
        $giftedCredit = array_key_exists('gifted_credit', $info) ? max(0, intval($info['gifted_credit'] ?? 0)) : null;
        $remark = trim(strval($info['remark'] ?? ''));
        $effectiveTime = intval($info['effective_time'] ?? 0);
        $expireTime = intval($info['expire_time'] ?? 0);
        $sourceType = trim(strval($info['source_type'] ?? 'recharge')) ?: 'recharge';
        $sourceId = max(0, intval($info['source_id'] ?? 0));

        if ($uid <= 0) {
            throw new \RuntimeException('请选择会员');
        }
        if ($packageId <= 0) {
            throw new \RuntimeException('请选择充值包');
        }

        $member = Db::name('member')
            ->where('aid', $aid)
            ->where('id', $uid)
            ->find();
        if (!$member) {
            throw new \RuntimeException('会员不存在');
        }

        $package = Db::name('zhuibao_package')
            ->where('aid', $aid)
            ->where('id', $packageId)
            ->where('status', 1)
            ->find();
        if (!$package) {
            throw new \RuntimeException('充值包不存在或已停用');
        }
        if ($giftedCredit === null) {
            $giftedCredit = max(0, intval($package['gift_credit_amount'] ?? 0));
        }

        $now = time();
        $effectiveTime = $effectiveTime > 0 ? $effectiveTime : $now;
        if ($expireTime <= 0) {
            $validityDays = intval($package['validity_days'] ?? 0);
            $expireTime = $validityDays > 0 ? strtotime('+' . $validityDays . ' day', $effectiveTime) : 0;
        }

        $creditAmount = max(0, intval($package['credit_amount'] ?? 0));
        $totalGrant = $creditAmount + $giftedCredit;
        if ($totalGrant <= 0) {
            throw new \RuntimeException('充值包' . $creditUnitLabel . '数量不能为 0');
        }

        Db::startTrans();
        try {
            $creditRow = Db::name('zhuibao_member_credit')
                ->where('aid', $aid)
                ->where('bid', $bid)
                ->where('uid', $uid)
                ->lock(true)
                ->find();

            $beforeBalance = intval($creditRow['credit_balance'] ?? 0);
            $beforeFrozen = intval($creditRow['credit_frozen'] ?? 0);
            $beforeTotal = intval($creditRow['credit_total'] ?? 0);

            $updateData = [
                'credit_total' => $beforeTotal + $totalGrant,
                'credit_balance' => $beforeBalance + $totalGrant,
                'last_recharge_time' => $now,
                'expire_time' => $expireTime,
                'status' => 1,
                'update_time' => $now,
            ];

            if ($creditRow) {
                Db::name('zhuibao_member_credit')->where('id', intval($creditRow['id']))->update($updateData);
            } else {
                Db::name('zhuibao_member_credit')->insert(array_merge($updateData, [
                    'aid' => $aid,
                    'bid' => $bid,
                    'uid' => $uid,
                    'credit_used' => 0,
                    'credit_frozen' => 0,
                    'create_time' => $now,
                ]));
            }

            Db::name('zhuibao_credit_log')->insert([
                'aid' => $aid,
                'bid' => $bid,
                'uid' => $uid,
                'task_id' => 0,
                'step_id' => 0,
                'step_code' => '',
                'rule_code' => '',
                'package_id' => $packageId,
                'change_type' => 'recharge',
                'credit_change' => $totalGrant,
                'before_balance' => $beforeBalance,
                'after_balance' => $beforeBalance + $totalGrant,
                'before_frozen' => $beforeFrozen,
                'after_frozen' => $beforeFrozen,
                'biz_no' => $sourceType . '_' . ($sourceId > 0 ? $sourceId : $now),
                'operator_type' => $operatorType,
                'operator_id' => $operatorId,
                'remark' => $remark !== '' ? $remark : ('充值' . $creditUnitLabel . '：' . strval($package['name'])),
                'create_time' => $now,
                'update_time' => $now,
            ]);

            self::syncPlatformMemberLevelByPackage($aid, $uid, $package, $expireTime, $now);

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        return self::getMemberCreditList($aid, 1, 1, strval($uid))['list'][0] ?? [];
    }

    public static function getMemberCreditList(int $aid, int $page = 1, int $limit = 20, string $keyword = ''): array
    {
        self::ensureAidDefaults($aid);

        $where = [['m.aid', '=', $aid]];
        if ($keyword !== '') {
            $where[] = ['m.nickname|m.realname|m.tel|m.id', 'like', '%' . $keyword . '%'];
        }

        $count = intval(Db::name('member')->alias('m')->where($where)->count());
        $list = Db::name('member')
            ->alias('m')
            ->field('m.id,m.aid,m.bid,m.nickname,m.realname,m.tel,m.headimg,m.createtime,mc.credit_total,mc.credit_used,mc.credit_balance,mc.credit_frozen,mc.expire_time as credit_expire_time')
            ->leftJoin('zhuibao_member_credit mc', 'mc.aid = m.aid and mc.bid = m.bid and mc.uid = m.id')
            ->where($where)
            ->page(max(1, $page), max(1, $limit))
            ->order('m.id desc')
            ->select()
            ->toArray();

        $assetSummaryMap = self::getMemberAssetSummaryMap($aid, array_column($list, 'id'));
        foreach ($list as &$item) {
            $uid = intval($item['id'] ?? 0);
            $packageRow = self::getActiveMemberPackage($aid, intval($item['bid'] ?? 0), intval($item['id'] ?? 0));
            $creditSummary = self::getMemberCreditSummary($aid, intval($item['bid'] ?? 0), intval($item['id'] ?? 0));
            $creditBalance = intval($item['credit_balance'] ?? 0);
            $assetSummary = $assetSummaryMap[$uid] ?? self::emptyMemberAssetSummary();
            $item['package_name'] = trim(strval($packageRow['package_name'] ?? '')) ?: ($creditBalance > 0 ? '非会员充值' : '未开通套餐');
            $item['package_expire_time'] = intval($packageRow['expire_time'] ?? 0);
            $item['package_expire_time_text'] = intval($item['package_expire_time']) > 0
                ? date('Y-m-d H:i:s', intval($item['package_expire_time']))
                : '长期有效';
            $item['credit_total'] = intval($item['credit_total'] ?? 0);
            $item['credit_used'] = intval($item['credit_used'] ?? 0);
            $item['credit_balance'] = $creditBalance;
            $item['credit_frozen'] = intval($item['credit_frozen'] ?? 0);
            $item['estimated_standard_videos'] = intval($creditSummary['estimated_standard_videos'] ?? 0);
            $item['low_balance'] = intval($creditSummary['low_balance'] ?? 0);
            $item['insufficient'] = intval($creditSummary['insufficient'] ?? 0);
            $item['avatar_asset_count'] = intval($assetSummary['avatar_count'] ?? 0);
            $item['voice_asset_count'] = intval($assetSummary['voice_count'] ?? 0);
            $item['media_asset_count'] = intval($assetSummary['media_count'] ?? 0);
            $item['asset_total_count'] = intval($assetSummary['total_count'] ?? 0);
            $item['asset_summary'] = $assetSummary;
            $item['create_time_text'] = !empty($item['createtime']) ? date('Y-m-d H:i:s', intval($item['createtime'])) : '';
        }
        unset($item);

        return [
            'list' => $list,
            'total' => $count,
        ];
    }

    public static function getMemberAssetDetail(int $aid, int $uid, int $bid = 0): array
    {
        if ($uid <= 0) {
            throw new \RuntimeException('请选择会员');
        }

        $member = Db::name('member')
            ->field('id,aid,bid,nickname,realname,tel,headimg,createtime')
            ->where('aid', $aid)
            ->where('id', $uid)
            ->find();
        if (!$member) {
            throw new \RuntimeException('会员不存在');
        }

        $avatarProfiles = self::getMemberAvatarAssetRows($aid, $uid, $bid);
        $voiceProfiles = self::getMemberVoiceAssetRows($aid, $uid, $bid);
        $mediaList = self::getMemberMediaAssetRows($aid, $uid, $bid, 120);
        $summary = [
            'avatar_count' => count($avatarProfiles),
            'voice_count' => count($voiceProfiles),
            'media_count' => count($mediaList),
        ];
        $summary['total_count'] = intval($summary['avatar_count']) + intval($summary['voice_count']) + intval($summary['media_count']);

        $member['create_time_text'] = !empty($member['createtime']) ? date('Y-m-d H:i:s', intval($member['createtime'])) : '';

        return [
            'member' => $member,
            'summary' => $summary,
            'avatar_profiles' => $avatarProfiles,
            'voice_profiles' => $voiceProfiles,
            'media_list' => $mediaList,
        ];
    }

    private static function getMemberAssetSummaryMap(int $aid, array $uids): array
    {
        $uids = array_values(array_unique(array_filter(array_map('intval', $uids), static function ($uid) {
            return $uid > 0;
        })));
        if (empty($uids)) {
            return [];
        }

        $summaryMap = [];
        foreach ($uids as $uid) {
            $summaryMap[$uid] = self::emptyMemberAssetSummary();
        }

        $avatarRows = Db::name('zhuibao_member_avatar_profile')
            ->field('uid,count(*) as total')
            ->where('aid', $aid)
            ->where('uid', 'in', $uids)
            ->where('status', 1)
            ->where('delete_time', 0)
            ->group('uid')
            ->select()
            ->toArray();
        foreach ($avatarRows as $row) {
            $uid = intval($row['uid'] ?? 0);
            if (isset($summaryMap[$uid])) {
                $summaryMap[$uid]['avatar_count'] = intval($row['total'] ?? 0);
            }
        }

        $voiceRows = Db::name('zhuibao_member_voice_profile')
            ->field('uid,count(*) as total')
            ->where('aid', $aid)
            ->where('uid', 'in', $uids)
            ->where('status', 1)
            ->where('delete_time', 0)
            ->group('uid')
            ->select()
            ->toArray();
        foreach ($voiceRows as $row) {
            $uid = intval($row['uid'] ?? 0);
            if (isset($summaryMap[$uid])) {
                $summaryMap[$uid]['voice_count'] = intval($row['total'] ?? 0);
            }
        }

        $mediaQuery = Db::name('admin_upload')
            ->field('uid,count(*) as total')
            ->where('aid', $aid)
            ->where('uid', 'in', $uids)
            ->where('isdel', 0)
            ->where('type', '<>', 'pem');
        self::applyMemberMediaAssetScope($mediaQuery);
        $mediaRows = $mediaQuery
            ->group('uid')
            ->select()
            ->toArray();
        foreach ($mediaRows as $row) {
            $uid = intval($row['uid'] ?? 0);
            if (isset($summaryMap[$uid])) {
                $summaryMap[$uid]['media_count'] = intval($row['total'] ?? 0);
            }
        }

        foreach ($summaryMap as &$summary) {
            $summary['total_count'] = intval($summary['avatar_count'] ?? 0)
                + intval($summary['voice_count'] ?? 0)
                + intval($summary['media_count'] ?? 0);
        }
        unset($summary);

        return $summaryMap;
    }

    private static function emptyMemberAssetSummary(): array
    {
        return [
            'avatar_count' => 0,
            'voice_count' => 0,
            'media_count' => 0,
            'total_count' => 0,
        ];
    }

    private static function getMemberAvatarAssetRows(int $aid, int $uid, int $bid = 0): array
    {
        $query = Db::name('zhuibao_member_avatar_profile')
            ->field('id,bid,name,sex,platform_code,sample_url,model_id,task_group_id,is_default,status,source_type,remark,create_time,update_time')
            ->where('aid', $aid)
            ->where('uid', $uid)
            ->where('status', 1)
            ->where('delete_time', 0);

        if ($bid > 0) {
            $query->where(function ($subQuery) use ($bid) {
                $subQuery->where('bid', 0)->whereOr('bid', $bid);
            });
        }

        $rows = $query
            ->order('is_default desc,sort asc,id desc')
            ->select()
            ->toArray();

        return array_values(array_map(static function ($row) {
            $modelId = trim(strval($row['model_id'] ?? ''));
            return [
                'id' => intval($row['id'] ?? 0),
                'bid' => intval($row['bid'] ?? 0),
                'name' => trim(strval($row['name'] ?? '')) ?: '我的数字人',
                'sex' => intval($row['sex'] ?? 1),
                'platform_code' => trim(strval($row['platform_code'] ?? 'VPro')) ?: 'VPro',
                'sample_url' => trim(strval($row['sample_url'] ?? '')),
                'model_id' => $modelId,
                'task_group_id' => intval($row['task_group_id'] ?? 0),
                'is_default' => intval($row['is_default'] ?? 0) === 1 ? 1 : 0,
                'status' => intval($row['status'] ?? 1),
                'source_type' => trim(strval($row['source_type'] ?? 'member')) ?: 'member',
                'remark' => trim(strval($row['remark'] ?? '')),
                'provider_status_text' => $modelId !== '' ? '已训练' : '待训练',
                'create_time' => intval($row['create_time'] ?? 0),
                'update_time' => intval($row['update_time'] ?? 0),
                'create_time_text' => !empty($row['create_time']) ? date('Y-m-d H:i:s', intval($row['create_time'])) : '',
            ];
        }, $rows));
    }

    private static function getMemberVoiceAssetRows(int $aid, int $uid, int $bid = 0): array
    {
        $query = Db::name('zhuibao_member_voice_profile')
            ->field('id,bid,name,sample_url,voice_id,sex,language,instruction,is_default,status,source_type,remark,create_time,update_time')
            ->where('aid', $aid)
            ->where('uid', $uid)
            ->where('status', 1)
            ->where('delete_time', 0);

        if ($bid > 0) {
            $query->where(function ($subQuery) use ($bid) {
                $subQuery->where('bid', 0)->whereOr('bid', $bid);
            });
        }

        $rows = $query
            ->order('is_default desc,sort asc,id desc')
            ->select()
            ->toArray();

        return array_values(array_map(static function ($row) {
            $voiceId = trim(strval($row['voice_id'] ?? ''));
            return [
                'id' => intval($row['id'] ?? 0),
                'bid' => intval($row['bid'] ?? 0),
                'name' => trim(strval($row['name'] ?? '')) ?: '我的声音',
                'sample_url' => trim(strval($row['sample_url'] ?? '')),
                'voice_id' => $voiceId,
                'sex' => intval($row['sex'] ?? 0),
                'language' => trim(strval($row['language'] ?? 'zh')) ?: 'zh',
                'instruction' => trim(strval($row['instruction'] ?? '')),
                'is_default' => intval($row['is_default'] ?? 0) === 1 ? 1 : 0,
                'status' => intval($row['status'] ?? 1),
                'source_type' => trim(strval($row['source_type'] ?? 'member')) ?: 'member',
                'remark' => trim(strval($row['remark'] ?? '')),
                'provider_status_text' => $voiceId !== '' ? '已克隆' : '待克隆',
                'create_time' => intval($row['create_time'] ?? 0),
                'update_time' => intval($row['update_time'] ?? 0),
                'create_time_text' => !empty($row['create_time']) ? date('Y-m-d H:i:s', intval($row['create_time'])) : '',
            ];
        }, $rows));
    }

    private static function getMemberMediaAssetRows(int $aid, int $uid, int $bid = 0, int $limit = 120): array
    {
        $query = Db::name('admin_upload')
            ->field('id,name,url,type,size,width,height,createtime,platform,merchant_id')
            ->where('aid', $aid)
            ->where('uid', $uid)
            ->where('isdel', 0)
            ->where('type', '<>', 'pem');
        self::applyMemberMediaAssetScope($query);

        if ($bid > 0) {
            $query->where(function ($subQuery) use ($bid) {
                $subQuery->where('merchant_id', 0)->whereOr('merchant_id', $bid);
            });
        }

        $rows = $query
            ->order('createtime desc,id desc')
            ->limit(max(1, $limit))
            ->select()
            ->toArray();

        return array_values(array_map(static function ($row) {
            $type = strtolower(trim(strval($row['type'] ?? '')));
            $category = self::resolveMediaAssetCategory($type);
            $platform = trim(strval($row['platform'] ?? ''));
            return [
                'id' => intval($row['id'] ?? 0),
                'name' => trim(strval($row['name'] ?? '')) ?: '用户素材',
                'url' => trim(strval($row['url'] ?? '')),
                'type' => $type,
                'category' => $category,
                'category_text' => self::resolveMediaAssetCategoryText($category),
                'size' => intval($row['size'] ?? 0),
                'size_text' => self::formatMediaAssetSize(intval($row['size'] ?? 0)),
                'width' => intval($row['width'] ?? 0),
                'height' => intval($row['height'] ?? 0),
                'platform' => $platform,
                'source_text' => self::resolveMediaAssetSourceText($platform, trim(strval($row['name'] ?? ''))),
                'merchant_id' => intval($row['merchant_id'] ?? 0),
                'create_time' => intval($row['createtime'] ?? 0),
                'create_time_text' => !empty($row['createtime']) ? date('Y-m-d H:i:s', intval($row['createtime'])) : '',
            ];
        }, $rows));
    }

    private static function applyMemberMediaAssetScope($query): void
    {
        $query->where(function ($builder) {
            $builder->where('platform', self::MEMBER_MEDIA_PLATFORM)
                ->whereOr(function ($subQuery) {
                    $subQuery->where('platform', 'vue')
                        ->where(function ($nameQuery) {
                            $nameQuery->where('name', 'like', 'zhuibao_%')
                                ->whereOr('name', 'like', 'rhflow_%');
                        });
                });
        });
    }

    private static function resolveMediaAssetCategory(string $type): string
    {
        $imageTypes = ['jpg', 'jpeg', 'png', 'bmp', 'gif', 'webp', 'svg'];
        $videoTypes = ['mp4', 'mov', 'm4v', 'avi', 'mkv', 'webm', 'ogg', 'ogv', 'wmv', 'flv', '3gp', 'mpeg', 'mpg'];
        $audioTypes = ['mp3', 'wav', 'm4a', 'aac', 'flac', 'oga', 'opus', 'amr', 'wma', 'weba'];

        if (in_array($type, $imageTypes, true)) {
            return 'image';
        }
        if (in_array($type, $videoTypes, true)) {
            return 'video';
        }
        if (in_array($type, $audioTypes, true)) {
            return 'audio';
        }
        return 'file';
    }

    private static function resolveMediaAssetCategoryText(string $category): string
    {
        $map = [
            'image' => '图片',
            'video' => '视频',
            'audio' => '音频',
            'file' => '文件',
        ];
        return $map[$category] ?? '文件';
    }

    private static function resolveMediaAssetSourceText(string $platform, string $name): string
    {
        if ($platform === self::MEMBER_MEDIA_PLATFORM) {
            return '用户端上传';
        }
        if ($platform === 'vue' && strpos($name, 'rhflow_') === 0) {
            return 'AI智能体生成';
        }
        if ($platform === 'vue' && strpos($name, 'zhuibao_') === 0) {
            return '一键追爆生成';
        }
        return '素材库';
    }

    private static function formatMediaAssetSize(int $size): string
    {
        if ($size <= 0) {
            return '';
        }
        if ($size >= 1048576) {
            return round($size / 1048576, 2) . ' MB';
        }
        if ($size >= 1024) {
            return round($size / 1024, 2) . ' KB';
        }
        return $size . ' B';
    }

    public static function grantMemberPackage(int $aid, int $operatorId, string $operatorType, array $info): array
    {
        self::ensureAidDefaults($aid);

        $uid = intval($info['uid'] ?? 0);
        $bid = max(0, intval($info['bid'] ?? 0));
        $packageId = intval($info['package_id'] ?? 0);
        $giftedCredit = array_key_exists('gifted_credit', $info) ? max(0, intval($info['gifted_credit'] ?? 0)) : null;
        $remark = trim(strval($info['remark'] ?? ''));
        $effectiveTime = intval($info['effective_time'] ?? 0);
        $expireTime = intval($info['expire_time'] ?? 0);
        $sourceType = trim(strval($info['source_type'] ?? 'admin')) ?: 'admin';
        $sourceId = max(0, intval($info['source_id'] ?? 0));

        if ($uid <= 0) {
            throw new \RuntimeException('请选择会员');
        }
        if ($packageId <= 0) {
            throw new \RuntimeException('请选择套餐');
        }

        $member = Db::name('member')
            ->where('aid', $aid)
            ->where('id', $uid)
            ->find();
        if (!$member) {
            throw new \RuntimeException('会员不存在');
        }

        $package = Db::name('zhuibao_package')
            ->where('aid', $aid)
            ->where('id', $packageId)
            ->where('status', 1)
            ->find();
        if (!$package) {
            throw new \RuntimeException('套餐不存在或已停用');
        }
        if (self::isCreditRechargePackage($package)) {
            return self::grantMemberCreditRecharge($aid, $operatorId, $operatorType, $info);
        }
        if ($giftedCredit === null) {
            $giftedCredit = max(0, intval($package['gift_credit_amount'] ?? 0));
        }

        $now = time();
        $effectiveTime = $effectiveTime > 0 ? $effectiveTime : $now;
        if ($expireTime <= 0) {
            $validityDays = intval($package['validity_days'] ?? 0);
            $expireTime = $validityDays > 0 ? strtotime('+' . $validityDays . ' day', $effectiveTime) : 0;
        }

        $creditAmount = max(0, intval($package['credit_amount'] ?? 0));
        $totalGrant = $creditAmount + $giftedCredit;

        Db::startTrans();
        try {
            $packageGrantId = intval(Db::name('zhuibao_member_package')->insertGetId([
                'aid' => $aid,
                'bid' => $bid,
                'uid' => $uid,
                'package_id' => $packageId,
                'package_name' => strval($package['name']),
                'granted_credit' => $creditAmount,
                'gifted_credit' => $giftedCredit,
                'rights_snapshot_json' => strval($package['rights_json'] ?? ''),
                'effective_time' => $effectiveTime,
                'expire_time' => $expireTime,
                'status' => 1,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'operator_type' => $operatorType,
                'operator_id' => $operatorId,
                'remark' => $remark,
                'create_time' => $now,
                'update_time' => $now,
            ]));

            $creditRow = Db::name('zhuibao_member_credit')
                ->where('aid', $aid)
                ->where('bid', $bid)
                ->where('uid', $uid)
                ->lock(true)
                ->find();

            $beforeBalance = intval($creditRow['credit_balance'] ?? 0);
            $beforeFrozen = intval($creditRow['credit_frozen'] ?? 0);
            $beforeTotal = intval($creditRow['credit_total'] ?? 0);

            $updateData = [
                'credit_total' => $beforeTotal + $totalGrant,
                'credit_balance' => $beforeBalance + $totalGrant,
                'last_recharge_time' => $now,
                'expire_time' => $expireTime,
                'status' => 1,
                'update_time' => $now,
            ];

            if ($creditRow) {
                Db::name('zhuibao_member_credit')->where('id', intval($creditRow['id']))->update($updateData);
            } else {
                Db::name('zhuibao_member_credit')->insert(array_merge($updateData, [
                    'aid' => $aid,
                    'bid' => $bid,
                    'uid' => $uid,
                    'credit_used' => 0,
                    'credit_frozen' => 0,
                    'create_time' => $now,
                ]));
            }

            Db::name('zhuibao_credit_log')->insert([
                'aid' => $aid,
                'bid' => $bid,
                'uid' => $uid,
                'task_id' => 0,
                'step_id' => 0,
                'step_code' => '',
                'rule_code' => '',
                'package_id' => $packageGrantId,
                'change_type' => 'recharge',
                'credit_change' => $totalGrant,
                'before_balance' => $beforeBalance,
                'after_balance' => $beforeBalance + $totalGrant,
                'before_frozen' => $beforeFrozen,
                'after_frozen' => $beforeFrozen,
                'biz_no' => 'package_' . $packageGrantId,
                'operator_type' => $operatorType,
                'operator_id' => $operatorId,
                'remark' => $remark !== '' ? $remark : ('开通套餐：' . strval($package['name'])),
                'create_time' => $now,
                'update_time' => $now,
            ]);

            self::syncPlatformMemberLevelByPackage($aid, $uid, $package, $expireTime, $now);

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        return self::getMemberCreditList($aid, 1, 1, strval($uid))['list'][0] ?? [];
    }

    public static function adjustMemberCredit(int $aid, int $operatorId, string $operatorType, array $info): array
    {
        self::ensureAidDefaults($aid);
        $creditUnitLabel = self::getCreditUnitLabel($aid);
        $uid = intval($info['uid'] ?? 0);
        $bid = max(0, intval($info['bid'] ?? 0));
        $delta = intval($info['credit_change'] ?? 0);
        $remark = trim(strval($info['remark'] ?? ''));

        if ($uid <= 0) {
            throw new \RuntimeException('请选择会员');
        }
        if ($delta === 0) {
            throw new \RuntimeException('调整' . $creditUnitLabel . '不能为 0');
        }

        $member = Db::name('member')
            ->where('aid', $aid)
            ->where('id', $uid)
            ->find();
        if (!$member) {
            throw new \RuntimeException('会员不存在');
        }

        $now = time();
        Db::startTrans();
        try {
            $creditRow = Db::name('zhuibao_member_credit')
                ->where('aid', $aid)
                ->where('bid', $bid)
                ->where('uid', $uid)
                ->lock(true)
                ->find();

            $beforeBalance = intval($creditRow['credit_balance'] ?? 0);
            $beforeFrozen = intval($creditRow['credit_frozen'] ?? 0);
            $beforeTotal = intval($creditRow['credit_total'] ?? 0);
            $beforeUsed = intval($creditRow['credit_used'] ?? 0);
            $afterBalance = $beforeBalance + $delta;
            if ($afterBalance < 0) {
                throw new \RuntimeException('剩余' . $creditUnitLabel . '不足，无法扣减');
            }

            $updateData = [
                'credit_total' => $delta > 0 ? ($beforeTotal + $delta) : $beforeTotal,
                'credit_used' => $delta < 0 ? ($beforeUsed + abs($delta)) : $beforeUsed,
                'credit_balance' => $afterBalance,
                'last_recharge_time' => $delta > 0 ? $now : intval($creditRow['last_recharge_time'] ?? 0),
                'last_used_time' => $delta < 0 ? $now : intval($creditRow['last_used_time'] ?? 0),
                'status' => 1,
                'update_time' => $now,
            ];

            if ($creditRow) {
                Db::name('zhuibao_member_credit')->where('id', intval($creditRow['id']))->update($updateData);
            } else {
                if ($delta < 0) {
                    throw new \RuntimeException('当前会员还没有可扣减的' . $creditUnitLabel);
                }
                Db::name('zhuibao_member_credit')->insert(array_merge($updateData, [
                    'aid' => $aid,
                    'bid' => $bid,
                    'uid' => $uid,
                    'credit_frozen' => 0,
                    'expire_time' => 0,
                    'create_time' => $now,
                ]));
            }

            Db::name('zhuibao_credit_log')->insert([
                'aid' => $aid,
                'bid' => $bid,
                'uid' => $uid,
                'task_id' => 0,
                'step_id' => 0,
                'step_code' => '',
                'rule_code' => '',
                'package_id' => 0,
                'change_type' => 'adjust',
                'credit_change' => $delta,
                'before_balance' => $beforeBalance,
                'after_balance' => $afterBalance,
                'before_frozen' => $beforeFrozen,
                'after_frozen' => $beforeFrozen,
                'biz_no' => 'adjust_' . $aid . '_' . $uid . '_' . $now,
                'operator_type' => $operatorType,
                'operator_id' => $operatorId,
                'remark' => $remark !== '' ? $remark : ($delta > 0 ? ('后台补充' . $creditUnitLabel) : ('后台扣减' . $creditUnitLabel)),
                'create_time' => $now,
                'update_time' => $now,
            ]);

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        return self::getMemberCreditList($aid, 1, 1, strval($uid))['list'][0] ?? [];
    }

    /**
     * 跨插件查询会员额度余额
     */
    public static function getBalance(int $aid, int $bid, int $uid): int
    {
        $row = Db::name('zhuibao_member_credit')
            ->where('aid', $aid)
            ->where('bid', $bid)
            ->where('uid', $uid)
            ->find();
        return max(0, intval($row['credit_balance'] ?? 0));
    }

    /**
     * 跨插件扣除会员额度（供 runninghubflow 等外部插件调用）
     * 余额不足抛异常；成功返回扣点后余额。
     */
    public static function deductForExternal(int $aid, int $bid, int $uid, int $amount, string $bizType, string $bizId, string $remark = ''): int
    {
        $amount = intval($amount);
        if ($uid <= 0) {
            throw new \RuntimeException('请先登录会员账号');
        }
        if ($amount <= 0) {
            return self::getBalance($aid, $bid, $uid);
        }

        $now = time();
        Db::startTrans();
        try {
            $creditRow = Db::name('zhuibao_member_credit')
                ->where('aid', $aid)
                ->where('bid', $bid)
                ->where('uid', $uid)
                ->lock(true)
                ->find();

            $beforeBalance = intval($creditRow['credit_balance'] ?? 0);
            if ($beforeBalance < $amount) {
                $creditUnitLabel = self::getCreditUnitLabel($aid);
                throw new \RuntimeException($creditUnitLabel . '余额不足');
            }
            $afterBalance = $beforeBalance - $amount;
            $beforeFrozen = intval($creditRow['credit_frozen'] ?? 0);
            $beforeUsed = intval($creditRow['credit_used'] ?? 0);

            Db::name('zhuibao_member_credit')->where('id', intval($creditRow['id']))->update([
                'credit_used' => $beforeUsed + $amount,
                'credit_balance' => $afterBalance,
                'last_used_time' => $now,
                'status' => 1,
                'update_time' => $now,
            ]);

            Db::name('zhuibao_credit_log')->insert([
                'aid' => $aid,
                'bid' => $bid,
                'uid' => $uid,
                'task_id' => 0,
                'step_id' => 0,
                'step_code' => $bizType,
                'rule_code' => '',
                'package_id' => 0,
                'change_type' => 'consume',
                'credit_change' => -$amount,
                'before_balance' => $beforeBalance,
                'after_balance' => $afterBalance,
                'before_frozen' => $beforeFrozen,
                'after_frozen' => $beforeFrozen,
                'biz_no' => $bizType . '_' . $bizId,
                'operator_type' => 'member',
                'operator_id' => $uid,
                'remark' => $remark !== '' ? $remark : '运行AI智能体',
                'create_time' => $now,
                'update_time' => $now,
            ]);

            Db::commit();
            return $afterBalance;
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    /**
     * 跨插件退回会员额度（任务失败时）
     */
    public static function refundForExternal(int $aid, int $bid, int $uid, int $amount, string $bizType, string $bizId, string $remark = ''): int
    {
        $amount = intval($amount);
        if ($uid <= 0 || $amount <= 0) {
            return self::getBalance($aid, $bid, $uid);
        }

        $now = time();
        Db::startTrans();
        try {
            $creditRow = Db::name('zhuibao_member_credit')
                ->where('aid', $aid)
                ->where('bid', $bid)
                ->where('uid', $uid)
                ->lock(true)
                ->find();

            $beforeBalance = intval($creditRow['credit_balance'] ?? 0);
            $afterBalance = $beforeBalance + $amount;
            $beforeFrozen = intval($creditRow['credit_frozen'] ?? 0);
            $beforeUsed = intval($creditRow['credit_used'] ?? 0);

            if ($creditRow) {
                Db::name('zhuibao_member_credit')->where('id', intval($creditRow['id']))->update([
                    'credit_used' => max(0, $beforeUsed - $amount),
                    'credit_balance' => $afterBalance,
                    'status' => 1,
                    'update_time' => $now,
                ]);
            } else {
                Db::name('zhuibao_member_credit')->insert([
                    'aid' => $aid,
                    'bid' => $bid,
                    'uid' => $uid,
                    'credit_total' => $amount,
                    'credit_used' => 0,
                    'credit_balance' => $amount,
                    'credit_frozen' => 0,
                    'expire_time' => 0,
                    'status' => 1,
                    'create_time' => $now,
                    'update_time' => $now,
                ]);
            }

            Db::name('zhuibao_credit_log')->insert([
                'aid' => $aid,
                'bid' => $bid,
                'uid' => $uid,
                'task_id' => 0,
                'step_id' => 0,
                'step_code' => $bizType,
                'rule_code' => '',
                'package_id' => 0,
                'change_type' => 'refund',
                'credit_change' => $amount,
                'before_balance' => $beforeBalance,
                'after_balance' => $afterBalance,
                'before_frozen' => $beforeFrozen,
                'after_frozen' => $beforeFrozen,
                'biz_no' => $bizType . '_' . $bizId,
                'operator_type' => 'system',
                'operator_id' => 0,
                'remark' => $remark !== '' ? $remark : 'AI智能体运行失败退回',
                'create_time' => $now,
                'update_time' => $now,
            ]);

            Db::commit();
            return $afterBalance;
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    private static function resolveStandardVideoCredit(int $aid, array $config): int
    {
        $fallback = max(1, intval($config['standard_video_credit'] ?? self::DEFAULT_STANDARD_VIDEO_CREDIT));
        if ($aid <= 0 || !class_exists(ZhuibaoAgentService::class)) {
            return $fallback;
        }

        try {
            $billing = ZhuibaoAgentService::getAgentBillingConfig($aid, self::WORKFLOW_VIDEO);
            $agentCredit = ZhuibaoAgentService::computeAgentBillingCredit($billing, 1);
            return $agentCredit > 0 ? $agentCredit : $fallback;
        } catch (\Throwable $e) {
            return $fallback;
        }
    }

    public static function ensureAidDefaults(int $aid): void
    {
        ZhuibaoInstaller::ensureInstalled();
        $now = time();

        $config = Db::name('zhuibao_credit_config')->where('aid', $aid)->find();
        if (!$config) {
            Db::name('zhuibao_credit_config')->insert([
                'aid' => $aid,
                'charge_mode' => 2,
                'package_enabled' => 1,
                'recharge_enabled' => 1,
                'credit_unit_label' => self::DEFAULT_CREDIT_UNIT_LABEL,
                'standard_video_credit' => self::DEFAULT_STANDARD_VIDEO_CREDIT,
                'low_balance_threshold' => self::DEFAULT_LOW_BALANCE_THRESHOLD,
                'insufficient_tips' => self::buildDefaultInsufficientTips(self::DEFAULT_CREDIT_UNIT_LABEL),
                'status' => 1,
                'create_time' => $now,
                'update_time' => $now,
            ]);
        }

        $existingRuleCodes = Db::name('zhuibao_credit_rule')
            ->where('aid', $aid)
            ->column('rule_code');
        $existingRuleMap = array_flip(array_map('strval', is_array($existingRuleCodes) ? $existingRuleCodes : []));

        foreach (self::defaultRules() as $index => $rule) {
            $ruleCode = strval($rule['rule_code']);
            if (isset($existingRuleMap[$ruleCode])) {
                continue;
            }

            Db::name('zhuibao_credit_rule')->insert([
                'aid' => $aid,
                'rule_code' => $ruleCode,
                'rule_name' => strval($rule['rule_name']),
                'workflow_type' => self::normalizeWorkflowType(strval($rule['workflow_type'] ?? self::WORKFLOW_VIDEO)),
                'rule_type' => intval($rule['rule_type']),
                'step_code' => strval($rule['step_code']),
                'billing_mode' => intval($rule['billing_mode'] ?? 1),
                'charge_credit' => intval($rule['charge_credit']),
                'base_credit' => intval($rule['base_credit'] ?? 0),
                'free_seconds' => intval($rule['free_seconds'] ?? 0),
                'unit_seconds' => intval($rule['unit_seconds'] ?? 30),
                'unit_credit' => intval($rule['unit_credit'] ?? 0),
                'max_credit' => intval($rule['max_credit'] ?? 0),
                'charge_timing' => intval($rule['charge_timing']),
                'is_refund_on_fail' => intval($rule['is_refund_on_fail']),
                'status' => 1,
                'sort' => ($index + 1) * 10,
                'remark' => strval($rule['remark']),
                'create_time' => $now,
                'update_time' => $now,
            ]);
        }

        $packageCount = intval(Db::name('zhuibao_package')->where('aid', $aid)->count());
        if ($packageCount <= 0) {
            foreach (self::defaultPackages() as $index => $package) {
                Db::name('zhuibao_package')->insert([
                    'aid' => $aid,
                    'name' => strval($package['name']),
                    'description' => strval($package['description']),
                    'group_key' => strval($package['group_key'] ?? 'member'),
                    'group_name' => strval($package['group_name'] ?? '会员套餐'),
                    'subtitle' => strval($package['subtitle'] ?? ''),
                    'badge_text' => strval($package['badge_text'] ?? ''),
                    'button_text' => strval($package['button_text'] ?? ''),
                    'button_action' => strval($package['button_action'] ?? 'pay'),
                    'button_tips' => strval($package['button_tips'] ?? ''),
                    'style_key' => strval($package['style_key'] ?? 'purple'),
                    'price' => strval($package['price']),
                    'credit_amount' => intval($package['credit_amount']),
                    'validity_days' => intval($package['validity_days']),
                    'rights_json' => json_encode($package['rights_json'], JSON_UNESCAPED_UNICODE),
                    'open_reward_json' => json_encode($package['open_reward_json'] ?? [], JSON_UNESCAPED_UNICODE),
                    'recharge_ratio' => strval($package['recharge_ratio'] ?? '0'),
                    'gift_credit_amount' => intval($package['gift_credit_amount'] ?? 0),
                    'recharge_reward_json' => json_encode($package['recharge_reward_json'] ?? [], JSON_UNESCAPED_UNICODE),
                    'sync_platform_level' => intval($package['sync_platform_level'] ?? 0) === 1 ? 1 : 0,
                    'sync_platform_levelid' => max(0, intval($package['sync_platform_levelid'] ?? 0)),
                    'is_default' => intval($package['is_default']),
                    'status' => 1,
                    'sort' => intval($package['sort'] ?? (($index + 1) * 10)),
                    'create_time' => $now,
                    'update_time' => $now,
                ]);
            }
        }

        $rechargePackageCount = intval(Db::name('zhuibao_package')
            ->where('aid', $aid)
            ->where('group_key', 'recharge')
            ->count());
        if ($rechargePackageCount <= 0) {
            foreach (self::defaultRechargePackages() as $index => $package) {
                Db::name('zhuibao_package')->insert([
                    'aid' => $aid,
                    'name' => strval($package['name']),
                    'description' => strval($package['description']),
                    'group_key' => 'recharge',
                    'group_name' => strval($package['group_name'] ?? '非会员'),
                    'subtitle' => strval($package['subtitle'] ?? ''),
                    'badge_text' => strval($package['badge_text'] ?? ''),
                    'button_text' => strval($package['button_text'] ?? '立即充值'),
                    'button_action' => strval($package['button_action'] ?? 'pay'),
                    'button_tips' => strval($package['button_tips'] ?? ''),
                    'style_key' => strval($package['style_key'] ?? 'purple'),
                    'price' => strval($package['price']),
                    'credit_amount' => intval($package['credit_amount']),
                    'validity_days' => intval($package['validity_days']),
                    'rights_json' => json_encode($package['rights_json'] ?? [], JSON_UNESCAPED_UNICODE),
                    'open_reward_json' => json_encode($package['open_reward_json'] ?? [], JSON_UNESCAPED_UNICODE),
                    'recharge_ratio' => strval($package['recharge_ratio'] ?? '0'),
                    'gift_credit_amount' => intval($package['gift_credit_amount'] ?? 0),
                    'recharge_reward_json' => json_encode($package['recharge_reward_json'] ?? [], JSON_UNESCAPED_UNICODE),
                    'sync_platform_level' => intval($package['sync_platform_level'] ?? 0) === 1 ? 1 : 0,
                    'sync_platform_levelid' => max(0, intval($package['sync_platform_levelid'] ?? 0)),
                    'is_default' => intval($package['is_default'] ?? 0),
                    'status' => 1,
                    'sort' => intval($package['sort'] ?? (($index + 1) * 10)),
                    'create_time' => $now,
                    'update_time' => $now,
                ]);
            }
        }
    }

    private static function getActiveMemberPackage(int $aid, int $bid, int $uid): array
    {
        $now = time();
        $query = Db::name('zhuibao_member_package')
            ->alias('mp')
            ->leftJoin('zhuibao_package p', 'mp.package_id = p.id')
            ->field('mp.*, p.description')
            ->where('mp.aid', $aid)
            ->where('mp.uid', $uid)
            ->where('mp.status', 1)
            ->where(function ($subQuery) {
                $subQuery->where('p.group_key', 'member')->whereOr('p.group_key', '');
            });

        if ($bid > 0) {
            $query->where(function ($subQuery) use ($bid) {
                $subQuery->where('mp.bid', $bid)->whereOr('mp.bid', 0);
            });
        } else {
            $query->where('mp.bid', 0);
        }

        $query
            ->where(function ($subQuery) use ($now) {
                $subQuery->where('mp.effective_time', 0)->whereOr('mp.effective_time', '<=', $now);
            })
            ->where(function ($subQuery) use ($now) {
                $subQuery->where('mp.expire_time', 0)->whereOr('mp.expire_time', '>=', $now);
            });

        $row = $query
            ->order('mp.expire_time asc, mp.id desc')
            ->find();

        return is_array($row) ? $row : [];
    }

    private static function syncPlatformMemberLevelByPackage(int $aid, int $uid, array $package, int $expireTime, int $now): void
    {
        if ($aid <= 0 || $uid <= 0) {
            return;
        }
        if (intval($package['sync_platform_level'] ?? 0) !== 1) {
            return;
        }

        $targetLevelId = max(0, intval($package['sync_platform_levelid'] ?? 0));
        if ($targetLevelId <= 0) {
            throw new \RuntimeException('套餐未配置同步的平台会员等级');
        }

        $targetLevel = Db::name('member_level')
            ->where('aid', $aid)
            ->where('id', $targetLevelId)
            ->field('id,name')
            ->find();
        if (!$targetLevel) {
            throw new \RuntimeException('套餐同步的平台会员等级不存在');
        }

        $updateData = [
            'levelid' => $targetLevelId,
        ];
        if (self::tableHasColumn('member', 'levelstarttime')) {
            $updateData['levelstarttime'] = $now;
        }
        if (self::tableHasColumn('member', 'levelendtime')) {
            $updateData['levelendtime'] = $expireTime > 0 ? $expireTime : 0;
        }

        Db::name('member')
            ->where('aid', $aid)
            ->where('id', $uid)
            ->update($updateData);

        if (
            class_exists('\\app\\admin\\common\\Wechat')
            && method_exists('\\app\\admin\\common\\Wechat', 'updatemembercard')
        ) {
            try {
                \app\admin\common\Wechat::updatemembercard($aid, $uid);
            } catch (\Throwable $e) {
                // 同步电子会员卡失败不影响会员等级落库。
            }
        }
    }

    private static function tableHasColumn(string $table, string $column): bool
    {
        static $tableFieldsCache = [];
        $cacheKey = $table;
        if (!array_key_exists($cacheKey, $tableFieldsCache)) {
            try {
                $tableFieldsCache[$cacheKey] = Db::name($table)->getTableFields();
            } catch (\Throwable $e) {
                $tableFieldsCache[$cacheKey] = [];
            }
        }

        return in_array($column, $tableFieldsCache[$cacheKey], true);
    }

    private static function defaultRules(): array
    {
        return [
            [
                'rule_code' => 'rewrite_copy',
                'rule_name' => '文案改写',
                'workflow_type' => self::WORKFLOW_VIDEO,
                'rule_type' => 1,
                'step_code' => 'rewrite_copy',
                'billing_mode' => 1,
                'charge_credit' => 1,
                'base_credit' => 0,
                'free_seconds' => 0,
                'unit_seconds' => 30,
                'unit_credit' => 0,
                'max_credit' => 0,
                'charge_timing' => 1,
                'is_refund_on_fail' => 1,
                'remark' => '改写文案时冻结 1 ' . self::DEFAULT_CREDIT_UNIT_LABEL,
            ],
            [
                'rule_code' => 'generate_audio',
                'rule_name' => '音频生成',
                'workflow_type' => self::WORKFLOW_VIDEO,
                'rule_type' => 1,
                'step_code' => 'generate_audio',
                'billing_mode' => 2,
                'charge_credit' => 0,
                'base_credit' => 0,
                'free_seconds' => 0,
                'unit_seconds' => 30,
                'unit_credit' => 1,
                'max_credit' => 0,
                'charge_timing' => 1,
                'is_refund_on_fail' => 1,
                'remark' => '生成音频时按时长阶梯冻结额度',
            ],
            [
                'rule_code' => 'generate_avatar_video',
                'rule_name' => '数字人生成',
                'workflow_type' => self::WORKFLOW_VIDEO,
                'rule_type' => 1,
                'step_code' => 'generate_avatar_video',
                'billing_mode' => 3,
                'charge_credit' => 0,
                'base_credit' => 2,
                'free_seconds' => 0,
                'unit_seconds' => 15,
                'unit_credit' => 2,
                'max_credit' => 0,
                'charge_timing' => 1,
                'is_refund_on_fail' => 1,
                'remark' => '生成数字人视频时按基础点 + 时长冻结额度',
            ],
            [
                'rule_code' => 'smart_edit',
                'rule_name' => '智能剪辑',
                'workflow_type' => self::WORKFLOW_VIDEO,
                'rule_type' => 1,
                'step_code' => 'smart_edit',
                'billing_mode' => 3,
                'charge_credit' => 0,
                'base_credit' => 2,
                'free_seconds' => 0,
                'unit_seconds' => 30,
                'unit_credit' => 2,
                'max_credit' => 0,
                'charge_timing' => 1,
                'is_refund_on_fail' => 1,
                'remark' => '执行智能剪辑时按基础点 + 时长冻结额度',
            ],
            [
                'rule_code' => 'smart_edit_english',
                'rule_name' => '英文翻译',
                'workflow_type' => self::WORKFLOW_VIDEO,
                'rule_type' => 2,
                'step_code' => 'smart_edit',
                'billing_mode' => 1,
                'charge_credit' => 2,
                'base_credit' => 0,
                'free_seconds' => 0,
                'unit_seconds' => 30,
                'unit_credit' => 0,
                'max_credit' => 0,
                'charge_timing' => 1,
                'is_refund_on_fail' => 1,
                'remark' => '开启英文双语字幕时额外冻结 2 ' . self::DEFAULT_CREDIT_UNIT_LABEL,
            ],
        ];
    }

    private static function ruleSettingPresets(): array
    {
        return [
            [
                'rule_code' => 'extract_copy',
                'rule_name' => '文案提取',
                'workflow_type' => self::WORKFLOW_VIDEO,
                'rule_type' => 1,
                'step_code' => 'extract_copy',
                'billing_mode' => 1,
                'charge_credit' => 0,
                'base_credit' => 0,
                'free_seconds' => 0,
                'unit_seconds' => 30,
                'unit_credit' => 0,
                'max_credit' => 0,
                'charge_timing' => 1,
                'is_refund_on_fail' => 1,
                'sort' => 10,
                'remark' => '执行文案提取时冻结额度',
            ],
            [
                'rule_code' => 'rewrite_copy',
                'rule_name' => '文案改写',
                'workflow_type' => self::WORKFLOW_VIDEO,
                'rule_type' => 1,
                'step_code' => 'rewrite_copy',
                'billing_mode' => 1,
                'charge_credit' => 1,
                'base_credit' => 0,
                'free_seconds' => 0,
                'unit_seconds' => 30,
                'unit_credit' => 0,
                'max_credit' => 0,
                'charge_timing' => 1,
                'is_refund_on_fail' => 1,
                'sort' => 20,
                'remark' => '执行文案改写时冻结额度',
            ],
            [
                'rule_code' => 'generate_audio',
                'rule_name' => '音频生成',
                'workflow_type' => self::WORKFLOW_VIDEO,
                'rule_type' => 1,
                'step_code' => 'generate_audio',
                'billing_mode' => 2,
                'charge_credit' => 0,
                'base_credit' => 0,
                'free_seconds' => 0,
                'unit_seconds' => 30,
                'unit_credit' => 1,
                'max_credit' => 0,
                'charge_timing' => 1,
                'is_refund_on_fail' => 1,
                'sort' => 30,
                'remark' => '执行音频生成时按时长阶梯冻结额度',
            ],
            [
                'rule_code' => 'generate_avatar_video',
                'rule_name' => '数字人生成',
                'workflow_type' => self::WORKFLOW_VIDEO,
                'rule_type' => 1,
                'step_code' => 'generate_avatar_video',
                'billing_mode' => 3,
                'charge_credit' => 0,
                'base_credit' => 2,
                'free_seconds' => 0,
                'unit_seconds' => 15,
                'unit_credit' => 2,
                'max_credit' => 0,
                'charge_timing' => 1,
                'is_refund_on_fail' => 1,
                'sort' => 40,
                'remark' => '执行数字人视频生成时按基础点 + 时长冻结额度',
            ],
            [
                'rule_code' => 'smart_edit',
                'rule_name' => '智能剪辑',
                'workflow_type' => self::WORKFLOW_VIDEO,
                'rule_type' => 1,
                'step_code' => 'smart_edit',
                'billing_mode' => 3,
                'charge_credit' => 0,
                'base_credit' => 2,
                'free_seconds' => 0,
                'unit_seconds' => 30,
                'unit_credit' => 2,
                'max_credit' => 0,
                'charge_timing' => 1,
                'is_refund_on_fail' => 1,
                'sort' => 50,
                'remark' => '执行智能剪辑时按基础点 + 时长冻结额度',
            ],
            [
                'rule_code' => 'generate_title_topic',
                'rule_name' => '标题与话题生成',
                'workflow_type' => self::WORKFLOW_VIDEO,
                'rule_type' => 1,
                'step_code' => 'generate_title_topic',
                'billing_mode' => 1,
                'charge_credit' => 0,
                'base_credit' => 0,
                'free_seconds' => 0,
                'unit_seconds' => 30,
                'unit_credit' => 0,
                'max_credit' => 0,
                'charge_timing' => 1,
                'is_refund_on_fail' => 1,
                'sort' => 60,
                'remark' => '执行标题与话题生成时冻结额度',
            ],
            [
                'rule_code' => 'generate_cover',
                'rule_name' => '封面生成',
                'workflow_type' => self::WORKFLOW_VIDEO,
                'rule_type' => 1,
                'step_code' => 'generate_cover',
                'billing_mode' => 1,
                'charge_credit' => 0,
                'base_credit' => 0,
                'free_seconds' => 0,
                'unit_seconds' => 30,
                'unit_credit' => 0,
                'max_credit' => 0,
                'charge_timing' => 1,
                'is_refund_on_fail' => 1,
                'sort' => 70,
                'remark' => '执行封面生成时冻结额度',
            ],
            [
                'rule_code' => 'publish_video',
                'rule_name' => '发布视频',
                'workflow_type' => self::WORKFLOW_VIDEO,
                'rule_type' => 1,
                'step_code' => 'publish_video',
                'billing_mode' => 1,
                'charge_credit' => 0,
                'base_credit' => 0,
                'free_seconds' => 0,
                'unit_seconds' => 30,
                'unit_credit' => 0,
                'max_credit' => 0,
                'charge_timing' => 1,
                'is_refund_on_fail' => 1,
                'sort' => 80,
                'remark' => '执行发布视频时冻结额度',
            ],
            [
                'rule_code' => 'smart_edit_english',
                'rule_name' => '英文翻译附加项',
                'workflow_type' => self::WORKFLOW_VIDEO,
                'rule_type' => 2,
                'step_code' => 'smart_edit',
                'billing_mode' => 1,
                'charge_credit' => 2,
                'base_credit' => 0,
                'free_seconds' => 0,
                'unit_seconds' => 30,
                'unit_credit' => 0,
                'max_credit' => 0,
                'charge_timing' => 1,
                'is_refund_on_fail' => 1,
                'sort' => 90,
                'remark' => '开启英文双语字幕时额外冻结额度',
            ],
            [
                'rule_code' => 'graphic_outline',
                'rule_name' => '图文大纲',
                'workflow_type' => self::WORKFLOW_GRAPHIC,
                'rule_type' => 1,
                'step_code' => 'outline',
                'billing_mode' => 1,
                'charge_credit' => 0,
                'base_credit' => 0,
                'free_seconds' => 0,
                'unit_seconds' => 30,
                'unit_credit' => 0,
                'max_credit' => 0,
                'charge_timing' => 1,
                'is_refund_on_fail' => 1,
                'sort' => 110,
                'remark' => '执行图文大纲生成时冻结额度',
            ],
            [
                'rule_code' => 'graphic_content',
                'rule_name' => '平台文案',
                'workflow_type' => self::WORKFLOW_GRAPHIC,
                'rule_type' => 1,
                'step_code' => 'content',
                'billing_mode' => 1,
                'charge_credit' => 0,
                'base_credit' => 0,
                'free_seconds' => 0,
                'unit_seconds' => 30,
                'unit_credit' => 0,
                'max_credit' => 0,
                'charge_timing' => 1,
                'is_refund_on_fail' => 1,
                'sort' => 120,
                'remark' => '执行图文平台文案生成时冻结额度',
            ],
            [
                'rule_code' => 'graphic_images',
                'rule_name' => '批量出图',
                'workflow_type' => self::WORKFLOW_GRAPHIC,
                'rule_type' => 1,
                'step_code' => 'images',
                'billing_mode' => 1,
                'charge_credit' => 0,
                'base_credit' => 0,
                'free_seconds' => 0,
                'unit_seconds' => 30,
                'unit_credit' => 0,
                'max_credit' => 0,
                'charge_timing' => 1,
                'is_refund_on_fail' => 1,
                'sort' => 130,
                'remark' => '执行图文批量出图时冻结额度',
            ],
            [
                'rule_code' => 'graphic_publish',
                'rule_name' => '发布文案',
                'workflow_type' => self::WORKFLOW_GRAPHIC,
                'rule_type' => 1,
                'step_code' => 'publish',
                'billing_mode' => 1,
                'charge_credit' => 0,
                'base_credit' => 0,
                'free_seconds' => 0,
                'unit_seconds' => 30,
                'unit_credit' => 0,
                'max_credit' => 0,
                'charge_timing' => 1,
                'is_refund_on_fail' => 1,
                'sort' => 140,
                'remark' => '执行图文发布文案生成时冻结额度',
            ],
            [
                'rule_code' => 'image_generate',
                'rule_name' => '图片生成',
                'workflow_type' => self::WORKFLOW_IMAGE,
                'rule_type' => 1,
                'step_code' => 'generate',
                'billing_mode' => 1,
                'charge_credit' => 0,
                'base_credit' => 0,
                'free_seconds' => 0,
                'unit_seconds' => 30,
                'unit_credit' => 0,
                'max_credit' => 0,
                'charge_timing' => 1,
                'is_refund_on_fail' => 1,
                'sort' => 150,
                'remark' => '执行文生图生成时冻结额度',
            ],
        ];
    }

    public static function normalizeWorkflowType(string $workflowType): string
    {
        $workflowType = trim($workflowType);
        if ($workflowType === self::WORKFLOW_GRAPHIC) {
            return self::WORKFLOW_GRAPHIC;
        }
        if ($workflowType === self::WORKFLOW_IMAGE) {
            return self::WORKFLOW_IMAGE;
        }

        return self::WORKFLOW_VIDEO;
    }

    public static function getWorkflowTypeText(string $workflowType): string
    {
        $workflowType = self::normalizeWorkflowType($workflowType);
        if ($workflowType === self::WORKFLOW_GRAPHIC) {
            return '图文做图';
        }
        if ($workflowType === self::WORKFLOW_IMAGE) {
            return '图片生成';
        }

        return '视频追爆';
    }

    private static function normalizeRuleChargeFields(
        string $workflowType,
        int $billingMode,
        int $chargeCredit,
        int $baseCredit,
        int $freeSeconds,
        int $unitSeconds,
        int $unitCredit,
        int $maxCredit
    ): array {
        $workflowType = self::normalizeWorkflowType($workflowType);
        if (in_array($workflowType, [self::WORKFLOW_GRAPHIC, self::WORKFLOW_IMAGE], true)) {
            return [
                'billing_mode' => 1,
                'charge_credit' => max(0, $chargeCredit),
                'base_credit' => 0,
                'free_seconds' => 0,
                'unit_seconds' => 30,
                'unit_credit' => 0,
                'max_credit' => 0,
            ];
        }

        return [
            'billing_mode' => self::normalizeBillingMode($billingMode),
            'charge_credit' => max(0, $chargeCredit),
            'base_credit' => max(0, $baseCredit),
            'free_seconds' => max(0, $freeSeconds),
            'unit_seconds' => max(1, $unitSeconds),
            'unit_credit' => max(0, $unitCredit),
            'max_credit' => max(0, $maxCredit),
        ];
    }

    private static function normalizeBillingMode(int $billingMode): int
    {
        if ($billingMode === 2) {
            return 2;
        }
        if ($billingMode === 3) {
            return 3;
        }
        return 1;
    }

    private static function getBillingModeText(int $billingMode): string
    {
        switch (self::normalizeBillingMode($billingMode)) {
            case 2:
                return '按时长';
            case 3:
                return '基础点+时长';
            default:
                return '固定点';
        }
    }

    public static function computeRuleChargeCredit(array $rule, float $seconds): int
    {
        $billingMode = self::normalizeBillingMode(intval($rule['billing_mode'] ?? 1));
        $chargeCredit = max(0, intval($rule['charge_credit'] ?? 0));
        if ($billingMode === 1) {
            return $chargeCredit;
        }

        $baseCredit = max(0, intval($rule['base_credit'] ?? 0));
        $freeSeconds = max(0, intval($rule['free_seconds'] ?? 0));
        $unitSeconds = max(1, intval($rule['unit_seconds'] ?? 30));
        $unitCredit = max(0, intval($rule['unit_credit'] ?? 0));
        $maxCredit = max(0, intval($rule['max_credit'] ?? 0));
        $billableSeconds = max(0, ceil($seconds) - $freeSeconds);
        $tierCredit = $billableSeconds > 0 && $unitCredit > 0
            ? intval(ceil($billableSeconds / $unitSeconds) * $unitCredit)
            : 0;
        $total = $billingMode === 3 ? ($baseCredit + $tierCredit) : $tierCredit;

        if ($maxCredit > 0) {
            $total = min($total, $maxCredit);
        }
        return max(0, intval($total));
    }

    private static function computeRuleEnabledStatus(array $rule): int
    {
        $billingMode = self::normalizeBillingMode(intval($rule['billing_mode'] ?? 1));
        if ($billingMode === 1) {
            return max(0, intval($rule['charge_credit'] ?? 0)) > 0 ? 1 : 0;
        }

        $baseCredit = max(0, intval($rule['base_credit'] ?? 0));
        $unitCredit = max(0, intval($rule['unit_credit'] ?? 0));
        $unitSeconds = max(0, intval($rule['unit_seconds'] ?? 0));
        if ($billingMode === 2) {
            return ($unitCredit > 0 && $unitSeconds > 0) ? 1 : 0;
        }
        return ($baseCredit > 0 || ($unitCredit > 0 && $unitSeconds > 0)) ? 1 : 0;
    }

    private static function getMemberLevelOptions(int $aid): array
    {
        try {
            return Db::name('member_level')
                ->where('aid', $aid)
                ->field('id,name,can_agent,sort')
                ->order('sort desc,id asc')
                ->select()
                ->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private static function getAgentLevelOptions(int $aid): array
    {
        try {
            return Db::name('member_level')
                ->where('aid', $aid)
                ->where('can_agent', '<>', 0)
                ->field('id,name,can_agent,sort')
                ->order('sort desc,id asc')
                ->select()
                ->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private static function normalizeOpenRewardConfig(array $value): array
    {
        $result = [];
        foreach ($value as $levelId => $percent) {
            $id = intval($levelId);
            if ($id <= 0 && is_array($percent)) {
                $id = intval($percent['level_id'] ?? $percent['id'] ?? 0);
                $percent = $percent['percent'] ?? $percent['reward_percent'] ?? 0;
            }
            if ($id <= 0) {
                continue;
            }
            $rate = round(floatval($percent), 2);
            if ($rate < 0) {
                $rate = 0;
            }
            if ($rate > 100) {
                $rate = 100;
            }
            $result[strval($id)] = $rate;
        }
        return $result;
    }

    private static function normalizeCommissionTierConfig(array $value): array
    {
        $result = [];
        foreach ($value as $levelId => $row) {
            $id = intval($levelId);
            if (is_array($row)) {
                if ($id <= 0) {
                    $id = intval($row['level_id'] ?? $row['id'] ?? 0);
                }
                $commission1 = $row['commission1'] ?? $row['level1'] ?? $row['percent'] ?? $row['reward_percent'] ?? 0;
                $commission2 = $row['commission2'] ?? $row['level2'] ?? 0;
                $commission3 = $row['commission3'] ?? $row['level3'] ?? 0;
            } else {
                $commission1 = $row;
                $commission2 = 0;
                $commission3 = 0;
            }
            if ($id <= 0) {
                continue;
            }
            $result[strval($id)] = [
                'commission1' => self::normalizePercentValue($commission1),
                'commission2' => self::normalizePercentValue($commission2),
                'commission3' => self::normalizePercentValue($commission3),
            ];
        }
        return $result;
    }

    private static function normalizePercentValue($value): float
    {
        $rate = round(floatval($value), 2);
        if ($rate < 0) {
            return 0.0;
        }
        if ($rate > 100) {
            return 100.0;
        }
        return $rate;
    }

    private static function normalizeRechargeRatio($value): float
    {
        $ratio = round(floatval($value), 4);
        if ($ratio < 0) {
            return 0.0;
        }
        if ($ratio > 999999) {
            return 999999.0;
        }
        return $ratio;
    }

    private static function formatPackageDisplayFields(array &$item): void
    {
        $item['credit_unit_label'] = self::getCreditUnitLabel(intval($item['aid'] ?? 0));
        $groupKey = self::normalizePackageGroupKey(strval($item['group_key'] ?? 'member'));
        $item['group_key'] = $groupKey;
        $item['group_name'] = self::cleanPackageText(strval($item['group_name'] ?? ''), 50);
        if (in_array($groupKey, ['recharge', 'non_member'], true) && in_array($item['group_name'], ['', '创作点充值', '充值包'], true)) {
            $item['group_name'] = '非会员';
        } elseif ($groupKey === 'member' && in_array($item['group_name'], ['', '长期权益'], true)) {
            $item['group_name'] = '会员套餐';
        } elseif ($item['group_name'] === '') {
            $item['group_name'] = self::defaultPackageGroupName($groupKey);
        }
        $item['subtitle'] = self::cleanPackageText(strval($item['subtitle'] ?? ''), 120);
        if ($item['subtitle'] === '') {
            $item['subtitle'] = self::buildDefaultPackageSubtitle($item);
        }
        $item['badge_text'] = self::cleanPackageText(strval($item['badge_text'] ?? ''), 30);
        if ($item['badge_text'] === '' && intval($item['is_default'] ?? 0) === 1) {
            $item['badge_text'] = '推荐';
        }
        $item['button_action'] = self::normalizePackageButtonAction(strval($item['button_action'] ?? 'pay'));
        $item['button_text'] = self::cleanPackageText(strval($item['button_text'] ?? ''), 50);
        if ($item['button_text'] === '') {
            if ($item['button_action'] === 'contact') {
                $item['button_text'] = '联系开通';
            } elseif ($item['button_action'] === 'pay') {
                $item['button_text'] = self::isCreditRechargePackage($item)
                    ? (floatval($item['price'] ?? 0) > 0 ? '立即充值' : '立即领取')
                    : (floatval($item['price'] ?? 0) > 0 ? '立即购买' : '立即领取');
            }
        }
        $item['button_tips'] = self::cleanPackageText(strval($item['button_tips'] ?? ''), 120);
        if ($item['button_tips'] === '' && $item['button_action'] === 'contact') {
            $item['button_tips'] = '请联系管理员开通';
        }
        $item['style_key'] = self::normalizePackageStyleKey(strval($item['style_key'] ?? 'purple'));
        $item['recharge_ratio'] = self::normalizeRechargeRatio($item['recharge_ratio'] ?? 0);
        $item['gift_credit_amount'] = max(0, intval($item['gift_credit_amount'] ?? 0));
        $item['sync_platform_level'] = intval($item['sync_platform_level'] ?? 0) === 1 ? 1 : 0;
        $item['sync_platform_levelid'] = max(0, intval($item['sync_platform_levelid'] ?? 0));
    }

    private static function buildDefaultPackageSubtitle(array $item): string
    {
        $parts = [];
        $creditUnitLabel = self::normalizeCreditUnitLabel($item['credit_unit_label'] ?? self::DEFAULT_CREDIT_UNIT_LABEL);
        $creditAmount = max(0, intval($item['credit_amount'] ?? 0));
        if ($creditAmount > 0) {
            $parts[] = $creditAmount . $creditUnitLabel;
        }
        $giftCreditAmount = max(0, intval($item['gift_credit_amount'] ?? 0));
        if ($giftCreditAmount > 0) {
            $parts[] = '送' . $giftCreditAmount . $creditUnitLabel;
        }
        $validityText = trim(strval($item['validity_text'] ?? ''));
        if ($validityText !== '') {
            $parts[] = $validityText;
        }
        return implode(' · ', $parts);
    }

    private static function normalizePackageEntrySettings(array $config): array
    {
        return [
            'package_enabled' => intval($config['package_enabled'] ?? 1) === 1 ? 1 : 0,
            'recharge_enabled' => intval($config['recharge_enabled'] ?? 1) === 1 ? 1 : 0,
            'credit_unit_label' => self::normalizeCreditUnitLabel($config['credit_unit_label'] ?? self::DEFAULT_CREDIT_UNIT_LABEL),
        ];
    }

    private static function normalizeCreditUnitLabel($value): string
    {
        $label = mb_substr(trim(strval($value)), 0, 12, 'UTF-8');
        return $label !== '' ? $label : self::DEFAULT_CREDIT_UNIT_LABEL;
    }

    private static function isPackageEntryEnabled(array $package, array $entrySettings): bool
    {
        return self::isCreditRechargePackage($package)
            ? intval($entrySettings['recharge_enabled'] ?? 1) === 1
            : intval($entrySettings['package_enabled'] ?? 1) === 1;
    }

    private static function assertPackageEntryEnabled(array $package, array $entrySettings): void
    {
        if (self::isPackageEntryEnabled($package, $entrySettings)) {
            return;
        }

        if (self::isCreditRechargePackage($package)) {
            throw new \RuntimeException('非会员充值暂未开启');
        }

        throw new \RuntimeException('会员套餐暂未开启');
    }

    private static function normalizePackageGroupKey(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_]/', '', $value);
        return $value !== '' ? substr($value, 0, 30) : 'member';
    }

    private static function isCreditRechargePackage(array $package): bool
    {
        return in_array(self::normalizePackageGroupKey(strval($package['group_key'] ?? 'member')), ['recharge', 'non_member'], true);
    }

    private static function buildPackageOrderQuery(
        int $aid,
        string $keyword = '',
        string $orderType = '',
        string $status = '',
        string $grantStatus = '',
        string $startDate = '',
        string $endDate = ''
    ) {
        $query = Db::name('zhuibao_package_order')
            ->alias('o')
            ->leftJoin('zhuibao_package p', 'p.aid = o.aid and p.id = o.package_id')
            ->leftJoin('member m', 'm.aid = o.aid and m.id = o.mid')
            ->leftJoin('payorder po', 'po.aid = o.aid and po.id = o.payorderid')
            ->where('o.aid', $aid);

        $keyword = trim($keyword);
        if ($keyword !== '') {
            $like = '%' . $keyword . '%';
            $query->where(function ($subQuery) use ($like) {
                $subQuery
                    ->where('o.ordernum', 'like', $like)
                    ->whereOr('o.package_name', 'like', $like)
                    ->whereOr('o.paynum', 'like', $like)
                    ->whereOr('po.paynum', 'like', $like)
                    ->whereOr('m.nickname', 'like', $like)
                    ->whereOr('m.realname', 'like', $like)
                    ->whereOr('m.tel', 'like', $like)
                    ->whereOr('m.id', 'like', $like);
            });
        }

        if ($orderType === 'recharge') {
            $query->whereIn('p.group_key', ['recharge', 'non_member']);
        } elseif ($orderType === 'member') {
            $query->whereRaw("(p.group_key IS NULL OR p.group_key NOT IN ('recharge','non_member'))");
        }

        if ($status === '1') {
            $query->whereRaw('(o.status = 1 OR po.status = 1)');
        } elseif ($status === '0') {
            $query->whereRaw('(o.status = 0 AND (po.status IS NULL OR po.status = 0))');
        }
        if ($grantStatus === '0' || $grantStatus === '1') {
            $query->where('o.grant_status', intval($grantStatus));
        }

        $startTime = $startDate !== '' ? strtotime($startDate . ' 00:00:00') : false;
        if ($startTime !== false) {
            $query->where('o.createtime', '>=', intval($startTime));
        }
        $endTime = $endDate !== '' ? strtotime($endDate . ' 23:59:59') : false;
        if ($endTime !== false) {
            $query->where('o.createtime', '<=', intval($endTime));
        }

        return $query;
    }

    private static function formatPackageOrderRow(array &$item, string $creditUnitLabel): void
    {
        $orderStatus = intval($item['status'] ?? 0) === 1 || intval($item['unified_pay_status'] ?? 0) === 1 ? 1 : 0;
        $paytype = trim(strval($item['paytype'] ?? '')) ?: trim(strval($item['unified_paytype'] ?? ''));
        $paytypeid = intval($item['paytypeid'] ?? 0) ?: intval($item['unified_paytypeid'] ?? 0);
        $paynum = trim(strval($item['paynum'] ?? '')) ?: trim(strval($item['unified_paynum'] ?? ''));
        $platform = trim(strval($item['platform'] ?? '')) ?: trim(strval($item['unified_platform'] ?? ''));
        $paytime = intval($item['paytime'] ?? 0) ?: intval($item['unified_paytime'] ?? 0);
        $isRecharge = self::isCreditRechargePackage([
            'group_key' => strval($item['package_group_key'] ?? 'member'),
        ]);

        $item['id'] = intval($item['id'] ?? 0);
        $item['aid'] = intval($item['aid'] ?? 0);
        $item['bid'] = intval($item['bid'] ?? 0);
        $item['mid'] = intval($item['mid'] ?? 0);
        $item['package_id'] = intval($item['package_id'] ?? 0);
        $item['credit_amount'] = max(0, intval($item['credit_amount'] ?? 0));
        $item['gifted_credit'] = max(0, intval($item['gifted_credit'] ?? 0));
        $item['total_credit'] = $item['credit_amount'] + $item['gifted_credit'];
        $item['validity_days'] = max(0, intval($item['validity_days'] ?? 0));
        $item['validity_text'] = $item['validity_days'] > 0 ? $item['validity_days'] . '天' : '长期有效';
        $item['price'] = number_format(floatval($item['price'] ?? 0), 2, '.', '');
        $item['order_type'] = $isRecharge ? 'recharge' : 'member';
        $item['order_type_text'] = $isRecharge ? $creditUnitLabel . '充值' : '会员套餐';
        $item['status'] = $orderStatus === 1 ? 1 : 0;
        $item['status_text'] = $item['status'] === 1 ? '已支付' : '未支付';
        $item['grant_status'] = intval($item['grant_status'] ?? 0) === 1 ? 1 : 0;
        $item['grant_status_text'] = $item['grant_status'] === 1 ? '已发放' : '未发放';
        $item['paytype'] = $paytype ?: ($item['status'] === 1 ? '在线支付' : '');
        $item['paytypeid'] = $paytypeid;
        $item['paynum'] = $paynum;
        $item['platform'] = $platform;
        $item['platform_text'] = self::getPackageOrderPlatformText($platform);
        $item['paytime'] = $paytime;
        $item['member_name'] = trim(strval($item['nickname'] ?? ''))
            ?: (trim(strval($item['realname'] ?? '')) ?: ('会员' . $item['mid']));
        $item['createtime'] = intval($item['createtime'] ?? 0);
        $item['updatetime'] = intval($item['updatetime'] ?? 0);
        $item['createtime_text'] = $item['createtime'] > 0 ? date('Y-m-d H:i:s', $item['createtime']) : '';
        $item['updatetime_text'] = $item['updatetime'] > 0 ? date('Y-m-d H:i:s', $item['updatetime']) : '';
        $item['paytime_text'] = $paytime > 0 ? date('Y-m-d H:i:s', $paytime) : '';
        $item['credit_unit_label'] = $creditUnitLabel;

        unset(
            $item['unified_pay_status'],
            $item['unified_paytype'],
            $item['unified_paytypeid'],
            $item['unified_paynum'],
            $item['unified_platform'],
            $item['unified_paytime']
        );
    }

    private static function getPackageOrderPlatformText(string $platform): string
    {
        $map = [
            'wx' => '微信小程序',
            'mp' => '微信公众号',
            'h5' => 'H5',
            'app' => 'App',
            'alipay' => '支付宝',
            'baidu' => '百度小程序',
            'toutiao' => '抖音小程序',
            'qq' => 'QQ小程序',
        ];

        return $map[$platform] ?? ($platform !== '' ? $platform : '-');
    }

    private static function defaultPackageGroupName(string $groupKey): string
    {
        $map = [
            'member' => '会员套餐',
            'non_member' => '非会员',
            'recharge' => '非会员',
        ];
        return $map[$groupKey] ?? '会员套餐';
    }

    private static function normalizePackageButtonAction(string $value): string
    {
        $value = strtolower(trim($value));
        return in_array($value, ['pay', 'contact', 'none'], true) ? $value : 'pay';
    }

    private static function normalizePackageStyleKey(string $value): string
    {
        $value = strtolower(trim($value));
        return in_array($value, ['purple', 'blue', 'green', 'gold', 'gray'], true) ? $value : 'purple';
    }

    private static function cleanPackageText(string $value, int $maxLength): string
    {
        $value = trim(strip_tags($value));
        return mb_substr($value, 0, max(1, $maxLength));
    }

    private static function normalizeRedeemCode(string $value): string
    {
        $value = strtoupper(trim($value));
        $value = preg_replace('/\s+/', '', $value);
        $value = substr(strval($value), 0, 60);
        if ($value === '' || !preg_match('/^[A-Z0-9_-]{4,60}$/', $value)) {
            return '';
        }
        return $value;
    }

    private static function normalizeRedeemTimestamp($value): int
    {
        if (is_numeric($value)) {
            return max(0, intval($value));
        }

        $text = trim(strval($value));
        if ($text === '') {
            return 0;
        }

        $timestamp = strtotime($text);
        return $timestamp ? intval($timestamp) : 0;
    }

    private static function formatRedeemCodeRow(array &$item): void
    {
        $now = time();
        $item['id'] = intval($item['id'] ?? 0);
        $item['package_id'] = intval($item['package_id'] ?? 0);
        $item['package_name'] = trim(strval($item['package_name'] ?? ''));
        if ($item['package_name'] === '') {
            $item['package_name'] = '套餐已删除';
        }
        $item['max_use_count'] = max(0, intval($item['max_use_count'] ?? 0));
        $item['used_count'] = max(0, intval($item['used_count'] ?? 0));
        $item['per_member_limit'] = max(0, intval($item['per_member_limit'] ?? 1));
        $item['start_time'] = max(0, intval($item['start_time'] ?? 0));
        $item['end_time'] = max(0, intval($item['end_time'] ?? 0));
        $item['status'] = intval($item['status'] ?? 0) === 1 ? 1 : 0;
        $item['start_time_text'] = $item['start_time'] > 0 ? date('Y-m-d H:i:s', $item['start_time']) : '';
        $item['end_time_text'] = $item['end_time'] > 0 ? date('Y-m-d H:i:s', $item['end_time']) : '';
        $item['valid_time_range'] = [
            $item['start_time_text'],
            $item['end_time_text'],
        ];
        $item['valid_time_text'] = self::buildRedeemValidTimeText($item['start_time'], $item['end_time']);
        $item['use_count_text'] = $item['max_use_count'] > 0
            ? ($item['used_count'] . '/' . $item['max_use_count'])
            : ($item['used_count'] . '/不限');
        $item['status_text'] = self::getRedeemStatusText($item, $now);
        $item['create_time_text'] = !empty($item['create_time']) ? date('Y-m-d H:i:s', intval($item['create_time'])) : '';
        $item['update_time_text'] = !empty($item['update_time']) ? date('Y-m-d H:i:s', intval($item['update_time'])) : '';
    }

    private static function buildRedeemValidTimeText(int $startTime, int $endTime): string
    {
        if ($startTime <= 0 && $endTime <= 0) {
            return '长期有效';
        }
        $startText = $startTime > 0 ? date('Y-m-d H:i', $startTime) : '不限';
        $endText = $endTime > 0 ? date('Y-m-d H:i', $endTime) : '不限';
        return $startText . ' 至 ' . $endText;
    }

    private static function getRedeemStatusText(array $item, int $now): string
    {
        if (intval($item['status'] ?? 0) !== 1) {
            return '停用';
        }
        $maxUseCount = max(0, intval($item['max_use_count'] ?? 0));
        $usedCount = max(0, intval($item['used_count'] ?? 0));
        if ($maxUseCount > 0 && $usedCount >= $maxUseCount) {
            return '已用完';
        }
        if (intval($item['start_time'] ?? 0) > 0 && intval($item['start_time']) > $now) {
            return '未开始';
        }
        if (intval($item['end_time'] ?? 0) > 0 && intval($item['end_time']) < $now) {
            return '已过期';
        }
        return '启用';
    }

    private static function getRedeemPackageOptions(int $aid): array
    {
        $rows = Db::name('zhuibao_package')
            ->where('aid', $aid)
            ->order('sort asc,id desc')
            ->field('id,aid,name,group_key,group_name,subtitle,badge_text,price,credit_amount,gift_credit_amount,validity_days,frontend_visible,purchase_enabled,status,sort')
            ->select()
            ->toArray();

        foreach ($rows as &$item) {
            $item['frontend_visible'] = intval($item['frontend_visible'] ?? 1) === 1 ? 1 : 0;
            $item['purchase_enabled'] = intval($item['purchase_enabled'] ?? 1) === 1 ? 1 : 0;
            $item['validity_text'] = intval($item['validity_days'] ?? 0) > 0 ? intval($item['validity_days']) . '天' : '长期有效';
            self::formatPackageDisplayFields($item);
        }
        unset($item);

        return $rows;
    }

    private static function defaultPackages(): array
    {
        return [
            [
                'name' => '体验版',
                'description' => '适合首次体验，先跑通一键创作链路。',
                'group_key' => 'member',
                'group_name' => '会员套餐',
                'subtitle' => '',
                'badge_text' => '体验',
                'button_text' => '立即领取',
                'button_action' => 'pay',
                'button_tips' => '',
                'style_key' => 'purple',
                'recharge_ratio' => '0.0000',
                'gift_credit_amount' => 0,
                'recharge_reward_json' => [],
                'open_reward_json' => [],
                'price' => '0.00',
                'credit_amount' => 30,
                'validity_days' => 7,
                'is_default' => 1,
                'rights_json' => [
                    'english_subtitle' => 0,
                    'advanced_template' => 0,
                    'ai_persona' => 1,
                ],
            ],
            [
                'name' => '基础版',
                'description' => '适合日常创作，支持完整标准视频生产。',
                'group_key' => 'member',
                'group_name' => '会员套餐',
                'subtitle' => '',
                'badge_text' => '超值',
                'button_text' => '立即购买',
                'button_action' => 'pay',
                'button_tips' => '',
                'style_key' => 'blue',
                'recharge_ratio' => '1.2121',
                'gift_credit_amount' => 0,
                'recharge_reward_json' => [],
                'open_reward_json' => [],
                'price' => '99.00',
                'credit_amount' => 120,
                'validity_days' => 30,
                'is_default' => 0,
                'rights_json' => [
                    'english_subtitle' => 1,
                    'advanced_template' => 0,
                    'ai_persona' => 1,
                ],
            ],
            [
                'name' => '专业版',
                'description' => '适合团队高频使用，支持双语字幕与高级创作能力。',
                'group_key' => 'member',
                'group_name' => '会员套餐',
                'subtitle' => '',
                'badge_text' => '热销',
                'button_text' => '立即购买',
                'button_action' => 'pay',
                'button_tips' => '',
                'style_key' => 'gold',
                'recharge_ratio' => '1.6722',
                'gift_credit_amount' => 0,
                'recharge_reward_json' => [],
                'open_reward_json' => [],
                'price' => '299.00',
                'credit_amount' => 500,
                'validity_days' => 365,
                'is_default' => 0,
                'rights_json' => [
                    'english_subtitle' => 1,
                    'advanced_template' => 1,
                    'ai_persona' => 1,
                ],
            ],
        ];
    }

    private static function defaultRechargePackages(): array
    {
        return [
            [
                'name' => '100点充值包',
                'description' => '无需开通会员，充值后直接到账额度。',
                'group_key' => 'recharge',
                'group_name' => '非会员',
                'subtitle' => '',
                'badge_text' => '常用',
                'button_text' => '立即充值',
                'button_action' => 'pay',
                'button_tips' => '',
                'style_key' => 'purple',
                'recharge_ratio' => '1.0000',
                'gift_credit_amount' => 0,
                'recharge_reward_json' => [],
                'open_reward_json' => [],
                'price' => '100.00',
                'credit_amount' => 100,
                'validity_days' => 0,
                'is_default' => 0,
                'sort' => 1,
                'rights_json' => [],
            ],
            [
                'name' => '300点充值包',
                'description' => '适合高频生成，充值后按智能体扣点使用。',
                'group_key' => 'recharge',
                'group_name' => '非会员',
                'subtitle' => '',
                'badge_text' => '超值',
                'button_text' => '立即充值',
                'button_action' => 'pay',
                'button_tips' => '',
                'style_key' => 'blue',
                'recharge_ratio' => '1.0000',
                'gift_credit_amount' => 30,
                'recharge_reward_json' => [],
                'open_reward_json' => [],
                'price' => '300.00',
                'credit_amount' => 300,
                'validity_days' => 0,
                'is_default' => 1,
                'sort' => 2,
                'rights_json' => [],
            ],
        ];
    }

    private static function decodeJson($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $text = trim(strval($value));
        if ($text === '') {
            return [];
        }

        $decoded = json_decode($text, true);
        return is_array($decoded) ? $decoded : [];
    }
}
