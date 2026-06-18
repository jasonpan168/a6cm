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
- **数据库**: MySQL 5.7+ 或 SQLite 3
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

**选项 A: 使用 SQLite（推荐用于开发）**

SQLite 是最简单的选项。应用会在首次运行时自动创建数据库：

```bash
# 确保项目目录可写
chmod 755 .

# 访问应用时会自动创建数据库文件
```

**选项 B: 使用 MySQL**

```bash
# 创建数据库
mysql -u root -p -e "CREATE DATABASE dwz CHARACTER SET utf8mb4;"

# 导入表结构
mysql -u root -p dwz < create_tables.sql

# 检查表是否创建成功
mysql -u root -p dwz -e "SHOW TABLES;"
```

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

    # URL 重写规则
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM 配置
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_param PATH_INFO $fastcgi_path_info;
    }

    # 防止访问敏感文件
    location ~ /\.env {
        deny all;
    }

    location ~ /database\.db {
        deny all;
    }
}
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

### SQLite

最简单的配置，无需安装数据库服务器：

```env
# .env
# SQLite 会自动使用 database.db 文件
DB_HOST=
DB_NAME=
DB_USER=
DB_PASSWORD=
```

**优点**:
- 无需配置数据库服务器
- 适合个人和小规模部署
- 便于备份和转移

**缺点**:
- 并发性能有限
- 不适合大规模应用

### MySQL

用于生产环境的配置：

```env
# .env
DB_HOST=localhost
DB_NAME=dwz
DB_USER=dwz
DB_PASSWORD=your_secure_password
```

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
