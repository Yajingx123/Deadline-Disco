<?php
declare(strict_types=1);

function api_list_users(PDO $db): void
{
    $stmt = $db->query(
        'SELECT user_id, username, email, create_time
         FROM `user`
         ORDER BY user_id ASC'
    );

    $users = array_map(static function (array $row): array {
        return [
            'userId' => (int) $row['user_id'],
            'username' => $row['username'],
            'email' => $row['email'],
            'createTime' => $row['create_time'],
        ];
    }, $stmt->fetchAll());

    send_json(['users' => $users]);
}
