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

header('Content-Type: application/json');

// 检查用户是否登录（普通用户或管理员）
if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'message' => '用户未登录']);
    exit;
}

// 如果是管理员登录，设置默认值
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    $user_id = null; // 管理员没有user_id
    $user_code = null; // 管理员没有user_code
    $is_admin = true;
} else {
    $user_id = $_SESSION['user_id'];
    $user_code = $_SESSION['user_code'];
    $is_admin = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_link') {
    if (!isset($_POST['link_id']) || !isset($_POST['original_url'])) {
        echo json_encode(['success' => false, 'message' => '缺少必要的参数']);
        exit;
    }

    $link_id = intval($_POST['link_id']);
    $original_url = trim($_POST['original_url']);
    $remark = isset($_POST['remark']) ? trim($_POST['remark']) : null;
    $max_clicks = isset($_POST['max_clicks']) && $_POST['max_clicks'] !== '' ? intval($_POST['max_clicks']) : null;
    $expire_at = isset($_POST['expire_at']) && !empty($_POST['expire_at']) ? date('Y-m-d H:i:s', strtotime($_POST['expire_at'])) : null;

    if (empty($original_url) || !filter_var($original_url, FILTER_VALIDATE_URL)) {
        echo json_encode(['success' => false, 'message' => '原始链接格式不正确']);
        exit;
    }

    // 检查链接权限：管理员可以编辑任何链接，普通用户只能编辑自己的链接
    if ($is_admin) {
        // 管理员权限：检查链接是否存在
        $stmt_check = $pdo->prepare("SELECT id FROM links WHERE id = ?");
        $stmt_check->execute([$link_id]);
    } else {
        // 普通用户权限：检查链接是否属于当前用户
        $stmt_check = $pdo->prepare("SELECT id FROM links WHERE id = ? AND (user_id = ? OR user_code = ?)");
        $stmt_check->execute([$link_id, $user_id, $user_code]);
    }
    
    if (!$stmt_check->fetch()) {
        echo json_encode(['success' => false, 'message' => '无权修改此链接或链接不存在']);
        exit;
    }

    try {
        if ($is_admin) {
            // 管理员可以更新任何链接
            $stmt = $pdo->prepare("UPDATE links SET original_url = ?, remark = ?, max_clicks = ?, expire_at = ? WHERE id = ?");
            $success = $stmt->execute([$original_url, $remark, $max_clicks, $expire_at, $link_id]);
        } else {
            // 普通用户只能更新自己的链接
            $stmt = $pdo->prepare("UPDATE links SET original_url = ?, remark = ?, max_clicks = ?, expire_at = ? WHERE id = ? AND (user_id = ? OR user_code = ?)");
            $success = $stmt->execute([$original_url, $remark, $max_clicks, $expire_at, $link_id, $user_id, $user_code]);
        }

        if ($success) {
            echo json_encode(['success' => true, 'message' => '链接更新成功']);
        } else {
            echo json_encode(['success' => false, 'message' => '链接更新失败，请稍后再试']);
        }
    } catch (PDOException $e) {
        // Log error $e->getMessage();
        error_log('Update link error: ' . $e->getMessage()); // 添加错误日志
        echo json_encode(['success' => false, 'message' => '数据库错误，请联系管理员']);
    }
    exit;
}

