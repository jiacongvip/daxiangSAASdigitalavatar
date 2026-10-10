-- 初始化数据库
-- 此文件会在 MySQL 首次启动时先创建数据库，再由 20-tpdaxiangshop.sql 导入整库数据

CREATE DATABASE IF NOT EXISTS tpdaxiangshop DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

USE tpdaxiangshop;

-- 设置时区
SET time_zone = '+8:00';


