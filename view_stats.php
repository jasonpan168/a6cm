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

if (!isset($_GET['id'])) {
    die("参数错误！");
}

$id = intval($_GET['id']);
$source_table = isset($_GET['source']) ? $_GET['source'] : 'links';

// 检查用户是否已登录
$is_logged_in = isset($_SESSION['user_id']);
$is_admin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// 获取链接信息
$link = null;
if ($source_table == 'links') {
    $stmt = $pdo->prepare("SELECT * FROM links WHERE id = ?");
    $stmt->execute([$id]);
    $link = $stmt->fetch(PDO::FETCH_ASSOC);
} else if ($source_table == 'urls') {
    $stmt = $pdo->prepare("SELECT * FROM urls WHERE id = ?");
    $stmt->execute([$id]);
    $link = $stmt->fetch(PDO::FETCH_ASSOC);
}

// 如果链接不存在
if (!$link) {
    die("链接不存在！");
}

// 检查访问权限
// 如果是管理员，直接允许访问
// 如果是普通用户，检查是否是链接创建者
if ($is_admin) {
    // 管理员可以查看任何链接的统计数据
    // 无需额外验证
} else if (!$is_logged_in) {
    // 用户未登录，重定向到登录页面
    header("Location: login.php?redirect=view_stats.php?id=$id&source=$source_table");
    exit;
} else if (isset($link['user_id']) && $link['user_id'] != $_SESSION['user_id']) {
    // 用户已登录但不是链接创建者
    die("您没有权限查看此链接的统计数据！");
}

// 确保查询正确的表和字段
$stmt = $pdo->prepare("SELECT * FROM url_clicks WHERE short_code = ? ORDER BY clicked_at DESC");
$stmt->execute([$link['short_code']]);
$clicks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 调试信息
error_log("查询短链接 {$link['short_code']} 的点击记录，找到 " . count($clicks) . " 条记录");

// 获取总点击次数
$total_clicks = 0;

// 从url_clicks表获取点击次数
$stmt = $pdo->prepare("SELECT COUNT(*) FROM url_clicks WHERE short_code = ?");
$stmt->execute([$link['short_code']]);
$url_clicks_count = $stmt->fetchColumn();
$total_clicks += $url_clicks_count;

// 如果是老数据(urls表)，还需要考虑表中原有的click_count字段
if ($source_table == 'urls' && isset($link['click_count'])) {
    // 如果url_clicks表中没有记录，使用urls表中的click_count
    if ($url_clicks_count == 0) {
        $total_clicks = $link['click_count'];
    }
}

// 如果是新数据(links表)，也考虑表中的click_count字段
if ($source_table == 'links' && isset($link['click_count'])) {
    // 如果url_clicks表中没有记录，使用links表中的click_count
    if ($url_clicks_count == 0 && $link['click_count'] > 0) {
        $total_clicks = $link['click_count'];
    }
}

// 记录调试信息
error_log("短链接 {$link['short_code']} 的总点击次数: $total_clicks (url_clicks表: $url_clicks_count, {$source_table}表: {$link['click_count']})");

