<?php
/**
 * 短链接控制器
 */
class LinkController {
    private LinkService $service;

    public function __construct() {
        $this->service = new LinkService();
    }

    /**
     * 创建短链接（前台/API）
     */
    public function create(): void {
        $ip   = Request::ip();
        $data = Request::all();

        // 全局设置读取
        $guestCreate = DB::fetchOne('SELECT value FROM settings WHERE `key`="guest_create"')['value'] ?? '1';

        // 判断登录状态
        $user = AuthMiddleware::user();

        // 游客限制
        if (!$user && !$guestCreate) {
            Response::error('请先登录后再创建短链接');
        }

        // IP检查（黑名单）
        IpBlockMiddleware::check($ip);

        // 登录用户每日额度检查
        if ($user) {
            $today = date('Y-m-d');
            $todayCount = DB::isSQLite()
                ? DB::count('links', 'user_id=? AND date(created_at)=? AND is_deleted=0', [$user['id'], $today])
                : DB::count('links', 'user_id=? AND DATE(created_at)=CURDATE() AND is_deleted=0', [$user['id']]);
            if ($todayCount >= $user['daily_limit']) {
                Response::error("今日创建上限 {$user['daily_limit']} 条，已用完");
            }
        }

        $result = $this->service->create($data, $user['id'] ?? 0);
        if (!$result['ok']) Response::error($result['msg']);

        // 拼接完整短链URL
        $domain = $this->getDefaultDomain();
        $result['short_url'] = rtrim($domain, '/') . '/' . $result['short_code'];

        // 操作日志
        if ($user) {
            DB::insert('operation_logs', [
                'user_id'     => $user['id'],
                'action'      => 'create_link',
                'target_type' => 'link',
                'target_id'   => $result['id'],
                'description' => '创建短链: ' . $result['short_code'],
                'ip'          => $ip,
            ]);
        }

        Response::success($result, '短链接创建成功');
    }

    /**
     * 批量创建
     */
    public function batchCreate(): void {
        $user  = AuthMiddleware::require();
        $data  = Request::json();
        $urls  = $data['urls'] ?? [];

        if (empty($urls) || !is_array($urls)) Response::error('请提供URL列表');
        if (count($urls) > 100) Response::error('批量创建每次最多100条');

        $results = $this->service->batchCreate($urls, $user['id'], $data['options'] ?? []);
        $domain  = $this->getDefaultDomain();
        foreach ($results as &$r) {
            if ($r['ok']) {
                $r['short_url'] = rtrim($domain, '/') . '/' . $r['short_code'];
            }
        }

        Response::success(['results' => $results, 'total' => count($urls)]);
    }

    /**
     * 获取我的链接列表
     */
    public function myLinks(): void {
        $user = AuthMiddleware::require();
        $this->listLinks($user['id']);
    }

    /**
     * 管理员获取所有链接
     */
    public function adminLinks(): void {
        AuthMiddleware::requireAdmin();
        $this->listLinks(null);
    }

    private function listLinks(?int $userId): void {
        $page     = max(1, (int)Request::get('page', 1));
        $pageSize = min(100, max(10, (int)Request::get('page_size', 20)));
        $keyword  = trim(Request::get('keyword', ''));
        $status   = Request::get('status', '');
        $groupId  = (int)Request::get('group_id', 0);
        $deleted  = Request::get('deleted', '0') === '1';

        $where  = ['is_deleted=?'];
        $params = [$deleted ? 1 : 0];

        if ($userId !== null) {
            $where[]  = 'user_id=?';
            $params[] = $userId;
        }
        if ($keyword !== '') {
            $where[]  = '(short_code LIKE ? OR original_url LIKE ? OR title LIKE ?)';
            $params[] = "%$keyword%";
            $params[] = "%$keyword%";
            $params[] = "%$keyword%";
        }
        if ($status !== '') {
            $where[]  = 'status=?';
            $params[] = (int)$status;
        }
        if ($groupId > 0) {
            $where[]  = 'group_id=?';
            $params[] = $groupId;
        }

        $whereStr = implode(' AND ', $where);
        $total    = DB::count('links', $whereStr, $params);
        $offset   = ($page - 1) * $pageSize;

        $links = DB::fetchAll(
            "SELECT l.*, u.username as creator_name
             FROM links l LEFT JOIN users u ON l.user_id=u.id
             WHERE $whereStr
             ORDER BY l.id DESC LIMIT $pageSize OFFSET $offset",
            $params
        );

        $domain = $this->getDefaultDomain();
        foreach ($links as &$link) {
            $link['short_url'] = rtrim($domain, '/') . '/' . $link['short_code'];
        }

        Response::success([
            'list'      => $links,
            'total'     => $total,
            'page'      => $page,
            'page_size' => $pageSize,
        ]);
    }

