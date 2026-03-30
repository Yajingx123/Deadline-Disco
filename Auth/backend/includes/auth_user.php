<?php
declare(strict_types=1);

function auth_start_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function auth_bootstrap_roles(PDO $pdo): void {
    static $bootstrapped = false;

    if ($bootstrapped) {
        return;
    }

    $columnStmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'");
    $hasRoleColumn = $columnStmt->fetch(PDO::FETCH_ASSOC) !== false;

    if (!$hasRoleColumn) {
        $pdo->exec("ALTER TABLE users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'user' AFTER avatar_url");
    }

    $adminIdentifierStmt = $pdo->prepare("
        SELECT user_id
        FROM users
        WHERE username = :username OR email = :email
        LIMIT 1
    ");
    $adminIdentifierStmt->execute([
        ':username' => 'admin',
        ':email' => 'admin@acadbeat.local',
    ]);
    $adminRow = $adminIdentifierStmt->fetch(PDO::FETCH_ASSOC);

    if ($adminRow) {
        $promoteStmt = $pdo->prepare("UPDATE users SET role = 'admin' WHERE user_id = ?");
        $promoteStmt->execute([(int)$adminRow['user_id']]);
    } else {
        $insertAdminStmt = $pdo->prepare("
            INSERT INTO users (username, email, password_hash, avatar_url, role, created_at, updated_at)
            VALUES (:username, :email, :password_hash, NULL, 'admin', NOW(), NOW())
        ");
        $insertAdminStmt->execute([
            ':username' => 'admin',
            ':email' => 'admin@acadbeat.local',
            ':password_hash' => password_hash('123456', PASSWORD_DEFAULT),
        ]);
    }

    $bootstrapped = true;
}

function auth_map_user_row(array $row): array {
    return [
        'user_id' => (int)$row['user_id'],
        'username' => (string)$row['username'],
        'email' => (string)$row['email'],
        'role' => (string)($row['role'] ?? 'user'),
    ];
}

function auth_fetch_user_by_identifier(PDO $pdo, string $identifier): ?array {
    auth_bootstrap_roles($pdo);

    $stmt = $pdo->prepare("
        SELECT user_id, username, email, password_hash, role
        FROM users
        WHERE username = :identifier OR email = :identifier
        LIMIT 1
    ");
    $stmt->execute([':identifier' => $identifier]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user ?: null;
}

function auth_fetch_user_by_id(PDO $pdo, int $userId): ?array {
    auth_bootstrap_roles($pdo);

    $stmt = $pdo->prepare("
        SELECT user_id, username, email, password_hash, role
        FROM users
        WHERE user_id = :user_id
        LIMIT 1
    ");
    $stmt->execute([':user_id' => $userId]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user ?: null;
}
