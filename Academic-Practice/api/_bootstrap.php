<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

$config = require __DIR__ . '/../../Auth/backend/config/config.php';

function listening_db(): PDO {
    static $pdo = null;
    global $config;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $config['db_host'],
        $config['db_port'],
        $config['db_name']
    );

    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function listening_json(array $payload, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function listening_input(): array {
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function listening_require_user(): array {
    $user = $_SESSION['auth_user'] ?? null;
    if (!is_array($user) || empty($user['user_id'])) {
        listening_json([
            'ok' => false,
            'message' => 'Login required.',
        ], 401);
    }
    return $user;
}

function listening_ensure_table(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS listening_practice_records (
            record_id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT NOT NULL,
            video_id VARCHAR(100) NOT NULL,
            mode VARCHAR(40) NOT NULL DEFAULT 'understand',
            title VARCHAR(255) NOT NULL,
            person_meta TEXT NULL,
            difficulty VARCHAR(40) NULL,
            duration_label VARCHAR(40) NULL,
            source_name VARCHAR(80) NULL,
            country_name VARCHAR(80) NULL,
            main_content TEXT NULL,
            key_word TEXT NULL,
            personal_view TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_listening_practice_records_user_created (user_id, created_at),
            KEY idx_listening_practice_records_video (video_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function integrated_ensure_table(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS integrated_response_records (
            record_id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT NOT NULL,
            video_id VARCHAR(100) NOT NULL,
            mode VARCHAR(40) NOT NULL DEFAULT 'respond',
            title VARCHAR(255) NOT NULL,
            person_meta TEXT NULL,
            difficulty VARCHAR(40) NULL,
            duration_label VARCHAR(40) NULL,
            source_name VARCHAR(80) NULL,
            country_name VARCHAR(80) NULL,
            audio_data LONGTEXT NOT NULL,
            audio_mime VARCHAR(80) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_integrated_response_records_user_created (user_id, created_at),
            KEY idx_integrated_response_records_video (video_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}
