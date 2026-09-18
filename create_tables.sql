-- 创建links表（如果不存在）
CREATE TABLE IF NOT EXISTS `links` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `original_url` text NOT NULL,
  `short_code` varchar(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `expire_at` datetime DEFAULT NULL,
  `max_clicks` int(11) DEFAULT NULL,
  `user_code` varchar(20) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `click_count` int(11) DEFAULT 0,
  `remark` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `short_code` (`short_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 确保users表存在
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `user_code` varchar(20) NOT NULL,
  `verification_code` varchar(64) DEFAULT NULL,
  `email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `is_premium` tinyint(1) DEFAULT 0,
  `link_limit` int(11) DEFAULT 5,
  `premium_expiry` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `user_code` (`user_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 确保admin表存在（后台管理员账号）
CREATE TABLE IF NOT EXISTS `admin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 确保url_clicks表存在并修复外键引用
CREATE TABLE IF NOT EXISTS `url_clicks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `url_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `referer` text,
  `user_agent` text,
  `referrer` text,
  `clicked_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `short_code` varchar(20) DEFAULT NULL,
  -- redirect.php 每次记录点击时都会写入 source_table（取值 links / urls）。
  -- 这一列曾经缺失，导致 INSERT 抛 "Unknown column 'source_table'"，
  -- 而 redirect.php 把异常吞掉只写 error_log —— 跳转照常 302，
  -- 但全新安装的站点点击统计永远是 0，且页面上没有任何报错。
  `source_table` varchar(20) NOT NULL DEFAULT 'links',
  PRIMARY KEY (`id`),
  KEY `short_code` (`short_code`),
  KEY `url_id` (`url_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 老数据表（兼容旧版数据；全新安装时为空表，仅供回退查询使用）
-- 被 redirect.php / view_stats.php / update_link.php / admin_dashboard.php 等当作老数据表查询
CREATE TABLE IF NOT EXISTS `urls` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `original_url` text NOT NULL,
  `short_code` varchar(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `expire_at` datetime DEFAULT NULL,
  `max_clicks` int(11) DEFAULT NULL,
  `user_code` varchar(20) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `click_count` int(11) DEFAULT 0,
  `remark` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `short_code` (`short_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 管理员后台收藏的链接（admin_dashboard.php，按 source_table 区分 links/urls）
CREATE TABLE IF NOT EXISTS `favorites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `link_id` int(11) NOT NULL,
  `source_table` varchar(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `link_source` (`link_id`, `source_table`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 用户收藏的链接（user_dashboard.php）
CREATE TABLE IF NOT EXISTS `favorite_links` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `link_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `link_user` (`link_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 登录失败计数（防爆破）。按 (scope, ip_address) 计数：
-- scope = 'admin'（后台 admin_login.php） / 'user'（前台 login.php）。
-- 计数必须存数据库而不是 session —— 存 session 的话攻击者丢掉 cookie 就重新开始。
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `scope` varchar(20) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `first_attempt_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_attempt_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `locked_until` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `scope_ip` (`scope`, `ip_address`),
  KEY `locked_until` (`locked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
