<?php
/**
 * A6.cm 短网址服务  https://www.a6.cm
 *
 * @author    AJIE  https://github.com/jasonpan168/a6cm
 * @copyright Copyright (c) 2026 AJIE
 * @license   AGPL-3.0-or-later  （详见项目根目录 LICENSE 与 LICENSE.md）
 *
 * 本程序是自由软件：你可在自由软件基金会发布的 GNU AGPL v3 条款下
 * 重新分发和/或修改它。本程序按"现状"分发，不附带任何担保。
 * 如需闭源商用（不公开源码），请通过项目仓库 https://github.com/jasonpan168/a6cm 提交 Issue 获取商业授权。
 */
require_once __DIR__ . '/config.php';
// 会话 Cookie 参数（secure/httponly/SameSite）必须在 session_start() 之前设置，
// 统一走 a6_session_boot()。
a6_session_boot();

// 判断是管理员还是普通用户
$is_admin = isset($_SESSION['admin_logged_in']);

// 清除所有会话变量
$_SESSION = array();

// 如果要彻底销毁会话，还需要删除会话cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 销毁会话
session_destroy();

// 根据用户类型重定向到不同页面
if ($is_admin) {
    header('Location: admin_login.php');
} else {
    header('Location: login.php?logout=1');
}
exit;
?>