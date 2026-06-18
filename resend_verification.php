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
require_once 'verify_email.php';

$message = "";

// 获取邮箱参数
$email = filter_input(INPUT_GET, 'email', FILTER_SANITIZE_EMAIL);

if ($email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "<p class='error'>⚠️ 无效的邮箱地址！</p>";
    } else {
        // 查询用户信息
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $message = "<p class='error'>⚠️ 未找到该邮箱对应的用户！</p>";
        } else if ($user['email_verified'] == 1) {
            $message = "<p class='info'>✅ 该邮箱已经验证，无需重新发送验证邮件。</p>";
        } else {
            // 生成新的验证码并发送邮件
            $verification_code = bin2hex(random_bytes(16));
            
            // 更新验证码
            $stmt = $pdo->prepare("UPDATE users SET verification_code = ? WHERE email = ?");
            if ($stmt->execute([$verification_code, $email])) {
                // 发送验证邮件
                if (sendVerificationEmail($email, $verification_code)) {
                    $message = "<p class='success'>✅ 验证邮件已重新发送，请查收！</p>";
                } else {
                    $message = "<p class='error'>⚠️ 验证邮件发送失败，请稍后再试！</p>";
                }
            } else {
                $message = "<p class='error'>⚠️ 系统错误，请稍后再试！</p>";
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
    <title>重新发送验证邮件 - A6.cm短网址</title>
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
            padding: 2rem 0;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }
        
        .header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 60%);
            animation: rotate 20s linear infinite;
        }
        
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.8rem;
            position: relative;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }
        
        .header p {
            font-size: 1.2rem;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
            position: relative;
        }
        
        .container {
            width: 90%;
            max-width: 500px;
            margin: 50px auto;
            padding: 2.5rem;
            background-color: var(--card-bg);
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            text-align: center;
            transform: translateY(0);
            transition: transform 0.3s ease;
        }
        
        .container:hover {
            transform: translateY(-5px);
        }
        
        .form-title {
            font-size: 1.8rem;
            color: var(--primary-color);
            margin-bottom: 2rem;
            position: relative;
            display: inline-block;
        }
        
        .form-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 50px;
            height: 3px;
            background: linear-gradient(to right, var(--primary-color), var(--primary-hover));
            border-radius: 3px;
        }
        
        .input-container {
            position: relative;
            margin-bottom: 1.5rem;
        }
        
        .input-container input {
            width: 100%;
            padding: 1rem 1.5rem 1rem 3rem;
            font-size: 1rem;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            transition: all 0.3s ease;
            background-color: #f8f9fa;
        }
        
        .input-container input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        .input-container::before {
            content: attr(data-icon);
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1.2rem;
            opacity: 0.7;
            transition: all 0.3s ease;
        }
        
        .input-container:focus-within::before {
            opacity: 1;
            color: var(--primary-color);
        }
        
        .success, .error, .info {
            padding: 1.2rem;
            border-radius: 12px;
            margin: 1.5rem 0;
            font-weight: 500;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .success {
            color: var(--success-color);
            background-color: rgba(46, 204, 113, 0.1);
            border: 1px solid rgba(46, 204, 113, 0.2);
        }
        
        .error {
            color: var(--error-color);
            background-color: rgba(231, 76, 60, 0.1);
            border: 1px solid rgba(231, 76, 60, 0.2);
        }
        
        .info {
            color: var(--primary-color);
            background-color: rgba(52, 152, 219, 0.1);
            border: 1px solid rgba(52, 152, 219, 0.2);
        }
        
        .btn {
            display: inline-block;
            padding: 1rem 2rem;
            font-size: 1.1rem;
            font-weight: 600;
            color: white;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
            border: none;
            border-radius: 12px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            margin-top: 1.5rem;
            position: relative;
            overflow: hidden;
        }
        
        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: 0.5s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(52, 152, 219, 0.2);
        }
        
        .btn:hover::before {
            left: 100%;
        }
        
        .back-link {
            margin-top: 2rem;
        }
        
        .back-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .back-link a::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 2px;
            background-color: var(--primary-color);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }
        
        .back-link a:hover::after {
            transform: scaleX(1);
        }
        
        .footer {
            text-align: center;
            padding: 2rem 1rem;
            margin-top: auto;
            color: var(--light-text);
            font-size: 0.9rem;
            background: linear-gradient(to top, rgba(52, 152, 219, 0.05), transparent);
        }
        
        @media (max-width: 768px) {
            .header h1 {
                font-size: 2rem;
            }
            
            .header p {
                font-size: 1rem;
            }
            
            .container {
                padding: 1.5rem;
            }
            
            .form-title {
                font-size: 1.5rem;
            }
            
            .btn {
                padding: 0.8rem 1.5rem;
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔗 A6.cm短网址生成器</h1>
        <p>简单、高效、安全的链接缩短服务</p>
    </div>

    <div class="container">
        <h2 class="form-title">重新发送验证邮件</h2>
        <?php if (!$email): ?>
        <form method="GET">
            <div class="input-container" data-icon="📧">
                <input type="email" name="email" placeholder="请输入您的邮箱地址" required>
            </div>
            
            <button type="submit" class="btn">📨 发送验证邮件</button>
        </form>
        <?php endif; ?>

        <?php echo $message; ?>
        
        <div class="back-link">
            <a href="login.php">返回登录页面</a>
        </div>
    </div>

    <div class="footer">
        <p>© <?php echo date('Y'); ?> A6.cm短网址服务 - 让链接分享更简单</p>
    </div>
</body>
</html>