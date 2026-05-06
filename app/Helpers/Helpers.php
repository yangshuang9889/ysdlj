<?php
/**
 * HTTP响应助手 + 路由器
 */
class Response {
    public static function json(mixed $data, int $code = 200): void {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success(mixed $data = null, string $msg = '操作成功'): void {
        self::json(['code' => 0, 'msg' => $msg, 'data' => $data]);
    }

    public static function error(string $msg = '操作失败', int $code = 400, mixed $data = null): void {
        self::json(['code' => $code, 'msg' => $msg, 'data' => $data]);
    }

    public static function redirect(string $url, int $code = 302): void {
        http_response_code($code);
        header("Location: $url");
        exit;
    }
}

class Router {
    private static array $routes = [];

    public static function add(string $method, string $path, array|callable $handler): void {
        self::$routes[] = compact('method', 'path', 'handler');
    }

    public static function get(string $path, array|callable $handler): void  { self::add('GET', $path, $handler); }
    public static function post(string $path, array|callable $handler): void { self::add('POST', $path, $handler); }
    public static function put(string $path, array|callable $handler): void  { self::add('PUT', $path, $handler); }
    public static function delete(string $path, array|callable $handler): void { self::add('DELETE', $path, $handler); }

    public static function dispatch(): void {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $uri    = rtrim($uri, '/') ?: '/';

        foreach (self::$routes as $route) {
            if ($route['method'] !== $method && $route['method'] !== 'ANY') continue;

            $pattern = preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $route['path']);
            $pattern = '#^' . $pattern . '$#u';

            if (preg_match($pattern, $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $handler = $route['handler'];

                if (is_callable($handler)) {
                    call_user_func_array($handler, $params);
                } else {
                    [$class, $method_name] = $handler;
                    (new $class)->$method_name(...array_values($params));
                }
                return;
            }
        }

        Response::error('接口不存在', 404);
    }
}

/**
 * JWT助手
 */
class JWT {
    private static string $secret = '';
    private static int $expire = 86400;

    public static function init(array $config): void {
        self::$secret = $config['secret'];
        self::$expire = $config['expire'] ?? 86400;
    }

    public static function encode(array $payload): string {
        $payload['iat'] = time();
        $payload['exp'] = time() + self::$expire;
        $header  = self::base64url(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $payload = self::base64url(json_encode($payload, JSON_UNESCAPED_UNICODE));
        $sig     = self::base64url(hash_hmac('sha256', "$header.$payload", self::$secret, true));
        return "$header.$payload.$sig";
    }

    public static function decode(string $token): ?array {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;
        [$header, $payload, $sig] = $parts;
        $expected = self::base64url(hash_hmac('sha256', "$header.$payload", self::$secret, true));
        if (!hash_equals($expected, $sig)) return null;
        $data = json_decode(self::base64urlDecode($payload), true);
        if (!$data || $data['exp'] < time()) return null;
        return $data;
    }

    private static function base64url(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    private static function base64urlDecode(string $data): string {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}

/**
 * 请求助手
 */
class Request {
    public static function json(): array {
        $body = file_get_contents('php://input');
        return json_decode($body, true) ?? [];
    }

    public static function get(string $key, mixed $default = null): mixed {
        return $_GET[$key] ?? $default;
    }

    public static function post(string $key, mixed $default = null): mixed {
        $data = self::json();
        return $data[$key] ?? $_POST[$key] ?? $default;
    }

    public static function all(): array {
        return array_merge($_GET, self::json(), $_POST);
    }

    public static function ip(): string {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = explode(',', $_SERVER[$key])[0];
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
        return '0.0.0.0';
    }

    public static function bearerToken(): ?string {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (str_starts_with($auth, 'Bearer ')) return substr($auth, 7);
        return $_GET['token'] ?? null;
    }

    public static function userAgent(): string {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }
}
