<?php
declare(strict_types=1);

const LISTENING_DB_HOST = '127.0.0.1';
const LISTENING_DB_PORT = 3306;
const LISTENING_DB_NAME = 'my_test_schema';
const LISTENING_DB_USER = 'root';
const LISTENING_DB_PASSWORD = '123456';

function listening_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        LISTENING_DB_HOST,
        LISTENING_DB_PORT,
        LISTENING_DB_NAME
    );

    $pdo = new PDO($dsn, LISTENING_DB_USER, LISTENING_DB_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}
