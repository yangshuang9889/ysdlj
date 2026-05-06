# 1Panel 部署说明

## 目录结构

这是专为 1Panel 优化的部署包，所有文件都在同一级目录，无需修改 1Panel 网站根目录配置。

```
index/
├── index.php          # 入口文件
├── index.html         # 前端页面
├── assets/            # 前端资源
├── icons/             # 图标
├── app/               # PHP 应用代码
│   ├── Controllers/
│   ├── Models/
│   ├── Services/
│   ├── Helpers/
│   └── Middleware/
├── config/            # 配置文件
└── database/           # 数据库相关
```

## 部署步骤

### 1. 上传文件

将项目所有文件上传到 1Panel 网站根目录。

例如：
```
/opt/1panel/www/sites/your-domain.com/
├── index.php
├── index.html
├── assets/
├── icons/
├── app/
├── config/
└── database/
```

### 2. 配置 PHP

在 1Panel 网站设置中：
- **运行目录**：`/`（根目录）
- **PHP 版本**：8.0 或更高

### 3. 配置伪静态（Nginx）

在 1Panel 网站设置 → 伪静态，添加以下配置：

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    fastcgi_index index.php;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;
}
```

### 4. 创建数据库

在 1Panel 数据库中创建 MySQL 数据库：
- 数据库名：`shortlink`
- 用户名：`root` 或其他
- 密码：设置强密码

### 5. 访问安装页面

打开浏览器访问（将域名替换为你的）：
```
https://your-domain.com/install
```

按安装向导填写数据库信息完成安装。

## 常见问题

### 404 错误

检查 Nginx 伪静态配置是否正确，确保 `try_files` 配置存在。

### 500 错误

检查 `config/config.php` 文件权限，确保 PHP 有读取权限。

### 数据库连接失败

确认数据库信息填写正确，数据库用户有相应权限。

## 文件权限

确保以下目录有写入权限（如需）：
```bash
chmod -R 755 /your/web/path/
```

## 环境变量（可选）

支持使用环境变量覆盖配置：

| 变量名 | 说明 | 默认值 |
|--------|------|--------|
| DB_HOST | 数据库主机 | localhost |
| DB_PORT | 数据库端口 | 3306 |
| DB_NAME | 数据库名 | shortlink |
| DB_USER | 数据库用户名 | root |
| DB_PASS | 数据库密码 | (空) |
| REDIS_HOST | Redis 主机 | 127.0.0.1 |
| REDIS_PORT | Redis 端口 | 6379 |
| JWT_SECRET | JWT 密钥 | (空) |
| APP_URL | 网站 URL | localhost |
| APP_DEBUG | 调试模式 | false |
