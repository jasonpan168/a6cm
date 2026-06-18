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
// 加固会话 Cookie（Secure, HttpOnly, SameSite=Lax）
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}
session_start();
include 'config.php';

// CSRF Token 初始化（每30分钟轮换一次）
if (empty($_SESSION['csrf_token']) || time() - ($_SESSION['csrf_token_time'] ?? 0) > 1800) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_token_time'] = time();
}

// 生成随机短链接
function generateRandomCode($length = 6) {
    return substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, $length);
}

$message = "";
$user_logged_in = isset($_SESSION['user_id']);
$user_code = $user_logged_in ? $_SESSION['user_code'] : '';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 检查用户是否登录
    if (!$user_logged_in) {
        header('Location: login.php?redirect=index.php');
        exit;
    }

    // CSRF 验证
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        header("Location: " . $_SERVER['PHP_SELF'] . "?error=csrf");
        exit;
    }

    $original_url = trim($_POST['url']);
    $custom_code = trim($_POST['custom_code']);
    $expire_days = isset($_POST['expire_days']) ? intval($_POST['expire_days']) : null;
    $max_clicks = isset($_POST['max_clicks']) ? intval($_POST['max_clicks']) : null;

    // 获取用户信息和链接限制
    $stmt = $pdo->prepare("SELECT link_limit, is_premium, premium_expiry FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_info = $stmt->fetch();

    // 检查用户是否是高级用户
    $is_premium = $user_info['is_premium'] && ($user_info['premium_expiry'] === null || strtotime($user_info['premium_expiry']) > time());

    // 每分钟创建次数限制：免费用户默认5次，付费用户60次
    $window_seconds = 60;
    $max_creations = $is_premium ? 60 : 5;
    if (!isset($_SESSION['create_window_start']) || time() - $_SESSION['create_window_start'] >= $window_seconds) {
        $_SESSION['create_window_start'] = time();
        $_SESSION['create_count'] = 0;
    }
    if (($_SESSION['create_count'] ?? 0) >= $max_creations) {
        header("Location: " . $_SERVER['PHP_SELF'] . "?error=rate_limit");
        exit;
    }
    $_SESSION['create_count'] = ($_SESSION['create_count'] ?? 0) + 1;

    if (!$is_premium) {
        // 获取用户已创建的链接数量
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM links WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $link_count = $stmt->fetchColumn();

        if ($link_count >= $user_info['link_limit']) {
            $message = "<p class='error'>⚠️ 您已达到免费链接创建限制！升级到高级账户可无限制创建链接。<br>如需提升额度，请联系站点管理员。</p>";
            header("Location: " . $_SERVER['PHP_SELF'] . "?error=limit_reached");
            exit;
        }
    }

    // 使用用户的编码和ID
    $user_code = $_SESSION['user_code'];
    $user_id = $_SESSION['user_id'];

    $expire_at = $expire_days ? date('Y-m-d H:i:s', strtotime("+$expire_days days")) : null;

    if ($custom_code) {
        $stmt = $pdo->prepare("SELECT * FROM links WHERE short_code = ?");
        $stmt->execute([$custom_code]);
        if ($stmt->rowCount() > 0) {
            header("Location: " . $_SERVER['PHP_SELF'] . "?error=exists");
            exit;
        }
        $short_code = $custom_code;
    } else {
        do {
            $short_code = generateRandomCode();
            $stmt = $pdo->prepare("SELECT * FROM links WHERE short_code = ?");
            $stmt->execute([$short_code]);
        } while ($stmt->rowCount() > 0);
    }

    $remark = isset($_POST['remark']) ? trim($_POST['remark']) : null;

    $stmt = $pdo->prepare("INSERT INTO links (original_url, short_code, expire_at, max_clicks, user_code, user_id, click_count, remark) VALUES (?, ?, ?, ?, ?, ?, 0, ?)");
    $stmt->execute([$original_url, $short_code, $expire_at, $max_clicks, $user_code, $user_id, $remark]);

    $short_url = "https://" . $_SERVER['HTTP_HOST'] . "/" . $short_code;

    // 使用会话闪存传递成功信息，避免在URL中暴露生成结果
    $_SESSION['flash_success_url'] = $short_url;
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// 错误提示
if (isset($_GET['error'])) {
    switch($_GET['error']) {
        case 'exists':
            $message = "<p class='error'>⚠️ 该自定义短链接已被占用，请更换！</p>";
            break;
        case 'limit_reached':
            $message = "<p class='error'>⚠️ 您已达到免费链接创建限制！升级到高级账户可无限制创建链接。<br>如需提升额度，请联系站点管理员。</p>";
            break;
        case 'rate_limit':
            $message = "<p class='error'>⚠️ 创建过于频繁，请稍后再试（免费用户每分钟最多5次）。</p>";
            break;
        case 'csrf':
            $message = "<p class='error'>⚠️ 请求校验失败，请刷新页面后重试。</p>";
            break;
    }
}

// 成功提示（仅当会话闪存存在时显示）
if (isset($_SESSION['flash_success_url'])) {
    $short_url = $_SESSION['flash_success_url'];
    unset($_SESSION['flash_success_url']);
    $message = "<p class='success'>🎉 短链接已生成: 
                <input type='text' id='shortLink' value='$short_url' readonly>
                <button onclick='copyLink()' class='copy-btn'>📋 复制</button></p>";
}

// 注册成功提示
if (isset($_GET['register_success'])) {
    $message .= "<p class='success'>✅ 注册成功！您已自动登录。</p>";
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>A6.cm短网址生成器 - 简单高效的链接缩短服务</title>
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
            width: 90%;
            max-width: 600px;
            margin: 50px auto;
            padding: 2rem;
            background-color: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            position: relative;
            z-index: 10;
        }
        
        .form-group {
            margin-bottom: 1.2rem;
        }
        
        .input-container {
            position: relative;
            margin-bottom: 1.2rem;
        }
        
        .input-container input {
            width: 100%;
            padding: 0.9rem 1rem 0.9rem 3rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .input-container input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
            outline: none;
        }
        
        .input-container::before {
            content: attr(data-icon);
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1.2rem;
            color: var(--light-text);
            pointer-events: none;
        }
        
        .btn {
            width: 100%;
            padding: 1rem;
            font-size: 1.1rem;
            font-weight: 600;
            color: white;
            background-color: var(--primary-color);
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
        }
        
        .nav-buttons {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .nav-btn {
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
        
        .nav-btn:hover {
            background-color: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .nav-btn-outline {
            background-color: transparent;
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
        }
        
        .nav-btn-outline:hover {
            background-color: rgba(52, 152, 219, 0.1);
        }
        
        .btn:hover {
            background-color: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .success {
            color: var(--success-color);
            padding: 1rem;
            border-radius: 8px;
            background-color: rgba(46, 204, 113, 0.1);
            margin-top: 1.5rem;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .error {
            color: var(--error-color);
            padding: 1rem;
            border-radius: 8px;
            background-color: rgba(231, 76, 60, 0.1);
            margin-top: 1.5rem;
        }
        
        .success input {
            flex: 1;
            min-width: 200px;
            padding: 0.7rem;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            font-size: 1rem;
            background-color: #f8f9fa;
        }
        
        .copy-btn {
            padding: 0.7rem 1rem;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .copy-btn:hover {
            background-color: var(--primary-hover);
        }
        
        .advanced-options {
            margin-top: 1.5rem;
            border-top: 1px solid var(--border-color);
            padding-top: 1.5rem;
        }
        
        .advanced-toggle {
            background: none;
            border: none;
            color: var(--primary-color);
            font-size: 1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            margin: 0 auto 1rem;
        }
        
        .advanced-toggle:hover {
            text-decoration: underline;
        }
        
        .advanced-fields {
            display: none;
        }
        
        .advanced-fields.show {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        .footer {
            text-align: center;
            padding: 2rem 1rem;
            margin-top: auto;
            color: var(--light-text);
            font-size: 0.9rem;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @media (max-width: 768px) {
            .container {
                width: 95%;
                padding: 1.5rem;
            }
            
            .header h1 {
                font-size: 1.8rem;
            }
            
            .header p {
                font-size: 1rem;
            }
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>🔗 A6.cm短网址生成器</h1>
        <p>简单、高效、安全的链接缩短服务</p>
        <div class="nav-buttons">
            <?php if ($user_logged_in): ?>
                <a href="user_dashboard.php" class="nav-btn">👤 我的短链接</a>
                <a href="logout.php" class="nav-btn nav-btn-outline">🚪 退出登录</a>
            <?php else: ?>
                <a href="login.php" class="nav-btn">🔑 登录</a>
                <a href="register.php" class="nav-btn nav-btn-outline">📝 注册</a>
                <a href="about.php" class="nav-btn nav-btn-outline">ℹ️ 关于我们</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="container">
        <form method="POST">
            <div class="input-container" data-icon="🌐">
                <input type="url" name="url" placeholder="请输入需要缩短的网址" required>
            </div>
            
            <button type="button" class="advanced-toggle" id="advancedToggle">⚙️ 高级选项</button>
            
            <div class="advanced-fields" id="advancedFields">
                <div class="input-container" data-icon="🔤">
                    <input type="text" name="custom_code" placeholder="自定义短链接（可选）">
                </div>
                
                <div class="input-container" data-icon="🆔">
                    <input type="text" name="user_code" placeholder="用户编码（可选）" <?php if ($user_logged_in) echo 'value="'.$user_code.'" readonly'; ?>>
                </div>
                
                <div class="input-container" data-icon="⏳">
                    <input type="number" name="expire_days" placeholder="有效天数（可选）">
                </div>
                
                <div class="input-container" data-icon="📊">
                    <input type="number" name="max_clicks" placeholder="最大点击次数（可选）">
                </div>

                <div class="input-container" data-icon="📝">
                    <input type="text" name="remark" placeholder="链接备注（可选）">
                </div>
            </div>
            
            <!-- CSRF 隐藏字段 -->
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

            <button type="submit" class="btn">🚀 立即生成短链接</button>
        </form>

        <?php if ($message) echo $message; ?>
    </div>

    <div class="footer">
        <p>© <?php echo date('Y'); ?> A6.cm短网址服务 - 让链接分享更简单</p>
    </div>

    <script>
        // 高级选项切换
        document.getElementById('advancedToggle').addEventListener('click', function() {
            const advancedFields = document.getElementById('advancedFields');
            advancedFields.classList.toggle('show');
            this.textContent = advancedFields.classList.contains('show') ? '⚙️ 收起高级选项' : '⚙️ 高级选项';
        });
        
        // 复制链接功能
        function copyLink() {
            const copyText = document.getElementById("shortLink");
            copyText.select();
            document.execCommand("copy");
            
            // 显示复制成功提示
            const copyBtn = document.querySelector('.copy-btn');
            const originalText = copyBtn.textContent;
            copyBtn.textContent = '✅ 已复制';
            
            setTimeout(function() {
                copyBtn.textContent = originalText;
            }, 2000);
        }
    </script>
</body>
</html>