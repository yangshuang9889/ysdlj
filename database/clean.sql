-- 清空测试数据脚本
USE shortlink;

-- 清空日志表
TRUNCATE TABLE access_logs;
TRUNCATE TABLE operation_logs;
TRUNCATE TABLE login_logs;

-- 清空短链接（保留表结构）
TRUNCATE TABLE links;

-- 删除非管理员用户
DELETE FROM users WHERE username != 'admin';

-- 删除非示例广告
DELETE FROM ads WHERE id > 1;

-- 重置全局广告
UPDATE settings SET value='0' WHERE `key`='global_ad_id';

-- 重置管理员密码为默认（admin123）
UPDATE users SET password='$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' WHERE username='admin';

SELECT '数据库已清空！' AS status;
