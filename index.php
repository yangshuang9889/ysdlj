<?php
/**
 * 杨爽短链接系统 - 入口文件
 * 支持 PHP 8.0+
 */

declare(strict_types=1);

// 时区设置
date_default_timezone_set('Asia/Shanghai');

// 自动加载
spl_autoload_register(function (string $class) {
    $dirs = [
        __DIR__ . '/app/Controllers/',
        __DIR__ . '/app/Models/',
        __DIR__ . '/app/Services/',
        __DIR__ . '/app/Helpers/',
        __DIR__ . '/app/Middleware/',
    ];
    foreach ($dirs as $dir) {
        $file = $dir . $class . '.php';
        if (file_exists($file)) { require_once $file; return; }
    }
});

// Helpers.php 包含多个类（Response、Router、JWT 等），需要显式加载
require_once __DIR__ . '/app/Helpers/Helpers.php';

// Middleware.php 包含多个类（AuthMiddleware、IpBlockMiddleware、CorsMiddleware），需要显式加载
require_once __DIR__ . '/app/Middleware/Middleware.php';

// AdminController.php 包含多个控制器类（AdController、SettingController、DashboardController、UserController、ApiController）
require_once __DIR__ . '/app/Controllers/AdminController.php';

// InstallController.php - 安装向导控制器
require_once __DIR__ . '/app/Controllers/InstallController.php';

// 加载配置
$appConfig = require __DIR__ . '/config/config.php';

