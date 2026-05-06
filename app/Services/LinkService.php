<?php
/**
 * 短链接核心服务
 */
class LinkService {
    private array $config;

    public function __construct() {
        global $appConfig;
        $this->config = $appConfig['shortlink'];
    }

    /**
     * 生成随机短码
     */
    public function generateCode(int $length = 0): string {
        $length = $length ?: $this->config['code_length'];
        $chars  = $this->config['code_chars'];
        $len    = strlen($chars);
        $code   = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[random_int(0, $len - 1)];
        }
        // 碰撞检测
        if (DB::count('links', 'short_code=? AND is_deleted=0', [$code]) > 0) {
            return $this->generateCode($length + 1);
        }
        return $code;
    }

    /**
     * 验证自定义短码合法性
     */
    public function validateCustomCode(string $code): array {
        if (empty($code)) return ['ok' => false, 'msg' => '短码不能为空'];
        if (mb_strlen($code) > $this->config['max_custom_length']) {
            return ['ok' => false, 'msg' => '短码过长（最多' . $this->config['max_custom_length'] . '字符）'];
        }
        // 保留字检测
        if (in_array(strtolower($code), $this->config['reserved_codes'])) {
            return ['ok' => false, 'msg' => '该短码为系统保留字，请换一个'];
        }
        // 检测是否已存在
        if (DB::count('links', 'short_code=? AND is_deleted=0', [$code]) > 0) {
            return ['ok' => false, 'msg' => '该短码已被使用，请换一个'];
        }
        return ['ok' => true, 'msg' => ''];
    }

    /**
     * 创建短链接
     */
    public function create(array $data, int $userId = 0): array {
        // 验证原始URL
        $url = trim($data['original_url'] ?? '');
        if (empty($url)) return ['ok' => false, 'msg' => '请输入链接地址'];

        // 协议补全
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        // 违规域名检测
        $domain = parse_url($url, PHP_URL_HOST);
        if ($domain && DB::count('domain_blacklist', 'domain=?', [$domain]) > 0) {
            return ['ok' => false, 'msg' => '该链接域名已被列入黑名单'];
        }

        // 短码处理
        $shortCode = trim($data['short_code'] ?? '');
        if (!empty($shortCode)) {
            $check = $this->validateCustomCode($shortCode);
            if (!$check['ok']) return $check;
        } else {
            $shortCode = $this->generateCode();
        }

        // 过期时间
        $expireAt = null;
        if (!empty($data['expire_days']) && (int)$data['expire_days'] > 0) {
            $expireAt = date('Y-m-d H:i:s', time() + (int)$data['expire_days'] * 86400);
        } elseif (!empty($data['expire_at'])) {
            $expireAt = $data['expire_at'];
        }

        // 域名处理
        $domainId = (int)($data['domain_id'] ?? 0);
        $domainRow = null;
        if ($domainId > 0) {
            $domainRow = DB::fetchOne('SELECT * FROM domains WHERE id=? AND status=1', [$domainId]);
            if (!$domainRow) Response::error('该域名不存在或已禁用');
        }
        
        $insertData = [
            'short_code'   => $shortCode,
            'original_url' => $url,
            'user_id'      => $userId,
            'domain_id'    => $domainId > 0 ? $domainId : 0,
            'title'        => trim($data['title'] ?? ''),
            'group_id'     => (int)($data['group_id'] ?? 0),
            'domain'       => trim($data['domain'] ?? ''),
            'status'       => 1,
            'password'     => trim($data['password'] ?? ''),
            'max_visits'   => (int)($data['max_visits'] ?? 0),
            'expire_at'    => $expireAt,
            'ad_id'        => (int)($data['ad_id'] ?? 0),
            'no_ad'        => (int)($data['no_ad'] ?? 0),
            'allow_ips'    => trim($data['allow_ips'] ?? ''),
        ];
        
        // 更新域名链接计数
        if ($domainId > 0) {
            DB::query('UPDATE domains SET link_count=link_count+1 WHERE id=?', [$domainId]);
        }

        $id = DB::insert('links', $insertData);

        // 清除缓存
        Cache::del('link:' . $shortCode);

        return ['ok' => true, 'id' => $id, 'short_code' => $shortCode, 'msg' => '创建成功'];
    }

    /**
     * 批量创建
     */
    public function batchCreate(array $urls, int $userId = 0, array $defaultOptions = []): array {
        $results = [];
        foreach ($urls as $url) {
            $results[] = $this->create(array_merge($defaultOptions, ['original_url' => $url]), $userId);
        }
        return $results;
    }

    /**
     * 获取短链接（带缓存）
     */
    public function getByCode(string $code): ?array {
        $cacheKey = 'link:' . $code;
        $cached = Cache::get($cacheKey);
        if ($cached !== null) return $cached;

        $link = DB::fetchOne(
            'SELECT * FROM `links` WHERE short_code=? AND is_deleted=0',
            [$code]
        );

        if ($link) {
            global $appConfig;
            $ttl = (int)$this->getSetting('cache_ttl', 300);
            Cache::set($cacheKey, $link, $ttl);
        }

        return $link ?: null;
    }

    /**
     * 检测链接是否可访问
     */
    public function checkAccess(array $link, string $ip, ?string $password = null): array {
        // 状态检测
        if ($link['status'] == 0) return ['ok' => false, 'reason' => 'disabled', 'msg' => '链接已禁用'];

        // 过期检测
        if (!empty($link['expire_at']) && strtotime($link['expire_at']) < time()) {
            DB::update('links', ['status' => 2], 'id=?', [$link['id']]);
            Cache::del('link:' . $link['short_code']);
            return ['ok' => false, 'reason' => 'expired', 'msg' => '链接已过期'];
        }

        // 最大访问次数
        if ($link['max_visits'] > 0 && $link['pv'] >= $link['max_visits']) {
            return ['ok' => false, 'reason' => 'limit_reached', 'msg' => '链接访问次数已达上限'];
        }

        // IP白名单
        if (!empty($link['allow_ips'])) {
            $allowed = array_map('trim', explode(',', $link['allow_ips']));
            if (!$this->ipInList($ip, $allowed)) {
                return ['ok' => false, 'reason' => 'ip_blocked', 'msg' => '您的IP不在允许访问列表中'];
            }
        }

        // 密码校验
        if (!empty($link['password'])) {
            if ($password === null) return ['ok' => false, 'reason' => 'need_password', 'msg' => '该链接需要密码访问'];
            if ($password !== $link['password']) return ['ok' => false, 'reason' => 'wrong_password', 'msg' => '访问密码错误'];
        }

        return ['ok' => true];
    }

    /**
     * 记录访问（异步写入）
     */
    public function recordAccess(array $link, string $ip, string $ua): void {
        $isBot = $this->detectBot($ua);

        // 设备解析
        $deviceInfo = $this->parseDevice($ua);

        // IP地理位置
        $geoInfo = $this->getIpGeo($ip);

        // 更新链接统计（乐观锁方式）
        DB::query(
            'UPDATE `links` SET pv=pv+1, updated_at=updated_at WHERE id=?',
            [$link['id']]
        );

        // 写入访问日志
        DB::insert('access_logs', [
            'link_id'     => $link['id'],
            'short_code'  => $link['short_code'],
            'ip'          => $ip,
            'ip_location' => $geoInfo['location'] ?? '',
            'province'    => $geoInfo['province'] ?? '',
            'city'        => $geoInfo['city'] ?? '',
            'country'     => $geoInfo['country'] ?? '',
            'device_type' => $deviceInfo['type'],
            'os'          => $deviceInfo['os'],
            'browser'     => $deviceInfo['browser'],
            'referer'     => $_SERVER['HTTP_REFERER'] ?? '',
            'user_agent'  => substr($ua, 0, 512),
            'is_bot'      => $isBot ? 1 : 0,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        // 更新每日汇总
        $today = date('Y-m-d');
        if (DB::isSQLite()) {
            // SQLite：先查询是否存在，再决定 INSERT 还是 UPDATE
            $existing = DB::fetchOne(
                'SELECT id FROM link_stats_daily WHERE link_id=? AND stat_date=?',
                [$link['id'], $today]
            );
            if ($existing) {
                DB::query(
                    'UPDATE link_stats_daily SET pv=pv+1, bot_count=bot_count+? WHERE link_id=? AND stat_date=?',
                    [$isBot ? 1 : 0, $link['id'], $today]
                );
            } else {
                DB::insert('link_stats_daily', [
                    'link_id'   => $link['id'],
                    'stat_date' => $today,
                    'pv'        => 1,
                    'bot_count' => $isBot ? 1 : 0,
                ]);
            }
        } else {
            DB::query(
                'INSERT INTO link_stats_daily (link_id, stat_date, pv, bot_count)
                 VALUES (?, ?, 1, ?)
                 ON DUPLICATE KEY UPDATE pv=pv+1, bot_count=bot_count+?',
                [$link['id'], $today, $isBot ? 1 : 0, $isBot ? 1 : 0]
            );
        }

        // UV/IP统计（用缓存去重）
        $uvKey = 'uv:' . $link['id'] . ':' . date('Ymd') . ':' . sha1($ip . $ua);
        if (!Cache::has($uvKey)) {
            Cache::set($uvKey, 1, 86400);
            DB::query('UPDATE `links` SET uv=uv+1, updated_at=updated_at WHERE id=?', [$link['id']]);
            DB::query('UPDATE link_stats_daily SET uv=uv+1 WHERE link_id=? AND stat_date=?', [$link['id'], $today]);
        }

        $ipKey = 'ip:' . $link['id'] . ':' . date('Ymd') . ':' . $ip;
        if (!Cache::has($ipKey)) {
            Cache::set($ipKey, 1, 86400);
            DB::query('UPDATE `links` SET ip_count=ip_count+1, updated_at=updated_at WHERE id=?', [$link['id']]);
            DB::query('UPDATE link_stats_daily SET ip_count=ip_count+1 WHERE link_id=? AND stat_date=?', [$link['id'], $today]);
        }

        // 更新用户每日已使用次数
        if (!empty($link['user_id'])) {
            $userId = (int)$link['user_id'];
            $resetDate = date('Y-m-d');
            
            // 先检查是否需要重置（跨天）
            $user = DB::fetchOne('SELECT daily_reset_date, daily_limit FROM users WHERE id=?', [$userId]);
            if ($user) {
                if ($user['daily_reset_date'] !== $resetDate) {
                    // 新的一天，重置计数器
                    DB::query(
                        'UPDATE users SET daily_used=1, daily_reset_date=? WHERE id=?',
                        [$resetDate, $userId]
                    );
                } else {
                    // 同一天，增加计数
                    DB::query(
                        'UPDATE users SET daily_used=daily_used+1 WHERE id=? AND daily_limit>0',
                        [$userId]
                    );
                }
            }
        }
    }

    /**
     * 获取链接统计数据
     */
    public function getStats(int $linkId, string $period = '7d'): array {
        // 趋势数据
        $days = match($period) {
            '24h' => 1, '7d' => 7, '30d' => 30, '90d' => 90, default => 7
        };

        $trends = DB::fetchAll(
            DB::isSQLite()
                ? "SELECT stat_date, pv, uv, ip_count, bot_count
                   FROM link_stats_daily
                   WHERE link_id=? AND stat_date >= date('now', '-' || ? || ' days')
                   ORDER BY stat_date ASC"
                : 'SELECT stat_date, pv, uv, ip_count, bot_count
                   FROM link_stats_daily
                   WHERE link_id=? AND stat_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                   ORDER BY stat_date ASC',
            [$linkId, $days]
        );

        // 地域分布
        $regions = DB::fetchAll(
            'SELECT province, city, COUNT(*) as cnt
             FROM access_logs
             WHERE link_id=? AND is_bot=0 AND province != ""
             GROUP BY province, city ORDER BY cnt DESC LIMIT 20',
            [$linkId]
        );

        // 设备分布
        $devices = DB::fetchAll(
            'SELECT device_type, COUNT(*) as cnt
             FROM access_logs WHERE link_id=? AND is_bot=0
             GROUP BY device_type',
            [$linkId]
        );

        // 浏览器分布
        $browsers = DB::fetchAll(
            'SELECT browser, COUNT(*) as cnt
             FROM access_logs WHERE link_id=? AND is_bot=0
             GROUP BY browser ORDER BY cnt DESC LIMIT 10',
            [$linkId]
        );

        return compact('trends', 'regions', 'devices', 'browsers');
    }

    // ---- 内部工具方法 ----

    private function detectBot(string $ua): bool {
        $botPatterns = [
            'bot', 'spider', 'crawl', 'slurp', 'wget', 'curl', 'python-requests',
            'scrapy', 'facebookexternalhit', 'twitterbot', 'linkedinbot',
            'baiduspider', 'googlebot', 'bingbot', 'sogou', 'yandex',
        ];
        $ua = strtolower($ua);
        foreach ($botPatterns as $p) {
            if (str_contains($ua, $p)) return true;
        }
        return empty($ua);
    }

    private function parseDevice(string $ua): array {
        $type = 'desktop';
        if (preg_match('/(mobile|android.*mobile|iphone|windows phone)/i', $ua)) $type = 'mobile';
        elseif (preg_match('/(ipad|android(?!.*mobile)|tablet)/i', $ua)) $type = 'tablet';

        $os = '未知';
        if (preg_match('/Windows NT/i', $ua)) $os = 'Windows';
        elseif (preg_match('/Mac OS X/i', $ua)) $os = 'macOS';
        elseif (preg_match('/Android/i', $ua)) $os = 'Android';
        elseif (preg_match('/iPhone|iPad/i', $ua)) $os = 'iOS';
        elseif (preg_match('/Linux/i', $ua)) $os = 'Linux';

        $browser = '未知';
        if (preg_match('/MicroMessenger/i', $ua)) $browser = '微信';
        elseif (preg_match('/WeiBo/i', $ua)) $browser = '微博';
        elseif (preg_match('/Edg\//i', $ua)) $browser = 'Edge';
        elseif (preg_match('/Chrome/i', $ua)) $browser = 'Chrome';
        elseif (preg_match('/Firefox/i', $ua)) $browser = 'Firefox';
        elseif (preg_match('/Safari/i', $ua)) $browser = 'Safari';

        return compact('type', 'os', 'browser');
    }

    private function getIpGeo(string $ip): array {
        // 简化实现，生产环境建议集成 ip2region 或纯真IP库
        if ($ip === '127.0.0.1' || str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.')) {
            return ['location' => '内网', 'province' => '', 'city' => '', 'country' => '中国'];
        }
        return ['location' => '', 'province' => '', 'city' => '', 'country' => ''];
    }

    private function ipInList(string $ip, array $list): bool {
        foreach ($list as $range) {
            if (str_contains($range, '/')) {
                if ($this->ipInCidr($ip, $range)) return true;
            } else {
                if ($ip === $range) return true;
            }
        }
        return false;
    }

    private function ipInCidr(string $ip, string $cidr): bool {
        [$subnet, $bits] = explode('/', $cidr);
        $ip     = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask   = -1 << (32 - (int)$bits);
        return ($ip & $mask) === ($subnet & $mask);
    }

    private function getSetting(string $key, mixed $default = null): mixed {
        $row = DB::fetchOne('SELECT value FROM settings WHERE `key`=?', [$key]);
        return $row ? $row['value'] : $default;
    }
}

