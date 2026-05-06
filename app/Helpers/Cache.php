<?php
/**
 * 缓存助手（Redis优先，降级文件缓存）
 */
class Cache {
    private static ?Redis $redis = null;
    private static bool $useRedis = false;
    private static string $cacheDir = '';
    private static string $prefix = 'sl:';

    public static function init(array $config, string $cacheDir): void {
        self::$cacheDir = $cacheDir ?: sys_get_temp_dir() . '/sl_cache/';
        self::$prefix   = $config['prefix'] ?? 'sl:';
        if (!is_dir(self::$cacheDir)) @mkdir(self::$cacheDir, 0755, true);

        if (class_exists('Redis') && !empty($config['host'])) {
            try {
                $r = new Redis();
                $r->connect($config['host'], $config['port'] ?? 6379, 2);
                if (!empty($config['password'])) $r->auth($config['password']);
                $r->select($config['database'] ?? 0);
                self::$redis    = $r;
                self::$useRedis = true;
            } catch (Exception $e) {
                self::$useRedis = false;
            }
        }
    }

    public static function get(string $key): mixed {
        $key = self::$prefix . $key;
        if (self::$useRedis) {
            $val = self::$redis->get($key);
            return $val !== false ? json_decode($val, true) : null;
        }
        $file = self::$cacheDir . md5($key) . '.cache';
        if (!file_exists($file)) return null;
        $data = unserialize(file_get_contents($file));
        if ($data['expire'] > 0 && $data['expire'] < time()) { @unlink($file); return null; }
        return $data['value'];
    }

    public static function set(string $key, mixed $value, int $ttl = 300): void {
        $key = self::$prefix . $key;
        if (self::$useRedis) {
            self::$redis->setex($key, $ttl, json_encode($value, JSON_UNESCAPED_UNICODE));
            return;
        }
        $file = self::$cacheDir . md5($key) . '.cache';
        file_put_contents($file, serialize([
            'expire' => $ttl > 0 ? time() + $ttl : 0,
            'value'  => $value,
        ]));
    }

    public static function del(string $key): void {
        $key = self::$prefix . $key;
        if (self::$useRedis) { self::$redis->del($key); return; }
        @unlink(self::$cacheDir . md5($key) . '.cache');
    }

    public static function increment(string $key, int $step = 1, int $ttl = 60): int {
        if (self::$useRedis) {
            $val = self::$redis->incrBy(self::$prefix . $key, $step);
            if ($val === $step) self::$redis->expire(self::$prefix . $key, $ttl);
            return $val;
        }
        $cur = (int)(self::get($key) ?? 0);
        self::set($key, $cur + $step, $ttl);
        return $cur + $step;
    }

    public static function has(string $key): bool {
        return self::get($key) !== null;
    }
}
