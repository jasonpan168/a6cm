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
require 'config.php';
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

function sendVerificationEmail($email, $verificationCode) {
    $mail = new PHPMailer(true);
    
    try {
        // 服务器设置
        $mail->SMTPDebug = 0;
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';
        
        // 发件人设置
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($email);
        
        // 邮件内容
        $mail->isHTML(true);
        $mail->Subject = 'A6.cm短网址 - 邮箱验证';
        
        $verificationLink = BASE_URL . 'verify_email.php?code=' . $verificationCode . '&email=' . urlencode($email);
        
        $mail->Body = <<<HTML
        <div style="font-family: 'Microsoft YaHei', Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
            <h2 style="color: #3498db;">欢迎注册 A6.cm短网址</h2>
            <p>您好！</p>
            <p>感谢您注册 A6.cm短网址。请点击下面的链接验证您的邮箱：</p>
            <p style="margin: 20px 0;">
                <a href="{$verificationLink}" style="background-color: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">验证邮箱</a>
            </p>
            <p>或者复制以下链接到浏览器地址栏：</p>
            <p style="background-color: #f8f9fa; padding: 10px; border-radius: 5px;">{$verificationLink}</p>
            <p>如果这不是您的操作，请忽略此邮件。</p>
            <p style="color: #666; font-size: 12px; margin-top: 20px;">此邮件由系统自动发送，请勿回复。</p>
        </div>
HTML;
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("邮件发送失败: {$mail->ErrorInfo}");
        return false;
    }
}

// 处理验证请求
if (isset($_GET['code']) && isset($_GET['email'])) {
    $verificationCode = $_GET['code'];
    $email = $_GET['email'];
    
    // 验证邮箱和验证码
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND verification_code = ? AND email_verified = 0");
    $stmt->execute([$email, $verificationCode]);
    $user = $stmt->fetch();
    
    if ($user) {
        // 更新用户状态为已验证
        $stmt = $pdo->prepare("UPDATE users SET email_verified = 1, verification_code = NULL WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        // 设置成功消息
        $_SESSION['login_message'] = [
            'type' => 'success',
            'text' => '✅ 邮箱验证成功！您现在可以登录了。'
        ];
        header('Location: login.php');
        exit;
    } else {
        $_SESSION['login_message'] = [
            'type' => 'error',
            'text' => '⚠️ 无效的验证链接或邮箱已验证。'
        ];
        header('Location: login.php');
        exit;
    }
}

// 处理重新发送验证邮件的请求
if (isset($_POST['resend']) && isset($_POST['email'])) {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    
    // 检查用户是否存在且未验证
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND email_verified = 0");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user) {
        // 生成新的验证码
        $verificationCode = bin2hex(random_bytes(16));
        
        // 更新验证码
        $stmt = $pdo->prepare("UPDATE users SET verification_code = ? WHERE id = ?");
        $stmt->execute([$verificationCode, $user['id']]);
        
        // 重新发送验证邮件
        if (sendVerificationEmail($email, $verificationCode)) {
            $_SESSION['message'] = '验证邮件已重新发送，请查收。';
        } else {
            $_SESSION['error'] = '发送验证邮件失败，请稍后重试。';
        }
    } else {
        $_SESSION['error'] = '未找到需要验证的邮箱地址。';
    }
    
    header('Location: login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>邮箱验证 - A6.cm短网址</title>
    <style>
        body {
            font-family: 'Microsoft YaHei', Arial, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        
        .container {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            max-width: 500px;
            width: 100%;
            text-align: center;
        }
        
        h1 {
            color: #3498db;
            margin-bottom: 20px;
        }
        
        .message {
            margin: 20px 0;
            padding: 15px;
            border-radius: 5px;
        }
        
        .success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .error {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        form {
            margin-top: 20px;
        }
        
        input[type="email"] {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-sizing: border-box;
        }
        
        button {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        button:hover {
            background-color: #2980b9;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>邮箱验证</h1>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="message success">
                <?php 
                echo $_SESSION['message'];
                unset($_SESSION['message']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="message error">
                <?php 
                echo $_SESSION['error'];
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>
        
        <p>如果您没有收到验证邮件，可以在下面重新发送：</p>
        
        <form method="post">
            <input type="email" name="email" placeholder="请输入您的邮箱地址" required>
            <button type="submit" name="resend">重新发送验证邮件</button>
        </form>
    </div>
</body>
</html>