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

// 登录验证
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin_login.php');
    exit;
}

// 批量删除短链接
if (isset($_POST['batch_delete']) && isset($_POST['selected_links'])) {
    $selected_links = $_POST['selected_links'];
    $deleted_count = 0;
    
    foreach ($selected_links as $link) {
        list($id, $source_table) = explode(':', $link);
        $id = intval($id);
        
        if ($source_table == 'links') {
            $stmt = $pdo->prepare("DELETE FROM links WHERE id = ?");
            $stmt->execute([$id]);
        } else if ($source_table == 'urls') {
            $stmt = $pdo->prepare("DELETE FROM urls WHERE id = ?");
            $stmt->execute([$id]);
        }
        $deleted_count++;
    }
    
    // 构建重定向URL，保留筛选条件但添加时间戳防止重复提交
    $redirect_url = "admin_dashboard.php?batch_deleted={$deleted_count}&t=" . time();
    
    // 保留筛选条件
    if (!empty($_POST['original_url'])) {
        $redirect_url .= "&original_url=" . urlencode($_POST['original_url']);
    }
    if (!empty($_POST['short_code'])) {
        $redirect_url .= "&short_code=" . urlencode($_POST['short_code']);
    }
    if (!empty($_POST['page'])) {
        $redirect_url .= "&page=" . intval($_POST['page']);
    }
    
    header("Location: $redirect_url");
    exit;
}

// 删除短链接
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $source_table = isset($_GET['source']) ? $_GET['source'] : 'links';
    
    if ($source_table == 'links') {
        $stmt = $pdo->prepare("DELETE FROM links WHERE id = ?");
        $stmt->execute([$id]);
    } else if ($source_table == 'urls') {
        $stmt = $pdo->prepare("DELETE FROM urls WHERE id = ?");
        $stmt->execute([$id]);
    }
    
    // 构建重定向URL，保留筛选条件但添加时间戳防止重复提交
    $redirect_url = "admin_dashboard.php?deleted=1&t=" . time();
    
    // 保留筛选条件
    if (!empty($_GET['original_url'])) {
        $redirect_url .= "&original_url=" . urlencode($_GET['original_url']);
    }
    if (!empty($_GET['short_code'])) {
        $redirect_url .= "&short_code=" . urlencode($_GET['short_code']);
    }
    if (!empty($_GET['page'])) {
        $redirect_url .= "&page=" . intval($_GET['page']);
    }
    
    header("Location: $redirect_url");
    exit;
}

// 处理收藏/取消收藏操作
if (isset($_POST['toggle_favorite'])) {
    $link_id = intval($_POST['link_id']);
    $source_table = $_POST['source_table'];
    
    // 检查是否已经收藏
    $stmt = $pdo->prepare("SELECT * FROM favorites WHERE link_id = ? AND source_table = ?");
    $stmt->execute([$link_id, $source_table]);
    $favorite = $stmt->fetch();
    
    if ($favorite) {
        // 取消收藏
        $stmt = $pdo->prepare("DELETE FROM favorites WHERE link_id = ? AND source_table = ?");
        $stmt->execute([$link_id, $source_table]);
    } else {
        // 添加收藏
        $stmt = $pdo->prepare("INSERT INTO favorites (link_id, source_table) VALUES (?, ?)");
        $stmt->execute([$link_id, $source_table]);
    }
    
    // 构建重定向URL，保留筛选条件
    $redirect_url = "admin_dashboard.php?t=" . time();
    if (!empty($_POST['original_url'])) {
        $redirect_url .= "&original_url=" . urlencode($_POST['original_url']);
    }
    if (!empty($_POST['short_code'])) {
        $redirect_url .= "&short_code=" . urlencode($_POST['short_code']);
    }
    if (!empty($_POST['page'])) {
        $redirect_url .= "&page=" . intval($_POST['page']);
    }
    
    header("Location: $redirect_url");
    exit;
}

// 获取筛选条件
$original_url_filter = isset($_GET['original_url']) ? trim($_GET['original_url']) : "";
$short_code_filter = isset($_GET['short_code']) ? trim($_GET['short_code']) : "";
$filter_type = isset($_GET['filter_type']) ? trim($_GET['filter_type']) : "";

// 获取当前页码
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$records_per_page = 20; // 默认每页记录数
if ($filter_type === 'duplicate_links' || $filter_type === 'duplicate_short_codes') {
    $records_per_page = 60; // 重复链接筛选时每页60条
}
$offset = ($page - 1) * $records_per_page;

// 根据筛选条件查询
$conditions_links = [];
$conditions_urls = [];
$params_links = [];
$params_urls = [];

if ($original_url_filter !== "") {
    $conditions_links[] = "u.original_url LIKE ?";
    $params_links[] = "%{$original_url_filter}%";
    $conditions_urls[] = "u.original_url LIKE ?";
    $params_urls[] = "%{$original_url_filter}%";
}
if ($short_code_filter !== "") {
    $conditions_links[] = "u.short_code = ?";
    $params_links[] = $short_code_filter;
    $conditions_urls[] = "u.short_code = ?";
    $params_urls[] = $short_code_filter;
}

// 保存用于计数查询的原始参数
$original_query_params_links = $params_links;
$original_query_params_urls = $params_urls;

