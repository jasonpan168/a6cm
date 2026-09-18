# 安装和部署指南

## 目录

- [开发环境设置](#开发环境设置)
- [生产环境部署](#生产环境部署)
- [数据库配置](#数据库配置)
- [邮件配置](#邮件配置)
- [故障排除](#故障排除)

## 开发环境设置

### 1. 系统要求

- **PHP**: 7.4 或更高
- **Web 服务器**: Apache 或 Nginx
- **数据库**: MySQL 5.7+ / MariaDB（**SQLite 目前不可用**，见下方「数据库配置」）
- **PHP 扩展**: PDO, OpenSSL, GD, cURL

### 2. 检查环境

```bash
# 检查 PHP 版本
php -v

# 检查已安装的扩展
php -m

# 检查 GD 扩展（用于图像处理）
php -i | grep -A 5 "GD"
```

### 3. 克隆项目

```bash
git clone https://github.com/jasonpan168/a6cm.git
cd a6cm
```

### 4. 配置环境

```bash
# 复制示例配置
cp .env.example .env

# 编辑 .env 文件
nano .env
# 或用你喜欢的编辑器打开
```

### 5. 初始化数据库

使用 MySQL / MariaDB（**目前唯一可用的数据库**，原因见下方「数据库配置」）：

```bash
# 创建数据库
mysql -u root -p -e "CREATE DATABASE a6cm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 导入表结构（纯结构，不含任何数据）
mysql -u root -p a6cm < create_tables.sql

# 检查表是否创建成功（应当看到 7 张表）
mysql -u root -p a6cm -e "SHOW TABLES;"
```

> 从旧版本升级的话，请另外执行一次 `db_update.sql`，它会补上
> `url_clicks.source_table` 这一列。缺这列时点击记录写入会失败，
> 但跳转一切正常、页面没有任何报错，表现为**点击统计永远是 0**。

### 5.1 创建管理员账号

本仓库**不预置任何账号**。用交互式脚本当场创建：

```bash
php create_admin.php
```

脚本会提示输入用户名和密码（输入时不回显），密码要求至少 8 位且同时包含
大写字母、小写字母和数字，使用 `password_hash()` 存库。
该脚本只能在命令行运行，通过 Web 访问会返回 403。

前台用户请通过 `register.php` 自行注册。

### 6. 启动开发服务器

```bash
# 使用 PHP 内置服务器
php -S localhost:8000

# 然后访问 http://localhost:8000
```

或者使用 Docker（如果已安装）：

```bash
docker run -p 8000:80 -v $(pwd):/var/www/html php:7.4-apache
```

## 生产环境部署

### 1. 服务器准备

```bash
# 更新系统
sudo apt update && sudo apt upgrade

# 安装必要的软件包
sudo apt install php php-cli php-pdo php-gd php-curl php-mbstring php-xml
sudo apt install mysql-server
sudo apt install nginx
```

### 2. 创建应用用户

```bash
# 创建专用用户
sudo useradd -m -s /bin/bash app

# 设置应用目录权限
sudo chown -R app:app /var/www/a6.cm
sudo chmod 755 /var/www/a6.cm
```

### 3. 配置 Nginx

创建 `/etc/nginx/sites-available/a6.cm`：

```nginx
server {
    listen 80;
    server_name a6.cm www.a6.cm;
    root /var/www/a6.cm;
    index index.php index.html;

    # 日志
    access_log /var/log/nginx/a6.cm-access.log;
    error_log /var/log/nginx/a6.cm-error.log;

    # 敏感文件必须放在最前面：location 的正则匹配是「先到先得」，
    # 放在短链接规则后面会被短链接规则抢先匹配掉。
    # 这一段与 .htaccess 的 <FilesMatch> 保持一致：
    # .env / config.php / *.sql / *.db / *.sqlite* 一律拒绝。
    location ~* ^/(?:\.env.*|config\.php|.*\.sql|.*\.db|.*\.sqlite.*)$ {
        deny all;
        return 404;
    }

    # 其它点开头的隐藏文件（.git、.htaccess 等）
    location ~ /\. {
        deny all;
        return 404;
    }

    # PHP-FPM 配置（改成你实际安装的版本，如 php8.2-fpm.sock）
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_param PATH_INFO $fastcgi_path_info;
    }

    # 短链接跳转：等价于 .htaccess 里的
    #   RewriteRule ^([a-zA-Z0-9]+)/?$ redirect.php?code=$1 [L,QSA]
    # 少了这一段，nginx 下访问 /abc123 会落到首页而不是发生跳转，
    # 也就是整个项目唯一的核心功能失效。
    location ~ "^/([a-zA-Z0-9]+)/?$" {
        try_files $uri $uri/ /redirect.php?code=$1&$query_string;
    }

    # URL 重写规则
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
}
```

> ⚠️ **两个容易漏掉的点**
> 1. 上面的短链接 `location` 必须存在，否则 nginx 部署下短网址跳转不工作。
> 2. 敏感文件的 `deny` 规则必须与 `.htaccess` 对齐。只 deny `.env` 和
>    `database.db` 是不够的 —— `create_tables.sql`、`db_update.sql`
>    这类文件会被公网直接下载。

验证这两条规则确实生效：

```bash
# 应当 301/302 跳转到目标地址（而不是返回首页 HTML）
curl -sI https://你的域名/abc123 | head -1

# 以下全部应当是 403 或 404，绝不能是 200
for f in .env config.php create_tables.sql db_update.sql database.db; do
  printf '%-26s %s\n' "$f" "$(curl -s -o /dev/null -w '%{http_code}' https://你的域名/$f)"
done
```

启用配置：

```bash
sudo ln -s /etc/nginx/sites-available/a6.cm /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

### 4. 配置 Apache

在 `.htaccess` 中已经配置了 URL 重写规则。确保启用 mod_rewrite：

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

### 5. 配置数据库

```bash
# 创建数据库
mysql -u root -p -e "CREATE DATABASE dwz CHARACTER SET utf8mb4;"

# 创建数据库用户
mysql -u root -p -e "CREATE USER 'dwz'@'localhost' IDENTIFIED BY 'secure_password';"

# 授予权限
mysql -u root -p -e "GRANT ALL PRIVILEGES ON dwz.* TO 'dwz'@'localhost';"
mysql -u root -p -e "FLUSH PRIVILEGES;"

# 导入表结构
mysql -u dwz -p dwz < create_tables.sql
```

### 6. 配置环境变量

```bash
# 编辑 .env 文件
sudo nano /var/www/a6.cm/.env

# 设置正确的权限
sudo chmod 640 /var/www/a6.cm/.env
sudo chown app:app /var/www/a6.cm/.env

# 二维码在本地生成，phpqrcode 需要写掩码缓存，该目录必须对 Web 用户可写
sudo chown -R www-data:www-data /var/www/a6.cm/phpqrcode/cache
sudo chmod 755 /var/www/a6.cm/phpqrcode/cache
```

### 7. 配置 SSL（HTTPS）

使用 Let's Encrypt：

```bash
# 安装 Certbot
sudo apt install certbot python3-certbot-nginx

# 获取证书
sudo certbot certonly --nginx -d a6.cm -d www.a6.cm

# 更新 Nginx 配置以使用 SSL
# 编辑 /etc/nginx/sites-available/a6.cm，添加:
# listen 443 ssl;
# ssl_certificate /etc/letsencrypt/live/a6.cm/fullchain.pem;
# ssl_certificate_key /etc/letsencrypt/live/a6.cm/privkey.pem;

# 重启 Nginx
sudo systemctl restart nginx
```

### 8. 设置备份

```bash
# 创建备份脚本 backup.sh
#!/bin/bash
BACKUP_DIR="/var/backups/a6.cm"
DATE=$(date +%Y%m%d_%H%M%S)

# 备份数据库
mysqldump -u dwz -p dwz > $BACKUP_DIR/db_$DATE.sql

# 备份应用文件（可选）
# tar -czf $BACKUP_DIR/app_$DATE.tar.gz /var/www/a6.cm

echo "备份完成: $BACKUP_DIR"

# 设置 cron 任务
sudo crontab -e
# 添加: 0 2 * * * /var/www/a6.cm/backup.sh
```

## 数据库配置

### ⚠️ 关于 SQLite：目前不可用

`config.php` 里确实有一段 SQLite 分支（检测到 `database.db` 就优先用它），
但**请不要依赖它**，实测无法工作：

1. `create_tables.sql` 是 MySQL DDL（`AUTO_INCREMENT`、`ENGINE=InnoDB`），
   用 `sqlite3` 执行会直接报语法错误；
2. 应用代码大量使用 MySQL 专有函数 —— `NOW()`、`CURDATE()`、
   `DATE_SUB(... INTERVAL n DAY)`，散布在 `redirect.php`、`user_dashboard.php`、
   `admin_dashboard.php`、`data_dashboard.php`、`admin_users.php`、
   `get_stats_data.php` 中。

要支持 SQLite，需要另写一份 SQLite 建表脚本并改写上述查询。**目前请使用 MySQL。**

### MySQL / MariaDB

```env
# .env
DB_HOST=127.0.0.1
DB_NAME=a6cm
DB_USER=a6cm
DB_PASSWORD=your_secure_password
```

> **`.env` 是怎么生效的**：PHP 的 `getenv()` 不会读取 `.env` 文件，
> 因此 `config.php` 顶部内置了一个零依赖加载器。同名的**真实环境变量
> 优先级高于 `.env` 文件**（方便容器 / systemd 覆盖）。
> 验证读到的值：`php -r 'include "config.php"; echo getenv("DB_NAME");'`

## 邮件配置

### Gmail 配置

```env
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=your-email@gmail.com
SMTP_PASSWORD=your_app_specific_password
SMTP_FROM_EMAIL=noreply@a6.cm
```

**注意**: 需要生成应用专用密码，不能使用常规账户密码。

[获取 Gmail 应用密码](https://support.google.com/accounts/answer/185833)

### Mailgun 配置

```env
SMTP_HOST=smtp.mailgun.org
SMTP_PORT=465
SMTP_USERNAME=postmaster@a6.cm
SMTP_PASSWORD=your_mailgun_password
SMTP_FROM_EMAIL=noreply@a6.cm
```

### SendGrid 配置

```env
SMTP_HOST=smtp.sendgrid.net
SMTP_PORT=587
SMTP_USERNAME=apikey
SMTP_PASSWORD=your_sendgrid_api_key
SMTP_FROM_EMAIL=noreply@a6.cm
```

## 故障排除

### 问题 1: 数据库连接失败

```
错误: 数据库连接失败
```

**检查清单**:
1. 确保 `.env` 文件中的数据库配置正确
2. 检查数据库服务是否运行: `systemctl status mysql`
3. 测试数据库连接: `mysql -u username -p databasename`
4. 检查用户权限: `SHOW GRANTS FOR 'username'@'localhost';`

### 问题 2: 邮件无法发送

```
错误: 无法连接到邮件服务器
```

**检查清单**:
1. 验证 SMTP 设置是否正确
2. 检查防火墙是否阻止了邮件端口
3. 查看应用日志: `tail -f error_log`
4. 测试邮件连接: 
   ```bash
   telnet smtp.gmail.com 587
   ```

### 问题 3: 二维码不显示

```
错误: GD 库未安装
```

**解决方案**:
```bash
# 安装 GD 扩展
sudo apt install php-gd
sudo systemctl restart php-fpm

# 验证安装
php -m | grep GD
```

### 问题 4: 短链接无法重定向

**检查**:
1. 确保 `.htaccess` 或 Nginx 重写规则正确配置
2. 检查是否启用了 Apache mod_rewrite
3. 检查文件夹权限
4. 查看日志: `tail -f access_log`

### 问题 5: 上传文件大小限制

```
错误: 文件过大无法上传
```

**调整 PHP 配置** (`/etc/php/7.4/fpm/php.ini`):

```ini
upload_max_filesize = 100M
post_max_size = 100M
```

然后重启 PHP-FPM:
```bash
sudo systemctl restart php7.4-fpm
```

---

**需要帮助?** 提交 Issue 或查看项目文档。
