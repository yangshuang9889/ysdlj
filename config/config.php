<?php
/**
 * 杨爽短链接系统 - 核心配置文件
 */

// 数据库驱动：mysql / sqlite
$db_driver = getenv('DB_DRIVER') ?: 'mysql';

return [
    // 数据库配置
    'db' => [
        'driver'   => $db_driver,
        'host'     => getenv('DB_HOST') ?: '127.0.0.1',
        'port'     => getenv('DB_PORT') ?: 3306,
        'dbname'   => getenv('DB_NAME') ?: 'dlj',
        'username' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASS') ?: '',
        'charset'  => 'utf8mb4',
        // SQLite 数据库路径
        'database' => getenv('SQLITE_DATABASE') ?: '/var/www/html/storage/database.sqlite',
        'options'  => [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ],
    ],

    // Redis缓存配置（可选，不配置则降级为文件缓存）
    'redis' => [
        'host'     => getenv('REDIS_HOST') ?: '1Panel-redis-8h48',
        'port'     => getenv('REDIS_PORT') ?: 6379,
        'password' => getenv('REDIS_PASS') ?: '',
        'database' => 0,
        'prefix'   => 'sl:',
    ],

    // JWT配置
    'jwt' => [
        'secret'     => getenv('JWT_SECRET') ?: 'change_this_secret_in_production',
        'expire'     => 86400 * 7,  // 7天
        'algorithm'  => 'HS256',
    ],

    // 应用配置
    'app' => [
        'url'        => getenv('APP_URL') ?: 'http://localhost',
        'debug'      => getenv('APP_DEBUG') !== 'false',
        'timezone'   => 'Asia/Shanghai',
        'upload_dir' => __DIR__ . '/../storage/uploads/',
        'log_dir'    => __DIR__ . '/../storage/logs/',
    ],

    // 短链配置
    'shortlink' => [
        'code_chars'   => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789',
        'code_length'  => 6,
        'max_custom_length' => 32,
        'reserved_codes' => ['api', 'admin', 'login', 'register', 'static', 'assets', 'favicon.ico'],
    ],

    // IP地理位置（使用纯真IP库或第三方API）
    'ip_geo' => [
        'driver' => 'qqwry',   // qqwry / ip2region / none
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
