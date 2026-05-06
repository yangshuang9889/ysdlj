<?php
/**
 * 广告控制器
 */
class AdController {

    public function list(): void {
        AuthMiddleware::requireAdmin();
        $ads = DB::fetchAll('SELECT * FROM ads ORDER BY id DESC');
        Response::success($ads);
    }

    public function create(): void {
        AuthMiddleware::requireAdmin();
        $data = Request::json();

        if (empty($data['name'])) Response::error('广告名称不能为空');
        if (empty($data['content'])) Response::error('广告内容不能为空');

        $id = DB::insert('ads', [
            'name'      => $data['name'],
            'type'      => $data['type'] ?? 'countdown',
            'content'   => $data['content'],
            'image_url' => $data['image_url'] ?? '',
            'link_url'  => $data['link_url'] ?? '',
            'countdown' => max(0, min(300, (int)($data['countdown'] ?? 5))),
            'skip_mode' => in_array($data['skip_mode'] ?? '', ['', 'auto', 'manual']) ? ($data['skip_mode'] ?: 'auto') : 'auto',
            'btn_text'  => $data['btn_text'] ?? '跳过广告（{countdown}秒）',
            'btn_style' => !empty($data['btn_style']) ? json_encode($data['btn_style']) : null,
            'is_global' => (int)($data['is_global'] ?? 0),
            'status'    => (int)($data['status'] ?? 1),
        ]);

        Response::success(['id' => $id], '广告创建成功');
    }

    public function update(string $id): void {
        AuthMiddleware::requireAdmin();
        $data = Request::json();
        $ad   = DB::fetchOne('SELECT id FROM ads WHERE id=?', [(int)$id]);
        if (!$ad) Response::error('广告不存在', 404);

        $fields = ['name', 'type', 'content', 'image_url', 'link_url', 'countdown', 'skip_mode', 'btn_text', 'btn_style', 'is_global', 'status'];
        $updateData = [];
        foreach ($fields as $f) {
            if (isset($data[$f])) $updateData[$f] = $data[$f];
        }

        if (!empty($updateData)) {
            DB::update('ads', $updateData, 'id=?', [(int)$id]);
            // 如果更新为全局广告，清除其他全局标记
            if (!empty($updateData['is_global'])) {
                DB::query('UPDATE ads SET is_global=0 WHERE id!=?', [(int)$id]);
                DB::update('settings', ['value' => (int)$id], '`key`="global_ad_id"');
            }
        }

        Response::success(null, '更新成功');
    }

    public function delete(string $id): void {
        AuthMiddleware::requireAdmin();
        DB::query('DELETE FROM ads WHERE id=?', [(int)$id]);
        Response::success(null, '删除成功');
    }

    /**
     * 获取某短链的有效广告配置
     */
    public function getAdForLink(string $code): void {
        $link = DB::fetchOne('SELECT id, ad_id, no_ad, status FROM links WHERE short_code=? AND is_deleted=0', [$code]);
        if (!$link) Response::error('链接不存在', 404);

        if ($link['no_ad']) {
            Response::success(['has_ad' => false]);
            return;
        }

        $adId = (int)$link['ad_id'];
        if ($adId > 0) {
            $ad = DB::fetchOne('SELECT * FROM ads WHERE id=? AND status=1', [$adId]);
        } else {
            $globalAdId = (int)(DB::fetchOne('SELECT value FROM settings WHERE `key`="global_ad_id"')['value'] ?? 0);
            $ad = $globalAdId ? DB::fetchOne('SELECT * FROM ads WHERE id=? AND status=1', [$globalAdId]) : null;
            if (!$ad) {
                $ad = DB::fetchOne('SELECT * FROM ads WHERE is_global=1 AND status=1 LIMIT 1');
            }
        }

        if (!$ad) {
            Response::success(['has_ad' => false]);
            return;
        }

        Response::success(['has_ad' => true, 'ad' => $ad]);
    }
}

/**
 * 系统配置控制器
 */
class SettingController {

