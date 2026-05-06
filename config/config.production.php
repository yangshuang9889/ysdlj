<?php
/**
 * 杨爽短链接系统 - 生产环境配置示例
 *
 * 使用说明：
 * 1. 复制此文件为 config.php
 * 2. 修改数据库、Redis、JWT 等配置为你的实际值
 * 3. 生产环境建议使用环境变量覆盖
 *
 * 推荐使用环境变量：
 * DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS
 * REDIS_HOST, REDIS_PORT, REDIS_PASS
 * JWT_SECRET, APP_URL, APP_DEBUG
 */

$db_driver = getenv('DB_DRIVER') ?: 'mysql';

return [
    'db' => [
        'driver'   => $db_driver,
        'host'     => getenv('DB_HOST') ?: 'localhost',
        'port'     => getenv('DB_PORT') ?: 3306,
        'dbname'   => getenv('DB_NAME') ?: 'shortlink',
        'username' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASS') ?: '',
        'charset'  => 'utf8mb4',
        'database' => getenv('SQLITE_DATABASE') ?: __DIR__ . '/../storage/database.sqlite',
        'options'  => [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ],
    ],
    'redis' => [
        'host'     => getenv('REDIS_HOST') ?: '127.0.0.1',
        'port'     => getenv('REDIS_PORT') ?: 6379,
        'password' => getenv('REDIS_PASS') ?: '',
        'database' => 0,
        'prefix'   => 'sl:',
    ],
    'jwt' => [
        'secret'     => getenv('JWT_SECRET') ?: 'CHANGE_THIS_TO_A_RANDOM_SECRET',
        'expire'     => 86400 * 7,
        'algorithm'  => 'HS256',
    ],
    'app' => [
        'url'        => getenv('APP_URL') ?: 'https://your-domain.com',
        'debug'      => getenv('APP_DEBUG') === 'true',
        'timezone'   => 'Asia/Shanghai',
        'upload_dir' => __DIR__ . '/../storage/uploads/',
        'log_dir'    => __DIR__ . '/../storage/logs/',
    ],
    'shortlink' => [
        'code_chars'   => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789',
        'code_length'  => 6,
        'max_custom_length' => 32,
        'reserved_codes' => ['api', 'admin', 'login', 'register', 'static', 'assets', 'favicon.ico'],
    ],
    'ip_geo' => [
        'driver' => 'qqwry',
        'db_path' => __DIR__ . '/../storage/qqwry.dat',
    ],
    'version' => [
        'current'   => '1.0.0',
        'name'      => '杨爽短链接系统',
        'author'    => '杨爽',
        'website'   => 'https://github.com/yangshuang9889/ysdj',
        'update_url' => 'https://api.github.com/repos/yangshuang9889/ysdj/releases/latest',
    ],
];
