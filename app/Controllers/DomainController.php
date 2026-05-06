<?php
/**
 * 域名管理控制器
 */
class DomainController {

    /**
     * 域名列表
     */
    public function list(): void {
        AuthMiddleware::requireAdmin();
        
        $domains = DB::fetchAll(
            'SELECT d.*, u.username as created_by_name 
             FROM domains d 
             LEFT JOIN users u ON d.created_by=u.id 
             ORDER BY d.is_default DESC, d.id DESC'
        );
        
        Response::success($domains);
    }

    /**
     * 获取所有可用的域名（下拉选择用）
     */
    public function available(): void {
        $domains = DB::fetchAll(
            'SELECT id, domain, name, is_default FROM domains WHERE status=1 ORDER BY is_default DESC, id ASC'
        );
        Response::success($domains);
    }

    /**
     * 添加域名
     */
    public function create(): void {
        AuthMiddleware::requireAdmin();
        $data = Request::json();
        
        $domain = trim($data['domain'] ?? '');
        if (empty($domain)) Response::error('域名不能为空');
        
        // 简单域名格式校验
        if (!preg_match('/^([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/', $domain)) {
            Response::error('域名格式不正确');
        }
        
        // 检查是否已存在
        if (DB::count('domains', 'domain=?', [$domain]) > 0) {
            Response::error('该域名已存在');
        }
        
        $isDefault = (int)($data['is_default'] ?? 0);
        
        // 如果设为默认，先取消其他默认
        if ($isDefault) {
            DB::query('UPDATE domains SET is_default=0');
        }
        
        $id = DB::insert('domains', [
            'domain'       => $domain,
            'name'        => $data['name'] ?? '',
            'is_default'  => $isDefault,
            'status'      => (int)($data['status'] ?? 1),
            'parse_type'  => $data['parse_type'] ?? 'cname',
            'parse_value' => $data['parse_value'] ?? '',
            'parse_status'=> 'pending',
            'created_by'  => AuthMiddleware::require()['id'],
        ]);
        
        // 记录操作日志
        $this->logOperation('create', 'domains', $id, "添加域名: {$domain}");
        
        Response::success(['id' => $id], '域名添加成功');
    }

    /**
     * 更新域名
     */
    public function update(string $id): void {
        AuthMiddleware::requireAdmin();
        $data = Request::json();
        
        $domain = DB::fetchOne('SELECT * FROM domains WHERE id=?', [(int)$id]);
        if (!$domain) Response::error('域名不存在', 404);
        
        // 如果设为默认，先取消其他默认
        if (!empty($data['is_default']) && !$domain['is_default']) {
            DB::query('UPDATE domains SET is_default=0');
        }
        
        $allowed = ['name', 'is_default', 'status', 'parse_type', 'parse_value', 'parse_status', 'parse_msg'];
        $updateData = [];
        
        foreach ($allowed as $key) {
            if (isset($data[$key])) {
                $updateData[$key] = $data[$key];
            }
        }
        
        if (!empty($updateData)) {
            DB::update('domains', $updateData, 'id=?', [(int)$id]);
            $this->logOperation('update', 'domains', $id, "更新域名: {$domain['domain']}");
        }
        
        Response::success(null, '域名已更新');
    }

    /**
     * 删除域名
     */
    public function delete(string $id): void {
        AuthMiddleware::requireAdmin();
        
        $domain = DB::fetchOne('SELECT * FROM domains WHERE id=?', [(int)$id]);
        if (!$domain) Response::error('域名不存在', 404);
        
        // 检查是否有链接使用该域名
        if ($domain['link_count'] > 0) {
            Response::error("该域名已被 {$domain['link_count']} 个链接使用，无法删除");
        }
        
        DB::query('DELETE FROM domains WHERE id=?', [(int)$id]);
        $this->logOperation('delete', 'domains', $id, "删除域名: {$domain['domain']}");
        
        Response::success(null, '域名已删除');
    }

    /**
     * 批量操作
     */
    public function batchAction(): void {
        AuthMiddleware::requireAdmin();
        $data = Request::json();
        
        $action = $data['action'] ?? '';
        $ids = $data['ids'] ?? [];
        
        if (empty($ids)) Response::error('请选择要操作的域名');
        if (!in_array($action, ['enable', 'disable', 'delete'])) {
            Response::error('无效的操作');
        }
        
        // 检查删除操作是否有链接关联
        if ($action === 'delete') {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $linked = DB::fetchAll(
                "SELECT id, domain, link_count FROM domains WHERE id IN ({$placeholders}) AND link_count > 0",
                $ids
            );
            if (!empty($linked)) {
                $names = implode(', ', array_column($linked, 'domain'));
                Response::error("以下域名有链接关联无法删除: {$names}");
            }
        }
        
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        
        switch ($action) {
            case 'enable':
                DB::query("UPDATE domains SET status=1 WHERE id IN ({$placeholders})", $ids);
                break;
            case 'disable':
                DB::query("UPDATE domains SET status=0 WHERE id IN ({$placeholders})", $ids);
                break;
            case 'delete':
                DB::query("DELETE FROM domains WHERE id IN ({$placeholders}) AND link_count=0", $ids);
                break;
        }
        
        $this->logOperation('batch', 'domains', 0, "批量{$action}: " . count($ids) . "个域名");
        
        Response::success(null, '批量操作成功');
    }

