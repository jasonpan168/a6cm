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

// 检查是否提供了短链接
if (!isset($_GET['url'])) {
    die('未提供短链接');
}

$shortUrl = $_GET['url'];

// 构建完整的短链接URL
$fullUrl = rtrim(BASE_URL, '/') . '/' . $shortUrl;

// 构建二维码API URL
$qrCodeUrl = QR_CODE_API . urlencode($fullUrl);

// 设置响应头
header('Content-Type: image/png');

// 获取二维码图片内容并输出
$qrCode = file_get_contents($qrCodeUrl);
echo $qrCode;