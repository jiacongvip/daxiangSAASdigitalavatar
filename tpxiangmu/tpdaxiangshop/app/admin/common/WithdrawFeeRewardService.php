<?php

namespace app\admin\common;

use think\facade\Db;
use think\facade\Log;

class WithdrawFeeRewardService
{
    private const ASSET_MAP = [
        'heiscore' => ['label' => '现金券', 'scale' => 4],
        'xiaofeib' => ['label' => '消费币', 'scale' => 2],
        'scorehuang' => ['label' => '黄积分', 'scale' => 4],
        'scorelv' => ['label' => '绿积分', 'scale' => 4],
        'score' => ['label' => '积分', 'scale' => 4],
        'score3' => ['label' => '积分3', 'scale' => 4],
        'jishoupeizi' => ['label' => '配资', 'scale' => 2],
    ];

    public static function ensureAdminSetColumns(): void
    {
        $tableName = config('database.connections.mysql.prefix', '') . 'admin_set';
        try {
            if (!self::tableColumnExists('admin_set', 'withdraw_fee_reward_enabled')) {
                Db::execute("ALTER TABLE `{$tableName}` ADD COLUMN `withdraw_fee_reward_enabled` tinyint(1) NOT NULL DEFAULT 0 COMMENT '余额提现手续费奖励开关：0关闭 1开启' AFTER `withdraw_fixed_fee`");
            }
        } catch (\Throwable $e) {
            Log::write('余额提现手续费奖励字段补齐失败：admin_set.withdraw_fee_reward_enabled，错误：' . $e->getMessage());
        }
        try {
            if (!self::tableColumnExists('admin_set', 'withdraw_fee_reward_asset')) {
                $afterSql = self::tableColumnExists('admin_set', 'withdraw_fee_reward_enabled') ? " AFTER `withdraw_fee_reward_enabled`" : "";
                Db::execute("ALTER TABLE `{$tableName}` ADD COLUMN `withdraw_fee_reward_asset` varchar(32) NOT NULL DEFAULT '' COMMENT '余额提现手续费奖励到账资产'{$afterSql}");
            }
        } catch (\Throwable $e) {
            Log::write('余额提现手续费奖励字段补齐失败：admin_set.withdraw_fee_reward_asset，错误：' . $e->getMessage());
        }
        try {
            if (!self::tableColumnExists('admin_set', 'withdraw_fee_reward_percent')) {
                $afterSql = self::tableColumnExists('admin_set', 'withdraw_fee_reward_asset') ? " AFTER `withdraw_fee_reward_asset`" : "";
                Db::execute("ALTER TABLE `{$tableName}` ADD COLUMN `withdraw_fee_reward_percent` decimal(10,4) NOT NULL DEFAULT 0 COMMENT '余额提现手续费奖励比例'{$afterSql}");
            }
        } catch (\Throwable $e) {
            Log::write('余额提现手续费奖励字段补齐失败：admin_set.withdraw_fee_reward_percent，错误：' . $e->getMessage());
        }
    }

    public static function adminSetColumnsReady(): bool
    {
        return self::tableColumnExists('admin_set', 'withdraw_fee_reward_enabled')
            && self::tableColumnExists('admin_set', 'withdraw_fee_reward_asset')
            && self::tableColumnExists('admin_set', 'withdraw_fee_reward_percent');
    }

    public static function ensureWithdrawlogColumns(): void
    {
        $tableName = config('database.connections.mysql.prefix', '') . 'member_withdrawlog';
        $columns = [
            'fee_reward_asset' => "ADD COLUMN `fee_reward_asset` varchar(32) NOT NULL DEFAULT '' COMMENT '手续费奖励到账资产' AFTER `platform`",
            'fee_reward_percent' => "ADD COLUMN `fee_reward_percent` decimal(10,4) NOT NULL DEFAULT 0 COMMENT '手续费奖励比例' AFTER `fee_reward_asset`",
            'fee_reward_amount' => "ADD COLUMN `fee_reward_amount` decimal(12,4) NOT NULL DEFAULT 0 COMMENT '手续费奖励金额' AFTER `fee_reward_percent`",
            'fee_reward_status' => "ADD COLUMN `fee_reward_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '手续费奖励状态：0待处理/无奖励 1已发放 2已跳过/失败' AFTER `fee_reward_amount`",
            'fee_reward_time' => "ADD COLUMN `fee_reward_time` int(11) NOT NULL DEFAULT 0 COMMENT '手续费奖励发放时间' AFTER `fee_reward_status`",
            'fee_reward_msg' => "ADD COLUMN `fee_reward_msg` varchar(255) NOT NULL DEFAULT '' COMMENT '手续费奖励处理说明' AFTER `fee_reward_time`",
        ];

        foreach ($columns as $column => $ddl) {
            try {
                if (!self::tableColumnExists('member_withdrawlog', $column)) {
                    Db::execute("ALTER TABLE `{$tableName}` {$ddl}");
                }
            } catch (\Throwable $e) {
                Log::write('余额提现手续费奖励字段补齐失败：member_withdrawlog.' . $column . '，错误：' . $e->getMessage());
            }
        }
    }

