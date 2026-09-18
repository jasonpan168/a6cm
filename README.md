# A6.cm - 短网址生成服务

一个功能完整的 PHP 短网址生成和管理系统，支持用户注册、链接管理、点击统计和二维码生成。

> **本仓库不预置任何账号、密码或演示数据。** 管理员由你在自己的服务器上用
> `php create_admin.php` 当场创建，前台用户通过 `register.php` 自行注册。

## 功能特性

- ✨ **用户认证** - 用户注册、登录、邮箱验证
- 🔗 **短网址生成** - 一键生成短网址，支持自定义别名、有效期、点击次数上限
- 📊 **统计分析** - 点击数、访问来源、浏览器 / 操作系统 / 设备类型
- 🎫 **二维码生成** - 本地生成（不依赖任何第三方接口）
- 👤 **用户中心** - 管理个人短链接、查看统计数据
- 🛡️ **管理后台** - 管理员可管理用户和全部链接
- 🌐 **中文界面**

---

## 一、怎么使用

### 1. 系统要求

| 项目 | 要求 | 说明 |
| --- | --- | --- |
| PHP | 7.4+（已在 8.5 上实测） | |
| 数据库 | **MySQL 5.7+ / MariaDB** | 见下方「关于 SQLite」 |
| Web 服务器 | Nginx 或 Apache（开发可用 `php -S`） | |
| PHP 扩展 | `pdo_mysql`、`gd`、`mbstring`、`openssl` | `gd` 是二维码生成必需 |

> **关于 SQLite：** `config.php` 里有一段 SQLite 分支（检测到 `database.db` 就用它），
> 但它**目前不可用**，不要依赖：`create_tables.sql` 是 MySQL DDL（`AUTO_INCREMENT`、
> `ENGINE=InnoDB`），SQLite 无法执行；且应用代码大量使用 `NOW()`、`CURDATE()`、
> `DATE_SUB(... INTERVAL n DAY)` 等 MySQL 专有函数。**请使用 MySQL。**

### 2. 安装

```bash
# 1) 克隆
git clone https://github.com/jasonpan168/a6cm.git
cd a6cm

# 2) 建库建表
mysql -u root -p -e "CREATE DATABASE a6cm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p a6cm < create_tables.sql
# 从旧版本升级的话，再跑一次增量脚本（新装不需要）
# mysql -u root -p a6cm < db_update.sql

# 3) 配置
cp .env.example .env
$EDITOR .env          # 至少填对 DB_NAME / DB_USER / DB_PASSWORD
```

`.env` 最小可用内容：

```ini
DB_HOST=127.0.0.1
DB_NAME=a6cm
DB_USER=a6cm
DB_PASSWORD=你的数据库密码
```

> **`.env` 是怎么被读到的：** PHP 原生的 `getenv()` **不会**读取 `.env` 文件。
> `config.php` 顶部内置了一个零依赖加载器：如果 `./.env` 存在就解析它，并且
> **只在该键尚未由真实环境变量提供时**才注入。也就是说
> **真实环境变量（容器 / systemd / Apache `SetEnv`）的优先级高于 `.env` 文件**。
> `.env` 不存在或格式有误时不会致命报错，只写 `error_log`。
> 无需 Composer，仓库也不需要 `vendor/`。

### 3. 创建第一个管理员

```bash
php create_admin.php
```

脚本会交互式提示输入用户名和密码（**输入时不回显**），用 `password_hash()` 存库。
密码要求：至少 8 位，且同时包含大写字母、小写字母和数字。
该脚本**只能在命令行运行**，通过 Web 访问会直接返回 403。

实测输出：

```
=== 创建管理员账号 ===
数据库：MySQL a6cm

管理员用户名: bossadmin
密码（输入时不回显）:
再次输入密码:
✅ 管理员 [bossadmin] 创建成功。
```

用户名已存在时，脚本会先问你是否重置密码，回答 `yes` 才会改动。

然后访问 `admin_login.php` 登录后台。

### 4. 注册第一个前台用户

短链接**必须登录后才能创建**（`index.php` 在 POST 时会检查登录态）。

1. 访问 `register.php`，填写用户名、邮箱、密码（同样要求 8 位以上 + 大小写 + 数字）
2. 系统发送验证邮件 → 点击邮件里的链接完成验证
3. 访问 `login.php` 登录

