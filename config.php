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
// 安全工具函数（全站统一实现，请勿在各页面另造一套）
// ---------------------------------------------------------------------------

/**
 * 取访客 IP。
 * 只信任 REMOTE_ADDR —— X-Forwarded-For 是客户端可伪造的请求头，
 * 直接拿它做登录锁定的键，等于让攻击者每次换个头就解锁。
 * 如果你的站点确实在反向代理后面，请设置 TRUSTED_PROXY=1 并确保
 * 只有你自己的代理能直连 PHP，否则不要打开。
 */
if (!function_exists('a6_client_ip')) {
    function a6_client_ip()
    {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if (getenv('TRUSTED_PROXY') === '1' && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $candidate = trim($parts[0]);
            if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                return $candidate;
            }
        }

        return $remote;
    }
}

// --------------------------- CSRF ---------------------------
// 沿用 index.php 原有的方案：会话内随机 token，每 30 分钟轮换。
// 轮换时保留上一枚 token 一个周期作为宽限，避免用户把表单开着超过 30 分钟
// 后提交被误杀。校验一律用 hash_equals，禁止用 === 比字符串。

if (!defined('CSRF_TTL')) {
    define('CSRF_TTL', 1800); // 30 分钟
}

if (!function_exists('a6_session_boot')) {
    function a6_session_boot()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        // Cookie 参数必须在 session_start() 之前设置，之后再设是无效的空操作。
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

if (!function_exists('csrf_token')) {
    /**
     * 取当前 CSRF token（不存在或已过期则生成新的）。
     * 注意：请在**校验之后**再调用它来渲染表单，避免刚校验完就轮换。
     */
    function csrf_token()
    {
        a6_session_boot();

        $now = time();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = $now;
        } elseif ($now - ($_SESSION['csrf_token_time'] ?? 0) > CSRF_TTL) {
            // 轮换，但把旧 token 留一个周期作为宽限
            $_SESSION['csrf_token_prev'] = $_SESSION['csrf_token'];
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = $now;
        }

        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    /** 直接输出可放进 <form> 的隐藏字段。 */
    function csrf_field()
    {
        return '<input type="hidden" name="csrf_token" value="'
            . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('csrf_validate')) {
    /**
     * 校验 token。取值顺序：显式传入 > POST 字段 csrf_token > 请求头 X-CSRF-Token。
     * 全站保持一致：AJAX 既可以放 body 也可以放请求头。
     */
    function csrf_validate($token = null)
    {
        a6_session_boot();

        if ($token === null) {
            if (isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])) {
                $token = $_POST['csrf_token'];
            } elseif (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
                $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
            }
        }

        if (!is_string($token) || $token === '') {
            return false;
        }

        $current = $_SESSION['csrf_token'] ?? '';
        if (is_string($current) && $current !== '' && hash_equals($current, $token)) {
            return true;
        }

        // 宽限：轮换前的上一枚 token 仍然接受一个周期
        $prev = $_SESSION['csrf_token_prev'] ?? '';
        if (is_string($prev) && $prev !== '' && hash_equals($prev, $token)) {
            return true;
        }

        return false;
    }
}

if (!function_exists('csrf_require')) {
    /**
     * 校验失败直接终止请求（403）。
     * $mode = 'json' 时返回 JSON，供 AJAX 接口使用。
     */
    function csrf_require($mode = 'html')
    {
        if (csrf_validate()) {
            return;
        }

        error_log('CSRF 校验失败: ' . ($_SERVER['REQUEST_URI'] ?? '?') . ' from ' . a6_client_ip());

        if (!headers_sent()) {
            http_response_code(403);
        }

        if ($mode === 'json') {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode(['success' => false, 'message' => '请求校验失败（CSRF），请刷新页面后重试'], JSON_UNESCAPED_UNICODE);
        } else {
            if (!headers_sent()) {
                header('Content-Type: text/html; charset=utf-8');
            }
            echo '<!DOCTYPE html><meta charset="utf-8"><title>403</title>'
                . '<p>请求校验失败（CSRF token 无效或已过期）。请返回上一页刷新后重试。</p>';
        }
        exit;
    }
}

// --------------------------- 登录失败锁定 ---------------------------
// 计数存数据库而不是 session —— 存 session 的话攻击者把 cookie 一丢就重新开始，
// 等于没有防护。这里按 (scope, ip) 计数，用单条 INSERT ... ON DUPLICATE KEY UPDATE
// 原子自增，并发下不会丢计数。

if (!defined('LOGIN_MAX_ATTEMPTS')) {
    define('LOGIN_MAX_ATTEMPTS', (int) (getenv('LOGIN_MAX_ATTEMPTS') ?: 5));
}
if (!defined('LOGIN_LOCK_SECONDS')) {
    define('LOGIN_LOCK_SECONDS', (int) (getenv('LOGIN_LOCK_SECONDS') ?: 900)); // 15 分钟
}
if (!defined('LOGIN_WINDOW_SECONDS')) {
    define('LOGIN_WINDOW_SECONDS', (int) (getenv('LOGIN_WINDOW_SECONDS') ?: 900));
}

