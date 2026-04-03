<?php
/**
 * 视频资源上传 API
 * 支持创建、更新、删除视频资源，并上传文件到远程服务器
 */

require_once __DIR__ . '/_bootstrap.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 远程服务器配置
$REMOTE_SERVER = [
    'host' => '111.231.10.140',
    'username' => 'root',
    'password' => 'Yzp211771590@@',
    'base_path' => '/var/www/media'
];

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'create':
            handleCreate();
            break;
        case 'update':
            handleUpdate();
            break;
        case 'delete':
            handleDelete();
            break;
        default:
            listening_json(['ok' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    listening_json(['ok' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

/**
 * 处理创建视频资源
 */
function handleCreate() {
    global $REMOTE_SERVER;
    
    // 验证必填字段
    $required = ['mode', 'title', 'type', 'difficulty'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            listening_json(['ok' => false, 'message' => "Field '$field' is required"]);
        }
    }
    
    $videoId = $_POST['video_id'] ?? generateVideoId($_POST['mode']);
    $mode = $_POST['mode'];
    
    // 确定远程服务器上的存储路径
    $remotePaths = [
        'video' => "/videos/{$mode}/{$videoId}.mp4",
        'transcript' => "/transcripts/{$mode}/{$videoId}.txt",
        'vtt' => "/vtt/{$mode}/{$videoId}.vtt",
        'labels' => "/labels/{$mode}/{$videoId}.json",
        'sampleNotes' => "/notes/{$mode}/{$videoId}.txt",
        'cover' => "/covers/{$mode}/{$videoId}.jpg",
        'flag' => "/flags/flags/" . strtolower($_POST['country'] ?? 'unknown') . ".png"
    ];
    
    // 上传文件到远程服务器
    $uploadedUrls = [];
    $fileFields = [
        'video_file' => 'video',
        'transcript_file' => 'transcript',
        'vtt_file' => 'vtt',
        'labels_file' => 'labels',
        'sampleNotes_file' => 'sampleNotes',
        'cover_file' => 'cover',
        'flag_file' => 'flag'
    ];
    
    foreach ($fileFields as $fieldName => $type) {
        if (isset($_FILES[$fieldName]) && $_FILES[$fieldName]['error'] === UPLOAD_ERR_OK) {
            $localPath = $_FILES[$fieldName]['tmp_name'];
            $remotePath = $REMOTE_SERVER['base_path'] . $remotePaths[$type];
            
            if (uploadToRemoteServer($localPath, $remotePath)) {
                $uploadedUrls[$type] = "http://{$REMOTE_SERVER['host']}/media" . $remotePaths[$type];
            }
        }
    }
    
    // 保存到数据库
    $db = listening_db();
    
    $stmt = $db->prepare("
        INSERT INTO video_resources (
            video_id, mode, title, type, difficulty, duration, source,
            country, author, time_specific, question,
            video_url, transcript_url, vtt_url, labels_url, sample_notes_url, cover_url, flag_url,
            transcript_text, answer_text
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $videoId,
        $mode,
        $_POST['title'],
        $_POST['type'],
        $_POST['difficulty'],
        $_POST['duration'] ?? null,
        $_POST['source'] ?? 'ELLLO',
        $_POST['country'] ?? null,
        $_POST['author'] ?? null,
        $_POST['time_specific'] ?? null,
        $_POST['question'] ?? null,
        $uploadedUrls['video'] ?? null,
        $uploadedUrls['transcript'] ?? null,
        $uploadedUrls['vtt'] ?? null,
        $uploadedUrls['labels'] ?? null,
        $uploadedUrls['sampleNotes'] ?? null,
        $uploadedUrls['cover'] ?? null,
        $uploadedUrls['flag'] ?? null,
        $_POST['transcript_text'] ?? null,
        $_POST['answer_text'] ?? null
    ]);
    
    listening_json(['ok' => true, 'message' => 'Video resource created successfully', 'data' => ['id' => $videoId]]);
}

/**
 * 处理更新视频资源
 */
function handleUpdate() {
    global $REMOTE_SERVER;
    
    $id = $_GET['id'] ?? '';
    if (empty($id)) {
        listening_json(['ok' => false, 'message' => 'Video ID is required']);
    }
    
    // 获取现有记录
    $db = listening_db();
    $stmt = $db->prepare("SELECT * FROM video_resources WHERE video_id = ?");
    $stmt->execute([$id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existing) {
        listening_json(['ok' => false, 'message' => 'Video not found']);
    }
    
    $mode = $existing['mode'];
    
    // 确定远程服务器上的存储路径
    $remotePaths = [
        'video' => "/videos/{$mode}/{$id}.mp4",
        'transcript' => "/transcripts/{$mode}/{$id}.txt",
        'vtt' => "/vtt/{$mode}/{$id}.vtt",
        'labels' => "/labels/{$mode}/{$id}.json",
        'sampleNotes' => "/notes/{$mode}/{$id}.txt",
        'cover' => "/covers/{$mode}/{$id}.jpg",
        'flag' => "/flags/flags/" . strtolower($_POST['country'] ?? $existing['country'] ?? 'unknown') . ".png"
    ];
    
    // 上传新文件到远程服务器
    $uploadedUrls = [];
    $fileFields = [
        'video_file' => 'video',
        'transcript_file' => 'transcript',
        'vtt_file' => 'vtt',
        'labels_file' => 'labels',
        'sampleNotes_file' => 'sampleNotes',
        'cover_file' => 'cover',
        'flag_file' => 'flag'
    ];
    
    foreach ($fileFields as $fieldName => $type) {
        if (isset($_FILES[$fieldName]) && $_FILES[$fieldName]['error'] === UPLOAD_ERR_OK) {
            $localPath = $_FILES[$fieldName]['tmp_name'];
            $remotePath = $REMOTE_SERVER['base_path'] . $remotePaths[$type];
            
            if (uploadToRemoteServer($localPath, $remotePath)) {
                $uploadedUrls[$type] = "http://{$REMOTE_SERVER['host']}/media" . $remotePaths[$type];
            }
        }
    }
    
    // 构建更新 SQL
    $updateFields = [
        'title' => $_POST['title'] ?? $existing['title'],
        'type' => $_POST['type'] ?? $existing['type'],
        'difficulty' => $_POST['difficulty'] ?? $existing['difficulty'],
        'duration' => $_POST['duration'] ?? $existing['duration'],
        'source' => $_POST['source'] ?? $existing['source'],
        'country' => $_POST['country'] ?? $existing['country'],
        'author' => $_POST['author'] ?? $existing['author'],
        'time_specific' => $_POST['time_specific'] ?? $existing['time_specific'],
        'question' => $_POST['question'] ?? $existing['question'],
        'transcript_text' => $_POST['transcript_text'] ?? $existing['transcript_text'],
        'answer_text' => $_POST['answer_text'] ?? $existing['answer_text']
    ];
    
    // 如果有新上传的文件，更新 URL
    if (!empty($uploadedUrls['video'])) $updateFields['video_url'] = $uploadedUrls['video'];
    if (!empty($uploadedUrls['transcript'])) $updateFields['transcript_url'] = $uploadedUrls['transcript'];
    if (!empty($uploadedUrls['vtt'])) $updateFields['vtt_url'] = $uploadedUrls['vtt'];
    if (!empty($uploadedUrls['labels'])) $updateFields['labels_url'] = $uploadedUrls['labels'];
    if (!empty($uploadedUrls['sampleNotes'])) $updateFields['sample_notes_url'] = $uploadedUrls['sampleNotes'];
    if (!empty($uploadedUrls['cover'])) $updateFields['cover_url'] = $uploadedUrls['cover'];
    if (!empty($uploadedUrls['flag'])) $updateFields['flag_url'] = $uploadedUrls['flag'];
    
    $fields = [];
    $values = [];
    foreach ($updateFields as $key => $value) {
        $fields[] = "$key = ?";
        $values[] = $value;
    }
    $values[] = $id;
    
    $sql = "UPDATE video_resources SET " . implode(', ', $fields) . " WHERE video_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($values);
    
    listening_json(['ok' => true, 'message' => 'Video resource updated successfully']);
}

/**
 * 处理删除视频资源
 */
function handleDelete() {
    $id = $_GET['id'] ?? '';
    if (empty($id)) {
        listening_json(['ok' => false, 'message' => 'Video ID is required']);
    }
    
    $db = listening_db();
    $stmt = $db->prepare("DELETE FROM video_resources WHERE video_id = ?");
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() > 0) {
        listening_json(['ok' => true, 'message' => 'Video resource deleted successfully']);
    } else {
        listening_json(['ok' => false, 'message' => 'Video not found']);
    }
}

/**
 * 上传文件到远程服务器 (使用 SFTP)
 */
function uploadToRemoteServer($localPath, $remotePath) {
    global $REMOTE_SERVER;
    
    // 检查是否安装了 ssh2 扩展
    if (!function_exists('ssh2_connect')) {
        // 如果没有 ssh2 扩展，使用备用方案：scp 命令
        return uploadViaScp($localPath, $remotePath);
    }
    
    try {
        // 建立 SSH 连接
        $connection = ssh2_connect($REMOTE_SERVER['host'], 22);
        if (!$connection) {
            throw new Exception('Failed to connect to remote server');
        }
        
        // 认证
        if (!ssh2_auth_password($connection, $REMOTE_SERVER['username'], $REMOTE_SERVER['password'])) {
            throw new Exception('Authentication failed');
        }
        
        // 初始化 SFTP
        $sftp = ssh2_sftp($connection);
        if (!$sftp) {
            throw new Exception('Failed to initialize SFTP');
        }
        
        // 创建远程目录（如果不存在）
        $remoteDir = dirname($remotePath);
        createRemoteDirectory($sftp, $remoteDir);
        
        // 上传文件
        $remoteFile = "ssh2.sftp://" . intval($sftp) . $remotePath;
        $result = copy($localPath, $remoteFile);
        
        // 关闭连接
        unset($sftp);
        unset($connection);
        
        return $result;
    } catch (Exception $e) {
        error_log('SFTP upload error: ' . $e->getMessage());
        // 降级到 SCP
        return uploadViaScp($localPath, $remotePath);
    }
}

/**
 * 使用 SCP 命令上传文件（备用方案）
 */
function uploadViaScp($localPath, $remotePath) {
    global $REMOTE_SERVER;
    
    // 创建远程目录
    $remoteDir = dirname($remotePath);
    $mkdirCmd = sprintf(
        'sshpass -p %s ssh -o StrictHostKeyChecking=no %s@%s "mkdir -p %s"',
        escapeshellarg($REMOTE_SERVER['password']),
        escapeshellarg($REMOTE_SERVER['username']),
        escapeshellarg($REMOTE_SERVER['host']),
        escapeshellarg($remoteDir)
    );
    exec($mkdirCmd . ' 2>&1', $output, $returnCode);
    
    // 上传文件
    $scpCmd = sprintf(
        'sshpass -p %s scp -o StrictHostKeyChecking=no %s %s@%s:%s',
        escapeshellarg($REMOTE_SERVER['password']),
        escapeshellarg($localPath),
        escapeshellarg($REMOTE_SERVER['username']),
        escapeshellarg($REMOTE_SERVER['host']),
        escapeshellarg($remotePath)
    );
    
    exec($scpCmd . ' 2>&1', $output, $returnCode);
    
    return $returnCode === 0;
}

/**
 * 创建远程目录
 */
function createRemoteDirectory($sftp, $path) {
    $parts = explode('/', trim($path, '/'));
    $currentPath = '';
    
    foreach ($parts as $part) {
        $currentPath .= '/' . $part;
        $remoteDir = "ssh2.sftp://" . intval($sftp) . $currentPath;
        
        if (!@file_exists($remoteDir)) {
            @ssh2_sftp_mkdir($sftp, $currentPath, 0755, true);
        }
    }
}

/**
 * 生成视频 ID
 */
function generateVideoId($mode) {
    $db = listening_db();
    $prefix = $mode === 'understand' ? 'u' : 'r';
    
    $stmt = $db->prepare("SELECT video_id FROM video_resources WHERE mode = ? AND video_id LIKE ? ORDER BY video_id DESC LIMIT 1");
    $stmt->execute([$mode, $prefix . '%']);
    $last = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($last) {
        $lastNum = intval(substr($last['video_id'], 1));
        return $prefix . ($lastNum + 1);
    }
    
    return $prefix . '1';
}