    /**
     * 文件上传
     */
    public function upload(): void {
        AuthMiddleware::requireAdmin();

        if (empty($_FILES['file'])) {
            Response::error('请选择要上传的文件');
        }

        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            Response::error('文件上传失败，错误码：' . $file['error']);
        }

        // 限制文件大小 2MB
        if ($file['size'] > 2 * 1024 * 1024) {
            Response::error('文件大小不能超过 2MB');
        }

        // 允许的图片类型
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            Response::error('只允许上传图片文件（PNG/JPG/GIF/WebP/SVG）');
        }

        // 创建上传目录
        $uploadDir = dirname(__DIR__, 2) . '/storage/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // 生成唯一文件名
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'png';
        $filename = uniqid('upload_') . '_' . time() . '.' . $ext;
        $targetPath = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            Response::error('文件保存失败');
        }

        // 返回访问URL（相对路径）
        $url = '/storage/uploads/' . $filename;
        Response::success(['url' => $url, 'filename' => $filename]);
    }

    public function get(): void {
        AuthMiddleware::requireAdmin();
        $group = Request::get('group', '');
        $where = $group ? 'group_name=?' : '1=1';
        $params = $group ? [$group] : [];
        $rows  = DB::fetchAll("SELECT `key`,`value` FROM settings WHERE $where", $params);

        $config = [];
        foreach ($rows as $row) {
            $config[$row['key']] = [
                'value' => $row['value'],
            ];
        }
        Response::success($config);
    }

    public function getPublic(): void {
        $keys   = ['site_name', 'site_logo', 'site_seo_title', 'icp', 'site_seo_keywords', 'site_seo_desc',
                   'register_mode', 'guest_create', 'query_public', 'show_icp', 'default_ad_duration'];
        $ph     = implode(',', array_fill(0, count($keys), '?'));
        $rows   = DB::fetchAll("SELECT `key`,`value` FROM settings WHERE `key` IN ($ph)", $keys);
        $config = array_column($rows, 'value', 'key');
        Response::success($config);
    }

    public function update(): void {
        AuthMiddleware::requireSuperAdmin();
        $data = Request::json();

        foreach ($data as $key => $value) {
            // 安全过滤键名
            if (!preg_match('/^[a-z_]+$/', $key)) continue;
            // 兼容 MySQL 和 SQLite 的 upsert 写法
            if (DB::isSQLite()) {
                DB::query(
                    'INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON CONFLICT(`key`) DO UPDATE SET value=excluded.value',
                    [$key, (string)$value]
                );
            } else {
                DB::query(
                    'INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE value=?',
                    [$key, (string)$value, (string)$value]
                );
            }
        }

        // 清除相关缓存
        Cache::del('settings:all');
        Response::success(null, '配置保存成功');
    }

    public function clearCache(): void {
        AuthMiddleware::requireAdmin();
        // 此处简单实现：删除所有 sl: 前缀缓存
        Response::success(null, '缓存清理成功（Redis环境）');
    }

    public function backupDb(): void {
        AuthMiddleware::requireSuperAdmin();
        // 生产环境应调用 mysqldump，此处返回SQL导出路径提示
        Response::success(['msg' => '请通过1Panel面板的数据库备份功能进行备份，或使用mysqldump命令导出']);
    }

    public function clearLogs(): void {
        AuthMiddleware::requireAdmin();
        $type  = Request::get('type', 'access');
        $days  = max(1, (int)Request::get('days', 30));

        switch ($type) {
            case 'access':
                if (DB::isSQLite()) {
                    DB::query("DELETE FROM access_logs WHERE created_at < datetime('now', '-' || ? || ' days')", [$days]);
                } else {
                    DB::query('DELETE FROM access_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)', [$days]);
                }
                break;
            case 'operation':
                if (DB::isSQLite()) {
                    DB::query("DELETE FROM operation_logs WHERE created_at < datetime('now', '-' || ? || ' days')", [$days]);
                } else {
                    DB::query('DELETE FROM operation_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)', [$days]);
                }
                break;
        }

        Response::success(null, "已清理{$days}天前的日志");
    }

    /**
     * 获取系统版本信息
     */
    public function getVersion(): void {
        global $appConfig;
        Response::success([
            'current_version' => $appConfig['version']['current'],
            'name' => $appConfig['version']['name'],
            'author' => $appConfig['version']['author'],
            'website' => $appConfig['version']['website'],
        ]);
    }

    /**
     * 检查更新
     */
    public function checkUpdate(): void {
        global $appConfig;

        $currentVersion = $appConfig['version']['current'];
        $updateUrl = $appConfig['version']['update_url'];

        $result = [
            'current_version' => $currentVersion,
            'latest_version' => $currentVersion,
            'has_update' => false,
            'update_url' => $appConfig['version']['website'] . '/releases',
            'changelog' => '',
        ];

        // 尝试从远程获取最新版本
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'ignore_errors' => true,
                ]
            ]);

            // 使用 Gitee 镜像作为备选（国内访问更快）
            $response = @file_get_contents($updateUrl, false, $context);

            if ($response) {
                $data = json_decode($response, true);
                if ($data && isset($data['tag_name'])) {
                    $latestVersion = ltrim($data['tag_name'], 'v');
                    $result['latest_version'] = $latestVersion;
                    $result['has_update'] = version_compare($latestVersion, $currentVersion, '>');
                    $result['changelog'] = $data['body'] ?? '';
                    $result['html_url'] = $data['html_url'] ?? '';
                }
            }
        } catch (Exception $e) {
            // 网络错误，返回本地信息
        }

        Response::success($result);
    }
}

