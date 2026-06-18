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

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 获取用户信息
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$user_code = $_SESSION['user_code'];

// 处理批量删除
if (isset($_POST['batch_delete']) && isset($_POST['selected_links'])) {
    $selected_links = $_POST['selected_links'];
    $placeholders = str_repeat('?,', count($selected_links) - 1) . '?';
    $params = array_merge($selected_links, [$user_id, $user_code]);
    $stmt = $pdo->prepare("DELETE FROM links WHERE id IN ($placeholders) AND (user_id = ? OR user_code = ?)");
    $stmt->execute($params);
    header("Location: user_dashboard.php?deleted=1");
    exit;
}

// 处理收藏/取消收藏
if (isset($_POST['toggle_favorite'])) {
    $link_id = intval($_POST['link_id']);
    $stmt = $pdo->prepare("SELECT id FROM favorite_links WHERE link_id = ? AND user_id = ?");
    $stmt->execute([$link_id, $user_id]);
    
    if ($stmt->fetch()) {
        // 取消收藏
        $stmt = $pdo->prepare("DELETE FROM favorite_links WHERE link_id = ? AND user_id = ?");
        $stmt->execute([$link_id, $user_id]);
    } else {
        // 添加收藏
        $stmt = $pdo->prepare("INSERT INTO favorite_links (link_id, user_id) VALUES (?, ?)");
        $stmt->execute([$link_id, $user_id]);
    }
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}

// 删除单个短链接
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    // 确保只能删除自己的链接
    $stmt = $pdo->prepare("DELETE FROM links WHERE id = ? AND (user_id = ? OR user_code = ?)");
    $stmt->execute([$id, $user_id, $user_code]);
    header("Location: user_dashboard.php?deleted=1");
    exit;
}

// 获取当前页码、筛选类型和搜索关键词
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// 基础查询条件
$base_where = "(u.user_id = ? OR u.user_code = ?)";
$params = [$user_id, $user_code];

// 添加收藏筛选
if ($filter === 'favorites') {
    $base_where .= " AND EXISTS (SELECT 1 FROM favorite_links f WHERE f.link_id = u.id AND f.user_id = ?)";
    $params[] = $user_id;
}

