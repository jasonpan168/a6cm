# A6.cm - 短网址生成服务

一个功能完整的PHP短网址生成和管理系统，支持用户注册、链接管理、统计分析和二维码生成。

## 功能特性

- ✨ **用户认证** - 用户注册、登录、邮箱验证
- 🔗 **短网址生成** - 一键生成短网址，支持自定义别名
- 📊 **统计分析** - 实时查看点击数、访问来源、设备类型等
- 🎫 **二维码生成** - 自动为每个短链接生成二维码
- 👤 **用户管理** - 管理个人短链接、查看统计数据
- 🛡️ **管理后台** - 系统管理员可管理用户和链接
- 🌐 **多语言支持** - 中文界面

## 快速开始

### 系统要求

- PHP 7.4+ 或更高版本
- MySQL 5.7+ 或 SQLite 3
- Web 服务器 (Apache/Nginx)
- PHP 扩展: PDO, OpenSSL, GD (用于图像处理)

### 安装步骤

1. **克隆项目**
```bash
git clone https://github.com/jasonpan168/a6cm.git
cd a6cm
```

2. **配置环境变量**
```bash
cp .env.example .env
# 编辑 .env 文件，填入你的数据库和邮件配置
```

3. **创建数据库**

使用 SQLite（推荐用于开发）：
```bash
# 应用会自动创建 database.db 文件
```

或使用 MySQL：
```bash
mysql -u root -p your_database < create_tables.sql
```

4. **（可选）导入测试账号**

如需快速体验，可导入内置测试账号：
```bash
mysql -u root -p your_database < seed_test_account.sql
```
导入后即可用 `testadmin` / `Test@1234` **同时登录用户端（login.php）和后台（admin_login.php）**。

> ⚠️ **生产环境务必删除该测试账号或修改其密码！** 详见下方常见问题。

5. **启动应用**

使用 PHP 内置服务器（开发环境）：
```bash
php -S localhost:8000
```

然后访问 `http://localhost:8000`

使用生产 Web 服务器，配置 DocumentRoot 指向项目目录。

### 文件结构

```
a6.cm/
├── index.php              # 首页及短链接生成
├── config.php             # 配置文件（使用环境变量）
├── login.php              # 用户登录
├── register.php           # 用户注册
├── user_dashboard.php     # 用户仪表板
├── redirect.php           # 短链接重定向逻辑
├── view_stats.php         # 统计分析页面
├── admin_dashboard.php    # 管理后台首页
├── admin_users.php        # 用户管理
├── admin_login.php        # 管理员登录
├── PHPMailer/             # 邮件发送库
├── phpqrcode/             # 二维码生成库
├── create_tables.sql      # 数据库表结构
└── .htaccess             # Apache URL重写配置
```

## 使用指南

### 创建短网址

1. 访问首页
2. 输入长网址
3. 点击"生成短网址"
4. 获取短链接和二维码

### 用户注册

1. 点击"注册"
2. 填写邮箱和密码
3. 验证邮箱地址
4. 登录后管理个人短链接

### 查看统计

1. 登录用户账号
2. 进入仪表板
3. 查看各链接的点击统计、访问来源等

## 技术栈

- **后端**: PHP 7.4+
- **数据库**: MySQL / SQLite
- **前端**: HTML5, CSS3, JavaScript
- **邮件**: PHPMailer (SMTP)
- **二维码**: PHP QR Code

## 许可证

本项目采用 **AGPL-3.0** 开源协议，并附带署名与商业双授权条款。完整法律文本见 [LICENSE](LICENSE)，中文说明与附加条款见 [LICENSE.md](LICENSE.md)。

**简要说明：**
- ✅ 自由使用、修改、分发，**包括商业用途**
- ⚠️ 修改后若对外提供网络服务，必须**公开你的完整源代码**（AGPL 核心义务）
- ⚠️ 衍生版的界面须保留指向本项目的来源链接与原作者署名
- ❌ 禁止移除版权声明、伪称原创
- 💼 若需**闭源商用**（不公开源码），请联系作者获取单独的商业授权

## 常见问题

### Q: 支持哪些数据库？
A: 同时支持 SQLite（开发/小规模）和 MySQL（生产环境）。应用会优先使用 SQLite，如果文件不存在则尝试 MySQL。

### Q: 如何自定义短链接前缀？
A: 修改 `index.php` 中的生成逻辑，默认使用随机字符串。

### Q: 邮件验证不工作？
A: 检查 `.env` 中的 SMTP 配置，确保邮件服务器信息正确。

### Q: 二维码不显示？
A: 检查 GD 扩展是否已启用，运行 `php -i | grep -A5 "GD"`。

### Q: 有没有可以直接登录的测试账号？
A: 有。导入 `seed_test_account.sql` 后，用户名 `testadmin`、密码 `Test@1234` 可**同时登录用户端（login.php）和管理后台（admin_login.php）**——它会在 `users` 和 `admin` 两张表各插入一条相同凭证。该用户的 `email_verified` 已设为 1，因此无需邮箱验证即可登录。

> ⚠️ **该账号仅供本地/开发体验。生产环境部署后请立即删除或修改密码：**
> ```sql
> DELETE FROM users WHERE username = 'testadmin';
> DELETE FROM admin WHERE username = 'testadmin';
> ```
> 如需保留但改密码，先用 `php -r "echo password_hash('新密码', PASSWORD_DEFAULT);"` 生成哈希，再 `UPDATE` 对应表的 `password` 列。

## 贡献

欢迎提交 Issue 和 Pull Request！详见 [CONTRIBUTING.md](CONTRIBUTING.md)

## 注意事项

- 数据库配置请使用环境变量，不要在代码中硬编码敏感信息
- 生产环境必须启用 HTTPS
- 定期备份数据库
- 建议配置 WAF 和速率限制防止滥用

## 联系方式

- 👤 作者：AJIE
- 📮 邮箱：weijianao@gmail.com
- 🌐 官网：https://www.a6.cm

如有问题或建议，欢迎提交 Issue 或通过以上方式联系作者。商业授权事宜请邮件联系。

---

**最后更新**: 2026-06-18