// 错误处理（提前注册，确保能捕获所有错误）
set_exception_handler(function (Throwable $e) use ($appConfig) {
    if ($appConfig['app']['debug']) {
        Response::json([
            'code' => 500,
            'msg'  => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ], 500);
    } else {
        error_log($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        Response::json(['code' => 500, 'msg' => '服务器内部错误'], 500);
    }
});

// 初始化各组件
try {
    DB::init($appConfig['db']);
    Cache::init($appConfig['redis'], __DIR__ . '/../storage/cache/');
    JWT::init($appConfig['jwt']);
} catch (Throwable $e) {
    if ($appConfig['app']['debug']) {
        Response::json([
            'code' => 500,
            'msg'  => '初始化失败: ' . $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ], 500);
    } else {
        error_log('初始化失败: ' . $e->getMessage());
        Response::json(['code' => 500, 'msg' => '服务器内部错误'], 500);
    }
}

// 数据库升级检查
function checkDbUpgrade(): void {
    try {
        if (DB::isSQLite()) {
            // SQLite：用 PRAGMA table_info 获取字段列表
            $columns = DB::fetchAll('PRAGMA table_info(users)');
            $columnNames = array_column($columns, 'name');
            if (!in_array('daily_used', $columnNames)) {
                DB::query('ALTER TABLE users ADD COLUMN daily_used INTEGER DEFAULT 0');
            }
            if (!in_array('daily_reset_date', $columnNames)) {
                DB::query('ALTER TABLE users ADD COLUMN daily_reset_date TEXT DEFAULT NULL');
            }
        } else {
            // MySQL
            $columns = DB::fetchAll('SHOW COLUMNS FROM users');
            $columnNames = array_column($columns, 'Field');
            if (!in_array('daily_used', $columnNames)) {
                DB::query('ALTER TABLE users ADD COLUMN daily_used INT DEFAULT 0 COMMENT "今日已使用次数" AFTER daily_limit');
            }
            if (!in_array('daily_reset_date', $columnNames)) {
                DB::query('ALTER TABLE users ADD COLUMN daily_reset_date DATE DEFAULT NULL COMMENT "上次重置日期" AFTER daily_used');
            }
        }
    } catch (Exception $e) {
        // 忽略升级错误，不影响正常流程
        error_log('DB upgrade check failed: ' . $e->getMessage());
    }
}
checkDbUpgrade();

// CORS处理
CorsMiddleware::handle();

// ============================================================
// 路由注册
// ============================================================

// 安装向导路由
Router::get('/api/install/status',  [InstallController::class, 'status']);
Router::post('/api/test-connection', [InstallController::class, 'testConnection']);
Router::post('/api/setup',          [InstallController::class, 'setup']);

// 公开路由
Router::post('/api/auth/login',    [AuthController::class, 'login']);
Router::post('/api/auth/register', [AuthController::class, 'register']);
Router::get('/api/settings/public', [SettingController::class, 'getPublic']);
Router::get('/api/link/query',     [LinkController::class, 'publicQuery']);
Router::get('/api/link/{id}/qrcode', [LinkController::class, 'qrcode']);

// 系统信息（公开）
Router::get('/api/system/version', [SettingController::class, 'getVersion']);
Router::get('/api/system/check-update', [SettingController::class, 'checkUpdate']);

// 广告查询（前台用）
Router::get('/api/ad/{code}', [AdController::class, 'getAdForLink']);

// 需登录的路由
Router::get('/api/auth/profile',        [AuthController::class, 'profile']);
Router::put('/api/auth/profile',        [AuthController::class, 'updateProfile']);
Router::post('/api/auth/refresh',       [AuthController::class, 'refreshToken']);
Router::post('/api/link/create',        [LinkController::class, 'create']);
Router::post('/api/link/batch',         [LinkController::class, 'batchCreate']);
Router::get('/api/link/my',             [LinkController::class, 'myLinks']);
Router::get('/api/link/{id}',           [LinkController::class, 'detail']);
Router::put('/api/link/{id}',           [LinkController::class, 'update']);
Router::delete('/api/link/{id}',        [LinkController::class, 'delete']);
Router::post('/api/link/{id}/restore',  [LinkController::class, 'restore']);
Router::post('/api/link/batch-action',  [LinkController::class, 'batchAction']);
Router::get('/api/link/{id}/stats',     [LinkController::class, 'stats']);
Router::get('/api/link/{id}/export',    [LinkController::class, 'export']);

// 管理员路由
Router::get('/api/admin/links',             [LinkController::class, 'adminLinks']);
Router::delete('/api/admin/link/{id}',      [LinkController::class, 'forceDelete']);
Router::post('/api/admin/links/import',     [LinkController::class, 'importCsv']);
Router::get('/api/admin/links/export',      [LinkController::class, 'exportLinks']);
Router::get('/api/admin/dashboard',         [DashboardController::class, 'overview']);
Router::get('/api/admin/access-logs',       [DashboardController::class, 'accessLogs']);
Router::get('/api/admin/operation-logs',    [DashboardController::class, 'operationLogs']);
Router::get('/api/admin/ads',               [AdController::class, 'list']);
Router::post('/api/admin/ads',              [AdController::class, 'create']);
Router::put('/api/admin/ads/{id}',          [AdController::class, 'update']);
Router::delete('/api/admin/ads/{id}',       [AdController::class, 'delete']);
Router::get('/api/admin/settings',          [SettingController::class, 'get']);
Router::post('/api/admin/settings',         [SettingController::class, 'update']);
Router::post('/api/admin/clear-cache',      [SettingController::class, 'clearCache']);
Router::post('/api/admin/clear-logs',       [SettingController::class, 'clearLogs']);
Router::post('/api/admin/backup',           [SettingController::class, 'backupDb']);
Router::post('/api/admin/upload',           [SettingController::class, 'upload']);
Router::get('/api/admin/users',             [UserController::class, 'list']);
Router::post('/api/admin/users',            [UserController::class, 'create']);
Router::put('/api/admin/users/{id}',        [UserController::class, 'update']);
Router::post('/api/admin/users/batch',      [UserController::class, 'batchAction']);
Router::get('/api/admin/users/{id}',        [UserController::class, 'detail']);
Router::get('/api/admin/users/{id}/login-logs', [UserController::class, 'loginLogs']);
Router::get('/api/admin/login-logs',        [AuthController::class, 'loginLogs']);
Router::get('/api/admin/invite-codes',      [UserController::class, 'generateInvite']);
Router::post('/api/admin/ip-blacklist',     [UserController::class, 'addIpBlacklist']);
Router::post('/api/admin/domain-blacklist', [UserController::class, 'addDomainBlacklist']);

// 域名管理
Router::get('/api/admin/domains',           [DomainController::class, 'list']);
Router::get('/api/admin/domains/available', [DomainController::class, 'available']);
Router::post('/api/admin/domains',           [DomainController::class, 'create']);
Router::put('/api/admin/domains/{id}',      [DomainController::class, 'update']);
Router::delete('/api/admin/domains/{id}',   [DomainController::class, 'delete']);
Router::post('/api/admin/domains/batch',    [DomainController::class, 'batchAction']);
Router::get('/api/admin/domains/{id}/check-dns', [DomainController::class, 'checkDns']);
Router::get('/api/admin/domains/{id}/links', [DomainController::class, 'links']);

// 对外开放API
Router::post('/api/open/create', [ApiController::class, 'create']);
Router::get('/api/open/query',   [ApiController::class, 'query']);

// 安装页面路由 - 返回前端index.html，由前端路由处理
Router::get('/install', function() {
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/index.html');
    exit;
});

// 短链跳转（必须放最后，因为会匹配所有单段路径）
Router::get('/{code}', function(string $code) {
    $service  = new LinkService();
    $host     = $_SERVER['HTTP_HOST'] ?? '';
    
    // 优先根据域名匹配，未匹配则用默认域名
    $domainRow = DB::fetchOne(
        'SELECT * FROM domains WHERE domain=? AND status=1',
        [$host]
    );
    
    // 根据域名查找链接
    if ($domainRow) {
        $link = DB::fetchOne(
            'SELECT * FROM `links` WHERE short_code=? AND domain_id=? AND is_deleted=0',
            [urldecode($code), $domainRow['id']]
        );
        // 如果该域名下没找到，也尝试用默认的（兼容）
        if (!$link) {
            $link = $service->getByCode(urldecode($code));
        }
    } else {
        $link = $service->getByCode(urldecode($code));
    }

    if (!$link) {
        http_response_code(404);
        include __DIR__ . '/404.html';
        exit;
    }

    $ip = Request::ip();
    $ua = Request::userAgent();
    $password = Request::get('pwd');

    IpBlockMiddleware::check($ip);

    // 检查链接创建者的每日限额（限制当天总访问次数）
    if ($link['user_id'] > 0) {
        $userId = $link['user_id'];
        $today = date('Y-m-d');
        
        // 获取用户信息（包括限额字段）
        $linkUser = DB::fetchOne(
            'SELECT daily_limit, daily_used, daily_reset_date FROM users WHERE id=?',
            [$userId]
        );
        
        if ($linkUser) {
            $limit = (int)$linkUser['daily_limit'];
            $used = (int)($linkUser['daily_used'] ?? 0);
            $resetDate = $linkUser['daily_reset_date'] ?? null;
            
            // 如果不是今天，自动重置
            if ($resetDate !== $today) {
                DB::query(
                    'UPDATE users SET daily_used=0, daily_reset_date=? WHERE id=?',
                    [$today, $userId]
                );
                $used = 0;
            }
            
            // 检查是否超限（0表示不限）
            if ($limit > 0 && $used >= $limit) {
                http_response_code(403);
                echo '<h1>403 今日访问次数已达上限（' . $limit . '次）</h1>';
                echo '<p>额度将于24:00重置</p>';
                exit;
            }
        }
    }

    $access = $service->checkAccess($link, $ip, $password);

    if (!$access['ok']) {
        if ($access['reason'] === 'need_password') {
            // 显示密码输入页
            header('Content-Type: text/html; charset=utf-8');
            echo file_get_contents(__DIR__ . '/password.html');
            exit;
        }
        http_response_code(403);
        echo '<h1>403 ' . htmlspecialchars($access['msg']) . '</h1>';
        exit;
    }

    // 检查广告 - 直接获取广告数据，不调用API方法（API方法会输出JSON并exit）
    $globalAdId = (int)(DB::fetchOne('SELECT value FROM settings WHERE `key`="global_ad_id"')['value'] ?? 0);
    $hasAd = !$link['no_ad'] && ($link['ad_id'] > 0 || $globalAdId > 0);

    if ($hasAd) {
        // 获取广告内容
        $adId = $link['ad_id'] > 0 ? $link['ad_id'] : $globalAdId;
        $ad   = DB::fetchOne('SELECT * FROM ads WHERE id=? AND status=1', [$adId]);

        if ($ad) {
            header('Content-Type: text/html; charset=utf-8');
            $targetUrl = htmlspecialchars($link['original_url']);
            $adContent = $ad['content'];
            $countdown = (int)$ad['countdown'];
            $skipMode  = $ad['skip_mode'];
            $btnText   = $ad['btn_text'];
            include __DIR__ . '/redirect.php';
            exit;
        }
    }

    // 记录访问（异步模拟，PHP中直接同步写）
    $service->recordAccess($link, $ip, $ua);

    // 直接跳转
    Response::redirect($link['original_url'], 302);
});

// 根路由 - 前台页面，返回index.html让前端路由处理
Router::get('/', function() {
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/index.html');
    exit;
});

// 分发请求
Router::dispatch();