    public static function withdrawlogColumnsReady(): bool
    {
        foreach (['fee_reward_asset', 'fee_reward_percent', 'fee_reward_amount', 'fee_reward_status', 'fee_reward_time', 'fee_reward_msg'] as $column) {
            if (!self::tableColumnExists('member_withdrawlog', $column)) {
                return false;
            }
        }
        return true;
    }

    public static function buildSnapshot(int $aid, array $set, $txmoney): array
    {
        self::ensureWithdrawlogColumns();
        if (!self::withdrawlogColumnsReady()) {
            return [];
        }

        $asset = self::normalizeAsset($set['withdraw_fee_reward_asset'] ?? '');
        $percent = self::normalizePercent($set['withdraw_fee_reward_percent'] ?? 0);
        $enabled = intval($set['withdraw_fee_reward_enabled'] ?? 0) === 1;
        $amount = 0;

        if ($enabled && $asset !== '' && $percent > 0 && floatval($txmoney) > 0) {
            $amount = self::formatAssetAmount($asset, floatval($txmoney) * $percent / 100);
        }

        if ($amount <= 0) {
            $asset = '';
            $percent = 0;
            $amount = 0;
        }

        return [
            'fee_reward_asset' => $asset,
            'fee_reward_percent' => $percent,
            'fee_reward_amount' => $amount,
            'fee_reward_status' => 0,
            'fee_reward_time' => 0,
            'fee_reward_msg' => '',
        ];
    }

    public static function grantByWithdrawId(int $aid, int $withdrawId): array
    {
        if ($aid <= 0 || $withdrawId <= 0) {
            return ['status' => 0, 'msg' => '参数错误'];
        }
        self::ensureWithdrawlogColumns();
        if (!self::withdrawlogColumnsReady()) {
            return ['status' => 1, 'msg' => '数据库缺少奖励字段，已跳过'];
        }

        Db::startTrans();
        try {
            $info = Db::name('member_withdrawlog')
                ->where('aid', $aid)
                ->where('id', $withdrawId)
                ->lock(true)
                ->find();
            if (!$info) {
                Db::commit();
                return ['status' => 0, 'msg' => '提现记录不存在'];
            }
            if (intval($info['status'] ?? 0) !== 3) {
                Db::commit();
                return ['status' => 1, 'msg' => '提现未成功，无需发放'];
            }
            if (intval($info['fee_reward_status'] ?? 0) === 1) {
                Db::commit();
                return ['status' => 1, 'msg' => '奖励已发放'];
            }

            $asset = self::normalizeAsset($info['fee_reward_asset'] ?? '');
            $amount = self::formatAssetAmount($asset, $info['fee_reward_amount'] ?? 0);
            $percent = self::normalizePercent($info['fee_reward_percent'] ?? 0);
            if ($asset === '' || $amount <= 0 || $percent <= 0) {
                Db::name('member_withdrawlog')
                    ->where('aid', $aid)
                    ->where('id', $withdrawId)
                    ->update([
                        'fee_reward_status' => 2,
                        'fee_reward_time' => time(),
                        'fee_reward_msg' => '未配置提现手续费奖励',
                    ]);
                Db::commit();
                return ['status' => 1, 'msg' => '无奖励配置'];
            }

            $remark = self::buildRemark($aid, $info, $asset, $amount, $percent);
            $result = self::grantAsset($aid, intval($info['mid'] ?? 0), $asset, $amount, $remark);
            if (is_array($result) && intval($result['status'] ?? 1) === 0) {
                $msg = strval($result['msg'] ?? '奖励发放失败');
                Db::name('member_withdrawlog')
                    ->where('aid', $aid)
                    ->where('id', $withdrawId)
                    ->update([
                        'fee_reward_status' => 2,
                        'fee_reward_time' => time(),
                        'fee_reward_msg' => substr($msg, 0, 255),
                    ]);
                Db::commit();
                return ['status' => 0, 'msg' => $msg];
            }

            Db::name('member_withdrawlog')
                ->where('aid', $aid)
                ->where('id', $withdrawId)
                ->update([
                    'fee_reward_status' => 1,
                    'fee_reward_time' => time(),
                    'fee_reward_msg' => '',
                ]);
            Db::commit();
            return ['status' => 1, 'msg' => '奖励发放成功'];
        } catch (\Throwable $e) {
            Db::rollback();
            Log::write('余额提现手续费奖励发放异常-aid:' . $aid . ' withdraw_id:' . $withdrawId . '，错误：' . $e->getMessage());
            return ['status' => 0, 'msg' => $e->getMessage()];
        }
    }