    /**
     * 获取单条链接详情
     */
    public function detail(string $id): void {
        $user = AuthMiddleware::require();
        $link = DB::fetchOne('SELECT * FROM links WHERE id=?', [(int)$id]);

        if (!$link) Response::error('链接不存在', 404);
        if ($user['role'] == 2 && $link['user_id'] != $user['id']) Response::error('无权访问', 403);

        $domain = $this->getDefaultDomain();
        $link['short_url'] = rtrim($domain, '/') . '/' . $link['short_code'];

        Response::success($link);
    }

    /**
     * 更新链接
     */
    public function update(string $id): void {
        $user = AuthMiddleware::require();
        $link = DB::fetchOne('SELECT * FROM links WHERE id=? AND is_deleted=0', [(int)$id]);

        if (!$link) Response::error('链接不存在', 404);
        if ($user['role'] == 2 && $link['user_id'] != $user['id']) Response::error('无权操作', 403);

        $data = Request::json();
        $updateData = [];

        // 可更新字段
        foreach (['original_url', 'title', 'group_id', 'password', 'max_visits', 'ad_id', 'no_ad', 'allow_ips', 'expire_at'] as $field) {
            if (isset($data[$field])) $updateData[$field] = $data[$field];
        }

        // 修改短码
        if (!empty($data['short_code']) && $data['short_code'] !== $link['short_code']) {
            $check = $this->service->validateCustomCode($data['short_code']);
            if (!$check['ok']) Response::error($check['msg']);
            $updateData['short_code'] = $data['short_code'];
            Cache::del('link:' . $link['short_code']);
        }

        if (isset($data['status'])) $updateData['status'] = (int)$data['status'];
        if (!empty($data['expire_days'])) {
            $updateData['expire_at'] = date('Y-m-d H:i:s', time() + (int)$data['expire_days'] * 86400);
        }

        if (!empty($updateData)) {
            DB::update('links', $updateData, 'id=?', [(int)$id]);
            Cache::del('link:' . ($updateData['short_code'] ?? $link['short_code']));
        }

        Response::success(null, '更新成功');
    }

    /**
     * 删除（移入回收站）
     */
    public function delete(string $id): void {
        $user = AuthMiddleware::require();
        $link = DB::fetchOne('SELECT * FROM links WHERE id=? AND is_deleted=0', [(int)$id]);

        if (!$link) Response::error('链接不存在', 404);
        if ($user['role'] == 2 && $link['user_id'] != $user['id']) Response::error('无权操作', 403);

        DB::update('links', [
            'is_deleted' => 1,
            'deleted_at' => date('Y-m-d H:i:s'),
        ], 'id=?', [(int)$id]);

        Cache::del('link:' . $link['short_code']);
        Response::success(null, '已移入回收站');
    }

    /**
     * 从回收站恢复
     */
    public function restore(string $id): void {
        $user = AuthMiddleware::require();
        $link = DB::fetchOne('SELECT * FROM links WHERE id=? AND is_deleted=1', [(int)$id]);

        if (!$link) Response::error('链接不存在', 404);
        if ($user['role'] == 2 && $link['user_id'] != $user['id']) Response::error('无权操作', 403);

        DB::update('links', ['is_deleted' => 0, 'deleted_at' => null], 'id=?', [(int)$id]);
        Response::success(null, '恢复成功');
    }

    /**
     * 永久删除
     */
    public function forceDelete(string $id): void {
        $user = AuthMiddleware::requireAdmin();
        DB::query('DELETE FROM links WHERE id=?', [(int)$id]);
        DB::query('DELETE FROM access_logs WHERE link_id=?', [(int)$id]);
        Response::success(null, '已永久删除');
    }

