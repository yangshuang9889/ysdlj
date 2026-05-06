<?php
/**
 * 安装向导控制器
 */
class InstallController {

    /**
     * 检查安装状态
     */
    public function status(): void {
        // 检查是否已安装：数据库中是否有管理员账号（role=1）
        try {
            $hasAdmin = DB::count('users', 'role = 1') > 0;
            Response::success(['installed' => $hasAdmin]);
        } catch (Exception $e) {
            // 数据库连接失败或表不存在
            Response::success(['installed' => false, 'dbError' => true]);
        }
    }

    /**
     * 测试数据库连接
     */
    public function testConnection(): void {
        $data = Request::json();
        
        $host     = $data['host'] ?? 'localhost';
        $port     = (int)($data['port'] ?? 3306);
        $database = $data['database'] ?? '';
        $username = $data['username'] ?? 'root';
        $password = $data['password'] ?? '';

        if (empty($database)) {
            Response::error('请填写数据库名');
        }

        try {
            // 尝试连接数据库
            $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]);

            // 尝试选择数据库
            $pdo->exec("USE `{$database}`");
            
            // 检查表是否存在
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            
            Response::success([
                'connected' => true,
                'hasTables' => count($tables) > 0,
                'tables' => $tables,
            ], '数据库连接成功');
        } catch (PDOException $e) {
            Response::error('数据库连接失败：' . $e->getMessage());
        }
    }

    /**
     * 执行安装
     */
    public function setup(): void {
        $data = Request::json();
        
        $dbConfig = $data['db'] ?? [];
        $adminData = $data['admin'] ?? [];

        // 验证管理员数据
        $username = trim($adminData['username'] ?? '');
        $password = $adminData['password'] ?? '';

        if (empty($username) || strlen($username) < 3) {
            Response::error('管理员账号至少3个字符');
        }
        if (strlen($password) < 6) {
            Response::error('密码至少6个字符');
        }

        try {
            // 连接数据库
            $host     = $dbConfig['host'] ?? 'localhost';
            $port     = (int)($dbConfig['port'] ?? 3306);
            $database = $dbConfig['database'] ?? '';
            $user     = $dbConfig['username'] ?? 'root';
            $pass     = $dbConfig['password'] ?? '';

            $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            // 创建数据库（如果不存在）
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$database}`");

            // 创建数据表
            $this->createTables($pdo);

            // 创建管理员账号
            $this->createAdmin($pdo, $username, $password);

            // 初始化默认设置
            $this->initSettings($pdo);

            // 保存数据库配置到 config.php
            $this->saveDbConfig($host, $port, $database, $user, $pass);

            Response::success(['admin' => $username], '安装成功');
        } catch (Exception $e) {
            Response::error('安装失败：' . $e->getMessage());
        }
    }

    /**
     * 保存数据库配置到 config.php
     */
    private function saveDbConfig(string $host, int $port, string $database, string $user, string $pass): void {
        $configPath = __DIR__ . '/../../config/config.php';
        
        // 生成新的配置内容
        $configContent = <<<PHP
<?php
/**
 * 杨爽短链接系统 - 核心配置文件
 * 由安装向导自动生成
 */

// 数据库驱动：mysql / sqlite
\$db_driver = 'mysql';

return [
    // 数据库配置
    'db' => [
        'driver'   => \$db_driver,
        'host'     => '{$host}',
        'port'     => {$port},
        'dbname'   => '{$database}',
        'username' => '{$user}',
        'password' => '{$pass}',
        'charset'  => 'utf8mb4',
        // SQLite 数据库路径
        'database' => '',
        'options'  => [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ],
    ],

    // Redis缓存配置（可选，不配置则降级为文件缓存）
    'redis' => [
        'host'     => getenv('REDIS_HOST') ?: '127.0.0.1',
        'port'     => getenv('REDIS_PORT') ?: 6379,
        'password' => getenv('REDIS_PASS') ?: '',
        'database' => 0,
        'prefix'   => 'sl:',
    ],

    // JWT配置
    'jwt' => [
        'secret'     => bin2hex(random_bytes(32)),
        'expire'     => 86400 * 7,  // 7天
        'algorithm'  => 'HS256',
    ],

    // 应用配置
    'app' => [
        'url'        => getenv('APP_URL') ?: 'https://' . (\$_SERVER['HTTP_HOST'] ?? 'localhost'),
        'debug'      => false,
        'timezone'   => 'Asia/Shanghai',
        'upload_dir' => __DIR__ . '/../storage/uploads/',
        'log_dir'    => __DIR__ . '/../storage/logs/',
    ],

    // 短链配置
    'shortlink' => [
        'code_chars'   => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789',
        'code_length'  => 6,
        'max_custom_length' => 32,
        'reserved_codes' => ['api', 'admin', 'login', 'register', 'static', 'assets', 'favicon.ico', 'install'],
    ],

    // IP地理位置（使用纯真IP库或第三方API）
    'ip_geo' => [
        'driver' => 'none',   // qqwry / ip2region / none
        'db_path' => __DIR__ . '/../storage/qqwry.dat',
    ],

    // 系统版本信息
    'version' => [
        'current'   => '1.0.0',
        'name'      => '杨爽短链接系统',
        'author'    => '杨爽',
        'website'   => 'https://github.com/yangshuang9889/ysdj',
        'update_url' => 'https://api.github.com/repos/yangshuang9889/ysdj/releases/latest',
    ],
];
PHP;

        // 写入配置文件
        if (file_put_contents($configPath, $configContent) === false) {
            throw new Exception('无法写入配置文件，请检查目录权限');
        }
    }

    /**
     * 创建数据表
     */
    private function createTables(PDO $pdo): void {
        $sql = "
        -- 用户表
        CREATE TABLE IF NOT EXISTS `users` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
          `username` VARCHAR(50) NOT NULL UNIQUE,
          `password` VARCHAR(255) NOT NULL,
          `nickname` VARCHAR(50) DEFAULT '',
          `role` TINYINT DEFAULT 2 COMMENT '1=超管, 2=普通用户',
          `status` TINYINT DEFAULT 1 COMMENT '1=正常, 0=禁用',
          `daily_limit` INT DEFAULT 100 COMMENT '每日链接访问限额',
          `daily_used` INT DEFAULT 0 COMMENT '今日已使用次数',
          `daily_reset_date` DATE DEFAULT NULL COMMENT '上次重置日期',
          `no_ad_quota` INT DEFAULT 0 COMMENT '免广告额度',
          `last_login_at` DATETIME DEFAULT NULL,
          `last_login_ip` VARCHAR(45) DEFAULT NULL,
          `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
          `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX `idx_username` (`username`),
          INDEX `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户表';

        -- 短链接表
        CREATE TABLE IF NOT EXISTS `links` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
          `short_code` VARCHAR(32) NOT NULL UNIQUE,
          `original_url` TEXT NOT NULL,
          `user_id` INT UNSIGNED DEFAULT 0,
          `title` VARCHAR(255) DEFAULT '',
          `pv` INT UNSIGNED DEFAULT 0,
          `uv` INT UNSIGNED DEFAULT 0,
          `ip_count` INT UNSIGNED DEFAULT 0,
          `status` TINYINT DEFAULT 1 COMMENT '1=正常, 0=禁用, 2=已过期',
          `ad_id` INT UNSIGNED DEFAULT 0,
          `no_ad` TINYINT DEFAULT 0,
          `domain_id` INT UNSIGNED DEFAULT 0,
          `expire_at` DATETIME DEFAULT NULL,
          `is_deleted` TINYINT DEFAULT 0,
          `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
          `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX `idx_short_code` (`short_code`),
          INDEX `idx_user_id` (`user_id`),
          INDEX `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='短链接表';

        -- 访问日志表
        CREATE TABLE IF NOT EXISTS `access_logs` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
          `link_id` INT UNSIGNED NOT NULL,
          `short_code` VARCHAR(32) NOT NULL,
          `ip` VARCHAR(45) NOT NULL,
          `country` VARCHAR(50) DEFAULT '',
          `province` VARCHAR(50) DEFAULT '',
          `city` VARCHAR(50) DEFAULT '',
          `device_type` VARCHAR(20) DEFAULT 'other',
          `device` VARCHAR(100) DEFAULT '',
          `browser` VARCHAR(50) DEFAULT '',
          `os` VARCHAR(30) DEFAULT '',
          `is_bot` TINYINT DEFAULT 0,
          `referer` TEXT,
          `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
          INDEX `idx_link_id` (`link_id`),
          INDEX `idx_ip` (`ip`),
          INDEX `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='访问日志';

        -- 操作日志表
        CREATE TABLE IF NOT EXISTS `operation_logs` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
          `user_id` INT UNSIGNED DEFAULT 0,
          `action` VARCHAR(50) NOT NULL,
          `target_type` VARCHAR(30) DEFAULT '',
          `target_id` VARCHAR(50) DEFAULT '',
          `description` TEXT,
          `ip` VARCHAR(45) DEFAULT '',
          `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
          INDEX `idx_user_id` (`user_id`),
          INDEX `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='操作日志';

        -- 登录日志表
        CREATE TABLE IF NOT EXISTS `login_logs` (
          `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `user_id` INT UNSIGNED,
          `username` VARCHAR(50),
          `ip` VARCHAR(45),
          `user_agent` VARCHAR(500),
          `status` TINYINT DEFAULT 1 COMMENT '1=成功, 0=失败',
          `fail_reason` VARCHAR(255),
          `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
          INDEX `idx_user_id` (`user_id`),
          INDEX `idx_ip` (`ip`),
          INDEX `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='登录日志';

        -- 广告表
        CREATE TABLE IF NOT EXISTS `ads` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
          `name` VARCHAR(100) NOT NULL,
          `type` VARCHAR(30) DEFAULT 'countdown',
          `content` TEXT NOT NULL,
          `image_url` VARCHAR(500) DEFAULT '',
          `link_url` VARCHAR(500) DEFAULT '',
          `countdown` INT DEFAULT 5,
          `skip_mode` VARCHAR(20) DEFAULT 'auto',
          `btn_text` VARCHAR(100) DEFAULT '跳过广告（{countdown}秒）',
          `btn_style` VARCHAR(500) DEFAULT '',
          `is_global` TINYINT DEFAULT 0,
          `status` TINYINT DEFAULT 1,
          `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
          `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='广告配置';

        -- 域名表
        CREATE TABLE IF NOT EXISTS `domains` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
          `domain` VARCHAR(255) NOT NULL UNIQUE,
          `type` VARCHAR(20) DEFAULT 'custom' COMMENT 'system=系统域名, custom=自定义',
          `status` TINYINT DEFAULT 1,
          `remark` VARCHAR(255) DEFAULT '',
          `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
          INDEX `idx_domain` (`domain`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='域名管理';

        -- 系统设置表
        CREATE TABLE IF NOT EXISTS `settings` (
          `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `group_name` VARCHAR(50) DEFAULT 'default',
          `key` VARCHAR(100) NOT NULL,
          `value` TEXT,
          `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          UNIQUE KEY `uk_key` (`key`),
          INDEX `idx_group` (`group_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='系统设置';

        -- 黑名单表
        CREATE TABLE IF NOT EXISTS `blacklists` (
          `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `type` VARCHAR(20) NOT NULL COMMENT 'ip/domain',
          `value` VARCHAR(255) NOT NULL,
          `reason` VARCHAR(255) DEFAULT '',
          `created_by` INT UNSIGNED DEFAULT 0,
          `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
          UNIQUE KEY `uk_value` (`type`, `value`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='黑名单';

        -- 邀请码表
        CREATE TABLE IF NOT EXISTS `invite_codes` (
          `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `code` VARCHAR(20) NOT NULL UNIQUE,
          `used_by` INT UNSIGNED DEFAULT NULL,
          `used_at` DATETIME DEFAULT NULL,
          `expires_at` DATETIME DEFAULT NULL,
          `created_by` INT UNSIGNED DEFAULT 0,
          `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
          INDEX `idx_code` (`code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='邀请码';

        -- 每日统计表
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

        -- 域名表
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
        ";

        // 分割并执行 SQL
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($statements as $stmt) {
            if (!empty($stmt)) {
                $pdo->exec($stmt);
            }
        }
    }

    /**
     * 创建管理员账号
     */
    private function createAdmin(PDO $pdo, string $username, string $password): void {
        // 检查是否已有管理员
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role=1");
        $stmt->execute();
        if ($stmt->fetchColumn() > 0) {
            return; // 已有管理员，跳过
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, status, daily_limit) VALUES (?, ?, 1, 1, 9999)");
        $stmt->execute([$username, $hash]);
    }

    /**
     * 初始化默认设置
     */
    private function initSettings(PDO $pdo): void {
        $settings = [
            ['site', 'site_name', '杨爽短链接系统'],
            ['site', 'site_logo', ''],
            ['site', 'icp', ''],
            ['site', 'allow_register', '1'],
            ['site', 'guest_create', '1'],
            ['site', 'default_ad_duration', '5'],
            ['site', 'redirect_seconds', '5'],
            ['site', 'show_icp', '1'],
            ['site', 'register_mode', 'open'],
            ['site', 'query_public', '1'],
            ['general', 'system_version', '1.0.0'],
            ['general', 'default_domain', ''],
            ['general', 'api_open', '1'],
            ['general', 'global_ad_id', '0'],
            ['seo', 'site_seo_title', ''],
            ['seo', 'site_seo_keywords', ''],
            ['seo', 'site_seo_desc', ''],
            ['security', 'ip_blacklist_enable', '1'],
            ['advanced', 'short_code_length', '6'],
            ['advanced', 'custom_domain_required', '0'],
            ['advanced', 'link_expire_days', '0'],
        ];

        $stmt = $pdo->prepare("INSERT IGNORE INTO settings (group_name, `key`, `value`) VALUES (?, ?, ?)");
        foreach ($settings as $s) {
            $stmt->execute($s);
        }
    }
}
