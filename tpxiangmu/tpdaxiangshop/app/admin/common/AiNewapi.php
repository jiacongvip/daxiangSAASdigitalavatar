<?php
/**
 * AI（NewAPI）配置与存储
 *
 * 设计目标：
 * - 平台侧（控制台）配置一套 NewAPI（sysset:newapi）
 * - 每个平台（aid）可选择：使用平台 / 使用自己的 / 不使用
 * - aid 自己的配置存储在 ai_newapi_config 表
 *
 * 注意：
 * - 出于安全考虑，对外返回配置时默认不返回 api_key，仅返回 has_api_key
 */

namespace app\admin\common;

use think\facade\Db;
use think\facade\Log;

class AiNewapi
{
    private static $schemaEnsured = false;

    public static function ensureInstalled(): void
    {
        if (self::$schemaEnsured) {
            return;
        }
        self::$schemaEnsured = true;

        try {
            $prefix = config('database.connections.mysql.prefix');
            $table = $prefix . 'ai_newapi_config';
            $rs = Db::query("SHOW TABLES LIKE '{$table}'");
            if (!empty($rs)) {
                return;
            }

            $sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
              `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
              `aid` int(11) NOT NULL DEFAULT '0' COMMENT '平台ID',
              `mode` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0=使用平台配置 1=使用自己的配置 2=不使用',
              `base_url` varchar(255) NOT NULL DEFAULT '' COMMENT 'NewAPI Base URL',
              `api_key` varchar(255) NOT NULL DEFAULT '' COMMENT 'NewAPI API Key',
              `model` varchar(100) NOT NULL DEFAULT '' COMMENT '默认模型',
              `temperature` decimal(4,2) NOT NULL DEFAULT '0.70' COMMENT 'temperature',
              `max_tokens` int(11) NOT NULL DEFAULT '0' COMMENT 'max_tokens(0=默认)',
              `timeout` int(11) NOT NULL DEFAULT '30' COMMENT 'timeout seconds',
              `createtime` int(11) NOT NULL DEFAULT '0' COMMENT '创建时间',
              `updatetime` int(11) NOT NULL DEFAULT '0' COMMENT '更新时间',
              PRIMARY KEY (`id`),
              UNIQUE KEY `uniq_aid` (`aid`),
              KEY `idx_mode` (`mode`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI(NewAPI) 配置';";

            Db::execute($sql);
        } catch (\Throwable $e) {
            Log::error('AI(NewAPI) 表初始化失败：' . $e->getMessage());
        }
    }

    /**
     * 平台侧 NewAPI 配置（sysset:newapi）
     *
     * @param bool $withSecret 是否返回 api_key
     */
    public static function getPlatformConfig(bool $withSecret = false): array
    {
        $row = null;
        try {
            $row = Db::name('sysset')->where('name', 'newapi')->find();
        } catch (\Throwable $e) {
        }

        if (!$row) {
            try {
                Db::name('sysset')->insert(['name' => 'newapi', 'value' => '[]']);
            } catch (\Throwable $e) {
            }
            $cfg = [];
        } else {
            $cfg = json_decode(strval($row['value'] ?? ''), true);
            if (!is_array($cfg)) {
                $cfg = [];
            }
        }

        $normalized = self::normalizePlatformConfig($cfg);
        if ($withSecret) {
            return $normalized;
        }

        $out = $normalized;
        unset($out['api_key']);
        $out['has_api_key'] = $normalized['api_key'] !== '' ? 1 : 0;
        return $out;
    }

    /**
     * 保存平台侧 NewAPI 配置（sysset:newapi）
     * - api_key：留空不修改
     * - clear_api_key=1：清空
     */
    public static function savePlatformConfig(array $input): void
    {
        $existing = self::getPlatformConfig(true);

        $enabled = intval($input['enabled'] ?? $existing['enabled'] ?? 0) === 1 ? 1 : 0;
        $baseUrl = trim(strval($input['base_url'] ?? $existing['base_url'] ?? ''));
        $model = trim(strval($input['model'] ?? $existing['model'] ?? ''));
        $temperature = floatval($input['temperature'] ?? $existing['temperature'] ?? 0.7);
        $maxTokens = intval($input['max_tokens'] ?? $existing['max_tokens'] ?? 0);
        $timeout = intval($input['timeout'] ?? $existing['timeout'] ?? 30);

        $apiKey = strval($existing['api_key'] ?? '');
        $clearKey = intval($input['clear_api_key'] ?? 0) === 1;
        $newKey = trim(strval($input['api_key'] ?? ''));
        if ($clearKey) {
            $apiKey = '';
        } elseif ($newKey !== '') {
            $apiKey = $newKey;
        }

        $cfg = self::normalizePlatformConfig([
            'enabled' => $enabled,
            'base_url' => $baseUrl,
            'api_key' => $apiKey,
            'model' => $model,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            'timeout' => $timeout,
        ]);

        $value = json_encode($cfg, JSON_UNESCAPED_UNICODE);
        $rs = Db::name('sysset')->where('name', 'newapi')->find();
        if ($rs) {
            Db::name('sysset')->where('name', 'newapi')->update(['value' => $value]);
        } else {
            Db::name('sysset')->insert(['name' => 'newapi', 'value' => $value]);
        }
    }

    /**
     * 获取平台（aid）侧 AI 配置（不返回 api_key）
     */
    public static function getAidConfig(int $aid, bool $withSecret = false): array
    {
        self::ensureInstalled();

        $row = null;
        try {
            $row = Db::name('ai_newapi_config')->where('aid', $aid)->find();
        } catch (\Throwable $e) {
        }

        $normalized = self::normalizeAidConfig(is_array($row) ? $row : []);
        if ($withSecret) {
            return $normalized;
        }

        $out = $normalized;
        unset($out['api_key']);
        $out['has_api_key'] = $normalized['api_key'] !== '' ? 1 : 0;
        return $out;
    }

    /**
     * 保存平台（aid）侧 AI 配置
     * - api_key：留空不修改
     * - clear_api_key=1：清空
     */
    public static function saveAidConfig(int $aid, array $input): void
    {
        self::ensureInstalled();

        $existing = self::getAidConfig($aid, true);

        $mode = intval($input['mode'] ?? $existing['mode'] ?? 0);
        if (!in_array($mode, [0, 1, 2], true)) {
            $mode = 0;
        }

        $baseUrl = trim(strval($input['base_url'] ?? $existing['base_url'] ?? ''));
        $model = trim(strval($input['model'] ?? $existing['model'] ?? ''));
        $temperature = floatval($input['temperature'] ?? $existing['temperature'] ?? 0.7);
        $maxTokens = intval($input['max_tokens'] ?? $existing['max_tokens'] ?? 0);
        $timeout = intval($input['timeout'] ?? $existing['timeout'] ?? 30);

        $apiKey = strval($existing['api_key'] ?? '');
        $clearKey = intval($input['clear_api_key'] ?? 0) === 1;
        $newKey = trim(strval($input['api_key'] ?? ''));
        if ($clearKey) {
            $apiKey = '';
        } elseif ($newKey !== '') {
            $apiKey = $newKey;
        }

        $data = self::normalizeAidConfig([
            'aid' => $aid,
            'mode' => $mode,
            'base_url' => $baseUrl,
            'api_key' => $apiKey,
            'model' => $model,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            'timeout' => $timeout,
        ]);

        $now = time();
        $save = [
            'aid' => $aid,
            'mode' => intval($data['mode']),
            'base_url' => strval($data['base_url']),
            'api_key' => strval($data['api_key']),
            'model' => strval($data['model']),
            'temperature' => strval($data['temperature']),
            'max_tokens' => intval($data['max_tokens']),
            'timeout' => intval($data['timeout']),
            'updatetime' => $now,
        ];

        $exists = Db::name('ai_newapi_config')->where('aid', $aid)->find();
        if ($exists) {
            Db::name('ai_newapi_config')->where('aid', $aid)->update($save);
        } else {
            $save['createtime'] = $now;
            Db::name('ai_newapi_config')->insert($save);
        }
    }

    /**
     * 平台后台（租户）保存自有 AI 配置：
     * - 不允许修改 mode（由总控台控制）
     * - 仅当 mode=1（使用自己的配置）时允许保存
     */
    public static function saveAidConfigByTenant(int $aid, array $input): void
    {
        $existing = self::getAidConfig($aid, true);
        $mode = intval($existing['mode'] ?? 0);
        if ($mode !== 1) {
            return;
        }
        if (isset($input['mode'])) {
            unset($input['mode']);
        }
        self::saveAidConfig($aid, $input);
    }

    /**
     * 获取最终生效的 NewAPI 配置（用于业务调用）
     *
     * 返回：
     * - enabled=0：不启用 AI
     * - enabled=1：返回 base_url/api_key/model/temperature/max_tokens/timeout
     */
    public static function getEffectiveConfig(int $aid): array
    {
        $platform = self::getPlatformConfig(true);
        $aidCfg = self::getAidConfig($aid, true);

        // 不使用
        if (intval($aidCfg['mode'] ?? 0) === 2) {
            return ['enabled' => 0];
        }

        // 使用自己的
        if (intval($aidCfg['mode'] ?? 0) === 1) {
            $cfg = [
                'enabled' => 1,
                'base_url' => strval($aidCfg['base_url'] ?? ''),
                'api_key' => strval($aidCfg['api_key'] ?? ''),
                'model' => strval($aidCfg['model'] ?? ''),
                'temperature' => floatval($aidCfg['temperature'] ?? 0.7),
                'max_tokens' => intval($aidCfg['max_tokens'] ?? 0),
                'timeout' => intval($aidCfg['timeout'] ?? 30),
            ];
            // api_key 为空则不可用
            if ($cfg['api_key'] === '') {
                $cfg['enabled'] = 0;
            }
            return $cfg;
        }

        // 使用平台配置
        $cfg = [
            'enabled' => intval($platform['enabled'] ?? 0) === 1 ? 1 : 0,
            'base_url' => strval($platform['base_url'] ?? ''),
            'api_key' => strval($platform['api_key'] ?? ''),
            'model' => strval($platform['model'] ?? ''),
            'temperature' => floatval($platform['temperature'] ?? 0.7),
            'max_tokens' => intval($platform['max_tokens'] ?? 0),
            'timeout' => intval($platform['timeout'] ?? 30),
        ];
        if ($cfg['enabled'] === 1 && $cfg['api_key'] === '') {
            $cfg['enabled'] = 0;
        }
        return $cfg;
    }

    private static function normalizePlatformConfig(array $cfg): array
    {
        $enabled = intval($cfg['enabled'] ?? 0) === 1 ? 1 : 0;
        $baseUrl = trim(strval($cfg['base_url'] ?? ''));
        $apiKey = trim(strval($cfg['api_key'] ?? ''));
        $model = trim(strval($cfg['model'] ?? ''));
        $temperature = floatval($cfg['temperature'] ?? 0.7);
        if ($temperature < 0) $temperature = 0;
        if ($temperature > 2) $temperature = 2;
        $maxTokens = intval($cfg['max_tokens'] ?? 0);
        if ($maxTokens < 0) $maxTokens = 0;
        $timeout = intval($cfg['timeout'] ?? 30);
        if ($timeout <= 0) $timeout = 30;

        return [
            'enabled' => $enabled,
            'base_url' => $baseUrl,
            'api_key' => $apiKey,
            'model' => $model,
            'temperature' => round($temperature, 2),
            'max_tokens' => $maxTokens,
            'timeout' => $timeout,
        ];
    }

    private static function normalizeAidConfig(array $cfg): array
    {
        $mode = intval($cfg['mode'] ?? 0);
        if (!in_array($mode, [0, 1, 2], true)) {
            $mode = 0;
        }

        $baseUrl = trim(strval($cfg['base_url'] ?? ''));
        $apiKey = trim(strval($cfg['api_key'] ?? ''));
        $model = trim(strval($cfg['model'] ?? ''));
        $temperature = floatval($cfg['temperature'] ?? 0.7);
        if ($temperature < 0) $temperature = 0;
        if ($temperature > 2) $temperature = 2;
        $maxTokens = intval($cfg['max_tokens'] ?? 0);
        if ($maxTokens < 0) $maxTokens = 0;
        $timeout = intval($cfg['timeout'] ?? 30);
        if ($timeout <= 0) $timeout = 30;

        return [
            'aid' => intval($cfg['aid'] ?? 0),
            'mode' => $mode,
            'base_url' => $baseUrl,
            'api_key' => $apiKey,
            'model' => $model,
            'temperature' => round($temperature, 2),
            'max_tokens' => $maxTokens,
            'timeout' => $timeout,
        ];
    }
}