    /**
     * 批量操作
     */
    public function batchAction(): void {
        $user   = AuthMiddleware::require();
        $data   = Request::json();
        $action = $data['action'] ?? '';
        $ids    = array_map('intval', $data['ids'] ?? []);

        if (empty($ids)) Response::error('请选择要操作的链接');
        if (count($ids) > 200) Response::error('批量操作每次最多200条');

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        // 权限过滤
        if ($user['role'] == 2) {
            $userIds = DB::fetchAll(
                "SELECT id FROM links WHERE id IN ($placeholders) AND user_id=?",
                [...$ids, $user['id']]
            );
            $ids = array_column($userIds, 'id');
            if (empty($ids)) Response::error('没有可操作的链接');
        }

        switch ($action) {
            case 'enable':
                DB::query("UPDATE links SET status=1 WHERE id IN ($placeholders)", $ids);
                Response::success(null, '批量启用成功');
            case 'disable':
                DB::query("UPDATE links SET status=0 WHERE id IN ($placeholders)", $ids);
                Response::success(null, '批量禁用成功');
            case 'delete':
                $now = date('Y-m-d H:i:s');
                DB::query("UPDATE links SET is_deleted=1, deleted_at=? WHERE id IN ($placeholders)", array_merge([$now], $ids));
                Response::success(null, '批量移入回收站');
            case 'restore':
                DB::query("UPDATE links SET is_deleted=0 WHERE id IN ($placeholders)", $ids);
                Response::success(null, '批量恢复成功');
            default:
                Response::error('未知操作');
        }
    }

    /**
     * 链接统计详情
     */
    public function stats(string $id): void {
        $user = AuthMiddleware::require();
        $link = DB::fetchOne('SELECT * FROM links WHERE id=?', [(int)$id]);
        if (!$link) Response::error('链接不存在', 404);
        if ($user['role'] == 2 && $link['user_id'] != $user['id']) Response::error('无权访问', 403);

        $period = Request::get('period', '7d');
        $stats  = $this->service->getStats((int)$id, $period);

        Response::success(array_merge(['link' => $link], $stats));
    }

    /**
     * 公开查询短链信息
     */
    public function publicQuery(): void {
        $queryPublic = DB::fetchOne('SELECT value FROM settings WHERE `key`="query_public"')['value'] ?? '1';
        if (!$queryPublic) Response::error('公开查询功能已关闭');

        $code = trim(Request::get('code', ''));
        if (empty($code)) Response::error('请输入短链后缀');

        $link = $this->service->getByCode($code);
        if (!$link) Response::error('链接不存在', 404);

        Response::success([
            'short_code' => $link['short_code'],
            'pv'         => $link['pv'],
            'uv'         => $link['uv'],
            'created_at' => $link['created_at'],
            'expire_at'  => $link['expire_at'],
            'status'     => $link['status'],
        ]);
    }

    /**
     * 导出统计数据
     */
    public function export(string $id): void {
        $user = AuthMiddleware::require();
        $link = DB::fetchOne('SELECT * FROM links WHERE id=?', [(int)$id]);
        if (!$link) Response::error('链接不存在', 404);
        if ($user['role'] == 2 && $link['user_id'] != $user['id']) Response::error('无权访问', 403);

        $logs = DB::fetchAll(
            'SELECT created_at, ip, ip_location, device_type, os, browser, referer
             FROM access_logs WHERE link_id=? AND is_bot=0 ORDER BY created_at DESC LIMIT 10000',
            [$link['id']]
        );

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="stats_' . $link['short_code'] . '.csv"');
        header('Cache-Control: max-age=0');

        $out = fopen('php://output', 'w');
        fprintf($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
        fputcsv($out, ['访问时间', 'IP地址', '归属地', '设备类型', '操作系统', '浏览器', '来源']);
        foreach ($logs as $row) {
            fputcsv($out, [
                $row['created_at'], $row['ip'], $row['ip_location'],
                $row['device_type'], $row['os'], $row['browser'], $row['referer'],
            ]);
        }
        fclose($out);
        exit;
    }

    /**
     * 生成二维码（返回base64）
     */
    public function qrcode(string $id): void {
        $link = DB::fetchOne('SELECT short_code FROM links WHERE id=?', [(int)$id]);
        if (!$link) Response::error('链接不存在', 404);

        $domain   = $this->getDefaultDomain();
        $shortUrl = rtrim($domain, '/') . '/' . $link['short_code'];

        // 使用第三方免费API生成二维码
        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($shortUrl);
        Response::success(['qr_url' => $qrUrl, 'short_url' => $shortUrl]);
    }

    /**
     * CSV批量导入短链接
     */
    public function importCsv(): void {
        $user = AuthMiddleware::require();
        
        // 获取上传的文件
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            Response::error('请上传CSV文件');
        }
        
        $file = $_FILES['file'];
        if ($file['size'] > 5 * 1024 * 1024) {
            Response::error('文件大小不能超过5MB');
        }
        
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            Response::error('只支持CSV格式文件');
        }
        