> **没有配置 SMTP 怎么办？** 注册后账号的 `email_verified` 为 0，`login.php` 会拦截登录。
> 本地开发可以直接在数据库里放行：
> ```sql
> UPDATE users SET email_verified = 1 WHERE username = '你的用户名';
> ```
> 生产环境请正确配置 `.env` 里的 `SMTP_*`（邮件通过内置的 PHPMailer 走 SMTP 发送）。

### 5. 生成第一条短链接

1. 登录后在首页 `index.php` 填写：
   - **长网址**（必填）
   - **自定义短码**（可选，留空则随机生成 6 位字母数字）
   - **有效期天数**（可选）
   - **最大点击次数**（可选）
   - **备注**（可选）
2. 提交后页面返回形如 `https://你的域名/abc123` 的短链接
3. 访问该短链接即 302 跳转到目标地址，同时记录一次点击

有效期和点击次数上限都是在**跳转时实时判断**的（`redirect.php`），超限直接拦截，
**不需要任何定时任务**。

### 6. 二维码

二维码端点：

```
/generate_qrcode.php?url=<短码>
```

例如 `/generate_qrcode.php?url=abc123` 返回该短链接的 PNG 二维码。

- 默认**使用仓库自带的 `phpqrcode` 库在本地生成**：不联网、不把用户的短链接
  发给任何第三方，需要 PHP 的 `gd` 扩展。
- `phpqrcode/cache/` 目录需要对 Web 用户可写（库会在这里缓存掩码模板）：
  ```bash
  chown -R www-data:www-data phpqrcode/cache && chmod 755 phpqrcode/cache
  ```
- 参数只接受 `^[A-Za-z0-9_-]{1,64}$`，非法输入返回 400。
- 如果你确实想改用某个远程二维码接口，在 `.env` 里设置 `QR_CODE_API`。
  **注意这会把完整短链接发送给该第三方服务**，默认留空即本地生成。

### 7. 部署配置

#### Nginx

```nginx
server {
    listen 80;
    server_name 你的域名;
    root /var/www/a6cm;
    index index.php index.html;

    # 敏感文件必须放在最前面：nginx 的正则 location 是先到先得。
    # 这一段与 .htaccess 的 <FilesMatch> 对齐。
    location ~* ^/(?:\.env.*|config\.php|.*\.sql|.*\.db|.*\.sqlite.*)$ {
        deny all;
        return 404;
    }

    # 其它点开头的隐藏文件（.git 等）
    location ~ /\. {
        deny all;
        return 404;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # ⚠️ 短链接跳转，缺了这一段 /abc123 会落到首页，短网址功能等于没有
    location ~ "^/([a-zA-Z0-9]+)/?$" {
        try_files $uri $uri/ /redirect.php?code=$1&$query_string;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
}
```

#### Apache

仓库自带的 `.htaccess` 已包含全部规则（短链接 rewrite + 敏感文件拒绝），
只需启用 `mod_rewrite` 并允许 `.htaccess` 覆盖：

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

#### 部署后自查（务必实际跑一遍）

```bash
# 短链接必须真的跳转（301/302），而不是返回首页 HTML
curl -sI https://你的域名/abc123 | head -1

# 以下全部必须是 403 或 404，绝不能是 200
for f in .env config.php create_tables.sql db_update.sql database.db; do
  printf '%-22s %s\n' "$f" "$(curl -s -o /dev/null -w '%{http_code}' https://你的域名/$f)"
done
```

### 8. 定时任务

**本项目的功能不依赖任何定时任务**（链接过期与点击上限都在跳转时实时判断）。
但建议你自行配置两件事：

```cron
# 每天凌晨 3 点备份数据库
0 3 * * * mysqldump -u a6cm -p'密码' a6cm | gzip > /backup/a6cm-$(date +\%F).sql.gz

# 每天清理 90 天前的点击明细（含访客 IP，见下方「安全与隐私」）
30 3 * * * mysql -u a6cm -p'密码' a6cm -e "DELETE FROM url_clicks WHERE clicked_at < NOW() - INTERVAL 90 DAY;"
```

### 9. 文件结构