if (!function_exists('a6_login_lock_remaining')) {
    /**
     * 还需锁定多少秒；0 表示未锁定。
     * 表不存在时返回 0（不因为缺表把所有人挡在门外），但会写 error_log。
     */
    function a6_login_lock_remaining(PDO $pdo, $scope, $ip)
    {
        try {
            $stmt = $pdo->prepare(
                'SELECT GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), locked_until)) AS remaining
                   FROM login_attempts
                  WHERE scope = ? AND ip_address = ? AND locked_until IS NOT NULL AND locked_until > NOW()'
            );
            $stmt->execute([$scope, $ip]);
            $row = $stmt->fetch();
            return $row ? (int) $row['remaining'] : 0;
        } catch (PDOException $e) {
            error_log('登录锁定检查失败（请执行 db_update.sql 建 login_attempts 表）: ' . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('a6_login_record_failure')) {
    /** 记一次失败。返回锁定剩余秒数（0 表示还没到阈值）。 */
    function a6_login_record_failure(PDO $pdo, $scope, $ip)
    {
        try {
            // 单条原子语句：
            //  1) attempts —— 距上次失败超过窗口期就从 1 重新计，否则 +1
            //  2) locked_until —— 用的是上一行刚算出的 NEW attempts
            //  3) last_attempt_at 放最后赋值，保证 1) 读到的是旧值
            $sql = 'INSERT INTO login_attempts (scope, ip_address, attempts, first_attempt_at, last_attempt_at, locked_until)
                    VALUES (?, ?, 1, NOW(), NOW(), NULL)
                    ON DUPLICATE KEY UPDATE
                        attempts     = IF(last_attempt_at < (NOW() - INTERVAL ? SECOND), 1, attempts + 1),
                        locked_until = IF(attempts >= ?, (NOW() + INTERVAL ? SECOND), NULL),
                        last_attempt_at = NOW()';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $scope, $ip,
                LOGIN_WINDOW_SECONDS,
                LOGIN_MAX_ATTEMPTS,
                LOGIN_LOCK_SECONDS,
            ]);

            return a6_login_lock_remaining($pdo, $scope, $ip);
        } catch (PDOException $e) {
            error_log('登录失败计数写入失败（请执行 db_update.sql 建 login_attempts 表）: ' . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('a6_login_clear')) {
    /** 登录成功后清掉该 IP 的失败记录。 */
    function a6_login_clear(PDO $pdo, $scope, $ip)
    {
        try {
            $stmt = $pdo->prepare('DELETE FROM login_attempts WHERE scope = ? AND ip_address = ?');
            $stmt->execute([$scope, $ip]);
        } catch (PDOException $e) {
            error_log('清理登录失败记录出错: ' . $e->getMessage());
        }
    }
}

if (!function_exists('a6_lock_message')) {
    function a6_lock_message($seconds)
    {
        $minutes = (int) ceil($seconds / 60);
        return '登录尝试次数过多，请在约 ' . $minutes . ' 分钟后再试。';
    }
}

// --------------------------- 目标 URL 校验与黑名单 ---------------------------

if (!function_exists('a6_url_blacklist')) {
    /**
     * 读取域名黑名单。两个来源，取并集：
     *   1) .env 的 URL_BLACKLIST（逗号分隔）
     *   2) 项目根目录的 blacklist.txt（每行一个域名，# 开头为注释）
     * 默认两者都为空 = 不拦截任何域名。
     */
    function a6_url_blacklist()
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $list = [];

        $env = (string) (getenv('URL_BLACKLIST') ?: '');
        if ($env !== '') {
            foreach (explode(',', $env) as $item) {
                $item = strtolower(trim($item));
                if ($item !== '') {
                    $list[] = $item;
                }
            }
        }

        $file = __DIR__ . '/blacklist.txt';
        if (is_file($file) && is_readable($file)) {
            $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines !== false) {
                foreach ($lines as $line) {
                    $line = strtolower(trim($line));
                    if ($line === '' || $line[0] === '#') {
                        continue;
                    }
                    $list[] = $line;
                }
            }
        }

        $cache = array_values(array_unique($list));
        return $cache;
    }
}

if (!function_exists('a6_host_blacklisted')) {
    /** 主机名命中黑名单（精确匹配或作为子域）即返回 true。 */
    function a6_host_blacklisted($host)
    {
        $host = strtolower(rtrim((string) $host, '.'));
        if ($host === '') {
            return false;
        }

        foreach (a6_url_blacklist() as $bad) {
            $bad = ltrim($bad, '.');
            if ($bad === '') {
                continue;
            }
            if ($host === $bad || substr($host, -(strlen($bad) + 1)) === '.' . $bad) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('a6_validate_target_url')) {
    /**
     * 校验用户提交的目标网址。全站唯一入口（index.php 创建 / update_link.php 编辑
     * 必须都用它，否则就会出现"新建不校验、编辑才校验"这种不一致）。
     *
     * 为什么不能只用 filter_var(FILTER_VALIDATE_URL)：它会放行
     *   javascript://comment%0Aalert(1)   （伪协议，XSS 向量）
     *   file:///etc/passwd
     *   ftp://host/x
     * 所以必须再叠一层 scheme 白名单。
     *
     * @param string      $url   原始输入
     * @param string|null $error 出参：失败原因
     * @return string|false 规范化后的 URL，失败返回 false
     */
    function a6_validate_target_url($url, &$error = null)
    {
        $error = null;
        $url = trim((string) $url);

        if ($url === '') {
            $error = '网址不能为空';
            return false;
        }
        if (strlen($url) > 2048) {
            $error = '网址过长（上限 2048 字符）';
            return false;
        }
        // 控制字符（含 %0A 解码后的换行）一律拒绝
        if (preg_match('/[\x00-\x1F\x7F]/', $url)) {
            $error = '网址包含非法字符';
            return false;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $error = '网址格式不正确';
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if ($scheme !== 'http' && $scheme !== 'https') {
            $error = '只允许 http:// 或 https:// 开头的网址';
            return false;
        }

        $host = (string) parse_url($url, PHP_URL_HOST);
        if ($host === '') {
            $error = '网址缺少主机名';
            return false;
        }

        if (a6_host_blacklisted($host)) {
            $error = '该域名已被本站列入黑名单，无法创建短链接';
            return false;
        }

        return $url;
    }
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