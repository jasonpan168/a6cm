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
include 'config.php';

/**
 * 输出错误：以 HTTP 状态码 + 纯文本返回，避免被当成损坏的 PNG。
 */
function qr_fail($status, $message)
{
    if (!headers_sent()) {
        header('Content-Type: text/plain; charset=utf-8', true, $status);
    }
    echo $message;
    exit;
}

// 校验短链接代码：与 .htaccess 的重写规则保持一致（字母数字），额外容忍 - 和 _。
// 这里必须校验，否则参数会被直接拼进 URL —— 过去的实现把它交给第三方 API，
// 等于把本站当成一个开放的任意 URL 代理。
if (!isset($_GET['url']) || !is_string($_GET['url'])) {
    qr_fail(400, '未提供短链接');
}

$shortUrl = trim($_GET['url']);

if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $shortUrl)) {
    qr_fail(400, '短链接代码不合法');
}

// 构建完整的短链接URL
$fullUrl = rtrim(BASE_URL, '/') . '/' . $shortUrl;

// 二维码内容不随时间变化，允许缓存，减轻重复生成的压力。
header('Cache-Control: public, max-age=86400');

// ---------------------------------------------------------------------------
// 默认：使用仓库内自带的 PHP QR Code 库在本地生成。
// ---------------------------------------------------------------------------
// 为什么不再默认走第三方 API：每生成一次二维码，就会把用户的完整短链接发给
// 第三方服务，等于泄露访问目标；而且该端点无鉴权无限流，一旦拼接外部地址，
// 本站就成了别人的开放代理。本地生成同时消除了这两个问题，也让 README 里
// 「二维码：PHP QR Code」的说明第一次成为事实。
//
// 仍然保留 QR_CODE_API 配置项作为可选回退：只有在 .env 里显式配置了它时才走远程。
$remoteApi = defined('QR_CODE_API') ? trim((string) QR_CODE_API) : '';
$useRemote = ($remoteApi !== '');

if ($useRemote) {
    $qrCodeUrl = $remoteApi . urlencode($fullUrl);
    $qrCode = @file_get_contents($qrCodeUrl);

    if ($qrCode === false || $qrCode === '') {
        error_log('二维码远程接口调用失败: ' . $qrCodeUrl);
        qr_fail(502, '二维码生成失败');
    }

    header('Content-Type: image/png');
    echo $qrCode;
    exit;
}

require_once __DIR__ . '/phpqrcode/qrlib.php';

// QR_CACHEABLE 默认为 true，会往 phpqrcode/cache/ 写掩码模板。
// 该目录不可写时库会报错，这里降级为不使用缓存（只是稍慢，结果一致）。
if (defined('QR_CACHEABLE') && QR_CACHEABLE && !is_writable(QR_CACHE_DIR)) {
    error_log('phpqrcode 缓存目录不可写，已降级为无缓存模式: ' . QR_CACHE_DIR);
}

// 用输出缓冲接住库的输出，确保只有拿到真正的 PNG 字节才发出去。
ob_start();
try {
    // 参数：内容、不落盘(false)、纠错等级 L、每模块像素 6、静区 2
    QRcode::png($fullUrl, false, QR_ECLEVEL_L, 6, 2);
    $png = ob_get_clean();
} catch (Throwable $e) {
    ob_end_clean();
    error_log('二维码本地生成失败: ' . $e->getMessage());
    qr_fail(500, '二维码生成失败');
}

// PNG 魔数校验：避免把 warning 文本当成图片吐给浏览器。
if ($png === false || strncmp($png, "\x89PNG\r\n\x1a\n", 8) !== 0) {
    error_log('二维码本地生成输出不是合法 PNG');
    qr_fail(500, '二维码生成失败');
}

header('Content-Type: image/png');
header('Content-Length: ' . strlen($png));
echo $png;