```
a6cm/
├── index.php              # 首页 + 短链接生成（需登录）
├── redirect.php           # 短链接跳转 + 点击记录  ← 核心
├── config.php             # 配置 + .env 加载器 + 页脚法律声明
├── create_admin.php       # 交互式创建管理员（仅命令行）
├── login.php / register.php / logout.php / verify_email.php
├── resend_verification.php
├── user_dashboard.php     # 用户中心
├── update_link.php        # 编辑链接
├── view_stats.php         # 统计页
├── get_stats_data.php / get_realtime_stats.php   # 统计数据接口
├── generate_qrcode.php    # 二维码（本地生成）
├── admin_login.php / admin_dashboard.php / admin_users.php
├── data_dashboard.php / view_user_links.php
├── create_tables.sql      # 建表脚本（纯结构，无任何数据）
├── db_update.sql          # 老版本升级用的增量 SQL
├── PHPMailer/             # 邮件库（LGPL-2.1）
├── phpqrcode/             # 二维码库（LGPL-3.0）
└── .htaccess              # Apache 重写与敏感文件拒绝
```

---

## 二、安全与隐私

> 这一节**有什么写什么**，包括本项目目前**做不到**的部分。请在上线前完整读完。

### 1. 这个系统会存储哪些数据

| 表 | 字段 | 说明 |
| --- | --- | --- |
| `links` / `urls` | `original_url`、`short_code`、`remark`、`expire_at`、`max_clicks`、`click_count`、`user_id` | 短链接本身 |
| `users` | `username`、`email`、`password`(哈希)、`user_code`、`verification_code`、`email_verified` | 前台用户 |
| `admin` | `username`、`password`(哈希) | 后台管理员 |
| `url_clicks` | **`ip_address`（访客 IP）**、`user_agent`、`referer`、`clicked_at`、`short_code` | **每一次点击都记录一行** |

⚠️ **`url_clicks.ip_address` 记录的是完整访客 IP（`$_SERVER['REMOTE_ADDR']`），
`user_agent` 和 `referer` 也原样入库。**

在中国大陆《个人信息保护法》、欧盟 GDPR 等法域下，**IP 地址通常被认定为个人信息**。
这意味着作为部署者，**合规责任在你**，你至少需要考虑：

- 在你的站点上提供隐私政策，说明收集了什么、保存多久、用途是什么；
- 设定保留期限并定期清理（见上方定时任务示例）；
- 如果不需要 IP 维度的统计，**直接不存**或只存哈希 / 截断后的网段；
- 响应用户的查询与删除请求。

本项目**不内置**任何 IP 脱敏、保留期限或数据导出/删除功能。

### 2. 密码怎么存

- 注册和创建管理员都使用 `password_hash($password, PASSWORD_DEFAULT)`
  （当前 PHP 版本为 bcrypt，`$2y$`），校验用 `password_verify()`。
- **数据库里没有明文密码，仓库里也没有任何预置哈希。**
- 密码强度要求：≥ 8 位，且同时含大写字母、小写字母、数字（`register.php`、`create_admin.php`）。
- ⚠️ **已知行为**：`login.php` 和 `register.php` 在哈希前都会对密码做
  `htmlspecialchars()`。两侧一致所以功能正常，但含 `< > & " '` 的密码实际被存成了
  转义后的形式，长度校验也是按转义后计算的。这是历史遗留行为，**改动它会导致现有
  用户无法登录**，因此保持原样并在此说明。

### 3. 各项防护的真实状态

