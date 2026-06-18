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
include 'config.php';

// 生产环境：错误写入日志，不向访问者显示
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// 获取短链接的编码
$code = '';
if (isset($_GET['code'])) {
    $code = preg_replace('/[^a-zA-Z0-9-_]/', '', trim($_GET['code'], '/'));
}

// 如果代码为空，尝试从URL路径中提取
if (empty($code) && isset($_SERVER['REQUEST_URI'])) {
    $path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    // 提取路径并进行安全过滤
    if (!empty($path)) {
        $code = preg_replace('/[^a-zA-Z0-9-_]/', '', str_replace('/', '', $path));
    }
}

// 限制短码长度
if (strlen($code) > 50) {
    die("无效的短链接！");
}

// 调试信息（安全：仅当服务器端环境变量 APP_DEBUG=1 时才允许，
// 否则任何访问者用 ?debug=1 即可 dump 数据库短码/IP/完整记录，造成信息泄露）
$debug = (getenv('APP_DEBUG') === '1') && isset($_GET['debug']) && $_GET['debug'] == 1;
if ($debug) {
    echo "<pre>";
    echo "请求URI: " . htmlspecialchars($_SERVER['REQUEST_URI']) . "\n";
    echo "原始路径: " . htmlspecialchars(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) . "\n";
    echo "处理后路径: " . htmlspecialchars(trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/')) . "\n";
    echo "GET参数code: " . (isset($_GET['code']) ? htmlspecialchars($_GET['code']) : '未设置') . "\n";
    echo "处理后的code参数: " . (isset($_GET['code']) ? htmlspecialchars(trim($_GET['code'], '/')) : '未设置') . "\n";
    echo "最终获取到的短链接代码: " . htmlspecialchars($code) . "\n";
}

// 确保代码不为空
if (empty($code)) {
    if ($debug) {
        echo "错误：短链接代码为空！\n";
        echo "</pre>";
    }
    die("错误：无效的短链接！");
}

// 查询数据库是否存在该短链接
try {
    // 首先在links表中查找
    $stmt = $pdo->prepare("SELECT *, 'links' AS source_table FROM links WHERE short_code = ?");
    $stmt->execute([$code]);
    $link = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 如果在links表中没找到，则在urls表(老数据)中查找
    if (!$link) {
        $stmt = $pdo->prepare("SELECT *, 'urls' AS source_table FROM urls WHERE short_code = ?");
        $stmt->execute([$code]);
        $link = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    if ($debug) {
        echo "数据库查询错误: " . $e->getMessage() . "\n";
        echo "</pre>";
    }
    die("系统错误：无法查询短链接数据！");
}

// 调试信息
if ($debug) {
    echo "SQL查询: 在links和urls表中查找短码 '" . htmlspecialchars($code) . "'\n";
    echo "查询结果: " . ($link ? "找到记录" : "未找到记录") . "\n";
    if ($link) {
        echo "链接信息: ";
        print_r($link);
    } else {
        // 查找所有可用的短链接代码
        echo "\n所有可用的短链接代码:\n";
        echo "Links表中的短码:\n";
        $all_codes = $pdo->query("SELECT id, short_code FROM links LIMIT 10");
        while ($row = $all_codes->fetch(PDO::FETCH_ASSOC)) {
            echo "ID: {$row['id']}, 短码: {$row['short_code']}\n";
        }
        echo "Urls表中的短码:\n";
        $all_codes = $pdo->query("SELECT id, short_code FROM urls LIMIT 10");
        while ($row = $all_codes->fetch(PDO::FETCH_ASSOC)) {
            echo "ID: {$row['id']}, 短码: {$row['short_code']}\n";
        }
    }
}

// 链接不存在
if (!$link) {
    die("链接不存在或已失效！(短码: " . htmlspecialchars($code) . ")");
}

// 检查链接是否已过期
if ($link['expire_at'] && strtotime($link['expire_at']) < time()) {
    $error_title = '链接已过期';
    $error_message = '很抱歉，您访问的链接已经过期，无法继续访问。';
    include 'error_template.php';
    exit;
}

// 检查是否已达到最大点击次数
if ($link['max_clicks'] && $link['click_count'] >= $link['max_clicks']) {
    $error_title = '访问次数已达上限';
    $error_message = '该链接已达到最大访问次数限制，无法继续访问。';
    include 'error_template.php';
    exit;
}

// 记录访问者信息（IP 和来源）
$ip_address = $_SERVER['REMOTE_ADDR'];  // 访问者 IP
$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '直接访问';  // 来源网站

// 记录访问详情
try {
    // 获取用户代理信息
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null;
    
    // 获取更多访问者信息
    $country = null;
    $city = null;
    $browser = null;
    $os = null;
    $device = null;
    
    // 尝试从用户代理获取浏览器和操作系统信息
    if ($user_agent) {
        // 简单的浏览器检测
        if (strpos($user_agent, 'Chrome') !== false) {
            $browser = 'Chrome';
        } elseif (strpos($user_agent, 'Firefox') !== false) {
            $browser = 'Firefox';
        } elseif (strpos($user_agent, 'Safari') !== false) {
            $browser = 'Safari';
        } elseif (strpos($user_agent, 'Edge') !== false || strpos($user_agent, 'Edg') !== false) {
            $browser = 'Edge';
        } elseif (strpos($user_agent, 'MSIE') !== false || strpos($user_agent, 'Trident') !== false) {
            $browser = 'Internet Explorer';
        } else {
            $browser = 'Other';
        }
        
        // 简单的操作系统检测
        if (strpos($user_agent, 'Windows') !== false) {
            $os = 'Windows';
        } elseif (strpos($user_agent, 'Macintosh') !== false || strpos($user_agent, 'Mac OS') !== false) {
            $os = 'Mac OS';
        } elseif (strpos($user_agent, 'Linux') !== false) {
            $os = 'Linux';
        } elseif (strpos($user_agent, 'Android') !== false) {
            $os = 'Android';
        } elseif (strpos($user_agent, 'iOS') !== false || strpos($user_agent, 'iPhone') !== false || strpos($user_agent, 'iPad') !== false) {
            $os = 'iOS';
        } else {
            $os = 'Other';
        }
        
        // 简单的设备类型检测
        if (strpos($user_agent, 'Mobile') !== false || strpos($user_agent, 'Android') !== false || strpos($user_agent, 'iPhone') !== false) {
            $device = 'Mobile';
        } elseif (strpos($user_agent, 'iPad') !== false || strpos($user_agent, 'Tablet') !== false) {
            $device = 'Tablet';
        } else {
            $device = 'Desktop';
        }
    }
    
    // 检查url_clicks表是否存在
    $table_exists = false;
    try {
        $check = $pdo->query("SELECT 1 FROM url_clicks LIMIT 1");
        $table_exists = true;
    } catch (PDOException $e) {
        // 表不存在，创建表
        $sql = "CREATE TABLE IF NOT EXISTS url_clicks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            url_id INT,
            short_code VARCHAR(20) NOT NULL,
            ip_address VARCHAR(45),
            referer TEXT,
            user_agent TEXT,
            clicked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            source_table VARCHAR(20) DEFAULT 'links',
            INDEX (short_code),
            INDEX (url_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $pdo->exec($sql);
        error_log("创建url_clicks表");
        $table_exists = true;
    }
    
    // 统一记录到url_clicks表中
    $source_table = isset($link['source_table']) ? $link['source_table'] : 'links';
    
    // 根据来源表决定是否设置url_id
    if ($source_table == 'urls') {
        // 如果是urls表的数据，保留url_id
        $stmt = $pdo->prepare("INSERT INTO url_clicks (url_id, short_code, ip_address, referer, user_agent, clicked_at, source_table) VALUES (?, ?, ?, ?, ?, NOW(), ?)");
        $result = $stmt->execute([$link['id'], $link['short_code'], $ip_address, $referer, $user_agent, $source_table]);
    } else {
        // 如果是links表的数据，不设置url_id，避免外键约束错误
        $stmt = $pdo->prepare("INSERT INTO url_clicks (short_code, ip_address, referer, user_agent, clicked_at, source_table) VALUES (?, ?, ?, ?, NOW(), ?)");
        $result = $stmt->execute([$link['short_code'], $ip_address, $referer, $user_agent, $source_table]);
    }

    
    // 记录调试信息
    if ($result) {
        error_log("访问记录已保存 - 短码: {$link['short_code']}, URL ID: {$link['id']}");
    } else {
        error_log("保存访问记录失败 - 短码: {$link['short_code']}, URL ID: {$link['id']}");
        error_log(print_r($stmt->errorInfo(), true));
    }
    
    // 获取刚插入的记录ID
    $click_id = $pdo->lastInsertId();
    
    // 根据来源表更新点击次数
    if (isset($link['source_table']) && $link['source_table'] == 'urls') {
        // 更新老数据表中的点击次数
        $update_stmt = $pdo->prepare("UPDATE urls SET click_count = click_count + 1 WHERE id = ?");
        $update_stmt->execute([$link['id']]);
    } else {
        // 更新新数据表中的点击次数
        $update_stmt = $pdo->prepare("UPDATE links SET click_count = click_count + 1 WHERE id = ?");
        $update_stmt->execute([$link['id']]);
    }
    
    if ($debug) {
        echo "访问记录已保存\n";
        echo "保存的数据: url_id={$link['id']}, short_code={$link['short_code']}, ip={$ip_address}, referer={$referer}, user_agent={$user_agent}\n";
        echo "浏览器: {$browser}, 操作系统: {$os}, 设备: {$device}\n";
        echo "来源表: " . (isset($link['source_table']) ? $link['source_table'] : 'links') . "\n";
    }
} catch (PDOException $e) {
    // 记录错误但继续执行，不影响用户访问
    if ($debug) {
        echo "访问记录保存失败: " . $e->getMessage() . "\n";
    }
    error_log("访问记录保存失败: " . $e->getMessage());
}

// 确保原始URL有效
if (empty($link['original_url'])) {
    if ($debug) {
        echo "错误：原始URL为空！\n";
        echo "</pre>";
    }
    die("错误：原始URL为空！");
}

// 确保URL格式正确
if (strpos($link['original_url'], 'http') !== 0) {
    $link['original_url'] = 'http://' . $link['original_url'];
    if ($debug) {
        echo "已自动添加http://前缀\n";
    }
}

if ($debug) {
    echo "\n即将跳转到: " . htmlspecialchars($link['original_url']) . "\n";
    echo "</pre>";
    echo "<p><a href=\"" . htmlspecialchars($link['original_url']) . "\">点击这里手动跳转</a></p>";
    echo "<p><small>调试模式下不会自动跳转。如需测试自动跳转，请移除URL中的debug参数。</small></p>";
} else {
    // 跳转到原始链接
    // 确保清除之前的输出缓冲，避免影响header重定向
    if (ob_get_level()) ob_end_clean();
    
    // 设置重定向头部
    header("Location: " . $link['original_url'], true, 302);
    // 添加备用的HTML重定向
    echo '<!DOCTYPE html>\n<html>\n<head>\n';
    echo '<meta http-equiv="refresh" content="0;url=' . htmlspecialchars($link['original_url']) . '">\n';
    echo '<title>重定向中...</title>\n</head>\n<body>\n';
    echo '<p>正在重定向到: <a href="' . htmlspecialchars($link['original_url']) . '">' . htmlspecialchars($link['original_url']) . '</a></p>\n';
    echo '<script>window.location.href = "' . htmlspecialchars(addslashes($link['original_url'])) . '";</script>\n';
    echo '</body>\n</html>';
    exit;
}
?>