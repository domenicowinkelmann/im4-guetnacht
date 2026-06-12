<?php
/**
 * GET  /api/notifications.php  — fetch notifications
 * POST /api/notifications.php  — mark all as read
 * Header: Authorization: Bearer <token>
 */

require_once __DIR__ . '/../utils/bootstrap.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../models/SleepModel.php';

$user = requireAuth();
$pdo  = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Mark all read
    $pdo->prepare(
        'UPDATE notifications n
         JOIN babies b ON b.id = n.baby_id
         SET n.is_read = 1
         WHERE b.user_id = :uid'
    )->execute([':uid' => $user['id']]);

    jsonOk(['message' => 'All notifications marked as read.']);
}

requireMethod('GET');

// ── ETL: Extract ──────────────────────────────────────────────────────────────
$notifications = SleepModel::getNotifications($pdo, $user['id'], 20);

// ── ETL: Transform ────────────────────────────────────────────────────────────
$formatted = array_map(function ($n) {
    $ts  = strtotime($n['created_at']);
    $now = time();
    $diff = $now - $ts;

    $timeAgo = match(true) {
        $diff < 60      => 'gerade eben',
        $diff < 3600    => 'vor ' . intdiv($diff, 60) . 'min',
        $diff < 86400   => 'vor ' . intdiv($diff, 3600) . 'h',
        default         => 'vor ' . intdiv($diff, 86400) . 'T',
    };

    return [
        'id'        => $n['id'],
        'title'     => $n['title'],
        'body'      => $n['body'],
        'icon_type' => $n['icon_type'],
        'is_read'   => (bool) $n['is_read'],
        'time_ago'  => $timeAgo,
    ];
}, $notifications);

// ── ETL: Load (return) ────────────────────────────────────────────────────────
jsonOk(['notifications' => $formatted]);