| 防护 | 状态 | 说明 |
| --- | --- | --- |
| **SQL 注入** | ✅ | 所有用户输入都走 PDO 预处理（`prepare`/`execute`），且 `PDO::ATTR_EMULATE_PREPARES => false`。少数 `->query()` 调用（如 `SELECT COUNT(*) FROM users`）均为**静态 SQL，不拼接任何输入**。 |
| **XSS** | ✅ 已审计并修复 | 已逐处审计所有输出用户可控数据的位置。审计中发现并修复了**两个真实的存储型 XSS**（详见下方「已修复的历史问题」）：自定义短码未校验 + 输出未转义；以及 `original_url` 被插进 JS 字符串时只做 `htmlspecialchars` 不足以防逃逸。现在 HTML 上下文一律 `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`，JS 上下文一律走 `a6_js()`（`json_encode` + 转义）。 |
| **CSRF** | ✅ 全覆盖 | 所有有副作用的端点（含 `admin_login`、`admin_dashboard`、`admin_users`、`user_dashboard`、`resend_verification`、`verify_email`、`update_link` 这个 JSON 接口）都在产生任何副作用之前调用 `csrf_require()`，校验用 `hash_equals`。AJAX 可把 token 放 body 或 `X-CSRF-Token` 头。token 每 30 分钟轮换，并保留上一枚一个周期作为宽限。 |
| **登录防爆破** | ✅ 前后台都有 | 失败计数存**数据库** `login_attempts` 表，按 `(scope, ip)` 计，单条 `INSERT ... ON DUPLICATE KEY UPDATE` 原子自增（并发不丢计数）。默认连续 5 次失败锁该 IP 15 分钟，可用 `LOGIN_MAX_ATTEMPTS` / `LOGIN_LOCK_SECONDS` / `LOGIN_WINDOW_SECONDS` 调整。**计数不在 session 里**，所以换 cookie 绕不过去。 |
| **会话安全** | ✅ | 前后台登录成功后都调用 `session_regenerate_id(true)`（防会话固定）。会话 cookie 的 `secure`（HTTPS 下自动开启）/ `httponly` / `SameSite=Lax` 由 `a6_session_boot()` 在 `session_start()` **之前**统一设置——原来 `login.php` 把它写在 `session_start()` 之后，那是个从未生效的空操作。 |
| **目标 URL 校验** | ✅ | 新建（`index.php`）与编辑（`update_link.php`）共用 `a6_validate_target_url()`：只允许 `http`/`https`，拒绝控制字符、超长、无主机名，并查域名黑名单。自定义短码限定 `^[a-zA-Z0-9]{1,20}$`。 |
| **权限校验** | ✅ | 后台页面均以 `isset($_SESSION['admin_logged_in'])` 作为入口守卫。 |
| **错误回显** | ✅ | `config.php` 默认 `display_errors=0` + `log_errors=1`，不向访问者暴露路径 / SQL。本地调试设 `APP_DEBUG=1` 打开。 |
| **敏感文件** | ✅ 有规则，需你验证 | `.htaccess` 拒绝 `.env`、`config.php`、`*.sql`、`*.db`、`*.sqlite*`；nginx 需要你按上面的配置手动对齐，**并用上面的 curl 自查脚本实际验证**。 |
| **速率限制** | ⚠️ 仅创建链接和登录 | `index.php` 对每用户每分钟创建条数有限制（免费 5 次 / 付费 60 次，**存在 session 里**，换 cookie 可绕过——它是防误操作不是防攻击）；登录有上面的 IP 锁定。跳转、二维码、统计接口**没有**限流，请在 nginx / WAF 层补。 |
| **内容安全策略（CSP）** | ❌ 没有 | 项目不下发 CSP 响应头。建议在 Web 服务器层自行添加。 |

### 3.1 已修复的历史问题（供从旧版本升级的人参考）

如果你部署的是本次加固之前的版本，以下问题**确实存在**，请尽快升级：

| 问题 | 影响 |
| --- | --- |
| 自定义短码未校验 + 输出未转义 | **存储型 XSS**：普通注册用户可在管理员后台执行脚本；超长短码还会直接 500 |
| `original_url` 插入 JS 字符串只用 `htmlspecialchars` | **存储型 XSS**：`?a='+alert(1)+'` 可从 JS 字符串逃逸 |
| 后台全线无 CSRF | 诱导已登录管理员访问一个页面即可批量删链接、改用户权限 |
| `resend_verification.php` 用 GET 触发发信 | CSRF + 免费发信放大器（`<img src=...>` 即可触发） |
| `admin_login.php` 无失败限制、无 `session_regenerate_id` | 可全速爆破；存在会话固定风险 |
| 前台失败计数存 session | 丢掉 cookie 即可重置，等于没有防护 |
| `login.php` 的 cookie 加固写在 `session_start()` 之后 | `secure`/`httponly`/`SameSite` 从未生效 |
| `index.php` 不校验目标 URL | `javascript:` / `file:` / `ftp:` 等可入库 |
| `create_tables.sql` 缺 `url_clicks.source_table` | 点击统计恒为 0，且无任何报错 |

### 4. 短链接服务特有的风险：被当成钓鱼跳板

这是短网址服务**最现实**的风险：任何人注册后都可以把你的域名变成钓鱼、
诈骗、恶意软件分发页面的中转跳板。后果由你的域名承担——被浏览器安全浏览
（Safe Browsing）拉黑、被邮件服务商标记、被 CDN 或注册商投诉下架。

**本项目当前的能力（如实说明）：**

