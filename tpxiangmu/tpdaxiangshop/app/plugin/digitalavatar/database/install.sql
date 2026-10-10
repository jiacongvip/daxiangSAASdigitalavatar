CREATE TABLE IF NOT EXISTS `{prefix}digitalavatar_setting` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL DEFAULT '0',
  `product_title` varchar(120) NOT NULL DEFAULT 'AI数字人工作台',
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `charge_mode` tinyint(1) NOT NULL DEFAULT '2' COMMENT '1免费 2按额度',
  `initial_credit` decimal(16,5) NOT NULL DEFAULT '100.00000',
  `credit_per_yuan` decimal(16,5) NOT NULL DEFAULT '1000.00000' COMMENT '1元对应创作点数',
  `max_concurrent_tasks` int(11) NOT NULL DEFAULT '3',
  `portal_path` varchar(255) NOT NULL DEFAULT '/portal/digitalavatar/',
  `portal_domain` varchar(255) NOT NULL DEFAULT '' COMMENT '数字人站点域名，访问根地址时识别aid',
  `notice_text` text NULL COMMENT '公告',
  `oem_logo` text NULL COMMENT 'OEM Logo',
  `oem_login_bg` text NULL COMMENT 'OEM登录背景',
  `login_style` varchar(20) NOT NULL DEFAULT 'niwa' COMMENT 'classic原登录页 niwa拟娲风格',
  `oem_color` varchar(20) NOT NULL DEFAULT '',
  `oem_subtitle` varchar(200) NOT NULL DEFAULT '',
  `oem_contact_wechat` varchar(80) NOT NULL DEFAULT '',
  `oem_contact_tel` varchar(30) NOT NULL DEFAULT '',
  `agent_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `agent_can_grant` tinyint(1) NOT NULL DEFAULT '1',
  `agent_can_redeem` tinyint(1) NOT NULL DEFAULT '1',
  `creative_video_enabled` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否开启创意视频',
  `avatar_enabled` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否开启数字人',
  `audio_enabled` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否开启音频',
  `image_enabled` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否开启图片创作',
  `image_agent_enabled` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否开启Agent作图画板',
  `image_agent_llm_enabled` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否开启Agent作图大模型',
  `image_agent_llm_base_url` varchar(255) NOT NULL DEFAULT '' COMMENT 'Agent作图LLM接口地址',
  `image_agent_llm_api_key` varchar(255) NOT NULL DEFAULT '' COMMENT 'Agent作图LLM密钥',
  `image_agent_llm_model` varchar(100) NOT NULL DEFAULT '' COMMENT 'Agent作图LLM模型',
  `image_agent_llm_timeout` int(11) NOT NULL DEFAULT '45' COMMENT 'Agent作图LLM超时秒',
  `image_agent_prefer_draft` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Agent作图默认先出草稿',
  `image_agent_draft_model_id` int(11) NOT NULL DEFAULT '0' COMMENT '草稿图片模型ID',
  `image_agent_draft_size` varchar(32) NOT NULL DEFAULT '1024x1024' COMMENT '草稿默认尺寸',
  `image_agent_draft_quality` varchar(20) NOT NULL DEFAULT 'low' COMMENT '草稿默认质量',
  `image_agent_hd_model_id` int(11) NOT NULL DEFAULT '0' COMMENT '高清图片模型ID',
  `image_agent_hd_size` varchar(32) NOT NULL DEFAULT '1024x1024' COMMENT '高清默认尺寸',
  `image_agent_hd_quality` varchar(20) NOT NULL DEFAULT 'high' COMMENT '高清默认质量',
  `create_time` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_aid` (`aid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人站点设置';

CREATE TABLE IF NOT EXISTS `{prefix}digitalavatar_ad` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL DEFAULT '0',
  `placement` varchar(20) NOT NULL DEFAULT 'carousel' COMMENT 'carousel左侧轮播，side右侧广告',
  `title` varchar(120) NOT NULL DEFAULT '',
  `tags` varchar(255) NOT NULL DEFAULT '' COMMENT '多个标签用英文逗号分隔',
  `description` varchar(500) NOT NULL DEFAULT '',
  `image_url` varchar(500) NOT NULL DEFAULT '',
  `background` varchar(500) NOT NULL DEFAULT '' COMMENT 'CSS背景色或渐变',
  `link_url` varchar(500) NOT NULL DEFAULT '',
  `sort` int(11) NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `start_time` varchar(19) NOT NULL DEFAULT '',
  `end_time` varchar(19) NOT NULL DEFAULT '',
  `create_time` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_aid_status_sort` (`aid`,`status`,`sort`),
  KEY `idx_aid_time` (`aid`,`start_time`,`end_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人首页轮播广告';

ALTER TABLE `{prefix}digitalavatar_ad`
  ADD COLUMN `placement` varchar(20) NOT NULL DEFAULT 'carousel' COMMENT 'carousel左侧轮播，side右侧广告' AFTER `aid`;

CREATE TABLE IF NOT EXISTS `{prefix}digitalavatar_provider_config` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL DEFAULT '0',
  `provider_key` varchar(60) NOT NULL DEFAULT '',
  `provider_name` varchar(120) NOT NULL DEFAULT '',
  `base_url` varchar(255) NOT NULL DEFAULT '',
  `api_key` varchar(255) NOT NULL DEFAULT '',
  `api_secret` varchar(255) NOT NULL DEFAULT '',
  `callback_secret` varchar(255) NOT NULL DEFAULT '',
  `extra_json` longtext,
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `create_time` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_aid_provider` (`aid`,`provider_key`),
  KEY `idx_aid_status` (`aid`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人提供商配置';

CREATE TABLE IF NOT EXISTS `{prefix}digitalavatar_model` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL DEFAULT '0',
  `task_type` varchar(60) NOT NULL DEFAULT '',
  `title` varchar(120) NOT NULL DEFAULT '',
  `model_code` varchar(120) NOT NULL DEFAULT '',
  `provider_key` varchar(60) NOT NULL DEFAULT '',
  `description` varchar(500) NOT NULL DEFAULT '',
  `config_json` longtext,
  `sort` int(11) NOT NULL DEFAULT '0',
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `create_time` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_aid_task_model` (`aid`,`task_type`,`model_code`),
  KEY `idx_aid_task_status_sort` (`aid`,`task_type`,`status`,`sort`),
  KEY `idx_aid_provider` (`aid`,`provider_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人可用模型目录';

CREATE TABLE IF NOT EXISTS `{prefix}digitalavatar_model_charge_rule` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL DEFAULT '0',
  `task_type` varchar(60) NOT NULL DEFAULT '',
  `model_code` varchar(120) NOT NULL DEFAULT '',
  `match_mode` varchar(20) NOT NULL DEFAULT 'platform_code' COMMENT 'platform_code按产品线 model按接口版本号',
  `platform_code` varchar(60) NOT NULL DEFAULT '',
  `api_model` varchar(120) NOT NULL DEFAULT '' COMMENT '接口 model 版本号，空表示整条产品线',
  `api_model_alias` varchar(120) NOT NULL DEFAULT '' COMMENT '用户端显示的 model 别称',
  `rule_name` varchar(120) NOT NULL DEFAULT '',
  `billing_mode` varchar(30) NOT NULL DEFAULT 'fixed' COMMENT 'fixed/seconds/chars/count',
  `unit_count` int(11) NOT NULL DEFAULT '1',
  `unit_credit` decimal(16,5) NOT NULL DEFAULT '1.00000',
  `base_credit` decimal(16,5) NOT NULL DEFAULT '0.00000',
  `success_charge` int(11) NOT NULL DEFAULT '1',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `create_time` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_aid_task_model_api` (`aid`,`task_type`,`model_code`,`api_model`),
  KEY `idx_aid_status` (`aid`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人模型计费规则';

CREATE TABLE IF NOT EXISTS `{prefix}digitalavatar_daily_quota_rule` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL DEFAULT '0',
  `model_code` varchar(120) NOT NULL DEFAULT '',
  `quota_name` varchar(120) NOT NULL DEFAULT '',
  `quota_count` int(11) NOT NULL DEFAULT '0',
  `carryover` tinyint(1) NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `create_time` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_aid_model` (`aid`,`model_code`),
  KEY `idx_aid_status` (`aid`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人每日额度规则';

CREATE TABLE IF NOT EXISTS `{prefix}digitalavatar_daily_quota_usage` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL DEFAULT '0',
  `bid` int(11) NOT NULL DEFAULT '0',
  `member_id` int(11) NOT NULL DEFAULT '0',
  `model_code` varchar(120) NOT NULL DEFAULT '',
  `quota_date` date NOT NULL,
  `quota_used` int(11) NOT NULL DEFAULT '0',
  `quota_reserved` int(11) NOT NULL DEFAULT '0',
  `create_time` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_member_model_date` (`aid`,`bid`,`member_id`,`model_code`,`quota_date`),
  KEY `idx_aid_date` (`aid`,`quota_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人每日额度使用记录';

CREATE TABLE IF NOT EXISTS `{prefix}digitalavatar_public_avatar` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL DEFAULT '0',
  `name` varchar(120) NOT NULL DEFAULT '',
  `cover_url` varchar(500) NOT NULL DEFAULT '',
  `preview_url` varchar(500) NOT NULL DEFAULT '',
  `provider_key` varchar(60) NOT NULL DEFAULT '',
  `model_code` varchar(120) NOT NULL DEFAULT '',
  `config_json` longtext,
  `visible_member_ids` text COMMENT '可见归属用户ID，逗号分隔，0表示所有人可见',
  `sort` int(11) NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `create_time` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_aid_status_sort` (`aid`,`status`,`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人公共形象';

CREATE TABLE IF NOT EXISTS `{prefix}digitalavatar_public_voice` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL DEFAULT '0',
  `name` varchar(120) NOT NULL DEFAULT '',
  `preview_url` varchar(500) NOT NULL DEFAULT '',
  `provider_key` varchar(60) NOT NULL DEFAULT '',
  `model_code` varchar(120) NOT NULL DEFAULT '',
  `config_json` longtext,
  `visible_member_ids` text COMMENT '可见归属用户ID，逗号分隔，0表示所有人可见',
  `sort` int(11) NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `create_time` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_aid_status_sort` (`aid`,`status`,`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人公共声音';

CREATE TABLE IF NOT EXISTS `{prefix}digitalavatar_member_avatar` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL DEFAULT '0',
  `bid` int(11) NOT NULL DEFAULT '0',
  `member_id` int(11) NOT NULL DEFAULT '0',
  `name` varchar(120) NOT NULL DEFAULT '',
  `avatar_url` varchar(500) NOT NULL DEFAULT '',
  `cover_url` varchar(500) NOT NULL DEFAULT '',
  `preview_url` varchar(500) NOT NULL DEFAULT '',
  `provider_key` varchar(60) NOT NULL DEFAULT '',
  `model_code` varchar(120) NOT NULL DEFAULT '',
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `config_json` longtext,
  `create_time` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_member_status` (`aid`,`bid`,`member_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人会员形象';

CREATE TABLE IF NOT EXISTS `{prefix}digitalavatar_member_voice` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL DEFAULT '0',
  `bid` int(11) NOT NULL DEFAULT '0',
  `member_id` int(11) NOT NULL DEFAULT '0',
  `name` varchar(120) NOT NULL DEFAULT '',
  `preview_url` varchar(500) NOT NULL DEFAULT '',
  `provider_key` varchar(60) NOT NULL DEFAULT '',
  `model_code` varchar(120) NOT NULL DEFAULT '',
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `config_json` longtext,
  `create_time` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_member_status` (`aid`,`bid`,`member_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人会员声音';

CREATE TABLE IF NOT EXISTS `{prefix}digitalavatar_member_credit` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL DEFAULT '0',
  `bid` int(11) NOT NULL DEFAULT '0',
  `member_id` int(11) NOT NULL DEFAULT '0',
  `credit_total` decimal(16,5) NOT NULL DEFAULT '0.00000',
  `credit_used` decimal(16,5) NOT NULL DEFAULT '0.00000',
  `credit_balance` decimal(16,5) NOT NULL DEFAULT '0.00000' COMMENT '未扣除冻结点数的余额',
  `credit_frozen` decimal(16,5) NOT NULL DEFAULT '0.00000',
  `package_id` int(11) NOT NULL DEFAULT '0',
  `expire_time` int(11) NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `last_recharge_time` int(11) NOT NULL DEFAULT '0',
  `last_used_time` int(11) NOT NULL DEFAULT '0',
  `max_concurrent_tasks` int(11) NOT NULL DEFAULT '0' COMMENT '0跟随系统并发，大于0为该会员全任务API并发上限',
  `create_time` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_aid_bid_member` (`aid`,`bid`,`member_id`),
  KEY `idx_aid_status` (`aid`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人会员额度账户';

CREATE TABLE IF NOT EXISTS `{prefix}digitalavatar_credit_log` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL DEFAULT '0',
  `bid` int(11) NOT NULL DEFAULT '0',
  `member_id` int(11) NOT NULL DEFAULT '0',
  `change_type` varchar(30) NOT NULL DEFAULT '',
  `credit_change` decimal(16,5) NOT NULL DEFAULT '0.00000',
  `change_before` decimal(16,5) NOT NULL DEFAULT '0.00000',
  `change_after` decimal(16,5) NOT NULL DEFAULT '0.00000',
  `source_type` varchar(60) NOT NULL DEFAULT '',
  `source_id` int(11) NOT NULL DEFAULT '0',
  `remark` varchar(500) NOT NULL DEFAULT '',
  `extra_json` longtext,
  `operator_id` int(11) NOT NULL DEFAULT '0',
  `create_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_member_time` (`aid`,`bid`,`member_id`,`create_time`),
  KEY `idx_source` (`aid`,`source_type`,`source_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人额度流水';

CREATE TABLE IF NOT EXISTS `{prefix}digitalavatar_package` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL DEFAULT '0',
  `package_type` varchar(20) NOT NULL DEFAULT 'credit' COMMENT 'credit会员套餐 boost加油包',
  `title` varchar(120) NOT NULL DEFAULT '',
  `subtitle` varchar(200) NOT NULL DEFAULT '' COMMENT '副标题',
  `description` varchar(500) NOT NULL DEFAULT '',
  `credit_amount` decimal(16,5) NOT NULL DEFAULT '0.00000',
  `gift_credit` decimal(16,5) NOT NULL DEFAULT '0.00000',
  `original_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `validity_days` int(11) NOT NULL DEFAULT '0',
  `max_concurrent_tasks` int(11) NOT NULL DEFAULT '0' COMMENT '会员套餐任务并发数，0不提高',
  `remark` varchar(500) NOT NULL DEFAULT '',
  `feature_scope_json` longtext,
  `quota_json` longtext COMMENT '加油包指定模型额度',
  `sort` int(11) NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `is_recommend` tinyint(1) NOT NULL DEFAULT '0',
  `create_time` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_aid_status_sort` (`aid`,`status`,`sort`),
  KEY `idx_aid_type_status` (`aid`,`package_type`,`status`,`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人套餐';

CREATE TABLE IF NOT EXISTS `{prefix}digitalavatar_package_order` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL DEFAULT '0',
  `bid` int(11) NOT NULL DEFAULT '0',
  `member_id` int(11) NOT NULL DEFAULT '0',
  `package_id` int(11) NOT NULL DEFAULT '0',
  `ordernum` varchar(64) NOT NULL DEFAULT '',
  `payorder_id` int(11) NOT NULL DEFAULT '0',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `credit_amount` decimal(16,5) NOT NULL DEFAULT '0.00000',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0待支付 1已支付 2已取消',
  `paytype` varchar(60) NOT NULL DEFAULT '',
  `paytypeid` int(11) NOT NULL DEFAULT '0',
  `paynum` varchar(120) NOT NULL DEFAULT '',
  `platform` varchar(30) NOT NULL DEFAULT '',
  `paytime` int(11) NOT NULL DEFAULT '0',
  `paid_time` int(11) NOT NULL DEFAULT '0',
  `grant_status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0未发放 1已发放',
  `create_time` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ordernum` (`ordernum`),
  KEY `idx_aid_member_status` (`aid`,`member_id`,`status`),
  KEY `idx_aid_payorder` (`aid`,`payorder_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人套餐订单';

ALTER TABLE `{prefix}digitalavatar_package_order` ADD COLUMN `paytype` varchar(60) NOT NULL DEFAULT '' AFTER `status`;
ALTER TABLE `{prefix}digitalavatar_package_order` ADD COLUMN `paytypeid` int(11) NOT NULL DEFAULT '0' AFTER `paytype`;
ALTER TABLE `{prefix}digitalavatar_package_order` ADD COLUMN `paynum` varchar(120) NOT NULL DEFAULT '' AFTER `paytypeid`;
ALTER TABLE `{prefix}digitalavatar_package_order` ADD COLUMN `platform` varchar(30) NOT NULL DEFAULT '' AFTER `paynum`;
ALTER TABLE `{prefix}digitalavatar_package_order` ADD COLUMN `paytime` int(11) NOT NULL DEFAULT '0' AFTER `platform`;
ALTER TABLE `{prefix}digitalavatar_package_order` ADD COLUMN `grant_status` tinyint(1) NOT NULL DEFAULT '0' AFTER `paid_time`;

ALTER TABLE `{prefix}digitalavatar_setting` ROW_FORMAT=DYNAMIC;
ALTER TABLE `{prefix}digitalavatar_setting` MODIFY COLUMN `notice_text` text NULL COMMENT '公告';
ALTER TABLE `{prefix}digitalavatar_setting` MODIFY COLUMN `oem_logo` text NULL COMMENT 'OEM Logo';
ALTER TABLE `{prefix}digitalavatar_setting` MODIFY COLUMN `oem_login_bg` text NULL COMMENT 'OEM登录背景';
ALTER TABLE `{prefix}digitalavatar_setting`
  ADD COLUMN `oem_logo` text NULL COMMENT 'OEM Logo' AFTER `notice_text`;
ALTER TABLE `{prefix}digitalavatar_setting`
  ADD COLUMN `oem_login_bg` text NULL COMMENT 'OEM登录背景' AFTER `oem_logo`;
ALTER TABLE `{prefix}digitalavatar_setting`
  ADD COLUMN `oem_color` varchar(20) NOT NULL DEFAULT '' AFTER `oem_login_bg`;
ALTER TABLE `{prefix}digitalavatar_setting`
  ADD COLUMN `oem_subtitle` varchar(200) NOT NULL DEFAULT '' AFTER `oem_color`;
ALTER TABLE `{prefix}digitalavatar_setting`
  ADD COLUMN `oem_contact_wechat` varchar(80) NOT NULL DEFAULT '' AFTER `oem_subtitle`;
ALTER TABLE `{prefix}digitalavatar_setting`
  ADD COLUMN `oem_contact_tel` varchar(30) NOT NULL DEFAULT '' AFTER `oem_contact_wechat`;
ALTER TABLE `{prefix}digitalavatar_setting`
  ADD COLUMN `agent_enabled` tinyint(1) NOT NULL DEFAULT '0' AFTER `oem_contact_tel`;
ALTER TABLE `{prefix}digitalavatar_setting`
  ADD COLUMN `agent_can_grant` tinyint(1) NOT NULL DEFAULT '1' AFTER `agent_enabled`;
ALTER TABLE `{prefix}digitalavatar_setting`
  ADD COLUMN `agent_can_redeem` tinyint(1) NOT NULL DEFAULT '1' AFTER `agent_can_grant`;
ALTER TABLE `{prefix}digitalavatar_setting`
  ADD COLUMN `creative_video_enabled` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否开启创意视频' AFTER `agent_can_redeem`;
ALTER TABLE `{prefix}digitalavatar_setting`
  ADD COLUMN `avatar_enabled` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否开启数字人' AFTER `creative_video_enabled`;
ALTER TABLE `{prefix}digitalavatar_setting`
  ADD COLUMN `audio_enabled` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否开启音频' AFTER `avatar_enabled`;
ALTER TABLE `{prefix}digitalavatar_setting`
  ADD COLUMN `image_enabled` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否开启图片创作' AFTER `audio_enabled`;
ALTER TABLE `{prefix}digitalavatar_setting`
  ADD COLUMN `image_agent_enabled` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否开启Agent作图画板' AFTER `image_enabled`;
ALTER TABLE `{prefix}digitalavatar_setting`
  ADD COLUMN `image_agent_llm_enabled` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否开启Agent作图大模型' AFTER `image_agent_enabled`;
ALTER TABLE `{prefix}digitalavatar_setting`
  ADD COLUMN `image_agent_llm_base_url` varchar(255) NOT NULL DEFAULT '' COMMENT 'Agent作图LLM接口地址' AFTER `image_agent_llm_enabled`;
ALTER TABLE `{prefix}digitalavatar_setting`
  ADD COLUMN `image_agent_llm_api_key` varchar(255) NOT NULL DEFAULT '' COMMENT 'Agent作图LLM密钥' AFTER `image_agent_llm_base_url`;
ALTER TABLE `{prefix}digitalavatar_setting`
  ADD COLUMN `image_agent_llm_model` varchar(100) NOT NULL DEFAULT '' COMMENT 'Agent作图LLM模型' AFTER `image_agent_llm_api_key`;
ALTER TABLE `{prefix}digitalavatar_setting`
  ADD COLUMN `image_agent_llm_timeout` int(11) NOT NULL DEFAULT '45' COMMENT 'Agent作图LLM超时秒' AFTER `image_agent_llm_model`;
ALTER TABLE `{prefix}digitalavatar_setting`
  ADD COLUMN `image_agent_prefer_draft` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Agent作图默认先出草稿' AFTER `image_agent_llm_timeout`;
ALTER TABLE `{prefix}digitalavatar_setting`
  ADD COLUMN `image_agent_draft_model_id` int(11) NOT NULL DEFAULT '0' COMMENT '草稿图片模型ID' AFTER `image_agent_prefer_draft`;
ALTER TABLE `{prefix}digitalavatar_setting`
  ADD COLUMN `image_agent_draft_size` varchar(32) NOT NULL DEFAULT '1024x1024' COMMENT '草稿默认尺寸' AFTER `image_agent_draft_model_id`;
ALTER TABLE `{prefix}digitalavatar_setting`
  ADD COLUMN `image_agent_draft_quality` varchar(20) NOT NULL DEFAULT 'low' COMMENT '草稿默认质量' AFTER `image_agent_draft_size`;
ALTER TABLE `{prefix}digitalavatar_setting`
  ADD COLUMN `image_agent_hd_model_id` int(11) NOT NULL DEFAULT '0' COMMENT '高清图片模型ID' AFTER `image_agent_draft_quality`;
ALTER TABLE `{prefix}digitalavatar_setting`
  ADD COLUMN `image_agent_hd_size` varchar(32) NOT NULL DEFAULT '1024x1024' COMMENT '高清默认尺寸' AFTER `image_agent_hd_model_id`;
ALTER TABLE `{prefix}digitalavatar_setting`
  ADD COLUMN `image_agent_hd_quality` varchar(20) NOT NULL DEFAULT 'high' COMMENT '高清默认质量' AFTER `image_agent_hd_size`;
ALTER TABLE `{prefix}digitalavatar_setting`
  ADD COLUMN `login_style` varchar(20) NOT NULL DEFAULT 'niwa' COMMENT 'classic原登录页 niwa拟娲风格' AFTER `oem_login_bg`;
ALTER TABLE `{prefix}digitalavatar_setting`
  ADD COLUMN `credit_per_yuan` decimal(16,5) NOT NULL DEFAULT '1000.00000' COMMENT '1元对应创作点数' AFTER `initial_credit`;

CREATE TABLE IF NOT EXISTS `{prefix}digitalavatar_redeem_code` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL DEFAULT '0',
  `bid` int(11) NOT NULL DEFAULT '0',
  `package_id` int(11) NOT NULL DEFAULT '0',
  `batch_no` varchar(40) NOT NULL DEFAULT '',
  `code` varchar(80) NOT NULL DEFAULT '',
  `credit_amount` decimal(16,5) NOT NULL DEFAULT '0.00000',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0未使用 1已使用 2作废',
  `used_member_id` int(11) NOT NULL DEFAULT '0',
  `used_time` int(11) NOT NULL DEFAULT '0',
  `expire_time` int(11) NOT NULL DEFAULT '0',
  `remark` varchar(200) NOT NULL DEFAULT '',
  `operator_id` int(11) NOT NULL DEFAULT '0',
  `create_time` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_aid_code` (`aid`,`code`),
  KEY `idx_aid_status` (`aid`,`status`),
  KEY `idx_aid_bid` (`aid`,`bid`),
  KEY `idx_aid_batch` (`aid`,`batch_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人兑换码';

ALTER TABLE `{prefix}digitalavatar_redeem_code`
  ADD COLUMN `bid` int(11) NOT NULL DEFAULT '0' AFTER `aid`;
ALTER TABLE `{prefix}digitalavatar_redeem_code`
  ADD COLUMN `batch_no` varchar(40) NOT NULL DEFAULT '' AFTER `package_id`;
ALTER TABLE `{prefix}digitalavatar_redeem_code`
  ADD COLUMN `expire_time` int(11) NOT NULL DEFAULT '0' AFTER `used_time`;
ALTER TABLE `{prefix}digitalavatar_redeem_code`
  ADD COLUMN `remark` varchar(200) NOT NULL DEFAULT '' AFTER `expire_time`;
ALTER TABLE `{prefix}digitalavatar_redeem_code`
  ADD COLUMN `operator_id` int(11) NOT NULL DEFAULT '0' AFTER `remark`;

CREATE TABLE IF NOT EXISTS `{prefix}digitalavatar_redeem_log` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL DEFAULT '0',
  `code_id` int(11) NOT NULL DEFAULT '0',
  `member_id` int(11) NOT NULL DEFAULT '0',
  `credit_amount` decimal(16,5) NOT NULL DEFAULT '0.00000',
  `create_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code_member` (`code_id`,`member_id`),
  KEY `idx_aid_member` (`aid`,`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人兑换记录';

CREATE TABLE IF NOT EXISTS `{prefix}digitalavatar_task` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL DEFAULT '0',
  `bid` int(11) NOT NULL DEFAULT '0',
  `member_id` int(11) NOT NULL DEFAULT '0',
  `request_id` varchar(80) NOT NULL DEFAULT '',
  `task_type` varchar(60) NOT NULL DEFAULT '',
  `provider_key` varchar(60) NOT NULL DEFAULT '',
  `model_code` varchar(120) NOT NULL DEFAULT '',
  `title` varchar(191) NOT NULL DEFAULT '',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0排队 1运行中 2成功 3失败 4取消 5待重试',
  `progress` int(11) NOT NULL DEFAULT '0',
  `credit_cost` decimal(16,5) NOT NULL DEFAULT '0.00000',
  `credit_status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0无需扣点 1已冻结 2已结算 3已退回',
  `credit_source` varchar(30) NOT NULL DEFAULT '',
  `quota_units` int(11) NOT NULL DEFAULT '0',
  `input_json` longtext,
  `result_json` longtext,
  `error_msg` text,
  `create_time` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  `started_time` int(11) NOT NULL DEFAULT '0',
  `finished_time` int(11) NOT NULL DEFAULT '0',
  `deleted_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_aid_member_request` (`aid`,`member_id`,`request_id`),
  KEY `idx_aid_member_status` (`aid`,`member_id`,`status`,`deleted_time`),
  KEY `idx_aid_provider_model_status` (`aid`,`provider_key`,`model_code`,`status`),
  KEY `idx_aid_created` (`aid`,`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人任务';

CREATE TABLE IF NOT EXISTS `{prefix}digitalavatar_asset` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL DEFAULT '0',
  `bid` int(11) NOT NULL DEFAULT '0',
  `member_id` int(11) NOT NULL DEFAULT '0',
  `task_id` int(11) NOT NULL DEFAULT '0',
  `asset_type` varchar(30) NOT NULL DEFAULT '',
  `name` varchar(191) NOT NULL DEFAULT '',
  `url` varchar(2000) NOT NULL DEFAULT '',
  `mime_type` varchar(120) NOT NULL DEFAULT '',
  `size` bigint(20) unsigned NOT NULL DEFAULT '0',
  `duration` decimal(12,3) NOT NULL DEFAULT '0.000',
  `metadata_json` longtext,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `create_time` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  `delete_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_aid_member_type` (`aid`,`member_id`,`asset_type`,`delete_time`),
  KEY `idx_aid_task` (`aid`,`task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人任务资产';

ALTER TABLE `{prefix}digitalavatar_member_avatar`
  ADD COLUMN `cover_url` varchar(500) NOT NULL DEFAULT '' AFTER `avatar_url`;
ALTER TABLE `{prefix}digitalavatar_member_avatar`
  ADD COLUMN `preview_url` varchar(500) NOT NULL DEFAULT '' AFTER `cover_url`;
ALTER TABLE `{prefix}digitalavatar_asset`
  MODIFY COLUMN `url` varchar(2000) NOT NULL DEFAULT '';
ALTER TABLE `{prefix}digitalavatar_public_avatar`
  ADD COLUMN `visible_member_ids` text COMMENT '可见归属用户ID，逗号分隔，0表示所有人可见' AFTER `config_json`;
UPDATE `{prefix}digitalavatar_public_avatar`
  SET `visible_member_ids` = '0'
  WHERE `visible_member_ids` IS NULL OR `visible_member_ids` = '';
ALTER TABLE `{prefix}digitalavatar_public_voice`
  ADD COLUMN `visible_member_ids` text COMMENT '可见归属用户ID，逗号分隔，0表示所有人可见' AFTER `config_json`;
UPDATE `{prefix}digitalavatar_public_voice`
  SET `visible_member_ids` = '0'
  WHERE `visible_member_ids` IS NULL OR `visible_member_ids` = '';

ALTER TABLE `{prefix}digitalavatar_credit_log`
  ADD COLUMN `extra_json` longtext AFTER `remark`;

CREATE TABLE IF NOT EXISTS `{prefix}digitalavatar_invoice` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL DEFAULT '0',
  `bid` int(11) NOT NULL DEFAULT '0',
  `member_id` int(11) NOT NULL DEFAULT '0',
  `order_id` int(11) NOT NULL DEFAULT '0',
  `ordernum` varchar(64) NOT NULL DEFAULT '',
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1普通发票 2增值税发票',
  `name_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1个人 2企业',
  `invoice_name` varchar(191) NOT NULL DEFAULT '',
  `tax_no` varchar(60) NOT NULL DEFAULT '',
  `address` varchar(255) NOT NULL DEFAULT '',
  `tel` varchar(50) NOT NULL DEFAULT '',
  `bank_name` varchar(120) NOT NULL DEFAULT '',
  `bank_account` varchar(80) NOT NULL DEFAULT '',
  `mobile` varchar(30) NOT NULL DEFAULT '',
  `email` varchar(120) NOT NULL DEFAULT '',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0待审核 1已开票 2已驳回',
  `admin_remark` varchar(500) NOT NULL DEFAULT '',
  `allow_reapply` tinyint(1) NOT NULL DEFAULT '0' COMMENT '驳回后是否允许再次申请',
  `operator_id` int(11) NOT NULL DEFAULT '0',
  `create_time` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_aid_order` (`aid`,`order_id`),
  KEY `idx_aid_member_status` (`aid`,`member_id`,`status`),
  KEY `idx_aid_status_time` (`aid`,`status`,`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人套餐发票申请';

ALTER TABLE `{prefix}digitalavatar_member_credit`
  ADD COLUMN `max_concurrent_tasks` int(11) NOT NULL DEFAULT '0' COMMENT '0跟随系统并发，大于0为该会员全任务API并发上限' AFTER `last_used_time`;

ALTER TABLE `{prefix}digitalavatar_package`
  ADD COLUMN `package_type` varchar(20) NOT NULL DEFAULT 'credit' COMMENT 'credit会员套餐 boost加油包' AFTER `aid`;
ALTER TABLE `{prefix}digitalavatar_package`
  ADD COLUMN `subtitle` varchar(200) NOT NULL DEFAULT '' COMMENT '副标题' AFTER `title`;
ALTER TABLE `{prefix}digitalavatar_package`
  ADD COLUMN `original_price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '原价' AFTER `gift_credit`;
ALTER TABLE `{prefix}digitalavatar_package`
  ADD COLUMN `remark` varchar(500) NOT NULL DEFAULT '' AFTER `validity_days`;
ALTER TABLE `{prefix}digitalavatar_package`
  ADD COLUMN `quota_json` longtext COMMENT '加油包指定模型额度' AFTER `feature_scope_json`;

ALTER TABLE `{prefix}digitalavatar_model_charge_rule`
  ADD COLUMN `match_mode` varchar(20) NOT NULL DEFAULT 'platform_code' COMMENT 'platform_code按产品线 model按接口版本号' AFTER `model_code`;
ALTER TABLE `{prefix}digitalavatar_model_charge_rule`
  ADD COLUMN `platform_code` varchar(60) NOT NULL DEFAULT '' AFTER `match_mode`;
ALTER TABLE `{prefix}digitalavatar_model_charge_rule`
  ADD COLUMN `api_model` varchar(120) NOT NULL DEFAULT '' COMMENT '接口 model 版本号，空表示整条产品线' AFTER `platform_code`;
ALTER TABLE `{prefix}digitalavatar_model_charge_rule`
  ADD COLUMN `api_model_alias` varchar(120) NOT NULL DEFAULT '' COMMENT '用户端显示的 model 别称' AFTER `api_model`;

CREATE TABLE IF NOT EXISTS `{prefix}digitalavatar_credit_lot` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL DEFAULT '0',
  `bid` int(11) NOT NULL DEFAULT '0',
  `member_id` int(11) NOT NULL DEFAULT '0',
  `lot_type` varchar(20) NOT NULL DEFAULT 'credit' COMMENT 'credit算力 quota模型额度',
  `quota_key` varchar(60) NOT NULL DEFAULT '',
  `amount_total` decimal(16,5) NOT NULL DEFAULT '0.00000',
  `amount_remain` decimal(16,5) NOT NULL DEFAULT '0.00000',
  `expire_time` int(11) NOT NULL DEFAULT '0' COMMENT '0长期有效，到期清空剩余',
  `source_type` varchar(60) NOT NULL DEFAULT '',
  `source_id` int(11) NOT NULL DEFAULT '0',
  `package_id` int(11) NOT NULL DEFAULT '0' COMMENT '来源会员套餐',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1有效 0已到期或用完',
  `remark` varchar(255) NOT NULL DEFAULT '',
  `create_time` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_member_type_status` (`aid`,`bid`,`member_id`,`lot_type`,`status`,`expire_time`),
  KEY `idx_quota_key` (`aid`,`member_id`,`quota_key`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人算力/模型额度批次';

CREATE TABLE IF NOT EXISTS `{prefix}digitalavatar_image_skill` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL DEFAULT '0',
  `skill_key` varchar(60) NOT NULL DEFAULT '',
  `name` varchar(80) NOT NULL DEFAULT '',
  `summary` varchar(200) NOT NULL DEFAULT '',
  `content` longtext COMMENT '技能要求和流程，注入 Agent 作图系统提示',
  `sort` int(11) NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `create_time` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_aid_status_sort` (`aid`,`status`,`sort`),
  UNIQUE KEY `uk_aid_skill_key` (`aid`,`skill_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人Agent作图技能';

ALTER TABLE `{prefix}digitalavatar_image_skill`
  ADD UNIQUE KEY `uk_aid_skill_key` (`aid`,`skill_key`);

CREATE TABLE IF NOT EXISTS `{prefix}digitalavatar_conversation` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL DEFAULT '0',
  `bid` int(11) NOT NULL DEFAULT '0',
  `member_id` int(11) NOT NULL DEFAULT '0',
  `title` varchar(120) NOT NULL DEFAULT '',
  `conv_type` varchar(40) NOT NULL DEFAULT '',
  `pinned` tinyint(1) NOT NULL DEFAULT '0',
  `pin_time` int(11) NOT NULL DEFAULT '0',
  `last_time` int(11) NOT NULL DEFAULT '0',
  `agent_session_id` int(11) NOT NULL DEFAULT '0',
  `deleted_time` int(11) NOT NULL DEFAULT '0',
  `create_time` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_member_last` (`aid`,`member_id`,`deleted_time`,`pinned`,`last_time`),
  KEY `idx_agent_session` (`aid`,`agent_session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人对话记录';

ALTER TABLE `{prefix}digitalavatar_task`
  ADD COLUMN `conversation_id` int(11) NOT NULL DEFAULT '0' AFTER `member_id`;
ALTER TABLE `{prefix}digitalavatar_task`
  ADD KEY `idx_conversation` (`aid`,`member_id`,`conversation_id`);
ALTER TABLE `{prefix}digitalavatar_task`
  ADD COLUMN `starred` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1收藏' AFTER `deleted_time`;

CREATE TABLE IF NOT EXISTS `{prefix}digitalavatar_agent_session` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL DEFAULT '0',
  `bid` int(11) NOT NULL DEFAULT '0',
  `member_id` int(11) NOT NULL DEFAULT '0',
  `skill_id` int(11) NOT NULL DEFAULT '0',
  `status` varchar(20) NOT NULL DEFAULT 'active' COMMENT 'active运行中 waiting_user待提问 closed结束',
  `title` varchar(120) NOT NULL DEFAULT '',
  `waiting_json` text COMMENT 'ask_user 待回答',
  `messages_json` longtext COMMENT '给大模型续写的消息',
  `ui_json` longtext COMMENT '用户端会话卡片',
  `last_task_ids` varchar(255) NOT NULL DEFAULT '',
  `create_time` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_aid_member` (`aid`,`member_id`),
  KEY `idx_aid_status` (`aid`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人Agent作图会话';

CREATE TABLE IF NOT EXISTS `{prefix}digitalavatar_checkin_set` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人签到设置';

CREATE TABLE IF NOT EXISTS `{prefix}digitalavatar_checkin_log` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人签到与奖励记录';

CREATE TABLE IF NOT EXISTS `{prefix}digitalavatar_invite_set` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人拉新设置';

CREATE TABLE IF NOT EXISTS `{prefix}digitalavatar_invite_log` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人拉新邀请与奖励记录';

ALTER TABLE `{prefix}digitalavatar_package_order` ADD COLUMN `parent_mid` int(11) NOT NULL DEFAULT '0' COMMENT '分佣上级会员ID' AFTER `grant_status`;
ALTER TABLE `{prefix}digitalavatar_package_order` ADD COLUMN `commission_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '分佣金额' AFTER `parent_mid`;
ALTER TABLE `{prefix}digitalavatar_package_order` ADD COLUMN `commission_rate` decimal(8,2) NOT NULL DEFAULT '0.00' COMMENT '分佣比例' AFTER `commission_amount`;
ALTER TABLE `{prefix}digitalavatar_package_order` ADD COLUMN `commission_level_id` int(11) NOT NULL DEFAULT '0' AFTER `commission_rate`;

ALTER TABLE `{prefix}digitalavatar_member_credit` ADD COLUMN `commission_balance` decimal(12,2) NOT NULL DEFAULT '0.00' AFTER `max_concurrent_tasks`;
ALTER TABLE `{prefix}digitalavatar_member_credit` ADD COLUMN `commission_total` decimal(12,2) NOT NULL DEFAULT '0.00' AFTER `commission_balance`;
ALTER TABLE `{prefix}digitalavatar_member_credit` ADD COLUMN `commission_withdrawn` decimal(12,2) NOT NULL DEFAULT '0.00' AFTER `commission_total`;
ALTER TABLE `{prefix}digitalavatar_member_credit` ADD COLUMN `commission_level_id` int(11) NOT NULL DEFAULT '0' AFTER `commission_withdrawn`;

CREATE TABLE IF NOT EXISTS `{prefix}digitalavatar_commission_level` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL DEFAULT '0',
  `name` varchar(120) NOT NULL DEFAULT '' COMMENT '团队长级别名称',
  `level` int(11) NOT NULL DEFAULT '1' COMMENT '分佣等级',
  `rate` decimal(8,2) NOT NULL DEFAULT '0.00' COMMENT '分佣比例百分比',
  `is_default` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1默认等级，每站仅一个',
  `auto_upgrade` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1满足条件自动升级',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `sort` int(11) NOT NULL DEFAULT '0',
  `remark` varchar(255) NOT NULL DEFAULT '',
  `create_time` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_aid_level` (`aid`,`level`),
  KEY `idx_aid_status` (`aid`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人团队长分佣等级';

CREATE TABLE IF NOT EXISTS `{prefix}digitalavatar_withdraw` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL DEFAULT '0',
  `bid` int(11) NOT NULL DEFAULT '0',
  `member_id` int(11) NOT NULL DEFAULT '0',
  `amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `pay_info` varchar(500) NOT NULL DEFAULT '' COMMENT '收款信息',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0审核中 1通过 2拒绝',
  `audit_remark` varchar(255) NOT NULL DEFAULT '',
  `audit_time` int(11) NOT NULL DEFAULT '0',
  `remark` varchar(255) NOT NULL DEFAULT '',
  `create_time` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_aid_status` (`aid`,`status`,`id`),
  KEY `idx_member` (`aid`,`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人提现申请';

CREATE TABLE IF NOT EXISTS `{prefix}digitalavatar_admin_role` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL DEFAULT '0',
  `name` varchar(120) NOT NULL DEFAULT '',
  `permissions` longtext COMMENT '功能模块权限JSON',
  `remark` varchar(255) NOT NULL DEFAULT '',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `sort` int(11) NOT NULL DEFAULT '0',
  `create_time` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_aid_status` (`aid`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人管理员角色';

CREATE TABLE IF NOT EXISTS `{prefix}digitalavatar_admin_user` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL DEFAULT '0',
  `username` varchar(60) NOT NULL DEFAULT '',
  `password` varchar(255) NOT NULL DEFAULT '',
  `role_id` int(11) NOT NULL DEFAULT '0',
  `remark` varchar(255) NOT NULL DEFAULT '',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1正常 0禁止',
  `last_login_time` int(11) NOT NULL DEFAULT '0',
  `create_time` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_aid_username` (`aid`,`username`),
  KEY `idx_aid_role` (`aid`,`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人管理员';

CREATE TABLE IF NOT EXISTS `{prefix}digitalavatar_agent` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL DEFAULT '0',
  `name` varchar(120) NOT NULL DEFAULT '' COMMENT '代理名称',
  `mobile` varchar(20) NOT NULL DEFAULT '' COMMENT '登陆手机号',
  `password` varchar(255) NOT NULL DEFAULT '',
  `money` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '可用余额',
  `expire_time` int(11) NOT NULL DEFAULT '0' COMMENT '有效期，0长期',
  `remark` varchar(255) NOT NULL DEFAULT '',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1正常 0禁止',
  `create_time` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_aid_mobile` (`aid`,`mobile`),
  KEY `idx_aid_status` (`aid`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人代理账号';

CREATE TABLE IF NOT EXISTS `{prefix}digitalavatar_member_wx` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL DEFAULT '0',
  `member_id` int(11) NOT NULL DEFAULT '0',
  `mpopenid` varchar(64) NOT NULL DEFAULT '',
  `nickname` varchar(120) NOT NULL DEFAULT '',
  `headimg` varchar(500) NOT NULL DEFAULT '',
  `update_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_aid_member` (`aid`,`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人会员微信号';

ALTER TABLE `{prefix}digitalavatar_setting` ROW_FORMAT=DYNAMIC;
ALTER TABLE `{prefix}digitalavatar_setting` ADD COLUMN `sidebar_json` longtext COMMENT '用户端左侧菜单JSON';

ALTER TABLE `{prefix}digitalavatar_setting` MODIFY COLUMN `initial_credit` decimal(16,5) NOT NULL DEFAULT '0.00000';
ALTER TABLE `{prefix}digitalavatar_model_charge_rule` MODIFY COLUMN `unit_credit` decimal(16,5) NOT NULL DEFAULT '0.00000';
ALTER TABLE `{prefix}digitalavatar_model_charge_rule` MODIFY COLUMN `base_credit` decimal(16,5) NOT NULL DEFAULT '0.00000';
ALTER TABLE `{prefix}digitalavatar_member_credit` MODIFY COLUMN `credit_total` decimal(16,5) NOT NULL DEFAULT '0.00000';
ALTER TABLE `{prefix}digitalavatar_member_credit` MODIFY COLUMN `credit_used` decimal(16,5) NOT NULL DEFAULT '0.00000';
ALTER TABLE `{prefix}digitalavatar_member_credit` MODIFY COLUMN `credit_balance` decimal(16,5) NOT NULL DEFAULT '0.00000';
ALTER TABLE `{prefix}digitalavatar_member_credit` MODIFY COLUMN `credit_frozen` decimal(16,5) NOT NULL DEFAULT '0.00000';
ALTER TABLE `{prefix}digitalavatar_credit_log` MODIFY COLUMN `credit_change` decimal(16,5) NOT NULL DEFAULT '0.00000';
ALTER TABLE `{prefix}digitalavatar_credit_log` MODIFY COLUMN `change_before` decimal(16,5) NOT NULL DEFAULT '0.00000';
ALTER TABLE `{prefix}digitalavatar_credit_log` MODIFY COLUMN `change_after` decimal(16,5) NOT NULL DEFAULT '0.00000';
ALTER TABLE `{prefix}digitalavatar_package` MODIFY COLUMN `credit_amount` decimal(16,5) NOT NULL DEFAULT '0.00000';
ALTER TABLE `{prefix}digitalavatar_package` MODIFY COLUMN `gift_credit` decimal(16,5) NOT NULL DEFAULT '0.00000';
ALTER TABLE `{prefix}digitalavatar_package_order` MODIFY COLUMN `credit_amount` decimal(16,5) NOT NULL DEFAULT '0.00000';
ALTER TABLE `{prefix}digitalavatar_redeem_code` MODIFY COLUMN `credit_amount` decimal(16,5) NOT NULL DEFAULT '0.00000';
ALTER TABLE `{prefix}digitalavatar_redeem_log` MODIFY COLUMN `credit_amount` decimal(16,5) NOT NULL DEFAULT '0.00000';
ALTER TABLE `{prefix}digitalavatar_task` MODIFY COLUMN `credit_cost` decimal(16,5) NOT NULL DEFAULT '0.00000';
ALTER TABLE `{prefix}digitalavatar_credit_lot` MODIFY COLUMN `amount_total` decimal(16,5) NOT NULL DEFAULT '0.00000';
ALTER TABLE `{prefix}digitalavatar_credit_lot` MODIFY COLUMN `amount_remain` decimal(16,5) NOT NULL DEFAULT '0.00000';

CREATE TABLE IF NOT EXISTS `{prefix}digitalavatar_inspire_category` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL DEFAULT '0',
  `name` varchar(40) NOT NULL DEFAULT '',
  `badge` varchar(12) NOT NULL DEFAULT '',
  `sort` int(11) NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `create_time` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_aid_sort` (`aid`,`status`,`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人灵感分类';

CREATE TABLE IF NOT EXISTS `{prefix}digitalavatar_inspire` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL DEFAULT '0',
  `bid` int(11) NOT NULL DEFAULT '0',
  `member_id` int(11) NOT NULL DEFAULT '0',
  `category_id` int(11) NOT NULL DEFAULT '0',
  `task_id` int(11) NOT NULL DEFAULT '0',
  `title` varchar(120) NOT NULL DEFAULT '',
  `prompt` text,
  `cover_url` varchar(500) NOT NULL DEFAULT '',
  `media_url` varchar(500) NOT NULL DEFAULT '',
  `media_type` varchar(20) NOT NULL DEFAULT 'image',
  `duration_sec` int(11) NOT NULL DEFAULT '0',
  `user_category` varchar(40) NOT NULL DEFAULT '',
  `like_count` int(11) NOT NULL DEFAULT '0',
  `favorite_count` int(11) NOT NULL DEFAULT '0',
  `remix_count` int(11) NOT NULL DEFAULT '0',
  `recommended` tinyint(1) NOT NULL DEFAULT '0',
  `sort` int(11) NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0待审核 1通过 2拒绝',
  `audit_time` int(11) NOT NULL DEFAULT '0',
  `audit_uid` int(11) NOT NULL DEFAULT '0',
  `audit_remark` varchar(255) NOT NULL DEFAULT '',
  `extra_json` text,
  `deleted_time` int(11) NOT NULL DEFAULT '0',
  `create_time` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_feed` (`aid`,`deleted_time`,`status`,`recommended`,`sort`,`id`),
  KEY `idx_category` (`aid`,`category_id`,`deleted_time`,`status`,`id`),
  KEY `idx_member_task` (`aid`,`member_id`,`task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人灵感';

CREATE TABLE IF NOT EXISTS `{prefix}digitalavatar_inspire_like` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL DEFAULT '0',
  `member_id` int(11) NOT NULL DEFAULT '0',
  `inspire_id` int(11) NOT NULL DEFAULT '0',
  `create_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_like` (`aid`,`member_id`,`inspire_id`),
  KEY `idx_inspire` (`inspire_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人灵感点赞';

CREATE TABLE IF NOT EXISTS `{prefix}digitalavatar_inspire_comment` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL DEFAULT '0',
  `inspire_id` int(11) NOT NULL DEFAULT '0',
  `member_id` int(11) NOT NULL DEFAULT '0',
  `parent_id` int(11) NOT NULL DEFAULT '0',
  `content` varchar(500) NOT NULL DEFAULT '',
  `deleted_time` int(11) NOT NULL DEFAULT '0',
  `create_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_inspire` (`aid`,`inspire_id`,`deleted_time`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人灵感评论';

CREATE TABLE IF NOT EXISTS `{prefix}digitalavatar_inspire_fav` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL DEFAULT '0',
  `member_id` int(11) NOT NULL DEFAULT '0',
  `inspire_id` int(11) NOT NULL DEFAULT '0',
  `create_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_fav` (`aid`,`member_id`,`inspire_id`),
  KEY `idx_inspire` (`inspire_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人灵感收藏';

CREATE TABLE IF NOT EXISTS `{prefix}digitalavatar_inspire_follow` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL DEFAULT '0',
  `member_id` int(11) NOT NULL DEFAULT '0',
  `target_member_id` int(11) NOT NULL DEFAULT '0',
  `create_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_follow` (`aid`,`member_id`,`target_member_id`),
  KEY `idx_target` (`aid`,`target_member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人灵感关注';

