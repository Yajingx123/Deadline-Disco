<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$host = 'localhost';
$dbname = 'acadbeat';
$user = 'root';
$pass = 'a1s2d3f4qwer';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    echo json_encode(["ok" => false, "error" => "DB连接失败: " . $e->getMessage()]);
    exit;
}

$action = $_GET['action'] ?? '';

// 列表
if ($action === 'list') {
    $stmt = $pdo->query("SELECT * FROM video_resources");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// ==========================
// ✅ 创建：完美修复版！
// ==========================
if ($action === 'create') {
    try {
        $required = ['video_id', 'mode', 'title'];
        foreach ($required as $f) {
            if (!isset($_REQUEST[$f]) || trim($_REQUEST[$f]) === '') {
                throw new Exception("必填字段缺失: $f");
            }
        }

        // 👇 这是完全匹配你数据库的 SQL！！！
        $stmt = $pdo->prepare("INSERT INTO video_resources (
            video_id, mode, title, type, difficulty, duration, source, country, author,
            time_specific, transcript_text, question, answer_text
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");

        $stmt->execute([
            $_REQUEST['video_id'],
            $_REQUEST['mode'],
            $_REQUEST['title'],
            $_REQUEST['type'] ?? 'Campus',
            $_REQUEST['difficulty'] ?? 'Easy',
            $_REQUEST['duration'] ?? '',
            $_REQUEST['source'] ?? 'ELLLO',
            $_REQUEST['country'] ?? '',
            $_REQUEST['author'] ?? '',
            $_REQUEST['time_specific'] ?? '',
            $_REQUEST['transcript_text'] ?? '',
            $_REQUEST['question'] ?? '',
            $_REQUEST['answer_text'] ?? ''
        ]);

        echo json_encode([
            "ok" => true,
            "status" => "success",
            "video_id" => $_REQUEST['video_id']
        ]);

    } catch (Exception $e) {
        echo json_encode(["ok" => false, "error" => $e->getMessage()]);
    }
    exit;
}

// 删除
if ($action === 'delete') {
    $id = $_GET['id'] ?? '';
    $stmt = $pdo->prepare("DELETE FROM video_resources WHERE video_id = ?");
    $stmt->execute([$id]);
    echo json_encode(["ok" => true]);
    exit;
}

echo json_encode(["ok" => false, "error" => "无效操作"]);
?>