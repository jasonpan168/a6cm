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
require_once 'config.php';

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => '未登录']);
    exit;
}

// 获取短链接代码
$short_code = $_GET['short_code'] ?? '';
if (empty($short_code)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => '缺少短链接代码']);
    exit;
}

try {
    // 连接数据库
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 验证用户权限（管理员或链接所有者）
    $user_id = $_SESSION['user_id'];
    $is_admin = $_SESSION['is_admin'] ?? false;
    
    if (!$is_admin) {
        // 检查链接是否属于当前用户
        $stmt = $pdo->prepare("SELECT user_id FROM links WHERE short_code = ?");
        $stmt->execute([$short_code]);
        $link = $stmt->fetch();
        
        if (!$link) {
            // 尝试从urls表查找
            $stmt = $pdo->prepare("SELECT user_id FROM urls WHERE short_code = ?");
            $stmt->execute([$short_code]);
            $link = $stmt->fetch();
        }
        
        if (!$link || $link['user_id'] != $user_id) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => '无权限访问此链接']);
            exit;
        }
    }
    
    // 使用与访问记录列表完全相同的查询方式
    $stmt = $pdo->prepare("SELECT * FROM url_clicks WHERE short_code = ? ORDER BY clicked_at DESC");
    $stmt->execute([$short_code]);
    $all_clicks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 过滤出今日的数据 (从今天 00:00:00 到 23:59:59)
    $today_clicks = [];
    $today_start = new DateTime('today midnight');
    $today_end = new DateTime('tomorrow midnight'); //明天凌晨即为今天的结束

    foreach ($all_clicks as $click) {
        $clickTime = new DateTime($click['clicked_at']);
        if ($clickTime >= $today_start && $clickTime < $today_end) {
            $today_clicks[] = $click;
        }
    }
    
    // 准备图表数据：X轴为访问时间，Y轴为事件标记 (e.g., 1 for each click)
    $timeLabels = [];
    $eventData = []; // Y轴数据，每个点代表一次访问

    // 按时间正序排列 (旧的在前，新的在后，适合时间序列图表)
    usort($today_clicks, function($a, $b) {
        return strtotime($a['clicked_at']) - strtotime($b['clicked_at']);
    });

    foreach ($today_clicks as $click) {
        $clickTime = new DateTime($click['clicked_at']);
        $timeLabels[] = $clickTime->format('m-d H:i:s'); // 更精确的时间格式
        $eventData[] = 1; // 每个访问事件在Y轴上标记为1
    }
    
    // 如果没有数据，提供默认值
    if (empty($timeLabels)) {
        $timeLabels = [(new DateTime())->format('m-d H:i:s')]; // 当前时间作为默认标签
        $eventData = [0]; // Y轴数据为0
    }
    
    // 返回JSON数据
    echo json_encode([
        'success' => true,
        'time_labels' => $timeLabels,
        'event_data' => $eventData, // Y轴数据，每个点代表一次访问
        'total_clicks_today' => count($today_clicks),
        'debug' => [
            'total_all_clicks_for_link' => count($all_clicks), // 这个短链接的总点击数
            'today_clicks_count' => count($today_clicks), // 今日的点击数
            'time_range' => [
                'from' => $today_start->format('Y-m-d H:i:s'),
                'to' => (clone $today_end)->modify('-1 second')->format('Y-m-d H:i:s') // 显示为今天的最后一秒
            ]
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("数据库错误: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => '数据库错误']);
} catch (Exception $e) {
    error_log("获取实时统计数据错误: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => '服务器错误']);
}
?>