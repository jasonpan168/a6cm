<?php
/**
 * A6.cm 短网址服务  https://www.a6.cm
 *
 * @author    AJIE  https://github.com/jasonpan168/a6cm
 * @copyright Copyright (c) 2026 AJIE
 * @license   AGPL-3.0-or-later  （详见项目根目录 LICENSE 与 LICENSE.md）
 *
 * 交互式创建管理员账号（仅限命令行运行）。
 *
 * 本仓库**不预置任何账号或密码哈希** —— 开源仓库里出现的默认口令，
 * 等于给全网每一个部署实例发了同一把钥匙。管理员一律由部署者在自己的
 * 服务器上，用这个脚本当场创建。
 *
 * 用法：
 *   php create_admin.php
 *
 * 脚本会提示输入用户名和密码（输入时不回显），用 password_hash() 存储。
 */

// 只允许 CLI 运行：绝不能让它变成一个公网可访问的"创建管理员"接口。
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit("此脚本只能在命令行运行：php create_admin.php\n");
}

require __DIR__ . '/config.php';

/**
 * 读一行输入。$hidden = true 时关闭终端回显（密码用）。
 */
function prompt($label, $hidden = false)
{
    fwrite(STDOUT, $label);

    if (!$hidden) {
        $line = fgets(STDIN);
        return $line === false ? '' : rtrim($line, "\r\n");
    }

    // 优先用 stty 关回显；不可用时退回明文输入并明确警告。
    $sttyPath = trim((string) @shell_exec('command -v stty 2>/dev/null'));
    if ($sttyPath !== '') {
        $original = trim((string) @shell_exec('stty -g 2>/dev/null'));
        @shell_exec('stty -echo 2>/dev/null');
        $line = fgets(STDIN);
        if ($original !== '') {
            @shell_exec('stty ' . escapeshellarg($original) . ' 2>/dev/null');
        } else {
            @shell_exec('stty echo 2>/dev/null');
        }
        fwrite(STDOUT, "\n");
        return $line === false ? '' : rtrim($line, "\r\n");
    }

    fwrite(STDOUT, "\n[警告] 当前环境无法关闭终端回显，密码将明文显示。\n");
    $line = fgets(STDIN);
    return $line === false ? '' : rtrim($line, "\r\n");
}

fwrite(STDOUT, "=== 创建管理员账号 ===\n");
fwrite(STDOUT, "数据库：" . (file_exists(__DIR__ . '/database.db') ? 'SQLite (database.db)' : 'MySQL ' . (getenv('DB_NAME') ?: 'dwz')) . "\n\n");

$username = trim(prompt('管理员用户名: '));
if ($username === '') {
    exit("用户名不能为空。\n");
}
if (!preg_match('/^[A-Za-z0-9_.@-]{3,50}$/', $username)) {
    exit("用户名只允许字母、数字和 _ . @ -，长度 3~50。\n");
}

$password = prompt('密码（输入时不回显）: ', true);
$confirm  = prompt('再次输入密码: ', true);

if ($password === '') {
    exit("密码不能为空。\n");
}
if ($password !== $confirm) {
    exit("两次输入的密码不一致。\n");
}
// 与 register.php 的强度要求保持一致
if (strlen($password) < 8
    || !preg_match('/[A-Z]/', $password)
    || !preg_match('/[a-z]/', $password)
    || !preg_match('/[0-9]/', $password)) {
    exit("密码至少 8 位，且必须同时包含大写字母、小写字母和数字。\n");
}

$hash = password_hash($password, PASSWORD_DEFAULT);
// 尽快从内存里抹掉明文
$password = $confirm = null;

try {
    $stmt = $pdo->prepare('SELECT id FROM admin WHERE username = ?');
    $stmt->execute([$username]);

    if ($stmt->fetch()) {
        fwrite(STDOUT, "管理员 [$username] 已存在。要重置它的密码吗？(yes/no): ");
        $answer = strtolower(trim((string) fgets(STDIN)));
        if ($answer !== 'yes' && $answer !== 'y') {
            exit("已取消，未做任何修改。\n");
        }
        $upd = $pdo->prepare('UPDATE admin SET password = ? WHERE username = ?');
        $upd->execute([$hash, $username]);
        fwrite(STDOUT, "✅ 管理员 [$username] 的密码已重置。\n");
    } else {
        $ins = $pdo->prepare('INSERT INTO admin (username, password) VALUES (?, ?)');
        $ins->execute([$username, $hash]);
        fwrite(STDOUT, "✅ 管理员 [$username] 创建成功。\n");
    }
} catch (PDOException $e) {
    error_log('创建管理员失败: ' . $e->getMessage());
    exit("数据库操作失败，请确认已执行 create_tables.sql 建表，并检查 .env 中的数据库配置。\n");
}

fwrite(STDOUT, "现在可以访问 admin_login.php 用该账号登录后台。\n");
fwrite(STDOUT, "提示：前台用户请通过 register.php 自行注册，本脚本只创建后台管理员。\n");
