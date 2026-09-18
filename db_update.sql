-- 添加用户权限相关字段
ALTER TABLE users
ADD COLUMN is_premium BOOLEAN DEFAULT FALSE,
ADD COLUMN link_limit INT DEFAULT 5,
ADD COLUMN premium_expiry DATETIME DEFAULT NULL;

-- 更新现有用户的链接限制
UPDATE users SET link_limit = 5 WHERE link_limit IS NULL;
-- 补齐 url_clicks.source_table（老版本建表脚本漏了这一列）
-- 缺这一列时 redirect.php 的点击记录 INSERT 会失败，跳转正常但统计恒为 0。
-- 若提示 Duplicate column name，说明你的库已经有这一列，忽略即可。
ALTER TABLE url_clicks ADD COLUMN source_table VARCHAR(20) NOT NULL DEFAULT 'links';