// 添加搜索条件
if (!empty($search)) {
    $base_where .= " AND (u.short_code LIKE ? OR u.original_url LIKE ?)"; 
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// 根据筛选类型构建查询
switch($filter) {
    case '7days':
        $query = "SELECT u.*, COUNT(c.id) AS click_count 
                FROM links u 
                LEFT JOIN url_clicks c ON c.short_code = u.short_code 
                WHERE $base_where AND c.clicked_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
                GROUP BY u.id 
                ORDER BY click_count DESC";
        break;
    case '30days':
        $query = "SELECT u.*, COUNT(c.id) AS click_count 
                FROM links u 
                LEFT JOIN url_clicks c ON c.short_code = u.short_code 
                WHERE $base_where AND c.clicked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
                GROUP BY u.id 
                ORDER BY click_count DESC";
        break;
    default:
        $query = "SELECT u.*, COUNT(c.id) AS click_count 
                FROM links u 
                LEFT JOIN url_clicks c ON c.short_code = u.short_code 
                WHERE $base_where 
                GROUP BY u.id 
                ORDER BY u.created_at DESC";
}

// 获取总记录数
$count_query = "SELECT COUNT(*) FROM links u WHERE " . $base_where;
$stmt = $pdo->prepare($count_query);
$stmt->execute($params);
$total_records = $stmt->fetchColumn();
$total_pages = ceil($total_records / $records_per_page);

// 添加分页限制
$query .= " LIMIT $offset, $records_per_page";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$links = $stmt->fetchAll(PDO::FETCH_ASSOC);



// 成功消息
$message = "";
if (isset($_GET['deleted']) && $_GET['deleted'] == 1) {
    $message = "<div class='alert alert-success'>短链接已成功删除！</div>";
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>我的短链接 - A6.cm短网址</title>
    <style>
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
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            padding: 0;
            margin: 0;
        }
        
        .header {
            background: linear-gradient(135deg, #3498db, #8e44ad);
            color: white;
            padding: 1.5rem 0;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            font-size: 2.2rem;
            margin-bottom: 0.5rem;
        }
        
        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .container {
            width: 95%;
            max-width: 1400px;
            margin: 30px auto;
            padding: 2rem;
            background-color: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1.5rem;
        }

        .dashboard-actions {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            align-items: flex-end;
        }

        .search-filter-form {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-input {
            padding: 0.5rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 1rem;
            min-width: 250px;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }

        .filter-select {
            padding: 0.5rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 1rem;
            background-color: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .filter-select:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }

        .nav-buttons {
            display: flex;
            gap: 0.5rem;
        }

        @media (max-width: 768px) {
            .dashboard-actions {
                width: 100%;
                align-items: stretch;
            }

            .search-filter-form {
                flex-direction: column;
                width: 100%;
            }

            .search-input,
            .filter-select {
                width: 100%;
            }

            .nav-buttons {
                justify-content: space-between;
            }
        }
        
        .dashboard-title {
            color: var(--primary-color);
        }
        
        .user-info {
            background-color: rgba(52, 152, 219, 0.1);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        
        .user-info p {
            margin: 0.5rem 0;
        }
        
        .user-code {
            font-weight: bold;
            color: var(--primary-color);
            background-color: rgba(52, 152, 219, 0.1);
            padding: 0.3rem 0.6rem;
            border-radius: 4px;
        }
        
        .btn {
            padding: 0.7rem 1.2rem;
            font-size: 1rem;
            font-weight: 600;
            color: white;
            background-color: var(--primary-color);
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn:hover {
            background-color: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .btn-danger {
            background-color: var(--error-color);
        }
        
        .btn-danger:hover {
            background-color: #c0392b;
        }
        
        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.9rem;
            min-width: 60px;
            text-align: center;
            margin: 2px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        table, th, td {
            border: 1px solid var(--border-color);
        }
        
        th, td {
            padding: 0.8rem;
            text-align: center;
        }
        
        /* 确保表格在所有设备上都能正常显示 */
        @media (max-width: 1200px) {
            table {
                display: block;
                overflow-x: auto;
            }
            
            th, td {
                padding: 0.8rem 0.5rem;
                font-size: 0.9rem;
            }
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
                width: 100%;
            }
            
            th, td {
                padding: 0.6rem 0.4rem;
                font-size: 0.85rem;
            }
            
            .btn-sm {
                padding: 0.4rem 0.6rem;
                font-size: 0.85rem;
                display: inline-block;
                margin-bottom: 3px;
            }
        }
        
        th {
            background-color: rgba(52, 152, 219, 0.1);
            padding: 1rem;
            text-align: left;
        }
        
        td {
            padding: 1rem;
            vertical-align: middle;
        }
        
        tr:nth-child(even) {
            background-color: rgba(0, 0, 0, 0.02);
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 2rem;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .pagination a, .pagination span {
            padding: 0.5rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            text-decoration: none;
            color: var(--primary-color);
            background-color: white;
            transition: all 0.3s ease;
        }
        
        .pagination a:hover {
            background-color: rgba(52, 152, 219, 0.1);
        }
        
        .pagination .active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        
        .alert-success {
            color: var(--success-color);
            background-color: rgba(46, 204, 113, 0.1);
        }
        
        .no-links {
            text-align: center;
            padding: 2rem;
            background-color: rgba(0, 0, 0, 0.02);
            border-radius: 8px;
            margin: 2rem 0;
        }
        
        .footer {
            text-align: center;
            padding: 2rem 1rem;
            margin-top: auto;
            color: var(--light-text);
            font-size: 0.9rem;
        }
        
        .actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .truncate {
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 1.5rem;
                width: 98%;
            }
            
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .truncate {
                max-width: 200px;
            }
        }
        .ranking-section {
            margin-bottom: 2rem;
            background-color: var(--card-bg);
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .ranking-section h3 {
            color: var(--primary-color);
            margin-bottom: 1rem;
            font-size: 1.3rem;
        }
        
        .no-data {
            text-align: center;
            padding: 1rem;
            background-color: rgba(0,0,0,0.02);
            border-radius: 4px;
            color: var(--light-text);
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔗 A6.cm短网址生成器</h1>
        <p>简单、高效、安全的链接缩短服务</p>
    </div>

    <div class="container">
        <?php if ($message) echo $message; ?>
        


        <div class="dashboard-header">
            <h2 class="dashboard-title">我的短链接管理</h2>
            <div class="dashboard-actions">
                <form method="GET" class="search-filter-form">
                    <input type="text" name="search" placeholder="搜索短链接或原始链接..." value="<?php echo htmlspecialchars($search); ?>" class="search-input">
                    <select name="filter" class="filter-select">
                        <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>全部链接</option>
                        <option value="favorites" <?php echo $filter === 'favorites' ? 'selected' : ''; ?>>收藏链接</option>
                        <option value="7days" <?php echo $filter === '7days' ? 'selected' : ''; ?>>近7天热门</option>
                        <option value="30days" <?php echo $filter === '30days' ? 'selected' : ''; ?>>近30天热门</option>
                    </select>
                    <button type="submit" class="btn btn-primary">🔍 搜索</button>
                    <a href="user_dashboard.php" class="btn">🔄 重置</a>
                </form>
                <div class="nav-buttons">
                    <a href="index.php" class="btn">🏠 返回首页</a>
                    <a href="logout.php" class="btn btn-danger">🚪 退出登录</a>
                </div>
            </div>
        </div>
        
        <div class="user-info">
            <p>👤 用户名：<?php echo htmlspecialchars($username); ?></p>
            <p>🔑 用户编码：<span class="user-code"><?php echo htmlspecialchars($user_code); ?></span> (未登录时可使用此编码关联短链接)</p>
        </div>
        
        <?php if (count($links) > 0): ?>
            <form method="POST" id="linksForm">
                <div class="batch-actions" style="margin-bottom: 1rem;">
                    <button type="submit" name="batch_delete" class="btn btn-danger" onclick="return confirm('确定要删除选中的链接吗？')" style="display: none;" id="batchDeleteBtn">🗑️ 批量删除</button>
                </div>
                <table>
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAll"></th>
                        <th>短链接</th>
                        <th>原始链接</th>
                        <th>备注</th>
                        <th>点击次数</th>
                        <th>最大点击数</th>
                        <th>创建时间</th>
                        <th>到期时间</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($links as $link): 
                        // 检查是否已收藏
                        $stmt = $pdo->prepare("SELECT 1 FROM favorite_links WHERE link_id = ? AND user_id = ?");
                        $stmt->execute([$link['id'], $user_id]);
                        $is_favorite = $stmt->fetch() ? true : false;
                    ?>
                        <tr>
                            <td><input type="checkbox" name="selected_links[]" value="<?php echo $link['id']; ?>" class="link-checkbox"></td>
                            <td>
                                <?php 
                                $short_url = "http://" . $_SERVER['HTTP_HOST'] . "/" . $link['short_code'];
                                echo "<a href='$short_url' target='_blank'>". htmlspecialchars($short_url) ."</a>";
                                ?>
                            </td>
                            <td class="truncate" title="<?php echo htmlspecialchars($link['original_url']); ?>">
                                <?php echo htmlspecialchars($link['original_url']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($link['remark'] ?? '无'); ?></td>
                            <td><?php echo $link['click_count']; ?></td>
                            <td><?php echo $link['max_clicks'] ? $link['max_clicks'] : '不限'; ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($link['created_at'])); ?></td>
                            <td><?php echo $link['expire_at'] ? date('Y-m-d H:i', strtotime($link['expire_at'])) : '永久有效'; ?></td>
                            <td class="actions">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="link_id" value="<?php echo $link['id']; ?>">
                                    <button type="submit" name="toggle_favorite" class="btn btn-sm <?php echo $is_favorite ? 'btn-warning' : ''; ?>">
                                        <?php echo $is_favorite ? '⭐ 已收藏' : '☆ 收藏'; ?>
                                    </button>
                                </form>
                                <a href="view_stats.php?id=<?php echo $link['id']; ?>&source=links" class="btn btn-sm">📊 统计</a>
                                <a href="user_dashboard.php?delete=<?php echo $link['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('确定要删除这个短链接吗？');">🗑️ 删除</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </form>
            <script>
                document.getElementById('selectAll').addEventListener('change', function() {
                    document.querySelectorAll('.link-checkbox').forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                    updateBatchDeleteButton();
                });

                document.querySelectorAll('.link-checkbox').forEach(checkbox => {
                    checkbox.addEventListener('change', updateBatchDeleteButton);
                });

                function updateBatchDeleteButton() {
                    const checkedBoxes = document.querySelectorAll('.link-checkbox:checked');
                    document.getElementById('batchDeleteBtn').style.display = checkedBoxes.length > 0 ? 'inline-block' : 'none';
                }
            </script>
            
            <!-- 分页 -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=1">首页</a>
                        <a href="?page=<?php echo $page-1; ?>">上一页</a>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++) {
                        if ($i == $page) {
                            echo "<span class='active'>$i</span>";
                        } else {
                            echo "<a href='?page=$i'>$i</a>";
                        }
                    }
                    ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page+1; ?>">下一页</a>
                        <a href="?page=<?php echo $total_pages; ?>">末页</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="no-links">
                <p>您还没有创建任何短链接！</p>
                <a href="index.php" class="btn" style="margin-top: 1rem;">🚀 立即创建短链接</a>
            </div>
        <?php endif; ?>
    </div>

    <div class="footer">
        <p>© <?php echo date('Y'); ?> A6.cm短网址服务 - 让链接分享更简单</p>
    </div>
</body>
</html>