// 根据筛选类型构建查询
switch($filter_type) {
    case 'favorites':
        // 查询收藏的链接
        $query_links = "SELECT u.id, u.user_code, u.original_url, u.short_code, 
                        u.created_at, COUNT(c.id) AS click_count, 
                        u.max_clicks, u.expire_at, u.remark, 
                        'links' AS source_table
                FROM links u
                LEFT JOIN url_clicks c ON c.short_code = u.short_code
                INNER JOIN favorites f ON f.link_id = u.id AND f.source_table = 'links'";
        if ($conditions_links) {
            $query_links .= " WHERE " . implode(" AND ", $conditions_links);
        }
        $query_links .= " GROUP BY u.id";
        $query_urls = "SELECT u.id, u.user_code, u.original_url, u.short_code, 
                        u.created_at, COALESCE(u.click_count, 0) AS click_count, 
                        NULL AS max_clicks, NULL AS expire_at, NULL AS remark, 
                        'urls' AS source_table
                FROM urls u
                INNER JOIN favorites f ON f.link_id = u.id AND f.source_table = 'urls'";
        if ($conditions_urls) {
            $query_urls .= " WHERE " . implode(" AND ", $conditions_urls);
        }
        $query = "($query_links) UNION ALL ($query_urls) ORDER BY created_at DESC LIMIT $offset, $records_per_page";
        
        // 计算收藏链接总数
        $count_query_links_fav = "SELECT COUNT(*) FROM links u INNER JOIN favorites f ON f.link_id = u.id AND f.source_table = 'links'";
        if ($conditions_links) {
            $count_query_links_fav .= " WHERE " . implode(" AND ", $conditions_links);
        }
        $count_stmt_links_fav = $pdo->prepare($count_query_links_fav);
        $count_stmt_links_fav->execute($params_links);
        $total_links_count_fav = $count_stmt_links_fav->fetchColumn();
        
        $count_query_urls_fav = "SELECT COUNT(*) FROM urls u INNER JOIN favorites f ON f.link_id = u.id AND f.source_table = 'urls'";
        if ($conditions_urls) {
            $count_query_urls_fav .= " WHERE " . implode(" AND ", $conditions_urls);
        }
        $count_stmt_urls_fav = $pdo->prepare($count_query_urls_fav);
        $count_stmt_urls_fav->execute($params_urls);
        $total_urls_count_fav = $count_stmt_urls_fav->fetchColumn();
        
        $total_links = $total_links_count_fav + $total_urls_count_fav;
        $total_pages = ceil($total_links / $records_per_page);
        break;
        
    case 'last_30_days_top50':
        // 近30天访问量前50的链接
        $query_links = "SELECT u.id, u.user_code, u.original_url, u.short_code, 
                        u.created_at, COUNT(c.id) AS click_count, 
                        u.max_clicks, u.expire_at, u.remark, 
                        'links' AS source_table
                FROM links u
                LEFT JOIN url_clicks c ON c.short_code = u.short_code
                AND c.clicked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        if ($conditions_links) {
            $query_links .= " WHERE " . implode(" AND ", $conditions_links);
        }
        $query_links .= " GROUP BY u.id ORDER BY click_count DESC LIMIT 50";
        $query_urls = "SELECT u.id, u.user_code, u.original_url, u.short_code, 
                        u.created_at, COALESCE(u.click_count, 0) AS click_count, 
                        NULL AS max_clicks, NULL AS expire_at, NULL AS remark, 
                        'urls' AS source_table
                FROM urls u";
        if ($conditions_urls) {
            $query_urls .= " WHERE " . implode(" AND ", $conditions_urls);
        }
        $query_urls .= " ORDER BY click_count DESC LIMIT 50";
        $query = "($query_links) UNION ALL ($query_urls) ORDER BY click_count DESC LIMIT 50";
        
        // 对于TOP类型的查询，总页数设为1，因为结果已经限制在固定数量
        $total_links = 50;
        $total_pages = 1;
        break;

    case 'last_7_days_top20':
        // 近7天点击量前20的链接
        $query_links = "SELECT u.id, u.user_code, u.original_url, u.short_code, 
                        u.created_at, COUNT(c.id) AS click_count, 
                        u.max_clicks, u.expire_at, u.remark, 
                        'links' AS source_table
                FROM links u
                LEFT JOIN url_clicks c ON c.short_code = u.short_code
                AND c.clicked_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        if ($conditions_links) {
            $query_links .= " WHERE " . implode(" AND ", $conditions_links);
        }
        $query_links .= " GROUP BY u.id ORDER BY click_count DESC LIMIT 20";
        $query_urls = "SELECT u.id, u.user_code, u.original_url, u.short_code, 
                        u.created_at, COALESCE(u.click_count, 0) AS click_count, 
                        NULL AS max_clicks, NULL AS expire_at, NULL AS remark, 
                        'urls' AS source_table
                FROM urls u";
        if ($conditions_urls) {
            $query_urls .= " WHERE " . implode(" AND ", $conditions_urls);
        }
        $query_urls .= " ORDER BY click_count DESC LIMIT 20";
        $query = "($query_links) UNION ALL ($query_urls) ORDER BY click_count DESC LIMIT 20";
        
        // 对于TOP类型的查询，总页数设为1，因为结果已经限制在固定数量
        $total_links = 20;
        $total_pages = 1;
        break;

    case 'all_time_top100':
        // 总点击量前100的链接
        $query_links = "SELECT u.id, u.user_code, u.original_url, u.short_code, 
                        u.created_at, COUNT(c.id) AS click_count, 
                        u.max_clicks, u.expire_at, u.remark, 
                        'links' AS source_table
                FROM links u
                LEFT JOIN url_clicks c ON c.short_code = u.short_code";
        if ($conditions_links) {
            $query_links .= " WHERE " . implode(" AND ", $conditions_links);
        }
        $query_links .= " GROUP BY u.id ORDER BY click_count DESC LIMIT 100";
        $query_urls = "SELECT u.id, u.user_code, u.original_url, u.short_code, 
                        u.created_at, COALESCE(u.click_count, 0) AS click_count, 
                        NULL AS max_clicks, NULL AS expire_at, NULL AS remark, 
                        'urls' AS source_table
                FROM urls u";
        if ($conditions_urls) {
            $query_urls .= " WHERE " . implode(" AND ", $conditions_urls);
        }
        $query_urls .= " ORDER BY click_count DESC LIMIT 100";
        $query = "($query_links) UNION ALL ($query_urls) ORDER BY click_count DESC LIMIT 100";
        
        // 对于TOP类型的查询，总页数设为1，因为结果已经限制在固定数量
        $total_links = 100;
        $total_pages = 1;
        break;

    case 'last_3_days_new':
        // 最近3天新增的链接
        $query_links = "SELECT u.id, u.user_code, u.original_url, u.short_code, 
                        u.created_at, COUNT(c.id) AS click_count, 
                        u.max_clicks, u.expire_at, u.remark, 
                        'links' AS source_table
                FROM links u
                LEFT JOIN url_clicks c ON c.short_code = u.short_code
                WHERE u.created_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)";
        if ($conditions_links) {
            $query_links .= " AND " . implode(" AND ", $conditions_links);
        }
        $query_links .= " GROUP BY u.id";
        $query_urls = "SELECT u.id, u.user_code, u.original_url, u.short_code, 
                        u.created_at, COALESCE(u.click_count, 0) AS click_count, 
                        NULL AS max_clicks, NULL AS expire_at, NULL AS remark, 
                        'urls' AS source_table
                FROM urls u
                WHERE u.created_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)";
        if ($conditions_urls) {
            $query_urls .= " AND " . implode(" AND ", $conditions_urls);
        }
        $query = "($query_links) UNION ALL ($query_urls) ORDER BY created_at DESC LIMIT $offset, $records_per_page";
        
        // 计算总页数
        $count_query_links = "SELECT COUNT(*) FROM links u WHERE u.created_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)";
        if ($conditions_links) {
            $count_query_links .= " AND " . implode(" AND ", $conditions_links);
        }
        $count_stmt_links = $pdo->prepare($count_query_links);
        $count_stmt_links->execute($params_links);
        $total_links_count = $count_stmt_links->fetchColumn();
        
        $count_query_urls = "SELECT COUNT(*) FROM urls u WHERE u.created_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)";
        if ($conditions_urls) {
            $count_query_urls .= " AND " . implode(" AND ", $conditions_urls);
        }
        $count_stmt_urls = $pdo->prepare($count_query_urls);
        $count_stmt_urls->execute($params_urls);
        $total_urls_count = $count_stmt_urls->fetchColumn();
        
        $total_links = $total_links_count + $total_urls_count;
        $total_pages = ceil($total_links / $records_per_page);
        break;

    case 'duplicate_links':
        // 查询重复的链接 (基于 original_url)
        // 先找出重复的 original_url
        $duplicate_original_urls_query = "
            SELECT original_url FROM (
                SELECT original_url FROM links GROUP BY original_url HAVING COUNT(*) > 1
                UNION ALL
                SELECT original_url FROM urls GROUP BY original_url HAVING COUNT(*) > 1
            ) AS all_originals
            GROUP BY original_url
            HAVING COUNT(*) > 0 OR EXISTS (SELECT 1 FROM links l WHERE l.original_url = all_originals.original_url GROUP BY l.original_url HAVING COUNT(*) > 1) OR EXISTS (SELECT 1 FROM urls u WHERE u.original_url = all_originals.original_url GROUP BY u.original_url HAVING COUNT(*) > 1)
        ";
        $stmt_duplicates = $pdo->query($duplicate_original_urls_query);
        $duplicate_urls_list = $stmt_duplicates->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($duplicate_urls_list)) {
            $placeholders = implode(',', array_fill(0, count($duplicate_urls_list), '?'));

            $query_links = "SELECT u.id, u.user_code, u.original_url, u.short_code, 
                            u.created_at, COUNT(c.id) AS click_count, 
                            u.max_clicks, u.expire_at, u.remark, 
                            'links' AS source_table
                    FROM links u
                    LEFT JOIN url_clicks c ON c.short_code = u.short_code
                    WHERE u.original_url IN ($placeholders)";
            if ($conditions_links) {
                // 移除可能存在的 original_url 条件，因为我们已经通过 $duplicate_urls_list 筛选了
                $temp_conditions_links = array_filter($conditions_links, function($condition) {
                    return strpos($condition, 'u.original_url') === false;
                });
                if ($temp_conditions_links) {
                    $query_links .= " AND " . implode(" AND ", $temp_conditions_links);
                }
            }
            $query_links .= " GROUP BY u.id";
            // Retain parameters from global $params_links that do not correspond to an original_url condition
            $retained_params_links = [];
            // Note: $conditions_links and $params_links (on the right) refer to the global ones defined before the switch.
            foreach ($conditions_links as $idx => $condition_str) {
                if (strpos($condition_str, 'u.original_url') === false) { // If condition is NOT for original_url
                    if (isset($params_links[$idx])) { // And its global parameter exists
                        $retained_params_links[] = $params_links[$idx]; // Add it
                    }
                }
            }
            // The new $params_links for this case combines $duplicate_urls_list with these retained parameters
            // $params_links on the left is the variable for this switch case.
            $params_links = array_merge($duplicate_urls_list, $retained_params_links);

            $query_urls = "SELECT u.id, u.user_code, u.original_url, u.short_code, 
                            u.created_at, COALESCE(u.click_count, 0) AS click_count, 
                            NULL AS max_clicks, NULL AS expire_at, NULL AS remark, 
                            'urls' AS source_table
                    FROM urls u
                    WHERE u.original_url IN ($placeholders)";
            if ($conditions_urls) {
                $temp_conditions_urls = array_filter($conditions_urls, function($condition) {
                    return strpos($condition, 'u.original_url') === false;
                });
                if ($temp_conditions_urls) {
                    $query_urls .= " AND " . implode(" AND ", $temp_conditions_urls);
                }
            }
            // Retain parameters from global $params_urls that do not correspond to an original_url condition
            $retained_params_urls = [];
            // Note: $conditions_urls and $params_urls (on the right) refer to the global ones defined before the switch.
            foreach ($conditions_urls as $idx => $condition_str) {
                if (strpos($condition_str, 'u.original_url') === false) { // If condition is NOT for original_url
                    if (isset($params_urls[$idx])) { // And its global parameter exists
                        $retained_params_urls[] = $params_urls[$idx]; // Add it
                    }
                }
            }
            // The new $params_urls for this case combines $duplicate_urls_list with these retained parameters
            // $params_urls on the left is the variable for this switch case.
            $params_urls = array_merge($duplicate_urls_list, $retained_params_urls);

            $query = "($query_links) UNION ALL ($query_urls) ORDER BY original_url, created_at DESC LIMIT $offset, $records_per_page";
            
            // 计算总页数
            $count_query_links = "SELECT COUNT(*) FROM links u WHERE u.original_url IN ($placeholders)";
            if ($conditions_links) {
                $temp_conditions_links = array_filter($conditions_links, function($condition) {
                    return strpos($condition, 'u.original_url') === false;
                });
                if ($temp_conditions_links) {
                    $count_query_links .= " AND " . implode(" AND ", $temp_conditions_links);
                }
            }
            $count_stmt_links = $pdo->prepare($count_query_links);
            $count_stmt_links->execute($params_links);
            $total_links_count = $count_stmt_links->fetchColumn();
            
            $count_query_urls = "SELECT COUNT(*) FROM urls u WHERE u.original_url IN ($placeholders)";
            if ($conditions_urls) {
                $temp_conditions_urls = array_filter($conditions_urls, function($condition) {
                    return strpos($condition, 'u.original_url') === false;
                });
                if ($temp_conditions_urls) {
                    $count_query_urls .= " AND " . implode(" AND ", $temp_conditions_urls);
                }
            }
            $count_stmt_urls = $pdo->prepare($count_query_urls);
            $count_stmt_urls->execute($params_urls);
            $total_urls_count = $count_stmt_urls->fetchColumn();
            
            $total_links = $total_links_count + $total_urls_count;
            $total_pages = ceil($total_links / $records_per_page);
        } else {
            // 没有重复链接，返回空结果集
            $query_links = "SELECT NULL AS id, NULL AS user_code, NULL AS original_url, NULL AS short_code, NULL AS created_at, 0 AS click_count, NULL AS max_clicks, NULL AS expire_at, NULL AS remark, 'links' AS source_table FROM links WHERE 1=0";
            $query_urls = "SELECT NULL AS id, NULL AS user_code, NULL AS original_url, NULL AS short_code, NULL AS created_at, 0 AS click_count, NULL AS max_clicks, NULL AS expire_at, NULL AS remark, 'urls' AS source_table FROM urls WHERE 1=0";
            $query = "($query_links) UNION ALL ($query_urls)";
            $params_links = [];
            $params_urls = [];
            $total_links = 0;
            $total_pages = 1;
        }
        break;

    case 'duplicate_short_codes':
        // 查询重复的短链接 (基于 short_code)
        // 先找出重复的 short_code
        $duplicate_short_codes_query = "
            SELECT short_code
            FROM (
                SELECT short_code FROM links
                UNION ALL
                SELECT short_code FROM urls
            ) AS combined_short_codes
            GROUP BY short_code
            HAVING COUNT(*) > 1
        ";
        $stmt_duplicates_sc = $pdo->query($duplicate_short_codes_query);
        $duplicate_sc_list = $stmt_duplicates_sc->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($duplicate_sc_list)) {
            $placeholders_sc = implode(',', array_fill(0, count($duplicate_sc_list), '?'));

            $query_links = "SELECT u.id, u.user_code, u.original_url, u.short_code, 
                            u.created_at, COUNT(c.id) AS click_count, 
                            u.max_clicks, u.expire_at, u.remark, 
                            'links' AS source_table
                    FROM links u
                    LEFT JOIN url_clicks c ON c.short_code = u.short_code
                    WHERE u.short_code IN ($placeholders_sc)";
            if ($conditions_links) {
                $temp_conditions_links = array_filter($conditions_links, function($condition) {
                    return strpos($condition, 'u.short_code') === false;
                });
                if ($temp_conditions_links) {
                    $query_links .= " AND " . implode(" AND ", $temp_conditions_links);
                }
            }
            $query_links .= " GROUP BY u.id";
            
            $retained_params_links_sc = [];
            foreach ($original_query_params_links as $idx => $param_val) { // Use original params for filtering
                if (isset($conditions_links[$idx]) && strpos($conditions_links[$idx], 'u.short_code') === false) {
                    $retained_params_links_sc[] = $param_val;
                }
            }
            $params_links = array_merge($duplicate_sc_list, $retained_params_links_sc);

            $query_urls = "SELECT u.id, u.user_code, u.original_url, u.short_code, 
                            u.created_at, COALESCE(u.click_count, 0) AS click_count, 
                            NULL AS max_clicks, NULL AS expire_at, NULL AS remark, 
                            'urls' AS source_table
                    FROM urls u
                    WHERE u.short_code IN ($placeholders_sc)";
            if ($conditions_urls) {
                $temp_conditions_urls = array_filter($conditions_urls, function($condition) {
                    return strpos($condition, 'u.short_code') === false;
                });
                if ($temp_conditions_urls) {
                    $query_urls .= " AND " . implode(" AND ", $temp_conditions_urls);
                }
            }
            $retained_params_urls_sc = [];
            foreach ($original_query_params_urls as $idx => $param_val) { // Use original params for filtering
                 if (isset($conditions_urls[$idx]) && strpos($conditions_urls[$idx], 'u.short_code') === false) {
                    $retained_params_urls_sc[] = $param_val;
                }
            }
            $params_urls = array_merge($duplicate_sc_list, $retained_params_urls_sc);

            $query = "($query_links) UNION ALL ($query_urls) ORDER BY short_code, created_at DESC LIMIT $offset, $records_per_page";
            
            // 计算总页数
            $count_query_links = "SELECT COUNT(*) FROM links u WHERE u.short_code IN ($placeholders_sc)";
            if ($conditions_links) {
                $temp_conditions_links = array_filter($conditions_links, function($condition) {
                    return strpos($condition, 'u.short_code') === false;
                });
                if ($temp_conditions_links) {
                    $count_query_links .= " AND " . implode(" AND ", $temp_conditions_links);
                }
            }
            $count_stmt_links = $pdo->prepare($count_query_links);
            $count_stmt_links->execute($params_links);
            $total_links_count = $count_stmt_links->fetchColumn();
            
            $count_query_urls = "SELECT COUNT(*) FROM urls u WHERE u.short_code IN ($placeholders_sc)";
            if ($conditions_urls) {
                $temp_conditions_urls = array_filter($conditions_urls, function($condition) {
                    return strpos($condition, 'u.short_code') === false;
                });
                if ($temp_conditions_urls) {
                    $count_query_urls .= " AND " . implode(" AND ", $temp_conditions_urls);
                }
            }
            $count_stmt_urls = $pdo->prepare($count_query_urls);
            $count_stmt_urls->execute($params_urls);
            $total_urls_count = $count_stmt_urls->fetchColumn();
            
            $total_links = $total_links_count + $total_urls_count;
            $total_pages = ceil($total_links / $records_per_page);
        } else {
            $query_links = "SELECT NULL AS id, NULL AS user_code, NULL AS original_url, NULL AS short_code, NULL AS created_at, 0 AS click_count, NULL AS max_clicks, NULL AS expire_at, NULL AS remark, 'links' AS source_table FROM links WHERE 1=0";
            $query_urls = "SELECT NULL AS id, NULL AS user_code, NULL AS original_url, NULL AS short_code, NULL AS created_at, 0 AS click_count, NULL AS max_clicks, NULL AS expire_at, NULL AS remark, 'urls' AS source_table FROM urls WHERE 1=0";
            $query = "($query_links) UNION ALL ($query_urls)";
            $params_links = [];
            $params_urls = [];
            $total_links = 0;
            $total_pages = 1;
        }
        break;

    default:
        // 默认查询
        $query_links = "SELECT u.id, u.user_code, u.original_url, u.short_code, 
                        u.created_at, COUNT(c.id) AS click_count, 
                        u.max_clicks, u.expire_at, u.remark, 
                        'links' AS source_table
                FROM links u
                LEFT JOIN url_clicks c ON c.short_code = u.short_code";
        if ($conditions_links) {
            $query_links .= " WHERE " . implode(" AND ", $conditions_links);
        }
        $query_links .= " GROUP BY u.id";
        $query_urls = "SELECT u.id, u.user_code, u.original_url, u.short_code, 
                        u.created_at, COALESCE(u.click_count, 0) AS click_count, 
                        NULL AS max_clicks, NULL AS expire_at, NULL AS remark, 
                        'urls' AS source_table
                FROM urls u";
        if ($conditions_urls) {
            $query_urls .= " WHERE " . implode(" AND ", $conditions_urls);
        }
        $query = "($query_links) UNION ALL ($query_urls) ORDER BY created_at DESC LIMIT $offset, $records_per_page";
        // 默认情况下的总数计算移到这里，以避免重复计算或在特定筛选下被覆盖
        $count_query_links_default = "SELECT COUNT(*) FROM links u";
        if ($conditions_links) {
            $count_query_links_default .= " WHERE " . implode(" AND ", $conditions_links);
        }
        $count_stmt_links_default = $pdo->prepare($count_query_links_default);
        $count_stmt_links_default->execute($original_query_params_links);
        $total_links_count_default = $count_stmt_links_default->fetchColumn();

        $count_query_urls_default = "SELECT COUNT(*) FROM urls u";
        if ($conditions_urls) {
            $count_query_urls_default .= " WHERE " . implode(" AND ", $conditions_urls);
        }
        $count_stmt_urls_default = $pdo->prepare($count_query_urls_default);
        $count_stmt_urls_default->execute($original_query_params_urls);
        $total_urls_count_default = $count_stmt_urls_default->fetchColumn();

        $total_links = $total_links_count_default + $total_urls_count_default;
        $total_pages = ceil($total_links / $records_per_page);
}