        // 读取CSV
        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            Response::error('无法读取文件');
        }
        
        $results = ['success' => 0, 'failed' => 0, 'errors' => []];
        $rowNum = 0;
        $urls = [];
        
        // 跳过 BOM 和标题行
        $firstRow = fgets($handle);
        if (strpos($firstRow, "\xEF\xBB\xBF") === 0) {
            $firstRow = substr($firstRow, 3);
        }
        
        // 检查第一行是否是标题
        $firstRow = trim($firstRow);
        if (preg_match('/^(original_url|url|链接地址)/i', $firstRow)) {
            // 第一行是标题，跳过
        } else {
            // 尝试解析为数据
            $urls[] = $firstRow;
        }
        
        // 读取剩余行
        while (($line = fgets($handle)) !== false) {
            $rowNum++;
            $line = trim($line);
            if (empty($line)) continue;
            
            // 支持 CSV 格式：逗号分隔
            if (strpos($line, ',') !== false) {
                $parts = str_getcsv($line);
            } else {
                $parts = ["\t" => "\t"];
                $parts[0] = $line;
            }
            
            $originalUrl = trim($parts[0]);
            
            // 验证URL格式
            if (!filter_var($originalUrl, FILTER_VALIDATE_URL) && !preg_match('/^https?:\/\/.+/i', $originalUrl)) {
                $results['failed']++;
                $results['errors'][] = "第{$rowNum}行：无效的URL格式 - " . substr($originalUrl, 0, 50);
                continue;
            }
            
            $urls[] = $originalUrl;
        }
        fclose($handle);
        
        if (empty($urls)) {
            Response::error('CSV文件中没有有效的链接数据');
        }
        
        if (count($urls) > 500) {
            Response::error('批量导入每次最多500条，当前文件有' . count($urls) . '条');
        }
        
        // 批量创建
        $createResults = $this->service->batchCreate($urls, $user['id'], []);
        
        foreach ($createResults as $r) {
            if ($r['ok']) {
                $results['success']++;
            } else {
                $results['failed']++;
                $results['errors'][] = $r['msg'];
            }
        }
        
        // 操作日志
        DB::insert('operation_logs', [
            'user_id'     => $user['id'],
            'action'      => 'import_links',
            'target_type' => 'link',
            'description' => sprintf('批量导入: 成功%d条, 失败%d条', $results['success'], $results['failed']),
            'ip'          => Request::ip(),
        ]);
        
        Response::success($results, sprintf('导入完成：成功 %d 条，失败 %d 条', $results['success'], $results['failed']));
    }

    /**
     * 导出链接列表
     */
    public function exportLinks(): void {
        $user = AuthMiddleware::require();
        $keyword  = trim(Request::get('keyword', ''));
        $status   = Request::get('status', '');
        $deleted  = Request::get('deleted', '0') === '1';
        
        $where  = ['is_deleted=?'];
        $params = [$deleted ? 1 : 0];
        
        if ($user['role'] == 2) {
            $where[]  = 'user_id=?';
            $params[] = $user['id'];
        }
        
        if ($keyword !== '') {
            $where[]  = '(short_code LIKE ? OR original_url LIKE ? OR title LIKE ?)';
            $params[] = "%$keyword%";
            $params[] = "%$keyword%";
            $params[] = "%$keyword%";
        }
        
        if ($status !== '') {
            $where[]  = 'status=?';
            $params[] = (int)$status;
        }
        
        $whereStr = implode(' AND ', $where);
        $links = DB::fetchAll(
            "SELECT short_code, original_url, title, pv, uv, ip_count, status, expire_at, created_at
             FROM links WHERE $whereStr ORDER BY id DESC LIMIT 5000",
            $params
        );
        
        $domain = $this->getDefaultDomain();
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="links_export_' . date('Ymd_His') . '.csv"');
        header('Cache-Control: max-age=0');
        
        $out = fopen('php://output', 'w');
        fprintf($out, "\xEF\xBB\xBF"); // UTF-8 BOM
        fputcsv($out, ['短链后缀', '完整短链', '原始链接', '备注', 'PV', 'UV', 'IP数', '状态', '过期时间', '创建时间']);
        
        foreach ($links as $row) {
            $statusText = $row['status'] == 1 ? '正常' : ($row['status'] == 0 ? '禁用' : '已过期');
            fputcsv($out, [
                $row['short_code'],
                rtrim($domain, '/') . '/' . $row['short_code'],
                $row['original_url'],
                $row['title'] ?? '',
                $row['pv'],
                $row['uv'],
                $row['ip_count'],
                $statusText,
                $row['expire_at'] ?? '永久',
                $row['created_at'],
            ]);
        }
        fclose($out);
        exit;
    }

    private function getDefaultDomain(): string {
        $row = DB::fetchOne('SELECT value FROM settings WHERE `key`="default_domain"');
        return $row['value'] ?: (
            (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
            . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
        );
    }
}
