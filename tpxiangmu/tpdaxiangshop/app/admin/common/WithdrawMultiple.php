<?php

namespace app\admin\common;

use think\facade\Db;
use think\facade\Log;

class WithdrawMultiple
{
    public static function ensureAdminSetColumns(): void
    {
        $tableName = config('database.connections.mysql.prefix', '') . 'admin_set';
        try {
            if (!self::tableColumnExists('admin_set', 'withdraw_multiple')) {
                $afterSql = self::tableColumnExists('admin_set', 'withdrawmin') ? " AFTER `withdrawmin`" : '';
                Db::execute("ALTER TABLE `{$tableName}` ADD COLUMN `withdraw_multiple` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '余额提现金额倍数，0不限制'{$afterSql}");
            }
        } catch (\Throwable $e) {
            Log::write('余额提现倍数字段补齐失败：admin_set.withdraw_multiple，错误：' . $e->getMessage());
        }
        try {
            if (!self::tableColumnExists('admin_set', 'comwithdraw_multiple')) {
                $afterSql = self::tableColumnExists('admin_set', 'comwithdrawmin') ? " AFTER `comwithdrawmin`" : '';
                Db::execute("ALTER TABLE `{$tableName}` ADD COLUMN `comwithdraw_multiple` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '佣金提现金额倍数，0不限制'{$afterSql}");
            }
        } catch (\Throwable $e) {
            Log::write('佣金提现倍数字段补齐失败：admin_set.comwithdraw_multiple，错误：' . $e->getMessage());
        }
        try {
            if (!self::tableColumnExists('admin_set', 'money_transfer_multiple')) {
                $afterSql = self::tableColumnExists('admin_set', 'tansrate') ? " AFTER `tansrate`" : '';
                Db::execute("ALTER TABLE `{$tableName}` ADD COLUMN `money_transfer_multiple` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '余额互转金额倍数，0不限制'{$afterSql}");
            }
        } catch (\Throwable $e) {
            Log::write('余额互转倍数字段补齐失败：admin_set.money_transfer_multiple，错误：' . $e->getMessage());
        }
        try {
            if (!self::tableColumnExists('admin_set', 'commission2money_multiple')) {
                $afterSql = self::tableColumnExists('admin_set', 'commission2money') ? " AFTER `commission2money`" : '';
                Db::execute("ALTER TABLE `{$tableName}` ADD COLUMN `commission2money_multiple` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '佣金转余额金额倍数，0不限制'{$afterSql}");
            }
        } catch (\Throwable $e) {
            Log::write('佣金转余额倍数字段补齐失败：admin_set.commission2money_multiple，错误：' . $e->getMessage());
        }
    }

    public static function hasColumn(string $columnName): bool
    {
        return self::tableColumnExists('admin_set', $columnName);
    }

    public static function adminSetColumnsReady(): bool
    {
        return self::hasColumn('withdraw_multiple') && self::hasColumn('comwithdraw_multiple');
    }

    public static function normalize($value): float
    {
        $value = floatval($value);
        if ($value < 0) {
            return 0;
        }
        return round($value, 2);
    }

    public static function isMultiple($money, $multiple): bool
    {
        $multiple = self::normalize($multiple);
        if ($multiple <= 0) {
            return true;
        }
        $moneyCents = (int) round(floatval($money) * 100);
        $multipleCents = (int) round($multiple * 100);
        if ($multipleCents <= 0) {
            return true;
        }
        return $moneyCents % $multipleCents === 0;
    }

    public static function format($multiple): string
    {
        $multiple = self::normalize($multiple);
        if (abs($multiple - round($multiple)) < 0.00001) {
            return (string) intval(round($multiple));
        }
        return rtrim(rtrim(number_format($multiple, 2, '.', ''), '0'), '.');
    }

    public static function rejectMessage($multiple, string $scene = ''): string
    {
        $text = self::format($multiple);
        if ($scene === 'level') {
            return '当前等级佣金提现必须为' . $text . '的倍数';
        }
        if ($scene === 'transfer') {
            return '转账金额必须是' . $text . '的倍数';
        }
        if ($scene === 'commission2money') {
            return '转入金额必须是' . $text . '的倍数';
        }
        if ($scene === 'score_transfer') {
            return '转账数量必须是' . $text . '的倍数';
        }
        return '提现金额必须是' . $text . '元的倍数';
    }

    public static function checkOrError($money, $multiple, string $scene = ''): ?array
    {
        if (self::isMultiple($money, $multiple)) {
            return null;
        }
        return ['status' => 0, 'msg' => self::rejectMessage($multiple, $scene)];
    }

    public static function checkAmountOrError($money, $multiple, string $label = '金额'): ?array
    {
        if (self::isMultiple($money, $multiple)) {
            return null;
        }
        return ['status' => 0, 'msg' => $label . '必须是' . self::format($multiple) . '的倍数'];
    }

    public static function fromSet(array $set, string $column, $fallback = 0): float
    {
        return self::normalize($set[$column] ?? $fallback);
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
