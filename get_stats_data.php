<?php
/**
 * A6.cm 短网址服务  https://www.a6.cm
 *
 * @author    AJIE <weijianao@gmail.com>
 * @copyright Copyright (c) 2026 AJIE
 * @license   AGPL-3.0-or-later  （详见项目根目录 LICENSE 与 LICENSE.md）
 *
 * 本程序是自由软件：你可在自由软件基金会发布的 GNU AGPL v3 条款下
 * 重新分发和/或修改它。本程序按"现状"分发，不附带任何担保。
 * 如需闭源商用（不公开源码），请邮件 weijianao@gmail.com 获取商业授权。
 */
session_start();
include 'config.php';

// 登录验证
if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    echo '<div class="text-red-500">访问被拒绝</div>';
    exit;
}

$type = $_GET['type'] ?? '';

switch ($type) {
    case 'today_new_users':
        $stmt = $pdo->prepare("SELECT username, email, created_at FROM users WHERE DATE(created_at) = CURDATE() ORDER BY created_at DESC");
        $stmt->execute();
        $users = $stmt->fetchAll();
        
        echo '<h3 class="text-lg font-bold mb-4">今日新增用户 (' . count($users) . '个)</h3>';
        if (empty($users)) {
            echo '<p class="text-gray-500">今日暂无新增用户</p>';
        } else {
            echo '<div class="space-y-2">';
            foreach ($users as $user) {
                echo '<div class="border-b pb-2">';
                echo '<div class="font-medium">' . htmlspecialchars($user['username']) . '</div>';
                echo '<div class="text-sm text-gray-600">' . htmlspecialchars($user['email']) . '</div>';
                echo '<div class="text-xs text-gray-500">' . $user['created_at'] . '</div>';
                echo '</div>';
            }
            echo '</div>';
        }
        break;
        
    case 'seven_days_users':
        $stmt = $pdo->prepare("SELECT username, email, created_at FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) ORDER BY created_at DESC");
        $stmt->execute();
        $users = $stmt->fetchAll();
        
        echo '<h3 class="text-lg font-bold mb-4">近七日新增用户 (' . count($users) . '个)</h3>';
        if (empty($users)) {
            echo '<p class="text-gray-500">近七日暂无新增用户</p>';
        } else {
            echo '<div class="space-y-2 max-h-96 overflow-y-auto">';
            foreach ($users as $user) {
                echo '<div class="border-b pb-2">';
                echo '<div class="font-medium">' . htmlspecialchars($user['username']) . '</div>';
                echo '<div class="text-sm text-gray-600">' . htmlspecialchars($user['email']) . '</div>';
                echo '<div class="text-xs text-gray-500">' . $user['created_at'] . '</div>';
                echo '</div>';
            }
            echo '</div>';
        }
        break;
        
    case 'today_generated_users':
        $stmt = $pdo->prepare("SELECT DISTINCT u.username, u.email, COUNT(l.id) as link_count FROM users u INNER JOIN links l ON u.id = l.user_id WHERE DATE(l.created_at) = CURDATE() GROUP BY u.id ORDER BY link_count DESC");
        $stmt->execute();
        $users = $stmt->fetchAll();
        
        echo '<h3 class="text-lg font-bold mb-4">今日生成链接的用户 (' . count($users) . '个)</h3>';
        if (empty($users)) {
            echo '<p class="text-gray-500">今日暂无用户生成链接</p>';
        } else {
            echo '<div class="space-y-2">';
            foreach ($users as $user) {
                echo '<div class="border-b pb-2">';
                echo '<div class="font-medium">' . htmlspecialchars($user['username']) . '</div>';
                echo '<div class="text-sm text-gray-600">' . htmlspecialchars($user['email']) . '</div>';
                echo '<div class="text-xs text-blue-500">今日生成 ' . $user['link_count'] . ' 个链接</div>';
                echo '</div>';
            }
            echo '</div>';
        }
        break;
        
    case 'today_links':
        // 获取今日生成的链接（包括links和urls表）
        $stmt = $pdo->prepare("
            SELECT 'links' as source, l.short_code, l.original_url, l.created_at, u.username 
            FROM links l 
            LEFT JOIN users u ON l.user_id = u.id 
            WHERE DATE(l.created_at) = CURDATE()
            UNION ALL
            SELECT 'urls' as source, ur.short_code, ur.original_url, ur.created_at, '游客' as username 
            FROM urls ur 
            WHERE DATE(ur.created_at) = CURDATE()
            ORDER BY created_at DESC
        ");
        $stmt->execute();
        $links = $stmt->fetchAll();
        
        echo '<h3 class="text-lg font-bold mb-4">今日生成的链接 (' . count($links) . '个)</h3>';
        if (empty($links)) {
            echo '<p class="text-gray-500">今日暂无生成链接</p>';
        } else {
            echo '<div class="space-y-2 max-h-96 overflow-y-auto">';
            foreach ($links as $link) {
                echo '<div class="border-b pb-2">';
                echo '<div class="font-medium text-blue-600">' . htmlspecialchars($link['short_code']) . '</div>';
                echo '<div class="text-sm text-gray-600 break-all">' . htmlspecialchars($link['original_url']) . '</div>';
                echo '<div class="text-xs text-gray-500">创建者: ' . htmlspecialchars($link['username']) . ' | ' . $link['created_at'] . '</div>';
                echo '</div>';
            }
            echo '</div>';
        }
        break;
        
    case 'today_clicks':
        // 获取今日被访问的链接及其点击次数
        $stmt = $pdo->prepare("
            SELECT 
                uc.short_code,
                COUNT(*) as click_count,
                MAX(uc.clicked_at) as last_click,
                COALESCE(l.original_url, ur.original_url) as original_url,
                COALESCE(u.username, '游客') as username
            FROM url_clicks uc
            LEFT JOIN links l ON uc.short_code = l.short_code
            LEFT JOIN urls ur ON uc.short_code = ur.short_code
            LEFT JOIN users u ON l.user_id = u.id
            WHERE DATE(uc.clicked_at) = CURDATE()
            GROUP BY uc.short_code
            ORDER BY click_count DESC, last_click DESC
        ");
        $stmt->execute();
        $clicks = $stmt->fetchAll();
        
        echo '<h3 class="text-lg font-bold mb-4">今日被访问的链接 (' . count($clicks) . '个链接，共被点击 ' . array_sum(array_column($clicks, 'click_count')) . ' 次)</h3>';
        if (empty($clicks)) {
            echo '<p class="text-gray-500">今日暂无链接被访问</p>';
        } else {
            echo '<div class="space-y-2 max-h-96 overflow-y-auto">';
            foreach ($clicks as $click) {
                echo '<div class="border-b pb-2">';
                echo '<div class="flex justify-between items-start">';
                echo '<div class="flex-1">';
                echo '<div class="font-medium text-blue-600">' . htmlspecialchars($click['short_code']) . '</div>';
                echo '<div class="text-sm text-gray-600 break-all">' . htmlspecialchars($click['original_url']) . '</div>';
                echo '<div class="text-xs text-gray-500">创建者: ' . htmlspecialchars($click['username']) . ' | 最后访问: ' . $click['last_click'] . '</div>';
                echo '</div>';
                echo '<div class="ml-4 text-right">';
                echo '<div class="text-lg font-bold text-red-600">' . $click['click_count'] . '</div>';
                echo '<div class="text-xs text-gray-500">次点击</div>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
            }
            echo '</div>';
        }
        break;
        
    default:
        echo '<div class="text-red-500">无效的请求类型</div>';
        break;
}
?>