// 如果没有找到点击记录，记录调试信息
if (empty($clicks)) {
    error_log("未找到短链接 {$link['short_code']} 的点击记录");
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>访问数据 - <?= $link['short_code'] ?></title>
    <!-- 添加Chart.js库，用于绘制统计图表 -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-moment@1.0.0/dist/chartjs-adapter-moment.min.js"></script>
    <style>
        /* 选项卡样式 */
        .tab-nav {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 1rem;
        }
        
        .tab-btn {
            padding: 0.75rem 1.5rem;
            border: none;
            background: none;
            color: var(--text-color);
            cursor: pointer;
            font-size: 1rem;
            border-radius: 4px;
            transition: all 0.3s;
        }
        
        .tab-btn:hover {
            background-color: rgba(52, 152, 219, 0.1);
        }
        
        .tab-btn.active {
            background-color: var(--primary-color);
            color: white;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        :root {
            --primary-color: #3498db;
            --primary-hover: #2980b9;
            --success-color: #2ecc71;
            --error-color: #e74c3c;
            --text-color: #333;
            --light-text: #666;
            --border-color: #ddd;
            --bg-color: #f8f9fa;
            --card-bg: #ffffff;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Microsoft YaHei', 'PingFang SC', 'Helvetica Neue', Arial, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            line-height: 1.6;
            padding: 0;
            margin: 0;
        }
        
        .header {
            background: linear-gradient(135deg, #3498db, #8e44ad);
            color: white;
            padding: 1.5rem 0;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .container {
            width: 95%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .card {
            background-color: var(--card-bg);
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stats-summary {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            flex: 1;
            min-width: 200px;
            background-color: white;
            border-radius: 8px;
            padding: 1rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-card h3 {
            margin-bottom: 0.5rem;
            color: var(--primary-color);
        }
        
        .stat-card .value {
            font-size: 2rem;
            font-weight: bold;
        }
        
        .stats-charts {
            margin: 2rem 0;
        }
        
        .chart-container {
            background-color: white;
            border-radius: 8px;
            padding: 1rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }
        
        .chart-container h3 {
            margin-bottom: 1rem;
            color: var(--primary-color);
            text-align: center;
        }
        
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            transition: background-color 0.3s;
            text-decoration: none;
        }
        
        .btn:hover {
            background-color: var(--primary-hover);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
        }
        
        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        th {
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
        }
        
        tr:hover {
            background-color: rgba(0,0,0,0.02);
        }
        
        .footer {
            text-align: center;
            padding: 1.5rem 0;
            margin-top: 2rem;
            color: var(--light-text);
            border-top: 1px solid var(--border-color);
        }
        
        .no-data {
            text-align: center;
            padding: 2rem;
            color: var(--light-text);
            font-style: italic;
        }
        
        @media (max-width: 768px) {
            .container {
                width: 100%;
                padding: 0 10px;
            }
            
            table {
                display: block;
                overflow-x: auto;
            }
            
            .stats-summary {
                flex-direction: column;
            }
            
            .stat-card {
                width: 100%;
            }
        }
        
        /* 确保表格在所有设备上都能正常显示 */
        @media (max-width: 1200px) {
            th, td {
                padding: 0.8rem 0.5rem;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1>📊 短链接访问详情：<?= htmlspecialchars($link['short_code']) ?></h1>
        </div>
    </div>
    
    <div class="container">
        <div class="card">
            <div class="stats-summary">
                <div class="stat-card">
                    <h3>总点击次数</h3>
                    <div class="value"><?= $total_clicks ?? 0 ?></div>
                </div>
                <div class="stat-card">
                    <h3>原始链接</h3>
                    <div style="word-break: break-all; font-size: 0.9rem;">
                        <a href="<?= htmlspecialchars($link['original_url']) ?>" target="_blank">
                            <?= htmlspecialchars($link['original_url']) ?>
                        </a>
                    </div>
                </div>
                <div class="stat-card">
                    <h3>创建时间</h3>
                    <div style="font-size: 1rem;"><?= $link['created_at'] ?></div>
                </div>
            </div>
            
            <div style="margin-bottom: 1rem;">
                <?php if(isset($_SESSION['admin_logged_in'])): ?>
                    <a href="admin_dashboard.php" class="btn">返回管理后台</a>
                <?php elseif(isset($_SESSION['user_id'])): ?>
                    <a href="user_dashboard.php" class="btn">返回用户后台</a>
                <?php else: ?>
                    <a href="index.php" class="btn">返回首页</a>
                <?php endif; ?>
            </div>
            
            <?php if(isset($_GET['deleted'])): ?>
            <div style="margin-bottom: 1rem; padding: 10px; background-color: var(--success-color); color: white; border-radius: 5px;">
                链接已成功删除！
            </div>
            <?php endif; ?>
            
            <!-- 选项卡导航 -->
            <div class="tab-nav">
                <button class="tab-btn active" data-tab="chart">📈 访问统计图表</button>
                <button class="tab-btn" data-tab="list">📋 访问记录列表</button>
            </div>
            
            <?php if (empty($clicks)): ?>
                <div class="no-data">暂无访问记录</div>
            <?php else: ?>
                <!-- 访问统计图表 -->
                <div class="tab-content" id="chart-tab" style="display: block;">
                    <div class="stats-charts">
                        <!-- 图表类型切换按钮 -->
                        <div style="margin-bottom: 20px; text-align: center;">
                            <button id="dailyChartBtn" class="chart-type-btn active" onclick="switchChart('daily')">📊 每日访问数据</button>
                            <button id="realtimeChartBtn" class="chart-type-btn" onclick="switchChart('realtime')">📅 今日访问数据</button>
                        </div>
                        
                        <style>
                        .chart-type-btn {
                            background: #f8f9fa;
                            border: 2px solid #dee2e6;
                            color: #495057;
                            padding: 12px 24px;
                            margin: 0 10px;
                            border-radius: 25px;
                            cursor: pointer;
                            font-size: 14px;
                            font-weight: 500;
                            transition: all 0.3s ease;
                            display: inline-flex;
                            align-items: center;
                            gap: 8px;
                        }
                        .chart-type-btn:hover {
                            background: #e9ecef;
                            border-color: #adb5bd;
                            transform: translateY(-1px);
                            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                        }
                        .chart-type-btn.active {
                            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                            border-color: #667eea;
                            color: white;
                            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
                        }
                        .chart-type-btn.active:hover {
                            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
                            transform: translateY(-2px);
                            box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
                        }
                        </style>
                        
                        <div class="chart-container">
                            <!-- 每日访问量图表 -->
                            <div id="dailyChartContainer">
                                <h3>每日访问量</h3>
                                <canvas id="dailyVisitsChart" style="max-height: 400px;"></canvas>
                            </div>
                            
                            <!-- 今日访问量图表 -->
                            <div id="realtimeChartContainer" style="display: none;">
                                <h3>今日访问量</h3>
                                <canvas id="realtimeVisitsChart" style="max-height: 400px;"></canvas>
                                <div style="text-align: center; margin-top: 10px; color: #666; font-size: 12px;">
                                    数据每30秒自动更新
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 访问记录列表 -->
                <div class="tab-content" id="list-tab" style="display: none;">
                    <table>
                    <thead>
                        <tr>
                            <th>访问时间</th>
                            <th>IP 地址</th>
                            <th>来源网址</th>
                            <th>浏览器</th>
                            <th>操作系统</th>
                            <th>设备类型</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($clicks as $click): 
                        // 从用户代理提取浏览器和操作系统信息
                        $browser = '未知';
                        $os = '未知';
                        $device = '未知';
                        
                        $user_agent = $click['user_agent'];
                        if ($user_agent) {
                            // 浏览器检测
                            if (strpos($user_agent, 'Chrome') !== false) {
                                $browser = 'Chrome';
                            } elseif (strpos($user_agent, 'Firefox') !== false) {
                                $browser = 'Firefox';
                            } elseif (strpos($user_agent, 'Safari') !== false) {
                                $browser = 'Safari';
                            } elseif (strpos($user_agent, 'Edge') !== false || strpos($user_agent, 'Edg') !== false) {
                                $browser = 'Edge';
                            } elseif (strpos($user_agent, 'MSIE') !== false || strpos($user_agent, 'Trident') !== false) {
                                $browser = 'IE';
                            }
                            
                            // 操作系统检测
                            if (strpos($user_agent, 'Windows') !== false) {
                                $os = 'Windows';
                            } elseif (strpos($user_agent, 'Macintosh') !== false || strpos($user_agent, 'Mac OS') !== false) {
                                $os = 'Mac OS';
                            } elseif (strpos($user_agent, 'Linux') !== false) {
                                $os = 'Linux';
                            } elseif (strpos($user_agent, 'Android') !== false) {
                                $os = 'Android';
                            } elseif (strpos($user_agent, 'iOS') !== false || strpos($user_agent, 'iPhone') !== false || strpos($user_agent, 'iPad') !== false) {
                                $os = 'iOS';
                            }
                            
                            // 设备类型检测
                            if (strpos($user_agent, 'Mobile') !== false || strpos($user_agent, 'Android') !== false || strpos($user_agent, 'iPhone') !== false) {
                                $device = '手机';
                            } elseif (strpos($user_agent, 'iPad') !== false || strpos($user_agent, 'Tablet') !== false) {
                                $device = '平板';
                            } else {
                                $device = '电脑';
                            }
                        }
                    ?>
                        <tr>
                            <td><?= $click['clicked_at'] ?></td>
                            <td><?= htmlspecialchars($click['ip_address']) ?></td>
                            <td><?= htmlspecialchars($click['referer'] ?: '直接访问') ?></td>
                            <td><?= htmlspecialchars($browser) ?></td>
                            <td><?= htmlspecialchars($os) ?></td>
                            <td><?= htmlspecialchars($device) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- 添加图表初始化脚本 -->
                <script>
                let dailyChart = null;
                let realtimeChart = null;
                let realtimeUpdateInterval = null;
                
                document.addEventListener('DOMContentLoaded', function() {
                    // 初始化每日访问图表
                    initDailyChart();
                });
                
                function initDailyChart() {
                    // 准备每日访问数据
                    const clickData = <?= json_encode($clicks) ?>;
                    const dailyVisits = {};
                    
                    // 确保clickData是数组
                    if (Array.isArray(clickData) && clickData.length > 0) {
                        // 按日期分组
                        clickData.forEach(click => {
                            if (click && click.clicked_at) {
                                const date = click.clicked_at.split(' ')[0]; // 只取日期部分
                                if (!dailyVisits[date]) {
                                    dailyVisits[date] = 0;
                                }
                                dailyVisits[date]++;
                            }
                        });
                    } else {
                        console.log('没有点击数据或数据格式不正确');
                    }
                    
                    // 转换为图表数据格式
                    const labels = Object.keys(dailyVisits).sort();
                    const data = labels.map(date => dailyVisits[date]);
                    
                    // 如果没有数据，添加今天的日期和0值
                    if (labels.length === 0) {
                        labels.push(new Date().toISOString().split('T')[0]);
                        data.push(0);
                    }
                    
                    // 创建每日图表
                    const ctx = document.getElementById('dailyVisitsChart').getContext('2d');
                    dailyChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: '每日访问量',
                                data: data,
                                backgroundColor: 'rgba(52, 152, 219, 0.2)',
                                borderColor: 'rgba(52, 152, 219, 1)',
                                borderWidth: 2,
                                tension: 0.1
                            }]
                        },
                        options: {
                            responsive: true,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        precision: 0
                                    }
                                }
                            }
                        }
                    });
                }
                
                function initRealtimeChart() {
                    // 如果实时图表实例已存在，先销毁它
                    if (realtimeChart) {
                        realtimeChart.destroy();
                        realtimeChart = null; // 重置实例
                        console.log('已销毁旧的实时图表实例');
                    }
                    
                    // 创建实时图表
                    const ctx = document.getElementById('realtimeVisitsChart').getContext('2d');
                    realtimeChart = new Chart(ctx, {
                        type: 'scatter', // 使用散点图更适合表示独立事件点
                        data: {
                            labels: [], // X轴标签，将由API数据填充
                            datasets: [{
                                label: '访问事件',
                                data: [], // 将由API数据填充，格式为 {x: time, y: 1}
                                backgroundColor: 'rgb(255, 99, 132)',
                                pointRadius: 5, // 点的大小
                                showLine: false // 不显示连接线，只显示点
                            }]
                        },
                        options: {
                            responsive: true,
                            scales: {
                                x: {
                                    type: 'time', // X轴为时间类型
                                    time: {
                                        parser: 'MM-DD HH:mm:ss',
                                        tooltipFormat: 'll HH:mm:ss', // 提示框中的时间格式
                                        unit: 'minute', // 时间单位可以根据数据密度调整
                                        displayFormats: {
                                            minute: 'HH:mm',
                                            hour: 'HH:00'
                                        }
                                    },
                                    title: {
                                        display: true,
                                        text: '今日访问时间 (时:分:秒)'
                                    }
                                },
                                y: {
                                    beginAtZero: true,
                                    max: 2, // Y轴最大值，因为我们只用1来标记事件
                                    ticks: {
                                        stepSize: 1, // Y轴刻度步长
                                        callback: function(value, index, values) {
                                            if (value === 1) return '访问';
                                            if (value === 0) return '';
                                            return null;
                                        }
                                    },
                                    title: {
                                        display: true,
                                        text: '事件'
                                    }
                                }
                            },
                            plugins: {
                                title: {
                                    display: true,
                                    text: '今日访问事件'
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            return '访问时间: ' + context.label;
                                        }
                                    }
                                }
                            }
                        }
                    });
                    
                    // 加载实时数据
                    updateRealtimeData();
                }
                
                function updateRealtimeData() {
                    // 获取实时数据
                    fetch('get_realtime_stats.php?short_code=<?= $link['short_code'] ?>')
                        .then(response => response.json())
                        .then(data => {
                            console.log('实时数据更新:', data);
                            
                            if (data.success && realtimeChart) {
                                if (data.time_labels && data.event_data) {
                                    if (data.time_labels && data.time_labels.length > 0 && data.event_data && data.event_data.length > 0) {
                                        const chartData = data.time_labels.map((time, index) => {
                                            // 确保时间字符串和事件数据都存在
                                            if (time && data.event_data[index] !== undefined) {
                                                return {
                                                    x: moment(time, 'MM-DD HH:mm:ss').valueOf(), // 使用 valueOf() 获取时间戳，确保兼容性
                                                    y: data.event_data[index]
                                                };
                                            }
                                            return null; // 如果数据不完整，返回null
                                        }).filter(item => item !== null); // 过滤掉无效数据点

                                        realtimeChart.data.datasets[0].data = chartData;
                                        
                                        if (data.total_clicks_today !== undefined) {
                                            realtimeChart.options.plugins.title.text = `今日访问事件 (${data.total_clicks_today}次)`;
                                        }
                                        console.log('图表数据已准备:', chartData);
                                    } else {
                                        // 如果没有有效数据点，清空图表数据或显示提示
                                        realtimeChart.data.datasets[0].data = [];
                                        realtimeChart.options.plugins.title.text = '今日暂无访问事件';
                                        console.log('没有有效的实时数据点来绘制图表。');
                                    }
                                }
                                
                                realtimeChart.update();
                                
                                console.log('图表已更新');
                                console.log('原始时间标签:', data.time_labels);
                                console.log('处理后图表数据:', realtimeChart.data.datasets[0].data);
                                console.log('调试信息:', data.debug);
                            } else {
                                console.error('获取实时数据失败:', data);
                            }
                        })
                        .catch(error => {
                            console.error('获取实时数据出错:', error);
                        });
                }
                
                function switchChart(type) {
                    const dailyBtn = document.getElementById('dailyChartBtn');
                    const realtimeBtn = document.getElementById('realtimeChartBtn');
                    const dailyContainer = document.getElementById('dailyChartContainer');
                    const realtimeContainer = document.getElementById('realtimeChartContainer');
                    
                    if (type === 'daily') {
                        // 切换到每日图表
                        dailyBtn.classList.add('active');
                        realtimeBtn.classList.remove('active');
                        dailyContainer.style.display = 'block';
                        realtimeContainer.style.display = 'none';
                        
                        // 停止实时更新
                        if (realtimeUpdateInterval) {
                            clearInterval(realtimeUpdateInterval);
                            realtimeUpdateInterval = null;
                        }
                    } else if (type === 'realtime') {
                        // 切换到实时图表
                        dailyBtn.classList.remove('active');
                        realtimeBtn.classList.add('active');
                        dailyContainer.style.display = 'none';
                        realtimeContainer.style.display = 'block';
                        
                        // 确保实时图表被正确初始化
                        initRealtimeChart(); // 每次切换都重新初始化，确保图表状态最新
                        
                        // 开始实时更新
                        if (realtimeUpdateInterval) {
                            clearInterval(realtimeUpdateInterval);
                        }
                        realtimeUpdateInterval = setInterval(updateRealtimeData, 30000); // 每30秒更新一次
                        
                        // 立即更新一次数据
                        updateRealtimeData();
                    }
                }
                </script>
                
                <!-- 添加选项卡切换脚本 -->
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const tabBtns = document.querySelectorAll('.tab-btn');
                    const tabContents = document.querySelectorAll('.tab-content');
                    
                    tabBtns.forEach(btn => {
                        btn.addEventListener('click', () => {
                            // 移除所有活动状态
                            tabBtns.forEach(b => b.classList.remove('active'));
                            tabContents.forEach(c => c.style.display = 'none');
                            
                            // 添加当前选中状态
                            btn.classList.add('active');
                            const tabId = btn.getAttribute('data-tab');
                            document.getElementById(`${tabId}-tab`).style.display = 'block';
                        });
                    });
                });
                </script>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="footer">
        <div class="container">
            <p>&copy; <?= date('Y') ?> 短链接管理系统</p>
        </div>
    </div>
</body>
</html>