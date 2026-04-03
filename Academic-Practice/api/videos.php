<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

/**
 * 视频资源 API
 * 获取 Listening and Understand / Listening and Respond 的视频列表
 */

// 确保视频资源表存在
function ensure_video_resources_table(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS video_resources (
            video_id VARCHAR(50) PRIMARY KEY COMMENT '视频唯一标识',
            mode VARCHAR(20) NOT NULL COMMENT '模式: understand/respond',
            title VARCHAR(255) NOT NULL COMMENT '视频标题',
            video_type VARCHAR(50) NOT NULL COMMENT '视频类型',
            difficulty VARCHAR(20) NOT NULL COMMENT '难度',
            duration VARCHAR(20) NOT NULL COMMENT '时长',
            source VARCHAR(50) NOT NULL COMMENT '来源',
            country VARCHAR(50) NOT NULL COMMENT '国家',
            author VARCHAR(100) NULL COMMENT '作者',
            time_specific VARCHAR(20) NULL COMMENT '具体时间点',
            video_url VARCHAR(500) NOT NULL COMMENT '视频文件URL',
            transcript_url VARCHAR(500) NOT NULL COMMENT '转录文本URL',
            vtt_url VARCHAR(500) NULL COMMENT '字幕文件URL',
            labels_url VARCHAR(500) NULL COMMENT '标签信息URL',
            sample_notes_url VARCHAR(500) NULL COMMENT '示例笔记URL',
            cover_url VARCHAR(500) NOT NULL COMMENT '封面图片URL',
            flag_url VARCHAR(500) NOT NULL COMMENT '国旗图片URL',
            transcript_text TEXT NULL COMMENT '转录文本内容',
            question TEXT NULL COMMENT 'respond模式的问题',
            answer_text TEXT NULL COMMENT '参考答案',
            status VARCHAR(20) NOT NULL DEFAULT 'active' COMMENT '状态',
            sort_order INT NOT NULL DEFAULT 0 COMMENT '排序',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

// 获取视频列表
function get_videos(PDO $pdo, array $filters = []): array {
    $where = ['status = ?'];
    $params = ['active'];
    
    // 模式过滤 (understand/respond)
    if (!empty($filters['mode'])) {
        $where[] = 'mode = ?';
        $params[] = $filters['mode'];
    }
    
    // 类型过滤
    if (!empty($filters['type']) && $filters['type'] !== 'All') {
        $where[] = 'video_type = ?';
        $params[] = $filters['type'];
    }
    
    // 难度过滤
    if (!empty($filters['difficulty']) && $filters['difficulty'] !== 'All') {
        $where[] = 'difficulty = ?';
        $params[] = $filters['difficulty'];
    }
    
    // 时长过滤
    if (!empty($filters['duration']) && $filters['duration'] !== 'All') {
        $where[] = 'duration = ?';
        $params[] = $filters['duration'];
    }
    
    // 来源过滤
    if (!empty($filters['source']) && $filters['source'] !== 'All') {
        $where[] = 'source = ?';
        $params[] = $filters['source'];
    }
    
    // 国家过滤
    if (!empty($filters['country']) && $filters['country'] !== 'All') {
        $where[] = 'country = ?';
        $params[] = $filters['country'];
    }
    
    // 搜索关键词
    if (!empty($filters['search'])) {
        $where[] = '(title LIKE ? OR author LIKE ? OR transcript_text LIKE ?)';
        $searchTerm = '%' . $filters['search'] . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    $whereClause = implode(' AND ', $where);
    
    $stmt = $pdo->prepare("
        SELECT 
            video_id,
            mode,
            title,
            video_type as type,
            difficulty,
            duration,
            source,
            country,
            author,
            time_specific,
            video_url,
            transcript_url,
            vtt_url,
            labels_url,
            sample_notes_url,
            cover_url,
            flag_url,
            transcript_text,
            question,
            answer_text,
            sort_order
        FROM video_resources
        WHERE {$whereClause}
        ORDER BY sort_order ASC, video_id ASC
    ");
    
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// 获取单个视频详情
function get_video_by_id(PDO $pdo, string $videoId): ?array {
    $stmt = $pdo->prepare("
        SELECT 
            video_id,
            mode,
            title,
            video_type as type,
            difficulty,
            duration,
            source,
            country,
            author,
            time_specific,
            video_url,
            transcript_url,
            vtt_url,
            labels_url,
            sample_notes_url,
            cover_url,
            flag_url,
            transcript_text,
            question,
            answer_text
        FROM video_resources
        WHERE video_id = ? AND status = 'active'
    ");
    $stmt->execute([$videoId]);
    $result = $stmt->fetch();
    return $result ?: null;
}

// 获取所有可用的过滤选项
function get_filter_options(PDO $pdo): array {
    $options = [
        'types' => [],
        'difficulties' => ['Easy', 'Medium', 'Hard'],
        'durations' => ['0-1min', '1-2min', '2-3min'],
        'sources' => [],
        'countries' => []
    ];
    
    // 获取类型列表
    $stmt = $pdo->query("
        SELECT DISTINCT video_type as type 
        FROM video_resources 
        WHERE status = 'active' 
        ORDER BY video_type
    ");
    $options['types'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // 获取来源列表
    $stmt = $pdo->query("
        SELECT DISTINCT source 
        FROM video_resources 
        WHERE status = 'active' 
        ORDER BY source
    ");
    $options['sources'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // 获取国家列表
    $stmt = $pdo->query("
        SELECT DISTINCT country 
        FROM video_resources 
        WHERE status = 'active' 
        ORDER BY country
    ");
    $options['countries'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    return $options;
}

// 主逻辑
$pdo = listening_db();
ensure_video_resources_table($pdo);

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'list';
    
    switch ($action) {
        case 'list':
            // 获取视频列表
            $filters = [
                'mode' => $_GET['mode'] ?? null,
                'type' => $_GET['type'] ?? 'All',
                'difficulty' => $_GET['difficulty'] ?? 'All',
                'duration' => $_GET['duration'] ?? 'All',
                'source' => $_GET['source'] ?? 'All',
                'country' => $_GET['country'] ?? 'All',
                'search' => $_GET['search'] ?? null
            ];
            
            $videos = get_videos($pdo, $filters);
            
            listening_json([
                'ok' => true,
                'data' => $videos,
                'count' => count($videos)
            ]);
            break;
            
        case 'detail':
            // 获取单个视频详情
            $videoId = $_GET['id'] ?? '';
            if (empty($videoId)) {
                listening_json(['ok' => false, 'message' => 'Video ID is required'], 400);
            }
            
            $video = get_video_by_id($pdo, $videoId);
            if (!$video) {
                listening_json(['ok' => false, 'message' => 'Video not found'], 404);
            }
            
            listening_json([
                'ok' => true,
                'data' => $video
            ]);
            break;
            
        case 'filters':
            // 获取过滤选项
            $options = get_filter_options($pdo);
            listening_json([
                'ok' => true,
                'data' => $options
            ]);
            break;
            
        default:
            listening_json(['ok' => false, 'message' => 'Unknown action'], 400);
    }
} else {
    listening_json(['ok' => false, 'message' => 'Method not allowed'], 405);
}