- ✅ **域名黑名单**（见下方用法），新建和编辑两条路径都会检查
- ✅ **协议白名单**：只允许 `http://` 和 `https://`，`javascript:` / `data:` /
  `file:` / `vbscript:` / `ftp:` 一律拒绝
- ✅ 只有**登录用户**才能创建链接，且注册需要邮箱验证
- ❌ **没有**接入恶意网址情报源（Google Safe Browsing / 腾讯 / 360 等）
- ❌ **没有**人工审核队列
- ❌ **没有**举报入口
- ❌ 跳转前**没有**「即将前往外部网站」的中间确认页

#### 域名黑名单怎么用

两个来源，取并集，默认都是空的（= 不拦截任何域名）：

**方式一：`blacklist.txt`**（项目根目录，每行一个域名，`#` 开头为注释）

```
# blacklist.txt
malware-example.test
phishing-example.test
```

**方式二：`.env` 里的 `URL_BLACKLIST`**（逗号分隔）

```ini
URL_BLACKLIST=malware-example.test,phishing-example.test
```

匹配规则：**精确匹配，或作为父域匹配**。写 `evil.test` 会同时拦下
`evil.test` 和 `a.b.evil.test`，但不会误伤 `notevil.test.com`。
命中后创建/编辑都会被拒绝，并提示「该域名已被本站列入黑名单」。

改完立即生效，无需重启（每次请求读取一次）。黑名单很大时建议改用
`.env` 方式或自行加缓存。

**仍然建议部署者补充：**

1. 接入恶意网址情报 API，在 `a6_validate_target_url()` 里加一层查询；
2. 新用户的链接先进待审队列，或对新注册账号限制创建条数；
3. 跳转前加一个「即将前往外部网站」的中间确认页；
4. 定期导出 `links.original_url` 抽查；
5. 提供举报入口并及时下架。

### 5. 生产环境加固清单

- [ ] **HTTPS 已启用**（必须——前台会话 cookie 设了 `secure`，没有 HTTPS 登录态直接失效）
- [ ] `.env` 权限收紧：`chmod 640 .env && chown app:www-data .env`
- [ ] 公网访问 `.env`、`config.php`、`*.sql`、`*.db` 全部返回 403/404（**用上面的 curl 脚本实测**）
- [ ] 数据库用户只授予 `SELECT/INSERT/UPDATE/DELETE`，不给 `DROP`/`GRANT`
- [ ] 数据库密码不是默认值（`.env.example` 里的是占位符，不要直接用）
- [ ] `APP_DEBUG` 未设置或不为 `1`（确保错误不回显）
- [ ] `admin_login.php` 额外加了 fail2ban / IP 白名单（项目内置的 IP 锁定已生效，但多一层更稳）
- [ ] `login_attempts` 表已存在（新装由 `create_tables.sql` 建；升级请跑 `db_update.sql`）
- [ ] 目标域名黑名单 `blacklist.txt` / `URL_BLACKLIST` 已按需配置
- [ ] `phpqrcode/cache/` 可写，但**不可通过 Web 列目录**
- [ ] 目标 URL 黑名单或审核机制已就位（见上一节）
- [ ] `url_clicks` 保留期限与清理任务已配置（IP 属个人信息）
- [ ] 站点已发布隐私政策
- [ ] 数据库定期备份且**验证过能恢复**
- [ ] PHP 与 PHPMailer 保持更新

### 6. 已知限制

- **SQLite 不可用**（`create_tables.sql` 是 MySQL DDL，代码用 MySQL 专有函数）。
- 后台没有二次验证（2FA），也没有操作审计日志；虽然 CSRF / 防爆破 / 会话加固已补齐，
  仍建议把 `admin_*.php` 放在 IP 白名单或 VPN 之后。
- 创建链接的频率限制存在 session 里，换 cookie 可绕过；它防的是误操作，不是攻击者。
- 没有下发 CSP 响应头。
- 前端依赖 jsDelivr / BootCDN 上的 Chart.js、moment.js、Tailwind CSS，
  已加 SRI 校验；但如果 CDN 在你的网络环境不可达，相关页面样式/图表会失效。
