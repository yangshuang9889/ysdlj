-- 2026-05-04: 移除email字段（不再需要邮箱功能）
﻿-- ============================================================
-- 杨爽短链接系统 - MySQL 8.0.12 完整数据库结构
-- 针对 MySQL 8.0.12 优化，移除非支持特性
-- 不支持：函数索引 (8.0.13+)、CHECK 约束 (8.0.16+)
-- ============================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- 用户表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` varchar(64) NOT NULL COMMENT '用户名',
    `password` varchar(255) NOT NULL COMMENT '密码hash',
  `nickname` varchar(64) DEFAULT '' COMMENT '昵称',
  `avatar` varchar(255) DEFAULT '' COMMENT '头像URL',
  `role` tinyint UNSIGNED NOT NULL DEFAULT 2 COMMENT '1=超级管理员 2=普通用户 3=运营员',
  `status` tinyint UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=禁用 1=正常',
  `invite_code` varchar(32) DEFAULT '' COMMENT '邀请码',
  `daily_limit` int UNSIGNED NOT NULL DEFAULT 100 COMMENT '每日访问限额',
  `daily_used` int UNSIGNED NOT NULL DEFAULT 0 COMMENT '今日已使用次数',
  `daily_reset_date` date DEFAULT NULL COMMENT '上次重置日期',
  `no_ad_quota` int UNSIGNED NOT NULL DEFAULT 0 COMMENT '免广告额度',
  `api_token` varchar(64) DEFAULT '' COMMENT 'API Token',
  `last_login_at` datetime DEFAULT NULL,
  `last_login_ip` varchar(64) DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`),
    KEY `idx_role` (`role`),
  KEY `idx_status` (`status`),
  KEY `idx_api_token` (`api_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户表';

-- ----------------------------
-- 域名管理表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `domains` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `domain` varchar(255) NOT NULL COMMENT '域名',
  `name` varchar(100) DEFAULT '' COMMENT '域名备注名',
  `is_default` tinyint UNSIGNED NOT NULL DEFAULT 0 COMMENT '1=默认域名',
  `status` tinyint UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=禁用 1=正常',
  `parse_type` varchar(20) DEFAULT 'cname' COMMENT 'cname/a - DNS解析类型',
  `parse_value` varchar(255) DEFAULT '' COMMENT '解析值（CNAME或IP）',
  `parse_status` varchar(20) DEFAULT 'pending' COMMENT 'pending/ok/failed',
  `parse_msg` varchar(255) DEFAULT '' COMMENT '解析检测信息',
  `link_count` int UNSIGNED NOT NULL DEFAULT 0 COMMENT '该域名下的链接数',
  `created_by` int UNSIGNED NOT NULL DEFAULT 1 COMMENT '创建者ID',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_domain` (`domain`),
  KEY `idx_status` (`status`),
  KEY `idx_is_default` (`is_default`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='域名管理表';

-- ----------------------------
-- 链接分组表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `link_groups` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建者ID，0=系统分组',
  `name` varchar(64) NOT NULL COMMENT '分组名',
  `color` varchar(16) DEFAULT '#409EFF' COMMENT '标签颜色',
  `sort` int NOT NULL DEFAULT 0 COMMENT '排序',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='链接分组表';

-- ----------------------------
-- 短链接表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `links` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `short_code` varchar(64) NOT NULL COMMENT '短链后缀（支持中文）',
  `original_url` text NOT NULL COMMENT '原始长链接',
  `user_id` int UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建者ID，0=游客',
  `domain_id` int UNSIGNED NOT NULL DEFAULT 0 COMMENT '绑定域名ID，0=使用默认域名',
  `title` varchar(255) DEFAULT '' COMMENT '链接标题/备注',
  `group_id` int UNSIGNED NOT NULL DEFAULT 0 COMMENT '分组ID，0=未分组',
  `domain` varchar(128) NOT NULL DEFAULT '' COMMENT '绑定域名（冗余字段，便于查询）',
  `status` tinyint UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=禁用 1=正常 2=过期',
  `password` varchar(64) DEFAULT '' COMMENT '访问密码，空=无需密码',
  `max_visits` int UNSIGNED NOT NULL DEFAULT 0 COMMENT '最大访问次数，0=不限',
  `expire_at` datetime DEFAULT NULL COMMENT '过期时间，NULL=永久',
  `ad_id` int UNSIGNED NOT NULL DEFAULT 0 COMMENT '绑定广告ID，0=用全局广告',
  `no_ad` tinyint UNSIGNED NOT NULL DEFAULT 0 COMMENT '1=免广告白名单',
  `allow_ips` text DEFAULT NULL COMMENT 'IP白名单，逗号分隔',
  `pv` bigint UNSIGNED NOT NULL DEFAULT 0 COMMENT '总访问次数PV',
  `uv` bigint UNSIGNED NOT NULL DEFAULT 0 COMMENT '独立访客UV',
  `ip_count` bigint UNSIGNED NOT NULL DEFAULT 0 COMMENT '独立IP数',
  `is_deleted` tinyint UNSIGNED NOT NULL DEFAULT 0 COMMENT '1=已删除（回收站）',
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_short_code` (`short_code`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_domain_id` (`domain_id`),
  KEY `idx_status` (`status`),
  KEY `idx_group_id` (`group_id`),
  KEY `idx_expire_at` (`expire_at`),
  KEY `idx_is_deleted` (`is_deleted`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='短链接表';

-- ----------------------------
-- 访问日志表
-- 针对 MySQL 8.0.12 优化（移除函数索引）
-- ----------------------------
CREATE TABLE IF NOT EXISTS `access_logs` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `link_id` bigint UNSIGNED NOT NULL COMMENT '短链ID',
  `short_code` varchar(64) NOT NULL COMMENT '短链后缀',
  `ip` varchar(64) NOT NULL DEFAULT '' COMMENT '访问IP',
  `ip_location` varchar(128) DEFAULT '' COMMENT 'IP归属地',
  `province` varchar(32) DEFAULT '' COMMENT '省份',
  `city` varchar(32) DEFAULT '' COMMENT '城市',
  `country` varchar(32) DEFAULT '' COMMENT '国家',
  `device_type` varchar(16) DEFAULT '' COMMENT 'mobile/desktop/tablet',
  `os` varchar(32) DEFAULT '' COMMENT '操作系统',
  `browser` varchar(64) DEFAULT '' COMMENT '浏览器',
  `referer` varchar(512) DEFAULT '' COMMENT '来源',
  `user_agent` varchar(512) DEFAULT '' COMMENT 'UA',
  `is_bot` tinyint UNSIGNED NOT NULL DEFAULT 0 COMMENT '1=机器人/爬虫',
  `stay_seconds` int UNSIGNED NOT NULL DEFAULT 0 COMMENT '停留秒数',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_date` date GENERATED ALWAYS AS (DATE(`created_at`)) STORED COMMENT '日期虚拟列，用于加速日期查询',
  PRIMARY KEY (`id`),
  KEY `idx_link_id` (`link_id`),
  KEY `idx_short_code` (`short_code`),
  KEY `idx_ip` (`ip`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_is_bot` (`is_bot`),
  KEY `idx_device_type` (`device_type`),
  KEY `idx_created_date` (`created_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='访问日志表';

-- ----------------------------
-- 每日统计汇总表（加速查询）
-- ----------------------------
CREATE TABLE IF NOT EXISTS `link_stats_daily` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `link_id` bigint UNSIGNED NOT NULL,
  `stat_date` date NOT NULL COMMENT '统计日期',
  `pv` bigint UNSIGNED NOT NULL DEFAULT 0,
  `uv` bigint UNSIGNED NOT NULL DEFAULT 0,
  `ip_count` bigint UNSIGNED NOT NULL DEFAULT 0,
  `bot_count` bigint UNSIGNED NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_link_date` (`link_id`, `stat_date`),
  KEY `idx_stat_date` (`stat_date`),
  KEY `idx_link_id` (`link_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='每日统计汇总表';

-- ----------------------------
-- 广告表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `ads` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL COMMENT '广告名称',
  `type` varchar(16) NOT NULL DEFAULT 'countdown' COMMENT 'countdown/banner/popup/landing',
  `content` text NOT NULL COMMENT '广告内容（HTML/图文/代码）',
  `image_url` varchar(512) DEFAULT '' COMMENT '广告图片',
  `link_url` varchar(512) DEFAULT '' COMMENT '广告跳转链接',
  `countdown` int UNSIGNED NOT NULL DEFAULT 5 COMMENT '倒计时秒数',
  `skip_mode` varchar(16) NOT NULL DEFAULT 'auto' COMMENT 'auto=自动跳转 manual=手动点击',
  `btn_text` varchar(32) DEFAULT '跳过广告' COMMENT '跳过按钮文字',
  `btn_style` json DEFAULT NULL COMMENT '按钮样式JSON',
  `is_global` tinyint UNSIGNED NOT NULL DEFAULT 0 COMMENT '1=全局广告',
  `status` tinyint UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=禁用 1=正常',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_type` (`type`),
  KEY `idx_is_global` (`is_global`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='广告表';

-- ----------------------------
-- 系统配置表
-- 统一使用 group_name 字段
-- ----------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_name` varchar(32) DEFAULT 'general' COMMENT '配置分组',
  `key` varchar(64) NOT NULL COMMENT '配置键',
  `value` text COMMENT '配置值',
  `type` varchar(16) DEFAULT 'string' COMMENT 'string/json/bool/int',
  `label` varchar(128) DEFAULT '' COMMENT '配置说明',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_key` (`key`),
  KEY `idx_group_name` (`group_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统配置表';

-- ----------------------------
-- 邀请码表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `invite_codes` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(32) NOT NULL COMMENT '邀请码',
  `created_by` int UNSIGNED NOT NULL DEFAULT 1 COMMENT '创建者ID',
  `used_by` int UNSIGNED DEFAULT NULL COMMENT '使用者ID',
  `used_at` datetime DEFAULT NULL COMMENT '使用时间',
  `max_uses` int UNSIGNED NOT NULL DEFAULT 1 COMMENT '最大使用次数',
  `use_count` int UNSIGNED NOT NULL DEFAULT 0 COMMENT '已使用次数',
  `expire_at` datetime DEFAULT NULL COMMENT '过期时间',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_used_by` (`used_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='邀请码表';

-- ----------------------------
-- 操作日志表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `operation_logs` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作用户ID',
  `action` varchar(64) NOT NULL COMMENT '操作类型',
  `target_type` varchar(32) DEFAULT '' COMMENT '操作对象类型',
  `target_id` varchar(64) DEFAULT '' COMMENT '操作对象ID',
  `description` varchar(512) DEFAULT '' COMMENT '操作描述',
  `ip` varchar(64) DEFAULT '' COMMENT '操作IP',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_target` (`target_type`, `target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='操作日志表';

-- ----------------------------
-- 登录日志表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `login_logs` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int UNSIGNED DEFAULT NULL COMMENT '用户ID，NULL=登录失败',
  `username` varchar(64) NOT NULL COMMENT '尝试登录的用户名',
  `ip` varchar(64) DEFAULT '' COMMENT '登录IP',
  `location` varchar(128) DEFAULT '' COMMENT 'IP归属地',
  `user_agent` varchar(512) DEFAULT '' COMMENT 'User Agent',
  `status` tinyint UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=成功 0=失败',
  `fail_reason` varchar(255) DEFAULT '' COMMENT '失败原因',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_username` (`username`),
  KEY `idx_ip` (`ip`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='登录日志表';

-- ----------------------------
-- IP黑名单表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `ip_blacklist` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip_range` varchar(64) NOT NULL COMMENT 'IP或CIDR',
  `reason` varchar(255) DEFAULT '' COMMENT '封禁原因',
  `expire_at` datetime DEFAULT NULL COMMENT 'NULL=永久封禁',
  `created_by` int UNSIGNED NOT NULL DEFAULT 1 COMMENT '操作人ID',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ip_range` (`ip_range`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_expire_at` (`expire_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IP黑名单表';

-- ----------------------------
-- 违规域名黑名单表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `domain_blacklist` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `domain` varchar(255) NOT NULL COMMENT '违规域名',
  `reason` varchar(64) DEFAULT '' COMMENT '赌博/诈骗/色情等',
  `created_by` int UNSIGNED NOT NULL DEFAULT 1 COMMENT '操作人ID',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_domain` (`domain`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='违规域名黑名单表';

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- 初始数据
-- ============================================================

-- 管理员账号（密码请在安装时重新设置）
-- 默认密码 hash: admin123 (使用 password_hash 生成)
INSERT INTO `users` ( `username`,  `password`, `nickname`, `role`, `status`) VALUES
('admin', '$2y$12$YourHashHere', '超级管理员', 1, 1);

-- 系统配置
INSERT INTO `settings` (`group_name`, `key`, `value`, `type`, `label`) VALUES
('general', 'site_name', '杨爽短链接系统', 'string', '网站名称'),
('general', 'site_logo', '', 'string', '网站LOGO'),
('general', 'site_icp', '', 'string', '备案号'),
('general', 'site_seo_keywords', '短链接,短网址,链接缩短', 'string', 'SEO关键词'),
('general', 'site_seo_desc', '专业短链接生成平台，快速生成短网址，支持访问统计', 'string', 'SEO描述'),
('general', 'default_domain', '', 'string', '默认短链域名'),
('general', 'short_code_length', '6', 'int', '随机短码长度'),
('general', 'default_expire_days', '0', 'int', '默认有效天数（0=永久）'),
('general', 'link_public_stat', '1', 'bool', '公开统计数据'),
('auth', 'register_mode', 'open', 'string', '注册模式: open/invite/closed'),
('auth', 'guest_create', '1', 'bool', '游客可免注册生成'),
('security', 'ip_rate_limit', '10', 'int', '同IP每分钟生成限制'),
('ad', 'global_ad_id', '0', 'int', '全局广告ID'),
('performance', 'cache_ttl', '300', 'int', '短链缓存秒数'),
('api', 'api_open', '1', 'bool', '开放API接口'),
('api', 'query_public', '1', 'bool', '公开查询开关'),
('site', 'show_icp', '1', 'bool', '显示备案号'),
('site', 'site_seo_title', '', 'string', 'SEO标题'),
('site', 'default_ad_duration', '5', 'int', '默认广告时长(秒)'),
('site', 'allow_register', '1', 'bool', '允许注册');

-- 默认广告
INSERT INTO `ads` (`name`, `type`, `content`, `countdown`, `skip_mode`, `btn_text`, `is_global`, `status`) VALUES
('默认全局广告', 'countdown', '<div class="ad-default"><p>广告位招租，请在后台配置广告内容</p></div>', 5, 'auto', '跳过广告（{countdown}秒）', 1, 1);

-- 默认链接分组
INSERT INTO `link_groups` (`user_id`, `name`, `color`) VALUES
(0, '默认分组', '#409EFF'),
(0, '推广链接', '#67C23A'),
(0, '测试链接', '#E6A23C');

-- ============================================================
-- 创建存储过程：重置用户每日使用量
-- ============================================================
DELIMITER $$

CREATE PROCEDURE IF NOT EXISTS `sp_reset_daily_usage`()
BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE cur_date DATE;
    SET cur_date = CURDATE();
    
    -- 重置昨日或更早的数据
    UPDATE `users` 
    SET `daily_used` = 0, `daily_reset_date` = cur_date
    WHERE `daily_reset_date` IS NULL OR `daily_reset_date` < cur_date;
END$$

DELIMITER ;

-- ============================================================
-- 创建事件：每天凌晨重置每日使用量
-- 需要开启事件调度器: SET GLOBAL event_scheduler = ON;
-- ============================================================
CREATE EVENT IF NOT EXISTS `evt_daily_reset`
ON SCHEDULE EVERY 1 DAY
STARTS TIMESTAMP(CURDATE(), '00:00:01')
DO
BEGIN
    CALL sp_reset_daily_usage();
END;

-- ============================================================
-- MySQL 8.0.12 注意事项
-- ============================================================

-- 1. 不支持 CHECK 约束，需要在应用层进行数据验证
-- 2. 不支持函数索引，使用 GENERATED COLUMNS 替代（已在 access_logs 表中实现）
-- 3. 存储过程和事件需要相应权限
-- 4. 开启事件调度器：SET GLOBAL event_scheduler = ON;

-- ============================================================
-- 完成
-- ============================================================
SELECT 'Database schema for MySQL 8.0.12 installed successfully!' AS Message;
