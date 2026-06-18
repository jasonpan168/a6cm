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

$message = "";

// 生成CSRF令牌
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 处理重新发送验证邮件的AJAX请求
if (isset($_POST['ajax_resend_verification']) && isset($_SESSION['unverified_email'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];

    // 检查今日发送次数
    $today = date('Y-m-d');
    if (!isset($_SESSION['verification_sends'][$today])) {
        $_SESSION['verification_sends'][$today] = 0;
    }
    
    if ($_SESSION['verification_sends'][$today] >= 3) {
        $response['message'] = '⚠️ 今日重发次数已达上限（3次），请明天再试！';
    } else {
        // 生成新的验证码
        $verification_code = bin2hex(random_bytes(16));
        
        // 更新验证码
        $stmt = $pdo->prepare("UPDATE users SET verification_code = ? WHERE email = ?");
        if ($stmt->execute([$verification_code, $_SESSION['unverified_email']])) {
            // 发送验证邮件
            require_once 'verify_email.php';
            sendVerificationEmail($_SESSION['unverified_email'], $verification_code);
            $_SESSION['verification_sends'][$today]++;
            $response['success'] = true;
            $response['message'] = '✅ 验证邮件已发送！请注意：部分不常见或不主流邮箱可能无法收到或被拦截，如未收到邮件请更换主流邮箱！';
        } else {
            $response['message'] = '⚠️ 系统错误，请稍后重试！';
        }
    }
    
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 验证CSRF令牌
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('非法请求！');
    }
    
    $username = trim(htmlspecialchars($_POST['username'], ENT_QUOTES, 'UTF-8'));
    $password = trim(htmlspecialchars($_POST['password'], ENT_QUOTES, 'UTF-8'));
    
    // 防止暴力破解
    if (isset($_SESSION['login_attempts']) && $_SESSION['login_attempts'] >= 5 && 
        time() - $_SESSION['last_attempt'] < 1800) { // 30分钟锁定
        $message = "<p class='error'>⚠️ 登录尝试次数过多，请30分钟后再试！</p>";
    } else {
        // 查询用户
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // 验证密码和邮箱验证状态
        if ($user && password_verify($password, $user['password'])) {
            if ($user['email_verified'] == 0) {
                // 存储未验证的邮箱地址用于重发验证邮件
                $_SESSION['unverified_email'] = $user['email'];
                
                $resendButton = "<button onclick='resendVerificationEmail()' class='resend-btn' id='resendBtn'>重新发送验证邮件</button>";
                
                $message = "<div class='warning'>
                    <p>⚠️ 您的账号尚未完成邮箱验证，请先完成以下步骤：</p>
                    <ol style='margin: 10px 0; padding-left: 20px;'>
                        <li>1. 检查您的邮箱 ({$user['email']}) 收件箱</li>
                        <li>2. 如未收到，请检查垃圾邮件文件夹</li>
                        <li>3. 仍未找到？点击下方按钮重新发送验证邮件</li>
                    </ol>
                    {$resendButton}
                </div>";
            } else {
                // 登录成功，重置登录尝试次数
                unset($_SESSION['login_attempts']);
                unset($_SESSION['last_attempt']);
                
                // 更新会话ID防止会话固定攻击
                session_regenerate_id(true);
                
                // 设置会话数据
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_code'] = $user['user_code'];
                $_SESSION['last_activity'] = time();
                
                // 设置安全的会话cookie
                session_set_cookie_params([
                    'lifetime' => 3600,
                    'path' => '/',
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'Strict'
                ]);
                
                header("Location: index.php");
                exit;
            }
        } else {
            // 记录失败的登录尝试
            $_SESSION['login_attempts'] = isset($_SESSION['login_attempts']) ? $_SESSION['login_attempts'] + 1 : 1;
            $_SESSION['last_attempt'] = time();
            $message = "<p class='error'>⚠️ 用户名或密码错误！</p>";
        }
    }
}