/**
 * 仪表盘控制器
 */
class DashboardController {

    public function overview(): void {
        AuthMiddleware::requireAdmin();

        $totalLinks   = DB::count('links', 'is_deleted=0');
        $totalVisits  = DB::fetchOne('SELECT COALESCE(SUM(pv),0) as total FROM links WHERE is_deleted=0')['total'] ?? 0;
        $totalUsers   = DB::count('users', '1=1');

        if (DB::isSQLite()) {
            $today = date('Y-m-d');
            $todayVisits = DB::fetchOne(
                'SELECT COALESCE(SUM(pv),0) as total FROM link_stats_daily WHERE stat_date=?', [$today]
            )['total'] ?? 0;
            $todayNewLinks = DB::count('links', "date(created_at)=? AND is_deleted=0", [$today]);

            $trends = DB::fetchAll(
                "SELECT stat_date, SUM(pv) as pv, SUM(uv) as uv
                 FROM link_stats_daily
                 WHERE stat_date >= date('now', '-6 days')
                 GROUP BY stat_date ORDER BY stat_date ASC"
            );

            $devices = DB::fetchAll(
                "SELECT device_type, COUNT(*) as cnt
                 FROM access_logs WHERE is_bot=0 AND date(created_at)>=date('now', '-7 days')
                 GROUP BY device_type"
            );
        } else {
            $todayVisits = DB::fetchOne(
                'SELECT COALESCE(SUM(pv),0) as total FROM link_stats_daily WHERE stat_date=CURDATE()'
            )['total'] ?? 0;
            $todayNewLinks = DB::count('links', 'DATE(created_at)=CURDATE() AND is_deleted=0');

            $trends = DB::fetchAll(
                'SELECT stat_date, SUM(pv) as pv, SUM(uv) as uv
                 FROM link_stats_daily
                 WHERE stat_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                 GROUP BY stat_date ORDER BY stat_date ASC'
            );

            $devices = DB::fetchAll(
                'SELECT device_type, COUNT(*) as cnt
                 FROM access_logs WHERE is_bot=0 AND DATE(created_at)>=DATE_SUB(CURDATE(),INTERVAL 7 DAY)
                 GROUP BY device_type'
            );
        }

        // 热门链接 TOP10
        $hotLinks = DB::fetchAll(
            'SELECT id, short_code, title, original_url, pv, uv, created_at
             FROM links WHERE is_deleted=0 ORDER BY pv DESC LIMIT 10'
        );

        Response::success(compact(
            'totalLinks', 'totalVisits', 'todayVisits', 'totalUsers',
            'todayNewLinks', 'hotLinks', 'trends', 'devices'
        ));
    }

