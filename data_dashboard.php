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
    header('Location: admin_login.php');
    exit;
}

// 获取总用户数
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

// 获取今日新增用户数
$today_new_users_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()");
$today_new_users_stmt->execute();
$today_new_users = $today_new_users_stmt->fetchColumn();

// 获取近七日新增用户数
$seven_days_new_users_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$seven_days_new_users_stmt->execute();
$seven_days_new_users = $seven_days_new_users_stmt->fetchColumn();

// 获取今日生成链接的用户数
$today_generated_links_stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM links WHERE DATE(created_at) = CURDATE()");
$today_generated_links_stmt->execute();
$today_generated_links = $today_generated_links_stmt->fetchColumn();

// 获取今日总链接数 (links + urls)
$today_total_links_stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM (
        SELECT created_at FROM links WHERE DATE(created_at) = CURDATE()
        UNION ALL
        SELECT created_at FROM urls WHERE DATE(created_at) = CURDATE()
    ) as combined_links
");
$today_total_links_stmt->execute();
$today_total_links = $today_total_links_stmt->fetchColumn();

// 获取今日点击次数
$today_clicks_stmt = $pdo->prepare("SELECT COUNT(*) FROM url_clicks WHERE DATE(clicked_at) = CURDATE()");
$today_clicks_stmt->execute();
$today_clicks = $today_clicks_stmt->fetchColumn();

// 获取总链接数
$total_links_stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM (
        SELECT id FROM links
        UNION ALL
        SELECT id FROM urls
    ) as all_links
");
$total_links_stmt->execute();
$total_links = $total_links_stmt->fetchColumn();

// 获取总点击次数
$total_clicks = $pdo->query("SELECT COUNT(*) FROM url_clicks")->fetchColumn();

