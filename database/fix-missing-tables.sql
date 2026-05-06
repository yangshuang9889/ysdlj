-- 修复缺失的数据表
-- 在 1Panel 的 MySQL 中执行此脚本

-- 每日统计表（如果缺失）
CREATE TABLE IF NOT EXISTS `link_stats_daily` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `link_id` INT UNSIGNED NOT NULL,
  `stat_date` DATE NOT NULL,
  `pv` INT UNSIGNED DEFAULT 0,
  `uv` INT UNSIGNED DEFAULT 0,
  `ip_count` INT UNSIGNED DEFAULT 0,
  `bot_count` INT UNSIGNED DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_link_date` (`link_id`, `stat_date`),
  INDEX `idx_stat_date` (`stat_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='链接每日统计';

-- 域名表（如果缺失）
CREATE TABLE IF NOT EXISTS `domains` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `domain` VARCHAR(255) NOT NULL UNIQUE,
  `name` VARCHAR(100) DEFAULT '',
  `is_default` TINYINT DEFAULT 0,
  `status` TINYINT DEFAULT 1,
  `parse_type` VARCHAR(20) DEFAULT 'cname',
  `parse_value` VARCHAR(255) DEFAULT '',
  `parse_status` VARCHAR(20) DEFAULT 'pending',
  `parse_msg` VARCHAR(255) DEFAULT '',
  `link_count` INT UNSIGNED DEFAULT 0,
  `created_by` INT UNSIGNED DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_status` (`status`),
  INDEX `idx_is_default` (`is_default`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='域名管理';

-- 用户表字段补充（daily_used 和 daily_reset_date）
SET @dbname = DATABASE();
SET @tablename = 'users';
SET @columnname = 'daily_used';

SET @sql = CONCAT(
    'ALTER TABLE `', @tablename, '` ',
    'ADD COLUMN IF NOT EXISTS `daily_used` INT DEFAULT 0 COMMENT "今日已使用次数"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @columnname = 'daily_reset_date';
SET @sql = CONCAT(
    'ALTER TABLE `', @tablename, '` ',
    'ADD COLUMN IF NOT EXISTS `daily_reset_date` DATE DEFAULT NULL COMMENT "上次重置日期"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- links 表补充 domain_id 字段
SET @tablename = 'links';
SET @columnname = 'domain_id';
SET @sql = CONCAT(
    'ALTER TABLE `', @tablename, '` ',
    'ADD COLUMN IF NOT EXISTS `domain_id` INT UNSIGNED DEFAULT 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- login_logs 表补充 fail_reason 字段
SET @tablename = 'login_logs';
SET @columnname = 'fail_reason';
SET @sql = CONCAT(
    'ALTER TABLE `', @tablename, '` ',
    'ADD COLUMN IF NOT EXISTS `fail_reason` VARCHAR(255) DEFAULT \'\''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 插入默认设置（如果不存在）
INSERT IGNORE INTO `settings` (`group_name`, `key`, `value`) VALUES
('site', 'show_icp', '1'),
('site', 'site_seo_title', ''),
('site', 'default_ad_duration', '5'),
('site', 'allow_register', '1'),
('general', 'global_ad_id', '0'),
('general', 'api_open', '1');