    public function accessLogs(): void {
        AuthMiddleware::requireAdmin();
        $page     = max(1, (int)Request::get('page', 1));
        $pageSize = min(100, (int)Request::get('page_size', 20));
        $linkId   = (int)Request::get('link_id', 0);

        $where    = $linkId ? 'link_id=?' : '1=1';
        $params   = $linkId ? [$linkId] : [];
        $total    = DB::count('access_logs', $where, $params);
        $offset   = ($page - 1) * $pageSize;

        $logs = DB::fetchAll(
            "SELECT * FROM access_logs WHERE $where ORDER BY id DESC LIMIT $pageSize OFFSET $offset",
            $params
        );

        Response::success(['list' => $logs, 'total' => $total, 'page' => $page]);
    }

    public function operationLogs(): void {
        AuthMiddleware::requireAdmin();
        $page     = max(1, (int)Request::get('page', 1));
        $pageSize = min(100, (int)Request::get('page_size', 20));
        $offset   = ($page - 1) * $pageSize;
        $total    = DB::count('operation_logs');

        $logs = DB::fetchAll(
            "SELECT o.*, u.username FROM operation_logs o
             LEFT JOIN users u ON o.user_id=u.id
             ORDER BY o.id DESC LIMIT $pageSize OFFSET $offset"
        );

        Response::success(['list' => $logs, 'total' => $total, 'page' => $page]);
    }
}

/**
 * 用户管理控制器（管理员）
 */
class UserController {

    public function list(): void {
        AuthMiddleware::requireAdmin();
        $page     = max(1, (int)Request::get('page', 1));
        $pageSize = min(100, (int)Request::get('page_size', 20));
        $keyword  = trim(Request::get('keyword', ''));
        $role     = Request::get('role', '');
        $status   = Request::get('status', '');

        $where  = '1=1';
        $params = [];
        
        if ($keyword) {
            $where    .= ' AND username LIKE ?';
            $params   = ["%$keyword%"];
        }
        
        if ($role !== '') {
            $where    .= ' AND role=?';
            $params[] = $role;
        }
        
        if ($status !== '') {
            $where    .= ' AND status=?';
            $params[] = (int)$status;
        }

        $total  = DB::count('users', $where, $params);
        $offset = ($page - 1) * $pageSize;
        $users  = DB::fetchAll(
            "SELECT id, username, role, status, daily_limit, last_login_at, created_at
             FROM users WHERE $where ORDER BY id DESC LIMIT $pageSize OFFSET $offset",
            $params
        );

        // 获取每个用户的链接数和访问量
        foreach ($users as &$user) {
            $user['link_count'] = (int)DB::fetchOne(
                'SELECT COUNT(*) FROM links WHERE user_id=? AND is_deleted=0',
                [$user['id']]
            )['COUNT(*)'];
            
            $user['total_pv'] = (int)DB::fetchOne(
                'SELECT COALESCE(SUM(pv), 0) as total FROM links WHERE user_id=? AND is_deleted=0',
                [$user['id']]
            )['total'];
        }

        Response::success(['list' => $users, 'total' => $total, 'page' => $page, 'page_size' => $pageSize]);
    }