// 获取今天最多点击的链接（用于滚动显示）
try {
    // 从url_clicks表获取今天的点击数据，同时关联新旧数据表
    $today_most_clicked_stmt = $pdo->prepare("
        SELECT 
            uc.short_code,
            COUNT(*) as click_count,
            MAX(uc.clicked_at) as last_click,
            COALESCE(l.original_url, ur.original_url) as original_url,
            COALESCE(l.user_code, uc.short_code) as title
        FROM url_clicks uc
        LEFT JOIN links l ON uc.short_code = l.short_code
        LEFT JOIN urls ur ON uc.short_code = ur.short_code
        WHERE DATE(uc.clicked_at) = CURDATE()
        GROUP BY uc.short_code
        ORDER BY click_count DESC, last_click DESC
        LIMIT 20
    ");
    $today_most_clicked_stmt->execute();
    $most_clicked_links = $today_most_clicked_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 如果今天没有点击数据，尝试获取最近的点击数据作为补充
    if (empty($most_clicked_links)) {
        try {
            // 获取最近的点击数据
            $recent_clicks_stmt = $pdo->prepare("
                SELECT 
                    uc.short_code,
                    COUNT(*) as click_count,
                    MAX(uc.clicked_at) as last_click,
                    COALESCE(l.original_url, ur.original_url) as original_url,
                    COALESCE(l.user_code, uc.short_code) as title
                FROM url_clicks uc
                LEFT JOIN links l ON uc.short_code = l.short_code
                LEFT JOIN urls ur ON uc.short_code = ur.short_code
                GROUP BY uc.short_code
                ORDER BY last_click DESC, click_count DESC
                LIMIT 20
            ");
            $recent_clicks_stmt->execute();
            $most_clicked_links = $recent_clicks_stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // 查询失败，忽略错误
        }
    }
    
} catch (Exception $e) {
    // 如果出错，使用空数组
    $most_clicked_links = [];
}

// 获取当日每小时点击数据（用于图表）
$hourly_clicks_stmt = $pdo->prepare("
    SELECT 
        HOUR(clicked_at) as hour,
        COUNT(*) as clicks
    FROM url_clicks 
    WHERE DATE(clicked_at) = CURDATE()
    GROUP BY HOUR(clicked_at)
    ORDER BY hour
");
$hourly_clicks_stmt->execute();
$hourly_data = $hourly_clicks_stmt->fetchAll(PDO::FETCH_ASSOC);

// 创建24小时完整数据数组
$hourly_clicks = array_fill(0, 24, 0);
foreach ($hourly_data as $data) {
    $hourly_clicks[$data['hour']] = $data['clicks'];
}

// 获取近7天每日点击数据
$daily_clicks_stmt = $pdo->prepare("
    SELECT 
        DATE(clicked_at) as date,
        COUNT(*) as clicks
    FROM url_clicks 
    WHERE clicked_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(clicked_at)
    ORDER BY date
");
$daily_clicks_stmt->execute();
$daily_data = $daily_clicks_stmt->fetchAll(PDO::FETCH_ASSOC);

// 创建7天完整数据数组
$daily_clicks = [];
$daily_labels = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $daily_labels[] = date('m-d', strtotime("-$i days"));
    $daily_clicks[] = 0;
    
    foreach ($daily_data as $data) {
        if ($data['date'] == $date) {
            $daily_clicks[count($daily_clicks) - 1] = $data['clicks'];
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>数据展示 - 短链接管理系统</title>
    <link href="https://cdn.bootcdn.net/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 0.5rem;
        }
        .alert-success {
            background-color: #def7ec;
            color: #03543f;
        }
        .chart-container {
            position: relative;
            height: 400px;
            margin: 20px 0;
        }
        .stat-card {
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        /* 滚动链接样式 */
        .scrolling-links {
            background: linear-gradient(to right, #4f46e5, #3b82f6);
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            position: relative;
            height: 180px;
        }
        .scrolling-content {
            animation: scroll 30s linear infinite;
            width: 100%;
        }
        .scrolling-links:hover .scrolling-content {
            animation-play-state: paused;
        }
        .link-item {
            display: flex;
            align-items: center;
            padding: 8px 12px;
            margin-bottom: 8px;
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 6px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .link-item:hover {
            transform: translateX(5px);
            background-color: #ffffff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .link-item .click-count {
            background-color: #f97316;
            color: white;
            font-weight: bold;
            padding: 4px 8px;
            border-radius: 12px;
            margin-right: 12px;
            min-width: 40px;
            text-align: center;
        }
        .link-item .link-title {
            font-weight: 500;
            color: #1f2937;
            flex-grow: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .link-item .link-url {
            color: #6b7280;
            font-size: 0.85em;
            margin-left: 10px;
        }
        @keyframes scroll {
            0% {
                transform: translateY(0);
            }
            100% {
                transform: translateY(-100%);
            }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <nav class="bg-gradient-to-r from-purple-600 to-indigo-600 p-4 shadow-lg">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-2xl font-bold text-white">📈 数据展示</h1>
            <div class="flex gap-4">
                <a href="admin_users.php" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition-colors duration-200">👥 用户管理</a>
                <a href="admin_dashboard.php" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition-colors duration-200">📊 返回仪表盘</a>
                <a href="logout.php" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg transition-colors duration-200">🚪 退出登录</a>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8">
        <!-- 数据统计卡片 -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="stat-card bg-gradient-to-br from-blue-500 to-blue-600 p-6 rounded-lg shadow-md text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold mb-2">总注册用户</h3>
                        <p class="text-3xl font-bold"><?= number_format($total_users) ?></p>
                    </div>
                    <div class="text-4xl opacity-80">👥</div>
                </div>
            </div>
            
            <div class="stat-card bg-gradient-to-br from-green-500 to-green-600 p-6 rounded-lg shadow-md text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold mb-2">今日新增用户</h3>
                        <p class="text-3xl font-bold"><?= number_format($today_new_users) ?></p>
                    </div>
                    <div class="text-4xl opacity-80">🆕</div>
                </div>
            </div>
            
            <div class="stat-card bg-gradient-to-br from-purple-500 to-purple-600 p-6 rounded-lg shadow-md text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold mb-2">总链接数</h3>
                        <p class="text-3xl font-bold"><?= number_format($total_links) ?></p>
                    </div>
                    <div class="text-4xl opacity-80">🔗</div>
                </div>
            </div>
            
            <div class="stat-card bg-gradient-to-br from-red-500 to-red-600 p-6 rounded-lg shadow-md text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold mb-2">总点击次数</h3>
                        <p class="text-3xl font-bold"><?= number_format($total_clicks) ?></p>
                    </div>
                    <div class="text-4xl opacity-80">👆</div>
                </div>
            </div>
        </div>

        <!-- 今日详细数据卡片 -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="stat-card bg-white p-6 rounded-lg shadow-md border-l-4 border-indigo-500">
                <h3 class="text-lg font-semibold text-gray-700 mb-2">今日生成用户</h3>
                <p class="text-3xl font-bold text-indigo-600"><?= number_format($today_generated_links) ?></p>
                <p class="text-sm text-gray-500 mt-2">今日有生成链接行为的用户数</p>
            </div>
            
            <div class="stat-card bg-white p-6 rounded-lg shadow-md border-l-4 border-teal-500">
                <h3 class="text-lg font-semibold text-gray-700 mb-2">今日总链接数</h3>
                <p class="text-3xl font-bold text-teal-600"><?= number_format($today_total_links) ?></p>
                <p class="text-sm text-gray-500 mt-2">今日新创建的短链接总数</p>
            </div>
            
            <div class="stat-card bg-white p-6 rounded-lg shadow-md border-l-4 border-orange-500">
                <h3 class="text-lg font-semibold text-gray-700 mb-2">今日点击次数</h3>
                <p class="text-3xl font-bold text-orange-600"><?= number_format($today_clicks) ?></p>
                <p class="text-sm text-gray-500 mt-2">今日短链接被点击的总次数</p>
            </div>
        </div>

        <!-- 今日点击最多链接滚动展示 -->
        <?php if (!empty($most_clicked_links)): ?>
        <div class="scrolling-links">
            <h3 class="text-white text-lg font-semibold mb-3 text-center">🔥 今日热门链接实时滚动</h3>
            <div class="scrolling-content">
                <?php 
                // 复制数据以实现无缝滚动
                $scroll_data = array_merge($most_clicked_links, $most_clicked_links);
                foreach ($scroll_data as $link): 
                ?>
                <div class="link-item" onclick="copyToClipboard('<?= htmlspecialchars($link['short_code']) ?>', '<?= htmlspecialchars($link['original_url']) ?>')">
                    <span class="click-count"><?= $link['click_count'] ?>次</span>
                    <span class="link-title">短链接: <?= htmlspecialchars($link['short_code']) ?></span>
                    <span class="link-url"><?= htmlspecialchars(substr($link['original_url'], 0, 50)) ?><?= strlen($link['original_url']) > 50 ? '...' : '' ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- 图表区域 -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- 当日每小时点击趋势 -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-xl font-semibold text-gray-800 mb-4">📊 今日每小时点击趋势</h3>
                <div class="chart-container">
                    <canvas id="hourlyChart"></canvas>
                </div>
            </div>
            
            <!-- 近7天每日点击趋势 -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-xl font-semibold text-gray-800 mb-4">📈 近7天每日点击趋势</h3>
                <div class="chart-container">
                    <canvas id="dailyChart"></canvas>
                </div>
            </div>
        </div>

        <!-- 近7天新增用户趋势 -->
        <div class="bg-white p-6 rounded-lg shadow-md mt-8">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">👥 近7天新增用户趋势</h3>
            <div class="text-center">
                <div class="inline-block bg-gradient-to-r from-blue-500 to-indigo-600 text-white px-6 py-3 rounded-lg">
                    <span class="text-lg font-semibold">近7天新增用户总数：</span>
                    <span class="text-2xl font-bold"><?= number_format($seven_days_new_users) ?></span>
                </div>
            </div>
        </div>
    </div>

    <script>
        // 当日每小时点击趋势图
        const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
        const hourlyChart = new Chart(hourlyCtx, {
            type: 'line',
            data: {
                labels: Array.from({length: 24}, (_, i) => i + ':00'),
                datasets: [{
                    label: '点击次数',
                    data: <?= json_encode($hourly_clicks) ?>,
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: 'rgb(59, 130, 246)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        },
                        ticks: {
                            color: '#6b7280'
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        },
                        ticks: {
                            color: '#6b7280'
                        }
                    }
                },
                elements: {
                    point: {
                        hoverBackgroundColor: 'rgb(59, 130, 246)'
                    }
                }
            }
        });

        // 近7天每日点击趋势图
        const dailyCtx = document.getElementById('dailyChart').getContext('2d');
        const dailyChart = new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($daily_labels) ?>,
                datasets: [{
                    label: '点击次数',
                    data: <?= json_encode($daily_clicks) ?>,
                    borderColor: 'rgb(16, 185, 129)',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: 'rgb(16, 185, 129)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 6,
                    pointHoverRadius: 9
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        },
                        ticks: {
                            color: '#6b7280'
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        },
                        ticks: {
                            color: '#6b7280'
                        }
                    }
                },
                elements: {
                    point: {
                        hoverBackgroundColor: 'rgb(16, 185, 129)'
                    }
                }
            }
        });
    </script>
    
    <script>
    function copyToClipboard(shortCode, originalUrl) {
        // 创建完整的短链接URL
        const shortUrl = window.location.origin + '/' + shortCode;
        
        // 尝试使用现代的 Clipboard API
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(shortUrl).then(function() {
                showCopyMessage('短链接已复制: ' + shortUrl);
            }).catch(function() {
                fallbackCopyTextToClipboard(shortUrl);
            });
        } else {
            // 降级方案
            fallbackCopyTextToClipboard(shortUrl);
        }
    }
    
    function fallbackCopyTextToClipboard(text) {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        textArea.style.top = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        try {
            document.execCommand('copy');
            showCopyMessage('短链接已复制: ' + text);
        } catch (err) {
            showCopyMessage('复制失败，请手动复制: ' + text);
        }
        
        document.body.removeChild(textArea);
    }
    
    function showCopyMessage(message) {
        // 创建提示消息
        const messageDiv = document.createElement('div');
        messageDiv.textContent = message;
        messageDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #4CAF50;
            color: white;
            padding: 12px 20px;
            border-radius: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            z-index: 10000;
            font-size: 14px;
            max-width: 300px;
            word-wrap: break-word;
        `;
        
        document.body.appendChild(messageDiv);
        
        // 3秒后自动移除
        setTimeout(function() {
            if (messageDiv.parentNode) {
                messageDiv.parentNode.removeChild(messageDiv);
            }
        }, 3000);
    }
    </script>
</body>
</html>