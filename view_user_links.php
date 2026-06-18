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
    die('未授权访问');
}

if (!isset($_GET['user_id'])) {
    die('参数错误');
}

$user_id = intval($_GET['user_id']);

// 获取用户信息
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die('用户不存在');
}

// 获取用户的所有链接，包括点击统计
$query = "SELECT l.*, COUNT(c.id) AS click_count 
         FROM links l 
         LEFT JOIN url_clicks c ON c.short_code = l.short_code 
         WHERE l.user_id = ? OR l.user_code = ? 
         GROUP BY l.id 
         ORDER BY l.created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute([$user_id, $user['user_code']]);
$links = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="p-4 bg-white rounded-lg shadow">
    <div class="mb-6 border-b pb-4">
        <h3 class="text-xl font-bold text-gray-800">用户详情</h3>
        <div class="mt-2 space-y-1">
            <p class="text-gray-600">用户名：<span class="font-medium text-gray-800"><?= htmlspecialchars($user['username']) ?></span></p>
            <p class="text-gray-600">用户编码：<span class="font-medium text-gray-800"><?= htmlspecialchars($user['user_code']) ?></span></p>
            <p class="text-gray-600">注册时间：<span class="font-medium text-gray-800"><?= $user['created_at'] ?></span></p>
            <p class="text-gray-600">链接数量：<span class="font-medium text-gray-800"><?= count($links) ?></span></p>
        </div>
    </div>

    <?php if (empty($links)): ?>
    <div class="text-center py-8">
        <p class="text-gray-500">该用户暂无链接</p>
    </div>
    <?php else: ?>
    <div class="space-y-4">
        <?php foreach ($links as $link): ?>
        <div class="border rounded-lg p-4 hover:bg-gray-50 transition-colors duration-200">
            <div class="flex justify-between items-start">
                <div class="space-y-2 flex-1">
                    <h4 class="font-semibold text-gray-900"><?= htmlspecialchars($link['title'] ?? '无标题') ?></h4>
                    <div class="space-y-1">
                        <p class="text-sm text-gray-600">
                            原始链接：<a href="<?= htmlspecialchars($link['original_url']) ?>" target="_blank" 
                                    class="text-blue-600 hover:text-blue-800 hover:underline break-all">
                                <?= htmlspecialchars($link['original_url']) ?>
                            </a>
                        </p>
                        <p class="text-sm text-gray-600">
                            短链接：<a href="<?= BASE_URL . $link['short_code'] ?>" target="_blank" 
                                   class="text-blue-600 hover:text-blue-800 hover:underline">
                                <?= BASE_URL . $link['short_code'] ?>
                            </a>
                        </p>
                    </div>
                </div>
                <div class="text-right ml-4">
                    <div class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-medium">
                        <?= number_format($link['click_count']) ?> 次点击
                    </div>
                    <p class="text-xs text-gray-500 mt-2"><?= $link['created_at'] ?></p>
                </div>
            </div>
            <div class="mt-3 pt-3 border-t flex justify-end space-x-3">
                <a href="view_stats.php?id=<?= $link['id'] ?>" target="_blank" 
                   class="text-sm bg-blue-50 text-blue-600 px-3 py-1 rounded hover:bg-blue-100 transition-colors duration-200">
                    📊 查看统计
                </a>
                <a href="admin_dashboard.php?delete=<?= $link['id'] ?>&source=links&user_id=<?= $user_id ?>" 
                   onclick="return confirm('确定要删除这个链接吗？')" 
                   class="text-sm bg-red-50 text-red-600 px-3 py-1 rounded hover:bg-red-100 transition-colors duration-200">
                    🗑️ 删除链接
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>