# 开源化变更日志

本文档记录了将 a6.cm 项目准备为开源项目时做出的所有变更。

## 删除的文件

### 生产数据备份（敏感）
- ❌ `dwz_2025-05-07_18-44-17_mysql_data_CyaQa.sql` - 生产数据备份
- ❌ `dwz_2025-05-09_08-37-32_mysql_data_zWI9D.sql` - 生产数据备份
- ❌ `dwz_2026-06-18_04-00-04_mysql_data.sql` - 生产数据备份

### 测试和调试文件
- ❌ `add_test_data.php` - 测试数据生成脚本
- ❌ `test_db.php` - 数据库测试脚本
- ❌ `test_rewrite.php` - URL 重写测试
- ❌ `create_test_link.php` - 测试短链接创建
- ❌ `debug.php` - 调试脚本
- ❌ `diagnose_db.php` - 数据库诊断脚本
- ❌ `fix_database.php` - 数据库修复脚本
- ❌ `admin_users.php1` - 备份文件

### 个人配置文件
- ❌ `.user.ini` - 用户个人 PHP 配置

### 项目存档
- ❌ `a6.cm_AFyDP.tar.gz` - 项目备份

### 无关的个人 HTML 文件
- ❌ `ajiea.html` - 个人文件
- ❌ `ax6000.html` - 个人文件
- ❌ `ceshi.html` - 个人文件
- ❌ `cq.html`, `cq1.html` - 个人文件
- ❌ `dy.html` - 抖音相关
- ❌ `hui.html` - 个人文件
- ❌ `jason.html` - 个人文件
- ❌ `lt.html` - 个人文件
- ❌ `m.html`, `ma.html` - 个人文件
- ❌ `tg.html` - 个人文件
- ❌ `tuijian.html` - 推荐相关
- ❌ `xc.html`, `xiaoc.html` - 个人文件
- ❌ `zfb.html` - 支付宝相关
- ❌ `1.html` - 测试文件

### 无关的功能目录
- ❌ `baohaotong/` - 保好通相关（独立功能）
- ❌ `fuli/` - 福利相关（无关功能）
- ❌ `jsq/` - 积分券相关（无关功能）
- ❌ `bzf666/` - 个人文件夹
- ❌ `cy/` - 空目录
- ❌ `dy/` - 抖音相关目录

## 修改的文件

### `config.php` 
从硬编码配置改为环境变量：

**原始问题**（具体值已脱敏，不在此记录）:
- 数据库密码硬编码（已移除并轮换）
- 邮件服务密码硬编码（已移除并轮换）
- SMTP 用户名硬编码（已移除）

**修改方案**:
- 改为使用 `getenv()` 读取环境变量
- 所有敏感信息从 `.env` 文件读取
- 支持默认值以便于开发

## 新增文件

### 开源文档
- ✅ `.gitignore` - 排除敏感文件和常见垃圾文件
- ✅ `.env.example` - 环境变量示例
- ✅ `README.md` - 项目介绍和快速开始
- ✅ `LICENSE` - AGPL-3.0 协议全文
- ✅ `LICENSE.md` - 中文说明 + 署名/商业双授权附加条款
- ✅ `CONTRIBUTING.md` - 贡献指南
- ✅ `INSTALL.md` - 详细安装和部署指南
- ✅ `SECURITY.md` - 安全最佳实践
- ✅ `OPENSOURCE_CHANGELOG.md` - 本文件

## 核心文件保留

以下文件在开源化中保留（功能的关键部分）：

### PHP 应用文件
- ✅ `index.php` - 首页和短链接生成
- ✅ `login.php` - 用户登录
- ✅ `register.php` - 用户注册
- ✅ `user_dashboard.php` - 用户仪表板
- ✅ `redirect.php` - 短链接重定向
- ✅ `view_stats.php` - 统计分析
- ✅ `admin_dashboard.php` - 管理后台
- ✅ `admin_users.php` - 用户管理
- ✅ `admin_login.php` - 管理员登录
- ✅ `verify_email.php` - 邮箱验证
- ✅ `resend_verification.php` - 重新发送验证邮件

### 数据库文件
- ✅ `create_tables.sql` - 表结构定义
- ✅ `db_update.sql` - 数据库更新脚本

### 依赖库
- ✅ `PHPMailer/` - 邮件发送库
- ✅ `phpqrcode/` - 二维码生成库

### 配置文件
- ✅ `.htaccess` - Apache URL 重写规则
- ✅ `404.html` - 404 错误页面

## 合规性改进

### 许可证
- 采用 OSI 认可的 **AGPL-3.0** 开源协议（`LICENSE` 全文）
- 附加署名条款 + 商业双授权（`LICENSE.md`）：社区免费用、回馈即合规；闭源商用需单独授权
- 与 new-api、MySQL、Qt 等项目一致的「AGPL + 双授权」变现模式

### 文档质量
- 完整的 README 和快速开始指南
- 详细的安装和部署说明
- 安全最佳实践指南
- 贡献者指南

### 代码安全
- 敏感信息改为环境变量配置
- 提供 .env 示例文件
- 敏感文件已添加到 .gitignore

## 开源项目清单

开源化完成后的项目包含：

✅ **代码质量**
- 移除了敏感信息
- 移除了个人文件
- 移除了生产数据

✅ **文档完整性**
- README.md - 项目介绍
- INSTALL.md - 安装指南
- CONTRIBUTING.md - 贡献指南
- LICENSE.md - 许可证
- SECURITY.md - 安全指南

✅ **配置管理**
- .env.example - 配置示例
- .gitignore - 排除规则

✅ **许可证**
- AGPL-3.0 开源协议（OSI 认可）
- 署名 + 商业双授权附加条款

## 后续建议

1. **创建 GitHub 仓库**
   - 推送代码到公开仓库
   - 配置 README 展示
   - 设置 Issues 和 Discussions

2. **CI/CD 配置**（可选）
   - 添加 GitHub Actions 测试
   - 自动化代码质量检查

3. **社区管理**
   - 监控和回复 Issues
   - 审查和合并 PR
   - 发布正式版本

4. **持续维护**
   - 定期更新依赖
   - 修复报告的 bug
   - 改进文档

---

**开源化完成日期**: 2026-06-18  
**版本**: 1.0 (开源初版)
