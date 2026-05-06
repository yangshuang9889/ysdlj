<?php
/**
 * 认证控制器 - 登录/注册/Token管理
 */
class AuthController {

    public function login(): void {
        $data = Request::json();
        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';
        $ip = Request::ip();
        $ua = Request::userAgent();

        if (empty($username) || empty($password)) {
            // 记录失败日志
            $this->logLogin(0, $username, $ip, $ua, 0, '用户名或密码为空');
            Response::error('用户名和密码不能为空');
        }

        // 检查是否有任何有效用户
        $anyUser = DB::count('users', 'status=1');
        if ($anyUser === 0) {
            Response::error('系统中没有有效用户，请联系管理员');
        }

        $user = DB::fetchOne(
            'SELECT * FROM users WHERE username=? AND status=1',
            [$username]
        );

        if (!$user || !password_verify($password, $user['password'])) {
            // 记录失败日志
            $this->logLogin(0, $username, $ip, $ua, 0, '用户名或密码错误');
            Response::error('用户名或密码错误');
        }

        // 检查用户状态
        if ($user['status'] != 1) {
            $this->logLogin($user['id'], $username, $ip, $ua, 0, '账户已被禁用');
            Response::error('账户已被禁用');
        }

        // 更新登录信息
        DB::update('users', [
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip' => $ip,
        ], 'id=?', [$user['id']]);

        // 记录成功日志
        $this->logLogin($user['id'], $username, $ip, $ua, 1);

        $token = JWT::encode([
            'uid'  => $user['id'],
            'role' => $user['role'] == 1 ? 'admin' : 'user',
        ]);

        Response::success([
            'token' => $token,
            'user'  => $this->safeUser($user),
        ], '登录成功');
    }

    /**
     * 记录登录日志
     */
    private function logLogin(int $userId, string $username, string $ip, string $ua, int $status, string $failReason = ''): void {
        DB::insert('login_logs', [
            'user_id'    => $userId ?: null,
            'username'   => $username,
            'ip'         => $ip,
            'user_agent' => mb_substr($ua, 0, 500),
            'status'     => $status,
            'fail_reason' => $failReason,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 获取登录日志
     */
    public function loginLogs(): void {
        AuthMiddleware::requireAdmin();
        $page = max(1, (int)Request::get('page', 1));
        $pageSize = min(50, max(10, (int)Request::get('page_size', 20)));

        $total = DB::count('login_logs', '1=1');
        $offset = ($page - 1) * $pageSize;

        $logs = DB::fetchAll(
            "SELECT * FROM login_logs ORDER BY id DESC LIMIT $pageSize OFFSET $offset"
        );

        Response::success([
            'list'      => $logs,
            'total'     => $total,
            'page'      => $page,
            'page_size' => $pageSize,
        ]);
    }

    public function register(): void {
        // 检查注册模式
        $row = DB::fetchOne('SELECT value FROM settings WHERE `key`="allow_register"');
        $allowRegister = $row['value'] ?? '1';
        if ($allowRegister !== '1') Response::error('注册功能已关闭');

        $row = DB::fetchOne('SELECT value FROM settings WHERE `key`="register_mode"');
        $mode = $row['value'] ?? 'open';

        $data       = Request::json();
        $username   = trim($data['username'] ?? '');
        $password   = $data['password'] ?? '';
        $inviteCode = trim($data['invite_code'] ?? '');

        // 基础验证
        if (empty($username) || mb_strlen($username) < 3 || mb_strlen($username) > 32) {
            Response::error('用户名长度3-32字符，支持字母、数字、下划线');
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            Response::error('用户名只能包含字母、数字和下划线');
        }
        if (strlen($password) < 6) {
            Response::error('密码至少6位');
        }

        // 邀请码模式验证
        if ($mode === 'invite') {
            if (empty($inviteCode)) Response::error('请输入邀请码');
            $invite = DB::fetchOne(
                'SELECT * FROM invite_codes WHERE code=? AND (expire_at IS NULL OR expire_at > NOW()) AND use_count < max_uses',
                [$inviteCode]
            );
            if (!$invite) Response::error('邀请码无效或已过期');
        }

        // 唯一性检查
        if (DB::count('users', 'username=?', [$username]) > 0) Response::error('用户名已被注册');

        $userId = DB::insert('users', [
            'username'   => $username,
            'password'   => password_hash($password, PASSWORD_BCRYPT),
            'role'       => 2,
            'status'     => 1,
            'daily_limit' => 100, // 默认每日100条
            'invite_code' => $inviteCode ?: null,
            'api_token'  => bin2hex(random_bytes(20)),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // 使用邀请码
        if ($mode === 'invite' && isset($invite)) {
            DB::update('invite_codes', [
                'used_by'    => $userId,
                'used_at'    => date('Y-m-d H:i:s'),
                'use_count'  => $invite['use_count'] + 1,
            ], 'id=?', [$invite['id']]);
        }

        $user  = DB::fetchOne('SELECT * FROM users WHERE id=?', [$userId]);
        $token = JWT::encode(['uid' => $user['id'], 'role' => $user['role']]);

        Response::success(['token' => $token, 'user' => $this->safeUser($user)], '注册成功');
    }

    public function profile(): void {
        $user = AuthMiddleware::require();
        Response::success($this->safeUser($user));
    }

    public function updateProfile(): void {
        $user = AuthMiddleware::require();
        $data = Request::json();

        $updateData = [];
        if (!empty($data['nickname'])) $updateData['nickname'] = mb_substr($data['nickname'], 0, 32);
        if (!empty($data['password'])) {
            if (strlen($data['password']) < 6) Response::error('密码至少6位');
            $updateData['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }

        if (!empty($updateData)) {
            DB::update('users', $updateData, 'id=?', [$user['id']]);
        }

        Response::success(null, '更新成功');
    }

    public function refreshToken(): void {
        $user  = AuthMiddleware::require();
        $token = JWT::encode(['uid' => $user['id'], 'role' => $user['role']]);
        Response::success(['token' => $token]);
    }

    private function safeUser(array $user): array {
        unset($user['password']);
        // 将 role 数字转换为字符串，兼容前端检查
        if (isset($user['role'])) {
            $user['role'] = $user['role'] == 1 ? 'admin' : 'user';
        }
        return $user;
    }
}
