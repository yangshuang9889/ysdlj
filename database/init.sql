-- 杨爽短链接系统 - 数据库初始化脚本
-- 执行时间: 首次启动时自动执行

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- 1. 用户表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT '用户ID',
  `username` VARCHAR(50) NOT NULL UNIQUE COMMENT '用户名',
  `password` VARCHAR(255) NOT NULL COMMENT '密码（bcrypt）',
  `nickname` VARCHAR(50) DEFAULT NULL COMMENT '昵称',
  `avatar` VARCHAR(255) DEFAULT NULL COMMENT '头像URL',
  `role` ENUM('admin','user') DEFAULT 'user' COMMENT '角色',
  `status` TINYINT DEFAULT 1 COMMENT '状态 1正常 0禁用',
  `daily_limit` INT DEFAULT 0 COMMENT '每日访问限额 0不限',
  `daily_used` INT DEFAULT 0 COMMENT '今日已使用次数',
  `daily_reset_date` DATE DEFAULT NULL COMMENT '上次重置日期',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login` DATETIME DEFAULT NULL COMMENT '最后登录时间',
  INDEX idx_username (`username`),
  INDEX idx_role (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户表';

-- ----------------------------
-- 2. 短链接表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `links` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT '链接ID',
  `user_id` INT UNSIGNED DEFAULT NULL COMMENT '创建者ID',
  `domain_id` INT UNSIGNED DEFAULT NULL COMMENT '域名ID',
  `short_code` VARCHAR(32) NOT NULL COMMENT '短码',
  `original_url` TEXT NOT NULL COMMENT '原始URL',
  `title` VARCHAR(200) DEFAULT NULL COMMENT '标题',
  `password` VARCHAR(255) DEFAULT NULL COMMENT '访问密码',
  `ad_id` INT UNSIGNED DEFAULT NULL COMMENT '广告ID 0不投放',
  `no_ad` TINYINT DEFAULT 0 COMMENT '是否禁用广告 1禁用',
  `expire_time` DATETIME DEFAULT NULL COMMENT '过期时间',
  `click_count` INT DEFAULT 0 COMMENT '点击数',
  `today_click` INT DEFAULT 0 COMMENT '今日点击',
  `status` TINYINT DEFAULT 1 COMMENT '状态 1启用 0禁用',
  `is_deleted` TINYINT DEFAULT 0 COMMENT '软删除 0未删 1已删',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL COMMENT '删除时间',
  UNIQUE KEY uk_code_domain (`short_code`, `domain_id`),
  INDEX idx_user (`user_id`),
  INDEX idx_domain (`domain_id`),
  INDEX idx_deleted (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='短链接表';

-- ----------------------------
-- 3. 访问日志表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `access_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `link_id` INT UNSIGNED NOT NULL COMMENT '链接ID',
  `ip` VARCHAR(45) NOT NULL COMMENT 'IP地址',
  `country` VARCHAR(50) DEFAULT NULL COMMENT '国家',
  `province` VARCHAR(50) DEFAULT NULL COMMENT '省份',
  `city` VARCHAR(50) DEFAULT NULL COMMENT '城市',
  `device` VARCHAR(50) DEFAULT NULL COMMENT '设备类型',
  `browser` VARCHAR(100) DEFAULT NULL COMMENT '浏览器',
  `os` VARCHAR(50) DEFAULT NULL COMMENT '操作系统',
  `referer` TEXT DEFAULT NULL COMMENT '来源',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_link (`link_id`),
  INDEX idx_ip (`ip`),
  INDEX idx_created (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='访问日志表';

-- ----------------------------
-- 4. 操作日志表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `operation_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED DEFAULT NULL COMMENT '操作用户',
  `username` VARCHAR(50) DEFAULT NULL COMMENT '用户名',
  `action` VARCHAR(50) NOT NULL COMMENT '操作类型',
  `target` VARCHAR(100) DEFAULT NULL COMMENT '操作对象',
  `detail` TEXT DEFAULT NULL COMMENT '详情',
  `ip` VARCHAR(45) DEFAULT NULL COMMENT 'IP地址',
  `user_agent` VARCHAR(500) DEFAULT NULL COMMENT 'UserAgent',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (`user_id`),
  INDEX idx_created (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='操作日志表';

-- ----------------------------
-- 5. 登录日志表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `login_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED DEFAULT NULL COMMENT '用户ID',
  `username` VARCHAR(50) DEFAULT NULL COMMENT '用户名',
  `status` TINYINT DEFAULT 1 COMMENT '登录状态 1成功 0失败',
  `ip` VARCHAR(45) DEFAULT NULL COMMENT 'IP地址',
  `location` VARCHAR(100) DEFAULT NULL COMMENT '登录地点',
  `device` VARCHAR(100) DEFAULT NULL COMMENT '设备信息',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (`user_id`),
  INDEX idx_created (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='登录日志表';

-- ----------------------------
-- 6. 广告表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `ads` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL COMMENT '广告名称',
  `type` ENUM('countdown','banner',' interstitial') DEFAULT 'countdown' COMMENT '广告类型',
  `content` TEXT NOT NULL COMMENT '广告内容/HTML',
  `countdown` INT DEFAULT 5 COMMENT '倒计时秒数',
  `skip_mode` TINYINT DEFAULT 0 COMMENT '跳过模式 0不可跳 1可跳',
  `btn_text` VARCHAR(50) DEFAULT '立即跳转' COMMENT '按钮文字',
  `target_url` VARCHAR(500) DEFAULT NULL COMMENT '跳转目标',
  `status` TINYINT DEFAULT 1 COMMENT '状态 1启用 0禁用',
  `sort_order` INT DEFAULT 0 COMMENT '排序',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='广告表';

-- ----------------------------
-- 7. 域名表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `domains` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `domain` VARCHAR(255) NOT NULL UNIQUE COMMENT '域名',
  `name` VARCHAR(100) DEFAULT NULL COMMENT '名称',
  `type` ENUM('primary','alias') DEFAULT 'alias' COMMENT '类型',
  `status` TINYINT DEFAULT 1 COMMENT '状态 1启用 0禁用',
  `is_default` TINYINT DEFAULT 0 COMMENT '是否默认 1默认',
  `record_type` VARCHAR(10) DEFAULT 'A' COMMENT 'DNS记录类型',
  `dns_checked_at` DATETIME DEFAULT NULL COMMENT 'DNS检查时间',
  `dns_ok` TINYINT DEFAULT 0 COMMENT 'DNS是否正常',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_domain (`domain`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='域名表';

-- ----------------------------
-- 8. 设置表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `type` VARCHAR(20) DEFAULT 'site' COMMENT '类型 site/ad/advance',
  `key` VARCHAR(100) NOT NULL UNIQUE COMMENT '配置键',
  `value` TEXT DEFAULT NULL COMMENT '配置值',
  `description` VARCHAR(255) DEFAULT NULL COMMENT '描述',
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_type (`type`),
  INDEX idx_key (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='设置表';

-- ----------------------------
-- 9. 黑名单表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `blacklists` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `type` ENUM('ip','domain') NOT NULL COMMENT '类型',
  `value` VARCHAR(255) NOT NULL COMMENT '值',
  `reason` VARCHAR(255) DEFAULT NULL COMMENT '原因',
  `created_by` INT UNSIGNED DEFAULT NULL COMMENT '创建人',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_type_value (`type`,`value`),
  INDEX idx_type (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='黑名单表';

-- ----------------------------
-- 10. 邀请码表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `invite_codes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(20) NOT NULL UNIQUE COMMENT '邀请码',
  `creator_id` INT UNSIGNED DEFAULT NULL COMMENT '创建者',
  `used_by` INT UNSIGNED DEFAULT NULL COMMENT '使用者',
  `used_at` DATETIME DEFAULT NULL COMMENT '使用时间',
  `status` TINYINT DEFAULT 1 COMMENT '状态 1可用 0已用',
  `expires_at` DATETIME DEFAULT NULL COMMENT '过期时间',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_code (`code`),
  INDEX idx_creator (`creator_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='邀请码表';

-- ----------------------------
-- 初始化数据
-- ----------------------------

-- 默认管理员账号 (密码: admin123)
INSERT INTO `users` (`username`, `password`, `nickname`, `role`, `status`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '管理员', 'admin', 1);

-- 默认站点设置
INSERT INTO `settings` (`type`, `key`, `value`, `description`) VALUES
('site', 'site_name', '杨爽短链接', '站点名称'),
('site', 'site_logo', '', '站点Logo'),
('site', 'site_icp', '', 'ICP备案号'),
('site', 'site_icp_link', '', 'ICP备案链接'),
('site', 'site_copyright', '© 2024 杨爽短链接', '版权信息'),
('ad', 'global_ad_id', '0', '全局广告ID'),
('ad', 'default_countdown', '5', '默认倒计时秒数');

-- 默认示例广告
INSERT INTO `ads` (`name`, `type`, `content`, `countdown`, `skip_mode`, `btn_text`, `status`) VALUES
('示例横幅广告', 'banner', '<div style="text-align:center;padding:20px;"><p>这是示例广告</p><a href="#" style="color:#1890ff;">了解更多</a></div>', 0, 1, '跳过广告', 1);

SET FOREIGN_KEY_CHECKS = 1;
