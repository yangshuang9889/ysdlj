<?php
/**
 * 认证中间件 + 权限控制
 */
class AuthMiddleware {

    public static function user(): ?array {
        $token = Request::bearerToken();
        if (!$token) return null;
        $payload = JWT::decode($token);
        if (!$payload || empty($payload['uid'])) return null;

        $user = DB::fetchOne('SELECT * FROM users WHERE id=? AND status=1', [$payload['uid']]);
        return $user ?: null;
    }

    public static function require(): array {
        $user = self::user();
        if (!$user) {
            Response::error('请先登录', 401);
        }
        return $user;
    }

    public static function requireAdmin(): array {
        $user = self::require();
        if (!in_array($user['role'], [1, 3])) {
            Response::error('权限不足', 403);
        }
        return $user;
    }

    public static function requireSuperAdmin(): array {
        $user = self::require();
        if ((int)$user['role'] !== 1) {
            Response::error('仅超级管理员可操作', 403);
        }
        return $user;
    }
}

/**
 * IP黑名单中间件
 */
class IpBlockMiddleware {

    public static function check(string $ip): void {
        $cacheKey = 'ipblock:' . $ip;
        $blocked  = Cache::get($cacheKey);

        if ($blocked === null) {
            $row = DB::fetchOne(
                'SELECT id FROM ip_blacklist WHERE ip_range=? AND (expire_at IS NULL OR expire_at > NOW())',
                [$ip]
            );
            $blocked = $row ? 1 : 0;
            Cache::set($cacheKey, $blocked, 300);
        }

        if ($blocked) {
            Response::error('您的IP已被封禁', 403);
        }
    }
}

/**
 * CORS中间件
 */
class CorsMiddleware {

    public static function handle(): void {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
        header("Access-Control-Allow-Origin: $origin");
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
