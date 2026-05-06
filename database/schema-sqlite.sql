-- 2026-05-04: 移除email字段（不再需要邮箱功能）
-- ============================================================
-- 短链系统 SQLite 数据库结构 v1.0
-- 用于单镜像部署（无需 MySQL）
-- ============================================================

-- 用户表
CREATE TABLE IF NOT EXISTS `users` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `username` TEXT NOT NULL UNIQUE,
    `password` TEXT NOT NULL,
  `nickname` TEXT DEFAULT '',
  `avatar` TEXT DEFAULT '',
  `role` INTEGER NOT NULL DEFAULT 2,
  `status` INTEGER NOT NULL DEFAULT 1,
  `invite_code` TEXT DEFAULT '',
  `daily_limit` INTEGER NOT NULL DEFAULT 100,
  `daily_used` INTEGER NOT NULL DEFAULT 0,
  `daily_reset_date` TEXT,
  `no_ad_quota` INTEGER NOT NULL DEFAULT 0,
  `api_token` TEXT DEFAULT '',
  `last_login_at` TEXT,
  `last_login_ip` TEXT DEFAULT '',
  `created_at` TEXT NOT NULL DEFAULT (datetime('now')),
  `updated_at` TEXT NOT NULL DEFAULT (datetime('now'))
);

-- 短链接表
CREATE TABLE IF NOT EXISTS `links` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `short_code` TEXT NOT NULL UNIQUE,
  `original_url` TEXT NOT NULL,
  `user_id` INTEGER NOT NULL DEFAULT 0,
  `domain_id` INTEGER DEFAULT 0,
  `title` TEXT DEFAULT '',
  `group_id` INTEGER NOT NULL DEFAULT 0,
  `domain` TEXT NOT NULL DEFAULT '',
  `status` INTEGER NOT NULL DEFAULT 1,
  `password` TEXT DEFAULT '',
  `max_visits` INTEGER NOT NULL DEFAULT 0,
  `expire_at` TEXT,
  `ad_id` INTEGER NOT NULL DEFAULT 0,
  `no_ad` INTEGER NOT NULL DEFAULT 0,
  `allow_ips` TEXT,
  `pv` INTEGER NOT NULL DEFAULT 0,
  `uv` INTEGER NOT NULL DEFAULT 0,
  `ip_count` INTEGER NOT NULL DEFAULT 0,
  `is_deleted` INTEGER NOT NULL DEFAULT 0,
  `deleted_at` TEXT,
  `created_at` TEXT NOT NULL DEFAULT (datetime('now')),
  `updated_at` TEXT NOT NULL DEFAULT (datetime('now'))
);

-- 访问日志表
CREATE TABLE IF NOT EXISTS `access_logs` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `link_id` INTEGER NOT NULL,
  `short_code` TEXT NOT NULL,
  `ip` TEXT NOT NULL DEFAULT '',
  `ip_location` TEXT DEFAULT '',
  `province` TEXT DEFAULT '',
  `city` TEXT DEFAULT '',
  `country` TEXT DEFAULT '',
  `device_type` TEXT DEFAULT '',
  `os` TEXT DEFAULT '',
  `browser` TEXT DEFAULT '',
  `referer` TEXT DEFAULT '',
  `user_agent` TEXT DEFAULT '',
  `is_bot` INTEGER NOT NULL DEFAULT 0,
  `stay_seconds` INTEGER NOT NULL DEFAULT 0,
  `created_at` TEXT NOT NULL DEFAULT (datetime('now'))
);

-- 每日统计汇总表
CREATE TABLE IF NOT EXISTS `link_stats_daily` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `link_id` INTEGER NOT NULL,
  `stat_date` TEXT NOT NULL,
  `pv` INTEGER NOT NULL DEFAULT 0,
  `uv` INTEGER NOT NULL DEFAULT 0,
  `ip_count` INTEGER NOT NULL DEFAULT 0,
  `bot_count` INTEGER NOT NULL DEFAULT 0
);

