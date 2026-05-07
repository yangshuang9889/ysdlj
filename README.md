# 特别说明
## 一、项目基础声明
本项目完全基于 QClaw 与 WorkBuddy 开发搭建，本人全程未编写一行代码。因个人技术能力十分有限，在此真诚恳请路过的各位技术大神，能够帮忙完善优化本项目。
## 二、项目开发初衷
本人此前使用 OneNav 开源项目搭建了个人导航网站，该程序本身不支持重复链接的录入使用，为了解决这个使用痛点，我萌生了通过短链接功能来规避该限制的想法。恰逢腾讯龙虾开启公测，便以此为契机，完成了本项目的搭建。
## 三、相关补充
关于我视频：https://www.bilibili.com/video/BV1wP4y1P7fZ
【重要提示】本人是一名脑瘫患者。

# 杨爽短链接系统

一款简洁、高效的短链接管理系统，支持短链生成、访问统计、广告管理等功能。

## 功能特性

### 核心功能
- 短链接生成：支持自定义短码和随机生成
- 访问统计：实时统计点击量、访客数、地区分布
- 广告管理：支持倒计时广告、横幅广告，可配置跳过模式
- 链接管理：批量操作、过期时间设置、无广告链接

### 管理功能
- 用户管理：管理员/普通用户角色分离
- 系统设置：网站名称、SEO配置、注册模式
- 登录日志：记录用户登录历史
- 仪表盘：可视化数据概览

### 技术特性
- 支持 MySQL 8.0+ 和 SQLite
- RESTful API 设计
- JWT 认证
- Redis 缓存支持
- 响应式设计

## 系统要求

- PHP 8.0+
- MySQL 8.0+ 或 SQLite 3
- Nginx / Apache
- Redis（可选，用于缓存）

## 快速部署

### 方式一：1Panel 部署（推荐）

1. 将项目文件上传到网站根目录
2. 创建 MySQL 数据库
3. 访问 `https://your-domain.com/install` 完成安装
4. 详细步骤见 [README-1Panel.md](README-1Panel.md)

### 方式二：手动部署

1. **上传文件**

```bash
# 将所有文件上传到 Web 服务器目录
/var/www/html/
├── index.php
├── index.html
├── assets/
├── icons/
├── app/
├── config/
└── database/
```

2. **配置 Web 服务器**

Nginx 配置示例：
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/html;
    index index.php index.html;

    # 伪静态
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP 处理
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # 静态资源
    location /assets/ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # 安全头
    location ~ /\. {
        deny all;
    }
}
```

Apache 配置示例（.htaccess）：
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?/$1 [L]
```

3. **创建数据库**

```sql
-- 登录 MySQL
mysql -u root -p

-- 创建数据库
CREATE DATABASE shortlink DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 创建用户（可选）
CREATE USER 'shortlink'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON shortlink.* TO 'shortlink'@'localhost';
FLUSH PRIVILEGES;
```

4. **配置数据库连接**

编辑 `config/config.php`：
```php
return [
    'db' => [
        'driver'   => 'mysql',
        'host'     => 'localhost',
        'port'     => 3306,
        'dbname'   => 'shortlink',
        'username' => 'shortlink',
        'password' => 'your_password',
        'charset'  => 'utf8mb4',
    ],
    // ... 其他配置
];
```

5. **访问安装页面**

打开浏览器访问：`https://your-domain.com/install`

按照安装向导完成安装。

### 方式三：Docker 部署

```yaml
# docker-compose.yml
version: '3.8'
services:
  web:
    image: nginx:alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./:/var/www/html
      - ./nginx.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - php

  php:
    image: php:8.0-fpm
    volumes:
      - ./:/var/www/html
    depends_on:
      - db

  db:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: root_password
      MYSQL_DATABASE: shortlink
      MYSQL_USER: shortlink
      MYSQL_PASSWORD: user_password
    volumes:
      - db_data:/var/lib/mysql

volumes:
  db_data:
```

## 目录结构

```
.
├── index.php              # 入口文件
├── index.html             # 前端页面
├── admin.html             # 后台管理页面
├── redirect.php           # 广告跳转页面
├── app/                   # 应用代码
│   ├── Controllers/       # 控制器
│   ├── Models/            # 数据模型
│   ├── Services/          # 业务服务
│   ├── Helpers/           # 辅助函数
│   └── Middleware/        # 中间件
├── config/                # 配置文件
│   └── config.php         # 主配置文件
├── database/              # 数据库结构
│   ├── schema-mysql8.sql  # MySQL 8.0 结构
│   └── schema-sqlite.sql   # SQLite 结构
├── assets/                # 前端资源
├── icons/                 # 图标文件
├── storage/               # 存储目录
│   ├── uploads/           # 上传文件
│   └── logs/              # 日志文件
└── README.md              # 说明文档
```

