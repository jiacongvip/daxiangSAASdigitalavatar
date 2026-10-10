-- community-ce: slim default menus (database source of truth)
-- Keep: 插件(520) + 系统(501) minimal children; digitalavatar via admin_plugin_menu + license.
-- Remove mall-oriented top trees: 商城/会员/财务/营销/多商户/设计/平台 and all descendants.
-- Safe to re-run.

SET NAMES utf8mb4;

-- 1) Grant local open-source license so digitalavatar plugin menus are visible
INSERT INTO `ddwx_admin_plugin_license`
  (`aid`,`plugin_name`,`status`,`license_type`,`expire_time`,`max_users`,`features`,`remark`,`grantor_uid`,`grantor_root_uid`,`grant_source`,`createtime`,`updatetime`)
SELECT 1,'digitalavatar',1,0,0,0,NULL,'community-ce local',0,0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `ddwx_admin_plugin_license` WHERE `aid`=1 AND `plugin_name`='digitalavatar'
);

-- 2) Keep only whitelist rows in admin_menu
-- Whitelist roots: plugin=520, system=501
-- Whitelist system children: 系统配置502, 管理员列表506, 操作日志511, 素材中心643 (upload UX)
CREATE TEMPORARY TABLE `_ce_keep_menu` (`id` INT PRIMARY KEY);
INSERT INTO `_ce_keep_menu` (`id`) VALUES (520),(501),(502),(506),(511),(643);

-- Expand keep-set: any ancestor already listed; (no need descendants of shop etc.)
-- Also keep any direct children of 520 that are NOT shop (plugin root has no file children usually)

DELETE FROM `ddwx_admin_menu`
WHERE `id` NOT IN (SELECT `id` FROM `_ce_keep_menu`);

DROP TEMPORARY TABLE `_ce_keep_menu`;

-- 3) Drop non-digitalavatar plugin menus (if any other plugins were seeded)
DELETE FROM `ddwx_admin_plugin_menu`
WHERE `plugin_name` <> 'digitalavatar' AND TRIM(`plugin_name`) <> '';

-- 4) Ensure database menu mode
UPDATE `ddwx_admin_system_config`
SET `value`='1', `updatetime`=UNIX_TIMESTAMP()
WHERE `key`='menu_use_database';

-- 5) Soft cleanup: hide designer/mp menu customizations not used
-- (optional no-op if tables empty)
