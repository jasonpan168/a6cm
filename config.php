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
// ---------------------------------------------------------------------------
// .env 加载器（零依赖）
// ---------------------------------------------------------------------------
// PHP 原生的 getenv() 只读进程环境变量，不会读取 .env 文件。为了让文档里
// "cp .env.example .env" 的配置方式真正生效，这里用 parse_ini_file 解析 .env，
// 并写入进程环境。优先级：真实环境变量 > .env 文件（真实环境变量永远不被覆盖），
// 这样容器/systemd/Apache SetEnv 注入的配置仍然说了算。
// .env 不存在或格式有误时静默跳过（只写 error_log），不影响程序启动。
(function () {
    $envFile = __DIR__ . '/.env';
    if (!is_file($envFile) || !is_readable($envFile)) {
        return;
    }

    // INI_SCANNER_RAW：保留值的原始字符串，避免 "yes"/"on"/"null" 被转成布尔/空值。
    // parse_ini_file 天然忽略以 ; 或 # 开头的注释行。
    $vars = @parse_ini_file($envFile, false, INI_SCANNER_RAW);

    if ($vars === false || !is_array($vars)) {
        // 退化路径：某些 .env 里有 parse_ini_file 不接受的字符，手工按行解析。
        $vars = [];
        $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            error_log('.env 读取失败，已跳过：' . $envFile);
            return;
        }
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || $line[0] === ';') {
                continue;
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $vars[trim(substr($line, 0, $pos))] = trim(substr($line, $pos + 1));
        }
    }

    foreach ($vars as $key => $value) {
        $key = trim((string) $key);
        if ($key === '') {
            continue;
        }

        // 已由真实环境变量提供的键，不被 .env 覆盖。
        // 注意用 getenv($key) === false 判断"未设置"，而不是用 !getenv()，
        // 否则 FOO=0 / FOO= 这类合法取值会被 .env 顶掉。
        if (getenv($key) !== false) {
            continue;
        }

        $value = trim((string) $value);
        // 去掉值两端成对的引号（"..." 或 '...'）
        $len = strlen($value);
        if ($len >= 2) {
            $first = $value[0];
            $last = $value[$len - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        if (!isset($_SERVER[$key])) {
            $_SERVER[$key] = $value;
        }
    }
})();

// 错误显示策略（对所有运行方式统一生效：Apache / nginx+fpm / php -S 内置服务器）
// 默认：错误写入日志、不向访问者输出，避免泄露路径/SQL/弃用警告。
// 本地调试时设环境变量 APP_DEBUG=1 即可在页面看到完整错误。
if (getenv('APP_DEBUG') === '1') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}

// 定义网站基础URL
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/');
}

// 数据库配置
$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'dwz';
$username = getenv('DB_USER') ?: 'dwz';
$password = getenv('DB_PASSWORD') ?: '';

// SQLite数据库文件路径
$sqlite_db_path = __DIR__ . '/database.db';

// 邮件服务器配置
if (!defined('SMTP_HOST')) {
    define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.example.com');
}
if (!defined('SMTP_PORT')) {
    define('SMTP_PORT', getenv('SMTP_PORT') ?: 465);
}
if (!defined('SMTP_USERNAME')) {
    define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: 'your-email@example.com');
}
if (!defined('SMTP_PASSWORD')) {
    define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: '');
}
if (!defined('SMTP_FROM_EMAIL')) {
    define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: 'noreply@example.com');
}
if (!defined('SMTP_FROM_NAME')) {
    define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: '短网址服务');
}

// 二维码配置
// 默认留空 = 使用仓库自带的 phpqrcode 库在本地生成二维码（推荐）。
// 只有显式配置 QR_CODE_API 时，generate_qrcode.php 才会改走该远程接口；
// 注意：走远程意味着把用户的完整短链接发送给第三方，请自行评估隐私影响。
if (!defined('QR_CODE_API')) {
    define('QR_CODE_API', getenv('QR_CODE_API') ?: '');
}

// ---------------------------------------------------------------------------
// 源代码获取地址 / Appropriate Legal Notice
// ---------------------------------------------------------------------------
// AGPL-3.0 第 13 条要求：通过网络向用户提供服务时，必须让用户能取得本服务
// 所运行的完整源代码。LICENSE.md 的附加条款同时要求保留指向原始仓库的链接
// 与原作者署名 —— 依据 AGPL 第 7(b) 条，附加条款只能要求「保留」，所以上游
// 自己必须先把这条声明放进页脚，下游才谈得上保留。
//
// 如果你修改了本项目并对外提供服务，请在 .env 里把 SOURCE_CODE_URL 指向
// 你自己那份修改后源代码的位置（AGPL 要求提供的是你实际运行的版本）。
if (!defined('UPSTREAM_REPO_URL')) {
    define('UPSTREAM_REPO_URL', 'https://github.com/jasonpan168/a6cm');
}
if (!defined('SOURCE_CODE_URL')) {
    define('SOURCE_CODE_URL', getenv('SOURCE_CODE_URL') ?: UPSTREAM_REPO_URL);
}

if (!function_exists('a6_legal_notice')) {
    /**
     * 页脚法律声明：原始项目署名 + 源代码入口。
     * 所有带界面的页面都应当输出它，请勿删除（见 LICENSE.md 附加条款第 1 条）。
     */
    function a6_legal_notice()
    {
        $upstream = htmlspecialchars(UPSTREAM_REPO_URL, ENT_QUOTES, 'UTF-8');
        $source   = htmlspecialchars(SOURCE_CODE_URL, ENT_QUOTES, 'UTF-8');

        return '<p class="agpl-notice" style="font-size:12px;opacity:.75;margin-top:6px;">'
            . '基于 <a href="' . $upstream . '" target="_blank" rel="noopener noreferrer">A6.cm</a>'
            . '（<a href="' . $upstream . '/blob/main/LICENSE" target="_blank" rel="noopener noreferrer">AGPL-3.0</a>）构建'
            . ' · <a href="' . $source . '" target="_blank" rel="noopener noreferrer">获取源代码</a>'
            . '</p>';
    }
}

try {
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ];

    // 优先尝试SQLite数据库
    if (file_exists($sqlite_db_path)) {
        $pdo = new PDO("sqlite:$sqlite_db_path", null, null, $options);
    } else {
        // 如果SQLite文件不存在，尝试MySQL连接
        // 注意：连接字符集已在 DSN 中通过 charset=utf8mb4 指定；
        // 这里用连接后执行 SET NAMES 来补充排序规则，兼容 PHP 7.4 ~ 8.5+
        // （避免使用 PHP 8.5 已弃用的 PDO::MYSQL_ATTR_INIT_COMMAND 常量）
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, $options);
        $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    }
} catch (PDOException $e) {
    error_log("数据库连接失败: " . $e->getMessage());
    die("系统错误，请稍后重试。");
}
?>