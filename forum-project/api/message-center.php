<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/challenge-lib.php';

$user = forum_require_user();
$userId = (int)($user['user_id'] ?? 0);
$pdo = forum_db();

challenge_maintain_weekly_cycle($pdo);
forum_sync_message_center_notifications($pdo, $userId);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $input = forum_input();
    $category = trim((string)($input['category'] ?? ''));
    $noticeId = (int)($input['noticeId'] ?? 0);
    $noticeKind = trim((string)($input['noticeKind'] ?? 'system'));

    if ($category === 'notice' && $noticeId > 0 && $noticeKind === 'system') {
        $stmt = $pdo->prepare("
            INSERT INTO message_center_notice_reads (notice_id, user_id, read_at)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE read_at = VALUES(read_at)
        ");
        $stmt->execute([$noticeId, $userId]);
    } elseif ($category === 'notice' && $noticeId > 0 && $noticeKind === 'challenge') {
        $stmt = $pdo->prepare("
            UPDATE message_center_notifications
            SET is_read = 1, updated_at = NOW()
            WHERE notification_id = ?
              AND recipient_user_id = ?
              AND notification_type = 'challenge_reset'
        ");
        $stmt->execute([$noticeId, $userId]);
    } elseif ($category === 'replies') {
        $stmt = $pdo->prepare("
            UPDATE message_center_notifications
            SET is_read = 1, updated_at = NOW()
            WHERE recipient_user_id = ? AND notification_type = 'reply' AND is_read = 0
        ");
        $stmt->execute([$userId]);
    } elseif ($category === 'reactions') {
        $stmt = $pdo->prepare("
            UPDATE message_center_notifications
            SET is_read = 1, updated_at = NOW()
            WHERE recipient_user_id = ? AND notification_type IN ('like', 'favorite') AND is_read = 0
        ");
        $stmt->execute([$userId]);
    } else {
        forum_json(['ok' => false, 'message' => 'Unsupported category.'], 422);
    }

    forum_json(['ok' => true]);
}

$summaryOnly = (string)($_GET['summaryOnly'] ?? '') === '1';

$countStmt = $pdo->prepare("
    SELECT notification_type, SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) AS unread_count
    FROM message_center_notifications
    WHERE recipient_user_id = ?
    GROUP BY notification_type
");
$countStmt->execute([$userId]);

$replyUnread = 0;
$reactionUnread = 0;
$challengeUnread = 0;
foreach ($countStmt->fetchAll() as $row) {
    $type = (string)($row['notification_type'] ?? '');
    $count = (int)($row['unread_count'] ?? 0);
    if ($type === 'reply') {
        $replyUnread += $count;
    } elseif ($type === 'like' || $type === 'favorite') {
        $reactionUnread += $count;
    } elseif ($type === 'challenge_reset') {
        $challengeUnread += $count;
    }
}

$chatUnreadStmt = $pdo->prepare("
    SELECT COUNT(*) AS unread_count
    FROM chat_conversation_members ccm
    JOIN chat_messages cm
      ON cm.conversation_id = ccm.conversation_id
    WHERE ccm.user_id = ?
      AND cm.user_id <> ?
      AND cm.status = 'active'
      AND (
        ccm.last_read_at IS NULL
        OR cm.created_at > ccm.last_read_at
      )
");
$chatUnreadStmt->execute([$userId, $userId]);
$chatUnread = (int)($chatUnreadStmt->fetchColumn() ?: 0);

$noticeCountStmt = $pdo->prepare("
    SELECT COUNT(*) AS unread_count
    FROM message_center_system_notices n
    LEFT JOIN message_center_notice_reads r
      ON r.notice_id = n.notice_id
     AND r.user_id = ?
    WHERE n.status = 'active'
      AND r.notice_read_id IS NULL
");
$noticeCountStmt->execute([$userId]);
$noticeCount = (int)($noticeCountStmt->fetchColumn() ?: 0);

$summary = [
    'chatsUnread' => $chatUnread,
    'repliesUnread' => $replyUnread,
    'reactionsUnread' => $reactionUnread,
    'systemCount' => $noticeCount + $challengeUnread,
    'totalUnread' => $chatUnread + $replyUnread + $reactionUnread + $noticeCount + $challengeUnread,
];

if ($summaryOnly) {
    forum_json([
        'ok' => true,
        'summary' => $summary,
    ]);
}

$replyStmt = $pdo->prepare("
    SELECT
        n.notification_id,
        n.notification_type,
        n.post_id,
        n.comment_id,
        n.title,
        n.body_text,
        n.cta_label,
        n.cta_url,
        n.is_read,
        n.created_at,
        actor.username AS actor_name,
        fp.title AS post_title,
        fp.content_text AS post_content,
        fc.content_text AS comment_content
    FROM message_center_notifications n
    LEFT JOIN users actor ON actor.user_id = n.actor_user_id
    LEFT JOIN forum_posts fp ON fp.post_id = n.post_id
    LEFT JOIN forum_comments fc ON fc.comment_id = n.comment_id
    WHERE n.recipient_user_id = ? AND n.notification_type = 'reply'
    ORDER BY n.created_at DESC, n.notification_id DESC
");
$replyStmt->execute([$userId]);

$reactionStmt = $pdo->prepare("
    SELECT
        n.notification_id,
        n.notification_type,
        n.post_id,
        n.title,
        n.body_text,
        n.cta_label,
        n.cta_url,
        n.is_read,
        n.created_at,
        actor.username AS actor_name,
        fp.title AS post_title
    FROM message_center_notifications n
    LEFT JOIN users actor ON actor.user_id = n.actor_user_id
    LEFT JOIN forum_posts fp ON fp.post_id = n.post_id
    WHERE n.recipient_user_id = ? AND n.notification_type IN ('like', 'favorite')
    ORDER BY n.created_at DESC, n.notification_id DESC
");
$reactionStmt->execute([$userId]);

$noticesStmt = $pdo->prepare("
    SELECT n.notice_id, n.title, n.body_text, n.cta_label, n.cta_url, n.created_at,
           CASE WHEN r.notice_read_id IS NULL THEN 0 ELSE 1 END AS is_read
    FROM message_center_system_notices n
    LEFT JOIN message_center_notice_reads r
      ON r.notice_id = n.notice_id
     AND r.user_id = ?
    WHERE n.status = 'active'
    ORDER BY n.created_at DESC, n.notice_id DESC
");
$noticesStmt->execute([$userId]);

$challengeNoticeStmt = $pdo->prepare("
    SELECT
        notification_id,
        title,
        body_text,
        cta_label,
        cta_url,
        is_read,
        created_at
    FROM message_center_notifications
    WHERE recipient_user_id = ?
      AND notification_type = 'challenge_reset'
    ORDER BY created_at DESC, notification_id DESC
");
$challengeNoticeStmt->execute([$userId]);

$replies = array_map(static function(array $row): array {
    return [
        'id' => (int)$row['notification_id'],
        'type' => 'reply',
        'actor' => (string)($row['actor_name'] ?? 'Someone'),
        'title' => (string)($row['title'] ?? ''),
        'body' => (string)($row['body_text'] ?? ''),
        'postId' => (int)($row['post_id'] ?? 0),
        'postTitle' => (string)($row['post_title'] ?? ''),
        'postPreview' => forum_plain_text_preview((string)($row['post_content'] ?? ''), 86),
        'commentPreview' => forum_plain_text_preview((string)($row['comment_content'] ?? ''), 96),
        'ctaLabel' => (string)($row['cta_label'] ?? 'Reply'),
        'ctaUrl' => (string)($row['cta_url'] ?? ''),
        'isRead' => (bool)($row['is_read'] ?? false),
        'createdAt' => (string)($row['created_at'] ?? ''),
    ];
}, $replyStmt->fetchAll());

$reactions = array_map(static function(array $row): array {
    return [
        'id' => (int)$row['notification_id'],
        'type' => (string)($row['notification_type'] ?? 'like'),
        'actor' => (string)($row['actor_name'] ?? 'Someone'),
        'title' => (string)($row['title'] ?? ''),
        'body' => (string)($row['body_text'] ?? ''),
        'postId' => (int)($row['post_id'] ?? 0),
        'postTitle' => (string)($row['post_title'] ?? ''),
        'ctaLabel' => (string)($row['cta_label'] ?? 'View post'),
        'ctaUrl' => (string)($row['cta_url'] ?? ''),
        'isRead' => (bool)($row['is_read'] ?? false),
        'createdAt' => (string)($row['created_at'] ?? ''),
    ];
}, $reactionStmt->fetchAll());

$systemNotices = array_map(static function(array $row): array {
    return [
        'id' => (int)$row['notice_id'],
        'kind' => 'system',
        'title' => (string)($row['title'] ?? ''),
        'body' => (string)($row['body_text'] ?? ''),
        'ctaLabel' => (string)($row['cta_label'] ?? ''),
        'ctaUrl' => (string)($row['cta_url'] ?? ''),
        'isRead' => (bool)($row['is_read'] ?? false),
        'createdAt' => (string)($row['created_at'] ?? ''),
    ];
}, $noticesStmt->fetchAll());

$challengeNotices = array_map(static function(array $row): array {
    return [
        'id' => (int)$row['notification_id'],
        'kind' => 'challenge',
        'title' => (string)($row['title'] ?? ''),
        'body' => (string)($row['body_text'] ?? ''),
        'ctaLabel' => (string)($row['cta_label'] ?? ''),
        'ctaUrl' => (string)($row['cta_url'] ?? ''),
        'isRead' => (bool)($row['is_read'] ?? false),
        'createdAt' => (string)($row['created_at'] ?? ''),
    ];
}, $challengeNoticeStmt->fetchAll());

$notices = array_merge($challengeNotices, $systemNotices);
usort($notices, static function (array $a, array $b): int {
    return strcmp((string)($b['createdAt'] ?? ''), (string)($a['createdAt'] ?? ''));
});

forum_json([
    'ok' => true,
    'summary' => $summary,
    'replies' => $replies,
    'reactions' => $reactions,
    'notices' => $notices,
]);