- 本次已针对 CSRF、XSS、SQL 注入、认证与目标 URL 校验做过一轮自查并修复，
  但**未经过独立第三方安全审计**。发现问题请走
  [GitHub 私密安全通报](https://github.com/jasonpan168/a6cm/security/advisories/new)。

### 7. 报告安全问题

**唯一渠道**：<https://github.com/jasonpan168/a6cm/security/advisories/new>

请勿在公开 Issue 中提交漏洞细节，也请勿发邮件（本项目不提供邮件报告渠道）。
详见 [SECURITY.md](SECURITY.md)。

---

## 技术栈

- **后端**: PHP 7.4+（实测 8.5）
- **数据库**: MySQL / MariaDB
- **前端**: HTML5, CSS3, JavaScript（Chart.js / Tailwind 走 CDN，已加 SRI）
- **邮件**: PHPMailer 6.x (SMTP)，LGPL-2.1
- **二维码**: PHP QR Code 本地生成，LGPL-3.0

## 许可证

本项目采用 **AGPL-3.0** 开源协议，并附带署名与商业双授权条款。
完整法律文本见 [LICENSE](LICENSE)，中文说明与附加条款见 [LICENSE.md](LICENSE.md)。

**简要说明：**
- ✅ 自由使用、修改、分发，**包括商业用途**
- ⚠️ 修改后若对外提供网络服务，必须**公开你的完整源代码**（AGPL 核心义务）
- ⚠️ 衍生版的界面须保留页脚中指向本项目的来源链接与原作者署名；
  fork 后请在 `.env` 里把 `SOURCE_CODE_URL` 指向**你自己**的仓库（AGPL 第 13 条）
- ❌ 禁止移除版权声明、伪称原创
- 💼 若需**闭源商用**，可获取单独的商业授权；但该授权**不覆盖**内置的
  `PHPMailer/`(LGPL-2.1) 与 `phpqrcode/`(LGPL-3.0)，详见 [LICENSE.md](LICENSE.md)

## 常见问题

### Q: 支持 SQLite 吗？
A: **目前不支持。** `config.php` 里有 SQLite 分支，但建表脚本是 MySQL DDL，
且应用代码使用 `NOW()`、`CURDATE()`、`DATE_SUB(... INTERVAL n DAY)` 等
MySQL 专有函数。请使用 MySQL。

### Q: 改了 `.env` 不生效？
A: 检查三点：① `.env` 与 `config.php` 在同一目录；② 同名的**真实环境变量会覆盖
`.env`**（这是设计如此，容器/systemd 优先）；③ 用
`php -r 'include "config.php"; echo getenv("DB_NAME");'` 直接确认读到的值。

### Q: 怎么创建管理员？
A: `php create_admin.php`，交互式输入用户名和密码。仓库里**没有**任何预置账号。

### Q: 短链接访问返回首页而不是跳转？
A: Web 服务器缺少短链接 rewrite 规则。Apache 看 `.htaccess` 是否生效
（`mod_rewrite` + `AllowOverride`），Nginx 看是否加了上面那条
`location ~ "^/([a-zA-Z0-9]+)/?$"`。

### Q: 点击统计一直是 0？
A: 老版本的 `create_tables.sql` 漏了 `url_clicks.source_table` 列，导致点击记录
写入失败——而 `redirect.php` 会吞掉这个异常，所以**跳转一切正常、页面毫无报错、
统计却永远是 0**。执行 `db_update.sql` 里的
`ALTER TABLE url_clicks ADD COLUMN source_table VARCHAR(20) NOT NULL DEFAULT 'links';`
即可修复（新版建表脚本已包含该列）。

### Q: 邮件验证不工作？
A: 检查 `.env` 中的 `SMTP_*` 配置。本地开发可直接
`UPDATE users SET email_verified = 1 WHERE username='...';` 放行。

### Q: 二维码不显示？
A: ① 确认 `gd` 扩展已启用（`php -m | grep -i gd`）；
② 确认 `phpqrcode/cache/` 对 Web 用户可写。

## 贡献

欢迎提交 Issue 和 Pull Request！详见 [CONTRIBUTING.md](CONTRIBUTING.md)

## 联系方式

- 👤 作者：AJIE
- 💬 联系方式：在项目仓库提交 Issue —— <https://github.com/jasonpan168/a6cm/issues>
- 🌐 官网：https://www.a6.cm

如有问题或建议，欢迎提交 Issue。商业授权事宜同样请提交 Issue（标题注明「商业授权咨询」）。
安全漏洞**请勿**公开提交 Issue，改用 GitHub 私密安全通报：<https://github.com/jasonpan168/a6cm/security/advisories/new>

---

**最后更新**: 2026-09-18
