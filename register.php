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

// 生成CSRF令牌
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = "";

// 处理session中的消息
if (isset($_SESSION['register_message'])) {
    $msg = $_SESSION['register_message'];
    $message = "<div class='alert alert-{$msg['type']}'>{$msg['text']}</div>";
    unset($_SESSION['register_message']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 防止CSRF攻击
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('非法请求！');
    }
    
    // 重新生成CSRF令牌，防止表单重复提交
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    
    $username = htmlspecialchars(trim($_POST['username'] ?? ''), ENT_QUOTES, 'UTF-8');
    $password = htmlspecialchars(trim($_POST['password'] ?? ''), ENT_QUOTES, 'UTF-8');
    $confirm_password = htmlspecialchars(trim($_POST['confirm_password'] ?? ''), ENT_QUOTES, 'UTF-8');
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    
    // 验证输入
    if (empty($username) || empty($password) || empty($confirm_password) || empty($email)) {
        $_SESSION['register_message'] = array(
            'type' => 'error',
            'text' => '所有字段都必须填写！'
        );
        header('Location: register.php');
        exit;
    } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['register_message'] = array(
            'type' => 'error',
            'text' => '请输入有效的邮箱地址！'
        );
        header('Location: register.php');
        exit;
    } else if (strlen($password) < 8) {
        $_SESSION['register_message'] = array(
            'type' => 'error',
            'text' => '密码长度必须至少为8个字符！'
        );
        header('Location: register.php');
        exit;
    } else if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $_SESSION['register_message'] = array(
            'type' => 'error',
            'text' => '密码必须包含大小写字母和数字！'
        );
        header('Location: register.php');
        exit;
    } else if ($password !== $confirm_password) {
        $_SESSION['register_message'] = array(
            'type' => 'error',
            'text' => '两次输入的密码不一致！'
        );
        header('Location: register.php');
        exit;
    } else {
        // 检查用户名是否已存在
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->rowCount() > 0) {
            $_SESSION['register_message'] = array(
                'type' => 'error',
                'text' => '该用户名已被注册！'
            );
            header('Location: register.php');
            exit;
        } else {
            // 检查邮箱是否已存在
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->rowCount() > 0) {
                $_SESSION['register_message'] = array(
                    'type' => 'error',
                    'text' => '该邮箱已被注册！'
                );
                header('Location: register.php');
                exit;
            } else {
                // 生成唯一的用户编码
                do {
                    $user_code = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_code = ?");
                    $stmt->execute([$user_code]);
                } while ($stmt->rowCount() > 0);
                
                // 密码加密
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // 生成验证码
                $verification_code = bin2hex(random_bytes(16));
                
                // 插入新用户
                $stmt = $pdo->prepare("INSERT INTO users (username, password, email, user_code, verification_code, email_verified) VALUES (?, ?, ?, ?, ?, 0)");
                if ($stmt->execute([$username, $hashed_password, $email, $user_code, $verification_code])) {
                    // 发送验证邮件
                    require_once 'verify_email.php';
                    if (sendVerificationEmail($email, $verification_code)) {
                        $_SESSION['register_message'] = array(
                            'type' => 'success',
                            'text' => '注册成功！请查收验证邮件完成注册。'
                        );
                        header('Location: register.php');
                        exit;
                    } else {
                        $_SESSION['register_message'] = array(
                            'type' => 'warning',
                            'text' => '注册成功，但发送验证邮件失败，请稍后在登录页面重新发送验证邮件。'
                        );
                        header('Location: register.php');
                        exit;
                    }
                } else {
                    $_SESSION['register_message'] = array(
                        'type' => 'error',
                        'text' => '注册失败，请稍后重试！'
                    );
                    header('Location: register.php');
                    exit;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户注册 - A6.cm短网址</title>
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
            --success-bg: #d4edda;
            --success-border: #c3e6cb;
            --success-text: #155724;
            --warning-bg: #fff3cd;
            --warning-border: #ffeeba;
            --warning-text: #856404;
            --error-bg: #f8d7da;
            --error-border: #f5c6cb;
            --error-text: #721c24;
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
        
        .login-link {
            text-align: center;
            margin-top: 1.5rem;
        }
        
        .login-link a {
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .alert {
            padding: 1rem;
            margin: 1rem 0;
            border: 1px solid transparent;
            border-radius: 8px;
            text-align: center;
            font-weight: 500;
            animation: fadeIn 0.5s ease-in-out;
        }

        .alert-error {
            color: var(--error-text);
            background-color: var(--error-bg);
            border-color: var(--error-border);
        }

        .alert-success {
            color: var(--success-text);
            background-color: var(--success-bg);
            border-color: var(--success-border);
        }

        .alert-warning {
            color: var(--warning-text);
            background-color: var(--warning-bg);
            border-color: var(--warning-border);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .loading {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .footer {
            text-align: center;
            padding: 2rem 1rem;
            margin-top: auto;
            color: var(--light-text);
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="loading">
        <div class="loading-spinner"></div>
    </div>
    <div class="header">
        <h1>🔗 A6.cm短网址生成器</h1>
        <p>简单、高效、安全的链接缩短服务</p>
    </div>

    <div class="container">
        <h2 class="form-title">用户注册</h2>
        <form method="POST" id="registerForm" onsubmit="return handleSubmit(event)">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <div class="input-container" data-icon="👤">
                <input type="text" name="username" placeholder="用户名" required>
            </div>
            
            <div class="input-container" data-icon="📧">
                <input type="email" name="email" placeholder="电子邮箱" required>
            </div>
            
            <div class="input-container" data-icon="🔒">
                <input type="password" name="password" placeholder="密码" required>
            </div>
            
            <div class="input-container" data-icon="🔐">
                <input type="password" name="confirm_password" placeholder="确认密码" required>
            </div>
            
            <button type="submit" class="btn">📝 注册账号</button>
        </form>

        <?php if ($message) echo $message; ?>
        
        <div class="login-link">
            已有账号？<a href="login.php">立即登录</a>
        </div>
    </div>

    <div class="footer">
        <p>© <?php echo date('Y'); ?> A6.cm短网址服务 - 让链接分享更简单</p>
    </div>

    <script>
    function handleSubmit(event) {
        event.preventDefault();
        
        // 表单验证
        const form = event.target;
        const inputs = form.querySelectorAll('input[required]');
        for (let input of inputs) {
            if (!input.value.trim()) {
                return true; // 让浏览器处理必填字段的验证
            }
        }
        
        // 显示加载动画
        document.querySelector('.loading').style.display = 'flex';
        
        // 禁用提交按钮并更改文字
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.style.opacity = '0.7';
        submitBtn.innerHTML = '📝 注册中...';
        
        // 提交表单
        form.submit();
        return false;
    }
    </script>
</body>
</html>