    public static function normalizePercent($percent): float
    {
        $percent = floatval($percent);
        if ($percent < 0) {
            return 0;
        }
        if ($percent > 100) {
            return 100;
        }
        return round($percent, 4);
    }

    public static function normalizeAsset($asset): string
    {
        $asset = strval($asset);
        return isset(self::ASSET_MAP[$asset]) ? $asset : '';
    }

    public static function getAssetName(int $aid, string $asset): string
    {
        $asset = self::normalizeAsset($asset);
        if ($asset === '') {
            return '';
        }
        $label = self::ASSET_MAP[$asset]['label'];
        return t($label, $aid);
    }

    private static function grantAsset(int $aid, int $mid, string $asset, float $amount, string $remark)
    {
        if ($mid <= 0) {
            return ['status' => 0, 'msg' => t('会员', $aid) . '不存在'];
        }
        switch ($asset) {
            case 'heiscore':
                return Member::addhei($aid, $mid, $amount, $remark);
            case 'xiaofeib':
                return Member::addxiaofeib($aid, $mid, $amount, $remark);
            case 'scorehuang':
                return Member::addscorehuang($aid, $mid, $amount, $remark);
            case 'scorelv':
                return Member::addscorelv($aid, $mid, $amount, $remark);
            case 'score':
                return Member::addscore($aid, $mid, $amount, $remark);
            case 'score3':
                return Member::addscore3($aid, $mid, 0, $amount, $remark);
            case 'jishoupeizi':
                return Member::updateAllocation($aid, $mid, $amount, $remark);
        }
        return ['status' => 0, 'msg' => '奖励资产不支持'];
    }

    private static function buildRemark(int $aid, array $info, string $asset, float $amount, float $percent): string
    {
        $assetName = self::getAssetName($aid, $asset);
        $txmoney = self::formatNumber($info['txmoney'] ?? 0, 2);
        $amountText = self::formatNumber($amount, 4);
        $percentText = self::formatNumber($percent, 4);
        return '余额提现手续费奖励到' . $assetName . '，提现金额' . $txmoney . '元，奖励比例' . $percentText . '%，奖励数量' . $amountText;
    }

    private static function formatAssetAmount(string $asset, $amount): float
    {
        $asset = self::normalizeAsset($asset);
        if ($asset === '') {
            return 0;
        }
        $scale = intval(self::ASSET_MAP[$asset]['scale'] ?? 4);
        return round(floatval($amount), $scale);
    }

    private static function formatNumber($number, int $scale): string
    {
        $text = number_format(floatval($number), $scale, '.', '');
        return rtrim(rtrim($text, '0'), '.');
    }

    private static function tableColumnExists(string $tableName, string $columnName): bool
    {
        try {
            $prefix = config('database.connections.mysql.prefix', '');
            $tableName = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
            $columnName = preg_replace('/[^a-zA-Z0-9_]/', '', $columnName);
            if ($tableName === '' || $columnName === '') {
                return false;
            }
            $rows = Db::query("SHOW COLUMNS FROM `{$prefix}{$tableName}` LIKE '{$columnName}'");
            return !empty($rows);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
