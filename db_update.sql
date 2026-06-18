-- 添加用户权限相关字段
ALTER TABLE users
ADD COLUMN is_premium BOOLEAN DEFAULT FALSE,
ADD COLUMN link_limit INT DEFAULT 5,
ADD COLUMN premium_expiry DATETIME DEFAULT NULL;

-- 更新现有用户的链接限制
UPDATE users SET link_limit = 5 WHERE link_limit IS NULL;