-- 广告表
CREATE TABLE IF NOT EXISTS `ads` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `name` TEXT NOT NULL,
  `type` TEXT NOT NULL DEFAULT 'countdown',
  `content` TEXT NOT NULL,
  `image_url` TEXT DEFAULT '',
  `link_url` TEXT DEFAULT '',
  `countdown` INTEGER NOT NULL DEFAULT 5,
  `skip_mode` TEXT NOT NULL DEFAULT 'auto',
  `btn_text` TEXT DEFAULT '跳过广告',
  `btn_style` TEXT DEFAULT '',
  `is_global` INTEGER NOT NULL DEFAULT 0,
  `status` INTEGER NOT NULL DEFAULT 1,
  `created_at` TEXT NOT NULL DEFAULT (datetime('now')),
  `updated_at` TEXT NOT NULL DEFAULT (datetime('now'))
);

-- 链接分组表
CREATE TABLE IF NOT EXISTS `link_groups` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `user_id` INTEGER NOT NULL DEFAULT 0,
  `name` TEXT NOT NULL,
  `color` TEXT DEFAULT '#409EFF',
  `sort` INTEGER NOT NULL DEFAULT 0,
  `created_at` TEXT NOT NULL DEFAULT (datetime('now'))
);

-- 系统配置表
CREATE TABLE IF NOT EXISTS `settings` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `key` TEXT NOT NULL UNIQUE,
  `value` TEXT,
  `type` TEXT DEFAULT 'string',
  `label` TEXT DEFAULT '',
  `group_name` TEXT DEFAULT 'general',
  `updated_at` TEXT NOT NULL DEFAULT (datetime('now'))
);

-- 邀请码表
CREATE TABLE IF NOT EXISTS `invite_codes` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `code` TEXT NOT NULL UNIQUE,
  `created_by` INTEGER NOT NULL DEFAULT 1,
  `used_by` INTEGER,
  `used_at` TEXT,
  `max_uses` INTEGER NOT NULL DEFAULT 1,
  `use_count` INTEGER NOT NULL DEFAULT 0,
  `expire_at` TEXT,
  `created_at` TEXT NOT NULL DEFAULT (datetime('now'))
);

-- 操作日志表
CREATE TABLE IF NOT EXISTS `operation_logs` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `user_id` INTEGER NOT NULL DEFAULT 0,
  `action` TEXT NOT NULL,
  `target_type` TEXT DEFAULT '',
  `target_id` TEXT DEFAULT '',
  `description` TEXT DEFAULT '',
  `ip` TEXT DEFAULT '',
  `created_at` TEXT NOT NULL DEFAULT (datetime('now'))
);

-- IP黑名单表
CREATE TABLE IF NOT EXISTS `ip_blacklist` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `ip_range` TEXT NOT NULL,
  `reason` TEXT DEFAULT '',
  `expire_at` TEXT,
  `created_by` INTEGER NOT NULL DEFAULT 1,
  `created_at` TEXT NOT NULL DEFAULT (datetime('now'))
);

-- 违规域名表
CREATE TABLE IF NOT EXISTS `domain_blacklist` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `domain` TEXT NOT NULL UNIQUE,
  `reason` TEXT DEFAULT '',
  `created_at` TEXT NOT NULL DEFAULT (datetime('now'))
);

-- 域名管理表
CREATE TABLE IF NOT EXISTS `domains` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `domain` TEXT NOT NULL UNIQUE,
  `name` TEXT DEFAULT '',
  `is_default` INTEGER NOT NULL DEFAULT 0,
  `status` INTEGER NOT NULL DEFAULT 1,
  `parse_type` TEXT DEFAULT 'cname',
  `parse_value` TEXT DEFAULT '',
  `parse_status` TEXT DEFAULT 'pending',
  `link_count` INTEGER NOT NULL DEFAULT 0,
  `created_by` INTEGER DEFAULT 0,
  `created_at` TEXT NOT NULL DEFAULT (datetime('now')),
  `updated_at` TEXT NOT NULL DEFAULT (datetime('now'))
);