## API 接口

### 认证接口

| 接口 | 方法 | 说明 |
|------|------|------|
| `/api/auth/login` | POST | 用户登录 |
| `/api/auth/register` | POST | 用户注册 |
| `/api/user/info` | GET | 获取用户信息 |

### 短链接口

| 接口 | 方法 | 说明 |
|------|------|------|
| `/api/links` | GET | 获取短链列表 |
| `/api/links` | POST | 创建短链 |
| `/api/links/{id}` | PUT | 更新短链 |
| `/api/links/{id}` | DELETE | 删除短链 |

### 管理接口（需管理员权限）

| 接口 | 方法 | 说明 |
|------|------|------|
| `/api/admin/ads` | GET | 广告列表 |
| `/api/admin/ads` | POST | 创建广告 |
| `/api/admin/ads/{id}` | PUT | 更新广告 |
| `/api/admin/ads/{id}` | DELETE | 删除广告 |
| `/api/admin/dashboard` | GET | 仪表盘数据 |
| `/api/admin/users` | GET | 用户列表 |

## 配置说明

### config/config.php

```php
return [
    'db' => [
        'driver'   => 'mysql',          // 数据库驱动 mysql/sqlite
        'host'     => 'localhost',      // 数据库主机
        'port'     => 3306,             // 数据库端口
        'dbname'   => 'shortlink',      // 数据库名
        'username' => 'root',           // 数据库用户名
        'password' => '',              // 数据库密码
        'charset'  => 'utf8mb4',        // 字符集
    ],
    'redis' => [
        'host'     => '127.0.0.1',      // Redis 主机
        'port'     => 6379,             // Redis 端口
        'password' => '',              // Redis 密码
        'database' => 0,               // 数据库编号
        'prefix'   => 'sl:',           // 键前缀
    ],
    'jwt' => [
        'secret'     => 'your-secret',  // JWT 密钥（必填）
        'expire'     => 604800,        // 过期时间（秒）
        'algorithm'  => 'HS256',       // 算法
    ],
    'app' => [
        'url'        => 'https://your-domain.com',  // 网站地址
        'debug'      => false,         // 调试模式
        'timezone'    => 'Asia/Shanghai',
        'upload_dir'  => __DIR__ . '/../storage/uploads/',
        'log_dir'     => __DIR__ . '/../storage/logs/',
    ],
];
```

### 环境变量

支持通过环境变量覆盖配置：

| 变量名 | 说明 | 默认值 |
|--------|------|--------|
| DB_DRIVER | 数据库驱动 | mysql |
| DB_HOST | 数据库主机 | localhost |
| DB_PORT | 数据库端口 | 3306 |
| DB_NAME | 数据库名 | shortlink |
| DB_USER | 数据库用户 | root |
| DB_PASS | 数据库密码 | (空) |
| SQLITE_DATABASE | SQLite 路径 | (空) |
| REDIS_HOST | Redis 主机 | 127.0.0.1 |
| REDIS_PORT | Redis 端口 | 6379 |
| REDIS_PASS | Redis 密码 | (空) |
| JWT_SECRET | JWT 密钥 | (空) |
| APP_URL | 网站 URL | localhost |
| APP_DEBUG | 调试模式 | false |

## 安全建议

1. **修改 JWT 密钥**：首次部署务必修改 `jwt.secret` 配置
2. **使用 HTTPS**：生产环境务必启用 HTTPS
3. **限制注册**：根据需要关闭公开注册或启用邀请码模式
4. **定期备份**：定期备份数据库和上传文件
5. **文件权限**：合理设置文件和目录权限

## 常见问题

### Q: 安装页面打不开？
A: 检查 Web 服务器配置、PHP 版本（需 8.0+）、目录权限。

### Q: 短链接访问 404？
A: 检查 Nginx/Apache 伪静态配置，确保 `try_files` 正确。

### Q: 数据库连接失败？
A: 确认数据库信息正确，用户有相应权限。

### Q: Redis 连接失败？
A: Redis 为可选组件，不影响基本功能。如需使用，请确保 Redis 服务运行中。

## 更新日志

### v1.0.0 (2026-05)
- 初始版本发布
- 支持短链接生成和统计
- 广告管理功能
- 用户权限系统
- 响应式后台管理

## 许可证

MIT License

## 联系方式

- 作者：杨爽
- 网站：https://yangshuang9889.github.io
- GitHub：https://github.com/yangshuang9889

## 致谢

- [ThinkPHP](https://www.thinkphp.cn/) - 框架灵感
- [Tailwind CSS](https://tailwindcss.com/) - CSS 框架
- [Font Awesome](https://fontawesome.com/) - 图标库
