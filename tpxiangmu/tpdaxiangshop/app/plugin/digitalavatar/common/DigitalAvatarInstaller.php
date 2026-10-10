<?php

namespace app\plugin\digitalavatar\common;

use app\admin\common\PluginMenu;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Log;

class DigitalAvatarInstaller
{
    private const SCHEMA_VERSION = '0.7.8';
    private const SCHEMA_CACHE_KEY = 'digitalavatar:schema:ensured';
    private static $installedEnsured = false;
    private static $portalDomainColumnReady = false;
    private static $sidebarSupportReady = false;
    private static $creditCnyRatioReady = false;

    public static function ensureInstalled(bool $force = false): void
    {
        if (!$force && self::$installedEnsured) {
            return;
        }
        if (!$force && self::isSchemaReady()) {
            self::$installedEnsured = true;
            return;
        }

        $lock = self::acquireSchemaLock();
        try {
            if (!$force && self::isSchemaReady()) {
                self::$installedEnsured = true;
                return;
            }
            self::$installedEnsured = true;
            try {
                self::migrateSchema();
                self::markSchemaReady();
            } catch (\Throwable $e) {
                Log::error('digitalavatar ensureInstalled: ' . $e->getMessage());
                if (self::isRowSizeError($e->getMessage())) {
                    try {
                        self::ensurePluginTablesDynamic();
                        self::ensureSettingRowCapacity();
                    } catch (\Throwable $retry) {
                        Log::error('digitalavatar row size heal: ' . $retry->getMessage());
                    }
                }
                if (self::settingTableExists()) {
                    self::markSchemaReady();
                }
            }
        } finally {
            self::releaseSchemaLock($lock);
        }
    }

