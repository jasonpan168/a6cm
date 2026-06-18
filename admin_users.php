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

// 处理用户权限更新
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $user_id = intval($_POST['user_id']);
    $is_premium = isset($_POST['is_premium']) ? 1 : 0;
    $link_limit = isset($_POST['link_limit']) ? intval($_POST['link_limit']) : 5;
    $premium_expiry = !empty($_POST['premium_expiry']) ? $_POST['premium_expiry'] : null;

    $stmt = $pdo->prepare("UPDATE users SET is_premium = ?, link_limit = ?, premium_expiry = ? WHERE id = ?");
    $stmt->execute([$is_premium, $link_limit, $premium_expiry, $user_id]);

    $message = "<div class='alert alert-success'>用户权限已成功更新！</div>";
}

// 处理删除用户链接
if (isset($_GET['delete_links']) && isset($_GET['user_id'])) {
    $user_id = intval($_GET['user_id']);
    $stmt = $pdo->prepare("DELETE FROM links WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $message = "<div class='alert alert-success'>用户的所有链接已成功删除！</div>";
}

// 处理删除用户账号
if (isset($_GET['delete_user']) && isset($_GET['user_id'])) {
    $user_id = intval($_GET['user_id']);
    // 先删除用户的所有链接
    $stmt = $pdo->prepare("DELETE FROM links WHERE user_id = ?");
    $stmt->execute([$user_id]);
    // 再删除用户账号
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $message = "<div class='alert alert-success'>用户账号已成功删除！</div>";
}

// 分页设置
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10; // 每页显示10个用户
$offset = ($page - 1) * $per_page;

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

$total_pages = ceil($total_users / $per_page);

// 获取当前页的用户列表
$stmt = $pdo->prepare("SELECT u.*, COUNT(l.id) as link_count 
                    FROM users u 
                    LEFT JOIN links l ON u.id = l.user_id 
                    GROUP BY u.id 
                    ORDER BY u.created_at DESC 
                    LIMIT ? OFFSET ?");
$stmt->execute([$per_page, $offset]);
$users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户管理 - 短链接管理系统</title>
    <link href="https://cdn.bootcdn.net/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
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
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }
        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 20px;
            border-radius: 8px;
            width: 80%;
            max-width: 600px;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <nav class="bg-gradient-to-r from-blue-600 to-indigo-600 p-4 shadow-lg">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-2xl font-bold text-white">👥 用户管理</h1>
            <div class="flex gap-4">
                <a href="admin_dashboard.php" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition-colors duration-200">📊 返回仪表盘</a>
                <a href="logout.php" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg transition-colors duration-200">🚪 退出登录</a>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8">
        <?php if (isset($message)) echo $message; ?>

        <!-- 用户统计卡片 -->
        <div class="grid grid-cols-1 md:grid-cols-6 gap-6 mb-8">
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-lg font-semibold text-gray-700 mb-2">总注册用户</h3>
                <p class="text-3xl font-bold text-blue-600 cursor-pointer hover:text-blue-800" onclick="viewAllUsers()"><?= $total_users ?></p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-lg font-semibold text-gray-700 mb-2">今日新增用户</h3>
                <p class="text-3xl font-bold text-green-600 cursor-pointer hover:text-green-800" onclick="viewTodayNewUsers()"><?= $today_new_users ?></p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-lg font-semibold text-gray-700 mb-2">近七日新增用户</h3>
                <p class="text-3xl font-bold text-indigo-600 cursor-pointer hover:text-indigo-800" onclick="viewSevenDaysUsers()"><?= $seven_days_new_users ?></p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-lg font-semibold text-gray-700 mb-2">今日生成用户</h3>
                <p class="text-3xl font-bold text-purple-600 cursor-pointer hover:text-purple-800" onclick="viewTodayGeneratedUsers()"><?= $today_generated_links ?></p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-lg font-semibold text-gray-700 mb-2">今日总链接数</h3>
                <p class="text-3xl font-bold text-teal-600 cursor-pointer hover:text-teal-800" onclick="viewTodayLinks()"><?= $today_total_links ?></p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-lg font-semibold text-gray-700 mb-2">今日点击次数</h3>
                <p class="text-3xl font-bold text-red-600 cursor-pointer hover:text-red-800" onclick="viewTodayClicks()"><?= $today_clicks ?></p>
            </div>
        </div>


        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">用户名</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">邮箱</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">用户编码</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">链接数量</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">注册时间</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">权限管理</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($user['username']) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($user['email']) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($user['user_code']) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <button onclick="viewLinks(<?= $user['id'] ?>)" class="text-blue-600 hover:text-blue-800">
                                <?= $user['link_count'] ?> 个链接
                            </button>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap"><?= $user['created_at'] ?></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center space-x-2">
                                <form method="POST" class="flex items-center space-x-2">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" name="is_premium" class="form-checkbox h-4 w-4 text-blue-600" 
                                               <?= $user['is_premium'] ? 'checked' : '' ?>>
                                        <span class="ml-2 text-sm">高级用户</span>
                                    </label>
                                    <input type="number" name="link_limit" value="<?= $user['link_limit'] ?>" 
                                           class="w-20 px-2 py-1 border rounded" min="0">
                                    <input type="date" name="premium_expiry" 
                                           value="<?= $user['premium_expiry'] ? date('Y-m-d', strtotime($user['premium_expiry'])) : '' ?>" 
                                           class="px-2 py-1 border rounded">
                                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded">
                                        更新
                                    </button>
                                </form>
                                <a href="?delete_links=1&user_id=<?= $user['id'] ?>" onclick="return confirm('确定要删除该用户的所有链接吗？')" 
                                   class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded">删除链接</a>
                                <a href="?delete_user=1&user_id=<?= $user['id'] ?>" onclick="return confirm('确定要删除该用户账号吗？这将同时删除用户的所有链接！')" 
                                   class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded">删除账号</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- 分页导航 -->
        <?php if ($total_pages > 1): ?>
        <div class="flex justify-center mt-6 space-x-2">
            <?php if ($page > 1): ?>
            <a href="?page=1" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">首页</a>
            <a href="?page=<?= $page-1 ?>" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">上一页</a>
            <?php endif; ?>

            <?php
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            
            for ($i = $start_page; $i <= $end_page; $i++): ?>
            <a href="?page=<?= $i ?>" class="px-4 py-2 <?= $i == $page ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700' ?> rounded-lg hover:bg-<?= $i == $page ? 'blue-600' : 'gray-300' ?>">
                <?= $i ?>
            </a>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page+1 ?>" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">下一页</a>
            <a href="?page=<?= $total_pages ?>" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">末页</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <!-- 链接详情模态框 -->
    <div id="linksModal" class="modal">
        <div class="modal-content">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold">用户链接详情</h2>
                <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700">&times;</button>
            </div>
            <div id="linksContent" class="max-h-96 overflow-y-auto"></div>
        </div>

        <!-- 分页导航 -->
        <?php if ($total_pages > 1): ?>
        <div class="flex justify-center mt-6 space-x-2">
            <?php if ($page > 1): ?>
            <a href="?page=1" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">首页</a>
            <a href="?page=<?= $page-1 ?>" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">上一页</a>
            <?php endif; ?>

            <?php
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            
            for ($i = $start_page; $i <= $end_page; $i++): ?>
            <a href="?page=<?= $i ?>" class="px-4 py-2 <?= $i == $page ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700' ?> rounded-lg hover:bg-<?= $i == $page ? 'blue-600' : 'gray-300' ?>">
                <?= $i ?>
            </a>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page+1 ?>" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">下一页</a>
            <a href="?page=<?= $total_pages ?>" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">末页</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function viewLinks(userId) {
            fetch(`view_user_links.php?user_id=${userId}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('linksContent').innerHTML = data;
                    document.getElementById('linksModal').style.display = 'block';
                });
        }

        function closeModal() {
            document.getElementById('linksModal').style.display = 'none';
        }

        // 点击模态框外部关闭
        window.onclick = function(event) {
            if (event.target == document.getElementById('linksModal')) {
                closeModal();
            }
        }

        // 统计卡片点击事件
        function viewAllUsers() {
            // 当前页面就是用户列表，滚动到表格位置
            document.querySelector('table').scrollIntoView({ behavior: 'smooth' });
        }

        function viewTodayNewUsers() {
            fetch('get_stats_data.php?type=today_new_users')
                .then(response => response.text())
                .then(data => {
                    document.getElementById('linksContent').innerHTML = data;
                    document.getElementById('linksModal').style.display = 'block';
                });
        }

        function viewSevenDaysUsers() {
            fetch('get_stats_data.php?type=seven_days_users')
                .then(response => response.text())
                .then(data => {
                    document.getElementById('linksContent').innerHTML = data;
                    document.getElementById('linksModal').style.display = 'block';
                });
        }

        function viewTodayGeneratedUsers() {
            fetch('get_stats_data.php?type=today_generated_users')
                .then(response => response.text())
                .then(data => {
                    document.getElementById('linksContent').innerHTML = data;
                    document.getElementById('linksModal').style.display = 'block';
                });
        }

        function viewTodayLinks() {
            fetch('get_stats_data.php?type=today_links')
                .then(response => response.text())
                .then(data => {
                    document.getElementById('linksContent').innerHTML = data;
                    document.getElementById('linksModal').style.display = 'block';
                });
        }

        function viewTodayClicks() {
            fetch('get_stats_data.php?type=today_clicks')
                .then(response => response.text())
                .then(data => {
                    document.getElementById('linksContent').innerHTML = data;
                    document.getElementById('linksModal').style.display = 'block';
                });
        }
    </script>
</body>
</html>