$stmt = $pdo->prepare($query);
$stmt->execute(array_merge($params_links, $params_urls));
$links = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 获取总记录数（用于分页） - 这部分逻辑移到各个 case 或 default 中
// $count_query_links = "SELECT COUNT(*) FROM links u";
// ... (删除或注释掉旧的总数计算逻辑) ...
// $total_links = $total_links_count + $total_urls_count;
// $total_pages = ceil($total_links / $records_per_page);

// 成功消息
$message = "";
if (isset($_GET['deleted']) && $_GET['deleted'] == 1) {
    $message = "<div class='alert alert-success'>短链接已成功删除！</div>";
} else if (isset($_GET['batch_deleted'])) {
    $count = intval($_GET['batch_deleted']);
    $message = "<div class='alert alert-success'>已成功删除 {$count} 个短链接！</div>";
} else if (isset($_GET['updated']) && $_GET['updated'] == 1) {
    $message = "<div class='alert alert-success'>短链接已成功更新！</div>";
}
?>

<!DOCTYPE html>
<html lang="zh-CN"
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>后台管理 - 短链接管理</title>
    <link href="https://cdn.bootcdn.net/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <style>
        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .table-container table {
            min-width: 100%;
            white-space: nowrap;
        }
        .truncate-text {
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: inline-block;
        }
        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 0.5rem;
        }
        .alert-success {
            background-color: #def7ec;
            color: #03543f;
        }
        .pagination a {
            display: inline-block;
            padding: 0.5rem 1rem;
            margin: 0 0.25rem;
            border-radius: 0.375rem;
            background-color: #4a5568;
            color: white;
            text-decoration: none;
            transition: background-color 0.2s;
        }
        .pagination a:hover {
            background-color: #2d3748;
        }
        .pagination a.active {
            background-color: #2b6cb0;
        }
        /* 模态框样式 */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            padding: 20px;
        }
        .modal-content {
            background-color: white;
            padding: 2rem;
            border-radius: 0.5rem;
            max-width: 500px;
            margin: 10vh auto;
            position: relative;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .close {
            position: absolute;
            right: 1rem;
            top: 1rem;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
        }
        .close:hover {
            color: #000;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <nav class="bg-gradient-to-r from-blue-600 to-indigo-600 p-4 shadow-lg">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-2xl font-bold text-white">📊 短链接后台管理系统</h1>
            <div class="flex gap-4">
                <a href="data_dashboard.php" class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-lg transition-colors duration-200">📈 数据展示</a>
                <a href="admin_users.php" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition-colors duration-200">👥 用户管理</a>
                <a href="logout.php" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg transition-colors duration-200">🚪 退出登录</a>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8">
        <?php if ($message): ?>
            <?php echo $message; ?>
        <?php endif; ?>

        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <form method="GET" action="" class="space-y-4">
                <div class="flex flex-wrap gap-4">
                    <input type="text" name="original_url" placeholder="原始链接..." value="<?= htmlspecialchars($original_url_filter) ?>" 
                           class="flex-1 min-w-[200px] px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <input type="text" name="short_code" placeholder="输入短链接后缀筛选" value="<?= htmlspecialchars($short_code_filter) ?>" 
                           class="flex-1 min-w-[200px] px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="flex flex-wrap gap-4">
                    <select name="filter_type" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="" <?= $filter_type == "" ? "selected" : "" ?>>默认排序</option>
                        <option value="favorites" <?= $filter_type == "favorites" ? "selected" : "" ?>>收藏的链接</option>
                        <option value="last_30_days_top50" <?= $filter_type == "last_30_days_top50" ? "selected" : "" ?>>近30天访问量Top50</option>
                        <option value="last_7_days_top20" <?= $filter_type == "last_7_days_top20" ? "selected" : "" ?>>近7天点击量Top20</option>
                        <option value="all_time_top100" <?= $filter_type == "all_time_top100" ? "selected" : "" ?>>总点击量前100</option>
                        <option value="last_3_days_new" <?= $filter_type == "last_3_days_new" ? "selected" : "" ?>>最近3天新增</option>
                        <option value="duplicate_links" <?= $filter_type == "duplicate_links" ? "selected" : "" ?>>重复长链接</option>
                        <option value="duplicate_short_codes" <?= $filter_type == "duplicate_short_codes" ? "selected" : "" ?>>重复短链接</option>
                    </select>
                </div>
                <div class="flex flex-wrap gap-4">
                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg transition-colors duration-200">筛选</button>
                    <a href="admin_dashboard.php" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition-colors duration-200">重置</a>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <form method="POST" action="" id="batchForm">
                <input type="hidden" name="user_code" value="<?= htmlspecialchars($user_code_filter) ?>">
                <input type="hidden" name="short_code" value="<?= htmlspecialchars($short_code_filter) ?>">
                <input type="hidden" name="page" value="<?= $page ?>">
                <div class="p-4 border-b flex justify-between items-center bg-gray-50">
                    <div class="flex items-center">
                        <input type="checkbox" id="selectAll" class="form-checkbox h-5 w-5 text-blue-600 rounded border-gray-300 focus:ring-blue-500" onclick="toggleAll(this)">
                        <label for="selectAll" class="ml-2 text-gray-700">全选本页</label>
                    </div>
                    <button type="submit" name="batch_delete" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg transition-colors duration-200" onclick="return confirmBatchDelete()">
                        🗑️ 批量删除
                    </button>
                </div>
                <div class="table-container">
                <?php if (count($links) > 0): ?>
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-800 text-white">
                                <th class="px-4 py-3 text-left">选择</th>
                                <th class="px-4 py-3 text-left">ID</th>
                                <th class="px-4 py-3 text-left">用户编码</th>
                                <th class="px-4 py-3 text-left">原始链接</th>
                                <th class="px-4 py-3 text-left">短链接</th>
                                <th class="px-4 py-3 text-left">创建时间</th>
                                <th class="px-4 py-3 text-center">点击次数</th>
                                <th class="px-4 py-3 text-center">最大点击次数</th>
                                <th class="px-4 py-3 text-left">有效期</th>
                                <th class="px-4 py-3 text-left">最新访问IP</th>
                                <th class="px-4 py-3 text-left">访问来源</th>
                                <th class="px-4 py-3 text-left">备注</th>
                                <th class="px-4 py-3 text-center">收藏</th>
                                <th class="px-4 py-3 text-center">操作</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                        <?php foreach ($links as $link): ?>
                            <?php
                            $stmt = $pdo->prepare("SELECT ip_address, referer FROM url_clicks WHERE short_code = ? ORDER BY clicked_at DESC LIMIT 1");
                            $stmt->execute([$link['short_code']]);
                            $latest_click = $stmt->fetch(PDO::FETCH_ASSOC);
                            $latest_ip = $latest_click ? $latest_click['ip_address'] : '暂无数据';
                            $latest_referer = $latest_click ? $latest_click['referer'] : '暂无数据';

                            $row_class = "hover:bg-gray-50"; // Default class
                            if ($filter_type === 'duplicate_links' || $filter_type === 'duplicate_short_codes') {
                                // Simple way to alternate colors for groups of duplicates. 
                                // This assumes duplicates are sorted together.
                                static $duplicate_color_index = 0;
                                static $prev_value = null;
                                $current_value = ($filter_type === 'duplicate_links') ? $link['original_url'] : $link['short_code'];

                                if ($prev_value !== null && $current_value === $prev_value) {
                                    // Same as previous, use the same color index
                                } else {
                                    // New value or first row, alternate color index
                                    $duplicate_color_index = 1 - $duplicate_color_index;
                                }
                                $row_class .= ($duplicate_color_index === 0) ? ' bg-red-100' : ' bg-yellow-100';
                                $prev_value = $current_value;
                            }
                            ?>
                            <tr class="<?= $row_class ?>">
                                <td class="px-4 py-3">
                                    <input type="checkbox" name="selected_links[]" value="<?= $link['id'] ?>:<?= $link['source_table'] ?>" class="form-checkbox h-5 w-5 text-blue-600 rounded border-gray-300 focus:ring-blue-500">
                                </td>
                                <td class="px-4 py-3"><?= $link['id'] ?></td>
                                <td class="px-4 py-3"><?= $link['user_code'] ?: '未填写' ?></td>
                                <td class="px-4 py-3">
                                    <a href="#" onclick="copyToClipboard(event, '<?= htmlspecialchars($link['original_url'], ENT_QUOTES) ?>')" class="text-blue-600 hover:text-blue-800 truncate-text" title="<?= htmlspecialchars($link['original_url']) ?>">
                                        <?= htmlspecialchars($link['original_url']) ?>
                                    </a>
                                </td>
                                <td class="px-4 py-3">
                                    <a href="#" onclick="copyToClipboard(event, '<?= "https://" . $_SERVER['HTTP_HOST'] . "/" . $link['short_code'] ?>')" class="text-blue-600 hover:text-blue-800">
                                        <?= $link['short_code'] ?>
                                    </a>
                                </td>
                                <td class="px-4 py-3"><?= date('Y-m-d H:i', strtotime($link['created_at'])) ?></td>
                                <td class="px-4 py-3 text-center"><?= $link['click_count'] ?></td>
                                <td class="px-4 py-3 text-center"><?= $link['max_clicks'] ?: '不限' ?></td>
                                <td class="px-4 py-3"><?= $link['expire_at'] ? date('Y-m-d H:i', strtotime($link['expire_at'])) : '永久有效' ?></td>
                                <td class="px-4 py-3"><?= $latest_ip ?></td>
                                <td class="px-4 py-3">
                                    <span class="truncate-text" title="<?= htmlspecialchars($latest_referer) ?>">
                                        <?= htmlspecialchars($latest_referer) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3"><?= htmlspecialchars($link['remark'] ?? '') ?></td>
                                <td class="px-4 py-3 text-center">
                                    <?php
                                    $stmt = $pdo->prepare("SELECT * FROM favorites WHERE link_id = ? AND source_table = ?");
                                    $stmt->execute([$link['id'], $link['source_table']]);
                                    $is_favorite = $stmt->fetch();
                                    ?>
                                    <form method="POST" action="" style="display: inline;">
                                        <input type="hidden" name="link_id" value="<?= $link['id'] ?>">
                                        <input type="hidden" name="source_table" value="<?= $link['source_table'] ?>">
                                        <input type="hidden" name="user_code" value="<?= htmlspecialchars($user_code_filter) ?>">
                                        <input type="hidden" name="short_code" value="<?= htmlspecialchars($short_code_filter) ?>">
                                        <input type="hidden" name="page" value="<?= $page ?>">
                                        <button type="submit" name="toggle_favorite" class="<?= $is_favorite ? 'bg-yellow-500 hover:bg-yellow-600' : 'bg-gray-500 hover:bg-gray-600' ?> text-white px-3 py-1 rounded-lg transition-colors duration-200">
                                            <?= $is_favorite ? '⭐ 已收藏' : '☆ 收藏' ?>
                                        </button>
                                    </form>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-center space-x-2">
                                        <a href="view_stats.php?id=<?= $link['id'] ?>&source=<?= $link['source_table'] ?>" target="_blank" 
                                           class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded transition-colors duration-200">📊 统计</a>
                                        <button onclick="editLink('<?= $link['id'] ?>', '<?= $link['source_table'] ?>', '<?= htmlspecialchars($link['original_url'], ENT_QUOTES) ?>', '<?= htmlspecialchars($link['remark'] ?? '', ENT_QUOTES) ?>', '<?= $link['max_clicks'] ?>', '<?= $link['expire_at'] ?>'); return false;" 
                                                class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded transition-colors duration-200 mr-2">
                                            ✏️ 编辑
                                        </button>
                                        <a href="?delete=<?= $link['id'] ?>&source=<?= $link['source_table'] ?>" 
                                           onclick="return confirm('⚠️ 确定要删除该链接吗？');" 
                                           class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded transition-colors duration-200">🗑️ 删除</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="text-center py-8 text-gray-500">
                        <p>没有找到符合条件的短链接！</p>
                    </div>
                <?php endif; ?>
            </div>
            </form>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="flex justify-center mt-8 pagination">
                <?php 
                $pagination_params = [];
                if (!empty($original_url_filter)) { $pagination_params['original_url'] = $original_url_filter; }
                if (!empty($short_code_filter)) { $pagination_params['short_code'] = $short_code_filter; }
                if (!empty($filter_type)) { $pagination_params['filter_type'] = $filter_type; }
                // user_code_filter 似乎未在之前的代码中定义，如果需要，也应在此处添加
                // if (!empty($user_code_filter)) { $pagination_params['user_code'] = $user_code_filter; }
                $query_string = http_build_query($pagination_params);
                ?>
                <?php if ($page > 1): ?>
                    <a href="?page=1<?= !empty($query_string) ? '&'.$query_string : '' ?>">首页</a>
                    <a href="?page=<?= $page - 1 ?><?= !empty($query_string) ? '&'.$query_string : '' ?>">⬅️ 上一页</a>
                <?php endif; ?>
                
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                for ($i = $start_page; $i <= $end_page; $i++) {
                    $active = $i == $page ? 'active' : '';
                    echo "<a href='?page=$i" . (!empty($query_string) ? '&'.$query_string : '') . "' class='$active'>$i</a>";
                }
                ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?><?= !empty($query_string) ? '&'.$query_string : '' ?>">下一页 ➡️</a>
                    <a href="?page=<?= $total_pages ?><?= !empty($query_string) ? '&'.$query_string : '' ?>">末页</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <footer class="bg-white border-t mt-8 py-4">
        <div class="container mx-auto px-4 text-center text-gray-600">
            <p>&copy; <?= date('Y') ?> 短链接管理系统. All rights reserved.</p>
        </div>
    </footer>

    <!-- 编辑链接模态框 -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h2 class="text-2xl font-bold mb-4">编辑链接</h2>
            <form id="editForm" class="space-y-4">
                <input type="hidden" id="editLinkId" name="link_id">
                <input type="hidden" id="editSourceTable" name="source_table">
                <div>
                    <label for="editOriginalUrl" class="block text-sm font-medium text-gray-700 mb-2">原始链接</label>
                    <input type="url" id="editOriginalUrl" name="original_url" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label for="editRemark" class="block text-sm font-medium text-gray-700 mb-2">备注</label>
                    <textarea id="editRemark" name="remark" rows="3"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
                <div>
                    <label for="editMaxClicks" class="block text-sm font-medium text-gray-700 mb-2">最大点击次数 (0 或留空表示不限制)</label>
                    <input type="number" id="editMaxClicks" name="max_clicks" min="0"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label for="editExpireAt" class="block text-sm font-medium text-gray-700 mb-2">到期时间 (留空表示永久)</label>
                    <input type="datetime-local" id="editExpireAt" name="expire_at"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="flex justify-end">
                    <button type="button" onclick="submitEditForm()" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition-colors duration-200">
                        保存更改
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function copyToClipboard(event, text) {
            event.preventDefault(); // 阻止链接的默认跳转行为
            navigator.clipboard.writeText(text).then(function() {
                alert('链接已复制到剪贴板！');
            }, function(err) {
                alert('复制失败，请手动复制。错误: ' + err);
            });
        }

        function editLink(id, sourceTable, originalUrl, remark, maxClicks, expireAt) {
            document.getElementById('editLinkId').value = id;
            document.getElementById('editSourceTable').value = sourceTable;
            document.getElementById('editOriginalUrl').value = originalUrl;
            document.getElementById('editRemark').value = remark || '';
            document.getElementById('editMaxClicks').value = maxClicks === null || maxClicks === undefined ? '' : maxClicks;
            document.getElementById('editExpireAt').value = expireAt ? expireAt.replace(' ', 'T') : '';
            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        // 点击模态框外部关闭
        window.onclick = function(event) {
            if (event.target == document.getElementById('editModal')) {
                closeEditModal();
            }
        }
        // 全选/取消全选功能
        function toggleAll(source) {
            const checkboxes = document.getElementsByName('selected_links[]');
            for (let checkbox of checkboxes) {
                checkbox.checked = source.checked;
            }
        }

        // 批量删除确认
        function confirmBatchDelete() {
            const checkboxes = document.getElementsByName('selected_links[]');
            let selectedCount = 0;
            for (let checkbox of checkboxes) {
                if (checkbox.checked) selectedCount++;
            }
            
            if (selectedCount === 0) {
                alert('请至少选择一个要删除的短链接！');
                return false;
            }
            
            return confirm(`确定要删除选中的 ${selectedCount} 个短链接吗？`);
        }

        function submitEditForm() {
            const form = document.getElementById('editForm');
            const formData = new FormData(form);
            
            // 添加 admin_action 标志，以便 update_link.php 区分
            formData.append('admin_action', 'true');

            fetch('update_link.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('链接更新成功！');
                    closeEditModal();
                    // 为了防止GET参数导致重复提交，这里直接刷新，但清空GET参数
                    window.location.href = window.location.pathname + '?t=' + new Date().getTime(); 
                } else {
                    alert('链接更新失败：' + (data.message || '未知错误'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('发生错误，请查看控制台。');
            });
        }
    </script>
</body>
</html>