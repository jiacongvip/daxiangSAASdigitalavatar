-- Base package SQL generated from filtered main database schema.
-- Source database: tpdaxiangshop
-- Seed database: tp_delivery_adminphp_core_seed_20260605_0928
-- Exported schema tables: 295
-- Excluded unselected plugin tables: 1353
-- Excluded optional business-module tables: 530
-- Generated at: 2026-10-09T08:01:44.548Z

-- MySQL dump 10.13  Distrib 8.0.45, for Linux (aarch64)
--
-- Host: localhost    Database: tpdaxiangshop
-- ------------------------------------------------------
-- Server version	8.0.45

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `ddwx_access_token`
--

DROP TABLE IF EXISTS `ddwx_access_token`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_access_token` (
  `id` int NOT NULL AUTO_INCREMENT,
  `appid` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `access_token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `expires_time` int DEFAULT NULL,
  `jsapi_ticket` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ticket_expires_time` int DEFAULT NULL,
  `card_ticket` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `card_ticket_expires_time` int DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `appid` (`appid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=96 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_admin`
--

DROP TABLE IF EXISTS `ddwx_admin`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_admin` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '账户ID',
  `owner_root_uid` int NOT NULL DEFAULT '0' COMMENT '归属渠道root uid',
  `creator_uid` int NOT NULL DEFAULT '0' COMMENT '创建人uid',
  `createtime` int DEFAULT NULL COMMENT '创建时间',
  `token` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '十位随机字符串',
  `chouchengset` tinyint(1) DEFAULT '0' COMMENT '是否设置微信支付抽成',
  `chouchengrate` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '抽成比例',
  `chouchengmin` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '抽成最小金额',
  `chouchengmoney` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '抽成金额',
  `linkman` varchar(60) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '',
  `tel` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '联系电话',
  `province` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '省',
  `city` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '市',
  `area` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '区',
  `address` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '地址',
  `platform` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '0' COMMENT '支持的平台 mp公众号 wx小程序',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态 0未开启 1已开启',
  `endtime` int DEFAULT NULL COMMENT '到期时间',
  `mianzhen_num` int NOT NULL DEFAULT '0' COMMENT '面诊次数余额',
  `pid` int DEFAULT '0',
  `remote` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '单独设置的附件存储信息',
  `copyright` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '版权信息',
  `domain` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '单独设置的前端访问域名',
  `remark` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '备注',
  `order_show_onlychildren` tinyint(1) DEFAULT '0',
  `choucheng_receivertype` tinyint(1) DEFAULT '0',
  `choucheng_receivertype1_account` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '',
  `choucheng_receivertype1_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '',
  `choucheng_receivertype2_openidtype` tinyint(1) DEFAULT '0',
  `choucheng_receivertype2_account` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '',
  `choucheng_receivertype2_accountwx` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '',
  `choucheng_receivertype2_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '',
  `face_times` int DEFAULT '0',
  `storage_limit` int NOT NULL DEFAULT '0' COMMENT '存储空间限制(MB，0表示不限制)',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_owner_root_uid` (`owner_root_uid`)
) ENGINE=InnoDB AUTO_INCREMENT=138 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_admin_channel_plugin_scope`
--

DROP TABLE IF EXISTS `ddwx_admin_channel_plugin_scope`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_admin_channel_plugin_scope` (
  `id` int NOT NULL AUTO_INCREMENT,
  `root_uid` int NOT NULL DEFAULT '0',
  `plugin_name` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `createtime` int NOT NULL DEFAULT '0',
  `updatetime` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_root_plugin` (`root_uid`,`plugin_name`),
  KEY `idx_root_uid_status` (`root_uid`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=COMPACT COMMENT='渠道可授权插件范围';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_admin_channel_site_log`
--

DROP TABLE IF EXISTS `ddwx_admin_channel_site_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_admin_channel_site_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `root_uid` int NOT NULL DEFAULT '0',
  `aid` int NOT NULL DEFAULT '0',
  `action` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1开设 2删除',
  `operator_uid` int NOT NULL DEFAULT '0',
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `createtime` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_root_uid` (`root_uid`),
  KEY `idx_aid` (`aid`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=COMPACT COMMENT='渠道平台操作日志';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_admin_dashboard_layout`
--

DROP TABLE IF EXISTS `ddwx_admin_dashboard_layout`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_admin_dashboard_layout` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `aid` int unsigned NOT NULL DEFAULT '0',
  `bid` int NOT NULL DEFAULT '0',
  `admin_uid` int unsigned NOT NULL DEFAULT '0',
  `scene` varchar(64) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'stats',
  `layout_json` longtext COLLATE utf8mb4_general_ci,
  `version` int unsigned NOT NULL DEFAULT '1',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `createtime` int unsigned NOT NULL DEFAULT '0',
  `updatetime` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_admin_scene` (`aid`,`bid`,`admin_uid`,`scene`),
  KEY `idx_aid_bid` (`aid`,`bid`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='后台首页驾驶舱DIY布局';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_admin_export_task`
--

DROP TABLE IF EXISTS `ddwx_admin_export_task`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_admin_export_task` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '任务ID',
  `aid` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `bid` int NOT NULL DEFAULT '0' COMMENT '商家ID',
  `uid` int NOT NULL DEFAULT '0' COMMENT '后台用户ID',
  `task_id` varchar(100) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '异步任务编号',
  `task_type` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '任务类型',
  `task_name` varchar(120) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '任务名称',
  `status` varchar(20) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'queued' COMMENT '状态',
  `processed` int NOT NULL DEFAULT '0' COMMENT '已处理数量',
  `total` int NOT NULL DEFAULT '0' COMMENT '总数量',
  `percent` int NOT NULL DEFAULT '0' COMMENT '进度百分比',
  `message` varchar(500) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '执行说明',
  `params_json` mediumtext COLLATE utf8mb4_general_ci COMMENT '筛选参数JSON',
  `columns_json` mediumtext COLLATE utf8mb4_general_ci COMMENT '导出列JSON',
  `file_path` varchar(500) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '服务器文件路径',
  `file_name` varchar(180) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '下载文件名',
  `file_size` bigint NOT NULL DEFAULT '0' COMMENT '文件大小',
  `error_msg` text COLLATE utf8mb4_general_ci COMMENT '错误信息',
  `expire_time` int NOT NULL DEFAULT '0' COMMENT '过期时间',
  `finish_time` int NOT NULL DEFAULT '0' COMMENT '完成时间',
  `create_time` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_task_id` (`task_id`),
  KEY `idx_aid_time` (`aid`,`create_time`),
  KEY `idx_aid_type_time` (`aid`,`task_type`,`create_time`),
  KEY `idx_status_time` (`status`,`create_time`),
  KEY `idx_expire_time` (`expire_time`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='后台导出任务';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_admin_log`
--

DROP TABLE IF EXISTS `ddwx_admin_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_admin_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int NOT NULL COMMENT '应用ID',
  `admin_id` int NOT NULL COMMENT '管理员ID',
  `admin_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '管理员姓名',
  `action` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '操作动作',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '操作描述',
  `ip` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'IP地址',
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '用户代理',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_aid` (`aid`) USING BTREE,
  KEY `idx_admin` (`admin_id`) USING BTREE,
  KEY `idx_action` (`action`) USING BTREE,
  KEY `idx_created` (`created_at`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='管理员操作日志表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_admin_loginlog`
--

DROP TABLE IF EXISTS `ddwx_admin_loginlog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_admin_loginlog` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `uid` int DEFAULT NULL,
  `logintime` int DEFAULT NULL,
  `loginip` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `logintype` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `uid` (`uid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=10000 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_admin_menu`
--

DROP TABLE IF EXISTS `ddwx_admin_menu`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_admin_menu` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '菜单ID',
  `pid` int DEFAULT '0' COMMENT '父级ID',
  `key` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '菜单标识(唯一)',
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '菜单名称',
  `fullname` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '完整名称',
  `path` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '路由路径',
  `icon` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '图标',
  `auth_data` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '权限标识(auth_mode=0时可空,自动推导)',
  `auth_mode` tinyint(1) DEFAULT '0' COMMENT '权限模式: 0-自动推导 1-手动指定 2-无需权限',
  `level` tinyint(1) DEFAULT '1' COMMENT '层级: 1-一级 2-二级 3-三级',
  `sort` int DEFAULT '999' COMMENT '排序(越小越靠前)',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态: 1-启用 0-禁用',
  `is_hidden` tinyint(1) DEFAULT '0' COMMENT '是否隐藏: 1-隐藏 0-显示',
  `can_promote` tinyint(1) DEFAULT '0' COMMENT '允许提升: 1-是 0-否',
  `admin_only` tinyint(1) DEFAULT '0' COMMENT '仅平台管理员: 1-是 0-否',
  `merchant_visible` tinyint(1) DEFAULT '1' COMMENT '商户可见: 1-是 0-否',
  `tuanzhang_visible` tinyint(1) DEFAULT '1' COMMENT '团长可见: 1-是 0-否',
  `console_visible` tinyint(1) DEFAULT '0' COMMENT '控制台可见: 1-是 0-否',
  `platform_limit` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '平台限制(mp,wx,alipay等,逗号分隔,空=不限)',
  `plugin_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '所属插件(空=系统菜单)',
  `params` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '扩展参数(JSON)',
  `createtime` int DEFAULT NULL COMMENT '创建时间',
  `updatetime` int DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `key` (`key`) USING BTREE,
  KEY `pid` (`pid`) USING BTREE,
  KEY `status` (`status`) USING BTREE,
  KEY `plugin_name` (`plugin_name`) USING BTREE,
  KEY `sort` (`sort`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=859 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='后台菜单配置表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_admin_menu_log`
--

DROP TABLE IF EXISTS `ddwx_admin_menu_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_admin_menu_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `uid` int NOT NULL COMMENT '用户ID',
  `menu_id` int NOT NULL COMMENT '菜单ID',
  `menu_key` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '菜单标识',
  `access_time` int DEFAULT NULL COMMENT '访问时间',
  `ip` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'IP地址',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `uid` (`uid`) USING BTREE,
  KEY `menu_id` (`menu_id`) USING BTREE,
  KEY `access_time` (`access_time`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='菜单访问日志表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_admin_menu_template`
--

DROP TABLE IF EXISTS `ddwx_admin_menu_template`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_admin_menu_template` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '模板名称',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '模板描述',
  `user_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '适用用户类型: admin/merchant/tuanzhang/all',
  `config` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '菜单配置(JSON)',
  `is_default` tinyint(1) DEFAULT '0' COMMENT '是否默认模板',
  `createtime` int DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='菜单模板表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_admin_notice`
--

DROP TABLE IF EXISTS `ddwx_admin_notice`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_admin_notice` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `bid` int DEFAULT '0',
  `uid` int DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `content` longtext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `createtime` int DEFAULT NULL,
  `isread` tinyint(1) DEFAULT '0',
  `readtime` int DEFAULT NULL,
  `type` tinyint(1) DEFAULT '0' COMMENT '0系统发送 1总后台发送 2管理员发送',
  `type2` varchar(150) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `count` int DEFAULT '0',
  `ordernum` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `ti_time` int DEFAULT '0',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `uid` (`uid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=7866 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_admin_notice_push_log`
--

DROP TABLE IF EXISTS `ddwx_admin_notice_push_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_admin_notice_push_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `aid` int unsigned NOT NULL DEFAULT '0',
  `bid` int NOT NULL DEFAULT '0',
  `uid` int unsigned NOT NULL DEFAULT '0',
  `mid` int unsigned NOT NULL DEFAULT '0',
  `notice_id` int unsigned NOT NULL DEFAULT '0',
  `channel` varchar(16) NOT NULL DEFAULT '',
  `scene` varchar(64) NOT NULL DEFAULT '',
  `target` varchar(128) NOT NULL DEFAULT '',
  `target_name` varchar(128) NOT NULL DEFAULT '',
  `title` varchar(191) NOT NULL DEFAULT '',
  `biz_no` varchar(64) NOT NULL DEFAULT '',
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `errmsg` varchar(255) NOT NULL DEFAULT '',
  `createtime` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_aid_channel_time` (`aid`,`channel`,`createtime`),
  KEY `idx_aid_uid` (`aid`,`uid`),
  KEY `idx_aid_biz` (`aid`,`biz_no`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_admin_notice_read`
--

DROP TABLE IF EXISTS `ddwx_admin_notice_read`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_admin_notice_read` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `aid` int unsigned NOT NULL DEFAULT '0',
  `bid` int NOT NULL DEFAULT '0',
  `notice_id` int unsigned NOT NULL DEFAULT '0',
  `admin_uid` int unsigned NOT NULL DEFAULT '0',
  `isread` tinyint(1) NOT NULL DEFAULT '0',
  `readtime` int unsigned NOT NULL DEFAULT '0',
  `remind_count` int unsigned NOT NULL DEFAULT '0',
  `ti_time` int unsigned NOT NULL DEFAULT '0',
  `createtime` int unsigned NOT NULL DEFAULT '0',
  `updatetime` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_notice_admin` (`aid`,`notice_id`,`admin_uid`),
  KEY `idx_admin_status` (`aid`,`admin_uid`,`isread`),
  KEY `idx_notice` (`aid`,`notice_id`)
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_admin_permission_template`
--

DROP TABLE IF EXISTS `ddwx_admin_permission_template`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_admin_permission_template` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '模板名称',
  `controller` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '控制器名',
  `auth_data` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '权限标识',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '说明',
  `is_system` tinyint(1) DEFAULT '1' COMMENT '是否系统模板',
  `createtime` int DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `controller` (`controller`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='权限模板表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_admin_plugin_license`
--

DROP TABLE IF EXISTS `ddwx_admin_plugin_license`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_admin_plugin_license` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `aid` int NOT NULL DEFAULT '0' COMMENT '商户ID',
  `plugin_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '插件名称',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '授权状态 1启用 0禁用',
  `license_type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '授权类型 0永久 1试用 2付费',
  `expire_time` int NOT NULL DEFAULT '0' COMMENT '过期时间（0表示永久）',
  `max_users` int NOT NULL DEFAULT '0' COMMENT '最大用户数（0表示不限制）',
  `features` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '授权功能（JSON格式）',
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '' COMMENT '备注',
  `grantor_uid` int NOT NULL DEFAULT '0' COMMENT '授权人uid',
  `grantor_root_uid` int NOT NULL DEFAULT '0' COMMENT '授权人root uid',
  `grant_source` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0总控 1渠道',
  `createtime` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `updatetime` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_aid_plugin` (`aid`,`plugin_name`) USING BTREE,
  KEY `idx_aid` (`aid`) USING BTREE,
  KEY `idx_plugin_name` (`plugin_name`) USING BTREE,
  KEY `idx_status` (`status`) USING BTREE,
  KEY `idx_grantor_root_uid` (`grantor_root_uid`)
) ENGINE=InnoDB AUTO_INCREMENT=4864 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='插件授权表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_admin_plugin_market`
--

DROP TABLE IF EXISTS `ddwx_admin_plugin_market`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_admin_plugin_market` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `plugin_name` varchar(120) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '插件标识',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否上架：0否 1是',
  `price_text` varchar(120) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '价格文案',
  `price_desc` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '价格说明',
  `contact_text` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '联系说明',
  `detail_html` mediumtext COLLATE utf8mb4_general_ci COMMENT '详情富文本',
  `sort` int NOT NULL DEFAULT '0' COMMENT '排序',
  `is_recommend` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否推荐',
  `createtime` int NOT NULL DEFAULT '0',
  `updatetime` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_plugin_name` (`plugin_name`),
  KEY `idx_status_sort` (`status`,`sort`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='插件市场配置表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_admin_plugin_market_request`
--

DROP TABLE IF EXISTS `ddwx_admin_plugin_market_request`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_admin_plugin_market_request` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `aid` int unsigned NOT NULL DEFAULT '0' COMMENT '站点ID',
  `owner_root_uid` int unsigned NOT NULL DEFAULT '0' COMMENT '所属渠道root uid',
  `plugin_name` varchar(120) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '插件标识',
  `site_name` varchar(120) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '站点名称',
  `contact_name` varchar(80) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '联系人',
  `contact_mobile` varchar(40) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '联系电话',
  `message` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '申请备注',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '状态：0待处理 1已开通 2已拒绝',
  `review_uid` int unsigned NOT NULL DEFAULT '0' COMMENT '处理人UID',
  `review_remark` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '处理备注',
  `license_id` int unsigned NOT NULL DEFAULT '0' COMMENT '关联授权ID',
  `createtime` int NOT NULL DEFAULT '0',
  `updatetime` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_aid_plugin` (`aid`,`plugin_name`),
  KEY `idx_status_ctime` (`status`,`createtime`),
  KEY `idx_owner_root_uid` (`owner_root_uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='插件市场开通申请表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_admin_plugin_menu`
--

DROP TABLE IF EXISTS `ddwx_admin_plugin_menu`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_admin_plugin_menu` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `aid` int NOT NULL DEFAULT '0' COMMENT '商户ID',
  `pid` int NOT NULL DEFAULT '0' COMMENT '父级ID',
  `plugin_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '插件名称',
  `key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '菜单标识',
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '菜单名称',
  `path` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '菜单路径',
  `vue_path` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT 'Vue路由路径',
  `icon` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '图标',
  `sort` int NOT NULL DEFAULT '0' COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态 1启用 0禁用',
  `use_vue` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否使用Vue3页面：0=旧版HTML 1=Vue3',
  `auth_mode` tinyint(1) NOT NULL DEFAULT '0' COMMENT '权限模式 0自动 1手动 2无需权限',
  `auth_data` varchar(500) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '权限标识',
  `admin_only` tinyint(1) NOT NULL DEFAULT '0' COMMENT '仅管理员可见',
  `merchant_visible` tinyint(1) NOT NULL DEFAULT '1' COMMENT '商户可见',
  `tuanzhang_visible` tinyint(1) NOT NULL DEFAULT '0' COMMENT '团长可见',
  `createtime` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `plugin_name` (`plugin_name`) USING BTREE,
  KEY `status` (`status`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=3779593 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='插件菜单表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_admin_plugin_top_menu`
--

DROP TABLE IF EXISTS `ddwx_admin_plugin_top_menu`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_admin_plugin_top_menu` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `aid` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `plugin_name` varchar(64) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT '' COMMENT '插件标识',
  `custom_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '自定义一级菜单名称',
  `custom_icon` varchar(100) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '自定义一级菜单图标',
  `sort` int NOT NULL DEFAULT '0' COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否提升为一级菜单',
  `promote_items` text COLLATE utf8mb4_general_ci COMMENT '提到主菜单的菜单项',
  `promote_icons` text COLLATE utf8mb4_general_ci COMMENT '提到主菜单的菜单项图标',
  `createtime` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `updatetime` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_aid_plugin` (`aid`,`plugin_name`),
  KEY `idx_aid_status` (`aid`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=86 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=COMPACT COMMENT='站点插件一级菜单配置表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_admin_plugin_version`
--

DROP TABLE IF EXISTS `ddwx_admin_plugin_version`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_admin_plugin_version` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `plugin_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '插件名称',
  `version` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '1.0.0' COMMENT '插件版本',
  `use_vue` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否使用Vue页面 0:老页面(iframe) 1:Vue页面',
  `createtime` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `updatetime` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `plugin_name` (`plugin_name`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=198 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='插件版本表（总控台全局）';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_admin_plugin_version_bak_20260822_hualuojinjie`
--

DROP TABLE IF EXISTS `ddwx_admin_plugin_version_bak_20260822_hualuojinjie`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_admin_plugin_version_bak_20260822_hualuojinjie` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `plugin_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '插件名称',
  `version` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '1.0.0' COMMENT '插件版本',
  `use_vue` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否使用Vue页面 0:老页面(iframe) 1:Vue页面',
  `createtime` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `updatetime` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `plugin_name` (`plugin_name`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=175 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='插件版本表（总控台全局）';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_admin_set`
--

DROP TABLE IF EXISTS `ddwx_admin_set`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_admin_set` (
  `id` int NOT NULL AUTO_INCREMENT,
  `yongzhuan` decimal(11,2) NOT NULL,
  `jifenchi_mode` tinyint(1) NOT NULL DEFAULT '0' COMMENT '积分池模式：0关闭，1开启',
  `jifenchi_jingtai` decimal(10,2) NOT NULL COMMENT '静态积分池比例',
  `jifenchi_dongtai` decimal(10,2) NOT NULL COMMENT '动态积分池比例',
  `aid` int DEFAULT NULL,
  `lvjifenjiazhi` decimal(10,2) DEFAULT NULL,
  `xiaofeizhijiazhi` decimal(10,2) DEFAULT NULL,
  `xiaofeizhizhuan` decimal(10,2) DEFAULT NULL,
  `pingtaizhanghao` int DEFAULT '0',
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `logo` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `mode` int DEFAULT '0',
  `outernet_url` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '外网模式链接地址',
  `custom_index_enabled` tinyint(1) NOT NULL DEFAULT '0' COMMENT '自定义首页开关 0关闭 1开启',
  `custom_index_url` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '自定义首页路径',
  `desc` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `maidian` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '商城卖点(一句话简介,纯文本)',
  `tel` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `province` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `city` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `district` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `address` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `longitude` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '经度',
  `latitude` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '纬度',
  `color1` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '#FD4A46',
  `color2` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '#7E71F6',
  `moneypay` tinyint(1) DEFAULT '1' COMMENT '余额支付',
  `recharge` tinyint(1) DEFAULT '1' COMMENT '是否开启充值',
  `money_transfer` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '转账',
  `withdraw_weixin` tinyint(1) DEFAULT '1',
  `withdraw_aliaccount` tinyint(1) DEFAULT '1',
  `withdraw_bankcard` tinyint(1) DEFAULT '1',
  `withdraw_cpcn_balance` tinyint(1) NOT NULL DEFAULT '0' COMMENT '余额提现启用中金提现',
  `withdraw_cpcn_commission` tinyint(1) NOT NULL DEFAULT '0' COMMENT '佣金提现启用中金提现',
  `cpcn_withdraw_mode` tinyint(1) NOT NULL DEFAULT '1' COMMENT '中金提现处理模式:0手动1审核2自动',
  `withdraw` tinyint(1) DEFAULT NULL,
  `withdraw_autotransfer` tinyint(1) DEFAULT '0',
  `withdrawmin` decimal(11,2) DEFAULT '1.00',
  `withdraw_multiple` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '余额提现金额倍数，0不限制',
  `withbeishu` decimal(11,2) NOT NULL,
  `withdrawfee` decimal(11,2) DEFAULT NULL,
  `withdraw_fixed_fee` decimal(10,2) DEFAULT '0.00' COMMENT '余额提现每笔扣费（元）',
  `withdraw_fee_reward_enabled` tinyint(1) NOT NULL DEFAULT '0' COMMENT '余额提现手续费奖励开关：0关闭 1开启',
  `withdraw_fee_reward_asset` varchar(32) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '余额提现手续费奖励到账资产',
  `withdraw_fee_reward_percent` decimal(10,4) NOT NULL DEFAULT '0.0000' COMMENT '余额提现手续费奖励比例',
  `comwithdraw` tinyint(1) DEFAULT '1',
  `comwithdraw_need_paycode` tinyint(1) NOT NULL DEFAULT '0' COMMENT '佣金提现是否需要上传收款码：0否 1是',
  `comwithdrawmin` decimal(11,2) DEFAULT '1.00',
  `comwithdraw_multiple` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '佣金提现金额倍数，0不限制',
  `comwithdrawfee` decimal(11,2) DEFAULT NULL,
  `comwithdraw_fixed_fee` decimal(10,2) DEFAULT '0.00' COMMENT '佣金提现每笔扣费（元）',
  `comwithdrawdate` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '0',
  `comwithdrawbl` decimal(11,2) DEFAULT '100.00',
  `commission2scorepercent` decimal(11,2) DEFAULT NULL,
  `commission2moneypercent1` decimal(11,2) DEFAULT NULL,
  `commission2moneypercent2` decimal(11,2) DEFAULT NULL,
  `banner_levelup` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `banner_usercenter` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `commission2money` tinyint(1) DEFAULT '0',
  `commission2money_multiple` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '佣金转余额金额倍数，0不限制',
  `commission2money_money` decimal(11,2) DEFAULT '100.00' COMMENT '佣金转余额比例',
  `commission2money_score` decimal(11,2) DEFAULT NULL COMMENT '佣金转积分比例',
  `commission2money_hei` decimal(5,2) NOT NULL COMMENT '佣金转现金券百分比',
  `commission2money_fee` decimal(11,2) DEFAULT NULL COMMENT '佣金转换手续费比例',
  `commission_autowithdraw` tinyint(1) DEFAULT '0',
  `subscribeurl` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `score2money` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '0.01',
  `score2money_grow_on` tinyint(1) NOT NULL DEFAULT '0' COMMENT '积分抵扣动态涨幅开关 0关闭 1开启',
  `score2money_grow_mode` tinyint(1) NOT NULL DEFAULT '0' COMMENT '涨幅方式 0固定增加 1按比例增加',
  `score2money_grow_value` decimal(10,4) NOT NULL DEFAULT '0.0000' COMMENT '涨幅值：固定=每次执行增加(元/积分)，比例=每次执行增加百分比(%)',
  `score2money_grow_last_ym` int NOT NULL DEFAULT '0' COMMENT '上次执行月份YYYYMM（记录用）',
  `scorein_money` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '消费每满多少元赠送积分',
  `scorein_score` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '消费每满多少元赠送多少积分',
  `scorecz_money` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '充值每满多少元赠送积分',
  `scorecz_score` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '充值每满多少元赠送多少积分',
  `scorebdkyf` tinyint(1) DEFAULT '0',
  `score_transfer` tinyint unsigned NOT NULL DEFAULT '0',
  `score_withdraw` tinyint unsigned NOT NULL DEFAULT '0',
  `score_withdraw_percent_day` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT '每日转允提积分比例',
  `score_to_money_percent` decimal(10,4) NOT NULL DEFAULT '0.0000' COMMENT '积分转余额换算比例',
  `kfurl` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `wxkf` tinyint(1) DEFAULT '0',
  `wxkfurl` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `corpid` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `wxkftransfer` tinyint(1) DEFAULT '0',
  `gzts` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `ddbb` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `ddbbtourl` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `scoredkmaxpercent` decimal(11,2) DEFAULT '100.00',
  `miandanst` tinyint(1) DEFAULT '0',
  `commissiontype` tinyint(1) DEFAULT '0',
  `fxjiesuantype` tinyint(1) DEFAULT '0',
  `fhjiesuantime_type` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '分红结算时间类型 0收货后，1付款后',
  `fxjiesuantime` tinyint(1) DEFAULT '0',
  `fxjiesuantime_delaydays` float(10,2) DEFAULT '0.00',
  `fhjiesuantype` tinyint(1) DEFAULT '0',
  `fhjiesuantime` tinyint(1) DEFAULT '0',
  `fhjiesuanhb` tinyint(1) DEFAULT '0',
  `fhjiesuanbusiness` tinyint(1) DEFAULT '1',
  `partner_jiaquan` tinyint(1) DEFAULT '0',
  `partner_gongxian` tinyint(1) DEFAULT '0',
  `areafenhong_jiaquan` tinyint(1) DEFAULT '0',
  `areafenhong_checktype` tinyint(1) DEFAULT '0',
  `teamyeji_show` tinyint(1) DEFAULT '0',
  `teamnum_show` tinyint(1) DEFAULT '0',
  `gongxianfenhong_show` tinyint(1) DEFAULT '0',
  `gongxianfenhong_txt` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `maidanfenxiao` tinyint(1) DEFAULT '0',
  `logintype_mp` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '1,2,3',
  `logintype_wx` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '1,2,3',
  `logintype_alipay` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '1,2,3',
  `logintype_baidu` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '1,2,3',
  `logintype_toutiao` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '1,2,3',
  `logintype_qq` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '1,2,3',
  `logintype_h5` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '1,2',
  `logintype_app` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '1,2,3',
  `google_client_id` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `login_bind` tinyint unsigned NOT NULL DEFAULT '0',
  `wechat_openid_auto_bind_platforms` varchar(32) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'mp,wx' COMMENT '微信OpenID自动绑定平台：mp公众号 wx小程序',
  `login_setnickname` tinyint unsigned NOT NULL DEFAULT '0',
  `login_mast` varchar(2550) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '强制登录',
  `member_register_enabled` tinyint(1) NOT NULL DEFAULT '1' COMMENT '会员开放注册：0关闭 1开启',
  `member_account_cancel_enabled` tinyint(1) NOT NULL DEFAULT '1' COMMENT '会员注销账号入口：0关闭 1开启',
  `login_popup_status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '前端当前页弹窗登录开关 0关闭 1开启',
  `login_popup_platforms` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '前端当前页弹窗登录适用端，逗号分隔',
  `reg_invite_code` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '0关闭，1开启',
  `reg_invite_code_type` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '0手机号',
  `textset` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '文本自定义配置',
  `gettj` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '-1' COMMENT '进入条件',
  `gettjtip` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '您没有权限进入',
  `pay_transfer` tinyint unsigned DEFAULT '0' COMMENT '转账汇款',
  `pay_transfer_account_name` varchar(200) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '',
  `pay_transfer_account` varchar(200) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '',
  `pay_transfer_bank` varchar(200) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '',
  `pay_transfer_desc` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '',
  `invoice` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '发票 0关 1开',
  `invoice_type` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '1' COMMENT '发票类型 1普通 2专票',
  `invoice_rate` decimal(5,2) DEFAULT NULL,
  `teamfenhong_differential` tinyint(1) DEFAULT '0',
  `teamfenhong_differential_pj` tinyint(1) DEFAULT '0',
  `areafenhong_differential` tinyint(1) DEFAULT '0',
  `reg_check` tinyint(1) DEFAULT '0' COMMENT '注册审核',
  `locking_pwd` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '',
  `appurl` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '',
  `huidong_status` tinyint unsigned DEFAULT '0',
  `huidong_url` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `parent_show` tinyint unsigned DEFAULT '1' COMMENT '是否显示推荐人：1显示，0隐藏',
  `my_commission_show` tinyint(1) NOT NULL DEFAULT '1' COMMENT '分销中心是否显示我的佣金卡片：0否 1是',
  `pay_transfer_check` tinyint(1) NOT NULL DEFAULT '0' COMMENT '转账审核 0：关闭 1：开启',
  `teamfenhong_show` tinyint unsigned DEFAULT '1' COMMENT '团队分红：1显示，0隐藏',
  `commissionlog_show` tinyint unsigned DEFAULT '1' COMMENT '佣金明细：1显示，0隐藏',
  `commissionrecord_show` tinyint unsigned DEFAULT '1' COMMENT '佣金记录：1显示，0隐藏',
  `downorder_show` tinyint(1) NOT NULL DEFAULT '1' COMMENT '分销中心是否显示分销订单 1=显示 0=不显示',
  `fhorder_show` tinyint unsigned DEFAULT '1' COMMENT '分红订单：1显示，0隐藏',
  `fhlog_show` tinyint unsigned DEFAULT '1' COMMENT '分红记录：1显示，0隐藏',
  `tansrate` float NOT NULL DEFAULT '0',
  `money_transfer_multiple` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '余额互转金额倍数，0不限制',
  `idcard_check` tinyint(1) DEFAULT NULL,
  `heiscpay` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1开启0关闭',
  `heishouxu` decimal(11,2) NOT NULL,
  `score2moneyhei` decimal(10,4) NOT NULL DEFAULT '0.0000' COMMENT '每现金券抵扣多少元',
  `scoredkmaxpercenthei` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT '现金券最多抵扣百分比',
  `scorebdkyfhei` decimal(11,2) NOT NULL,
  `cyz_qu` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `heipay` tinyint(1) NOT NULL DEFAULT '0',
  `xiaofeibpay` tinyint(1) NOT NULL DEFAULT '0' COMMENT '消费币支付总开关：0关闭 1开启',
  `xiaofeib_transfer` tinyint(1) NOT NULL DEFAULT '0' COMMENT '消费币转账开关：0关闭 1开启',
  `xiaofeib_tansrate` decimal(10,4) NOT NULL DEFAULT '0.0000' COMMENT '消费币转账手续费比例',
  `xiaofeib_transfer_need_paypwd` tinyint(1) NOT NULL DEFAULT '0' COMMENT '消费币转账需支付密码：0否 1是',
  `xiaofeib_transfer_target_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '消费币转账识别方式：1手机号 2用户ID',
  `wxpay_enabled` tinyint(1) NOT NULL DEFAULT '1' COMMENT '微信支付总开关：1=开启，0=关闭',
  `alipay_enabled` tinyint(1) NOT NULL DEFAULT '1' COMMENT '支付宝支付总开关：0关闭 1开启',
  `score2moneyyu` decimal(10,4) NOT NULL DEFAULT '0.0000' COMMENT '每余额抵扣多少元',
  `scoredkmaxpercentyu` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT '余额最多抵扣百分比',
  `scorebdkyfyu` tinyint(1) NOT NULL DEFAULT '0',
  `hei_tansrate` decimal(11,2) NOT NULL,
  `cyz_shi` varchar(1) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `score2moneyhuang` decimal(10,2) DEFAULT NULL COMMENT '黄积分 每黄积分抵扣',
  `scoredkmaxpercenthuang` decimal(10,2) DEFAULT NULL COMMENT '黄积分  最多抵扣百分比',
  `scorebdkyfhuang` decimal(10,2) DEFAULT NULL COMMENT '黄积分  不抵扣运费',
  `huang_tansrate` decimal(10,2) DEFAULT NULL COMMENT '黄积分转账手续费',
  `gxz_tansrate` decimal(10,2) DEFAULT NULL COMMENT '贡献值转账手续费',
  `hei_onemoney_tansrate` decimal(11,2) NOT NULL,
  `hei_onemoney` decimal(11,2) NOT NULL,
  `gongpaizhi_shi` tinyint(1) NOT NULL DEFAULT '0',
  `gongpaizhi_ren` int NOT NULL DEFAULT '0',
  `gongpaizhi_xiaofei` decimal(10,2) NOT NULL,
  `gongpaizhi_jiangli` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `xsyj_shi` tinyint(1) DEFAULT '0',
  `xsyj_yeji` decimal(11,2) NOT NULL,
  `xsyj_fhd` int NOT NULL DEFAULT '0',
  `gongpaizhi_ren_lun` int NOT NULL DEFAULT '0',
  `yihuo_status` tinyint(1) NOT NULL DEFAULT '0',
  `yihuo_baifenbi` decimal(10,2) NOT NULL,
  `yihuo_jiesuan_status` tinyint(1) DEFAULT '0',
  `yihuo_sxf` decimal(10,2) DEFAULT NULL,
  `yihuo_deduct_gongxianzhi` tinyint(1) NOT NULL DEFAULT '0' COMMENT '转余额是否扣除贡献值 0=不扣除 1=扣除',
  `yihuo_gongxianzhi_rate` decimal(5,2) NOT NULL DEFAULT '100.00' COMMENT '贡献值扣除比例 单位:%',
  `tuozhanyuan_apply_enabled` tinyint NOT NULL DEFAULT '0' COMMENT '拓展员申请开关：0-关闭 1-开启',
  `tuozhanyuan_apply_audit` tinyint NOT NULL DEFAULT '1' COMMENT '拓展员申请审核：0-自动通过 1-需要审核',
  `tuozhanyuan_xieyi_show` tinyint NOT NULL DEFAULT '0' COMMENT '是否显示拓展员协议：0-不显示 1-显示',
  `tuozhanyuan_xieyi` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '拓展员入驻协议内容',
  `tuozhanyuan_verify_success` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '恭喜您审核通过！您已成为拓展员' COMMENT '审核通过提示',
  `tuozhanyuan_verify_reject` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '审核未通过：' COMMENT '审核拒绝提示',
  `tuozhanyuan_verify_notice` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '等待审核提示',
  `tuozhanyuan_verify_normal` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '温馨提示：成为拓展员后可享受推广分佣权益' COMMENT '温馨提示',
  `withdraw_zd_max` decimal(10,2) DEFAULT NULL,
  `withdraw_wx_max` decimal(10,2) DEFAULT NULL,
  `withdraw_zfb_max` decimal(11,2) DEFAULT NULL,
  `withdraw_yhk_max` decimal(10,2) DEFAULT NULL,
  `area_on` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '是否开启多城市拓展',
  `area_set` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL COMMENT '保存多城市配置数据',
  `default_template` int unsigned NOT NULL DEFAULT '0' COMMENT '没有设置区域模板，0：系统默认，非0数据为用户自定义的模板ID',
  `areamode` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '分区模式，0省份，1城市，2区县',
  `province_id` int unsigned NOT NULL DEFAULT '0',
  `city_id` int unsigned NOT NULL DEFAULT '0',
  `district_id` int unsigned NOT NULL DEFAULT '0',
  `gongpaizhi_lunshu` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `gongpaizhi_jiangli_lunshu` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `lunshu` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `jifenzhuanzhangbeishu` int DEFAULT NULL,
  `qiang_video` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `qiang_status` tinyint(1) NOT NULL DEFAULT '0',
  `jiange_ci` int NOT NULL,
  `jiange_min` int NOT NULL DEFAULT '0',
  `qiangti_video` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tuozhancrm_status` tinyint NOT NULL DEFAULT '0',
  `idcardbanghao` tinyint NOT NULL DEFAULT '0',
  `comwithdrawkouxianquan` decimal(10,2) DEFAULT NULL,
  `commission_withdraw_reward` tinyint(1) NOT NULL DEFAULT '0' COMMENT '佣金提现奖励返还开关：0关闭，1开启',
  `commission_withdraw_money` decimal(5,2) NOT NULL DEFAULT '100.00' COMMENT '佣金提现分配到余额的比例(%)',
  `commission_withdraw_score` decimal(5,2) NOT NULL COMMENT '佣金提现到积分百分比',
  `commission_withdraw_hei` decimal(5,2) NOT NULL COMMENT '佣金提现到现金券百分比',
  `commission_withdraw_fee` decimal(5,2) NOT NULL COMMENT '佣金提现手续费百分比',
  `commission_withdraw_deduct_gongxianzhi` tinyint(1) NOT NULL DEFAULT '0' COMMENT '佣金提现是否扣除贡献值：0关闭，1开启',
  `commission_withdraw_gongxianzhi_rate` decimal(5,2) NOT NULL DEFAULT '100.00' COMMENT '佣金提现贡献值扣除比例',
  `commission_ratio` decimal(10,2) DEFAULT NULL,
  `map_type` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT 'gaode',
  `gaode_key` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `gaode_sk` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '高德地图安全密钥',
  `baidu_key` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `qq_key` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tianditu_key` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '天地图key',
  `scorehuangwithdraw` int NOT NULL DEFAULT '0',
  `scorehuangwithdrawmin` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `scorehuangwithdrawfee` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `scorehuangwithdrawkouxianquan` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `shouji_score_percent` decimal(5,2) DEFAULT NULL COMMENT '收集收益转积分百分比',
  `shouji_money_percent` decimal(5,2) DEFAULT NULL COMMENT '收集收益转余额百分比',
  `shouji_commission_percent` decimal(5,2) DEFAULT '100.00' COMMENT '收集收益转佣金百分比',
  `yongzhuan_jifen` decimal(5,2) DEFAULT '0.33' COMMENT '佣金转入积分比例',
  `yongzhuan_yue` decimal(5,2) DEFAULT '0.33' COMMENT '佣金转入余额比例',
  `yongzhuan_xianjinquan` decimal(5,2) DEFAULT '0.34' COMMENT '佣金转入现金券比例',
  `yongzhuan_shouxufei` decimal(5,2) NOT NULL COMMENT '佣金转换手续费比例（0-1，如0.02表示2%）',
  `xuni_team_shi` tinyint(1) NOT NULL DEFAULT '0' COMMENT '虚拟账号团队开关:0关闭,1开启',
  `xuni_team_num` int NOT NULL DEFAULT '0' COMMENT '虚拟账号团队人数要求',
  `yihuo_jiesuan_type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '易货结算方式：0-按商品价格结算，1-按成交价格结算',
  `zt_sy_calc_mode` tinyint(1) NOT NULL DEFAULT '0' COMMENT '直推奖励计算方式：0=按现有佣金基数 1=按商家让利基数',
  `wx_transfer_type` int DEFAULT '0',
  `distribution_abc_yeji_show` tinyint(1) DEFAULT '0' COMMENT '是否显示分销ABC业绩，0不显示，1显示',
  `team_list_yeji_show` tinyint(1) DEFAULT '0' COMMENT '团队列表是否显示团队业绩，0不显示，1显示',
  `team_list_relation_show` tinyint(1) DEFAULT '0' COMMENT '团队列表是否可查看下级关系图，0不显示，1显示',
  `team_list_commission_show` tinyint(1) DEFAULT '0' COMMENT '团队列表是否显示他人佣金，0不显示，1显示',
  `team_list_level_show` tinyint(1) NOT NULL DEFAULT '1' COMMENT '团队列表是否显示等级：0否 1是',
  `team_list_subnum_show` tinyint(1) DEFAULT '0' COMMENT '团队列表是否显示下级人数，0不显示，1显示',
  `team_list_member_count_show` tinyint(1) DEFAULT '0' COMMENT '团队列表是否显示成员数量，0不显示，1显示',
  `my_order_performance_show` tinyint(1) NOT NULL DEFAULT '1' COMMENT '我的单数业绩显示控制',
  `my_earnings_show` tinyint(1) NOT NULL DEFAULT '1' COMMENT '我的收益显示控制',
  `ip_capture` tinyint(1) DEFAULT '0' COMMENT 'IP地址获取开关',
  `ip_capture_mode` tinyint(1) DEFAULT '1' COMMENT 'IP获取方式：1仅首次登录，2每次登录，3间隔登录',
  `ip_capture_interval` int DEFAULT '24' COMMENT 'IP获取间隔时间（小时）',
  `ip_address_confirm` tinyint(1) DEFAULT '0' COMMENT '是否需要确认地址',
  `ip_address_edit` tinyint(1) DEFAULT '0' COMMENT '是否支持修改地址',
  `ip_address_edit_times` int DEFAULT '0' COMMENT '允许修改地址次数',
  `money_transfer_need_paypwd` tinyint(1) NOT NULL DEFAULT '0' COMMENT '余额转账是否需要支付密码',
  `money_transfer_target_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '余额互转识别方式：1=按手机号 2=按用户ID',
  `score_transfer_need_paypwd` tinyint(1) NOT NULL DEFAULT '0' COMMENT '积分转账是否需要支付密码',
  `xianjinquan_transfer_need_paypwd` tinyint(1) NOT NULL DEFAULT '0' COMMENT '现金券转账是否需要支付密码',
  `xianjinquan_transfer_target_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '现金券转账识别方式：1手机号 2用户ID',
  `xianjinquan_to_money_need_paypwd` tinyint(1) NOT NULL DEFAULT '0' COMMENT '现金券转余额是否需要支付密码',
  `hongbao_transfer_need_paypwd` tinyint(1) NOT NULL DEFAULT '0' COMMENT '红包转账是否需要支付密码',
  `gongxianzhi_transfer_need_paypwd` tinyint(1) NOT NULL DEFAULT '0' COMMENT '贡献值转账是否需要支付密码',
  `scorehuang_enabled` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否启用黄积分功能',
  `scorehuang_withdraw_enabled` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否启用黄积分提现功能',
  `scorehuang_withdraw_min` decimal(10,2) NOT NULL COMMENT '黄积分最低提现金额',
  `scorehuang_withdraw_fee` decimal(5,2) NOT NULL COMMENT '黄积分提现手续费百分比',
  `scorehuang_withdraw_date` int NOT NULL DEFAULT '1' COMMENT '黄积分提现处理时间(天)',
  `scorehuang_withdraw_weixin` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否支持微信提现',
  `scorehuang_withdraw_aliaccount` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否支持支付宝提现',
  `scorehuang_withdraw_bankcard` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否支持银行卡提现',
  `score_to_commission_status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '积分转佣金功能开关 0关闭 1开启',
  `score_to_commission_rate` int NOT NULL DEFAULT '100' COMMENT '兑换比例 多少积分换1佣金',
  `score_to_commission_beishu` int NOT NULL DEFAULT '100' COMMENT '转换倍数限制',
  `score_to_commission_min` int NOT NULL DEFAULT '100' COMMENT '最小转换积分数量',
  `score_to_commission_max` int NOT NULL DEFAULT '10000' COMMENT '最大转换积分数量 0表示不限制',
  `score_to_commission_need_paypwd` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否需要支付密码 0不需要 1需要',
  `score_to_commission_fee_rate` decimal(5,4) NOT NULL COMMENT '转换手续费比例 0-1',
  `maidan_login` tinyint(1) NOT NULL DEFAULT '1' COMMENT '买单收款页是否强制登录 1是 0否',
  `maidan_getlocation` tinyint(1) NOT NULL DEFAULT '0' COMMENT '买单开启定位 0关闭 1开启',
  `maidan_payaftertourl` varchar(200) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `maidan_balance_pay` tinyint(1) NOT NULL DEFAULT '0' COMMENT '买单余额支付开关 0-关闭 1-开启',
  `maidan_score_pay` tinyint(1) NOT NULL DEFAULT '1' COMMENT '买单积分抵扣开关 0-关闭 1-开启',
  `maidan_commission_calc_mode` tinyint(1) NOT NULL DEFAULT '0' COMMENT '买单分销计算方式：0=按买单支付金额 1=按商家让利基数',
  `maidan_commission_score_switch` tinyint(1) DEFAULT '0' COMMENT '买单提成积分开关：0关闭，1开启',
  `business_reward_enabled` tinyint(1) NOT NULL DEFAULT '0' COMMENT '商家入驻奖励开关 0关闭 1开启',
  `business_reward_days` int NOT NULL DEFAULT '7' COMMENT '商家入驻奖励天数',
  `business_reward_amount` decimal(10,2) NOT NULL COMMENT '商家入驻奖励金额',
  `business_reward_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '奖励类型 1现金 2积分',
  `business_reward_inviter_rate` decimal(5,2) NOT NULL DEFAULT '100.00' COMMENT '邀请人奖励比例(%)',
  `business_reward_notice` varchar(500) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '奖励说明',
  `business_reward_flow_enabled` tinyint(1) NOT NULL DEFAULT '0' COMMENT '商家入驻奖励流水条件开关 0:不启用 1:启用',
  `business_reward_flow_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '商家入驻奖励流水金额要求（单位：元，0表示不限制）',
  `small_market_fhjiesuantype` tinyint(1) NOT NULL DEFAULT '0' COMMENT '小市场分红结算方式 0:按销售金额 1:按销售利润',
  `team_list_enable` tinyint(1) NOT NULL DEFAULT '1' COMMENT '团队列表开关 1=开启 0=关闭',
  `team_agent_list_enable` tinyint(1) NOT NULL DEFAULT '1' COMMENT '接点人列表开关 1=开启 0=关闭',
  `team_agent_list_text` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '接点人列表' COMMENT '接点人列表自定义名称',
  `team_agent_page_settings` longtext COLLATE utf8mb4_general_ci COMMENT '接点人团队列表页面显示配置JSON',
  `team_list_direct_count_show` tinyint(1) NOT NULL DEFAULT '0' COMMENT '直推人数显示开关 1=显示 0=不显示',
  `team_list_statistics_show` tinyint(1) NOT NULL DEFAULT '0' COMMENT '总列表统计开关 1=显示 0=不显示',
  `team_list_level_filter_show` tinyint(1) NOT NULL DEFAULT '0' COMMENT '团队列表是否显示下级会员等级筛选：0否 1是',
  `team_list_shezhen_customer_show` tinyint(1) NOT NULL DEFAULT '0' COMMENT '团队列表是否可查看下级舌诊客户',
  `team_list_consume_show` tinyint(1) NOT NULL DEFAULT '0' COMMENT '消费金额显示开关 1=显示 0=不显示',
  `reg_invite_code_show` tinyint NOT NULL DEFAULT '0' COMMENT '注册邀请码显示控制',
  `reg_need_jiedian` tinyint(1) NOT NULL DEFAULT '0' COMMENT '注册是否需要接点人：0否，1是',
  `reg_jiedian_default_invite` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否默认邀请人即接点人：0否，1是',
  `commission2money_deduct_gongxianzhi` tinyint(1) NOT NULL DEFAULT '0' COMMENT '佣金转余额是否扣除贡献值：0=关闭，1=开启',
  `commission2money_gongxianzhi_rate` decimal(5,2) NOT NULL DEFAULT '100.00' COMMENT '佣金转余额贡献值扣除比例：默认100%',
  `tuozhanyuan_simple_apply` tinyint(1) NOT NULL DEFAULT '0' COMMENT '拓展员简单申请开关：0-关闭，1-开启',
  `tuozhanyuan_apply_mode` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'simple' COMMENT '申请模式: simple=简单申请, level=分类升级',
  `tuozhanyuan_condition_fail_action` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'guide' COMMENT '条件不满足时: guide=引导购买, audit=提交审核',
  `tuozhanyuan_condition_pass_action` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'auto' COMMENT '条件满足时: auto=自动通过, audit=仍需审核',
  `team_query_optimize` tinyint(1) DEFAULT '0' COMMENT '团队查询优化开关：0=关闭，1=开启',
  `uplv_optimize` tinyint(1) DEFAULT '0' COMMENT '用户升级优化开关：0=关闭，1=开启',
  `uplv_async_enabled` tinyint(1) DEFAULT '0' COMMENT '是否启用异步升级处理',
  `uplv_event_v2_enabled` tinyint(1) NOT NULL DEFAULT '0' COMMENT '会员升级二阶段事件写入：0关闭1开启',
  `uplv_event_execute_enabled` tinyint(1) NOT NULL DEFAULT '0' COMMENT '会员升级二阶段事件执行：0关闭1开启',
  `uplv_lease_seconds` int NOT NULL DEFAULT '900' COMMENT '会员升级任务租约秒数30-3600',
  `uplv_async_threshold` int DEFAULT '10000' COMMENT '异步处理团队人数阈值',
  `uplv_async_batch_size` int DEFAULT '10' COMMENT '每次处理队列数量',
  `uplv_async_max_retry` int DEFAULT '3' COMMENT '最大重试次数',
  `member_auto_addlogin` tinyint(1) NOT NULL DEFAULT '0' COMMENT '会员自动添加登录：1=开启，0=关闭',
  `member_auto_reg` tinyint(1) NOT NULL DEFAULT '0' COMMENT '游客自动注册会员：1=开启，0=关闭',
  `nologin_day` int NOT NULL DEFAULT '7' COMMENT '免登录天数设置',
  `maidan_auto_bind_parent` tinyint(1) NOT NULL DEFAULT '0' COMMENT '买单自动绑定上级开关（0：关闭，1：开启）',
  `cloud_speaker_enabled` tinyint(1) NOT NULL DEFAULT '0' COMMENT '云喇叭开关：0关闭，1开启',
  `cloud_speaker_device_id` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '' COMMENT '云喇叭设备编号',
  `team_reward_limit_enabled` tinyint(1) NOT NULL DEFAULT '0' COMMENT '团队奖限额功能开关：0=关闭，1=开启',
  `team_lottery_enabled` tinyint(1) NOT NULL DEFAULT '0' COMMENT '团队限额抽奖功能开关：0=关闭，1=开启',
  `global_lottery_fenhong_percent` decimal(5,2) NOT NULL DEFAULT '5.00' COMMENT '全网抽奖分红比例(%)',
  `lottery_mall_url` varchar(500) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '' COMMENT '抽奖商城链接地址',
  `lottery_mall_title` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '抽奖商城' COMMENT '抽奖商城入口标题',
  `jiedian_allow_modify` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否允许修改接点人：0不允许，1允许',
  `jiedian_modify_times` int NOT NULL DEFAULT '0' COMMENT '允许修改次数：0不限制，>0限制次数',
  `jiedian_need_audit` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否需要审核：0不需要，1需要审核',
  `jiedian_modify_interval` int NOT NULL DEFAULT '0' COMMENT '修改间隔天数：0不限制，>0限制间隔天数',
  `commission_withdraw_hei_reward` tinyint(1) NOT NULL DEFAULT '0' COMMENT '佣金提现现金券奖励开关 0=关闭 1=开启',
  `commission_withdraw_hei_percent` decimal(5,2) NOT NULL COMMENT '佣金提现现金券奖励百分比',
  `teamfenhong_show_admin` tinyint(1) NOT NULL DEFAULT '0' COMMENT '团队分红显示管理员 0关闭 1开启',
  `teamfenhong_show_phone` tinyint(1) NOT NULL DEFAULT '0' COMMENT '团队分红显示手机号 0不显示 1显示',
  `loading_effect_version` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT 'old' COMMENT '加载模式:old旧版/new新版/skeleton骨架屏',
  `loading_icon` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '当前加载图标URL',
  `loading_style` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '0' COMMENT '加载动画样式：0-线性旋转, 1-缓动旋转',
  `storage_limit` int NOT NULL DEFAULT '0',
  `tuozhanyuan_quota_enabled` tinyint(1) NOT NULL DEFAULT '0' COMMENT '拓展名额功能开关：0=关闭，1=开启',
  `website_template` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT 'business' COMMENT '当前使用模板',
  `website_config` longtext COLLATE utf8mb4_general_ci COMMENT '官网站点配置JSON',
  `team_reward_exclude_yeji` tinyint(1) NOT NULL DEFAULT '0' COMMENT '团队奖励是否排除业绩商品计算(0=只计算业绩商品,1=计算所有商品)',
  `team_reward_include_self` tinyint(1) NOT NULL DEFAULT '1' COMMENT '团队奖励是否包含自己业绩(0=不包含,1=包含)',
  `team_show_user_id` tinyint(1) NOT NULL DEFAULT '0' COMMENT '团队列表是否显示用户ID(0=不显示,1=显示)',
  `team_show_zichanfenqi_score` tinyint(1) NOT NULL DEFAULT '0' COMMENT '团队列表是否显示资产分期到账绿积分',
  `team_show_self_performance` tinyint(1) NOT NULL DEFAULT '1' COMMENT '团队列表是否显示个人业绩(0=不显示,1=显示)',
  `team_show_monthly_performance` tinyint(1) NOT NULL DEFAULT '1' COMMENT '团队列表是否显示当月业绩(0=不显示,1=显示)',
  `team_show_quarterly_performance` tinyint(1) NOT NULL DEFAULT '1' COMMENT '团队列表是否显示季度业绩(0=不显示,1=显示)',
  `team_show_monthly_boxes` tinyint(1) NOT NULL DEFAULT '1' COMMENT '团队列表是否显示当月盒数(0=不显示,1=显示)',
  `team_show_quarterly_boxes` tinyint(1) NOT NULL DEFAULT '1' COMMENT '团队列表是否显示季度盒数(0=不显示,1=显示)',
  `team_boxes_text` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '盒' COMMENT '团队列表盒数显示的自定义文字单位',
  `team_show_yuyue_commission` tinyint(1) DEFAULT '0' COMMENT '是否显示预约订单佣金',
  `team_show_yuyue_monthly` tinyint(1) DEFAULT '0' COMMENT '是否显示当月预约业绩',
  `team_show_yuyue_quarterly` tinyint(1) DEFAULT '0' COMMENT '是否显示季度预约业绩',
  `team_show_yuyue_yearly` tinyint(1) DEFAULT '0' COMMENT '是否显示当年预约业绩',
  `fenhong_mode` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT 'apiauto' COMMENT '分红计算模式 apiauto=ApiAuto定时分红 realtime=实时分红',
  `fenhong_optimization` tinyint(1) DEFAULT '0' COMMENT '分红优化开关 0关闭 1开启',
  `skeleton_type` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT 'list' COMMENT '骨架屏类型:list/card/grid',
  `skeleton_rows` tinyint DEFAULT '5' COMMENT '骨架屏行数',
  `skeleton_scenes` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '骨架屏场景',
  `tuozhanyuan_reward_enabled` tinyint(1) NOT NULL DEFAULT '0' COMMENT '拓展员奖励功能开关',
  `tuozhanyuan_reward_period` tinyint(1) NOT NULL DEFAULT '3' COMMENT '奖励周期 1天 2周 3月 4季度',
  `tuozhanyuan_reward_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '奖励发放方式 1余额 2拓展费 3积分',
  `tuozhanyuan_reward_auto` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否自动发放 1自动 0手动',
  `tuozhanyuan_reward_include_sub` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否包含直推下级拓展员绑定商户的流水 0不包含 1包含',
  `tuozhanyuan_layered_mode` tinyint(1) NOT NULL DEFAULT '0' COMMENT '拓展员层级递减模式：0关闭 1开启',
  `tuozhanyuan_equal_level_rate` decimal(5,2) NOT NULL DEFAULT '2.00' COMMENT '拓展员平级奖励比例(%)',
  `tuozhanyuan_fee_rate_mode` tinyint(1) NOT NULL DEFAULT '0' COMMENT '拓展员奖励模式：0=固定比例 1=按商家抽成费率',
  `uplv_usercenter_enabled` tinyint(1) NOT NULL DEFAULT '0' COMMENT '个人中心检查升级：0-关闭，1-启用',
  `uplv_usercenter_check_self` tinyint(1) NOT NULL DEFAULT '1' COMMENT '个人中心检查自己升级：0-关闭，1-启用',
  `uplv_usercenter_check_mode` varchar(16) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'async' COMMENT '个人中心本人升级检查方式：async异步 direct直接',
  `uplv_usercenter_check_parent` tinyint(1) NOT NULL DEFAULT '0' COMMENT '个人中心检查上级大团队：0-关闭，1-启用',
  `uplv_order_check_parent` tinyint(1) NOT NULL DEFAULT '1' COMMENT '订单完成检查上级大团队：0-关闭，1-启用',
  `uplv_order_check_parent_upgrade` tinyint(1) NOT NULL DEFAULT '1' COMMENT '订单完成检查上级升级：0-关闭，1-启用',
  `uplv_order_performance_stage` varchar(16) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'paid' COMMENT '会员升级商城订单业绩生效时机：paid支付后 collect确认收货后',
  `uplv_limit_parent_count` tinyint(1) NOT NULL DEFAULT '0' COMMENT '限制检查上级数量：0-关闭，1-启用',
  `uplv_limit_parent_levels` int NOT NULL DEFAULT '5' COMMENT '限制检查最近的几级上级（1-20级）',
  `uplv_early_stop` tinyint(1) NOT NULL DEFAULT '0' COMMENT '提前结束检查：0-关闭，1-启用（找到第一个大团队后停止）',
  `reg_jiedian_always_show` tinyint(1) NOT NULL DEFAULT '0' COMMENT '无邀请人时始终显示接点人输入框',
  `moneylog_page_settings` longtext COLLATE utf8mb4_general_ci COMMENT '会员资金明细页面显示配置JSON',
  `member_register_notel` tinyint(1) NOT NULL DEFAULT '0' COMMENT '注册支持账号(无需手机号)：0否 1是',
  `scorelv_withdraw_enabled` tinyint(1) NOT NULL DEFAULT '0' COMMENT '绿积分提现开关',
  `scorelv_withdraw_min` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '绿积分最低提现金额',
  `scorelv_withdraw_fee` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '绿积分提现手续费百分比',
  `scorelv_withdraw_rate` decimal(12,4) NOT NULL DEFAULT '1.0000' COMMENT '1绿积分兑换金额',
  `scorelv_withdraw_date` int NOT NULL DEFAULT '1' COMMENT '绿积分提现处理天数',
  `scorelv_withdraw_weixin` tinyint(1) NOT NULL DEFAULT '0' COMMENT '绿积分微信提现',
  `scorelv_withdraw_aliaccount` tinyint(1) NOT NULL DEFAULT '0' COMMENT '绿积分支付宝提现',
  `scorelv_withdraw_bankcard` tinyint(1) NOT NULL DEFAULT '0' COMMENT '绿积分银行卡提现',
  `scorelv_transfer_enabled` tinyint(1) NOT NULL DEFAULT '0' COMMENT '绿积分互转开关',
  `scorelv_transfer_fee` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '绿积分互转手续费百分比',
  `scorelv_transfer_min` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '绿积分最低互转数量',
  `scorelv_transfer_need_paypwd` tinyint(1) NOT NULL DEFAULT '1' COMMENT '绿积分互转需要支付密码',
  `scorelv_transfer_target_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '绿积分互转对象 1手机号 2会员ID',
  `scorelv_to_commission_enabled` tinyint(1) NOT NULL DEFAULT '0' COMMENT '绿积分兑换佣金开关',
  `scorelv_to_commission_rate` decimal(12,4) NOT NULL DEFAULT '1.0000' COMMENT '1绿积分兑换佣金',
  `scorelv_to_score_enabled` tinyint(1) NOT NULL DEFAULT '0' COMMENT '绿积分兑换积分开关',
  `scorelv_to_score_rate` decimal(12,4) NOT NULL DEFAULT '1.0000' COMMENT '1绿积分兑换积分',
  `scorelv_exchange_min` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '绿积分最低兑换数量',
  `scorelv_exchange_need_paypwd` tinyint(1) NOT NULL DEFAULT '1' COMMENT '绿积分兑换需要支付密码',
  `scorelv_exchange_enabled` tinyint(1) NOT NULL DEFAULT '0' COMMENT '绿积分兑换开关',
  `scorelv_exchange_target` varchar(20) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'commission' COMMENT '绿积分兑换目标 commission佣金 score积分',
  `scorelv_exchange_rate` decimal(12,4) NOT NULL DEFAULT '1.0000' COMMENT '1绿积分兑换数量',
  `scorelv_withdraw_multiple` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '绿积分提现倍数，0不限制',
  `h5_qq_map_key` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT 'H5前台腾讯地图Key，与后台选点Key分开',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `aid` (`aid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=139 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_admin_set_poster`
--

DROP TABLE IF EXISTS `ddwx_admin_set_poster`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_admin_set_poster` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `type` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT 'index' COMMENT 'index,product,collage,collageteam,kanjia,kanjiajoin',
  `platform` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT 'mp' COMMENT 'mp,wx,alipay,baidu,toutiao,qq,app',
  `content` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `guize` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `createtime` int DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=6635 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_admin_set_sms`
--

DROP TABLE IF EXISTS `ddwx_admin_set_sms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_admin_set_sms` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `status` tinyint(1) DEFAULT '0',
  `smstype` tinyint(1) DEFAULT '1' COMMENT '1阿里云 2聚合',
  `accesskey` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `accesssecret` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `sdkappid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `sign_name` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_smscode` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_orderconfirm` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_orderpay` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_collagesuccess` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_orderfahuo` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_tuisuccess` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_tuierror` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_tixiansuccess` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_tixianerror` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_fenxiaosuccess` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_smscode_st` tinyint(1) DEFAULT '1',
  `tmpl_orderconfirm_st` tinyint(1) DEFAULT '0',
  `tmpl_orderpay_st` tinyint(1) DEFAULT '1',
  `tmpl_collagesuccess_st` tinyint(1) DEFAULT '1',
  `tmpl_orderfahuo_st` tinyint(1) DEFAULT '1',
  `tmpl_yy_user` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '用户预约通知模板ID',
  `tmpl_yy_user_st` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '用户预约通知模板状态 0关闭 1开启',
  `tmpl_yy_worker` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '师傅预约通知模板ID',
  `tmpl_yy_worker_st` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '师傅预约通知模板状态 0关闭 1开启',
  `tmpl_tuisuccess_st` tinyint(1) DEFAULT '1',
  `tmpl_tuierror_st` tinyint(1) DEFAULT '1',
  `tmpl_tixiansuccess_st` tinyint(1) DEFAULT '1',
  `tmpl_tixianerror_st` tinyint(1) DEFAULT '1',
  `tmpl_fenxiaosuccess_st` tinyint(1) DEFAULT '1',
  `tmpl_restaurant_booking` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_restaurant_booking_st` tinyint(1) DEFAULT '1',
  `tmpl_restaurant_booking_fail` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_restaurant_booking_fail_st` tinyint(1) DEFAULT '1',
  `tmpl_checksuccess` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '审核通过通知',
  `tmpl_checkerror` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '审核驳回通知',
  `tmpl_checksuccess_st` tinyint(1) DEFAULT '1',
  `tmpl_checkerror_st` tinyint(1) DEFAULT '1',
  `tmpl_yysucess` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '',
  `tmpl_yysucess_st` tinyint(1) DEFAULT '1',
  `tmpl_recharge` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '充值成功',
  `tmpl_recharge_st` tinyint(1) DEFAULT '1' COMMENT '充值短信是否开启',
  `tmpl_sysmsg_notice` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '充值成功',
  `tmpl_sysmsg_notice_st` tinyint(1) DEFAULT '1' COMMENT '系统消息提醒是否开启',
  `tmpl_checknotice` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '待审核通知',
  `tmpl_checknotice_st` tinyint(1) DEFAULT '1' COMMENT '待审核通知提醒是否开启',
  `notice_mobile` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_neworder` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_neworder_st` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `aid` (`aid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=128 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_admin_set_xieyi`
--

DROP TABLE IF EXISTS `ddwx_admin_set_xieyi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_admin_set_xieyi` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '《用户注册协议》',
  `content` longtext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `name2` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '',
  `content2` longtext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `status` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `aid` (`aid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_admin_setapp_alipay`
--

DROP TABLE IF EXISTS `ddwx_admin_setapp_alipay`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_admin_setapp_alipay` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `appid` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `appsecret` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `publickey` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `nickname` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `headimg` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `qrcode` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `alipay` tinyint(1) DEFAULT '1',
  `sxpay` tinyint(1) DEFAULT '0',
  `sxpay_mno` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `sxpay_mchkey` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `openid_set` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT 'userid' COMMENT 'openid配置管理，userid、openid',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `aid` (`aid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=138 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_admin_setapp_app`
--

DROP TABLE IF EXISTS `ddwx_admin_setapp_app`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_admin_setapp_app` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `appid` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `appsecret` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `wxpay` tinyint(1) DEFAULT '0',
  `wxpay_type` tinyint(1) DEFAULT '0',
  `wxpay_sub_mchid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `wxpay_mchid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `wxpay_mchkey` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `wxpay_apiclient_cert` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `wxpay_apiclient_key` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `alipay` tinyint(1) DEFAULT '0',
  `ali_appid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `ali_privatekey` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `ali_publickey` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `paypal` tinyint(1) DEFAULT '0',
  `paypal_clientid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `paypal_clientsecret` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `androidurl` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `iosurl` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `app_update_open` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'App在线更新开关 1开0关',
  `android_version` varchar(20) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '安卓版本名 如2.1.0',
  `android_versioncode` int NOT NULL DEFAULT '0' COMMENT '安卓版本号(整数,对比用)',
  `android_url` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '安卓下载地址(apk直链或应用市场地址)',
  `android_force` tinyint(1) NOT NULL DEFAULT '0' COMMENT '安卓是否强制更新 1是0否',
  `ios_version` varchar(20) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT 'iOS版本名',
  `ios_url` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT 'iOS跳转地址(AppStore/企业分发)',
  `ios_force` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'iOS是否强制更新',
  `update_log` text COLLATE utf8mb4_general_ci COMMENT '更新说明(支持换行)',
  `wgt_open` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'wgt热更新开关 1开0关',
  `wgt_version` varchar(20) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT 'wgt资源包版本名',
  `wgt_versioncode` int NOT NULL DEFAULT '0' COMMENT 'wgt版本号(整数)',
  `wgt_url` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT 'wgt资源包下载地址',
  `wgt_force` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'wgt是否强制更新',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `aid` (`aid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=138 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_admin_setapp_baidu`
--

DROP TABLE IF EXISTS `ddwx_admin_setapp_baidu`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_admin_setapp_baidu` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `appid` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `appkey` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `appsecret` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `nickname` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `headimg` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `qrcode` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `access_token` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `expires_time` int DEFAULT NULL,
  `baidupay` tinyint(1) DEFAULT '1',
  `pay_appid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `pay_appkey` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `pay_dealId` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `pay_publickey` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `pay_privatekey` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `aid` (`aid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=138 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_admin_setapp_cashdesk`
--

DROP TABLE IF EXISTS `ddwx_admin_setapp_cashdesk`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_admin_setapp_cashdesk` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `appid` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `appsecret` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `wxpay` tinyint(1) DEFAULT '1',
  `wxpay_type` tinyint(1) DEFAULT '0',
  `wxpay_sub_mchid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `wxpay_mchid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `wxpay_mchkey` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `wxpay_apiclient_cert` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `wxpay_apiclient_key` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `bid` int DEFAULT '0',
  `sxpay_sub_mno` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '随行付商户号 服务商',
  `sxpay_sub_mchkey` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '随行付秘钥 服务商',
  `alipay` tinyint(1) DEFAULT '1',
  `ali_appid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `ali_privatekey` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `ali_publickey` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `sxpay` tinyint(1) DEFAULT '1',
  `sxpay_mno` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `sxpay_mchkey` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `cpcnpay` tinyint(1) DEFAULT '0' COMMENT '中金支付开关 0=关闭 1=开启',
  `cpcn_institution_id` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '中金机构号',
  `cpcn_certificate_type` tinyint(1) DEFAULT '1' COMMENT '证书类型 1=国密 2=国际',
  `cpcn_certificate_path` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '证书文件路径',
  `cpcn_certificate_password` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '证书密码',
  `cpcn_sign_algorithm` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT 'SHA1withRSA' COMMENT '签名算法',
  `cpcn_gateway_url` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT 'https://test.cpcn.com.cn/Gateway/InterfaceI' COMMENT '网关地址',
  `cpcn_notify_url` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '回调通知地址',
  `cpcn_settlement_flag` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '0001' COMMENT '结算标识',
  `cpcn_split_type` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '10' COMMENT '分账模式 10=不分账',
  `cpcn_store_num` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '门店编号',
  `cpcn_device_no` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '设备编号',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_admin_setapp_h5`
--

DROP TABLE IF EXISTS `ddwx_admin_setapp_h5`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_admin_setapp_h5` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `appid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `wxpay` tinyint(1) DEFAULT '0',
  `wxpay_type` tinyint(1) DEFAULT '0',
  `wxpay_sub_mchid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `wxpay_mchid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `wxpay_mchkey` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `wxpay_apiclient_cert` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `wxpay_apiclient_key` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `alipay` tinyint(1) DEFAULT '0',
  `ali_appid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `ali_privatekey` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `ali_publickey` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `cpcnpay` tinyint(1) DEFAULT '0' COMMENT '中金支付开关 0=关闭 1=开启',
  `cpcn_institution_id` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '中金机构号',
  `cpcn_certificate_type` tinyint(1) DEFAULT '1' COMMENT '证书类型 1=国密 2=国际',
  `cpcn_certificate_path` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '证书文件路径',
  `cpcn_certificate_password` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '证书密码',
  `cpcn_sign_algorithm` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT 'SHA1withRSA' COMMENT '签名算法',
  `cpcn_gateway_url` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT 'https://test.cpcn.com.cn/Gateway/InterfaceI' COMMENT '网关地址',
  `cpcn_notify_url` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '回调通知地址',
  `cpcn_settlement_flag` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '0001' COMMENT '结算标识',
  `cpcn_split_type` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '10' COMMENT '分账模式 10=不分账',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `aid` (`aid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=138 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_admin_setapp_mp`
--

DROP TABLE IF EXISTS `ddwx_admin_setapp_mp`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_admin_setapp_mp` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `authtype` tinyint(1) DEFAULT '0' COMMENT '0普通接入 1授权接入',
  `level` int DEFAULT '4' COMMENT '1普通订阅号2普通服务号3认证订阅号4认证服务号',
  `appid` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `appsecret` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `nickname` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `headimg` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `qrcode` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `token` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `key` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `refresh_token` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `open_appid` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `wxpay` tinyint(1) DEFAULT '0',
  `wxpay_type` tinyint(1) DEFAULT '0',
  `wxpay_sub_mchid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `wxpay_mchid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `wxpay_mchkey` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `wxpay_apiclient_cert` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `wxpay_apiclient_key` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `wxpay_serial_no` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `sxpay_mno` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `sxpay_mchkey` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `wxpay_wechatpay_pem` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '微信支付平台证书v3使用',
  `transfer_scene_id` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '商家转账场景ID(目前仅支持1005)',
  `jlpay_mno` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `jlpay_zd` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `fbpay_mno` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `fbpay_store` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `new_mch_transfer` tinyint(1) NOT NULL DEFAULT '0' COMMENT '新商家转账开关：0-关闭，1-开启',
  `transfer_scene_type` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '转账场景报备信息-岗位类型',
  `transfer_scene_content` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '转账场景报备信息-报酬说明',
  `wxpay_mchkey_v3` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '微信支付APIv3密钥密钥',
  `sign_type` tinyint(1) DEFAULT '0' COMMENT '微信支付加密验签方式 0平台证书 1支付公钥',
  `public_key_id` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '支付公钥ID',
  `public_key_pem` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '支付公钥文件路径',
  `huifu_sys_id` varchar(60) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `huifu_product_id` varchar(60) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `huifu_quickpay_st` tinyint(1) NOT NULL DEFAULT '0' COMMENT '汇付快捷支付开关',
  `huifu_public_key` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `huifu_merch_public_key` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `huifu_merch_private_key` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `wxpay_plate_serialno` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '平台证书序列号',
  `cpcnpay` tinyint(1) DEFAULT '0' COMMENT '中金支付开关 0=关闭 1=开启',
  `cpcn_payee_user_id` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '010026' COMMENT '中金支付收款用户ID',
  `cpcn_is_institution` tinyint(1) DEFAULT '1' COMMENT '是否机构收款模式：1=机构收款，0=普通用户收款',
  `cpcn_institution_id` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '中金机构号',
  `cpcn_certificate_type` tinyint(1) DEFAULT '1' COMMENT '证书类型 1=国密 2=国际',
  `cpcn_certificate_path` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '证书文件路径',
  `cpcn_certificate_password` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '证书密码',
  `cpcn_sign_algorithm` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT 'SHA1withRSA' COMMENT '签名算法',
  `cpcn_gateway_url` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT 'https://test.cpcn.com.cn/Gateway/InterfaceI' COMMENT '网关地址',
  `cpcn_notify_url` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '回调通知地址',
  `cpcn_settlement_flag` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '0001' COMMENT '结算标识',
  `cpcn_split_type` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '10' COMMENT '分账模式 10=不分账',
  `cpcn_verify_cert_path` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `cpcn_digital_envelope` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `cpcn_merchant_id` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '' COMMENT '中金支付公众号商户号',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `aid` (`aid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=138 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_admin_setapp_qq`
--

DROP TABLE IF EXISTS `ddwx_admin_setapp_qq`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_admin_setapp_qq` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `appid` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `appsecret` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `apptoken` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `nickname` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `headimg` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `qrcode` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `access_token` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `expires_time` int DEFAULT NULL,
  `wxpay` tinyint(1) DEFAULT '0',
  `wxpay_appid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `wxpay_type` tinyint(1) DEFAULT '0',
  `wxpay_sub_mchid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `wxpay_mchid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `wxpay_mchkey` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `wxpay_apiclient_cert` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `wxpay_apiclient_key` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `aid` (`aid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=138 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_admin_setapp_toutiao`
--

DROP TABLE IF EXISTS `ddwx_admin_setapp_toutiao`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_admin_setapp_toutiao` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `appid` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `appsecret` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `nickname` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `headimg` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `qrcode` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `toutiaopay` tinyint(1) DEFAULT '1',
  `pay_token` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `pay_mchid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `pay_salt` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `aid` (`aid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=138 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_admin_setapp_wx`
--

DROP TABLE IF EXISTS `ddwx_admin_setapp_wx`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_admin_setapp_wx` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `authtype` tinyint(1) DEFAULT '0' COMMENT '0普通接入 1授权接入',
  `level` tinyint(1) DEFAULT '1' COMMENT '1已认证0未认证',
  `appid` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `appsecret` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `nickname` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `headimg` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `qrcode` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `token` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `key` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `refresh_token` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `open_appid` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `wxpay` tinyint(1) DEFAULT '0',
  `wxpay_type` tinyint(1) DEFAULT '0',
  `wxpay_sub_mchid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `wxpay_sub_mchid2` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `wxpay_mchid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `wxpay_mchkey` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `wxpay_apiclient_cert` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `wxpay_apiclient_key` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `wxpay_serial_no` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `createtype` tinyint(1) DEFAULT '0' COMMENT '0自己注册 1通过开放平台复用公众号资质快速注册  2通过开放平台快速创建',
  `signature` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `sxpay_mno` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `sxpay_mchkey` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `sxpay_embedded` tinyint(1) DEFAULT NULL,
  `wxpay_wechatpay_pem` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `fbpay_mno` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `fbpay_embedded` tinyint(1) NOT NULL DEFAULT '0',
  `fbpay_store` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `jlpay_mno` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `jlpay_zd` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `new_mch_transfer` tinyint(1) NOT NULL DEFAULT '0' COMMENT '新商家转账开关：0-关闭，1-开启',
  `transfer_scene_id` varchar(36) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '转账场景ID',
  `transfer_scene_type` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `transfer_scene_content` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `wxpay_mchkey_v3` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `sign_type` tinyint(1) DEFAULT NULL,
  `public_key_id` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `public_key_pem` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `huifu_sys_id` varchar(60) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `huifu_product_id` varchar(60) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `huifu_public_key` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `huifu_merch_public_key` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `huifu_merch_private_key` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `wxpay_plate_serialno` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '平台证书序列号',
  `cpcnpay` tinyint(1) DEFAULT '0' COMMENT '中金支付开关 0=关闭 1=开启',
  `cpcn_institution_id` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '中金机构号',
  `cpcn_certificate_type` tinyint(1) DEFAULT '1' COMMENT '证书类型 1=国密 2=国际',
  `cpcn_certificate_path` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '证书文件路径',
  `cpcn_certificate_password` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '证书密码',
  `cpcn_sign_algorithm` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT 'SHA1withRSA' COMMENT '签名算法',
  `cpcn_gateway_url` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT 'https://test.cpcn.com.cn/Gateway/InterfaceI' COMMENT '网关地址',
  `cpcn_notify_url` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '回调通知地址',
  `cpcn_settlement_flag` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '0001' COMMENT '结算标识',
  `cpcn_split_type` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '10' COMMENT '分账模式 10=不分账',
  `cpcn_merchant_id` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '' COMMENT '中金支付小程序商户号',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `aid` (`aid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=138 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_admin_simulate_login_token`
--

DROP TABLE IF EXISTS `ddwx_admin_simulate_login_token`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_admin_simulate_login_token` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `aid` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `mid` int NOT NULL DEFAULT '0' COMMENT '会员ID',
  `admin_uid` int NOT NULL DEFAULT '0' COMMENT '后台用户ID',
  `token_hash` varchar(64) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '令牌SHA256',
  `used_time` int NOT NULL DEFAULT '0' COMMENT '使用时间',
  `expire_time` int NOT NULL DEFAULT '0' COMMENT '过期时间',
  `create_time` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_token_hash` (`token_hash`),
  KEY `idx_aid_token` (`aid`,`token_hash`),
  KEY `idx_expire_time` (`expire_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='后台模拟登录会员令牌';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_admin_system_config`
--

DROP TABLE IF EXISTS `ddwx_admin_system_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_admin_system_config` (
  `id` int NOT NULL AUTO_INCREMENT,
  `key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '配置键名',
  `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '配置值',
  `type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'string' COMMENT '类型: string/int/bool/json',
  `group` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'system' COMMENT '分组',
  `title` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '配置标题',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '配置描述',
  `createtime` int DEFAULT NULL COMMENT '创建时间',
  `updatetime` int DEFAULT NULL COMMENT '更新时间',
  `update_uid` int DEFAULT '0' COMMENT '更新人ID',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `key` (`key`) USING BTREE,
  KEY `group` (`group`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='系统配置表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_admin_system_config_log`
--

DROP TABLE IF EXISTS `ddwx_admin_system_config_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_admin_system_config_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `config_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '配置键名',
  `old_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '旧值',
  `new_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '新值',
  `uid` int DEFAULT '0' COMMENT '操作人ID',
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '操作人',
  `ip` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'IP地址',
  `createtime` int DEFAULT NULL COMMENT '操作时间',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `config_key` (`config_key`) USING BTREE,
  KEY `uid` (`uid`) USING BTREE,
  KEY `createtime` (`createtime`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='配置修改日志表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_admin_upload`
--

DROP TABLE IF EXISTS `ddwx_admin_upload`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_admin_upload` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `uid` int DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `dir` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `url` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `type` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `size` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `bsize` int DEFAULT NULL,
  `width` int DEFAULT NULL,
  `height` int DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  `platform` varchar(11) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT 'ht',
  `isdel` tinyint(1) DEFAULT '0',
  `gid` int DEFAULT '0',
  `merchant_id` int NOT NULL DEFAULT '0' COMMENT '商户ID',
  `order_id` int NOT NULL DEFAULT '0' COMMENT '订单ID',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `uid` (`uid`) USING BTREE,
  KEY `dir` (`dir`) USING BTREE,
  KEY `platform` (`platform`) USING BTREE,
  KEY `isdel` (`isdel`) USING BTREE,
  KEY `idx_merchant_order` (`merchant_id`,`order_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=12658 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_admin_upload_group`
--

DROP TABLE IF EXISTS `ddwx_admin_upload_group`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_admin_upload_group` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `uid` int DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  `sort` int DEFAULT '0',
  `pid` int unsigned DEFAULT '0',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `uid` (`uid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=58 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_admin_user`
--

DROP TABLE IF EXISTS `ddwx_admin_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_admin_user` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL COMMENT '账户ID',
  `bid` int DEFAULT '0' COMMENT '商户ID 平台是0',
  `un` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '登录账号',
  `pwd` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '登录密码MD5',
  `menu_display_mode` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT 'smart' COMMENT '菜单展示模式: standard-标准 smart-智能优化 flat-扁平化 custom-自定义',
  `ip` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '最后登录IP',
  `logintime` int DEFAULT NULL COMMENT '最后登录时间',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态 1开启 0关闭',
  `createtime` int DEFAULT NULL COMMENT '创建时间',
  `remark` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '备注',
  `auth_type` tinyint(1) DEFAULT '0' COMMENT '1 所有权限 0 选择权限',
  `auth_data` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '权限集 auth_type为0时才有效',
  `wxauth_data` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '手机端权限集 auth_type为0时才有效',
  `notice_auth_data` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '接收通知权限集',
  `isadmin` tinyint(1) DEFAULT '0' COMMENT '是否是主账号 1主账号 0分账号',
  `console_role` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0站点账号 2总控 3渠道主 4渠道子',
  `parent_uid` int NOT NULL DEFAULT '0' COMMENT '上级渠道管理员uid',
  `root_uid` int NOT NULL DEFAULT '0' COMMENT '渠道根uid',
  `platform_quota` int NOT NULL DEFAULT '0' COMMENT '可开设平台额度',
  `channel_auth_data` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '渠道后台权限json',
  `hexiao_auth_data` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '核销权限集',
  `mdid` int DEFAULT '0' COMMENT '门店ID 全部是0',
  `tmpl_orderconfirm` tinyint(1) DEFAULT '0' COMMENT '订单提交通知',
  `tmpl_orderpay` tinyint(1) DEFAULT '1' COMMENT '订单支付通知',
  `tmpl_ordershouhuo` tinyint(1) DEFAULT '1' COMMENT '订单收货通知',
  `tmpl_ordertui` tinyint(1) DEFAULT '1' COMMENT '退款通知',
  `tmpl_recharge` tinyint(1) DEFAULT '1' COMMENT '充值通知',
  `tmpl_withdraw` tinyint(1) DEFAULT '1' COMMENT '提现通知',
  `tmpl_formsub` tinyint(1) DEFAULT '1' COMMENT '表单提交通知',
  `tmpl_kehuzixun` tinyint(1) DEFAULT '1' COMMENT '客户咨询通知',
  `tmpl_uplv` tinyint(1) DEFAULT '1' COMMENT '会员升级通知',
  `tmpl_restaurant_booking` tinyint(1) DEFAULT '1' COMMENT '餐厅预订通知',
  `showtj` tinyint(1) DEFAULT '0' COMMENT '是否显示首页数据统计',
  `mid` int DEFAULT '0' COMMENT '绑定的会员ID',
  `random_str` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '随机字符串16位',
  `groupid` int DEFAULT '0' COMMENT '权限组id',
  `addid` int DEFAULT '0' COMMENT '哪个管理员添加的',
  `bids` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '0',
  `workcate_id` int DEFAULT '0',
  `workorder_type` int DEFAULT '0',
  `tuanid` int DEFAULT '0',
  `type` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT 'business' COMMENT '用户类型：business=商家,tuanzhang=团长',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE,
  KEY `un` (`un`) USING BTREE,
  KEY `status` (`status`) USING BTREE,
  KEY `idx_console_role` (`console_role`),
  KEY `idx_root_uid` (`root_uid`),
  KEY `idx_parent_uid` (`parent_uid`)
) ENGINE=InnoDB AUTO_INCREMENT=581 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_admin_user_group`
--

DROP TABLE IF EXISTS `ddwx_admin_user_group`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_admin_user_group` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `bid` int DEFAULT '0',
  `sort` int DEFAULT '0',
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `status` tinyint(1) DEFAULT '1',
  `createtime` int DEFAULT NULL,
  `remark` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `auth_data` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `wxauth_data` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `notice_auth_data` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `hexiao_auth_data` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `mdid` int DEFAULT '0',
  `showtj` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_admin_user_menu_custom`
--

DROP TABLE IF EXISTS `ddwx_admin_user_menu_custom`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_admin_user_menu_custom` (
  `id` int NOT NULL AUTO_INCREMENT,
  `uid` int NOT NULL COMMENT '用户ID',
  `menu_id` int NOT NULL COMMENT '菜单ID',
  `custom_pid` int DEFAULT NULL COMMENT '自定义父级ID',
  `custom_level` tinyint(1) DEFAULT NULL COMMENT '自定义层级',
  `custom_sort` int DEFAULT NULL COMMENT '自定义排序',
  `custom_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '自定义名称',
  `is_hidden` tinyint(1) DEFAULT '0' COMMENT '是否隐藏: 1-隐藏 0-显示',
  `createtime` int DEFAULT NULL,
  `updatetime` int DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uid_menu` (`uid`,`menu_id`) USING BTREE,
  KEY `uid` (`uid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='用户自定义菜单配置';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_admin_user_preference`
--

DROP TABLE IF EXISTS `ddwx_admin_user_preference`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_admin_user_preference` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `uid` int NOT NULL DEFAULT '0' COMMENT '管理员ID',
  `pref_key` varchar(100) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '配置键',
  `pref_value` longtext COLLATE utf8mb4_general_ci COMMENT '配置值(JSON)',
  `create_time` int NOT NULL DEFAULT '0',
  `update_time` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_aid_uid_pref_key` (`aid`,`uid`,`pref_key`),
  KEY `idx_aid_uid` (`aid`,`uid`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='后台管理员个性化配置';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_admin_virtual_user`
--

DROP TABLE IF EXISTS `ddwx_admin_virtual_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_admin_virtual_user` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int NOT NULL DEFAULT '0',
  `nickname` varchar(80) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `headimg` varchar(500) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `sort` int NOT NULL DEFAULT '0',
  `remark` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `createtime` int NOT NULL DEFAULT '0',
  `updatetime` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_aid_status_sort` (`aid`,`status`,`sort`),
  KEY `idx_aid` (`aid`)
) ENGINE=InnoDB AUTO_INCREMENT=2196 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=COMPACT COMMENT='氛围用户池';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_admin_wxalog`
--

DROP TABLE IF EXISTS `ddwx_admin_wxalog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_admin_wxalog` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  `user_version` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `user_desc` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `status` int DEFAULT '0' COMMENT '0未提交1审核中2待发布3已发布4已驳回',
  `audit_time` int DEFAULT NULL,
  `audit_reason` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `category` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `auditid` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `autofb` tinyint(1) DEFAULT '0',
  `version_desc` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_admin_wxreglog`
--

DROP TABLE IF EXISTS `ddwx_admin_wxreglog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_admin_wxreglog` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `code` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `code_type` int DEFAULT NULL,
  `legal_persona_wechat` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `legal_persona_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `component_phone` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `status` tinyint(1) DEFAULT '0' COMMENT '0审核中 1通过 2不通过',
  `reason` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '失败原因',
  `createtime` int DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_ai_newapi_config`
--

DROP TABLE IF EXISTS `ddwx_ai_newapi_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_ai_newapi_config` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `aid` int NOT NULL DEFAULT '0' COMMENT '平台ID',
  `mode` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0=使用平台配置 1=使用自己的配置 2=不使用',
  `base_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT 'NewAPI Base URL',
  `api_key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT 'NewAPI API Key',
  `model` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '默认模型',
  `temperature` decimal(4,2) NOT NULL DEFAULT '0.70' COMMENT 'temperature',
  `max_tokens` int NOT NULL DEFAULT '0' COMMENT 'max_tokens(0=默认)',
  `timeout` int NOT NULL DEFAULT '30' COMMENT 'timeout seconds',
  `createtime` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `updatetime` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_aid` (`aid`),
  KEY `idx_mode` (`mode`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='AI(NewAPI) 配置';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_alipay`
--

DROP TABLE IF EXISTS `ddwx_alipay`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_alipay` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int NOT NULL DEFAULT '0',
  `appid` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `alimach` varchar(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `bei` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  `userid` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '',
  `remarks` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '',
  `appCertPublicKey` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '',
  `alipayCertPublicKey` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '',
  `alipayRootCert` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uniq_aid` (`aid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_alipay_log`
--

DROP TABLE IF EXISTS `ddwx_alipay_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_alipay_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `mid` int DEFAULT NULL,
  `openid` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tablename` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `ordernum` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `mch_id` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `transaction_id` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `total_fee` decimal(11,2) DEFAULT NULL,
  `givescore` int DEFAULT '0',
  `createtime` int DEFAULT NULL,
  `fenzhangmoney` decimal(11,2) DEFAULT NULL,
  `isfenzhang` tinyint(1) DEFAULT '0',
  `fz_ordernum` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `fz_errmsg` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE,
  KEY `createtime` (`createtime`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_area`
--

DROP TABLE IF EXISTS `ddwx_area`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_area` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '' COMMENT '行政代码',
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '' COMMENT '名称',
  `parent_id` int NOT NULL COMMENT '父id',
  `first_letter` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '' COMMENT '首字母',
  `level` int NOT NULL COMMENT '城市等级',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=4651 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='多城市区域表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_async_worker_log`
--

DROP TABLE IF EXISTS `ddwx_async_worker_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_async_worker_log` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '日志ID',
  `aid` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `bid` int NOT NULL DEFAULT '0' COMMENT '商家ID',
  `uid` int NOT NULL DEFAULT '0' COMMENT '触发后台用户ID',
  `worker_key` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'async_work' COMMENT 'worker标识',
  `task_type` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '任务类型',
  `queue_key` varchar(120) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '队列Key',
  `task_id` varchar(100) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '任务ID',
  `status` varchar(20) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'success' COMMENT '执行状态',
  `message` varchar(500) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '执行说明',
  `duration_ms` int NOT NULL DEFAULT '0' COMMENT '耗时毫秒',
  `payload_json` mediumtext COLLATE utf8mb4_general_ci COMMENT '任务载荷JSON',
  `result_json` mediumtext COLLATE utf8mb4_general_ci COMMENT '执行结果JSON',
  `error_msg` text COLLATE utf8mb4_general_ci COMMENT '错误信息',
  `create_time` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_task_type_time` (`task_type`,`create_time`),
  KEY `idx_status_time` (`status`,`create_time`),
  KEY `idx_queue_task` (`queue_key`(80),`task_id`(80)),
  KEY `idx_aid_time` (`aid`,`create_time`)
) ENGINE=InnoDB AUTO_INCREMENT=10808 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='商城异步worker执行日志';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_baidupay_log`
--

DROP TABLE IF EXISTS `ddwx_baidupay_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_baidupay_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `mid` int DEFAULT NULL,
  `openid` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tablename` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `ordernum` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `mch_id` int DEFAULT NULL,
  `transaction_id` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `total_fee` decimal(11,2) DEFAULT NULL,
  `givescore` int DEFAULT '0',
  `createtime` int DEFAULT NULL,
  `fenzhangmoney` decimal(11,2) DEFAULT NULL,
  `isfenzhang` tinyint(1) DEFAULT '0',
  `fz_ordernum` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `fz_errmsg` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `userId` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE,
  KEY `createtime` (`createtime`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_business`
--

DROP TABLE IF EXISTS `ddwx_business`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_business` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `mid` int DEFAULT NULL,
  `mid2` int DEFAULT '0',
  `cid` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '0',
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `logo` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `desc` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `linkman` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `linktel` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tel` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `pics` varchar(1000) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `content` longtext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `province` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `city` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `district` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `street_id` int DEFAULT '0' COMMENT '街道ID',
  `address` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `zhengming` varchar(1000) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `register_signature_url` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '商家入驻签字图片',
  `register_contract_no` varchar(64) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '商家入驻签字合同编号',
  `register_contract_title` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '商家入驻签字合同标题',
  `register_contract_pdf` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '商家入驻签字合同PDF',
  `register_contract_signed_at` int NOT NULL DEFAULT '0' COMMENT '商家入驻签字时间',
  `longitude` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '经度',
  `latitude` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '纬度',
  `money` decimal(11,2) DEFAULT NULL,
  `weixin` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `aliaccount` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `bankname` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `bankcarduser` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `bankcardnum` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `sales` int DEFAULT '0',
  `virtual_sales` int unsigned NOT NULL DEFAULT '0' COMMENT '虚拟销售额',
  `feepercent` float(11,2) DEFAULT '0.00' COMMENT '费率',
  `feepercent_freight` float(11,2) DEFAULT '0.00' COMMENT '配送费费率',
  `sort` int DEFAULT '0',
  `home_template` tinyint(1) NOT NULL DEFAULT '0' COMMENT '店铺首页模板：0跟随平台 1经典 2图式 3产地实景',
  `home_metric2_value` varchar(32) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '首页指标2数值',
  `home_metric2_label` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '经营经验' COMMENT '首页指标2名称',
  `home_metric3_value` varchar(32) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '首页指标3数值',
  `home_metric3_label` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '环境评价' COMMENT '首页指标3名称',
  `home_live_title` varchar(100) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '实时监控' COMMENT '店铺首页监控区标题',
  `home_follow_show` tinyint(1) NOT NULL DEFAULT '1' COMMENT '店铺首页是否显示关注按钮',
  `createtime` int DEFAULT NULL,
  `status` tinyint(1) DEFAULT '0',
  `balance` decimal(10,2) NOT NULL COMMENT '进货账户余额',
  `consume_mode` tinyint(1) NOT NULL DEFAULT '1' COMMENT '消耗模式:1=预充值,2=月结',
  `recharge_notice` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '充值说明',
  `reason` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `comment_score` decimal(2,1) DEFAULT '5.0',
  `comment_num` int DEFAULT '0',
  `comment_haopercent` int DEFAULT '100' COMMENT '好评率',
  `start_hours` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '00:00',
  `end_hours` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '00:00',
  `invoice` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '发票 0关 1开',
  `invoice_type` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '1' COMMENT '发票类型 1普通 2专票',
  `is_open` tinyint(1) DEFAULT '1',
  `autocollecthour` int DEFAULT '168',
  `start_hours2` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '00:00',
  `end_hours2` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '00:00',
  `start_hours3` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '00:00',
  `end_hours3` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '00:00',
  `wxpayst` tinyint(1) DEFAULT '0',
  `wxpay_submchid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `express_wx_shop_no` varchar(60) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '商家门店编号',
  `kfurl` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `sxpay_mno` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `sxpay_mchkey` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `sxpay_storeNum` varchar(100) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '随行付门店号',
  `sxpay_deviceNo` varchar(100) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '随行付终端编号',
  `tuozhanid` varchar(11) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '0',
  `yihuo_baifenbi` decimal(10,2) NOT NULL,
  `storage_limit` int NOT NULL DEFAULT '0' COMMENT '存储空间限制(MB，0表示不限制)',
  `province_id` int unsigned NOT NULL DEFAULT '0' COMMENT '省ID',
  `city_id` int unsigned NOT NULL DEFAULT '0' COMMENT '城市ID',
  `district_id` int unsigned NOT NULL DEFAULT '0' COMMENT '区县ID',
  `jlpay_mno` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `jlpay_zd` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `jlpay_zd2` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `rate_id` int DEFAULT NULL,
  `fb_wxpayst` int NOT NULL DEFAULT '0',
  `fbpay_mno` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `fbpay_store` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `depositPaid` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `production_address` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '生产地址',
  `shop_address` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '实体店地址',
  `shop_url` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '线上链接',
  `wxid` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `debt_amount` decimal(11,2) NOT NULL COMMENT '欠款金额',
  `maidan_payaftertourl` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '支付后跳转URL',
  `reward_status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '奖励状态 0未达标 1待发放 2已发放 3已失效',
  `reward_check_time` int DEFAULT NULL COMMENT '奖励检查时间',
  `reward_send_time` int DEFAULT NULL COMMENT '奖励发放时间',
  `inviter_mid` int DEFAULT '0' COMMENT '邀请人会员ID',
  `maidan_day_limit` decimal(10,2) DEFAULT NULL COMMENT '买单日限额，0为不限额',
  `maidan_month_limit` decimal(10,2) DEFAULT NULL COMMENT '买单月限额，0为不限额',
  `maidan_single_limit` decimal(10,2) DEFAULT NULL COMMENT '买单每笔限额，0为不限额',
  `cloud_speaker_enabled` tinyint(1) NOT NULL DEFAULT '0' COMMENT '云喇叭开关：0关闭，1开启',
  `cloud_speaker_device_id` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '' COMMENT '云喇叭设备编号',
  `cpcn_institution_id` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '中金机构号（商户个性化）',
  `cpcn_certificate_path` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '证书文件路径（商户个性化）',
  `cpcn_certificate_password` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '证书密码（商户个性化）',
  `cpcn_certificate_type` tinyint(1) DEFAULT '0' COMMENT '证书类型（商户个性化） 0=使用系统配置 1=国密 2=国际',
  `cpcn_store_num` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '门店编号',
  `cpcn_device_no` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '设备编号',
  `cpcn_enabled` tinyint(1) DEFAULT '0' COMMENT '是否开启中金支付 0=关闭 1=开启',
  `cpcn_split_enabled` tinyint(1) DEFAULT '0' COMMENT '是否开启中金分账 0=关闭 1=开启',
  `cpcn_deduct_split_enabled` tinyint(1) NOT NULL DEFAULT '2' COMMENT '中金抵扣是否参与分账：0否 1是 2跟随系统',
  `cpcn_user_id` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '商户中金支付UserID',
  `cpcn_merchant_id` varchar(64) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '中金商户号',
  `cpcn_settlement_flag` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '商户结算标识',
  `show_balance` tinyint(1) DEFAULT '0' COMMENT '是否显示余额 0=隐藏 1=显示',
  `allow_withdraw` tinyint(1) DEFAULT '0' COMMENT '是否可提现 0=禁止 1=允许',
  `score` int NOT NULL DEFAULT '0' COMMENT '商家积分',
  `score2money` decimal(11,4) DEFAULT NULL COMMENT '独立积分：每积分抵扣金额(元)，为空走平台默认',
  `scoredkmaxpercent` decimal(11,2) DEFAULT NULL COMMENT '独立积分：最多抵扣百分比(0-100)，为空走平台默认',
  `scorebdkyf` tinyint(1) DEFAULT NULL COMMENT '独立积分：是否不抵扣运费 0否 1是，空走平台默认',
  `home_video` varchar(500) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '建材风首页顶部视频',
  `home_article_show` tinyint(1) NOT NULL DEFAULT '1' COMMENT '建材风首页是否展示商户文章',
  `home_badges` varchar(1000) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '建材风店名标识，JSON：文字和颜色',
  `home_banner_colors` varchar(20) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '建材风店名背景渐变',
  `home_sample_text` varchar(24) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '设计师首页作品按钮文案',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE,
  KEY `cid` (`cid`) USING BTREE,
  KEY `status` (`status`) USING BTREE,
  KEY `business_province_group` (`province_id`) USING BTREE,
  KEY `business_district_group` (`district_id`) USING BTREE,
  KEY `idx_street_id` (`street_id`) USING BTREE,
  KEY `idx_maidan_limits` (`maidan_day_limit`,`maidan_month_limit`,`maidan_single_limit`) USING BTREE,
  KEY `idx_cpcn_enabled` (`cpcn_enabled`) USING BTREE,
  KEY `idx_cpcn_split_enabled` (`cpcn_split_enabled`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=367 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_business_balance_log`
--

DROP TABLE IF EXISTS `ddwx_business_balance_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_business_balance_log` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `aid` int NOT NULL DEFAULT '0' COMMENT '应用ID/管理员ID',
  `bid` int NOT NULL DEFAULT '0' COMMENT '商户ID',
  `type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '类型：1收入 2支出',
  `amount` decimal(10,2) NOT NULL COMMENT '金额',
  `balance` decimal(10,2) NOT NULL COMMENT '操作后余额',
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '' COMMENT '备注说明',
  `createtime` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_aid_bid` (`aid`,`bid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='商户余额变动记录';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_business_balancelog`
--

DROP TABLE IF EXISTS `ddwx_business_balancelog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_business_balancelog` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL COMMENT '应用ID',
  `bid` int DEFAULT NULL COMMENT '商家ID',
  `money` decimal(11,2) DEFAULT NULL COMMENT '交易金额',
  `after` decimal(11,2) DEFAULT NULL COMMENT '交易后余额',
  `createtime` int DEFAULT NULL COMMENT '创建时间',
  `remark` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '备注说明',
  `orderid` int DEFAULT '0' COMMENT '相关订单ID',
  `admin_id` int DEFAULT '0' COMMENT '操作管理员ID',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE,
  KEY `createtime` (`createtime`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='商家进货款变动日志';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_business_category`
--

DROP TABLE IF EXISTS `ddwx_business_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_business_category` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pid` int NOT NULL DEFAULT '0' COMMENT '父分类ID，0为顶级',
  `aid` int DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `pic` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `status` int DEFAULT '1',
  `sort` int DEFAULT '1',
  `createtime` int DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `idx_pid` (`pid`)
) ENGINE=InnoDB AUTO_INCREMENT=106 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_business_comment`
--

DROP TABLE IF EXISTS `ddwx_business_comment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_business_comment` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `bid` int DEFAULT NULL,
  `mid` int DEFAULT NULL,
  `orderid` int DEFAULT NULL,
  `bname` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `ordernum` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `openid` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `nickname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `headimg` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `score` int DEFAULT NULL,
  `content` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `content_pic` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `reply_content` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `reply_content_pic` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `append_content` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `append_content_pic` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `append_reply_content` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `append_reply_content_pic` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  `appendtime` int DEFAULT NULL,
  `status` int DEFAULT '1',
  `reply_time` int DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE,
  KEY `orderid` (`orderid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_business_consume`
--

DROP TABLE IF EXISTS `ddwx_business_consume`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_business_consume` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int NOT NULL COMMENT '站点ID',
  `bid` int NOT NULL COMMENT '商家ID',
  `amount` decimal(10,2) NOT NULL COMMENT '消费金额',
  `type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '消费类型',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '备注',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_aid` (`aid`) USING BTREE,
  KEY `idx_bid` (`bid`) USING BTREE,
  KEY `idx_create_time` (`create_time`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='商家消费记录表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_business_feilv`
--

DROP TABLE IF EXISTS `ddwx_business_feilv`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_business_feilv` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `rate` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '费率(%)',
  `pic` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `status` int DEFAULT '1',
  `sort` int DEFAULT '1',
  `createtime` int DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_business_insurance_rate`
--

DROP TABLE IF EXISTS `ddwx_business_insurance_rate`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_business_insurance_rate` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `aid` int NOT NULL DEFAULT '0' COMMENT '平台ID',
  `bid` int NOT NULL DEFAULT '0' COMMENT '商户ID',
  `insurance_type` tinyint(1) NOT NULL DEFAULT '2' COMMENT '险种类型：1主险 2附加险',
  `insurance_id` int NOT NULL DEFAULT '0' COMMENT '险种ID',
  `rate` decimal(10,2) NOT NULL COMMENT '费率',
  `createtime` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `idx_unique` (`aid`,`bid`,`insurance_type`,`insurance_id`) USING BTREE,
  KEY `idx_bid` (`bid`) USING BTREE,
  KEY `idx_insurance` (`insurance_type`,`insurance_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='商户险种费率表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_business_member`
--

DROP TABLE IF EXISTS `ddwx_business_member`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_business_member` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bid` int NOT NULL,
  `mid` int NOT NULL,
  `createtime` int NOT NULL,
  `deletetime` int DEFAULT NULL,
  `totalmoney` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_business_member_scorelog`
--

DROP TABLE IF EXISTS `ddwx_business_member_scorelog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_business_member_scorelog` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `bid` int DEFAULT NULL,
  `mid` int DEFAULT NULL,
  `score` int DEFAULT '0',
  `after` int DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_business_member_upgrade_delay`
--

DROP TABLE IF EXISTS `ddwx_business_member_upgrade_delay`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_business_member_upgrade_delay` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int NOT NULL DEFAULT '0' COMMENT '应用ID',
  `mid` int NOT NULL DEFAULT '0' COMMENT '会员ID',
  `bid` int NOT NULL DEFAULT '0' COMMENT '商家ID',
  `target_level_id` int NOT NULL DEFAULT '0' COMMENT '目标等级ID',
  `upgrade_time` int NOT NULL DEFAULT '0' COMMENT '升级时间',
  `createtime` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `executetime` int NOT NULL DEFAULT '0' COMMENT '执行时间',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '状态 0待处理 1已处理 2已取消',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE,
  KEY `status` (`status`) USING BTREE,
  KEY `upgrade_time` (`upgrade_time`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='商家入驻延迟升级任务表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_business_memberscore`
--

DROP TABLE IF EXISTS `ddwx_business_memberscore`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_business_memberscore` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `bid` int DEFAULT NULL,
  `mid` int DEFAULT NULL,
  `score` int DEFAULT '0',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_business_moneylog`
--

DROP TABLE IF EXISTS `ddwx_business_moneylog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_business_moneylog` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `orderid` int DEFAULT '0' COMMENT '关联订单ID',
  `bid` int DEFAULT NULL,
  `mid` int NOT NULL COMMENT '用户id',
  `money` decimal(11,2) DEFAULT NULL,
  `order_money` decimal(11,2) DEFAULT NULL COMMENT '订单原始金额',
  `deduct_money` decimal(11,2) DEFAULT NULL COMMENT '扣除金额',
  `deduct_reason` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '扣款原因',
  `after` decimal(11,2) DEFAULT NULL,
  `afterusertotal` decimal(11,0) DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  `deletetime` int DEFAULT '0',
  `remark` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=760 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_business_poster`
--

DROP TABLE IF EXISTS `ddwx_business_poster`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_business_poster` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `aid` int NOT NULL DEFAULT '0' COMMENT '应用ID',
  `bid` int NOT NULL DEFAULT '0' COMMENT '商家ID',
  `posterid` int NOT NULL DEFAULT '0' COMMENT '海报模板ID',
  `scene` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '场景参数',
  `page` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '页面路径',
  `poster` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '生成的海报URL',
  `poster_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '海报URL（新接口使用）',
  `createtime` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE,
  KEY `posterid` (`posterid`) USING BTREE,
  KEY `aid_bid_posterid` (`aid`,`bid`,`posterid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='商家海报生成记录表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_business_recharge`
--

DROP TABLE IF EXISTS `ddwx_business_recharge`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_business_recharge` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `bid` int NOT NULL DEFAULT '0' COMMENT '商户ID',
  `amount` decimal(10,2) NOT NULL COMMENT '充值金额',
  `pay_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '支付方式:bank=银行转账,alipay=支付宝,wxpay=微信',
  `pay_evidence` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '支付凭证',
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '' COMMENT '备注说明',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '状态:0=待审核,1=已通过,2=已拒绝',
  `has_invoice` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '是否已开发票 0否 1是',
  `audit_remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '' COMMENT '审核备注',
  `audit_time` datetime DEFAULT NULL COMMENT '审核时间',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_aid_bid` (`aid`,`bid`) USING BTREE,
  KEY `idx_status` (`status`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='商户充值记录表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_business_recharge_order`
--

DROP TABLE IF EXISTS `ddwx_business_recharge_order`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_business_recharge_order` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `bid` int DEFAULT NULL,
  `money` decimal(11,2) DEFAULT NULL,
  `ordernum` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  `status` tinyint(1) DEFAULT '0',
  `payorderid` int DEFAULT NULL,
  `paytypeid` int DEFAULT NULL,
  `paytype` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `paynum` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `paytime` int DEFAULT NULL,
  `platform` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `mid` (`bid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_business_scorelog`
--

DROP TABLE IF EXISTS `ddwx_business_scorelog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_business_scorelog` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `bid` int DEFAULT NULL,
  `mid` int NOT NULL DEFAULT '0' COMMENT '来源会员ID',
  `from_table` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '来源表',
  `from_orderid` int NOT NULL DEFAULT '0' COMMENT '来源订单ID',
  `from_ordernum` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '来源单号',
  `score` int DEFAULT '0',
  `after` int DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_business_sysset`
--

DROP TABLE IF EXISTS `ddwx_business_sysset`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_business_sysset` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `default_rate` decimal(11,2) DEFAULT NULL,
  `withdraw_weixin` tinyint(1) DEFAULT '1',
  `withdraw_aliaccount` tinyint(1) DEFAULT '1',
  `withdraw_bankcard` tinyint(1) DEFAULT '1',
  `withdrawmin` decimal(11,2) DEFAULT '1.00',
  `withdrawfee` decimal(11,2) DEFAULT NULL,
  `product_isshow` tinyint(1) DEFAULT '0',
  `commission_canset` tinyint(1) DEFAULT '0',
  `coupon_canset` tinyint(1) DEFAULT '0',
  `commission_kouchu` tinyint(1) DEFAULT '0',
  `product_showset` tinyint(1) DEFAULT '0',
  `xieyi_show` tinyint(1) DEFAULT '0',
  `xieyi` longtext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `seckill_canset` tinyint(1) DEFAULT '0',
  `collage_canset` tinyint(1) DEFAULT '0',
  `diy_canset` tinyint(1) DEFAULT '0',
  `product_check` tinyint(1) DEFAULT '0',
  `article_check` tinyint(1) DEFAULT '0',
  `filem_check` tinyint(1) NOT NULL DEFAULT '0' COMMENT '文件是否需要审核 0否 1是',
  `shortvideo_check` tinyint(1) DEFAULT '0',
  `status` tinyint(1) DEFAULT '0',
  `charge_status` int NOT NULL DEFAULT '0',
  `commission_autotransfer` tinyint(1) DEFAULT '0',
  `wxfw_status` tinyint(1) DEFAULT '0',
  `wxfw_mchname` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `wxfw_appid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `wxfw_mchid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `wxfw_mchkey` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `wxfw_apiclient_cert` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `wxfw_apiclient_key` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `moneypay` tinyint(1) DEFAULT '0',
  `scorein_money` decimal(11,2) DEFAULT '1.00',
  `scorein_score` int DEFAULT '0',
  `parentcommission` decimal(11,2) DEFAULT NULL,
  `show_product` tinyint(1) NOT NULL DEFAULT '1' COMMENT '首页是否显示本店商品0:否 1：是',
  `show_comment` tinyint(1) NOT NULL DEFAULT '1' COMMENT '首页是否显示店铺评价0:否 1：是',
  `show_detail` tinyint(1) NOT NULL DEFAULT '1' COMMENT '首页是否显示商家详情0:否 1：是',
  `show_link` tinyint(1) NOT NULL DEFAULT '1' COMMENT '首页是否显示联系商家0:否 1：是',
  `show_linktext` varchar(30) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '' COMMENT '联系商家文字',
  `show_producttext` varchar(30) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '' COMMENT '本店商品文字',
  `show_commenttext` varchar(30) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '' COMMENT '店铺评价文字',
  `show_detailtext` varchar(30) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '' COMMENT '商家详情文字',
  `jlfw_status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否开启 0-关闭 1-开启',
  `jlfw_mchname` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `jlfw_appid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '嘉联机构号',
  `jlfw_mchid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '嘉联商户号',
  `jlfw_termno` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '嘉联终端号',
  `jlfw_mchkey` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `jlfw_apiclient_cert` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `jlfw_apiclient_key` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `pay_service` tinyint DEFAULT '1' COMMENT '支付服务商 1-微信 2-嘉联',
  `cpcn_bindcard_required_for_qrcode` tinyint(1) NOT NULL DEFAULT '0' COMMENT '收款码绑定要求：0=无需绑定即可显示，1=必须绑定中金账户',
  `daihuoyiuan_check` tinyint(1) NOT NULL,
  `rukouweiyi_status` tinyint NOT NULL DEFAULT '0',
  `deposit_switch` tinyint(1) DEFAULT '0' COMMENT '保证金缴纳开关',
  `deposit_url` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '保证金缴纳链接',
  `pay_switch` tinyint(1) NOT NULL DEFAULT '0' COMMENT '买单开关 0关闭 1开启',
  `pay_notice` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '付款须知说明',
  `verify_notice` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '入驻审核提示',
  `verify_success` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '审核成功提示',
  `verify_reject` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '审核拒绝提示',
  `verify_normal` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '常规审核页显示',
  `after_register_url` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '入驻后跳转链接',
  `consume_mode` tinyint(1) NOT NULL DEFAULT '1' COMMENT '消耗模式:1=预充值,2=月结',
  `recharge_notice` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '充值说明',
  `message_check` tinyint(1) NOT NULL DEFAULT '0' COMMENT '消息通知是否需要审核 1需要 0不需要',
  `maidan_user_contribution_percent` decimal(5,2) DEFAULT NULL COMMENT '用户买单贡献值比例(%)',
  `maidan_user_yellow_score_percent` decimal(5,2) DEFAULT NULL COMMENT '用户买单黄积分比例(%)',
  `maidan_admin_contribution_percent` decimal(5,2) DEFAULT NULL COMMENT '商家管理员贡献值比例(%)',
  `maidan_admin_yellow_score_percent` decimal(5,2) DEFAULT NULL COMMENT '商家管理员黄积分比例(%)',
  `maidan_reward_type` tinyint(1) DEFAULT '1' COMMENT '奖励计算方式：1=按流水百分比，2=按抽佣百分比',
  `maidan_reward_deduct_gift_enabled` tinyint(1) NOT NULL DEFAULT '1' COMMENT '买单奖励中抵扣金额是否参与赠送：0否 1是',
  `maidan_reward_paytype` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'balance,wechat' COMMENT '买单奖励支付方式(balance=余额支付,wechat=微信支付，多个逗号分隔，空表示不赠送)',
  `maidan_limit_enable` tinyint(1) DEFAULT '0' COMMENT '买单限额功能开关，0关闭1开启',
  `global_maidan_day_limit` decimal(10,2) DEFAULT NULL COMMENT '全局买单日限额，0为不限额',
  `global_maidan_month_limit` decimal(10,2) DEFAULT NULL COMMENT '全局买单月限额，0为不限额',
  `global_maidan_single_limit` decimal(10,2) DEFAULT NULL COMMENT '全局买单每笔限额，0为不限额',
  `simple_register_mode` tinyint(1) NOT NULL DEFAULT '0' COMMENT '简单入驻模式开关：0关闭，1开启',
  `register_need_audit` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否需要审核：0不需要，1需要',
  `register_need_invite_code` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否需要邀请码：0不需要，1需要',
  `register_need_select_rate` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否需要选择费率：0不需要，1需要',
  `register_need_fill_rate` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否需要填写费率：0不需要，1需要',
  `rate_min` decimal(5,2) NOT NULL COMMENT '费率最小值（%）',
  `rate_max` decimal(5,2) NOT NULL DEFAULT '100.00' COMMENT '费率最大值（%）',
  `register_need_product_images` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否需要产品图片：0不需要，1需要',
  `register_need_certificates` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否需要身份证营业执照：0不需要，1需要',
  `register_need_withdraw_method` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否需要提现方式：0不需要，1需要',
  `register_sign_enable` tinyint(1) NOT NULL DEFAULT '0' COMMENT '商家入驻是否启用协议签字 0关闭 1开启',
  `register_member_upgrade_enable` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否启用会员升级功能 0关闭 1启用',
  `register_member_upgrade_level` int NOT NULL DEFAULT '0' COMMENT '升级目标等级ID',
  `register_member_upgrade_condition` tinyint(1) NOT NULL DEFAULT '1' COMMENT '升级条件 1入驻通过即升级 2入驻且经营满7天',
  `jlfw_auto_register` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否嘉联进件 0关闭 1开启',
  `business_qr_register_bind_enabled` tinyint(1) NOT NULL DEFAULT '0' COMMENT '商家二维码注册绑定开关 0关闭 1开启',
  `global_show_balance` tinyint(1) DEFAULT '0' COMMENT '全局余额显示开关 0=关闭 1=开启',
  `global_allow_withdraw` tinyint(1) DEFAULT '0' COMMENT '全局提现功能开关 0=关闭 1=开启',
  `independent_score_enabled` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否开启独立积分 0关闭 1开启',
  `cloud_speaker_switch` tinyint(1) NOT NULL DEFAULT '0' COMMENT '商家云喇叭设置开关',
  `show_sales_price` tinyint(1) NOT NULL DEFAULT '1' COMMENT '商家列表是否显示销售额 0关闭 1开启',
  `index_template` tinyint(1) NOT NULL DEFAULT '1' COMMENT '商家首页模板 1模板1经典 2模板2图式',
  `list_template` tinyint(1) NOT NULL DEFAULT '1' COMMENT '商家列表样式：1默认卡片 2大图卡片',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `status` (`status`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=137 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_business_withdrawlog`
--

DROP TABLE IF EXISTS `ddwx_business_withdrawlog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_business_withdrawlog` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `bid` int DEFAULT NULL,
  `money` decimal(11,2) DEFAULT NULL,
  `txmoney` decimal(11,2) DEFAULT NULL,
  `weixin` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `aliaccount` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `ordernum` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `paytype` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `status` tinyint(1) DEFAULT '0',
  `createtime` int DEFAULT NULL,
  `bankname` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `bankcarduser` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `bankcardnum` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `paytime` int DEFAULT NULL,
  `paynum` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `reason` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `wx_package_info` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `wx_transfer_bill_no` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `wx_transfer_msg` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `wx_state` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `platform` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE,
  KEY `status` (`status`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_cashback`
--

DROP TABLE IF EXISTS `ddwx_cashback`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_cashback` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT '0',
  `bid` int DEFAULT '0',
  `name` varchar(30) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '名称',
  `back_ratio` decimal(10,5) DEFAULT NULL COMMENT '返还比率',
  `reward_calc_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '奖励计算方式：1按销售额百分比、2按成本价奖励、3按利润奖励',
  `deduct_gift_enabled` tinyint(1) NOT NULL DEFAULT '1' COMMENT '抵扣金额是否参与赠送：0否 1是',
  `paytypeids` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '-1' COMMENT '适用主支付方式：-1不限，逗号分隔支付分组key',
  `deduct_match_mode` tinyint(1) NOT NULL DEFAULT '0' COMMENT '抵扣条件：0不限 1任一选中 2全部选中 3未使用选中 4不使用任何抵扣',
  `deduct_types` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '适用抵扣资产：score,heiscore,scorehuang,money,coupon',
  `back_type` tinyint(1) DEFAULT '0' COMMENT '返还类型 1、余额 2、佣金 3、积分',
  `reward_types` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '订单达标玩法返现资产类型，逗号分隔',
  `gettj` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '参与人群',
  `fwtype` tinyint(1) DEFAULT '0',
  `categoryids` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `productids` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `minprice` decimal(10,2) DEFAULT NULL,
  `starttime` int DEFAULT NULL,
  `endtime` int DEFAULT NULL,
  `sort` int DEFAULT '0',
  `status` int DEFAULT '1',
  `createtime` int DEFAULT NULL,
  `back_time` tinyint(1) NOT NULL DEFAULT '1' COMMENT '返现时机：1收货后返现 2下单后返现',
  `calc_mode` tinyint(1) NOT NULL DEFAULT '0' COMMENT '返现玩法：0按商品行比例 1订单达标按实付1:1返积分',
  `threshold_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '订单门槛类型：1总件数 2实付金额',
  `threshold_value` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '订单门槛值：件数或实付金额',
  `sku_rule_mode` tinyint(1) NOT NULL DEFAULT '0' COMMENT '订单达标奖励方式：0统一比例 1按规格固定赠送',
  `sku_reward_rules` longtext COLLATE utf8mb4_general_ci COMMENT '规格实付门槛及固定赠送数量JSON',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE,
  KEY `status` (`status`) USING BTREE,
  KEY `starttime` (`starttime`) USING BTREE,
  KEY `endtime` (`endtime`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_cashback_reward_log`
--

DROP TABLE IF EXISTS `ddwx_cashback_reward_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_cashback_reward_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `bid` int NOT NULL DEFAULT '0' COMMENT '商家ID',
  `cashback_id` int NOT NULL DEFAULT '0' COMMENT '购物返现活动ID',
  `orderid` int NOT NULL DEFAULT '0' COMMENT '商城订单ID',
  `ogid` int NOT NULL DEFAULT '0' COMMENT '订单商品ID',
  `mid` int NOT NULL DEFAULT '0' COMMENT '获奖会员ID',
  `trigger_type` varchar(20) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '触发类型：pay下单后 receive收货后',
  `paytypeid` int NOT NULL DEFAULT '0' COMMENT '订单主支付方式',
  `deduct_snapshot` varchar(500) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '抵扣字段快照JSON',
  `calc_base` decimal(12,5) NOT NULL DEFAULT '0.00000' COMMENT '计算基数',
  `reward_ratio` decimal(10,5) NOT NULL DEFAULT '0.00000' COMMENT '返现比例',
  `reward_amount` decimal(12,5) NOT NULL DEFAULT '0.00000' COMMENT '发放数量',
  `reward_type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '发放资产类型',
  `reward_types` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '实际发放资产类型，逗号分隔',
  `remark` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '发放说明',
  `create_time` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_cashback_order_goods_trigger` (`aid`,`cashback_id`,`orderid`,`ogid`,`trigger_type`) USING BTREE,
  KEY `idx_aid_bid` (`aid`,`bid`) USING BTREE,
  KEY `idx_orderid` (`orderid`) USING BTREE,
  KEY `idx_mid` (`mid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='购物返现发放日志';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_channel_quota`
--

DROP TABLE IF EXISTS `ddwx_channel_quota`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_channel_quota` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `root_uid` int NOT NULL DEFAULT '0',
  `quota_key` varchar(32) NOT NULL DEFAULT '',
  `remaining` int NOT NULL DEFAULT '0',
  `total_in` int NOT NULL DEFAULT '0',
  `total_out` int NOT NULL DEFAULT '0',
  `updatetime` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_root_key` (`root_uid`,`quota_key`),
  KEY `idx_quota_key` (`quota_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='渠道接口次数池';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_channel_quota_log`
--

DROP TABLE IF EXISTS `ddwx_channel_quota_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_channel_quota_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `root_uid` int NOT NULL DEFAULT '0',
  `quota_key` varchar(32) NOT NULL DEFAULT '',
  `change_num` int NOT NULL DEFAULT '0',
  `before_num` int NOT NULL DEFAULT '0',
  `after_num` int NOT NULL DEFAULT '0',
  `aid` int DEFAULT NULL,
  `biz_type` varchar(32) NOT NULL DEFAULT '',
  `operator_id` int NOT NULL DEFAULT '0',
  `operator_name` varchar(64) NOT NULL DEFAULT '',
  `remark` varchar(255) NOT NULL DEFAULT '',
  `createtime` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_root_key_time` (`root_uid`,`quota_key`,`createtime`),
  KEY `idx_aid` (`aid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='渠道接口次数流水';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_commission_convert_records`
--

DROP TABLE IF EXISTS `ddwx_commission_convert_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_commission_convert_records` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `aid` int NOT NULL DEFAULT '0' COMMENT '应用ID',
  `member_id` int NOT NULL DEFAULT '0' COMMENT '会员ID',
  `original_commission` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '原始佣金金额',
  `convert_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '转换金额',
  `shouxufei_rate` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT '手续费比例',
  `shouxufei_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '手续费金额',
  `actual_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '实际转换金额（扣除手续费后）',
  `jifen_ratio` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT '转入积分比例',
  `jifen_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '转入积分金额',
  `yue_ratio` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT '转入余额比例',
  `yue_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '转入余额金额',
  `xianjinquan_ratio` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT '转入现金券比例',
  `xianjinquan_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '转入现金券金额',
  `convert_time` int NOT NULL DEFAULT '0' COMMENT '转换时间',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态：1-成功，0-失败',
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '' COMMENT '备注',
  `created_time` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_aid` (`aid`) USING BTREE,
  KEY `idx_member_id` (`member_id`) USING BTREE,
  KEY `idx_convert_time` (`convert_time`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='佣金转换记录表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_console_doc_category`
--

DROP TABLE IF EXISTS `ddwx_console_doc_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_console_doc_category` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '分类名称',
  `description` varchar(500) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '分类描述',
  `sort` int NOT NULL DEFAULT '0' COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态 1启用 0禁用',
  `creator_uid` int unsigned NOT NULL DEFAULT '0' COMMENT '创建人',
  `updater_uid` int unsigned NOT NULL DEFAULT '0' COMMENT '更新人',
  `createtime` int unsigned NOT NULL DEFAULT '0' COMMENT '创建时间',
  `updatetime` int unsigned NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_status_sort` (`status`,`sort`,`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=COMPACT COMMENT='控制台文档分类';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_console_doc_item`
--

DROP TABLE IF EXISTS `ddwx_console_doc_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_console_doc_item` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `category_id` int unsigned NOT NULL DEFAULT '0' COMMENT '分类ID',
  `title` varchar(200) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '文档标题',
  `summary` varchar(1000) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '文档摘要',
  `content` longtext COLLATE utf8mb4_general_ci COMMENT '文档内容',
  `sort` int NOT NULL DEFAULT '0' COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态 1启用 0禁用',
  `is_top` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否置顶',
  `creator_uid` int unsigned NOT NULL DEFAULT '0' COMMENT '创建人',
  `updater_uid` int unsigned NOT NULL DEFAULT '0' COMMENT '更新人',
  `createtime` int unsigned NOT NULL DEFAULT '0' COMMENT '创建时间',
  `updatetime` int unsigned NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_category_status_sort` (`category_id`,`status`,`sort`,`id`),
  KEY `idx_title` (`title`(191))
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=COMPACT COMMENT='控制台文档内容';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_coupon`
--

DROP TABLE IF EXISTS `ddwx_coupon`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_coupon` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL COMMENT '账户ID',
  `bid` int DEFAULT '0' COMMENT '商家ID 0表示平台',
  `type` tinyint(1) DEFAULT '1' COMMENT '1代金券2礼品券3计次券',
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '优惠券名称',
  `starttime` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '开始时间',
  `endtime` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '结束时间',
  `yxqtype` tinyint DEFAULT NULL COMMENT '有效期类型 1定时间范围 2领取后时长',
  `yxqdate` int DEFAULT NULL COMMENT '领取后多少天有效',
  `yxqtime` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '有效期时间范围',
  `money` float(11,2) DEFAULT NULL COMMENT '抵扣金额',
  `discount` float(11,2) DEFAULT NULL COMMENT '折扣券折扣比例',
  `minprice` float(11,2) DEFAULT NULL COMMENT '最低消费金额',
  `limit_count` int DEFAULT '10' COMMENT '计次券类型时 可用次数',
  `limit_perday` int DEFAULT '0' COMMENT '计次券类型时 每天可用次数',
  `stock` int DEFAULT NULL COMMENT '库存',
  `getnum` int DEFAULT '0',
  `perlimit` int DEFAULT '1' COMMENT '每人可领数量',
  `gettips` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `usetips` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '使用说明',
  `price` float(11,2) DEFAULT '0.00' COMMENT '所需金额',
  `score` int DEFAULT '0' COMMENT '所需积分',
  `sort` int DEFAULT '0' COMMENT '排序',
  `createtime` int DEFAULT NULL COMMENT '创建时间',
  `tolist` tinyint(1) DEFAULT '1' COMMENT '是否可直接领取',
  `showtj` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '-1' COMMENT '显示条件 -1不限制',
  `usetj` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '-1' COMMENT '使用条件 -1不限制',
  `isgive` tinyint(1) DEFAULT '0' COMMENT '是否可赠送',
  `paygive` tinyint(1) DEFAULT '0' COMMENT '支付后赠送',
  `paygive_scene` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '支付赠送场景',
  `paygive_minprice` decimal(10,2) DEFAULT NULL COMMENT '支付赠送最小金额',
  `gettj` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '-1' COMMENT '领取条件 -1不限制',
  `paygive_maxprice` decimal(10,2) DEFAULT '9999.00',
  `fwtype` tinyint(1) DEFAULT '0' COMMENT '0全场通用,1指定类目,2指定商品,3指定菜品,4指定服务商品',
  `categoryids` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '指定类目ids',
  `productids` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '指定商品ids',
  `restaurant_productids` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '指定菜品ids',
  `yuyue_productids` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '指定服务商品ids',
  `buyproids` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '购买商品赠送 商品ID数据',
  `buyprogive` tinyint(1) DEFAULT '0' COMMENT '是否开启购买商品赠送',
  `buypro_give_num` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '1',
  `buy_restaurant_product_give` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '是否购买菜品赠送',
  `buy_restaurant_product_ids` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '菜品id,间隔',
  `usecoupon_give_score` int DEFAULT '0',
  `usecoupon_give_type` tinyint(1) DEFAULT '1',
  `pack_coupon_ids` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `fwscene` tinyint(1) DEFAULT '0' COMMENT '适用场景0：所有 1:买单 2：收银台',
  `canused_bids` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `canused_bcids` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `rewardedvideoad` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `font_color` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '#2B2B2B' COMMENT '字体颜色',
  `title_color` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '#2B2B2B' COMMENT '标题颜色',
  `bg_color` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '#FFFFFF' COMMENT '背景颜色',
  `buyyuyueprogive` tinyint(1) DEFAULT '0' COMMENT '购买服务商品赠送 0:关闭 1：开启',
  `buyyuyueproids` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '购买服务商品集合',
  `buyyuyuepro_give_num` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '购买服务商品数量',
  `carhailing_productids` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `buycarhailingprogive` tinyint(1) DEFAULT '0' COMMENT '购买约车赠送 0:关闭 1：开启',
  `buycarhailingproids` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '购买约车商品集合',
  `carhailing_give_num` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '购买约车商品数量',
  `hotelids` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `categoryids2` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '指定商家类目ids',
  `roomids` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `give_num_type` tinyint(1) DEFAULT '0' COMMENT '赠送数量类型,0按设置数量,1按设置数量*购买数量',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE,
  KEY `type` (`type`) USING BTREE,
  KEY `starttime` (`starttime`) USING BTREE,
  KEY `endtime` (`endtime`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='优惠券';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_coupon_order`
--

DROP TABLE IF EXISTS `ddwx_coupon_order`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_coupon_order` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL COMMENT '账户ID',
  `bid` int DEFAULT '0' COMMENT '商户ID 0表示平台',
  `cpid` int DEFAULT NULL COMMENT '优惠券ID',
  `mid` int DEFAULT NULL COMMENT '会员ID',
  `title` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '标题',
  `price` decimal(11,2) DEFAULT NULL COMMENT '金额',
  `ordernum` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '订单号',
  `createtime` int DEFAULT NULL COMMENT '创建时间',
  `status` tinyint(1) DEFAULT '0' COMMENT '状态 0未支付1已支付',
  `payorderid` int DEFAULT NULL COMMENT '支付表id',
  `paytype` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '支付方式',
  `paytypeid` int DEFAULT NULL,
  `paynum` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '支付单号',
  `paytime` int DEFAULT NULL COMMENT '支付时间',
  `msg` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `platform` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `isfenhong` tinyint(1) DEFAULT '0' COMMENT '是否已分红 0未分红 1已分红',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `cpid` (`cpid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='优惠券购买订单';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_coupon_record`
--

DROP TABLE IF EXISTS `ddwx_coupon_record`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_coupon_record` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL COMMENT '账户ID',
  `bid` int DEFAULT '0' COMMENT '商户ID 0表示平台',
  `mid` int DEFAULT NULL COMMENT '会员ID',
  `couponid` int DEFAULT NULL COMMENT '优惠券ID',
  `couponname` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '优惠券名称',
  `money` float(11,2) DEFAULT '0.00' COMMENT '优惠金额',
  `discount` float(11,2) DEFAULT '0.00' COMMENT '折扣券折扣比例',
  `minprice` float(11,2) DEFAULT '0.00' COMMENT '最低消费金额',
  `starttime` int DEFAULT NULL COMMENT '开始时间',
  `endtime` int DEFAULT NULL COMMENT '到期时间',
  `usetime` int DEFAULT NULL COMMENT '使用时间',
  `createtime` int DEFAULT NULL COMMENT '领取时间',
  `status` tinyint(1) DEFAULT '0' COMMENT '状态0未使用1已使用',
  `type` tinyint(1) DEFAULT '1' COMMENT '1代金券2礼品券3计次券',
  `limit_count` int DEFAULT '10' COMMENT '计次券类型时 可用次数',
  `limit_perday` int DEFAULT '0' COMMENT '计次券类型时 每天可用次数',
  `used_count` int DEFAULT '0' COMMENT '计次券时使用次数',
  `code` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '核销码',
  `hexiaoqr` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '核销码图片url',
  `remark` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `from_mid` int DEFAULT NULL COMMENT '赠送人',
  `receive_time` int DEFAULT NULL,
  `canused_bids` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `canused_bcids` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `is_api_get` tinyint(1) DEFAULT '0' COMMENT '是否api领取',
  `api_params` varchar(120) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT 'api关键参数，json格式',
  `uplv_reward_item_id` bigint unsigned DEFAULT NULL COMMENT '会员升级奖励项ID',
  `uplv_item_dedup_key` char(64) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '会员升级发券幂等键SHA-256',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uniq_uplv_item_dedup_key` (`uplv_item_dedup_key`),
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE,
  KEY `couponid` (`couponid`) USING BTREE,
  KEY `idx_uplv_reward_item_id` (`uplv_reward_item_id`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='优惠券领取记录';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_cuxiao`
--

DROP TABLE IF EXISTS `ddwx_cuxiao`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_cuxiao` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `bid` int DEFAULT '0',
  `type` tinyint(1) DEFAULT '1' COMMENT '0仅显示 1满减 2满赠 3换购',
  `tip` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `starttime` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `endtime` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `sort` int DEFAULT '0',
  `createtime` int DEFAULT NULL,
  `gettj` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '-1',
  `fwtype` tinyint(1) DEFAULT '0',
  `categoryids` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `productids` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `minprice` decimal(10,2) DEFAULT NULL,
  `proid` int DEFAULT '0' COMMENT '赠送或换购的商品id',
  `ggid` int DEFAULT '0' COMMENT '赠送或换购的商品规格id',
  `money` float(11,2) DEFAULT '0.00' COMMENT '满减额度或加价金额',
  `minnum` int DEFAULT '1' COMMENT '最低购买件数',
  `zhekou` float(11,2) DEFAULT NULL COMMENT '折扣0.01~9.99',
  `prozk` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '单独设置的商品折扣',
  `pronum` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '单独设置的商品最低购买数量',
  `back_type` tinyint(1) DEFAULT '1' COMMENT '赠送类型：1-{:t("余额")}，2-{:t("佣金")}，3-{:t("积分")}，4-{:t("贡献值")}，5-{:t("黄积分")}，6-{:t("现金券")}',
  `back_price` decimal(10,2) DEFAULT NULL COMMENT '赠送金额/数量',
  `give_jifen` decimal(10,2) DEFAULT NULL COMMENT '满额赠送积分',
  `give_money` decimal(10,2) DEFAULT NULL COMMENT '满额赠送余额',
  `give_commission` decimal(10,2) DEFAULT NULL COMMENT '满额赠送佣金',
  `give_contribution` decimal(10,2) DEFAULT NULL COMMENT '满额赠送贡献值',
  `give_yellow_points` decimal(10,2) DEFAULT NULL COMMENT '满额赠送黄积分',
  `give_cash_coupon` decimal(10,2) DEFAULT NULL COMMENT '满额赠送现金券',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE,
  KEY `type` (`type`) USING BTREE,
  KEY `starttime` (`starttime`) USING BTREE,
  KEY `endtime` (`endtime`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_designer_login`
--

DROP TABLE IF EXISTS `ddwx_designer_login`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_designer_login` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `data` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `updatetime` int NOT NULL DEFAULT '0',
  `type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '类型',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_designer_menu`
--

DROP TABLE IF EXISTS `ddwx_designer_menu`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_designer_menu` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `indexurl` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '/m/main/index',
  `menucount` int DEFAULT NULL,
  `menudata` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `navigationBarBackgroundColor` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `navigationBarTextStyle` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `updatetime` int DEFAULT NULL,
  `platform` varchar(11) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT 'mp',
  `tongbu` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `platform` (`platform`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1089 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_designer_menu2`
--

DROP TABLE IF EXISTS `ddwx_designer_menu2`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_designer_menu2` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `bid` int DEFAULT '0',
  `indexurlname` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `indexurl` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `menudata` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `createtime` int DEFAULT NULL,
  `status` tinyint(1) DEFAULT '1',
  `platform` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `backgroundColor` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `menucount` int DEFAULT '4',
  `sort` int DEFAULT '0' COMMENT '排序值,越小越靠前',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE,
  KEY `indexurl` (`indexurl`) USING BTREE,
  KEY `platform` (`platform`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_designer_menu_business`
--

DROP TABLE IF EXISTS `ddwx_designer_menu_business`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_designer_menu_business` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `indexurl` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '/pages/business/index',
  `menucount` int DEFAULT NULL,
  `menudata` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `updatetime` int DEFAULT NULL,
  `platform` varchar(11) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT 'mp',
  `tongbu` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `platform` (`platform`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=121 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_designer_mobile`
--

DROP TABLE IF EXISTS `ddwx_designer_mobile`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_designer_mobile` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `data` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `updatetime` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_designer_page_popup`
--

DROP TABLE IF EXISTS `ddwx_designer_page_popup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_designer_page_popup` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `aid` int unsigned NOT NULL DEFAULT '0',
  `bid` int NOT NULL DEFAULT '0',
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `status` tinyint NOT NULL DEFAULT '1',
  `platform` varchar(20) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'all',
  `target_json` longtext COLLATE utf8mb4_general_ci,
  `design_json` longtext COLLATE utf8mb4_general_ci,
  `style_json` longtext COLLATE utf8mb4_general_ci,
  `audience_json` longtext COLLATE utf8mb4_general_ci,
  `frequency` varchar(30) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'always',
  `delay_seconds` int unsigned NOT NULL DEFAULT '0',
  `start_time` int unsigned NOT NULL DEFAULT '0',
  `end_time` int unsigned NOT NULL DEFAULT '0',
  `sort` int NOT NULL DEFAULT '0',
  `version` int unsigned NOT NULL DEFAULT '1',
  `source_type` varchar(40) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `source_id` int unsigned NOT NULL DEFAULT '0',
  `impression_count` bigint unsigned NOT NULL DEFAULT '0',
  `click_count` bigint unsigned NOT NULL DEFAULT '0',
  `close_count` bigint unsigned NOT NULL DEFAULT '0',
  `create_time` int unsigned NOT NULL DEFAULT '0',
  `update_time` int unsigned NOT NULL DEFAULT '0',
  `delete_time` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_aid_bid_status` (`aid`,`bid`,`status`,`sort`),
  KEY `idx_source` (`aid`,`source_type`,`source_id`),
  KEY `idx_schedule` (`aid`,`status`,`start_time`,`end_time`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='页面活动弹窗';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_designer_page_popup_daily_stat`
--

DROP TABLE IF EXISTS `ddwx_designer_page_popup_daily_stat`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_designer_page_popup_daily_stat` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `aid` int unsigned NOT NULL DEFAULT '0',
  `bid` int NOT NULL DEFAULT '0',
  `popup_id` int unsigned NOT NULL DEFAULT '0',
  `stat_day` int unsigned NOT NULL DEFAULT '0',
  `impressions` int unsigned NOT NULL DEFAULT '0',
  `clicks` int unsigned NOT NULL DEFAULT '0',
  `closes` int unsigned NOT NULL DEFAULT '0',
  `create_time` int unsigned NOT NULL DEFAULT '0',
  `update_time` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_popup_day` (`aid`,`bid`,`popup_id`,`stat_day`),
  KEY `idx_aid_day` (`aid`,`bid`,`stat_day`)
) ENGINE=InnoDB AUTO_INCREMENT=269 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='页面活动弹窗按日统计';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_designer_share`
--

DROP TABLE IF EXISTS `ddwx_designer_share`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_designer_share` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `bid` int DEFAULT '0',
  `indexurlname` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `indexurl` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `desc` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `pic` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `url` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  `status` tinyint(1) DEFAULT '1',
  `platform` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `is_rootpath` tinyint(1) DEFAULT '0' COMMENT '是否过滤参数 1页面根路径忽略参数匹配',
  `allow_friend` tinyint(1) NOT NULL DEFAULT '1' COMMENT '允许分享给好友 1允许 0禁止',
  `allow_timeline` tinyint(1) NOT NULL DEFAULT '1' COMMENT '允许分享到朋友圈 1允许 0禁止',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE,
  KEY `status` (`status`) USING BTREE,
  KEY `indexurl` (`indexurl`) USING BTREE,
  KEY `platform` (`platform`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=82 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_designer_shopdetail`
--

DROP TABLE IF EXISTS `ddwx_designer_shopdetail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_designer_shopdetail` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `bid` int DEFAULT '0',
  `indexurl` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '/pages/index/index',
  `menucount` int DEFAULT NULL,
  `menudata` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `navigationBarBackgroundColor` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `navigationBarTextStyle` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `updatetime` int DEFAULT NULL,
  `platform` varchar(11) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT 'mp',
  `tongbu` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `platform` (`platform`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='详情页导航栏调整';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_designer_splash`
--

DROP TABLE IF EXISTS `ddwx_designer_splash`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_designer_splash` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `aid` int NOT NULL COMMENT '账号ID',
  `bid` int NOT NULL DEFAULT '0' COMMENT '商户ID',
  `platform` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT 'mp' COMMENT '平台类型：mp-微信小程序，wx-微信公众号，h5-H5，app-APP，alipay-支付宝小程序，baidu-百度小程序，toutiao-头条小程序，qq-QQ小程序，all-全部',
  `is_enabled` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否启用：0-禁用，1-启用',
  `display_type` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT 'image' COMMENT '显示类型：image-图片，video-视频',
  `display_time` int NOT NULL DEFAULT '3' COMMENT '显示时间（秒）',
  `skip_enabled` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否允许跳过：0-不允许，1-允许',
  `skip_time` int NOT NULL DEFAULT '2' COMMENT '跳过时间（秒）',
  `image_url` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '开屏图片URL',
  `video_url` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '开屏视频URL',
  `link_url` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '点击链接URL',
  `link_name` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '链接名称',
  `createtime` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `updatetime` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  `display_mode` varchar(20) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'full' COMMENT 'full全屏 modal弹窗',
  `overlay_opacity` decimal(3,2) NOT NULL DEFAULT '0.00' COMMENT '遮罩透明度',
  `skip_position` varchar(20) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'top-right' COMMENT '跳过按钮位置',
  `image_fit` varchar(20) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'cover' COMMENT '图片适应方式',
  `video_muted` tinyint(1) NOT NULL DEFAULT '1' COMMENT '视频静音',
  `show_link_button` tinyint(1) NOT NULL DEFAULT '1' COMMENT '显示链接按钮',
  `show_frequency` varchar(20) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'once_session' COMMENT 'every每次 once_session启动一次 once_day每天一次',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE,
  KEY `platform` (`platform`) USING BTREE,
  KEY `is_enabled` (`is_enabled`) USING BTREE,
  KEY `display_type` (`display_type`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='开屏过度配置表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_designer_splash_statistics`
--

DROP TABLE IF EXISTS `ddwx_designer_splash_statistics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_designer_splash_statistics` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `aid` int NOT NULL COMMENT '账号ID',
  `bid` int NOT NULL DEFAULT '0' COMMENT '商户ID',
  `splash_id` int NOT NULL COMMENT '开屏配置ID',
  `platform` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL COMMENT '平台类型',
  `mid` int NOT NULL DEFAULT '0' COMMENT '用户ID',
  `action_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '动作类型：1-显示，2-点击，3-跳过',
  `display_time` int NOT NULL DEFAULT '0' COMMENT '实际显示时间（毫秒）',
  `ip` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT 'IP地址',
  `user_agent` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT 'User-Agent',
  `createtime` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `date` date DEFAULT NULL COMMENT '日期',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE,
  KEY `splash_id` (`splash_id`) USING BTREE,
  KEY `platform` (`platform`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE,
  KEY `action_type` (`action_type`) USING BTREE,
  KEY `date` (`date`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='开屏统计表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_designerpage`
--

DROP TABLE IF EXISTS `ddwx_designerpage`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_designerpage` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `bid` int DEFAULT '0',
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `ishome` int DEFAULT '0' COMMENT '1首页 2会员中心 9默认城市模板',
  `gid` int DEFAULT '0',
  `pageinfo` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `content` longtext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `createtime` int DEFAULT NULL,
  `updatetime` int DEFAULT NULL,
  `sort` int DEFAULT '0',
  `isarea` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '是否是区域模板',
  `template_source` int NOT NULL DEFAULT '0' COMMENT '模板来源ID：0原创 >0来自模板库',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE,
  KEY `ishome` (`ishome`) USING BTREE,
  KEY `designpage_isarea` (`isarea`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=626 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_designerpage_category`
--

DROP TABLE IF EXISTS `ddwx_designerpage_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_designerpage_category` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int NOT NULL DEFAULT '0',
  `bid` int NOT NULL DEFAULT '0',
  `name` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态 0：隐藏 1：启用',
  `sort` int NOT NULL DEFAULT '1',
  `type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '类型 0：普通 1:模板库',
  `is_del` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否删除 0：未删除 1：删除',
  `createtime` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_designerpage_order`
--

DROP TABLE IF EXISTS `ddwx_designerpage_order`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_designerpage_order` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `bid` int DEFAULT '0',
  `pageid` int DEFAULT NULL,
  `mid` int DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `price` decimal(11,2) DEFAULT NULL,
  `ordernum` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  `status` tinyint(1) DEFAULT '0',
  `payorderid` int DEFAULT NULL,
  `paytypeid` int DEFAULT NULL,
  `paytype` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `paynum` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `paytime` int DEFAULT NULL,
  `platform` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE,
  KEY `pageid` (`pageid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_designerpage_rwvideoad`
--

DROP TABLE IF EXISTS `ddwx_designerpage_rwvideoad`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_designerpage_rwvideoad` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `unitid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `givescore` int DEFAULT NULL,
  `givetimes` int DEFAULT '0',
  `createtime` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `unitid` (`unitid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_designerpage_rwvideoad_record`
--

DROP TABLE IF EXISTS `ddwx_designerpage_rwvideoad_record`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_designerpage_rwvideoad_record` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `mid` int DEFAULT NULL,
  `hid` int DEFAULT NULL,
  `givescore` int DEFAULT NULL,
  `givetimes` int DEFAULT '0',
  `createdate` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE,
  KEY `hid` (`hid`) USING BTREE,
  KEY `createdate` (`createdate`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_designerpage_tab`
--

DROP TABLE IF EXISTS `ddwx_designerpage_tab`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_designerpage_tab` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `bid` int NOT NULL DEFAULT '0' COMMENT '商户ID',
  `tabid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '0',
  `tabindexid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `content` longtext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `createtime` int DEFAULT NULL,
  `updatetime` int DEFAULT NULL,
  `area` int unsigned NOT NULL DEFAULT '0' COMMENT '模板所属的城市区域，需要用作查询条件',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `tabid` (`tabid`) USING BTREE,
  KEY `tabindexid` (`tabindexid`) USING BTREE,
  KEY `idx_aid_bid_tab` (`aid`,`bid`,`tabid`,`tabindexid`)
) ENGINE=InnoDB AUTO_INCREMENT=83 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_dict_data`
--

DROP TABLE IF EXISTS `ddwx_dict_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_dict_data` (
  `dict_code` bigint NOT NULL AUTO_INCREMENT COMMENT '字典编码',
  `dict_sort` int DEFAULT '0' COMMENT '字典排序',
  `dict_label` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '' COMMENT '字典标签',
  `dict_value` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '' COMMENT '字典键值',
  `dict_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '' COMMENT '字典类型',
  `status` char(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '0' COMMENT '状态（0正常 1停用）',
  `create_time` datetime DEFAULT NULL COMMENT '创建时间',
  `update_time` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`dict_code`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='字典数据表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_dict_type`
--

DROP TABLE IF EXISTS `ddwx_dict_type`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_dict_type` (
  `dict_id` bigint NOT NULL AUTO_INCREMENT COMMENT '字典主键',
  `dict_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '' COMMENT '字典名称',
  `dict_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '' COMMENT '字典类型',
  `status` char(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '0' COMMENT '状态（0正常 1停用）',
  `create_time` datetime DEFAULT NULL COMMENT '创建时间',
  `update_time` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`dict_id`) USING BTREE,
  UNIQUE KEY `dict_type` (`dict_type`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='字典类型表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_dividend_details`
--

DROP TABLE IF EXISTS `ddwx_dividend_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_dividend_details` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '自增主键',
  `member_id` int NOT NULL COMMENT '会员ID，关联member表',
  `dividend_amount` decimal(10,2) DEFAULT NULL COMMENT '分红金额',
  `dividend_time` int NOT NULL COMMENT '分红时间（时间戳）',
  `aid` int NOT NULL COMMENT '平台ID',
  `queue_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `deducted_contribution` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `pool_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_member_id` (`member_id`) USING BTREE,
  KEY `idx_aid` (`aid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=2023 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='分红明细表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_douyin_sysset`
--

DROP TABLE IF EXISTS `ddwx_douyin_sysset`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_douyin_sysset` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `app_id` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `app_secret` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `shop_id` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `mobile` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `status` tinyint(1) DEFAULT '0',
  `access_token` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `expires_time` int DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `aid` (`aid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_epay_config`
--

DROP TABLE IF EXISTS `ddwx_epay_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_epay_config` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int NOT NULL DEFAULT '0' COMMENT '站点aid',
  `platform` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '平台: mp/h5',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '总开关',
  `wxpay_status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '微信开关',
  `alipay_status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '支付宝开关',
  `apiurl` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '网关地址',
  `pid` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '商户PID',
  `platform_public_key` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '平台公钥',
  `merchant_private_key` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '商户私钥',
  `createtime` int NOT NULL DEFAULT '0',
  `updatetime` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_aid_platform` (`aid`,`platform`),
  KEY `idx_platform_status` (`platform`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='易支付配置';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_epay_log`
--

DROP TABLE IF EXISTS `ddwx_epay_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_epay_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int NOT NULL DEFAULT '0' COMMENT '站点aid',
  `payorder_id` int NOT NULL DEFAULT '0' COMMENT 'payorder.id',
  `ordernum` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '商户订单号',
  `platform` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '当前支付平台: mp/h5',
  `source_page` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '支付页路由',
  `pay_method` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT 'wxpay/alipay',
  `trade_no` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '第三方交易号',
  `return_tourl` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '支付成功回跳路径',
  `detailurl` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '订单详情路径',
  `request_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '发起支付请求参数',
  `notify_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '异步回调原始数据',
  `return_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '同步回调原始数据',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0待支付 1异步成功 2同步返回成功 -1失败',
  `createtime` int NOT NULL DEFAULT '0',
  `updatetime` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_ordernum` (`ordernum`),
  KEY `idx_aid_payorder` (`aid`,`payorder_id`),
  KEY `idx_trade_no` (`trade_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='易支付订单日志';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_express_address`
--

DROP TABLE IF EXISTS `ddwx_express_address`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_express_address` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `mid` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `company` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tel` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `province` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `city` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `district` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `area` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `address` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `latitude` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `longitude` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `isdefault` int DEFAULT '0',
  `createtime` int DEFAULT NULL,
  `mailtype` tinyint DEFAULT '1' COMMENT '1 为寄件 2 为收件',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE,
  KEY `isdefault` (`isdefault`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_fans`
--

DROP TABLE IF EXISTS `ddwx_fans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_fans` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `subscribe` int DEFAULT NULL,
  `openid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `unionid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `nickname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sex` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `city` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `country` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `province` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `language` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `headimgurl` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `subscribe_time` int DEFAULT NULL,
  `tag` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `messagetime` int DEFAULT NULL,
  `remark` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `subscribe_scene` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `qr_scene` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `qr_scene_str` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `doing` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `openid` (`openid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=124 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_fans_tag`
--

DROP TABLE IF EXISTS `ddwx_fans_tag`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_fans_tag` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tagid` int DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_fenhong_list`
--

DROP TABLE IF EXISTS `ddwx_fenhong_list`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_fenhong_list` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `aid` int NOT NULL COMMENT '商家ID',
  `level_ids` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '参与分红的会员等级ID，多个等级用逗号分隔',
  `fenhong_ratio` decimal(5,2) NOT NULL COMMENT '分红比例，例如10.00表示10%',
  `yeji_level_ids` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '业绩统计的会员等级ID，多个等级用逗号分隔',
  `yeji_calculation_method` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'sales' COMMENT '业绩统计方式：sales-按销售额，cost-按成本，profit-按利润',
  `createtime` int NOT NULL COMMENT '创建时间',
  `fenhong_title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `fenhong_rule` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'normal',
  `auto_token` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '自动分红链接标识',
  `pool_ratio` decimal(10,2) DEFAULT NULL COMMENT '奖金池百分比',
  `deduct_contribution` tinyint(1) DEFAULT '0' COMMENT '是否扣除贡献值 0-否 1-是',
  `contribution_ratio` decimal(10,2) DEFAULT '1.00' COMMENT '贡献值扣除比例,默认1:1',
  `clear_contribution_after_dividend` tinyint(1) NOT NULL DEFAULT '0' COMMENT '分红后是否清空剩余贡献值 0否1是',
  `dividend_to` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'commission' COMMENT '分红发放到：commission(佣金)、money(余额)、score(积分)、contribution(贡献值)',
  `member_ids` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '手动勾选参与分红的会员ID，多个ID用逗号分隔',
  `category_ids` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '商品分类ID，多个分类用逗号分隔，为空则不限制分类',
  `target_amount` decimal(10,2) DEFAULT NULL COMMENT '达标金额，会员在分红时间内需达到此金额才能参与分红',
  `target_calculation_method` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'sales' COMMENT '达标金额计算方式：sales-销售额，cost-成本，profit-利润',
  `auto_dividend_enabled` tinyint(1) DEFAULT '0' COMMENT '是否启用自动分红：0-关闭，1-启用',
  `dividend_cycle` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'weekly' COMMENT '分红周期：daily-每日，weekly-每周，monthly-每月，custom-自定义天数',
  `custom_days` int DEFAULT '7' COMMENT '自定义周期天数，默认7天',
  `execution_time` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '09:00' COMMENT '分红执行时间，格式HH:mm',
  `statistics_days` int DEFAULT '7' COMMENT '统计周期天数，统计最近多少天的订单',
  `include_maidan_flow` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否包含买单流水：0=不包含，1=包含',
  `maidan_flow_ratio` decimal(5,2) NOT NULL COMMENT '买单流水百分比：0.00-100.00',
  `last_execution_time` int DEFAULT '0' COMMENT '上次自动分红执行时间戳',
  `next_execution_time` int DEFAULT '0' COMMENT '下次自动分红执行时间戳',
  `reward_mode` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'direct' COMMENT '奖励模式：direct-直推人模式，node-接点人模式',
  `data_source` varchar(20) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'order' COMMENT '数据来源 order订单 zichanfenqi资产分期',
  `zichanfenqi_activity_id` int NOT NULL DEFAULT '0' COMMENT '资产分期活动ID，0表示全部活动',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_aid` (`aid`) USING BTREE,
  KEY `idx_reward_mode` (`reward_mode`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='月分红列表表，包含分红规则、达标金额、自动分红配置和奖励模式设置';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_freight`
--

DROP TABLE IF EXISTS `ddwx_freight`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_freight` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `bid` int DEFAULT '0',
  `pstype` tinyint(1) DEFAULT '0' COMMENT '0快递 1自提 2同城配送',
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `type` tinyint(1) DEFAULT '1' COMMENT '1按重量 2按件数',
  `pricedata` longtext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `storetype` tinyint(1) DEFAULT '0' COMMENT '自提门店 0全部 1选择',
  `storeids` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `status` int DEFAULT '0',
  `sort` int DEFAULT '0',
  `free_price` decimal(11,2) DEFAULT NULL,
  `freeset` tinyint(1) DEFAULT '0',
  `pstimeset` tinyint(1) DEFAULT '0' COMMENT '是否开启配送时间选择',
  `pstimedata` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '配送时间设置',
  `psprehour` int DEFAULT '4' COMMENT '选择配送时间y要大于当前时间多少小时',
  `createtime` int DEFAULT NULL,
  `peisong_juli1` float(11,1) DEFAULT '5.0',
  `peisong_fee1` float(11,2) DEFAULT '3.00',
  `peisong_juli2` float(11,1) DEFAULT NULL,
  `peisong_fee2` float(11,2) DEFAULT NULL,
  `peisong_lng` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `peisong_lat` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `peisong_range` int DEFAULT NULL,
  `field_list` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `pscontenttype` tinyint(1) DEFAULT '0',
  `pscontent` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `needlinkinfo` tinyint(1) DEFAULT '0',
  `minpriceset` tinyint(1) DEFAULT '0',
  `minprice` decimal(11,2) DEFAULT NULL,
  `fwprice` decimal(11,2) DEFAULT NULL,
  `peisong_rangetype` tinyint(1) DEFAULT '0',
  `peisong_rangepath` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `formdata` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `peisong_lng2` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `peisong_lat2` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE,
  KEY `pstype` (`pstype`) USING BTREE,
  KEY `type` (`type`) USING BTREE,
  KEY `status` (`status`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=884 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_freight_formdata`
--

DROP TABLE IF EXISTS `ddwx_freight_formdata`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_freight_formdata` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `type` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `orderid` int DEFAULT NULL,
  `form0` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form1` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form2` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form3` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form4` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form5` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form6` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form7` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form8` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form9` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form10` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form11` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form12` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form13` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form14` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form15` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form16` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form17` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form18` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form19` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form20` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form21` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form22` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form23` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form24` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form25` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form26` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form27` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form28` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form29` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form30` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `type` (`type`) USING BTREE,
  KEY `orderid` (`orderid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=3603 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_frontend_link_group`
--

DROP TABLE IF EXISTS `ddwx_frontend_link_group`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_frontend_link_group` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `aid` int NOT NULL DEFAULT '0',
  `source_type` varchar(20) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT 'core',
  `group_key` varchar(120) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT '',
  `group_name` varchar(120) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `sort` int NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `scene_json` text COLLATE utf8mb4_general_ci,
  `platform_json` text COLLATE utf8mb4_general_ci,
  `createtime` int NOT NULL DEFAULT '0',
  `updatetime` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_aid_status_sort` (`aid`,`status`,`sort`),
  KEY `idx_source_group` (`source_type`,`group_key`)
) ENGINE=InnoDB AUTO_INCREMENT=80 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=COMPACT;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_frontend_link_item`
--

DROP TABLE IF EXISTS `ddwx_frontend_link_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_frontend_link_item` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `aid` int NOT NULL DEFAULT '0',
  `group_id` int NOT NULL DEFAULT '0',
  `item_key` varchar(120) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT '',
  `name` varchar(120) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `path` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `description` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `scene_json` text COLLATE utf8mb4_general_ci,
  `platform_json` text COLLATE utf8mb4_general_ci,
  `sort` int NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `append_bid` tinyint(1) NOT NULL DEFAULT '0',
  `createtime` int NOT NULL DEFAULT '0',
  `updatetime` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_aid_group_sort` (`aid`,`group_id`,`sort`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=1030 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=COMPACT;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_guanggao_showlog`
--

DROP TABLE IF EXISTS `ddwx_guanggao_showlog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_guanggao_showlog` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `mid` int DEFAULT NULL,
  `pic` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  `platform` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_headimg_upload`
--

DROP TABLE IF EXISTS `ddwx_headimg_upload`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_headimg_upload` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `uid` int DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `dir` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `url` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `type` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `size` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `bsize` int DEFAULT NULL,
  `width` int DEFAULT NULL,
  `height` int DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  `platform` varchar(11) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT 'ht',
  `isdel` tinyint(1) DEFAULT '0',
  `gid` int DEFAULT '0',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `uid` (`uid`) USING BTREE,
  KEY `dir` (`dir`) USING BTREE,
  KEY `platform` (`platform`) USING BTREE,
  KEY `isdel` (`isdel`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_help`
--

DROP TABLE IF EXISTS `ddwx_help`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_help` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `cid` int DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `subname` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `pic` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `author` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `content` longtext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `readcount` int DEFAULT '0',
  `sort` int DEFAULT '0',
  `createtime` int DEFAULT NULL,
  `sendtime` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `status` int DEFAULT '1',
  `showname` tinyint(1) DEFAULT '1',
  `showsubname` tinyint(1) DEFAULT '1',
  `showreadcount` tinyint(1) DEFAULT '1',
  `showsendtime` tinyint(1) DEFAULT '1',
  `showauthor` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `cid` (`cid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_help_center_category`
--

DROP TABLE IF EXISTS `ddwx_help_center_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_help_center_category` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '分类ID',
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '分类名称',
  `type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '类型：1=通用帮助 2=设置进度',
  `menu_key` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '关联菜单模块（如shop,member等，多个用逗号分隔，为空表示所有商户可见）',
  `plugin_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '绑定插件名，空表示不限制插件',
  `icon` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '分类图标',
  `description` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '分类描述',
  `sort` int NOT NULL DEFAULT '0' COMMENT '排序（越大越靠前）',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态：1=显示 0=隐藏',
  `createtime` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `updatetime` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `type` (`type`) USING BTREE,
  KEY `status` (`status`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=166 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='帮助中心-分类表（控制台专用）';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_help_center_item`
--

DROP TABLE IF EXISTS `ddwx_help_center_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_help_center_item` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '项目ID',
  `category_id` int NOT NULL DEFAULT '0' COMMENT '所属分类ID',
  `menu_key` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '关联菜单模块（如shop,member等，多个用逗号分隔，为空则继承分类权限）',
  `plugin_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '绑定插件名，空表示不限制插件',
  `title` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '标题',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '描述内容',
  `tutorial_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '' COMMENT '教程链接（视频教程或外部文档URL）',
  `link_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '跳转链接',
  `link_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '链接类型：1=站内链接 2=外部链接 3=自动检测',
  `check_table` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '检测数据表',
  `check_field` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '检测字段（用于判断是否完成）',
  `check_condition` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '检测条件（JSON格式）',
  `check_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '检测类型：1=不检测 2=数据库检测 3=接口检测',
  `weight` int NOT NULL DEFAULT '10' COMMENT '权重（用于计算进度百分比）',
  `sort` int NOT NULL DEFAULT '0' COMMENT '排序（越大越靠前）',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态：1=启用 0=禁用',
  `createtime` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `updatetime` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `category_id` (`category_id`) USING BTREE,
  KEY `status` (`status`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=741 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='帮助中心-项目表（控制台专用）';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_help_center_progress`
--

DROP TABLE IF EXISTS `ddwx_help_center_progress`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_help_center_progress` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `aid` int NOT NULL DEFAULT '0' COMMENT '所属应用ID',
  `bid` int NOT NULL DEFAULT '0' COMMENT '所属商家ID',
  `item_id` int NOT NULL DEFAULT '0' COMMENT '项目ID',
  `user_id` int NOT NULL DEFAULT '0' COMMENT '用户ID（管理员ID）',
  `is_completed` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否完成：1=已完成 0=未完成',
  `completed_time` int NOT NULL DEFAULT '0' COMMENT '完成时间',
  `remark` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '备注',
  `createtime` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `updatetime` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `unique_item_user` (`aid`,`bid`,`item_id`,`user_id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE,
  KEY `item_id` (`item_id`) USING BTREE,
  KEY `user_id` (`user_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='帮助中心-进度记录表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_hexiao_order`
--

DROP TABLE IF EXISTS `ddwx_hexiao_order`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_hexiao_order` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `bid` int DEFAULT '0',
  `uid` int DEFAULT NULL,
  `mid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `mdid` int DEFAULT NULL,
  `orderid` int DEFAULT NULL,
  `ordernum` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  `remark` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `type` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `is_subordinate` tinyint(1) DEFAULT '0' COMMENT '是否下级订单核销:0=否,1=是',
  `operator_mid` int DEFAULT '0' COMMENT '操作者会员ID（用于下级订单核销）',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE,
  KEY `uid` (`uid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE,
  KEY `idx_is_subordinate` (`is_subordinate`) USING BTREE,
  KEY `idx_operator_mid` (`operator_mid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1414 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_iconsvg_history`
--

DROP TABLE IF EXISTS `ddwx_iconsvg_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_iconsvg_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `uid` int DEFAULT NULL,
  `iconid` int DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `show_svg` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `pngurl` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `uid` (`uid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=500 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_invoice`
--

DROP TABLE IF EXISTS `ddwx_invoice`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_invoice` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int unsigned NOT NULL DEFAULT '0',
  `bid` int unsigned DEFAULT '0',
  `order_type` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT 'shop' COMMENT '订单类型',
  `orderid` int NOT NULL DEFAULT '0',
  `ordernum` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL COMMENT '发票金额',
  `tax_rate` decimal(5,2) NOT NULL COMMENT '税率',
  `type` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '类型 1普票 2专票',
  `invoice_name` varchar(200) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL COMMENT '抬头',
  `name_type` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '抬头类型 1个人 2公司',
  `tax_no` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '税号',
  `address` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tel` varchar(30) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `bank_name` varchar(200) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `bank_account` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '状态 0待审核 1通过 2驳回',
  `check_remark` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '审核备注',
  `create_time` int DEFAULT NULL,
  `check_time` int DEFAULT NULL,
  `mobile` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `email` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `status` (`status`) USING BTREE,
  KEY `type` (`type`) USING BTREE,
  KEY `name_type` (`name_type`) USING BTREE,
  KEY `invoice_name` (`invoice_name`) USING BTREE,
  KEY `tax_no` (`tax_no`) USING BTREE,
  KEY `orderid` (`orderid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_jiedian_reward_statistics`
--

DROP TABLE IF EXISTS `ddwx_jiedian_reward_statistics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_jiedian_reward_statistics` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `aid` int NOT NULL DEFAULT '0' COMMENT '商户ID',
  `member_id` int NOT NULL DEFAULT '0' COMMENT '用户ID',
  `total_reward_amount` decimal(12,2) NOT NULL COMMENT '累计奖励金额',
  `total_reward_count` int NOT NULL DEFAULT '0' COMMENT '累计奖励次数',
  `this_month_amount` decimal(10,2) NOT NULL COMMENT '本月奖励金额',
  `this_month_count` int NOT NULL DEFAULT '0' COMMENT '本月奖励次数',
  `last_reward_time` int NOT NULL DEFAULT '0' COMMENT '最后奖励时间',
  `created_time` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `updated_time` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `idx_aid_member` (`aid`,`member_id`) USING BTREE COMMENT '商户用户唯一索引',
  KEY `idx_total_amount` (`total_reward_amount`) USING BTREE COMMENT '累计金额索引'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='接点奖励统计表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_jobs`
--

DROP TABLE IF EXISTS `ddwx_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_jobs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '自增主键',
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '队列名称',
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '有效负载',
  `attempts` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '重试次数',
  `reserved` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '订阅次数',
  `reserve_time` int unsigned DEFAULT '0' COMMENT '订阅时间',
  `available_time` int unsigned DEFAULT '0' COMMENT '有效时间',
  `create_time` int unsigned DEFAULT '0' COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='消息队列';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_kefu_message`
--

DROP TABLE IF EXISTS `ddwx_kefu_message`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_kefu_message` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `bid` int DEFAULT '0',
  `mid` int DEFAULT NULL,
  `uid` int DEFAULT '0',
  `nickname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `headimg` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `unickname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `uheadimg` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tel` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `msgtype` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `content` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `mediaid` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  `isreply` tinyint(1) DEFAULT '0' COMMENT '0 客户发的 1 客服回复的',
  `isread` tinyint(1) DEFAULT '0',
  `platform` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT 'mp',
  `iswx` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE,
  KEY `uid` (`uid`) USING BTREE,
  KEY `createtime` (`createtime`) USING BTREE,
  KEY `isread` (`isread`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=422 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_largearea`
--

DROP TABLE IF EXISTS `ddwx_largearea`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_largearea` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `province` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `status` int DEFAULT '1',
  `sort` int DEFAULT '1',
  `createtime` int DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_maidan_order`
--

DROP TABLE IF EXISTS `ddwx_maidan_order`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_maidan_order` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `bid` int DEFAULT '0',
  `mid` int DEFAULT NULL,
  `ordernum` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `money` float(11,2) DEFAULT NULL,
  `paymoney` float(11,2) DEFAULT NULL,
  `disprice` float(11,2) DEFAULT '0.00' COMMENT '会员折扣优惠',
  `scoredk` float(11,2) DEFAULT '0.00',
  `decscore` int DEFAULT '0',
  `decmoney` float(11,2) DEFAULT '0.00' COMMENT '付款码付款时 余额抵扣',
  `couponrid` int DEFAULT NULL,
  `couponmoney` float(11,2) DEFAULT '0.00',
  `moneydkmoney` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '余额抵扣金额',
  `createtime` int DEFAULT NULL,
  `platform` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `status` tinyint(1) DEFAULT '0',
  `payorderid` int DEFAULT NULL,
  `paytypeid` int DEFAULT NULL,
  `paytype` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `paynum` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `paytime` int DEFAULT NULL,
  `mdid` int DEFAULT '0' COMMENT '门店id',
  `uid` int DEFAULT '0',
  `remark` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '',
  `refund_money` float(10,2) DEFAULT '0.00',
  `printdaynum` int NOT NULL DEFAULT '0' COMMENT '日单号',
  `paidui_jiang` decimal(10,2) NOT NULL DEFAULT '0.00',
  `paidui_give` decimal(10,2) NOT NULL DEFAULT '0.00',
  `tuozhanid` int DEFAULT '0',
  `tuozhanfei` decimal(11,2) DEFAULT NULL,
  `tuozhanid_jian` int DEFAULT '0',
  `tuozhanfei_jian` decimal(11,2) DEFAULT NULL,
  `tuozhanid_jianjian` int DEFAULT '0',
  `tuozhanfei_jianjian` decimal(11,2) DEFAULT NULL,
  `tuozhanfei_status` tinyint(1) DEFAULT '0',
  `peisong_order_id` int NOT NULL DEFAULT '0' COMMENT '配送订单id',
  `shoporderid` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `isfenhong` tinyint(1) DEFAULT '0' COMMENT '是否已分红 0未分红 1已分红',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE,
  KEY `mdid` (`mdid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=438 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_manjian_set`
--

DROP TABLE IF EXISTS `ddwx_manjian_set`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_manjian_set` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `mjdata` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `status` tinyint(1) DEFAULT '0',
  `createtime` int DEFAULT NULL,
  `fwtype` tinyint(1) DEFAULT '0',
  `categoryids` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `productids` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `total_status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '总消费额满减 0：不开启 1：开启',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `aid` (`aid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_manual_order`
--

DROP TABLE IF EXISTS `ddwx_manual_order`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_manual_order` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int NOT NULL DEFAULT '0' COMMENT '商户ID',
  `mid` int NOT NULL DEFAULT '0' COMMENT '下单用户ID',
  `product_id` int NOT NULL DEFAULT '0' COMMENT '产品ID（可选）',
  `product_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '产品名称',
  `order_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '订单金额',
  `remark` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '备注',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态 1=正常 0=已取消',
  `admin_id` int NOT NULL DEFAULT '0' COMMENT '操作管理员ID',
  `createtime` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `updatetime` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_aid` (`aid`),
  KEY `idx_mid` (`mid`),
  KEY `idx_createtime` (`createtime`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='手动订单记录表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_manual_order_commission`
--

DROP TABLE IF EXISTS `ddwx_manual_order_commission`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_manual_order_commission` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int NOT NULL DEFAULT '0' COMMENT '商户ID',
  `order_id` int NOT NULL DEFAULT '0' COMMENT '手动订单ID',
  `mid` int NOT NULL DEFAULT '0' COMMENT '下单用户ID',
  `to_mid` int NOT NULL DEFAULT '0' COMMENT '获得佣金的用户ID',
  `level` tinyint NOT NULL DEFAULT '1' COMMENT '上级层级 1=一级 2=二级 3=三级',
  `order_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '订单金额',
  `commission_percent` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT '佣金比例（%）',
  `commission_total` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '预计佣金总额',
  `commission_immediate` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '直接到账佣金',
  `commission_frozen` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '冻结佣金',
  `frozen_status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '冻结状态 0=无冻结 1=冻结中 2=已解冻',
  `immediate_paid` tinyint(1) NOT NULL DEFAULT '0' COMMENT '直接佣金是否已发放 0=否 1=是',
  `frozen_paid` tinyint(1) NOT NULL DEFAULT '0' COMMENT '冻结佣金是否已发放 0=否 1=是',
  `unfreeze_time` int NOT NULL DEFAULT '0' COMMENT '解冻时间',
  `unfreeze_admin_id` int NOT NULL DEFAULT '0' COMMENT '解冻操作管理员ID',
  `remark` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '备注',
  `createtime` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `updatetime` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_aid` (`aid`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_mid` (`mid`),
  KEY `idx_to_mid` (`to_mid`),
  KEY `idx_frozen_status` (`frozen_status`),
  KEY `idx_createtime` (`createtime`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='手动订单佣金分配表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member`
--

DROP TABLE IF EXISTS `ddwx_member`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL COMMENT '账户ID',
  `wxopenid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '小程序openid',
  `mpopenid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '公众号openid',
  `appopenid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT 'APP的授权登录openid',
  `unionid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '开放平台unionid',
  `alipayopenid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '支付宝小程序openid',
  `baiduopenid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '百度小程序openid',
  `qqopenid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT 'qq小程序openid',
  `toutiaoopenid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '头条小城openid',
  `iosopenid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `googleopenid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `pid` int DEFAULT '0' COMMENT '上级ID',
  `jiedianpid` int NOT NULL DEFAULT '0',
  `path` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '所有上级ID数据',
  `levelid` int DEFAULT '0' COMMENT '会员等级ID',
  `yunkucun_global_bonus_weight` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '云仓全球分红个人权重(权)',
  `money` decimal(15,5) NOT NULL COMMENT '余额（支持5位小数）',
  `s_money` varchar(4096) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '各个分支商店的余额情况',
  `totalcommission` decimal(11,2) DEFAULT NULL COMMENT '总计获得的佣金',
  `totalcommission2` decimal(11,2) DEFAULT NULL,
  `commission2` decimal(11,2) DEFAULT NULL,
  `commission` decimal(11,2) DEFAULT NULL COMMENT '佣金余额',
  `total_fenhong` decimal(11,2) NOT NULL COMMENT '总分红',
  `total_fenhong_team` decimal(11,2) NOT NULL,
  `total_fenhong_level_team` decimal(11,2) NOT NULL,
  `total_fenhong_partner` decimal(11,2) NOT NULL COMMENT '股东分红',
  `total_fenhong_area` decimal(11,2) NOT NULL,
  `score` decimal(11,2) DEFAULT NULL COMMENT '积分',
  `scorehuang` decimal(11,2) NOT NULL COMMENT '黄积分',
  `hgb_balance` decimal(20,8) NOT NULL DEFAULT '0.00000000' COMMENT 'HGB余额',
  `sgb_balance` decimal(20,8) NOT NULL DEFAULT '0.00000000' COMMENT 'SGB待释放余额',
  `scorexiaofeizhi` decimal(10,2) DEFAULT NULL,
  `scorelv` decimal(10,2) DEFAULT NULL,
  `score2` decimal(11,2) DEFAULT NULL COMMENT '分红积分',
  `scoreA` decimal(10,2) NOT NULL,
  `scoreB` decimal(10,2) NOT NULL,
  `duihuanfen` decimal(11,2) NOT NULL,
  `score_withdraw` int NOT NULL DEFAULT '0',
  `nickname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '昵称',
  `headimg` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '头像',
  `sex` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '性别',
  `realname` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '姓名',
  `tel` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '手机号',
  `pwd` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '登录密码md5',
  `usercard` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '身份证号',
  `weixin` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '微信号',
  `aliaccount` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '支付宝账号',
  `aliaccountname` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '支付宝姓名',
  `country` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '国家',
  `province` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '省份',
  `city` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '城市',
  `area` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '区域',
  `address` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '地址',
  `birthday` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '生日',
  `bankcardnum` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '银行卡号',
  `bankname` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '银行名称',
  `bankaddress` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '所属分支行',
  `bankcarduser` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '银行卡开户人',
  `card_id` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '会员卡ID',
  `card_code` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '会员卡号',
  `activate_ticket` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `qrcode` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `sharepic` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `shareMediaId` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  `last_visittime` int DEFAULT NULL,
  `signdate` varchar(30) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '当日签到日期',
  `signtime` int DEFAULT NULL,
  `signtimes` int DEFAULT '0' COMMENT '签到次数',
  `signtimeslx` int DEFAULT '0' COMMENT '连续签到次数',
  `session_id` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `session_key` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `paypwd` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '支付密码',
  `platform` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '来源平台 mp公众号 wx小程序',
  `subscribe` tinyint(1) DEFAULT '0' COMMENT '是否关注',
  `subscribe_time` int DEFAULT NULL COMMENT '关注时间',
  `levelstarttime` int DEFAULT '0',
  `levelendtime` int DEFAULT '0',
  `remark` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '备注',
  `random_str` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '',
  `bid` int DEFAULT '0',
  `laxin_time` int DEFAULT '0' COMMENT '最后拉新时间',
  `areafenhong_province` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `areafenhong_city` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `areafenhong_area` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `areafenhong_largearea` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `areafenhong` tinyint(1) DEFAULT '0' COMMENT '区域代理 0跟随会员等级设置 1省级 2市级 3区县 4非区域代理',
  `areafenhongbl` decimal(10,2) DEFAULT NULL,
  `areafenhong_money` decimal(10,2) DEFAULT NULL COMMENT '区域代理固定分红金额（元/单）',
  `areafenhong_type` tinyint DEFAULT '0' COMMENT '区域代理分红类型（0=按比例，1=按固定金额）',
  `ktnum` tinyint(1) DEFAULT '0' COMMENT '开团次数限制',
  `is_wanshan_score` tinyint(1) DEFAULT '0',
  `yqcode` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '邀请码',
  `checkst` tinyint(1) DEFAULT '1' COMMENT '是否已审核',
  `checkreason` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '审核备注',
  `isfreeze` tinyint(1) DEFAULT '0',
  `is_blacklist` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否黑名单:0=否,1=是',
  `islock` tinyint(1) DEFAULT '0',
  `huidong_sync` tinyint(1) DEFAULT '0' COMMENT '0未处理，1已处理，-1无需处理',
  `huidong_mid` varchar(60) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `is_yh` int NOT NULL DEFAULT '0' COMMENT '用户的优惠是否用完',
  `kuang_num` int NOT NULL DEFAULT '0' COMMENT '店内框数量',
  `bus_total` int DEFAULT '999999999' COMMENT '创业值',
  `cyz` decimal(11,2) NOT NULL,
  `heiscore` decimal(15,5) NOT NULL COMMENT '现金券（支持5位小数）',
  `xiaofeib` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '消费币',
  `is_fenhong` tinyint(1) DEFAULT '1' COMMENT '0 不进行分红 1进行分红',
  `fenhong_num` int DEFAULT '0' COMMENT '分红点数',
  `contribution_num` decimal(15,5) NOT NULL COMMENT '贡献值（支持5位小数）',
  `cpcn_user_id` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '会员中金支付UserID',
  `cpcn_merchant_id` varchar(64) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '中金商户号',
  `guquan` decimal(10,2) DEFAULT NULL,
  `fanxian_nums` decimal(10,2) DEFAULT NULL COMMENT '返现值总金额记录',
  `xingji` tinyint(1) NOT NULL DEFAULT '0',
  `suo1` int NOT NULL DEFAULT '0',
  `suo2` int NOT NULL DEFAULT '0',
  `suo1jiang` decimal(10,2) NOT NULL,
  `suo2jiang` decimal(11,2) NOT NULL,
  `gongpaipid` int NOT NULL DEFAULT '0',
  `gongpainum` int NOT NULL DEFAULT '0',
  `gongpaitime` int DEFAULT '0',
  `gongpaipid2` int NOT NULL DEFAULT '0',
  `gongpainum2` tinyint(1) NOT NULL DEFAULT '0',
  `gongpaitime2` int NOT NULL DEFAULT '0',
  `gongpaipid3` int NOT NULL DEFAULT '0',
  `gongpainum3` tinyint(1) NOT NULL DEFAULT '0',
  `gongpaitime3` int NOT NULL DEFAULT '0',
  `gongpaipid4` int NOT NULL DEFAULT '0',
  `gongpainum4` tinyint(1) NOT NULL DEFAULT '0',
  `gongpaitime4` int NOT NULL DEFAULT '0',
  `gongpaipid5` int NOT NULL DEFAULT '0',
  `gongpainum5` tinyint(1) NOT NULL DEFAULT '0',
  `gongpaitime5` int NOT NULL DEFAULT '0',
  `gongpaipid6` int NOT NULL DEFAULT '0',
  `gongpainum6` tinyint(1) NOT NULL DEFAULT '0',
  `gongpaitime6` int NOT NULL DEFAULT '0',
  `gongpaipid7` int NOT NULL DEFAULT '0',
  `gongpainum7` tinyint(1) NOT NULL DEFAULT '0',
  `gongpaitime7` int NOT NULL DEFAULT '0',
  `isxuni` tinyint(1) NOT NULL DEFAULT '0',
  `ypid` int NOT NULL DEFAULT '0',
  `gongpaistatus` tinyint(1) NOT NULL DEFAULT '0',
  `gongpaistatus2` tinyint(1) NOT NULL DEFAULT '0',
  `gongpaistatus3` tinyint(1) NOT NULL DEFAULT '0',
  `gongpaistatus4` tinyint(1) NOT NULL DEFAULT '0',
  `gongpaistatus5` tinyint(1) NOT NULL DEFAULT '0',
  `gongpaistatus6` tinyint(1) NOT NULL DEFAULT '0',
  `gongpaistatus7` tinyint(1) NOT NULL DEFAULT '0',
  `jiangcount` decimal(10,2) NOT NULL,
  `gongpainum8` int NOT NULL DEFAULT '0',
  `gongpaipid8` int NOT NULL DEFAULT '0',
  `gongpaitime8` int NOT NULL DEFAULT '0',
  `gongpaistatus8` tinyint(1) NOT NULL DEFAULT '0',
  `zijifh` decimal(10,2) NOT NULL,
  `fxfhc` decimal(11,2) NOT NULL,
  `syc` decimal(11,2) NOT NULL,
  `score3` decimal(15,5) NOT NULL COMMENT '排队返现积分（支持5位小数）',
  `ytpid` int NOT NULL DEFAULT '0',
  `ytpath` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `tuo1` tinyint NOT NULL DEFAULT '0',
  `tuo2` tinyint(1) NOT NULL DEFAULT '0',
  `dongjienum` decimal(10,2) DEFAULT NULL,
  `dong_status` tinyint(1) NOT NULL DEFAULT '0',
  `dong_time` int DEFAULT NULL,
  `tuozhanfei` decimal(10,5) NOT NULL,
  `ylevelid` int DEFAULT '0',
  `tuozhanedu` decimal(11,2) DEFAULT NULL,
  `liandongren` int DEFAULT '0',
  `yliandongren` int DEFAULT '0',
  `shengjiscore` decimal(10,2) DEFAULT NULL,
  `wximg` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `zfbimg` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `face_verified` int NOT NULL DEFAULT '0',
  `agreement_signed` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `zi` tinyint DEFAULT NULL,
  `yonghujianjie` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `user_type` tinyint(1) DEFAULT '0' COMMENT '用户类型，0: 现结用户, 1: 月结用户',
  `arrears` decimal(10,2) DEFAULT NULL COMMENT '欠费金额',
  `credit_limit` decimal(10,2) DEFAULT NULL COMMENT '月结用户最大欠费金额',
  `usd` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `jishoupeizi` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '0',
  `qiangtime` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '0',
  `epjiaoyi` varchar(200) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `msqiangtime` int NOT NULL DEFAULT '0' COMMENT '秒杀提前抢购时间(分钟)',
  `credit_score` decimal(10,2) NOT NULL DEFAULT '100.00' COMMENT '信用分',
  `temp_frozen_money` decimal(10,2) NOT NULL COMMENT '临时冻结余额',
  `corridor` int DEFAULT NULL COMMENT '通道ID',
  `wallet_address` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '钱包地址',
  `wallet_receiving_code` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '钱包收款码',
  `ip_address` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT 'IP地址',
  `ip_last_update` int DEFAULT NULL COMMENT 'IP地址最后更新时间',
  `address_edit_count` int unsigned NOT NULL DEFAULT '0' COMMENT '地址编辑次数',
  `address_confirmed` tinyint(1) NOT NULL DEFAULT '0' COMMENT '地址确认状态(0=未确认,1=已确认)',
  `tags` varchar(500) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '会员标签ID列表，逗号分隔',
  `form_record_id` int DEFAULT NULL COMMENT '自定义表单记录ID',
  `alipay_openid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '' COMMENT '支付宝openid',
  `contribution` decimal(10,2) DEFAULT NULL COMMENT '贡献值',
  `score_huangjin` decimal(10,2) DEFAULT NULL COMMENT '黄积分',
  `max_single_reward` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '单笔最大奖励累计金额',
  `team_reward_balance` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '团队限额余额',
  `source_id` int DEFAULT '0' COMMENT '会员来源ID',
  `source_name` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '会员来源名称',
  `register_cashier_id` int DEFAULT '0' COMMENT '注册收银台ID',
  `register_uid` int DEFAULT '0' COMMENT '注册操作员ID',
  `shezhen_group_id` int NOT NULL DEFAULT '0' COMMENT '舌诊检测分组ID',
  `comprehensive_customer_limit_extra` int NOT NULL DEFAULT '0' COMMENT '综合诊疗可建客户数追加',
  `comprehensive_detect_times_total_extra` int NOT NULL DEFAULT '0' COMMENT '综合诊疗可检测次数追加',
  `comprehensive_detect_times_used` int NOT NULL DEFAULT '0' COMMENT '综合诊疗已用检测次数(从0开始)',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `mpopenid_tel` (`mpopenid`,`tel`,`aid`) USING BTREE,
  UNIQUE KEY `wxopenid_tel` (`wxopenid`,`tel`,`aid`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `mpopenid` (`mpopenid`) USING BTREE,
  KEY `pid` (`pid`) USING BTREE,
  KEY `wxopenid` (`wxopenid`) USING BTREE,
  KEY `unionid` (`unionid`) USING BTREE,
  KEY `createtime` (`createtime`) USING BTREE,
  KEY `session_id` (`session_id`) USING BTREE,
  KEY `huidong_sync` (`huidong_sync`) USING BTREE,
  KEY `huidong_mid` (`huidong_mid`) USING BTREE,
  KEY `last_visittime` (`last_visittime`) USING BTREE,
  KEY `idx_tags` (`tags`(255)) USING BTREE,
  KEY `idx_form_record_id` (`form_record_id`) USING BTREE,
  KEY `idx_mpopenid` (`aid`,`mpopenid`) USING BTREE,
  KEY `idx_alipay_openid` (`aid`,`alipay_openid`) USING BTREE,
  KEY `idx_aid_pid_levelid` (`aid`,`pid`,`levelid`) USING BTREE,
  KEY `idx_aid_levelid_path` (`aid`,`levelid`,`path`(100)) USING BTREE,
  KEY `idx_path` (`path`(200)) USING BTREE,
  KEY `idx_member_areafenhong_type` (`areafenhong_type`,`areafenhongbl`) USING BTREE,
  KEY `idx_is_blacklist` (`is_blacklist`) USING BTREE,
  KEY `idx_source_id` (`source_id`) USING BTREE,
  KEY `idx_session_id` (`aid`,`session_id`(254)),
  KEY `idx_aid_jiedianpid_levelid` (`aid`,`jiedianpid`,`levelid`)
) ENGINE=InnoDB AUTO_INCREMENT=15727 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='会员表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_address`
--

DROP TABLE IF EXISTS `ddwx_member_address`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_address` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `mid` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `company` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tel` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `province` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `city` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `district` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `street_id` int NOT NULL DEFAULT '0' COMMENT '街道ID',
  `street` varchar(100) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '街道名称',
  `area` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `address` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `latitude` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `longitude` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `isdefault` int DEFAULT '0',
  `createtime` int DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE,
  KEY `isdefault` (`isdefault`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=5136 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_bustotallog`
--

DROP TABLE IF EXISTS `ddwx_member_bustotallog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_bustotallog` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `mid` int DEFAULT NULL,
  `frommid` int DEFAULT NULL,
  `busTotal` int DEFAULT NULL,
  `after` decimal(11,0) DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_commission_corridor`
--

DROP TABLE IF EXISTS `ddwx_member_commission_corridor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_commission_corridor` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '',
  `status` tinyint DEFAULT '0' COMMENT '状态：0->未启用, 1->启用',
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '' COMMENT '通道描述',
  `sort` int DEFAULT '0' COMMENT '排序',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='通道表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_commission_order`
--

DROP TABLE IF EXISTS `ddwx_member_commission_order`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_commission_order` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT '0',
  `mid` int DEFAULT '0' COMMENT '卖家',
  `from_mid` int DEFAULT '0' COMMENT '买家',
  `order_num` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '' COMMENT '订单号',
  `parent_order_id` int DEFAULT '0',
  `commission` decimal(10,2) DEFAULT NULL COMMENT '现金',
  `total_commission` decimal(10,2) DEFAULT NULL COMMENT '总现金',
  `lock_commission` decimal(10,2) DEFAULT NULL COMMENT '锁定现金',
  `amount` decimal(10,2) DEFAULT NULL COMMENT '金额',
  `total_amount` decimal(10,2) DEFAULT NULL COMMENT '总金额',
  `ratio` decimal(10,2) DEFAULT NULL COMMENT '兑率',
  `buy_voucher` varchar(2000) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '' COMMENT '付款凭证',
  `buy_remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '' COMMENT '买家留言',
  `sale_remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '' COMMENT '卖家回复',
  `status` tinyint DEFAULT '0' COMMENT '状态1： 0->在售, 1->交易, 2->完成, 3->关闭\r\n状态2： 0->待审核, 1->成交, 2->不通过, 3->关闭',
  `createtime` int DEFAULT '0',
  `asset_type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'commission' COMMENT '资产类型:money余额,score积分,commission佣金,heiscore现金券,contribution贡献值,score_huangjin黄积分',
  `price` decimal(10,2) DEFAULT NULL COMMENT '售卖价格',
  `total_price` decimal(10,2) DEFAULT NULL COMMENT '总价格',
  `fee` decimal(10,2) DEFAULT NULL COMMENT '手续费',
  `fee_rate` decimal(10,2) DEFAULT NULL COMMENT '手续费率',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=MyISAM AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='现金交易订单表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_commission_record`
--

DROP TABLE IF EXISTS `ddwx_member_commission_record`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_commission_record` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `mid` int DEFAULT NULL,
  `frommid` int DEFAULT NULL,
  `orderid` int DEFAULT NULL,
  `ogid` int DEFAULT NULL,
  `type` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT 'shop' COMMENT 'shop 商城',
  `commission` decimal(11,2) DEFAULT NULL,
  `score` int DEFAULT '0',
  `remark` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  `endtime` int DEFAULT NULL,
  `status` tinyint(1) DEFAULT '0',
  `fenhong` decimal(10,2) NOT NULL DEFAULT '0.00',
  `jl_type` tinyint(1) DEFAULT '1' COMMENT '1 正常佣金 2 分红3静态分佣',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE,
  KEY `idx_aid_mid_type_status` (`aid`,`mid`,`type`,`status`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=10239 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_commission_sale_settings`
--

DROP TABLE IF EXISTS `ddwx_member_commission_sale_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_commission_sale_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL COMMENT '账户ID',
  `enable_money` tinyint(1) DEFAULT '0' COMMENT '启用余额售卖',
  `enable_score` tinyint(1) DEFAULT '0' COMMENT '启用积分售卖',
  `enable_commission` tinyint(1) DEFAULT '1' COMMENT '启用佣金售卖',
  `enable_heiscore` tinyint(1) DEFAULT '0' COMMENT '启用现金券售卖',
  `enable_contribution` tinyint(1) DEFAULT '0' COMMENT '启用贡献值售卖',
  `enable_score_huangjin` tinyint(1) DEFAULT '0' COMMENT '启用黄积分售卖',
  `sale_fee` decimal(11,2) DEFAULT NULL COMMENT '售卖手续费比例',
  `min_discount` decimal(11,2) DEFAULT '0.50' COMMENT '最低折扣率',
  `max_discount` decimal(11,2) DEFAULT '1.00' COMMENT '最高折扣率',
  `sale_status` tinyint(1) DEFAULT '1' COMMENT '售卖功能状态',
  `buy_status` tinyint(1) DEFAULT '1' COMMENT '购买功能状态',
  `createtime` int DEFAULT NULL COMMENT '创建时间',
  `updatetime` int DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='售卖设置表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_commission_withdrawlog`
--

DROP TABLE IF EXISTS `ddwx_member_commission_withdrawlog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_commission_withdrawlog` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `mid` int DEFAULT NULL,
  `money` decimal(11,2) DEFAULT NULL,
  `txmoney` decimal(11,2) DEFAULT NULL,
  `aliaccount` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `wximg` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '微信收款码快照',
  `zfbimg` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '支付宝收款码快照',
  `aliaccountname` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '支付宝姓名',
  `ordernum` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `paytype` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `status` tinyint(1) DEFAULT '0',
  `createtime` int DEFAULT NULL,
  `bankname` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `bankcarduser` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `bankcardnum` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `paytime` int DEFAULT NULL,
  `paynum` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `platform` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT 'wx' COMMENT 'wx小程序 m公众号网页',
  `reason` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `transfer_package` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '转账package数据',
  `transfer_mch_id` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '转账商户号',
  `transfer_app_id` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '转账AppID',
  `wx_package_info` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `wx_transfer_bill_no` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `wx_transfer_msg` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `wx_state` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE,
  KEY `status` (`status`) USING BTREE,
  KEY `createtime` (`createtime`) USING BTREE,
  KEY `idx_aid_mid_status` (`aid`,`mid`,`status`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=935 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_commissionlog`
--

DROP TABLE IF EXISTS `ddwx_member_commissionlog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_commissionlog` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `mid` int DEFAULT NULL,
  `frommid` int DEFAULT NULL,
  `commission` decimal(11,2) DEFAULT NULL,
  `after` decimal(11,2) DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `fhtype` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '分红类型',
  `service_fee` decimal(17,6) DEFAULT NULL,
  `uid` int DEFAULT '0',
  `fhid` int DEFAULT '0' COMMENT '来源分红数据id',
  `isgongpaitixian` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=39537 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_duihuanfenlog`
--

DROP TABLE IF EXISTS `ddwx_member_duihuanfenlog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_duihuanfenlog` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `aid` int NOT NULL,
  `mid` int NOT NULL,
  `money` decimal(11,2) NOT NULL,
  `after` decimal(10,2) NOT NULL,
  `createtime` int NOT NULL,
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=419 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_favorite`
--

DROP TABLE IF EXISTS `ddwx_member_favorite`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_favorite` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `mid` int DEFAULT NULL,
  `proid` int DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  `type` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE,
  KEY `proid` (`proid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=190 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_fenhonglog`
--

DROP TABLE IF EXISTS `ddwx_member_fenhonglog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_fenhonglog` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `mid` int DEFAULT NULL,
  `commission` decimal(11,2) DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  `remark` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `type` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '',
  `module` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT 'shop',
  `ogids` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `send_commission` decimal(12,2) DEFAULT NULL COMMENT '发放到佣金的数量',
  `send_money` decimal(12,2) DEFAULT NULL COMMENT '发放到余额的数量',
  `send_score` decimal(12,2) DEFAULT NULL COMMENT '发放到积分的数量',
  `send_fuchi` decimal(12,2) DEFAULT NULL COMMENT '发放到扶持金的数量',
  `frommid` int DEFAULT '0' COMMENT '来源会员id',
  `levelid` int DEFAULT '0',
  `send_remark` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '发放备注',
  `status` tinyint(1) DEFAULT '1' COMMENT '是否发放 0未发放 1已发放 2已退回',
  `sendtime_yj` int DEFAULT '0' COMMENT '预计发放时间',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE,
  KEY `idx_aid_mid_type` (`aid`,`mid`,`type`(253))
) ENGINE=InnoDB AUTO_INCREMENT=14023 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_fenhongshouyilog`
--

DROP TABLE IF EXISTS `ddwx_member_fenhongshouyilog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_fenhongshouyilog` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int NOT NULL,
  `mid` int NOT NULL,
  `money` decimal(10,2) NOT NULL,
  `after` decimal(10,2) NOT NULL,
  `createtime` int NOT NULL,
  `remark` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_form_id`
--

DROP TABLE IF EXISTS `ddwx_member_form_id`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_form_id` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `mid` int DEFAULT NULL,
  `openid` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form_id` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `type` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT 'wx' COMMENT 'wx微信小程序 tt头条小程序',
  `createtime` int DEFAULT NULL,
  `isused` int DEFAULT '0',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE,
  KEY `openid` (`openid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_fxfhclog`
--

DROP TABLE IF EXISTS `ddwx_member_fxfhclog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_fxfhclog` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `aid` int NOT NULL,
  `mid` int NOT NULL,
  `money` decimal(11,2) NOT NULL,
  `after` decimal(11,2) NOT NULL,
  `createtime` int NOT NULL DEFAULT '0',
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_guquanscorelog`
--

DROP TABLE IF EXISTS `ddwx_member_guquanscorelog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_guquanscorelog` (
  `id` int NOT NULL DEFAULT '0',
  `aid` int DEFAULT NULL,
  `mid` int DEFAULT NULL,
  `money` decimal(11,2) DEFAULT NULL,
  `after` decimal(11,2) DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_heiscorelog`
--

DROP TABLE IF EXISTS `ddwx_member_heiscorelog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_heiscorelog` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `mid` int DEFAULT NULL,
  `money` decimal(11,2) DEFAULT NULL,
  `after` decimal(11,2) DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=2860 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_history`
--

DROP TABLE IF EXISTS `ddwx_member_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL COMMENT '账户ID',
  `mid` int DEFAULT NULL COMMENT '会员ID',
  `proid` int DEFAULT NULL COMMENT '商品ID',
  `createtime` int DEFAULT NULL COMMENT '创建时间',
  `type` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '类型 shop商城商品 scoreshop积分兑换商品 seckill秒杀商品 collage拼团商品 kanjia砍价商品',
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '页面/商品标题',
  `page_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '页面路径',
  `thumb` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '页面/商品缩略图',
  `description` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '页面描述/商品简介',
  `duration` int NOT NULL DEFAULT '0' COMMENT '浏览时长(秒)',
  `view_count` int NOT NULL DEFAULT '1' COMMENT '浏览次数',
  `extra` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '扩展数据(JSON格式)',
  `source` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '来源(wx/h5/app)',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE,
  KEY `proid` (`proid`) USING BTREE,
  KEY `idx_createtime` (`createtime`),
  KEY `idx_aid_mid_type` (`aid`,`mid`,`type`(253))
) ENGINE=InnoDB AUTO_INCREMENT=26755 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_level`
--

DROP TABLE IF EXISTS `ddwx_member_level`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_level` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `cid` int NOT NULL DEFAULT '0',
  `sort` int NOT NULL DEFAULT '1',
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `icon` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `card_main_cover` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '会员卡主封面图片URL',
  `card_sub_cover` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '会员卡副封面图片URL',
  `card_primary_color` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '#1890ff' COMMENT '会员卡主色调',
  `card_secondary_color` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '#f0f0f0' COMMENT '会员卡副色调',
  `card_description` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '会员卡描述信息',
  `card_activate_url` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '会员卡激活按钮链接',
  `card_activate_text` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '立即激活' COMMENT '会员卡激活按钮文字',
  `card_upgrade_btn_color` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '#000000' COMMENT '立即升级按钮背景颜色',
  `card_upgrade_btn_border_color` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '#333333' COMMENT '立即升级按钮边框颜色',
  `card_level_name_color` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '等级名称文字颜色',
  `card_description_color` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '描述文字颜色',
  `discount` decimal(11,2) DEFAULT '10.00',
  `maxnum` int DEFAULT '0',
  `can_up` tinyint(1) DEFAULT '0' COMMENT '能否通过下订单等途径完成正常升级..',
  `up_condition_show` tinyint unsigned NOT NULL DEFAULT '1',
  `up_ordermoney` decimal(11,2) DEFAULT NULL,
  `up_rechargemoney` decimal(11,2) DEFAULT NULL,
  `up_fxorder_condition` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT 'or' COMMENT 'or或，and且',
  `up_fxordermoney` decimal(11,2) DEFAULT NULL,
  `up_fxorderlevelnum` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '0',
  `up_fxorderlevelid` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '0',
  `up_fxordermoney_removemax` tinyint(1) DEFAULT '0',
  `up_fxordermoney_xiao` decimal(11,2) DEFAULT NULL,
  `up_fxorderlevelnum_xiao` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '0',
  `up_fxorderlevelid_xiao` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '0',
  `up_fxdowncount` int DEFAULT '0',
  `up_fxdownlevelnum` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '0',
  `up_fxdownlevelid` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '0',
  `up_fxdowncount2` int NOT NULL DEFAULT '0',
  `up_fxdownlevelnum2` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '0',
  `up_fxdownlevelid2` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '0',
  `up_fxdowncount3` int DEFAULT '0',
  `up_fxdownlevelnum3` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '0',
  `up_fxdownlevelid3` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '0',
  `up_fxdowncount_and` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '0',
  `up_fxdownlevelnum_and` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '0',
  `up_fxdownlevelid_and` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '0',
  `up_fxdowncount2_and` int DEFAULT '0',
  `up_fxdownlevelnum2_and` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '0',
  `up_fxdownlevelid2_and` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '0',
  `up_proid` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `up_pronum` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '1',
  `up_pro_extend_time` tinyint unsigned DEFAULT '0',
  `can_apply` int DEFAULT '1' COMMENT '该等级是否可以通过用户提供资料进行申请',
  `apply_check` int DEFAULT '0',
  `apply_ordercount` int DEFAULT NULL,
  `apply_ordermoney` decimal(11,2) DEFAULT NULL,
  `apply_rechargemoney` decimal(11,2) DEFAULT NULL,
  `apply_paymoney` decimal(11,2) DEFAULT NULL,
  `can_agent` tinyint(1) DEFAULT '0',
  `agent_rule` tinyint(1) DEFAULT '0',
  `commission1` decimal(11,2) DEFAULT NULL,
  `commission2` decimal(11,2) DEFAULT NULL,
  `commission3` decimal(11,2) DEFAULT NULL,
  `commission4` decimal(11,2) DEFAULT NULL,
  `commission5` decimal(11,2) DEFAULT NULL,
  `commission6` decimal(11,2) DEFAULT NULL,
  `commission_parent` decimal(11,2) NOT NULL COMMENT '持续推荐奖励',
  `commission_parent_pj` decimal(11,2) NOT NULL,
  `commission_appointlevelid` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `score1` int DEFAULT '0',
  `score2` int DEFAULT '0',
  `score3` int DEFAULT '0',
  `explain` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `createtime` int DEFAULT NULL,
  `isdefault` int DEFAULT '0',
  `up_wxpaymoney` decimal(11,2) DEFAULT NULL,
  `yxqdate` int DEFAULT '0',
  `up_getmembercard` tinyint(1) DEFAULT '0',
  `apply_formdata` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `fenhong` decimal(11,2) DEFAULT '0.00',
  `fenhong_num` int DEFAULT '0',
  `fenhong_max_money` decimal(11,2) NOT NULL,
  `commission1own` tinyint(1) DEFAULT '0',
  `teamfenhonglv` int DEFAULT '0',
  `teamfenhongbl` decimal(11,2) DEFAULT NULL,
  `teamfenhong_money` decimal(11,2) NOT NULL COMMENT '团队分红每单奖励',
  `teamfenhongonly` tinyint(1) DEFAULT '0',
  `teamfenhong_self` tinyint(1) DEFAULT '0' COMMENT '分红包含自己',
  `teamfenhong_removemax` tinyint(1) DEFAULT '0',
  `teamfenhong_pingji_lv` int DEFAULT '1',
  `teamfenhong_pingji_bl` decimal(11,2) DEFAULT NULL,
  `teamfenhong_pingji_money` decimal(11,2) DEFAULT NULL,
  `teamfenhong_pingji_type` tinyint(1) DEFAULT '0',
  `product_teamfenhong_ids` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '0',
  `product_teamfenhonglv` int DEFAULT NULL,
  `product_teamfenhong_money` decimal(11,2) NOT NULL,
  `product_teamfenhongonly` tinyint(1) DEFAULT '0',
  `product_teamfenhong_self` tinyint(1) DEFAULT '0',
  `level_teamfenhong_ids` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `level_teamfenhonglv` int DEFAULT NULL,
  `level_teamfenhongbl` decimal(11,2) DEFAULT NULL,
  `level_teamfenhong_money` decimal(11,2) NOT NULL,
  `level_teamfenhongonly` tinyint(1) DEFAULT '0',
  `areafenhong` tinyint(1) DEFAULT '0' COMMENT '区域代理 0不开启 1省级 2市级 3区县',
  `areafenhongbl` decimal(10,2) DEFAULT NULL,
  `areafenhong_money` decimal(10,2) DEFAULT NULL COMMENT '区域代理固定分红金额（元/单）',
  `areafenhong_type` tinyint DEFAULT '0' COMMENT '区域代理分红类型（0=按比例，1=按固定金额）',
  `areafenhongmaxnum` int DEFAULT '0',
  `apply_paytxt` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `apply_payfenxiao` tinyint(1) DEFAULT '0',
  `commissiontype` tinyint(1) DEFAULT '0',
  `scoremax` int DEFAULT '0' COMMENT '推荐最高奖励积分',
  `can_buyselect` tinyint(1) DEFAULT '0',
  `buyselect_commission` float(11,2) DEFAULT '0.00',
  `show_business` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '是否显示在商家',
  `team_showtel` tinyint(1) DEFAULT '0' COMMENT '是否显示下级手机号',
  `team_givemoney` tinyint(1) DEFAULT '0' COMMENT '是否可以给下级转余额',
  `team_givescore` tinyint(1) DEFAULT '0' COMMENT '是否可以给下级转积分',
  `team_levelup` tinyint(1) DEFAULT '0' COMMENT '是否可以给下级升级',
  `team_levelup_num` int DEFAULT NULL COMMENT '给下级升级数量',
  `team_levelup_id` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `comwithdraw` tinyint(1) DEFAULT '1',
  `up_give_score` int DEFAULT NULL COMMENT '升级赠送积分',
  `up_give_money` float(11,2) DEFAULT '0.00' COMMENT '升级赠送余额',
  `up_give_commission` decimal(11,2) NOT NULL COMMENT '升级赠送佣金',
  `up_give_parent_money` decimal(11,2) NOT NULL COMMENT '升级给上级赠送余额',
  `up_give_parent_coupon_ids` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `up_give_parent_coupon_nums` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `tongji_yeji` tinyint(1) NOT NULL DEFAULT '0',
  `tongji_yeji_proids` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `fenhong_gongxian_minyeji` decimal(11,2) DEFAULT NULL,
  `fenhong_gongxian_percent` decimal(11,2) DEFAULT NULL,
  `kecheng_discount` decimal(11,2) DEFAULT '10.00',
  `maidan_commission_score1` int DEFAULT '0',
  `maidan_commission_score2` int DEFAULT '0',
  `maidan_commission_score3` int DEFAULT '0',
  `islock` tinyint(1) DEFAULT '0',
  `up_giveparent_num` int DEFAULT '0',
  `up_catid` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `up_cat_ordermoney` decimal(11,2) DEFAULT NULL,
  `up_giveparent_levelid` int DEFAULT NULL,
  `up_change_pid` tinyint(1) DEFAULT '0',
  `freight` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '运费配置',
  `up_orderpaymoney` decimal(10,2) NOT NULL COMMENT '一次性订单支付金额满',
  `up_newdowncount` int NOT NULL DEFAULT '0' COMMENT '下级总人数满（包含等级）',
  `up_newdownlevelids` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL COMMENT '升级包含ids 逗号分割',
  `up_mydengji` int NOT NULL DEFAULT '0',
  `fhcommissionstatus` int NOT NULL DEFAULT '0',
  `fh_commission2` decimal(12,3) NOT NULL,
  `fh_commission3` decimal(12,3) NOT NULL,
  `fh_commission4` decimal(12,3) NOT NULL,
  `is_ershi` tinyint NOT NULL DEFAULT '0',
  `up_tuandui` decimal(11,2) NOT NULL,
  `profenhong` tinyint(1) NOT NULL DEFAULT '0' COMMENT '该等级是否可以参与分红',
  `can_jiandian` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0不能',
  `ti_edu` int NOT NULL DEFAULT '0',
  `up_dengjiid` int DEFAULT '0',
  `up_dengjiidjifen` decimal(10,2) DEFAULT NULL,
  `up_dengjiidjifennum` int DEFAULT '0',
  `xfz_commission2` decimal(10,2) NOT NULL,
  `xfz_commission3` decimal(10,2) NOT NULL,
  `xfz_commission4` decimal(10,2) NOT NULL,
  `yongjinbeishu` int NOT NULL DEFAULT '0',
  `fenyong_zong` decimal(10,2) DEFAULT NULL,
  `fenyong_yongjin` decimal(10,2) DEFAULT NULL,
  `fenyong_jifen` decimal(10,2) NOT NULL,
  `up_zt_new` int NOT NULL DEFAULT '0',
  `up_zt_new_dengjiid` int NOT NULL DEFAULT '0',
  `up_zt_new_dengjiyeji` decimal(11,2) NOT NULL,
  `up_zt_renshu` int NOT NULL DEFAULT '0',
  `up_zt_renshu_count` int NOT NULL DEFAULT '0',
  `up_zt_renshu_shichang` int NOT NULL DEFAULT '0',
  `up_zt_renshu_tuanduiyeji` decimal(11,2) NOT NULL,
  `zuigao_money` decimal(10,2) DEFAULT NULL,
  `zuigao_score` decimal(10,2) NOT NULL,
  `commission1_score` decimal(10,2) NOT NULL,
  `commission2_score` decimal(10,2) NOT NULL,
  `commission3_score` decimal(10,2) NOT NULL,
  `commission_parent_score` decimal(10,2) NOT NULL,
  `commission_parent_pj_score` decimal(10,2) NOT NULL,
  `kfj_yeji` int DEFAULT '0',
  `kfj_jiangli` decimal(10,2) DEFAULT NULL,
  `pyj_dengji` int DEFAULT '0',
  `pyj_baifenbi` decimal(10,2) NOT NULL,
  `pyj_qudengji` int DEFAULT '0',
  `cyjj_tuandui` decimal(10,2) DEFAULT NULL,
  `cyjj_qudengji` int DEFAULT '0',
  `cyjj_jiangli` decimal(10,2) DEFAULT NULL,
  `cyjj_shangdengji` int DEFAULT '0',
  `cyjj_shangjiangli` decimal(10,2) DEFAULT NULL,
  `up_zishen_levelid` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '0',
  `is_zuigao_money` tinyint(1) DEFAULT NULL,
  `is_zuigao_score` tinyint(1) DEFAULT NULL,
  `bus_bound_multiples` decimal(11,0) DEFAULT NULL COMMENT '奖励倍数',
  `is_bus_bound_multiples` tinyint(1) DEFAULT '0' COMMENT '是否开启创业值;',
  `up_mei_order_monnnney` decimal(11,2) NOT NULL,
  `up_zt_new_duo` int NOT NULL DEFAULT '0',
  `up_zt_new_dengjiid_duo` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '0',
  `up_zt_new_dengjiyeji_duo` decimal(11,2) NOT NULL,
  `up_zt_new2_duo` int NOT NULL DEFAULT '0',
  `up_zt_new2_dengjiid_shichang1` int NOT NULL DEFAULT '0',
  `up_zt_new2_dengjiid_duo1` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `up_zt_new2_dengjiid_shichang2` int NOT NULL DEFAULT '0',
  `up_zt_new2_dengjiid_duo2` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '0',
  `up_zt_new2_tuanduiyeji` decimal(10,2) NOT NULL,
  `up_zt_new3_duo` int NOT NULL DEFAULT '0',
  `up_zt_new3_dengjiid_shichang1` int NOT NULL DEFAULT '0',
  `up_zt_new3_dengjiid_duo1` varchar(11) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '0',
  `up_zt_new3_dengjiid_shichang2` int NOT NULL DEFAULT '0',
  `up_zt_new3_dengjiid_duo2` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '0',
  `up_zt_new3_tuanduiyeji` decimal(11,2) NOT NULL,
  `gxz_xf_nums` decimal(10,2) DEFAULT NULL COMMENT '贡献点 消耗多少元',
  `gxz_xf_zs` decimal(10,2) DEFAULT NULL COMMENT '贡献值  消耗多少元 赠送多少点的贡献值',
  `gxz_fx_nums` int DEFAULT '0' COMMENT '贡献值  分享多人少',
  `gxz_fx_dj` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `gxz_fx_zs` decimal(10,2) DEFAULT NULL COMMENT '贡献值  分享 赠送贡献值',
  `gxz_ljxf_nums` decimal(10,2) DEFAULT NULL COMMENT '贡献值 直推用户每累计消费',
  `gxz_ljxf_zs` decimal(10,2) DEFAULT NULL COMMENT '贡献值 累计消费 赠送贡献值',
  `sy_xj_dj` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '0' COMMENT '收益抽佣奖励享受下级',
  `sy_xj_nums` decimal(10,2) DEFAULT NULL COMMENT '收益抽佣奖励享受下级 收益%比',
  `td_fh_j` decimal(10,2) DEFAULT NULL COMMENT '团队分红加一个平级奖励  %当达到平级时享受下级平级的收益的百分比',
  `hjf_xf_nums` decimal(10,2) DEFAULT NULL COMMENT '黄积分  消耗多少元',
  `hjf_xf_zs` decimal(10,2) DEFAULT NULL COMMENT '黄积分  消耗多少元 赠送多少点的黄积分',
  `up_fxorder_condition_two` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT 'or' COMMENT 'or或，and且',
  `up_fxordermoney_two` decimal(11,2) DEFAULT NULL,
  `up_fxorderlevelnum_two` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '0',
  `up_fxorderlevelid_two` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '0',
  `gq_xf_nums` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '0',
  `gq_xf_zs` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '0',
  `jinbi_xf_nums` decimal(10,2) DEFAULT NULL,
  `jinbi_xf_zs` decimal(10,2) DEFAULT NULL,
  `commissionshouyi1` decimal(11,2) NOT NULL,
  `commissionshouyi2` decimal(11,2) NOT NULL,
  `commissionshouyi3` decimal(11,2) NOT NULL,
  `commissionshouyi_parent` decimal(11,2) NOT NULL,
  `oneordermoney` decimal(11,2) NOT NULL,
  `up_give_xianjinquan` decimal(11,2) NOT NULL,
  `tuandui_chouyong` tinyint(1) NOT NULL DEFAULT '1',
  `pingjilevelid` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `pingjilevelidjiangli` decimal(11,2) NOT NULL,
  `pingjilevelid2` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `pingjilevelidjiangli2` decimal(11,2) NOT NULL,
  `pingjilevelid3` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '0.00',
  `pingjilevelidjiangli3` decimal(11,2) NOT NULL,
  `can_cunhuo` tinyint(1) NOT NULL DEFAULT '1',
  `yun_nahuo` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '0',
  `up_fxdowncount_new1` int NOT NULL DEFAULT '0',
  `up_fxdownlevelnum_new1` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '0',
  `up_fxdownlevelid_new1` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '0',
  `up_fxdowncount2_new1` int NOT NULL DEFAULT '0',
  `up_fxdownlevelnum2_new1` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '0',
  `up_fxdownlevelid2_new1` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '0',
  `up_fxdowncount_new2` int NOT NULL DEFAULT '0',
  `up_fxdownlevelnum_new2` int DEFAULT '0',
  `up_fxdownlevelid_new2` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '0',
  `up_fxdowncount2_new2` int NOT NULL DEFAULT '0',
  `up_fxdownlevelnum2_new2` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '0',
  `up_fxdownlevelid2_new2` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '0',
  `up_fxdowncount_new3` int NOT NULL DEFAULT '0',
  `up_fxdownlevelnum_new3` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '0',
  `up_fxdownlevelid_new3` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '0',
  `up_fxdownlevelnum2_new3` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '0',
  `up_fxdownlevelid2_new3` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '0',
  `up_fxdowncount2_new3` int NOT NULL DEFAULT '0',
  `up_fxdowncount_new4` int NOT NULL DEFAULT '0',
  `up_fxdownlevelnum_new4` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '0',
  `up_fxdownlevelid_new4` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '0',
  `up_fxdowncount2_new4` int NOT NULL DEFAULT '0',
  `up_fxdownlevelnum2_new4` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '0',
  `up_fxdownlevelid2_new4` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '0',
  `up_fxdowncount_new5` int NOT NULL DEFAULT '0',
  `up_fxdownlevelnum_new5` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '0',
  `up_fxdownlevelid_new5` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '0',
  `up_fxdowncount2_new5` int NOT NULL DEFAULT '0',
  `up_fxdownlevelnum2_new5` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '0',
  `up_fxdownlevelid2_new5` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '0',
  `peiyang_levelid1` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '0',
  `peiyang_jiangli1` decimal(10,2) NOT NULL,
  `peiyang_levelid2` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '0',
  `peiyang_jiangli2` decimal(11,2) NOT NULL,
  `suoding_levelid1` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '0',
  `suodingpeiyang1` decimal(11,2) NOT NULL,
  `suoding_levelid2` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '0',
  `suodingpeiyang2` decimal(11,2) NOT NULL,
  `jiedian_teamfenhonglv` decimal(10,2) NOT NULL,
  `jiedian_teamfenhongbl` decimal(10,2) DEFAULT NULL,
  `jiedian_teamfenhong_money` decimal(11,2) NOT NULL,
  `jiedian_teamfenhongonly` tinyint(1) NOT NULL DEFAULT '0',
  `jiedian_teamfenhong_self` tinyint(1) NOT NULL DEFAULT '0',
  `fxzhuli` tinyint(1) NOT NULL DEFAULT '0',
  `fxlianjie` tinyint(1) NOT NULL DEFAULT '0',
  `fxdengji` int NOT NULL DEFAULT '0',
  `isyuexin` tinyint(1) NOT NULL DEFAULT '1',
  `yuexin_zigou` decimal(10,2) DEFAULT NULL,
  `yuexin_zhitui` decimal(11,2) DEFAULT NULL,
  `yexin_leveid_f` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '0',
  `yexin_bfb_f` decimal(11,2) DEFAULT NULL,
  `yexin_leveid_w` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '0',
  `yexin_bfb_w` decimal(11,2) DEFAULT NULL,
  `zt_sy_levelid1` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '0',
  `zt_sy_yong1` decimal(11,2) NOT NULL,
  `zt_sy_levelid2` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '0',
  `zt_sy_yong2` decimal(11,2) NOT NULL,
  `jt_sy_ceng` int NOT NULL DEFAULT '0',
  `jt_sy_levelid` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '0',
  `jt_sy_yong` decimal(11,2) NOT NULL,
  `tjmoney` decimal(10,2) NOT NULL,
  `tjmoney_one` decimal(10,2) NOT NULL,
  `tjmoney_two` decimal(11,2) NOT NULL,
  `zijifh` tinyint(1) NOT NULL DEFAULT '0',
  `zijifh_jiang` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `fxfhc` tinyint(1) NOT NULL DEFAULT '0',
  `fxfhc_jiang` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `syc` tinyint(1) NOT NULL DEFAULT '0',
  `syc_jiang` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `syc_shouyi` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `paidui_jiang` decimal(10,2) NOT NULL,
  `paidui_give` decimal(11,2) NOT NULL,
  `tuoli_new_count` tinyint(1) NOT NULL DEFAULT '0',
  `tuoli_old_count` tinyint(1) DEFAULT '0',
  `tuoli_shengji_prent1` tinyint(1) NOT NULL DEFAULT '0',
  `tuoli_shengji_prent2` tinyint(1) NOT NULL DEFAULT '0',
  `tuoli_member_count` int NOT NULL DEFAULT '0',
  `tuoli_member_parent_levelid` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `tuoli_member_son_levelid` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `pingjilevelidjiangli_num` int NOT NULL DEFAULT '1',
  `next_level_id` int DEFAULT NULL,
  `huigui_status` tinyint(1) NOT NULL DEFAULT '0',
  `paidui_zhijiasu` decimal(10,2) DEFAULT NULL,
  `paidui_jianjiasu` decimal(11,2) DEFAULT NULL,
  `paidui_fugoujiasu` decimal(10,2) DEFAULT NULL COMMENT '复购加速比例',
  `tuoli_jiedianren` tinyint(1) DEFAULT '0',
  `tuoli_liandongjiang` decimal(10,2) DEFAULT NULL,
  `tuoli_jiedian_member_count` int DEFAULT NULL,
  `tuoli_jiedian_member_parent_levelid` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `tuoli_jiedian_member_son_levelid` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `tuoli_liandongjiangshang1` decimal(10,2) DEFAULT NULL,
  `tuoli_liandongjiangshang2` decimal(11,2) DEFAULT NULL,
  `tuoli_liandongjiangshang3` decimal(10,2) DEFAULT NULL,
  `up_give_gongxianzhi` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `up_give_parent_gongxianzhi` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `up_give_parent2_gongxianzhi` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `up_give_max_single_reward` decimal(10,2) NOT NULL COMMENT '升级奖励单笔最大奖励金额',
  `up_jifen_condition` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '0',
  `up_jifen` decimal(11,2) DEFAULT NULL,
  `shengjijifen_status` tinyint(1) DEFAULT '0',
  `qiangnum` int DEFAULT '0',
  `yongjincishu` int NOT NULL DEFAULT '0',
  `team_daikexiadan` int NOT NULL DEFAULT '0',
  `upgrade_level_ids` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `upgrade_reward` int DEFAULT NULL,
  `up_market_ab_total` decimal(10,2) DEFAULT NULL COMMENT 'A+B市场业绩要求',
  `up_market_other_min` decimal(10,2) DEFAULT NULL COMMENT '其它市场最低业绩要求',
  `up_market_condition` enum('or','and') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'or' COMMENT '市场业绩条件：or/and',
  `need_signature` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否需要签名，0:不需要，1:需要',
  `up_fxorder_condition_three` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT 'or' COMMENT '小区业绩（去除一个大区）条件选择，or表示或者，and表示并且',
  `up_fxordermoney_three` decimal(10,2) DEFAULT NULL COMMENT '小区业绩（去除一个大区）金额',
  `up_fxorderlevelnum_three` int DEFAULT '0' COMMENT '小区业绩（去除一个大区）级数',
  `up_fxorderlevelid_three` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '0' COMMENT '小区业绩（去除一个大区）等级ID',
  `small_market_min_level` int NOT NULL DEFAULT '0' COMMENT '小市场等级要求',
  `small_market_min_count` int NOT NULL DEFAULT '0' COMMENT '小市场人数要求',
  `small_market_fenhong_percent` decimal(10,2) NOT NULL COMMENT '小市场分红比例',
  `small_market_fenhong_money` decimal(10,2) NOT NULL COMMENT '小市场每单固定奖励',
  `small_market_fenhong_only` tinyint(1) NOT NULL DEFAULT '0' COMMENT '只给最近的上级分红 0=否 1=是',
  `small_market_include_self` tinyint(1) NOT NULL DEFAULT '0' COMMENT '计算小市场人数时是否包含自己 0=否 1=是',
  `small_market_teamfenhonglv` int NOT NULL DEFAULT '0' COMMENT '小市场团队分红级数：可以拿到下级多少层的分红',
  `small_market_teamfenhongbl` decimal(8,2) NOT NULL COMMENT '小市场团队分红比例(%)：从订单金额中提取的分红比例',
  `small_market_teamfenhong_money` decimal(8,2) NOT NULL COMMENT '小市场团队每单分红金额：除比例分红外的固定奖励',
  `small_market_teamfenhongonly` tinyint(1) NOT NULL DEFAULT '0' COMMENT '小市场团队只给最近上级：1-开启，0-关闭',
  `small_market_teamfenhong_self` tinyint(1) NOT NULL DEFAULT '0' COMMENT '小市场团队包含自己：1-允许自己享受分红，0-不允许',
  `small_market_td_fh_j` decimal(8,2) NOT NULL COMMENT '小市场团队平级奖比例(%)：用于计算同等级间的分红',
  `maidan_zt_ratio` decimal(5,2) DEFAULT NULL COMMENT '买单直推比例(%)',
  `maidan_zt_payother_ratio` decimal(5,2) DEFAULT NULL COMMENT '买单直推支付其他比例(%)',
  `maidan_nzt_payself_ratio` decimal(5,2) DEFAULT NULL COMMENT '买单非直推自付比例(%)',
  `jiedian_zhitui_type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '接点人直推奖励类型：0百分比，1固定金额',
  `jiedian_zhitui_money` decimal(10,2) NOT NULL COMMENT '接点人直推奖励金额',
  `jiedian_zhitui_score` int NOT NULL DEFAULT '0' COMMENT '接点人直推积分奖励',
  `team_reward_limit_percent` decimal(5,2) NOT NULL COMMENT '团队奖限额百分比(%)',
  `show_as_teamfenhong_admin` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否显示为团队分红管理员 0否 1是',
  `can_view_subordinate_phone` tinyint(1) NOT NULL DEFAULT '0' COMMENT '可以查看下级手机号 0否 1是',
  `can_view_relation_chart` tinyint(1) NOT NULL DEFAULT '0' COMMENT '可以查看关系图 0否 1是',
  `can_view_unlimited_phone` tinyint(1) NOT NULL DEFAULT '0' COMMENT '可以查看无限极手机号 0否 1是',
  `up_jiedian_xiaoqu_condition` enum('or','and') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'or' COMMENT '接点人小区业绩条件关系：or=或，and=且',
  `up_jiedian_xiaoqu_money` decimal(10,2) DEFAULT NULL COMMENT '接点人小区业绩要求金额',
  `up_jiedian_xiaoqu_levelnum` int DEFAULT '0' COMMENT '接点人小区业绩计算级数限制，0表示不限制',
  `up_jiedian_xiaoqu_levelid` int DEFAULT '0' COMMENT '接点人小区业绩计算等级ID限制，0表示不限制',
  `up_jiedian_xiaoqu_type` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT 'turnover' COMMENT '接点人小区业绩计算类型：turnover=流水，profit=利润',
  `up_jiedian_market_condition` enum('or','and') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'or' COMMENT '接点人市场条件关系：or=或，and=且',
  `up_jiedian_market_count` int DEFAULT '0' COMMENT '要求的接点市场数量',
  `up_jiedian_market_money` decimal(10,2) DEFAULT NULL COMMENT '每个接点市场要求的业绩金额',
  `up_give_gongxianzhi_from_levelids` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '升级赠送贡献值的来源等级ID列表，逗号分隔，为空表示不限制',
  `up_zt_market_count` int DEFAULT '0' COMMENT '直推市场数量要求',
  `up_zt_market_money` decimal(10,2) DEFAULT NULL COMMENT '每个市场业绩要求（万元）',
  `up_zt_market_type` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT 'turnover' COMMENT '计算类型：turnover=业绩，profit=利润',
  `jiedian_teamfenhong_performance_check` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否启用接点团队分红业绩检查 0否 1是',
  `jiedian_teamfenhong_performance_amount` decimal(10,2) NOT NULL DEFAULT '560000.00' COMMENT '接点团队分红业绩检查金额（默认56万）',
  `jiedian_teamfenhong_performance_type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '业绩计算类型 0流水 1利润',
  `can_view_subordinate_orders` tinyint(1) DEFAULT '0' COMMENT '是否可以查看下级订单:0=否,1=是',
  `can_hexiao_subordinate_orders` tinyint(1) DEFAULT '0' COMMENT '是否可以核销下级订单:0=否,1=是',
  `team_gen_poster` tinyint(1) NOT NULL DEFAULT '0' COMMENT '帮客户生成分销海报权限 0=否 1=是',
  `up_give_parent_level_ids` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '升级奖励指定等级上级的等级ID，多个逗号隔开',
  `up_give_parent_level_score` decimal(10,2) DEFAULT '0.00' COMMENT '升级奖励指定等级上级积分数量',
  `up_give_parent_level_continue` tinyint(1) DEFAULT '0' COMMENT '查找模式 0=找到即停止 1=往上找不限高低 2=往上找只奖更高',
  `can_view_subordinate_footprint` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否允许查看下级足迹 0否 1是',
  `comprehensive_customer_limit_base` int NOT NULL DEFAULT '-1' COMMENT '综合诊疗可建客户数基础(-1不限)',
  `comprehensive_detect_times_total_base` int NOT NULL DEFAULT '-1' COMMENT '综合诊疗可检测次数基础(-1不限)',
  `up_zichanfenqi_status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '资产分期到账升级开关',
  `up_zichanfenqi_condition` enum('or','and') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'or' COMMENT '资产分期转化升级条件关系',
  `up_zichanfenqi_scope` varchar(16) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'all' COMMENT '资产分期到账统计范围',
  `up_zichanfenqi_asset_type` enum('','money','score','heiscore','scorehuang','commission','contribution','scorelv','score3','xiaofeib') COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '资产分期转化资产类型',
  `up_zichanfenqi_amount` decimal(12,5) NOT NULL DEFAULT '0.00000' COMMENT '资产分期到账升级门槛',
  `up_zichanfenqi_team_status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '资产分期团队到账升级开关',
  `up_zichanfenqi_team_condition` enum('or','and') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'or' COMMENT '资产分期团队转化升级条件关系',
  `up_zichanfenqi_team_relation_type` enum('recommend','jiedian') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'recommend' COMMENT '资产分期团队转化统计关系',
  `up_zichanfenqi_team_asset_type` enum('','money','score','heiscore','scorehuang','commission','contribution','scorelv','score3','xiaofeib') COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '资产分期团队转化资产类型',
  `up_zichanfenqi_team_amount` decimal(12,5) NOT NULL DEFAULT '0.00000' COMMENT '资产分期团队转化升级门槛',
  `up_jiedian_renshu_condition` enum('or','and') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'or' COMMENT '接点人数和市场条件关系：or/and',
  `up_jiedian_renshu` int NOT NULL DEFAULT '0' COMMENT '接点人数门槛',
  `up_jiedian_renshu_count` int NOT NULL DEFAULT '0' COMMENT '接点市场数量门槛',
  `up_jiedian_renshu_shichang` int NOT NULL DEFAULT '0' COMMENT '接点市场出现等级ID',
  `up_jiedian_renshu_tuanduiyeji` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '接点团队业绩门槛',
  `up_downbuypronum` int NOT NULL DEFAULT '0' COMMENT 'down-buy product quantity threshold',
  `up_downbuyproid` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT 'down-buy product ids, comma separated',
  `up_downbuyprolvnum` int NOT NULL DEFAULT '0' COMMENT 'down-buy product statistic depth',
  `view_platform_level_ids` varchar(500) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '可查看的平台会员等级ID，逗号分隔',
  `team_ext_perms` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '团队扩展权限JSON：如查看下级舌诊客户等',
  `agent_recharge_status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '代理充值升级开关：0关闭 1开启',
  `agent_recharge_min` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '该等级充值最低金额',
  `agent_recharge_max` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '该等级充值最高金额',
  `level_desc` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '等级说明富文本',
  `rights_desc` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '权益描述富文本',
  `bg_image` varchar(500) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '会员等级背景图',
  `up_rules_json` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '会员自动升级条件配置JSON',
  `commission2money_enabled` tinyint(1) NOT NULL DEFAULT '1' COMMENT '当前等级是否允许佣金转余额：0否 1是',
  `up_give_parent_money_level_ids` varchar(500) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '升级赠送上级佣金的上级等级限制，逗号分隔',
  `upgrade_reward_rules_json` mediumtext COLLATE utf8mb4_general_ci COMMENT '升级团队级差奖与评级奖规则JSON',
  PRIMARY KEY (`id`,`up_fxdowncount2`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `cid` (`cid`) USING BTREE,
  KEY `sort` (`sort`) USING BTREE,
  KEY `can_up` (`can_up`) USING BTREE,
  KEY `isdefault` (`isdefault`) USING BTREE,
  KEY `idx_card_main_cover` (`card_main_cover`) USING BTREE,
  KEY `idx_card_activate_url` (`card_activate_url`) USING BTREE,
  KEY `idx_aid_id` (`aid`,`id`) USING BTREE,
  KEY `idx_show_as_teamfenhong_admin` (`show_as_teamfenhong_admin`) USING BTREE,
  KEY `idx_jiedian_xiaoqu_conditions` (`up_jiedian_xiaoqu_money`,`up_jiedian_xiaoqu_condition`,`up_jiedian_xiaoqu_type`) USING BTREE,
  KEY `idx_up_give_gongxianzhi_from_levelids` (`up_give_gongxianzhi_from_levelids`) USING BTREE,
  KEY `idx_jiedian_performance_check` (`jiedian_teamfenhong_performance_check`) USING BTREE,
  KEY `idx_member_level_areafenhong_type` (`areafenhong_type`,`areafenhongbl`) USING BTREE,
  KEY `idx_can_view_subordinate` (`can_view_subordinate_orders`) USING BTREE,
  KEY `idx_can_hexiao_subordinate` (`can_hexiao_subordinate_orders`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=376 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='会员等级表，包含接点团队分红业绩检查等功能';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_level_category`
--

DROP TABLE IF EXISTS `ddwx_member_level_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_level_category` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `bid` int DEFAULT '0',
  `pid` int DEFAULT '0',
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `pic` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `status` int DEFAULT '1',
  `sort` int DEFAULT '1',
  `createtime` int DEFAULT NULL,
  `isdefault` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE,
  KEY `pid` (`pid`) USING BTREE,
  KEY `isdefault` (`isdefault`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_level_permissions`
--

DROP TABLE IF EXISTS `ddwx_member_level_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_level_permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int NOT NULL DEFAULT '0' COMMENT '商家ID',
  `level_id` int NOT NULL DEFAULT '0' COMMENT '会员等级ID',
  `can_view_subordinate_orders` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否可以查看下级订单:0=否,1=是',
  `can_hexiao_subordinate_orders` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否可以核销下级订单:0=否,1=是',
  `createtime` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `updatetime` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `idx_aid_level_id` (`aid`,`level_id`) USING BTREE,
  KEY `idx_can_view_subordinate` (`can_view_subordinate_orders`) USING BTREE,
  KEY `idx_can_hexiao_subordinate` (`can_hexiao_subordinate_orders`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='会员等级权限扩展表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_level_record`
--

DROP TABLE IF EXISTS `ddwx_member_level_record`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_level_record` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `mid` int DEFAULT '0',
  `bid` int DEFAULT '0',
  `levelid` int DEFAULT '0',
  `cid` int DEFAULT '0',
  `qrcode` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `sharepic` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  `levelstarttime` int DEFAULT '0',
  `levelendtime` int DEFAULT '0',
  `areafenhong_province` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `areafenhong_city` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `areafenhong_area` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `areafenhong` tinyint(1) DEFAULT '0',
  `areafenhongbl` decimal(10,2) DEFAULT NULL,
  `areafenhong_money` decimal(10,2) DEFAULT NULL COMMENT '区域代理固定分红金额（元/单）',
  `areafenhong_type` tinyint DEFAULT '0' COMMENT '区域代理分红类型（0=按比例，1=按固定金额）',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE,
  KEY `cid` (`cid`) USING BTREE,
  KEY `levelid` (`levelid`) USING BTREE,
  KEY `levelendtime` (`levelendtime`) USING BTREE,
  KEY `createtime` (`createtime`) USING BTREE,
  KEY `areafenhong_province` (`areafenhong_province`) USING BTREE,
  KEY `areafenhong_city` (`areafenhong_city`) USING BTREE,
  KEY `areafenhong_area` (`areafenhong_area`) USING BTREE,
  KEY `areafenhong` (`areafenhong`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_level_upgrade_log`
--

DROP TABLE IF EXISTS `ddwx_member_level_upgrade_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_level_upgrade_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int NOT NULL DEFAULT '0' COMMENT '应用ID',
  `mid` int NOT NULL DEFAULT '0' COMMENT '会员ID',
  `bid` int NOT NULL DEFAULT '0' COMMENT '商家ID',
  `old_level_id` int NOT NULL DEFAULT '0' COMMENT '原等级ID',
  `new_level_id` int NOT NULL DEFAULT '0' COMMENT '新等级ID',
  `upgrade_reason` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '' COMMENT '升级原因',
  `createtime` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `old_level_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '原等级名称',
  `new_level_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '新等级名称',
  `upgrade_type` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '升级方式',
  `upgrade_remark` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '升级备注',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='会员等级升级日志表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_levelup_order`
--

DROP TABLE IF EXISTS `ddwx_member_levelup_order`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_levelup_order` (
  `id` int NOT NULL AUTO_INCREMENT,
  `settlement_id` bigint unsigned DEFAULT NULL COMMENT '二阶段升级结算ID',
  `settlement_key` char(64) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '二阶段升级结算幂等键',
  `aid` int DEFAULT NULL,
  `mid` int DEFAULT NULL,
  `levelid` int DEFAULT NULL,
  `ordernum` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `totalprice` decimal(11,2) DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `status` int DEFAULT '0',
  `createtime` int DEFAULT NULL,
  `levelup_time` int DEFAULT NULL,
  `payorderid` int DEFAULT NULL,
  `paytypeid` int DEFAULT NULL,
  `paynum` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `paytype` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `paytime` int DEFAULT NULL,
  `beforelevelid` int DEFAULT NULL,
  `areafenhong_province` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `areafenhong_city` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `areafenhong_area` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `areafenhong_largearea` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `from_mid` int DEFAULT NULL,
  `form0` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form1` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form2` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form3` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form4` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form5` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form6` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form7` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form8` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form9` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form10` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form11` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form12` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form13` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form14` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form15` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form16` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form17` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form18` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form19` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form20` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `platform` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `isfenhong` int NOT NULL DEFAULT '0' COMMENT '是否分红，0:否，1:是',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uniq_uplv_settlement_key` (`settlement_key`),
  KEY `aid` (`aid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE,
  KEY `levelid` (`levelid`) USING BTREE,
  KEY `beforelevelid` (`beforelevelid`) USING BTREE,
  KEY `levelup_time` (`levelup_time`) USING BTREE,
  KEY `status` (`status`) USING BTREE,
  KEY `idx_aid_mid_status` (`aid`,`mid`,`status`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=5074 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_lyj_logs`
--

DROP TABLE IF EXISTS `ddwx_member_lyj_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_lyj_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `times` datetime DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=4056 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='开发测试使用 -可删除';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_moneylog`
--

DROP TABLE IF EXISTS `ddwx_member_moneylog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_moneylog` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `mid` int DEFAULT NULL,
  `money` decimal(11,2) DEFAULT NULL,
  `after` decimal(11,2) DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `from_mid` int DEFAULT '0',
  `paytype` varchar(60) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `rechargeid` int DEFAULT NULL,
  `uid` int DEFAULT '0',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1383 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_openid_bind_log`
--

DROP TABLE IF EXISTS `ddwx_member_openid_bind_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_openid_bind_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `aid` int unsigned NOT NULL DEFAULT '0' COMMENT '站点ID',
  `platform` varchar(8) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '平台：mp公众号 wx小程序',
  `mid` int unsigned NOT NULL DEFAULT '0' COMMENT '当前会员ID',
  `conflict_mid` int unsigned NOT NULL DEFAULT '0' COMMENT '冲突会员ID',
  `source` varchar(32) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '来源：login/pay/register/manual',
  `action` varchar(32) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '动作：bound/already/conflict/skipped/error',
  `old_openid` varchar(128) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '原OpenID',
  `new_openid` varchar(128) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '新OpenID',
  `unionid` varchar(128) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT 'UnionID',
  `reason` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '结果说明',
  `ip` varchar(64) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT 'IP地址',
  `user_agent` varchar(500) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT 'User-Agent',
  `createtime` int unsigned NOT NULL DEFAULT '0' COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_aid_platform_mid` (`aid`,`platform`,`mid`),
  KEY `idx_aid_platform_action` (`aid`,`platform`,`action`),
  KEY `idx_aid_conflict_mid` (`aid`,`conflict_mid`),
  KEY `idx_aid_createtime` (`aid`,`createtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='会员微信OpenID绑定与冲突审计';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_performance_cache`
--

DROP TABLE IF EXISTS `ddwx_member_performance_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_performance_cache` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `aid` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `mid` int NOT NULL DEFAULT '0' COMMENT '会员ID',
  `cache_date` date NOT NULL COMMENT '缓存日期',
  `cache_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'daily' COMMENT '缓存类型：minute分钟缓存，hourly小时缓存，daily日缓存，monthly月缓存，total累计',
  `relation_mode` varchar(20) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'direct',
  `amount_method` varchar(20) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'sales',
  `period_start` int NOT NULL DEFAULT '0',
  `period_end` int NOT NULL DEFAULT '0',
  `scope_hash` varchar(64) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'default',
  `extra_data` text COLLATE utf8mb4_general_ci,
  `direct_count` int NOT NULL DEFAULT '0' COMMENT '直推人数',
  `direct_active_count` int NOT NULL DEFAULT '0' COMMENT '直推活跃人数',
  `direct_performance` decimal(15,2) NOT NULL COMMENT '直推业绩',
  `direct_commission` decimal(15,2) NOT NULL COMMENT '直推佣金',
  `team_count` int NOT NULL DEFAULT '0' COMMENT '团队总人数',
  `team_active_count` int NOT NULL DEFAULT '0' COMMENT '团队活跃人数',
  `team_performance` decimal(15,2) NOT NULL COMMENT '团队总业绩',
  `team_commission` decimal(15,2) NOT NULL COMMENT '团队佣金',
  `self_orders` int NOT NULL DEFAULT '0' COMMENT '个人订单数',
  `self_performance` decimal(15,2) NOT NULL COMMENT '个人业绩',
  `self_commission` decimal(15,2) NOT NULL COMMENT '个人佣金',
  `big_area_performance` decimal(15,2) NOT NULL COMMENT '大区业绩',
  `small_area_performance` decimal(15,2) NOT NULL COMMENT '小区业绩',
  `big_area_mid` int NOT NULL DEFAULT '0' COMMENT '大区线头ID',
  `balance_performance` decimal(15,2) NOT NULL COMMENT '平衡业绩',
  `current_level` int NOT NULL DEFAULT '0' COMMENT '当前等级',
  `level_commission` decimal(15,2) NOT NULL COMMENT '级差奖',
  `leadership_commission` decimal(15,2) NOT NULL COMMENT '领导奖',
  `special_bonus` decimal(15,2) NOT NULL COMMENT '特殊奖金',
  `last_order_time` int NOT NULL DEFAULT '0' COMMENT '最后订单时间',
  `calculation_time` decimal(10,4) NOT NULL COMMENT '计算耗时(秒)',
  `cache_version` int NOT NULL DEFAULT '1' COMMENT '缓存版本号',
  `is_valid` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否有效：1有效，0失效',
  `create_time` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_perf_cache_scope` (`aid`,`mid`,`cache_date`,`cache_type`,`relation_mode`,`amount_method`,`scope_hash`),
  KEY `aid_mid` (`aid`,`mid`) USING BTREE,
  KEY `cache_date` (`cache_date`) USING BTREE,
  KEY `cache_type` (`cache_type`) USING BTREE,
  KEY `update_time` (`update_time`) USING BTREE,
  KEY `team_performance` (`team_performance`) USING BTREE,
  KEY `current_level` (`current_level`) USING BTREE,
  KEY `idx_aid_date_type` (`aid`,`cache_date`,`cache_type`) USING BTREE,
  KEY `idx_performance_level` (`team_performance`,`current_level`) USING BTREE,
  KEY `idx_perf_cache_scope` (`aid`,`cache_date`,`cache_type`,`relation_mode`,`amount_method`,`scope_hash`)
) ENGINE=InnoDB AUTO_INCREMENT=209 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='用户业绩缓存主表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_poster`
--

DROP TABLE IF EXISTS `ddwx_member_poster`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_poster` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `mid` int DEFAULT NULL,
  `scene` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `page` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `type` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `poster` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `poster_hash` char(32) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '海报缓存签名',
  `createtime` int DEFAULT NULL,
  `posterid` int DEFAULT NULL,
  `qu` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE,
  KEY `type` (`type`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=5528 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_score3log`
--

DROP TABLE IF EXISTS `ddwx_member_score3log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_score3log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `aid` int NOT NULL,
  `mid` int NOT NULL,
  `score` decimal(15,5) NOT NULL,
  `after` decimal(15,5) NOT NULL,
  `createtime` int NOT NULL DEFAULT '0',
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `bid` int DEFAULT '0',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=2999 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_scorelog`
--

DROP TABLE IF EXISTS `ddwx_member_scorelog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_scorelog` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `mid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `score` decimal(11,2) DEFAULT NULL,
  `after` decimal(11,2) DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `type` tinyint NOT NULL DEFAULT '1' COMMENT '1普通，2允提',
  `channel` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '变动渠道',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE,
  KEY `type` (`type`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=732 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_scorelogguquan`
--

DROP TABLE IF EXISTS `ddwx_member_scorelogguquan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_scorelogguquan` (
  `id` int unsigned NOT NULL DEFAULT '0',
  `aid` int DEFAULT NULL,
  `mid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `score` decimal(11,2) DEFAULT NULL,
  `after` decimal(11,2) DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `type` tinyint NOT NULL DEFAULT '1' COMMENT '1鏅€氾紝2鍏佹彁',
  `channel` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '鍙樺姩娓犻亾',
  `lj_month` decimal(10,2) DEFAULT NULL COMMENT '累计处理消费到多少元了  用来计算',
  `bc_month` decimal(10,2) DEFAULT NULL COMMENT '处理的消费金额  '
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_scoreloghuang`
--

DROP TABLE IF EXISTS `ddwx_member_scoreloghuang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_scoreloghuang` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `mid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `score` decimal(11,2) DEFAULT NULL,
  `after` decimal(11,2) DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `type` tinyint NOT NULL DEFAULT '1' COMMENT '1鏅€氾紝2鍏佹彁',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态',
  `channel` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '鍙樺姩娓犻亾',
  `lj_month` decimal(10,2) DEFAULT NULL COMMENT '累计处理消费到多少元了  用来计算',
  `bc_month` decimal(10,2) DEFAULT NULL COMMENT '处理的消费金额  ',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=12119 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_scorelogjinbi`
--

DROP TABLE IF EXISTS `ddwx_member_scorelogjinbi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_scorelogjinbi` (
  `id` int unsigned NOT NULL DEFAULT '0',
  `aid` int DEFAULT NULL,
  `mid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `score` decimal(11,2) DEFAULT NULL,
  `after` decimal(11,2) DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `type` tinyint NOT NULL DEFAULT '1' COMMENT '1鏅€氾紝2鍏佹彁',
  `channel` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '鍙樺姩娓犻亾',
  `lj_month` decimal(10,2) DEFAULT NULL COMMENT '累计处理消费到多少元了  用来计算',
  `bc_month` decimal(10,2) DEFAULT NULL COMMENT '处理的消费金额  '
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_scorelogxiaofeizhi`
--

DROP TABLE IF EXISTS `ddwx_member_scorelogxiaofeizhi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_scorelogxiaofeizhi` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `mid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `score` decimal(11,2) DEFAULT NULL,
  `after` decimal(11,2) DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `type` tinyint NOT NULL DEFAULT '1' COMMENT '1鏅€氾紝2鍏佹彁',
  `channel` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '鍙樺姩娓犻亾',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=114 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_shengjiscorelog`
--

DROP TABLE IF EXISTS `ddwx_member_shengjiscorelog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_shengjiscorelog` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `mid` int DEFAULT NULL,
  `money` decimal(11,2) DEFAULT NULL,
  `after` decimal(11,2) DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=148 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_source`
--

DROP TABLE IF EXISTS `ddwx_member_source`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_source` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `aid` int NOT NULL DEFAULT '0' COMMENT '平台ID',
  `bid` int NOT NULL DEFAULT '0' COMMENT '商户ID',
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '来源名称',
  `icon` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '' COMMENT '来源图标',
  `color` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '#409EFF' COMMENT '来源颜色',
  `sort` int DEFAULT '0' COMMENT '排序',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态:1启用,0禁用',
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '' COMMENT '备注说明',
  `createtime` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `updatetime` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_aid_bid` (`aid`,`bid`) USING BTREE,
  KEY `idx_status` (`status`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='会员来源配置表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_syclog`
--

DROP TABLE IF EXISTS `ddwx_member_syclog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_syclog` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `aid` int NOT NULL,
  `mid` int NOT NULL,
  `money` decimal(11,2) NOT NULL,
  `after` decimal(11,2) NOT NULL,
  `createtime` int NOT NULL DEFAULT '0',
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `q` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_tag`
--

DROP TABLE IF EXISTS `ddwx_member_tag`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_tag` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '标签名称',
  `medal_image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '勋章图片URL',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '标签详细介绍',
  `type` int DEFAULT '1' COMMENT '1自动标签 2手动标签',
  `condition` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '' COMMENT '条件关系: or(或) and(与)',
  `status` int DEFAULT '1' COMMENT '状态: 0关闭 1开启',
  `aid` int DEFAULT '1' COMMENT '应用ID',
  `sort` int DEFAULT '0' COMMENT '排序',
  `regdatestatus` tinyint DEFAULT '0' COMMENT '注册时间条件是否启用',
  `mindays` int DEFAULT NULL COMMENT '注册时间最小天数',
  `maxdays` int DEFAULT '0' COMMENT '注册时间最大天数',
  `levelstatus` tinyint DEFAULT '0' COMMENT '等级条件是否启用',
  `levelid` int DEFAULT NULL COMMENT '指定会员等级ID',
  `buystatus` tinyint(1) DEFAULT '0' COMMENT '消费次数条件是否启用',
  `buynum` int DEFAULT '0' COMMENT '消费次数阈值',
  `buymoneystatus` tinyint(1) DEFAULT '0' COMMENT '消费金额条件是否启用',
  `buymoney` float(11,2) DEFAULT '0.00' COMMENT '消费金额阈值',
  `prostatus` tinyint(1) DEFAULT '0' COMMENT '指定商品条件是否启用',
  `weekincomestatus` tinyint(1) NOT NULL DEFAULT '0' COMMENT '当周收入条件开关(0:关闭, 1:开启)',
  `weekincome` decimal(10,2) NOT NULL COMMENT '当周佣金收入金额阈值',
  `custom_period_commission_status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '自定义时间段佣金条件开关(0:关闭, 1:开启)',
  `custom_period_commission_amount` decimal(10,2) NOT NULL COMMENT '自定义时间段佣金金额阈值',
  `custom_period_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '自定义时间段类型(1:天数, 2:月数, 3:年数, 4:指定日期范围)',
  `custom_period_value` int NOT NULL DEFAULT '30' COMMENT '自定义时间段数值(天数/月数/年数)',
  `custom_period_start_date` date DEFAULT NULL COMMENT '自定义开始日期(仅当类型为4时使用)',
  `custom_period_end_date` date DEFAULT NULL COMMENT '自定义结束日期(仅当类型为4时使用)',
  `is_upgradeable` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否可升级标签(0:否, 1:是)',
  `upgrade_target_tag_id` int NOT NULL DEFAULT '0' COMMENT '升级目标标签ID',
  `upgrade_required_days` int NOT NULL DEFAULT '30' COMMENT '升级所需连续天数',
  `upgrade_order_status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '下单升级开关 0关闭 1开启',
  `upgrade_order_num` int NOT NULL DEFAULT '0' COMMENT '下单升级所需订单数',
  `hold_days` int NOT NULL DEFAULT '15' COMMENT '标签保持天数(满足条件后保持多少天)',
  `remove_lower_on_upgrade` tinyint(1) NOT NULL DEFAULT '0' COMMENT '升级后移除低级别标签(0:保留,1:移除)',
  `enable_downgrade` tinyint(1) NOT NULL DEFAULT '0' COMMENT '开启降级功能(0:关闭,1:开启)',
  `downgrade_tag_id` int NOT NULL DEFAULT '0' COMMENT '降级目标标签ID(保持期结束且条件不满足时降级到此标签)',
  `productids` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '指定商品ID列表，逗号分隔',
  `createtime` int DEFAULT NULL COMMENT '创建时间',
  `member_count` int NOT NULL DEFAULT '0' COMMENT '会员数量',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_weekincome` (`weekincomestatus`,`weekincome`) USING BTREE,
  KEY `idx_upgrade` (`is_upgradeable`,`upgrade_target_tag_id`) USING BTREE,
  KEY `idx_custom_commission` (`custom_period_commission_status`,`custom_period_type`) USING BTREE,
  KEY `idx_medal` (`medal_image`(100)) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='会员标签表 - 支持按天数升级、标签保持天数、升级后移除低级标签等功能';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_tag_assignment`
--

DROP TABLE IF EXISTS `ddwx_member_tag_assignment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_tag_assignment` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `aid` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `mid` int NOT NULL DEFAULT '0' COMMENT '会员ID',
  `tag_id` int NOT NULL DEFAULT '0' COMMENT '标签ID',
  `assign_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '分配方式(1:自动分配, 2:手动分配)',
  `consecutive_days` int NOT NULL DEFAULT '0' COMMENT '连续持有天数',
  `last_check_time` int NOT NULL DEFAULT '0' COMMENT '最后检查时间',
  `assign_time` int NOT NULL DEFAULT '0' COMMENT '分配时间',
  `obtain_time` int NOT NULL DEFAULT '0' COMMENT '标签获得时间',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态(0:已移除, 1:持有中)',
  `createtime` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `updatetime` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `unique_ddwx_member_tag` (`aid`,`mid`,`tag_id`) USING BTREE,
  KEY `idx_member` (`aid`,`mid`) USING BTREE,
  KEY `idx_tag` (`tag_id`) USING BTREE,
  KEY `idx_status` (`status`) USING BTREE,
  KEY `idx_consecutive` (`consecutive_days`) USING BTREE,
  KEY `idx_consecutive_days` (`consecutive_days`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='会员标签持有记录表 - 记录会员持有标签的连续天数';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_tag_record`
--

DROP TABLE IF EXISTS `ddwx_member_tag_record`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_tag_record` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `aid` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `mid` int NOT NULL DEFAULT '0' COMMENT '会员ID',
  `tag_id` int NOT NULL DEFAULT '0' COMMENT '标签ID',
  `assign_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '分配方式(1:自动分配, 2:手动分配)',
  `assign_time` int NOT NULL DEFAULT '0' COMMENT '分配时间',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态(0:已移除, 1:持有中)',
  `createtime` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `updatetime` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '备注信息',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `unique_member_tag` (`aid`,`mid`,`tag_id`) USING BTREE,
  KEY `idx_member` (`aid`,`mid`) USING BTREE,
  KEY `idx_tag` (`tag_id`) USING BTREE,
  KEY `idx_status` (`status`) USING BTREE,
  KEY `idx_assign_time` (`assign_time`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='会员标签记录表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_tag_upgrade_log`
--

DROP TABLE IF EXISTS `ddwx_member_tag_upgrade_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_tag_upgrade_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `aid` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `mid` int NOT NULL DEFAULT '0' COMMENT '会员ID',
  `from_tag_id` int NOT NULL DEFAULT '0' COMMENT '原标签ID',
  `to_tag_id` int NOT NULL DEFAULT '0' COMMENT '升级后标签ID',
  `consecutive_days` int NOT NULL DEFAULT '0' COMMENT '连续持有天数',
  `upgrade_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '升级原因',
  `removed_lower_tags` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '被移除的低级标签ID(逗号分隔)',
  `week_start` int NOT NULL DEFAULT '0' COMMENT '升级周期开始时间',
  `week_end` int NOT NULL DEFAULT '0' COMMENT '升级周期结束时间',
  `createtime` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_member` (`aid`,`mid`) USING BTREE,
  KEY `idx_from_tag` (`from_tag_id`) USING BTREE,
  KEY `idx_to_tag` (`to_tag_id`) USING BTREE,
  KEY `idx_upgrade_time` (`createtime`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='标签升级历史记录表 - 记录升级详情和被移除的低级标签';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_tag_upgrade_progress`
--

DROP TABLE IF EXISTS `ddwx_member_tag_upgrade_progress`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_tag_upgrade_progress` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `aid` int NOT NULL DEFAULT '1' COMMENT '应用ID',
  `mid` int NOT NULL COMMENT '会员ID',
  `from_tag_id` int NOT NULL COMMENT '前置标签ID',
  `to_tag_id` int NOT NULL COMMENT '目标标签ID',
  `consecutive_weeks` int NOT NULL DEFAULT '0' COMMENT '连续满足条件的周数',
  `required_weeks` int NOT NULL DEFAULT '4' COMMENT '升级所需周数',
  `last_check_time` int NOT NULL DEFAULT '0' COMMENT '最后检查时间',
  `week_start` int NOT NULL DEFAULT '0' COMMENT '当周开始时间',
  `week_end` int NOT NULL DEFAULT '0' COMMENT '当周结束时间',
  `createtime` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `updatetime` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_aid_mid` (`aid`,`mid`) USING BTREE,
  KEY `idx_from_to_tag` (`from_tag_id`,`to_tag_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='会员标签升级进度表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_team_lines_cache`
--

DROP TABLE IF EXISTS `ddwx_member_team_lines_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_team_lines_cache` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `aid` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `parent_mid` int NOT NULL DEFAULT '0' COMMENT '上级会员ID',
  `line_mid` int NOT NULL DEFAULT '0' COMMENT '线路会员ID',
  `cache_date` date NOT NULL COMMENT '缓存日期',
  `line_level` int NOT NULL DEFAULT '1' COMMENT '线路层级',
  `line_position` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '' COMMENT '线路位置(left/right等)',
  `is_big_area` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否大区：1是，0否',
  `line_team_count` int NOT NULL DEFAULT '0' COMMENT '线路团队人数',
  `line_team_performance` decimal(15,2) NOT NULL COMMENT '线路团队业绩',
  `line_self_performance` decimal(15,2) NOT NULL COMMENT '线路个人业绩',
  `line_commission` decimal(15,2) NOT NULL COMMENT '线路佣金贡献',
  `calculation_time` decimal(10,4) NOT NULL COMMENT '计算耗时(秒)',
  `is_valid` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否有效',
  `create_time` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `aid_parent_line_date` (`aid`,`parent_mid`,`line_mid`,`cache_date`) USING BTREE,
  KEY `aid_parent_mid` (`aid`,`parent_mid`) USING BTREE,
  KEY `line_mid` (`line_mid`) USING BTREE,
  KEY `cache_date` (`cache_date`) USING BTREE,
  KEY `is_big_area` (`is_big_area`) USING BTREE,
  KEY `idx_parent_date_area` (`parent_mid`,`cache_date`,`is_big_area`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='用户团队线路缓存表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_team_reward_log`
--

DROP TABLE IF EXISTS `ddwx_member_team_reward_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_team_reward_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int NOT NULL COMMENT '应用ID',
  `mid` int NOT NULL COMMENT '会员ID',
  `type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '类型：1=增加，2=扣减',
  `amount` decimal(11,2) NOT NULL COMMENT '变动金额',
  `balance_before` decimal(11,2) NOT NULL COMMENT '变动前余额',
  `balance_after` decimal(11,2) NOT NULL COMMENT '变动后余额',
  `remark` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '' COMMENT '备注说明',
  `order_id` int NOT NULL DEFAULT '0' COMMENT '关联订单ID',
  `createtime` int NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_aid_mid` (`aid`,`mid`) USING BTREE,
  KEY `idx_createtime` (`createtime`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='可抵扣团队奖励余额变动日志表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_tjscore`
--

DROP TABLE IF EXISTS `ddwx_member_tjscore`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_tjscore` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `mid` int DEFAULT NULL,
  `frommid` int DEFAULT NULL,
  `score` int DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_tmplnum`
--

DROP TABLE IF EXISTS `ddwx_member_tmplnum`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_tmplnum` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `mid` int DEFAULT NULL,
  `tmplid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `num` int DEFAULT '0',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=109 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_upload`
--

DROP TABLE IF EXISTS `ddwx_member_upload`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_upload` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `mid` int DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `dir` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `url` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `type` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `size` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `bsize` int DEFAULT NULL,
  `width` int DEFAULT NULL,
  `height` int DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_uplv_event`
--

DROP TABLE IF EXISTS `ddwx_member_uplv_event`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_uplv_event` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '升级事件ID',
  `aid` int NOT NULL DEFAULT '0' COMMENT '平台ID',
  `origin_mid` int NOT NULL DEFAULT '0' COMMENT '原始触发会员ID',
  `scene` varchar(32) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '触发场景',
  `source_type` varchar(32) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '业务来源类型',
  `source_id` varchar(64) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '业务来源ID',
  `business_version` varchar(64) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '业务状态版本',
  `trigger_key` char(64) COLLATE utf8mb4_general_ci NOT NULL COMMENT '事件幂等键SHA-256',
  `request_trigger_key` varchar(128) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '调用方触发标识，仅供审计',
  `context_json` longtext COLLATE utf8mb4_general_ci COMMENT '已校验的业务上下文快照JSON',
  `status` varchar(32) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending_plan' COMMENT 'shadow_recorded/pending_plan/planning/processing/completed/partial_failed/failed/closed',
  `target_total` int NOT NULL DEFAULT '0' COMMENT '规划目标总数',
  `success_count` int NOT NULL DEFAULT '0' COMMENT '成功目标数',
  `failed_count` int NOT NULL DEFAULT '0' COMMENT '失败目标数',
  `skipped_count` int NOT NULL DEFAULT '0' COMMENT '业务跳过目标数',
  `worker_token` char(32) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '规划worker令牌',
  `lease_expire_time` datetime DEFAULT NULL COMMENT '规划租约到期时间',
  `retry_count` int NOT NULL DEFAULT '0' COMMENT '规划失败次数',
  `next_retry_time` datetime DEFAULT NULL COMMENT '下次允许规划时间',
  `last_error_code` varchar(32) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '最后错误码',
  `error_msg` text COLLATE utf8mb4_general_ci COMMENT '最后错误信息',
  `created_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `end_time` datetime DEFAULT NULL COMMENT '终态时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_aid_trigger_key` (`aid`,`trigger_key`),
  KEY `idx_aid_status_retry` (`aid`,`status`,`next_retry_time`,`id`),
  KEY `idx_aid_origin_created` (`aid`,`origin_mid`,`created_time`,`id`),
  KEY `idx_lease_expire` (`status`,`lease_expire_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='会员升级业务事件表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_uplv_outbox`
--

DROP TABLE IF EXISTS `ddwx_member_uplv_outbox`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_uplv_outbox` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '通知outbox ID',
  `settlement_id` bigint unsigned NOT NULL COMMENT '升级结算ID',
  `aid` int NOT NULL DEFAULT '0' COMMENT '平台ID',
  `mid` int NOT NULL DEFAULT '0' COMMENT '通知目标会员ID',
  `notify_type` varchar(32) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '通知类型',
  `notify_key` varchar(128) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '通知子项标识',
  `outbox_dedup_key` char(64) COLLATE utf8mb4_general_ci NOT NULL COMMENT '通知幂等键SHA-256',
  `payload_json` longtext COLLATE utf8mb4_general_ci COMMENT '提交后发送负载JSON',
  `status` varchar(32) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending' COMMENT 'pending/processing/succeeded/failed/unknown',
  `external_receipt` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '第三方回执',
  `worker_token` char(32) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '通知worker令牌',
  `lease_expire_time` datetime DEFAULT NULL COMMENT '通知租约到期时间',
  `attempt_count` int NOT NULL DEFAULT '0' COMMENT '发送尝试次数',
  `next_retry_time` datetime DEFAULT NULL COMMENT '下次允许发送时间',
  `last_error_code` varchar(32) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '最后错误码',
  `error_msg` text COLLATE utf8mb4_general_ci COMMENT '最后错误信息',
  `reconciled_time` datetime DEFAULT NULL COMMENT 'unknown 对账完成时间',
  `reconciliation_note` text COLLATE utf8mb4_general_ci COMMENT 'unknown 对账证据与结论',
  `created_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `succeeded_time` datetime DEFAULT NULL COMMENT '发送成功时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_outbox_dedup_key` (`outbox_dedup_key`),
  KEY `idx_settlement_status` (`settlement_id`,`status`,`id`),
  KEY `idx_status_retry` (`status`,`next_retry_time`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='会员升级提交后通知outbox';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_uplv_outbox_attempt`
--

DROP TABLE IF EXISTS `ddwx_member_uplv_outbox_attempt`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_uplv_outbox_attempt` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '通知投递尝试ID',
  `outbox_id` bigint unsigned NOT NULL COMMENT '通知outbox ID',
  `settlement_id` bigint unsigned NOT NULL COMMENT '升级结算ID',
  `aid` int NOT NULL DEFAULT '0' COMMENT '平台ID',
  `mid` int NOT NULL DEFAULT '0' COMMENT '通知目标会员ID',
  `notify_type` varchar(32) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '通知类型',
  `adapter` varchar(32) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '发送适配器',
  `delivery_semantics` varchar(32) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '状态同步/本地效果/外部消息',
  `unknown_policy` varchar(32) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '结果未知后的处理策略',
  `attempt_no` int NOT NULL DEFAULT '1' COMMENT '该outbox第几次尝试',
  `attempt_key` char(64) COLLATE utf8mb4_general_ci NOT NULL COMMENT '投递尝试稳定键SHA-256',
  `payload_digest` char(64) COLLATE utf8mb4_general_ci NOT NULL COMMENT '冻结负载摘要SHA-256',
  `status` varchar(32) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'prepared' COMMENT 'prepared/sending/succeeded/failed/unknown',
  `external_receipt` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '可用时保存外部回执',
  `error_msg` text COLLATE utf8mb4_general_ci COMMENT '错误或未知结果说明',
  `started_time` datetime DEFAULT NULL COMMENT '开始发送时间',
  `finished_time` datetime DEFAULT NULL COMMENT '结束时间',
  `reconciled_time` datetime DEFAULT NULL COMMENT 'unknown 对账时间',
  `reconciliation_note` text COLLATE utf8mb4_general_ci COMMENT 'unknown 对账证据与结论',
  `created_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_outbox_attempt_no` (`outbox_id`,`attempt_no`),
  UNIQUE KEY `uniq_outbox_attempt_key` (`attempt_key`),
  KEY `idx_attempt_status` (`status`,`updated_time`,`id`),
  KEY `idx_attempt_settlement` (`settlement_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='会员升级通知投递尝试账本';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_uplv_queue`
--

DROP TABLE IF EXISTS `ddwx_member_uplv_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_uplv_queue` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `event_id` bigint unsigned DEFAULT NULL COMMENT '升级事件ID，历史任务为空',
  `aid` int NOT NULL COMMENT '应用ID',
  `mid` int NOT NULL COMMENT '用户ID',
  `target_mid` int DEFAULT NULL COMMENT '实际升级检查会员ID',
  `orderid` int DEFAULT '0' COMMENT '订单ID',
  `levelid` int DEFAULT '0' COMMENT '等级ID',
  `relation_type` varchar(16) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '升级链路 self/recommend/jiedian',
  `relation_types` longtext COLLATE utf8mb4_general_ci COMMENT '命中的推荐/接点关系JSON',
  `scene` varchar(32) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'legacy' COMMENT '升级触发场景',
  `source_type` varchar(32) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '业务来源类型',
  `source_id` varchar(64) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '业务来源ID',
  `trigger_key` char(64) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '触发来源标识',
  `context_snapshot` longtext COLLATE utf8mb4_general_ci COMMENT '目标执行上下文快照JSON',
  `active_dedup_key` char(64) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '活动任务唯一键，终态清空',
  `worker_token` char(32) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '任务认领worker标识',
  `lease_expire_time` datetime DEFAULT NULL COMMENT '任务认领租约到期时间',
  `next_retry_time` datetime DEFAULT NULL COMMENT '下次允许重试时间',
  `blocked_by_queue_id` int DEFAULT NULL COMMENT '阻塞当前任务的更早队列ID',
  `team_count` int DEFAULT '0' COMMENT '团队人数',
  `team_count_recommend` int DEFAULT NULL COMMENT '推荐团队人数快照，NULL表示未知',
  `team_count_jiedian` int DEFAULT NULL COMMENT '接点团队人数快照，NULL表示未知',
  `snapshot_version` varchar(64) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '团队及业务快照版本',
  `status` tinyint(1) DEFAULT '0' COMMENT '状态：0待处理，1处理中，2已完成，3失败',
  `retry_count` int DEFAULT '0' COMMENT '重试次数',
  `error_msg` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '错误信息',
  `last_error_code` varchar(32) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '最后错误分类',
  `result_code` varchar(32) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT 'upgraded/already_satisfied/not_eligible/audit_closed',
  `priority` tinyint(1) DEFAULT '1' COMMENT '优先级：1普通，2高，3紧急',
  `created_time` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `start_time` datetime DEFAULT NULL COMMENT '开始处理时间',
  `end_time` datetime DEFAULT NULL COMMENT '完成时间',
  `updated_time` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `processed_time` datetime DEFAULT NULL COMMENT '处理完成时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uniq_active_dedup_key` (`active_dedup_key`),
  UNIQUE KEY `uniq_event_target` (`event_id`,`target_mid`),
  KEY `idx_status_created` (`status`,`created_time`) USING BTREE,
  KEY `idx_aid_mid` (`aid`,`mid`) USING BTREE,
  KEY `idx_priority_status` (`priority`,`status`) USING BTREE,
  KEY `idx_status_retry_priority` (`status`,`next_retry_time`,`priority`,`created_time`),
  KEY `idx_lease_expire` (`status`,`lease_expire_time`),
  KEY `idx_aid_target_order` (`aid`,`target_mid`,`status`,`event_id`,`id`),
  KEY `idx_aid_target_legacy_order` (`aid`,`target_mid`,`status`,`created_time`,`id`),
  KEY `idx_blocked_by_queue` (`blocked_by_queue_id`)
) ENGINE=InnoDB AUTO_INCREMENT=486 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='用户升级队列表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_uplv_reward_effect`
--

DROP TABLE IF EXISTS `ddwx_member_uplv_reward_effect`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_uplv_reward_effect` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '奖励实际效果ID',
  `settlement_id` bigint unsigned NOT NULL COMMENT '升级结算ID',
  `reward_item_id` bigint unsigned NOT NULL COMMENT '奖励项ID',
  `aid` int NOT NULL DEFAULT '0' COMMENT '平台ID',
  `mid` int NOT NULL DEFAULT '0' COMMENT '资产接收会员ID',
  `reward_type` varchar(32) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '奖励类型',
  `item_dedup_key` char(64) COLLATE utf8mb4_general_ci NOT NULL COMMENT '奖励项幂等键SHA-256',
  `asset_field` varchar(32) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '会员资产字段',
  `amount` decimal(20,5) NOT NULL DEFAULT '0.00000' COMMENT '实际变动金额',
  `before_amount` decimal(20,5) NOT NULL DEFAULT '0.00000' COMMENT '变动前金额',
  `after_amount` decimal(20,5) NOT NULL DEFAULT '0.00000' COMMENT '变动后金额',
  `effect_ref_type` varchar(32) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '兼容业务流水类型',
  `effect_ref_id` varchar(128) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '兼容业务流水ID',
  `effect_snapshot_json` longtext COLLATE utf8mb4_general_ci COMMENT '复合奖励实际分配快照JSON',
  `created_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '效果提交时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_effect_item_dedup_key` (`item_dedup_key`),
  UNIQUE KEY `uniq_effect_reward_item` (`reward_item_id`),
  KEY `idx_effect_settlement` (`settlement_id`,`id`),
  KEY `idx_effect_aid_mid` (`aid`,`mid`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='会员升级奖励实际效果账本';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_uplv_reward_effect_detail`
--

DROP TABLE IF EXISTS `ddwx_member_uplv_reward_effect_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_uplv_reward_effect_detail` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '复合奖励效果明细ID',
  `effect_id` bigint unsigned NOT NULL COMMENT '奖励主效果ID',
  `settlement_id` bigint unsigned NOT NULL COMMENT '升级结算ID',
  `reward_item_id` bigint unsigned NOT NULL COMMENT '奖励项ID',
  `aid` int NOT NULL DEFAULT '0' COMMENT '平台ID',
  `mid` int NOT NULL DEFAULT '0' COMMENT '资产接收会员ID',
  `effect_key` varchar(32) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '贡献值/收益池/积分/佣金等固定效果键',
  `asset_field` varchar(32) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '会员资产字段',
  `amount` decimal(20,5) NOT NULL DEFAULT '0.00000' COMMENT '实际有符号变动金额',
  `before_amount` decimal(20,5) NOT NULL DEFAULT '0.00000' COMMENT '变动前金额',
  `after_amount` decimal(20,5) NOT NULL DEFAULT '0.00000' COMMENT '变动后金额',
  `effect_ref_type` varchar(32) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '兼容业务流水类型',
  `effect_ref_id` varchar(128) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '兼容业务流水ID',
  `created_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '效果明细提交时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_reward_item_effect_key` (`reward_item_id`,`effect_key`),
  KEY `idx_effect_id` (`effect_id`,`id`),
  KEY `idx_settlement_effect` (`settlement_id`,`effect_key`,`id`),
  KEY `idx_aid_mid_effect` (`aid`,`mid`,`effect_key`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='会员升级复合奖励效果明细账本';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_uplv_reward_item`
--

DROP TABLE IF EXISTS `ddwx_member_uplv_reward_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_uplv_reward_item` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '逐项奖励ID',
  `settlement_id` bigint unsigned NOT NULL COMMENT '升级结算ID',
  `aid` int NOT NULL DEFAULT '0' COMMENT '平台ID',
  `reward_type` varchar(32) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '奖励或副作用类型',
  `reward_target_mid` int NOT NULL DEFAULT '0' COMMENT '奖励接收会员ID',
  `reward_item_key` varchar(128) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '券/资产/层级/动作子项标识',
  `reward_sequence` int NOT NULL DEFAULT '1' COMMENT '同规则重复发放序号',
  `item_dedup_key` char(64) COLLATE utf8mb4_general_ci NOT NULL COMMENT '奖励项幂等键SHA-256',
  `plan_snapshot_json` longtext COLLATE utf8mb4_general_ci COMMENT '冻结的奖励计划JSON',
  `status` varchar(32) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending' COMMENT 'pending/processing/succeeded/failed/unknown',
  `effect_ref_type` varchar(32) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '资产流水或外部回执类型',
  `effect_ref_id` varchar(128) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '资产流水或外部回执ID',
  `worker_token` char(32) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '奖励认领worker令牌',
  `lease_expire_time` datetime DEFAULT NULL COMMENT '奖励租约到期时间',
  `attempt_count` int NOT NULL DEFAULT '0' COMMENT '执行尝试次数',
  `next_retry_time` datetime DEFAULT NULL COMMENT '下次允许执行时间',
  `last_error_code` varchar(32) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '最后错误码',
  `error_msg` text COLLATE utf8mb4_general_ci COMMENT '最后错误信息',
  `started_time` datetime DEFAULT NULL COMMENT '首次开始时间',
  `succeeded_time` datetime DEFAULT NULL COMMENT '成功时间',
  `reconciled_time` datetime DEFAULT NULL COMMENT 'unknown 对账完成时间',
  `reconciliation_note` text COLLATE utf8mb4_general_ci COMMENT 'unknown 对账证据与结论',
  `created_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_item_dedup_key` (`item_dedup_key`),
  KEY `idx_settlement_status` (`settlement_id`,`status`,`id`),
  KEY `idx_aid_target_created` (`aid`,`reward_target_mid`,`created_time`,`id`),
  KEY `idx_status_retry` (`status`,`next_retry_time`,`id`),
  KEY `idx_lease_expire` (`status`,`lease_expire_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='会员升级逐项奖励幂等表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_uplv_settlement`
--

DROP TABLE IF EXISTS `ddwx_member_uplv_settlement`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_uplv_settlement` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '升级结算ID',
  `event_id` bigint unsigned DEFAULT NULL COMMENT '升级事件ID',
  `queue_id` int DEFAULT NULL COMMENT '目标任务ID',
  `original_queue_id` int DEFAULT NULL COMMENT '人工补偿关联的历史任务ID',
  `aid` int NOT NULL DEFAULT '0' COMMENT '平台ID',
  `mid` int NOT NULL DEFAULT '0' COMMENT '升级会员ID',
  `before_levelid` int NOT NULL DEFAULT '0' COMMENT '升级前等级ID',
  `target_levelid` int NOT NULL DEFAULT '0' COMMENT '目标等级ID',
  `cycle_no` int NOT NULL DEFAULT '1' COMMENT '同会员同等级升级周期',
  `settlement_key` char(64) COLLATE utf8mb4_general_ci NOT NULL COMMENT '等级变化幂等键SHA-256',
  `level_status` varchar(32) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending' COMMENT 'pending/processing/committed/failed',
  `reward_status` varchar(32) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending' COMMENT 'pending/processing/succeeded/partial_failed/unknown',
  `notify_status` varchar(32) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending' COMMENT 'pending/processing/succeeded/partial_failed/unknown',
  `rule_snapshot_json` longtext COLLATE utf8mb4_general_ci COMMENT '首次确认的等级及奖励规则快照JSON',
  `context_snapshot` longtext COLLATE utf8mb4_general_ci COMMENT '升级来源上下文快照JSON',
  `worker_token` char(32) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '当前结算worker令牌',
  `lease_expire_time` datetime DEFAULT NULL COMMENT '结算租约到期时间',
  `retry_count` int NOT NULL DEFAULT '0' COMMENT '结算失败次数',
  `next_retry_time` datetime DEFAULT NULL COMMENT '下次允许结算时间',
  `last_error_code` varchar(32) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '最后错误码',
  `error_msg` text COLLATE utf8mb4_general_ci COMMENT '最后错误信息',
  `level_committed_time` datetime DEFAULT NULL COMMENT '等级提交时间',
  `level_reconciled_time` datetime DEFAULT NULL COMMENT '等级结果最后对账时间',
  `level_reconciliation_note` text COLLATE utf8mb4_general_ci COMMENT '等级结果对账证据与结论',
  `rewards_settled_time` datetime DEFAULT NULL COMMENT '奖励全部完成时间',
  `created_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `end_time` datetime DEFAULT NULL COMMENT '结算终态时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_settlement_key` (`settlement_key`),
  KEY `idx_aid_mid_created` (`aid`,`mid`,`created_time`,`id`),
  KEY `idx_event_id` (`event_id`),
  KEY `idx_queue_id` (`queue_id`),
  KEY `idx_level_status_retry` (`level_status`,`next_retry_time`,`id`),
  KEY `idx_reward_status` (`reward_status`,`updated_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='会员升级幂等结算表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_withdrawlog`
--

DROP TABLE IF EXISTS `ddwx_member_withdrawlog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_withdrawlog` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `mid` int DEFAULT NULL,
  `money` decimal(11,2) DEFAULT NULL,
  `txmoney` decimal(11,2) DEFAULT NULL,
  `aliaccount` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `aliaccountname` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '支付宝姓名',
  `ordernum` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `paytype` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `status` tinyint(1) DEFAULT '0',
  `createtime` int DEFAULT NULL,
  `bankname` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `bankcarduser` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `bankcardnum` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `paytime` int DEFAULT NULL,
  `paynum` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `platform` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT 'wx',
  `reason` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `deduct_contribution` decimal(10,2) NOT NULL COMMENT '扣减的贡献值',
  `is_tk` tinyint DEFAULT NULL,
  `wx_package_info` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `wx_transfer_bill_no` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `wx_transfer_msg` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `wx_state` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE,
  KEY `createtime` (`createtime`) USING BTREE,
  KEY `status` (`status`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=200 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_xiaofeiblog`
--

DROP TABLE IF EXISTS `ddwx_member_xiaofeiblog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_xiaofeiblog` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `aid` int NOT NULL DEFAULT '0' COMMENT '平台ID',
  `mid` int NOT NULL DEFAULT '0' COMMENT '会员ID',
  `money` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '变动金额',
  `after` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '变动后余额',
  `createtime` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `remark` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '备注',
  PRIMARY KEY (`id`),
  KEY `idx_aid_mid` (`aid`,`mid`),
  KEY `idx_createtime` (`createtime`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='会员消费币明细';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_xingjilog`
--

DROP TABLE IF EXISTS `ddwx_member_xingjilog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_xingjilog` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `mid` int NOT NULL,
  `money` decimal(11,2) NOT NULL,
  `formid` int NOT NULL,
  `afterxingji` tinyint(1) NOT NULL,
  `xingji` tinyint NOT NULL,
  `createtime` int NOT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_member_zijifhlog`
--

DROP TABLE IF EXISTS `ddwx_member_zijifhlog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_member_zijifhlog` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `aid` int NOT NULL,
  `mid` int NOT NULL,
  `money` decimal(11,2) NOT NULL,
  `after` decimal(11,2) DEFAULT NULL,
  `createtime` int NOT NULL DEFAULT '0',
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_membercard`
--

DROP TABLE IF EXISTS `ddwx_membercard`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_membercard` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int NOT NULL,
  `card_id` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '会员卡编号',
  `ret_url` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '领卡地址',
  `card_type` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '卡的类型 默认是  MEMBER_CARD',
  `logo_url` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '会员卡logo',
  `code_type` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '展示类型 CODE_TYPE_TEXT 文本',
  `brand_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '商户名字',
  `title` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '卡券名',
  `bg_type` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `color` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '券颜色  Color010 默认',
  `background_pic_url` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '会员卡背景图',
  `notice` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '卡券使用提醒',
  `description` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '卡券使用说明',
  `custom_field` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `bonus_rules` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `quantity` int DEFAULT '99999999' COMMENT '卡券库存的数量 上限为100000000',
  `date_info_type` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT 'DATE_TYPE_PERMANENT',
  `date_info_begin_time` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `date_info_end_time` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `date_info_fixed_begin_term` int DEFAULT NULL,
  `date_info_fixed_term` int DEFAULT NULL,
  `service_phone` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '客服电话',
  `location_id_list` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '门店位置ID',
  `use_all_locations` tinyint(1) DEFAULT '0' COMMENT '会员卡是否支持全部门店',
  `center_title` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '快速买单' COMMENT '卡券中部居中的按钮',
  `center_sub_title` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '买单即享优惠' COMMENT '显示在入口下方的提示语',
  `center_url` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '顶部居中的url',
  `custom_url_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `custom_url` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `custom_url_sub_title` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `promotion_url_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `promotion_url` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `promotion_url_sub_title` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `custom_cell1_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `custom_cell1_tips` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `custom_cell1_url` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `get_limit` int DEFAULT '1',
  `can_share` tinyint(1) DEFAULT '0',
  `can_give_friend` tinyint(1) DEFAULT '0',
  `prerogative` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '特权说明',
  `field_list` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `auto_activate` tinyint(1) DEFAULT '1',
  `wx_activate` tinyint(1) DEFAULT '1',
  `activate_url` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `activate_app_brand_user_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `activate_app_brand_pass` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `status` int DEFAULT '0' COMMENT '0 删除 1正常',
  `RefuseReason` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  `custom_field_customize1_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '自定义1',
  `custom_field_customize1_value` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '查看',
  `custom_field_customize1_link` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `custom_field_customize2_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '自定义2',
  `custom_field_customize2_value` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '查看',
  `custom_field_customize2_link` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `remark` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `card_id` (`card_id`) USING BTREE,
  KEY `status` (`status`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_membercard_record`
--

DROP TABLE IF EXISTS `ddwx_membercard_record`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_membercard_record` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `card_id` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `card_code` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `mid` int DEFAULT NULL,
  `openid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `unionid` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `nickname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `headimg` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `mobile` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `idcard` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `sex` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `birthday` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `location` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `education` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `industry` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `income` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `habit` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `field1` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `field2` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `field3` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `field4` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `field5` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `status` tinyint(1) DEFAULT '1' COMMENT '1正常 2删除 3注销',
  `createtime` int DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `card_id` (`card_id`) USING BTREE,
  KEY `card_code` (`card_code`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE,
  KEY `openid` (`openid`) USING BTREE,
  KEY `unionid` (`unionid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_membercard_sendscorelog`
--

DROP TABLE IF EXISTS `ddwx_membercard_sendscorelog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_membercard_sendscorelog` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `card_id` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `score` int DEFAULT NULL,
  `remark` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `createtime` int DEFAULT NULL,
  `sendcount` int DEFAULT NULL,
  `successcount` int DEFAULT NULL,
  `errorcount` int DEFAULT NULL,
  `endtime` int DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `card_id` (`card_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_mendian`
--

DROP TABLE IF EXISTS `ddwx_mendian`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_mendian` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `bid` int DEFAULT '0',
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `subname` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `pic` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `pics` varchar(1000) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `content` longtext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `tel` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `area` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `province_id` int NOT NULL DEFAULT '0' COMMENT '省份ID',
  `city_id` int NOT NULL DEFAULT '0' COMMENT '城市ID',
  `district_id` int NOT NULL DEFAULT '0' COMMENT '区县ID',
  `group_id` int NOT NULL DEFAULT '0' COMMENT '分组ID',
  `level_id` int NOT NULL DEFAULT '0' COMMENT '等级ID',
  `city_ids` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '' COMMENT '可见城市ID，多个用逗号分隔',
  `address` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `longitude` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '经度',
  `latitude` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '纬度',
  `commission_money_type` tinyint(1) DEFAULT '0' COMMENT '分成类型：0比例 1固定',
  `commission_money_percent` decimal(5,2) DEFAULT NULL,
  `commission_money` decimal(11,2) DEFAULT NULL,
  `commission_score_type` tinyint(1) DEFAULT '0' COMMENT '积分分成类型：0比例 1固定',
  `commission_score_percent` decimal(5,2) DEFAULT NULL,
  `commission_score` int DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  `status` tinyint(1) DEFAULT '1',
  `hexiaogivepercent` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '门店核销奖励比例(%)',
  `hexiaogivemoney` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '门店核销固定奖励(元)',
  `sort` int DEFAULT '0',
  `code_prefix` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT 'A' COMMENT '编码前缀',
  `code_max_layer` int DEFAULT '5' COMMENT '最大层数',
  `code_max_number` int DEFAULT '50' COMMENT '每层最大编号',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=488 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_mendian_group`
--

DROP TABLE IF EXISTS `ddwx_mendian_group`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_mendian_group` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int NOT NULL DEFAULT '0' COMMENT '商家ID',
  `bid` int NOT NULL DEFAULT '0' COMMENT '品牌ID',
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '分组名称',
  `sort` int NOT NULL DEFAULT '0' COMMENT '排序(数字越大越靠前)',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态:1启用 0禁用',
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '备注说明',
  `createtime` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `updatetime` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='门店分组表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_mendian_level`
--

DROP TABLE IF EXISTS `ddwx_mendian_level`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_mendian_level` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int NOT NULL DEFAULT '0' COMMENT '商家ID',
  `bid` int NOT NULL DEFAULT '0' COMMENT '品牌ID',
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '等级名称',
  `icon` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '等级图标',
  `sort` int NOT NULL DEFAULT '0' COMMENT '排序(数字越大越靠前)',
  `benefits` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '等级权益说明',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态:1启用 0禁用',
  `createtime` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `updatetime` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='门店等级表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_mendian_set`
--

DROP TABLE IF EXISTS `ddwx_mendian_set`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_mendian_set` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int NOT NULL DEFAULT '0' COMMENT '商家ID',
  `bid` int NOT NULL DEFAULT '0' COMMENT '品牌ID',
  `apply_switch` tinyint(1) NOT NULL DEFAULT '1' COMMENT '门店申请开关 1开启 0关闭',
  `service_distance` decimal(10,2) NOT NULL COMMENT '门店服务距离',
  `show_comment_address` tinyint(1) NOT NULL DEFAULT '1' COMMENT '前端显示评论地址 1显示 0隐藏',
  `order_notify` tinyint(1) NOT NULL DEFAULT '1' COMMENT '订单通知 1开启 0关闭',
  `unship_order_link` tinyint(1) NOT NULL DEFAULT '1' COMMENT '未发货订单续联 1开启 0关闭',
  `member_level` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '会员等级',
  `city_switch` tinyint(1) NOT NULL DEFAULT '0' COMMENT '城市筛选开关 1开启 0关闭',
  `default_city_id` int NOT NULL DEFAULT '0' COMMENT '默认城市ID',
  `createtime` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `updatetime` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='门店设置表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_message_category`
--

DROP TABLE IF EXISTS `ddwx_message_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_message_category` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '分类ID',
  `aid` int NOT NULL DEFAULT '0' COMMENT '应用ID',
  `bid` int NOT NULL DEFAULT '0' COMMENT '商户ID',
  `pid` int NOT NULL DEFAULT '0' COMMENT '父级分类ID',
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '分类名称',
  `pic` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '分类图标',
  `sort` int NOT NULL DEFAULT '0' COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态 1开启 0关闭',
  `createtime` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE,
  KEY `pid` (`pid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='消息通知分类表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_message_notify`
--

DROP TABLE IF EXISTS `ddwx_message_notify`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_message_notify` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '消息ID',
  `aid` int NOT NULL DEFAULT '0' COMMENT '应用ID',
  `bid` int NOT NULL DEFAULT '0' COMMENT '商户ID',
  `cid` int NOT NULL DEFAULT '0' COMMENT '分类ID',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '消息标题',
  `subname` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '消息描述',
  `content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '消息内容',
  `pic` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '消息图片',
  `author` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '发布者',
  `readcount` int NOT NULL DEFAULT '0' COMMENT '阅读量',
  `unread_count` int NOT NULL DEFAULT '0' COMMENT '未读用户数量',
  `zan` int NOT NULL DEFAULT '0' COMMENT '点赞数',
  `sort` int NOT NULL DEFAULT '0' COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态 1开启 0关闭',
  `push_status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '推送状态：0=未推送,1=已推送',
  `push_time` int NOT NULL DEFAULT '0' COMMENT '推送时间',
  `push_channels` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '推送渠道：wx,mp,sms（逗号分隔）',
  `target_type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '推送对象类型：0=全平台,1=指定用户',
  `target_mids` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '目标用户ID列表（JSON格式，全平台时为空）',
  `canpl` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否允许评论 1允许 0不允许',
  `canplrp` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否允许评论回复 1允许 0不允许',
  `pinglun_check` tinyint(1) NOT NULL DEFAULT '0' COMMENT '评论是否需要审核 1需要 0不需要',
  `showtj` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '-1' COMMENT '显示条件 -1所有人 0关注用户 其他为等级ID',
  `showname` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否显示标题',
  `showreadcount` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否显示阅读量',
  `showsendtime` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否显示发布时间',
  `showauthor` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否显示作者',
  `createtime` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `scene_key` varchar(80) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '消息场景标识',
  `scene_text` varchar(30) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '消息场景文案',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE,
  KEY `cid` (`cid`) USING BTREE,
  KEY `status` (`status`) USING BTREE,
  KEY `createtime` (`createtime`) USING BTREE,
  KEY `idx_push_status` (`push_status`)
) ENGINE=InnoDB AUTO_INCREMENT=623 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='消息通知表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_message_pinglun`
--

DROP TABLE IF EXISTS `ddwx_message_pinglun`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_message_pinglun` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '评论ID',
  `aid` int NOT NULL DEFAULT '0' COMMENT '应用ID',
  `bid` int NOT NULL DEFAULT '0' COMMENT '商户ID',
  `sid` int NOT NULL DEFAULT '0' COMMENT '消息ID',
  `mid` int NOT NULL DEFAULT '0' COMMENT '会员ID',
  `nickname` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '昵称',
  `headimg` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '头像',
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '评论内容',
  `zan` int NOT NULL DEFAULT '0' COMMENT '点赞数',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态 1通过 0待审核 2驳回',
  `createtime` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE,
  KEY `sid` (`sid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE,
  KEY `status` (`status`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='消息通知评论表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_message_pinglun_reply`
--

DROP TABLE IF EXISTS `ddwx_message_pinglun_reply`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_message_pinglun_reply` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '回复ID',
  `aid` int NOT NULL DEFAULT '0' COMMENT '应用ID',
  `bid` int NOT NULL DEFAULT '0' COMMENT '商户ID',
  `sid` int NOT NULL DEFAULT '0' COMMENT '消息ID',
  `pid` int NOT NULL DEFAULT '0' COMMENT '评论ID',
  `mid` int NOT NULL DEFAULT '0' COMMENT '会员ID',
  `nickname` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '昵称',
  `headimg` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '头像',
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '回复内容',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态 1通过 0待审核',
  `createtime` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE,
  KEY `sid` (`sid`) USING BTREE,
  KEY `pid` (`pid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='消息通知评论回复表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_message_pzanlog`
--

DROP TABLE IF EXISTS `ddwx_message_pzanlog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_message_pzanlog` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '点赞ID',
  `aid` int NOT NULL DEFAULT '0' COMMENT '应用ID',
  `bid` int NOT NULL DEFAULT '0' COMMENT '商户ID',
  `pid` int NOT NULL DEFAULT '0' COMMENT '评论ID',
  `mid` int NOT NULL DEFAULT '0' COMMENT '会员ID',
  `createtime` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE,
  KEY `pid` (`pid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='消息通知评论点赞记录表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_message_read_status`
--

DROP TABLE IF EXISTS `ddwx_message_read_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_message_read_status` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '阅读记录ID',
  `aid` int NOT NULL DEFAULT '0' COMMENT '应用ID',
  `bid` int NOT NULL DEFAULT '0' COMMENT '商户ID',
  `mid` int NOT NULL DEFAULT '0' COMMENT '会员ID',
  `message_id` int NOT NULL DEFAULT '0' COMMENT '消息通知ID',
  `is_read` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否已读 0未读 1已读',
  `read_time` int NOT NULL DEFAULT '0' COMMENT '阅读时间戳',
  `createtime` int NOT NULL DEFAULT '0' COMMENT '创建时间戳',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `unique_user_message` (`aid`,`bid`,`mid`,`message_id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE,
  KEY `message_id` (`message_id`) USING BTREE,
  KEY `is_read` (`is_read`) USING BTREE,
  KEY `idx_read_status_query` (`aid`,`bid`,`mid`,`is_read`) USING BTREE,
  KEY `idx_message_user_read` (`message_id`,`mid`,`is_read`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='消息通知阅读状态表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_message_set`
--

DROP TABLE IF EXISTS `ddwx_message_set`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_message_set` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '设置ID',
  `aid` int NOT NULL DEFAULT '0' COMMENT '应用ID',
  `bid` int NOT NULL DEFAULT '0' COMMENT '商户ID',
  `listtype` tinyint(1) NOT NULL DEFAULT '1' COMMENT '列表显示类型',
  `share_score` int NOT NULL DEFAULT '0' COMMENT '转发赠送积分',
  `share_score_max_perday` int NOT NULL DEFAULT '0' COMMENT '每日转发积分上限',
  `jifen_max` int NOT NULL DEFAULT '0' COMMENT '每日阅读积分上限',
  `enable_notify` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否开启通知消息',
  `enable_member_chat` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否开启会员聊天',
  `enable_group_chat` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否开启群聊',
  `enable_group_create` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否允许创建群聊',
  `enable_group_create_empty` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否允许无人创建群聊',
  `enable_group_image` tinyint(1) NOT NULL DEFAULT '1' COMMENT '群聊是否允许发图片',
  `enable_group_voice` tinyint(1) NOT NULL DEFAULT '1' COMMENT '群聊是否允许发语音',
  `enable_group_video` tinyint(1) NOT NULL DEFAULT '1' COMMENT '群聊是否允许发视频',
  `enable_group_product` tinyint(1) NOT NULL DEFAULT '1' COMMENT '群聊是否允许发商品',
  `enable_group_redpacket` tinyint(1) NOT NULL DEFAULT '0' COMMENT '群聊是否允许发红包',
  `enable_group_emoji` tinyint(1) NOT NULL DEFAULT '1' COMMENT '群聊是否允许发表情',
  `group_create_max` int NOT NULL DEFAULT '0' COMMENT '每人最多创建群聊数,0不限制',
  `group_member_max` int NOT NULL DEFAULT '50' COMMENT '单个群聊最大真实成员数',
  `group_default_cover` varchar(500) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '群聊默认头像',
  `group_invite_code_length` tinyint NOT NULL DEFAULT '6' COMMENT '群聊邀请码长度',
  `default_comment_open` tinyint(1) NOT NULL DEFAULT '1' COMMENT '新消息默认开启评论',
  `default_reply_open` tinyint(1) NOT NULL DEFAULT '1' COMMENT '新消息默认开启回复',
  `default_comment_audit` tinyint(1) NOT NULL DEFAULT '0' COMMENT '新消息默认评论审核',
  `enable_share_reward` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否开启转发积分',
  `enable_read_reward` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否开启阅读积分',
  `enable_external_push` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否开启外部提醒推送',
  `default_auto_push` tinyint(1) NOT NULL DEFAULT '0' COMMENT '新消息默认自动推送',
  `enable_mp_push` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否开启公众号提醒',
  `enable_wx_push` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否开启小程序提醒',
  `enable_sms_push` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否开启短信提醒',
  `enable_app_push` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否开启App锁屏推送',
  `notify_sound` varchar(500) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '消息提示音音频地址 空则用系统默认',
  `allow_mp_old_fallback` tinyint(1) NOT NULL DEFAULT '1' COMMENT '公众号旧模板兜底',
  `enable_business_notice_sync` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否同步系统业务通知到消息中心',
  `message_banner_enabled` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否开启消息页广告横幅',
  `message_banner_title` varchar(100) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '拍照抽红包，瓜分百万' COMMENT '消息页广告主标题',
  `message_banner_subtitle` varchar(200) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '闲鱼相机，拍得多赚得多！' COMMENT '消息页广告副标题',
  `message_banner_button_text` varchar(30) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '去拍照' COMMENT '消息页广告按钮文案',
  `message_banner_pic` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '消息页广告图片',
  `message_banner_link` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '消息页广告跳转链接',
  `message_banner_link_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '消息页广告链接名称',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='消息通知设置表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_message_zanlog`
--

DROP TABLE IF EXISTS `ddwx_message_zanlog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_message_zanlog` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '点赞ID',
  `aid` int NOT NULL DEFAULT '0' COMMENT '应用ID',
  `bid` int NOT NULL DEFAULT '0' COMMENT '商户ID',
  `sid` int NOT NULL DEFAULT '0' COMMENT '消息ID',
  `mid` int NOT NULL DEFAULT '0' COMMENT '会员ID',
  `createtime` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE,
  KEY `sid` (`sid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='消息通知点赞记录表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_miandan_order`
--

DROP TABLE IF EXISTS `ddwx_miandan_order`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_miandan_order` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `orderid` int DEFAULT NULL,
  `ordertype` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `mid` int DEFAULT NULL,
  `add_source` int DEFAULT NULL,
  `wx_appid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `order_id` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `openid` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `biz_id` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `delivery_id` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `delivery_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `service_type` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `service_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `expect_time` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `custom_remark` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `receiver_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `receiver_mobile` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `receiver_province` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `receiver_city` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `receiver_area` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `receiver_address` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `receiver_tel` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `receiver_company` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `receiver_post_code` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `sender_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `sender_mobile` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `sender_province` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `sender_city` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `sender_area` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `sender_address` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `sender_tel` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `sender_company` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `sender_post_code` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `cargo_count` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `cargo_weight` double DEFAULT NULL,
  `cargo_space_x` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `cargo_space_y` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `cargo_space_z` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `cargo_detail_list` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `shop_wxa_path` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `shop_img_url` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `shop_goods_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `shop_goods_count` int DEFAULT NULL,
  `use_insured` tinyint(1) DEFAULT NULL,
  `insured_value` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `status` tinyint(1) DEFAULT '0',
  `waybill_id` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `waybill_data` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `orderid` (`orderid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_mp_keyword`
--

DROP TABLE IF EXISTS `ddwx_mp_keyword`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_mp_keyword` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `keyword` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `ktype` int DEFAULT '0' COMMENT '0完全匹配 1包含匹配 2关注回复',
  `msgtype` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `content` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `sort` int DEFAULT '0',
  `createtime` int DEFAULT NULL,
  `status` int DEFAULT '1',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `status` (`status`) USING BTREE,
  KEY `ktype` (`ktype`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_mp_kfmsg_sendlog`
--

DROP TABLE IF EXISTS `ddwx_mp_kfmsg_sendlog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_mp_kfmsg_sendlog` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `media_id` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `to_type` tinyint(1) DEFAULT '1',
  `tagid` int DEFAULT NULL,
  `openids` longtext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `createtime` int DEFAULT NULL,
  `endtime` int DEFAULT NULL,
  `sendcount` int DEFAULT '0',
  `successcount` int DEFAULT '0',
  `errorcount` int DEFAULT '0',
  `isdel` int DEFAULT '0',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `to_type` (`to_type`) USING BTREE,
  KEY `isdel` (`isdel`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_mp_kfmsg_sendlog_errlog`
--

DROP TABLE IF EXISTS `ddwx_mp_kfmsg_sendlog_errlog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_mp_kfmsg_sendlog_errlog` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `logid` int DEFAULT NULL,
  `openid` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `errcode` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `errmsg` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `logid` (`logid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_mp_material`
--

DROP TABLE IF EXISTS `ddwx_mp_material`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_mp_material` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `url` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `media_id` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  `type` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `description` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `endtime` int DEFAULT '2147483647',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `media_id` (`media_id`) USING BTREE,
  KEY `type` (`type`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_mp_menu`
--

DROP TABLE IF EXISTS `ddwx_mp_menu`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_mp_menu` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `menudata` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `createtime` int DEFAULT NULL,
  `updatetime` int DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_mp_menukey`
--

DROP TABLE IF EXISTS `ddwx_mp_menukey`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_mp_menukey` (
  `id` int NOT NULL AUTO_INCREMENT,
  `key` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `val` varchar(1000) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `key` (`key`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_mp_preview_wxhistory`
--

DROP TABLE IF EXISTS `ddwx_mp_preview_wxhistory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_mp_preview_wxhistory` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `towxname` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  `isdel` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_mp_tmpl_sendlog`
--

DROP TABLE IF EXISTS `ddwx_mp_tmpl_sendlog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_mp_tmpl_sendlog` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `template_id` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `data` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `url` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `to_type` tinyint(1) DEFAULT '1',
  `tagid` int DEFAULT NULL,
  `openids` longtext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `createtime` int DEFAULT NULL,
  `endtime` int DEFAULT NULL,
  `sendcount` int DEFAULT '0',
  `successcount` int DEFAULT '0',
  `errorcount` int DEFAULT '0',
  `isdel` int DEFAULT '0',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_mp_tmpl_sendlog_errlog`
--

DROP TABLE IF EXISTS `ddwx_mp_tmpl_sendlog_errlog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_mp_tmpl_sendlog_errlog` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `logid` int DEFAULT NULL,
  `openid` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `errcode` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `errmsg` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_mp_tmplset`
--

DROP TABLE IF EXISTS `ddwx_mp_tmplset`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_mp_tmplset` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `tmpl_orderconfirm` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_orderpay` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_orderfahuo` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_ordershouhuo` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_ordertui` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_tuisuccess` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_tuierror` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_withdraw` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_tixiansuccess` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_tixianerror` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_collagesuccess` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_formsub` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_kehuzixun` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_fenxiaosuccess` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_fuwudaoqi` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_joinin` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_peisongorder` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_uplv` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '会员升级通知',
  `tmpl_moneychange` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '余额变动提示',
  `tmpl_restaurant_booking` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_shenhe` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '审核结果通知',
  `tmpl_prize` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '抽奖结果通知',
  `tmpl_kucunbuzu` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `aid` (`aid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_mp_tmplset_new`
--

DROP TABLE IF EXISTS `ddwx_mp_tmplset_new`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_mp_tmplset_new` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `tmpl_orderconfirm` varchar(64) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_orderpay` varchar(64) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_orderfahuo` varchar(64) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_ordershouhuo` varchar(64) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_ordertui` varchar(64) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_tuisuccess` varchar(64) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_tuierror` varchar(64) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_withdraw` varchar(64) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_tixiansuccess` varchar(64) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_tixianerror` varchar(64) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_collagesuccess` varchar(64) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_formsub` varchar(64) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_kehuzixun` varchar(64) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_fenxiaosuccess` varchar(64) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_fuwudaoqi` varchar(64) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_joinin` varchar(64) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_peisongorder` varchar(64) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_uplv` varchar(64) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '会员升级通知',
  `tmpl_moneychange` varchar(64) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '余额变动提示',
  `tmpl_restaurant_booking` varchar(64) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_shenhe` varchar(64) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '审核结果通知',
  `tmpl_prize` varchar(64) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '抽奖结果通知',
  `tmpl_fenhong` varchar(64) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '',
  `tmpl_joinfree` varchar(64) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '' COMMENT '加入免单通知',
  `tmpl_zhaopin_notice` varchar(64) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '',
  `tmpl_register` varchar(64) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_coupon_expire` varchar(64) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_maidanpay` varchar(64) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '买单付款通知',
  `tmpl_message_link` varchar(11) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT 'mp' COMMENT '模板消息链接 mp公众号 wx小程序',
  `tmpl_electricity_form` varchar(64) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '流程中转通知',
  `tmpl_customer_service_message` varchar(64) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '' COMMENT '客服消息',
  `tmpl_common_notice` varchar(64) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '统一提醒通知',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `aid` (`aid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_open_app`
--

DROP TABLE IF EXISTS `ddwx_open_app`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_open_app` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int NOT NULL,
  `appid` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `appsecret` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `token` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `refresh_token` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `name` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `channel` varchar(30) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  `request_time` int DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `appid` (`appid`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `name` (`name`) USING BTREE,
  KEY `channel` (`channel`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_ordernum_change_log`
--

DROP TABLE IF EXISTS `ddwx_ordernum_change_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_ordernum_change_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `aid` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `bid` int NOT NULL DEFAULT '0' COMMENT '商家ID',
  `mid` int NOT NULL DEFAULT '0' COMMENT '会员ID',
  `tablename` varchar(100) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '业务表/订单类型',
  `orderid` int NOT NULL DEFAULT '0' COMMENT '业务订单ID',
  `ordernum` varchar(100) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '原订单号',
  `ordernum_new` varchar(100) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '新订单号',
  `createtime` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_aid_ordernum` (`aid`,`ordernum`),
  KEY `idx_aid_ordernum_new` (`aid`,`ordernum_new`),
  KEY `idx_aid_order` (`aid`,`tablename`,`orderid`),
  KEY `idx_mid` (`mid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='订单号变更留痕';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_page_visit_daily_stat`
--

DROP TABLE IF EXISTS `ddwx_page_visit_daily_stat`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_page_visit_daily_stat` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `aid` int unsigned NOT NULL DEFAULT '0',
  `bid` int NOT NULL DEFAULT '0',
  `stat_day` int unsigned NOT NULL DEFAULT '0',
  `scene` varchar(64) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'site',
  `page_path` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `page_key` char(32) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `pv` int unsigned NOT NULL DEFAULT '0',
  `uv` int unsigned NOT NULL DEFAULT '0',
  `vv` int unsigned NOT NULL DEFAULT '0',
  `createtime` int unsigned NOT NULL DEFAULT '0',
  `updatetime` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_day_page` (`aid`,`bid`,`stat_day`,`scene`,`page_key`),
  KEY `idx_aid_bid_day` (`aid`,`bid`,`stat_day`)
) ENGINE=InnoDB AUTO_INCREMENT=1175 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='前台页面访问按天汇总';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_page_visit_log`
--

DROP TABLE IF EXISTS `ddwx_page_visit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_page_visit_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `aid` int unsigned NOT NULL DEFAULT '0',
  `bid` int NOT NULL DEFAULT '0',
  `mid` int unsigned NOT NULL DEFAULT '0',
  `visitor_key` varchar(64) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `session_key` varchar(64) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `scene` varchar(64) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'mobile',
  `page_path` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `page_key` char(32) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `page_title` varchar(120) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `query_hash` char(32) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `platform` varchar(32) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `ip` varchar(45) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `user_agent` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `stat_day` int unsigned NOT NULL DEFAULT '0',
  `createtime` int unsigned NOT NULL DEFAULT '0',
  `updatetime` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_aid_bid_day` (`aid`,`bid`,`stat_day`),
  KEY `idx_visitor_day` (`aid`,`bid`,`visitor_key`,`stat_day`),
  KEY `idx_session_day` (`aid`,`bid`,`session_key`,`stat_day`),
  KEY `idx_page_day` (`aid`,`bid`,`scene`,`page_key`,`stat_day`)
) ENGINE=InnoDB AUTO_INCREMENT=5785 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='前台页面访问日志';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_pay_changci`
--

DROP TABLE IF EXISTS `ddwx_pay_changci`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_pay_changci` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `changciid` int NOT NULL DEFAULT '0',
  `buyer` int NOT NULL,
  `seller` int NOT NULL,
  `trade_amount` decimal(11,2) NOT NULL,
  `shouchu` decimal(11,2) DEFAULT NULL,
  `mairu` decimal(11,2) DEFAULT NULL,
  `settle_type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '结算类型:0汇算1互买差额',
  `settle_note` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '互买差额说明',
  `status` tinyint(1) NOT NULL,
  `createtime` int DEFAULT NULL,
  `payimg` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `paytime` int DEFAULT NULL,
  `qurentime` int DEFAULT NULL,
  `buyer_products` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '买家购买的秒杀商品信息',
  `order_ids` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '交易涉及的订单号列表',
  `confirm_time` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_payaftergive`
--

DROP TABLE IF EXISTS `ddwx_payaftergive`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_payaftergive` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `gettj` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '-1',
  `paygive_scene` varchar(500) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `limittimes` int DEFAULT '0',
  `pricestart` decimal(11,2) DEFAULT NULL,
  `priceend` decimal(11,2) DEFAULT NULL,
  `starttime` int DEFAULT NULL,
  `endtime` int DEFAULT NULL,
  `money` float(11,2) DEFAULT NULL,
  `score` int DEFAULT '0',
  `choujiangtimes` int DEFAULT NULL,
  `choujiangid` int DEFAULT NULL,
  `give_coupon` tinyint(1) DEFAULT '0',
  `coupon_ids` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tourl` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '',
  `btntext` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `status` tinyint(1) DEFAULT '0',
  `createtime` int DEFAULT NULL,
  `sort` int DEFAULT '0',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `status` (`status`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `starttime` (`starttime`) USING BTREE,
  KEY `endtime` (`endtime`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_payaftergive_record`
--

DROP TABLE IF EXISTS `ddwx_payaftergive_record`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_payaftergive_record` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `hid` int DEFAULT NULL,
  `mid` int DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '-1',
  `money` float(11,2) DEFAULT NULL,
  `score` int DEFAULT '0',
  `choujiangtimes` int DEFAULT NULL,
  `choujiangid` int DEFAULT NULL,
  `give_coupon` tinyint(1) DEFAULT '0',
  `coupon_ids` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE,
  KEY `hid` (`hid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_payorder`
--

DROP TABLE IF EXISTS `ddwx_payorder`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_payorder` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `bid` int DEFAULT NULL,
  `mid` int DEFAULT NULL,
  `orderid` int DEFAULT NULL,
  `ordernum` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `money` decimal(11,2) DEFAULT NULL,
  `s_money` varchar(4096) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `pay_s_info` varchar(4096) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `qixian_zhongheng` varchar(4096) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL COMMENT '店铺信息',
  `score` int DEFAULT NULL,
  `type` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT 'shop collage scoreshop kanjia seckill recharge maidan designerpage form',
  `createtime` int DEFAULT NULL,
  `status` tinyint(1) DEFAULT '0' COMMENT '0未支付 1已支付 2已取消',
  `paynum` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `paytypeid` tinyint(1) DEFAULT '0' COMMENT '1余额支付 2微信支付 3支付宝支付 4货到付款 5转账汇款 11百度小程序 12头条小程序',
  `paytype` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `paytime` int DEFAULT NULL,
  `paypics` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `check_status` tinyint(1) DEFAULT NULL COMMENT '0待审核，1审核通过，2驳回',
  `check_remark` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `platform` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `issettle` tinyint(1) DEFAULT '0',
  `isbusinesspay` tinyint(1) DEFAULT '0',
  `money_usd` float(11,2) DEFAULT NULL,
  `issxpay` tinyint(1) DEFAULT '0',
  `refund_money` decimal(11,2) DEFAULT NULL COMMENT '退款金额',
  `refund_time` int DEFAULT '0' COMMENT '退款时间',
  `wxpay_typeid` int NOT NULL DEFAULT '0' COMMENT '微信支付模式 0 普通模式1  服务商模式2  二级商户3  随行付4  汇付天下斗拱5  银盛支付6 通联支付 7 微信收款码（定制pay_wx_native_h5）8 B2b模式',
  `iscpcnpay` tinyint(1) DEFAULT '0' COMMENT '是否使用中金支付 0=否 1=是',
  `cpcn_payment_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '中金支付流水号',
  `cpcn_order_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '中金订单号',
  `cpcn_trade_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '中金交易号',
  `tx_sn` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '交易流水号，用于中金支付回调查找',
  `has_split` tinyint(1) DEFAULT '0' COMMENT '是否有分账 0=否 1=是',
  `cpcn_transfer_status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '中金划转状态: 0=未划转, 1=已划转, 2=划转失败, 3=划转异常',
  `cpcn_transfer_txsn` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '中金划转流水号',
  `cpcn_transfer_time` int NOT NULL DEFAULT '0' COMMENT '中金划转时间',
  `cpcn_transfer_error` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '中金划转错误信息',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE,
  KEY `orderid` (`orderid`) USING BTREE,
  KEY `ordernum` (`ordernum`) USING BTREE,
  KEY `idx_iscpcnpay` (`iscpcnpay`) USING BTREE,
  KEY `idx_cpcn_payment_no` (`cpcn_payment_no`) USING BTREE,
  KEY `idx_has_split` (`has_split`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=16716 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_performance_cache_config`
--

DROP TABLE IF EXISTS `ddwx_performance_cache_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_performance_cache_config` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '配置ID',
  `aid` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `config_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '配置键名',
  `config_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '配置名称',
  `config_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '配置值JSON',
  `is_enabled` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否启用',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '配置说明',
  `create_time` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `aid_config_key` (`aid`,`config_key`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='业绩缓存配置表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_performance_cache_logs`
--

DROP TABLE IF EXISTS `ddwx_performance_cache_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_performance_cache_logs` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '日志ID',
  `aid` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `mid` int NOT NULL DEFAULT '0' COMMENT '会员ID',
  `operation_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '操作类型：get获取，update更新，calculate计算，clear清理',
  `cache_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'daily' COMMENT '缓存类型：minute，hourly，daily，monthly，total',
  `operation_result` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'success' COMMENT '操作结果：success成功，failed失败，hit命中，miss未命中',
  `execution_time` decimal(10,4) NOT NULL COMMENT '执行耗时(秒)',
  `affected_records` int NOT NULL DEFAULT '0' COMMENT '影响记录数',
  `user_id` int NOT NULL DEFAULT '0' COMMENT '操作用户ID',
  `user_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'system' COMMENT '用户类型：admin管理员，member会员，system系统',
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '' COMMENT 'IP地址',
  `error_msg` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '错误信息',
  `extra_data` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '额外数据JSON',
  `create_time` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid_mid` (`aid`,`mid`) USING BTREE,
  KEY `operation_type` (`operation_type`) USING BTREE,
  KEY `operation_result` (`operation_result`) USING BTREE,
  KEY `create_time` (`create_time`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='业绩缓存操作日志表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_performance_cache_tasks`
--

DROP TABLE IF EXISTS `ddwx_performance_cache_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_performance_cache_tasks` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '任务ID',
  `aid` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `mid` int NOT NULL DEFAULT '0' COMMENT '会员ID',
  `task_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'update' COMMENT '任务类型：update更新，recalc重算，clear清理',
  `cache_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'daily' COMMENT '缓存类型：minute，hourly，daily，monthly，total',
  `target_date` date DEFAULT NULL COMMENT '目标日期',
  `priority` tinyint(1) NOT NULL DEFAULT '1' COMMENT '优先级：1普通，2重要，3紧急',
  `trigger_reason` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '' COMMENT '触发原因',
  `retry_count` int NOT NULL DEFAULT '0' COMMENT '重试次数',
  `max_retry` int NOT NULL DEFAULT '3' COMMENT '最大重试次数',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '状态：0待处理，1处理中，2已完成，3失败',
  `error_msg` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '错误信息',
  `start_time` int NOT NULL DEFAULT '0' COMMENT '开始处理时间',
  `complete_time` int NOT NULL DEFAULT '0' COMMENT '完成时间',
  `execution_time` decimal(10,4) NOT NULL COMMENT '执行耗时(秒)',
  `create_time` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid_status` (`aid`,`status`) USING BTREE,
  KEY `priority_create` (`priority`,`create_time`) USING BTREE,
  KEY `mid_task_type` (`mid`,`task_type`) USING BTREE,
  KEY `target_date` (`target_date`) USING BTREE,
  KEY `idx_status_priority_time` (`status`,`priority`,`create_time`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='业绩缓存任务队列表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_pictolocal`
--

DROP TABLE IF EXISTS `ddwx_pictolocal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_pictolocal` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `pic` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `url` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `pic` (`pic`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_plog`
--

DROP TABLE IF EXISTS `ddwx_plog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_plog` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `bid` int DEFAULT '0',
  `uid` int DEFAULT NULL,
  `remark` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `createtime` int DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE,
  KEY `uid` (`uid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=4653 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_plugin_business_auth`
--

DROP TABLE IF EXISTS `ddwx_plugin_business_auth`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_plugin_business_auth` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `aid` int NOT NULL DEFAULT '0' COMMENT '平台/站点ID',
  `bid` int NOT NULL DEFAULT '0' COMMENT '商户ID',
  `plugin_key` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '插件标识',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态 1启用 0禁用',
  `permissions` text COLLATE utf8mb4_general_ci COMMENT '权限节点JSON',
  `expire_time` int NOT NULL DEFAULT '0' COMMENT '过期时间 0表示不过期',
  `remark` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '备注',
  `operator_uid` int NOT NULL DEFAULT '0' COMMENT '最后操作人ID',
  `operator_type` varchar(20) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '最后操作人类型',
  `create_time` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_aid_bid_plugin` (`aid`,`bid`,`plugin_key`),
  KEY `idx_plugin_status` (`plugin_key`,`status`),
  KEY `idx_expire_time` (`expire_time`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='商户插件授权表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_random_discount_set`
--

DROP TABLE IF EXISTS `ddwx_random_discount_set`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_random_discount_set` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL COMMENT '站点ID',
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '付款随机立减' COMMENT '活动名称',
  `desc` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '活动描述',
  `rddata` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '立减范围数据(JSON格式)',
  `status` tinyint(1) DEFAULT '0' COMMENT '状态 0:关闭 1:开启',
  `createtime` int DEFAULT NULL COMMENT '创建时间',
  `updatetime` int DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `aid` (`aid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='随机立减设置表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_recharge_card`
--

DROP TABLE IF EXISTS `ddwx_recharge_card`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_recharge_card` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `aid` int NOT NULL DEFAULT '0' COMMENT '账号ID',
  `bid` int NOT NULL DEFAULT '0' COMMENT '商户ID',
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '充值卡名称',
  `recharge_amount` decimal(10,2) NOT NULL COMMENT '充值金额',
  `give_amount` decimal(10,2) NOT NULL COMMENT '赠送余额',
  `give_score` int NOT NULL DEFAULT '0' COMMENT '赠送积分',
  `sell_price` decimal(10,2) NOT NULL COMMENT '售价（实际支付金额）',
  `total_amount` decimal(10,2) NOT NULL COMMENT '到账总额（充值+赠送）',
  `pic` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '' COMMENT '卡片图片',
  `sort` int NOT NULL DEFAULT '0' COMMENT '排序（越大越靠前）',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态：1=启用，0=禁用',
  `remark` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '' COMMENT '备注说明',
  `sales_count` int NOT NULL DEFAULT '0' COMMENT '销售次数',
  `sales_amount` decimal(10,2) NOT NULL COMMENT '销售总额',
  `createtime` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `updatetime` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE,
  KEY `status` (`status`) USING BTREE,
  KEY `sort` (`sort`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='充值卡套餐表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_recharge_giveset`
--

DROP TABLE IF EXISTS `ddwx_recharge_giveset`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_recharge_giveset` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `givedata` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `status` tinyint(1) DEFAULT '0',
  `createtime` int DEFAULT NULL,
  `shuoming` longtext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `caninput` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `aid` (`aid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_recharge_order`
--

DROP TABLE IF EXISTS `ddwx_recharge_order`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_recharge_order` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `mid` int DEFAULT NULL,
  `money` decimal(11,2) DEFAULT NULL,
  `s_money` varchar(4096) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL COMMENT '各个商店充值情况',
  `ordernum` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  `status` tinyint(1) DEFAULT '0',
  `payorderid` int DEFAULT NULL,
  `paytypeid` int DEFAULT NULL,
  `paytype` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `paynum` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `paytime` int DEFAULT NULL,
  `platform` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=785 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_register_form`
--

DROP TABLE IF EXISTS `ddwx_register_form`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_register_form` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `content` longtext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `sort` int DEFAULT '0',
  `createtime` int DEFAULT NULL,
  `bid` int DEFAULT '0',
  `savetype` tinyint(1) DEFAULT '1' COMMENT '1：下单时保存表单 2：跟随系统表单',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_register_form_record`
--

DROP TABLE IF EXISTS `ddwx_register_form_record`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_register_form_record` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `bid` int DEFAULT '0',
  `formid` bigint DEFAULT NULL,
  `content` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '自定义表单内容',
  `createtime` int DEFAULT NULL,
  `form0` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form1` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form2` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form3` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form4` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form5` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form6` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form7` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form8` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form9` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form10` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form11` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form12` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form13` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form14` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form15` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form16` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form17` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form18` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form19` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form20` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form21` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form22` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form23` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form24` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form25` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form26` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form27` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form28` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form29` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form30` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form31` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form32` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form33` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form34` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form35` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form36` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form37` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form38` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form39` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form40` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form41` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form42` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form43` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form44` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form45` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form46` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form47` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form48` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form49` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form50` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form51` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form52` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form53` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form54` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form55` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form56` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form57` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form58` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form59` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `form60` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE,
  KEY `formid` (`formid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_register_giveset`
--

DROP TABLE IF EXISTS `ddwx_register_giveset`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_register_giveset` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `money` float(11,2) DEFAULT NULL,
  `score` int DEFAULT '0',
  `scorehuang` int DEFAULT NULL COMMENT '注册赠送黄币',
  `wanshan_score` int DEFAULT '0',
  `give_coupon` tinyint(1) DEFAULT '0',
  `coupon_ids` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `starttime` datetime DEFAULT NULL,
  `endtime` datetime DEFAULT NULL,
  `status` tinyint(1) DEFAULT '0',
  `createtime` int DEFAULT NULL,
  `storedvaluecoupon_rule_id` bigint NOT NULL DEFAULT '0' COMMENT '注册赠送储值优惠券规则ID',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `aid` (`aid`) USING BTREE,
  KEY `status` (`status`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_score_to_commission_log`
--

DROP TABLE IF EXISTS `ddwx_score_to_commission_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_score_to_commission_log` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `aid` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `mid` int NOT NULL DEFAULT '0' COMMENT '用户ID',
  `score` int NOT NULL DEFAULT '0' COMMENT '转换的积分数量',
  `commission` decimal(10,6) NOT NULL DEFAULT '0.000000' COMMENT '计算得到的佣金数量（未扣手续费）',
  `fee` decimal(10,6) NOT NULL DEFAULT '0.000000' COMMENT '手续费',
  `final_commission` decimal(10,6) NOT NULL DEFAULT '0.000000' COMMENT '实际获得的佣金数量（已扣手续费）',
  `rate` int NOT NULL DEFAULT '100' COMMENT '兑换比例（多少积分换1佣金）',
  `fee_rate` decimal(5,4) NOT NULL DEFAULT '0.0000' COMMENT '手续费比例',
  `createtime` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid_mid` (`aid`,`mid`) USING BTREE,
  KEY `createtime` (`createtime`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=72 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='积分转佣金日志表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_sendredpack_log`
--

DROP TABLE IF EXISTS `ddwx_sendredpack_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_sendredpack_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `openid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `money` decimal(11,2) DEFAULT NULL,
  `appid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `mchid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `ordernum` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `createtime` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `status` tinyint(1) DEFAULT '0',
  `remark` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `openid` (`openid`) USING BTREE,
  KEY `status` (`status`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_session`
--

DROP TABLE IF EXISTS `ddwx_session`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_session` (
  `session_id` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `session_key` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `aid` int DEFAULT NULL,
  `mid` int DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Http User Agent',
  `login_time` int unsigned NOT NULL DEFAULT '0' COMMENT '登录时间',
  `login_ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '登录IP地址',
  `login_ip_location` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'IP地址转换成的地理位置',
  `platform` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  KEY `session_id` (`session_id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE,
  KEY `login_time` (`login_time`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_shop_cart`
--

DROP TABLE IF EXISTS `ddwx_shop_cart`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_shop_cart` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL COMMENT '账户ID',
  `bid` int DEFAULT '0' COMMENT '商户ID 0代表平台的',
  `mid` int DEFAULT NULL COMMENT '会员ID',
  `proid` int DEFAULT NULL COMMENT '商品ID',
  `ggid` int DEFAULT NULL COMMENT '商品规格ID',
  `num` int DEFAULT NULL COMMENT '数量',
  `dhtid` int DEFAULT '0' COMMENT '带货团id',
  `tid` int DEFAULT NULL COMMENT '团长id',
  `createtime` int DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE,
  KEY `proid` (`proid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=3655 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='购物车';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_shop_category`
--

DROP TABLE IF EXISTS `ddwx_shop_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_shop_category` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL COMMENT '账户ID',
  `pid` int DEFAULT '0' COMMENT '上级分类ID',
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '分类名称',
  `pic` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '分类图片',
  `status` int DEFAULT '1' COMMENT '状态0关闭1开启',
  `sort` int DEFAULT '1' COMMENT '排序 越大越靠前',
  `showtj` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '-1',
  `limitbuy_by_level` varchar(2000) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '按会员等级限购次数JSON {levelId: count}',
  `createtime` int DEFAULT NULL COMMENT '创建时间',
  `start_hours` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `end_hours` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `banner` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `stbz_id` int DEFAULT NULL COMMENT '接口id',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `pid` (`pid`) USING BTREE,
  KEY `showtj` (`showtj`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1257000 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='商品分类';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_shop_category2`
--

DROP TABLE IF EXISTS `ddwx_shop_category2`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_shop_category2` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `bid` int DEFAULT NULL,
  `pid` int DEFAULT '0',
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `pic` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `status` int DEFAULT '1',
  `sort` int DEFAULT '1',
  `createtime` int DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`aid`) USING BTREE,
  KEY `pid` (`pid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_shop_codelist`
--

DROP TABLE IF EXISTS `ddwx_shop_codelist`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_shop_codelist` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL COMMENT '账户ID',
  `bid` int DEFAULT '0' COMMENT '商户ID 0代表平台的',
  `proid` int DEFAULT NULL COMMENT '商品ID',
  `content` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '卡密内容',
  `ordernum` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '订单号',
  `orderid` int DEFAULT NULL COMMENT '订单ID',
  `headimg` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '用户头像',
  `nickname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '用户昵称',
  `createtime` int DEFAULT NULL COMMENT '创建时间',
  `buytime` int DEFAULT NULL COMMENT '购买时间',
  `status` tinyint(1) DEFAULT '0' COMMENT '状态0未售出1已售出',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE,
  KEY `proid` (`proid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='卡密商品的卡密';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_shop_comment`
--

DROP TABLE IF EXISTS `ddwx_shop_comment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_shop_comment` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL COMMENT '账户ID',
  `bid` int DEFAULT '0' COMMENT '商户ID 0代表平台的',
  `mid` int DEFAULT NULL COMMENT '用户ID',
  `orderid` int DEFAULT NULL COMMENT '订单ID',
  `ogid` int DEFAULT NULL COMMENT '订单商品ID',
  `proid` int DEFAULT NULL COMMENT '商品ID',
  `proname` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '商品名称',
  `propic` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '商品图片',
  `ggid` int DEFAULT NULL COMMENT '商品规格ID',
  `ggname` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '商品规格名称',
  `ordernum` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '订单号',
  `openid` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '用户openid',
  `nickname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '用户昵称',
  `headimg` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '用户头像',
  `score` int DEFAULT NULL COMMENT '评价积分',
  `content` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '评价内容',
  `content_pic` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '评价图片',
  `reply_content` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '商家回复内容',
  `reply_content_pic` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '商家回复图片',
  `append_content` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `append_content_pic` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `append_reply_content` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `append_reply_content_pic` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  `appendtime` int DEFAULT NULL,
  `status` int DEFAULT '1' COMMENT '状态0待审核1已通过2已驳回',
  `reply_time` int DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE,
  KEY `proid` (`proid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='商品评价';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_shop_ex_set`
--

DROP TABLE IF EXISTS `ddwx_shop_ex_set`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_shop_ex_set` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int NOT NULL,
  `field_name` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `name` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `value` int NOT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_shop_fastcart`
--

DROP TABLE IF EXISTS `ddwx_shop_fastcart`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_shop_fastcart` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `mid` int DEFAULT NULL,
  `proid` int DEFAULT NULL,
  `ggid` int DEFAULT NULL,
  `num` int DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE,
  KEY `proid` (`proid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_shop_fuwu`
--

DROP TABLE IF EXISTS `ddwx_shop_fuwu`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_shop_fuwu` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `bid` int DEFAULT '0',
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `desc` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `status` int DEFAULT '1',
  `sort` int DEFAULT '1',
  `createtime` int DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_shop_group`
--

DROP TABLE IF EXISTS `ddwx_shop_group`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_shop_group` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL COMMENT '账户ID',
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '分组名称',
  `pic` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '分组图片',
  `status` int DEFAULT '1' COMMENT '状态0关闭1开启',
  `sort` int DEFAULT '1' COMMENT '排序 越大越靠前',
  `createtime` int DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=391 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='商品分组';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_shop_guige`
--

DROP TABLE IF EXISTS `ddwx_shop_guige`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_shop_guige` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL COMMENT '账户ID',
  `proid` int DEFAULT NULL COMMENT '商品ID',
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '规格名称',
  `pic` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '规格图片',
  `market_price` decimal(11,2) DEFAULT NULL COMMENT '市场价',
  `cost_price` decimal(11,2) DEFAULT NULL COMMENT '成本价',
  `sell_price` decimal(11,2) DEFAULT NULL COMMENT '销售价',
  `weight` int DEFAULT NULL COMMENT '重量',
  `stock` int unsigned DEFAULT '0' COMMENT '库存',
  `procode` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '编码',
  `sales` int DEFAULT '0' COMMENT '已售数量',
  `ks` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '规格结构',
  `lvprice_data` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '开启会员价时各个会员等级的价格数据',
  `wholesale_data` text COLLATE utf8mb4_general_ci COMMENT '规格独立阶梯价JSON（空则跟随商品主阶梯）',
  `givescore` int DEFAULT '0' COMMENT '购买给多少积分',
  `givescorehuang` int DEFAULT '0' COMMENT '购买给多少黄积分',
  `givescoregongxianzhi` int DEFAULT NULL,
  `limit_start` int unsigned DEFAULT '0',
  `barcode` varchar(60) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态:0下架,1上架',
  `unit` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '单位',
  `stat_quantity` int DEFAULT '1' COMMENT '统计数量',
  `count_num` decimal(10,2) NOT NULL DEFAULT '1.00' COMMENT '统计数量（支持小数）',
  `source_plugin` varchar(40) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '来源插件标识',
  `source_supplier` varchar(60) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '来源供应链标识',
  `source_spu_id` varchar(64) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '来源SPU ID',
  `source_sku_id` varchar(64) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '来源SKU ID',
  `source_sync_time` int unsigned NOT NULL DEFAULT '0' COMMENT '来源同步时间',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `proid` (`proid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=3310 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='商品规格';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_shop_order`
--

DROP TABLE IF EXISTS `ddwx_shop_order`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_shop_order` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL COMMENT '账户ID',
  `bid` int DEFAULT '0' COMMENT '商户ID',
  `mid` int DEFAULT NULL COMMENT '会员ID',
  `ordernum` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '订单号',
  `is_daigou` int NOT NULL DEFAULT '0',
  `dai_id` int NOT NULL DEFAULT '0',
  `title` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '订单名称',
  `totalprice` float(11,2) DEFAULT NULL COMMENT '总金额',
  `product_price` float(11,2) DEFAULT '0.00' COMMENT '商品金额',
  `freight_price` float(11,2) DEFAULT NULL COMMENT '运费金额',
  `invoice_money` float(11,2) DEFAULT '0.00',
  `scoredk_money` float(11,2) DEFAULT NULL COMMENT '积分抵扣金额',
  `leveldk_money` float(11,2) DEFAULT '0.00' COMMENT '会员等级优惠金额',
  `manjian_money` decimal(11,2) DEFAULT NULL COMMENT '满减优惠金额',
  `random_discount_money` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '随机立减金额',
  `coupon_money` decimal(11,2) DEFAULT NULL COMMENT '优惠券金额',
  `storedvaluecoupon_money` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '储值优惠券抵扣金额',
  `coupon_rid` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `scoredkscore` int DEFAULT '0' COMMENT '积分抵扣用掉的积分',
  `givescore` int DEFAULT '0' COMMENT '赠送积分',
  `givescore2` int DEFAULT '0' COMMENT '赠送积分2',
  `givescorehuang` int DEFAULT '0' COMMENT '赠送黄积分',
  `givescoregongxianzhi` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '0',
  `createtime` int DEFAULT NULL COMMENT '创建时间',
  `status` int DEFAULT '0' COMMENT '0未支付;1已支付;2已发货,3已收货,4关闭',
  `deposit_released` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否已用于押金释放',
  `linkman` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '姓名',
  `company` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '单位',
  `tel` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '手机号',
  `area` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '省市区',
  `area2` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '省市区逗号分割',
  `street_id` int NOT NULL DEFAULT '0' COMMENT '街道ID',
  `street` varchar(100) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '街道名称',
  `address` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '地址',
  `longitude` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '经度',
  `latitude` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '维度',
  `message` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '留言',
  `remark` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '后台备注',
  `payorderid` int DEFAULT NULL COMMENT '订单支付表id',
  `paytypeid` int DEFAULT NULL COMMENT '支付方式 1余额支付 2微信支付 3支付宝支付 4货到付款 5转账汇款 11百度小程序 12头条小程序',
  `paytype` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '支付方式文本',
  `paynum` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '支付单号',
  `paytime` int DEFAULT NULL COMMENT '支付时间',
  `express_com` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '快递公司',
  `express_no` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '快递单号',
  `express_ogids` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `express_isbufen` tinyint(1) DEFAULT '0',
  `express_type` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '物流类型',
  `express_content` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '多个快递单号时的快递单号数据',
  `refund_reason` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '退款原因',
  `refund_money` decimal(11,2) DEFAULT NULL,
  `refund_status` int DEFAULT '0' COMMENT '1申请退款审核中 2已同意退款 3已驳回',
  `refund_time` int DEFAULT NULL COMMENT '退款时间',
  `refund_checkremark` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '退款审核备注',
  `send_time` bigint DEFAULT NULL COMMENT '发货时间',
  `collect_time` int DEFAULT NULL COMMENT '收货时间',
  `freight_id` int DEFAULT NULL COMMENT '配送方式ID',
  `freight_text` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '配送方式',
  `freight_type` tinyint(1) DEFAULT '0' COMMENT '配送方式类型 0普通快递 1到店自提 2同城配送 3自动发货 4在线卡密',
  `mdid` int DEFAULT NULL COMMENT '到店自提时门店ID',
  `freight_time` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '取货时间',
  `freight_content` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '自动发货信息 卡密',
  `hexiao_code` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '唯一码 核销码',
  `hexiao_qr` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '核销码图片',
  `hexiao_code_member` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `platform` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT 'wx' COMMENT '来源平台 mp公众号 wx微信小程序',
  `iscomment` tinyint(1) DEFAULT '0' COMMENT '是否已评价',
  `delete` tinyint(1) DEFAULT '0' COMMENT '用户删除 0未删除 1已删除',
  `isfenhong` tinyint(1) DEFAULT '0' COMMENT '是否已经分红',
  `checkmemid` int DEFAULT NULL,
  `balance_price` float(11,2) DEFAULT '0.00',
  `balance_pay_status` tinyint(1) DEFAULT '0',
  `balance_pay_orderid` int DEFAULT NULL,
  `fromwxvideo` tinyint(1) DEFAULT '0' COMMENT '是否是视频号过来的订单',
  `scene` int DEFAULT '0' COMMENT '小程序场景',
  `wxvideo_order_id` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `sysOrderNo` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '定制同步app端',
  `transfer_check` tinyint(1) NOT NULL DEFAULT '0' COMMENT '转账审核 -1 驳回 0：待审核 1：通过',
  `is_tuikuang` int NOT NULL DEFAULT '0' COMMENT '是否进行退框了',
  `tuikuang_id` int DEFAULT NULL COMMENT '退框的ID',
  `is_yh` int NOT NULL DEFAULT '0' COMMENT '是否存在优惠0不存在1存在',
  `ntext` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL COMMENT '优惠详情合集',
  `knum` int NOT NULL DEFAULT '0' COMMENT '框数量',
  `peisong_uid` int DEFAULT NULL COMMENT '配送员的uid',
  `peisong_relname` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '配送员的名字',
  `total_freight` int NOT NULL DEFAULT '0' COMMENT '运费',
  `kfj_jiangli` decimal(11,2) DEFAULT NULL,
  `kfj_shengyu` decimal(11,2) DEFAULT NULL,
  `cyjj_shengyu` decimal(11,2) NOT NULL DEFAULT '0.00',
  `scoredk_moneyhei` decimal(11,2) NOT NULL DEFAULT '0.00',
  `scoredkscorehei` decimal(11,2) NOT NULL DEFAULT '0.00',
  `scoredk_moneyyu` decimal(11,2) NOT NULL DEFAULT '0.00',
  `scoredkscoreyu` decimal(10,2) NOT NULL DEFAULT '0.00',
  `scoredk_moneyhuang` decimal(10,2) DEFAULT NULL COMMENT '黄积分抵扣',
  `scoredkscorehuang` decimal(10,2) DEFAULT NULL COMMENT '抵扣掉的黄积分',
  `order_fx_type` tinyint(1) DEFAULT '0' COMMENT '是否签到返现 0未返现 1已经返现',
  `ti` tinyint(1) NOT NULL DEFAULT '0',
  `nahuoid` int NOT NULL DEFAULT '0',
  `nahuoArr` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `zouhuoshouxu` decimal(10,2) NOT NULL DEFAULT '0.00',
  `ylevelid` int NOT NULL DEFAULT '0',
  `xsyjfh` decimal(11,2) NOT NULL DEFAULT '0.00',
  `dianshu` decimal(11,2) NOT NULL DEFAULT '0.00',
  `xsyjfhstatus` tinyint(1) NOT NULL DEFAULT '0',
  `miaoshaid` int DEFAULT '0',
  `hui_status` tinyint(1) NOT NULL DEFAULT '0',
  `tixingcount` int NOT NULL DEFAULT '0',
  `d_orderid` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `peisongyifu` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tuanzhangid` int NOT NULL DEFAULT '0' COMMENT '团长ID',
  `pickup_code` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '提货编号',
  `print_status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '打印状态 0未打印 1已打印',
  `print_count` int NOT NULL DEFAULT '0' COMMENT '打印次数',
  `give_assets` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '满额赠送资产JSON数据',
  `fenhong_dian_status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '分红点处理状态 0未处理 1已处理',
  `buy_xieyi_snapshot` mediumtext COLLATE utf8mb4_general_ci COMMENT '下单时购买协议快照(JSON)',
  `buy_xieyi_agree_time` int NOT NULL DEFAULT '0' COMMENT '购买协议同意时间',
  `issource` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否供应链订单',
  `source` varchar(60) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '供应链来源编码',
  `sourceid` int unsigned NOT NULL DEFAULT '0' COMMENT '供应链关联ID',
  `source_plugin` varchar(40) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '来源插件标识',
  `source_supplier` varchar(60) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '来源供应链标识',
  `source_order_sn` varchar(64) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '供应链订单号',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `code` (`hexiao_code`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE,
  KEY `status` (`status`) USING BTREE,
  KEY `createtime` (`createtime`) USING BTREE,
  KEY `idx_aid_mid_status` (`aid`,`mid`,`status`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=9670 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='商城订单表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_shop_order_goods`
--

DROP TABLE IF EXISTS `ddwx_shop_order_goods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_shop_order_goods` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL COMMENT '账户ID',
  `bid` int DEFAULT '0' COMMENT '商户ID 0表示平台',
  `mid` int DEFAULT NULL COMMENT '会员ID',
  `orderid` int DEFAULT NULL COMMENT '订单ID',
  `ordernum` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '订单号',
  `proid` int DEFAULT NULL COMMENT '商品ID',
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '商品名称',
  `pic` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '商品图片',
  `procode` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '商品编码',
  `barcode` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `ggid` int DEFAULT NULL COMMENT '规格ID',
  `ggname` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '规格名称',
  `cid` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '0',
  `num` int DEFAULT '0' COMMENT '购买数量',
  `total_count_num` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '总统计数量（支持小数）',
  `refund_num` int unsigned NOT NULL DEFAULT '0' COMMENT '退款数量',
  `cost_price` decimal(11,2) DEFAULT NULL COMMENT '成本价',
  `sell_price` decimal(11,2) DEFAULT NULL COMMENT '销售价',
  `wholesale_unit_price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '下单时的阶梯单价（0表示非阶梯价）',
  `wholesale_tier_info` varchar(200) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '阶梯快照(如: 50-99件@70元)',
  `totalprice` decimal(11,2) DEFAULT NULL COMMENT '总价',
  `total_weight` decimal(11,2) unsigned DEFAULT NULL,
  `scoredk_money` decimal(11,2) DEFAULT NULL,
  `leveldk_money` decimal(11,2) DEFAULT NULL,
  `manjian_money` decimal(11,2) DEFAULT NULL,
  `coupon_money` decimal(11,2) DEFAULT NULL,
  `hei_money` decimal(11,2) DEFAULT NULL COMMENT '抵现的黑积分',
  `real_totalprice` decimal(10,2) DEFAULT NULL COMMENT '实际商品销售金额 减去了优惠券抵扣会员折扣满减积分抵扣的金额',
  `business_total_money` decimal(11,2) DEFAULT NULL,
  `status` int DEFAULT '0' COMMENT '0未付款1已付款2已发货3已收货4申请退款5已退款',
  `createtime` int DEFAULT NULL COMMENT '创建时间',
  `endtime` int DEFAULT NULL COMMENT '完成时间 收货时间',
  `iscomment` tinyint(1) DEFAULT '0' COMMENT '是否已评价',
  `parent1` int DEFAULT NULL COMMENT '直接推荐人',
  `parent2` int DEFAULT NULL COMMENT '二级推荐人',
  `parent3` int DEFAULT NULL COMMENT '三级推荐人',
  `parent4` int DEFAULT NULL,
  `parent5` int DEFAULT NULL,
  `parent6` int DEFAULT NULL,
  `parent7` int DEFAULT NULL,
  `parent8` int DEFAULT NULL,
  `parent9` int DEFAULT NULL,
  `parent10` int DEFAULT NULL,
  `parent11` int DEFAULT NULL,
  `parent12` int DEFAULT NULL,
  `parent13` int DEFAULT NULL,
  `parent14` int DEFAULT NULL,
  `parent15` int DEFAULT NULL,
  `parent16` int DEFAULT NULL,
  `parent17` int DEFAULT NULL,
  `parent18` int DEFAULT NULL,
  `parent19` int DEFAULT NULL,
  `parent20` int DEFAULT NULL,
  `parent1commission` decimal(11,2) DEFAULT NULL COMMENT '一级提成',
  `parent2commission` decimal(11,2) DEFAULT NULL COMMENT '二级提成',
  `parent3commission` decimal(11,2) DEFAULT NULL COMMENT '三级提成',
  `parent4commission` decimal(11,2) DEFAULT NULL,
  `parent5commission` decimal(11,2) DEFAULT NULL,
  `parent6commission` decimal(11,2) DEFAULT NULL,
  `parent7commission` decimal(10,2) DEFAULT NULL,
  `parent8commission` decimal(10,2) DEFAULT NULL,
  `parent9commission` decimal(10,2) DEFAULT NULL,
  `parent10commission` decimal(10,2) DEFAULT NULL,
  `parent11commission` decimal(10,2) DEFAULT NULL,
  `parent12commission` decimal(10,2) DEFAULT NULL,
  `parent13commission` decimal(10,2) DEFAULT NULL,
  `parent14commission` decimal(10,2) DEFAULT NULL,
  `parent15commission` decimal(10,2) DEFAULT NULL,
  `parent16commission` decimal(10,2) DEFAULT NULL,
  `parent17commission` decimal(10,2) DEFAULT NULL,
  `parent18commission` decimal(10,2) DEFAULT NULL,
  `parent19commission` decimal(10,2) DEFAULT NULL,
  `parent20commission` decimal(10,2) DEFAULT NULL,
  `parent1score` int DEFAULT '0' COMMENT '一级提成积分',
  `parent2score` int DEFAULT '0' COMMENT '二级提成积分',
  `parent3score` int DEFAULT '0' COMMENT '三级提成积分',
  `parent4score` decimal(10,2) NOT NULL DEFAULT '0.00',
  `parent5score` decimal(10,2) NOT NULL DEFAULT '0.00',
  `parent6score` decimal(10,2) NOT NULL DEFAULT '0.00',
  `parent7score` decimal(10,2) NOT NULL DEFAULT '0.00',
  `parent8score` decimal(10,2) NOT NULL DEFAULT '0.00',
  `parent9score` decimal(10,2) NOT NULL DEFAULT '0.00',
  `parent10score` decimal(10,2) NOT NULL DEFAULT '0.00',
  `parent11score` decimal(10,2) NOT NULL DEFAULT '0.00',
  `parent12score` decimal(10,2) NOT NULL DEFAULT '0.00',
  `parent13score` decimal(10,2) NOT NULL DEFAULT '0.00',
  `parent14score` decimal(10,2) NOT NULL DEFAULT '0.00',
  `parent15score` decimal(10,2) NOT NULL DEFAULT '0.00',
  `parent16score` decimal(10,2) NOT NULL DEFAULT '0.00',
  `parent17score` decimal(10,2) NOT NULL DEFAULT '0.00',
  `parent18score` decimal(10,2) NOT NULL DEFAULT '0.00',
  `parent19score` decimal(10,2) NOT NULL DEFAULT '0.00',
  `parent20score` decimal(10,2) NOT NULL DEFAULT '0.00',
  `iscommission` tinyint(1) DEFAULT '0' COMMENT '佣金是否已发放',
  `isfenhong` tinyint(1) DEFAULT '0' COMMENT '分红是否已结算',
  `no_performance` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否不计算业绩：0=计算业绩，1=不计算业绩（如第三单等特殊情况）',
  `isfg` tinyint(1) DEFAULT '0' COMMENT '是否复购',
  `isteamfenhong` tinyint(1) DEFAULT '0' COMMENT '团队分红是否已结算',
  `isdan` int DEFAULT '0',
  `is_yh` int NOT NULL DEFAULT '0' COMMENT '是否是优惠订单',
  `yh_nums` int NOT NULL DEFAULT '0' COMMENT '优惠数量',
  `yh_prices` double(10,2) NOT NULL DEFAULT '0.00' COMMENT '优惠金额',
  `fhparent2commission` decimal(12,2) NOT NULL DEFAULT '0.00',
  `fhparent3commission` decimal(12,2) NOT NULL DEFAULT '0.00',
  `fhparent4commission` decimal(12,2) NOT NULL DEFAULT '0.00',
  `fenhongbili` decimal(11,2) DEFAULT NULL,
  `fenhongstatus` tinyint(1) NOT NULL DEFAULT '0',
  `jiandianjiangli` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `jiandianstatus` tinyint(1) NOT NULL DEFAULT '0',
  `yuejifen` decimal(10,2) NOT NULL DEFAULT '0.00',
  `jiyuejifen` int NOT NULL DEFAULT '0',
  `shengyuyue` int NOT NULL DEFAULT '0',
  `koulvjifen` decimal(10,2) NOT NULL DEFAULT '0.00',
  `xiaofeizhifan` decimal(10,2) DEFAULT NULL,
  `xfzparent2commission` decimal(10,2) NOT NULL DEFAULT '0.00',
  `xfzparent3commission` decimal(10,2) NOT NULL DEFAULT '0.00',
  `xfzparent4commission` decimal(10,2) NOT NULL DEFAULT '0.00',
  `xfzparent2id` int NOT NULL DEFAULT '0',
  `xfzparent3id` int NOT NULL DEFAULT '0',
  `xfzparent4id` int NOT NULL DEFAULT '0',
  `jingyong` decimal(10,2) DEFAULT NULL,
  `jingji` decimal(10,2) DEFAULT NULL,
  `parent1commissionscore` decimal(10,2) NOT NULL DEFAULT '0.00',
  `parent2commissionscore` decimal(10,2) NOT NULL DEFAULT '0.00',
  `parent3commissionscore` decimal(10,2) NOT NULL DEFAULT '0.00',
  `parent4commissionscore` decimal(10,2) NOT NULL DEFAULT '0.00',
  `is_yeji` int DEFAULT NULL,
  `no_yeji_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '不计算业绩的原因（如：单数奖循环第三单不计算业绩）',
  `no_performance_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '不计算业绩原因（如：单数奖循环第三单）',
  `parent1commissionshouyi` decimal(11,2) NOT NULL DEFAULT '0.00',
  `parent2commissionshouyi` decimal(11,2) NOT NULL DEFAULT '0.00',
  `parent3commissionshouyi` decimal(11,2) NOT NULL DEFAULT '0.00',
  `parent4commissionshouyi` decimal(11,2) NOT NULL DEFAULT '0.00',
  `ti` tinyint(1) NOT NULL DEFAULT '0',
  `nahuoid` int NOT NULL DEFAULT '0',
  `nahuoArr` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `zouhuoshouxu` decimal(10,2) NOT NULL DEFAULT '0.00',
  `iszuhe` tinyint(1) NOT NULL DEFAULT '0',
  `iszuheshow` tinyint(1) NOT NULL DEFAULT '0',
  `givefanyong` decimal(10,2) NOT NULL DEFAULT '0.00',
  `paidui_jiang` decimal(10,2) NOT NULL DEFAULT '0.00',
  `paidui_give` decimal(11,2) NOT NULL DEFAULT '0.00',
  `tuozhanfei` decimal(10,5) DEFAULT NULL,
  `tuozhanid` int DEFAULT '0',
  `tuozhanfei_status` tinyint(1) DEFAULT '0',
  `tuozhanid_jian` int DEFAULT '0',
  `tuozhanfei_jian` decimal(11,5) DEFAULT NULL,
  `tuozhanid_jianjian` int DEFAULT '0',
  `tuozhanfei_jianjian` decimal(11,5) DEFAULT NULL,
  `dhtid` int DEFAULT '0',
  `tid` int DEFAULT '0',
  `daiticheng` decimal(10,2) DEFAULT NULL,
  `t_status` tinyint(1) NOT NULL DEFAULT '0',
  `isyuefenhong` int NOT NULL DEFAULT '0',
  `queue_id` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `diy_amount` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '0',
  `stat_quantity` int DEFAULT '1' COMMENT '统计数量',
  `unit` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '件' COMMENT '单位',
  `count_num` decimal(10,2) NOT NULL DEFAULT '1.00' COMMENT '单个规格统计数量（支持小数）',
  `source_plugin` varchar(40) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '来源插件标识',
  `source_supplier` varchar(60) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '来源供应链标识',
  `source_spu_id` varchar(64) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '来源SPU ID',
  `source_sku_id` varchar(64) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '来源SKU ID',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE,
  KEY `orderid` (`orderid`) USING BTREE,
  KEY `proid` (`proid`) USING BTREE,
  KEY `idx_aid_mid_status` (`aid`,`mid`,`status`) USING BTREE,
  KEY `idx_aid_status_isfenhong` (`aid`,`status`,`isfenhong`) USING BTREE,
  KEY `idx_aid_isfenhong` (`aid`,`isfenhong`) USING BTREE,
  KEY `idx_no_performance` (`no_performance`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=11548 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='订单商品明细表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_shop_param`
--

DROP TABLE IF EXISTS `ddwx_shop_param`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_shop_param` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `bid` int DEFAULT '0',
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `cid` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '',
  `type` tinyint(1) DEFAULT '0',
  `params` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `tips` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `is_required` tinyint(1) DEFAULT '0',
  `sort` int DEFAULT NULL,
  `status` tinyint(1) DEFAULT '1',
  `createtime` int DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_shop_product`
--

DROP TABLE IF EXISTS `ddwx_shop_product`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_shop_product` (
  `id` int NOT NULL AUTO_INCREMENT,
  `hq_product_id` int unsigned DEFAULT '0' COMMENT '总部商品ID',
  `aid` int DEFAULT NULL,
  `bid` int DEFAULT '0',
  `cid` varchar(200) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '0' COMMENT '分类id，可存储多分类用,间隔',
  `cid2` varchar(200) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '0' COMMENT '商家的商品分类',
  `gid` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '分组ID多个用逗号分割',
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '商品名称',
  `procode` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '商品编码',
  `barcode` varchar(60) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `fwid` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '商品服务ID多个用逗号分割',
  `fuwupoint` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `sellpoint` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '卖点',
  `pic` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '商品主图',
  `pics` varchar(4000) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '商品图片',
  `diypics` varchar(4000) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `sales` int DEFAULT '0' COMMENT '已售数量',
  `paramdata` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `detail` longtext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '商品详情',
  `market_price` float(11,2) DEFAULT NULL COMMENT '市场价',
  `sell_price` float(11,2) DEFAULT '0.00' COMMENT '销售价',
  `cost_price` decimal(11,2) DEFAULT NULL COMMENT '成本价',
  `price_type` tinyint unsigned DEFAULT '0' COMMENT '价格模式：0默认，1询价',
  `product_type` int NOT NULL DEFAULT '0' COMMENT '商品类型 0普通 20预售券',
  `givescore` int DEFAULT '0' COMMENT '购买赠送积分',
  `givescorehuang` int DEFAULT '0' COMMENT '赠送黄积分',
  `givescoregongxianzhi` int DEFAULT NULL,
  `weight` int DEFAULT NULL COMMENT '重量',
  `sort` int DEFAULT '0' COMMENT '排序 越大越靠前',
  `status` int DEFAULT '1' COMMENT '状态0未上架1已上架',
  `stock` int unsigned DEFAULT '100' COMMENT '库存',
  `createtime` int DEFAULT NULL COMMENT '创建时间',
  `commissionset` int DEFAULT '0' COMMENT '分销设置 0按会员等级 1价格比例  2固定金额 3分销送积分 -1不参与分销',
  `commissiondata1` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '按比例分销时的数据',
  `commissiondata2` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '按固定金额时的数据',
  `commissiondata3` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '按分销送积分的数据',
  `commission1` decimal(11,2) DEFAULT NULL,
  `commission2` decimal(11,2) DEFAULT NULL,
  `commission3` decimal(11,2) DEFAULT NULL,
  `commissionset4` tinyint(1) DEFAULT '0' COMMENT '是否开启极差分销',
  `fenhongset` int DEFAULT '1' COMMENT '分红设置',
  `gdfenhongset` int DEFAULT '0',
  `gdfenhongdata1` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `gdfenhongdata2` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `teamfenhongset` int DEFAULT '0',
  `teamfenhongdata1` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `teamfenhongdata2` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `areafenhongset` int DEFAULT '0',
  `areafenhongdata1` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `areafenhongdata2` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `commissionpingjiset` int DEFAULT '0',
  `commissionpingjidata1` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `commissionpingjidata2` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `guigedata` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '规格数据',
  `comment_score` decimal(2,1) DEFAULT '5.0',
  `comment_haopercent` int DEFAULT '100' COMMENT '好评率',
  `comment_num` int DEFAULT '0' COMMENT '评价数量',
  `freighttype` tinyint(1) DEFAULT '1' COMMENT '配送方式 1全部模板 0指定模板 3自动发货 4在线卡密',
  `freightdata` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '指定模板时的配送模板ID 多个逗号分割',
  `freightcontent` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '自动发货时的发货内容',
  `fastbuy` tinyint(1) DEFAULT '1',
  `lvprice` tinyint(1) DEFAULT '0' COMMENT '是否开启会员价 不同会员等级设置不同价格',
  `lvprice_data` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '开启会员价时的各个等级价格数据',
  `isfuwu` tinyint(1) DEFAULT '0',
  `fuwuday` int DEFAULT NULL,
  `video` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '视频url',
  `video_duration` int DEFAULT '0' COMMENT '视频时长',
  `warehouse_pid` int NOT NULL DEFAULT '0' COMMENT '关联仓库商品ID',
  `bcid` int DEFAULT '0' COMMENT '商户的商品时 商户的商品分类',
  `perlimit` int DEFAULT '0' COMMENT '每人限购多少件',
  `perlimitdan` int DEFAULT '0' COMMENT '每单限购多少件',
  `daily_limit` int NOT NULL DEFAULT '0' COMMENT '每日限购件数,0为不限制',
  `limit_start` int DEFAULT '0' COMMENT '起售数量',
  `wholesale_enable` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否开启批发阶梯价 0否 1是',
  `wholesale_data` text COLLATE utf8mb4_general_ci COMMENT '阶梯价格JSON',
  `wholesale_allow_coupon` tinyint(1) NOT NULL DEFAULT '1' COMMENT '阶梯价是否允许叠加优惠券 0否 1是',
  `showtj` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '-1' COMMENT '显示条件 -1表示不限制',
  `gettj` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '-1' COMMENT '购买条件 -1表示不限制',
  `gettjurl` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `gettjtip` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `show_price` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '-1' COMMENT '显示价格用户组',
  `hide_price_text` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '' COMMENT '隐藏价格后的文字',
  `hide_price_detail_text` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '' COMMENT '隐藏价格后详情页显示文字',
  `hide_price_link` varchar(1000) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT '' COMMENT '隐藏价格后的链接',
  `scoredkmaxset` tinyint(1) DEFAULT '0' COMMENT '积分抵扣设置 0按照系统设置抵扣 1单独设置抵扣比例 2单独设置抵扣金额 -1不可用积分抵扣',
  `scoredkmaxval` decimal(11,2) DEFAULT NULL COMMENT '积分抵扣时最多抵扣多少',
  `ischecked` tinyint(1) DEFAULT '1' COMMENT '是否已审核 1已通过 0待审核 2已驳回',
  `check_reason` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '驳回原因',
  `start_hours` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `end_hours` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `start_time` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `end_time` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `balance` float(11,2) DEFAULT '0.00',
  `wxvideo_edit_status` int DEFAULT '0' COMMENT '视频号商品草稿状态 0未同步 1未审核 2审核中 3审核失败 4审核成功',
  `wxvideo_status` int DEFAULT '0' COMMENT '视频号商品线上状态 0初始值 5已上架 11已下架 13违规/风控',
  `wxvideo_third_cat_id` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '视频号类目id',
  `wxvideo_brand_id` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '视频号品牌id',
  `wxvideo_product_id` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '视频号商品id',
  `wxvideo_qualification_pics` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '0' COMMENT '视频号商品资质',
  `wxvideo_reject_reason` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '视频号商品驳回原因',
  `linkid` int DEFAULT '0',
  `payaftertourl` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `payafterbtntext` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `sharetitle` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `sharepic` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `sharedesc` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `sharelink` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `no_discount` tinyint(1) DEFAULT '0',
  `douyin_product_id` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '',
  `douyin_check_status` tinyint DEFAULT NULL,
  `douyin_status` tinyint(1) DEFAULT NULL,
  `feepercent` decimal(5,2) unsigned DEFAULT NULL,
  `fengdanjiangli` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '',
  `givescore_time` tinyint(1) DEFAULT '0',
  `islock` tinyint(1) DEFAULT '0',
  `viewnum` int DEFAULT '0',
  `show_recommend` tinyint DEFAULT '0',
  `recommend_productids` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `jiandian` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `fenhongbili` decimal(10,2) DEFAULT NULL,
  `yuejifen` decimal(10,2) NOT NULL,
  `jiyuejifen` int NOT NULL DEFAULT '0',
  `xiaofeizhi` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `scoredkmaxsethei` tinyint(1) NOT NULL DEFAULT '1',
  `scoredkmaxvalhei` decimal(11,2) NOT NULL,
  `scoredkmaxsetyu` tinyint(1) NOT NULL DEFAULT '1',
  `scoredkmaxvalyu` decimal(11,2) NOT NULL,
  `product_moneypay` tinyint(1) NOT NULL DEFAULT '1',
  `product_heipay` tinyint(1) NOT NULL DEFAULT '1',
  `product_wxpay` tinyint(1) NOT NULL DEFAULT '1' COMMENT '商品是否支持微信支付：1=支持，0=不支持',
  `product_alipay` tinyint(1) NOT NULL DEFAULT '1' COMMENT '商品是否支持支付宝支付：0不支持 1支持',
  `if_jiandian` tinyint(1) DEFAULT NULL COMMENT '是否见点奖励',
  `if_xiaofeizhi` tinyint(1) DEFAULT NULL COMMENT '是否返消费值',
  `if_xianjinquan` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '是否反现金券',
  `xianjinquan` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `scoredkmaxsethuang` tinyint(1) DEFAULT '0' COMMENT '黄积分抵扣  ',
  `scoredkmaxunithuang` decimal(10,2) DEFAULT NULL COMMENT '最多抵扣',
  `stbz_id` int DEFAULT NULL COMMENT '胜天商品id',
  `is_yeji` int DEFAULT NULL,
  `kuncun_type` tinyint(1) DEFAULT '0',
  `dddata` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `fanyongbl` decimal(11,2) NOT NULL,
  `if_fanyong` tinyint(1) NOT NULL DEFAULT '0',
  `fanyong` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `days` int NOT NULL DEFAULT '0',
  `days_ci` int NOT NULL DEFAULT '0',
  `paiduistatus` tinyint(1) DEFAULT '0' COMMENT '1开启0关闭',
  `paidui_jiang` decimal(11,2) DEFAULT NULL,
  `paidui_give` decimal(11,2) DEFAULT NULL,
  `buketui` tinyint(1) DEFAULT '0',
  `area_mode` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '商品地址模式【0.默认.跟随商户地址，1.指定商品归属地】',
  `province_id` int DEFAULT '0',
  `city_id` int unsigned DEFAULT '0',
  `district_id` int unsigned DEFAULT '0',
  `province` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '省名字',
  `city` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '城市名字',
  `district` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '区县名字',
  `zeng_jingtai` decimal(10,2) DEFAULT NULL,
  `zeng_dongtai` decimal(11,2) DEFAULT NULL,
  `if_shengjiscore` tinyint DEFAULT NULL,
  `shengjiscore` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `songduihuanfen` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `diy_amount` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '0' COMMENT '包装价格会累积',
  `shop_content` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '促销文案',
  `is_newcustom` int NOT NULL DEFAULT '0' COMMENT '是否开启新客优惠',
  `yh_price` double(10,2) NOT NULL DEFAULT '0.00' COMMENT '新客优惠金额',
  `yh_num` int NOT NULL COMMENT '优惠的数量',
  `yh_order_times` int NOT NULL DEFAULT '3' COMMENT '新客优惠总订单次数，0为不限',
  `yh_daily_times` int NOT NULL DEFAULT '1' COMMENT '新客优惠每日订单次数，0为不限',
  `yh_danshu` int NOT NULL DEFAULT '1' COMMENT '优惠单数',
  `card_enable` tinyint(1) DEFAULT '0' COMMENT '加速卡功能开关 0关闭 1开启',
  `direct_speed` decimal(10,2) DEFAULT NULL COMMENT '直推加速比例',
  `indirect_speed` decimal(10,2) DEFAULT NULL COMMENT '间推加速比例',
  `card_days` int DEFAULT '0' COMMENT '加速卡有效期(天)',
  `buybtn_name` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '立即购买',
  `buybtn_link_name` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `buybtn_link_url` varchar(1000) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT '' COMMENT '购买按钮跳转链接',
  `addcart_name` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '加入购物车',
  `addcart_link_name` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `addcart_link_url` varchar(1000) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT '' COMMENT '加入购物车按钮跳转链接',
  `show_buybtn` tinyint(1) DEFAULT '1',
  `show_addcartbtn` tinyint(1) DEFAULT '1',
  `auto_delivery_url` varchar(1000) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL COMMENT '自动发货后跳转链接',
  `card_delivery_url` varchar(1000) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL COMMENT '卡密发货后跳转链接',
  `btn_style_mode` tinyint(1) NOT NULL DEFAULT '0' COMMENT '按钮样式模式 0默认 1自定义',
  `buybtn_bg_type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '购买按钮背景类型 0纯色 1渐变',
  `buybtn_bg_color` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '' COMMENT '购买按钮纯色背景',
  `buybtn_grad_color1` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '' COMMENT '购买按钮渐变色1',
  `buybtn_grad_color2` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '' COMMENT '购买按钮渐变色2',
  `buybtn_text_color` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '' COMMENT '购买按钮文字颜色',
  `buybtn_show_countdown` tinyint(1) NOT NULL DEFAULT '0' COMMENT '购买按钮是否显示倒计时',
  `buybtn_countdown_seconds` int NOT NULL DEFAULT '0' COMMENT '购买按钮倒计时秒数',
  `addcart_bg_type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '加购按钮背景类型 0纯色 1渐变',
  `addcart_show_countdown` tinyint(1) NOT NULL DEFAULT '0' COMMENT '加购按钮是否显示倒计时',
  `addcart_countdown_seconds` int NOT NULL DEFAULT '0' COMMENT '加购按钮倒计时秒数',
  `addcart_bg_color` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '' COMMENT '加购按钮纯色背景',
  `addcart_grad_color1` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '' COMMENT '加购按钮渐变色1',
  `addcart_grad_color2` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '' COMMENT '加购按钮渐变色2',
  `addcart_text_color` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '' COMMENT '加购按钮文字颜色',
  `jiedian_commissionset` tinyint NOT NULL DEFAULT '0' COMMENT '接点人直推奖励设置：0按照会员等级，1单独设置提成比例，2单独设置提成金额，3接点人直推送积分，-1不参与接点人直推奖励',
  `jiedian_commissiondata1` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '接点人直推奖励比例配置数据',
  `jiedian_commissiondata2` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '接点人直推奖励金额配置数据',
  `jiedian_commissiondata3` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '接点人直推奖励积分配置数据',
  `teamfenhongpjset` int DEFAULT '0' COMMENT '团队分红平级奖设置：0按会员等级 1单独比例 2单独金额 -1不参与',
  `teamfenhongpjdata1` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '团队分红平级奖-按比例数据',
  `teamfenhongpjdata2` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '团队分红平级奖-按金额数据',
  `everyday_hongbao_bl` decimal(10,2) DEFAULT NULL COMMENT '红包额度比例',
  `oldsales` int DEFAULT '0' COMMENT '历史销量',
  `is_rzh` tinyint(1) DEFAULT '0' COMMENT '是否需要认证会员',
  `relation_type` tinyint DEFAULT '-1' COMMENT '会员关系类型',
  `house_status` tinyint(1) DEFAULT '1' COMMENT '一户仅限一次',
  `group_status` tinyint(1) DEFAULT '0' COMMENT '授权小区开关',
  `paidui_pool_id` int NOT NULL DEFAULT '0' COMMENT '商品归属排队池ID',
  `buy_xieyi_mode` tinyint(1) NOT NULL DEFAULT '0' COMMENT '购买协议模式 0跟随商城默认 1无需协议 2商品独立协议',
  `buy_xieyi_title` varchar(100) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '商品独立购买协议标题',
  `buy_xieyi` mediumtext COLLATE utf8mb4_general_ci COMMENT '商品独立购买协议内容',
  `source_plugin` varchar(40) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '来源插件标识',
  `source_supplier` varchar(60) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '来源供应链标识',
  `source_spu_id` varchar(64) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '来源SPU ID',
  `source_sync_time` int unsigned NOT NULL DEFAULT '0' COMMENT '来源同步时间',
  `product_xiaofeibpay` tinyint(1) NOT NULL DEFAULT '0' COMMENT '消费币支付开关',
  `atmosphere` text COLLATE utf8mb4_general_ci COMMENT '秒杀氛围配置JSON',
  `goods_page_id` int NOT NULL DEFAULT '0' COMMENT '指定商品DIY页，0跟随站点已发布页',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE,
  KEY `cid` (`cid`) USING BTREE,
  KEY `gid` (`gid`) USING BTREE,
  KEY `status` (`status`) USING BTREE,
  KEY `stock` (`stock`) USING BTREE,
  KEY `stbz_id` (`stbz_id`) USING BTREE,
  KEY `product_city_group` (`city_id`) USING BTREE,
  KEY `product_district_group` (`district_id`) USING BTREE,
  KEY `idx_hq_product_id` (`hq_product_id`) USING BTREE,
  KEY `idx_name` (`name`(100)) USING BTREE COMMENT '商品名称索引，用于模糊搜索'
) ENGINE=InnoDB AUTO_INCREMENT=2692 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_shop_refund_order`
--

DROP TABLE IF EXISTS `ddwx_shop_refund_order`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_shop_refund_order` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `bid` int DEFAULT '0',
  `mdid` int DEFAULT NULL,
  `mid` int DEFAULT NULL,
  `refund_ordernum` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `orderid` int DEFAULT '0',
  `ordernum` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `title` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `product_price` float(11,2) DEFAULT '0.00',
  `createtime` int DEFAULT NULL,
  `status` int DEFAULT '0' COMMENT '0未支付;1已支付;2已发货,3已收货',
  `message` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `remark` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `payorderid` int DEFAULT NULL,
  `paytypeid` int DEFAULT NULL,
  `paytype` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `paynum` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `paytime` int DEFAULT NULL,
  `express_com` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '快递公司',
  `express_no` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '快递单号',
  `refund_type` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT 'refund退款，return退货退款',
  `refund_reason` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `refund_money` decimal(11,2) DEFAULT NULL,
  `refund_status` int DEFAULT '1' COMMENT '0取消 1申请退款审核中 2已同意退款 4同意待退货 3已驳回',
  `refund_time` int DEFAULT NULL,
  `refund_checkremark` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `refund_pics` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `freight_time` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `freight_content` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '自动发货信息 卡密',
  `platform` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT 'wx',
  `delete` tinyint(1) DEFAULT '0',
  `aftersale_id` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE,
  KEY `status` (`status`) USING BTREE,
  KEY `createtime` (`createtime`) USING BTREE,
  KEY `refund_type` (`refund_type`) USING BTREE,
  KEY `orderid` (`orderid`) USING BTREE,
  KEY `refund_ordernum` (`refund_ordernum`) USING BTREE,
  KEY `ordernum` (`ordernum`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=182 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_shop_refund_order_goods`
--

DROP TABLE IF EXISTS `ddwx_shop_refund_order_goods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_shop_refund_order_goods` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `bid` int DEFAULT '0',
  `mid` int DEFAULT NULL,
  `refund_orderid` int DEFAULT NULL,
  `refund_ordernum` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `refund_num` int unsigned NOT NULL DEFAULT '0',
  `refund_money` decimal(11,2) unsigned NOT NULL DEFAULT '0.00',
  `orderid` int DEFAULT NULL,
  `ordernum` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `ogid` int NOT NULL,
  `proid` int DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `pic` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `procode` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `ggid` int DEFAULT NULL,
  `ggname` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `cid` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '0',
  `cost_price` decimal(11,2) DEFAULT NULL,
  `sell_price` decimal(11,2) DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE,
  KEY `refund_orderid` (`refund_orderid`) USING BTREE,
  KEY `refund_ordernum` (`refund_ordernum`) USING BTREE,
  KEY `orderid` (`orderid`) USING BTREE,
  KEY `ordernum` (`ordernum`) USING BTREE,
  KEY `proid` (`proid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=193 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_shop_sysset`
--

DROP TABLE IF EXISTS `ddwx_shop_sysset`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_shop_sysset` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `autoshdays` int DEFAULT '7',
  `autoclose` int DEFAULT '60' COMMENT '多少分钟不支付自动关闭订单',
  `comment` tinyint(1) DEFAULT '1',
  `comment_check` tinyint(1) DEFAULT '1',
  `showjd` tinyint(1) DEFAULT '1',
  `more_one` int NOT NULL DEFAULT '0',
  `cancod` tinyint(1) DEFAULT '0',
  `gwctj` tinyint(1) DEFAULT '1',
  `showcommission` tinyint(1) DEFAULT '0',
  `needzb` tinyint(1) DEFAULT '0',
  `codtxt` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '货到付款',
  `pay_month` tinyint(1) DEFAULT '0',
  `pay_month_txt` varchar(60) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '月结账户',
  `hide_sales` tinyint(1) DEFAULT '0' COMMENT '是否隐藏销量',
  `hide_stock` tinyint(1) DEFAULT '0' COMMENT '是否隐藏库存',
  `member_price_checkout_only` tinyint(1) NOT NULL DEFAULT '0' COMMENT '会员价是否仅在结算时可见',
  `show_prolist_member_price` tinyint(1) NOT NULL DEFAULT '1' COMMENT '列表页是否显示会员价和划线价',
  `show_prolist_sellpoint` tinyint(1) NOT NULL DEFAULT '0' COMMENT '列表页是否显示商品卖点',
  `canrefund` tinyint(1) DEFAULT '1',
  `show_lvupsavemoney` tinyint(1) DEFAULT '0' COMMENT '是否显示升级优惠',
  `receiving_address_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `receiving_address_tel` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `receiving_address_province` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `receiving_address_city` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `receiving_address_area` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `receiving_address_address` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `shipping_pagetitle` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '送货单',
  `coupon_peruselimit` int DEFAULT '1',
  `order_detail_toppic` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '订单详情顶部图片',
  `is_qd` int NOT NULL DEFAULT '0' COMMENT '是否开启统一起订',
  `qd_num` int NOT NULL DEFAULT '1' COMMENT '起订的数量',
  `daigojiesuan` tinyint NOT NULL DEFAULT '0',
  `shipping_title1` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `shipping_title2` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `dibucontent` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `shipping_hangshu` tinyint DEFAULT '5',
  `show_notes` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否显示笔记 0不显示 1显示',
  `show_douyin_share` tinyint(1) NOT NULL DEFAULT '0' COMMENT '商品详情页是否开启分享到抖音',
  `show_daihuobiji_entry` tinyint(1) NOT NULL DEFAULT '1' COMMENT '商品详情分享弹层是否显示发布笔记入口',
  `mp_force_phone_on_product` tinyint(1) NOT NULL DEFAULT '0' COMMENT '微信小程序商品页是否强制获取手机号',
  `mp_force_phone_on_order` tinyint(1) NOT NULL DEFAULT '0' COMMENT '微信小程序确认订单页是否强制获取手机号',
  `buy_xieyi_show` tinyint(1) NOT NULL DEFAULT '0' COMMENT '下单页是否显示购买协议',
  `buy_xieyi_title` varchar(100) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '购买协议' COMMENT '购买协议标题',
  `buy_xieyi` text COLLATE utf8mb4_general_ci COMMENT '购买协议内容',
  `enable_pickup_code` tinyint(1) DEFAULT '0' COMMENT '是否启用提货编号 0-否 1-是',
  `pickup_code_reset_interval` int DEFAULT '24' COMMENT '提货编号重置间隔(小时)',
  `aliyun_express_host` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '站点独立阿里云物流查询Host',
  `aliyun_express_path` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '站点独立阿里云物流查询Path',
  `aliyun_express_appcode` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '站点独立阿里云物流查询AppCode',
  `my_order_performance_show` tinyint(1) DEFAULT '1' COMMENT '是否显示我的单数业绩',
  `my_earnings_show` tinyint(1) DEFAULT '1' COMMENT '是否显示我的收益',
  `address_name_label` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '姓名' COMMENT '手机端收货人字段名称',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `aid` (`aid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=137 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_site_quota`
--

DROP TABLE IF EXISTS `ddwx_site_quota`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_site_quota` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `aid` int NOT NULL DEFAULT '0',
  `quota_key` varchar(32) NOT NULL DEFAULT '',
  `remaining_num` int NOT NULL DEFAULT '0',
  `updatetime` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_aid_key` (`aid`,`quota_key`),
  KEY `idx_quota_key` (`quota_key`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='站点额度余额';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_site_quota_call_log`
--

DROP TABLE IF EXISTS `ddwx_site_quota_call_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_site_quota_call_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `aid` int NOT NULL DEFAULT '0',
  `quota_key` varchar(32) NOT NULL DEFAULT '',
  `biz_no` varchar(128) NOT NULL DEFAULT '',
  `source` varchar(64) NOT NULL DEFAULT '',
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `cache_hit` tinyint(1) NOT NULL DEFAULT '0',
  `billed` tinyint(1) NOT NULL DEFAULT '0',
  `skip_bill` tinyint(1) NOT NULL DEFAULT '0',
  `duration` int NOT NULL DEFAULT '0',
  `http_code` int NOT NULL DEFAULT '0',
  `error_msg` varchar(255) NOT NULL DEFAULT '',
  `extra_json` text,
  `ip` varchar(64) NOT NULL DEFAULT '',
  `createtime` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_aid_key_time` (`aid`,`quota_key`,`createtime`),
  KEY `idx_biz_no` (`quota_key`,`biz_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='站点额度上游调用日志';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_site_quota_log`
--

DROP TABLE IF EXISTS `ddwx_site_quota_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_site_quota_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `aid` int NOT NULL DEFAULT '0',
  `quota_key` varchar(32) NOT NULL DEFAULT '',
  `change_num` int NOT NULL DEFAULT '0',
  `before_num` int NOT NULL DEFAULT '0',
  `after_num` int NOT NULL DEFAULT '0',
  `biz_type` varchar(20) NOT NULL DEFAULT 'recharge',
  `operator_id` int NOT NULL DEFAULT '0',
  `operator_name` varchar(64) NOT NULL DEFAULT '',
  `remark` varchar(255) NOT NULL DEFAULT '',
  `source` varchar(64) NOT NULL DEFAULT '',
  `createtime` int NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `idx_aid_key_time` (`aid`,`quota_key`,`createtime`),
  KEY `idx_key_time` (`quota_key`,`createtime`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='站点额度流水';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_smslog`
--

DROP TABLE IF EXISTS `ddwx_smslog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_smslog` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `PhoneNumbers` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `num` int DEFAULT '1',
  `SignName` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `TemplateCode` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `TemplateContent` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `TemplateParam` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `ip` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  `createdate` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `biz_id` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `status` tinyint(1) DEFAULT '0' COMMENT '0发送中 1成功 2失败',
  `err_msg` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `successnum` int DEFAULT '0',
  `errnum` int DEFAULT '0',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `status` (`status`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=748 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_split_records`
--

DROP TABLE IF EXISTS `ddwx_split_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_split_records` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int NOT NULL COMMENT '应用ID',
  `bid` int NOT NULL COMMENT '商户ID',
  `order_num` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '订单号',
  `payment_txsn` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '支付流水号',
  `payment_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '支付类型:wechat,alipay,balance',
  `total_amount` int NOT NULL COMMENT '订单总金额(分)',
  `merchant_amount` int NOT NULL COMMENT '商户分账金额(分)',
  `platform_amount` int NOT NULL COMMENT '平台分账金额(分)',
  `merchant_ratio` decimal(5,2) NOT NULL COMMENT '商户分账比例(%)',
  `platform_ratio` decimal(5,2) NOT NULL COMMENT '平台分账比例(%)',
  `split_status` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '10' COMMENT '分账状态:10=处理中,20=成功,30=失败,40=人工处理,50=已撤销',
  `split_items` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '分账明细JSON字符串',
  `refund_status` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '00' COMMENT '退款状态:00=无退款,10=部分退款,20=全部退款',
  `refund_amount` int DEFAULT '0' COMMENT '已退款金额(分)',
  `refund_time` timestamp NULL DEFAULT NULL COMMENT '退款时间',
  `refund_txsn` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '' COMMENT '退款流水号',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `retry_count` int DEFAULT '0' COMMENT '重试次数',
  `last_retry_at` timestamp NULL DEFAULT NULL COMMENT '最后重试时间',
  `retry_error` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '重试错误信息',
  `process_remark` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '处理备注',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_payment` (`payment_txsn`) USING BTREE,
  KEY `idx_order` (`order_num`) USING BTREE,
  KEY `idx_merchant` (`aid`,`bid`) USING BTREE,
  KEY `idx_status` (`split_status`) USING BTREE,
  KEY `idx_created` (`created_at`) USING BTREE,
  KEY `idx_refund_status` (`refund_status`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='分账记录表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_staff`
--

DROP TABLE IF EXISTS `ddwx_staff`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_staff` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `aid` int NOT NULL DEFAULT '0' COMMENT '代理商ID',
  `bid` int NOT NULL DEFAULT '0' COMMENT '店铺ID',
  `category_id` int NOT NULL DEFAULT '0' COMMENT '员工分类ID',
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '员工姓名',
  `realname` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '真实姓名',
  `tel` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '手机号',
  `commission_rate` decimal(5,2) NOT NULL COMMENT '默认提成比例(%)',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态：0=禁用,1=启用',
  `createtime` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `updatetime` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  `sort` int NOT NULL DEFAULT '0' COMMENT '排序',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_aid_bid` (`aid`,`bid`) USING BTREE,
  KEY `idx_status` (`status`) USING BTREE,
  KEY `idx_tel` (`tel`) USING BTREE,
  KEY `idx_category_id` (`category_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='员工表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_staff_category`
--

DROP TABLE IF EXISTS `ddwx_staff_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_staff_category` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `aid` int NOT NULL DEFAULT '0' COMMENT '代理商ID',
  `bid` int NOT NULL DEFAULT '0' COMMENT '店铺ID',
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '分类名称',
  `sort` int NOT NULL DEFAULT '0' COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态：0=禁用,1=启用',
  `createtime` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `updatetime` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_aid_bid` (`aid`,`bid`) USING BTREE,
  KEY `idx_status` (`status`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='员工分类表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_staff_commission_summary`
--

DROP TABLE IF EXISTS `ddwx_staff_commission_summary`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_staff_commission_summary` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `aid` int NOT NULL DEFAULT '0' COMMENT '代理商ID',
  `bid` int NOT NULL DEFAULT '0' COMMENT '店铺ID',
  `staff_id` int NOT NULL DEFAULT '0' COMMENT '员工ID',
  `staff_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '员工姓名',
  `date` date NOT NULL COMMENT '日期',
  `total_commission` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '提成总额',
  `settled_commission` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '已结算提成',
  `pending_commission` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '待结算提成',
  `order_count` int NOT NULL DEFAULT '0' COMMENT '订单数量',
  `createtime` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `updatetime` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `idx_staff_date` (`staff_id`,`date`) USING BTREE,
  KEY `idx_aid_bid` (`aid`,`bid`) USING BTREE,
  KEY `idx_date` (`date`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='员工提成统计汇总表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_sysset`
--

DROP TABLE IF EXISTS `ddwx_sysset`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_sysset` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL COMMENT '配置名称',
  `value` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '配置值(JSON格式)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb3 COMMENT='系统配置表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_template_category`
--

DROP TABLE IF EXISTS `ddwx_template_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_template_category` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '分类名称',
  `parent_id` int NOT NULL DEFAULT '0' COMMENT '父分类ID',
  `icon` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '分类图标',
  `sort` int NOT NULL DEFAULT '0' COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态：0禁用 1启用',
  `create_time` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `parent_id` (`parent_id`) USING BTREE,
  KEY `sort` (`sort`) USING BTREE,
  KEY `status` (`status`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='模板分类表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_template_library`
--

DROP TABLE IF EXISTS `ddwx_template_library`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_template_library` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '模板名称',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '模板描述',
  `category_id` int NOT NULL DEFAULT '0' COMMENT '分类ID',
  `industry_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '行业类型',
  `scene_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '场景类型',
  `preview_image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '预览图片',
  `template_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '模板数据JSON',
  `page_info` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '页面配置信息',
  `version` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '1.0' COMMENT '版本号',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '状态：0待审核 1已发布 2已下架',
  `is_featured` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否精选：0否 1是',
  `use_count` int NOT NULL DEFAULT '0' COMMENT '使用次数',
  `rating` decimal(3,2) NOT NULL COMMENT '评分',
  `creator_aid` int NOT NULL DEFAULT '0' COMMENT '创建者应用ID',
  `creator_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '创建者名称',
  `create_time` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  `publish_time` int NOT NULL DEFAULT '0' COMMENT '发布时间',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `category_id` (`category_id`) USING BTREE,
  KEY `industry_type` (`industry_type`) USING BTREE,
  KEY `status` (`status`) USING BTREE,
  KEY `is_featured` (`is_featured`) USING BTREE,
  KEY `create_time` (`create_time`) USING BTREE,
  KEY `use_count` (`use_count`) USING BTREE,
  KEY `rating` (`rating`) USING BTREE,
  KEY `idx_template_library_search` (`status`,`category_id`,`industry_type`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='DIY模板库';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_template_rating`
--

DROP TABLE IF EXISTS `ddwx_template_rating`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_template_rating` (
  `id` int NOT NULL AUTO_INCREMENT,
  `template_id` int NOT NULL DEFAULT '0' COMMENT '模板ID',
  `aid` int NOT NULL DEFAULT '0' COMMENT '应用ID',
  `rating` tinyint(1) NOT NULL DEFAULT '5' COMMENT '评分：1-5星',
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '评价内容',
  `create_time` int NOT NULL DEFAULT '0' COMMENT '评价时间',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `template_id` (`template_id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `rating` (`rating`) USING BTREE,
  KEY `create_time` (`create_time`) USING BTREE,
  KEY `idx_template_rating_stats` (`template_id`,`rating`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='模板评价表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_template_usage`
--

DROP TABLE IF EXISTS `ddwx_template_usage`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_template_usage` (
  `id` int NOT NULL AUTO_INCREMENT,
  `template_id` int NOT NULL DEFAULT '0' COMMENT '模板ID',
  `aid` int NOT NULL DEFAULT '0' COMMENT '应用ID',
  `bid` int NOT NULL DEFAULT '0' COMMENT '商户ID',
  `page_id` int NOT NULL DEFAULT '0' COMMENT '生成的页面ID',
  `use_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '使用类型：1复制 2基于修改',
  `create_time` int NOT NULL DEFAULT '0' COMMENT '使用时间',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `template_id` (`template_id`) USING BTREE,
  KEY `aid_bid` (`aid`,`bid`) USING BTREE,
  KEY `create_time` (`create_time`) USING BTREE,
  KEY `idx_template_usage_stats` (`template_id`,`create_time`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='模板使用记录表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_toutiaopay_log`
--

DROP TABLE IF EXISTS `ddwx_toutiaopay_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_toutiaopay_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `mid` int DEFAULT NULL,
  `openid` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tablename` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `ordernum` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `mch_id` int DEFAULT NULL,
  `transaction_id` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `total_fee` decimal(11,2) DEFAULT NULL,
  `givescore` int DEFAULT '0',
  `createtime` int DEFAULT NULL,
  `fenzhangmoney` decimal(11,2) DEFAULT NULL,
  `isfenzhang` tinyint(1) DEFAULT '0',
  `fz_ordernum` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `fz_errmsg` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE,
  KEY `createtime` (`createtime`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_tuanzhang`
--

DROP TABLE IF EXISTS `ddwx_tuanzhang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_tuanzhang` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `mid` int DEFAULT NULL,
  `mid2` int DEFAULT '0',
  `cid` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '0',
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `logo` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `desc` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '账号描述',
  `weixinpic` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `linkman` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `linktel` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tel` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `pics` varchar(1000) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `content` longtext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `province` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `city` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `district` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `address` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `zhengming` varchar(1000) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `longitude` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '经度',
  `latitude` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '纬度',
  `money` decimal(11,2) DEFAULT NULL,
  `jinhuokuan` decimal(10,2) DEFAULT NULL,
  `endtime` int NOT NULL DEFAULT '0' COMMENT '到期时间(UNIX时间戳)',
  `weixin` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `aliaccount` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `bankname` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `bankcarduser` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `bankcardnum` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `sales` int DEFAULT '0',
  `feepercent` float(11,2) DEFAULT '0.00' COMMENT '费率',
  `feepercent_freight` float(11,2) DEFAULT '0.00' COMMENT '配送费费率',
  `sort` int DEFAULT '0',
  `storage_limit` int NOT NULL DEFAULT '0' COMMENT '存储空间限制(MB，0表示不限制)',
  `createtime` int DEFAULT NULL,
  `status` tinyint(1) DEFAULT '1',
  `reason` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `comment_score` decimal(2,1) DEFAULT '5.0',
  `comment_num` int DEFAULT '0',
  `comment_haopercent` int DEFAULT '100' COMMENT '好评率',
  `start_hours` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '00:00',
  `end_hours` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '00:00',
  `invoice` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '发票 0关 1开',
  `invoice_type` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '1' COMMENT '发票类型 1普通 2专票',
  `is_open` tinyint(1) DEFAULT '1',
  `autocollecthour` int DEFAULT '168',
  `start_hours2` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '00:00',
  `end_hours2` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '00:00',
  `start_hours3` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '00:00',
  `end_hours3` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '00:00',
  `wxpayst` tinyint(1) DEFAULT '0',
  `wxpay_submchid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `express_wx_shop_no` varchar(60) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '商家门店编号',
  `kfurl` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `sxpay_mno` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `sxpay_mchkey` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tuozhanid` varchar(11) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '0',
  `yihuo_baifenbi` decimal(10,2) NOT NULL,
  `province_id` int unsigned NOT NULL DEFAULT '0' COMMENT '省ID',
  `city_id` int unsigned NOT NULL DEFAULT '0' COMMENT '城市ID',
  `district_id` int unsigned NOT NULL DEFAULT '0' COMMENT '区县ID',
  `jlpay_mno` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `jlpay_zd` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `jlpay_zd2` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `wxid` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '联系人微信号',
  `shop_url` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '线上店铺链接',
  `shop_address` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '线下店铺地址',
  `group_pics` longtext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '微信群截图',
  `license_pics` longtext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '营业执照图片',
  `conditions` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '申请条件(1:微信群,2:线上店铺,3:线下店铺)',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE,
  KEY `cid` (`cid`) USING BTREE,
  KEY `status` (`status`) USING BTREE,
  KEY `business_province_group` (`province_id`) USING BTREE,
  KEY `business_district_group` (`district_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_user_allocation_log`
--

DROP TABLE IF EXISTS `ddwx_user_allocation_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_user_allocation_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `mid` int DEFAULT NULL,
  `money` decimal(11,2) DEFAULT NULL,
  `after` decimal(11,2) DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_webmessage`
--

DROP TABLE IF EXISTS `ddwx_webmessage`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_webmessage` (
  `id` int NOT NULL AUTO_INCREMENT,
  `realname` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tel` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `content` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `ip` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  `status` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_wifiprint_set`
--

DROP TABLE IF EXISTS `ddwx_wifiprint_set`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_wifiprint_set` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `bid` int DEFAULT '0',
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '打印机名称',
  `title` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '小票标题',
  `client_id` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `client_secret` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `access_token` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `machine_code` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `msign` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `status` tinyint(1) DEFAULT '1',
  `print_ps` tinyint(1) DEFAULT '1' COMMENT '打印配送订单',
  `print_zt_type` tinyint(1) DEFAULT '1' COMMENT '打印自提订单 0不打印 1全部门店 2选择门店打印',
  `print_zt_mdid` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `voice` tinyint(1) DEFAULT '2' COMMENT '0不播报 1低音 2中音 3高音',
  `createtime` int DEFAULT NULL,
  `type` tinyint(1) DEFAULT '0',
  `autoprint` tinyint(1) DEFAULT '1',
  `machine_type` tinyint(1) DEFAULT '0',
  `tmpltype` tinyint(1) DEFAULT '0',
  `tmplcontent` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `width` int DEFAULT '40',
  `height` int DEFAULT '30',
  `print_maidan` tinyint(1) DEFAULT '1' COMMENT '买单打印',
  `print_cashdesk` tinyint(1) DEFAULT '1' COMMENT '收银台打印',
  `boot_custom` tinyint(1) NOT NULL DEFAULT '0' COMMENT '底部自定义 0：关闭 1：开启',
  `boot_custom_content` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '底部自定义内容',
  `day_ordernum` tinyint(1) NOT NULL DEFAULT '0' COMMENT '日单号 0: 关闭 1：开启',
  `print_num` int NOT NULL DEFAULT '1' COMMENT '打印份数',
  `rsa_publickey` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT 'k8打印机平台公钥 验签',
  `aes_publickey` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT 'k8打印机平台公钥 解密公钥',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_wx_advert`
--

DROP TABLE IF EXISTS `ddwx_wx_advert`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_wx_advert` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `bid` int DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `type` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `ad_unit_id` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '广告ID',
  `code` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '代码',
  `tmpl_id` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '模板广告的模板ID',
  `createtime` int DEFAULT NULL,
  `status` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_wx_keyword`
--

DROP TABLE IF EXISTS `ddwx_wx_keyword`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_wx_keyword` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `keyword` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `ktype` int DEFAULT '0' COMMENT '0完全匹配 1包含匹配 2关注回复',
  `msgtype` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `content` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `sort` int DEFAULT '0',
  `createtime` int DEFAULT NULL,
  `status` int DEFAULT '1',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `status` (`status`) USING BTREE,
  KEY `ktype` (`ktype`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_wx_tmplset`
--

DROP TABLE IF EXISTS `ddwx_wx_tmplset`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_wx_tmplset` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `tmpl_kucunbuzu` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_set` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_orderfahuo` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_tuisuccess` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_tuierror` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_tixiansuccess` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_tixianerror` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_collagesuccess` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_fuwudaoqi` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_shenhe` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_orderfahuo_new` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_tuisuccess_new` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_tuierror_new` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_tixiansuccess_new` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_tixianerror_new` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_collagesuccess_new` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_shenhe_new` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_orderconfirm` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_ordershouhuo` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_ordertui` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_withdraw` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_kehuzixun` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_choujiang` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tmpl_fenxiaodaozhang_new` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL COMMENT '分销到账订阅消息通知',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `aid` (`aid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_wx_transfer_log`
--

DROP TABLE IF EXISTS `ddwx_wx_transfer_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_wx_transfer_log` (
  `id` int NOT NULL,
  `aid` int DEFAULT NULL,
  `mid` int DEFAULT '0' COMMENT '会员id',
  `ordernum` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '订单号',
  `money` decimal(12,2) DEFAULT NULL COMMENT '转账的金额',
  `realname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '收款人的真实姓名',
  `desc` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '对此次转账操作的描述信息',
  `data_tbl` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '与该转账操作相关的数据表名称',
  `data_id` int DEFAULT NULL COMMENT '与该转账操作相关的数据表的行的唯一标识符',
  `status_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '微信支付 API 返回的状态码，用于表示转账操作的状态',
  `createtime` int DEFAULT NULL COMMENT '转账操作的时间戳',
  `package_info` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '用户确认页面的信息',
  `state` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '转账状态',
  `transfer_bill_no` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '微信支付返回的转账单号',
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '转账失败的原因'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='新版微信转账记录';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_wx_upload_shipping`
--

DROP TABLE IF EXISTS `ddwx_wx_upload_shipping`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_wx_upload_shipping` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `mid` int DEFAULT NULL,
  `wxpaylogid` int DEFAULT NULL,
  `postdata` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `openid` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tablename` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `ordernum` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `mch_id` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  `nexttime` int DEFAULT NULL COMMENT '下次运行时间',
  `times_failed` tinyint DEFAULT '0' COMMENT '失败次数',
  `status` tinyint DEFAULT '0' COMMENT '0默认，1成功，-1失败 记录最后一次',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE,
  KEY `nexttime` (`nexttime`) USING BTREE,
  KEY `times_failed` (`times_failed`) USING BTREE,
  KEY `status` (`status`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=165 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='微信小程序录入发货信息';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_wx_url`
--

DROP TABLE IF EXISTS `ddwx_wx_url`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_wx_url` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `path` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `url` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  `endtime` int DEFAULT NULL,
  `days` int DEFAULT '30',
  `sort` int DEFAULT '0',
  `code` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_wxpay_log`
--

DROP TABLE IF EXISTS `ddwx_wxpay_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_wxpay_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `mid` int DEFAULT NULL,
  `openid` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `tablename` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `ordernum` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `mch_id` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `transaction_id` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `total_fee` decimal(11,2) DEFAULT NULL,
  `givescore` int DEFAULT '0',
  `createtime` int DEFAULT NULL,
  `fenzhangmoney` decimal(11,2) DEFAULT NULL,
  `fenzhangmoney2` decimal(11,2) DEFAULT NULL,
  `isfenzhang` tinyint(1) DEFAULT '0',
  `fz_ordernum` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `fz_errmsg` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `sub_mchid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `platform` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `bid` int DEFAULT '0',
  `refund_money` decimal(11,2) DEFAULT NULL,
  `is_upload_shipping_info` tinyint(1) DEFAULT '0' COMMENT '是否录入小程序发货信息',
  `paysetjson` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE,
  KEY `createtime` (`createtime`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=4049 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_wxpic_cache`
--

DROP TABLE IF EXISTS `ddwx_wxpic_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_wxpic_cache` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pic` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `media_id` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `img_url` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_wxrefund_log`
--

DROP TABLE IF EXISTS `ddwx_wxrefund_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_wxrefund_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `mch_id` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `ordernum` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `out_refund_no` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `totalprice` decimal(11,2) DEFAULT NULL,
  `refundmoney` decimal(11,2) DEFAULT NULL,
  `createtime` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `status` tinyint(1) DEFAULT '0',
  `remark` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `errmsg` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=69 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_wxvideo_brand_apply`
--

DROP TABLE IF EXISTS `ddwx_wxvideo_brand_apply`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_wxvideo_brand_apply` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `license` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `brand_audit_type` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `trademark_type` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `brand_management_type` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `commodity_origin_type` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `brand_wording` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `sale_authorization` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `trademark_registration_certificate` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `trademark_change_certificate` varchar(11) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `trademark_registrant` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `trademark_registrant_nu` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `trademark_authorization_period` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `trademark_registration_application` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `trademark_applicant` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `trademark_application_time` datetime DEFAULT NULL,
  `imported_goods_form` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  `audit_id` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `status` tinyint(1) DEFAULT '0' COMMENT '0审核中 1已通过 2驳回',
  `reject_reason` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '驳回原因',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_wxvideo_category_apply`
--

DROP TABLE IF EXISTS `ddwx_wxvideo_category_apply`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_wxvideo_category_apply` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int DEFAULT NULL,
  `license` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `first_cat_id` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `first_cat_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `second_cat_id` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `second_cat_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `third_cat_id` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `third_cat_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `certificate` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  `audit_id` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `status` tinyint(1) DEFAULT '0' COMMENT '0审核中 1已通过 2驳回',
  `reject_reason` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '驳回原因',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_wxvideo_catelist`
--

DROP TABLE IF EXISTS `ddwx_wxvideo_catelist`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_wxvideo_catelist` (
  `id` int NOT NULL AUTO_INCREMENT,
  `third_cat_id` int DEFAULT NULL,
  `third_cat_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `qualification` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `qualification_type` tinyint(1) DEFAULT '0' COMMENT '类目资质类型,0:不需要,1:必填,2:选填',
  `product_qualification` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `product_qualification_type` tinyint(1) DEFAULT '0' COMMENT '商品资质类型,0:不需要,1:必填,2:选填',
  `second_cat_id` int DEFAULT NULL,
  `second_cat_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `first_cat_id` int DEFAULT NULL,
  `first_cat_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_xsyjfh_send`
--

DROP TABLE IF EXISTS `ddwx_xsyjfh_send`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_xsyjfh_send` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `aid` int NOT NULL DEFAULT '0',
  `starttime` int NOT NULL DEFAULT '0',
  `endtime` int NOT NULL DEFAULT '0',
  `jiangjinchi` decimal(11,2) NOT NULL,
  `zongdianshu` decimal(10,2) NOT NULL,
  `renshu` int NOT NULL DEFAULT '0',
  `zhixingtime` int NOT NULL DEFAULT '0',
  `idstr` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_yun_nahuo`
--

DROP TABLE IF EXISTS `ddwx_yun_nahuo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_yun_nahuo` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int NOT NULL DEFAULT '0',
  `mid` int NOT NULL DEFAULT '0',
  `nahuoid` int NOT NULL DEFAULT '0',
  `proid` int NOT NULL DEFAULT '0',
  `ggid` int NOT NULL DEFAULT '0',
  `proname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ggname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `pic` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `num` int NOT NULL DEFAULT '0',
  `price` decimal(11,2) NOT NULL,
  `pid` int NOT NULL DEFAULT '0',
  `pidprice` decimal(11,2) NOT NULL,
  `peiyanglevel1` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `peiyang1` int DEFAULT NULL,
  `peiyang2` int DEFAULT NULL,
  `peiyanglevel2` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  `ylevelid` int NOT NULL DEFAULT '0',
  `orderid` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_zhuanjifen_log`
--

DROP TABLE IF EXISTS `ddwx_zhuanjifen_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_zhuanjifen_log` (
  `uid` int NOT NULL COMMENT '用户id',
  `phone` varchar(11) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL COMMENT '手机号',
  `target_id` int NOT NULL,
  `charge_money` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL COMMENT '手续费',
  `after_money` double DEFAULT NULL,
  `before_money` double DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  `remark` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int NOT NULL,
  `mid` int DEFAULT NULL,
  `money` varchar(244) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `id` (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=863 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_zhuanxiaofeizhi_log`
--

DROP TABLE IF EXISTS `ddwx_zhuanxiaofeizhi_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_zhuanxiaofeizhi_log` (
  `uid` int NOT NULL COMMENT '用户id',
  `phone` varchar(11) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL COMMENT '手机号',
  `target_id` int NOT NULL,
  `charge_money` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL COMMENT '手续费',
  `after_money` double DEFAULT NULL,
  `before_money` double DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  `remark` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int NOT NULL,
  `mid` int DEFAULT NULL,
  `money` varchar(244) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ddwx_zhuanzhang_log`
--

DROP TABLE IF EXISTS `ddwx_zhuanzhang_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ddwx_zhuanzhang_log` (
  `uid` int NOT NULL COMMENT '用户id',
  `phone` varchar(11) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL COMMENT '手机号',
  `target_id` int NOT NULL,
  `charge_money` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL COMMENT '手续费',
  `after_money` double DEFAULT NULL,
  `before_money` double DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  `remark` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `id` int NOT NULL AUTO_INCREMENT,
  `aid` int NOT NULL,
  `mid` int DEFAULT NULL,
  `money` varchar(244) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=741 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tmp_if_not_exists_test`
--

DROP TABLE IF EXISTS `tmp_if_not_exists_test`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tmp_if_not_exists_test` (
  `id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping routines for database 'tpdaxiangshop'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-09 16:01:37


-- Base package schema patches. These are applied only to the exported SQL.
ALTER TABLE `ddwx_member_level` MODIFY `commission_parent` decimal(11,2) NOT NULL DEFAULT '0' COMMENT '持续推荐奖励';
ALTER TABLE `ddwx_member_level` MODIFY `commission_parent_pj` decimal(11,2) NOT NULL DEFAULT '0';
ALTER TABLE `ddwx_member_level` MODIFY `fenhong_max_money` decimal(11,2) NOT NULL DEFAULT '0';
ALTER TABLE `ddwx_member_level` MODIFY `teamfenhong_money` decimal(11,2) NOT NULL DEFAULT '0' COMMENT '团队分红每单奖励';
ALTER TABLE `ddwx_member_level` MODIFY `product_teamfenhong_money` decimal(11,2) NOT NULL DEFAULT '0';
ALTER TABLE `ddwx_member_level` MODIFY `level_teamfenhong_money` decimal(11,2) NOT NULL DEFAULT '0';
ALTER TABLE `ddwx_member_level` MODIFY `up_give_commission` decimal(11,2) NOT NULL DEFAULT '0' COMMENT '升级赠送佣金';
ALTER TABLE `ddwx_member_level` MODIFY `up_give_parent_money` decimal(11,2) NOT NULL DEFAULT '0' COMMENT '升级给上级赠送余额';
ALTER TABLE `ddwx_member_level` MODIFY `up_orderpaymoney` decimal(10,2) NOT NULL DEFAULT '0' COMMENT '一次性订单支付金额满';
ALTER TABLE `ddwx_member_level` MODIFY `fh_commission2` decimal(12,3) NOT NULL DEFAULT '0';
ALTER TABLE `ddwx_member_level` MODIFY `fh_commission3` decimal(12,3) NOT NULL DEFAULT '0';
ALTER TABLE `ddwx_member_level` MODIFY `fh_commission4` decimal(12,3) NOT NULL DEFAULT '0';
ALTER TABLE `ddwx_member_level` MODIFY `up_tuandui` decimal(11,2) NOT NULL DEFAULT '0';
ALTER TABLE `ddwx_member_level` MODIFY `xfz_commission2` decimal(10,2) NOT NULL DEFAULT '0';
ALTER TABLE `ddwx_member_level` MODIFY `xfz_commission3` decimal(10,2) NOT NULL DEFAULT '0';
ALTER TABLE `ddwx_member_level` MODIFY `xfz_commission4` decimal(10,2) NOT NULL DEFAULT '0';
ALTER TABLE `ddwx_member_level` MODIFY `fenyong_jifen` decimal(10,2) NOT NULL DEFAULT '0';
ALTER TABLE `ddwx_member_level` MODIFY `up_zt_new_dengjiyeji` decimal(11,2) NOT NULL DEFAULT '0';
ALTER TABLE `ddwx_member_level` MODIFY `up_zt_renshu_tuanduiyeji` decimal(11,2) NOT NULL DEFAULT '0';
ALTER TABLE `ddwx_member_level` MODIFY `zuigao_score` decimal(10,2) NOT NULL DEFAULT '0';
ALTER TABLE `ddwx_member_level` MODIFY `commission1_score` decimal(10,2) NOT NULL DEFAULT '0';
ALTER TABLE `ddwx_member_level` MODIFY `commission2_score` decimal(10,2) NOT NULL DEFAULT '0';
ALTER TABLE `ddwx_member_level` MODIFY `commission3_score` decimal(10,2) NOT NULL DEFAULT '0';
ALTER TABLE `ddwx_member_level` MODIFY `commission_parent_score` decimal(10,2) NOT NULL DEFAULT '0';
ALTER TABLE `ddwx_member_level` MODIFY `commission_parent_pj_score` decimal(10,2) NOT NULL DEFAULT '0';
ALTER TABLE `ddwx_member_level` MODIFY `pyj_baifenbi` decimal(10,2) NOT NULL DEFAULT '0';
ALTER TABLE `ddwx_member_level` MODIFY `up_mei_order_monnnney` decimal(11,2) NOT NULL DEFAULT '0';
ALTER TABLE `ddwx_member_level` MODIFY `up_zt_new_dengjiyeji_duo` decimal(11,2) NOT NULL DEFAULT '0';
ALTER TABLE `ddwx_member_level` MODIFY `up_zt_new2_tuanduiyeji` decimal(10,2) NOT NULL DEFAULT '0';
ALTER TABLE `ddwx_member_level` MODIFY `up_zt_new3_tuanduiyeji` decimal(11,2) NOT NULL DEFAULT '0';
ALTER TABLE `ddwx_member_level` MODIFY `commissionshouyi1` decimal(11,2) NOT NULL DEFAULT '0';
ALTER TABLE `ddwx_member_level` MODIFY `commissionshouyi2` decimal(11,2) NOT NULL DEFAULT '0';
ALTER TABLE `ddwx_member_level` MODIFY `commissionshouyi3` decimal(11,2) NOT NULL DEFAULT '0';
ALTER TABLE `ddwx_member_level` MODIFY `commissionshouyi_parent` decimal(11,2) NOT NULL DEFAULT '0';
ALTER TABLE `ddwx_member_level` MODIFY `oneordermoney` decimal(11,2) NOT NULL DEFAULT '0';
ALTER TABLE `ddwx_member_level` MODIFY `up_give_xianjinquan` decimal(11,2) NOT NULL DEFAULT '0';
ALTER TABLE `ddwx_member_level` MODIFY `pingjilevelidjiangli` decimal(11,2) NOT NULL DEFAULT '0';
ALTER TABLE `ddwx_member_level` MODIFY `pingjilevelidjiangli2` decimal(11,2) NOT NULL DEFAULT '0';
ALTER TABLE `ddwx_member_level` MODIFY `pingjilevelidjiangli3` decimal(11,2) NOT NULL DEFAULT '0';
ALTER TABLE `ddwx_member_level` MODIFY `peiyang_jiangli1` decimal(10,2) NOT NULL DEFAULT '0';
ALTER TABLE `ddwx_member_level` MODIFY `peiyang_jiangli2` decimal(11,2) NOT NULL DEFAULT '0';
ALTER TABLE `ddwx_member_level` MODIFY `suodingpeiyang1` decimal(11,2) NOT NULL DEFAULT '0';
ALTER TABLE `ddwx_member_level` MODIFY `suodingpeiyang2` decimal(11,2) NOT NULL DEFAULT '0';
ALTER TABLE `ddwx_member_level` MODIFY `jiedian_teamfenhonglv` decimal(10,2) NOT NULL DEFAULT '0';
ALTER TABLE `ddwx_member_level` MODIFY `jiedian_teamfenhong_money` decimal(11,2) NOT NULL DEFAULT '0';
ALTER TABLE `ddwx_member_level` MODIFY `zt_sy_yong1` decimal(11,2) NOT NULL DEFAULT '0';
ALTER TABLE `ddwx_member_level` MODIFY `zt_sy_yong2` decimal(11,2) NOT NULL DEFAULT '0';
ALTER TABLE `ddwx_member_level` MODIFY `jt_sy_yong` decimal(11,2) NOT NULL DEFAULT '0';
ALTER TABLE `ddwx_member_level` MODIFY `tjmoney` decimal(10,2) NOT NULL DEFAULT '0';
ALTER TABLE `ddwx_member_level` MODIFY `tjmoney_one` decimal(10,2) NOT NULL DEFAULT '0';
ALTER TABLE `ddwx_member_level` MODIFY `tjmoney_two` decimal(11,2) NOT NULL DEFAULT '0';
ALTER TABLE `ddwx_member_level` MODIFY `paidui_jiang` decimal(10,2) NOT NULL DEFAULT '0';
ALTER TABLE `ddwx_member_level` MODIFY `paidui_give` decimal(11,2) NOT NULL DEFAULT '0';
ALTER TABLE `ddwx_member_level` MODIFY `up_give_max_single_reward` decimal(10,2) NOT NULL DEFAULT '0' COMMENT '升级奖励单笔最大奖励金额';
ALTER TABLE `ddwx_member_level` MODIFY `small_market_fenhong_percent` decimal(10,2) NOT NULL DEFAULT '0' COMMENT '小市场分红比例';
ALTER TABLE `ddwx_member_level` MODIFY `small_market_fenhong_money` decimal(10,2) NOT NULL DEFAULT '0' COMMENT '小市场每单固定奖励';
ALTER TABLE `ddwx_member_level` MODIFY `small_market_teamfenhongbl` decimal(8,2) NOT NULL DEFAULT '0' COMMENT '小市场团队分红比例(%)：从订单金额中提取的分红比例';
ALTER TABLE `ddwx_member_level` MODIFY `small_market_teamfenhong_money` decimal(8,2) NOT NULL DEFAULT '0' COMMENT '小市场团队每单分红金额：除比例分红外的固定奖励';
ALTER TABLE `ddwx_member_level` MODIFY `small_market_td_fh_j` decimal(8,2) NOT NULL DEFAULT '0' COMMENT '小市场团队平级奖比例(%)：用于计算同等级间的分红';
ALTER TABLE `ddwx_member_level` MODIFY `jiedian_zhitui_money` decimal(10,2) NOT NULL DEFAULT '0' COMMENT '接点人直推奖励金额';
ALTER TABLE `ddwx_member_level` MODIFY `team_reward_limit_percent` decimal(5,2) NOT NULL DEFAULT '0' COMMENT '团队奖限额百分比(%)';
ALTER TABLE `ddwx_member_level` MODIFY `freight` varchar(512) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '' COMMENT '运费配置';
ALTER TABLE `ddwx_member_level` MODIFY `up_newdownlevelids` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '' COMMENT '升级包含ids 逗号分割';
ALTER TABLE `ddwx_admin_set` MODIFY `team_agent_list_enable` tinyint(1) NOT NULL DEFAULT '1' COMMENT '接点人列表开关 1=开启 0=关闭';
ALTER TABLE `ddwx_admin_set` MODIFY `team_agent_list_text` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '接点人列表' COMMENT '接点人列表自定义名称';
ALTER TABLE `ddwx_admin_setapp_mp` MODIFY `jlpay_mno` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '';
ALTER TABLE `ddwx_admin_setapp_mp` MODIFY `jlpay_zd` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '';
ALTER TABLE `ddwx_admin_setapp_mp` MODIFY `fbpay_mno` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '';
ALTER TABLE `ddwx_admin_setapp_mp` MODIFY `fbpay_store` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '';
ALTER TABLE `ddwx_admin_setapp_wx` MODIFY `fbpay_store` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '';
ALTER TABLE `ddwx_admin_setapp_wx` MODIFY `jlpay_mno` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '';
ALTER TABLE `ddwx_admin_setapp_wx` MODIFY `jlpay_zd` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '';
ALTER TABLE `ddwx_wx_tmplset` MODIFY `tmpl_fenxiaodaozhang_new` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '' COMMENT '分销到账订阅消息通知';

-- Base package initial data. Exported only from the sanitized seed database.
SET FOREIGN_KEY_CHECKS=0;
-- MySQL dump 10.13  Distrib 8.0.45, for Linux (aarch64)
--
-- Host: localhost    Database: tp_delivery_adminphp_core_seed_20260605_0928
-- ------------------------------------------------------
-- Server version	8.0.45

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `ddwx_admin`
--

LOCK TABLES `ddwx_admin` WRITE;
/*!40000 ALTER TABLE `ddwx_admin` DISABLE KEYS */;
INSERT INTO `ddwx_admin` (`id`, `owner_root_uid`, `creator_uid`, `createtime`, `token`, `chouchengset`, `chouchengrate`, `chouchengmin`, `chouchengmoney`, `linkman`, `tel`, `province`, `city`, `area`, `address`, `platform`, `status`, `endtime`, `mianzhen_num`, `pid`, `remote`, `copyright`, `domain`, `remark`, `order_show_onlychildren`, `choucheng_receivertype`, `choucheng_receivertype1_account`, `choucheng_receivertype1_name`, `choucheng_receivertype2_openidtype`, `choucheng_receivertype2_account`, `choucheng_receivertype2_accountwx`, `choucheng_receivertype2_name`, `face_times`, `storage_limit`) VALUES (1,0,0,1668741341,'ce_demo_token',0,'','','','','',NULL,NULL,NULL,NULL,'mp,wx,alipay,baidu,toutiao,qq,h5,app',1,1731899708,0,0,'{\"type\":\"0\",\"alioss\":{\"key\":\"\",\"secret\":\"\",\"bucket\":\"\",\"ossurl\":\"\",\"url\":\"\"},\"qiniu\":{\"accesskey\":\"\",\"secretkey\":\"\",\"bucket\":\"\",\"url\":\"\"},\"cos\":{\"appid\":\"\",\"secretid\":\"\",\"secretkey\":\"\",\"bucket\":\"\",\"local\":\"\",\"url\":\"\"}}','','','',0,0,'','',0,'','','',0,0);
/*!40000 ALTER TABLE `ddwx_admin` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-09 16:01:38

-- MySQL dump 10.13  Distrib 8.0.45, for Linux (aarch64)
--
-- Host: localhost    Database: tp_delivery_adminphp_core_seed_20260605_0928
-- ------------------------------------------------------
-- Server version	8.0.45

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `ddwx_admin_set`
--

LOCK TABLES `ddwx_admin_set` WRITE;
/*!40000 ALTER TABLE `ddwx_admin_set` DISABLE KEYS */;
INSERT INTO `ddwx_admin_set` (`id`, `yongzhuan`, `jifenchi_mode`, `jifenchi_jingtai`, `jifenchi_dongtai`, `aid`, `lvjifenjiazhi`, `xiaofeizhijiazhi`, `xiaofeizhizhuan`, `pingtaizhanghao`, `name`, `logo`, `mode`, `outernet_url`, `custom_index_enabled`, `custom_index_url`, `desc`, `tel`, `province`, `city`, `district`, `address`, `longitude`, `latitude`, `color1`, `color2`, `moneypay`, `recharge`, `money_transfer`, `withdraw_weixin`, `withdraw_aliaccount`, `withdraw_bankcard`, `withdraw_cpcn_balance`, `withdraw_cpcn_commission`, `cpcn_withdraw_mode`, `withdraw`, `withdraw_autotransfer`, `withdrawmin`, `withbeishu`, `withdrawfee`, `withdraw_fixed_fee`, `comwithdraw`, `comwithdraw_need_paycode`, `comwithdrawmin`, `comwithdrawfee`, `comwithdraw_fixed_fee`, `comwithdrawdate`, `comwithdrawbl`, `commission2scorepercent`, `commission2moneypercent1`, `commission2moneypercent2`, `banner_levelup`, `banner_usercenter`, `commission2money`, `commission2money_money`, `commission2money_score`, `commission2money_hei`, `commission2money_fee`, `commission_autowithdraw`, `subscribeurl`, `score2money`, `score2money_grow_on`, `score2money_grow_mode`, `score2money_grow_value`, `score2money_grow_last_ym`, `scorein_money`, `scorein_score`, `scorecz_money`, `scorecz_score`, `scorebdkyf`, `score_transfer`, `score_withdraw`, `score_withdraw_percent_day`, `score_to_money_percent`, `kfurl`, `wxkf`, `wxkfurl`, `corpid`, `wxkftransfer`, `gzts`, `ddbb`, `ddbbtourl`, `scoredkmaxpercent`, `miandanst`, `commissiontype`, `fxjiesuantype`, `fhjiesuantime_type`, `fxjiesuantime`, `fxjiesuantime_delaydays`, `fhjiesuantype`, `fhjiesuantime`, `fhjiesuanhb`, `fhjiesuanbusiness`, `partner_jiaquan`, `partner_gongxian`, `areafenhong_jiaquan`, `areafenhong_checktype`, `teamyeji_show`, `teamnum_show`, `gongxianfenhong_show`, `gongxianfenhong_txt`, `maidanfenxiao`, `logintype_mp`, `logintype_wx`, `logintype_alipay`, `logintype_baidu`, `logintype_toutiao`, `logintype_qq`, `logintype_h5`, `logintype_app`, `google_client_id`, `login_bind`, `login_setnickname`, `login_mast`, `login_popup_status`, `login_popup_platforms`, `reg_invite_code`, `reg_invite_code_type`, `textset`, `gettj`, `gettjtip`, `pay_transfer`, `pay_transfer_account_name`, `pay_transfer_account`, `pay_transfer_bank`, `pay_transfer_desc`, `invoice`, `invoice_type`, `invoice_rate`, `teamfenhong_differential`, `teamfenhong_differential_pj`, `areafenhong_differential`, `reg_check`, `locking_pwd`, `appurl`, `huidong_status`, `huidong_url`, `parent_show`, `my_commission_show`, `pay_transfer_check`, `teamfenhong_show`, `commissionlog_show`, `commissionrecord_show`, `fhorder_show`, `fhlog_show`, `tansrate`, `idcard_check`, `heiscpay`, `heishouxu`, `score2moneyhei`, `scoredkmaxpercenthei`, `scorebdkyfhei`, `cyz_qu`, `heipay`, `wxpay_enabled`, `score2moneyyu`, `scoredkmaxpercentyu`, `scorebdkyfyu`, `hei_tansrate`, `cyz_shi`, `score2moneyhuang`, `scoredkmaxpercenthuang`, `scorebdkyfhuang`, `huang_tansrate`, `gxz_tansrate`, `hei_onemoney_tansrate`, `hei_onemoney`, `gongpaizhi_shi`, `gongpaizhi_ren`, `gongpaizhi_xiaofei`, `gongpaizhi_jiangli`, `xsyj_shi`, `xsyj_yeji`, `xsyj_fhd`, `gongpaizhi_ren_lun`, `yihuo_status`, `yihuo_baifenbi`, `yihuo_jiesuan_status`, `yihuo_sxf`, `yihuo_deduct_gongxianzhi`, `yihuo_gongxianzhi_rate`, `tuozhanyuan_apply_enabled`, `tuozhanyuan_apply_audit`, `tuozhanyuan_xieyi_show`, `tuozhanyuan_xieyi`, `tuozhanyuan_verify_success`, `tuozhanyuan_verify_reject`, `tuozhanyuan_verify_notice`, `tuozhanyuan_verify_normal`, `withdraw_zd_max`, `withdraw_wx_max`, `withdraw_zfb_max`, `withdraw_yhk_max`, `area_on`, `area_set`, `default_template`, `areamode`, `province_id`, `city_id`, `district_id`, `gongpaizhi_lunshu`, `gongpaizhi_jiangli_lunshu`, `lunshu`, `jifenzhuanzhangbeishu`, `qiang_video`, `qiang_status`, `jiange_ci`, `jiange_min`, `qiangti_video`, `tuozhancrm_status`, `idcardbanghao`, `comwithdrawkouxianquan`, `commission_withdraw_reward`, `commission_withdraw_money`, `commission_withdraw_score`, `commission_withdraw_hei`, `commission_withdraw_fee`, `commission_withdraw_deduct_gongxianzhi`, `commission_withdraw_gongxianzhi_rate`, `commission_ratio`, `map_type`, `gaode_key`, `gaode_sk`, `baidu_key`, `qq_key`, `tianditu_key`, `scorehuangwithdraw`, `scorehuangwithdrawmin`, `scorehuangwithdrawfee`, `scorehuangwithdrawkouxianquan`, `shouji_score_percent`, `shouji_money_percent`, `shouji_commission_percent`, `yongzhuan_jifen`, `yongzhuan_yue`, `yongzhuan_xianjinquan`, `yongzhuan_shouxufei`, `xuni_team_shi`, `xuni_team_num`, `yihuo_jiesuan_type`, `zt_sy_calc_mode`, `wx_transfer_type`, `distribution_abc_yeji_show`, `team_list_yeji_show`, `team_list_relation_show`, `team_list_commission_show`, `team_list_level_show`, `team_list_subnum_show`, `team_list_member_count_show`, `my_order_performance_show`, `my_earnings_show`, `ip_capture`, `ip_capture_mode`, `ip_capture_interval`, `ip_address_confirm`, `ip_address_edit`, `ip_address_edit_times`, `money_transfer_need_paypwd`, `money_transfer_target_type`, `score_transfer_need_paypwd`, `xianjinquan_transfer_need_paypwd`, `xianjinquan_to_money_need_paypwd`, `hongbao_transfer_need_paypwd`, `gongxianzhi_transfer_need_paypwd`, `scorehuang_enabled`, `scorehuang_withdraw_enabled`, `scorehuang_withdraw_min`, `scorehuang_withdraw_fee`, `scorehuang_withdraw_date`, `scorehuang_withdraw_weixin`, `scorehuang_withdraw_aliaccount`, `scorehuang_withdraw_bankcard`, `score_to_commission_status`, `score_to_commission_rate`, `score_to_commission_beishu`, `score_to_commission_min`, `score_to_commission_max`, `score_to_commission_need_paypwd`, `score_to_commission_fee_rate`, `maidan_login`, `maidan_getlocation`, `maidan_payaftertourl`, `maidan_balance_pay`, `maidan_score_pay`, `maidan_commission_calc_mode`, `maidan_commission_score_switch`, `business_reward_enabled`, `business_reward_days`, `business_reward_amount`, `business_reward_type`, `business_reward_inviter_rate`, `business_reward_notice`, `business_reward_flow_enabled`, `business_reward_flow_amount`, `small_market_fhjiesuantype`, `team_list_enable`, `team_agent_list_enable`, `team_agent_list_text`, `team_agent_page_settings`, `team_list_direct_count_show`, `team_list_statistics_show`, `team_list_consume_show`, `reg_invite_code_show`, `reg_need_jiedian`, `reg_jiedian_default_invite`, `commission2money_deduct_gongxianzhi`, `commission2money_gongxianzhi_rate`, `tuozhanyuan_simple_apply`, `tuozhanyuan_apply_mode`, `tuozhanyuan_condition_fail_action`, `tuozhanyuan_condition_pass_action`, `team_query_optimize`, `uplv_optimize`, `uplv_async_enabled`, `uplv_async_threshold`, `uplv_async_batch_size`, `uplv_async_max_retry`, `member_auto_addlogin`, `member_auto_reg`, `nologin_day`, `maidan_auto_bind_parent`, `cloud_speaker_enabled`, `cloud_speaker_device_id`, `team_reward_limit_enabled`, `team_lottery_enabled`, `global_lottery_fenhong_percent`, `lottery_mall_url`, `lottery_mall_title`, `jiedian_allow_modify`, `jiedian_modify_times`, `jiedian_need_audit`, `jiedian_modify_interval`, `commission_withdraw_hei_reward`, `commission_withdraw_hei_percent`, `teamfenhong_show_admin`, `teamfenhong_show_phone`, `loading_effect_version`, `loading_icon`, `loading_style`, `storage_limit`, `tuozhanyuan_quota_enabled`, `website_template`, `website_config`, `team_reward_exclude_yeji`, `team_reward_include_self`, `team_show_user_id`, `team_show_zichanfenqi_score`, `team_show_self_performance`, `team_show_monthly_performance`, `team_show_quarterly_performance`, `team_show_monthly_boxes`, `team_show_quarterly_boxes`, `team_boxes_text`, `team_show_yuyue_commission`, `team_show_yuyue_monthly`, `team_show_yuyue_quarterly`, `team_show_yuyue_yearly`, `fenhong_mode`, `fenhong_optimization`, `skeleton_type`, `skeleton_rows`, `skeleton_scenes`, `tuozhanyuan_reward_enabled`, `tuozhanyuan_reward_period`, `tuozhanyuan_reward_type`, `tuozhanyuan_reward_auto`, `tuozhanyuan_reward_include_sub`, `tuozhanyuan_layered_mode`, `tuozhanyuan_equal_level_rate`, `tuozhanyuan_fee_rate_mode`, `uplv_usercenter_enabled`, `uplv_usercenter_check_self`, `uplv_usercenter_check_parent`, `uplv_order_check_parent`, `uplv_order_check_parent_upgrade`, `uplv_limit_parent_count`, `uplv_limit_parent_levels`, `uplv_early_stop`, `reg_jiedian_always_show`, `moneylog_page_settings`) VALUES (1,0.33,0,0.00,0.00,1,0.00,0.00,0.00,1,'Community CE Demo','',0,'',0,'','','0','重庆市','重庆市','九龙坡区','Demo Address','113.17545533180237','41.001637927184795','#3AA578','#F8CF91',1,1,0,1,1,1,0,0,1,1,1,1.00,2.00,10.00,0.00,1,0,1.00,10.00,0.00,'0',100.00,0.00,0.00,0.00,NULL,NULL,1,100.00,0.00,0.00,0.00,0,NULL,'1',0,0,0.0000,0,0.00,0.00,0.00,0.00,1,0,0,0.00,0.0000,'',2,'tel:13800138000','',0,'1',NULL,NULL,10.00,1,0,0,1,1,0.00,0,3,1,1,0,0,0,0,0,0,0,NULL,0,'3','3','1','1','1','1','1,2','3',NULL,1,1,'mp',0,'',1,0,'{\"余额\":\"余额\",\"积分\":\"积分\",\"佣金\":\"佣金\",\"优惠券\":\"优惠券\",\"会员\":\"会员\",\"一级\":\"销售一组\",\"二级\":\"销售二组\",\"团队分红\":\"团队分红\",\"股东分红\":\"股东分红\",\"区域代理分红\":\"区域代理分红\",\"我的团队\":\"我的团队\",\"分销订单\":\"分销订单\",\"自定义表单\":\"自定义表单\",\"推荐人\":\"推荐人\",\"现金券\":\"购物积分\",\"奖励队列\":\"排队中\",\"奖励记录\":\"已完成\",\"贡献值\":\"贡献值\",\"黄积分\":\"黄积分\"}','-1,1','您没有权限进入',0,'','','','',1,NULL,0.00,1,0,0,0,'','',0,NULL,1,1,0,0,1,1,1,1,1,0,0,0.10,1.0000,0.00,1.00,'null',1,1,1.0000,0.00,1,0.05,'0',1.00,0.00,1.00,0.05,0.10,0.10,500.00,1,12,1.00,'null',0,0.00,0,3,1,0.50,0,10.00,0,100.00,0,1,0,NULL,'恭喜您审核通过！您已成为拓展员','审核未通过：',NULL,'温馨提示：成为拓展员后可享受推广分佣权益',2000.00,500.00,600.00,700.00,1,'{\"area_on\": \"1\", \"gettype\": \"2\", \"hotarea\": \"1\", \"areamode\": \"0\", \"hotareas\": \"72,336,35,94,300\", \"switcharea\": \"0\", \"defaultcity\": \"160\", \"hotareas_str\": \"阿拉善盟、安康市、北京市、白城市、毕节市\", \"defaultdistrict\": \"1453\", \"defaultprovince\": \"14\", \"switcharearange\": \"1\", \"defaultcity_name\": \"南昌市\", \"defaultdistrict_name\": \"东湖区\", \"defaultprovince_name\": \"江西省\", \"switcharearangeareas\": \"72,192,144,273\", \"switcharearangeareas_str\": \"阿拉善盟、安阳市、滁州市、重庆市\"}',0,0,22,22,2326,'{\"1\":\"0\",\"2\":\"0\",\"3\":\"0\",\"4\":\"1\",\"5\":\"1\",\"6\":\"0\",\"7\":\"0\",\"8\":\"0\"}','{\"1\":{\"xiaofei\":\"0\"},\"2\":{\"xiaofei\":\"0\",\"zhi\":\"0\",\"jian\":\"0\"},\"3\":{\"xiaofei\":\"0\",\"zhi\":\"0\",\"jian\":\"0\"},\"4\":{\"xiaofei\":\"0\",\"zhi\":\"0\",\"jian\":\"0\"},\"5\":{\"xiaofei\":\"0\",\"zhi\":\"0\",\"jian\":\"0\"},\"6\":{\"xiaofei\":\"0\",\"zhi\":\"0\",\"jian\":\"0\"},\"7\":{\"xiaofei\":\"0\",\"zhi\":\"0\",\"jian\":\"0\"},\"8\":{\"xiaofei\":\"0\",\"zhi\":\"0\",\"jian\":\"0\"}}','null',0,'https://kuaifengimg.example.com/upload/1/20240927/6b38410a87c20b1a6c3ac7fad3338b1d.mp3',1,3,2,'https://kuaifengimg.example.com/upload/1/20240927/827c0477ffa8069a98a34c411f9f4f04.mp3',0,0,0.00,0,100.00,0.00,0.00,0.00,0,100.00,0.00,'gaode','','','','','',0,'','','',0.00,0.00,100.00,0.33,0.33,0.34,0.00,0,0,0,0,0,0,0,0,0,1,0,0,0,0,0,1,24,0,0,0,0,1,0,0,0,0,0,0,0,0.00,0.00,1,0,0,0,0,100,100,100,10000,0,0.0000,1,0,NULL,0,1,0,0,0,7,0.00,1,100.00,'',0,0.00,0,1,1,'接点人列表',NULL,0,0,0,0,0,0,0,100.00,0,'simple','guide','auto',0,0,0,10000,10,3,0,0,7,0,0,'',0,0,5.00,'','抽奖商城',0,0,1,0,0,0.00,0,0,'old','','0',0,0,'business',NULL,0,1,0,0,1,1,1,1,1,'盒',0,0,0,0,'apiauto',0,'list',5,'',0,3,1,1,0,0,2.00,0,0,1,0,1,1,0,5,0,0,NULL);
/*!40000 ALTER TABLE `ddwx_admin_set` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-09 16:01:39

-- MySQL dump 10.13  Distrib 8.0.45, for Linux (aarch64)
--
-- Host: localhost    Database: tp_delivery_adminphp_core_seed_20260605_0928
-- ------------------------------------------------------
-- Server version	8.0.45

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `ddwx_admin_user`
--

LOCK TABLES `ddwx_admin_user` WRITE;
/*!40000 ALTER TABLE `ddwx_admin_user` DISABLE KEYS */;
INSERT INTO `ddwx_admin_user` (`id`, `aid`, `bid`, `un`, `pwd`, `menu_display_mode`, `ip`, `logintime`, `status`, `createtime`, `remark`, `auth_type`, `auth_data`, `wxauth_data`, `notice_auth_data`, `isadmin`, `console_role`, `parent_uid`, `root_uid`, `platform_quota`, `channel_auth_data`, `hexiao_auth_data`, `mdid`, `tmpl_orderconfirm`, `tmpl_orderpay`, `tmpl_ordershouhuo`, `tmpl_ordertui`, `tmpl_recharge`, `tmpl_withdraw`, `tmpl_formsub`, `tmpl_kehuzixun`, `tmpl_uplv`, `tmpl_restaurant_booking`, `showtj`, `mid`, `random_str`, `groupid`, `addid`, `bids`, `workcate_id`, `workorder_type`, `tuanid`, `type`) VALUES (1,1,0,'admin','e10adc3949ba59abbe56e057f20f883e','smart','192.168.65.1',1780546857,1,1647259858,NULL,1,'[\"ShopProduct\\/index,ShopProduct\\/*,ShopCode\\/*\",\"ShopOrder\\/index,ShopOrder\\/*\",\"ShopRefundOrder\\/index,ShopRefundOrder\\/*\",\"ShopComment\\/index,ShopComment\\/*\",\"ShopCategory\\/index,ShopCategory\\/*\",\"ShopGroup\\/index,ShopGroup\\/*\",\"ShopParam\\/index,ShopParam\\/*\",\"ShopFuwu\\/index,ShopFuwu\\/*\",\"ShopPoster\\/index,ShopPoster\\/*\",\"ShopOrderlr\\/index,ShopOrderlr\\/*,ShopProduct\\/chooseproduct,ShopProduct\\/index,ShopProduct\\/getproduct,Member\\/index\",\"ShopTaobao\\/index,ShopTaobao\\/*\",\"ShopOrder\\/tongji,ShopOrder\\/*\",\"ShopSet\\/index,ShopSet\\/*\",\"Member\\/recharge,Member\\/recharge\",\"Member\\/addscore,Member\\/addscore\",\"Member\\/addcommission,Member\\/addcommission\",\"Member\\/addheijifen,Member\\/addheijifen\",\"MemberLevel\\/index,MemberLevel\\/*\",\"MemberLevel\\/applyorder,MemberLevel\\/*\",\"Member\\/charts,Member\\/charts\",\"MemberPoster\\/index,MemberPoster\\/*\",\"Payorder\\/index,Payorder\\/*\",\"BusinessMoney\\/memberindex,BusinessMoney\\/*\",\"Money\\/moneylog,Money\\/moneylog,Money\\/moneylogexcel,Money\\/moneylogsetst,Money\\/moneylogdel\",\"Money\\/heijifenlog,Money\\/heijifenlog,Money\\/moneylog,Money\\/moneylogexcel,Money\\/moneylogsetst,Money\\/moneylogdel\",\"Money\\/rechargelog,Money\\/rechargelog,Money\\/rechargelogexcel,Money\\/rechargelogdel\",\"Money\\/withdrawlog,Money\\/*\",\"Commission\\/record,Commission\\/record\",\"Commission\\/commissionlog,Commission\\/commissionlog,Commission\\/commissionlogexcel,Commission\\/commissionlogdel\",\"Commission\\/withdrawlog,Commission\\/*\",\"Score\\/scorelog,Score\\/*\",\"Maidan\\/index,Maidan\\/*\",\"Commission\\/fenhonglog,Commission\\/*\",\"Hexiao\\/index,Hexiao\\/*\",\"Invoice\\/index,Invoice\\/*\",\"Coupon\\/index,Coupon\\/*,ShopCategory\\/index,ShopCategory\\/choosecategory\",\"Member\\/registerGive,Member\\/registerGive\",\"Money\\/giveset,Money\\/giveset\",\"Manjian\\/set,Manjian\\/set\",\"Cuxiao\\/index,Cuxiao\\/*\",\"Cashback\\/index,Cashback\\/*\",\"Business\\/index,Business\\/*,BusinessFreight\\/*\",\"Business\\/category,Business\\/*\",\"Business\\/feilv,Business\\/*\",\"ShopProduct\\/index&showtype=2,ShopProduct\\/*\",\"ShopOrder\\/index&showtype=2,ShopOrder\\/*\",\"BusinessComment\\/index&showtype=2,BusinessComment\\/*\",\"BusinessMoney\\/moneylog,BusinessMoney\\/moneylog,BusinessMoney\\/moneylogexcel,BusinessMoney\\/moneylogsetst,BusinessMoney\\/moneylogdel\",\"BusinessMoney\\/withdrawlog,BusinessMoney\\/*\",\"BusinessNotice\\/index,BusinessNotice\\/*\",\"DesignerMenu\\/business,DesignerMenu\\/*\",\"Business\\/sysset,Business\\/sysset\",\"DesignerPage\\/index,DesignerPage\\/*\",\"DesignerMenu\\/index,DesignerMenu\\/*\",\"DesignerMenu\\/menu2,DesignerMenu\\/*\",\"DesignerShare\\/index,DesignerShare\\/*\",\"DesignerPage\\/chooseurl,DesignerPage\\/chooseurl\",\"Binding\\/index,Binding\\/*\",\"Mpmenu\\/index,Mpmenu\\/*\",\"Mppay\\/set,Mppay\\/*\",\"Mptmpl\\/tmplset,Mptmpl\\/*\",\"Mptmpl\\/tmplsetNew,Mptmpl\\/tmplsetNew\",\"Mptmpl\\/mytmpl,Mptmpl\\/*\",\"Mpkeyword\\/subscribe,Mpkeyword\\/*\",\"Mpkeyword\\/index,Mpkeyword\\/*\",\"Mpfans\\/fanslist,Mpfans\\/*\",\"Mpfans\\/sourcelist,Mpfans\\/*\",\"Mpfans\\/tmplsend,Mpfans\\/*\",\"Mpfans\\/kfmsgsend,Mpfans\\/*\",\"Membercard\\/record,Membercard\\/record\",\"Membercard\\/index,Membercard\\/*\",\"Binding\\/index,Binding\\/*\",\"Wxpay\\/set,Wxpay\\/*\",\"Wxtmpl\\/tmplset,Wxtmpl\\/*\",\"Wxleimu\\/index,Wxleimu\\/*\",\"Wxurl\\/index,Wxurl\\/*\",\"Binding\\/alipay,Binding\\/*\",\"Binding\\/baidu,Binding\\/*\",\"Binding\\/toutiao,Binding\\/*\",\"Binding\\/qq,Binding\\/*\",\"Binding\\/h5,Binding\\/*\",\"Binding\\/app,Binding\\/*\",\"Backstage\\/sysset,Backstage\\/sysset\",\"Backstage\\/setorder,Backstage\\/setorder\",\"Backstage\\/orderchuli,Backstage\\/orderchuli\",\"Mendian\\/index,Mendian\\/*\",\"User\\/index,User\\/*,UserGroup\\/*\",\"Freight\\/index,Freight\\/*\",\"Wifiprint\\/index,Wifiprint\\/*\",\"Money\\/alipay,Money\\/*\",\"Sms\\/set,Sms\\/*\",\"Backstage\\/plog,Backstage\\/plog\"]','[\"member\",\"product\",\"order\",\"finance\",\"zixun\"]','{\"0\":\"tmpl_orderconfirm\",\"1\":\"tmpl_orderpay\",\"3\":\"tmpl_ordershouhuo\",\"4\":\"tmpl_ordertui\",\"5\":\"tmpl_withdraw\",\"6\":\"tmpl_uplv\",\"7\":\"tmpl_formsub\",\"8\":\"tmpl_kehuzixun\"}',2,2,0,0,0,'','[\"shop\",\"coupon\"]',0,1,1,1,1,1,1,1,1,1,1,0,77,'ce_demo_salt',0,0,'0',0,0,0,'business');
/*!40000 ALTER TABLE `ddwx_admin_user` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-09 16:01:39

-- MySQL dump 10.13  Distrib 8.0.45, for Linux (aarch64)
--
-- Host: localhost    Database: tp_delivery_adminphp_core_seed_20260605_0928
-- ------------------------------------------------------
-- Server version	8.0.45

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `ddwx_admin_system_config`
--

LOCK TABLES `ddwx_admin_system_config` WRITE;
/*!40000 ALTER TABLE `ddwx_admin_system_config` DISABLE KEYS */;
INSERT INTO `ddwx_admin_system_config` (`id`, `key`, `value`, `type`, `group`, `title`, `description`, `createtime`, `updatetime`, `update_uid`) VALUES (2,'test','test_value','string','system',NULL,NULL,NULL,NULL,0),(3,'webname','Tiantianshan Shop','string','system','Website Name',NULL,NULL,NULL,0),(4,'weburl','http://localhost','string','system','Website URL',NULL,NULL,NULL,0),(5,'logo','','string','system','Website Logo',NULL,NULL,NULL,0),(6,'close','0','string','system','Website Status',NULL,NULL,NULL,0),(7,'menu_use_database','1','switch','system','数据库菜单模式','是否使用数据库菜单模式',1766656823,1777432886,1);
/*!40000 ALTER TABLE `ddwx_admin_system_config` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-09 16:01:39

-- MySQL dump 10.13  Distrib 8.0.45, for Linux (aarch64)
--
-- Host: localhost    Database: tp_delivery_adminphp_core_seed_20260605_0928
-- ------------------------------------------------------
-- Server version	8.0.45

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `ddwx_admin_menu`
--
-- WHERE:  NOT (COALESCE(`key`,'') LIKE '%Backstage/dashboard%' ESCAPE '\\' OR COALESCE(`name`,'') LIKE '%Backstage/dashboard%' ESCAPE '\\' OR COALESCE(`fullname`,'') LIKE '%Backstage/dashboard%' ESCAPE '\\' OR COALESCE(`path`,'') LIKE '%Backstage/dashboard%' ESCAPE '\\' OR COALESCE(`auth_data`,'') LIKE '%Backstage/dashboard%' ESCAPE '\\' OR COALESCE(`plugin_name`,'') LIKE '%Backstage/dashboard%' ESCAPE '\\' OR COALESCE(`key`,'') LIKE '%backstage\\_dashboard%' ESCAPE '\\' OR COALESCE(`name`,'') LIKE '%backstage\\_dashboard%' ESCAPE '\\' OR COALESCE(`fullname`,'') LIKE '%backstage\\_dashboard%' ESCAPE '\\' OR COALESCE(`path`,'') LIKE '%backstage\\_dashboard%' ESCAPE '\\' OR COALESCE(`auth_data`,'') LIKE '%backstage\\_dashboard%' ESCAPE '\\' OR COALESCE(`plugin_name`,'') LIKE '%backstage\\_dashboard%' ESCAPE '\\' OR COALESCE(`key`,'') LIKE '%Collage%' ESCAPE '\\' OR COALESCE(`name`,'') LIKE '%Collage%' ESCAPE '\\' OR COALESCE(`fullname`,'') LIKE '%Collage%' ESCAPE '\\' OR COALESCE(`path`,'') LIKE '%Collage%' ESCAPE '\\' OR COALESCE(`auth_data`,'') LIKE '%Collage%' ESCAPE '\\' OR COALESCE(`plugin_name`,'') LIKE '%Collage%' ESCAPE '\\' OR COALESCE(`key`,'') LIKE '%collage%' ESCAPE '\\' OR COALESCE(`name`,'') LIKE '%collage%' ESCAPE '\\' OR COALESCE(`fullname`,'') LIKE '%collage%' ESCAPE '\\' OR COALESCE(`path`,'') LIKE '%collage%' ESCAPE '\\' OR COALESCE(`auth_data`,'') LIKE '%collage%' ESCAPE '\\' OR COALESCE(`plugin_name`,'') LIKE '%collage%' ESCAPE '\\' OR COALESCE(`key`,'') LIKE '%Kanjia%' ESCAPE '\\' OR COALESCE(`name`,'') LIKE '%Kanjia%' ESCAPE '\\' OR COALESCE(`fullname`,'') LIKE '%Kanjia%' ESCAPE '\\' OR COALESCE(`path`,'') LIKE '%Kanjia%' ESCAPE '\\' OR COALESCE(`auth_data`,'') LIKE '%Kanjia%' ESCAPE '\\' OR COALESCE(`plugin_name`,'') LIKE '%Kanjia%' ESCAPE '\\' OR COALESCE(`key`,'') LIKE '%kanjia%' ESCAPE '\\' OR COALESCE(`name`,'') LIKE '%kanjia%' ESCAPE '\\' OR COALESCE(`fullname`,'') LIKE '%kanjia%' ESCAPE '\\' OR COALESCE(`path`,'') LIKE '%kanjia%' ESCAPE '\\' OR COALESCE(`auth_data`,'') LIKE '%kanjia%
LOCK TABLES `ddwx_admin_menu` WRITE;
/*!40000 ALTER TABLE `ddwx_admin_menu` DISABLE KEYS */;
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (1,0,'shop','商城','商城系统','','my-icon my-icon-shop','',0,1,1,1,0,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (2,1,'shopproduct_index','商品管理','商品管理','ShopProduct/index','','ShopProduct/*,ShopCode/*',0,2,1,1,0,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (3,1,'shoporder_index','订单管理','订单管理','ShopOrder/index','','ShopOrder/*',0,2,2,1,0,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (4,1,'shoprefundorder_index','退款申请','退款申请','ShopRefundOrder/index','','ShopRefundOrder/*',0,2,3,1,0,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (5,1,'shopcomment_index','评价管理','评价管理','ShopComment/index','','ShopComment/*',0,2,4,1,0,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (6,1,'shopcategory_index','商品分类','商品分类','ShopCategory/index','','ShopCategory/*',0,2,5,1,0,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (7,1,'shopgroup_index','商品分组','商品分组','ShopGroup/index','','ShopGroup/*',0,2,6,1,0,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (8,1,'shopparam_index','商品参数','商品参数','ShopParam/index','','ShopParam/*',0,2,7,1,0,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (9,1,'shopfuwu_index','商品服务','商品服务','ShopFuwu/index','','ShopFuwu/*',0,2,8,1,0,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (10,1,'shopposter_index','商品海报','商品海报','ShopPoster/index','','ShopPoster/*',0,2,9,1,0,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (11,1,'shoporderlr_index','录入订单','录入订单','ShopOrderlr/index','','ShopOrderlr/*,ShopProduct/chooseproduct,ShopProduct/index,ShopProduct/getproduct,Member/index',0,2,10,1,0,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (12,1,'shoptaobao_index','商品采集','商品采集','ShopTaobao/index','','ShopTaobao/*',0,2,11,1,0,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (13,1,'shoporder_tongji','销售统计','销售统计','ShopOrder/tongji','','ShopOrder/*',0,2,12,1,0,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (14,1,'shopset_index','系统设置','系统设置','ShopSet/index','','ShopSet/*',0,2,13,1,0,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (15,0,'member','会员','会员管理','','my-icon my-icon-member','',0,1,2,1,0,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (16,15,'member_recharge','充值','充值','Member/recharge','','Member/recharge',0,2,2,1,1,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (17,15,'member_addscore','加积分','加积分','Member/addscore','','Member/addscore',0,2,4,1,1,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (18,15,'member_addcommission','加佣金','加佣金','Member/addcommission','','Member/addcommission',0,2,5,1,1,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (19,15,'member_addheijifen','加现金券','加现金券','Member/addheijifen','','Member/addheijifen',0,2,7,1,1,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (20,15,'memberlevel_index','等级及分销','等级及分销','MemberLevel/index','','MemberLevel/*',0,2,8,1,0,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (21,15,'memberlevel_applyorder','升级申请记录','升级申请记录','MemberLevel/applyorder','','MemberLevel/*',0,2,9,1,0,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (22,15,'member_charts','会员关系图','会员关系图','Member/charts','','Member/charts',0,2,10,1,0,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (23,15,'memberposter_index','分享海报','分享海报','MemberPoster/index','','MemberPoster/*',0,2,15,1,0,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (27,0,'finance','财务','财务管理','','my-icon my-icon-finance','',0,1,3,1,0,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (28,27,'payorder_index','消费明细','消费明细','Payorder/index','','Payorder/*',0,2,5,1,0,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (29,27,'businessmoney_memberindex','用户明细','用户明细','BusinessMoney/memberindex','','BusinessMoney/*',0,2,6,1,0,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (30,27,'money_moneylog','余额明细','余额明细','Money/moneylog','','Money/moneylog,Money/moneylogexcel,Money/moneylogsetst,Money/moneylogdel',0,2,7,1,0,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (31,27,'money_heijifenlog','现金券明细','现金券明细','Money/heijifenlog','','Money/heijifenlog,Money/moneylog,Money/moneylogexcel,Money/moneylogsetst,Money/moneylogdel',0,2,8,1,0,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (32,27,'money_rechargelog','充值记录','充值记录','Money/rechargelog','','Money/rechargelog,Money/rechargelogexcel,Money/rechargelogdel',0,2,9,1,0,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (33,27,'money_withdrawlog','余额提现','余额提现','Money/withdrawlog','','Money/*',0,2,10,1,0,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (34,27,'commission_record','佣金记录','佣金记录','Commission/record','','Commission/record',0,2,11,1,0,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (35,27,'commission_commissionlog','佣金明细','佣金明细','Commission/commissionlog','','Commission/commissionlog,Commission/commissionlogexcel,Commission/commissionlogdel',0,2,12,1,0,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (37,27,'commission_withdrawlog','佣金提现','佣金提现','Commission/withdrawlog','','Commission/*',0,2,14,1,0,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (38,27,'score_scorelog','积分明细','积分明细','Score/scorelog','','Score/*',0,2,16,1,0,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (39,27,'maidan_index','买单记录','买单记录','Maidan/index','','Maidan/*',0,2,21,1,0,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (40,27,'commission_fenhonglog','分红记录','分红记录','Commission/fenhonglog','','Commission/*',0,2,22,1,0,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (41,27,'hexiao_index','核销记录','核销记录','Hexiao/index','','Hexiao/*',0,2,23,1,0,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (42,27,'invoice_index','发票管理','发票管理','Invoice/index','','Invoice/*',0,2,24,1,0,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (43,0,'yingxiao','营销','营销活动','','my-icon my-icon-yingxiao','',0,1,4,1,0,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (44,43,'coupon_index','优惠券','优惠券','Coupon/index','','Coupon/*,ShopCategory/index,ShopCategory/choosecategory',0,2,1,1,0,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (45,43,'member_registergive','注册赠送','注册赠送','Member/registerGive','','Member/registerGive',0,2,2,1,0,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (46,43,'money_giveset','充值赠送','充值赠送','Money/giveset','','Money/giveset',0,2,3,1,0,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (47,43,'manjian_set','购物满减','购物满减','Manjian/set','','Manjian/set',0,2,4,1,0,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (48,43,'cuxiao_index','商品促销','商品促销','Cuxiao/index','','Cuxiao/*',0,2,6,1,0,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (49,43,'cashback_index','购物返现','购物返现','Cashback/index','','Cashback/*',0,2,7,1,0,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (109,0,'business','多商户','多商户管理','','my-icon my-icon-business','',0,1,5,1,0,0,0,0,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (113,109,'shopproduct_index_showtype_2','商品管理','商品管理','ShopProduct/index&showtype=2','','ShopProduct/*',0,2,5,1,0,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (114,109,'shoporder_index_showtype_2','商户订单','商户订单','ShopOrder/index&showtype=2','','ShopOrder/*',0,2,6,1,0,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (115,109,'businesscomment_index_showtype_2','商户评价','商户评价','BusinessComment/index&showtype=2','','BusinessComment/*',0,2,7,1,0,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (125,109,'businessmoney_moneylog','余额明细','余额明细','BusinessMoney/moneylog','','BusinessMoney/moneylog,BusinessMoney/moneylogexcel,BusinessMoney/moneylogsetst,BusinessMoney/moneylogdel',0,2,17,1,0,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (126,109,'businessmoney_withdrawlog','提现记录','提现记录','BusinessMoney/withdrawlog','','BusinessMoney/*',0,2,19,1,0,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (127,109,'businessnotice_index','通知公告','通知公告','BusinessNotice/index','','BusinessNotice/*',0,2,20,1,0,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (128,109,'designermenu_business','默认导航','默认导航','DesignerMenu/business','','DesignerMenu/*',0,2,22,1,0,0,0,1,1,0,'','','',1777391195,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (466,0,'jiemian','设计','界面设计','','my-icon my-icon-sheji','',0,1,7,1,0,0,0,1,1,0,'','','',1777391196,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (467,466,'designerpage_index','页面设计','页面设计','DesignerPage/index','','DesignerPage/*',0,2,1,1,0,0,0,1,1,0,'','','',1777391196,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (468,466,'designermenu_index','底部导航','底部导航','DesignerMenu/index','','DesignerMenu/*',0,2,4,1,0,0,0,1,1,0,'','','',1777391196,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (469,466,'designermenu_menu2','内页导航','内页导航','DesignerMenu/menu2','','DesignerMenu/*',0,2,4,1,0,0,0,1,1,0,'','','',1777391196,1777391196);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (470,466,'designershare_index','分享设置','分享设置','DesignerShare/index','','DesignerShare/*',0,2,11,1,0,0,0,1,1,0,'','','',1777391196,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (471,466,'designerpage_chooseurl','链接地址','链接地址','DesignerPage/chooseurl','','DesignerPage/chooseurl',0,2,13,1,0,0,0,1,1,0,'','','/type/geturl',1777391196,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (472,0,'pingtai','平台','平台设置','','my-icon my-icon-pingtai','',0,1,8,1,0,0,0,1,1,0,'','','',1777391196,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (473,472,'pingtai_0','微信公众号','微信公众号','','','',0,2,1,1,0,0,0,1,1,0,'','','',1777391196,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (474,473,'binding_index','公众号绑定','公众号绑定','Binding/index','','Binding/*',0,3,1,1,0,0,0,1,1,0,'','','',1777391196,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (475,473,'mpmenu_index','菜单设置','菜单设置','Mpmenu/index','','Mpmenu/*',0,3,2,1,0,0,0,1,1,0,'','','',1777391196,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (476,473,'mppay_set','支付设置','支付设置','Mppay/set','','Mppay/*',0,3,3,1,0,0,0,1,1,0,'','','',1777391196,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (477,473,'mptmpl_tmplset','模板消息设置','模板消息设置','Mptmpl/tmplset','','Mptmpl/*',0,3,4,1,0,0,0,1,1,0,'','','',1777391196,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (478,473,'mptmpl_tmplsetnew','类目模板消息','类目模板消息','Mptmpl/tmplsetNew','','Mptmpl/tmplsetNew',0,3,5,1,0,0,0,1,1,0,'','','',1777391196,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (479,473,'mptmpl_mytmpl','已添加的模板','已添加的模板','Mptmpl/mytmpl','','Mptmpl/*',0,3,6,1,0,0,0,1,1,0,'','','',1777391196,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (480,473,'mpkeyword_subscribe','被关注回复','被关注回复','Mpkeyword/subscribe','','Mpkeyword/*',0,3,7,1,0,0,0,1,1,0,'','','',1777391196,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (481,473,'mpkeyword_index','关键字回复','关键字回复','Mpkeyword/index','','Mpkeyword/*',0,3,8,1,0,0,0,1,1,0,'','','',1777391196,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (482,473,'mpfans_fanslist','粉丝列表','粉丝列表','Mpfans/fanslist','','Mpfans/*',0,3,9,1,0,0,0,1,1,0,'','','',1777391196,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (483,473,'mpfans_sourcelist','素材管理','素材管理','Mpfans/sourcelist','','Mpfans/*',0,3,10,1,0,0,0,1,1,0,'','','',1777391196,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (484,473,'mpfans_tmplsend','模板消息群发','模板消息群发','Mpfans/tmplsend','','Mpfans/*',0,3,11,1,0,0,0,1,1,0,'','','',1777391196,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (485,473,'mpfans_kfmsgsend','活跃粉丝群发','活跃粉丝群发','Mpfans/kfmsgsend','','Mpfans/*',0,3,12,1,0,0,0,1,1,0,'','','',1777391196,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (486,472,'pingtai_1','微信会员卡','微信会员卡','','','',0,2,2,1,0,0,0,1,1,0,'','','',1777391196,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (487,486,'membercard_record','领取记录','领取记录','Membercard/record','','Membercard/record',0,3,1,1,0,0,0,1,1,0,'','','',1777391196,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (488,486,'membercard_index','会员卡/创建','会员卡/创建','Membercard/index','','Membercard/*',0,3,2,1,0,0,0,1,1,0,'','','',1777391196,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (489,472,'pingtai_2','微信小程序','微信小程序','','','',0,2,3,1,0,0,0,1,1,0,'','','',1777391196,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (490,489,'binding_index_2','小程序绑定','小程序绑定','Binding/index','','Binding/*',0,3,1,1,0,0,0,1,1,0,'','','',1777391196,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (491,489,'wxpay_set','支付设置','支付设置','Wxpay/set','','Wxpay/*',0,3,2,1,0,0,0,1,1,0,'','','',1777391196,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (492,489,'wxtmpl_tmplset','订阅消息设置','订阅消息设置','Wxtmpl/tmplset','','Wxtmpl/*',0,3,3,1,0,0,0,1,1,0,'','','',1777391196,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (493,489,'wxleimu_index','服务类目','服务类目','Wxleimu/index','','Wxleimu/*',0,3,4,1,0,0,0,1,1,0,'','','',1777391196,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (494,489,'wxurl_index','外部链接','外部链接','Wxurl/index','','Wxurl/*',0,3,5,1,0,0,0,1,1,0,'','','',1777391196,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (495,472,'binding_alipay','支付宝小程序','支付宝小程序','Binding/alipay','','Binding/*',0,2,4,1,0,0,0,1,1,0,'','','',1777391196,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (496,472,'binding_baidu','百度小程序','百度小程序','Binding/baidu','','Binding/*',0,2,5,1,0,0,0,1,1,0,'','','',1777391196,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (497,472,'binding_toutiao','头条小程序','头条小程序','Binding/toutiao','','Binding/*',0,2,6,1,0,0,0,1,1,0,'','','',1777391196,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (498,472,'binding_qq','QQ小程序','QQ小程序','Binding/qq','','Binding/*',0,2,7,1,0,0,0,1,1,0,'','','',1777391196,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (499,472,'binding_h5','手机H5','手机H5','Binding/h5','','Binding/*',0,2,8,1,0,0,0,1,1,0,'','','',1777391196,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (500,472,'binding_app','手机APP','手机APP','Binding/app','','Binding/*',0,2,9,1,0,0,0,1,1,0,'','','',1777391196,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (501,0,'system','系统','系统设置','','my-icon my-icon-sysset','',0,1,9,1,0,0,0,1,1,0,'','','',1777391196,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (502,501,'backstage_sysset','系统配置','系统配置','Backstage/sysset','','Backstage/sysset',0,2,1,1,0,0,0,1,1,0,'','','',1777391196,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (503,501,'backstage_setorder','提示音配置','提示音配置','Backstage/setorder','','Backstage/setorder',0,2,3,1,0,0,0,1,1,0,'','','',1777391196,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (504,501,'backstage_orderchuli','提示订单处理','提示订单处理','Backstage/orderchuli','','Backstage/orderchuli',0,2,4,1,0,0,0,1,1,0,'','','',1777391196,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (505,501,'mendian_index','门店管理','门店管理','Mendian/index','','Mendian/*',0,2,5,1,0,0,0,1,1,0,'','','',1777391196,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (506,501,'user_index','管理员列表','管理员列表','User/index','','User/*,UserGroup/*',0,2,7,1,0,0,0,1,1,0,'','','',1777391196,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (507,501,'freight_index','配送方式','配送方式','Freight/index','','Freight/*',0,2,8,1,0,0,0,1,1,0,'','','',1777391196,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (508,501,'wifiprint_index','小票打印机','小票打印机','Wifiprint/index','','Wifiprint/*',0,2,9,1,0,0,0,1,1,0,'','','',1777391196,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (509,501,'money_alipay','支付宝提现','支付宝提现','Money/alipay','','Money/*',0,2,10,1,0,0,0,1,1,0,'','','',1777391196,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (510,501,'sms_set','短信设置','短信设置','Sms/set','','Sms/*',0,2,11,1,0,0,0,1,1,0,'','','',1777391196,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (511,501,'backstage_plog','操作日志','操作日志','Backstage/plog','','Backstage/plog',0,2,12,1,0,0,0,1,1,0,'','','',1777391196,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (512,15,'member_index','会员列表','会员列表','Member/index','','Member/index,Member/excel,Member/excel,Member/importexcel,Member/getplatform,Member/edit,Member/save,Member/del,Member/getcarddetail,Member/charts,Member/setst,Member/*,MemberStatistics/*,MemberManage/*',0,2,1,1,0,0,0,1,1,0,NULL,NULL,'',1777442856,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (513,15,'membertag_index','会员标签','会员标签','MemberTag/index','','MemberTag/*',0,2,11,1,0,0,0,1,1,0,NULL,NULL,'',1777442856,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (514,15,'membermanage_source','会员来源管理','会员来源管理','MemberManage/source','','MemberManage/*',0,2,12,1,0,0,0,1,1,0,NULL,NULL,'',1777442856,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (515,15,'memberhistory_index','会员足迹','会员足迹','MemberHistory/index','','MemberHistory/*',0,2,13,1,0,0,0,1,1,0,NULL,NULL,'',1777442856,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (516,15,'registerform_index','注册自定义','注册自定义','RegisterForm/index','','RegisterForm/*',0,2,14,1,0,0,0,1,1,0,NULL,NULL,'',1777442856,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (517,466,'designermenu_splash','开屏配置','开屏配置','DesignerMenu/splash','','DesignerMenu/*',0,2,6,1,0,0,0,1,1,0,NULL,NULL,'',1777443543,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (518,466,'designermenu_splashmanage','开屏管理','开屏管理','DesignerMenu/splashmanage','','DesignerMenu/*',0,2,7,1,1,0,0,1,1,0,NULL,NULL,'',1777443543,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (519,466,'designermenushopdetail_shopdetail','商品详情','商品详情','DesignerMenuShopdetail/shopdetail','','DesignerMenuShopdetail/*',0,2,8,1,0,0,0,1,1,0,NULL,NULL,'',1777443543,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (520,0,'plugin','插件','插件管理','MyPlugin/index','my-icon my-icon-kuozhan','MyPlugin/*',1,1,6,1,0,0,0,1,1,0,'','','',1779114876,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (521,466,'designercategory_index','页面分类','页面分类','DesignerCategory/index','','DesignerCategory/*',0,2,3,1,0,0,0,1,1,0,'','','',1779185606,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (522,466,'designerlogin_index','登录页面','登录页面','DesignerLogin/index','','DesignerLogin/*',0,2,9,1,0,0,0,1,1,0,'','','',1779185606,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (523,466,'designermobile_index','移动端后台','移动端后台','DesignerMobile/index','','DesignerMobile/*',0,2,10,1,0,0,0,1,1,0,'','','',1779185606,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (524,466,'loadingeffect_index','加载动效','加载动效','LoadingEffect/index','','LoadingEffect/*',0,2,12,1,0,0,0,1,1,0,'','','',1779185606,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (525,466,'distributionsettings_index','分销设置','分销设置','DistributionSettings/index','','DistributionSettings/*',0,2,14,1,0,0,0,1,1,0,'','','',1779185606,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (526,109,'business_index','商户管理','商户管理','Business/index','','Business/*,BusinessFreight/*',0,2,1,1,0,0,0,0,1,0,'','','',1783062527,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (527,109,'business_category','商户分类','商户分类','Business/category','','Business/*',0,2,2,1,0,0,0,0,1,0,'','','',1783062527,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (528,109,'business_feilv','商户费率','商户费率','Business/feilv','','Business/*',0,2,3,1,0,0,0,0,1,0,'','','',1783062527,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (529,109,'businessposter_index','商家海报','商家海报','BusinessPoster/index','','BusinessPoster/*',0,2,4,1,0,0,0,0,1,0,'','','',1783062527,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (530,15,'member_addallocation','加配资','加配资','Member/addAllocation','','Member/addAllocation',0,2,3,1,1,0,0,1,1,0,'','','',1783660689,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (532,15,'teamfenhong','团队分红','团队分红','teamfenhong','','teamfenhong',0,2,16,1,1,0,0,1,1,0,'','','',1783660689,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (533,15,'gdfenhong','股东分红','股东分红','gdfenhong','','gdfenhong',0,2,17,1,1,0,0,1,1,0,'','','',1783660689,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (534,15,'areafenhong','区域分红','区域分红','areafenhong','','areafenhong',0,2,18,1,1,0,0,1,1,0,'','','',1783660689,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (535,27,'membercaiwu_index','会员列表','会员列表','Membercaiwu/index','','Membecaiwu/*',0,2,1,1,0,0,0,1,1,0,'','','',1783660689,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (536,27,'payorder_tongjiyue','全局明细','全局明细','Payorder/tongjiyue','','Payorder/*',0,2,2,1,0,0,0,1,1,0,'','','',1783660689,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (537,27,'payorder_caiwu','每日收款明细','每日收款明细','Payorder/caiwu','','Payorder/*',0,2,3,1,0,0,0,1,1,0,'','','',1783660689,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (538,27,'payorder_fahuomingxi','每日货款明细','每日货款明细','Payorder/fahuomingxi','','Payorder/*',0,2,4,1,0,0,0,1,1,0,'','','',1783660689,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (540,27,'commission_withdrawlogcpcn','中金佣金提现','中金佣金提现','Commission/withdrawlogcpcn','','Commission/*',0,2,15,1,0,0,0,1,1,0,'','','',1783660689,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (541,27,'scorehuang_scorelog','黄积分明细','黄积分明细','ScoreHuang/scorelog','','ScoreHuang/scorelog,ScoreHuang/scorelogexcel,ScoreHuang/scorelogdel,ScorehuangLog/getList,ScorehuangLog/export,ScorehuangLog/delete',0,2,17,1,0,0,0,1,1,0,'','','',1783660689,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (542,27,'xiaofeiblog_index','消费币明细','消费币明细','XiaofeibLog/index','','XiaofeibLog/getList,XiaofeibLog/export,XiaofeibLog/delete',0,2,18,1,0,0,0,1,1,0,'','','',1783660689,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (543,27,'score_zhuanscorelog','积分转账明细','积分转账明细','Score/zhuanscorelog','','Score/*',0,2,19,1,0,0,0,1,1,0,'','','',1783660689,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (544,27,'money_zhuangzhang','余额转账明细','余额转账明细','Money/zhuangzhang','','Money/*',0,2,20,1,0,0,0,1,1,0,'','','',1783660689,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (545,43,'randomdiscount_set','随机立减','随机立减','RandomDiscount/set','','RandomDiscount/set',0,2,5,1,0,0,0,1,1,0,'','','',1783660689,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (631,109,'cycleproduct_index_showtype_2','周期购商品','周期购商品','CycleProduct/index&showtype=2','','CycleProduct/*',0,2,13,1,0,0,0,1,1,0,'','','',1783660689,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (632,109,'article_index_showtype_2','文章列表','文章列表','Article/index&showtype=2','','Article/*',0,2,14,1,0,0,0,1,1,0,'','','',1783660689,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (635,109,'businessmoney_rechargelog','充值审核','充值审核','BusinessMoney/rechargelog','','BusinessMoney/*',0,2,18,1,0,0,0,1,1,0,'','','',1783660689,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (636,109,'businesssplitrecords_index','分账记录','分账记录','BusinessSplitRecords/index','','BusinessSplitRecords/*',0,2,21,1,0,0,0,1,1,0,'','','',1783660689,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (637,109,'business_sysset','系统设置','系统设置','Business/sysset','','Business/sysset',0,2,23,1,0,0,0,0,1,0,'','','',1783660689,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (638,109,'merchantbalancequery_index','余额查询','余额查询','MerchantBalanceQuery/index','','MerchantBalanceQuery/*',0,2,24,1,0,0,0,1,1,0,'','','',1783660689,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (639,466,'designerpage_member','个人中心设计','个人中心设计','DesignerPage/member','','DesignerPage/*',0,2,2,1,0,0,0,1,1,0,'','','',1783660689,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (640,466,'designermenu_inner','内页导航','内页导航','DesignerMenu/inner','','DesignerMenu/*',0,2,5,1,0,0,0,1,1,0,'','','',1783660689,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (641,466,'designervirtualuser_index','氛围用户池','氛围用户池','DesignerVirtualUser/index','','DesignerVirtualUser/*',0,2,15,1,0,0,0,1,1,0,'','','',1783660689,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (643,501,'materialcenter_index','素材中心','素材中心','MaterialCenter/index','','Upload/*',0,2,6,1,0,0,0,1,1,0,'','','',1783660689,1783662776);
INSERT INTO `ddwx_admin_menu` (`id`, `pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`) VALUES (644,501,'helpcenterprogress_index','学习中心','学习中心','HelpCenterProgress/index','','HelpCenterProgress/*',0,2,13,1,0,0,0,1,1,0,'','','',1783660689,1783662776);
/*!40000 ALTER TABLE `ddwx_admin_menu` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-09 16:01:40

-- MySQL dump 10.13  Distrib 8.0.45, for Linux (aarch64)
--
-- Host: localhost    Database: tp_delivery_adminphp_core_seed_20260605_0928
-- ------------------------------------------------------
-- Server version	8.0.45

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `ddwx_area`
--

LOCK TABLES `ddwx_area` WRITE;
/*!40000 ALTER TABLE `ddwx_area` DISABLE KEYS */;
INSERT INTO `ddwx_area` (`id`, `code`, `name`, `parent_id`, `first_letter`, `level`) VALUES (1,'110000','北京市',0,'B',0),(2,'120000','天津市',0,'T',0),(3,'130000','河北省',0,'H',0),(4,'140000','山西省',0,'S',0),(5,'150000','内蒙古自治区',0,'N',0),(6,'210000','辽宁省',0,'L',0),(7,'220000','吉林省',0,'J',0),(8,'230000','黑龙江省',0,'H',0),(9,'310000','上海市',0,'S',0),(10,'320000','江苏省',0,'J',0),(11,'330000','浙江省',0,'Z',0),(12,'340000','安徽省',0,'A',0),(13,'350000','福建省',0,'F',0),(14,'360000','江西省',0,'J',0),(15,'370000','山东省',0,'S',0),(16,'410000','河南省',0,'H',0),(17,'420000','湖北省',0,'H',0),(18,'430000','湖南省',0,'H',0),(19,'440000','广东省',0,'G',0),(20,'450000','广西壮族自治区',0,'G',0),(21,'460000','海南省',0,'H',0),(22,'500000','重庆市',0,'Z',0),(23,'510000','四川省',0,'S',0),(24,'520000','贵州省',0,'G',0),(25,'530000','云南省',0,'Y',0),(26,'540000','西藏自治区',0,'X',0),(27,'610000','陕西省',0,'S',0),(28,'620000','甘肃省',0,'G',0),(29,'630000','青海省',0,'Q',0),(30,'640000','宁夏回族自治区',0,'N',0),(31,'650000','新疆维吾尔自治区',0,'X',0),(32,'710000','台湾省',0,'T',0),(33,'810000','香港特别行政区',0,'X',0),(34,'820000','澳门特别行政区',0,'A',0),(35,'110100','北京市',1,'B',1),(36,'110200','北京县',1,'B',1),(37,'120100','天津市',2,'T',1),(38,'120200','天津县',2,'T',1),(39,'130100','石家庄市',3,'S',1),(40,'130200','唐山市',3,'T',1),(41,'130300','秦皇岛市',3,'Q',1),(42,'130400','邯郸市',3,'H',1),(43,'130500','邢台市',3,'X',1),(44,'130600','保定市',3,'B',1),(45,'130700','张家口市',3,'Z',1),(46,'130800','承德市',3,'C',1),(47,'130900','沧州市',3,'C',1),(48,'131000','廊坊市',3,'L',1),(49,'131100','衡水市',3,'H',1),(50,'140100','太原市',4,'T',1),(51,'140200','大同市',4,'D',1),(52,'140300','阳泉市',4,'Y',1),(53,'140400','长治市',4,'C',1),(54,'140500','晋城市',4,'J',1),(55,'140600','朔州市',4,'S',1),(56,'140700','晋中市',4,'J',1),(57,'140800','运城市',4,'Y',1),(58,'140900','忻州市',4,'X',1),(59,'141000','临汾市',4,'L',1),(60,'141100','吕梁市',4,'L',1),(61,'150100','呼和浩特市',5,'H',1),(62,'150200','包头市',5,'B',1),(63,'150300','乌海市',5,'W',1),(64,'150400','赤峰市',5,'C',1),(65,'150500','通辽市',5,'T',1),(66,'150600','鄂尔多斯市',5,'E',1),(67,'150700','呼伦贝尔市',5,'H',1),(68,'150800','巴彦淖尔市',5,'B',1),(69,'150900','DemoCity市',5,'W',1),(70,'152200','兴安盟',5,'X',1),(71,'152500','锡林郭勒盟',5,'X',1),(72,'152900','阿拉善盟',5,'A',1),(73,'210100','沈阳市',6,'S',1),(74,'210200','大连市',6,'D',1),(75,'210300','鞍山市',6,'A',1),(76,'210400','抚顺市',6,'F',1),(77,'210500','本溪市',6,'B',1),(78,'210600','丹东市',6,'D',1),(79,'210700','锦州市',6,'J',1),(80,'210800','营口市',6,'Y',1),(81,'210900','阜新市',6,'F',1),(82,'211000','辽阳市',6,'L',1),(83,'211100','盘锦市',6,'P',1),(84,'211200','铁岭市',6,'T',1),(85,'211300','朝阳市',6,'C',1),(86,'211400','葫芦岛市',6,'H',1),(87,'220100','长春市',7,'C',1),(88,'220200','吉林市',7,'J',1),(89,'220300','四平市',7,'S',1),(90,'220400','辽源市',7,'L',1),(91,'220500','通化市',7,'T',1),(92,'220600','白山市',7,'B',1),(93,'220700','松原市',7,'S',1),(94,'220800','白城市',7,'B',1),(95,'222400','延边朝鲜族自治州',7,'Y',1),(96,'230100','哈尔滨市',8,'H',1),(97,'230200','齐齐哈尔市',8,'Q',1),(98,'230300','鸡西市',8,'J',1),(99,'230400','鹤岗市',8,'H',1),(100,'230500','双鸭山市',8,'S',1),(101,'230600','大庆市',8,'D',1),(102,'230700','伊春市',8,'Y',1),(103,'230800','佳木斯市',8,'J',1),(104,'230900','七台河市',8,'Q',1),(105,'231000','牡丹江市',8,'M',1),(106,'231100','黑河市',8,'H',1),(107,'231200','绥化市',8,'S',1),(108,'232700','大兴安岭地区',8,'D',1),(109,'310100','上海市',9,'S',1),(110,'310200','上海县',9,'S',1),(111,'320100','南京市',10,'N',1),(112,'320200','无锡市',10,'W',1),(113,'320300','徐州市',10,'X',1),(114,'320400','常州市',10,'C',1),(115,'320500','苏州市',10,'S',1),(116,'320600','南通市',10,'N',1),(117,'320700','连云港市',10,'L',1),(118,'320800','淮安市',10,'H',1),(119,'320900','盐城市',10,'Y',1),(120,'321000','扬州市',10,'Y',1),(121,'321100','镇江市',10,'Z',1),(122,'321200','泰州市',10,'T',1),(123,'321300','宿迁市',10,'S',1),(124,'330100','杭州市',11,'H',1),(125,'330200','宁波市',11,'N',1),(126,'330300','温州市',11,'W',1),(127,'330400','嘉兴市',11,'J',1),(128,'330500','湖州市',11,'H',1),(129,'330600','绍兴市',11,'S',1),(130,'330700','金华市',11,'J',1),(131,'330800','衢州市',11,'Q',1),(132,'330900','舟山市',11,'Z',1),(133,'331000','台州市',11,'T',1),(134,'331100','丽水市',11,'L',1),(135,'340100','合肥市',12,'H',1),(136,'340200','芜湖市',12,'W',1),(137,'340300','蚌埠市',12,'B',1),(138,'340400','淮南市',12,'H',1),(139,'340500','马鞍山市',12,'M',1),(140,'340600','淮北市',12,'H',1),(141,'340700','铜陵市',12,'T',1),(142,'340800','安庆市',12,'A',1),(143,'341000','黄山市',12,'H',1),(144,'341100','滁州市',12,'C',1),(145,'341200','阜阳市',12,'F',1),(146,'341300','宿州市',12,'S',1),(147,'341500','六安市',12,'L',1),(148,'341600','亳州市',12,'B',1),(149,'341700','池州市',12,'C',1),(150,'341800','宣城市',12,'X',1),(151,'350100','福州市',13,'F',1),(152,'350200','厦门市',13,'X',1),(153,'350300','莆田市',13,'P',1),(154,'350400','三明市',13,'S',1),(155,'350500','泉州市',13,'Q',1),(156,'350600','漳州市',13,'Z',1),(157,'350700','南平市',13,'N',1),(158,'350800','龙岩市',13,'L',1),(159,'350900','宁德市',13,'N',1),(160,'360100','南昌市',14,'N',1),(161,'360200','景德镇市',14,'J',1),(162,'360300','萍乡市',14,'P',1),(163,'360400','九江市',14,'J',1),(164,'360500','新余市',14,'X',1),(165,'360600','鹰潭市',14,'Y',1),(166,'360700','赣州市',14,'G',1),(167,'360800','吉安市',14,'J',1),(168,'360900','宜春市',14,'Y',1),(169,'361000','抚州市',14,'F',1),(170,'361100','上饶市',14,'S',1),(171,'370100','济南市',15,'J',1),(172,'370200','青岛市',15,'Q',1),(173,'370300','淄博市',15,'Z',1),(174,'370400','枣庄市',15,'Z',1),(175,'370500','东营市',15,'D',1),(176,'370600','烟台市',15,'Y',1),(177,'370700','潍坊市',15,'W',1),(178,'370800','济宁市',15,'J',1),(179,'370900','泰安市',15,'T',1),(180,'371000','威海市',15,'W',1),(181,'371100','日照市',15,'R',1),(182,'371200','莱芜市',15,'L',1),(183,'371300','临沂市',15,'L',1),(184,'371400','德州市',15,'D',1),(185,'371500','聊城市',15,'L',1),(186,'371600','滨州市',15,'B',1),(187,'371700','菏泽市',15,'H',1),(188,'410100','郑州市',16,'Z',1),(189,'410200','开封市',16,'K',1),(190,'410300','洛阳市',16,'L',1),(191,'410400','平顶山市',16,'P',1),(192,'410500','安阳市',16,'A',1),(193,'410600','鹤壁市',16,'H',1),(194,'410700','新乡市',16,'X',1),(195,'410800','焦作市',16,'J',1),(196,'410900','濮阳市',16,'P',1),(197,'411000','许昌市',16,'X',1),(198,'411100','漯河市',16,'L',1),(199,'411200','三门峡市',16,'S',1),(200,'411300','南阳市',16,'N',1),(201,'411400','商丘市',16,'S',1),(202,'411500','信阳市',16,'X',1),(203,'411600','周口市',16,'Z',1),(204,'411700','驻马店市',16,'Z',1),(206,'420100','武汉市',17,'W',1),(207,'420200','黄石市',17,'H',1),(208,'420300','十堰市',17,'S',1),(209,'420500','宜昌市',17,'Y',1),(210,'420600','襄阳市',17,'X',1),(211,'420700','鄂州市',17,'E',1),(212,'420800','荆门市',17,'J',1),(213,'420900','孝感市',17,'X',1),(214,'421000','荆州市',17,'J',1),(215,'421100','黄冈市',17,'H',1),(216,'421200','咸宁市',17,'X',1),(217,'421300','随州市',17,'S',1),(218,'422800','恩施土家族苗族自治州',17,'E',1),(220,'430100','长沙市',18,'C',1),(221,'430200','株洲市',18,'Z',1),(222,'430300','湘潭市',18,'X',1),(223,'430400','衡阳市',18,'H',1),(224,'430500','邵阳市',18,'S',1),(225,'430600','岳阳市',18,'Y',1),(226,'430700','常德市',18,'C',1),(227,'430800','张家界市',18,'Z',1),(228,'430900','益阳市',18,'Y',1),(229,'431000','郴州市',18,'C',1),(230,'431100','永州市',18,'Y',1),(231,'431200','怀化市',18,'H',1),(232,'431300','娄底市',18,'L',1),(233,'433100','湘西土家族苗族自治州',18,'X',1),(234,'440100','广州市',19,'G',1),(235,'440200','韶关市',19,'S',1),(236,'440300','深圳市',19,'S',1),(237,'440400','珠海市',19,'Z',1),(238,'440500','汕头市',19,'S',1),(239,'440600','佛山市',19,'F',1),(240,'440700','江门市',19,'J',1),(241,'440800','湛江市',19,'Z',1),(242,'440900','茂名市',19,'M',1),(243,'441200','肇庆市',19,'Z',1),(244,'441300','惠州市',19,'H',1),(245,'441400','梅州市',19,'M',1),(246,'441500','汕尾市',19,'S',1),(247,'441600','河源市',19,'H',1),(248,'441700','阳江市',19,'Y',1),(249,'441800','清远市',19,'Q',1),(250,'441900','东莞市',19,'D',1),(251,'442000','中山市',19,'Z',1),(252,'445100','潮州市',19,'C',1),(253,'445200','揭阳市',19,'J',1),(254,'445300','云浮市',19,'Y',1),(255,'450100','南宁市',20,'N',1),(256,'450200','柳州市',20,'L',1),(257,'450300','桂林市',20,'G',1),(258,'450400','梧州市',20,'W',1),(259,'450500','北海市',20,'B',1),(260,'450600','防城港市',20,'F',1),(261,'450700','钦州市',20,'Q',1),(262,'450800','贵港市',20,'G',1),(263,'450900','玉林市',20,'Y',1),(264,'451000','百色市',20,'B',1),(265,'451100','贺州市',20,'H',1),(266,'451200','河池市',20,'H',1),(267,'451300','来宾市',20,'L',1),(268,'451400','崇左市',20,'C',1),(269,'460100','海口市',21,'H',1),(270,'460200','三亚市',21,'S',1),(271,'460300','三沙市',21,'S',1),(273,'500100','重庆市',22,'C',1),(274,'500200','重庆县',22,'C',1),(275,'510100','成都市',23,'C',1),(276,'510300','自贡市',23,'Z',1),(277,'510400','攀枝花市',23,'P',1),(278,'510500','泸州市',23,'L',1),(279,'510600','德阳市',23,'D',1),(280,'510700','绵阳市',23,'M',1),(281,'510800','广元市',23,'G',1),(282,'510900','遂宁市',23,'S',1),(283,'511000','内江市',23,'N',1),(284,'511100','乐山市',23,'L',1),(285,'511300','南充市',23,'N',1),(286,'511400','眉山市',23,'M',1),(287,'511500','宜宾市',23,'Y',1),(288,'511600','广安市',23,'G',1),(289,'511700','达州市',23,'D',1),(290,'511800','雅安市',23,'Y',1),(291,'511900','巴中市',23,'B',1),(292,'512000','资阳市',23,'Z',1),(293,'513200','阿坝藏族羌族自治州',23,'A',1),(294,'513300','甘孜藏族自治州',23,'G',1),(295,'513400','凉山彝族自治州',23,'L',1),(296,'520100','贵阳市',24,'G',1),(297,'520200','六盘水市',24,'L',1),(298,'520300','遵义市',24,'Z',1),(299,'520400','安顺市',24,'A',1),(300,'520500','毕节市',24,'B',1),(301,'520600','铜仁市',24,'T',1),(302,'522300','黔西南布依族苗族自治州',24,'Q',1),(303,'522600','黔东南苗族侗族自治州',24,'Q',1),(304,'522700','黔南布依族苗族自治州',24,'Q',1),(305,'530100','昆明市',25,'K',1),(306,'530300','曲靖市',25,'Q',1),(307,'530400','玉溪市',25,'Y',1),(308,'530500','保山市',25,'B',1),(309,'530600','昭通市',25,'Z',1),(310,'530700','丽江市',25,'L',1),(311,'530800','普洱市',25,'P',1),(312,'530900','临沧市',25,'L',1),(313,'532300','楚雄彝族自治州',25,'C',1),(314,'532500','红河哈尼族彝族自治州',25,'H',1),(315,'532600','文山壮族苗族自治州',25,'W',1),(316,'532800','西双版纳傣族自治州',25,'X',1),(317,'532900','大理白族自治州',25,'D',1),(318,'533100','德宏傣族景颇族自治州',25,'D',1),(319,'533300','怒江傈僳族自治州',25,'N',1),(320,'533400','迪庆藏族自治州',25,'D',1),(321,'540100','拉萨市',26,'L',1),(322,'540200','日喀则市',26,'R',1),(323,'542100','昌都地区',26,'C',1),(324,'542200','山南地区',26,'S',1),(325,'542400','那曲地区',26,'N',1),(326,'542500','阿里地区',26,'A',1),(327,'542600','林芝地区',26,'L',1),(328,'610100','西安市',27,'X',1),(329,'610200','铜川市',27,'T',1),(330,'610300','宝鸡市',27,'B',1),(331,'610400','咸阳市',27,'X',1),(332,'610500','渭南市',27,'W',1),(333,'610600','延安市',27,'Y',1),(334,'610700','汉中市',27,'H',1),(335,'610800','榆林市',27,'Y',1),(336,'610900','安康市',27,'A',1),(337,'611000','商洛市',27,'S',1),(338,'620100','兰州市',28,'L',1),(339,'620200','嘉峪关市',28,'J',1),(340,'620300','金昌市',28,'J',1),(341,'620400','白银市',28,'B',1),(342,'620500','天水市',28,'T',1),(343,'620600','武威市',28,'W',1),(344,'620700','张掖市',28,'Z',1),(345,'620800','平凉市',28,'P',1),(346,'620900','酒泉市',28,'J',1),(347,'621000','庆阳市',28,'Q',1),(348,'621100','定西市',28,'D',1),(349,'621200','陇南市',28,'L',1),(350,'622900','临夏回族自治州',28,'L',1),(351,'623000','甘南藏族自治州',28,'G',1),(352,'630100','西宁市',29,'X',1),(353,'630200','海东市',29,'H',1),(354,'632200','海北藏族自治州',29,'H',1),(355,'632300','黄南藏族自治州',29,'H',1),(356,'632500','海南藏族自治州',29,'H',1),(357,'632600','果洛藏族自治州',29,'G',1),(358,'632700','玉树藏族自治州',29,'Y',1),(359,'632800','海西蒙古族藏族自治州',29,'H',1),(360,'640100','银川市',30,'Y',1),(361,'640200','石嘴山市',30,'S',1),(362,'640300','吴忠市',30,'W',1),(363,'640400','固原市',30,'G',1),(364,'640500','中卫市',30,'Z',1),(365,'650100','乌鲁木齐市',31,'W',1),(366,'650200','克拉玛依市',31,'K',1),(367,'652100','吐鲁番地区',31,'T',1),(368,'652200','哈密地区',31,'H',1),(369,'652300','昌吉回族自治州',31,'C',1),(370,'652700','博尔塔拉蒙古自治州',31,'B',1),(371,'652800','巴音郭楞蒙古自治州',31,'B',1),(372,'652900','阿克苏地区',31,'A',1),(373,'653000','克孜勒苏柯尔克孜自治州',31,'K',1),(374,'653100','喀什地区',31,'K',1),(375,'653200','和田地区',31,'H',1),(376,'654000','伊犁哈萨克自治州',31,'Y',1),(377,'654200','塔城地区',31,'T',1),(378,'654300','阿勒泰地区',31,'A',1),(380,'110101','东城区',35,'D',2),(381,'110102','西城区',35,'X',2),(382,'110105','朝阳区',35,'C',2),(383,'110106','丰台区',35,'F',2),(384,'110107','石景山区',35,'S',2),(385,'110108','海淀区',35,'H',2),(386,'110109','门头沟区',35,'M',2),(387,'110111','房山区',35,'F',2),(388,'110112','通州区',35,'T',2),(389,'110113','顺义区',35,'S',2),(390,'110114','昌平区',35,'C',2),(391,'110115','大兴区',35,'D',2),(392,'110116','怀柔区',35,'H',2),(393,'110117','平谷区',35,'P',2),(394,'110228','密云县',36,'M',2),(395,'110229','延庆县',36,'Y',2),(396,'120101','和平区',37,'H',2),(397,'120102','河东区',37,'H',2),(398,'120103','河西区',37,'H',2),(399,'120104','南开区',37,'N',2),(400,'120105','河北区',37,'H',2),(401,'120106','红桥区',37,'H',2),(402,'120110','东丽区',37,'D',2),(403,'120111','西青区',37,'X',2),(404,'120112','津南区',37,'J',2),(405,'120113','北辰区',37,'B',2),(406,'120114','武清区',37,'W',2),(407,'120115','宝坻区',37,'B',2),(408,'120116','滨海新区',37,'B',2),(409,'120221','宁河县',38,'N',2),(410,'120223','静海县',38,'J',2),(411,'120225','蓟县',38,'J',2),(413,'130102','长安区',39,'C',2),(414,'130104','桥西区',39,'Q',2),(415,'130123','正定县',39,'Z',2),(416,'130125','行唐县',39,'X',2),(417,'130126','灵寿县',39,'L',2),(418,'130127','高邑县',39,'G',2),(419,'130181','辛集市',39,'X',2),(420,'130183','晋州市',39,'J',2),(421,'130184','新乐市',39,'X',2),(422,'130202','路南区',40,'L',2),(423,'130203','路北区',40,'L',2),(424,'130204','古冶区',40,'G',2),(425,'130205','开平区',40,'K',2),(426,'130207','丰南区',40,'F',2),(427,'130208','丰润区',40,'F',2),(428,'130209','曹妃甸区',40,'C',2),(429,'130223','滦县',40,'L',2),(430,'130224','滦南县',40,'L',2),(431,'130225','乐亭县',40,'L',2),(432,'130227','迁西县',40,'Q',2),(433,'130229','玉田县',40,'Y',2),(434,'130281','遵化市',40,'Z',2),(435,'130283','迁安市',40,'Q',2),(436,'130302','海港区',41,'H',2),(437,'130303','山海关区',41,'S',2),(438,'130304','北戴河区',41,'B',2),(439,'130321','青龙满族自治县',41,'Q',2),(440,'130322','昌黎县',41,'C',2),(441,'130323','抚宁县',41,'F',2),(442,'130324','卢龙县',41,'L',2),(443,'130402','邯山区',42,'H',2),(444,'130403','丛台区',42,'C',2),(445,'130404','复兴区',42,'F',2),(446,'130406','峰峰矿区',42,'F',2),(447,'130421','邯郸县',42,'H',2),(448,'130423','临漳县',42,'L',2),(449,'130424','成安县',42,'C',2),(450,'130425','大名县',42,'D',2),(451,'130426','涉县',42,'S',2),(452,'130427','磁县',42,'C',2),(453,'130428','肥乡县',42,'F',2),(454,'130429','永年县',42,'Y',2),(455,'130430','邱县',42,'Q',2),(456,'130431','鸡泽县',42,'J',2),(457,'130432','广平县',42,'G',2),(458,'130433','馆陶县',42,'G',2),(459,'130434','魏县',42,'W',2),(460,'130435','曲周县',42,'Q',2),(461,'130481','武安市',42,'W',2),(462,'130502','桥东区',43,'Q',2),(463,'130521','邢台县',43,'X',2),(464,'130522','临城县',43,'L',2),(465,'130523','内丘县',43,'N',2),(466,'130524','柏乡县',43,'B',2),(467,'130525','隆尧县',43,'L',2),(468,'130526','任县',43,'R',2),(469,'130527','南和县',43,'N',2),(470,'130528','宁晋县',43,'N',2),(471,'130529','巨鹿县',43,'J',2),(472,'130530','新河县',43,'X',2),(473,'130531','广宗县',43,'G',2),(474,'130532','平乡县',43,'P',2),(475,'130533','威县',43,'W',2),(476,'130534','清河县',43,'Q',2),(477,'130535','临西县',43,'L',2),(478,'130581','南宫市',43,'N',2),(479,'130582','沙河市',43,'S',2),(480,'130602','新市区',44,'X',2),(481,'130603','北市区',44,'B',2),(482,'130604','南市区',44,'N',2),(483,'130621','满城县',44,'M',2),(484,'130622','清苑县',44,'Q',2),(485,'130623','涞水县',44,'L',2),(486,'130624','阜平县',44,'F',2),(487,'130625','徐水县',44,'X',2),(488,'130626','定兴县',44,'D',2),(489,'130627','唐县',44,'T',2),(490,'130628','高阳县',44,'G',2),(491,'130629','容城县',44,'R',2),(492,'130630','涞源县',44,'L',2),(493,'130631','望都县',44,'W',2),(494,'130632','安新县',44,'A',2),(495,'130633','易县',44,'Y',2),(496,'130634','曲阳县',44,'Q',2),(497,'130635','蠡县',44,'L',2),(498,'130636','顺平县',44,'S',2),(499,'130637','博野县',44,'B',2),(500,'130638','雄县',44,'X',2),(501,'130681','涿州市',44,'Z',2),(502,'130682','定州市',44,'D',2),(503,'130683','安国市',44,'A',2),(504,'130684','高碑店市',44,'G',2),(505,'130705','宣化区',45,'X',2),(506,'130706','下花园区',45,'X',2),(507,'130721','宣化县',45,'X',2),(508,'130722','张北县',45,'Z',2),(509,'130723','康保县',45,'K',2),(510,'130724','沽源县',45,'G',2),(511,'130725','尚义县',45,'S',2),(512,'130726','蔚县',45,'W',2),(513,'130727','阳原县',45,'Y',2),(514,'130728','怀安县',45,'H',2),(515,'130729','万全县',45,'W',2),(516,'130730','怀来县',45,'H',2),(517,'130731','涿鹿县',45,'Z',2),(518,'130732','赤城县',45,'C',2),(519,'130733','崇礼县',45,'C',2),(520,'130802','双桥区',46,'S',2),(521,'130803','双滦区',46,'S',2),(522,'130804','鹰手营子矿区',46,'Y',2),(523,'130821','承德县',46,'C',2),(524,'130822','兴隆县',46,'X',2),(525,'130823','平泉县',46,'P',2),(526,'130824','滦平县',46,'L',2),(527,'130825','隆化县',46,'L',2),(528,'130826','丰宁满族自治县',46,'F',2),(529,'130827','宽城满族自治县',46,'K',2),(530,'130828','围场满族蒙古族自治县',46,'W',2),(531,'130903','运河区',47,'Y',2),(532,'130921','沧县',47,'C',2),(533,'130922','青县',47,'Q',2),(534,'130923','东光县',47,'D',2),(535,'130924','海兴县',47,'H',2),(536,'130925','盐山县',47,'Y',2),(537,'130926','肃宁县',47,'S',2),(538,'130927','南皮县',47,'N',2),(539,'130928','吴桥县',47,'W',2),(540,'130929','献县',47,'X',2),(541,'130930','孟村回族自治县',47,'M',2),(542,'130981','泊头市',47,'B',2),(543,'130982','任丘市',47,'R',2),(544,'130983','黄骅市',47,'H',2),(545,'130984','河间市',47,'H',2),(546,'131002','安次区',48,'A',2),(547,'131003','广阳区',48,'G',2),(548,'131022','固安县',48,'G',2),(549,'131023','永清县',48,'Y',2),(550,'131024','香河县',48,'X',2),(551,'131025','大城县',48,'D',2),(552,'131026','文安县',48,'W',2),(553,'131028','大厂回族自治县',48,'D',2),(554,'131081','霸州市',48,'B',2),(555,'131082','三河市',48,'S',2),(556,'131102','桃城区',49,'T',2),(557,'131121','枣强县',49,'Z',2),(558,'131122','武邑县',49,'W',2),(559,'131123','武强县',49,'W',2),(560,'131124','饶阳县',49,'R',2),(561,'131125','安平县',49,'A',2),(562,'131126','故城县',49,'G',2),(563,'131127','景县',49,'J',2),(564,'131128','阜城县',49,'F',2),(565,'131181','冀州市',49,'J',2),(566,'131182','深州市',49,'S',2),(567,'140105','小店区',50,'X',2),(568,'140106','迎泽区',50,'Y',2),(569,'140107','杏花岭区',50,'X',2),(570,'140108','尖草坪区',50,'J',2),(571,'140109','万柏林区',50,'W',2),(572,'140110','晋源区',50,'J',2),(573,'140121','清徐县',50,'Q',2),(574,'140122','阳曲县',50,'Y',2),(575,'140123','娄烦县',50,'L',2),(576,'140181','古交市',50,'G',2),(577,'140202','城区',51,'C',2),(578,'140203','矿区',51,'K',2),(579,'140211','南郊区',51,'N',2),(580,'140212','新荣区',51,'X',2),(581,'140221','阳高县',51,'Y',2),(582,'140222','天镇县',51,'T',2),(583,'140223','广灵县',51,'G',2),(584,'140224','灵丘县',51,'L',2),(585,'140225','浑源县',51,'H',2),(586,'140226','左云县',51,'Z',2),(587,'140227','大同县',51,'D',2),(588,'140311','郊区',52,'J',2),(589,'140321','平定县',52,'P',2),(590,'140322','盂县',52,'Y',2),(591,'140421','长治县',53,'C',2),(592,'140423','襄垣县',53,'X',2),(593,'140424','屯留县',53,'T',2),(594,'140425','平顺县',53,'P',2),(595,'140426','黎城县',53,'L',2),(596,'140427','壶关县',53,'H',2),(597,'140428','长子县',53,'C',2),(598,'140429','武乡县',53,'W',2),(599,'140430','沁县',53,'Q',2),(600,'140431','沁源县',53,'Q',2),(601,'140481','潞城市',53,'L',2),(602,'140521','沁水县',54,'Q',2),(603,'140522','阳城县',54,'Y',2),(604,'140524','陵川县',54,'L',2),(605,'140525','泽州县',54,'Z',2),(606,'140581','高平市',54,'G',2),(607,'140602','朔城区',55,'S',2),(608,'140603','平鲁区',55,'P',2),(609,'140621','山阴县',55,'S',2),(610,'140622','应县',55,'Y',2),(611,'140623','右玉县',55,'Y',2),(612,'140624','怀仁县',55,'H',2),(613,'140702','榆次区',56,'Y',2),(614,'140721','榆社县',56,'Y',2),(615,'140722','左权县',56,'Z',2),(616,'140723','和顺县',56,'H',2),(617,'140724','昔阳县',56,'X',2),(618,'140725','寿阳县',56,'S',2),(619,'140726','太谷县',56,'T',2),(620,'140727','祁县',56,'Q',2),(621,'140728','平遥县',56,'P',2),(622,'140729','灵石县',56,'L',2),(623,'140781','介休市',56,'J',2),(624,'140802','盐湖区',57,'Y',2),(625,'140821','临猗县',57,'L',2),(626,'140822','万荣县',57,'W',2),(627,'140823','闻喜县',57,'W',2),(628,'140824','稷山县',57,'J',2),(629,'140825','新绛县',57,'X',2),(630,'140826','绛县',57,'J',2),(631,'140827','垣曲县',57,'Y',2),(632,'140828','夏县',57,'X',2),(633,'140829','平陆县',57,'P',2),(634,'140830','芮城县',57,'R',2),(635,'140881','永济市',57,'Y',2),(636,'140882','河津市',57,'H',2),(637,'140902','忻府区',58,'X',2),(638,'140921','定襄县',58,'D',2),(639,'140922','五台县',58,'W',2),(640,'140923','代县',58,'D',2),(641,'140924','繁峙县',58,'F',2),(642,'140925','宁武县',58,'N',2),(643,'140926','静乐县',58,'J',2),(644,'140927','神池县',58,'S',2),(645,'140928','五寨县',58,'W',2),(646,'140929','岢岚县',58,'K',2),(647,'140930','河曲县',58,'H',2),(648,'140931','保德县',58,'B',2),(649,'140932','偏关县',58,'P',2),(650,'140981','原平市',58,'Y',2),(651,'141002','尧都区',59,'Y',2),(652,'141021','曲沃县',59,'Q',2),(653,'141022','翼城县',59,'Y',2),(654,'141023','襄汾县',59,'X',2),(655,'141024','洪洞县',59,'H',2),(656,'141025','古县',59,'G',2),(657,'141026','安泽县',59,'A',2),(658,'141027','浮山县',59,'F',2),(659,'141028','吉县',59,'J',2),(660,'141029','乡宁县',59,'X',2),(661,'141030','大宁县',59,'D',2),(662,'141031','隰县',59,'X',2),(663,'141032','永和县',59,'Y',2),(664,'141033','蒲县',59,'P',2),(665,'141034','汾西县',59,'F',2),(666,'141081','侯马市',59,'H',2),(667,'141082','霍州市',59,'H',2),(668,'141102','离石区',60,'L',2),(669,'141121','文水县',60,'W',2),(670,'141122','交城县',60,'J',2),(671,'141123','兴县',60,'X',2),(672,'141124','临县',60,'L',2),(673,'141125','柳林县',60,'L',2),(674,'141126','石楼县',60,'S',2),(675,'141127','岚县',60,'L',2),(676,'141128','方山县',60,'F',2),(677,'141129','中阳县',60,'Z',2),(678,'141130','交口县',60,'J',2),(679,'141181','孝义市',60,'X',2),(680,'141182','汾阳市',60,'F',2),(681,'150102','新城区',61,'X',2),(682,'150103','回民区',61,'H',2),(683,'150104','玉泉区',61,'Y',2),(684,'150105','赛罕区',61,'S',2),(685,'150121','土默特左旗',61,'T',2),(686,'150122','托克托县',61,'T',2),(687,'150123','和林格尔县',61,'H',2),(688,'150124','清水河县',61,'Q',2),(689,'150125','武川县',61,'W',2),(690,'150202','东河区',62,'D',2),(691,'150203','昆都仑区',62,'K',2),(692,'150204','青山区',62,'Q',2),(693,'150205','石拐区',62,'S',2),(694,'150206','白云鄂博矿区',62,'B',2),(695,'150207','九原区',62,'J',2),(696,'150221','土默特右旗',62,'T',2),(697,'150222','固阳县',62,'G',2),(698,'150223','达尔罕茂明安联合旗',62,'D',2),(699,'150302','海勃湾区',63,'H',2),(700,'150303','海南区',63,'H',2),(701,'150304','乌达区',63,'W',2),(702,'150402','红山区',64,'H',2),(703,'150403','元宝山区',64,'Y',2),(704,'150404','松山区',64,'S',2),(705,'150421','阿鲁科尔沁旗',64,'A',2),(706,'150422','巴林左旗',64,'B',2),(707,'150423','巴林右旗',64,'B',2),(708,'150424','林西县',64,'L',2),(709,'150425','克什克腾旗',64,'K',2),(710,'150426','翁牛特旗',64,'W',2),(711,'150428','喀喇沁旗',64,'K',2),(712,'150429','宁城县',64,'N',2),(713,'150430','敖汉旗',64,'A',2),(714,'150502','科尔沁区',65,'K',2),(715,'150521','科尔沁左翼中旗',65,'K',2),(716,'150522','科尔沁左翼后旗',65,'K',2),(717,'150523','开鲁县',65,'K',2),(718,'150524','库伦旗',65,'K',2),(719,'150525','奈曼旗',65,'N',2),(720,'150526','扎鲁特旗',65,'Z',2),(721,'150581','霍林郭勒市',65,'H',2),(722,'150602','东胜区',66,'D',2),(723,'150621','达拉特旗',66,'D',2),(724,'150622','准格尔旗',66,'Z',2),(725,'150623','鄂托克前旗',66,'E',2),(726,'150624','鄂托克旗',66,'E',2),(727,'150625','杭锦旗',66,'H',2),(728,'150626','乌审旗',66,'W',2),(729,'150627','伊金霍洛旗',66,'Y',2),(730,'150702','海拉尔区',67,'H',2),(731,'150703','扎赉诺尔区',67,'Z',2),(732,'150721','阿荣旗',67,'A',2),(733,'150722','莫力达瓦达斡尔族自治旗',67,'M',2),(734,'150723','鄂伦春自治旗',67,'E',2),(735,'150724','鄂温克族自治旗',67,'E',2),(736,'150725','陈巴尔虎旗',67,'C',2),(737,'150726','新巴尔虎左旗',67,'X',2),(738,'150727','新巴尔虎右旗',67,'X',2),(739,'150781','满洲里市',67,'M',2),(740,'150782','牙克石市',67,'Y',2),(741,'150783','扎兰屯市',67,'Z',2),(742,'150784','额尔古纳市',67,'E',2),(743,'150785','根河市',67,'G',2),(744,'150802','临河区',68,'L',2),(745,'150821','五原县',68,'W',2),(746,'150822','磴口县',68,'D',2),(747,'150823','乌拉特前旗',68,'W',2),(748,'150824','乌拉特中旗',68,'W',2),(749,'150825','乌拉特后旗',68,'W',2),(750,'150826','杭锦后旗',68,'H',2),(751,'150902','集宁区',69,'J',2),(752,'150921','卓资县',69,'Z',2),(753,'150922','化德县',69,'H',2),(754,'150923','商都县',69,'S',2),(755,'150924','兴和县',69,'X',2),(756,'150925','凉城县',69,'L',2),(757,'150926','察哈尔右翼前旗',69,'C',2),(758,'150927','察哈尔右翼中旗',69,'C',2),(759,'150928','察哈尔右翼后旗',69,'C',2),(760,'150929','四子王旗',69,'S',2),(761,'150981','丰镇市',69,'F',2),(762,'152201','乌兰浩特市',70,'W',2),(763,'152202','阿尔山市',70,'A',2),(764,'152221','科尔沁右翼前旗',70,'K',2),(765,'152222','科尔沁右翼中旗',70,'K',2),(766,'152223','扎赉特旗',70,'Z',2),(767,'152224','突泉县',70,'T',2),(768,'152501','二连浩特市',71,'E',2),(769,'152502','锡林浩特市',71,'X',2),(770,'152522','阿巴嘎旗',71,'A',2),(771,'152523','苏尼特左旗',71,'S',2),(772,'152524','苏尼特右旗',71,'S',2),(773,'152525','东乌珠穆沁旗',71,'D',2),(774,'152526','西乌珠穆沁旗',71,'X',2),(775,'152527','太仆寺旗',71,'T',2),(776,'152528','镶黄旗',71,'X',2),(777,'152529','正镶白旗',71,'Z',2),(778,'152530','正蓝旗',71,'Z',2),(779,'152531','多伦县',71,'D',2),(780,'152921','阿拉善左旗',72,'A',2),(781,'152922','阿拉善右旗',72,'A',2),(782,'152923','额济纳旗',72,'E',2),(783,'210103','沈河区',73,'S',2),(784,'210104','大东区',73,'D',2),(785,'210105','皇姑区',73,'H',2),(786,'210106','铁西区',73,'T',2),(787,'210111','苏家屯区',73,'S',2),(788,'210112','浑南区',73,'H',2),(789,'210113','沈北新区',73,'S',2),(790,'210114','于洪区',73,'Y',2),(791,'210122','辽中县',73,'L',2),(792,'210123','康平县',73,'K',2),(793,'210124','法库县',73,'F',2),(794,'210181','新民市',73,'X',2),(795,'210202','中山区',74,'Z',2),(796,'210203','西岗区',74,'X',2),(797,'210204','沙河口区',74,'S',2),(798,'210211','甘井子区',74,'G',2),(799,'210212','旅顺口区',74,'L',2),(800,'210213','金州区',74,'J',2),(801,'210224','长海县',74,'C',2),(802,'210281','瓦房店市',74,'W',2),(803,'210282','普兰店市',74,'P',2),(804,'210283','庄河市',74,'Z',2),(805,'210302','铁东区',75,'T',2),(806,'210304','立山区',75,'L',2),(807,'210311','千山区',75,'Q',2),(808,'210321','台安县',75,'T',2),(809,'210323','岫岩满族自治县',75,'X',2),(810,'210381','海城市',75,'H',2),(811,'210402','新抚区',76,'X',2),(812,'210403','东洲区',76,'D',2),(813,'210404','望花区',76,'W',2),(814,'210411','顺城区',76,'S',2),(815,'210421','抚顺县',76,'F',2),(816,'210422','新宾满族自治县',76,'X',2),(817,'210423','清原满族自治县',76,'Q',2),(818,'210502','平山区',77,'P',2),(819,'210503','溪湖区',77,'X',2),(820,'210504','明山区',77,'M',2),(821,'210505','南芬区',77,'N',2),(822,'210521','本溪满族自治县',77,'B',2),(823,'210522','桓仁满族自治县',77,'H',2),(824,'210602','元宝区',78,'Y',2),(825,'210603','振兴区',78,'Z',2),(826,'210604','振安区',78,'Z',2),(827,'210624','宽甸满族自治县',78,'K',2),(828,'210681','东港市',78,'D',2),(829,'210682','凤城市',78,'F',2),(830,'210702','古塔区',79,'G',2),(831,'210703','凌河区',79,'L',2),(832,'210711','太和区',79,'T',2),(833,'210726','黑山县',79,'H',2),(834,'210727','义县',79,'Y',2),(835,'210781','凌海市',79,'L',2),(836,'210782','北镇市',79,'B',2),(837,'210802','站前区',80,'Z',2),(838,'210803','西市区',80,'X',2),(839,'210804','鲅鱼圈区',80,'B',2),(840,'210811','老边区',80,'L',2),(841,'210881','盖州市',80,'G',2),(842,'210882','大石桥市',80,'D',2),(843,'210902','海州区',81,'H',2),(844,'210903','新邱区',81,'X',2),(845,'210904','太平区',81,'T',2),(846,'210905','清河门区',81,'Q',2),(847,'210911','细河区',81,'X',2),(848,'210921','阜新蒙古族自治县',81,'F',2),(849,'210922','彰武县',81,'Z',2),(850,'211002','白塔区',82,'B',2),(851,'211003','文圣区',82,'W',2),(852,'211004','宏伟区',82,'H',2),(853,'211005','弓长岭区',82,'G',2),(854,'211011','太子河区',82,'T',2),(855,'211021','辽阳县',82,'L',2),(856,'211081','灯塔市',82,'D',2),(858,'211102','双台子区',83,'S',2),(859,'211103','兴隆台区',83,'X',2),(860,'211121','大洼县',83,'D',2),(861,'211122','盘山县',83,'P',2),(862,'211202','银州区',84,'Y',2),(863,'211204','清河区',84,'Q',2),(864,'211221','铁岭县',84,'T',2),(865,'211223','西丰县',84,'X',2),(866,'211224','昌图县',84,'C',2),(867,'211281','调兵山市',84,'D',2),(868,'211282','开原市',84,'K',2),(869,'211302','双塔区',85,'S',2),(870,'211303','龙城区',85,'L',2),(871,'211321','朝阳县',85,'C',2),(872,'211322','建平县',85,'J',2),(873,'211324','喀喇沁左翼蒙古族自治县',85,'K',2),(874,'211381','北票市',85,'B',2),(875,'211382','凌源市',85,'L',2),(876,'211402','连山区',86,'L',2),(877,'211403','龙港区',86,'L',2),(878,'211404','南票区',86,'N',2),(879,'211421','绥中县',86,'S',2),(880,'211422','建昌县',86,'J',2),(881,'211481','兴城市',86,'X',2),(882,'220102','南关区',87,'N',2),(883,'220103','宽城区',87,'K',2),(884,'220105','二道区',87,'E',2),(885,'220106','绿园区',87,'L',2),(886,'220112','双阳区',87,'S',2),(887,'220113','九台区',87,'J',2),(888,'220122','农安县',87,'N',2),(889,'220182','榆树市',87,'Y',2),(890,'220183','德惠市',87,'D',2),(891,'220202','昌邑区',88,'C',2),(892,'220203','龙潭区',88,'L',2),(893,'220204','船营区',88,'C',2),(894,'220211','丰满区',88,'F',2),(895,'220221','永吉县',88,'Y',2),(896,'220281','蛟河市',88,'J',2),(897,'220282','桦甸市',88,'H',2),(898,'220283','舒兰市',88,'S',2),(899,'220284','磐石市',88,'P',2),(900,'220322','梨树县',89,'L',2),(901,'220323','伊通满族自治县',89,'Y',2),(902,'220381','公主岭市',89,'G',2),(903,'220382','双辽市',89,'S',2),(904,'220402','龙山区',90,'L',2),(905,'220403','西安区',90,'X',2),(906,'220421','东丰县',90,'D',2),(907,'220422','东辽县',90,'D',2),(908,'220502','东昌区',91,'D',2),(909,'220503','二道江区',91,'E',2),(910,'220521','通化县',91,'T',2),(911,'220523','辉南县',91,'H',2),(912,'220524','柳河县',91,'L',2),(913,'220581','梅河口市',91,'M',2),(914,'220582','集安市',91,'J',2),(915,'220602','浑江区',92,'H',2),(916,'220605','江源区',92,'J',2),(917,'220621','抚松县',92,'F',2),(918,'220622','靖宇县',92,'J',2),(919,'220623','长白朝鲜族自治县',92,'C',2),(920,'220681','临江市',92,'L',2),(921,'220702','宁江区',93,'N',2),(922,'220721','前郭尔罗斯蒙古族自治县',93,'Q',2),(923,'220722','长岭县',93,'C',2),(924,'220723','乾安县',93,'Q',2),(925,'220781','扶余市',93,'F',2),(926,'220802','洮北区',94,'T',2),(927,'220821','镇赉县',94,'Z',2),(928,'220822','通榆县',94,'T',2),(929,'220881','洮南市',94,'T',2),(930,'220882','大安市',94,'D',2),(931,'222401','延吉市',95,'Y',2),(932,'222402','图们市',95,'T',2),(933,'222403','敦化市',95,'D',2),(934,'222404','珲春市',95,'H',2),(935,'222405','龙井市',95,'L',2),(936,'222406','和龙市',95,'H',2),(937,'222424','汪清县',95,'W',2),(938,'222426','安图县',95,'A',2),(939,'230102','道里区',96,'D',2),(940,'230103','南岗区',96,'N',2),(941,'230104','道外区',96,'D',2),(942,'230108','平房区',96,'P',2),(943,'230109','松北区',96,'S',2),(944,'230110','香坊区',96,'X',2),(945,'230111','呼兰区',96,'H',2),(946,'230112','阿城区',96,'A',2),(947,'230123','依兰县',96,'Y',2),(948,'230124','方正县',96,'F',2),(949,'230125','宾县',96,'B',2),(950,'230126','巴彦县',96,'B',2),(951,'230127','木兰县',96,'M',2),(952,'230128','通河县',96,'T',2),(953,'230129','延寿县',96,'Y',2),(954,'230182','双城市',96,'S',2),(955,'230183','尚志市',96,'S',2),(956,'230184','五常市',96,'W',2),(957,'230202','龙沙区',97,'L',2),(958,'230203','建华区',97,'J',2),(959,'230204','铁锋区',97,'T',2),(960,'230205','昂昂溪区',97,'A',2),(961,'230206','富拉尔基区',97,'F',2),(962,'230207','碾子山区',97,'N',2),(963,'230208','梅里斯达斡尔族区',97,'M',2),(964,'230221','龙江县',97,'L',2),(965,'230223','依安县',97,'Y',2),(966,'230224','泰来县',97,'T',2),(967,'230225','甘南县',97,'G',2),(968,'230227','富裕县',97,'F',2),(969,'230229','克山县',97,'K',2),(970,'230230','克东县',97,'K',2),(971,'230231','拜泉县',97,'B',2),(972,'230281','讷河市',97,'N',2),(973,'230302','鸡冠区',98,'J',2),(974,'230303','恒山区',98,'H',2),(975,'230304','滴道区',98,'D',2),(976,'230305','梨树区',98,'L',2),(977,'230306','城子河区',98,'C',2),(978,'230307','麻山区',98,'M',2),(979,'230321','鸡东县',98,'J',2),(980,'230381','虎林市',98,'H',2),(981,'230382','密山市',98,'M',2),(982,'230402','向阳区',99,'X',2),(983,'230403','工农区',99,'G',2),(984,'230404','南山区',99,'N',2),(985,'230405','兴安区',99,'X',2),(986,'230406','东山区',99,'D',2),(987,'230407','兴山区',99,'X',2),(988,'230421','萝北县',99,'L',2),(989,'230422','绥滨县',99,'S',2),(990,'230502','尖山区',100,'J',2),(991,'230503','岭东区',100,'L',2),(992,'230505','四方台区',100,'S',2),(993,'230506','宝山区',100,'B',2),(994,'230521','集贤县',100,'J',2),(995,'230522','友谊县',100,'Y',2),(996,'230523','宝清县',100,'B',2),(997,'230524','饶河县',100,'R',2),(998,'230602','萨尔图区',101,'S',2),(999,'230603','龙凤区',101,'L',2),(1000,'230604','让胡路区',101,'R',2),(1001,'230605','红岗区',101,'H',2),(1002,'230606','大同区',101,'D',2),(1003,'230621','肇州县',101,'Z',2),(1004,'230622','肇源县',101,'Z',2),(1005,'230623','林甸县',101,'L',2),(1006,'230624','杜尔伯特蒙古族自治县',101,'D',2),(1007,'230702','伊春区',102,'Y',2),(1008,'230703','南岔区',102,'N',2),(1009,'230704','友好区',102,'Y',2),(1010,'230705','西林区',102,'X',2),(1011,'230706','翠峦区',102,'C',2),(1012,'230707','新青区',102,'X',2),(1013,'230708','美溪区',102,'M',2),(1014,'230709','金山屯区',102,'J',2),(1015,'230710','五营区',102,'W',2),(1016,'230711','乌马河区',102,'W',2),(1017,'230712','汤旺河区',102,'T',2),(1018,'230713','带岭区',102,'D',2),(1019,'230714','乌伊岭区',102,'W',2),(1020,'230715','红星区',102,'H',2),(1021,'230716','上甘岭区',102,'S',2),(1022,'230722','嘉荫县',102,'J',2),(1023,'230781','铁力市',102,'T',2),(1024,'230804','前进区',103,'Q',2),(1025,'230805','东风区',103,'D',2),(1026,'230822','桦南县',103,'H',2),(1027,'230826','桦川县',103,'H',2),(1028,'230828','汤原县',103,'T',2),(1029,'230833','抚远县',103,'F',2),(1030,'230881','同江市',103,'T',2),(1031,'230882','富锦市',103,'F',2),(1032,'230902','新兴区',104,'X',2),(1033,'230903','桃山区',104,'T',2),(1034,'230904','茄子河区',104,'Q',2),(1035,'230921','勃利县',104,'B',2),(1036,'231002','东安区',105,'D',2),(1037,'231003','阳明区',105,'Y',2),(1038,'231004','爱民区',105,'A',2),(1039,'231024','东宁县',105,'D',2),(1040,'231025','林口县',105,'L',2),(1041,'231081','绥芬河市',105,'S',2),(1042,'231083','海林市',105,'H',2),(1043,'231084','宁安市',105,'N',2),(1044,'231085','穆棱市',105,'M',2),(1045,'231102','爱辉区',106,'A',2),(1046,'231121','嫩江县',106,'N',2),(1047,'231123','逊克县',106,'X',2),(1048,'231124','孙吴县',106,'S',2),(1049,'231181','北安市',106,'B',2),(1050,'231182','五大连池市',106,'W',2),(1051,'231202','北林区',107,'B',2),(1052,'231221','望奎县',107,'W',2),(1053,'231222','兰西县',107,'L',2),(1054,'231223','青冈县',107,'Q',2),(1055,'231224','庆安县',107,'Q',2),(1056,'231225','明水县',107,'M',2),(1057,'231226','绥棱县',107,'S',2),(1058,'231281','安达市',107,'A',2),(1059,'231282','肇东市',107,'Z',2),(1060,'231283','海伦市',107,'H',2),(1061,'232721','呼玛县',108,'H',2),(1062,'232722','塔河县',108,'T',2),(1063,'232723','漠河县',108,'M',2),(1064,'310101','黄浦区',109,'H',2),(1065,'310104','徐汇区',109,'X',2),(1066,'310105','长宁区',109,'C',2),(1067,'310106','静安区',109,'J',2),(1068,'310107','普陀区',109,'P',2),(1069,'310108','闸北区',109,'Z',2),(1070,'310109','虹口区',109,'H',2),(1071,'310110','杨浦区',109,'Y',2),(1072,'310112','闵行区',109,'M',2),(1073,'310114','嘉定区',109,'J',2),(1074,'310115','浦东新区',109,'P',2),(1075,'310116','金山区',109,'J',2),(1076,'310117','松江区',109,'S',2),(1077,'310118','青浦区',109,'Q',2),(1078,'310120','奉贤区',109,'F',2),(1079,'310230','崇明县',110,'C',2),(1080,'320102','玄武区',111,'X',2),(1081,'320104','秦淮区',111,'Q',2),(1082,'320105','建邺区',111,'J',2),(1083,'320106','鼓楼区',111,'G',2),(1084,'320111','浦口区',111,'P',2),(1085,'320113','栖霞区',111,'Q',2),(1086,'320114','雨花台区',111,'Y',2),(1087,'320115','江宁区',111,'J',2),(1088,'320116','六合区',111,'L',2),(1089,'320117','溧水区',111,'L',2),(1090,'320118','高淳区',111,'G',2),(1091,'320202','崇安区',112,'C',2),(1092,'320203','南长区',112,'N',2),(1093,'320204','北塘区',112,'B',2),(1094,'320205','锡山区',112,'X',2),(1095,'320206','惠山区',112,'H',2),(1096,'320211','滨湖区',112,'B',2),(1097,'320281','江阴市',112,'J',2),(1098,'320282','宜兴市',112,'Y',2),(1099,'320303','云龙区',113,'Y',2),(1100,'320305','贾汪区',113,'J',2),(1101,'320311','泉山区',113,'Q',2),(1102,'320312','铜山区',113,'T',2),(1103,'320321','丰县',113,'F',2),(1104,'320322','沛县',113,'P',2),(1105,'320324','睢宁县',113,'S',2),(1106,'320381','新沂市',113,'X',2),(1107,'320382','邳州市',113,'P',2),(1108,'320402','天宁区',114,'T',2),(1109,'320404','钟楼区',114,'Z',2),(1110,'320405','戚墅堰区',114,'Q',2),(1111,'320411','新北区',114,'X',2),(1112,'320412','武进区',114,'W',2),(1113,'320481','溧阳市',114,'L',2),(1114,'320482','金坛市',114,'J',2),(1115,'320505','虎丘区',115,'H',2),(1116,'320506','吴中区',115,'W',2),(1117,'320507','相城区',115,'X',2),(1118,'320508','姑苏区',115,'G',2),(1119,'320509','吴江区',115,'W',2),(1120,'320581','常熟市',115,'C',2),(1121,'320582','张家港市',115,'Z',2),(1122,'320583','昆山市',115,'K',2),(1123,'320585','太仓市',115,'T',2),(1124,'320602','崇川区',116,'C',2),(1125,'320611','港闸区',116,'G',2),(1126,'320612','通州区',116,'T',2),(1127,'320621','海安县',116,'H',2),(1128,'320623','如东县',116,'R',2),(1129,'320681','启东市',116,'Q',2),(1130,'320682','如皋市',116,'R',2),(1131,'320684','海门市',116,'H',2),(1132,'320703','连云区',117,'L',2),(1133,'320707','赣榆区',117,'G',2),(1134,'320722','东海县',117,'D',2),(1135,'320723','灌云县',117,'G',2),(1136,'320724','灌南县',117,'G',2),(1137,'320803','淮安区',118,'H',2),(1138,'320804','淮阴区',118,'H',2),(1139,'320811','清浦区',118,'Q',2),(1140,'320826','涟水县',118,'L',2),(1141,'320829','洪泽县',118,'H',2),(1142,'320830','盱眙县',118,'X',2),(1143,'320831','金湖县',118,'J',2),(1144,'320902','亭湖区',119,'T',2),(1145,'320903','盐都区',119,'Y',2),(1146,'320921','响水县',119,'X',2),(1147,'320922','滨海县',119,'B',2),(1148,'320923','阜宁县',119,'F',2),(1149,'320924','射阳县',119,'S',2),(1150,'320925','建湖县',119,'J',2),(1151,'320981','东台市',119,'D',2),(1152,'320982','大丰市',119,'D',2),(1153,'321002','广陵区',120,'G',2),(1154,'321003','邗江区',120,'H',2),(1155,'321012','江都区',120,'J',2),(1156,'321023','宝应县',120,'B',2),(1157,'321081','仪征市',120,'Y',2),(1158,'321084','高邮市',120,'G',2),(1159,'321102','京口区',121,'J',2),(1160,'321111','润州区',121,'R',2),(1161,'321112','丹徒区',121,'D',2),(1162,'321181','丹阳市',121,'D',2),(1163,'321182','扬中市',121,'Y',2),(1164,'321183','句容市',121,'J',2),(1165,'321202','海陵区',122,'H',2),(1166,'321203','高港区',122,'G',2),(1167,'321204','姜堰区',122,'J',2),(1168,'321281','兴化市',122,'X',2),(1169,'321282','靖江市',122,'J',2),(1170,'321283','泰兴市',122,'T',2),(1171,'321302','宿城区',123,'S',2),(1172,'321311','宿豫区',123,'S',2),(1173,'321322','沭阳县',123,'S',2),(1174,'321323','泗阳县',123,'S',2),(1175,'321324','泗洪县',123,'S',2),(1176,'330102','上城区',124,'S',2),(1177,'330103','下城区',124,'X',2),(1178,'330104','江干区',124,'J',2),(1179,'330105','拱墅区',124,'G',2),(1180,'330106','西湖区',124,'X',2),(1181,'330108','滨江区',124,'B',2),(1182,'330109','萧山区',124,'X',2),(1183,'330110','余杭区',124,'Y',2),(1184,'330122','桐庐县',124,'T',2),(1185,'330127','淳安县',124,'C',2),(1186,'330182','建德市',124,'J',2),(1187,'330183','富阳市',124,'F',2),(1188,'330185','临安市',124,'L',2),(1189,'330203','海曙区',125,'H',2),(1190,'330204','江东区',125,'J',2),(1191,'330205','江北区',125,'J',2),(1192,'330206','北仑区',125,'B',2),(1193,'330211','镇海区',125,'Z',2),(1194,'330212','鄞州区',125,'Y',2),(1195,'330225','象山县',125,'X',2),(1196,'330226','宁海县',125,'N',2),(1197,'330281','余姚市',125,'Y',2),(1198,'330282','慈溪市',125,'C',2),(1199,'330283','奉化市',125,'F',2),(1200,'330302','鹿城区',126,'L',2),(1201,'330303','龙湾区',126,'L',2),(1202,'330304','瓯海区',126,'O',2),(1203,'330322','洞头县',126,'D',2),(1204,'330324','永嘉县',126,'Y',2),(1205,'330326','平阳县',126,'P',2),(1206,'330327','苍南县',126,'C',2),(1207,'330328','文成县',126,'W',2),(1208,'330329','泰顺县',126,'T',2),(1209,'330381','瑞安市',126,'R',2),(1210,'330382','乐清市',126,'L',2),(1211,'330402','南湖区',127,'N',2),(1212,'330411','秀洲区',127,'X',2),(1213,'330421','嘉善县',127,'J',2),(1214,'330424','海盐县',127,'H',2),(1215,'330481','海宁市',127,'H',2),(1216,'330482','平湖市',127,'P',2),(1217,'330483','桐乡市',127,'T',2),(1218,'330502','吴兴区',128,'W',2),(1219,'330503','南浔区',128,'N',2),(1220,'330521','德清县',128,'D',2),(1221,'330522','长兴县',128,'C',2),(1222,'330523','安吉县',128,'A',2),(1223,'330602','越城区',129,'Y',2),(1224,'330603','柯桥区',129,'K',2),(1225,'330604','上虞区',129,'S',2),(1226,'330624','新昌县',129,'X',2),(1227,'330681','诸暨市',129,'Z',2),(1228,'330683','嵊州市',129,'S',2),(1229,'330702','婺城区',130,'W',2),(1230,'330703','金东区',130,'J',2),(1231,'330723','武义县',130,'W',2),(1232,'330726','浦江县',130,'P',2),(1233,'330727','磐安县',130,'P',2),(1234,'330781','兰溪市',130,'L',2),(1235,'330782','义乌市',130,'Y',2),(1236,'330783','东阳市',130,'D',2),(1237,'330784','永康市',130,'Y',2),(1238,'330802','柯城区',131,'K',2),(1239,'330803','衢江区',131,'Q',2),(1240,'330822','常山县',131,'C',2),(1241,'330824','开化县',131,'K',2),(1242,'330825','龙游县',131,'L',2),(1243,'330881','江山市',131,'J',2),(1244,'330902','定海区',132,'D',2),(1245,'330921','岱山县',132,'D',2),(1246,'330922','嵊泗县',132,'S',2),(1247,'331002','椒江区',133,'J',2),(1248,'331003','黄岩区',133,'H',2),(1249,'331004','路桥区',133,'L',2),(1250,'331021','玉环县',133,'Y',2),(1251,'331022','三门县',133,'S',2),(1252,'331023','天台县',133,'T',2),(1253,'331024','仙居县',133,'X',2),(1254,'331081','温岭市',133,'W',2),(1255,'331082','临海市',133,'L',2),(1256,'331102','莲都区',134,'L',2),(1257,'331121','青田县',134,'Q',2),(1258,'331122','缙云县',134,'J',2),(1259,'331123','遂昌县',134,'S',2),(1260,'331124','松阳县',134,'S',2),(1261,'331125','云和县',134,'Y',2),(1262,'331126','庆元县',134,'Q',2),(1263,'331127','景宁畲族自治县',134,'J',2),(1264,'331181','龙泉市',134,'L',2),(1265,'340102','瑶海区',135,'Y',2),(1266,'340103','庐阳区',135,'L',2),(1267,'340104','蜀山区',135,'S',2),(1268,'340111','包河区',135,'B',2),(1269,'340121','长丰县',135,'C',2),(1270,'340122','肥东县',135,'F',2),(1271,'340123','肥西县',135,'F',2),(1272,'340124','庐江县',135,'L',2),(1273,'340181','巢湖市',135,'C',2),(1274,'340202','镜湖区',136,'J',2),(1275,'340203','弋江区',136,'Y',2),(1276,'340207','鸠江区',136,'J',2),(1277,'340208','三山区',136,'S',2),(1278,'340221','芜湖县',136,'W',2),(1279,'340222','繁昌县',136,'F',2),(1280,'340223','南陵县',136,'N',2),(1281,'340225','无为县',136,'W',2),(1282,'340302','龙子湖区',137,'L',2),(1283,'340303','蚌山区',137,'B',2),(1284,'340304','禹会区',137,'Y',2),(1285,'340311','淮上区',137,'H',2),(1286,'340321','怀远县',137,'H',2),(1287,'340322','五河县',137,'W',2),(1288,'340323','固镇县',137,'G',2),(1289,'340402','大通区',138,'D',2),(1290,'340403','田家庵区',138,'T',2),(1291,'340404','谢家集区',138,'X',2),(1292,'340405','八公山区',138,'B',2),(1293,'340406','潘集区',138,'P',2),(1294,'340421','凤台县',138,'F',2),(1295,'340503','花山区',139,'H',2),(1296,'340504','雨山区',139,'Y',2),(1297,'340506','博望区',139,'B',2),(1298,'340521','当涂县',139,'D',2),(1299,'340522','含山县',139,'H',2),(1300,'340523','和县',139,'H',2),(1301,'340602','杜集区',140,'D',2),(1302,'340603','相山区',140,'X',2),(1303,'340604','烈山区',140,'L',2),(1304,'340621','濉溪县',140,'S',2),(1305,'340702','铜官山区',141,'T',2),(1306,'340703','狮子山区',141,'S',2),(1307,'340721','铜陵县',141,'T',2),(1308,'340802','迎江区',142,'Y',2),(1309,'340803','大观区',142,'D',2),(1310,'340811','宜秀区',142,'Y',2),(1311,'340822','怀宁县',142,'H',2),(1312,'340823','枞阳县',142,'C',2),(1313,'340824','潜山县',142,'Q',2),(1314,'340825','太湖县',142,'T',2),(1315,'340826','宿松县',142,'S',2),(1316,'340827','望江县',142,'W',2),(1317,'340828','岳西县',142,'Y',2),(1318,'340881','桐城市',142,'T',2),(1319,'341002','屯溪区',143,'T',2),(1320,'341003','黄山区',143,'H',2),(1321,'341004','徽州区',143,'H',2),(1322,'341021','歙县',143,'X',2),(1323,'341022','休宁县',143,'X',2),(1324,'341023','黟县',143,'Y',2),(1325,'341024','祁门县',143,'Q',2),(1326,'341102','琅琊区',144,'L',2),(1327,'341103','南谯区',144,'N',2),(1328,'341122','来安县',144,'L',2),(1329,'341124','全椒县',144,'Q',2),(1330,'341125','定远县',144,'D',2),(1331,'341126','凤阳县',144,'F',2),(1332,'341181','天长市',144,'T',2),(1333,'341182','明光市',144,'M',2),(1334,'341202','颍州区',145,'Y',2),(1335,'341203','颍东区',145,'Y',2),(1336,'341204','颍泉区',145,'Y',2),(1337,'341221','临泉县',145,'L',2),(1338,'341222','太和县',145,'T',2),(1339,'341225','阜南县',145,'F',2),(1340,'341226','颍上县',145,'Y',2),(1341,'341282','界首市',145,'J',2),(1342,'341302','埇桥区',146,'Y',2),(1343,'341321','砀山县',146,'D',2),(1344,'341322','萧县',146,'X',2),(1345,'341323','灵璧县',146,'L',2),(1346,'341324','泗县',146,'S',2),(1347,'341502','金安区',147,'J',2),(1348,'341503','裕安区',147,'Y',2),(1349,'341521','寿县',147,'S',2),(1350,'341522','霍邱县',147,'H',2),(1351,'341523','舒城县',147,'S',2),(1352,'341524','金寨县',147,'J',2),(1353,'341525','霍山县',147,'H',2),(1354,'341602','谯城区',148,'Q',2),(1355,'341621','涡阳县',148,'W',2),(1356,'341622','蒙城县',148,'M',2),(1357,'341623','利辛县',148,'L',2),(1358,'341702','贵池区',149,'G',2),(1359,'341721','东至县',149,'D',2),(1360,'341722','石台县',149,'S',2),(1361,'341723','青阳县',149,'Q',2),(1362,'341802','宣州区',150,'X',2),(1363,'341821','郎溪县',150,'L',2),(1364,'341822','广德县',150,'G',2),(1365,'341823','泾县',150,'J',2),(1366,'341824','绩溪县',150,'J',2),(1367,'341825','旌德县',150,'J',2),(1368,'341881','宁国市',150,'N',2),(1369,'350103','台江区',151,'T',2),(1370,'350104','仓山区',151,'C',2),(1371,'350105','马尾区',151,'M',2),(1372,'350111','晋安区',151,'J',2),(1373,'350121','闽侯县',151,'M',2),(1374,'350122','连江县',151,'L',2),(1375,'350123','罗源县',151,'L',2),(1376,'350124','闽清县',151,'M',2),(1377,'350125','永泰县',151,'Y',2),(1378,'350128','平潭县',151,'P',2),(1379,'350181','福清市',151,'F',2),(1380,'350182','长乐市',151,'C',2),(1381,'350203','思明区',152,'S',2),(1382,'350205','海沧区',152,'H',2),(1383,'350206','湖里区',152,'H',2),(1384,'350211','集美区',152,'J',2),(1385,'350212','同安区',152,'T',2),(1386,'350213','翔安区',152,'X',2),(1387,'350302','城厢区',153,'C',2),(1388,'350303','涵江区',153,'H',2),(1389,'350304','荔城区',153,'L',2),(1390,'350305','秀屿区',153,'X',2),(1391,'350322','仙游县',153,'X',2),(1392,'350402','梅列区',154,'M',2),(1393,'350403','三元区',154,'S',2),(1394,'350421','明溪县',154,'M',2),(1395,'350423','清流县',154,'Q',2),(1396,'350424','宁化县',154,'N',2),(1397,'350425','大田县',154,'D',2),(1398,'350426','尤溪县',154,'Y',2),(1399,'350427','沙县',154,'S',2),(1400,'350428','将乐县',154,'J',2),(1401,'350429','泰宁县',154,'T',2),(1402,'350430','建宁县',154,'J',2),(1403,'350481','永安市',154,'Y',2),(1404,'350502','鲤城区',155,'L',2),(1405,'350503','丰泽区',155,'F',2),(1406,'350504','洛江区',155,'L',2),(1407,'350505','泉港区',155,'Q',2),(1408,'350521','惠安县',155,'H',2),(1409,'350524','安溪县',155,'A',2),(1410,'350525','永春县',155,'Y',2),(1411,'350526','德化县',155,'D',2),(1412,'350527','金门县',155,'J',2),(1413,'350581','石狮市',155,'S',2),(1414,'350582','晋江市',155,'J',2),(1415,'350583','南安市',155,'N',2),(1416,'350602','芗城区',156,'X',2),(1417,'350603','龙文区',156,'L',2),(1418,'350622','云霄县',156,'Y',2),(1419,'350623','漳浦县',156,'Z',2),(1420,'350624','诏安县',156,'Z',2),(1421,'350625','长泰县',156,'C',2),(1422,'350626','东山县',156,'D',2),(1423,'350627','南靖县',156,'N',2),(1424,'350628','平和县',156,'P',2),(1425,'350629','华安县',156,'H',2),(1426,'350681','龙海市',156,'L',2),(1427,'350702','延平区',157,'Y',2),(1428,'350721','顺昌县',157,'S',2),(1429,'350722','浦城县',157,'P',2),(1430,'350723','光泽县',157,'G',2),(1431,'350724','松溪县',157,'S',2),(1432,'350725','政和县',157,'Z',2),(1433,'350781','邵武市',157,'S',2),(1434,'350782','武夷山市',157,'W',2),(1435,'350783','建瓯市',157,'J',2),(1436,'350784','建阳市',157,'J',2),(1437,'350802','新罗区',158,'X',2),(1438,'350821','长汀县',158,'C',2),(1439,'350822','永定县',158,'Y',2),(1440,'350823','上杭县',158,'S',2),(1441,'350824','武平县',158,'W',2),(1442,'350825','连城县',158,'L',2),(1443,'350881','漳平市',158,'Z',2),(1444,'350902','蕉城区',159,'J',2),(1445,'350921','霞浦县',159,'X',2),(1446,'350922','古田县',159,'G',2),(1447,'350923','屏南县',159,'P',2),(1448,'350924','寿宁县',159,'S',2),(1449,'350925','周宁县',159,'Z',2),(1450,'350926','柘荣县',159,'Z',2),(1451,'350981','福安市',159,'F',2),(1452,'350982','福鼎市',159,'F',2),(1453,'360102','东湖区',160,'D',2),(1454,'360104','青云谱区',160,'Q',2),(1455,'360105','湾里区',160,'W',2),(1456,'360111','青山湖区',160,'Q',2),(1457,'360121','南昌县',160,'N',2),(1458,'360122','新建县',160,'X',2),(1459,'360123','安义县',160,'A',2),(1460,'360124','进贤县',160,'J',2),(1461,'360202','昌江区',161,'C',2),(1462,'360203','珠山区',161,'Z',2),(1463,'360222','浮梁县',161,'F',2),(1464,'360281','乐平市',161,'L',2),(1465,'360302','安源区',162,'A',2),(1466,'360313','湘东区',162,'X',2),(1467,'360321','莲花县',162,'L',2),(1468,'360322','上栗县',162,'S',2),(1469,'360323','芦溪县',162,'L',2),(1470,'360402','庐山区',163,'L',2),(1471,'360403','浔阳区',163,'X',2),(1472,'360421','九江县',163,'J',2),(1473,'360423','武宁县',163,'W',2),(1474,'360424','修水县',163,'X',2),(1475,'360425','永修县',163,'Y',2),(1476,'360426','德安县',163,'D',2),(1477,'360427','星子县',163,'X',2),(1478,'360428','都昌县',163,'D',2),(1479,'360429','湖口县',163,'H',2),(1480,'360430','彭泽县',163,'P',2),(1481,'360481','瑞昌市',163,'R',2),(1482,'360482','共青城市',163,'G',2),(1483,'360502','渝水区',164,'Y',2),(1484,'360521','分宜县',164,'F',2),(1485,'360602','月湖区',165,'Y',2),(1486,'360622','余江县',165,'Y',2),(1487,'360681','贵溪市',165,'G',2),(1488,'360702','章贡区',166,'Z',2),(1489,'360703','南康区',166,'N',2),(1490,'360721','赣县',166,'G',2),(1491,'360722','信丰县',166,'X',2),(1492,'360723','大余县',166,'D',2),(1493,'360724','上犹县',166,'S',2),(1494,'360725','崇义县',166,'C',2),(1495,'360726','安远县',166,'A',2),(1496,'360727','龙南县',166,'L',2),(1497,'360728','定南县',166,'D',2),(1498,'360729','全南县',166,'Q',2),(1499,'360730','宁都县',166,'N',2),(1500,'360731','于都县',166,'Y',2),(1501,'360732','兴国县',166,'X',2),(1502,'360733','会昌县',166,'H',2),(1503,'360734','寻乌县',166,'X',2),(1504,'360735','石城县',166,'S',2),(1505,'360781','瑞金市',166,'R',2),(1506,'360802','吉州区',167,'J',2),(1507,'360803','青原区',167,'Q',2),(1508,'360821','吉安县',167,'J',2),(1509,'360822','吉水县',167,'J',2),(1510,'360823','峡江县',167,'X',2),(1511,'360824','新干县',167,'X',2),(1512,'360825','永丰县',167,'Y',2),(1513,'360826','泰和县',167,'T',2),(1514,'360827','遂川县',167,'S',2),(1515,'360828','万安县',167,'W',2),(1516,'360829','安福县',167,'A',2),(1517,'360830','永新县',167,'Y',2),(1518,'360881','井冈山市',167,'J',2),(1519,'360902','袁州区',168,'Y',2),(1520,'360921','奉新县',168,'F',2),(1521,'360922','万载县',168,'W',2),(1522,'360923','上高县',168,'S',2),(1523,'360924','宜丰县',168,'Y',2),(1524,'360925','靖安县',168,'J',2),(1525,'360926','铜鼓县',168,'T',2),(1526,'360981','丰城市',168,'F',2),(1527,'360982','樟树市',168,'Z',2),(1528,'360983','高安市',168,'G',2),(1529,'361002','临川区',169,'L',2),(1530,'361021','南城县',169,'N',2),(1531,'361022','黎川县',169,'L',2),(1532,'361023','南丰县',169,'N',2),(1533,'361024','崇仁县',169,'C',2),(1534,'361025','乐安县',169,'L',2),(1535,'361026','宜黄县',169,'Y',2),(1536,'361027','金溪县',169,'J',2),(1537,'361028','资溪县',169,'Z',2),(1538,'361029','东乡县',169,'D',2),(1539,'361030','广昌县',169,'G',2),(1540,'361102','信州区',170,'X',2),(1541,'361121','上饶县',170,'S',2),(1542,'361122','广丰县',170,'G',2),(1543,'361123','玉山县',170,'Y',2),(1544,'361124','铅山县',170,'Q',2),(1545,'361125','横峰县',170,'H',2),(1546,'361126','弋阳县',170,'Y',2),(1547,'361127','余干县',170,'Y',2),(1548,'361128','鄱阳县',170,'P',2),(1549,'361129','万年县',170,'W',2),(1550,'361130','婺源县',170,'W',2),(1551,'361181','德兴市',170,'D',2),(1552,'370102','历下区',171,'L',2),(1553,'370103','市中区',171,'S',2),(1554,'370104','槐荫区',171,'H',2),(1555,'370105','天桥区',171,'T',2),(1556,'370112','历城区',171,'L',2),(1557,'370113','长清区',171,'C',2),(1558,'370124','平阴县',171,'P',2),(1559,'370125','济阳县',171,'J',2),(1560,'370126','商河县',171,'S',2),(1561,'370181','章丘市',171,'Z',2),(1562,'370202','市南区',172,'S',2),(1563,'370203','市北区',172,'S',2),(1564,'370211','黄岛区',172,'H',2),(1565,'370212','崂山区',172,'L',2),(1566,'370213','李沧区',172,'L',2),(1567,'370214','城阳区',172,'C',2),(1568,'370281','胶州市',172,'J',2),(1569,'370282','即墨市',172,'J',2),(1570,'370283','平度市',172,'P',2),(1571,'370285','莱西市',172,'L',2),(1572,'370302','淄川区',173,'Z',2),(1573,'370303','张店区',173,'Z',2),(1574,'370304','博山区',173,'B',2),(1575,'370305','临淄区',173,'L',2),(1576,'370306','周村区',173,'Z',2),(1577,'370321','桓台县',173,'H',2),(1578,'370322','高青县',173,'G',2),(1579,'370323','沂源县',173,'Y',2),(1580,'370403','薛城区',174,'X',2),(1581,'370404','峄城区',174,'Y',2),(1582,'370405','台儿庄区',174,'T',2),(1583,'370406','山亭区',174,'S',2),(1584,'370481','滕州市',174,'T',2),(1585,'370502','东营区',175,'D',2),(1586,'370503','河口区',175,'H',2),(1587,'370521','垦利县',175,'K',2),(1588,'370522','利津县',175,'L',2),(1589,'370523','广饶县',175,'G',2),(1590,'370602','芝罘区',176,'Z',2),(1591,'370611','福山区',176,'F',2),(1592,'370612','牟平区',176,'M',2),(1593,'370613','莱山区',176,'L',2),(1594,'370634','长岛县',176,'C',2),(1595,'370681','龙口市',176,'L',2),(1596,'370682','莱阳市',176,'L',2),(1597,'370683','莱州市',176,'L',2),(1598,'370684','蓬莱市',176,'P',2),(1599,'370685','招远市',176,'Z',2),(1600,'370686','栖霞市',176,'Q',2),(1601,'370687','海阳市',176,'H',2),(1602,'370702','潍城区',177,'W',2),(1603,'370703','寒亭区',177,'H',2),(1604,'370704','坊子区',177,'F',2),(1605,'370705','奎文区',177,'K',2),(1606,'370724','临朐县',177,'L',2),(1607,'370725','昌乐县',177,'C',2),(1608,'370781','青州市',177,'Q',2),(1609,'370782','诸城市',177,'Z',2),(1610,'370783','寿光市',177,'S',2),(1611,'370784','安丘市',177,'A',2),(1612,'370785','高密市',177,'G',2),(1613,'370786','昌邑市',177,'C',2),(1614,'370811','任城区',178,'R',2),(1615,'370812','兖州区',178,'Y',2),(1616,'370826','微山县',178,'W',2),(1617,'370827','鱼台县',178,'Y',2),(1618,'370828','金乡县',178,'J',2),(1619,'370829','嘉祥县',178,'J',2),(1620,'370830','汶上县',178,'W',2),(1621,'370831','泗水县',178,'S',2),(1622,'370832','梁山县',178,'L',2),(1623,'370881','曲阜市',178,'Q',2),(1624,'370883','邹城市',178,'Z',2),(1625,'370902','泰山区',179,'T',2),(1626,'370911','岱岳区',179,'D',2),(1627,'370921','宁阳县',179,'N',2),(1628,'370923','东平县',179,'D',2),(1629,'370982','新泰市',179,'X',2),(1630,'370983','肥城市',179,'F',2),(1631,'371002','环翠区',180,'H',2),(1632,'371003','文登区',180,'W',2),(1633,'371082','荣成市',180,'R',2),(1634,'371083','乳山市',180,'R',2),(1635,'371102','东港区',181,'D',2),(1636,'371103','岚山区',181,'L',2),(1637,'371121','五莲县',181,'W',2),(1638,'371122','莒县',181,'J',2),(1639,'371202','莱城区',182,'L',2),(1640,'371203','钢城区',182,'G',2),(1641,'371302','兰山区',183,'L',2),(1642,'371311','罗庄区',183,'L',2),(1643,'371321','沂南县',183,'Y',2),(1644,'371322','郯城县',183,'T',2),(1645,'371323','沂水县',183,'Y',2),(1646,'371324','兰陵县',183,'L',2),(1647,'371325','费县',183,'F',2),(1648,'371326','平邑县',183,'P',2),(1649,'371327','莒南县',183,'J',2),(1650,'371328','蒙阴县',183,'M',2),(1651,'371329','临沭县',183,'L',2),(1652,'371402','德城区',184,'D',2),(1653,'371403','陵城区',184,'L',2),(1654,'371422','宁津县',184,'N',2),(1655,'371423','庆云县',184,'Q',2),(1656,'371424','临邑县',184,'L',2),(1657,'371425','齐河县',184,'Q',2),(1658,'371426','平原县',184,'P',2),(1659,'371427','夏津县',184,'X',2),(1660,'371428','武城县',184,'W',2),(1661,'371481','乐陵市',184,'L',2),(1662,'371482','禹城市',184,'Y',2),(1663,'371502','东昌府区',185,'D',2),(1664,'371521','阳谷县',185,'Y',2),(1665,'371522','莘县',185,'X',2),(1666,'371523','茌平县',185,'C',2),(1667,'371524','东阿县',185,'D',2),(1668,'371525','冠县',185,'G',2),(1669,'371526','高唐县',185,'G',2),(1670,'371581','临清市',185,'L',2),(1671,'371602','滨城区',186,'B',2),(1672,'371603','沾化区',186,'Z',2),(1673,'371621','惠民县',186,'H',2),(1674,'371622','阳信县',186,'Y',2),(1675,'371623','无棣县',186,'W',2),(1676,'371625','博兴县',186,'B',2),(1677,'371626','邹平县',186,'Z',2),(1678,'371702','牡丹区',187,'M',2),(1679,'371721','曹县',187,'C',2),(1680,'371722','单县',187,'D',2),(1681,'371723','成武县',187,'C',2),(1682,'371724','巨野县',187,'J',2),(1683,'371725','郓城县',187,'Y',2),(1684,'371726','鄄城县',187,'J',2),(1685,'371727','定陶县',187,'D',2),(1686,'371728','东明县',187,'D',2),(1687,'410102','中原区',188,'Z',2),(1688,'410103','二七区',188,'E',2),(1689,'410104','管城回族区',188,'G',2),(1690,'410105','金水区',188,'J',2),(1691,'410106','上街区',188,'S',2),(1692,'410108','惠济区',188,'H',2),(1693,'410122','中牟县',188,'Z',2),(1694,'410181','巩义市',188,'G',2),(1695,'410182','荥阳市',188,'Y',2),(1696,'410183','新密市',188,'X',2),(1697,'410184','新郑市',188,'X',2),(1698,'410185','登封市',188,'D',2),(1699,'410202','龙亭区',189,'L',2),(1700,'410203','顺河回族区',189,'S',2),(1701,'410205','禹王台区',189,'Y',2),(1702,'410211','金明区',189,'J',2),(1703,'410221','杞县',189,'Q',2),(1704,'410222','通许县',189,'T',2),(1705,'410223','尉氏县',189,'W',2),(1706,'410224','开封县',189,'K',2),(1707,'410225','兰考县',189,'L',2),(1708,'410302','老城区',190,'L',2),(1709,'410303','西工区',190,'X',2),(1710,'410304','瀍河回族区',190,'C',2),(1711,'410305','涧西区',190,'J',2),(1712,'410306','吉利区',190,'J',2),(1713,'410311','洛龙区',190,'L',2),(1714,'410322','孟津县',190,'M',2),(1715,'410323','新安县',190,'X',2),(1716,'410324','栾川县',190,'L',2),(1717,'410325','嵩县',190,'S',2),(1718,'410326','汝阳县',190,'R',2),(1719,'410327','宜阳县',190,'Y',2),(1720,'410328','洛宁县',190,'L',2),(1721,'410329','伊川县',190,'Y',2),(1722,'410381','偃师市',190,'Y',2),(1723,'410403','卫东区',191,'W',2),(1724,'410404','石龙区',191,'S',2),(1725,'410411','湛河区',191,'Z',2),(1726,'410421','宝丰县',191,'B',2),(1727,'410422','叶县',191,'Y',2),(1728,'410423','鲁山县',191,'L',2),(1729,'410425','郏县',191,'J',2),(1730,'410481','舞钢市',191,'W',2),(1731,'410482','汝州市',191,'R',2),(1732,'410502','文峰区',192,'W',2),(1733,'410503','北关区',192,'B',2),(1734,'410505','殷都区',192,'Y',2),(1735,'410506','龙安区',192,'L',2),(1736,'410522','安阳县',192,'A',2),(1737,'410523','汤阴县',192,'T',2),(1738,'410526','滑县',192,'H',2),(1739,'410527','内黄县',192,'N',2),(1740,'410581','林州市',192,'L',2),(1741,'410602','鹤山区',193,'H',2),(1742,'410603','山城区',193,'S',2),(1743,'410611','淇滨区',193,'Q',2),(1744,'410621','浚县',193,'J',2),(1745,'410622','淇县',193,'Q',2),(1746,'410702','红旗区',194,'H',2),(1747,'410703','卫滨区',194,'W',2),(1748,'410704','凤泉区',194,'F',2),(1749,'410711','牧野区',194,'M',2),(1750,'410721','新乡县',194,'X',2),(1751,'410724','获嘉县',194,'H',2),(1752,'410725','原阳县',194,'Y',2),(1753,'410726','延津县',194,'Y',2),(1754,'410727','封丘县',194,'F',2),(1755,'410728','长垣县',194,'C',2),(1756,'410781','卫辉市',194,'W',2),(1757,'410782','辉县市',194,'H',2),(1758,'410802','解放区',195,'J',2),(1759,'410803','中站区',195,'Z',2),(1760,'410804','马村区',195,'M',2),(1761,'410811','山阳区',195,'S',2),(1762,'410821','修武县',195,'X',2),(1763,'410822','博爱县',195,'B',2),(1764,'410823','武陟县',195,'W',2),(1765,'410825','温县',195,'W',2),(1766,'410882','沁阳市',195,'Q',2),(1767,'410883','孟州市',195,'M',2),(1768,'410902','华龙区',196,'H',2),(1769,'410922','清丰县',196,'Q',2),(1770,'410923','南乐县',196,'N',2),(1771,'410926','范县',196,'F',2),(1772,'410927','台前县',196,'T',2),(1773,'410928','濮阳县',196,'P',2),(1774,'411002','魏都区',197,'W',2),(1775,'411023','许昌县',197,'X',2),(1776,'411024','鄢陵县',197,'Y',2),(1777,'411025','襄城县',197,'X',2),(1778,'411081','禹州市',197,'Y',2),(1779,'411082','长葛市',197,'C',2),(1781,'411102','源汇区',198,'Y',2),(1782,'411103','郾城区',198,'Y',2),(1783,'411104','召陵区',198,'Z',2),(1784,'411121','舞阳县',198,'W',2),(1785,'411122','临颍县',198,'L',2),(1786,'411202','湖滨区',199,'H',2),(1787,'411221','渑池县',199,'S',2),(1788,'411222','陕县',199,'S',2),(1789,'411224','卢氏县',199,'L',2),(1790,'411281','义马市',199,'Y',2),(1791,'411282','灵宝市',199,'L',2),(1792,'411302','宛城区',200,'W',2),(1793,'411303','卧龙区',200,'W',2),(1794,'411321','南召县',200,'N',2),(1795,'411322','方城县',200,'F',2),(1796,'411323','西峡县',200,'X',2),(1797,'411324','镇平县',200,'Z',2),(1798,'411325','内乡县',200,'N',2),(1799,'411326','淅川县',200,'X',2),(1800,'411327','社旗县',200,'S',2),(1801,'411328','唐河县',200,'T',2),(1802,'411329','新野县',200,'X',2),(1803,'411330','桐柏县',200,'T',2),(1804,'411381','邓州市',200,'D',2),(1805,'411402','梁园区',201,'L',2),(1806,'411403','睢阳区',201,'S',2),(1807,'411421','民权县',201,'M',2),(1808,'411422','睢县',201,'S',2),(1809,'411423','宁陵县',201,'N',2),(1810,'411424','柘城县',201,'Z',2),(1811,'411425','虞城县',201,'Y',2),(1812,'411426','夏邑县',201,'X',2),(1813,'411481','永城市',201,'Y',2),(1814,'411502','浉河区',202,'S',2),(1815,'411503','平桥区',202,'P',2),(1816,'411521','罗山县',202,'L',2),(1817,'411522','光山县',202,'G',2),(1818,'411523','新县',202,'X',2),(1819,'411524','商城县',202,'S',2),(1820,'411525','固始县',202,'G',2),(1821,'411526','潢川县',202,'H',2),(1822,'411527','淮滨县',202,'H',2),(1823,'411528','息县',202,'X',2),(1824,'411602','川汇区',203,'C',2),(1825,'411621','扶沟县',203,'F',2),(1826,'411622','西华县',203,'X',2),(1827,'411623','商水县',203,'S',2),(1828,'411624','沈丘县',203,'S',2),(1829,'411625','郸城县',203,'D',2),(1830,'411626','淮阳县',203,'H',2),(1831,'411627','太康县',203,'T',2),(1832,'411628','鹿邑县',203,'L',2),(1833,'411681','项城市',203,'X',2),(1834,'411702','驿城区',204,'Y',2),(1835,'411721','西平县',204,'X',2),(1836,'411722','上蔡县',204,'S',2),(1837,'411723','平舆县',204,'P',2),(1838,'411724','正阳县',204,'Z',2),(1839,'411725','确山县',204,'Q',2),(1840,'411726','泌阳县',204,'M',2),(1841,'411727','汝南县',204,'R',2),(1842,'411728','遂平县',204,'S',2),(1843,'411729','新蔡县',204,'X',2),(1844,'419001','济源市',205,'J',2),(1845,'420102','江岸区',206,'J',2),(1846,'420103','江汉区',206,'J',2),(1847,'420104','硚口区',206,'Q',2),(1848,'420105','汉阳区',206,'H',2),(1849,'420106','武昌区',206,'W',2),(1850,'420111','洪山区',206,'H',2),(1851,'420112','东西湖区',206,'D',2),(1852,'420113','汉南区',206,'H',2),(1853,'420114','蔡甸区',206,'C',2),(1854,'420115','江夏区',206,'J',2),(1855,'420116','黄陂区',206,'H',2),(1856,'420117','新洲区',206,'X',2),(1857,'420202','黄石港区',207,'H',2),(1858,'420203','西塞山区',207,'X',2),(1859,'420204','下陆区',207,'X',2),(1860,'420205','铁山区',207,'T',2),(1861,'420222','阳新县',207,'Y',2),(1862,'420281','大冶市',207,'D',2),(1863,'420302','茅箭区',208,'M',2),(1864,'420303','张湾区',208,'Z',2),(1865,'420304','郧阳区',208,'Y',2),(1866,'420322','郧西县',208,'Y',2),(1867,'420323','竹山县',208,'Z',2),(1868,'420324','竹溪县',208,'Z',2),(1869,'420325','房县',208,'F',2),(1870,'420381','丹江口市',208,'D',2),(1871,'420502','西陵区',209,'X',2),(1872,'420503','伍家岗区',209,'W',2),(1873,'420504','点军区',209,'D',2),(1874,'420505','猇亭区',209,'X',2),(1875,'420506','夷陵区',209,'Y',2),(1876,'420525','远安县',209,'Y',2),(1877,'420526','兴山县',209,'X',2),(1878,'420527','秭归县',209,'Z',2),(1879,'420528','长阳土家族自治县',209,'C',2),(1880,'420529','五峰土家族自治县',209,'W',2),(1881,'420581','宜都市',209,'Y',2),(1882,'420582','当阳市',209,'D',2),(1883,'420583','枝江市',209,'Z',2),(1884,'420602','襄城区',210,'X',2),(1885,'420606','樊城区',210,'F',2),(1886,'420607','襄州区',210,'X',2),(1887,'420624','南漳县',210,'N',2),(1888,'420625','谷城县',210,'G',2),(1889,'420626','保康县',210,'B',2),(1890,'420682','老河口市',210,'L',2),(1891,'420683','枣阳市',210,'Z',2),(1892,'420684','宜城市',210,'Y',2),(1893,'420702','梁子湖区',211,'L',2),(1894,'420703','华容区',211,'H',2),(1895,'420704','鄂城区',211,'E',2),(1896,'420802','东宝区',212,'D',2),(1897,'420804','掇刀区',212,'D',2),(1898,'420821','京山县',212,'J',2),(1899,'420822','沙洋县',212,'S',2),(1900,'420881','钟祥市',212,'Z',2),(1901,'420902','孝南区',213,'X',2),(1902,'420921','孝昌县',213,'X',2),(1903,'420922','大悟县',213,'D',2),(1904,'420923','云梦县',213,'Y',2),(1905,'420981','应城市',213,'Y',2),(1906,'420982','安陆市',213,'A',2),(1907,'420984','汉川市',213,'H',2),(1908,'421002','沙市区',214,'S',2),(1909,'421003','荆州区',214,'J',2),(1910,'421022','公安县',214,'G',2),(1911,'421023','监利县',214,'J',2),(1912,'421024','江陵县',214,'J',2),(1913,'421081','石首市',214,'S',2),(1914,'421083','洪湖市',214,'H',2),(1915,'421087','松滋市',214,'S',2),(1916,'421102','黄州区',215,'H',2),(1917,'421121','团风县',215,'T',2),(1918,'421122','红安县',215,'H',2),(1919,'421123','罗田县',215,'L',2),(1920,'421124','英山县',215,'Y',2),(1921,'421125','浠水县',215,'X',2),(1922,'421126','蕲春县',215,'Q',2),(1923,'421127','黄梅县',215,'H',2),(1924,'421181','麻城市',215,'M',2),(1925,'421182','武穴市',215,'W',2),(1926,'421202','咸安区',216,'X',2),(1927,'421221','嘉鱼县',216,'J',2),(1928,'421222','通城县',216,'T',2),(1929,'421223','崇阳县',216,'C',2),(1930,'421224','通山县',216,'T',2),(1931,'421281','赤壁市',216,'C',2),(1932,'421303','曾都区',217,'Z',2),(1933,'421321','随县',217,'S',2),(1934,'421381','广水市',217,'G',2),(1935,'422801','恩施市',218,'E',2),(1936,'422802','利川市',218,'L',2),(1937,'422822','建始县',218,'J',2),(1938,'422823','巴东县',218,'B',2),(1939,'422825','宣恩县',218,'X',2),(1940,'422826','咸丰县',218,'X',2),(1941,'422827','来凤县',218,'L',2),(1942,'422828','鹤峰县',218,'H',2),(1943,'429004','仙桃市',219,'X',2),(1944,'429005','潜江市',219,'Q',2),(1945,'429006','天门市',219,'T',2),(1946,'429021','神农架林区',219,'S',2),(1947,'430102','芙蓉区',220,'F',2),(1948,'430103','天心区',220,'T',2),(1949,'430104','岳麓区',220,'Y',2),(1950,'430105','开福区',220,'K',2),(1951,'430111','雨花区',220,'Y',2),(1952,'430112','望城区',220,'W',2),(1953,'430121','长沙县',220,'C',2),(1954,'430124','宁乡县',220,'N',2),(1955,'430181','浏阳市',220,'L',2),(1956,'430202','荷塘区',221,'H',2),(1957,'430203','芦淞区',221,'L',2),(1958,'430204','石峰区',221,'S',2),(1959,'430211','天元区',221,'T',2),(1960,'430221','株洲县',221,'Z',2),(1961,'430223','攸县',221,'Y',2),(1962,'430224','茶陵县',221,'C',2),(1963,'430225','炎陵县',221,'Y',2),(1964,'430281','醴陵市',221,'L',2),(1965,'430302','雨湖区',222,'Y',2),(1966,'430304','岳塘区',222,'Y',2),(1967,'430321','湘潭县',222,'X',2),(1968,'430381','湘乡市',222,'X',2),(1969,'430382','韶山市',222,'S',2),(1970,'430405','珠晖区',223,'Z',2),(1971,'430406','雁峰区',223,'Y',2),(1972,'430407','石鼓区',223,'S',2),(1973,'430408','蒸湘区',223,'Z',2),(1974,'430412','南岳区',223,'N',2),(1975,'430421','衡阳县',223,'H',2),(1976,'430422','衡南县',223,'H',2),(1977,'430423','衡山县',223,'H',2),(1978,'430424','衡东县',223,'H',2),(1979,'430426','祁东县',223,'Q',2),(1980,'430481','耒阳市',223,'L',2),(1981,'430482','常宁市',223,'C',2),(1982,'430502','双清区',224,'S',2),(1983,'430503','大祥区',224,'D',2),(1984,'430511','北塔区',224,'B',2),(1985,'430521','邵东县',224,'S',2),(1986,'430522','新邵县',224,'X',2),(1987,'430523','邵阳县',224,'S',2),(1988,'430524','隆回县',224,'L',2),(1989,'430525','洞口县',224,'D',2),(1990,'430527','绥宁县',224,'S',2),(1991,'430528','新宁县',224,'X',2),(1992,'430529','城步苗族自治县',224,'C',2),(1993,'430581','武冈市',224,'W',2),(1994,'430602','岳阳楼区',225,'Y',2),(1995,'430603','云溪区',225,'Y',2),(1996,'430611','君山区',225,'J',2),(1997,'430621','岳阳县',225,'Y',2),(1998,'430623','华容县',225,'H',2),(1999,'430624','湘阴县',225,'X',2),(2000,'430626','平江县',225,'P',2),(2001,'430681','汨罗市',225,'M',2),(2002,'430682','临湘市',225,'L',2),(2003,'430702','武陵区',226,'W',2),(2004,'430703','鼎城区',226,'D',2),(2005,'430721','安乡县',226,'A',2),(2006,'430722','汉寿县',226,'H',2),(2007,'430723','澧县',226,'L',2),(2008,'430724','临澧县',226,'L',2),(2009,'430725','桃源县',226,'T',2),(2010,'430726','石门县',226,'S',2),(2011,'430781','津市市',226,'J',2),(2012,'430802','永定区',227,'Y',2),(2013,'430811','武陵源区',227,'W',2),(2014,'430821','慈利县',227,'C',2),(2015,'430822','桑植县',227,'S',2),(2016,'430902','资阳区',228,'Z',2),(2017,'430903','赫山区',228,'H',2),(2018,'430921','南县',228,'N',2),(2019,'430922','桃江县',228,'T',2),(2020,'430923','安化县',228,'A',2),(2021,'430981','沅江市',228,'Y',2),(2022,'431002','北湖区',229,'B',2),(2023,'431003','苏仙区',229,'S',2),(2024,'431021','桂阳县',229,'G',2),(2025,'431022','宜章县',229,'Y',2),(2026,'431023','永兴县',229,'Y',2),(2027,'431024','嘉禾县',229,'J',2),(2028,'431025','临武县',229,'L',2),(2029,'431026','汝城县',229,'R',2),(2030,'431027','桂东县',229,'G',2),(2031,'431028','安仁县',229,'A',2),(2032,'431081','资兴市',229,'Z',2),(2033,'431102','零陵区',230,'L',2),(2034,'431103','冷水滩区',230,'L',2),(2035,'431121','祁阳县',230,'Q',2),(2036,'431122','东安县',230,'D',2),(2037,'431123','双牌县',230,'S',2),(2038,'431124','道县',230,'D',2),(2039,'431125','江永县',230,'J',2),(2040,'431126','宁远县',230,'N',2),(2041,'431127','蓝山县',230,'L',2),(2042,'431128','新田县',230,'X',2),(2043,'431129','江华瑶族自治县',230,'J',2),(2044,'431202','鹤城区',231,'H',2),(2045,'431221','中方县',231,'Z',2),(2046,'431222','沅陵县',231,'Y',2),(2047,'431223','辰溪县',231,'C',2),(2048,'431224','溆浦县',231,'X',2),(2049,'431225','会同县',231,'H',2),(2050,'431226','麻阳苗族自治县',231,'M',2),(2051,'431227','新晃侗族自治县',231,'X',2),(2052,'431228','芷江侗族自治县',231,'Z',2),(2053,'431229','靖州苗族侗族自治县',231,'J',2),(2054,'431230','通道侗族自治县',231,'T',2),(2055,'431281','洪江市',231,'H',2),(2056,'431302','娄星区',232,'L',2),(2057,'431321','双峰县',232,'S',2),(2058,'431322','新化县',232,'X',2),(2059,'431381','冷水江市',232,'L',2),(2060,'431382','涟源市',232,'L',2),(2061,'433101','吉首市',233,'J',2),(2062,'433122','泸溪县',233,'L',2),(2063,'433123','凤凰县',233,'F',2),(2064,'433124','花垣县',233,'H',2),(2065,'433125','保靖县',233,'B',2),(2066,'433126','古丈县',233,'G',2),(2067,'433127','永顺县',233,'Y',2),(2068,'433130','龙山县',233,'L',2),(2069,'440103','荔湾区',234,'L',2),(2070,'440104','越秀区',234,'Y',2),(2071,'440105','海珠区',234,'H',2),(2072,'440106','天河区',234,'T',2),(2073,'440111','白云区',234,'B',2),(2074,'440112','黄埔区',234,'H',2),(2075,'440113','番禺区',234,'F',2),(2076,'440114','花都区',234,'H',2),(2077,'440115','南沙区',234,'N',2),(2078,'440116','萝岗区',234,'L',2),(2079,'440117','从化区',234,'C',2),(2080,'440118','增城区',234,'Z',2),(2081,'440203','武江区',235,'W',2),(2082,'440204','浈江区',235,'Z',2),(2083,'440205','曲江区',235,'Q',2),(2084,'440222','始兴县',235,'S',2),(2085,'440224','仁化县',235,'R',2),(2086,'440229','翁源县',235,'W',2),(2087,'440232','乳源瑶族自治县',235,'R',2),(2088,'440233','新丰县',235,'X',2),(2089,'440281','乐昌市',235,'L',2),(2090,'440282','南雄市',235,'N',2),(2091,'440303','罗湖区',236,'L',2),(2092,'440304','福田区',236,'F',2),(2093,'440306','宝安区',236,'B',2),(2094,'440307','龙岗区',236,'L',2),(2095,'440308','盐田区',236,'Y',2),(2096,'440402','香洲区',237,'X',2),(2097,'440403','斗门区',237,'D',2),(2098,'440404','金湾区',237,'J',2),(2099,'440507','龙湖区',238,'L',2),(2100,'440511','金平区',238,'J',2),(2101,'440512','濠江区',238,'H',2),(2102,'440513','潮阳区',238,'C',2),(2103,'440514','潮南区',238,'C',2),(2104,'440515','澄海区',238,'C',2),(2105,'440523','南澳县',238,'N',2),(2106,'440604','禅城区',239,'C',2),(2107,'440605','南海区',239,'N',2),(2108,'440606','顺德区',239,'S',2),(2109,'440607','三水区',239,'S',2),(2110,'440608','高明区',239,'G',2),(2111,'440703','蓬江区',240,'P',2),(2112,'440704','江海区',240,'J',2),(2113,'440705','新会区',240,'X',2),(2114,'440781','台山市',240,'T',2),(2115,'440783','开平市',240,'K',2),(2116,'440784','鹤山市',240,'H',2),(2117,'440785','恩平市',240,'E',2),(2118,'440802','赤坎区',241,'C',2),(2119,'440803','霞山区',241,'X',2),(2120,'440804','坡头区',241,'P',2),(2121,'440811','麻章区',241,'M',2),(2122,'440823','遂溪县',241,'S',2),(2123,'440825','徐闻县',241,'X',2),(2124,'440881','廉江市',241,'L',2),(2125,'440882','雷州市',241,'L',2),(2126,'440883','吴川市',241,'W',2),(2127,'440902','茂南区',242,'M',2),(2128,'440904','电白区',242,'D',2),(2129,'440981','高州市',242,'G',2),(2130,'440982','化州市',242,'H',2),(2131,'440983','信宜市',242,'X',2),(2132,'441202','端州区',243,'D',2),(2133,'441203','鼎湖区',243,'D',2),(2134,'441223','广宁县',243,'G',2),(2135,'441224','怀集县',243,'H',2),(2136,'441225','封开县',243,'F',2),(2137,'441226','德庆县',243,'D',2),(2138,'441283','高要市',243,'G',2),(2139,'441284','四会市',243,'S',2),(2140,'441302','惠城区',244,'H',2),(2141,'441303','惠阳区',244,'H',2),(2142,'441322','博罗县',244,'B',2),(2143,'441323','惠东县',244,'H',2),(2144,'441324','龙门县',244,'L',2),(2145,'441402','梅江区',245,'M',2),(2146,'441403','梅县区',245,'M',2),(2147,'441422','大埔县',245,'D',2),(2148,'441423','丰顺县',245,'F',2),(2149,'441424','五华县',245,'W',2),(2150,'441426','平远县',245,'P',2),(2151,'441427','蕉岭县',245,'J',2),(2152,'441481','兴宁市',245,'X',2),(2153,'441521','海丰县',246,'H',2),(2154,'441523','陆河县',246,'L',2),(2155,'441581','陆丰市',246,'L',2),(2156,'441602','源城区',247,'Y',2),(2157,'441621','紫金县',247,'Z',2),(2158,'441622','龙川县',247,'L',2),(2159,'441623','连平县',247,'L',2),(2160,'441624','和平县',247,'H',2),(2161,'441625','东源县',247,'D',2),(2162,'441702','江城区',248,'J',2),(2163,'441721','阳西县',248,'Y',2),(2164,'441723','阳东县',248,'Y',2),(2165,'441781','阳春市',248,'Y',2),(2166,'441802','清城区',249,'Q',2),(2167,'441803','清新区',249,'Q',2),(2168,'441821','佛冈县',249,'F',2),(2169,'441823','阳山县',249,'Y',2),(2170,'441825','连山壮族瑶族自治县',249,'L',2),(2171,'441826','连南瑶族自治县',249,'L',2),(2172,'441881','英德市',249,'Y',2),(2173,'441882','连州市',249,'L',2),(2174,'445102','湘桥区',252,'X',2),(2175,'445103','潮安区',252,'C',2),(2176,'445122','饶平县',252,'R',2),(2177,'445202','榕城区',253,'R',2),(2178,'445203','揭东区',253,'J',2),(2179,'445222','揭西县',253,'J',2),(2180,'445224','惠来县',253,'H',2),(2181,'445281','普宁市',253,'P',2),(2182,'445302','云城区',254,'Y',2),(2183,'445303','云安区',254,'Y',2),(2184,'445321','新兴县',254,'X',2),(2185,'445322','郁南县',254,'Y',2),(2186,'445381','罗定市',254,'L',2),(2187,'450102','兴宁区',255,'X',2),(2188,'450103','青秀区',255,'Q',2),(2189,'450105','江南区',255,'J',2),(2190,'450107','西乡塘区',255,'X',2),(2191,'450108','良庆区',255,'L',2),(2192,'450109','邕宁区',255,'Y',2),(2193,'450122','武鸣县',255,'W',2),(2194,'450123','隆安县',255,'L',2),(2195,'450124','马山县',255,'M',2),(2196,'450125','上林县',255,'S',2),(2197,'450126','宾阳县',255,'B',2),(2198,'450127','横县',255,'H',2),(2199,'450202','城中区',256,'C',2),(2200,'450203','鱼峰区',256,'Y',2),(2201,'450204','柳南区',256,'L',2),(2202,'450205','柳北区',256,'L',2),(2203,'450221','柳江县',256,'L',2),(2204,'450222','柳城县',256,'L',2),(2205,'450223','鹿寨县',256,'L',2),(2206,'450224','融安县',256,'R',2),(2207,'450225','融水苗族自治县',256,'R',2),(2208,'450226','三江侗族自治县',256,'S',2),(2209,'450302','秀峰区',257,'X',2),(2210,'450303','叠彩区',257,'D',2),(2211,'450304','象山区',257,'X',2),(2212,'450305','七星区',257,'Q',2),(2213,'450311','雁山区',257,'Y',2),(2214,'450312','临桂区',257,'L',2),(2215,'450321','阳朔县',257,'Y',2),(2216,'450323','灵川县',257,'L',2),(2217,'450324','全州县',257,'Q',2),(2218,'450325','兴安县',257,'X',2),(2219,'450326','永福县',257,'Y',2),(2220,'450327','灌阳县',257,'G',2),(2221,'450328','龙胜各族自治县',257,'L',2),(2222,'450329','资源县',257,'Z',2),(2223,'450330','平乐县',257,'P',2),(2224,'450331','荔浦县',257,'L',2),(2225,'450332','恭城瑶族自治县',257,'G',2),(2226,'450403','万秀区',258,'W',2),(2227,'450405','长洲区',258,'C',2),(2228,'450406','龙圩区',258,'L',2),(2229,'450421','苍梧县',258,'C',2),(2230,'450422','藤县',258,'T',2),(2231,'450423','蒙山县',258,'M',2),(2232,'450481','岑溪市',258,'C',2),(2233,'450502','海城区',259,'H',2),(2234,'450503','银海区',259,'Y',2),(2235,'450512','铁山港区',259,'T',2),(2236,'450521','合浦县',259,'H',2),(2237,'450602','港口区',260,'G',2),(2238,'450603','防城区',260,'F',2),(2239,'450621','上思县',260,'S',2),(2240,'450681','东兴市',260,'D',2),(2241,'450702','钦南区',261,'Q',2),(2242,'450703','钦北区',261,'Q',2),(2243,'450721','灵山县',261,'L',2),(2244,'450722','浦北县',261,'P',2),(2245,'450802','港北区',262,'G',2),(2246,'450803','港南区',262,'G',2),(2247,'450804','覃塘区',262,'T',2),(2248,'450821','平南县',262,'P',2),(2249,'450881','桂平市',262,'G',2),(2250,'450902','玉州区',263,'Y',2),(2251,'450903','福绵区',263,'F',2),(2252,'450921','容县',263,'R',2),(2253,'450922','陆川县',263,'L',2),(2254,'450923','博白县',263,'B',2),(2255,'450924','兴业县',263,'X',2),(2256,'450981','北流市',263,'B',2),(2257,'451002','右江区',264,'Y',2),(2258,'451021','田阳县',264,'T',2),(2259,'451022','田东县',264,'T',2),(2260,'451023','平果县',264,'P',2),(2261,'451024','德保县',264,'D',2),(2262,'451025','靖西县',264,'J',2),(2263,'451026','那坡县',264,'N',2),(2264,'451027','凌云县',264,'L',2),(2265,'451028','乐业县',264,'L',2),(2266,'451029','田林县',264,'T',2),(2267,'451030','西林县',264,'X',2),(2268,'451031','隆林各族自治县',264,'L',2),(2269,'451102','八步区',265,'B',2),(2270,'451121','昭平县',265,'Z',2),(2271,'451122','钟山县',265,'Z',2),(2272,'451123','富川瑶族自治县',265,'F',2),(2273,'451202','金城江区',266,'J',2),(2274,'451221','南丹县',266,'N',2),(2275,'451222','天峨县',266,'T',2),(2276,'451223','凤山县',266,'F',2),(2277,'451224','东兰县',266,'D',2),(2278,'451225','罗城仫佬族自治县',266,'L',2),(2279,'451226','环江毛南族自治县',266,'H',2),(2280,'451227','巴马瑶族自治县',266,'B',2),(2281,'451228','都安瑶族自治县',266,'D',2),(2282,'451229','大化瑶族自治县',266,'D',2),(2283,'451281','宜州市',266,'Y',2),(2284,'451302','兴宾区',267,'X',2),(2285,'451321','忻城县',267,'X',2),(2286,'451322','象州县',267,'X',2),(2287,'451323','武宣县',267,'W',2),(2288,'451324','金秀瑶族自治县',267,'J',2),(2289,'451381','合山市',267,'H',2),(2290,'451402','江州区',268,'J',2),(2291,'451421','扶绥县',268,'F',2),(2292,'451422','宁明县',268,'N',2),(2293,'451423','龙州县',268,'L',2),(2294,'451424','大新县',268,'D',2),(2295,'451425','天等县',268,'T',2),(2296,'451481','凭祥市',268,'P',2),(2297,'460105','秀英区',269,'X',2),(2298,'460106','龙华区',269,'L',2),(2299,'460107','琼山区',269,'Q',2),(2300,'460108','美兰区',269,'M',2),(2301,'460202','海棠区',270,'H',2),(2302,'460203','吉阳区',270,'J',2),(2303,'460204','天涯区',270,'T',2),(2304,'460205','崖州区',270,'Y',2),(2305,'469001','五指山市',272,'W',2),(2306,'469002','琼海市',272,'Q',2),(2307,'469003','儋州市',272,'D',2),(2308,'469005','文昌市',272,'W',2),(2309,'469006','万宁市',272,'W',2),(2310,'469007','东方市',272,'D',2),(2311,'469021','定安县',272,'D',2),(2312,'469022','屯昌县',272,'T',2),(2313,'469023','澄迈县',272,'C',2),(2314,'469024','临高县',272,'L',2),(2315,'469025','白沙黎族自治县',272,'B',2),(2316,'469026','昌江黎族自治县',272,'C',2),(2317,'469027','乐东黎族自治县',272,'L',2),(2318,'469028','陵水黎族自治县',272,'L',2),(2319,'469029','保亭黎族苗族自治县',272,'B',2),(2320,'469030','琼中黎族苗族自治县',272,'Q',2),(2321,'500101','万州区',273,'W',2),(2322,'500102','涪陵区',273,'F',2),(2323,'500103','渝中区',273,'Y',2),(2324,'500104','大渡口区',273,'D',2),(2325,'500106','沙坪坝区',273,'S',2),(2326,'500107','九龙坡区',273,'J',2),(2327,'500108','南岸区',273,'N',2),(2328,'500109','北碚区',273,'B',2),(2329,'500110','綦江区',273,'Q',2),(2330,'500111','大足区',273,'D',2),(2331,'500112','渝北区',273,'Y',2),(2332,'500113','巴南区',273,'B',2),(2333,'500114','黔江区',273,'Q',2),(2334,'500115','长寿区',273,'C',2),(2335,'500116','江津区',273,'J',2),(2336,'500117','合川区',273,'H',2),(2337,'500118','永川区',273,'Y',2),(2338,'500119','南川区',273,'N',2),(2339,'500120','璧山区',273,'B',2),(2340,'500151','铜梁区',273,'T',2),(2341,'500223','潼南县',274,'T',2),(2342,'500226','荣昌县',274,'R',2),(2343,'500228','梁平县',274,'L',2),(2344,'500229','城口县',274,'C',2),(2345,'500230','丰都县',274,'F',2),(2346,'500231','垫江县',274,'D',2),(2347,'500232','武隆县',274,'W',2),(2348,'500233','忠县',274,'Z',2),(2349,'500234','开县',274,'K',2),(2350,'500235','云阳县',274,'Y',2),(2351,'500236','奉节县',274,'F',2),(2352,'500237','巫山县',274,'W',2),(2353,'500238','巫溪县',274,'W',2),(2354,'500240','石柱土家族自治县',274,'S',2),(2355,'500241','秀山土家族苗族自治县',274,'X',2),(2356,'500242','酉阳土家族苗族自治县',274,'Y',2),(2357,'500243','彭水苗族土家族自治县',274,'P',2),(2358,'510104','锦江区',275,'J',2),(2359,'510105','青羊区',275,'Q',2),(2360,'510106','金牛区',275,'J',2),(2361,'510107','武侯区',275,'W',2),(2362,'510108','成华区',275,'C',2),(2363,'510112','龙泉驿区',275,'L',2),(2364,'510113','青白江区',275,'Q',2),(2365,'510114','新都区',275,'X',2),(2366,'510115','温江区',275,'W',2),(2367,'510121','金堂县',275,'J',2),(2368,'510122','双流县',275,'S',2),(2369,'510124','郫县',275,'P',2),(2370,'510129','大邑县',275,'D',2),(2371,'510131','蒲江县',275,'P',2),(2372,'510132','新津县',275,'X',2),(2373,'510181','都江堰市',275,'D',2),(2374,'510182','彭州市',275,'P',2),(2375,'510183','邛崃市',275,'Q',2),(2376,'510184','崇州市',275,'C',2),(2377,'510302','自流井区',276,'Z',2),(2378,'510303','贡井区',276,'G',2),(2379,'510304','大安区',276,'D',2),(2380,'510311','沿滩区',276,'Y',2),(2381,'510321','荣县',276,'R',2),(2382,'510322','富顺县',276,'F',2),(2383,'510402','东区',277,'D',2),(2384,'510403','西区',277,'X',2),(2385,'510411','仁和区',277,'R',2),(2386,'510421','米易县',277,'M',2),(2387,'510422','盐边县',277,'Y',2),(2388,'510502','江阳区',278,'J',2),(2389,'510503','纳溪区',278,'N',2),(2390,'510504','龙马潭区',278,'L',2),(2391,'510521','泸县',278,'L',2),(2392,'510522','合江县',278,'H',2),(2393,'510524','叙永县',278,'X',2),(2394,'510525','古蔺县',278,'G',2),(2395,'510603','旌阳区',279,'J',2),(2396,'510623','中江县',279,'Z',2),(2397,'510626','罗江县',279,'L',2),(2398,'510681','广汉市',279,'G',2),(2399,'510682','什邡市',279,'S',2),(2400,'510683','绵竹市',279,'M',2),(2401,'510703','涪城区',280,'F',2),(2402,'510704','游仙区',280,'Y',2),(2403,'510722','三台县',280,'S',2),(2404,'510723','盐亭县',280,'Y',2),(2405,'510724','安县',280,'A',2),(2406,'510725','梓潼县',280,'Z',2),(2407,'510726','北川羌族自治县',280,'B',2),(2408,'510727','平武县',280,'P',2),(2409,'510781','江油市',280,'J',2),(2410,'510802','利州区',281,'L',2),(2411,'510811','昭化区',281,'Z',2),(2412,'510812','朝天区',281,'C',2),(2413,'510821','旺苍县',281,'W',2),(2414,'510822','青川县',281,'Q',2),(2415,'510823','剑阁县',281,'J',2),(2416,'510824','苍溪县',281,'C',2),(2417,'510903','船山区',282,'C',2),(2418,'510904','安居区',282,'A',2),(2419,'510921','蓬溪县',282,'P',2),(2420,'510922','射洪县',282,'S',2),(2421,'510923','大英县',282,'D',2),(2422,'511011','东兴区',283,'D',2),(2423,'511024','威远县',283,'W',2),(2424,'511025','资中县',283,'Z',2),(2425,'511028','隆昌县',283,'L',2),(2427,'511102','市中区',284,'S',2),(2428,'511111','沙湾区',284,'S',2),(2429,'511112','五通桥区',284,'W',2),(2430,'511113','金口河区',284,'J',2),(2431,'511123','犍为县',284,'J',2),(2432,'511124','井研县',284,'J',2),(2433,'511126','夹江县',284,'J',2),(2434,'511129','沐川县',284,'M',2),(2435,'511132','峨边彝族自治县',284,'E',2),(2436,'511133','马边彝族自治县',284,'M',2),(2437,'511181','峨眉山市',284,'E',2),(2438,'511302','顺庆区',285,'S',2),(2439,'511303','高坪区',285,'G',2),(2440,'511304','嘉陵区',285,'J',2),(2441,'511321','南部县',285,'N',2),(2442,'511322','营山县',285,'Y',2),(2443,'511323','蓬安县',285,'P',2),(2444,'511324','仪陇县',285,'Y',2),(2445,'511325','西充县',285,'X',2),(2446,'511381','阆中市',285,'L',2),(2447,'511402','东坡区',286,'D',2),(2448,'511421','仁寿县',286,'R',2),(2449,'511422','彭山县',286,'P',2),(2450,'511423','洪雅县',286,'H',2),(2451,'511424','丹棱县',286,'D',2),(2452,'511425','青神县',286,'Q',2),(2453,'511502','翠屏区',287,'C',2),(2454,'511503','南溪区',287,'N',2),(2455,'511521','宜宾县',287,'Y',2),(2456,'511523','江安县',287,'J',2),(2457,'511524','长宁县',287,'C',2),(2458,'511525','高县',287,'G',2),(2459,'511526','珙县',287,'G',2),(2460,'511527','筠连县',287,'J',2),(2461,'511528','兴文县',287,'X',2),(2462,'511529','屏山县',287,'P',2);
/*!40000 ALTER TABLE `ddwx_area` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-09 16:01:40

-- MySQL dump 10.13  Distrib 8.0.45, for Linux (aarch64)
--
-- Host: localhost    Database: tp_delivery_adminphp_core_seed_20260605_0928
-- ------------------------------------------------------
-- Server version	8.0.45

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `ddwx_designer_splash`
--
-- WHERE:  aid = 1 AND bid = 0

LOCK TABLES `ddwx_designer_splash` WRITE;
/*!40000 ALTER TABLE `ddwx_designer_splash` DISABLE KEYS */;
INSERT INTO `ddwx_designer_splash` (`id`, `aid`, `bid`, `platform`, `is_enabled`, `display_type`, `display_time`, `skip_enabled`, `skip_time`, `image_url`, `video_url`, `link_url`, `link_name`, `createtime`, `updatetime`) VALUES (1,1,0,'mp',0,'image',3,1,2,'','','','',1777443506,1777443506);
/*!40000 ALTER TABLE `ddwx_designer_splash` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-09 16:01:42

-- MySQL dump 10.13  Distrib 8.0.45, for Linux (aarch64)
--
-- Host: localhost    Database: tp_delivery_adminphp_core_seed_20260605_0928
-- ------------------------------------------------------
-- Server version	8.0.45

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `ddwx_designer_shopdetail`
--
-- WHERE:  aid = 1 AND bid = 0

LOCK TABLES `ddwx_designer_shopdetail` WRITE;
/*!40000 ALTER TABLE `ddwx_designer_shopdetail` DISABLE KEYS */;
INSERT INTO `ddwx_designer_shopdetail` (`id`, `aid`, `bid`, `indexurl`, `menucount`, `menudata`, `navigationBarBackgroundColor`, `navigationBarTextStyle`, `updatetime`, `platform`, `tongbu`) VALUES (1,1,0,'/pages/index/index',3,'{\"detailStyle\":\"default\",\"color\":\"#BBBBBB\",\"selectedColor\":\"#FD4A46\",\"backgroundColor\":\"#ffffff\",\"borderStyle\":\"black\",\"position\":\"bottom\",\"list\":[{\"text\":\"客服\",\"pagePath\":\"\",\"iconPath\":\"https:\\/\\/127.0.0.1:8089\\/static\\/img\\/tabbar\\/kefu.png\",\"selectedIconPath\":\"https:\\/\\/127.0.0.1:8089\\/static\\/img\\/tabbar\\/kefu.png\",\"pagePathname\":\"功能>客服\",\"isShow\":1,\"menuType\":1,\"useSystem\":1},{\"text\":\"购物车\",\"pagePath\":\"\\/pages\\/shop\\/cart\",\"iconPath\":\"https:\\/\\/127.0.0.1:8089\\/static\\/img\\/tabbar\\/gwc.png\",\"selectedIconPath\":\"https:\\/\\/127.0.0.1:8089\\/static\\/img\\/tabbar\\/gwc.png\",\"pagePathname\":\"基础功能>购物车\",\"isShow\":1,\"menuType\":2,\"useSystem\":0},{\"text\":\"收藏\",\"pagePath\":\"addfavorite::\",\"iconPath\":\"https:\\/\\/127.0.0.1:8089\\/static\\/img\\/tabbar\\/shoucang.png\",\"selectedIconPath\":\"https:\\/\\/127.0.0.1:8089\\/static\\/img\\/tabbar\\/shoucangselected.png\",\"pagePathname\":\"基础功能>收藏\",\"isShow\":1,\"menuType\":3,\"useSystem\":0,\"selectedtext\":\"已收藏\"}]}','#333333','white',NULL,'all',1);
/*!40000 ALTER TABLE `ddwx_designer_shopdetail` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-09 16:01:43

INSERT INTO `ddwx_sysset` (`id`, `name`, `value`) VALUES (1,'webinfo','{"webname":"Tiantianshan Shop","logo":"","ico":"","adminloginbg":"","copyright":"","aliyun_express_host":"","aliyun_express_path":"","aliyun_express_appcode":"","beian":"","copyright2":"","beian2":"","showweb":"0","company":"","address":"","email":"","tel":"","weixin":"","fenhong_optimization":"0","reg_open":"0","reg_user_agreement_url":"","reg_user_time":"","sms_open":"0","jiesuan_fenhong_type":"0"}'),(2,'remote','{"type":"0","alioss":{"key":"","secret":"","bucket":"","ossurl":"","url":""},"qiniu":{"accesskey":"","secretkey":"","bucket":"","url":""},"cos":{"appid":"","secretid":"","secretkey":"","bucket":"","local":"","url":""},"thumb":"0","thumb_width":"","thumb_height":""}'),(3,'component','[]'),(4,'newapi','{"enabled":0,"base_url":"","api_key":"","model":"","temperature":0.7,"max_tokens":0,"timeout":30}');
-- Safe default admin user group seed. Avoid copying plugin permissions from source database.
DELETE FROM `ddwx_admin_user_group`;
INSERT INTO `ddwx_admin_user_group` (`id`, `aid`, `bid`, `sort`, `name`, `status`, `createtime`, `remark`, `auth_data`, `wxauth_data`, `notice_auth_data`, `hexiao_auth_data`, `mdid`, `showtj`) VALUES
(1, 1, 0, 0, '超级管理员', 1, UNIX_TIMESTAMP(), '基础包默认角色', '[]', '[]', '[]', '[]', 0, 1);
-- Ensure core design menu entries exist in database-menu mode.
SET @packager_design_menu_pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='jiemian' OR `name`='设计' ORDER BY `id` ASC LIMIT 1);
SET @packager_design_menu_now := UNIX_TIMESTAMP();

INSERT INTO `ddwx_admin_menu` (`pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`)
SELECT @packager_design_menu_pid, 'designercategory_index', '页面分类', '页面分类', 'DesignerCategory/index', '', 'DesignerCategory/*', 0, 2, 3, 1, 0, 0, 0, 1, 1, 0, '', '', '', @packager_design_menu_now, @packager_design_menu_now
FROM DUAL
WHERE @packager_design_menu_pid IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='designercategory_index' OR `path`='DesignerCategory/index');

INSERT INTO `ddwx_admin_menu` (`pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`)
SELECT @packager_design_menu_pid, 'designerpagepopup_index', '页面弹窗', '页面弹窗', 'DesignerPagePopup/index', '', 'DesignerPagePopup/*', 0, 2, 6, 1, 0, 0, 0, 1, 1, 0, '', '', '', @packager_design_menu_now, @packager_design_menu_now
FROM DUAL
WHERE @packager_design_menu_pid IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='designerpagepopup_index' OR `path`='DesignerPagePopup/index');

INSERT INTO `ddwx_admin_menu` (`pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`)
SELECT @packager_design_menu_pid, 'designerlogin_index', '登录页面', '登录页面', 'DesignerLogin/index', '', 'DesignerLogin/*', 0, 2, 10, 1, 0, 0, 0, 1, 1, 0, '', '', '', @packager_design_menu_now, @packager_design_menu_now
FROM DUAL
WHERE @packager_design_menu_pid IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='designerlogin_index' OR `path`='DesignerLogin/index');

INSERT INTO `ddwx_admin_menu` (`pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`)
SELECT @packager_design_menu_pid, 'designermobile_index', '移动端后台', '移动端后台', 'DesignerMobile/index', '', 'DesignerMobile/*', 0, 2, 11, 1, 0, 0, 0, 1, 1, 0, '', '', '', @packager_design_menu_now, @packager_design_menu_now
FROM DUAL
WHERE @packager_design_menu_pid IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='designermobile_index' OR `path`='DesignerMobile/index');

INSERT INTO `ddwx_admin_menu` (`pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`)
SELECT @packager_design_menu_pid, 'loadingeffect_index', '加载动效', '加载动效', 'LoadingEffect/index', '', 'LoadingEffect/*', 0, 2, 13, 1, 0, 0, 0, 1, 1, 0, '', '', '', @packager_design_menu_now, @packager_design_menu_now
FROM DUAL
WHERE @packager_design_menu_pid IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='loadingeffect_index' OR `path`='LoadingEffect/index');

INSERT INTO `ddwx_admin_menu` (`pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`)
SELECT @packager_design_menu_pid, 'distributionsettings_index', '分销设置', '分销设置', 'DistributionSettings/index', '', 'DistributionSettings/*', 0, 2, 15, 1, 0, 0, 0, 1, 1, 0, '', '', '', @packager_design_menu_now, @packager_design_menu_now
FROM DUAL
WHERE @packager_design_menu_pid IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='distributionsettings_index' OR `path`='DistributionSettings/index');

INSERT INTO `ddwx_admin_menu` (`pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`)
SELECT @packager_design_menu_pid, 'designervirtualuser_index', '氛围用户池', '氛围用户池', 'DesignerVirtualUser/index', '', 'DesignerVirtualUser/*', 0, 2, 16, 1, 0, 0, 0, 1, 1, 0, '', '', '', @packager_design_menu_now, @packager_design_menu_now
FROM DUAL
WHERE @packager_design_menu_pid IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='designervirtualuser_index' OR `path`='DesignerVirtualUser/index');

UPDATE `ddwx_admin_menu` SET `pid`=@packager_design_menu_pid, `sort`=1, `status`=1, `is_hidden`=0 WHERE @packager_design_menu_pid IS NOT NULL AND (`key`='designerpage_index' OR `path`='DesignerPage/index');
UPDATE `ddwx_admin_menu` SET `pid`=@packager_design_menu_pid, `sort`=2, `status`=1, `is_hidden`=0 WHERE @packager_design_menu_pid IS NOT NULL AND (`key`='designerpage_member' OR `path`='DesignerPage/member');
UPDATE `ddwx_admin_menu` SET `pid`=@packager_design_menu_pid, `sort`=3, `status`=1, `is_hidden`=0 WHERE @packager_design_menu_pid IS NOT NULL AND (`key`='designercategory_index' OR `path`='DesignerCategory/index');
UPDATE `ddwx_admin_menu` SET `pid`=@packager_design_menu_pid, `sort`=4, `status`=1, `is_hidden`=0 WHERE @packager_design_menu_pid IS NOT NULL AND (`key`='designermenu_index' OR `path`='DesignerMenu/index');
UPDATE `ddwx_admin_menu` SET `pid`=@packager_design_menu_pid, `sort`=5, `status`=1, `is_hidden`=0 WHERE @packager_design_menu_pid IS NOT NULL AND (`key`='designermenu_inner' OR `path`='DesignerMenu/inner');
UPDATE `ddwx_admin_menu` SET `pid`=@packager_design_menu_pid, `sort`=6, `status`=1, `is_hidden`=0 WHERE @packager_design_menu_pid IS NOT NULL AND (`key`='designerpagepopup_index' OR `path`='DesignerPagePopup/index');
UPDATE `ddwx_admin_menu` SET `pid`=@packager_design_menu_pid, `sort`=7, `status`=1, `is_hidden`=0 WHERE @packager_design_menu_pid IS NOT NULL AND (`key`='designermenu_splash' OR `path`='DesignerMenu/splash');
UPDATE `ddwx_admin_menu` SET `pid`=@packager_design_menu_pid, `sort`=8, `status`=1, `is_hidden`=1 WHERE @packager_design_menu_pid IS NOT NULL AND (`key`='designermenu_splashmanage' OR `path`='DesignerMenu/splashmanage');
UPDATE `ddwx_admin_menu` SET `pid`=@packager_design_menu_pid, `sort`=9, `status`=1, `is_hidden`=0 WHERE @packager_design_menu_pid IS NOT NULL AND (`key`='designermenushopdetail_shopdetail' OR `path`='DesignerMenuShopdetail/shopdetail');
UPDATE `ddwx_admin_menu` SET `pid`=@packager_design_menu_pid, `sort`=10, `status`=1, `is_hidden`=0 WHERE @packager_design_menu_pid IS NOT NULL AND (`key`='designerlogin_index' OR `path`='DesignerLogin/index');
UPDATE `ddwx_admin_menu` SET `pid`=@packager_design_menu_pid, `sort`=11, `status`=1, `is_hidden`=0 WHERE @packager_design_menu_pid IS NOT NULL AND (`key`='designermobile_index' OR `path`='DesignerMobile/index');
UPDATE `ddwx_admin_menu` SET `pid`=@packager_design_menu_pid, `sort`=12, `status`=1, `is_hidden`=0 WHERE @packager_design_menu_pid IS NOT NULL AND (`key`='designershare_index' OR `path`='DesignerShare/index');
UPDATE `ddwx_admin_menu` SET `pid`=@packager_design_menu_pid, `sort`=13, `status`=1, `is_hidden`=0 WHERE @packager_design_menu_pid IS NOT NULL AND (`key`='loadingeffect_index' OR `path`='LoadingEffect/index');
UPDATE `ddwx_admin_menu` SET `pid`=@packager_design_menu_pid, `sort`=14, `status`=1, `is_hidden`=0 WHERE @packager_design_menu_pid IS NOT NULL AND (`key`='designerpage_chooseurl' OR `path`='DesignerPage/chooseurl');
UPDATE `ddwx_admin_menu` SET `pid`=@packager_design_menu_pid, `sort`=15, `status`=1, `is_hidden`=0 WHERE @packager_design_menu_pid IS NOT NULL AND (`key`='distributionsettings_index' OR `path`='DistributionSettings/index');
UPDATE `ddwx_admin_menu` SET `pid`=@packager_design_menu_pid, `sort`=16, `status`=1, `is_hidden`=0 WHERE @packager_design_menu_pid IS NOT NULL AND (`key`='designervirtualuser_index' OR `path`='DesignerVirtualUser/index');
UPDATE `ddwx_admin_menu` SET `status`=0, `is_hidden`=1 WHERE `key`='designermenu_menu2' OR `path`='DesignerMenu/menu2';
-- Ensure core finance asset menu entries exist in database-menu mode.
SET @packager_finance_menu_now := UNIX_TIMESTAMP();
INSERT INTO `ddwx_admin_menu` (`pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`)
SELECT 0, 'finance', '财务', '财务', '', 'my-icon my-icon-finance', '', 0, 1, 3, 1, 0, 0, 0, 1, 1, 0, '', '', '', @packager_finance_menu_now, @packager_finance_menu_now
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='finance' OR `name`='财务');
SET @packager_finance_menu_pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='finance' OR `name`='财务' ORDER BY `id` ASC LIMIT 1);
INSERT INTO `ddwx_admin_menu` (`pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`)
SELECT @packager_finance_menu_pid, 'scorehuang_scorelog', '黄积分明细', '黄积分明细', 'ScoreHuang/scorelog', '', 'ScoreHuang/scorelog,ScoreHuang/scorelogexcel,ScoreHuang/scorelogdel,ScorehuangLog/*', 0, 2, 17, 1, 0, 0, 0, 1, 1, 0, '', '', '', @packager_finance_menu_now, @packager_finance_menu_now
FROM DUAL
WHERE @packager_finance_menu_pid IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='scorehuang_scorelog' OR `path`='ScoreHuang/scorelog');
UPDATE `ddwx_admin_menu` SET `pid`=@packager_finance_menu_pid, `name`='黄积分明细', `fullname`='黄积分明细', `sort`=17, `status`=1, `is_hidden`=0, `auth_data`='ScoreHuang/scorelog,ScoreHuang/scorelogexcel,ScoreHuang/scorelogdel,ScorehuangLog/*' WHERE @packager_finance_menu_pid IS NOT NULL AND (`key`='scorehuang_scorelog' OR `path`='ScoreHuang/scorelog');
INSERT INTO `ddwx_admin_menu` (`pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`)
SELECT @packager_finance_menu_pid, 'xiaofeiblog_index', '消费币明细', '消费币明细', 'XiaofeibLog/index', '', 'XiaofeibLog/getList,XiaofeibLog/export,XiaofeibLog/delete', 0, 2, 18, 1, 0, 0, 0, 1, 1, 0, '', '', '', @packager_finance_menu_now, @packager_finance_menu_now
FROM DUAL
WHERE @packager_finance_menu_pid IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='xiaofeiblog_index' OR `path`='XiaofeibLog/index');
UPDATE `ddwx_admin_menu` SET `pid`=@packager_finance_menu_pid, `name`='消费币明细', `fullname`='消费币明细', `sort`=18, `status`=1, `is_hidden`=0, `auth_data`='XiaofeibLog/getList,XiaofeibLog/export,XiaofeibLog/delete' WHERE @packager_finance_menu_pid IS NOT NULL AND (`key`='xiaofeiblog_index' OR `path`='XiaofeibLog/index');
INSERT INTO `ddwx_admin_menu` (`pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`)
SELECT @packager_finance_menu_pid, 'membertransfer_index', '会员互转明细', '会员互转明细', 'Money/membertransfer', '', 'MemberTransferLog/getList', 0, 2, 19, 1, 0, 0, 0, 1, 1, 0, '', '', '', @packager_finance_menu_now, @packager_finance_menu_now
FROM DUAL
WHERE @packager_finance_menu_pid IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='membertransfer_index' OR `path`='Money/membertransfer');
UPDATE `ddwx_admin_menu` SET `pid`=@packager_finance_menu_pid, `name`='会员互转明细', `fullname`='会员互转明细', `sort`=19, `status`=1, `is_hidden`=0, `auth_data`='MemberTransferLog/getList' WHERE @packager_finance_menu_pid IS NOT NULL AND (`key`='membertransfer_index' OR `path`='Money/membertransfer');
-- Ensure core business management menu entries exist in database-menu mode.
SET @packager_business_menu_now := UNIX_TIMESTAMP();
INSERT INTO `ddwx_admin_menu` (`pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`)
SELECT 0, 'business', '多商户', '多商户管理', '', 'my-icon my-icon-business', '', 0, 1, 5, 1, 0, 0, 0, 0, 1, 0, '', '', '', @packager_business_menu_now, @packager_business_menu_now
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='business' OR `name` IN ('商户','多商户'));
SET @packager_business_menu_pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='business' OR `name` IN ('商户','多商户') ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `name`='多商户', `fullname`='多商户管理', `sort`=5, `status`=1, `is_hidden`=0, `merchant_visible`=0 WHERE @packager_business_menu_pid IS NOT NULL AND `id`=@packager_business_menu_pid;

INSERT INTO `ddwx_admin_menu` (`pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`)
SELECT @packager_business_menu_pid, 'business_index', '商户管理', '商户管理', 'Business/index', '', 'Business/*,BusinessFreight/*', 0, 2, 1, 1, 0, 0, 0, 0, 1, 0, '', '', '', @packager_business_menu_now, @packager_business_menu_now
FROM DUAL
WHERE @packager_business_menu_pid IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='business_index' OR `path`='Business/index');

INSERT INTO `ddwx_admin_menu` (`pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`)
SELECT @packager_business_menu_pid, 'business_category', '商户分类', '商户分类', 'Business/category', '', 'Business/*', 0, 2, 2, 1, 0, 0, 0, 0, 1, 0, '', '', '', @packager_business_menu_now, @packager_business_menu_now
FROM DUAL
WHERE @packager_business_menu_pid IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='business_category' OR `path`='Business/category');

INSERT INTO `ddwx_admin_menu` (`pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`)
SELECT @packager_business_menu_pid, 'business_feilv', '商户费率', '商户费率', 'Business/feilv', '', 'Business/*', 0, 2, 3, 1, 0, 0, 0, 0, 1, 0, '', '', '', @packager_business_menu_now, @packager_business_menu_now
FROM DUAL
WHERE @packager_business_menu_pid IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='business_feilv' OR `path`='Business/feilv');

INSERT INTO `ddwx_admin_menu` (`pid`, `key`, `name`, `fullname`, `path`, `icon`, `auth_data`, `auth_mode`, `level`, `sort`, `status`, `is_hidden`, `can_promote`, `admin_only`, `merchant_visible`, `tuanzhang_visible`, `console_visible`, `platform_limit`, `plugin_name`, `params`, `createtime`, `updatetime`)
SELECT @packager_business_menu_pid, 'businessposter_index', '商家海报', '商家海报', 'BusinessPoster/index', '', 'BusinessPoster/*', 0, 2, 4, 1, 0, 0, 0, 0, 1, 0, '', '', '', @packager_business_menu_now, @packager_business_menu_now
FROM DUAL
WHERE @packager_business_menu_pid IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='businessposter_index' OR `path`='BusinessPoster/index');

UPDATE `ddwx_admin_menu` SET `pid`=@packager_business_menu_pid, `sort`=1, `status`=1, `is_hidden`=0, `merchant_visible`=0 WHERE @packager_business_menu_pid IS NOT NULL AND (`key`='business_index' OR `path`='Business/index');
UPDATE `ddwx_admin_menu` SET `pid`=@packager_business_menu_pid, `sort`=2, `status`=1, `is_hidden`=0, `merchant_visible`=0 WHERE @packager_business_menu_pid IS NOT NULL AND (`key`='business_category' OR `path`='Business/category');
UPDATE `ddwx_admin_menu` SET `pid`=@packager_business_menu_pid, `sort`=3, `status`=1, `is_hidden`=0, `merchant_visible`=0 WHERE @packager_business_menu_pid IS NOT NULL AND (`key`='business_feilv' OR `path`='Business/feilv');
UPDATE `ddwx_admin_menu` SET `pid`=@packager_business_menu_pid, `sort`=4, `status`=1, `is_hidden`=0, `merchant_visible`=0 WHERE @packager_business_menu_pid IS NOT NULL AND (`key`='businessposter_index' OR `path`='BusinessPoster/index');
UPDATE `ddwx_admin_menu` SET `pid`=@packager_business_menu_pid, `sort`=5, `status`=1, `is_hidden`=0, `merchant_visible`=1 WHERE @packager_business_menu_pid IS NOT NULL AND (`key`='shopproduct_index_showtype_2' OR `path`='ShopProduct/index&showtype=2');
UPDATE `ddwx_admin_menu` SET `pid`=@packager_business_menu_pid, `sort`=6, `status`=1, `is_hidden`=0, `merchant_visible`=1 WHERE @packager_business_menu_pid IS NOT NULL AND (`key`='shoporder_index_showtype_2' OR `path`='ShopOrder/index&showtype=2');
UPDATE `ddwx_admin_menu` SET `pid`=@packager_business_menu_pid, `sort`=7, `status`=1, `is_hidden`=0, `merchant_visible`=1 WHERE @packager_business_menu_pid IS NOT NULL AND (`key`='businesscomment_index_showtype_2' OR `path`='BusinessComment/index&showtype=2');
-- Sync base admin_menu from app/admin/common/Menu.php core navigation. Match rows by `key` only to support duplicate controller paths.
SET @menu_sync_now := UNIX_TIMESTAMP();
CREATE TABLE IF NOT EXISTS `ddwx_admin_menu_bak_20260710` AS SELECT * FROM `ddwx_admin_menu`;
UPDATE `ddwx_admin_menu` SET `pid`=0,`name`='商城',`fullname`='商城系统',`path`='',`icon`='my-icon my-icon-shop',`auth_data`='',`level`=1,`sort`=1,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='shop';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT 0,'shop','商城','商城系统','','my-icon my-icon-shop','',0,1,1,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='shop');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='shop' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='商品管理',`fullname`='商品管理',`path`='ShopProduct/index',`icon`='',`auth_data`='ShopProduct/*,ShopCode/*',`level`=2,`sort`=1,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='shopproduct_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'shopproduct_index','商品管理','商品管理','ShopProduct/index','','ShopProduct/*,ShopCode/*',0,2,1,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='shopproduct_index');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='shop' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='订单管理',`fullname`='订单管理',`path`='ShopOrder/index',`icon`='',`auth_data`='ShopOrder/*',`level`=2,`sort`=2,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='shoporder_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'shoporder_index','订单管理','订单管理','ShopOrder/index','','ShopOrder/*',0,2,2,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='shoporder_index');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='shop' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='退款申请',`fullname`='退款申请',`path`='ShopRefundOrder/index',`icon`='',`auth_data`='ShopRefundOrder/*',`level`=2,`sort`=3,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='shoprefundorder_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'shoprefundorder_index','退款申请','退款申请','ShopRefundOrder/index','','ShopRefundOrder/*',0,2,3,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='shoprefundorder_index');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='shop' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='评价管理',`fullname`='评价管理',`path`='ShopComment/index',`icon`='',`auth_data`='ShopComment/*',`level`=2,`sort`=4,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='shopcomment_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'shopcomment_index','评价管理','评价管理','ShopComment/index','','ShopComment/*',0,2,4,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='shopcomment_index');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='shop' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='商品分类',`fullname`='商品分类',`path`='ShopCategory/index',`icon`='',`auth_data`='ShopCategory/*',`level`=2,`sort`=5,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='shopcategory_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'shopcategory_index','商品分类','商品分类','ShopCategory/index','','ShopCategory/*',0,2,5,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='shopcategory_index');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='shop' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='商品分组',`fullname`='商品分组',`path`='ShopGroup/index',`icon`='',`auth_data`='ShopGroup/*',`level`=2,`sort`=6,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='shopgroup_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'shopgroup_index','商品分组','商品分组','ShopGroup/index','','ShopGroup/*',0,2,6,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='shopgroup_index');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='shop' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='商品参数',`fullname`='商品参数',`path`='ShopParam/index',`icon`='',`auth_data`='ShopParam/*',`level`=2,`sort`=7,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='shopparam_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'shopparam_index','商品参数','商品参数','ShopParam/index','','ShopParam/*',0,2,7,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='shopparam_index');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='shop' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='商品服务',`fullname`='商品服务',`path`='ShopFuwu/index',`icon`='',`auth_data`='ShopFuwu/*',`level`=2,`sort`=8,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='shopfuwu_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'shopfuwu_index','商品服务','商品服务','ShopFuwu/index','','ShopFuwu/*',0,2,8,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='shopfuwu_index');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='shop' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='商品海报',`fullname`='商品海报',`path`='ShopPoster/index',`icon`='',`auth_data`='ShopPoster/*',`level`=2,`sort`=9,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='shopposter_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'shopposter_index','商品海报','商品海报','ShopPoster/index','','ShopPoster/*',0,2,9,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='shopposter_index');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='shop' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='录入订单',`fullname`='录入订单',`path`='ShopOrderlr/index',`icon`='',`auth_data`='ShopOrderlr/*,ShopProduct/chooseproduct,ShopProduct/index,ShopProduct/getproduct,Member/index',`level`=2,`sort`=10,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='shoporderlr_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'shoporderlr_index','录入订单','录入订单','ShopOrderlr/index','','ShopOrderlr/*,ShopProduct/chooseproduct,ShopProduct/index,ShopProduct/getproduct,Member/index',0,2,10,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='shoporderlr_index');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='shop' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='商品采集',`fullname`='商品采集',`path`='ShopTaobao/index',`icon`='',`auth_data`='ShopTaobao/*',`level`=2,`sort`=11,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='shoptaobao_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'shoptaobao_index','商品采集','商品采集','ShopTaobao/index','','ShopTaobao/*',0,2,11,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='shoptaobao_index');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='shop' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='销售统计',`fullname`='销售统计',`path`='ShopOrder/tongji',`icon`='',`auth_data`='ShopOrder/*',`level`=2,`sort`=12,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='shoporder_tongji';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'shoporder_tongji','销售统计','销售统计','ShopOrder/tongji','','ShopOrder/*',0,2,12,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='shoporder_tongji');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='shop' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='系统设置',`fullname`='系统设置',`path`='ShopSet/index',`icon`='',`auth_data`='ShopSet/*',`level`=2,`sort`=13,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='shopset_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'shopset_index','系统设置','系统设置','ShopSet/index','','ShopSet/*',0,2,13,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='shopset_index');
UPDATE `ddwx_admin_menu` SET `pid`=0,`name`='会员',`fullname`='会员管理',`path`='',`icon`='my-icon my-icon-member',`auth_data`='',`level`=1,`sort`=2,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='member';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT 0,'member','会员','会员管理','','my-icon my-icon-member','',0,1,2,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='member');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='member' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='会员列表',`fullname`='会员列表',`path`='Member/index',`icon`='',`auth_data`='Member/index,Member/excel,Member/excel,Member/importexcel,Member/getplatform,Member/edit,Member/save,Member/del,Member/getcarddetail,Member/charts,Member/setst,Member/*,MemberStatistics/*,MemberManage/*',`level`=2,`sort`=1,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='member_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'member_index','会员列表','会员列表','Member/index','','Member/index,Member/excel,Member/excel,Member/importexcel,Member/getplatform,Member/edit,Member/save,Member/del,Member/getcarddetail,Member/charts,Member/setst,Member/*,MemberStatistics/*,MemberManage/*',0,2,1,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='member_index');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='member' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='充值',`fullname`='充值',`path`='Member/recharge',`icon`='',`auth_data`='Member/recharge',`level`=2,`sort`=2,`status`=1,`is_hidden`=1,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='member_recharge';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'member_recharge','充值','充值','Member/recharge','','Member/recharge',0,2,2,1,1,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='member_recharge');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='member' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='加配资',`fullname`='加配资',`path`='Member/addAllocation',`icon`='',`auth_data`='Member/addAllocation',`level`=2,`sort`=3,`status`=1,`is_hidden`=1,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='member_addallocation';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'member_addallocation','加配资','加配资','Member/addAllocation','','Member/addAllocation',0,2,3,1,1,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='member_addallocation');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='member' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='加积分',`fullname`='加积分',`path`='Member/addscore',`icon`='',`auth_data`='Member/addscore',`level`=2,`sort`=4,`status`=1,`is_hidden`=1,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='member_addscore';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'member_addscore','加积分','加积分','Member/addscore','','Member/addscore',0,2,4,1,1,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='member_addscore');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='member' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='加佣金',`fullname`='加佣金',`path`='Member/addcommission',`icon`='',`auth_data`='Member/addcommission',`level`=2,`sort`=5,`status`=1,`is_hidden`=1,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='member_addcommission';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'member_addcommission','加佣金','加佣金','Member/addcommission','','Member/addcommission',0,2,5,1,1,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='member_addcommission');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='member' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='加现金券',`fullname`='加现金券',`path`='Member/addheijifen',`icon`='',`auth_data`='Member/addheijifen',`level`=2,`sort`=7,`status`=1,`is_hidden`=1,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='member_addheijifen';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'member_addheijifen','加现金券','加现金券','Member/addheijifen','','Member/addheijifen',0,2,7,1,1,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='member_addheijifen');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='member' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='等级及分销',`fullname`='等级及分销',`path`='MemberLevel/index',`icon`='',`auth_data`='MemberLevel/*',`level`=2,`sort`=8,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='memberlevel_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'memberlevel_index','等级及分销','等级及分销','MemberLevel/index','','MemberLevel/*',0,2,8,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='memberlevel_index');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='member' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='升级申请记录',`fullname`='升级申请记录',`path`='MemberLevel/applyorder',`icon`='',`auth_data`='MemberLevel/*',`level`=2,`sort`=9,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='memberlevel_applyorder';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'memberlevel_applyorder','升级申请记录','升级申请记录','MemberLevel/applyorder','','MemberLevel/*',0,2,9,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='memberlevel_applyorder');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='member' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='会员关系图',`fullname`='会员关系图',`path`='Member/charts',`icon`='',`auth_data`='Member/charts',`level`=2,`sort`=10,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='member_charts';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'member_charts','会员关系图','会员关系图','Member/charts','','Member/charts',0,2,10,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='member_charts');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='member' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='会员标签',`fullname`='会员标签',`path`='MemberTag/index',`icon`='',`auth_data`='MemberTag/*',`level`=2,`sort`=11,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='membertag_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'membertag_index','会员标签','会员标签','MemberTag/index','','MemberTag/*',0,2,11,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='membertag_index');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='member' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='会员来源管理',`fullname`='会员来源管理',`path`='MemberManage/source',`icon`='',`auth_data`='MemberManage/*',`level`=2,`sort`=12,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='membermanage_source';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'membermanage_source','会员来源管理','会员来源管理','MemberManage/source','','MemberManage/*',0,2,12,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='membermanage_source');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='member' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='会员足迹',`fullname`='会员足迹',`path`='MemberHistory/index',`icon`='',`auth_data`='MemberHistory/*',`level`=2,`sort`=13,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='memberhistory_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'memberhistory_index','会员足迹','会员足迹','MemberHistory/index','','MemberHistory/*',0,2,13,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='memberhistory_index');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='member' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='注册自定义',`fullname`='注册自定义',`path`='RegisterForm/index',`icon`='',`auth_data`='RegisterForm/*',`level`=2,`sort`=14,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='registerform_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'registerform_index','注册自定义','注册自定义','RegisterForm/index','','RegisterForm/*',0,2,14,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='registerform_index');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='member' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='分享海报',`fullname`='分享海报',`path`='MemberPoster/index',`icon`='',`auth_data`='MemberPoster/*',`level`=2,`sort`=15,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='memberposter_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'memberposter_index','分享海报','分享海报','MemberPoster/index','','MemberPoster/*',0,2,15,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='memberposter_index');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='member' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='团队分红',`fullname`='团队分红',`path`='teamfenhong',`icon`='',`auth_data`='teamfenhong',`level`=2,`sort`=16,`status`=1,`is_hidden`=1,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='teamfenhong';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'teamfenhong','团队分红','团队分红','teamfenhong','','teamfenhong',0,2,16,1,1,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='teamfenhong');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='member' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='股东分红',`fullname`='股东分红',`path`='gdfenhong',`icon`='',`auth_data`='gdfenhong',`level`=2,`sort`=17,`status`=1,`is_hidden`=1,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='gdfenhong';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'gdfenhong','股东分红','股东分红','gdfenhong','','gdfenhong',0,2,17,1,1,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='gdfenhong');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='member' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='区域分红',`fullname`='区域分红',`path`='areafenhong',`icon`='',`auth_data`='areafenhong',`level`=2,`sort`=18,`status`=1,`is_hidden`=1,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='areafenhong';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'areafenhong','区域分红','区域分红','areafenhong','','areafenhong',0,2,18,1,1,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='areafenhong');
UPDATE `ddwx_admin_menu` SET `pid`=0,`name`='财务',`fullname`='财务管理',`path`='',`icon`='my-icon my-icon-finance',`auth_data`='',`level`=1,`sort`=3,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='finance';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT 0,'finance','财务','财务管理','','my-icon my-icon-finance','',0,1,3,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='finance');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='finance' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='会员列表',`fullname`='会员列表',`path`='Membercaiwu/index',`icon`='',`auth_data`='Membecaiwu/*',`level`=2,`sort`=1,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='membercaiwu_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'membercaiwu_index','会员列表','会员列表','Membercaiwu/index','','Membecaiwu/*',0,2,1,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='membercaiwu_index');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='finance' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='全局明细',`fullname`='全局明细',`path`='Payorder/tongjiyue',`icon`='',`auth_data`='Payorder/*',`level`=2,`sort`=2,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='payorder_tongjiyue';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'payorder_tongjiyue','全局明细','全局明细','Payorder/tongjiyue','','Payorder/*',0,2,2,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='payorder_tongjiyue');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='finance' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='每日收款明细',`fullname`='每日收款明细',`path`='Payorder/caiwu',`icon`='',`auth_data`='Payorder/*',`level`=2,`sort`=3,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='payorder_caiwu';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'payorder_caiwu','每日收款明细','每日收款明细','Payorder/caiwu','','Payorder/*',0,2,3,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='payorder_caiwu');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='finance' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='每日货款明细',`fullname`='每日货款明细',`path`='Payorder/fahuomingxi',`icon`='',`auth_data`='Payorder/*',`level`=2,`sort`=4,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='payorder_fahuomingxi';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'payorder_fahuomingxi','每日货款明细','每日货款明细','Payorder/fahuomingxi','','Payorder/*',0,2,4,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='payorder_fahuomingxi');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='finance' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='消费明细',`fullname`='消费明细',`path`='Payorder/index',`icon`='',`auth_data`='Payorder/*',`level`=2,`sort`=5,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='payorder_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'payorder_index','消费明细','消费明细','Payorder/index','','Payorder/*',0,2,5,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='payorder_index');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='finance' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='用户明细',`fullname`='用户明细',`path`='BusinessMoney/memberindex',`icon`='',`auth_data`='BusinessMoney/*',`level`=2,`sort`=6,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='businessmoney_memberindex';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'businessmoney_memberindex','用户明细','用户明细','BusinessMoney/memberindex','','BusinessMoney/*',0,2,6,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='businessmoney_memberindex');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='finance' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='余额明细',`fullname`='余额明细',`path`='Money/moneylog',`icon`='',`auth_data`='Money/moneylog,Money/moneylogexcel,Money/moneylogsetst,Money/moneylogdel',`level`=2,`sort`=7,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='money_moneylog';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'money_moneylog','余额明细','余额明细','Money/moneylog','','Money/moneylog,Money/moneylogexcel,Money/moneylogsetst,Money/moneylogdel',0,2,7,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='money_moneylog');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='finance' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='现金券明细',`fullname`='现金券明细',`path`='Money/heijifenlog',`icon`='',`auth_data`='Money/heijifenlog,Money/moneylog,Money/moneylogexcel,Money/moneylogsetst,Money/moneylogdel',`level`=2,`sort`=8,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='money_heijifenlog';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'money_heijifenlog','现金券明细','现金券明细','Money/heijifenlog','','Money/heijifenlog,Money/moneylog,Money/moneylogexcel,Money/moneylogsetst,Money/moneylogdel',0,2,8,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='money_heijifenlog');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='finance' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='充值记录',`fullname`='充值记录',`path`='Money/rechargelog',`icon`='',`auth_data`='Money/rechargelog,Money/rechargelogexcel,Money/rechargelogdel',`level`=2,`sort`=9,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='money_rechargelog';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'money_rechargelog','充值记录','充值记录','Money/rechargelog','','Money/rechargelog,Money/rechargelogexcel,Money/rechargelogdel',0,2,9,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='money_rechargelog');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='finance' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='余额提现',`fullname`='余额提现',`path`='Money/withdrawlog',`icon`='',`auth_data`='Money/*',`level`=2,`sort`=10,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='money_withdrawlog';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'money_withdrawlog','余额提现','余额提现','Money/withdrawlog','','Money/*',0,2,10,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='money_withdrawlog');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='finance' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='佣金记录',`fullname`='佣金记录',`path`='Commission/record',`icon`='',`auth_data`='Commission/record',`level`=2,`sort`=11,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='commission_record';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'commission_record','佣金记录','佣金记录','Commission/record','','Commission/record',0,2,11,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='commission_record');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='finance' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='佣金明细',`fullname`='佣金明细',`path`='Commission/commissionlog',`icon`='',`auth_data`='Commission/commissionlog,Commission/commissionlogexcel,Commission/commissionlogdel',`level`=2,`sort`=12,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='commission_commissionlog';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'commission_commissionlog','佣金明细','佣金明细','Commission/commissionlog','','Commission/commissionlog,Commission/commissionlogexcel,Commission/commissionlogdel',0,2,12,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='commission_commissionlog');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='finance' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='佣金提现',`fullname`='佣金提现',`path`='Commission/withdrawlog',`icon`='',`auth_data`='Commission/*',`level`=2,`sort`=14,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='commission_withdrawlog';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'commission_withdrawlog','佣金提现','佣金提现','Commission/withdrawlog','','Commission/*',0,2,14,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='commission_withdrawlog');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='finance' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='中金佣金提现',`fullname`='中金佣金提现',`path`='Commission/withdrawlogcpcn',`icon`='',`auth_data`='Commission/*',`level`=2,`sort`=15,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='commission_withdrawlogcpcn';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'commission_withdrawlogcpcn','中金佣金提现','中金佣金提现','Commission/withdrawlogcpcn','','Commission/*',0,2,15,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='commission_withdrawlogcpcn');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='finance' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='积分明细',`fullname`='积分明细',`path`='Score/scorelog',`icon`='',`auth_data`='Score/*',`level`=2,`sort`=16,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='score_scorelog';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'score_scorelog','积分明细','积分明细','Score/scorelog','','Score/*',0,2,16,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='score_scorelog');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='finance' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='黄积分明细',`fullname`='黄积分明细',`path`='ScoreHuang/scorelog',`icon`='',`auth_data`='ScoreHuang/scorelog,ScoreHuang/scorelogexcel,ScoreHuang/scorelogdel,ScorehuangLog/getList,ScorehuangLog/export,ScorehuangLog/delete',`level`=2,`sort`=17,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='scorehuang_scorelog';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'scorehuang_scorelog','黄积分明细','黄积分明细','ScoreHuang/scorelog','','ScoreHuang/scorelog,ScoreHuang/scorelogexcel,ScoreHuang/scorelogdel,ScorehuangLog/getList,ScorehuangLog/export,ScorehuangLog/delete',0,2,17,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='scorehuang_scorelog');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='finance' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='消费币明细',`fullname`='消费币明细',`path`='XiaofeibLog/index',`icon`='',`auth_data`='XiaofeibLog/getList,XiaofeibLog/export,XiaofeibLog/delete',`level`=2,`sort`=18,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='xiaofeiblog_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'xiaofeiblog_index','消费币明细','消费币明细','XiaofeibLog/index','','XiaofeibLog/getList,XiaofeibLog/export,XiaofeibLog/delete',0,2,18,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='xiaofeiblog_index');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='finance' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='会员互转明细',`fullname`='会员互转明细',`path`='Money/membertransfer',`icon`='',`auth_data`='MemberTransferLog/getList',`level`=2,`sort`=19,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='membertransfer_index' OR `path`='Money/membertransfer';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'membertransfer_index','会员互转明细','会员互转明细','Money/membertransfer','','MemberTransferLog/getList',0,2,19,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='membertransfer_index' OR `path`='Money/membertransfer');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='finance' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='积分转账明细',`fullname`='积分转账明细',`path`='Score/zhuanscorelog',`icon`='',`auth_data`='Score/*',`level`=2,`sort`=20,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='score_zhuanscorelog';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'score_zhuanscorelog','积分转账明细','积分转账明细','Score/zhuanscorelog','','Score/*',0,2,20,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='score_zhuanscorelog');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='finance' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='余额转账明细',`fullname`='余额转账明细',`path`='Money/zhuangzhang',`icon`='',`auth_data`='Money/*',`level`=2,`sort`=21,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='money_zhuangzhang';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'money_zhuangzhang','余额转账明细','余额转账明细','Money/zhuangzhang','','Money/*',0,2,21,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='money_zhuangzhang');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='finance' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='买单记录',`fullname`='买单记录',`path`='Maidan/index',`icon`='',`auth_data`='Maidan/*',`level`=2,`sort`=22,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='maidan_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'maidan_index','买单记录','买单记录','Maidan/index','','Maidan/*',0,2,22,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='maidan_index');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='finance' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='分红记录',`fullname`='分红记录',`path`='Commission/fenhonglog',`icon`='',`auth_data`='Commission/*',`level`=2,`sort`=23,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='commission_fenhonglog';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'commission_fenhonglog','分红记录','分红记录','Commission/fenhonglog','','Commission/*',0,2,23,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='commission_fenhonglog');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='finance' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='核销记录',`fullname`='核销记录',`path`='Hexiao/index',`icon`='',`auth_data`='Hexiao/*',`level`=2,`sort`=24,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='hexiao_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'hexiao_index','核销记录','核销记录','Hexiao/index','','Hexiao/*',0,2,24,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='hexiao_index');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='finance' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='发票管理',`fullname`='发票管理',`path`='Invoice/index',`icon`='',`auth_data`='Invoice/*',`level`=2,`sort`=25,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='invoice_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'invoice_index','发票管理','发票管理','Invoice/index','','Invoice/*',0,2,25,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='invoice_index');
UPDATE `ddwx_admin_menu` SET `pid`=0,`name`='营销',`fullname`='营销活动',`path`='',`icon`='my-icon my-icon-yingxiao',`auth_data`='',`level`=1,`sort`=4,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='yingxiao';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT 0,'yingxiao','营销','营销活动','','my-icon my-icon-yingxiao','',0,1,4,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='yingxiao');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='yingxiao' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='优惠券',`fullname`='优惠券',`path`='Coupon/index',`icon`='',`auth_data`='Coupon/*,ShopCategory/index,ShopCategory/choosecategory',`level`=2,`sort`=1,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='coupon_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'coupon_index','优惠券','优惠券','Coupon/index','','Coupon/*,ShopCategory/index,ShopCategory/choosecategory',0,2,1,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='coupon_index');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='yingxiao' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='注册赠送',`fullname`='注册赠送',`path`='Member/registerGive',`icon`='',`auth_data`='Member/registerGive',`level`=2,`sort`=2,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='member_registergive';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'member_registergive','注册赠送','注册赠送','Member/registerGive','','Member/registerGive',0,2,2,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='member_registergive');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='yingxiao' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='充值赠送',`fullname`='充值赠送',`path`='Money/giveset',`icon`='',`auth_data`='Money/giveset',`level`=2,`sort`=3,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='money_giveset';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'money_giveset','充值赠送','充值赠送','Money/giveset','','Money/giveset',0,2,3,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='money_giveset');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='yingxiao' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='购物满减',`fullname`='购物满减',`path`='Manjian/set',`icon`='',`auth_data`='Manjian/set',`level`=2,`sort`=4,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='manjian_set';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'manjian_set','购物满减','购物满减','Manjian/set','','Manjian/set',0,2,4,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='manjian_set');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='yingxiao' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='随机立减',`fullname`='随机立减',`path`='RandomDiscount/set',`icon`='',`auth_data`='RandomDiscount/set',`level`=2,`sort`=5,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='randomdiscount_set';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'randomdiscount_set','随机立减','随机立减','RandomDiscount/set','','RandomDiscount/set',0,2,5,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='randomdiscount_set');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='yingxiao' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='商品促销',`fullname`='商品促销',`path`='Cuxiao/index',`icon`='',`auth_data`='Cuxiao/*',`level`=2,`sort`=6,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='cuxiao_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'cuxiao_index','商品促销','商品促销','Cuxiao/index','','Cuxiao/*',0,2,6,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='cuxiao_index');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='yingxiao' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='购物返现',`fullname`='购物返现',`path`='Cashback/index',`icon`='',`auth_data`='Cashback/*',`level`=2,`sort`=7,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='cashback_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'cashback_index','购物返现','购物返现','Cashback/index','','Cashback/*',0,2,7,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='cashback_index');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='yingxiao' ORDER BY `id` ASC LIMIT 1);
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='yingxiao' ORDER BY `id` ASC LIMIT 1);
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='yingxiao' ORDER BY `id` ASC LIMIT 1);
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='yingxiao' ORDER BY `id` ASC LIMIT 1);
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='yingxiao' ORDER BY `id` ASC LIMIT 1);
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='yingxiao' ORDER BY `id` ASC LIMIT 1);
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='yingxiao' ORDER BY `id` ASC LIMIT 1);
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='yingxiao' ORDER BY `id` ASC LIMIT 1);
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='yingxiao' ORDER BY `id` ASC LIMIT 1);
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='yingxiao' ORDER BY `id` ASC LIMIT 1);
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='yingxiao' ORDER BY `id` ASC LIMIT 1);
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='yingxiao' ORDER BY `id` ASC LIMIT 1);
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='yingxiao' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=0,`name`='多商户',`fullname`='多商户管理',`path`='',`icon`='my-icon my-icon-business',`auth_data`='',`level`=1,`sort`=5,`status`=1,`is_hidden`=0,`merchant_visible`=0,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='business';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT 0,'business','多商户','多商户管理','','my-icon my-icon-business','',0,1,5,1,0,0,0,0,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='business');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='business' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='商户管理',`fullname`='商户管理',`path`='Business/index',`icon`='',`auth_data`='Business/*,BusinessFreight/*',`level`=2,`sort`=1,`status`=1,`is_hidden`=0,`merchant_visible`=0,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='business_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'business_index','商户管理','商户管理','Business/index','','Business/*,BusinessFreight/*',0,2,1,1,0,0,0,0,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='business_index');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='business' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='商户分类',`fullname`='商户分类',`path`='Business/category',`icon`='',`auth_data`='Business/*',`level`=2,`sort`=2,`status`=1,`is_hidden`=0,`merchant_visible`=0,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='business_category';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'business_category','商户分类','商户分类','Business/category','','Business/*',0,2,2,1,0,0,0,0,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='business_category');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='business' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='商户费率',`fullname`='商户费率',`path`='Business/feilv',`icon`='',`auth_data`='Business/*',`level`=2,`sort`=3,`status`=1,`is_hidden`=0,`merchant_visible`=0,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='business_feilv';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'business_feilv','商户费率','商户费率','Business/feilv','','Business/*',0,2,3,1,0,0,0,0,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='business_feilv');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='business' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='商家海报',`fullname`='商家海报',`path`='BusinessPoster/index',`icon`='',`auth_data`='BusinessPoster/*',`level`=2,`sort`=4,`status`=1,`is_hidden`=0,`merchant_visible`=0,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='businessposter_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'businessposter_index','商家海报','商家海报','BusinessPoster/index','','BusinessPoster/*',0,2,4,1,0,0,0,0,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='businessposter_index');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='business' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='商品管理',`fullname`='商品管理',`path`='ShopProduct/index&showtype=2',`icon`='',`auth_data`='ShopProduct/*',`level`=2,`sort`=5,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='shopproduct_index_showtype_2';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'shopproduct_index_showtype_2','商品管理','商品管理','ShopProduct/index&showtype=2','','ShopProduct/*',0,2,5,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='shopproduct_index_showtype_2');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='business' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='商户订单',`fullname`='商户订单',`path`='ShopOrder/index&showtype=2',`icon`='',`auth_data`='ShopOrder/*',`level`=2,`sort`=6,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='shoporder_index_showtype_2';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'shoporder_index_showtype_2','商户订单','商户订单','ShopOrder/index&showtype=2','','ShopOrder/*',0,2,6,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='shoporder_index_showtype_2');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='business' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='商户评价',`fullname`='商户评价',`path`='BusinessComment/index&showtype=2',`icon`='',`auth_data`='BusinessComment/*',`level`=2,`sort`=7,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='businesscomment_index_showtype_2';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'businesscomment_index_showtype_2','商户评价','商户评价','BusinessComment/index&showtype=2','','BusinessComment/*',0,2,7,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='businesscomment_index_showtype_2');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='business' ORDER BY `id` ASC LIMIT 1);
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='business' ORDER BY `id` ASC LIMIT 1);
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='business' ORDER BY `id` ASC LIMIT 1);
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='business' ORDER BY `id` ASC LIMIT 1);
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='business' ORDER BY `id` ASC LIMIT 1);
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='business' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='周期购商品',`fullname`='周期购商品',`path`='CycleProduct/index&showtype=2',`icon`='',`auth_data`='CycleProduct/*',`level`=2,`sort`=13,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='cycleproduct_index_showtype_2';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'cycleproduct_index_showtype_2','周期购商品','周期购商品','CycleProduct/index&showtype=2','','CycleProduct/*',0,2,13,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='cycleproduct_index_showtype_2');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='business' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='文章列表',`fullname`='文章列表',`path`='Article/index&showtype=2',`icon`='',`auth_data`='Article/*',`level`=2,`sort`=14,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='article_index_showtype_2';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'article_index_showtype_2','文章列表','文章列表','Article/index&showtype=2','','Article/*',0,2,14,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='article_index_showtype_2');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='business' ORDER BY `id` ASC LIMIT 1);
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='business' ORDER BY `id` ASC LIMIT 1);
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='business' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='余额明细',`fullname`='余额明细',`path`='BusinessMoney/moneylog',`icon`='',`auth_data`='BusinessMoney/moneylog,BusinessMoney/moneylogexcel,BusinessMoney/moneylogsetst,BusinessMoney/moneylogdel',`level`=2,`sort`=17,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='businessmoney_moneylog';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'businessmoney_moneylog','余额明细','余额明细','BusinessMoney/moneylog','','BusinessMoney/moneylog,BusinessMoney/moneylogexcel,BusinessMoney/moneylogsetst,BusinessMoney/moneylogdel',0,2,17,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='businessmoney_moneylog');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='business' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='充值审核',`fullname`='充值审核',`path`='BusinessMoney/rechargelog',`icon`='',`auth_data`='BusinessMoney/*',`level`=2,`sort`=18,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='businessmoney_rechargelog';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'businessmoney_rechargelog','充值审核','充值审核','BusinessMoney/rechargelog','','BusinessMoney/*',0,2,18,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='businessmoney_rechargelog');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='business' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='提现记录',`fullname`='提现记录',`path`='BusinessMoney/withdrawlog',`icon`='',`auth_data`='BusinessMoney/*',`level`=2,`sort`=19,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='businessmoney_withdrawlog';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'businessmoney_withdrawlog','提现记录','提现记录','BusinessMoney/withdrawlog','','BusinessMoney/*',0,2,19,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='businessmoney_withdrawlog');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='business' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='通知公告',`fullname`='通知公告',`path`='BusinessNotice/index',`icon`='',`auth_data`='BusinessNotice/*',`level`=2,`sort`=20,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='businessnotice_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'businessnotice_index','通知公告','通知公告','BusinessNotice/index','','BusinessNotice/*',0,2,20,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='businessnotice_index');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='business' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='分账记录',`fullname`='分账记录',`path`='BusinessSplitRecords/index',`icon`='',`auth_data`='BusinessSplitRecords/*',`level`=2,`sort`=21,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='businesssplitrecords_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'businesssplitrecords_index','分账记录','分账记录','BusinessSplitRecords/index','','BusinessSplitRecords/*',0,2,21,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='businesssplitrecords_index');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='business' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='默认导航',`fullname`='默认导航',`path`='DesignerMenu/business',`icon`='',`auth_data`='DesignerMenu/*',`level`=2,`sort`=22,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='designermenu_business';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'designermenu_business','默认导航','默认导航','DesignerMenu/business','','DesignerMenu/*',0,2,22,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='designermenu_business');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='business' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='系统设置',`fullname`='系统设置',`path`='Business/sysset',`icon`='',`auth_data`='Business/sysset',`level`=2,`sort`=23,`status`=1,`is_hidden`=0,`merchant_visible`=0,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='business_sysset';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'business_sysset','系统设置','系统设置','Business/sysset','','Business/sysset',0,2,23,1,0,0,0,0,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='business_sysset');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='business' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='余额查询',`fullname`='余额查询',`path`='MerchantBalanceQuery/index',`icon`='',`auth_data`='MerchantBalanceQuery/*',`level`=2,`sort`=24,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='merchantbalancequery_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'merchantbalancequery_index','余额查询','余额查询','MerchantBalanceQuery/index','','MerchantBalanceQuery/*',0,2,24,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='merchantbalancequery_index');
UPDATE `ddwx_admin_menu` SET `pid`=0,`name`='插件',`fullname`='插件管理',`path`='MyPlugin/index',`icon`='my-icon my-icon-kuozhan',`auth_data`='MyPlugin/*',`level`=1,`sort`=6,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='plugin';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT 0,'plugin','插件','插件管理','MyPlugin/index','my-icon my-icon-kuozhan','MyPlugin/*',0,1,6,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='plugin');
UPDATE `ddwx_admin_menu` SET `pid`=0,`name`='设计',`fullname`='界面设计',`path`='',`icon`='my-icon my-icon-sheji',`auth_data`='',`level`=1,`sort`=7,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='jiemian';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT 0,'jiemian','设计','界面设计','','my-icon my-icon-sheji','',0,1,7,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='jiemian');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='jiemian' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='页面设计',`fullname`='页面设计',`path`='DesignerPage/index',`icon`='',`auth_data`='DesignerPage/*',`level`=2,`sort`=1,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='designerpage_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'designerpage_index','页面设计','页面设计','DesignerPage/index','','DesignerPage/*',0,2,1,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='designerpage_index');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='jiemian' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='个人中心设计',`fullname`='个人中心设计',`path`='DesignerPage/member',`icon`='',`auth_data`='DesignerPage/*',`level`=2,`sort`=2,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='designerpage_member';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'designerpage_member','个人中心设计','个人中心设计','DesignerPage/member','','DesignerPage/*',0,2,2,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='designerpage_member');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='jiemian' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='页面分类',`fullname`='页面分类',`path`='DesignerCategory/index',`icon`='',`auth_data`='DesignerCategory/*',`level`=2,`sort`=3,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='designercategory_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'designercategory_index','页面分类','页面分类','DesignerCategory/index','','DesignerCategory/*',0,2,3,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='designercategory_index');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='jiemian' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='底部导航',`fullname`='底部导航',`path`='DesignerMenu/index',`icon`='',`auth_data`='DesignerMenu/*',`level`=2,`sort`=4,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='designermenu_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'designermenu_index','底部导航','底部导航','DesignerMenu/index','','DesignerMenu/*',0,2,4,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='designermenu_index');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='jiemian' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='内页导航',`fullname`='内页导航',`path`='DesignerMenu/inner',`icon`='',`auth_data`='DesignerMenu/*',`level`=2,`sort`=5,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='designermenu_inner';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'designermenu_inner','内页导航','内页导航','DesignerMenu/inner','','DesignerMenu/*',0,2,5,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='designermenu_inner');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='jiemian' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='页面弹窗',`fullname`='页面弹窗',`path`='DesignerPagePopup/index',`icon`='',`auth_data`='DesignerPagePopup/*',`level`=2,`sort`=6,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='designerpagepopup_index' OR `path`='DesignerPagePopup/index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'designerpagepopup_index','页面弹窗','页面弹窗','DesignerPagePopup/index','','DesignerPagePopup/*',0,2,6,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='designerpagepopup_index' OR `path`='DesignerPagePopup/index');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='jiemian' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='开屏配置',`fullname`='开屏配置',`path`='DesignerMenu/splash',`icon`='',`auth_data`='DesignerMenu/*',`level`=2,`sort`=7,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='designermenu_splash';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'designermenu_splash','开屏配置','开屏配置','DesignerMenu/splash','','DesignerMenu/*',0,2,7,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='designermenu_splash');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='jiemian' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='开屏管理',`fullname`='开屏管理',`path`='DesignerMenu/splashmanage',`icon`='',`auth_data`='DesignerMenu/*',`level`=2,`sort`=8,`status`=1,`is_hidden`=1,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='designermenu_splashmanage';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'designermenu_splashmanage','开屏管理','开屏管理','DesignerMenu/splashmanage','','DesignerMenu/*',0,2,8,1,1,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='designermenu_splashmanage');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='jiemian' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='商品详情',`fullname`='商品详情',`path`='DesignerMenuShopdetail/shopdetail',`icon`='',`auth_data`='DesignerMenuShopdetail/*',`level`=2,`sort`=9,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='designermenushopdetail_shopdetail';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'designermenushopdetail_shopdetail','商品详情','商品详情','DesignerMenuShopdetail/shopdetail','','DesignerMenuShopdetail/*',0,2,9,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='designermenushopdetail_shopdetail');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='jiemian' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='登录页面',`fullname`='登录页面',`path`='DesignerLogin/index',`icon`='',`auth_data`='DesignerLogin/*',`level`=2,`sort`=10,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='designerlogin_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'designerlogin_index','登录页面','登录页面','DesignerLogin/index','','DesignerLogin/*',0,2,10,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='designerlogin_index');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='jiemian' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='移动端后台',`fullname`='移动端后台',`path`='DesignerMobile/index',`icon`='',`auth_data`='DesignerMobile/*',`level`=2,`sort`=11,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='designermobile_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'designermobile_index','移动端后台','移动端后台','DesignerMobile/index','','DesignerMobile/*',0,2,11,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='designermobile_index');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='jiemian' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='分享设置',`fullname`='分享设置',`path`='DesignerShare/index',`icon`='',`auth_data`='DesignerShare/*',`level`=2,`sort`=12,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='designershare_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'designershare_index','分享设置','分享设置','DesignerShare/index','','DesignerShare/*',0,2,12,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='designershare_index');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='jiemian' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='加载动效',`fullname`='加载动效',`path`='LoadingEffect/index',`icon`='',`auth_data`='LoadingEffect/*',`level`=2,`sort`=13,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='loadingeffect_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'loadingeffect_index','加载动效','加载动效','LoadingEffect/index','','LoadingEffect/*',0,2,13,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='loadingeffect_index');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='jiemian' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='链接地址',`fullname`='链接地址',`path`='DesignerPage/chooseurl',`icon`='',`auth_data`='DesignerPage/chooseurl',`level`=2,`sort`=14,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='/type/geturl',`updatetime`=@menu_sync_now WHERE `key`='designerpage_chooseurl';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'designerpage_chooseurl','链接地址','链接地址','DesignerPage/chooseurl','','DesignerPage/chooseurl',0,2,14,1,0,0,0,1,1,0,'','','/type/geturl',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='designerpage_chooseurl');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='jiemian' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='分销设置',`fullname`='分销设置',`path`='DistributionSettings/index',`icon`='',`auth_data`='DistributionSettings/*',`level`=2,`sort`=15,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='distributionsettings_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'distributionsettings_index','分销设置','分销设置','DistributionSettings/index','','DistributionSettings/*',0,2,15,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='distributionsettings_index');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='jiemian' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='氛围用户池',`fullname`='氛围用户池',`path`='DesignerVirtualUser/index',`icon`='',`auth_data`='DesignerVirtualUser/*',`level`=2,`sort`=16,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='designervirtualuser_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'designervirtualuser_index','氛围用户池','氛围用户池','DesignerVirtualUser/index','','DesignerVirtualUser/*',0,2,16,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='designervirtualuser_index');
UPDATE `ddwx_admin_menu` SET `status`=0,`is_hidden`=1,`updatetime`=@menu_sync_now WHERE `key`='designermenu_menu2' OR `path`='DesignerMenu/menu2';
UPDATE `ddwx_admin_menu` SET `pid`=0,`name`='平台',`fullname`='平台设置',`path`='',`icon`='my-icon my-icon-pingtai',`auth_data`='',`level`=1,`sort`=8,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='pingtai';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT 0,'pingtai','平台','平台设置','','my-icon my-icon-pingtai','',0,1,8,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='pingtai');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='pingtai' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='微信公众号',`fullname`='微信公众号',`path`='',`icon`='',`auth_data`='',`level`=2,`sort`=1,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='pingtai_0';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'pingtai_0','微信公众号','微信公众号','','','',0,2,1,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='pingtai_0');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='pingtai_0' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='公众号绑定',`fullname`='公众号绑定',`path`='Binding/index',`icon`='',`auth_data`='Binding/*',`level`=3,`sort`=1,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='binding_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'binding_index','公众号绑定','公众号绑定','Binding/index','','Binding/*',0,3,1,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='binding_index');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='pingtai_0' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='菜单设置',`fullname`='菜单设置',`path`='Mpmenu/index',`icon`='',`auth_data`='Mpmenu/*',`level`=3,`sort`=2,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='mpmenu_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'mpmenu_index','菜单设置','菜单设置','Mpmenu/index','','Mpmenu/*',0,3,2,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='mpmenu_index');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='pingtai_0' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='支付设置',`fullname`='支付设置',`path`='Mppay/set',`icon`='',`auth_data`='Mppay/*',`level`=3,`sort`=3,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='mppay_set';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'mppay_set','支付设置','支付设置','Mppay/set','','Mppay/*',0,3,3,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='mppay_set');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='pingtai_0' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='模板消息设置',`fullname`='模板消息设置',`path`='Mptmpl/tmplset',`icon`='',`auth_data`='Mptmpl/*',`level`=3,`sort`=4,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='mptmpl_tmplset';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'mptmpl_tmplset','模板消息设置','模板消息设置','Mptmpl/tmplset','','Mptmpl/*',0,3,4,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='mptmpl_tmplset');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='pingtai_0' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='类目模板消息',`fullname`='类目模板消息',`path`='Mptmpl/tmplsetNew',`icon`='',`auth_data`='Mptmpl/tmplsetNew',`level`=3,`sort`=5,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='mptmpl_tmplsetnew';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'mptmpl_tmplsetnew','类目模板消息','类目模板消息','Mptmpl/tmplsetNew','','Mptmpl/tmplsetNew',0,3,5,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='mptmpl_tmplsetnew');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='pingtai_0' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='已添加的模板',`fullname`='已添加的模板',`path`='Mptmpl/mytmpl',`icon`='',`auth_data`='Mptmpl/*',`level`=3,`sort`=6,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='mptmpl_mytmpl';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'mptmpl_mytmpl','已添加的模板','已添加的模板','Mptmpl/mytmpl','','Mptmpl/*',0,3,6,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='mptmpl_mytmpl');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='pingtai_0' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='被关注回复',`fullname`='被关注回复',`path`='Mpkeyword/subscribe',`icon`='',`auth_data`='Mpkeyword/*',`level`=3,`sort`=7,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='mpkeyword_subscribe';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'mpkeyword_subscribe','被关注回复','被关注回复','Mpkeyword/subscribe','','Mpkeyword/*',0,3,7,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='mpkeyword_subscribe');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='pingtai_0' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='关键字回复',`fullname`='关键字回复',`path`='Mpkeyword/index',`icon`='',`auth_data`='Mpkeyword/*',`level`=3,`sort`=8,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='mpkeyword_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'mpkeyword_index','关键字回复','关键字回复','Mpkeyword/index','','Mpkeyword/*',0,3,8,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='mpkeyword_index');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='pingtai_0' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='粉丝列表',`fullname`='粉丝列表',`path`='Mpfans/fanslist',`icon`='',`auth_data`='Mpfans/*',`level`=3,`sort`=9,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='mpfans_fanslist';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'mpfans_fanslist','粉丝列表','粉丝列表','Mpfans/fanslist','','Mpfans/*',0,3,9,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='mpfans_fanslist');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='pingtai_0' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='素材管理',`fullname`='素材管理',`path`='Mpfans/sourcelist',`icon`='',`auth_data`='Mpfans/*',`level`=3,`sort`=10,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='mpfans_sourcelist';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'mpfans_sourcelist','素材管理','素材管理','Mpfans/sourcelist','','Mpfans/*',0,3,10,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='mpfans_sourcelist');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='pingtai_0' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='模板消息群发',`fullname`='模板消息群发',`path`='Mpfans/tmplsend',`icon`='',`auth_data`='Mpfans/*',`level`=3,`sort`=11,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='mpfans_tmplsend';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'mpfans_tmplsend','模板消息群发','模板消息群发','Mpfans/tmplsend','','Mpfans/*',0,3,11,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='mpfans_tmplsend');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='pingtai_0' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='活跃粉丝群发',`fullname`='活跃粉丝群发',`path`='Mpfans/kfmsgsend',`icon`='',`auth_data`='Mpfans/*',`level`=3,`sort`=12,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='mpfans_kfmsgsend';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'mpfans_kfmsgsend','活跃粉丝群发','活跃粉丝群发','Mpfans/kfmsgsend','','Mpfans/*',0,3,12,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='mpfans_kfmsgsend');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='pingtai' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='微信会员卡',`fullname`='微信会员卡',`path`='',`icon`='',`auth_data`='',`level`=2,`sort`=2,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='pingtai_1';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'pingtai_1','微信会员卡','微信会员卡','','','',0,2,2,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='pingtai_1');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='pingtai_1' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='领取记录',`fullname`='领取记录',`path`='Membercard/record',`icon`='',`auth_data`='Membercard/record',`level`=3,`sort`=1,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='membercard_record';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'membercard_record','领取记录','领取记录','Membercard/record','','Membercard/record',0,3,1,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='membercard_record');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='pingtai_1' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='会员卡/创建',`fullname`='会员卡/创建',`path`='Membercard/index',`icon`='',`auth_data`='Membercard/*',`level`=3,`sort`=2,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='membercard_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'membercard_index','会员卡/创建','会员卡/创建','Membercard/index','','Membercard/*',0,3,2,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='membercard_index');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='pingtai' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='微信小程序',`fullname`='微信小程序',`path`='',`icon`='',`auth_data`='',`level`=2,`sort`=3,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='pingtai_2';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'pingtai_2','微信小程序','微信小程序','','','',0,2,3,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='pingtai_2');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='pingtai_2' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='小程序绑定',`fullname`='小程序绑定',`path`='Binding/index',`icon`='',`auth_data`='Binding/*',`level`=3,`sort`=1,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='binding_index_2';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'binding_index_2','小程序绑定','小程序绑定','Binding/index','','Binding/*',0,3,1,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='binding_index_2');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='pingtai_2' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='支付设置',`fullname`='支付设置',`path`='Wxpay/set',`icon`='',`auth_data`='Wxpay/*',`level`=3,`sort`=2,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='wxpay_set';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'wxpay_set','支付设置','支付设置','Wxpay/set','','Wxpay/*',0,3,2,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='wxpay_set');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='pingtai_2' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='订阅消息设置',`fullname`='订阅消息设置',`path`='Wxtmpl/tmplset',`icon`='',`auth_data`='Wxtmpl/*',`level`=3,`sort`=3,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='wxtmpl_tmplset';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'wxtmpl_tmplset','订阅消息设置','订阅消息设置','Wxtmpl/tmplset','','Wxtmpl/*',0,3,3,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='wxtmpl_tmplset');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='pingtai_2' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='服务类目',`fullname`='服务类目',`path`='Wxleimu/index',`icon`='',`auth_data`='Wxleimu/*',`level`=3,`sort`=4,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='wxleimu_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'wxleimu_index','服务类目','服务类目','Wxleimu/index','','Wxleimu/*',0,3,4,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='wxleimu_index');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='pingtai_2' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='外部链接',`fullname`='外部链接',`path`='Wxurl/index',`icon`='',`auth_data`='Wxurl/*',`level`=3,`sort`=5,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='wxurl_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'wxurl_index','外部链接','外部链接','Wxurl/index','','Wxurl/*',0,3,5,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='wxurl_index');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='pingtai' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='支付宝小程序',`fullname`='支付宝小程序',`path`='Binding/alipay',`icon`='',`auth_data`='Binding/*',`level`=2,`sort`=4,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='binding_alipay';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'binding_alipay','支付宝小程序','支付宝小程序','Binding/alipay','','Binding/*',0,2,4,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='binding_alipay');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='pingtai' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='百度小程序',`fullname`='百度小程序',`path`='Binding/baidu',`icon`='',`auth_data`='Binding/*',`level`=2,`sort`=5,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='binding_baidu';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'binding_baidu','百度小程序','百度小程序','Binding/baidu','','Binding/*',0,2,5,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='binding_baidu');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='pingtai' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='头条小程序',`fullname`='头条小程序',`path`='Binding/toutiao',`icon`='',`auth_data`='Binding/*',`level`=2,`sort`=6,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='binding_toutiao';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'binding_toutiao','头条小程序','头条小程序','Binding/toutiao','','Binding/*',0,2,6,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='binding_toutiao');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='pingtai' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='QQ小程序',`fullname`='QQ小程序',`path`='Binding/qq',`icon`='',`auth_data`='Binding/*',`level`=2,`sort`=7,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='binding_qq';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'binding_qq','QQ小程序','QQ小程序','Binding/qq','','Binding/*',0,2,7,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='binding_qq');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='pingtai' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='手机H5',`fullname`='手机H5',`path`='Binding/h5',`icon`='',`auth_data`='Binding/*',`level`=2,`sort`=8,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='binding_h5';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'binding_h5','手机H5','手机H5','Binding/h5','','Binding/*',0,2,8,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='binding_h5');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='pingtai' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='手机APP',`fullname`='手机APP',`path`='Binding/app',`icon`='',`auth_data`='Binding/*',`level`=2,`sort`=9,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='binding_app';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'binding_app','手机APP','手机APP','Binding/app','','Binding/*',0,2,9,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='binding_app');
UPDATE `ddwx_admin_menu` SET `pid`=0,`name`='系统',`fullname`='系统设置',`path`='',`icon`='my-icon my-icon-sysset',`auth_data`='',`level`=1,`sort`=9,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='system';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT 0,'system','系统','系统设置','','my-icon my-icon-sysset','',0,1,9,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='system');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='system' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='系统配置',`fullname`='系统配置',`path`='Backstage/sysset',`icon`='',`auth_data`='Backstage/sysset',`level`=2,`sort`=1,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='backstage_sysset';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'backstage_sysset','系统配置','系统配置','Backstage/sysset','','Backstage/sysset',0,2,1,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='backstage_sysset');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='system' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='提示音配置',`fullname`='提示音配置',`path`='Backstage/setorder',`icon`='',`auth_data`='Backstage/setorder',`level`=2,`sort`=3,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='backstage_setorder';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'backstage_setorder','提示音配置','提示音配置','Backstage/setorder','','Backstage/setorder',0,2,3,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='backstage_setorder');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='system' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='提示订单处理',`fullname`='提示订单处理',`path`='Backstage/orderchuli',`icon`='',`auth_data`='Backstage/orderchuli',`level`=2,`sort`=4,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='backstage_orderchuli';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'backstage_orderchuli','提示订单处理','提示订单处理','Backstage/orderchuli','','Backstage/orderchuli',0,2,4,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='backstage_orderchuli');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='system' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='门店管理',`fullname`='门店管理',`path`='Mendian/index',`icon`='',`auth_data`='Mendian/*',`level`=2,`sort`=5,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='mendian_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'mendian_index','门店管理','门店管理','Mendian/index','','Mendian/*',0,2,5,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='mendian_index');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='system' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='素材中心',`fullname`='素材中心',`path`='MaterialCenter/index',`icon`='',`auth_data`='Upload/*',`level`=2,`sort`=6,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='materialcenter_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'materialcenter_index','素材中心','素材中心','MaterialCenter/index','','Upload/*',0,2,6,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='materialcenter_index');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='system' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='管理员列表',`fullname`='管理员列表',`path`='User/index',`icon`='',`auth_data`='User/*,UserGroup/*',`level`=2,`sort`=7,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='user_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'user_index','管理员列表','管理员列表','User/index','','User/*,UserGroup/*',0,2,7,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='user_index');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='system' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='配送方式',`fullname`='配送方式',`path`='Freight/index',`icon`='',`auth_data`='Freight/*',`level`=2,`sort`=8,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='freight_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'freight_index','配送方式','配送方式','Freight/index','','Freight/*',0,2,8,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='freight_index');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='system' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='小票打印机',`fullname`='小票打印机',`path`='Wifiprint/index',`icon`='',`auth_data`='Wifiprint/*',`level`=2,`sort`=9,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='wifiprint_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'wifiprint_index','小票打印机','小票打印机','Wifiprint/index','','Wifiprint/*',0,2,9,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='wifiprint_index');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='system' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='支付宝提现',`fullname`='支付宝提现',`path`='Money/alipay',`icon`='',`auth_data`='Money/*',`level`=2,`sort`=10,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='money_alipay';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'money_alipay','支付宝提现','支付宝提现','Money/alipay','','Money/*',0,2,10,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='money_alipay');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='system' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='短信设置',`fullname`='短信设置',`path`='Sms/set',`icon`='',`auth_data`='Sms/*',`level`=2,`sort`=11,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='sms_set';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'sms_set','短信设置','短信设置','Sms/set','','Sms/*',0,2,11,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='sms_set');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='system' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='操作日志',`fullname`='操作日志',`path`='Backstage/plog',`icon`='',`auth_data`='Backstage/plog',`level`=2,`sort`=12,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='backstage_plog';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'backstage_plog','操作日志','操作日志','Backstage/plog','','Backstage/plog',0,2,12,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='backstage_plog');
SET @pid := (SELECT `id` FROM `ddwx_admin_menu` WHERE `key`='system' ORDER BY `id` ASC LIMIT 1);
UPDATE `ddwx_admin_menu` SET `pid`=IFNULL(@pid,0),`name`='学习中心',`fullname`='学习中心',`path`='HelpCenterProgress/index',`icon`='',`auth_data`='HelpCenterProgress/*',`level`=2,`sort`=13,`status`=1,`is_hidden`=0,`merchant_visible`=1,`tuanzhang_visible`=1,`params`='',`updatetime`=@menu_sync_now WHERE `key`='helpcenterprogress_index';
INSERT INTO `ddwx_admin_menu` (`pid`,`key`,`name`,`fullname`,`path`,`icon`,`auth_data`,`auth_mode`,`level`,`sort`,`status`,`is_hidden`,`can_promote`,`admin_only`,`merchant_visible`,`tuanzhang_visible`,`console_visible`,`platform_limit`,`plugin_name`,`params`,`createtime`,`updatetime`) SELECT IFNULL(@pid,0),'helpcenterprogress_index','学习中心','学习中心','HelpCenterProgress/index','','HelpCenterProgress/*',0,2,13,1,0,0,0,1,1,0,'','','',@menu_sync_now,@menu_sync_now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ddwx_admin_menu` WHERE `key`='helpcenterprogress_index');
UPDATE `ddwx_admin_system_config` SET `value`='1' WHERE `key`='menu_use_database';
SET FOREIGN_KEY_CHECKS=1;