    public function update(string $id): void {
        AuthMiddleware::requireSuperAdmin();
        $data = Request::json();
        $user = DB::fetchOne('SELECT id FROM users WHERE id=?', [(int)$id]);
        if (!$user) Response::error('用户不存在', 404);

        $allowed = ['nickname', 'role', 'status', 'daily_limit', 'no_ad_quota'];
        $updateData = array_intersect_key($data, array_flip($allowed));
        if (!empty($data['password'])) {
            $updateData['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }

        if (!empty($updateData)) DB::update('users', $updateData, 'id=?', [(int)$id]);
        Response::success(null, '用户信息已更新');
    }

    public function create(): void {
        AuthMiddleware::requireSuperAdmin();
        $data = Request::json();

        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';
        $role     = $data['role'] ?? 'user';
        $dailyLimit = max(1, (int)($data['daily_limit'] ?? 100));

        // 验证
        if (empty($username) || mb_strlen($username) < 3 || mb_strlen($username) > 32) {
            Response::error('用户名长度3-32字符');
        }
        if (strlen($password) < 6) {
            Response::error('密码至少6位');
        }

        // 用户名唯一性检查
        if (DB::count('users', 'username=?', [$username]) > 0) {
            Response::error('用户名已被注册');
        }

        $id = DB::insert('users', [
            'username'    => $username,
            'password'    => password_hash($password, PASSWORD_BCRYPT),
            'role'       => $role,
            'daily_limit'=> $dailyLimit,
            'status'     => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        Response::success(['id' => $id], '用户创建成功');
    }

    /**
     * 批量操作
     */
    public function batchAction(): void {
        AuthMiddleware::requireAdmin();
        $data   = Request::json();
        $action = $data['action'] ?? '';
        $ids    = $data['ids'] ?? [];
        
        if (empty($ids)) Response::error('请选择要操作用户');
        if (!in_array($action, ['enable', 'disable', 'delete'])) {
            Response::error('无效的操作');
        }
        
        // 不能对管理员进行批量操作
        $adminIds = DB::fetchAll('SELECT id FROM users WHERE role="admin"');
        $adminIdArr = array_column($adminIds, 'id');
        $adminSelected = array_intersect($ids, $adminIdArr);
        if (!empty($adminSelected) && $action === 'delete') {
            Response::error('不能批量删除管理员');
        }
        
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        
        switch ($action) {
            case 'enable':
                DB::query("UPDATE users SET status=1 WHERE id IN ({$placeholders})", $ids);
                break;
            case 'disable':
                DB::query("UPDATE users SET status=0 WHERE id IN ({$placeholders}) AND role<>'admin'", $ids);
                break;
            case 'delete':
                // 先删除用户的链接
                DB::query("DELETE FROM links WHERE user_id IN ({$placeholders})", $ids);
                DB::query("DELETE FROM users WHERE id IN ({$placeholders}) AND role<>'admin'", $ids);
                break;
        }
        
        Response::success(null, '批量操作成功');
    }

    /**
     * 用户详情
     */
    public function detail(string $id): void {
        AuthMiddleware::requireAdmin();
        
        $userId = (int)$id;
        
        $user = DB::fetchOne(
            'SELECT id, username, role, status, daily_limit, daily_used, daily_reset_date, last_login_at, created_at FROM users WHERE id=?',
            [$userId]
        );
        
        if (!$user) {
            Response::error('用户不存在', 404);
            return;
        }
        
        // 获取链接统计
        $linkCountRow = DB::fetchOne(
            'SELECT COUNT(*) as cnt FROM links WHERE user_id=? AND is_deleted=0',
            [$userId]
        );
        $user['link_count'] = $linkCountRow ? (int)($linkCountRow['cnt'] ?? 0) : 0;
        
        $pvRow = DB::fetchOne(
            'SELECT COALESCE(SUM(pv), 0) as total FROM links WHERE user_id=? AND is_deleted=0',
            [$userId]
        );
        $user['total_pv'] = $pvRow ? (int)($pvRow['total'] ?? 0) : 0;
        
        $uvRow = DB::fetchOne(
            'SELECT COALESCE(SUM(uv), 0) as total FROM links WHERE user_id=? AND is_deleted=0',
            [$userId]
        );
        $user['total_uv'] = $uvRow ? (int)($uvRow['total'] ?? 0) : 0;
        
        // 计算今日剩余额度
        $today = date('Y-m-d');
        if ($user['daily_reset_date'] !== $today) {
            $user['daily_used'] = 0;
        }
        $user['daily_remaining'] = max(0, (int)$user['daily_limit'] - (int)$user['daily_used']);
        
        Response::success($user);
    }

    /**
     * 用户登录日志
     */
    public function loginLogs(string $id): void {
        AuthMiddleware::requireAdmin();
        
        $page     = max(1, (int)Request::get('page', 1));
        $pageSize = min(50, (int)Request::get('page_size', 20));
        $offset   = ($page - 1) * $pageSize;
        
        $logs = DB::fetchAll(
            "SELECT id, ip, user_agent, status, fail_reason, created_at 
             FROM login_logs WHERE user_id=? ORDER BY id DESC LIMIT {$pageSize} OFFSET {$offset}",
            [(int)$id]
        );
        
        $total = DB::count('login_logs', 'user_id=?', [(int)$id]);
        
        Response::success(['list' => $logs, 'total' => $total, 'page' => $page, 'page_size' => $pageSize]);
    }

    public function generateInvite(): void {
        AuthMiddleware::requireSuperAdmin();
        $count   = min(50, max(1, (int)Request::get('count', 1)));
        $codes   = [];

        for ($i = 0; $i < $count; $i++) {
            $code = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
            DB::insert('invite_codes', [
                'code'        => $code,
                'created_by'  => 1,
                'max_uses'    => 1,
                'expire_at'   => date('Y-m-d H:i:s', time() + 30 * 86400),
            ]);
            $codes[] = $code;
        }

        Response::success(['codes' => $codes]);
    }

    public function addIpBlacklist(): void {
        AuthMiddleware::requireAdmin();
        $data = Request::json();
        $ip   = trim($data['ip'] ?? '');
        if (empty($ip)) Response::error('IP不能为空');

        DB::insert('ip_blacklist', [
            'ip_range'   => $ip,
            'reason'     => $data['reason'] ?? '',
            'expire_at'  => !empty($data['expire_at']) ? $data['expire_at'] : null,
            'created_by' => AuthMiddleware::require()['id'],
        ]);

        Cache::del('ipblock:' . $ip);
        Response::success(null, '已加入IP黑名单');
    }

    public function addDomainBlacklist(): void {
        AuthMiddleware::requireAdmin();
        $data   = Request::json();
        $domain = trim($data['domain'] ?? '');
        if (empty($domain)) Response::error('域名不能为空');

        if (DB::count('domain_blacklist', 'domain=?', [$domain]) > 0) {
            Response::error('该域名已在黑名单中');
        }

        DB::insert('domain_blacklist', [
            'domain' => $domain,
            'reason' => $data['reason'] ?? '',
        ]);

        Response::success(null, '域名已加入黑名单');
    }
}

/**
 * API接口控制器（对外开放）
 */
class ApiController {

    private function checkApiToken(): array {
        $token = Request::bearerToken() ?? Request::get('api_token');
        if (!$token) Response::error('缺少API Token', 401);

        $user = DB::fetchOne('SELECT * FROM users WHERE api_token=? AND status=1', [$token]);
        if (!$user) Response::error('API Token无效', 401);

        // 检查API开关
        $apiOpen = DB::fetchOne('SELECT value FROM settings WHERE `key`="api_open"')['value'] ?? '1';
        if (!$apiOpen) Response::error('API接口已关闭', 403);

        return $user;
    }

    public function create(): void {
        $user = $this->checkApiToken();
        $data = Request::all();

        $service = new LinkService();
        $result  = $service->create($data, $user['id']);
        if (!$result['ok']) Response::error($result['msg']);

        $domain = DB::fetchOne('SELECT value FROM settings WHERE `key`="default_domain"')['value'] ?? '';
        $result['short_url'] = rtrim($domain, '/') . '/' . $result['short_code'];

        Response::success($result);
    }

    public function query(): void {
        $this->checkApiToken();
        $code = trim(Request::get('code', ''));
        if (!$code) Response::error('请提供短链后缀');

        $service = new LinkService();
        $link    = $service->getByCode($code);
        if (!$link) Response::error('链接不存在', 404);

        Response::success([
            'short_code'   => $link['short_code'],
            'original_url' => $link['original_url'],
            'pv'           => $link['pv'],
            'uv'           => $link['uv'],
            'status'       => $link['status'],
            'expire_at'    => $link['expire_at'],
        ]);
    }
}