    /**
     * 检测域名DNS解析状态
     */
    public function checkDns(string $id): void {
        AuthMiddleware::requireAdmin();
        
        $domain = DB::fetchOne('SELECT * FROM domains WHERE id=?', [(int)$id]);
        if (!$domain) Response::error('域名不存在', 404);
        
        $domainName = $domain['domain'];
        $result = [
            'domain' => $domainName,
            'cname' => null,
            'a' => [],
            'is_resolved' => false,
            'status' => 'pending',
            'message' => '',
        ];
        
        // 获取默认域名（用于比较）
        $defaultDomain = DB::fetchOne('SELECT value FROM settings WHERE `key`="default_domain"')['value'] ?? '';
        $serverIp = $_SERVER['SERVER_ADDR'] ?? gethostbyname(gethostname());
        
        try {
            // 检测 CNAME 记录
            $cname = @dns_get_record($domainName, DNS_CNAME);
            if (!empty($cname)) {
                $result['cname'] = $cname[0]['target'] ?? null;
            }
            
            // 检测 A 记录
            $aRecords = @dns_get_record($domainName, DNS_A);
            if (!empty($aRecords)) {
                foreach ($aRecords as $record) {
                    $result['a'][] = $record['ip'] ?? '';
                }
            }
            
            // 判断解析状态
            if (!empty($result['cname']) || !empty($result['a'])) {
                $result['is_resolved'] = true;
                $result['status'] = 'resolved';
                $result['message'] = 'DNS解析正常';
                
                // 更新数据库状态
                DB::update('domains', ['parse_status' => 'resolved'], 'id=?', [(int)$id]);
            } else {
                $result['status'] = 'unresolved';
                $result['message'] = '未检测到DNS解析记录';
                DB::update('domains', ['parse_status' => 'unresolved', 'parse_msg' => $result['message']], 'id=?', [(int)$id]);
            }
        } catch (\Exception $e) {
            $result['status'] = 'error';
            $result['message'] = 'DNS检测失败: ' . $e->getMessage();
            DB::update('domains', ['parse_status' => 'error', 'parse_msg' => $result['message']], 'id=?', [(int)$id]);
        }
        
        Response::success($result);
    }

    /**
     * 获取域名关联的链接列表
     */
    public function links(string $id): void {
        AuthMiddleware::requireAdmin();
        
        $domain = DB::fetchOne('SELECT * FROM domains WHERE id=?', [(int)$id]);
        if (!$domain) Response::error('域名不存在', 404);
        
        $page = max(1, (int)($_GET['page'] ?? 1));
        $pageSize = min(100, max(10, (int)($_GET['page_size'] ?? 20)));
        $offset = ($page - 1) * $pageSize;
        
        $where = 'domain_id=?';
        $params = [(int)$id];
        
        // 搜索条件
        if (!empty($_GET['keyword'])) {
            $where .= ' AND (short_code LIKE ? OR original_url LIKE ?)';
            $params[] = '%' . $_GET['keyword'] . '%';
            $params[] = '%' . $_GET['keyword'] . '%';
        }
        
        // 状态筛选
        if (isset($_GET['status']) && $_GET['status'] !== '') {
            $where .= ' AND status=?';
            $params[] = (int)$_GET['status'];
        }
        
        // 获取列表
        $list = DB::fetchAll(
            "SELECT id, short_code, original_url, pv, uv, status, created_at 
             FROM links WHERE {$where} ORDER BY id DESC LIMIT {$pageSize} OFFSET {$offset}",
            $params
        );
        
        $total = DB::count('links', explode(' AND', $where)[0], [array_slice($params, 0, 1)[0]]);
        
        // 获取默认域名
        $defaultDomain = DB::fetchOne('SELECT value FROM settings WHERE `key`="default_domain"')['value'] ?? '';
        
        // 格式化短链接
        foreach ($list as &$link) {
            $domainUrl = $defaultDomain ?: 'http://short.link';
            $link['short_url'] = rtrim($domainUrl, '/') . '/' . $link['short_code'];
        }
        
        Response::success([
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
        ]);
    }

    /**
     * 记录操作日志
     */
    private function logOperation(string $action, string $targetType, int $targetId, string $desc): void {
        $user = AuthMiddleware::require();
        DB::insert('operation_logs', [
            'user_id'     => $user['id'],
            'action'      => $action,
            'target_type' => $targetType,
            'target_id'   => (string)$targetId,
            'description' => $desc,
            'ip'          => Request::ip(),
        ]);
    }
}