    private static function ensureAgentSessionTable(): void
    {
        $prefix = strval(config('database.connections.mysql.prefix', ''));
        $table = $prefix . 'digitalavatar_agent_session';
        try {
            $exists = Db::query("SHOW TABLES LIKE '" . str_replace('`', '', $table) . "'");
            if ($exists) {
                return;
            }
        } catch (\Throwable $e) {
        }
        try {
            Db::execute(
                "CREATE TABLE IF NOT EXISTS `" . str_replace('`', '', $table) . "` (
                  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                  `aid` int(11) NOT NULL DEFAULT '0',
                  `bid` int(11) NOT NULL DEFAULT '0',
                  `member_id` int(11) NOT NULL DEFAULT '0',
                  `skill_id` int(11) NOT NULL DEFAULT '0',
                  `status` varchar(20) NOT NULL DEFAULT 'active',
                  `title` varchar(120) NOT NULL DEFAULT '',
                  `waiting_json` text,
                  `messages_json` longtext,
                  `ui_json` longtext,
                  `last_task_ids` varchar(255) NOT NULL DEFAULT '',
                  `create_time` int(11) NOT NULL DEFAULT '0',
                  `update_time` int(11) NOT NULL DEFAULT '0',
                  PRIMARY KEY (`id`),
                  KEY `idx_aid_member` (`aid`,`member_id`),
                  KEY `idx_aid_status` (`aid`,`status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人Agent作图会话'"
            );
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            if (stripos($message, 'already exists') === false) {
                Log::error('digitalavatar agent session table: ' . $message);
            }
        }
    }

    private static function ensureAgentSessionIndexes(): void
    {
        $prefix = strval(config('database.connections.mysql.prefix', ''));
        $table = str_replace('`', '', $prefix . 'digitalavatar_agent_session');
        if ($table === '') {
            return;
        }
        $names = [];
        try {
            $indexes = Db::query('SHOW INDEX FROM `' . $table . '`');
            foreach ((array) $indexes as $index) {
                $name = strval($index['Key_name'] ?? '');
                if ($name !== '') {
                    $names[$name] = true;
                }
            }
        } catch (\Throwable $e) {
            return;
        }
        if (isset($names['uk_aid_member'])) {
            try {
                Db::execute('ALTER TABLE `' . $table . '` DROP INDEX `uk_aid_member`');
            } catch (\Throwable $e) {
                if (!self::isIgnorableSchemaError($e->getMessage())) {
                    Log::error('digitalavatar drop session unique: ' . $e->getMessage());
                }
            }
        }
        if (!isset($names['idx_aid_member'])) {
            try {
                Db::execute('ALTER TABLE `' . $table . '` ADD KEY `idx_aid_member` (`aid`,`member_id`)');
            } catch (\Throwable $e) {
                $message = $e->getMessage();
                if (stripos($message, 'Duplicate key') === false && stripos($message, 'already exists') === false) {
                    Log::error('digitalavatar add session index: ' . $message);
                }
            }
        }
    }

    private static function ensureCheckinTables(): void
    {
        $prefix = strval(config('database.connections.mysql.prefix', ''));
        $setTable = str_replace('`', '', $prefix . 'digitalavatar_checkin_set');
        $logTable = str_replace('`', '', $prefix . 'digitalavatar_checkin_log');
        if ($setTable === '' || $logTable === '') {
            return;
        }
        try {
            Db::execute(
                "CREATE TABLE IF NOT EXISTS `" . $setTable . "` (
                  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                  `aid` int(11) NOT NULL DEFAULT '0',
                  `enabled` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1开启签到',
                  `daily_credit` int(11) NOT NULL DEFAULT '10' COMMENT '每日签到创作点',
                  `expire_days` int(11) NOT NULL DEFAULT '30' COMMENT '奖励有效期天数，0长期有效',
                  `start_time` varchar(8) NOT NULL DEFAULT '00:00',
                  `end_time` varchar(8) NOT NULL DEFAULT '23:59',
                  `rule_text` varchar(2000) NOT NULL DEFAULT '',
                  `streak_rules_json` longtext COMMENT '连续签到额外奖励 [{days,credit,expire_days}]',
                  `quota_json` longtext COMMENT '每日签到附赠模型额度',
                  `create_time` int(11) NOT NULL DEFAULT '0',
                  `update_time` int(11) NOT NULL DEFAULT '0',
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `uk_aid` (`aid`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人签到设置'"
            );
        } catch (\Throwable $e) {
            if (!self::isIgnorableSchemaError($e->getMessage())) {
                Log::error('digitalavatar checkin set table: ' . $e->getMessage());
            }
        }
        try {
            Db::execute(
                "CREATE TABLE IF NOT EXISTS `" . $logTable . "` (
                  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                  `aid` int(11) NOT NULL DEFAULT '0',
                  `bid` int(11) NOT NULL DEFAULT '0',
                  `member_id` int(11) NOT NULL DEFAULT '0',
                  `checkin_date` varchar(10) NOT NULL DEFAULT '' COMMENT '签到日期Y-m-d',
                  `streak_days` int(11) NOT NULL DEFAULT '1',
                  `daily_credit` int(11) NOT NULL DEFAULT '0',
                  `extra_credit` int(11) NOT NULL DEFAULT '0',
                  `total_credit` int(11) NOT NULL DEFAULT '0',
                  `expire_days` int(11) NOT NULL DEFAULT '0',
                  `expire_time` int(11) NOT NULL DEFAULT '0',
                  `quota_json` longtext,
                  `reward_json` longtext,
                  `remark` varchar(255) NOT NULL DEFAULT '',
                  `create_time` int(11) NOT NULL DEFAULT '0',
                  `update_time` int(11) NOT NULL DEFAULT '0',
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `uk_member_date` (`aid`,`bid`,`member_id`,`checkin_date`),
                  KEY `idx_aid_date` (`aid`,`checkin_date`),
                  KEY `idx_member` (`aid`,`member_id`,`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人签到与奖励记录'"
            );
        } catch (\Throwable $e) {
            if (!self::isIgnorableSchemaError($e->getMessage())) {
                Log::error('digitalavatar checkin log table: ' . $e->getMessage());
            }
        }
    }

    private static function ensureInviteTables(): void
    {
        $prefix = strval(config('database.connections.mysql.prefix', ''));
        $setTable = str_replace('`', '', $prefix . 'digitalavatar_invite_set');
        $logTable = str_replace('`', '', $prefix . 'digitalavatar_invite_log');
        if ($setTable === '' || $logTable === '') {
            return;
        }
        try {
            Db::execute(
                "CREATE TABLE IF NOT EXISTS `" . $setTable . "` (
                  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                  `aid` int(11) NOT NULL DEFAULT '0',
                  `enabled` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1开启拉新奖励',
                  `inviter_credit` int(11) NOT NULL DEFAULT '50' COMMENT '邀请人创作点',
                  `invitee_credit` int(11) NOT NULL DEFAULT '20' COMMENT '新用户见面礼创作点',
                  `expire_days` int(11) NOT NULL DEFAULT '30' COMMENT '奖励有效期天数，0长期有效',
                  `daily_limit` int(11) NOT NULL DEFAULT '0' COMMENT '邀请人每日可奖励人数，0不限制',
                  `total_limit` int(11) NOT NULL DEFAULT '0' COMMENT '邀请人累计可奖励人数，0不限制',
                  `reward_invitee` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1同时奖励新用户',
                  `rule_text` varchar(2000) NOT NULL DEFAULT '',
                  `quota_json` longtext COMMENT '邀请人附赠模型额度',
                  `create_time` int(11) NOT NULL DEFAULT '0',
                  `update_time` int(11) NOT NULL DEFAULT '0',
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `uk_aid` (`aid`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人拉新设置'"
            );
        } catch (\Throwable $e) {
            if (!self::isIgnorableSchemaError($e->getMessage())) {
                Log::error('digitalavatar invite set table: ' . $e->getMessage());
            }
        }
        try {
            Db::execute(
                "CREATE TABLE IF NOT EXISTS `" . $logTable . "` (
                  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                  `aid` int(11) NOT NULL DEFAULT '0',
                  `bid` int(11) NOT NULL DEFAULT '0',
                  `inviter_id` int(11) NOT NULL DEFAULT '0' COMMENT '邀请人会员ID',
                  `invitee_id` int(11) NOT NULL DEFAULT '0' COMMENT '新用户会员ID',
                  `invite_date` varchar(10) NOT NULL DEFAULT '' COMMENT '拉新日期Y-m-d',
                  `inviter_credit` int(11) NOT NULL DEFAULT '0',
                  `invitee_credit` int(11) NOT NULL DEFAULT '0',
                  `expire_days` int(11) NOT NULL DEFAULT '0',
                  `expire_time` int(11) NOT NULL DEFAULT '0',
                  `quota_json` longtext,
                  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1已发放奖励',
                  `skip_reason` varchar(120) NOT NULL DEFAULT '',
                  `remark` varchar(255) NOT NULL DEFAULT '',
                  `create_time` int(11) NOT NULL DEFAULT '0',
                  `update_time` int(11) NOT NULL DEFAULT '0',
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `uk_aid_invitee` (`aid`,`invitee_id`),
                  KEY `idx_inviter_date` (`aid`,`inviter_id`,`invite_date`),
                  KEY `idx_aid_date` (`aid`,`invite_date`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人拉新邀请与奖励记录'"
            );
        } catch (\Throwable $e) {
            if (!self::isIgnorableSchemaError($e->getMessage())) {
                Log::error('digitalavatar invite log table: ' . $e->getMessage());
            }
        }
    }

    private static function ensureImageSkillUniqueKey(): void
    {
        DigitalAvatarImageSkillService::dedupeBySkillKey();
        $prefix = strval(config('database.connections.mysql.prefix', ''));
        $table = $prefix . 'digitalavatar_image_skill';
        try {
            Db::execute('ALTER TABLE `' . str_replace('`', '', $table) . '` ADD UNIQUE KEY `uk_aid_skill_key` (`aid`,`skill_key`)');
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            if (
                stripos($message, 'Duplicate') === false
                && stripos($message, 'already exists') === false
            ) {
                Log::error('digitalavatar image skill unique index: ' . $message);
            }
        }
    }

    private static function ensurePluginMenus(): void
    {
        $aids = [0];
        try {
            $aids = array_merge($aids, array_map('intval', Db::name('digitalavatar_setting')->column('aid')));
        } catch (\Throwable $e) {
        }
        foreach (array_unique($aids) as $aid) {
            try {
                PluginMenu::registerFromConfig('digitalavatar', intval($aid));
            } catch (\Throwable $e) {
            }
        }
    }

    private static function ensureImageSkills(): void
    {
        $aids = [0];
        try {
            $aids = array_merge($aids, array_map('intval', Db::name('digitalavatar_setting')->column('aid')));
        } catch (\Throwable $e) {
        }
        foreach (array_unique($aids) as $aid) {
            try {
                DigitalAvatarImageSkillService::ensureDefaults(intval($aid));
            } catch (\Throwable $e) {
            }
        }
    }

    private static function ensureChargeRuleUniqueKey(): void
    {
        $prefix = strval(config('database.connections.mysql.prefix', ''));
        $table = $prefix . 'digitalavatar_model_charge_rule';
        try {
            $indexes = Db::query('SHOW INDEX FROM `' . str_replace('`', '', $table) . '`');
        } catch (\Throwable $e) {
            return;
        }
        $hasOld = false;
        $hasNew = false;
        foreach ($indexes as $index) {
            $name = strval($index['Key_name'] ?? '');
            if ($name === 'uk_aid_task_model') {
                $hasOld = true;
            }
            if ($name === 'uk_aid_task_model_api') {
                $hasNew = true;
            }
        }
        if ($hasOld && !$hasNew) {
            try {
                Db::execute('ALTER TABLE `' . str_replace('`', '', $table) . '` DROP INDEX `uk_aid_task_model`');
            } catch (\Throwable $e) {
            }
        }
        try {
            $dupes = Db::query(
                'SELECT `aid`,`task_type`,`model_code`,`api_model`, MIN(`id`) AS keep_id, COUNT(*) AS cnt FROM `'
                . str_replace('`', '', $table)
                . '` GROUP BY `aid`,`task_type`,`model_code`,`api_model` HAVING cnt > 1'
            );
            foreach ($dupes as $dupe) {
                Db::name('digitalavatar_model_charge_rule')
                    ->where('aid', intval($dupe['aid']))
                    ->where('task_type', strval($dupe['task_type']))
                    ->where('model_code', strval($dupe['model_code']))
                    ->where('api_model', strval($dupe['api_model']))
                    ->where('id', '<>', intval($dupe['keep_id']))
                    ->delete();
            }
        } catch (\Throwable $e) {
        }
        if (!$hasNew) {
            try {
                Db::execute('ALTER TABLE `' . str_replace('`', '', $table) . '` ADD UNIQUE KEY `uk_aid_task_model_api` (`aid`,`task_type`,`model_code`,`api_model`)');
            } catch (\Throwable $e) {
                $message = $e->getMessage();
                if (stripos($message, 'Duplicate') === false) {
                    Log::error('digitalavatar charge rule unique index: ' . $message);
                }
            }
        }
    }

    private static function ensureApiModelAliasColumn(): void
    {
        $prefix = strval(config('database.connections.mysql.prefix', ''));
        $table = str_replace('`', '', $prefix . 'digitalavatar_model_charge_rule');
        if ($table === '') {
            return;
        }
        try {
            $columns = Db::query('SHOW COLUMNS FROM `' . $table . '` LIKE \'api_model_alias\'');
            if ($columns) {
                return;
            }
        } catch (\Throwable $e) {
            return;
        }
        try {
            Db::execute(
                'ALTER TABLE `' . $table . '` ADD COLUMN `api_model_alias` varchar(120) NOT NULL DEFAULT \'\' COMMENT \'用户端显示的 model 别称\' AFTER `api_model`'
            );
        } catch (\Throwable $e) {
            if (!self::isIgnorableSchemaError($e->getMessage())) {
                Log::error('digitalavatar api_model_alias column: ' . $e->getMessage());
            }
        }
    }

    private static function ensurePackageSubtitleColumn(): void
    {
        $prefix = strval(config('database.connections.mysql.prefix', ''));
        $table = str_replace('`', '', $prefix . 'digitalavatar_package');
        if ($table === '') {
            return;
        }
        try {
            $columns = Db::query("SHOW COLUMNS FROM `" . $table . "` LIKE 'subtitle'");
            if ($columns) {
                return;
            }
        } catch (\Throwable $e) {
            return;
        }
        try {
            Db::execute(
                'ALTER TABLE `' . $table . '` ADD COLUMN `subtitle` varchar(200) NOT NULL DEFAULT \'\' COMMENT \'副标题\' AFTER `title`'
            );
        } catch (\Throwable $e) {
            if (!self::isIgnorableSchemaError($e->getMessage())) {
                Log::error('digitalavatar package subtitle column: ' . $e->getMessage());
            }
        }
    }

    public static function ensurePortalDomainColumn(): void
    {
        if (self::$portalDomainColumnReady) {
            return;
        }
        self::ensureColumn(
            'digitalavatar_setting',
            'portal_domain',
            "ADD COLUMN `portal_domain` varchar(255) NOT NULL DEFAULT '' COMMENT '数字人站点域名，访问根地址时识别aid' AFTER `portal_path`"
        );
        $prefix = strval(config('database.connections.mysql.prefix', ''));
        $table = str_replace('`', '', $prefix . 'digitalavatar_setting');
        try {
            $columns = Db::query("SHOW COLUMNS FROM `" . $table . "` LIKE 'portal_domain'");
            self::$portalDomainColumnReady = !empty($columns);
        } catch (\Throwable $e) {
        }
    }

    public static function ensureInvoiceReapplyColumn(): void
    {
        self::ensureColumn(
            'digitalavatar_invoice',
            'allow_reapply',
            "ADD COLUMN `allow_reapply` tinyint(1) NOT NULL DEFAULT '0' COMMENT '驳回后是否允许再次申请' AFTER `admin_remark`"
        );
    }

    public static function ensureConversationSchema(): void
    {
        DigitalAvatarConversationService::ensureSchema();
    }

    public static function ensureInspireSchema(): void
    {
        DigitalAvatarInspireService::ensureSchema();
    }

    public static function ensureConversationDefaultColumn(): void
    {
        self::ensureColumn(
            'digitalavatar_conversation',
            'is_default',
            "ADD COLUMN `is_default` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1默认对话记录' AFTER `pinned`"
        );
    }

    public static function ensureConversationTaskColumn(): void
    {
        self::ensureColumn(
            'digitalavatar_task',
            'conversation_id',
            "ADD COLUMN `conversation_id` int(11) NOT NULL DEFAULT '0' AFTER `member_id`"
        );
        $prefix = strval(config('database.connections.mysql.prefix', ''));
        $table = str_replace('`', '', $prefix . 'digitalavatar_task');
        if ($table === '') {
            return;
        }
        try {
            $indexes = Db::query('SHOW INDEX FROM `' . $table . '`');
            foreach ((array) $indexes as $index) {
                if (strval($index['Key_name'] ?? '') === 'idx_conversation') {
                    return;
                }
            }
            Db::execute('ALTER TABLE `' . $table . '` ADD KEY `idx_conversation` (`aid`,`member_id`,`conversation_id`)');
        } catch (\Throwable $e) {
            if (!self::isIgnorableSchemaError($e->getMessage())) {
                Log::error('digitalavatar conversation index: ' . $e->getMessage());
            }
        }
    }

    public static function ensureTaskStarredColumn(): void
    {
        self::ensureColumn(
            'digitalavatar_task',
            'starred',
            "ADD COLUMN `starred` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1收藏' AFTER `deleted_time`"
        );
    }

    public static function ensureCreditCnyRatioColumn(): void
    {
        if (self::$creditCnyRatioReady) {
            return;
        }
        self::ensureColumn(
            'digitalavatar_setting',
            'credit_per_yuan',
            "ADD COLUMN `credit_per_yuan` decimal(16,5) NOT NULL DEFAULT '1000.00000' COMMENT '1元对应创作点数' AFTER `initial_credit`"
        );
        self::$creditCnyRatioReady = true;
    }

    public static function ensureSidebarSupport(): void
    {
        if (self::$sidebarSupportReady) {
            return;
        }
        self::$sidebarSupportReady = true;
        self::ensureSettingRowCapacity();
        self::ensureColumn(
            'digitalavatar_setting',
            'sidebar_json',
            "ADD COLUMN `sidebar_json` longtext NULL COMMENT '用户端左侧菜单JSON'"
        );
        try {
            self::ensurePluginMenus();
        } catch (\Throwable $e) {
        }
    }

    public static function ensurePackageConcurrentColumns(): void
    {
        self::ensureColumn(
            'digitalavatar_package',
            'max_concurrent_tasks',
            "ADD COLUMN `max_concurrent_tasks` int(11) NOT NULL DEFAULT '0' COMMENT '会员套餐任务并发数，0不提高' AFTER `validity_days`"
        );
        self::ensureColumn(
            'digitalavatar_credit_lot',
            'package_id',
            "ADD COLUMN `package_id` int(11) NOT NULL DEFAULT '0' COMMENT '来源会员套餐' AFTER `source_id`"
        );
    }

    private static function ensureColumn(string $tableName, string $column, string $ddl): void
    {
        $prefix = strval(config('database.connections.mysql.prefix', ''));
        $table = str_replace('`', '', $prefix . $tableName);
        if ($table === '' || $column === '') {
            return;
        }
        try {
            $columns = Db::query("SHOW COLUMNS FROM `" . $table . "` LIKE '" . str_replace("'", '', $column) . "'");
            if ($columns) {
                return;
            }
        } catch (\Throwable $e) {
            return;
        }
        $sql = 'ALTER TABLE `' . $table . '` ' . $ddl;
        try {
            Db::execute($sql);
        } catch (\Throwable $e) {
            if (self::repairRowSizeAndRetry($sql, $e)) {
                return;
            }
            if (!self::isIgnorableSchemaError($e->getMessage()) && !self::isRowSizeError($e->getMessage())) {
                Log::error('digitalavatar column ' . $tableName . '.' . $column . ': ' . $e->getMessage());
            }
        }
    }

    private static function ensureOrgTables(): void
    {
        $prefix = strval(config('database.connections.mysql.prefix', ''));
        $creates = [
            'digitalavatar_commission_level' => "CREATE TABLE IF NOT EXISTS `{table}` (
                  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                  `aid` int(11) NOT NULL DEFAULT '0',
                  `name` varchar(120) NOT NULL DEFAULT '',
                  `level` int(11) NOT NULL DEFAULT '1',
                  `rate` decimal(8,2) NOT NULL DEFAULT '0.00',
                  `is_default` tinyint(1) NOT NULL DEFAULT '0',
                  `auto_upgrade` tinyint(1) NOT NULL DEFAULT '0',
                  `status` tinyint(1) NOT NULL DEFAULT '1',
                  `sort` int(11) NOT NULL DEFAULT '0',
                  `remark` varchar(255) NOT NULL DEFAULT '',
                  `create_time` int(11) NOT NULL DEFAULT '0',
                  `update_time` int(11) NOT NULL DEFAULT '0',
                  PRIMARY KEY (`id`),
                  KEY `idx_aid_level` (`aid`,`level`),
                  KEY `idx_aid_status` (`aid`,`status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人团队长分佣等级'",
            'digitalavatar_withdraw' => "CREATE TABLE IF NOT EXISTS `{table}` (
                  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                  `aid` int(11) NOT NULL DEFAULT '0',
                  `bid` int(11) NOT NULL DEFAULT '0',
                  `member_id` int(11) NOT NULL DEFAULT '0',
                  `amount` decimal(12,2) NOT NULL DEFAULT '0.00',
                  `pay_info` varchar(500) NOT NULL DEFAULT '',
                  `status` tinyint(1) NOT NULL DEFAULT '0',
                  `audit_remark` varchar(255) NOT NULL DEFAULT '',
                  `audit_time` int(11) NOT NULL DEFAULT '0',
                  `remark` varchar(255) NOT NULL DEFAULT '',
                  `create_time` int(11) NOT NULL DEFAULT '0',
                  `update_time` int(11) NOT NULL DEFAULT '0',
                  PRIMARY KEY (`id`),
                  KEY `idx_aid_status` (`aid`,`status`,`id`),
                  KEY `idx_member` (`aid`,`member_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人提现申请'",
            'digitalavatar_admin_role' => "CREATE TABLE IF NOT EXISTS `{table}` (
                  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                  `aid` int(11) NOT NULL DEFAULT '0',
                  `name` varchar(120) NOT NULL DEFAULT '',
                  `permissions` longtext,
                  `remark` varchar(255) NOT NULL DEFAULT '',
                  `status` tinyint(1) NOT NULL DEFAULT '1',
                  `sort` int(11) NOT NULL DEFAULT '0',
                  `create_time` int(11) NOT NULL DEFAULT '0',
                  `update_time` int(11) NOT NULL DEFAULT '0',
                  PRIMARY KEY (`id`),
                  KEY `idx_aid_status` (`aid`,`status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人管理员角色'",
            'digitalavatar_admin_user' => "CREATE TABLE IF NOT EXISTS `{table}` (
                  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                  `aid` int(11) NOT NULL DEFAULT '0',
                  `username` varchar(60) NOT NULL DEFAULT '',
                  `password` varchar(255) NOT NULL DEFAULT '',
                  `role_id` int(11) NOT NULL DEFAULT '0',
                  `remark` varchar(255) NOT NULL DEFAULT '',
                  `status` tinyint(1) NOT NULL DEFAULT '1',
                  `last_login_time` int(11) NOT NULL DEFAULT '0',
                  `create_time` int(11) NOT NULL DEFAULT '0',
                  `update_time` int(11) NOT NULL DEFAULT '0',
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `uk_aid_username` (`aid`,`username`),
                  KEY `idx_aid_role` (`aid`,`role_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人管理员'",
            'digitalavatar_agent' => "CREATE TABLE IF NOT EXISTS `{table}` (
                  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                  `aid` int(11) NOT NULL DEFAULT '0',
                  `name` varchar(120) NOT NULL DEFAULT '',
                  `mobile` varchar(20) NOT NULL DEFAULT '',
                  `password` varchar(255) NOT NULL DEFAULT '',
                  `money` decimal(12,2) NOT NULL DEFAULT '0.00',
                  `expire_time` int(11) NOT NULL DEFAULT '0',
                  `remark` varchar(255) NOT NULL DEFAULT '',
                  `status` tinyint(1) NOT NULL DEFAULT '1',
                  `create_time` int(11) NOT NULL DEFAULT '0',
                  `update_time` int(11) NOT NULL DEFAULT '0',
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `uk_aid_mobile` (`aid`,`mobile`),
                  KEY `idx_aid_status` (`aid`,`status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人代理账号'",
        ];
        foreach ($creates as $tableName => $ddl) {
            $table = str_replace('`', '', $prefix . $tableName);
            try {
                Db::execute(str_replace('{table}', $table, $ddl));
            } catch (\Throwable $e) {
                if (!self::isIgnorableSchemaError($e->getMessage())) {
                    Log::error('digitalavatar create ' . $tableName . ': ' . $e->getMessage());
                }
            }
        }
        $alters = [
            'digitalavatar_package_order' => [
                'parent_mid' => "ADD COLUMN `parent_mid` int(11) NOT NULL DEFAULT '0' COMMENT '分佣上级会员ID' AFTER `grant_status`",
                'commission_amount' => "ADD COLUMN `commission_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '分佣金额' AFTER `parent_mid`",
                'commission_rate' => "ADD COLUMN `commission_rate` decimal(8,2) NOT NULL DEFAULT '0.00' COMMENT '分佣比例' AFTER `commission_amount`",
                'commission_level_id' => "ADD COLUMN `commission_level_id` int(11) NOT NULL DEFAULT '0' AFTER `commission_rate`",
            ],
            'digitalavatar_member_credit' => [
                'commission_balance' => "ADD COLUMN `commission_balance` decimal(12,2) NOT NULL DEFAULT '0.00' AFTER `max_concurrent_tasks`",
                'commission_total' => "ADD COLUMN `commission_total` decimal(12,2) NOT NULL DEFAULT '0.00' AFTER `commission_balance`",
                'commission_withdrawn' => "ADD COLUMN `commission_withdrawn` decimal(12,2) NOT NULL DEFAULT '0.00' AFTER `commission_total`",
                'commission_level_id' => "ADD COLUMN `commission_level_id` int(11) NOT NULL DEFAULT '0' AFTER `commission_withdrawn`",
            ],
        ];
        foreach ($alters as $tableName => $columns) {
            $table = str_replace('`', '', $prefix . $tableName);
            if ($table === '') {
                continue;
            }
            foreach ($columns as $column => $ddl) {
                try {
                    $exists = Db::query("SHOW COLUMNS FROM `" . $table . "` LIKE '" . $column . "'");
                    if ($exists) {
                        continue;
                    }
                    Db::execute('ALTER TABLE `' . $table . '` ' . $ddl);
                } catch (\Throwable $e) {
                    if (!self::isIgnorableSchemaError($e->getMessage())) {
                        Log::error('digitalavatar alter ' . $tableName . '.' . $column . ': ' . $e->getMessage());
                    }
                }
            }
        }
    }

    private static function ensureCreditDecimalColumns(): void
    {
        $prefix = strval(config('database.connections.mysql.prefix', ''));
        $decimal = "decimal(16,5) NOT NULL DEFAULT '0.00000'";
        $columns = [
            'digitalavatar_setting' => ['initial_credit' => $decimal . " COMMENT '新会员初始创作点'"],
            'digitalavatar_model_charge_rule' => [
                'unit_credit' => $decimal . " COMMENT '单位创作点，最小0.00001'",
                'base_credit' => $decimal . " COMMENT '基础创作点，最小0.00001'",
            ],
            'digitalavatar_member_credit' => [
                'credit_total' => $decimal,
                'credit_used' => $decimal,
                'credit_balance' => $decimal . " COMMENT '未扣除冻结点数的余额'",
                'credit_frozen' => $decimal,
            ],
            'digitalavatar_credit_log' => [
                'credit_change' => $decimal,
                'change_before' => $decimal,
                'change_after' => $decimal,
            ],
            'digitalavatar_package' => [
                'credit_amount' => $decimal,
                'gift_credit' => $decimal,
            ],
            'digitalavatar_package_order' => [
                'credit_amount' => $decimal,
            ],
            'digitalavatar_redeem_code' => [
                'credit_amount' => $decimal,
            ],
            'digitalavatar_redeem_log' => [
                'credit_amount' => $decimal,
            ],
            'digitalavatar_task' => [
                'credit_cost' => $decimal,
            ],
            'digitalavatar_credit_lot' => [
                'amount_total' => $decimal,
                'amount_remain' => $decimal,
            ],
        ];
        foreach ($columns as $tableName => $defs) {
            $table = str_replace('`', '', $prefix . $tableName);
            foreach ($defs as $column => $definition) {
                try {
                    Db::execute("ALTER TABLE `{$table}` MODIFY COLUMN `{$column}` {$definition}");
                } catch (\Throwable $e) {
                    if (!self::isIgnorableSchemaError($e->getMessage())) {
                        Log::error('digitalavatar alter decimal ' . $tableName . '.' . $column . ': ' . $e->getMessage());
                    }
                }
            }
        }
    }

    private static function migrateSchema(): void
    {
        self::ensurePluginTablesDynamic();
        self::ensureSettingRowCapacity();
        if (!self::settingTableExists()) {
            $sqlFile = __DIR__ . '/../database/install.sql';
            if (file_exists($sqlFile)) {
                $sql = file_get_contents($sqlFile);
                if (is_string($sql) && trim($sql) !== '') {
                    $prefix = config('database.connections.mysql.prefix', '');
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
                            if (self::isIgnorableSchemaError($e->getMessage())) {
                                continue;
                            }
                            if (self::repairRowSizeAndRetry($statement, $e)) {
                                continue;
                            }
                            Log::error('digitalavatar install sql error: ' . $e->getMessage());
                            if (self::isRowSizeError($e->getMessage())) {
                                continue;
                            }
                            throw $e;
                        }
                    }
                }
            }
        }

        self::ensureSettingRowCapacity();
        self::ensureChargeRuleUniqueKey();
        self::ensureApiModelAliasColumn();
        self::ensurePackageSubtitleColumn();
        self::ensurePackageConcurrentColumns();
        self::ensurePortalDomainColumn();
        self::ensureInvoiceReapplyColumn();
        self::ensureCreditCnyRatioColumn();
        self::ensureSidebarSupport();
        self::ensureConversationSchema();
        self::ensureImageSkillUniqueKey();
        self::ensureImageSkills();
        self::ensurePluginMenus();
        self::ensureAgentSessionTable();
        self::ensureAgentSessionIndexes();
        self::ensureCheckinTables();
        self::ensureInviteTables();
        self::ensureOrgTables();
        self::ensureCreditDecimalColumns();
        self::ensureInspireSchema();
    }

    private static function isSchemaReady(): bool
    {
        try {
            if (strval(Cache::get(self::SCHEMA_CACHE_KEY)) === self::SCHEMA_VERSION) {
                return true;
            }
        } catch (\Throwable $e) {
        }
        return is_file(self::schemaMarkerFile());
    }

    private static function markSchemaReady(): void
    {
        try {
            Cache::set(self::SCHEMA_CACHE_KEY, self::SCHEMA_VERSION, 86400 * 30);
        } catch (\Throwable $e) {
        }
        $marker = self::schemaMarkerFile();
        $dir = dirname($marker);
        if ($dir !== '' && !is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents($marker, self::SCHEMA_VERSION);
    }

    private static function schemaMarkerFile(): string
    {
        $runtime = '';
        try {
            $runtime = strval(function_exists('runtime_path') ? runtime_path() : '');
        } catch (\Throwable $e) {
            $runtime = '';
        }
        if ($runtime === '') {
            $runtime = dirname(__DIR__, 4) . '/runtime/';
        }
        return rtrim($runtime, '/\\') . '/digitalavatar_schema_' . preg_replace('/[^0-9A-Za-z]+/', '_', self::SCHEMA_VERSION) . '.ok';
    }

    private static function acquireSchemaLock()
    {
        $file = dirname(self::schemaMarkerFile()) . '/digitalavatar_schema.lock';
        $fp = @fopen($file, 'c');
        if (!is_resource($fp)) {
            return null;
        }
        @flock($fp, LOCK_EX);
        return $fp;
    }

    private static function releaseSchemaLock($fp): void
    {
        if (!is_resource($fp)) {
            return;
        }
        @flock($fp, LOCK_UN);
        @fclose($fp);
    }

    private static function settingTableExists(): bool
    {
        $prefix = strval(config('database.connections.mysql.prefix', ''));
        $table = str_replace('`', '', $prefix . 'digitalavatar_setting');
        if ($table === '') {
            return false;
        }
        try {
            $exists = Db::query("SHOW TABLES LIKE '" . str_replace("'", '', $table) . "'");
            return !empty($exists);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function ensurePluginTablesDynamic(): void
    {
        $prefix = strval(config('database.connections.mysql.prefix', ''));
        $like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $prefix) . 'digitalavatar\\_%';
        try {
            $rows = Db::query("SHOW TABLE STATUS WHERE `Name` LIKE '" . str_replace("'", '', $like) . "'");
        } catch (\Throwable $e) {
            return;
        }
        if (!is_array($rows)) {
            return;
        }
        foreach ($rows as $row) {
            $name = strval($row['Name'] ?? $row['name'] ?? '');
            $format = strtolower(strval($row['Row_format'] ?? $row['row_format'] ?? ''));
            if ($name === '' || $format === 'dynamic' || $format === 'compressed') {
                continue;
            }
            self::convertTableToDynamic($name);
        }
    }

    private static function ensureSettingRowCapacity(): void
    {
        $prefix = strval(config('database.connections.mysql.prefix', ''));
        $table = str_replace('`', '', $prefix . 'digitalavatar_setting');
        if ($table === '') {
            return;
        }
        self::convertTableToDynamic($table);
        $converts = [
            'notice_text' => "text NULL COMMENT '公告'",
            'oem_logo' => "text NULL COMMENT 'OEM Logo'",
            'oem_login_bg' => "text NULL COMMENT 'OEM登录背景'",
        ];
        foreach ($converts as $column => $definition) {
            try {
                $exists = Db::query("SHOW COLUMNS FROM `" . $table . "` LIKE '" . $column . "'");
                if (!$exists) {
                    continue;
                }
                $type = strtolower(strval($exists[0]['Type'] ?? $exists[0]['type'] ?? ''));
                if (strpos($type, 'text') !== false || strpos($type, 'blob') !== false) {
                    continue;
                }
                Db::execute('ALTER TABLE `' . $table . '` MODIFY COLUMN `' . $column . '` ' . $definition);
            } catch (\Throwable $e) {
                if (self::isRowSizeError($e->getMessage())) {
                    self::convertTableToDynamic($table);
                    try {
                        Db::execute('ALTER TABLE `' . $table . '` MODIFY COLUMN `' . $column . '` ' . $definition);
                    } catch (\Throwable $retry) {
                        Log::error('digitalavatar setting shrink ' . $column . ': ' . $retry->getMessage());
                    }
                    continue;
                }
                if (!self::isIgnorableSchemaError($e->getMessage())) {
                    Log::error('digitalavatar setting shrink ' . $column . ': ' . $e->getMessage());
                }
            }
        }
    }

    private static function convertTableToDynamic(string $table): void
    {
        $table = str_replace('`', '', $table);
        if ($table === '') {
            return;
        }
        try {
            Db::execute('ALTER TABLE `' . $table . '` ROW_FORMAT=DYNAMIC');
        } catch (\Throwable $e) {
            if (!self::isIgnorableSchemaError($e->getMessage())) {
                Log::error('digitalavatar row_format ' . $table . ': ' . $e->getMessage());
            }
        }
    }

    private static function isRowSizeError(string $message): bool
    {
        return stripos($message, 'Row size too large') !== false || stripos($message, '1118') !== false;
    }

    private static function repairRowSizeAndRetry(string $statement, \Throwable $e): bool
    {
        if (!self::isRowSizeError($e->getMessage())) {
            return false;
        }
        if (preg_match('/ALTER\s+TABLE\s+`?([a-zA-Z0-9_]+)`?/i', $statement, $match)) {
            self::convertTableToDynamic($match[1]);
            try {
                Db::execute($statement);
                return true;
            } catch (\Throwable $retry) {
                if (self::isIgnorableSchemaError($retry->getMessage())) {
                    return true;
                }
                Log::error('digitalavatar row size retry: ' . $retry->getMessage());
            }
        }
        if (preg_match('/CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?\s+`?([a-zA-Z0-9_]+)`?/i', $statement, $match)) {
            $fixed = preg_replace('/ROW_FORMAT\s*=\s*COMPACT/i', 'ROW_FORMAT=DYNAMIC', $statement);
            if (!is_string($fixed) || $fixed === '') {
                $fixed = $statement;
            }
            try {
                Db::execute($fixed);
                return true;
            } catch (\Throwable $retry) {
                if (self::isIgnorableSchemaError($retry->getMessage())) {
                    return true;
                }
                Log::error('digitalavatar create row size retry: ' . $retry->getMessage());
            }
        }
        return false;
    }

    private static function isIgnorableSchemaError(string $message): bool
    {
        $needles = [
            'already exists',
            'Duplicate column name',
            'Duplicate key name',
            'Duplicate index',
            "Can't DROP",
            'check that column/key exists',
            'check that it exists',
            'Unknown table',
            'doesn\'t exist',
            '1091',
        ];
        foreach ($needles as $needle) {
            if (stripos($message, $needle) !== false) {
                return true;
            }
        }
        return false;
    }
}
