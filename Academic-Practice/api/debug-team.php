<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$user = listening_require_user();
$userId = (int)$user['user_id'];

echo "<h1>Debug: User ID = " . $userId . "</h1>";

try {
    $pdo = listening_db();
    
    echo "<h2>Checking challenge_team_members table...</h2>";
    $stmt = $pdo->prepare("
        SELECT *
        FROM challenge_team_members
        WHERE user_id = ?
    ");
    $stmt->execute([$userId]);
    $results = $stmt->fetchAll();
    
    if (count($results) === 0) {
        echo "<p style='color: red;'>No records found for this user!</p>";
    } else {
        echo "<table border='1' style='margin: 10px 0;'>";
        echo "<tr><th>team_id</th><th>user_id</th><th>member_role</th><th>membership_status</th></tr>";
        foreach ($results as $row) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars((string)$row['team_id']) . "</td>";
            echo "<td>" . htmlspecialchars((string)$row['user_id']) . "</td>";
            echo "<td>" . htmlspecialchars((string)$row['member_role']) . "</td>";
            echo "<td>" . htmlspecialchars((string)$row['membership_status']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<h2>Checking score_records table...</h2>";
    $stmt = $pdo->prepare("
        SELECT *
        FROM score_records
        WHERE user_id = ?
    ");
    $stmt->execute([$userId]);
    $results = $stmt->fetchAll();
    
    if (count($results) === 0) {
        echo "<p>No weekly match records found.</p>";
    } else {
        echo "<table border='1' style='margin: 10px 0;'>";
        echo "<tr><th>record_id</th><th>user_id</th><th>rule_id</th><th>score</th><th>record_time</th></tr>";
        foreach ($results as $row) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars((string)$row['record_id']) . "</td>";
            echo "<td>" . htmlspecialchars((string)$row['user_id']) . "</td>";
            echo "<td>" . htmlspecialchars((string)$row['rule_id']) . "</td>";
            echo "<td>" . htmlspecialchars((string)$row['score']) . "</td>";
            echo "<td>" . htmlspecialchars((string)$row['record_time']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
