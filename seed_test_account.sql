-- ============================================================
-- 测试账号种子数据
-- ============================================================
-- 用户名：testadmin
-- 明文密码：Test@1234
-- 说明：同一套凭证在用户端(login.php)和后台(admin_login.php)均可登录。
--
-- ⚠️ 安全提醒：此文件仅用于本地/开发环境快速体验。
--    生产环境部署后请务必删除该测试账号或修改其密码！
--
-- password 列存储的是 password_hash('Test@1234', PASSWORD_DEFAULT) 生成的
-- bcrypt 哈希，切勿替换为明文，否则 password_verify() 校验会失败。
-- 如需自定义密码，请用 PHP 重新生成哈希：
--   php -r "echo password_hash('你的密码', PASSWORD_DEFAULT);"
-- ============================================================

-- 用户端账号（email_verified=1 已验证，否则 login.php 会拦截登录）
INSERT INTO `users`
  (`username`, `password`, `email`, `user_code`, `verification_code`, `email_verified`, `is_premium`, `link_limit`)
VALUES
  ('testadmin', '$2y$12$koB.2kvBUCfD4M7imgIer.4Fc8oTKZSIP4cw4Xf.XAJT2K1TK8Mha', 'test@example.com', 'TESTADMN', NULL, 1, 0, 5)
ON DUPLICATE KEY UPDATE
  `password` = VALUES(`password`),
  `email` = VALUES(`email`),
  `email_verified` = VALUES(`email_verified`);

-- 后台管理员账号（同一套用户名+密码）
INSERT INTO `admin`
  (`username`, `password`)
VALUES
  ('testadmin', '$2y$12$koB.2kvBUCfD4M7imgIer.4Fc8oTKZSIP4cw4Xf.XAJT2K1TK8Mha')
ON DUPLICATE KEY UPDATE
  `password` = VALUES(`password`);
