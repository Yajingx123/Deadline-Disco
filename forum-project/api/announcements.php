<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$path = trim($_SERVER['PATH_INFO'] ?? '', '/');
$parts = explode('/', $path);

switch ($method) {
    case 'GET':
        if (empty($parts[0])) {
            // 获取公告列表
            getAnnouncements();
        } elseif (is_numeric($parts[0])) {
            // 获取单个公告详情
            getAnnouncement((int)$parts[0]);
        }
        break;
    case 'POST':
        // 创建公告（需要管理员权限）
        createAnnouncement();
        break;
    case 'PUT':
        // 更新公告（需要管理员权限）
        if (is_numeric($parts[0])) {
            updateAnnouncement((int)$parts[0]);
        }
        break;
    case 'DELETE':
        // 删除公告（需要管理员权限）
        if (is_numeric($parts[0])) {
            deleteAnnouncement((int)$parts[0]);
        }
        break;
    default:
        forum_json([
            'ok' => false,
            'message' => 'Method not allowed.',
        ], 405);
}

function getAnnouncements(): void {
    $pdo = forum_db();
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 10);
    $offset = ($page - 1) * $limit;
    
    // 获取公告列表
    $stmt = $pdo->prepare("SELECT * FROM forum_announcements WHERE is_active = 1 ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->execute([$limit, $offset]);
    $announcements = $stmt->fetchAll();
    
    // 获取总数
    $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM forum_announcements WHERE is_active = 1");
    $countStmt->execute();
    $total = (int)$countStmt->fetch()['total'];
    
    // 格式化返回数据
    $formatted = array_map(function ($row) {
        return [
            'id' => (int)$row['announcement_id'],
            'title' => (string)$row['title'],
            'content' => (string)$row['content'],
            'created_at' => (string)$row['created_at'],
            'updated_at' => (string)$row['updated_at'],
            'author' => 'Admin', // 默认为 Admin
            'is_pinned' => false, // 暂时不支持置顶
            'status' => 'published', // 默认为已发布
            'view_count' => 0, // 暂时不支持浏览量
            'publishTime' => forum_format_datetime((string)$row['created_at']),
        ];
    }, $announcements);
    
    forum_json([
        'ok' => true,
        'announcements' => $formatted,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit),
        ],
    ]);
}

function getAnnouncement(int $id): void {
    $pdo = forum_db();
    
    // 获取公告详情
    $stmt = $pdo->prepare("SELECT * FROM forum_announcements WHERE announcement_id = ? AND is_active = 1");
    $stmt->execute([$id]);
    $announcement = $stmt->fetch();
    
    if (!$announcement) {
        forum_json([
            'ok' => false,
            'message' => 'Announcement not found.',
        ], 404);
    }
    
    // 格式化返回数据
    $formatted = [
        'id' => (int)$announcement['announcement_id'],
        'title' => (string)$announcement['title'],
        'content' => (string)$announcement['content'],
        'created_at' => (string)$announcement['created_at'],
        'updated_at' => (string)$announcement['updated_at'],
        'author' => 'Admin', // 默认为 Admin
        'is_pinned' => false, // 暂时不支持置顶
        'status' => 'published', // 默认为已发布
        'view_count' => 0, // 暂时不支持浏览量
        'publishTime' => forum_format_datetime((string)$announcement['created_at']),
    ];
    
    forum_json([
        'ok' => true,
        'announcement' => $formatted,
    ]);
}

function createAnnouncement(): void {
    forum_require_admin();
    
    $pdo = forum_db();
    $data = forum_input();
    
    $title = trim((string)($data['title'] ?? ''));
    $content = trim((string)($data['content'] ?? ''));
    $createdBy = 1; // 默认为管理员用户ID
    
    if (empty($title) || empty($content)) {
        forum_json([
            'ok' => false,
            'message' => 'Title and content are required.',
        ], 400);
    }
    
    $stmt = $pdo->prepare("INSERT INTO forum_announcements (title, content, created_by) VALUES (?, ?, ?)");
    $stmt->execute([$title, $content, $createdBy]);
    
    $id = (int)$pdo->lastInsertId();
    
    forum_json([
        'ok' => true,
        'message' => 'Announcement created successfully.',
        'announcement_id' => $id,
    ]);
}

function updateAnnouncement(int $id): void {
    forum_require_admin();
    
    $pdo = forum_db();
    $data = forum_input();
    
    // 检查公告是否存在
    $checkStmt = $pdo->prepare("SELECT * FROM forum_announcements WHERE announcement_id = ?");
    $checkStmt->execute([$id]);
    if (!$checkStmt->fetch()) {
        forum_json([
            'ok' => false,
            'message' => 'Announcement not found.',
        ], 404);
    }
    
    $title = trim((string)($data['title'] ?? ''));
    $content = trim((string)($data['content'] ?? ''));
    
    if (empty($title) || empty($content)) {
        forum_json([
            'ok' => false,
            'message' => 'Title and content are required.',
        ], 400);
    }
    
    $stmt = $pdo->prepare("UPDATE forum_announcements SET title = ?, content = ? WHERE announcement_id = ?");
    $stmt->execute([$title, $content, $id]);
    
    forum_json([
        'ok' => true,
        'message' => 'Announcement updated successfully.',
    ]);
}

function deleteAnnouncement(int $id): void {
    forum_require_admin();
    
    $pdo = forum_db();
    
    // 检查公告是否存在
    $checkStmt = $pdo->prepare("SELECT * FROM forum_announcements WHERE announcement_id = ?");
    $checkStmt->execute([$id]);
    if (!$checkStmt->fetch()) {
        forum_json([
            'ok' => false,
            'message' => 'Announcement not found.',
        ], 404);
    }
    
    // 软删除公告
    $stmt = $pdo->prepare("UPDATE forum_announcements SET is_active = 0 WHERE announcement_id = ?");
    $stmt->execute([$id]);
    
    forum_json([
        'ok' => true,
        'message' => 'Announcement deleted successfully.',
    ]);
}
