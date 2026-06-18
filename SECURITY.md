# 安全指南

本文档说明在使用和部署 A6.cm 时的安全最佳实践。

## 重要安全建议

### 1. 环境变量配置

**绝不要**在代码中硬编码敏感信息：

❌ **错误示例**:
```php
$password = 'your-real-password-here';  // 永远不要这样做
```

✅ **正确做法**:
```php
$password = getenv('DB_PASSWORD');
```

### 2. 使用 HTTPS

- 生产环境**必须**使用 HTTPS
- 使用 SSL/TLS 证书（推荐 Let's Encrypt）
- 重定向 HTTP 到 HTTPS
- 设置安全的 HTTP 头

```nginx
# Nginx 配置示例
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-Frame-Options "DENY" always;
```

### 3. 数据库安全

```sql
-- 创建强密码的用户
CREATE USER 'dwz'@'localhost' IDENTIFIED BY 'very_strong_password_here';

-- 只授予必要的权限
GRANT SELECT, INSERT, UPDATE, DELETE ON dwz.* TO 'dwz'@'localhost';

-- 不要授予 DROP 权限
-- 不要使用 root 用户运行应用

-- 定期更改密码
ALTER USER 'dwz'@'localhost' IDENTIFIED BY 'new_strong_password';
```

### 4. 文件和目录权限

```bash
# 应用目录权限
chmod 755 /var/www/a6.cm

# 配置文件权限（仅所有者和应用用户可读）
chmod 640 .env
chown app:app .env

# 数据库文件权限（SQLite）
chmod 640 database.db
chown app:app database.db

# 日志目录权限
chmod 755 logs
chown app:app logs
```

### 5. 禁止访问敏感文件

**使用 `.htaccess` (Apache)**:
```apache
# 禁止访问 .env 文件
<Files ".env">
    Order allow,deny
    Deny from all
</Files>

# 禁止访问数据库文件
<Files "database.db">
    Order allow,deny
    Deny from all
</Files>

# 禁止访问 PHP 错误日志
<Files "error_log">
    Order allow,deny
    Deny from all
</Files>
```

**使用 Nginx**:
```nginx
location ~ /\.env {
    deny all;
}

location ~ /database\.db {
    deny all;
}

location ~ /error_log {
    deny all;
}
```

### 6. 输入验证

所有用户输入必须进行验证和清理：

```php
// 验证邮箱
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die("无效的邮箱地址");
}

// 验证 URL
if (!filter_var($url, FILTER_VALIDATE_URL)) {
    die("无效的 URL");
}

// 防止 SQL 注入 - 使用准备语句
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);

// 不要使用这样的方式
// $sql = "SELECT * FROM users WHERE email = '$email'";  // SQL 注入风险！
```

### 7. 防止 XSS (跨网站脚本)

始终对输出内容进行转义：

```php
// 正确的做法
<?php echo htmlspecialchars($user_input, ENT_QUOTES, 'UTF-8'); ?>

// 不要这样做
<?php echo $user_input; ?>  <!-- XSS 风险 -->
```

### 8. 防止 CSRF (跨域请求伪造)

在表单中使用 CSRF 令牌：

```php
// 生成令牌
session_start();
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 在表单中包含
<form method="POST">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
    <!-- 其他字段 -->
</form>

// 验证令牌
if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die('CSRF 令牌无效');
}
```

### 9. 会话安全

```php
// 配置会话安全设置
ini_set('session.cookie_httponly', 1);    // 防止 XSS 访问 cookie
ini_set('session.cookie_secure', 1);      // 仅通过 HTTPS 发送
ini_set('session.cookie_samesite', 'Strict');  // CSRF 防护

// 定期重生成会话 ID
session_regenerate_id(true);
```

### 10. 日志记录

记录敏感的安全事件：

```php
// 记录登录尝试
error_log("登录尝试: IP=" . $_SERVER['REMOTE_ADDR'] . ", 用户=" . $email . ", 结果=" . ($success ? "成功" : "失败"));

// 记录权限拒绝
error_log("权限拒绝: IP=" . $_SERVER['REMOTE_ADDR'] . ", 用户=" . $user_id . ", 操作=" . $action);

// 不要记录密码或令牌
```

### 11. 速率限制

防止暴力攻击和滥用：

```php
// 简单的速率限制实现
$ip = $_SERVER['REMOTE_ADDR'];
$key = "rate_limit_" . $ip;
$attempts = intval(apcu_fetch($key) ?: 0);

if ($attempts > 5) {
    http_response_code(429);
    die("请求过于频繁，请稍后再试");
}

apcu_store($key, $attempts + 1, 60);  // 60 秒窗口
```

### 12. 依赖项更新

定期更新 PHP 依赖：

```bash
# 检查 PHPMailer 是否有更新
cd PHPMailer
git pull origin main
```

### 13. 错误处理

不要向用户暴露敏感的错误信息：

```php
// 生产环境关闭详细错误显示
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// 向用户显示通用错误消息
try {
    // 代码
} catch (Exception $e) {
    // 记录详细错误到日志
    error_log($e->getMessage());
    // 向用户显示通用消息
    die("发生了错误，请稍后重试");
}
```

## 安全检查清单

在部署到生产环境前，检查以下项目：

- [ ] `.env` 文件已创建，包含真实的敏感信息
- [ ] `.env` 文件不在版本控制中（已添加到 .gitignore）
- [ ] HTTPS 已启用
- [ ] 数据库密码已更改（不使用默认密码）
- [ ] 邮件服务器凭证已配置
- [ ] 文件和目录权限正确设置
- [ ] 敏感文件已被 Web 服务器阻止访问
- [ ] 数据库用户权限最小化
- [ ] 日志记录已启用
- [ ] 定期备份已配置
- [ ] WAF（Web 应用防火墙）已配置（推荐）
- [ ] 监控和告警已设置
- [ ] 依赖项已更新到最新版本

## 报告安全问题

如果发现安全问题，**请不要**在公开的 Issue 中报告。

相反，请通过以下方式私下联系维护者：
1. 通过项目页面查找维护者的联系方式
2. 包括问题的详细描述和重现步骤
3. 给维护者合理的时间来修复问题后再公开披露

感谢你帮助保护这个项目的安全！

---

**最后更新**: 2026-06-18