// 处理管理员的更新请求
// 优先处理来自 admin_dashboard.php 的 AJAX 请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['admin_logged_in']) && isset($_POST['admin_action']) && $_POST['admin_action'] === 'true') {
    if (!isset($_POST['link_id']) || !isset($_POST['source_table']) || !isset($_POST['original_url'])) {
        echo json_encode(['success' => false, 'message' => '管理员更新：缺少必要的参数']);
        exit;
    }

    $link_id = intval($_POST['link_id']);
    $source_table = $_POST['source_table'];
    $original_url = trim($_POST['original_url']);
    $admin_remark = isset($_POST['remark']) ? trim($_POST['remark']) : null;
    $admin_max_clicks = isset($_POST['max_clicks']) && $_POST['max_clicks'] !== '' ? intval($_POST['max_clicks']) : null;
    $admin_expire_at = isset($_POST['expire_at']) && !empty($_POST['expire_at']) ? date('Y-m-d H:i:s', strtotime($_POST['expire_at'])) : null;

    if (empty($original_url) || !filter_var($original_url, FILTER_VALIDATE_URL)) {
        echo json_encode(['success' => false, 'message' => '原始链接格式不正确']);
        exit;
    }

    try {
        $update_fields = ['original_url' => $original_url];
        $sql_set_parts = ['original_url = :original_url'];

        // 明确处理每个可选字段
        $update_fields['remark'] = $admin_remark;
        $sql_set_parts[] = 'remark = :remark';
        
        $update_fields['max_clicks'] = $admin_max_clicks;
        $sql_set_parts[] = 'max_clicks = :max_clicks';

        $update_fields['expire_at'] = $admin_expire_at;
        $sql_set_parts[] = 'expire_at = :expire_at';
        
        $update_fields['id'] = $link_id;
        $sql_set_string = implode(', ', $sql_set_parts);

        $table_to_update = '';
        if ($source_table === 'links') {
            $table_to_update = 'links';
        } else if ($source_table === 'urls') { 
            $table_to_update = 'urls';
        } else {
            echo json_encode(['success' => false, 'message' => '无效的表来源']);
            exit;
        }
        
        // 确保 urls 表也有这些字段，如果 urls 表结构不同，需要分别处理
        // 假设 urls 表也有 remark, max_clicks, expire_at
        $stmt = $pdo->prepare("UPDATE {$table_to_update} SET {$sql_set_string} WHERE id = :id");
        
        if ($stmt && $stmt->execute($update_fields)) {
            echo json_encode(['success' => true, 'message' => '链接更新成功']);
        } else {
            echo json_encode(['success' => false, 'message' => '链接更新失败，请稍后再试']);
        }
    } catch (PDOException $e) {
        error_log('Admin AJAX update link error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => '数据库错误，请联系管理员']);
    }
    exit;
}
// 处理传统的管理员表单提交更新请求 (作为后备)
else if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['admin_logged_in']) && isset($_POST['link_id']) && isset($_POST['source_table']) && isset($_POST['original_url'])) {
    $link_id = intval($_POST['link_id']);
    $source_table = $_POST['source_table'];
    $original_url = trim($_POST['original_url']);
    $admin_remark = isset($_POST['remark']) ? trim($_POST['remark']) : null; // 管理员也可能需要修改备注等
    $admin_max_clicks = isset($_POST['max_clicks']) && $_POST['max_clicks'] !== '' ? intval($_POST['max_clicks']) : null;
    $admin_expire_at = isset($_POST['expire_at']) && !empty($_POST['expire_at']) ? date('Y-m-d H:i:s', strtotime($_POST['expire_at'])) : null;

    if (!filter_var($original_url, FILTER_VALIDATE_URL)) {
        header("Location: admin_dashboard.php?error=" . urlencode('请输入有效的URL地址'));
        exit;
    }

    try {
        $update_fields = ['original_url' => $original_url];
        $sql_set_parts = ['original_url = :original_url'];
        
        if (isset($_POST['remark'])) { // 允许管理员更新备注
            $update_fields['remark'] = $admin_remark;
            $sql_set_parts[] = 'remark = :remark';
        }
        if (isset($_POST['max_clicks'])) { // 允许管理员更新最大点击
            $update_fields['max_clicks'] = $admin_max_clicks;
            $sql_set_parts[] = 'max_clicks = :max_clicks';
        }
        if (isset($_POST['expire_at'])) { // 允许管理员更新过期时间
            $update_fields['expire_at'] = $admin_expire_at;
            $sql_set_parts[] = 'expire_at = :expire_at';
        }
        
        $update_fields['id'] = $link_id;
        $sql_set_string = implode(', ', $sql_set_parts);

        if ($source_table === 'links') {
            $stmt = $pdo->prepare("UPDATE links SET $sql_set_string WHERE id = :id");
        } else if ($source_table === 'urls') { // 假设urls表也有类似字段
            $stmt = $pdo->prepare("UPDATE urls SET $sql_set_string WHERE id = :id");
        } else {
            header("Location: admin_dashboard.php?error=" . urlencode('无效的表来源'));
            exit;
        }
        
        if ($stmt && $stmt->execute($update_fields)) {
            $redirect_url = "admin_dashboard.php?updated=1";
            // 保留原有重定向参数
            if (!empty($_POST['user_code'])) $redirect_url .= "&user_code=" . urlencode($_POST['user_code']);
            if (!empty($_POST['short_code'])) $redirect_url .= "&short_code=" . urlencode($_POST['short_code']);
            if (!empty($_POST['page'])) $redirect_url .= "&page=" . intval($_POST['page']);
            header("Location: $redirect_url");
            exit;
        } else {
            header("Location: admin_dashboard.php?error=" . urlencode('更新链接失败，请重试'));
            exit;
        }
    } catch (PDOException $e) {
        error_log('Admin update link error: ' . $e->getMessage()); // 添加错误日志
        header("Location: admin_dashboard.php?error=" . urlencode('数据库错误：' . $e->getMessage()));
        exit;
    }
}

// 如果没有匹配的action，则返回错误
if (!headers_sent()) { // 避免在已发送header后再次发送
    echo json_encode(['success' => false, 'message' => '无效的请求或操作']);
}

?>