-- 登录日志表
CREATE TABLE IF NOT EXISTS `login_logs` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `user_id` INTEGER NOT NULL DEFAULT 0,
  `username` TEXT DEFAULT '',
  `ip` TEXT DEFAULT '',
  `location` TEXT DEFAULT '',
  `user_agent` TEXT DEFAULT '',
  `status` INTEGER NOT NULL DEFAULT 0,
  `result` TEXT DEFAULT '',
  `fail_reason` TEXT DEFAULT '',
  `created_at` TEXT NOT NULL DEFAULT (datetime('now'))
);

-- 创建索引
CREATE INDEX IF NOT EXISTS idx_links_user_id ON links(user_id);
CREATE INDEX IF NOT EXISTS idx_links_status ON links(status);
CREATE INDEX IF NOT EXISTS idx_links_short_code ON links(short_code);
CREATE INDEX IF NOT EXISTS idx_access_logs_link_id ON access_logs(link_id);
CREATE INDEX IF NOT EXISTS idx_access_logs_created_at ON access_logs(created_at);
CREATE INDEX IF NOT EXISTS idx_stats_daily_link_date ON link_stats_daily(link_id, stat_date);

-- 插入初始管理员账号 (密码: admin123)
INSERT INTO `users` ( `username`,  `password`, `nickname`, `role`, `status`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '超级管理员', 1, 1);

-- 插入系统配置
INSERT INTO `settings` (`key`, `value`, `type`, `label`, `group_name`) VALUES
('site_name', '杨爽短链接系统', 'string', '网站名称', 'general'),
('site_logo', '', 'string', '网站LOGO', 'general'),
('icp', '', 'string', '备案号', 'general'),
('show_icp', '1', 'bool', '显示备案号', 'general'),
('site_seo_title', '杨爽短链接系统', 'string', 'SEO标题', 'general'),
('site_seo_keywords', '短链接,短网址,链接缩短', 'string', 'SEO关键词', 'general'),
('site_seo_desc', '专业短链接生成平台，快速生成短网址，支持访问统计', 'string', 'SEO描述', 'general'),
('default_domain', '', 'string', '默认短链域名', 'general'),
('default_ad_duration', '5', 'int', '默认广告时长（秒）', 'general'),
('register_mode', 'open', 'string', '注册模式: open/invite/closed', 'auth'),
('allow_register', '1', 'bool', '允许注册', 'auth'),
('guest_create', '1', 'bool', '游客可免注册生成', 'auth'),
('api_open', '1', 'bool', '开放API接口', 'general'),
('query_public', '1', 'bool', '公开查询开关', 'general'),
('ip_rate_limit', '10', 'int', '同IP每分钟生成限制', 'security'),
('global_ad_id', '0', 'int', '全局广告ID', 'ad'),
('cache_ttl', '300', 'int', '短链缓存秒数', 'performance'),
('short_code_length', '6', 'int', '随机短码长度', 'general'),
('default_expire_days', '0', 'int', '默认有效天数（0=永久）', 'general'),
('link_public_stat', '1', 'bool', '公开统计数据', 'general');

-- 插入默认广告
INSERT INTO `ads` (`name`, `type`, `content`, `countdown`, `skip_mode`, `btn_text`, `is_global`, `status`) VALUES
('默认全局广告', 'countdown', '<div class="ad-default"><p>广告位招租，请在后台配置广告内容</p></div>', 5, 'auto', '跳过广告（{countdown}秒）', 1, 1);

-- 插入默认分组
INSERT INTO `link_groups` (`user_id`, `name`, `color`) VALUES
(0, '默认分组', '#409EFF'),
(0, '推广链接', '#67C23A'),
(0, '测试链接', '#E6A23C');
