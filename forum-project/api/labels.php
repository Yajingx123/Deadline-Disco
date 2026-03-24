<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
forum_require_user();

$stmt = forum_db()->query("
    SELECT fl.label_id, fl.name, COUNT(fpl.post_label_id) AS usage_count
    FROM forum_labels fl
    LEFT JOIN forum_post_labels fpl ON fpl.label_id = fl.label_id
    GROUP BY fl.label_id, fl.name
    ORDER BY fl.name ASC
");

$labels = array_map(static function(array $row): array {
    return [
        'id' => (int)$row['label_id'],
        'name' => (string)$row['name'],
        'usageCount' => (int)($row['usage_count'] ?? 0),
    ];
}, $stmt->fetchAll());

forum_json([
    'ok' => true,
    'labels' => $labels,
    'currentUser' => forum_current_user(),
]);