// 处理session中的消息
if (isset($_SESSION['login_message'])) {
    $msg = $_SESSION['login_message'];
    $message = "<p class='{$msg['type']}'>". htmlspecialchars($msg['text']) . "</p>";
    unset($_SESSION['login_message']);
}

// 登录成功提示
if (isset($_GET['logout']) && $_GET['logout'] == 1) {
    $message = "<p class='success'>✅ 您已成功退出登录！</p>";
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户登录 - A6.cm短网址</title>
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
            max-width: 500px;
            margin: 50px auto;
            padding: 2rem;
            background-color: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .form-title {
            text-align: center;
            margin-bottom: 1.5rem;
            color: var(--primary-color);
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
            margin-top: 1rem;
        }
        
        .btn:hover {
            background-color: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .register-link {
            text-align: center;
            margin-top: 1.5rem;
        }
        
        .register-link a {
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .register-link a:hover {
            text-decoration: underline;
        }
        
        .error, .warning, .info {
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1.5rem;
        }

        .error {
            color: var(--error-color);
            background-color: rgba(231, 76, 60, 0.1);
        }

        .warning {
            color: #f39c12;
            background-color: rgba(243, 156, 18, 0.1);
        }

        .info {
            color: var(--primary-color);
            background-color: rgba(52, 152, 219, 0.1);
        }

        .resend-link {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .resend-link:hover {
            text-decoration: underline;
            opacity: 0.8;
        }
        
        .resend-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .resend-btn:hover {
            background-color: var(--primary-hover);
            transform: translateY(-1px);
        }
        
        .resend-btn:disabled {
            background-color: #95a5a6;
            cursor: not-allowed;
            transform: none;
        }
        
        .message-container {
            margin-bottom: 1rem;
        }
        
        .warning ol {
            margin: 10px 0;
            color: #d35400;
        }
        
        .warning li {
            margin: 5px 0;
        }
        
        .success {
            color: var(--success-color);
            padding: 1rem;
            border-radius: 8px;
            background-color: rgba(46, 204, 113, 0.1);
            margin-top: 1.5rem;
        }
        
        .footer {
            text-align: center;
            padding: 2rem 1rem;
            margin-top: auto;
            color: var(--light-text);
            font-size: 0.9rem;
        }
    </style>
    <script>
    function resendVerificationEmail() {
        const btn = document.getElementById('resendBtn');
        const messageDiv = document.getElementById('message');
        btn.disabled = true;
        
        fetch('login.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'ajax_resend_verification=1&csrf_token=<?php echo $_SESSION["csrf_token"]; ?>'
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data && typeof data.success !== 'undefined') {
                messageDiv.innerHTML = `<p class='${data.success ? "success" : "error"}'>${data.message}</p>`;
                if (data.success) {
                    btn.disabled = true;
                } else {
                    btn.disabled = false;
                }
            } else {
                throw new Error('Invalid response format');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            messageDiv.innerHTML = '<p class="error">⚠️ 发送请求失败，请稍后重试！</p>';
            btn.disabled = false;
        });
    }
    </script>
</head>
<body>
    <div class="header">
        <h1>🔗 A6.cm短网址生成器</h1>
        <p>简单、高效、安全的链接缩短服务</p>
    </div>

    <div class="container">
        <div id="message" class="message-container"></div>
        <h2 class="form-title">用户登录</h2>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <div class="input-container" data-icon="👤">
                <input type="text" name="username" placeholder="用户名" required>
            </div>
            
            <div class="input-container" data-icon="🔒">
                <input type="password" name="password" placeholder="密码" required>
            </div>
            
            <button type="submit" class="btn">🔑 登录</button>
        </form>

        <?php if ($message) echo $message; ?>
        
        <div class="register-link">
            还没有账号？<a href="register.php">立即注册</a>
        </div>
    </div>

    <div class="footer">
        <p>© <?php echo date('Y'); ?> A6.cm短网址服务 - 让链接分享更简单</p>
    </div>
</body>
</html>