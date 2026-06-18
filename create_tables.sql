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
  PRIMARY KEY (`id`),
  KEY `short_code` (`short_code`)
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