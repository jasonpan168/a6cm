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
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>关于我们 - A6.cm短网址生成器</title>
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
            max-width: 800px;
            margin: 50px auto;
            padding: 2rem;
            background-color: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .section {
            margin-bottom: 3rem;
        }

        .section:last-child {
            margin-bottom: 0;
        }

        .section-title {
            color: var(--primary-color);
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--primary-color);
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .feature-item {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
        }

        .feature-item:hover {
            transform: translateY(-5px);
        }

        .feature-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
        }

        .price-table {
            width: 100%;
            border-collapse: collapse;
            margin: 2rem 0;
        }

        .price-table th,
        .price-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .price-table th {
            background-color: var(--primary-color);
            color: white;
        }

        .price-table tr:last-child td {
            border-bottom: none;
        }

        .advantage-list {
            list-style: none;
        }

        .advantage-list li {
            margin-bottom: 1rem;
            padding-left: 2rem;
            position: relative;
        }

        .advantage-list li::before {
            content: '✓';
            position: absolute;
            left: 0;
            color: var(--success-color);
            font-weight: bold;
        }

        .footer {
            text-align: center;
            padding: 2rem 1rem;
            margin-top: auto;
            color: var(--light-text);
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1.5rem;
            }

            .feature-grid {
                grid-template-columns: 1fr;
            }

            .section-title {
                font-size: 1.5rem;
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
        <div class="section">
            <h2 class="section-title">平台介绍</h2>
            <p>A6.cm是一个专业的短网址生成平台，致力于为用户提供安全、可靠、高效的链接缩短服务。我们的目标是简化链接分享过程，提供更好的用户体验，同时确保链接的安全性和可靠性、业内极少数提供 2位数域名的短网址平台</p>
        </div>

        <div class="section">
            <h2 class="section-title">核心功能</h2>
            <div class="feature-grid">
                <div class="feature-item">
                    <div class="feature-icon">🔒</div>
                    <h3>安全可靠</h3>
                    <p>采用先进的安全措施，保护您的链接和数据安全</p>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">⚡</div>
                    <h3>快速响应</h3>
                    <p>高性能服务器确保链接跳转速度快如闪电</p>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">📊</div>
                    <h3>数据统计</h3>
                    <p>详细的访问次数统计，趋势统计，来源设备统计，瞬间掌握链接传播效果</p>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">🎯</div>
                    <h3>自定义短链+自定义域名</h3>
                    <p>支持自定义短链接，付费用户可以自定义域名打造个性化品牌</p>
                </div>
            </div>
        </div>

        <div class="section">
            <h2 class="section-title">会员方案</h2>
            <table class="price-table">
                <tr>
                    <th>功能特性</th>
                    <th>免费用户</th>
                    <th>高级会员</th>
                </tr>
                <tr>
                    <td>链接创建数量</td>
                    <td> 5个</td>
                    <td>无限制</td>
                </tr>
                <tr>
                    <td>自定义短链接</td>
                    <td>❌</td>
                    <td>✓</td>
                </tr>
                <tr>
                    <td>访问统计</td>
                    <td>限制版</td>
                    <td>专业版</td>
                </tr>
                <tr>
                    <td>添加自定义域名</td>
                    <td>无</td>
                    <td>有</td>
                </tr>
                <tr>
                    <td>价格</td>
                    <td>免费</td>
                    <td>66U/年</td>
                </tr>
            </table>
        </div>

        <div class="section">
            <h2 class="section-title">服务优势</h2>
            <ul class="advantage-list">
                <li><strong>稳定可靠</strong> - 采用高可用架构，确保服务7x24小时稳定运行</li>
                <li><strong>安全防护</strong> - 内置防滥用机制，自动过滤恶意链接</li>
                <li><strong>简单易用</strong> - 直观的操作界面，无需专业知识即可使用</li>
                <li><strong>快速响应</strong> - 毫秒级响应速度，提供流畅的访问体验</li>
                <li><strong>技术支持</strong> - 专业的技术团队，为您提供及时的支持服务</li>
            </ul>
        </div>

        <div class="section">
            <h2 class="section-title">联系我们</h2>
            <p>如果您有任何问题或需求，欢迎通过站点管理员配置的联系方式与我们联系。</p>
        </div>
    </div>

    <div class="footer">
        <p>© <?php echo date('Y'); ?> A6.cm短网址服务 - 让链接分享更简单</p>
    </div>
</body>
</html>