<?php

namespace app\plugin\zhuibaoagent\common;

use think\facade\Db;
use think\facade\Log;

class ZhuibaoInstaller
{
    private static $installedEnsured = false;

    public static function ensureInstalled(): void
    {
        if (self::$installedEnsured) {
            return;
        }
        self::$installedEnsured = true;
        self::runInstallSql();
    }

    private static function runInstallSql(): void
    {
        $sqlFile = __DIR__ . '/../database/install.sql';
        if (!file_exists($sqlFile)) {
            return;
        }

        $sql = file_get_contents($sqlFile);
        if (!is_string($sql) || trim($sql) === '') {
            return;
        }

        $prefix = config('database.connections.mysql.prefix');
        $sql = str_replace('{prefix}', $prefix, $sql);

        $statements = array_filter(explode(';', $sql));
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if ($statement === '') {
                continue;
            }

            $withoutComments = preg_replace('#/\*.*?\*/#s', '', $statement);
            $withoutComments = preg_replace('/^\s*(--|#).*$/m', '', strval($withoutComments));
            if (trim(strval($withoutComments)) === '') {
                continue;
            }

            try {
                Db::execute($statement);
            } catch (\Throwable $e) {
                $msg = $e->getMessage();
                if (
                    stripos($msg, 'already exists') !== false
                    || stripos($msg, 'Duplicate column name') !== false
                    || stripos($msg, 'Duplicate key name') !== false
                ) {
                    continue;
                }
                Log::error('zhuibaoagent install sql error: ' . $msg);
                throw $e;
            }
        }
    }
}
